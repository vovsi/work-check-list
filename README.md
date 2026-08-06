# work-check-list

Одностраничное приложение-чек-лист для работы с задачами Jira. PHP 8.3 + SQLite, без фреймворков.

## Запуск локально (PHP)

```bash
php -S localhost:8000
```

Открыть в браузере: http://localhost:8000/public/index.php

Рекомендуется открывать в отдельном маленьком окне Chrome (Create Shortcut / App mode).

## Запуск через Docker

```bash
docker compose up -d --build
```

Открыть в браузере: http://localhost:8000/public/index.php

Файл БД сохраняется на хосте в `storage/app.sqlite` (смонтирован как volume — не теряется при пересборке).

Остановить: `docker compose down`

## Структура

- `database/schema.sql` — схема SQLite (tasks, checklist, task_checklist)
- `storage/app.sqlite` — файл БД, создаётся автоматически при первом запуске
- `src/` — классы (Database, TaskRepository, ChecklistRepository, TaskService)
- `api/` — JSON-эндпоинты (task.php, toggle.php, finish.php)
- `public/` — фронтенд (index.php, assets/css, assets/js)
- `Dockerfile`, `docker-compose.yml` — запуск в контейнере (PHP 8.3 + pdo_sqlite)
