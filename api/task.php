<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\JiraSyncService;
use App\TaskRepository;
use App\TaskService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$link = trim((string) ($input['link'] ?? ''));

if ($link === '') {
    respond(['error' => 'Не указана ссылка на задачу'], 422);
}

$taskRepository = new TaskRepository($pdo);
$service = new TaskService($taskRepository, new ChecklistRepository($pdo), JiraSyncService::createFromConfig($taskRepository));

try {
    $result = $service->findOrCreateByLink($link);
} catch (\Throwable $e) {
    // Единственный источник исключений здесь — валидация ссылки (формат ключа и наличие
    // задачи в Jira), поэтому это ошибка ввода, а не сбой сервиса
    respond(['error' => $e->getMessage()], 422);
}

respond([
    'task' => $result['task'],
    'checklist' => $result['checklist'],
    'isNew' => $result['isNew'],
]);
