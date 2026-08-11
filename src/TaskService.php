<?php

declare(strict_types=1);

namespace App;

/**
 * Оркестрирует поиск/создание задачи и подготовку её чек-листа.
 * Инкапсулирует бизнес-правило: для уже существующей задачи пункты 3-9 обнуляются,
 * для новой задачи создаётся полный чек-лист без отметок.
 */
final class TaskService
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly ChecklistRepository $checklist,
    ) {
    }

    public function findOrCreateByLink(string $link): array
    {
        $taskId = $this->tasks->extractTaskId($link);
        $existing = $this->tasks->findByLinkOrTaskId($link, $taskId);

        if ($existing !== null) {
            $this->checklist->ensureRowsForTask((int) $existing['id']);
            $this->checklist->resetOnReopen((int) $existing['id']);

            return [
                'task' => $existing,
                'checklist' => $this->checklist->getStatusesForTask((int) $existing['id']),
                'isNew' => false,
            ];
        }

        $task = $this->tasks->create($link, $taskId);
        $this->checklist->ensureRowsForTask((int) $task['id']);

        return [
            'task' => $task,
            'checklist' => $this->checklist->getStatusesForTask((int) $task['id']),
            'isNew' => true,
        ];
    }

    /**
     * Полностью удаляет задачу и её чек-лист по ссылке/идентификатору.
     * Возвращает false, если задача не найдена.
     */
    public function deleteByLink(string $link): bool
    {
        $taskId = $this->tasks->extractTaskId($link);
        $existing = $this->tasks->findByLinkOrTaskId($link, $taskId);

        if ($existing === null) {
            return false;
        }

        $this->checklist->deleteForTask((int) $existing['id']);
        $this->tasks->delete((int) $existing['id']);

        return true;
    }
}
