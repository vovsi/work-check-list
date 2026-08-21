<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

/**
 * Показатели дашборда на экране ввода ссылки. Service поверх JiraSyncService: знает
 * бизнес-правила показателей (что считать «зависшей» задачей), но не знает про HTTP.
 *
 * Новый числовой показатель добавляется методом-расчётом + строкой в metrics(); эндпоинт
 * api/dashboard.php при этом не меняется.
 */
final class DashboardService
{
    /**
     * @param int       $stalePullRequestHours Сколько часов в статусе Pull request считаем
     *                                         нормой — дольше задача «зависла»
     * @param list<int> $nonWorkingWeekdays    Дни недели (ISO-8601: 1 = Пн … 7 = Вс), которые
     *                                         не идут в счёт часов «зависания»
     */
    public function __construct(
        private readonly JiraSyncService $jiraSync,
        private readonly string $pullRequestStatus,
        private readonly int $stalePullRequestHours = Config::STALE_PULL_REQUEST_HOURS_DEFAULT,
        private readonly array $nonWorkingWeekdays = [],
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

        return new self(
            $jiraSync,
            Config::atlassianPullRequestStatus(),
            Config::stalePullRequestHours(),
            Config::nonWorkingWeekdays()
        );
    }

    /**
     * @return array{stale_pull_requests: array{count: int, hours: int, status: string, non_working_weekdays: list<int>, tasks: list<array{task_id: string, title: string, status: string, link: string}>}}
     */
    public function metrics(): array
    {
        return [
            'stale_pull_requests' => $this->stalePullRequests(),
        ];
    }

    /**
     * @return array{count: int, hours: int, status: string, non_working_weekdays: list<int>, tasks: list<array{task_id: string, title: string, status: string, link: string}>}
     */
    private function stalePullRequests(): array
    {
        // Таймзона пользователя Jira, а не сервера: в ней JQL трактует дату порога, а
        // контейнер живёт в UTC и на границах суток давал бы сдвиг
        $now = new DateTimeImmutable('now', $this->jiraSync->getUserTimeZone());

        $tasks = $this->jiraSync->getIssuesStuckInStatus(
            $this->pullRequestStatus,
            $this->staleCutoff($now)
        );

        return [
            'count' => count($tasks),
            'hours' => $this->stalePullRequestHours,
            'status' => $this->pullRequestStatus,
            'non_working_weekdays' => $this->nonWorkingWeekdays,
            'tasks' => $tasks,
        ];
    }

    /**
     * Момент, раньше которого попадание в статус считается «зависанием»: отсчитываем
     * [dashboard].stale_pull_request_hours назад от «сейчас», пропуская нерабочие дни целиком
     * ([worktime].non_working_days). Поэтому при пороге 24 ч перевод в Pull request в пятницу
     * 17:00 станет «зависшим» только в понедельник 17:00, а не в субботу.
     *
     * Шаг — час, а не сразу вся разница: сутки в нерабочий день должны вычитаться из
     * календаря, но не из отсчёта часов, и без пошагового прохода это не выразить.
     * Бесконечного цикла быть не может — Config::nonWorkingWeekdays() не возвращает все
     * семь дней.
     */
    private function staleCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        $cursor = $now;
        $remainingHours = $this->stalePullRequestHours;

        while ($remainingHours > 0) {
            $cursor = $cursor->modify('-1 hour');
            // Час относим к дню своего начала: шаг из понедельника 00:00 попадает в воскресенье
            if (!in_array((int) $cursor->format('N'), $this->nonWorkingWeekdays, true)) {
                $remainingHours--;
            }
        }

        return $cursor;
    }
}
