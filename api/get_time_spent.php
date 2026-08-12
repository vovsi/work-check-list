<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\JiraSyncService;
use App\TaskRepository;
use App\TaskService;

// Read-only чтение уже затреканного в Jira времени — для отображения в модалке пункта
// «Затрекать время» перед добавлением нового worklog, без побочных эффектов.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$taskId = (int) ($input['task_id'] ?? 0);

if ($taskId <= 0) {
    respond(['error' => 'Не указана задача'], 422);
}

$taskRepository = new TaskRepository($pdo);
$service = new TaskService($taskRepository, new ChecklistRepository($pdo), JiraSyncService::createFromConfig($taskRepository));

try {
    $seconds = $service->getTimeSpentSeconds($taskId);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['time_spent_seconds' => $seconds]);
