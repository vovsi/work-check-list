<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
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

$service = new TaskService(new TaskRepository($pdo), new ChecklistRepository($pdo));
$result = $service->findOrCreateByLink($link);

respond([
    'task' => $result['task'],
    'checklist' => $result['checklist'],
    'isNew' => $result['isNew'],
]);
