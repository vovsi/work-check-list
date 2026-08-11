<?php

declare(strict_types=1);

namespace App;

use Throwable;

/** Оркестрирует подтягивание заголовка/описания задачи из Jira и сохранение их в БД */
final class JiraSyncService
{
    public function __construct(
        private readonly JiraClient $client,
        private readonly TaskRepository $tasks,
    ) {
    }

    /**
     * Интеграция опциональна (нужен config/params.ini) — если её не настроили, возвращает null,
     * а не бросает исключение. Так вызывающий код (TaskService) не обязан знать о Config.
     */
    public static function createFromConfig(TaskRepository $tasks): ?self
    {
        try {
            $config = Config::atlassian();
        } catch (Throwable $e) {
            return null;
        }

        return new self(
            new JiraClient($config['base_url'], $config['email'], $config['api_token']),
            $tasks
        );
    }

    public function sync(array $task): array
    {
        $issue = $this->client->fetchIssue($task['task_id']);
        $this->tasks->updateJiraData((int) $task['id'], $issue['title'], $issue['description']);

        return $this->tasks->findById((int) $task['id']);
    }
}
