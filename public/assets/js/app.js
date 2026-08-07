// Work Check List — вся клиентская логика одностраничного приложения
(() => {
    'use strict';

    // ==================== Тексты для копирования (пункты 6 и 7) ====================

    function buildJiraCommentText(prLink) {
        return 'Для выливки необходимо:\n' +
            '1. Запустить скрипты БД:\n```\n\n```\n' +
            '2. Добавить в конфиг апи3:\n```\n\n```\n' +
            '3. Вылить: ' + (prLink || '[ссылка на PR не найдена — вставьте вручную]');
    }

    const JIRA_DESCRIPTION_HTML =
        '<b> Results</b><br/>1. <br/>' +
        '<b> Testing</b><br/>1. <br/>' +
        '<b> Pull Requests</b><br/>1. <br/>';

    const JIRA_DESCRIPTION_PLAIN = 'Results\n1. \n\nTesting\n1. \n\nPull Requests\n1. ';

    function buildClaudeReviewText(prLink) {
        return 'Ты — Senior Fullstack Code Reviewer с глубокой экспертизой в PHP, MySQL, JavaScript, HTML и CSS. \n\n' +
        'Проведи тщательное Code Review предоставленного Pull Request (PR) / diff. \n\n' +
        '### Оценивай код по следующим критериям:\n\n' +
        '1. **Безопасность (Security - Критический приоритет):**\n' +
        '   - **PHP / MySQL:** Уязвимости к SQL-инъекциям (отсутствие Prepared Statements/PDO), XSS (неэкранированный вывод), CSRF, невалидированные пользовательские данные, небезопасное хранение паролей или токенов.\n' +
        '   - **JS / HTML:** Dom-based XSS, некорректная обработка `innerHTML` / `dangerouslySetInnerHTML`, утечки токенов или чувствительных данных в клиентский код / `localStorage`.\n\n' +
        '2. **Производительность и работа с БД:**\n' +
        '   - **MySQL / PHP:** Проблема N+1 запросов в циклах, отсутствующие или неоптимальные индексы, загрузка избыточных данных (`SELECT *`), неэффективная фильтрация на стороне PHP вместо БД, отсутствие транзакций там, где обновляются несколько связанных таблиц.\n' +
        '   - **JS / CSS / HTML:** Избыточная перерисовка (layout thrashing), тяжелые операции на UI-потоке, утечки памяти (неудаленные event listener\'ы / таймеры), загрузка неоптимизированных ресурсов.\n\n' +
        '3. **Архитектура и чистый код (Clean Code):**\n' +
        '   - **PHP:** Соблюдение SOLID, DRY, KISS, YAGNI. Нарушение разделения ответственности (например, SQL-запросы в контроллерах или шаблонах).\n' +
        '   - **JS:** Ограничение глобальной области видимости, асинхронная обработка (promises / async-await вместо callback hell), чистые функции.\n' +
        '   - **HTML / CSS:** Семантическая верстка, правильная иерархия тегов, доступность (a11y), понятный и масштабируемый CSS (БЭМ, отсутствие жесткого завязывания на ID или избыточной вложенности селекторов).\n\n' +
        '4. **Обработка ошибок и крайние случаи (Edge Cases):**\n' +
        '   - Обработка `null` / `undefined` / пустых строк / отрицательных чисел.\n' +
        '   - Перехват исключений (try-catch) на бэкенде и корректные HTTP-статусы ответа.\n' +
        '   - Корректность валидации данных как на клиенте, так и ОБЯЗАТЕЛЬНО на сервере.\n\n' +
        '5. **Тестируемость и поддерживаемость:**\n' +
        '   - Насколько просто написать Unit/Integration тесты для этого кода.\n' +
        '   - Наличие хардкода (константы, конфиги, секреты прямо в коде).\n\n' +
        '---\n\n' +
        '### Формат ответа:\n\n' +
        '1. **Краткое резюме:** Общее впечатление от PR (1–3 предложения).\n' +
        '2. **Critical / Blocker (Критические проблемы):** Ошибки безопасности, баги, приводящие к падению, утечки памяти, SQL-инъекции. Требуют обязательно исправления.\n' +
        '3. **Major (Важные замечания):** Архитектурные огрехи, проблемы с производительностью (N+1), отсутствие валидации.\n' +
        '4. **Minor / Nits (Мелкие улучшения и стиль):** Рефакторинг, читаемость, форматирование, верстка.\n' +
        '5. **Примеры исправлений:** Для ключевых проблем укажи конкретный фрагмент кода "Было → Стало".\n\n' +
        '---\n\n' +
        '**Вот код для ревью:**\n' +
        (prLink || '[ссылка на PR не найдена — вставьте вручную]');
    }

    // ==================== DOM-элементы ====================

    const linkScreen = document.getElementById('link-screen');
    const taskScreen = document.getElementById('task-screen');
    const taskLinkInput = document.getElementById('task-link-input');
    const linkError = document.getElementById('link-error');
    const recentTasksEl = document.getElementById('recent-tasks');
    const recentTasksListEl = document.getElementById('recent-tasks-list');
    const taskIdLabel = document.getElementById('task-id-label');
    const changeTaskBtn = document.getElementById('change-task-btn');
    const checklistEl = document.getElementById('checklist');
    const progressFill = document.getElementById('progress-fill');
    const progressLabel = document.getElementById('progress-label');
    const finishTaskBtn = document.getElementById('finish-task-btn');
    const gitBranchValue = document.getElementById('git-branch-value');
    const gitActionsBtn = document.getElementById('git-actions-btn');
    const gitActionsPopover = document.getElementById('git-actions-popover');
    const settingsBtn = document.getElementById('settings-btn');
    const themePopover = document.getElementById('theme-popover');
    const toastEl = document.getElementById('toast');
    const modalOverlay = document.getElementById('modal-overlay');
    const modalTitleEl = document.getElementById('modal-title');
    const modalBodyEl = document.getElementById('modal-body');
    const modalActionsEl = document.getElementById('modal-actions');

    const CHECK_SVG =
        '<svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" ' +
        'stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>';

    const COPY_SVG =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" ' +
        'stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2.2"/>' +
        '<path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>';

    // ==================== Иконки сервисов (справа у каждого пункта чек-листа) ====================

    /** Разметка и брендовый цвет иконки по сервису. Цвет одновременно — источник лёгкой подсветки строки */
    const SERVICE_META = {
        jira: {
            color: '#0052CC',
            svg:
                '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.571 11.513H0a5.218 5.218 0 0 0 ' +
                '5.232 5.215h2.13v2.057A5.215 5.215 0 0 0 12.575 24V12.518a1.005 1.005 0 0 0-1.004-1.005zm5.723' +
                '-5.756H5.736a5.215 5.215 0 0 0 5.215 5.215h2.129v2.058a5.218 5.218 0 0 0 5.215 5.214V6.758a1.001' +
                ' 1.001 0 0 0-1.001-1.001zM23.013 0H11.455a5.215 5.215 0 0 0 5.215 5.215h2.129v2.129A5.215 5.215' +
                ' 0 0 0 24 12.559V1.001A1.001 1.001 0 0 0 23.013 0z"/></svg>',
        },
        git: {
            color: '#F05032',
            svg:
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
                'stroke-linejoin="round"><circle cx="6" cy="6" r="2.4"/><circle cx="6" cy="18" r="2.4"/>' +
                '<circle cx="18" cy="6" r="2.4"/><path d="M6 8.4V15.6"/><path d="M8.4 6H14a4 4 0 0 1 4 4v0"/></svg>',
        },
        github: {
            color: '#8957E5',
            svg:
                '<svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 ' +
                '5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82' +
                '-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51' +
                '-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2' +
                '.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12' +
                '.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 ' +
                '.21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>',
        },
        claude: {
            color: '#D97757',
            svg:
                '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c.4 3.6 1.2 6.1 2.4 7.4 1.3 1.3 3.8 ' +
                '2.1 7.4 2.4-3.6.4-6.1 1.2-7.4 2.4-1.3 1.3-2.1 3.8-2.4 7.4-.4-3.6-1.2-6.1-2.4-7.4C8.3 12.9 5.8 ' +
                '12.1 2.2 11.8 5.8 11.4 8.3 10.6 9.6 9.4 10.9 8.1 11.7 5.6 12 2z"/></svg>',
        },
        telegram: {
            color: '#26A5E4',
            svg:
                '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 ' +
                '12 12-5.373 12-12S18.627 0 12 0zm5.568 8.16c-.169 1.858-.896 6.728-1.266 8.9-.156.918-.472 ' +
                '1.226-.804 1.257-.696.06-1.226-.406-1.899-.807-1.056-.63-1.653-1.02-2.673-1.632-1.184-.708-.417' +
                '-1.098.259-1.734.176-.168 3.239-2.964 3.298-3.216.007-.031.014-.147-.056-.208-.07-.061-.174-.04' +
                '-.249-.024-.106.023-1.793 1.146-5.062 3.369-.478.328-.913.489-1.301.481-.428-.009-1.252-.242' +
                '-1.865-.442-.751-.244-1.349-.373-1.297-.787.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 ' +
                '6.998-3.014 3.332-1.386 4.023-1.627 4.476-1.635.099-.002.321.023.465.14.121.098.153.23.171.322' +
                '.017.09.038.297.021.458z"/></svg>',
        },
        php: {
            color: '#777BB4',
            svg:
                '<svg viewBox="0 0 32 20"><rect width="32" height="20" rx="4" fill="currentColor"/>' +
                '<text x="16" y="14.5" font-family="Helvetica, Arial, sans-serif" font-size="11" ' +
                'font-weight="700" font-style="italic" fill="#fff" text-anchor="middle">php</text></svg>',
        },
    };

    /** Какой сервис относится к каждому пункту чек-листа (код → ключ SERVICE_META) */
    const ITEM_SERVICE = {
        story_points: 'jira',
        status_doing: 'jira',
        git_branch: 'git',
        code_written: 'php',
        pull_request: 'github',
        claude_review: 'claude',
        pr_description: 'github',
        jira_comment: 'jira',
        jira_description: 'jira',
        status_pull_request: 'jira',
        time_tracking: 'jira',
        send_pr: 'telegram',
    };

    /** Ключ localStorage — под ним хранится ссылка последней открытой задачи */
    const TASK_LINK_STORAGE_KEY = 'wcl_task_link';

    /** Ключ localStorage и лимит для списка последних открытых задач на экране ввода ссылки */
    const RECENT_TASKS_STORAGE_KEY = 'wcl_recent_tasks';
    const RECENT_TASKS_LIMIT = 10;

    /** Состояние текущей задачи и чек-листа */
    const state = {
        task: null,
        checklist: [],
    };

    // ==================== Утилиты ====================

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    function prLinkStorageKey() {
        return `wcl_pr_link_${state.task.id}`;
    }

    let toastTimer = null;

    function showToast(message) {
        toastEl.textContent = message;
        toastEl.classList.add('visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toastEl.classList.remove('visible'), 2200);
    }

    /** Уведомление о том, что именно скопировано в буфер обмена (единый формат для всех копирований) */
    function notifyCopied(description) {
        showToast(`Скопировано: ${description}`);
    }

    /** Копирует обычный текст в буфер обмена */
    async function copyText(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (e) {
            return false;
        }
    }

    /** Копирует текст с HTML-разметкой (сохраняет стиль при вставке в Jira/редакторы) */
    async function copyRichText(html, plain) {
        try {
            const item = new ClipboardItem({
                'text/html': new Blob([html], { type: 'text/html' }),
                'text/plain': new Blob([plain], { type: 'text/plain' }),
            });
            await navigator.clipboard.write([item]);
            return true;
        } catch (e) {
            return copyText(plain);
        }
    }

    async function apiCall(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok) {
            throw new Error(data.error || 'Ошибка запроса');
        }
        return data;
    }

    // ==================== Модальное окно ====================

    /**
     * Показывает модальное окно и возвращает Promise, который разрешается значением
     * нажатой кнопки. Кнопки с keepOpen выполняют действие, не закрывая окно
     * (используется для кнопки «Скопировать» рядом с подтверждением).
     * onRender(bodyEl) вызывается сразу после вставки bodyHtml — там можно навесить
     * свои обработчики на интерактивные элементы внутри тела модалки.
     */
    function showModal(title, bodyHtml, buttons, onRender) {
        return new Promise((resolve) => {
            modalTitleEl.textContent = title;
            modalBodyEl.innerHTML = bodyHtml;
            modalActionsEl.innerHTML = '';

            if (typeof onRender === 'function') {
                onRender(modalBodyEl);
            }

            function cleanup() {
                modalOverlay.classList.add('hidden');
                modalOverlay.removeEventListener('click', onOverlayClick);
            }

            function onOverlayClick(e) {
                if (e.target === modalOverlay) {
                    cleanup();
                    resolve(null);
                }
            }

            buttons.forEach((btn) => {
                const buttonEl = document.createElement('button');
                buttonEl.className = 'btn ' + (btn.primary ? 'btn-primary' : 'btn-secondary');
                buttonEl.textContent = btn.label;
                buttonEl.addEventListener('click', async () => {
                    if (btn.onClick) {
                        await btn.onClick();
                    }
                    if (btn.keepOpen) {
                        return;
                    }
                    const value = btn.getValue ? btn.getValue() : btn.value;
                    cleanup();
                    resolve(value);
                });
                modalActionsEl.appendChild(buttonEl);
            });

            modalOverlay.addEventListener('click', onOverlayClick);
            modalOverlay.classList.remove('hidden');

            const input = modalBodyEl.querySelector('input');
            if (input) {
                input.focus();
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        const primaryBtn = modalActionsEl.querySelector('.btn-primary');
                        if (primaryBtn) primaryBtn.click();
                    }
                });
            }
        });
    }

    /** Модальное окно с текстовым полем ввода. Возвращает введённую строку или null */
    function promptModal(title, placeholder) {
        return showModal(
            title,
            `<input type="text" class="input" placeholder="${escapeHtml(placeholder)}">`,
            [
                { label: 'Отмена', value: null },
                {
                    label: 'Сохранить',
                    primary: true,
                    getValue: () => modalBodyEl.querySelector('input').value.trim() || null,
                },
            ]
        );
    }

    // ==================== Тема оформления ====================

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('wcl_theme', theme);
    }

    function initTheme() {
        const saved = localStorage.getItem('wcl_theme');
        if (saved) {
            applyTheme(saved);
            return;
        }
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(prefersDark ? 'dark' : 'light');
    }

    function closePopovers() {
        themePopover.classList.add('hidden');
        gitActionsPopover.classList.add('hidden');
    }

    settingsBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        closePopovers();
        themePopover.classList.toggle('hidden');
    });
    document.addEventListener('click', closePopovers);
    themePopover.addEventListener('click', (e) => e.stopPropagation());
    themePopover.querySelectorAll('.theme-option').forEach((btn) => {
        btn.addEventListener('click', () => {
            applyTheme(btn.dataset.theme);
            themePopover.classList.add('hidden');
        });
    });

    // ==================== Дропдаун git-команд у названия ветки ====================

    /** Команды git по каждому пункту дропдауна (branch — текущая ветка задачи) */
    const GIT_ACTION_COMMANDS = {
        'create-branch': (branch) => `git checkout -b ${branch}`,
        push: (branch) => `git push origin ${branch}`,
        'rebase-api3': () => 'git rebase origin/main',
        'rebase-adminka': () => 'git rebase origin/dev',
    };

    gitActionsBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const wasHidden = gitActionsPopover.classList.contains('hidden');
        closePopovers();
        if (wasHidden) {
            gitActionsPopover.classList.remove('hidden');
        }
    });
    gitActionsPopover.addEventListener('click', (e) => e.stopPropagation());
    gitActionsPopover.querySelectorAll('.git-action-option').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const command = GIT_ACTION_COMMANDS[btn.dataset.action](state.task.git_branch);
            await copyText(command);
            notifyCopied(command);
            gitActionsPopover.classList.add('hidden');
        });
    });

    // ==================== Рендер экрана задачи ====================

    function renderGitBranch() {
        const hasBranch = Boolean(state.task.git_branch);
        gitActionsBtn.classList.toggle('hidden', !hasBranch);
        if (hasBranch) {
            gitBranchValue.textContent = state.task.git_branch;
            gitBranchValue.classList.remove('hidden');
        } else {
            gitBranchValue.classList.add('hidden');
        }
    }

    /**
     * Рендерит чек-лист. Выполненные пункты в списке не показываются — они уже улетели по
     * анимации. Работаем строго по очереди: активен и доступен для клика только первый
     * невыполненный пункт, остальные заблокированы (updateLockState расставляет классы).
     */
    function renderChecklist() {
        checklistEl.innerHTML = '';
        const pending = state.checklist.filter((item) => !item.is_done);

        if (pending.length === 0) {
            checklistEl.innerHTML =
                '<li class="checklist-empty">' +
                '<span class="checklist-empty-text">Все пункты выполнены</span>' +
                '<span class="checklist-empty-icon">👍</span>' +
                '</li>';
            renderProgress();
            return;
        }

        pending.forEach((item) => {
            const service = SERVICE_META[ITEM_SERVICE[item.code]];

            const li = document.createElement('li');
            li.className = 'checklist-item';
            li.dataset.checklistId = String(item.id);
            if (service) {
                li.style.setProperty('--service-color', service.color);
            }
            li.innerHTML =
                `<span class="checkbox">${CHECK_SVG}</span>` +
                `<span class="item-title">${escapeHtml(item.title)}</span>` +
                (service ? `<span class="service-icon" style="color: ${service.color}">${service.svg}</span>` : '');
            li.addEventListener('click', () => {
                // 'done' — на случай повторного клика в ~0.5с окне до улёта пункта: сама li уже
                // отмечена выполненной, но замыкание ниже всё ещё держит старый объект item
                // (state.checklist был заменён новым массивом после markDone), поэтому проверка
                // item.is_done внутри handleItemClick тут не сработает — нужна проверка по DOM.
                if (li.classList.contains('locked') || li.classList.contains('done')) return;
                handleItemClick(item);
            });
            checklistEl.appendChild(li);
        });
        updateLockState();
        renderProgress();
    }

    /** Только первый оставшийся пункт активен, все следующие — заблокированы */
    function updateLockState() {
        checklistEl.querySelectorAll('.checklist-item').forEach((li, index) => {
            li.classList.toggle('locked', index !== 0);
        });
    }

    /**
     * Анимация выполнения пункта: сначала проставляется галочка, затем, с небольшой
     * паузой, пункт плавно сворачивается и выезжает из списка, после чего удаляется из DOM.
     */
    function animateItemCompletion(checklistId) {
        const li = checklistEl.querySelector(`li[data-checklist-id="${checklistId}"]`);
        if (!li) {
            renderChecklist();
            return;
        }

        li.classList.add('done');

        setTimeout(() => {
            const height = li.getBoundingClientRect().height;
            li.style.maxHeight = `${height}px`;

            requestAnimationFrame(() => {
                li.classList.add('leaving');
                li.style.maxHeight = '0px';
                li.style.paddingTop = '0px';
                li.style.paddingBottom = '0px';
                li.style.marginTop = '0px';
                li.style.marginBottom = '0px';
            });

            const removeAndCheckEmpty = () => {
                li.remove();
                if (!checklistEl.querySelector('.checklist-item')) {
                    renderChecklist();
                } else {
                    updateLockState(); // следующий по очереди пункт становится активным
                }
            };
            li.addEventListener('transitionend', removeAndCheckEmpty, { once: true });
            setTimeout(removeAndCheckEmpty, 500); // страховка, если transitionend не сработает
        }, 550);
    }

    /** Отображает долю выполненных пунктов чек-листа зелёным прогресс-баром */
    function renderProgress() {
        const total = state.checklist.length;
        const done = state.checklist.filter((item) => item.is_done).length;
        const percent = total === 0 ? 0 : Math.round((done / total) * 100);

        progressFill.style.width = `${percent}%`;
        progressLabel.textContent = `${percent}%`;
    }

    function showTaskScreen() {
        linkError.classList.add('hidden');
        linkScreen.classList.add('hidden');
        taskScreen.classList.remove('hidden');
        taskIdLabel.textContent = state.task.task_id;
        renderGitBranch();
        renderChecklist();
    }

    function showLinkScreen() {
        state.task = null;
        state.checklist = [];
        localStorage.removeItem(TASK_LINK_STORAGE_KEY);
        taskScreen.classList.add('hidden');
        linkScreen.classList.remove('hidden');
        taskLinkInput.value = '';
        taskLinkInput.focus();
        renderRecentTasks();
    }

    // ==================== Последние открытые задачи (экран ввода ссылки) ====================

    function getRecentTasks() {
        try {
            const list = JSON.parse(localStorage.getItem(RECENT_TASKS_STORAGE_KEY));
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    /** Добавляет задачу в начало списка последних (без дублей, максимум RECENT_TASKS_LIMIT) */
    function rememberRecentTask(task) {
        const withoutCurrent = getRecentTasks().filter((t) => t.taskId !== task.task_id);
        withoutCurrent.unshift({ taskId: task.task_id, link: task.task_link });
        localStorage.setItem(
            RECENT_TASKS_STORAGE_KEY,
            JSON.stringify(withoutCurrent.slice(0, RECENT_TASKS_LIMIT))
        );
    }

    /** Рендерит список последних задач на экране ввода ссылки; клик по строке открывает задачу */
    function renderRecentTasks() {
        const tasks = getRecentTasks();
        if (tasks.length === 0) {
            recentTasksEl.classList.add('hidden');
            return;
        }

        recentTasksListEl.innerHTML = '';
        tasks.forEach((task) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'recent-task-item';
            btn.innerHTML =
                `<span class="recent-task-code">${escapeHtml(task.taskId)}</span>` +
                '<span class="recent-task-percent"></span>';
            btn.addEventListener('click', () => loadTask(task.link));
            recentTasksListEl.appendChild(btn);

            // Процент выполнения подгружается отдельно и необязателен для самого открытия
            // задачи — ошибка (например, задача удалена из БД) просто оставит поле пустым.
            apiCall('../api/state.php', { link: task.link })
                .then((data) => {
                    const total = data.checklist.length;
                    const done = data.checklist.filter((item) => item.is_done).length;
                    const percent = total === 0 ? 0 : Math.round((done / total) * 100);
                    btn.querySelector('.recent-task-percent').textContent = `${percent}%`;
                })
                .catch(() => {});
        });
        recentTasksEl.classList.remove('hidden');
    }

    // ==================== Загрузка / обновление задачи ====================

    function applyTaskState(data, link) {
        state.task = data.task;
        state.checklist = data.checklist;
        localStorage.setItem(TASK_LINK_STORAGE_KEY, link);
        rememberRecentTask(data.task);
        showTaskScreen();
    }

    /**
     * Пользователь явно вставил ссылку на экране ввода — находит либо создаёт задачу.
     * Для уже существующей задачи применяется бизнес-правило сброса чек-листа
     * (см. ChecklistRepository::resetOnReopen) — это осознанное «переоткрытие» задачи.
     */
    async function loadTask(link) {
        try {
            const data = await apiCall('../api/task.php', { link });
            applyTaskState(data, link);
        } catch (e) {
            linkScreen.classList.remove('hidden');
            linkError.textContent = e.message;
            linkError.classList.remove('hidden');
        }
    }

    /**
     * Восстанавливает уже открытую задачу после обновления страницы (ссылка из localStorage).
     * В отличие от loadTask — НЕ переоткрытие, поэтому чек-лист не сбрасывается: читаем
     * текущее состояние как есть через отдельный read-only эндпоинт api/state.php.
     */
    async function restoreTask(link) {
        try {
            const data = await apiCall('../api/state.php', { link });
            applyTaskState(data, link);
        } catch (e) {
            // Сохранённая ссылка больше не актуальна (например, БД была очищена) — просто
            // показываем экран ввода, без сообщения об ошибке (это не действие пользователя)
            showLinkScreen();
        }
    }

    /** Отмечает пункт чек-листа выполненным (опционально передаёт доп. данные, например ветку) */
    async function markDone(checklistId, extra = {}) {
        const data = await apiCall('../api/toggle.php', {
            task_id: state.task.id,
            checklist_id: checklistId,
            done: true,
            ...extra,
        });
        state.checklist = data.checklist;
        if (data.task) {
            state.task = data.task;
        }
        renderProgress();
        renderGitBranch();
        animateItemCompletion(checklistId);
    }

    // ==================== Поведение пунктов чек-листа ====================
    // Ключ — стабильный code пункта (см. Database::CHECKLIST_ITEMS), а не порядковый номер:
    // так добавление/удаление/переупорядочивание пунктов не требует правок здесь.

    const ITEM_HANDLERS = {
        // Story Points указано — отмечается сразу
        story_points: (item) => markDone(item.id),

        // Статус сменен на Doing — отмечается сразу
        status_doing: (item) => markDone(item.id),

        // Создать ветку в Git — запросить название, скопировать, сохранить в задаче
        git_branch: async (item) => {
            const branch = await promptModal('Название ветки', 'например feature/PROJ-123-описание');
            if (!branch) return;
            const copied = await copyText(branch);
            await markDone(item.id, { branch });
            if (copied) {
                notifyCopied(`название ветки «${branch}»`);
            } else {
                showToast('Ветка сохранена, но не скопирована');
            }
        },

        // Код написан — показать команду git push (та же, что в дропдауне «Push»), отметить по копированию
        code_written: async (item) => {
            const command = GIT_ACTION_COMMANDS.push(state.task.git_branch);
            const confirmed = await showModal(
                'Код написан',
                `<div class="snippet">${escapeHtml(command)}</div>`,
                [
                    { label: 'Отмена', value: false },
                    { label: 'Скопировать', primary: true, value: true },
                ]
            );
            if (confirmed) {
                await copyText(command);
                await markDone(item.id);
                notifyCopied(`команда «${command}»`);
            }
        },

        // Создать Pull Request — запросить ссылку, сохранить для пункта «Отправить PR в ЛС»
        pull_request: async (item) => {
            const link = await promptModal('Ссылка на Pull Request', 'https://github.com/...');
            if (!link) return;
            sessionStorage.setItem(prLinkStorageKey(), link);
            await markDone(item.id);
        },

        // Проверить PR через Claude — скопировать шаблон промпта со ссылкой на PR из шага «PR создан»,
        // отметка пункта выполненным отдельной кнопкой (копирование можно повторять, не завершая пункт)
        claude_review: async (item) => {
            const reviewText = buildClaudeReviewText(sessionStorage.getItem(prLinkStorageKey()));
            const confirmed = await showModal(
                'Проверка PR через Claude',
                `<div class="snippet">${escapeHtml(reviewText)}</div>`,
                [
                    { label: 'Отмена', value: false },
                    {
                        label: 'Скопировать',
                        keepOpen: true,
                        onClick: async () => {
                            await copyText(reviewText);
                            notifyCopied('промпт для ревью Claude');
                        },
                    },
                    { label: 'Завершить', primary: true, value: true },
                ]
            );
            if (confirmed) {
                await markDone(item.id);
            }
        },

        // Заполнить описание PR — чек-лист из 3 шагов, отметить по подтверждению
        pr_description: async (item) => {
            const confirmed = await showModal(
                'Заполнение описания PR',
                `<div class="pr-steps">
                     <div class="pr-step pr-step--action" id="pr-copy-link-step">
                         <span class="pr-step-num">1</span>
                         <span class="pr-step-text">Скопировать ссылку на задачу</span>
                         <span class="pr-step-icon">${COPY_SVG}</span>
                     </div>
                     <div class="pr-step">
                         <span class="pr-step-num">2</span>
                         <span class="pr-step-text">Заасайните PR на себя</span>
                     </div>
                     <div class="pr-step">
                         <span class="pr-step-num">3</span>
                         <span class="pr-step-text">Укажите в PR своего техлида и ревьювера</span>
                     </div>
                 </div>`,
                [
                    { label: 'Отмена', value: false },
                    { label: 'Готово', primary: true, value: true },
                ],
                (bodyEl) => {
                    bodyEl.querySelector('#pr-copy-link-step').addEventListener('click', async () => {
                        await copyText(state.task.task_link);
                        notifyCopied(`ссылка на задачу «${state.task.task_link}»`);
                    });
                }
            );
            if (confirmed) {
                await markDone(item.id);
            }
        },

        // Оставить коммент в Jira — скопировать шаблон текста со ссылкой на PR из шага «PR создан»
        jira_comment: async (item) => {
            const commentText = buildJiraCommentText(sessionStorage.getItem(prLinkStorageKey()));
            const confirmed = await showModal(
                'Комментарий в Jira',
                `<div class="snippet">${escapeHtml(commentText)}</div>`,
                [
                    { label: 'Отмена', value: false },
                    { label: 'Скопировать', primary: true, value: true },
                ]
            );
            if (confirmed) {
                await copyText(commentText);
                await markDone(item.id);
                notifyCopied('комментарий для Jira');
            }
        },

        // Оставить описание в Jira — скопировать текст с HTML-разметкой (сохраняет стиль)
        jira_description: async (item) => {
            const confirmed = await showModal(
                'Описание в Jira',
                `<div class="snippet" style="font-family: inherit;">${JIRA_DESCRIPTION_HTML}</div>`,
                [
                    { label: 'Отмена', value: false },
                    { label: 'Скопировать', primary: true, value: true },
                ]
            );
            if (confirmed) {
                await copyRichText(JIRA_DESCRIPTION_HTML, JIRA_DESCRIPTION_PLAIN);
                await markDone(item.id);
                notifyCopied('описание для Jira (с форматированием)');
            }
        },

        // Задача переведена в Pull Request — отмечается сразу
        status_pull_request: (item) => markDone(item.id),

        // Затрекать время в Jira — отмечается сразу
        time_tracking: (item) => markDone(item.id),

        // Отправить PR в ЛС — скопировать ранее сохранённую ссылку на PR
        send_pr: async (item) => {
            const link = sessionStorage.getItem(prLinkStorageKey());
            if (link) {
                await copyText(link);
                notifyCopied(`ссылка на PR «${link}»`);
            } else {
                showToast('Ссылка на PR не найдена — скопируйте вручную');
            }
            await markDone(item.id);
        },
    };

    function handleItemClick(item) {
        if (item.is_done) {
            return;
        }
        const handler = ITEM_HANDLERS[item.code];
        if (handler) {
            handler(item);
        }
    }

    // ==================== Обработчики верхнего уровня ====================

    taskLinkInput.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        const link = taskLinkInput.value.trim();
        if (!link) return;
        loadTask(link);
    });

    changeTaskBtn.addEventListener('click', showLinkScreen);

    finishTaskBtn.addEventListener('click', async () => {
        const data = await apiCall('../api/finish.php', { task_id: state.task.id });
        state.checklist = data.checklist;
        sessionStorage.removeItem(prLinkStorageKey());
        renderChecklist();
        showToast('Чек-лист сброшен');
    });

    gitBranchValue.addEventListener('click', async () => {
        const copied = await copyText(state.task.git_branch);
        if (copied) {
            notifyCopied(`название ветки «${state.task.git_branch}»`);
        } else {
            showToast('Не удалось скопировать');
        }
    });

    // ==================== Инициализация ====================

    initTheme();

    const savedLink = localStorage.getItem(TASK_LINK_STORAGE_KEY);
    if (savedLink) {
        linkScreen.classList.add('hidden'); // прячем экран ввода на время подгрузки сохранённой задачи
        restoreTask(savedLink);
    } else {
        showLinkScreen();
    }
})();
