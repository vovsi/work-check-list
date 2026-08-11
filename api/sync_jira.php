<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\JiraSyncService;
use App\TaskRepository;
use App\TaskService;

// Кнопка синхронизации на фронте — принудительно перечитывает заголовок/описание из Jira,
// в отличие от task.php/state.php, которые тянут Jira только если данных ещё нет.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$taskId = (int) ($input['task_id'] ?? 0);

if ($taskId <= 0) {
    respond(['error' => 'Не указан task_id'], 422);
}

$taskRepository = new TaskRepository($pdo);
$service = new TaskService($taskRepository, new ChecklistRepository($pdo), JiraSyncService::createFromConfig($taskRepository));

try {
    $task = $service->resyncJira($taskId);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['task' => $task]);
