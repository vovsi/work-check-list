<?php

declare(strict_types=1);

namespace App;

/**
 * Считает заработок за отработанные секунды в валюте из [currency] — для модалки поздравления
 * при достижении дневной нормы. Сама валюта сервису не нужна: её знает ExchangeRateClient.
 */
final class EarningsService
{
    public function __construct(
        private readonly ExchangeRateClient $exchangeRateClient,
        private readonly float $hourlyRateUsd,
    ) {
    }

    public function earningsForSeconds(int $seconds): float
    {
        $hours = $seconds / 3600;

        return round($hours * $this->hourlyRateUsd * $this->exchangeRateClient->rateFromUsd(), 2);
    }
}
