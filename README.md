# DevFlow

Single-page checklist app for working with Jira tasks. PHP 8.3 + SQLite, no frameworks.

## Run locally (PHP)

```bash
php -S localhost:8000
```

Open in browser: http://localhost:8000/public/index.php

It's recommended to open it in a small standalone Chrome window (Create Shortcut / App mode).

## Run with Docker

```bash
docker compose up -d --build
```

Open in browser: http://localhost:8000/public/index.php

The whole project is mounted into the container as a volume, so code changes on the host are picked up immediately — after editing a file, just run `docker compose restart` (no rebuild needed). A rebuild (`docker compose up -d --build`) is only required after changing the `Dockerfile` itself (e.g. adding a PHP extension).

Stop: `docker compose down`

## Structure

- `database/schema.sql` — SQLite schema (tasks, checklist, task_checklist)
- `storage/` — created automatically: `app.sqlite` (DB file) and `exchange_rate_cache.json`
  (USD→target currency rate cache, 6h TTL, used by the earnings calculation)
- `src/` — classes: `Database` (connection + migrations + checklist seeding), `Config`
  (reads/validates `config/params.ini`), `TaskRepository`, `ChecklistRepository`, `TaskService`
  (find-or-create task + checklist orchestration), `JiraClient` (Jira REST API v2 client),
  `JiraSyncService` (sync, status transitions, worklogs), `LlmClient` (transport client to a
  local LLM server), `AnthropicLlmClient` (transport client to Claude), `LlmClientFactory`
  (picks one of the two by config), `BranchNameService`, `CommitMessageService`,
  `DeployInstructionService`, `MotivationQuoteService` (all four generate text through
  `LlmClientInterface`), `EarningsService`
  (earnings for time worked), `ExchangeRateClient` (USD→target currency rate with file cache)
- `api/` — JSON endpoints: `task.php`, `state.php`, `toggle.php`, `finish.php`,
  `delete_task.php`, `today_time_spent.php`, `get_time_spent.php`, `log_time_quick.php`,
  `log_time.php`, `update_story_points.php`, `transition_doing.php`,
  `transition_pull_request.php`, `generate_branch_name.php`, `generate_commit_message.php`,
  `generate_pr_description.php`, `generate_deploy_instruction.php`,
  `generate_motivation_quote.php`, `calc_earnings.php`
- `public/` — frontend (index.php, assets/css, assets/js)
- `Dockerfile`, `docker-compose.yml` — containerized run (PHP 8.3 + pdo_sqlite)
- `config/params.ini` — integrations config (Jira, LLM, GitHub, work day, salary), not in git
  (see below)

See `CLAUDE.md` in this repo for the full architecture, business rules and per-endpoint
description.

## LLM config (branch name / commit message / PR description / motivation quote)

Four checklist/modal features generate text with an LLM: the "Создать ветку в Git" item
(branch name suggestion), the "Закоммитить код" item (commit message subject line), the
"Указать описание PR" item (PR description filled into the team template, plus the optional
deploy instruction block appended to it), and the "reached daily hours" congrats modal
(motivation quote). All four share the same `[llm]` config and go through
`LlmClientInterface`, so they don't care which provider is configured.

Two providers are supported, selected by `provider`: **Claude** over the Anthropic API
(`AnthropicLlmClient`), or **LM Studio** (and compatible servers) exposing
`POST <host>/api/v1/chat` with `{model, system_prompt, input}` (`LlmClient`). Settings for both
live side by side — switching is a one-line change, nothing needs to be deleted.

Copy the example config and fill in the provider you want:

```bash
cp config/params.ini.example config/params.ini
```

```ini
[llm]
provider = "anthropic"
anthropic_api_key = "sk-ant-..."
anthropic_model = "claude-haiku-4-5"
lmstudio_host = "http://host.docker.internal:1234"
lmstudio_model = "deepseek-coder-v2-lite-instruct"
```

- `provider = "anthropic"` — needs `anthropic_api_key` (create one at
  [platform.claude.com](https://platform.claude.com)). `anthropic_model` is optional and
  defaults to `claude-haiku-4-5` — the cheapest and fastest model, which is plenty for outputs
  this short. No Composer dependency: the API is called over plain cURL.
- `provider = "lmstudio"` — needs `lmstudio_host` and `lmstudio_model`. Since the app runs in Docker,
  `localhost` inside the container points at the container itself — use `host.docker.internal`
  (or your host machine's LAN IP) to reach an LLM server running on the host. If you run the app
  without Docker (`php -S localhost:8000`), plain `localhost` works.

`provider` is optional and defaults to `"lmstudio"`. `config/params.ini` is gitignored (the API key
and local addresses are yours). If the selected provider isn't configured, the four LLM
endpoints respond with an error — the rest of the app still works.

## Jira config (auto-fetch task title/description, status transitions, Story Points, time tracking)

Opening a task fetches its title/description from Jira once and stores them in the DB — the
sync icon (top-left, next to the checklist) re-fetches on demand. The same integration also
powers the "Указать Story Points", "Перевести в статус Doing", "Перевести задачу в Pull
Request" and "Затрекать время" checklist items.

Add credentials to `config/params.ini`:

```ini
[atlassian]
base_url = "https://your-domain.atlassian.net"
email = "you@example.com"
api_token = "your-api-token"
story_points_field = "customfield_10016"
doing_status = "Doing"
pull_request_status = "Pull request"
```

Generate an API token at https://id.atlassian.com/manage-profile/security/api-tokens.
`story_points_field`/`doing_status`/`pull_request_status` are optional — shown above are their
defaults. Without this section the app still works — tasks just open without a synced
title/description, and the Jira-backed checklist items are skipped.

## GitHub CLI (PR creation)

The "Создать PR" checklist item copies a `gh pr create ...` command (with reviewers from
`config/params.ini`, section `[github]`, key `reviewers`). Running that command requires the
GitHub CLI installed and authenticated on your machine:

```bash
brew install gh
gh auth login
```

## Git commands dropdown and team-specific texts

Repository names, their base branches and the team-specific bits of the copied texts are config
too — none of them live in the code:

```ini
[git]
rebase_targets = "API3:main, Adminka:dev"

[templates]
review_skip_migration_repos = "api_v3, adminka"
deploy_config_project = "апи3"
```

Optional. `rebase_targets` (`Label:base-branch`, comma-separated) renders the "Rebase …" entries
of the git commands dropdown next to the branch name — each copies `git rebase origin/<base>`;
with no value the dropdown keeps only `Create Branch` and `Push`. `review_skip_migration_repos`
lists the repositories whose migrations the Claude review prompt must skip — with no value that
exception paragraph is left out of the prompt. `deploy_config_project` is the project named in
the "Добавить в конфиг …" line of the deploy template copied on the last checklist item — with
no value the line just has no project name.

Links to your internal documentation are config too — the Claude review prompt cites them next
to the matching review checklist points:

```ini
[docs]
php_code_style = "https://your-domain.atlassian.net/wiki/spaces/DevTeam/pages/000000001"
testing_standards = "https://your-domain.atlassian.net/wiki/spaces/DevTeam/pages/000000002"
api_data_format = "https://your-domain.atlassian.net/wiki/spaces/DevTeam/pages/000000003"
```

Every key is optional — a missing one simply leaves its link out of the prompt.

## Work day config (quick time tracking)

Hovering the "time spent today" pill (top-left) reveals a circle button that opens a slider for
logging time into the current task. The slider spans your work day and highlights today's total
against your daily norm — set them in `config/params.ini`:

```ini
[worktime]
start = "09:00"
end = "18:00"
daily_hours = 8
lunch_start = "12:00"
lunch_end = "13:00"
```

Optional — defaults are 09:00, 18:00, 8 hours and a 12:00–13:00 lunch break. The lunch break is
highlighted in orange on the slider and never counted into the logged time; a break outside the
work day bounds is simply ignored. `daily_hours` is also the threshold for the congrats modal
below.

## Salary config (earnings shown on the congrats modal)

The first time-log of the day that brings today's total up to `[worktime].daily_hours` pops up
a congrats modal with a motivation quote (see LLM config above) and, in parallel, today's
earnings — computed from an hourly USD rate converted at the current exchange rate into the
currency from `[currency]` (cached for 6 hours, see `storage/exchange_rate_cache.json` above).

```ini
[salary]
monthly_usd = 1500
working_days_per_month = 21
```

Optional — defaults shown above. Hourly rate = `monthly_usd / (working_days_per_month *
daily_hours)`. The earnings line is simply omitted from the modal if the exchange rate request
fails or the computed amount is `0`.

## Currency and external service URLs

The currency the earnings are converted to, and the URLs of the keyless external services, are
config too — nothing is hardcoded in PHP or JS:

```ini
[currency]
code = "UAH"
label = "грн"

[services]
exchange_rate_url = "https://open.er-api.com/v6/latest/USD"
quotes_url = "https://zenquotes.io/api/random"
```

Optional — defaults shown above. `code` is looked up in the exchange rate response (`rates`),
`label` is what the UI prints next to the amount. Services that need a token stay in their own
sections (`[atlassian]`, `[llm]`).

## Claude Code skill mode (hiding the steps the skill does for you)

When a Claude Code skill already handles committing, opening the PR, reviewing it and writing
the PR description, those checklist items are just noise. One switch hides them:

```ini
[mode]
claude_code_skill_mode = "1"
```

`1` hides `code_written`, `pull_request`, `claude_review` and `pr_description` (list —
`ChecklistRepository::CLAUDE_CODE_SKILL_MODE_HIDDEN_CODES`) together with the copy buttons that
depend on the PR link they produce, and adds one step of its own right after "Create a git
branch" — `skill_commit` ("Закоммитить изменения"), a modal that copies the skill's `/commit`
command to the clipboard (list — `CLAUDE_CODE_SKILL_MODE_ONLY_CODES`, hidden when the mode is
off). `0`, missing key or missing `config/params.ini` — full
checklist. Nothing is deleted from the database: already ticked items come back exactly as they
were when the mode is switched off.
