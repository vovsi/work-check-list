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
- `storage/app.sqlite` — DB file, created automatically on first run
- `src/` — classes (Database, TaskRepository, ChecklistRepository, TaskService, LlmClient, CommitMessageService)
- `api/` — JSON endpoints (task.php, toggle.php, finish.php, generate_commit_message.php)
- `public/` — frontend (index.php, assets/css, assets/js)
- `Dockerfile`, `docker-compose.yml` — containerized run (PHP 8.3 + pdo_sqlite)
- `config/params.ini` — local LLM config, not in git (see below)

## LLM config (Commit Message generation)

The "Закоммитить код" checklist item can generate a commit message via a local LLM server
(e.g. LM Studio) exposing `POST <host>/api/v1/chat` with `{model, system_prompt, input}`.

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

## Jira config (auto-fetch task title/description)

Opening a task fetches its title/description from Jira once and stores them in the DB — the
sync icon (top-left, next to the checklist) re-fetches on demand.

Add credentials to `config/params.ini`:

```ini
[atlassian]
base_url = "https://your-domain.atlassian.net"
email = "you@example.com"
api_token = "your-api-token"
```

Generate an API token at https://id.atlassian.com/manage-profile/security/api-tokens. Without
this section the app still works — tasks just open without a synced title/description.

## GitHub CLI (PR creation)

The "Создать PR" checklist item copies a `gh pr create ...` command (with reviewers from
`config/params.ini`, section `[github]`, key `reviewers`). Running that command requires the
GitHub CLI installed and authenticated on your machine:

```bash
brew install gh
gh auth login
```

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
work day bounds is simply ignored.
