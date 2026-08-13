<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\Config;
use App\DeployInstructionService;
use App\LlmClient;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$instruction = trim((string) ($input['instruction'] ?? ''));

if ($instruction === '') {
    respond(['error' => 'Не указана инструкция'], 422);
}

try {
    $llmConfig = Config::llm();
    $service = new DeployInstructionService(new LlmClient($llmConfig['host'], $llmConfig['model']));
    $formatted = $service->generate($instruction);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['instruction' => $formatted]);
