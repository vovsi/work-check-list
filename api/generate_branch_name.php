<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\BranchNameService;
use App\Config;
use App\LlmClient;
use App\TaskRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$taskId = (int) ($input['task_id'] ?? 0);

if ($taskId <= 0) {
    respond(['error' => 'Не указан task_id'], 422);
}

$task = (new TaskRepository($pdo))->findById($taskId);
if ($task === null) {
    respond(['error' => 'Задача не найдена'], 404);
}
if ($task['title'] === null) {
    respond(['error' => 'Сначала синхронизируйте данные задачи из Jira (значок синхронизации)'], 422);
}

try {
    $llmConfig = Config::llm();
    $service = new BranchNameService(new LlmClient($llmConfig['host'], $llmConfig['model']));
    $branchName = $service->generate($task['task_id'], $task['title'], (string) $task['description']);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['branch_name' => $branchName]);
