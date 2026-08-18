<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Мотивационная цитата для модалки поздравления: реальная цитата из открытого API (QuoteClient),
 * переведённая на русский нейронкой (локальной или Claude — см. LlmClientFactory). Нейронка тут
 * не автор, а только переводчик — если она не настроена или упала, отдаём оригинал на
 * английском, лишь бы цитата была.
 */
final class MotivationQuoteService
{
    private const TRANSLATE_PROMPT = <<<'PROMPT'
Ты переводишь короткие мотивационные цитаты на русский язык. Переведи цитату, которую тебе
дали: сохрани смысл и краткость, звучи естественно по-русски, а не буквально. Верни только сам
перевод — без кавычек, без имени автора, без вступлений, пояснений и вариантов на выбор.
PROMPT;

    public function __construct(
        private readonly QuoteClient $quoteClient,
        private readonly ?LlmClientInterface $llmClient,
    ) {
    }

    /** @return array{quote: string, author: string} */
    public function generate(): array
    {
        $quote = $this->quoteClient->randomQuote();

        return [
            'quote' => $this->translate($quote['text']),
            'author' => $quote['author'],
        ];
    }

    private function translate(string $text): string
    {
        if ($this->llmClient === null) {
            return $text;
        }

        try {
            $translated = trim($this->llmClient->chat(self::TRANSLATE_PROMPT, $text), " \t\n\r\0\x0B\"«»");
        } catch (Throwable $e) {
            return $text;
        }

        return $translated !== '' ? $translated : $text;
    }
}
