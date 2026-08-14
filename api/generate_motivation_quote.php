<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\Config;
use App\LlmClient;
use App\MotivationQuoteService;

// Генерирует мотивационную цитату для модалки поздравления (см. calc_earnings.php — тот же повод).

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

try {
    $llmConfig = Config::llm();
    $service = new MotivationQuoteService(new LlmClient($llmConfig['host'], $llmConfig['model']));
    $quote = $service->generate();
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['quote' => $quote]);
