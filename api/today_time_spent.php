<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\JiraSyncService;
use App\TaskRepository;

// Read-only чтение суммарно затреканного сегодня времени по всем задачам Jira — для
// индикатора в шапке. К конкретной задаче не привязано, поэтому идёт мимо TaskService.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$jiraSync = JiraSyncService::createFromConfig(new TaskRepository($pdo));
if ($jiraSync === null) {
    respond(['error' => 'Интеграция с Jira не настроена — заполните config/params.ini'], 422);
}

try {
    $seconds = $jiraSync->getTodayTimeSpentSeconds();
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['time_spent_seconds' => $seconds]);
