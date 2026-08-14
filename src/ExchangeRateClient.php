<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Курс USD → UAH для EarningsService — с open.er-api.com (бесплатный публичный API, без ключа).
 * Кэшируется в файле на CACHE_TTL_SECONDS, чтобы не дёргать сторонний сервис при каждом
 * достижении дневной нормы и не терять расчёт заработка при его временной недоступности —
 * тогда отдаётся последнее закэшированное значение, пусть и устаревшее.
 */
final class ExchangeRateClient
{
    private const API_URL = 'https://open.er-api.com/v6/latest/USD';
    private const CACHE_TTL_SECONDS = 6 * 3600;

    public function __construct(
        private readonly string $cacheFile,
    ) {
    }

    public function usdToUahRate(): float
    {
        $cache = $this->readCache();
        if ($cache !== null && $cache['fetched_at'] > time() - self::CACHE_TTL_SECONDS) {
            return $cache['rate'];
        }

        try {
            $rate = $this->fetchRate();
        } catch (RuntimeException $e) {
            if ($cache !== null) {
                return $cache['rate'];
            }
            throw $e;
        }

        $this->writeCache($rate);

        return $rate;
    }

    private function fetchRate(): float
    {
        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Не удалось получить курс валют: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Сервис курсов валют ответил с ошибкой (HTTP {$status})");
        }

        $data = json_decode($response, true);
        $rate = $data['rates']['UAH'] ?? null;
        if (!is_numeric($rate)) {
            throw new RuntimeException('Сервис курсов валют не вернул курс UAH');
        }

        return (float) $rate;
    }

    private function readCache(): ?array
    {
        if (!is_file($this->cacheFile)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->cacheFile), true);
        if (!is_array($data) || !isset($data['rate'], $data['fetched_at'])) {
            return null;
        }

        return $data;
    }

    private function writeCache(float $rate): void
    {
        file_put_contents($this->cacheFile, json_encode(['rate' => $rate, 'fetched_at' => time()]));
    }
}
