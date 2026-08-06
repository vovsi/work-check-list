<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\ChecklistRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$taskId = (int) ($input['task_id'] ?? 0);

if ($taskId <= 0) {
    respond(['error' => 'Не указана задача'], 422);
}

$checklistRepository = new ChecklistRepository($pdo);
$checklistRepository->resetAll($taskId);

respond([
    'checklist' => $checklistRepository->getStatusesForTask($taskId),
]);
