<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Отправляет запрос в Claude (Anthropic Messages API). Альтернатива LlmClient — какой из двух
 * используется, решает LlmClientFactory по [llm].provider. Официального PHP SDK тут нет
 * намеренно: он требует composer, которого в проекте нет.
 *
 * Запрос сознательно минимальный — ни thinking, ни output_config.effort: у разных моделей
 * Claude они настраиваются по-разному (на claude-haiku-4-5 effort вообще возвращает 400),
 * а модель приходит из конфига, поэтому клиент не должен зависеть от того, какая она.
 */
final class AnthropicLlmClient implements LlmClientInterface
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    /** С запасом: самый длинный ответ — описание PR по шаблону; платим за сгенерированные токены, а не за лимит */
    private const MAX_TOKENS = 4096;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function chat(string $systemPrompt, string $input): string
    {
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->model,
                'max_tokens' => self::MAX_TOKENS,
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $input]],
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Не удалось обратиться к Claude: {$error}");
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Claude ответил с ошибкой (HTTP {$status}): {$this->extractError($response)}");
        }

        return $this->extractMessage($response);
    }

    /**
     * В content лежит массив блоков разных типов — берём только text: при включённом у модели
     * thinking рядом окажутся блоки thinking, и склеивать их с ответом нельзя.
     */
    private function extractMessage(string $response): string
    {
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException("Claude вернул неразбираемый ответ: {$response}");
        }

        // Отказ классификаторов приходит с HTTP 200, а не ошибкой — content при этом пустой или обрезанный
        if (($data['stop_reason'] ?? null) === 'refusal') {
            throw new RuntimeException('Claude отклонил запрос (stop_reason: refusal)');
        }

        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text .= (string) $block['text'];
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException("Claude вернул пустой ответ: {$response}");
        }

        return $text;
    }

    /** Ошибки API приходят как {"error": {"message": "..."}} — в тост попадает только суть, а не весь JSON */
    private function extractError(string $response): string
    {
        $data = json_decode($response, true);
        $message = $data['error']['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : $response;
    }
}
