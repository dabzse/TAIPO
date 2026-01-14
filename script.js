let draggedId = null;
let currentOpenTaskId = null;

function loadProject(encodedProjectName) {
    if (encodedProjectName) {
        window.location.href = `index.php?project=${encodedProjectName}`;
    }
}

function createSafeId(title) {
    let safeTitle = title.toLowerCase();

    safeTitle = safeTitle
        .replace(/á/g, 'a').replace(/é/g, 'e').replace(/í/g, 'i')
        .replace(/ó/g, 'o').replace(/ö/g, 'o').replace(/ő/g, 'o')
        .replace(/ú/g, 'u').replace(/ü/g, 'u').replace(/ű/g, 'u')
        .replace(/ /g, '_');

    return safeTitle.replace(/[^a-z0-9_]/g, '');
}

function createTaskCard(task) {
    const newCard = document.createElement('div');
    newCard.className = 'task-card';
    newCard.setAttribute('draggable', 'true');
    newCard.setAttribute('ondragstart', 'drag(event)');
    newCard.id = 'task-' + task.id;

    const safeDescription = task.description.replace(/'/g, "\\'").replace(/"/g, '\\"');

    newCard.innerHTML =
        `<div class="task-card-header">
            <button class="task-menu-toggle" title="Beállítások" onclick="toggleTaskMenu(${task.id}, this)">⋮</button>
            
            <div id="task-menu-${task.id}" class="task-actions-menu">
                <button class="menu-action-button" title="Feladat szerkesztése" onclick="toggleEdit(${task.id}, event)">✏️ Szerkesztés</button>
                <button class="menu-action-button" title="Java Kód generálása" onclick="generateJavaCodeModal(${task.id}, '${safeDescription}')">💻 Kód generálása</button>
                <button class="menu-action-button delete-action" title="Feladat törlése" onclick="deleteTask(${task.id}, event)">🗑️ Törlés</button>
            </div>
        </div>
        
        <p class="card-description" id="desc-${task.id}" contenteditable="false" data-original-content="${task.description}">
            ${task.description}
        </p>`;

    return newCard;
}

function drag(ev) {
    const card = ev.target.closest('.task-card');
    if (card) {
        draggedId = card.id;
        ev.dataTransfer.setData("text/plain", draggedId);
        card.style.opacity = '0.6';
    }
}

function allowDrop(ev) {
    ev.preventDefault();
}

function drop(ev) {
    ev.preventDefault();

    let targetColumn = ev.target.closest('.kanban-column');

    if (targetColumn) {
        let targetStatus = targetColumn.getAttribute('data-status');
        let draggedElement = document.getElementById(draggedId);

        if (draggedElement) {
            const sourceColumn = draggedElement.closest('.kanban-column');
            const oldStatus = sourceColumn ? sourceColumn.getAttribute('data-status') : null;

            if (oldStatus === targetStatus) {
                draggedElement.style.opacity = '1';
                return;
            }
            const targetList = targetColumn.querySelector('.task-list');

            const placeholder = targetList.querySelector('.empty-placeholder');
            if (placeholder) {
                placeholder.remove();
            }

            targetList.appendChild(draggedElement);
            let taskId = draggedId.replace('task-', '');

            updateTaskStatus(taskId, targetStatus, oldStatus);
            window.location.reload();

        }
    }
}

function updateTaskStatus(taskId, newStatus, oldStatus) {
    if (!oldStatus || oldStatus === newStatus) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('task_id', taskId);
    formData.append('new_status', newStatus);

    // KULCS: Ez szükséges a WIP ellenőrzéshez és a számláló visszamozgatásához
    formData.append('old_status', oldStatus);
    formData.append('current_project', window.currentProjectName);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                updateCount(newStatus, -1);
                updateCount(oldStatus, 1);

                const originalCard = document.getElementById(`task-${taskId}`);
                const oldColumnList = document.querySelector(`#col-${createSafeId(oldStatus)}`);
                if (originalCard && oldColumnList) {
                    oldColumnList.appendChild(originalCard);
                }

                checkAndInsertPlaceholder(newStatus);
                checkAndInsertPlaceholder(oldStatus);
                window.location.reload();

                return response.text().then(text => {
                    alert('Hiba történt a szerver oldalon a státusz frissítésekor: ' + text.substring(0, 100) + '... (A kártya visszaállt.)');
                    throw new Error(text);
                });
            }
            return response.text();
        })
        .then(() => { // SIKERES FRISSÍTÉS ESETÉN
            updateCount(oldStatus, -1);
            updateCount(newStatus, 1);
            checkAndInsertPlaceholder(oldStatus); // Ez így jó, ellenőrzi, hogy beszúr-e helytartót, ha a régi oszlop üres lett.
            window.location.reload();
        })
        .catch(error => {
            console.error('Hiba a státusz frissítésekor:', error);
        })
        .finally(() => {
            const card = document.getElementById(`task-${taskId}`);
            if (card) {
                card.style.opacity = '1';
            }
        });
}

// script.js (a createTaskCard függvény hiányzik az Ön által adott kódban, de a feltételezett kód alapján)
function checkAndInsertPlaceholder(status) {
    const column = document.querySelector(`[data-status="${status}"]`);
    if (column) {
        const taskList = column.querySelector('.task-list');
        // JAVÍTÁS: Számolni kell az ELTÁVOLÍTOTT kártyákat is a hibaágon!
        if (taskList.querySelectorAll('.task-card:not(.empty-placeholder)').length === 0) {
            taskList.innerHTML = '<div class="task-card empty-placeholder"><p class="card-description" style="color: #6c757d; font-style: italic;">Nincsenek feladatok ebben az oszlopban.</p></div>';
        }
    }
}
function updateCount(status, delta) {
    const safeStatusId = createSafeId(status);
    const countSpan = document.getElementById(`count-${safeStatusId}`);
    if (countSpan) {
        let currentCount = parseInt(countSpan.textContent) || 0;
        countSpan.textContent = Math.max(0, currentCount + delta);
        window.location.reload();

    }
}

function toggleTaskInput() {
    const form = document.getElementById('addTaskInputForm');
    const toggleButton = document.getElementById('addTaskToggle');

    if (form.style.display === 'none') {
        form.style.display = 'flex';
        toggleButton.textContent = '✖️';
        toggleButton.classList.add('active');
        document.getElementById('inline_task_description').focus();
    } else {
        form.style.display = 'none';
        toggleButton.textContent = '➕';
        toggleButton.classList.remove('active');
    }
}
function toggleTaskMenu(taskId, buttonElement) {
    const menu = document.getElementById(`task-menu-${taskId}`);
    if (menu) {
        document.querySelectorAll('.task-actions-menu.active').forEach(openMenu => {
            if (openMenu.id !== menu.id) {
                openMenu.classList.remove('active');
                const toggleButton = openMenu.closest('.task-card').querySelector('.task-menu-toggle');
                if (toggleButton) toggleButton.textContent = '...';
            }
        });

        menu.classList.toggle('active');

        if (menu.classList.contains('active')) {
            buttonElement.textContent = '✖';
        } else {
            buttonElement.textContent = '⋮';
        }
    }
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.task-card') && !e.target.closest('.task-menu-toggle')) {
        document.querySelectorAll('.task-actions-menu.active').forEach(menu => {
            menu.classList.remove('active');
            const toggleButton = menu.closest('.task-card').querySelector('.task-menu-toggle');
            if (toggleButton) toggleButton.textContent = '...';
        });
    }
});

function addTask(isInline = true) {
    const descriptionInput = isInline
        ? document.getElementById('inline_task_description')
        : document.getElementById('new_task_description');

    const newDescription = descriptionInput ? descriptionInput.value.trim() : '';
    const currentProjectName = window.currentProjectName;

    if (!newDescription || !currentProjectName) {
        alert('Kérlek, add meg a feladat leírását, és győződj meg róla, hogy egy projekt be van töltve!');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_task');
    formData.append('description', newDescription);
    formData.append('current_project', currentProjectName);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errorData => {
                    throw new Error(errorData.error || 'Ismeretlen szerverhiba');
                }).catch(() => {
                    throw new Error('Hálózati hiba: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // A kártya létrehozása a szervertől kapott ID-val és leírással
                const newTask = { id: data.id, description: data.description };
                const newCard = createTaskCard(newTask); // <<-- AZ ÖN LÉTEZŐ FÜGGVÉNYE HASZNÁLVA!

                const targetList = document.querySelector('#col-' + createSafeId('SPRINTBACKLOG'));

                if (targetList) {
                    // A helytartó (placeholder) eltávolítása, ha létezik
                    const placeholder = targetList.querySelector('.empty-placeholder');
                    if (placeholder) {
                        placeholder.remove();
                    }

                    targetList.appendChild(newCard); // Kártya beszúrása a DOM-ba
                    updateCount('SPRINTBACKLOG', 1); // Számláló frissítése
                    window.location.reload();

                }

                if (descriptionInput) {
                    descriptionInput.value = '';
                }

            } else {
                alert('Hiba a feladat hozzáadása során. (Sikertelen JSON válasz)');
            }
        })
        .catch(error => {
            console.error('[ADD TASK] Hiba a hozzáadáskor:', error);
            alert('Hiba történt a feladat hozzáadása során: ' + error.message);
        });
}

function deleteTask(taskId, status, description) {
    // 1. Megerősítés a leírással
    if (!confirm(`Biztosan törölni szeretné a következő feladatot: "${description}" (ID: ${taskId})?`)) {
        return;
    }

    const currentProjectName = window.currentProjectName;
    if (!currentProjectName) {
        alert('Nincs projekt betöltve.');
        return;
    }

    // A kártya megtalálása a helyes ID formátummal
    const card = document.getElementById('task-' + taskId); 

    const formData = new FormData();
    formData.append('action', 'delete_task');
    formData.append('task_id', taskId);
    formData.append('current_project', currentProjectName);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(errorData => {
                throw new Error(errorData.error || 'Ismeretlen szerverhiba');
            }).catch(() => {
                throw new Error('Hálózati hiba: ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        // A data.status már nem kell a szerver válaszából, mert az index.php-ből kapott 'status' paramétert használjuk.
        if (data.success) { 
            if (card) {
                // 2. Kártya eltávolítása a DOM-ból
                card.remove();

                // 3. Oszlop számláló frissítése (a paraméterként kapott, helyes státusszal)
                updateCount(status, -1);

                // 4. Ellenőrzés, hogy kell-e helytartót beszúrni az üres oszlopba
                checkAndInsertPlaceholder(status);
                window.location.reload();
            } else {
                 console.error(`[DELETE TASK] Hiba: A kártya (task-${taskId}) nem található a DOM-ban.`);
            }
        } else {
            alert('Hiba a feladat törlése során. (Sikertelen JSON válasz)');
        }
    })
    .catch(error => {
        console.error('[DELETE TASK] Hiba a törléskor:', error);
        alert('Hiba történt a feladat törlése során: ' + error.message);
    });
}

function toggleDarkMode() {
    const body = document.body;
    const isDarkMode = body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', isDarkMode ? 'enabled' : 'disabled');
    updateToggleIcon(isDarkMode);
}

function updateToggleIcon(isDarkMode) {
    const icon = document.getElementById('mode-toggle-icon');
    if (icon) {
        icon.textContent = isDarkMode ? '☀️' : '🌙';
        icon.title = isDarkMode ? 'Váltás világos módra' : 'Váltás sötét módra';
    }
}

let isEditing = {};

function toggleEdit(taskId, ev) {
    if (ev) ev.stopPropagation();

    const currentMenu = document.getElementById(`task-menu-${taskId}`);
    if (currentMenu) currentMenu.classList.remove('active');

    const descElement = document.getElementById(`desc-${taskId}`);
    const editButtonInMenu = currentMenu ? currentMenu.querySelector('[title="Feladat szerkesztése"]') : null;

    if (!descElement || !editButtonInMenu) return;

    if (descElement.getAttribute('contenteditable') === 'true') {
        const newDescription = descElement.textContent.trim();
        const originalContent = descElement.dataset.originalContent.trim();

        if (newDescription === originalContent) {
            cancelEdit(taskId);
            return;
        }

        if (newDescription === "") {
            alert("A feladat leírása nem lehet üres!");
            descElement.textContent = originalContent;
            return;
        }

        editTask(taskId, newDescription)
            .then(success => {
                if (success) {
                } else {
                    descElement.textContent = originalContent;
                }
            });

    } else {
        if (Object.keys(isEditing).length > 0) {
            alert("Kérlek, fejezd be az aktuális feladat szerkesztését, mielőtt másikat kezdenél!");
            return;
        }

        descElement.setAttribute('contenteditable', 'true');
        descElement.classList.add('editing');

        editButtonInMenu.textContent = '💾 Mentés (Enter)';

        descElement.focus();
        document.execCommand('selectAll', false, null);
        document.getSelection().collapseToEnd();

        isEditing[taskId] = true;

        descElement.onkeydown = function (e) {
            if (e.key === "Escape") {
                cancelEdit(taskId);
                e.preventDefault();
            } else if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                toggleEdit(taskId);
            }
        };
    }
}

function cancelEdit(taskId) {
    const descElement = document.getElementById(`desc-${taskId}`);
    const currentMenu = document.getElementById(`task-menu-${taskId}`);
    const editButtonInMenu = currentMenu ? currentMenu.querySelector('[title="Feladat szerkesztése"]') : null;

    descElement.textContent = descElement.dataset.originalContent;

    descElement.setAttribute('contenteditable', 'false');
    descElement.classList.remove('editing');

    if (editButtonInMenu) editButtonInMenu.textContent = '✏️ Szerkesztés';

    descElement.onkeydown = null;
    delete isEditing[taskId];
}

function editTask(taskId, newDescription) {
    const formData = new FormData();
    formData.append('action', 'edit_task');
    formData.append('task_id', taskId);
    formData.append('description', newDescription);

    return fetch('index.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                return response.json().then(error => {
                    alert(`Hiba történt a feladat mentésekor: ${error.error || 'Ismeretlen hiba'}`);
                    return false;
                });
            }
            return response.json().then(data => data.success);
        })
        .catch(error => {
            console.error('Hiba a szerkesztés során:', error);
            alert('Hálózati hiba történt a feladat mentésekor.');
            return false;
        });
}

function toggleMenu() {
    const dropdown = document.getElementById('projectDropdown');
    dropdown.classList.toggle('active');
}

function openGithubLoginModal() {
    const modal = document.getElementById('githubLoginModal');
    const repoInput = document.getElementById('github_repo_input');

    const storedRepo = sessionStorage.getItem('githubRepo');
    if (repoInput && storedRepo) {
        repoInput.value = storedRepo;
    }
    if (modal) {
        document.getElementById('github_pat').value = '';
        modal.style.display = 'flex';
    }
}

function closeGithubLoginModal() {
    document.getElementById('githubLoginModal').style.display = 'none';
    updateModalGithubStatus();
}

function updateModalGithubStatus() {
    const statusDiv = document.getElementById('modalGithubStatus');
    const isUserLoggedIn = sessionStorage.getItem('githubToken') !== null;

    if (statusDiv) {
        let message = '';
        if (isUserLoggedIn) {
            message = "✅ Sikeresen mentett token! Commitolhatsz a saját fiókoddal. (Jelszó nincs tárolva)";
            statusDiv.style.color = '#28a745';
        } else {
            message = "🔐 Kérlek, add meg a PAT tokent a commitoláshoz.";
            statusDiv.style.color = '#ffc107';
        }

        if (!window.isGitHubRepoConfigured && !isUserLoggedIn) {
            message = "⚠️ HIBA: A szerver oldali repo adatok hiányoznak. A commit nem fog működni.";
            statusDiv.style.color = '#dc3545';
        }
        statusDiv.innerHTML = message;
    }
}

function githubLogin() {
    const tokenInput = document.getElementById('github_pat');
    const usernameInput = document.getElementById('github_username_input');
    const repoInput = document.getElementById('github_repo_input');

    const statusDiv = document.getElementById('modalGithubStatus');

    const token = tokenInput ? tokenInput.value.trim() : '';
    const username = usernameInput ? usernameInput.value.trim() : '';
    const repo = repoInput ? repoInput.value.trim() : '';

    if (token === '' || username === '' || repo === '') {
        statusDiv.innerHTML = "❌ HIBA: Kérlek, add meg mind a GitHub felhasználónevedet, a Repó nevét, és a Personal Access Token-t.";
        statusDiv.style.color = '#dc3545';
        statusDiv.style.borderColor = '#dc3545';
        return;
    }

    sessionStorage.setItem('githubToken', token);
    sessionStorage.setItem('githubUsername', username);
    sessionStorage.setItem('githubRepo', repo);

    statusDiv.innerHTML = "✅ Sikeres mentés! A token és a repó mentve.";
    statusDiv.style.color = '#28a745';
    statusDiv.style.borderColor = '#28a745';

    setTimeout(() => {
        closeGithubLoginModal();
    }, 1500);
}
document.addEventListener('DOMContentLoaded', () => {
    updateModalGithubStatus();
});

function handleProjectFormSubmission(event) {
    const projectNameInput = document.getElementById('project_name');
    const promptTextarea = document.getElementById('ai_prompt');
    const mainModal = document.getElementById('mainGenerationModal');

    const projectName = projectNameInput.value.trim();

    if (projectName === '' || promptTextarea.value.trim() === '') {
        return true;
    }

    document.getElementById('generatingProjectNamePlaceholder').textContent = projectName;
    mainModal.style.display = 'flex';
    document.getElementById('generateButton').disabled = true;

    return true;
}

async function generateJavaCodeModal(taskId, description) {
    const javaCodeModal = document.getElementById('javaCodeModal');
    if (!javaCodeModal) return;

    window.currentOpenTaskId = taskId;
    const currentMenu = document.getElementById(`task-menu-${taskId}`);
    if (currentMenu) currentMenu.classList.remove('active');

    const taskDescElement = document.getElementById('javaModalTaskDesc');
    const resultContainer = document.getElementById('javaCodeResultContainer');
    const loadingIndicator = document.getElementById('javaCodeLoadingIndicator');

    if (taskDescElement) {
        taskDescElement.textContent = description;
    } else {
        console.warn("Hiányzik a javaModalTaskDesc DOM elem. Folytatás...");
    }

    javaCodeModal.style.display = 'flex';
    resultContainer.innerHTML = 'Kód generálása folyamatban...';
    loadingIndicator.style.display = 'block';

    const userToken = sessionStorage.getItem('githubToken') || '';
    const userUsername = sessionStorage.getItem('githubUsername') || '';
    const userRepo = sessionStorage.getItem('githubRepo') || '';

    try {
        const response = await fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate_java_code',
                task_id: taskId,
                description: description,
                user_token: userToken,
                user_username: userUsername,
                user_repo: userRepo,
            })
        });

        const data = await response.json();

        if (data.success) {
            resultContainer.innerHTML = data.code;
        } else {
            resultContainer.innerHTML = `<div class="error-box">❌ Hiba a generálásban: ${data.error || 'Ismeretlen hiba történt.'}</div>`;
        }

    } catch (error) {
        console.error('Java Kódgenerálási hiba:', error);
        resultContainer.innerHTML = `<div class="error-box">❌ Hiba a szerverhívásban: ${error.message}</div>`;
    } finally {
        loadingIndicator.style.display = 'none';
    }
}

function copyCodeBlock(buttonElement) {
    const codeBlockWrapper = buttonElement.closest('.code-block-wrapper');
    const codeElement = codeBlockWrapper ? codeBlockWrapper.querySelector('code') : null;
    const originalText = buttonElement.textContent;

    if (codeElement) {
        const codeToCopy = codeElement.textContent;

        navigator.clipboard.writeText(codeToCopy).then(() => {
            buttonElement.textContent = '✅';
            buttonElement.classList.add('copied');
            setTimeout(() => {
                buttonElement.textContent = originalText;
                buttonElement.classList.remove('copied');
            }, 1500);
        }).catch(err => {
            console.error('Nem sikerült a kód másolása: ', err);
            buttonElement.textContent = '❌';
        });
    } else {
        alert('Nincs kód a másoláshoz!');
    }
}

function closeJavaCodeModal() {
    document.getElementById('javaCodeModal').style.display = 'none';

    if (window.currentOpenTaskId) {
        const cardElement = document.getElementById(`task-${window.currentOpenTaskId}`);
        if (cardElement) {
            const toggleButton = cardElement.querySelector('.task-menu-toggle');
            if (toggleButton) {
                toggleButton.textContent = '⋮';
            }
        }
    }
    window.currentOpenTaskId = null;
}

function loadDefaultPrompt() {
    const textarea = document.getElementById('ai_prompt');
    const projectNameInput = document.getElementById('project_name');

    const defaultTemplate = textarea.getAttribute('data-default-prompt');
    const projectName = projectNameInput.value.trim() || 'Projekt Neve';
    const finalPrompt = defaultTemplate.replace('{{PROJECT_NAME}}', projectName);

    textarea.value = finalPrompt;
    textarea.focus();
}

document.addEventListener('DOMContentLoaded', () => {
    const savedMode = localStorage.getItem('darkMode');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

    const initialDarkMode = (savedMode === 'enabled') || (savedMode === null && prefersDark);

    if (initialDarkMode) {
        document.body.classList.add('dark-mode');
    }
    updateToggleIcon(initialDarkMode);

    const selector = document.getElementById('project_selector');
    if (selector && typeof currentProjectName !== 'undefined') {
        selector.value = encodeURIComponent(currentProjectName);
    }

    const projectForm = document.getElementById('projectForm');
    if (projectForm) {
        projectForm.addEventListener('submit', handleProjectFormSubmission);
    }

    document.addEventListener('dragend', function (e) {
        if (e.target.classList.contains('task-card')) {
            e.target.style.opacity = '1';
        }
    });

    const modeToggle = document.getElementById('mode-toggle-icon');
    if (modeToggle) {
        modeToggle.addEventListener('click', toggleDarkMode);
    }

    const inlineDescriptionInput = document.getElementById('inline_task_description');
    if (inlineDescriptionInput) {
        inlineDescriptionInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addTask(true);
            }
        });
    }

    const globalMessageBox = document.getElementById('global-message-box');
    if (globalMessageBox) {
        setTimeout(() => {
            globalMessageBox.style.opacity = '0';
            setTimeout(() => globalMessageBox.remove(), 1000);
        }, 5000);
    }

});

function toggleImportance(taskId) {
    const toggleButton = document.querySelector(`#task-${taskId} .importance-toggle`);
    const cardElement = document.getElementById(`task-${taskId}`);

    if (!toggleButton || !cardElement) return;

    const currentStatus = parseInt(toggleButton.getAttribute('data-is-important')) || 0;
    const newStatus = currentStatus === 1 ? 0 : 1;

    const formData = new FormData();
    formData.append('action', 'toggle_importance');
    formData.append('task_id', taskId);
    formData.append('is_important', newStatus);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toggleButton.setAttribute('data-is-important', newStatus);

                if (newStatus === 1) {
                    toggleButton.textContent = '⭐';
                    cardElement.classList.add('is-important');
                } else {
                    toggleButton.textContent = '☆';
                    cardElement.classList.remove('is-important');
                }
            } else {
                console.error('Hiba a fontosság váltása során:', data.error);
                alert('Hiba történt a fontosság váltása során.');
            }
        })
        .catch(error => {
            console.error('Hálózati hiba a fontosság váltása során:', error);
            alert('Hálózati hiba történt.');
        });
}

async function commitJavaCodeToGitHubInline(buttonElement) {
    const taskId = buttonElement.getAttribute('data-task-id');
    const description = buttonElement.getAttribute('data-description');

    const codeBlockWrapper = buttonElement.closest('.code-block-wrapper');
    const codeElement = codeBlockWrapper ? codeBlockWrapper.querySelector('code') : null;
    const codeToCommit = codeElement ? codeElement.textContent : '';

    const userToken = sessionStorage.getItem('githubToken');
    const userUsername = sessionStorage.getItem('githubUsername');
    const userRepo = sessionStorage.getItem('githubRepo');

    const originalText = buttonElement.innerHTML;

    if (!userToken || !userUsername || !userRepo) {
        alert("Commitoláshoz be kell jelentkezni a Project menüben, majd meg kell adni a tokent, felhasználónevet és repó nevet!");
        return;
    }
    if (!codeToCommit || !taskId || !description) {
        alert("Hiba: A kód vagy a feladat adatai hiányoznak a commitoláshoz.");
        return;
    }

    buttonElement.disabled = true;
    buttonElement.innerHTML = 'Commit... 🚀';

    try {
        const response = await fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'commit_to_github',
                task_id: taskId,
                description: description,
                code: codeToCommit,
                user_token: userToken,
                user_username: userUsername,
                user_repo: userRepo,
            })
        });

        const data = await response.json();

        if (data.success) {
            buttonElement.innerHTML = 'Siker ✅';
            alert(`Sikeres commit! A kód a következő fájlba került: ${data.filePath}`);

            const cardElement = document.getElementById(`task-${taskId}`);
            if (cardElement) {
                const currentStatus = cardElement.closest('.kanban-column').getAttribute('data-status');

                if (currentStatus !== 'KÉSZ') {
                    const targetStatus = 'KÉSZ';
                    const targetColumn = document.querySelector(`[data-status="${targetStatus}"]`);
                    const targetList = targetColumn ? targetColumn.querySelector('.task-list') : null;

                    if (targetList) {

                        const placeholder = targetList.querySelector('.empty-placeholder');
                        if (placeholder) {
                            placeholder.remove();
                        }

                        targetList.appendChild(cardElement);

                        updateCount(currentStatus, -1);
                        updateCount(targetStatus, 1);

                        const syncFormData = new FormData();
                        syncFormData.append('action', 'update_status');
                        syncFormData.append('task_id', taskId);
                        syncFormData.append('new_status', targetStatus);
                        syncFormData.append('current_project', window.currentProjectName);

                        fetch('index.php', { method: 'POST', body: syncFormData })
                            .catch(error => { console.error('Hiba a KÉSZ státusz szinkronizálásánál:', error); });
                        window.location.reload();

                    }
                }
            }
            closeJavaCodeModal();

        } else {
            alert('GitHub Commit Hiba: ' + (data.error || 'Ismeretlen hiba történt.'));
            buttonElement.innerHTML = 'Hiba ❌';
        }
    } catch (error) {
        console.error('Commit hiba:', error);
        alert('Hálózati hiba a commitolás során.');
        buttonElement.innerHTML = 'Hiba ❌';
    } finally {
        setTimeout(() => {
            buttonElement.innerHTML = originalText;
            buttonElement.disabled = false;
        }, 3000);
    }
}

function showHelpMessage(buttonElement) {
    const message = buttonElement.getAttribute('data-help');

    console.log("GitHub PAT Súgó: " + message);

    alert(message);
}