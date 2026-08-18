<?php

declare(strict_types=1);

namespace App;

/**
 * Контракт транспорта к нейронке: один системный промпт + один пользовательский ввод → текст.
 * Существует, чтобы пять Service поверх него (BranchNameService, CommitMessageService,
 * DeployInstructionService, PrDescriptionService, MotivationQuoteService) не знали, какой
 * провайдер настроен — локальная нейронка или Claude (см. LlmClientFactory).
 */
interface LlmClientInterface
{
    public function chat(string $systemPrompt, string $input): string;
}
