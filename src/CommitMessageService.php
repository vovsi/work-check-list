<?php

declare(strict_types=1);

namespace App;

/** Оркестрирует генерацию commit message: собирает промпт по стандарту команды и зовёт LlmClient */
final class CommitMessageService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Create me a Commit Message subject line following these writing rules:
Commit Message Standards
Every commit starts with the Jira key and then follows the Conventional Commits specification.
The key makes the ticket visible in `git log` and in blame; the type keeps changelog generation and version bumps automatic.
Format
`#<JIRA-KEY> <type>: <short description>`

Return exactly this single line — nothing else. No task link, no body, no footer, no blank lines,
no quotation marks, no "```" fences, no explanations before or after.

* #JIRA-KEY — required. The ticket this commit belongs to, with a literal `#`: `#PPO-4098`, `#BAC-1257`.
* type — required. What kind of change (see table below).
* short description — required. Imperative mood, present tense. Max 80 characters. No trailing period.

Types

* `feat` — New feature or user-facing capability
* `fix` — Bug fix
* `refactor` — Code change that neither adds a feature nor fixes a bug
* `perf` — Performance improvement
* `test` — Adding or correcting tests
* `docs` — Documentation only
* `style` — Formatting, missing semicolons — no logic change
* `chore` — Build tools, dependency updates, CI, config
* `revert` — Revert a previous commit

The type of the squashed PR commit determines the version bump at release: `feat` → MINOR, `fix` / `perf` / `refactor` / `chore` → PATCH. The `#JIRA-KEY` prefix goes before the type and doesn't affect the bump.
Rules

1. Jira key first — `#PPO-4098` opens every commit subject, including the squashed merge commit.
2. English only — no exceptions.
3. Imperative mood in the short description: "add", "fix", "update", "remove" — not "added", "fixing".
4. The prefix is not a description — after the key, the subject must still state what changed in the code. A reader should understand the change from the subject alone, without opening the ticket.
5. One concern per commit — don't mix a feature with a refactor.
6. No "WIP" commits in the final PR — squash or reword before marking Ready for Review.

Return only the commit message subject line itself, no explanations.
PROMPT;

    public function __construct(
        private readonly LlmClient $llmClient,
    ) {
    }

    public function generate(string $taskId, string $description): string
    {
        $input = "Jira key: {$taskId}\nHere my description: {$description}";

        return $this->llmClient->chat(self::SYSTEM_PROMPT, $input);
    }
}
