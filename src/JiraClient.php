<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/** Читает данные задачи из Atlassian Jira REST API v2 (Basic Auth email + API token) */
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
        $url = rtrim($this->baseUrl, '/') . '/rest/api/2/issue/' . rawurlencode($taskId)
            . '?fields=summary,description&expand=renderedFields';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode("{$this->email}:{$this->apiToken}"),
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Не удалось обратиться к Jira ({$this->baseUrl}): {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Jira ответила с ошибкой (HTTP {$status}) для задачи {$taskId}");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Не удалось разобрать ответ Jira');
        }

        return [
            'title' => (string) ($data['fields']['summary'] ?? ''),
            'description' => $data['renderedFields']['description'] ?? null,
        ];
    }

    public function updateStoryPoints(string $taskId, int $storyPoints): void
    {
        $url = rtrim($this->baseUrl, '/') . '/rest/api/2/issue/' . rawurlencode($taskId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode(['fields' => [$this->storyPointsFieldId => $storyPoints]]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode("{$this->email}:{$this->apiToken}"),
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Не удалось обратиться к Jira ({$this->baseUrl}): {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Jira ответила с ошибкой (HTTP {$status}) при обновлении Story Points для задачи {$taskId}");
        }
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

        $url = rtrim($this->baseUrl, '/') . '/rest/api/2/issue/' . rawurlencode($taskId) . '/transitions';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode(['transition' => ['id' => $transitionId]]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode("{$this->email}:{$this->apiToken}"),
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Не удалось обратиться к Jira ({$this->baseUrl}): {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Jira ответила с ошибкой (HTTP {$status}) при переводе статуса задачи {$taskId}");
        }
    }

    /** Ищет id перехода по названию целевого статуса среди доступных для задачи переходов */
    private function findTransitionId(string $taskId, string $statusName): ?string
    {
        $url = rtrim($this->baseUrl, '/') . '/rest/api/2/issue/' . rawurlencode($taskId) . '/transitions';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode("{$this->email}:{$this->apiToken}"),
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Не удалось обратиться к Jira ({$this->baseUrl}): {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Jira ответила с ошибкой (HTTP {$status}) при получении переходов задачи {$taskId}");
        }

        $data = json_decode($response, true);
        $transitions = is_array($data['transitions'] ?? null) ? $data['transitions'] : [];

        foreach ($transitions as $transition) {
            if (isset($transition['name']) && strcasecmp((string) $transition['name'], $statusName) === 0) {
                return (string) $transition['id'];
            }
        }

        return null;
    }
}
