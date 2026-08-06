<?php
// Страница активно меняется в разработке — запрещаем браузеру кешировать сам HTML,
// чтобы правки разметки не терялись за старой закешированной версией страницы.
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Версия статики = время последнего изменения файла: при каждой правке CSS/JS
// у ссылки меняется ?v=..., и браузер больше не подставляет закешированную версию.
$assetVersion = static function (string $relativePath): string {
    $path = __DIR__ . '/' . $relativePath;
    // Встроенный сервер PHP держит один процесс на все запросы, поэтому без сброса
    // stat-кеша filemtime() продолжал бы отдавать время первого запроса.
    clearstatcache(true, $path);

    return (string) filemtime($path);
};
?>
<!doctype html>
<html lang="ru" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Work Check List</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $assetVersion('assets/css/style.css') ?>">
</head>
<body>

<!-- Иконка настроек -->
<button id="settings-btn" class="icon-btn settings-btn" title="Настройки" aria-label="Настройки">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7">
        <circle cx="12" cy="12" r="3.2"/>
        <path d="M19.4 13a7.6 7.6 0 0 0 .1-1 7.6 7.6 0 0 0-.1-1l2-1.6-2-3.4-2.4 1a7.4 7.4 0 0 0-1.7-1L15 3h-4l-.3 2.6a7.4 7.4 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.6a7.6 7.6 0 0 0 0 2l-2 1.6 2 3.4 2.4-1a7.4 7.4 0 0 0 1.7 1L11 21h4l.3-2.6a7.4 7.4 0 0 0 1.7-1l2.4 1 2-3.4-2-1.6Z"/>
    </svg>
</button>

<!-- Попап выбора темы -->
<div id="theme-popover" class="popover popover--theme hidden">
    <button class="theme-option" data-theme="light">Светлая</button>
    <button class="theme-option" data-theme="dark">Тёмная</button>
</div>

<main class="app">

    <!-- Экран ввода ссылки на задачу -->
    <section id="link-screen" class="screen link-screen">
        <div class="card link-card">
            <h1 class="app-title">Work Check List</h1>
            <input
                type="text"
                id="task-link-input"
                class="input link-input"
                placeholder="Вставьте ссылку на задачу Jira"
                autofocus
            >
            <div id="link-error" class="error-text hidden"></div>
        </div>
    </section>

    <!-- Экран задачи с чек-листом -->
    <section id="task-screen" class="screen task-screen hidden">
        <div class="task-header">
            <div class="task-id" id="task-id-label"></div>
            <button id="change-task-btn" class="link-btn" title="Ввести другую задачу">сменить задачу</button>
        </div>

        <!-- Прогресс выполнения чек-листа -->
        <div class="progress" id="progress">
            <div class="progress-track">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
            <div class="progress-label" id="progress-label">0%</div>
        </div>

        <ul id="checklist" class="checklist"></ul>

        <button id="finish-task-btn" class="btn btn-primary finish-btn">Завершить задачу</button>

        <div class="git-branch-footer" id="git-branch-footer">
            <button id="git-branch-value" class="branch-pill hidden" title="Скопировать ветку"></button>
            <div class="git-icon-wrap">
                <button id="git-actions-btn" class="git-icon-btn hidden" title="Git команды" aria-label="Git команды">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="6" cy="6" r="2.2"/>
                        <circle cx="6" cy="18" r="2.2"/>
                        <circle cx="18" cy="6" r="2.2"/>
                        <path d="M6 8.2V15.8"/>
                        <path d="M8.2 6H14a4 4 0 0 1 4 4v0"/>
                    </svg>
                </button>
                <div id="git-actions-popover" class="popover popover--git hidden">
                    <button class="git-action-option" data-action="create-branch">Create Branch</button>
                    <button class="git-action-option" data-action="push">Push</button>
                    <button class="git-action-option" data-action="rebase-api3">Rebase API3</button>
                    <button class="git-action-option" data-action="rebase-adminka">Rebase Adminka</button>
                </div>
            </div>
        </div>
    </section>

</main>

<!-- Универсальное модальное окно -->
<div id="modal-overlay" class="modal-overlay hidden">
    <div class="modal card">
        <h2 class="modal-title" id="modal-title"></h2>
        <div class="modal-body" id="modal-body"></div>
        <div class="modal-actions" id="modal-actions"></div>
    </div>
</div>

<!-- Всплывающее уведомление (например «Скопировано») -->
<div id="toast" class="toast"></div>

<script src="assets/js/app.js?v=<?= $assetVersion('assets/js/app.js') ?>"></script>
</body>
</html>
