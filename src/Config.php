<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/** Читает config/params.ini (не в git — см. config/params.ini.example) */
final class Config
{
    /** Значения [worktime] по умолчанию — используются и при отсутствии config/params.ini (см. public/index.php) */
    public const WORK_TIME_DEFAULTS = [
        'start' => '09:00',
        'end' => '18:00',
        'daily_hours' => 8.0,
        'lunch_start' => '12:00',
        'lunch_end' => '13:00',
    ];

    /** Значения [salary] по умолчанию (см. salaryHourlyRateUsd()) */
    public const SALARY_DEFAULTS = [
        'monthly_usd' => 1500.0,
        'working_days_per_month' => 21.0,
    ];

    private static ?array $data = null;

    public static function llm(): array
    {
        $data = self::load();

        if (!isset($data['llm']['host'], $data['llm']['model'])) {
            throw new RuntimeException(
                'В config/params.ini не заданы llm.host и llm.model — скопируйте config/params.ini.example'
            );
        }

        return $data['llm'];
    }

    public static function atlassian(): array
    {
        $data = self::load();

        if (!isset($data['atlassian']['base_url'], $data['atlassian']['email'], $data['atlassian']['api_token'])) {
            throw new RuntimeException(
                'В config/params.ini не заданы atlassian.base_url, atlassian.email и atlassian.api_token — скопируйте config/params.ini.example'
            );
        }

        return $data['atlassian'];
    }

    /**
     * ID кастомного поля Story Points в Jira — отличается между инстансами Jira Cloud/Server,
     * найти можно в URL при редактировании поля в настройках или через
     * GET /rest/api/2/field. Не задано в конфиге — используется значение по умолчанию,
     * подходящее для большинства инстансов Jira Cloud.
     */
    public static function atlassianStoryPointsField(): string
    {
        $data = self::load();
        $field = trim((string) ($data['atlassian']['story_points_field'] ?? ''));

        return $field !== '' ? $field : 'customfield_10016';
    }

    /** Название статуса Jira для перехода по пункту «Перевести задачу в Pull Request». Не задано в конфиге — по умолчанию "Pull request" */
    public static function atlassianPullRequestStatus(): string
    {
        $data = self::load();
        $status = trim((string) ($data['atlassian']['pull_request_status'] ?? ''));

        return $status !== '' ? $status : 'Pull request';
    }

    /** Название статуса Jira для перехода по пункту «Перевести в статус Doing». Не задано в конфиге — по умолчанию "Doing" */
    public static function atlassianDoingStatus(): string
    {
        $data = self::load();
        $status = trim((string) ($data['atlassian']['doing_status'] ?? ''));

        return $status !== '' ? $status : 'Doing';
    }

    /**
     * Рабочий день и норма часов для ползунка быстрого трека времени: границы ползунка
     * (start/end), норма, по которой затреканное за день время подсвечивается оранжевым
     * (меньше нормы) или зелёным, и обеденный перерыв (lunch_start/lunch_end) — он
     * подсвечивается на ползунке и не идёт в трекинг. Некорректные значения (не HH:MM,
     * конец не позже начала, норма ≤ 0) молча заменяются на WORK_TIME_DEFAULTS — иначе
     * ползунок оказался бы мёртвым. Обед, не влезающий в рабочий день, просто отключается
     * (пустые строки) — это не повод ломать сам ползунок.
     */
    public static function workTime(): array
    {
        $section = self::load()['worktime'] ?? [];

        $start = self::normalizeClock((string) ($section['start'] ?? ''), self::WORK_TIME_DEFAULTS['start']);
        $end = self::normalizeClock((string) ($section['end'] ?? ''), self::WORK_TIME_DEFAULTS['end']);
        // HH:MM с ведущим нулём сравнимы как строки
        if ($end <= $start) {
            $start = self::WORK_TIME_DEFAULTS['start'];
            $end = self::WORK_TIME_DEFAULTS['end'];
        }

        $dailyHours = (float) ($section['daily_hours'] ?? 0);

        $lunchStart = self::normalizeClock(
            (string) ($section['lunch_start'] ?? ''),
            self::WORK_TIME_DEFAULTS['lunch_start']
        );
        $lunchEnd = self::normalizeClock(
            (string) ($section['lunch_end'] ?? ''),
            self::WORK_TIME_DEFAULTS['lunch_end']
        );
        if ($lunchEnd <= $lunchStart || $lunchStart < $start || $lunchEnd > $end) {
            $lunchStart = '';
            $lunchEnd = '';
        }

        return [
            'start' => $start,
            'end' => $end,
            'daily_hours' => $dailyHours > 0 ? $dailyHours : self::WORK_TIME_DEFAULTS['daily_hours'],
            'lunch_start' => $lunchStart,
            'lunch_end' => $lunchEnd,
        ];
    }

    /**
     * Часовая ставка в USD для расчёта заработка за день в модалке поздравления
     * (EarningsService, показывается при достижении [worktime].daily_hours) — оклад в месяц
     * делится на условное число рабочих часов в месяце. Некорректные значения (≤ 0) молча
     * заменяются на SALARY_DEFAULTS, как и у workTime().
     */
    public static function salaryHourlyRateUsd(): float
    {
        $section = self::load()['salary'] ?? [];

        $monthlyUsd = (float) ($section['monthly_usd'] ?? 0);
        if ($monthlyUsd <= 0) {
            $monthlyUsd = self::SALARY_DEFAULTS['monthly_usd'];
        }

        $workingDays = (float) ($section['working_days_per_month'] ?? 0);
        if ($workingDays <= 0) {
            $workingDays = self::SALARY_DEFAULTS['working_days_per_month'];
        }

        return $monthlyUsd / ($workingDays * self::workTime()['daily_hours']);
    }

    private static function normalizeClock(string $value, string $fallback): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', trim($value)) === 1 ? trim($value) : $fallback;
    }

    /** Ники ревьюверов для команды `gh pr create --reviewer`. Не задано в конфиге — пустой список (флаг просто не добавляется в команду) */
    public static function githubReviewers(): array
    {
        $data = self::load();
        $reviewers = trim((string) ($data['github']['reviewers'] ?? ''));

        if ($reviewers === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $reviewers))));
    }

    private static function load(): array
    {
        if (self::$data === null) {
            $path = dirname(__DIR__) . '/config/params.ini';
            if (!is_file($path)) {
                throw new RuntimeException(
                    'Не найден config/params.ini — скопируйте config/params.ini.example и заполните его'
                );
            }

            $parsed = parse_ini_file($path, true);
            self::$data = $parsed === false ? [] : $parsed;
        }

        return self::$data;
    }
}
