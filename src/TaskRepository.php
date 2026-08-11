<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий для работы с таблицей tasks.
 */
final class TaskRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Ищет задачу по ссылке или по вычисленному task_id.
     */
    public function findByLinkOrTaskId(string $link, string $taskId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM tasks WHERE task_link = :link OR task_id = :task_id LIMIT 1'
        );
        $stmt->execute(['link' => $link, 'task_id' => $taskId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function create(string $link, string $taskId): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (task_link, task_id) VALUES (:link, :task_id)'
        );
        $stmt->execute(['link' => $link, 'task_id' => $taskId]);

        $id = (int) $this->db->lastInsertId();

        return $this->findById($id);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function updateGitBranch(int $taskId, string $branch): void
    {
        $stmt = $this->db->prepare('UPDATE tasks SET git_branch = :branch WHERE id = :id');
        $stmt->execute(['branch' => $branch, 'id' => $taskId]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Извлекает идентификатор задачи (например PROJ-123) из ссылки Jira.
     * Если ссылка сама по себе похожа на идентификатор — возвращает её как есть.
     */
    public function extractTaskId(string $link): string
    {
        if (preg_match('~/browse/([A-Za-z0-9]+-\d+)~', $link, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        if (preg_match('~^([A-Za-z0-9]+-\d+)$~', trim($link), $matches) === 1) {
            return strtoupper($matches[1]);
        }

        // Фолбэк: последний сегмент пути без query-параметров
        $path = parse_url($link, PHP_URL_PATH) ?: $link;
        $segments = array_filter(explode('/', $path));

        return strtoupper((string) end($segments));
    }
}
