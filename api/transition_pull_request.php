<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\JiraSyncService;
use App\TaskRepository;
use App\TaskService;

// Переводит задачу в Jira в статус Pull Request и отмечает пункт чек-листа выполненным —
// как и update_story_points.php, у этого пункта есть побочный эффект в Jira, поэтому он
// не может отмечаться напрямую через toggle.php.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$taskId = (int) ($input['task_id'] ?? 0);
$checklistId = (int) ($input['checklist_id'] ?? 0);

if ($taskId <= 0 || $checklistId <= 0) {
    respond(['error' => 'Не указана задача или пункт чек-листа'], 422);
}

$taskRepository = new TaskRepository($pdo);
$service = new TaskService($taskRepository, new ChecklistRepository($pdo), JiraSyncService::createFromConfig($taskRepository));

try {
    $result = $service->transitionToPullRequest($taskId, $checklistId);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond($result);
