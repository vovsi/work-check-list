<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\DeployInstructionService;
use App\LlmClientFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$instruction = trim((string) ($input['instruction'] ?? ''));

if ($instruction === '') {
    respond(['error' => 'Не указана инструкция выливки'], 422);
}

try {
    $service = new DeployInstructionService(LlmClientFactory::createFromConfig());
    $deployInstruction = $service->generate($instruction);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['instruction' => $deployInstruction]);
