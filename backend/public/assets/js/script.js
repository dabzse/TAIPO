let draggedId = null;
let currentOpenTaskId = null;

function loadProject(encodedProjectName) {
    if (encodedProjectName) {
        globalThis.location.href = `index.php?project=${encodedProjectName}`;
    }
}

function createSafeId(title) {
    let safeTitle = title.toLowerCase();

    safeTitle = safeTitle
        .replaceAll('á', 'a').replaceAll('é', 'e').replaceAll('í', 'i')
        .replaceAll('ó', 'o').replaceAll('ö', 'o').replaceAll('ő', 'o')
        .replaceAll('ú', 'u').replaceAll('ü', 'u').replaceAll('ű', 'u')
        .replaceAll(' ', '_');

    return safeTitle.replaceAll(/[^a-z0-9_]/g, '');
}

function createTaskCard(task) {
    const newCard = document.createElement('div');
    newCard.className = 'task-card' + (task.is_subtask ? ' is-subtask' : '');
    newCard.setAttribute('draggable', 'true');
    newCard.setAttribute('ondragstart', 'drag(event)');
    newCard.id = 'task-' + task.id;
    newCard.dataset.currentStatus = task.status || 'SPRINT BACKLOG';

    const safeDescription = task.description.replaceAll("'", String.raw`\'`).replaceAll('"', String.raw`\"`);
    const isImportant = task.is_important == 1;

    let poCommentHtml = '';
    let aiIndicatorHtml = '';

    if (task.po_comments) {
        poCommentHtml = `
            <div class="po-comment-container">
                <div class="po-comment-header">🤖 TAIPO PO Feedback</div>
                <div class="po-comment-text">${task.po_comments}</div>
            </div>`;
    }

    if (task.generated_code) {
        aiIndicatorHtml = '<div class="ai-code-indicator" title="AI code already generated">🤖</div>';
    }

    const subtaskBadge = task.is_subtask ? `<span class="subtask-badge">Technical Task</span>` : '';
    const hasCodeClass = task.generated_code ? ' has-ai-code' : '';
    const importantClass = isImportant ? ' is-important' : '';
    newCard.className += hasCodeClass + importantClass;

    newCard.innerHTML = `
        ${aiIndicatorHtml}
        <button class="importance-toggle" onclick="toggleImportance(${task.id})" data-is-important="${isImportant ? 1 : 0}" title="Set importance">
            ${isImportant ? '⭐' : '☆'}
        </button>
        <div class="task-menu-group">
            <button class="task-menu-toggle" title="Settings" onclick="toggleTaskMenu(${task.id}, this)">⋮</button>
            <div id="task-menu-${task.id}" class="task-actions-menu">
                <button class="menu-action-button" onclick="toggleEdit(${task.id}, event)">✏️ Edit</button>
                <button class="menu-action-button" onclick="decomposeTask(${task.id}, '${safeDescription}')">🔨 Decompose Story</button>
                <button class="menu-action-button" onclick="generateJavaCodeModal(${task.id}, '${safeDescription}')">💻 Generate Code</button>
                <button class="menu-action-button delete-action" onclick="deleteTask(${task.id}, 'SPRINT BACKLOG', '${safeDescription}')">🗑️ Delete</button>
            </div>
        </div>
        ${subtaskBadge}
        <p class="card-description" id="desc-${task.id}" contenteditable="false" data-original-content="${task.description}">${task.description}</p>
        ${poCommentHtml}
    `;

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
    const targetColumn = ev.target.closest('.kanban-column');
    if (!targetColumn) return;

    const targetStatus = targetColumn.dataset.status; // Using dataset (Public)
    const draggedId = ev.dataTransfer.getData("text/plain");
    const draggedElement = document.getElementById(draggedId);

    if (draggedElement) {
        const sourceColumn = draggedElement.closest('.kanban-column');
        const oldStatus = sourceColumn ? sourceColumn.dataset.status : null;

        if (oldStatus === targetStatus) {
            draggedElement.style.opacity = '1';
            return;
        }

        const targetList = targetColumn.querySelector('.task-list');

        const placeholders = targetList.querySelectorAll('.empty-placeholder');
        placeholders.forEach(p => p.remove());

        targetList.appendChild(draggedElement);
        // Update current status attribute (Root feature)
        draggedElement.dataset.currentStatus = targetStatus;

        const taskId = draggedId.replace('task-', '');
        updateTaskStatus(taskId, targetStatus, oldStatus);

        updateCount(oldStatus, -1);
        updateCount(targetStatus, 1);
        checkAndInsertPlaceholder(oldStatus);

        draggedElement.style.opacity = '1';
    }
}

function updateTaskStatus(taskId, newStatus, oldStatus) {
    if (!oldStatus || oldStatus === newStatus) return;

    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('task_id', taskId);
    formData.append('new_status', newStatus);
    formData.append('old_status', oldStatus);
    formData.append('current_project', globalThis.currentProjectName);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                alert('Could not update status (maybe WIP limit reached). Reverting...');
                globalThis.location.reload(); // Reload on error to sync state
            }
        })
        .catch(error => {
            console.error('Error updating status:', error);
        })
        .finally(() => {
            const card = document.getElementById(`task-${taskId}`);
            if (card) card.style.opacity = '1';
        });
}

function checkAndInsertPlaceholder(status) {
    const column = document.querySelector(`[data-status="${status}"]`);
    if (column) {
        const taskList = column.querySelector('.task-list');
        if (taskList.querySelectorAll('.task-card:not(.empty-placeholder)').length === 0) {
            taskList.innerHTML = '<div class="task-card empty-placeholder"><p class="card-description">No tasks in this column.</p></div>';
        }
    }
}

function updateCount(status, delta) {
    const safeStatusId = createSafeId(status);
    const countSpan = document.getElementById(`count-${safeStatusId}`);
    if (countSpan) {
        let currentCount = Number.parseInt(countSpan.textContent) || 0;
        countSpan.textContent = Math.max(0, currentCount + delta);
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
    const currentProjectName = globalThis.currentProjectName;

    if (!newDescription || !currentProjectName) {
        alert('Please provide a task description and ensure a project is loaded!');
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
                    throw new Error(errorData.error || 'Unknown server error');
                }).catch(() => {
                    throw new Error('Network error: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const newTask = { id: data.id, description: data.description };
                const newCard = createTaskCard(newTask);
                const targetList = document.querySelector('#col-' + createSafeId('SPRINT BACKLOG'));

                if (targetList) {
                    const placeholder = targetList.querySelector('.empty-placeholder');

                    if (placeholder) {
                        placeholder.remove();
                    }

                    targetList.appendChild(newCard);
                    updateCount('SPRINT BACKLOG', 1);
                    globalThis.location.reload();
                }

                if (descriptionInput) {
                    descriptionInput.value = '';
                }

            } else {
                alert('Error while adding task. (Unsuccessful JSON response)');
            }
        })
        .catch(error => {
            console.error('[ADD TASK] Error adding:', error);
            alert('An error occurred while adding the task: ' + error.message);
        });
}

function deleteTask(taskId, status, description) {
    if (!confirm(`Are you sure you want to delete the following task: "${description}" (ID: ${taskId})?`)) {
        return;
    }

    const currentProjectName = globalThis.currentProjectName;
    if (!currentProjectName) {
        alert('No project loaded.');
        return;
    }

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
                    throw new Error(errorData.error || 'Unknown server error');
                }).catch(() => {
                    throw new Error('Network error: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (card) {
                    card.remove();
                    updateCount(status, -1);
                    checkAndInsertPlaceholder(status);
                    globalThis.location.reload();
                } else {
                    console.error(`[DELETE TASK] Error: Task card (task-${taskId}) not found in DOM.`);
                }
            } else {
                alert('Error while deleting task. (Unsuccessful JSON response)');
            }
        })
        .catch(error => {
            console.error('[DELETE TASK] Error deleting:', error);
            alert('An error occurred while deleting the task: ' + error.message);
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
        icon.title = isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode';
    }
}

let isEditing = {};

function toggleEdit(taskId, ev) {
    if (ev) ev.stopPropagation();

    const currentMenu = document.getElementById(`task-menu-${taskId}`);
    if (currentMenu) currentMenu.classList.remove('active');

    const descElement = document.getElementById(`desc-${taskId}`);
    const editButtonInMenu = currentMenu ? currentMenu.querySelector('[onclick*="toggleEdit"]') : null;

    if (!descElement) {
        console.error("Edit error: Description element not found!");
        return;
    }

    if (descElement.getAttribute('contenteditable') === 'true') {
        const newDescription = descElement.textContent.trim();
        const originalContent = descElement.dataset.originalContent.trim();

        if (newDescription === originalContent) {
            cancelEdit(taskId);
            return;
        }

        if (newDescription === "") {
            alert("Task description cannot be empty!");
            descElement.textContent = originalContent;
            return;
        }

        editTask(taskId, newDescription)
            .then(success => {
                if (success) {
                    descElement.dataset.originalContent = newDescription;
                    cancelEdit(taskId);
                } else {
                    alert("An error occurred during saving.");
                    descElement.textContent = originalContent;
                }
            });

    } else {
        Object.keys(isEditing).forEach(activeId => cancelEdit(activeId));

        descElement.setAttribute('contenteditable', 'true');
        descElement.classList.add('editing');

        if (editButtonInMenu) editButtonInMenu.innerHTML = '💾 Save';

        descElement.focus();
        // Use simpler selection logic or keep Root's
        const range = document.createRange();
        range.selectNodeContents(descElement);
        const selection = globalThis.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        selection.collapseToEnd();

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
    const cardElement = document.getElementById(`task-${taskId}`);
    if (!descElement || !cardElement) return;

    descElement.textContent = descElement.dataset.originalContent;
    descElement.setAttribute('contenteditable', 'false');
    descElement.classList.remove('editing');

    const currentMenu = document.getElementById(`task-menu-${taskId}`);
    const editButtonInMenu = currentMenu ? currentMenu.querySelector('[onclick*="toggleEdit"]') : null;
    if (editButtonInMenu) editButtonInMenu.innerHTML = '✏️ Edit';

    const menuToggleButton = cardElement.querySelector('.task-menu-toggle');
    if (menuToggleButton) {
        menuToggleButton.textContent = '⋮';
    }

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
    .then(response => response.json())
    .then(data => data.success);
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
            message = "✅ Token saved successfully! You can commit using your own account. (Password not stored)";
            statusDiv.style.color = '#28a745';
        } else {
            message = "🔐 Please provide a PAT token to enable commits.";
            statusDiv.style.color = '#ffc107';
        }

        if (!globalThis.isGitHubRepoConfigured && !isUserLoggedIn) {
            message = "⚠️ ERROR: Server-side repository data is missing. Commit will not work.";
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
        statusDiv.innerHTML = "❌ ERROR: Please provide your GitHub username, repository name, and Personal Access Token.";
        statusDiv.style.color = '#dc3545';
        statusDiv.style.borderColor = '#dc3545';
        return;
    }

    sessionStorage.setItem('githubToken', token);
    sessionStorage.setItem('githubUsername', username);
    sessionStorage.setItem('githubRepo', repo);

    statusDiv.innerHTML = "✅ Success! Token and repository saved.";
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
        event.preventDefault();
        return false;
    }

    document.getElementById('generatingProjectNamePlaceholder').textContent = projectName;
    mainModal.style.display = 'flex';
    document.getElementById('generateButton').disabled = true;

    return true;
}

async function generateJavaCodeModal(taskId, description) {
    const javaCodeModal = document.getElementById('javaCodeModal');
    if (!javaCodeModal) return;

    globalThis.currentOpenTaskId = taskId;
    const currentMenu = document.getElementById(`task-menu-${taskId}`);
    if (currentMenu) currentMenu.classList.remove('active');

    const resultContainer = document.getElementById('javaCodeResultContainer');
    const loadingIndicator = document.getElementById('javaCodeLoadingIndicator');

    javaCodeModal.style.display = 'flex';
    resultContainer.innerHTML = '<div style="text-align:center; padding:20px;">Generating or loading code...</div>';
    loadingIndicator.style.display = 'block';

    const userToken = sessionStorage.getItem('githubToken') || '';
    const userUsername = sessionStorage.getItem('githubUsername') || '';

    try {
        const data = await fetchGeneratedCode(taskId, description, userToken, userUsername);

        if (data.success) {
            handleCodeGenerationSuccess(taskId, data.cached, data.code, resultContainer);
        } else {
            resultContainer.innerHTML = `<div class="error-box">❌ Error: ${data.error}</div>`;
        }
    } catch (error) {
        console.error('Fetch error:', error);
        resultContainer.innerHTML = '<div class="error-box">❌ Network error or invalid JSON response.</div>';
    } finally {
        loadingIndicator.style.display = 'none';
    }
}

async function fetchGeneratedCode(taskId, description, userToken, userUsername) {
    const response = await fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'generate_java_code',
            task_id: taskId,
            description: description,
            user_token: userToken,
            user_username: userUsername
        })
    });
    return await response.json();
}

function handleCodeGenerationSuccess(taskId, isCached, code, resultContainer) {
    updateTaskCardUI(taskId);

    let statusNote = isCached
        ? '<div style="color: #6c757d; font-size: 0.8em; margin-bottom: 10px; text-align: left; padding-left: 5px;">💾 Loaded from cache</div>'
        : '<div style="color: #28a745; font-size: 0.8em; margin-bottom: 10px; text-align: left; padding-left: 5px;">✨ Newly generated</div>';

    resultContainer.innerHTML = statusNote + code;

    moveTaskToWip(taskId);
}

function updateTaskCardUI(taskId) {
    const cardElement = document.getElementById(`task-${taskId}`);
    if (cardElement) {
        if (!cardElement.classList.contains('has-ai-code')) {
            cardElement.classList.add('has-ai-code');
        }
        if (!cardElement.querySelector('.ai-code-indicator')) {
            const indicator = document.createElement('div');
            indicator.className = 'ai-code-indicator';
            indicator.title = 'AI code already generated';
            indicator.textContent = '🤖';
            cardElement.appendChild(indicator);
        }
    }
}

function moveTaskToWip(taskId) {
    const cardElement = document.getElementById(`task-${taskId}`);
    const targetStatus = 'IMPLEMENTATION WIP:3';

    if (cardElement) {
        const sourceColumn = cardElement.closest('.kanban-column');
        const currentStatus = sourceColumn ? sourceColumn.dataset.status : null; // Public dataset

        if (currentStatus && currentStatus !== targetStatus && currentStatus !== 'DONE') {
            const targetColumn = document.querySelector(`[data-status="${targetStatus}"]`);
            if (targetColumn) {
                const targetList = targetColumn.querySelector('.task-list');
                const placeholder = targetList.querySelector('.empty-placeholder');
                if (placeholder) placeholder.remove();

                targetList.appendChild(cardElement);

                updateCount(currentStatus, -1);
                updateCount(targetStatus, 1);
                checkAndInsertPlaceholder(currentStatus);

                const syncFormData = new FormData();
                syncFormData.append('action', 'update_status');
                syncFormData.append('task_id', taskId);
                syncFormData.append('new_status', targetStatus);
                syncFormData.append('current_project', globalThis.currentProjectName);
                fetch('index.php', { method: 'POST', body: syncFormData });
            }
        }
    }
}

function copyCodeBlock(buttonElement) {
    const codeBlockWrapper = buttonElement.closest('.code-block-wrapper');
    const codeElement = codeBlockWrapper.querySelector('code');
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
            console.error('Failed to copy code: ', err);
            buttonElement.textContent = '❌';
        });
    } else {
        alert('No code to copy!');
    }
}

function closeJavaCodeModal() {
    document.getElementById('javaCodeModal').style.display = 'none';

    if (globalThis.currentOpenTaskId) {
        const cardElement = document.getElementById(`task-${globalThis.currentOpenTaskId}`);
        if (cardElement) {
            const toggleButton = cardElement.querySelector('.task-menu-toggle');
            if (toggleButton) {
                toggleButton.textContent = '⋮';
            }
        }
    }
    globalThis.currentOpenTaskId = null;
}

function loadDefaultPrompt() {
    const textarea = document.getElementById('ai_prompt');
    const projectNameInput = document.getElementById('project_name');
    const defaultTemplate = textarea.dataset.defaultPrompt;
    const projectName = projectNameInput.value.trim() || 'Project Name';
    const finalPrompt = defaultTemplate.replace('{{PROJECT_NAME}}', projectName);

    textarea.value = finalPrompt;
    textarea.focus();
}

document.addEventListener('DOMContentLoaded', () => {
    const savedMode = localStorage.getItem('darkMode');
    const prefersDark = globalThis.matchMedia?.('(prefers-color-scheme: dark)').matches;

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

    // Public: Drag and Drop Listeners
    document.querySelectorAll('.kanban-column').forEach(column => {
        column.addEventListener('dragover', allowDrop);
        column.addEventListener('drop', drop);
    });
});

function toggleImportance(taskId) {
    const toggleButton = document.querySelector(`#task-${taskId} .importance-toggle`);
    const cardElement = document.getElementById(`task-${taskId}`);

    if (!toggleButton || !cardElement) return;

    const currentStatus = Number.parseInt(toggleButton.dataset.isImportant) || 0;
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
            toggleButton.dataset.isImportant = newStatus;

            if (newStatus === 1) {
                toggleButton.textContent = '⭐';
                cardElement.classList.add('is-important');
            } else {
                toggleButton.textContent = '☆';
                cardElement.classList.remove('is-important');
            }
        } else {
            console.error('Error toggling importance:', data.error);
            alert('An error occurred while toggling importance.');
        }
    })
    .catch(error => {
        console.error('Network error toggling importance:', error);
        alert('A network error occurred.');
    });
}



async function commitJavaCodeToGitHubInline(buttonElement) {
    const taskId = buttonElement.dataset.taskId;
    const description = buttonElement.dataset.description || "";

    const cardElement = document.getElementById(`task-${taskId}`);
    if (!cardElement) return;

    let currentStatus = cardElement.dataset.currentStatus;
    if (!currentStatus) {
        const columnElement = cardElement.closest('.kanban-column');
        currentStatus = columnElement ? columnElement.dataset.status : "";
    }

    console.log("Detected status at commit time:", currentStatus);

    const authData = getAuthData();
    if (!validateCommitPreconditions(currentStatus, authData)) return;

    buttonElement.disabled = true;
    buttonElement.innerHTML = '🚀 Committing...';

    const codeBlockWrapper = buttonElement.closest('.code-block-wrapper');
    const codeElement = codeBlockWrapper ? codeBlockWrapper.querySelector('code') : null;
    const codeToCommit = codeElement ? codeElement.textContent : '';

    try {
        const data = await performGitHubCommit(taskId, description, codeToCommit, authData);

        if (data.success) {
            handleCommitSuccess(taskId, currentStatus, cardElement, data.filePath);
            closeJavaCodeModal();
        } else {
            alert('GitHub Error: ' + (data.error || 'Unknown error occurred.'));
        }
    } catch (error) {
        console.error('Commit error:', error);
        alert('A network error occurred during the commit process.');
    } finally {
        buttonElement.disabled = false;
        buttonElement.innerHTML = 'Commit to GitHub';
    }
}

function getAuthData() {
    return {
        token: sessionStorage.getItem('githubToken'),
        username: sessionStorage.getItem('githubUsername'),
        repo: sessionStorage.getItem('githubRepo')
    };
}

function validateCommitPreconditions(currentStatus, authData) {
    if (!currentStatus?.toUpperCase().includes('REVIEW')) {
        alert(`❌ Error: Task status is "${currentStatus}". Commits are only allowed from the REVIEW column!`);
        return false;
    }

    if (!authData.token || !authData.username || !authData.repo) {
        alert("Please log in to GitHub first!");
        return false;
    }
    return true;
}

async function performGitHubCommit(taskId, description, code, authData) {
    const response = await fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'commit_to_github',
            task_id: taskId,
            description: description,
            code: code,
            user_token: authData.token,
            user_username: authData.username,
            user_repo: authData.repo
        })
    });
    return await response.json();
}

function handleCommitSuccess(taskId, currentStatus, cardElement, filePath) {
    alert(`✅ Success! Commit completed. File: ${filePath}`);

    const targetStatus = 'DONE';
    const targetColumn = document.querySelector(`[data-status="${targetStatus}"]`);

    if (targetColumn) {
        const targetList = targetColumn.querySelector('.task-list');
        const placeholder = targetList.querySelector('.empty-placeholder');
        if (placeholder) placeholder.remove();

        targetList.appendChild(cardElement);

        updateCount(currentStatus, -1); 
        updateCount(targetStatus, 1); 
        checkAndInsertPlaceholder(currentStatus);

        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_status',
                task_id: taskId,
                new_status: targetStatus,
                current_project: globalThis.currentProjectName
            })
        })
        .then(syncRes => syncRes.json())
        .then(syncData => {
            console.log("Database status updated to DONE:", syncData);
        })
        .catch(err => console.error("Database sync failed:", err));
    }
}

function showHelpMessage(buttonElement) {
    const message = buttonElement.dataset.help;
    alert(message);
}

async function decomposeTask(taskId, description) {
    if (!confirm("Are you sure you want to decompose this user story into technical tasks?")) return;

    const mainModal = document.getElementById('mainGenerationModal');
    const projectPlaceholder = document.getElementById('generatingProjectNamePlaceholder');

    if (mainModal && projectPlaceholder) {
        projectPlaceholder.textContent = "Decomposing task...";
        mainModal.style.display = 'flex';
    }

    try {
        const response = await fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'decompose_task',
                task_id: taskId,
                description: description,
                current_project: globalThis.currentProjectName,
                is_taipo_action: true
            })
        });

        const data = await response.json();
        if (data.success) {
            alert(`Success! ${data.count} new technical tasks have been added to the Backlog.`);
            globalThis.location.reload();
        } else {
            alert(`Error: ${data.error || 'Failed to decompose task.'}`);
            if (mainModal) mainModal.style.display = 'none';
        }
    } catch (error) {
        console.error('Decomposition error:', error);
        alert('A network error occurred during decomposition.');
        if (mainModal) mainModal.style.display = 'none';
    }
}

setInterval(async () => {
    if (!globalThis.currentProjectName) return;

    console.log("TAIPO PO assistant is waking up...");
    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'simulate_po_comment',
            current_project: globalThis.currentProjectName
        })
    });
}, 1000 * 60 * 30);
