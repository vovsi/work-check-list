<?php

declare(strict_types=1);

namespace App;

/**
 * Оркестрирует подготовку описания PR: генерирует текст по шаблону команды через LlmClient и,
 * если задана инструкция выливки, дописывает её оформленный блок в конец (DeployInstructionService).
 */
final class PrDescriptionService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You write the description of a GitHub Pull Request for the developer who made the change. The input
below gives you the Jira key, the Jira issue link and a free-form note about what was done.

Fill in the template at the end of this prompt and return the filled template as your whole answer —
nothing before it, nothing after it, no explanations, no code fences around the whole answer.

Rules:

1. English only.
2. Copy every section heading (`## What`, `## How`, `## Jira`, `## Checklist`,
   `## Screenshots / examples`, `## Breaking changes`) character for character, in the same order.
   Do not add, rename, drop or reorder sections.
3. Drop the HTML comments (`<!-- ... -->`) from the template — they are instructions for you, not
   content of the PR. Put the real content in their place.
4. `## What` — one short paragraph: what this change is and why. `## How` — a couple of sentences on
   the approach and key decisions, not a line-by-line retelling of the diff.
5. `## Jira` — the issue link given in the input, verbatim, on its own line. Do not invent a link.
6. `## Checklist` — copy all its lines character for character, leaving every box unchecked (`- [ ]`).
   The author ticks them himself.
7. `## Screenshots / examples` — fill in only if the input really contains request/response examples
   or mentions screenshots; otherwise write `N/A`.
8. `## Breaking changes` — list breaking API changes, DB schema changes or required config updates if
   the input mentions them; otherwise write `None`.
9. Never invent facts: no made-up file names, tickets, numbers, endpoints or decisions that are not in
   the input. Better a short section than an invented one.
10. Anything that looks like code or a technical identifier in the input — SQL, table/column/config
    names, variables, file paths, snippets — must be copied character for character, unchanged; you
    may only wrap it in inline code or a fenced code block. Do not rewrite, reformat or "improve" it.
11. The reader is the reviewer on GitHub, not the person who wrote you the note: no phrases addressed
    to them or to yourself, no comments about the formatting process.

Template:

## What

## How

## Jira

## Checklist
- [ ] Follows code style (PSR-12 / Laravel conventions).
- [ ] Engineering principles respected (SOLID, DRY, KISS).
- [ ] No commented-out code.
- [ ] Tests added/updated.
- [ ] Tests pass locally.
- [ ] Definition of Done checked.

## Screenshots / examples

## Breaking changes
PROMPT;

    public function __construct(
        private readonly LlmClient $llmClient,
        private readonly DeployInstructionService $deployInstructionService,
    ) {
    }

    /** Инструкция выливки опциональна: не указана — в описание PR не попадает вообще */
    public function generate(string $taskId, string $taskLink, string $description, string $instruction = ''): string
    {
        $input = "Jira key: {$taskId}\nJira link: {$taskLink}\nWhat was done: {$description}";
        $prDescription = trim($this->llmClient->chat(self::SYSTEM_PROMPT, $input));

        if ($instruction === '') {
            return $prDescription;
        }

        return $prDescription . "\n\n" . $this->deployInstructionService->generate($instruction);
    }
}
