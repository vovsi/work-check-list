<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use RuntimeException;

/** Читает и обновляет данные задачи в Atlassian Jira REST API v2 (Basic Auth email + API token) */
final class JiraClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $email,
        private readonly string $apiToken,
        private readonly string $storyPointsFieldId = 'customfield_10016',
        private readonly string $pullRequestStatusName = 'Pull request',
        private readonly string $doingStatusName = 'Doing',
    ) {
    }

    /**
     * @return array{title: string, description: ?string, story_points_set: bool}
     */
    public function fetchIssue(string $taskId): array
    {
        // expand=renderedFields отдаёт description готовым HTML вместо ADF/wiki-разметки —
        // так не нужно разбирать формат описания отдельно для Jira Cloud и Server.
        $data = $this->request(
            'GET',
            '/rest/api/2/issue/' . rawurlencode($taskId)
                . '?fields=summary,description,' . rawurlencode($this->storyPointsFieldId)
                . '&expand=renderedFields',
            null,
            "для задачи {$taskId}"
        );

        return [
            'title' => (string) ($data['fields']['summary'] ?? ''),
            'description' => $data['renderedFields']['description'] ?? null,
            'story_points_set' => ($data['fields'][$this->storyPointsFieldId] ?? null) !== null,
        ];
    }

    /**
     * Есть ли такая задача в Jira. 404 — задачи нет (ошибкой не считается, это ответ
     * «не найдена»); любой другой сбой (сеть, 401/403, 5xx) пробрасывается исключением —
     * это проблема доступа/сервиса, а не приговор ссылке.
     */
    public function issueExists(string $taskId): bool
    {
        try {
            $this->request(
                'GET',
                '/rest/api/2/issue/' . rawurlencode($taskId) . '?fields=summary',
                null,
                "при проверке существования задачи {$taskId}"
            );
        } catch (RuntimeException $e) {
            if ($e->getCode() === 404) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    public function updateStoryPoints(string $taskId, int $storyPoints): void
    {
        $this->request(
            'PUT',
            '/rest/api/2/issue/' . rawurlencode($taskId),
            ['fields' => [$this->storyPointsFieldId => $storyPoints]],
            "при обновлении Story Points для задачи {$taskId}"
        );
    }

    /** Переводит задачу в статус $pullRequestStatusName (например «Pull request») через Jira transitions API */
    public function transitionToPullRequest(string $taskId): void
    {
        $transitionId = $this->findTransitionId($taskId, $this->pullRequestStatusName);
        if ($transitionId === null) {
            throw new RuntimeException(
                "В Jira не найден переход в статус «{$this->pullRequestStatusName}» для задачи {$taskId}"
            );
        }

        $this->request(
            'POST',
            '/rest/api/2/issue/' . rawurlencode($taskId) . '/transitions',
            ['transition' => ['id' => $transitionId]],
            "при переводе статуса задачи {$taskId}"
        );
    }

    /** Переводит задачу в статус $doingStatusName (например «Doing») через Jira transitions API */
    public function transitionToDoing(string $taskId): void
    {
        $transitionId = $this->findTransitionId($taskId, $this->doingStatusName);
        if ($transitionId === null) {
            throw new RuntimeException(
                "В Jira не найден переход в статус «{$this->doingStatusName}» для задачи {$taskId}"
            );
        }

        $this->request(
            'POST',
            '/rest/api/2/issue/' . rawurlencode($taskId) . '/transitions',
            ['transition' => ['id' => $transitionId]],
            "при переводе статуса задачи {$taskId}"
        );
    }

    /** Ищет id перехода по названию целевого статуса среди доступных для задачи переходов */
    private function findTransitionId(string $taskId, string $statusName): ?string
    {
        $data = $this->request(
            'GET',
            '/rest/api/2/issue/' . rawurlencode($taskId) . '/transitions',
            null,
            "при получении переходов задачи {$taskId}"
        );
        $transitions = is_array($data['transitions'] ?? null) ? $data['transitions'] : [];

        foreach ($transitions as $transition) {
            // Сравниваем с названием целевого статуса (transition.to.name), а не с названием
            // самого перехода (transition.name) — это название кнопки-действия в workflow
            // и может не совпадать с именем статуса, в который она ведёт.
            $targetStatusName = (string) ($transition['to']['name'] ?? $transition['name'] ?? '');
            if ($targetStatusName !== '' && strcasecmp($targetStatusName, $statusName) === 0) {
                return (string) $transition['id'];
            }
        }

        return null;
    }

    /** Суммарное время (в секундах), уже затреканное в задаче (агрегат по всем worklog) */
    public function fetchTimeSpentSeconds(string $taskId): int
    {
        $data = $this->request(
            'GET',
            '/rest/api/2/issue/' . rawurlencode($taskId) . '?fields=timespent',
            null,
            "при получении затреканного времени задачи {$taskId}"
        );

        return (int) ($data['fields']['timespent'] ?? 0);
    }

    /**
     * Сколько времени текущий пользователь суммарно затрекал сегодня во все задачи Jira.
     * «Сегодня» считается в таймзоне пользователя Jira (её же имеет в виду startOfDay() в JQL),
     * а не сервера — контейнер живёт в UTC и на границах суток давал бы сдвиг.
     */
    public function fetchTodayTimeSpentSeconds(): int
    {
        $total = 0;
        foreach ($this->fetchTodayTimeSpentBreakdown() as $entry) {
            $total += $entry['seconds'];
        }

        return $total;
    }

    /**
     * Сегодняшнее затреканное время текущего пользователя, разбитое по задачам.
     *
     * @return list<array{task_id: string, title: string, status: string, link: string, seconds: int}>
     */
    public function fetchTodayTimeSpentBreakdown(): array
    {
        $me = $this->request('GET', '/rest/api/2/myself', null, 'при получении текущего пользователя');
        $accountId = (string) ($me['accountId'] ?? '');

        try {
            $timezone = new DateTimeZone((string) ($me['timeZone'] ?? 'UTC'));
        } catch (Exception $e) {
            $timezone = new DateTimeZone('UTC');
        }
        $dayStart = (new DateTimeImmutable('today', $timezone))->getTimestamp();
        $dayEnd = (new DateTimeImmutable('tomorrow', $timezone))->getTimestamp();

        // На Jira Cloud старый /rest/api/2/search удалён (HTTP 410) — актуальный поиск это /search/jql.
        // За одни сутки задач с ворклогом заведомо меньше 50, поэтому пагинация не нужна.
        $jql = rawurlencode('worklogAuthor = currentUser() AND worklogDate >= startOfDay()');
        $search = $this->request(
            'GET',
            "/rest/api/2/search/jql?jql={$jql}&fields=key,summary,status&maxResults=50",
            null,
            'при поиске задач с сегодняшним ворклогом'
        );

        $result = [];
        foreach (($search['issues'] ?? []) as $issue) {
            $key = (string) ($issue['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $data = $this->request(
                'GET',
                '/rest/api/2/issue/' . rawurlencode($key) . '/worklog?startedAfter=' . ($dayStart * 1000),
                null,
                "при получении ворклогов задачи {$key}"
            );

            $seconds = 0;
            foreach (($data['worklogs'] ?? []) as $worklog) {
                // В задаче есть ворклоги и других участников — считаем только свои
                if ((string) ($worklog['author']['accountId'] ?? '') !== $accountId) {
                    continue;
                }
                // startedAfter отсекает только нижнюю границу суток, верхнюю проверяем сами
                $started = strtotime((string) ($worklog['started'] ?? ''));
                if ($started === false || $started < $dayStart || $started >= $dayEnd) {
                    continue;
                }
                $seconds += (int) ($worklog['timeSpentSeconds'] ?? 0);
            }

            if ($seconds > 0) {
                $result[] = [
                    'task_id' => $key,
                    'title' => (string) ($issue['fields']['summary'] ?? ''),
                    'status' => (string) ($issue['fields']['status']['name'] ?? ''),
                    'link' => rtrim($this->baseUrl, '/') . '/browse/' . $key,
                    'seconds' => $seconds,
                ];
            }
        }

        return $result;
    }

    /** Добавляет worklog (доп. время сверх уже затреканного) к задаче */
    public function addWorklog(string $taskId, int $seconds): void
    {
        $this->request(
            'POST',
            '/rest/api/2/issue/' . rawurlencode($taskId) . '/worklog',
            ['timeSpentSeconds' => $seconds],
            "при добавлении времени в задачу {$taskId}"
        );
    }

    /** Общий curl-вызов Jira REST API — авторизация, таймаут и разбор ошибок в одном месте */
    private function request(string $method, string $path, ?array $body, string $errorContext): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode("{$this->email}:{$this->apiToken}"),
            ],
            CURLOPT_TIMEOUT => 20,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Не удалось обратиться к Jira ({$this->baseUrl}): {$error}");
        }
        if ($status < 200 || $status >= 300) {
            // Код исключения = HTTP-статус: по нему issueExists() отличает «задачи нет» (404)
            // от недоступности Jira или проблем с доступом
            throw new RuntimeException("Jira ответила с ошибкой (HTTP {$status}) {$errorContext}", (int) $status);
        }

        if ($response === '') {
            return [];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Не удалось разобрать ответ Jira');
        }

        return $data;
    }
}
