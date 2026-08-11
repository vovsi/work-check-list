-- Схема БД Work Check List

CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_link TEXT NOT NULL UNIQUE,
    task_id TEXT NOT NULL,
    -- title/description — заголовок и описание из Jira. NULL, пока не стянуты
    -- (см. TaskService::syncJiraIfMissing — тянутся один раз, дальше берутся из БД).
    title TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    git_branch TEXT DEFAULT NULL,
    stat TEXT NOT NULL DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS checklist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    -- code — стабильный идентификатор смысла пункта (используется в бизнес-логике и на фронте).
    -- id и sort_order можно менять/переставлять, code — никогда: на нём держится привязка
    -- уже проставленных галочек в task_checklist к правильному пункту.
    code TEXT NOT NULL,
    title TEXT NOT NULL,
    sort_order INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS task_checklist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    checklist_id INTEGER NOT NULL,
    is_done INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (task_id) REFERENCES tasks(id),
    FOREIGN KEY (checklist_id) REFERENCES checklist(id),
    UNIQUE (task_id, checklist_id)
);
