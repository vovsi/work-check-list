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
     * Пункты, которые при любом сбросе чек-листа (повторное открытие задачи или кнопка
     * «Завершить задачу») принудительно отмечаются выполненными, а не обнуляются — это
     * метаданные уровня задачи, которые не имеют смысла переделывать в каждом новом цикле
     * работы над ней. Указаны через стабильный code, а не id/позицию — при добавлении,
     * удалении или переупорядочивании пунктов в Database::CHECKLIST_ITEMS список не
     * сломается, а новые пункты по умолчанию попадут в «обнуляемые».
     */
    private const ALWAYS_DONE_ON_RESET_CODES = ['story_points'];

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
     */
    public function getStatusesForTask(int $taskId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.code, c.title, tc.is_done
             FROM checklist c
             JOIN task_checklist tc ON tc.checklist_id = c.id
             WHERE tc.task_id = :task_id
             ORDER BY c.sort_order'
        );
        $stmt->execute(['task_id' => $taskId]);

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
     * Повторное открытие уже существующей задачи. По эффекту полностью идентично
     * «Завершить задачу» — см. resetChecklist().
     */
    public function resetOnReopen(int $taskId): void
    {
        $this->resetChecklist($taskId);
    }

    /**
     * Сбрасывает чек-лист (кнопка «Завершить задачу»). По эффекту полностью идентично
     * повторному открытию задачи — см. resetChecklist().
     */
    public function resetAll(int $taskId): void
    {
        $this->resetChecklist($taskId);
    }

    /**
     * Общая реализация сброса для resetOnReopen() и resetAll(): все пункты обнуляются,
     * кроме ALWAYS_DONE_ON_RESET_CODES — они принудительно отмечаются выполненными.
     */
    private function resetChecklist(int $taskId): void
    {
        $stmt = $this->db->prepare('UPDATE task_checklist SET is_done = 0 WHERE task_id = :task_id');
        $stmt->execute(['task_id' => $taskId]);

        $placeholders = implode(',', array_fill(0, count(self::ALWAYS_DONE_ON_RESET_CODES), '?'));
        $stmt = $this->db->prepare(
            "UPDATE task_checklist SET is_done = 1
             WHERE task_id = ? AND checklist_id IN (SELECT id FROM checklist WHERE code IN ($placeholders))"
        );
        $stmt->execute([$taskId, ...self::ALWAYS_DONE_ON_RESET_CODES]);
    }
}
