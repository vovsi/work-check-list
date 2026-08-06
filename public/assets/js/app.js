// Work Check List — вся клиентская логика одностраничного приложения
(() => {
    'use strict';

    // ==================== Тексты для копирования (пункты 6 и 7) ====================

    const JIRA_COMMENT_TEXT =
        'Для выливки необходимо:\n' +
        '1. Запустить скрипты БД:\n```\n\n```\n' +
        '2. Добавить в конфиг апи3:\n```\n\n```\n' +
        '3. Вылить:';

    const JIRA_DESCRIPTION_HTML =
        '<b> Results</b><br/>1. <br/>' +
        '<b> Testing</b><br/>1. <br/>' +
        '<b> Pull Requests</b><br/>1. <br/>';

    const JIRA_DESCRIPTION_PLAIN = 'Results\n1. \n\nTesting\n1. \n\nPull Requests\n1. ';

    // ==================== DOM-элементы ====================

    const linkScreen = document.getElementById('link-screen');
    const taskScreen = document.getElementById('task-screen');
    const taskLinkInput = document.getElementById('task-link-input');
    const linkError = document.getElementById('link-error');
    const taskIdLabel = document.getElementById('task-id-label');
    const changeTaskBtn = document.getElementById('change-task-btn');
    const checklistEl = document.getElementById('checklist');
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
        toastTimer = setTimeout(() => toastEl.classList.remove('visible'), 1800);
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
     */
    function showModal(title, bodyHtml, buttons) {
        return new Promise((resolve) => {
            modalTitleEl.textContent = title;
            modalBodyEl.innerHTML = bodyHtml;
            modalActionsEl.innerHTML = '';

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
            showToast(`Скопировано: ${command}`);
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

    function renderChecklist() {
        checklistEl.innerHTML = '';
        state.checklist.forEach((item) => {
            const li = document.createElement('li');
            li.className = 'checklist-item' + (item.is_done ? ' done' : '');
            li.innerHTML =
                `<span class="checkbox">${CHECK_SVG}</span>` +
                `<span class="item-title">${escapeHtml(item.title)}</span>`;
            li.addEventListener('click', () => handleItemClick(item));
            checklistEl.appendChild(li);
        });
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
        taskScreen.classList.add('hidden');
        linkScreen.classList.remove('hidden');
        taskLinkInput.value = '';
        taskLinkInput.focus();
    }

    // ==================== Загрузка / обновление задачи ====================

    async function loadTask(link) {
        try {
            const data = await apiCall('../api/task.php', { link });
            state.task = data.task;
            state.checklist = data.checklist;
            showTaskScreen();
        } catch (e) {
            linkError.textContent = e.message;
            linkError.classList.remove('hidden');
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
        renderChecklist();
        renderGitBranch();
    }

    // ==================== Поведение пунктов чек-листа 1-9 ====================

    const ITEM_HANDLERS = {
        // 1. Указать Story Points — отмечается сразу
        1: (item) => markDone(item.id),

        // 2. Создать ветку в Git — запросить название, скопировать, сохранить в задаче
        2: async (item) => {
            const branch = await promptModal('Название ветки', 'например feature/PROJ-123-описание');
            if (!branch) return;
            const copied = await copyText(branch);
            await markDone(item.id, { branch });
            showToast(copied ? 'Ветка скопирована в буфер обмена' : 'Ветка сохранена');
        },

        // 3. Создать Pull Request — запросить ссылку, сохранить для пункта 9
        3: async (item) => {
            const link = await promptModal('Ссылка на Pull Request', 'https://github.com/...');
            if (!link) return;
            sessionStorage.setItem(prLinkStorageKey(), link);
            await markDone(item.id);
        },

        // 4. Проверить PR через Claude — отмечается сразу
        4: (item) => markDone(item.id),

        // 5. Заполнить описание PR — показать номер задачи и подсказку, отметить по подтверждению
        5: async (item) => {
            const confirmed = await showModal(
                'Заполнение описания PR',
                `<div>Номер задачи:</div>
                 <div class="snippet">${escapeHtml(state.task.task_id)}</div>
                 <div class="hint">Укажите техлида, ревьювера и заасайните на себя ПР</div>`,
                [
                    {
                        label: 'Скопировать номер',
                        keepOpen: true,
                        onClick: async () => {
                            await copyText(state.task.task_id);
                            showToast('Номер задачи скопирован');
                        },
                    },
                    { label: 'Отмена', value: false },
                    { label: 'Подтвердить', primary: true, value: true },
                ]
            );
            if (confirmed) {
                await markDone(item.id);
            }
        },

        // 6. Оставить коммент в Jira — скопировать шаблон текста
        6: async (item) => {
            const confirmed = await showModal(
                'Комментарий в Jira',
                `<div class="snippet">${escapeHtml(JIRA_COMMENT_TEXT)}</div>`,
                [
                    { label: 'Отмена', value: false },
                    { label: 'Скопировать', primary: true, value: true },
                ]
            );
            if (confirmed) {
                await copyText(JIRA_COMMENT_TEXT);
                await markDone(item.id);
                showToast('Текст скопирован в буфер обмена');
            }
        },

        // 7. Оставить описание в Jira — скопировать текст с HTML-разметкой (сохраняет стиль)
        7: async (item) => {
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
                showToast('Текст скопирован (со стилем)');
            }
        },

        // 8. Затрекать время в Jira — отмечается сразу
        8: (item) => markDone(item.id),

        // 9. Отправить PR в ЛС — скопировать ранее сохранённую ссылку на PR
        9: async (item) => {
            const link = sessionStorage.getItem(prLinkStorageKey());
            if (link) {
                await copyText(link);
                showToast('Ссылка на PR скопирована');
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
        const handler = ITEM_HANDLERS[item.id];
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
        showToast(copied ? 'Ветка скопирована' : 'Не удалось скопировать');
    });

    // ==================== Инициализация ====================

    initTheme();
    showLinkScreen();
})();
