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
  local LLM server), `BranchNameService`, `CommitMessageService`, `DeployInstructionService`,
  `MotivationQuoteService` (all four generate text via `LlmClient`), `EarningsService`
  (earnings for time worked), `ExchangeRateClient` (USD→target currency rate with file cache)
- `api/` — JSON endpoints: `task.php`, `state.php`, `toggle.php`, `finish.php`,
  `delete_task.php`, `today_time_spent.php`, `get_time_spent.php`, `log_time_quick.php`,
  `log_time.php`, `update_story_points.php`, `transition_doing.php`,
  `transition_pull_request.php`, `generate_branch_name.php`, `generate_commit_message.php`,
  `generate_pr_description.php`, `generate_motivation_quote.php`, `calc_earnings.php`
- `public/` — frontend (index.php, assets/css, assets/js)
- `Dockerfile`, `docker-compose.yml` — containerized run (PHP 8.3 + pdo_sqlite)
- `config/params.ini` — integrations config (Jira, LLM, GitHub, work day, salary), not in git
  (see below)

See `CLAUDE.md` in this repo for the full architecture, business rules and per-endpoint
description.

## LLM config (branch name / commit message / PR description / motivation quote)

Four checklist/modal features generate text via a local LLM server (e.g. LM Studio) exposing
`POST <host>/api/v1/chat` with `{model, system_prompt, input}`: the "Создать ветку в Git" item
(branch name suggestion), the "Закоммитить код" item (commit message subject line), the
"Указать описание PR" item (PR description filled into the team template, plus the optional
deploy instruction block appended to it), and the "reached daily hours" congrats modal
(motivation quote). All four share the same `[llm]` config and go through one `LlmClient`.

Copy the example config and point it at your LLM server:

```bash
cp config/params.ini.example config/params.ini
```

```ini
[llm]
host = "http://host.docker.internal:1234"
model = "deepseek-coder-v2-lite-instruct"
```

`config/params.ini` is gitignored (host/model are local to your machine). Since the app runs
in Docker, `localhost` inside the container points at the container itself — use
`host.docker.internal` (or your host machine's LAN IP) to reach an LLM server running on the
host. If you run the app without Docker (`php -S localhost:8000`), plain `localhost` works.
Without this section the four LLM endpoints respond with an error — the rest of the app still
works.

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
