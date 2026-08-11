<?php

declare(strict_types=1);

namespace App;

use RuntimeException;
use Throwable;

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
        // Интеграция с Jira опциональна (см. JiraSyncService::createFromConfig) — без неё
        // задача всё равно открывается/создаётся, просто без заголовка/описания.
        private readonly ?JiraSyncService $jiraSync = null,
    ) {
    }

    public function findOrCreateByLink(string $link): array
    {
        $taskId = $this->tasks->extractTaskId($link);
        $existing = $this->tasks->findByLinkOrTaskId($link, $taskId);

        if ($existing !== null) {
            $this->checklist->ensureRowsForTask((int) $existing['id']);
            $this->checklist->resetOnReopen((int) $existing['id']);
            $task = $this->syncJiraIfMissing($existing);

            return [
                'task' => $task,
                'checklist' => $this->checklist->getStatusesForTask((int) $task['id']),
                'isNew' => false,
            ];
        }

        $task = $this->tasks->create($link, $taskId);
        $this->checklist->ensureRowsForTask((int) $task['id']);
        $task = $this->syncJiraIfMissing($task);

        return [
            'task' => $task,
            'checklist' => $this->checklist->getStatusesForTask((int) $task['id']),
            'isNew' => true,
        ];
    }

    /**
     * Подтягивает заголовок/описание из Jira только если их ещё нет (title === null) —
     * повторное открытие уже синхронизированной задачи Jira не дёргает. Ошибки (Jira недоступна
     * или не настроена) не должны мешать открытию задачи — просто оставляем её без данных.
     */
    private function syncJiraIfMissing(array $task): array
    {
        if ($task['title'] !== null || $this->jiraSync === null) {
            return $task;
        }

        try {
            return $this->jiraSync->sync($task);
        } catch (Throwable $e) {
            return $task;
        }
    }

    /**
     * Принудительно перечитывает заголовок/описание из Jira (кнопка синхронизации на фронте).
     * В отличие от syncJiraIfMissing — тянет всегда и пробрасывает ошибку вызывающему коду,
     * чтобы пользователь увидел, что синхронизация не удалась.
     */
    public function resyncJira(int $taskId): array
    {
        $task = $this->tasks->findById($taskId);
        if ($task === null) {
            throw new RuntimeException('Задача не найдена');
        }
        if ($this->jiraSync === null) {
            throw new RuntimeException('Интеграция с Jira не настроена — заполните config/params.ini');
        }

        return $this->jiraSync->sync($task);
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
