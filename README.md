# work-check-list

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
- `src/` — classes (Database, TaskRepository, ChecklistRepository, TaskService)
- `api/` — JSON endpoints (task.php, toggle.php, finish.php)
- `public/` — frontend (index.php, assets/css, assets/js)
- `Dockerfile`, `docker-compose.yml` — containerized run (PHP 8.3 + pdo_sqlite)
