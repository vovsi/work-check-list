<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\JiraSyncService;
use App\TaskRepository;
use App\TaskService;

// Добавляет worklog (время сверх уже затреканного) в задаче Jira и отмечает пункт чек-листа
// выполненным — как и update_story_points.php/transition_pull_request.php, у этого пункта
// есть побочный эффект в Jira, поэтому он не может отмечаться напрямую через toggle.php.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$taskId = (int) ($input['task_id'] ?? 0);
$checklistId = (int) ($input['checklist_id'] ?? 0);
$hours = max(0, (int) ($input['hours'] ?? 0));
$minutes = max(0, (int) ($input['minutes'] ?? 0));
$seconds = $hours * 3600 + $minutes * 60;

if ($taskId <= 0 || $checklistId <= 0 || $seconds <= 0) {
    respond(['error' => 'Не указана задача, пункт чек-листа или время затрекано нулевое'], 422);
}

$taskRepository = new TaskRepository($pdo);
$service = new TaskService($taskRepository, new ChecklistRepository($pdo), JiraSyncService::createFromConfig($taskRepository));

try {
    $result = $service->logTime($taskId, $checklistId, $seconds);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond($result);
