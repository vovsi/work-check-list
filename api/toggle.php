<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;
use App\TaskRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$taskId = (int) ($input['task_id'] ?? 0);
$checklistId = (int) ($input['checklist_id'] ?? 0);
$done = (bool) ($input['done'] ?? true);
$branch = isset($input['branch']) ? trim((string) $input['branch']) : null;

if ($taskId <= 0 || $checklistId <= 0) {
    respond(['error' => 'Не указана задача или пункт чек-листа'], 422);
}

$taskRepository = new TaskRepository($pdo);
$checklistRepository = new ChecklistRepository($pdo);

// Пункт «Создать ветку в Git» дополнительно сохраняет имя ветки в задаче
if ($branch !== null && $branch !== '') {
    $taskRepository->updateGitBranch($taskId, $branch);
}

$checklistRepository->setDone($taskId, $checklistId, $done);

respond([
    'task' => $taskRepository->findById($taskId),
    'checklist' => $checklistRepository->getStatusesForTask($taskId),
]);
