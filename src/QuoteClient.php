<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Случайная цитата с zenquotes.io (бесплатный публичный API, без ключа) для MotivationQuoteService.
 * Кэша нет намеренно — цитата должна быть новой при каждом показе модалки поздравления.
 */
final class QuoteClient
{
    private const API_URL = 'https://zenquotes.io/api/random';

    /** Длинная цитата не влезает в модалку 500×500 — перезапрашиваем, пока не попадётся короткая */
    private const MAX_LENGTH = 120;
    private const MAX_ATTEMPTS = 3;

    /** @return array{text: string, author: string} */
    public function randomQuote(): array
    {
        $last = null;

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $last = $this->fetchQuote();
            if (mb_strlen($last['text']) <= self::MAX_LENGTH) {
                return $last;
            }
        }

        return $last;
    }

    /** @return array{text: string, author: string} */
    private function fetchQuote(): array
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
            throw new RuntimeException("Не удалось получить цитату: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Сервис цитат ответил с ошибкой (HTTP {$status})");
        }

        $data = json_decode($response, true);
        $text = $data[0]['q'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('Сервис цитат не вернул текст цитаты');
        }

        $author = $data[0]['a'] ?? '';

        return [
            'text' => trim($text),
            'author' => is_string($author) ? trim($author) : '',
        ];
    }
}
