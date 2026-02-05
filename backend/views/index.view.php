<?php

use App\Utils;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Driven Kanban</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <div class="project-menu-container">
        <button class="menu-toggle-button menu-icon" onclick="toggleMenu()" title="Project Settings">
            ☰
        </button>

        <div class="project-menu-dropdown" id="projectDropdown">
            <button type="button" class="menu-close-button" onclick="toggleMenu()" title="Close menu">x</button>
            <form method="POST" action="<?php echo basename($_SERVER['SCRIPT_NAME']); ?>" id="projectForm" class="menu-form">
                <p class="menu-label">What project would you like to generate tasks for?</p>

                <div class="input-group generate-group">
                    <input type="text" id="project_name" name="project_name" placeholder="e.g. 'E-commerce website'"
                        value="<?php echo htmlspecialchars($currentProjectName ?? ''); ?>" required>
                    <button type="submit" class="submit-button" id="generateButton"
                        title="Generating will overwrite existing tasks for this project!">
                        Generate with AI
                    </button>

                </div>

                <p class="menu-label" style="margin-top: 15px;">AI Instruction (Prompt):
                    <button type="button" class="help-button" onclick="loadDefaultPrompt()"
                        title="Load default prompt">
                        ❓
                    </button>
                </p>

                <?php
                $defaultPrompt = "Plan a project named {{PROJECT_NAME}}! Generate at least 10 tasks for the Kanban board covering basic development steps. Provide each task on a new line without any prefix (e.g. [SPRINT BACKLOG]:) so they all go into the **SPRINT BACKLOG** column. Do not include introductory text."; ?>
                <textarea id="ai_prompt" name="ai_prompt" rows="5" class="prompt-textarea" required
                    placeholder="AI prompt..."
                    data-default-prompt="<?php echo htmlspecialchars($defaultPrompt); ?>"></textarea>
                <?php if (!empty($existingProjects)) : ?>
                    <p class="menu-label" style="margin-top: 15px;">Or choose an existing project:
                    </p>
                    <select id="project_selector" onchange="loadProject(this.value)" class="project-select-dropdown">

                        <option value="" <?php echo empty($currentProjectName) ? 'selected' : ''; ?>>-- Load Project --
                        </option>
                        <?php foreach ($existingProjects as $proj) : ?>
                            <option value="<?php echo urlencode($proj); ?>" <?php echo ($proj === $currentProjectName) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($proj); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button type="button" class="submit-button github-login-toggle-button" onclick="openGithubLoginModal()">
                    <img width="32" height="32" src="assets/images/github.svg" alt="github">
                </button>

            </form>

        </div>

    </div>
    </div>


    <div class="header-bar">
        <div style="width: 48px;"></div>
        <div class="header-title-container">
            <h1>🤖 AI-Driven Kanban</h1>
        </div>

        <div class="mode-toggle" id="mode-toggle-icon" title="Switch to Dark Mode">
            🌙
        </div>
    </div>


    <div class="content-wrapper">
        <?php if (isset($currentProjectName) && $currentProjectName) : ?>
            <div class="project-status-info">
                Current Project: <strong><?php echo htmlspecialchars($currentProjectName); ?></strong>
            </div>
        <?php else : ?>
            <div class="project-status-info">
                Generate a project in the menu!
            </div>
        <?php endif; ?>

        <div class="message-container">
            <?php if (isset($error)) : ?>
                <div class="error-box">
                    ❌ Error:<?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (isset($tasksAdded) && $tasksAdded < 5 && $tasksAdded > 0) : ?>
                <div class="warning-box">
                    ⚠️ Warning: Only <?php echo $tasksAdded; ?> tasks were generated.
                </div>
            <?php elseif (isset($currentProjectName) && $currentProjectName && empty($error) && (!isset($_POST['action']) || $_POST['action'] !== 'add_task')) : ?>
                <div class="success-box" id="global-message-box">
                    ✅ Tasks successfully loaded for project "<?php echo htmlspecialchars($currentProjectName); ?>"!
                </div>
            <?php endif; ?>
        </div>

        <div class="kanban-board">
            <?php foreach ($columns as $title => $style) : ?>
                <div class="kanban-column"
                    data-status="<?php echo htmlspecialchars($title); ?>"
                    role="region"
                    aria-label="<?php echo htmlspecialchars($title); ?> column">
                    <div class="column-header header-<?php echo $style; ?>">
                        <?php echo htmlspecialchars($title); ?> (<span class="task-count"
                            id="count-<?php echo Utils::createSafeId($title); ?>"><?php echo count($kanbanTasks[$title] ?? []); ?></span>)
                    </div>

                    <?php if (strpos(strtoupper($title), 'BACKLOG') !== false && isset($currentProjectName) && $currentProjectName) : ?>
                        <button class="add-task-icon-only" id="addTaskToggle" onclick="toggleTaskInput()"
                            title="Add new task">
                            ➕
                        </button>
                        <div class="add-task-input-form" id="addTaskInputForm" style="display: none;">
                            <input type="text" id="inline_task_description" placeholder="Task description" required>
                            <button type="button" class="submit-button add-task-submit" onclick="addTask(true)">
                                Add
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="task-list" id="col-<?php echo Utils::createSafeId($title); ?>">
                        <?php
                        $hasTasks = !empty($kanbanTasks[$title]);

                        if ($hasTasks) {
                            foreach ($kanbanTasks[$title] as $task) {
                                $safeDescription = htmlspecialchars(addslashes($task['description']));
                                $isImportant = (int) $task['is_important'];
                                $isSubtask = (isset($task['is_subtask']) && (int) $task['is_subtask'] === 1);
                                $isSubtaskClass = $isSubtask ? ' is-subtask' : '';
                                $poComments = $task['po_comments'] ?? '';
                                $hasCodeClass = (!empty($task['generated_code'])) ? ' has-ai-code' : '';

                                echo '<div class="task-card' . ($isImportant ? ' is-important' : '') . $hasCodeClass . $isSubtaskClass . '" draggable="true" ondragstart="drag(event)" id="task-' . htmlspecialchars($task['id']) . '" data-current-status="' . htmlspecialchars($title) . '">';

                                if (!empty($task['generated_code'])) {
                                    echo '<div class="ai-code-indicator" title="AI code already generated">🤖</div>';
                                }

                                echo '<button class="importance-toggle" onclick="toggleImportance(' . htmlspecialchars($task['id']) . ')" data-is-important="' . $isImportant . '" title="Set importance">';
                                echo $isImportant ? '⭐' : '☆';
                                echo '</button>';

                                echo '<div class="task-menu-group">';

                                echo '<button class="task-menu-toggle" title="Settings" onclick="toggleTaskMenu(' . htmlspecialchars($task['id']) . ', this)">⋮</button>';

                                echo '<div id="task-menu-' . htmlspecialchars($task['id']) . '" class="task-actions-menu">';
                                echo '<button class="menu-action-button" title="Edit task" onclick="toggleEdit(' . htmlspecialchars($task['id']) . ', event)">✏️ Edit</button>';
                                echo '<button class="menu-action-button" title="Decompose Story" onclick="decomposeTask(' . htmlspecialchars($task['id']) . ', \'' . $safeDescription . '\')">🔨 Decompose Story</button>';
                                echo '<button class="menu-action-button" title="Generate Java Code" onclick="generateJavaCodeModal(' . htmlspecialchars($task['id']) . ', \'' . $safeDescription . '\')">💻 Generate Code</button>';
                                echo '<button class="menu-action-button delete-action" title="Delete task" onclick="deleteTask(' . htmlspecialchars($task['id']) . ', \'' . htmlspecialchars($title) . '\', \'' . $safeDescription . '\')">🗑️ Delete</button>';
                                echo '</div>';
                                echo '</div>';

                                if ($isSubtask) {
                                    echo '<span class="subtask-badge">Technical Task</span>';
                                }

                                echo '<p class="card-description" id="desc-' . htmlspecialchars($task['id']) . '" contenteditable="false" data-original-content="' . htmlspecialchars($task['description']) . '">';
                                echo htmlspecialchars($task['description']);
                                echo '</p>';

                                if (!empty($poComments)) {
                                    echo '<div class="po-comment-container">';
                                    echo '<div class="po-comment-header">🤖 TAIPO PO Feedback</div>';
                                    echo '<div class="po-comment-text">' . htmlspecialchars($poComments) . '</div>';
                                    echo '</div>';
                                }

                                echo '</div>';
                            }
                        } else {
                            echo '<div class="task-card empty-placeholder">';
                            echo '<p class="card-description">No tasks in this column.</p></div>';
                        }
                        ?>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="modal-overlay" id="javaCodeModal" style="display: none;">
            <div class="code-modal-content">
                <button class="modal-close" onclick="closeJavaCodeModal()">x</button>

                <div id="javaCodeResultContainer" class="code-result-container">
                    Generating code...
                </div>

                <div id="javaCodeLoadingIndicator" style="display: none; text-align: center; margin-top: 15px;">
                    <div class="spinner"></div>
                    <p>Java code generation in progress...</p>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="mainGenerationModal" style="display: none;">
            <div class="code-modal-content" style="max-width: 400px; text-align: center; padding: 40px 20px;">
                <h2 style="margin-bottom: 20px;">
                    Generating project tasks:
                    <strong id="generatingProjectNamePlaceholder">Project Name</strong>
                </h2>
                <div id="mainGenerationLoadingIndicator" style="text-align: center;">
                    <div class="spinner large-spinner"></div>
                    <p style="margin-top: 15px;">AI is currently organizing the project.<br>This may take 10-20 seconds.
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-overlay" id="githubLoginModal" style="display: none;">
            <div class="modal-content github-config-modal">
                <button class="modal-close" onclick="closeGithubLoginModal()">x</button>
                <h2>
                    <img width="32" height="32" src="assets/images/github.svg" alt="github">
                    GitHub Login
                </h2>

                <div class="input-group">
                    <input type="text" id="github_username_input" placeholder="GitHub Username"
                        value="<?php echo htmlspecialchars($_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME') ?? ''); ?>"
                        required>
                </div>

                <div class="input-group">
                    <input type="text" id="github_repo_input" placeholder="GitHub Repository Name"
                        value="<?php echo htmlspecialchars($_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO') ?? ''); ?>"
                        required>
                </div>
                <div class="input-group">
                    <div style="display: flex; align-items: center; gap: 8px; position: relative;">
                        <input type="password" id="github_pat" placeholder="GitHub Personal Access Token (PAT)" required
                            style="flex-grow: 1;">

                        <button type="button" class="help-button" onclick="showHelpMessage(this)"
                            data-help="You can create a Personal Access Token (PAT) in your GitHub settings (Settings > Developer settings > Personal access tokens). 'repo' permissions are required!">
                            ?
                        </button>
                    </div>
                </div>

                <button type="button" class="submit-button" onclick="githubLogin()">
                    Login / Save Token
                </button>

                <div id="modalGithubStatus"
                    style="padding: 10px 0; font-size: 0.9em; color: #ffc107; font-style: italic;">
                    <?php
                    if (!$isServerConfigured) : ?>
                        ⚠️ **ERROR:** Server-side repo data (GITHUB_REPO) is missing from the .env file.
                    <?php else : ?>
                        ✔️ Server-side repo settings are OK.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
            window.currentProjectName = "<?php echo htmlspecialchars($currentProjectName ?? ''); ?>";
            const isGitHubRepoConfigured = <?php echo $isServerConfigured ? 'true' : 'false'; ?>;
            console.log("Project Name:", window.currentProjectName);
        </script>

        <script src="assets/js/script.js"></script>

</body>
</html>
