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
            new JiraClient(
                $config['base_url'],
                $config['email'],
                $config['api_token'],
                Config::atlassianStoryPointsField(),
                Config::atlassianPullRequestStatus(),
                Config::atlassianDoingStatus()
            ),
            $tasks
        );
    }

    public function sync(array $task): array
    {
        $issue = $this->client->fetchIssue($task['task_id']);
        $this->tasks->updateJiraData((int) $task['id'], $issue['title'], $issue['description']);

        return $this->tasks->findById((int) $task['id']);
    }

    public function updateStoryPoints(array $task, int $storyPoints): void
    {
        $this->client->updateStoryPoints($task['task_id'], $storyPoints);
    }

    public function transitionToPullRequest(array $task): void
    {
        $this->client->transitionToPullRequest($task['task_id']);
    }

    public function transitionToDoing(array $task): void
    {
        $this->client->transitionToDoing($task['task_id']);
    }

    public function getTimeSpentSeconds(array $task): int
    {
        return $this->client->fetchTimeSpentSeconds($task['task_id']);
    }

    /** Суммарно затреканное сегодня время по всем задачам — не привязано к конкретной задаче */
    public function getTodayTimeSpentSeconds(): int
    {
        return $this->client->fetchTodayTimeSpentSeconds();
    }

    public function addWorklog(array $task, int $seconds): void
    {
        $this->client->addWorklog($task['task_id'], $seconds);
    }
}
