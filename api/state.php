<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\JiraSyncService;
use App\TaskRepository;
use App\TaskService;

// Читает текущее состояние задачи, не сбрасывая чек-лист (в отличие от task.php, которое
// реализует бизнес-правило "аннулировать пункты при повторном открытии"). Используется
// фронтом в двух местах: восстановление последней открытой задачи после обновления страницы
// (это тоже открытие задачи — с флагом refresh_jira подтягиваем свежие данные из Jira) и
// подгрузка процента выполнения для строк списка «Последние задачи» (без флага — там это не
// открытие, а лёгкий предпросмотр, дёргать Jira на каждую строку списка не нужно).

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$link = trim((string) ($input['link'] ?? ''));
$refreshJira = (bool) ($input['refresh_jira'] ?? false);

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

if ($refreshJira) {
    $service = new TaskService($taskRepository, $checklistRepository, JiraSyncService::createFromConfig($taskRepository));
    $task = $service->syncJira($task);
}

respond([
    'task' => $task,
    'checklist' => $checklistRepository->getStatusesForTask((int) $task['id']),
]);
