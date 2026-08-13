<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\JiraSyncService;
use App\TaskRepository;
use App\TaskService;

// Быстрый трек времени ползунком (кружок рядом с индикатором затреканного за сегодня времени):
// добавляет worklog в текущую задачу и не трогает чек-лист — в отличие от log_time.php, где
// трек времени является выполнением пункта чек-листа.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$taskId = (int) ($input['task_id'] ?? 0);
$minutes = max(0, (int) ($input['minutes'] ?? 0));

if ($taskId <= 0 || $minutes <= 0) {
    respond(['error' => 'Не указана задача или время трека нулевое'], 422);
}

$taskRepository = new TaskRepository($pdo);
$service = new TaskService($taskRepository, new ChecklistRepository($pdo), JiraSyncService::createFromConfig($taskRepository));

try {
    $service->logTimeOnly($taskId, $minutes * 60);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['success' => true]);
