<?php

declare(strict_types=1);

namespace App;

/** Оркестрирует генерацию названия git-ветки по Branch Naming Rules команды и зовёт LlmClient */
final class BranchNameService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Generate a single git branch name following these Branch Naming Rules. Consistent branch naming
keeps the repository readable, supports automation, and links code changes to Jira tasks automatically.

Standard format
<type>/<JIRA-CODE>-<short-description>

Examples:
feature/BAC-123-user-authentication
bugfix/BAC-456-fix-login-redirect
hotfix/BAC-789-payment-gateway-timeout
refactor/BAC-101-extract-auth-service
docs/BAC-55-api-endpoints

Components
- type — lowercase, one word, must match a predefined type (see below).
- JIRA-CODE — exact Jira ticket ID (e.g. BAC-123), given below as "Jira key", used verbatim.
- short-description — 3-5 words, lowercase, hyphen-separated (kebab-case), specific, not vague.

Branch types — pick exactly one based on the task's title/description:
- feature/ — new functionality, improvements, additions, new API endpoints, user stories.
- bugfix/ — non-critical bug fixes found during development, staging, or production.
- hotfix/ — critical production bugs requiring immediate attention (incidents, security, outages).
- refactor/ — code improvements without changing functionality (cleanup, perf, readability).
- docs/ — documentation-only changes (README, API docs, architecture, comments).
- test/ — adding or improving tests without changing production code.
- chore/ — maintenance: dependency updates, build/CI config, dev environment, version bumps.
- spike/ — exploratory/research work with no direct code delivery.
If the task doesn't clearly fit one of the above, default to feature/.

DO:
- Be specific: feature/BAC-123-user-auth-jwt, not feature/BAC-123-fix.
- Use hyphens as separators: feature/BAC-123-add-payment-form.
- Always include the Jira ticket — never skip it.
- Use lowercase for the description.

DON'T:
- No underscores, no spaces, no special characters (@, $, etc.) in the description.
- No overly long descriptions — keep it to 3-5 words.
- No vague descriptions like "fixes", "updates", "changes".

Return only the branch name itself, on a single line, no explanations, no backticks, no quotes.
PROMPT;

    public function __construct(private readonly LlmClient $llmClient)
    {
    }

    public function generate(string $taskId, string $title, string $descriptionHtml): string
    {
        $description = $this->stripHtml($descriptionHtml);
        $input = "Jira key: {$taskId}\nTitle: {$title}\nDescription: {$description}";

        return $this->sanitize($this->llmClient->chat(self::SYSTEM_PROMPT, $input));
    }

    /** Description из Jira приходит как HTML (renderedFields) — для промпта нейронке нужен читаемый текст */
    private function stripHtml(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /** Нейронка иногда оборачивает ответ в кавычки/backticks или добавляет пояснение под ним — берём только имя ветки */
    private function sanitize(string $raw): string
    {
        $firstLine = trim(strtok(trim($raw), "\n"));

        return trim($firstLine, " `'\"");
    }
}
