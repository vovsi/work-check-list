<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\Config;
use App\LlmClientFactory;
use App\MotivationQuoteService;
use App\QuoteClient;

// Отдаёт мотивационную цитату для модалки поздравления (см. calc_earnings.php — тот же повод).

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

// Нейронка тут только переводчик — без неё цитата всё равно отдаётся, на языке оригинала
try {
    $llmClient = LlmClientFactory::createFromConfig();
} catch (\Throwable $e) {
    $llmClient = null;
}

try {
    $service = new MotivationQuoteService(new QuoteClient(Config::quotesUrl()), $llmClient);
    $result = $service->generate();
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond($result);
