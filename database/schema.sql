-- Схема БД Work Check List

CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_link TEXT NOT NULL UNIQUE,
    task_id TEXT NOT NULL,
    git_branch TEXT DEFAULT NULL,
    stat TEXT NOT NULL DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS checklist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL
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
