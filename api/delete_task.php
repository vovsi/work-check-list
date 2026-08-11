<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\TaskRepository;
use App\TaskService;

// Полностью удаляет задачу и весь её чек-лист из БД (без возврата) — в отличие от finish.php,
// который только сбрасывает отметки, оставляя саму задачу.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$link = trim((string) ($input['link'] ?? ''));

if ($link === '') {
    respond(['error' => 'Не указана ссылка на задачу'], 422);
}

$taskService = new TaskService(new TaskRepository($pdo), new ChecklistRepository($pdo));

if (!$taskService->deleteByLink($link)) {
    respond(['error' => 'Задача не найдена'], 404);
}

respond(['success' => true]);
