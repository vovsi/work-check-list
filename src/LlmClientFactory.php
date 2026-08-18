<?php

declare(strict_types=1);

namespace App;

/**
 * Собирает клиента нейронки по [llm].provider из config/params.ini — тот же приём, что у
 * JiraSyncService::createFromConfig(): выбор провайдера инкапсулирован здесь, а не размазан
 * по api/generate_*.php. Конфиг не настроен — Config::llm() бросает исключение, и эндпоинт
 * отвечает 502 (для generate_motivation_quote.php это не ошибка — там нейронка опциональна).
 */
final class LlmClientFactory
{
    public static function createFromConfig(): LlmClientInterface
    {
        $llm = Config::llm();

        return $llm['provider'] === Config::LLM_PROVIDER_ANTHROPIC
            ? new AnthropicLlmClient($llm['api_key'], $llm['model'])
            : new LlmClient($llm['host'], $llm['model']);
    }
}
