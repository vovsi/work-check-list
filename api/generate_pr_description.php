<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\Config;
use App\DeployInstructionService;
use App\LlmClient;
use App\PrDescriptionService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$description = trim((string) ($input['description'] ?? ''));
$instruction = trim((string) ($input['instruction'] ?? ''));
$taskId = trim((string) ($input['task_id'] ?? ''));
$taskLink = trim((string) ($input['task_link'] ?? ''));

if ($description === '') {
    respond(['error' => 'Не указано описание изменений'], 422);
}

try {
    $llmConfig = Config::llm();
    $llmClient = new LlmClient($llmConfig['host'], $llmConfig['model']);
    $service = new PrDescriptionService($llmClient, new DeployInstructionService($llmClient));
    $prDescription = $service->generate($taskId, $taskLink, $description, $instruction);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['description' => $prDescription]);
