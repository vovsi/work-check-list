<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

use App\Config;
use App\EarningsService;
use App\ExchangeRateClient;

// Считает заработок в UAH за отработанные сегодня секунды — для модалки поздравления,
// показывается при достижении [worktime].daily_hours (см. openQuickTrackModal в app.js).
// К конкретной задаче не привязано, поэтому идёт мимо TaskService.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Метод не поддерживается'], 405);
}

$input = readJsonInput();
$seconds = max(0, (int) ($input['seconds'] ?? 0));

if ($seconds <= 0) {
    respond(['error' => 'Не указано отработанное время'], 422);
}

$exchangeRateClient = new ExchangeRateClient(dirname(__DIR__) . '/storage/exchange_rate_cache.json');
$service = new EarningsService($exchangeRateClient, Config::salaryHourlyRateUsd());

try {
    $earningsUah = $service->earningsUahForSeconds($seconds);
} catch (\Throwable $e) {
    respond(['error' => $e->getMessage()], 502);
}

respond(['earnings_uah' => $earningsUah]);
