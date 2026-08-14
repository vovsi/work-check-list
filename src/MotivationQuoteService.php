<?php

declare(strict_types=1);

namespace App;

/** Генерирует короткую мотивационную цитату для модалки поздравления (LlmClient — та же нейронка, что и для commit message/deploy instruction) */
final class MotivationQuoteService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Ты придумываешь короткие мотивационные цитаты на русском языке для разработчика, который
только что отработал полную рабочую норму часов за день. Придумай ОДНУ цитату — не длиннее
одного предложения, максимум 20 слов, в воодушевляющем, но не приторном тоне, как будто её
мог сказать реальный человек, а не мотивационный плакат. Верни только сам текст цитаты, без
кавычек, без указания автора, без вступительных фраз и пояснений.
PROMPT;

    public function __construct(
        private readonly LlmClient $llmClient,
    ) {
    }

    public function generate(): string
    {
        return trim($this->llmClient->chat(self::SYSTEM_PROMPT, 'Придумай цитату.'), " \t\n\r\0\x0B\"«»");
    }
}
