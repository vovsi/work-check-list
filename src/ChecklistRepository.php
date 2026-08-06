<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий для работы с чек-листом и связью задача-пункт (task_checklist).
 */
final class ChecklistRepository
{
    /** Пункты 3-9 обнуляются, если задача уже существовала в БД */
    private const RESETABLE_ON_REOPEN = [3, 4, 5, 6, 7, 8, 9];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Создаёт недостающие строки task_checklist для задачи (все пункты, is_done = 0).
     */
    public function ensureRowsForTask(int $taskId): void
    {
        $checklistIds = $this->db->query('SELECT id FROM checklist ORDER BY id')
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
     */
    public function getStatusesForTask(int $taskId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.title, tc.is_done
             FROM checklist c
             JOIN task_checklist tc ON tc.checklist_id = c.id
             WHERE tc.task_id = :task_id
             ORDER BY c.id'
        );
        $stmt->execute(['task_id' => $taskId]);

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
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
     * Сбрасывает пункты 3-9 при повторном открытии уже существующей задачи.
     */
    public function resetOnReopen(int $taskId): void
    {
        $this->resetItems($taskId, self::RESETABLE_ON_REOPEN);
    }

    /**
     * Сбрасывает все пункты чек-листа (кнопка «Завершить задачу»).
     */
    public function resetAll(int $taskId): void
    {
        $stmt = $this->db->prepare('UPDATE task_checklist SET is_done = 0 WHERE task_id = :task_id');
        $stmt->execute(['task_id' => $taskId]);
    }

    private function resetItems(int $taskId, array $checklistIds): void
    {
        $placeholders = implode(',', array_fill(0, count($checklistIds), '?'));
        $stmt = $this->db->prepare(
            "UPDATE task_checklist SET is_done = 0 WHERE task_id = ? AND checklist_id IN ($placeholders)"
        );
        $stmt->execute([$taskId, ...$checklistIds]);
    }
}
