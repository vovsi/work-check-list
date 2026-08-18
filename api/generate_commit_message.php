<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\CommitMessageService;
use App\LlmClientFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$description = trim((string) ($input['description'] ?? ''));
$taskId = trim((string) ($input['task_id'] ?? ''));

if ($description === '') {
    respond(['error' => 'Не указано описание изменений'], 422);
}

try {
    $service = new CommitMessageService(LlmClientFactory::createFromConfig());
    $message = $service->generate($taskId, $description);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['message' => $message]);
