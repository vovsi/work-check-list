<?php

declare(strict_types=1);

namespace App;

/**
 * Показатели дашборда на экране ввода ссылки. Service поверх JiraSyncService: знает
 * бизнес-правила показателей (что считать «зависшей» задачей), но не знает про HTTP.
 *
 * Новый числовой показатель добавляется методом-расчётом + строкой в metrics(); эндпоинт
 * api/dashboard.php при этом не меняется.
 */
final class DashboardService
{
    /** Сколько часов в статусе Pull request считаем нормой — дольше задача «зависла» */
    public const STALE_PULL_REQUEST_HOURS = 24;

    public function __construct(
        private readonly JiraSyncService $jiraSync,
        private readonly string $pullRequestStatus,
    ) {
    }

    /**
     * Интеграция с Jira опциональна (как и у JiraSyncService) — без настроенного
     * config/params.ini возвращает null, а не бросает исключение.
     */
    public static function createFromConfig(TaskRepository $tasks): ?self
    {
        $jiraSync = JiraSyncService::createFromConfig($tasks);
        if ($jiraSync === null) {
            return null;
        }

        return new self($jiraSync, Config::atlassianPullRequestStatus());
    }

    /**
     * @return array{stale_pull_requests: array{count: int, hours: int, status: string, tasks: list<array{task_id: string, title: string, status: string, link: string}>}}
     */
    public function metrics(): array
    {
        return [
            'stale_pull_requests' => $this->stalePullRequests(),
        ];
    }

    /**
     * @return array{count: int, hours: int, status: string, tasks: list<array{task_id: string, title: string, status: string, link: string}>}
     */
    private function stalePullRequests(): array
    {
        $tasks = $this->jiraSync->getIssuesStuckInStatus(
            $this->pullRequestStatus,
            self::STALE_PULL_REQUEST_HOURS
        );

        return [
            'count' => count($tasks),
            'hours' => self::STALE_PULL_REQUEST_HOURS,
            'status' => $this->pullRequestStatus,
            'tasks' => $tasks,
        ];
    }
}
