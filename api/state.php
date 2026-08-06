<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\TaskRepository;

// Только читает текущее состояние задачи, без побочных эффектов (в отличие от task.php,
// которое реализует бизнес-правило "аннулировать пункты при повторном открытии").
// Используется фронтом при восстановлении последней открытой задачи после обновления
// страницы (localStorage) — в этот момент сбрасывать чек-лист не нужно.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$link = trim((string) ($input['link'] ?? ''));

if ($link === '') {
    respond(['error' => 'Не указана ссылка на задачу'], 422);
}

$taskRepository = new TaskRepository($pdo);
$checklistRepository = new ChecklistRepository($pdo);

$taskId = $taskRepository->extractTaskId($link);
$task = $taskRepository->findByLinkOrTaskId($link, $taskId);

if ($task === null) {
    respond(['error' => 'Задача не найдена'], 404);
}

respond([
    'task' => $task,
    'checklist' => $checklistRepository->getStatusesForTask((int) $task['id']),
]);
