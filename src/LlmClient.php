<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/** Отправляет запрос локальной нейронке по протоколу host/api/v1/chat. Ничего не знает про commit message. */
final class LlmClient
{
    public function __construct(
        private readonly string $host,
        private readonly string $model,
    ) {
    }

    public function chat(string $systemPrompt, string $input): string
    {
        $ch = curl_init(rtrim($this->host, '/') . '/api/v1/chat');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->model,
                'system_prompt' => $systemPrompt,
                'input' => $input,
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Не удалось обратиться к нейронке ({$this->host}): {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Нейронка ответила с ошибкой (HTTP {$status}): {$response}");
        }

        return $this->extractMessage($response);
    }

    /** Ответ разных LLM-серверов приходит в разной форме — пробуем известные варианты, иначе берём сырой текст */
    private function extractMessage(string $response): string
    {
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return trim($response);
        }

        $candidates = [
            $data['output'][0]['content'] ?? null,
            $data['choices'][0]['message']['content'] ?? null,
            $data['choices'][0]['text'] ?? null,
            $data['message'] ?? null,
            $data['response'] ?? null,
            $data['content'] ?? null,
            $data['text'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return trim($candidate);
            }
        }

        return trim($response);
    }
}
