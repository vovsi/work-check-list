<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/** Читает config/params.ini (не в git — см. config/params.ini.example) */
final class Config
{
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
