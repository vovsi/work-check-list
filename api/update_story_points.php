<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\JiraSyncService;
use App\TaskRepository;
use App\TaskService;

// Проставляет выбранные пользователем Story Points в самой задаче Jira и отмечает пункт
// чек-листа выполненным — в отличие от toggle.php, у этого пункта есть побочный эффект
// в Jira, поэтому он не может отмечаться напрямую через toggle.php.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$taskId = (int) ($input['task_id'] ?? 0);
$checklistId = (int) ($input['checklist_id'] ?? 0);
$storyPoints = (int) ($input['story_points'] ?? 0);

if ($taskId <= 0 || $checklistId <= 0 || $storyPoints <= 0) {
    respond(['error' => 'Не указана задача, пункт чек-листа или количество Story Points'], 422);
}

$taskRepository = new TaskRepository($pdo);
$service = new TaskService($taskRepository, new ChecklistRepository($pdo), JiraSyncService::createFromConfig($taskRepository));

try {
    $result = $service->updateStoryPoints($taskId, $checklistId, $storyPoints);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond($result);
