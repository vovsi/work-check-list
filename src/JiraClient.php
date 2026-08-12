<?php

declare(strict_types=1);

namespace App;

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
    ) {
    }

    /**
     * @return array{title: string, description: ?string}
     */
    public function fetchIssue(string $taskId): array
    {
        // expand=renderedFields отдаёт description готовым HTML вместо ADF/wiki-разметки —
        // так не нужно разбирать формат описания отдельно для Jira Cloud и Server.
        $data = $this->request(
            'GET',
            '/rest/api/2/issue/' . rawurlencode($taskId) . '?fields=summary,description&expand=renderedFields',
            null,
            "для задачи {$taskId}"
        );

        return [
            'title' => (string) ($data['fields']['summary'] ?? ''),
            'description' => $data['renderedFields']['description'] ?? null,
        ];
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
            if (isset($transition['name']) && strcasecmp((string) $transition['name'], $statusName) === 0) {
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
            throw new RuntimeException("Jira ответила с ошибкой (HTTP {$status}) {$errorContext}");
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
