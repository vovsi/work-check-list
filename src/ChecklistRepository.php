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
     * Пункты, которые при сбросе чек-листа (кнопка «Начать заново») принудительно
     * отмечаются выполненными, а не обнуляются — это
     * метаданные уровня задачи, которые не имеют смысла переделывать в каждом новом цикле
     * работы над ней. Указаны через стабильный code, а не id/позицию — при добавлении,
     * удалении или переупорядочивании пунктов в Database::CHECKLIST_ITEMS список не
     * сломается, а новые пункты по умолчанию попадут в «обнуляемые».
     * Сейчас пуст: `story_points` раньше был здесь, но с появлением
     * HIDE_IF_STORY_POINTS_ALREADY_SET_CODE его видимость и так полностью управляется
     * реальным статусом в Jira — форсировать is_done при сбросе больше не нужно, иначе
     * пункт остаётся скрытым (как выполненный) даже когда Story Points в Jira не проставлен.
     */
    private const ALWAYS_DONE_ON_RESET_CODES = [];

    /**
     * Пункт скрывается из чек-листа задачи, если в самой задаче Jira Story Points уже
     * проставлен (tasks.story_points_set, обновляется при каждой синхронизации,
     * см. JiraSyncService::sync) — тогда шаг не нужен, а не просто уже выполнен.
     */
    private const HIDE_IF_STORY_POINTS_ALREADY_SET_CODE = 'story_points';

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
             JOIN tasks t ON t.id = tc.task_id
             WHERE tc.task_id = :task_id
               AND NOT (c.code = :hidden_code AND t.story_points_set = 1)
             ORDER BY c.sort_order'
        );
        $stmt->execute([
            'task_id' => $taskId,
            'hidden_code' => self::HIDE_IF_STORY_POINTS_ALREADY_SET_CODE,
        ]);

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

    /**
     * Удаляет все отметки чек-листа задачи (перед удалением самой задачи).
     */
    public function deleteForTask(int $taskId): void
    {
        $stmt = $this->db->prepare('DELETE FROM task_checklist WHERE task_id = :task_id');
        $stmt->execute(['task_id' => $taskId]);
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
     * Сбрасывает чек-лист (кнопка «Начать заново»). Единственное место, где чек-лист
     * обнуляется при живой задаче — повторное открытие (ввод ссылки заново или клик по
     * «Последним задачам») больше не сбрасывает прогресс, см. TaskService::findOrCreateByLink.
     */
    public function resetAll(int $taskId): void
    {
        $stmt = $this->db->prepare('UPDATE task_checklist SET is_done = 0 WHERE task_id = :task_id');
        $stmt->execute(['task_id' => $taskId]);

        if (self::ALWAYS_DONE_ON_RESET_CODES === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count(self::ALWAYS_DONE_ON_RESET_CODES), '?'));
        $stmt = $this->db->prepare(
            "UPDATE task_checklist SET is_done = 1
             WHERE task_id = ? AND checklist_id IN (SELECT id FROM checklist WHERE code IN ($placeholders))"
        );
        $stmt->execute([$taskId, ...self::ALWAYS_DONE_ON_RESET_CODES]);
    }
}
