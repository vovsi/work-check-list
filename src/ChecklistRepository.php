<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий для работы с чек-листом и связью задача-пункт (task_checklist).
 */
final class ChecklistRepository
{
    /**
     * Пункты, которые обнуляются при повторном открытии уже существующей задачи.
     * Указаны через стабильный code, а не id/позицию — при добавлении, удалении или
     * переупорядочивании пунктов в Database::CHECKLIST_ITEMS этот список не сломается.
     */
    private const RESETABLE_ON_REOPEN_CODES = [
        'pull_request',
        'claude_review',
        'pr_description',
        'jira_comment',
        'jira_description',
        'time_tracking',
        'send_pr',
    ];

    /** Код пункта «Создать ветку в Git» — считается выполненным, если у задачи уже есть git_branch */
    private const GIT_BRANCH_CODE = 'git_branch';

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Создаёт недостающие строки task_checklist для задачи (все пункты, is_done = 0).
     */
    public function ensureRowsForTask(int $taskId): void
    {
        $checklistIds = $this->db->query('SELECT id FROM checklist ORDER BY sort_order')
            ->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $this->db->prepare(
            'INSERT OR IGNORE INTO task_checklist (task_id, checklist_id, is_done) VALUES (:task_id, :checklist_id, 0)'
        );

        foreach ($checklistIds as $checklistId) {
            $stmt->execute(['task_id' => $taskId, 'checklist_id' => $checklistId]);
        }
    }

    /**
     * Возвращает пункты чек-листа с отметкой выполнения для конкретной задачи.
     * Пункт «Создать ветку в Git» считается выполненным, если у задачи уже сохранена
     * git_branch — независимо от is_done (например, если ветка была задана раньше).
     */
    public function getStatusesForTask(int $taskId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.code, c.title,
                    CASE
                        WHEN c.code = :git_branch_code AND t.git_branch IS NOT NULL THEN 1
                        ELSE tc.is_done
                    END AS is_done
             FROM checklist c
             JOIN task_checklist tc ON tc.checklist_id = c.id
             JOIN tasks t ON t.id = tc.task_id
             WHERE tc.task_id = :task_id
             ORDER BY c.sort_order'
        );
        $stmt->execute(['task_id' => $taskId, 'git_branch_code' => self::GIT_BRANCH_CODE]);

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'code' => $row['code'],
                'title' => $row['title'],
                'is_done' => (bool) $row['is_done'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function setDone(int $taskId, int $checklistId, bool $done): void
    {
        $stmt = $this->db->prepare(
            'UPDATE task_checklist SET is_done = :is_done WHERE task_id = :task_id AND checklist_id = :checklist_id'
        );
        $stmt->execute([
            'is_done' => $done ? 1 : 0,
            'task_id' => $taskId,
            'checklist_id' => $checklistId,
        ]);
    }

    /**
     * Сбрасывает пункты из RESETABLE_ON_REOPEN_CODES при повторном открытии уже существующей задачи.
     */
    public function resetOnReopen(int $taskId): void
    {
        $this->resetItemsByCode($taskId, self::RESETABLE_ON_REOPEN_CODES);
    }

    /**
     * Сбрасывает все пункты чек-листа (кнопка «Завершить задачу»).
     */
    public function resetAll(int $taskId): void
    {
        $stmt = $this->db->prepare('UPDATE task_checklist SET is_done = 0 WHERE task_id = :task_id');
        $stmt->execute(['task_id' => $taskId]);
    }

    private function resetItemsByCode(int $taskId, array $codes): void
    {
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $stmt = $this->db->prepare(
            "UPDATE task_checklist SET is_done = 0
             WHERE task_id = ? AND checklist_id IN (SELECT id FROM checklist WHERE code IN ($placeholders))"
        );
        $stmt->execute([$taskId, ...$codes]);
    }
}
