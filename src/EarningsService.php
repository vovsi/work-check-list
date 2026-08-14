<?php

declare(strict_types=1);

namespace App;

/** Считает заработок в UAH за отработанные секунды — для модалки поздравления при достижении дневной нормы */
final class EarningsService
{
    public function __construct(
        private readonly ExchangeRateClient $exchangeRateClient,
        private readonly float $hourlyRateUsd,
    ) {
    }

    public function earningsUahForSeconds(int $seconds): float
    {
        $hours = $seconds / 3600;

        return round($hours * $this->hourlyRateUsd * $this->exchangeRateClient->usdToUahRate(), 2);
    }
}
