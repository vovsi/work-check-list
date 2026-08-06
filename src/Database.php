<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Обёртка над подключением к SQLite. Реализует единую точку доступа к БД (Singleton),
 * при первом обращении создаёт таблицы и сеет базовый чек-лист.
 */
final class Database
{
    private static ?PDO $connection = null;

    /** Пункты чек-листа в порядке отображения (индекс + 1 = id в таблице checklist) */
    private const CHECKLIST_ITEMS = [
        'Story Points указано',
        'Статус сменен на Doing',
        'Создать ветку в Git',
        'PR создан',
        'PR проверен Claude',
        'Заполнить описание PR (ссылка, ревьюеры)',
        'Оставить коммент в Jira',
        'Оставить описание в Jira',
        'Время в Jira затрекано',
        'Отправить PR в ЛС',
    ];

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $path = dirname(__DIR__) . '/storage/app.sqlite';
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA foreign_keys = ON');

            self::$connection = $pdo;
            self::migrate($pdo);
        }

        return self::$connection;
    }

    private static function migrate(PDO $pdo): void
    {
        $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
        $pdo->exec($schema);

        self::seedChecklist($pdo);
    }

    /**
     * Записывает канонические тексты пунктов по фиксированным id (upsert).
     * Так правки формулировок в CHECKLIST_ITEMS применяются и к уже существующим БД.
     */
    private static function seedChecklist(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO checklist (id, title) VALUES (:id, :title)
             ON CONFLICT (id) DO UPDATE SET title = excluded.title'
        );

        foreach (self::CHECKLIST_ITEMS as $index => $title) {
            $stmt->execute(['id' => $index + 1, 'title' => $title]);
        }
    }
}
