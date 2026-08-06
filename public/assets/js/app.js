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

    /** Ключ localStorage — под ним хранится ссылка последней открытой задачи */
    const TASK_LINK_STORAGE_KEY = 'wcl_task_link';

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

    /** Рендерит чек-лист. Выполненные пункты в списке не показываются — они уже улетели по анимации */
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
            const li = document.createElement('li');
            li.className = 'checklist-item';
            li.dataset.checklistId = String(item.id);
            li.innerHTML =
                `<span class="checkbox">${CHECK_SVG}</span>` +
                `<span class="item-title">${escapeHtml(item.title)}</span>`;
            li.addEventListener('click', () => handleItemClick(item));
            checklistEl.appendChild(li);
        });
        renderProgress();
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
    }

    // ==================== Загрузка / обновление задачи ====================

    function applyTaskState(data, link) {
        state.task = data.task;
        state.checklist = data.checklist;
        localStorage.setItem(TASK_LINK_STORAGE_KEY, link);
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

        // Создать Pull Request — запросить ссылку, сохранить для пункта «Отправить PR в ЛС»
        pull_request: async (item) => {
            const link = await promptModal('Ссылка на Pull Request', 'https://github.com/...');
            if (!link) return;
            sessionStorage.setItem(prLinkStorageKey(), link);
            await markDone(item.id);
        },

        // Проверить PR через Claude — отмечается сразу
        claude_review: (item) => markDone(item.id),

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

        // Оставить коммент в Jira — скопировать шаблон текста
        jira_comment: async (item) => {
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
