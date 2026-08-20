<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\JiraSyncService;
use App\TaskRepository;
use App\TaskService;

// Read-only чтение суммарно затреканного сегодня времени по всем задачам Jira — для
// индикатора в шапке. Необязательный task_id — открытая сейчас задача: её время нужно учесть,
// даже если JQL-поиск Jira ещё не проиндексировал только что добавленный worklog.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$ensureTaskId = (int) ($input['task_id'] ?? 0);

$taskRepository = new TaskRepository($pdo);
$jiraSync = JiraSyncService::createFromConfig($taskRepository);
if ($jiraSync === null) {
    respond(['error' => 'Интеграция с Jira не настроена — заполните config/params.ini'], 422);
}

$service = new TaskService($taskRepository, new ChecklistRepository($pdo), $jiraSync);

try {
    $seconds = $service->getTodayTimeSpentSeconds($ensureTaskId);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['time_spent_seconds' => $seconds]);
