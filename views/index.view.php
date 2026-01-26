<?php

use App\Utils;

?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-vezérelt Kanban</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <div class="project-menu-container">
        <button class="menu-toggle-button menu-icon" onclick="toggleMenu()" title="Projekt beállítások">
            ☰
        </button>

        <div class="project-menu-dropdown" id="projectDropdown">
            <button type="button" class="menu-close-button" onclick="toggleMenu()" title="Menü bezárása">x</button>
            <form method="POST" action="<?php echo basename($_SERVER['SCRIPT_NAME']); ?>" id="projectForm" class="menu-form">
                <p class="menu-label">Milyen projekthez szeretnél feladatokat generálni?</p>

                <div class="input-group generate-group">
                    <input type="text" id="project_name" name="project_name" placeholder="Pl. 'E-commerce weboldal'"
                        value="<?php echo htmlspecialchars($currentProjectName ?? ''); ?>" required>
                    <button type="submit" class="submit-button" id="generateButton"
                        title="A generálás felülírja a már létező feladatokat ezen a projekten!">
                        Generálás AI-val
                    </button>

                </div>

                <p class="menu-label" style="margin-top: 15px;">AI utasítás:
                    <button type="button" class="help-button" onclick="loadDefaultPrompt()"
                        title="Alapértelmezett prompt betöltése">
                        ❓
                    </button>
                </p>

                <?php
                $defaultPrompt = "Tervezz meg egy {{PROJECT_NAME}} nevű projektet! Generálj legalább 10 feladatot a Kanban táblához, amelyek a fejlesztés alapvető lépéseit fedik le. Minden feladatot külön sorban, minden előtag nélkül (pl. [SPRINTBACKLOG]:) adj meg, hogy mindegyik feladat a **SPRINTBACKLOG** oszlopba kerüljön. Az első magyarázó elem nélkül."; ?>
                <textarea id="ai_prompt" name="ai_prompt" rows="5" class="prompt-textarea" required
                    placeholder="AI utasítás (Prompt)..."
                    data-default-prompt="<?php echo htmlspecialchars($defaultPrompt); ?>"></textarea>
                <?php if (!empty($existingProjects)) : ?>
                    <p class="menu-label" style="margin-top: 15px;">Vagy válassz egy meglévő projektet:
                    </p>
                    <select id="project_selector" onchange="loadProject(this.value)" class="project-select-dropdown">

                        <option value="" <?php echo empty($currentProjectName) ? 'selected' : ''; ?>>-- Projekt betöltése --
                        </option>
                        <?php foreach ($existingProjects as $proj) : ?>
                            <option value="<?php echo urlencode($proj); ?>" <?php echo ($proj === $currentProjectName) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($proj); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button type="button" class="submit-button github-login-toggle-button" onclick="openGithubLoginModal()">
                    <img width="32" height="32" src="assets/images/github.png" alt="github">
                </button>

            </form>

        </div>

    </div>
    </div>


    <div class="header-bar">
        <div style="width: 48px;"></div>
        <div class="header-title-container">
            <h1>🤖 AI-vezérelt Kanban</h1>
        </div>

        <div class="mode-toggle" id="mode-toggle-icon" title="Váltás sötét módra">
            🌙
        </div>
    </div>


    <div class="content-wrapper">
        <?php if (isset($currentProjectName) && $currentProjectName) : ?>
            <div class="project-status-info">
                Aktuális Projekt: <strong><?php echo htmlspecialchars($currentProjectName); ?></strong>
            </div>
        <?php else : ?>
            <div class="project-status-info">
                Generálj egy projektet a menüben!
            </div>
        <?php endif; ?>

        <div class="message-container">
            <?php if (isset($error)) : ?>
                <div class="error-box">
                    ❌ Hiba:<?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (isset($tasksAdded) && $tasksAdded < 5 && $tasksAdded > 0) : ?>
                <div class="warning-box">
                    ⚠️ Figyelem: Csak <?php echo $tasksAdded; ?> feladatot sikerült generálni.
                </div>
            <?php elseif (isset($currentProjectName) && $currentProjectName && empty($error) && (!isset($_POST['action']) || $_POST['action'] !== 'add_task')) : ?>
                <div class="success-box" id="global-message-box">
                    ✅ Feladatok sikeresen betöltve a(z) "<?php echo htmlspecialchars($currentProjectName); ?>" projekthez!
                </div>
            <?php endif; ?>
        </div>

        <div class="kanban-board">
            <?php foreach ($columns as $title => $style) : ?>
                <div class="kanban-column"
                    data-status="<?php echo htmlspecialchars($title); ?>"
                    tabindex="0"
                    role="region"
                    aria-label="<?php echo htmlspecialchars($title); ?> oszlop">
                    <div class="column-header header-<?php echo $style; ?>">
                        <?php echo htmlspecialchars($title); ?> (<span class="task-count"
                            id="count-<?php echo Utils::createSafeId($title); ?>"><?php echo count($kanbanTasks[$title] ?? []); ?></span>)
                    </div>

                    <?php if ($title === 'SPRINTBACKLOG' && isset($currentProjectName) && $currentProjectName) : ?>
                        <button class="add-task-icon-only" id="addTaskToggle" onclick="toggleTaskInput()"
                            title="Új feladat hozzáadása">
                            ➕
                        </button>
                        <div class="add-task-input-form" id="addTaskInputForm" style="display: none;">
                            <input type="text" id="inline_task_description" placeholder="Feladat leírása" required>
                            <button type="button" class="submit-button add-task-submit" onclick="addTask(true)">
                                Hozzáadás
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

                                echo '<div class="task-card' . ($isImportant ? ' is-important' : '') . '" draggable="true" ondragstart="drag(event)" id="task-' . htmlspecialchars($task['id']) . '">';

                                echo '<button class="importance-toggle" onclick="toggleImportance(' . htmlspecialchars($task['id']) . ')" data-is-important="' . $isImportant . '" title="Fontosság beállítása">';
                                echo $isImportant ? '⭐' : '☆';
                                echo '</button>';

                                echo '<div class="task-menu-group">';

                                echo '<button class="task-menu-toggle" title="Beállítások" onclick="toggleTaskMenu(' . htmlspecialchars($task['id']) . ', this)">⋮</button>';

                                echo '<div id="task-menu-' . htmlspecialchars($task['id']) . '" class="task-actions-menu">';
                                echo '<button class="menu-action-button" title="Feladat szerkesztése" onclick="toggleEdit(' . htmlspecialchars($task['id']) . ', event)">✏️ Szerkesztés</button>';
                                echo '<button class="menu-action-button" title="Java Kód generálása" onclick="generateJavaCodeModal(' . htmlspecialchars($task['id']) . ', \'' . $safeDescription . '\')">💻 Kód generálása</button>';
                                echo '<button class="menu-action-button delete-action" title="Feladat törlése" onclick="deleteTask(' . htmlspecialchars($task['id']) . ', \'' . htmlspecialchars($title) . '\', \'' . $safeDescription . '\')">🗑️ Törlés</button>';
                                echo '</div>';
                                echo '</div>';

                                echo '<p class="card-description" id="desc-' . htmlspecialchars($task['id']) . '" contenteditable="false" data-original-content="' . htmlspecialchars($task['description']) . '">';
                                echo htmlspecialchars($task['description']);
                                echo '</p>';

                                echo '</div>';
                            }
                        } else {
                            echo '<div class="task-card empty-placeholder">';
                            echo '<p class="card-description" style="color: #6c757d; font-style: italic;">Nincsenek feladatok ebben az oszlopban.</p></div>';
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
                    Kód generálása folyamatban...
                </div>

                <div id="javaCodeLoadingIndicator" style="display: none; text-align: center; margin-top: 15px;">
                    <div class="spinner"></div>
                    <p>Java kód generálása folyamatban...</p>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="mainGenerationModal" style="display: none;">
            <div class="code-modal-content" style="max-width: 400px; text-align: center; padding: 40px 20px;">
                <h2 style="margin-bottom: 20px;">
                    Projekt feladatok generálása:
                    <strong id="generatingProjectNamePlaceholder">Projekt neve</strong>
                </h2>
                <div id="mainGenerationLoadingIndicator" style="text-align: center;">
                    <div class="spinner large-spinner"></div>
                    <p style="margin-top: 15px;">Az AI jelenleg szervezi a projektet.<br>Ez eltarthat 10-20 másodpercig.
                    </p>
                </div>
            </div>
        </div>
        <div class="modal-overlay" id="githubLoginModal" style="display: none;">
            <div class="modal-content github-config-modal">
                <button class="modal-close" onclick="closeGithubLoginModal()">x</button>
                <h2>
                    <img width="32" height="32" src="assets/images/github.png" alt="github">
                    GitHub bejelentkezés
                </h2>


                <div class="input-group">
                    <input type="text" id="github_username_input" placeholder="GitHub felhasználónév"
                        value="<?php echo htmlspecialchars($_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME') ?? ''); ?>"
                        required>
                </div>

                <div class="input-group">
                    <input type="text" id="github_repo_input" placeholder="GitHub repository neve"
                        value="<?php echo htmlspecialchars($_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO') ?? ''); ?>"
                        required>
                </div>
                <div class="input-group">
                    <div style="display: flex; align-items: center; gap: 8px; position: relative;">
                        <input type="password" id="github_pat" placeholder="GitHub Personal Access Token (PAT)" required
                            style="flex-grow: 1;">

                        <button type="button" class="help-button" onclick="showHelpMessage(this)"
                            data-help="A Personal Access Token-t (PAT) a GitHub profilod beállításaiban (Settings > Developer settings > Personal access tokens) tudod létrehozni. Szükséges 'repo' jogosultság!">
                            ?
                        </button>
                    </div>
                </div>

                <button type="button" class="submit-button" onclick="githubLogin()">
                    Bejelentkezés / Token Mentése
                </button>

                <div id="modalGithubStatus"
                    style="padding: 10px 0; font-size: 0.9em; color: #ffc107; font-style: italic;">
                    <?php
                    if (!$isServerConfigured) : ?>
                        ⚠️ **HIBA:** A szerver oldali repo adatok (GITHUB_REPO) hiányoznak a .env fájlból.
                    <?php else : ?>
                        ✔️ A szerver oldali repo beállítások rendben vannak.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
            window.currentProjectName = "<?php echo htmlspecialchars($currentProjectName ?? ''); ?>";
            const isGitHubRepoConfigured = <?php echo $isServerConfigured ? 'true' : 'false'; ?>;
            console.log("Projekt Név:", window.currentProjectName);
        </script>

        <script src="assets/js/script.js"></script>

</body>
</html>
