<?php

function loadEnv($filePath = '.env')
{
    if (!file_exists($filePath)) {
        return;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
loadEnv();

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
) {
    $json_data = file_get_contents('php://input');
    $_POST = array_merge($_POST, json_decode($json_data, true) ?? []);
}

$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
$dbFile = 'kanban.sqlite';

$columns = [
    'SPRINTBACKLOG' => 'info',
    'IMPLEMENTÁCIÓ WIP:3' => 'danger',
    'TESZTELÉS WIP:2' => 'warning',
    'REVIEW WIP:2' => 'primary',
    'KÉSZ' => 'success',
];
$kanbanTasks = [
    'SPRINTBACKLOG' => [],
    'IMPLEMENTÁCIÓ WIP:3' => [],
    'TESZTELÉS WIP:2' => [],
    'REVIEW WIP:2' => [],
    'KÉSZ' => [],
];

$projectName = trim($_POST['project_name'] ?? '');

$currentProjectName = trim($_GET['project'] ?? $projectName ?? '');
$currentProjectName = trim($_POST['current_project'] ?? $currentProjectName);


$error = null;
$db = null;
$existingProjects = [];

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_name TEXT NOT NULL,
            description TEXT NOT NULL,
            status TEXT NOT NULL CHECK (status IN ('SPRINTBACKLOG','IMPLEMENTÁCIÓ WIP:3', 'TESZTELÉS WIP:2', 'REVIEW WIP:2', 'KÉSZ')),
            is_important INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $stmtProjects = $db->query("SELECT DISTINCT project_name FROM tasks ORDER BY project_name ASC");
    $existingProjects = $stmtProjects->fetchAll(PDO::FETCH_COLUMN);

    if (empty($currentProjectName) && !empty($existingProjects)) {
        $currentProjectName = $existingProjects[0];
    }

} catch (Exception $e) {
    $error = "Hiba az adatbázis inicializálásakor: " . $e->getMessage();
}

if ($error) {
    goto skip_post_handlers;
}
function createSafeId($title)
{
    $title = str_replace(
        ['á', 'é', 'í', 'ó', 'ö', 'ő', 'ú', 'ü', 'ű', ' '],
        ['a', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'u', '_'],
        strtolower($title)
    );
    return preg_replace('/[^a-z0-9_]/', '', $title);
}
function getWIPLimit($columnTitle)
{
    if (preg_match('/WIP:(\d+)/i', $columnTitle, $matches)) {
        return (int) $matches[1];
    }
    return null;
}
function formatCodeBlocks($markdown)
{
    if (preg_match_all('/```(\w*)\n(.*?)```/s', $markdown, $matches)) {
        $output = '';
        foreach ($matches[2] as $index => $code) {
            $language = $matches[1][$index] ?: 'Kód';

            $taskId = $_POST['task_id'] ?? '';
            $description = htmlspecialchars($_POST['description'] ?? '');

            $isUserLoggedIn = !empty($_POST['user_token']);

            $output .= '<div class="code-block-wrapper">';
            $output .= '<div class="code-language-header">';
            $output .= '<span>' . htmlspecialchars(ucfirst($language)) . '</span>';

            $output .= '<span class="header-actions">';

            if (!empty($taskId) && $isUserLoggedIn) {
                $output .= '<button class="github-commit-button-inline" title="Commit a GitHubra" 
                            data-task-id="' . $taskId . '" 
                            data-description="' . $description . '" 
                            onclick="commitJavaCodeToGitHubInline(this)">
                            <svg height="16" aria-hidden="true" viewBox="0 0 16 16" version="1.1" width="16" style="fill: currentColor; vertical-align: middle;">
                                <path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path>
                            </svg>
                        </button>';
            }

            $output .= '<button class="copy-icon" title="Kód másolása" onclick="copyCodeBlock(this)">📋</button>';
            $output .= '</span>';

            $output .= '</div>';
            $output .= '<pre><code class="language-' . htmlspecialchars($language) . '">' . htmlspecialchars($code) . '</code></pre>';
            $output .= '</div>';
        }
        return $output;
    }
    return '<pre>' . htmlspecialchars($markdown) . '</pre>';
}
function callGeminiAPI($apiKey, $prompt)
{
    $model = 'gemini-2.5-flash';
    $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . $apiKey;

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 4096,
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("cURL hiba: " . $curlError);
    }

    $result = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorMessage = $result['error']['message'] ?? 'Ismeretlen hiba';
        throw new Exception("API hiba ({$httpCode}): " . $errorMessage . " - Válasz: " . $response);
    }

    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $blockReason = $result['candidates'][0]['finishReason'] ?? 'ismeretlen';
        throw new Exception("API válasz formátuma hibás (vagy blokkolva lett). Ok: " . $blockReason . ". Válasz: " . substr($response, 0, 500));
    }

    return $result['candidates'][0]['content']['parts'][0]['text'];
}

if (isset($_POST['action']) && $_POST['action'] === 'add_task') {
    $newTaskDescription = trim($_POST['description'] ?? '');
    $projectForAdd = trim($_POST['current_project'] ?? '');

    if (!empty($newTaskDescription) && !empty($projectForAdd)) {
        try {
            $insertStmt = $db->prepare("INSERT INTO tasks (project_name, description, status) VALUES (:project_name, :description, 'SPRINTBACKLOG')");
            $insertStmt->execute([
                ':project_name' => $projectForAdd,
                ':description' => $newTaskDescription
            ]);
            $newId = $db->lastInsertId();

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'id' => $newId, 'description' => $newTaskDescription]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Hiba történt a feladat hozzáadása során: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Szerver oldali hiba: " . $e->getMessage()]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "A projekt nevének és a feladat leírásának is szerepelnie kell."]);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_task') {
    $taskId = $_POST['task_id'] ?? null;

    if (is_numeric($taskId)) {
        try {
            $statusStmt = $db->prepare("SELECT status FROM tasks WHERE id = :id");
            $statusStmt->execute([':id' => $taskId]);
            $taskStatus = $statusStmt->fetchColumn();

            $deleteStmt = $db->prepare("DELETE FROM tasks WHERE id = :id");
            $deleteStmt->execute([':id' => $taskId]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'status' => $taskStatus]);
            http_response_code(200);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Hiba történt a feladat törlése során: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Szerver oldali hiba a törlés során."]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Hibás ID a törléshez."]);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'toggle_importance') {
    $taskId = $_POST['task_id'] ?? null;
    $isImportant = filter_var($_POST['is_important'] ?? 0, FILTER_VALIDATE_INT);

    if (is_numeric($taskId)) {
        try {
            $updateStmt = $db->prepare("UPDATE tasks SET is_important = :is_important WHERE id = :id");
            $updateStmt->execute([
                ':is_important' => $isImportant,
                ':id' => $taskId
            ]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            error_log("Hiba a fontosság frissítése során: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Szerver oldali hiba."]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Hibás ID."]);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'update_status') {

    $taskId = $_POST['task_id'] ?? null;
    $newStatus = $_POST['new_status'] ?? null;
    $oldStatus = $_POST['old_status'] ?? null;
    $projectNameForWIP = trim($_POST['current_project'] ?? $currentProjectName);

    if (is_numeric($taskId) && in_array($newStatus, array_keys($columns))) {
        $wipLimit = getWIPLimit($newStatus);

        if ($wipLimit !== null) {
            try {
                $countStmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE project_name = :projectName AND status = :status");
                $countStmt->execute([
                    ':projectName' => $projectNameForWIP,
                    ':status' => $newStatus
                ]);
                $currentTaskCount = $countStmt->fetchColumn();

                if ($currentTaskCount >= $wipLimit) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => "WIP Korlát túllépés: A(z) '{$newStatus}' oszlop maximális korlátja {$wipLimit} feladat."]);
                    exit;
                }
            } catch (Exception $e) {
                http_response_code(500);
                error_log("WIP ellenőrzési hiba: " . $e->getMessage());
                echo "Szerver oldali hiba a WIP ellenőrzése során: " . $e->getMessage();
                exit;
            }
        }
        try {
            $updateStmt = $db->prepare("UPDATE tasks SET status = :status WHERE id = :id");
            $updateStmt->execute([
                ':status' => $newStatus,
                ':id' => $taskId
            ]);

            echo "Sikeres frissítés: ID {$taskId}, új státusz: {$newStatus}";
            http_response_code(200);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            error_log("Hiba történt az adatbázis frissítése során: " . $e->getMessage());
            echo "Szerver oldali hiba a státusz frissítése során.";
            exit;
        }
    } else {
        http_response_code(400);
        echo "Hibás ID vagy státusz érték.";
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'edit_task') {
    $taskId = $_POST['task_id'] ?? null;
    $newDescription = trim($_POST['description'] ?? '');

    if (is_numeric($taskId) && !empty($newDescription)) {
        try {
            $updateStmt = $db->prepare("UPDATE tasks SET description = :description WHERE id = :id");
            $updateStmt->execute([
                ':description' => $newDescription,
                ':id' => $taskId
            ]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            http_response_code(200);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            error_log("Hiba történt a feladat leírásának frissítése során: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Szerver oldali hiba a leírás frissítése során."]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Hibás ID vagy üres leírás."]);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'generate_java_code') {

    $description = trim($_POST['description'] ?? '');

    if (empty($apiKey) || strpos($apiKey, 'AIza') !== 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Hiba: A Gemini API kulcs nincs beállítva."]);
        exit;
    }

    if (empty($description)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Hiányzó vagy üres feladatleírás."]);
        exit;
    }

    $prompt = "A következő feladatot kell megoldani: '{$description}'. Generálj egy **komplett, de nagyon tömör** Java osztályt vagy függvényt a feladat megoldásához. A kód legyen **működőképes**, de csak a szükséges importokat és logikát tartalmazza. Ne generálj hosszú magyarázó kommenteket és bevezető szöveget! A kimenetben használj egyetlen Markdown kódblokkot (```java ... ```).";

    try {
        $rawText = callGeminiAPI($apiKey, $prompt);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'code' => formatCodeBlocks($rawText)]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        error_log("Kódgenerálási hiba: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => "Gemini API hiba: " . $e->getMessage()]);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'commit_to_github') {
    $taskId = $_POST['task_id'] ?? null;
    $description = $_POST['description'] ?? null;
    $code = $_POST['code'] ?? null;

    $userToken = $_POST['user_token'] ?? null;
    $userUsername = $_POST['user_username'] ?? null;

    $githubToken = $userToken ?: ($_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN'));
    $githubUsername = $userUsername ?: ($_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME'));
    $githubRepo = $_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO');

    if (empty($githubToken)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => "A commitoláshoz be kell jelentkezni GitHub token (PAT) megadásával! (Hiányzó token)"]);
        exit;
    }

    if (empty($githubUsername) || empty($githubRepo)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Hiányzó GitHub konfiguráció (.env: GITHUB_REPO, GITHUB_USERNAME). Kérlek, állítsd be a szerveren!"]);
        exit;
    }

    if (empty($taskId) || empty($code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Hiba: A kód vagy a feladat adatai hiányoznak a commitoláshoz."]);
        exit;
    }

    $safeDescription = preg_replace('/[^a-zA-Z0-9\s]/', '', $description);
    $safeDescription = trim(substr($safeDescription, 0, 50));
    $fileName = 'Task_' . $taskId . '_' . str_replace(' ', '_', $safeDescription) . '.java';
    $filePath = 'src/main/java/' . $fileName;

    $encodedContent = base64_encode($code);
    $commitMessage = "feat: Adds task implementation for: " . substr($description, 0, 70) . '...';

    $url = "https://api.github.com/repos/{$githubUsername}/{$githubRepo}/contents/{$filePath}";

    $payload = json_encode([
        'message' => $commitMessage,
        'content' => $encodedContent,
        'branch' => 'main'
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $githubToken,
        'Content-Type: application/json',
        'User-Agent: AI-Kanban-App'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($httpCode === 201) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'filePath' => $filePath]);
        exit;
    } elseif ($httpCode === 422 && strpos($result['message'] ?? '', 'sha') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => "A fájl már létezik a GitHubon: '{$filePath}'. Töröld/nevezd át a frissítéshez."]);
        exit;
    } else {
        http_response_code($httpCode > 0 ? $httpCode : 500);
        error_log("GitHub commit hiba: HTTP {$httpCode}. Válasz: " . $response);
        echo json_encode(['success' => false, 'error' => "GitHub API hiba ({$httpCode}): " . ($result['message'] ?? 'Ismeretlen hiba.')]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($projectName) && !isset($_POST['action'])) {

    $rawPrompt = trim($_POST['ai_prompt'] ?? '');

    if (empty($apiKey) || strpos($apiKey, 'AIza') !== 0) {
        $error = "Hiba: A Gemini API kulcs nincs beállítva.";
    } elseif (empty($rawPrompt)) {
        $error = "Hiba: Az AI utasítás (Prompt) mező nem lehet üres!";
    }

    if (!$error) {
        $prompt = str_replace('{{PROJECT_NAME}}', $projectName, $rawPrompt);

        try {
            $rawText = callGeminiAPI($apiKey, $prompt);

            $db->beginTransaction();

            $stmt = $db->prepare("DELETE FROM tasks WHERE project_name = :projectName");
            $stmt->execute([':projectName' => $projectName]);

            $lines = explode("\n", $rawText);
            $tasksAdded = 0;

            $insertStmt = $db->prepare(
                "INSERT INTO tasks (project_name, description, status) VALUES (:project_name, :description, :status)"
            );

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line))
                    continue;

                $taskDescription = $line;
                $finalStatus = 'SPRINTBACKLOG';

                if (preg_match('/^\[(SPRINTBACKLOG|IMPLEMENTÁCIÓ|TESZTELÉS|REVIEW|KÉSZ)\]:\s*(.*)/iu', $line, $matches)) {
                    $taskDescription = trim($matches[2]);
                    $finalStatus = strtoupper($matches[1]);
                }

                if (!empty($taskDescription) && strlen($taskDescription) > 5) {
                    $insertStmt->execute([
                        ':project_name' => $projectName,
                        ':description' => $taskDescription,
                        ':status' => $finalStatus
                    ]);
                    $tasksAdded++;
                }
            }

            $db->commit();

            if ($tasksAdded < 5) {
                $error = "Figyelem: Csak {$tasksAdded} feladatot sikerült generálni. Lehet, hogy a válasz formátuma nem megfelelő.";
            }
            header("Location: index.php?project=" . urlencode($projectName));
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Hiba történt a Gemini API hívás/mentés során: " . $e->getMessage();
        }
    }
}
skip_post_handlers:

if ($db && !empty($currentProjectName) && !$error) {
    try {
        $stmt = $db->prepare("SELECT id, description, status, is_important FROM tasks WHERE project_name = :projectName ORDER BY id ASC");
        $stmt->execute([':projectName' => $currentProjectName]);

        $kanbanTasks = ['SPRINTBACKLOG' => [], 'IMPLEMENTÁCIÓ WIP:3' => [], 'TESZTELÉS WIP:2' => [], 'REVIEW WIP:2' => [], 'KÉSZ' => [],];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($kanbanTasks[$row['status']])) {
                $kanbanTasks[$row['status']][] = [
                    'id' => $row['id'],
                    'description' => $row['description'],
                    'is_important' => $row['is_important']
                ];
            }
        }
    } catch (Exception $e) {
        $error = "Hiba történt az adatok olvasása során: " . $e->getMessage();
    }
}

$isServerConfigured = !empty($_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO')) && !empty($_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME'));
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-vezérelt Kanban</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="project-menu-container">
        <button class="menu-toggle-button menu-icon" onclick="toggleMenu()" title="Projekt beállítások">
            ☰
        </button>

        <div class="project-menu-dropdown" id="projectDropdown">
            <button type="button" class="menu-close-button" onclick="toggleMenu()" title="Menü bezárása">x</button>
            <form method="POST" action="index.php" id="projectForm" class="menu-form">
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
                <?php if (!empty($existingProjects)): ?>
                    <p class="menu-label" style="margin-top: 15px;">Vagy válassz egy meglévő projektet:
                    </p>
                    <select id="project_selector" onchange="loadProject(this.value)" class="project-select-dropdown">

                        <option value="" <?php echo empty($currentProjectName) ? 'selected' : ''; ?>>-- Projekt betöltése --
                        </option>
                        <?php foreach ($existingProjects as $proj): ?>
                            <option value="<?php echo urlencode($proj); ?>" <?php echo ($proj === $currentProjectName) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($proj); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button type="button" class="submit-button github-login-toggle-button" onclick="openGithubLoginModal()">
                    <img width="32" height="32" src="https://img.icons8.com/windows/32/228BE6/github.png"
                        alt="github" />
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
        <?php if (isset($currentProjectName) && $currentProjectName): ?>
            <div class="project-status-info">
                Aktuális Projekt: <strong><?php echo htmlspecialchars($currentProjectName); ?></strong>
            </div>
        <?php else: ?>
            <div class="project-status-info" style="font-style: italic; color: #6c757d; padding: 5px;">
                Generálj egy projektet a menüben!
            </div>
        <?php endif; ?>

        <div class="message-container">
            <?php if (isset($error)): ?>
                <div class="error-box">
                    ❌ Hiba:<?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (isset($tasksAdded) && $tasksAdded < 5 && $tasksAdded > 0): ?>
                <div class="warning-box">
                    ⚠️ Figyelem: Csak <?php echo $tasksAdded; ?> feladatot sikerült generálni.
                </div>
            <?php elseif (isset($currentProjectName) && $currentProjectName && empty($error) && (!isset($_POST['action']) || $_POST['action'] !== 'add_task')): ?>
                <div class="success-box" id="global-message-box">
                    ✅ Feladatok sikeresen betöltve a(z) "<?php echo htmlspecialchars($currentProjectName); ?>" projekthez!
                </div>
            <?php endif; ?>
        </div>

        <div class="kanban-board">
            <?php foreach ($columns as $title => $style): ?>
                <div class="kanban-column" ondragover="allowDrop(event)" ondrop="drop(event)"
                    data-status="<?php echo htmlspecialchars($title); ?>">
                    <div class="column-header header-<?php echo $style; ?>">
                        <?php echo htmlspecialchars($title); ?> (<span class="task-count"
                            id="count-<?php echo createSafeId($title); ?>"><?php echo count($kanbanTasks[$title] ?? []); ?></span>)
                    </div>

                    <?php if ($title === 'SPRINTBACKLOG' && isset($currentProjectName) && $currentProjectName): ?>
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

                    <div class="task-list" id="col-<?php echo createSafeId($title); ?>">
                        <?php
                        $hasTasks = !empty($kanbanTasks[$title]);

                        if ($hasTasks) {
                            foreach ($kanbanTasks[$title] as $task) {
                                $safeDescription = htmlspecialchars(addslashes($task['description']));
                                $isImportant = (int) $task['is_important'];

                                echo '<div class="task-card' . ($isImportant ? ' is-important' : '') . '" draggable="true" ondragstart="drag(event)" id="task-' . htmlspecialchars($task['id']) . '">';

                                echo '<button class="importance-toggle" onclick="toggleImportance(' . htmlspecialchars($task['id']) . ')" data-is-important="' . $isImportant . '" title="Fontosság beállítása">';
                                echo ($isImportant ? '⭐' : '☆');
                                echo '</button>';

                                echo '<div class="task-menu-group">';

                                echo '<button class="task-menu-toggle" title="Beállítások" onclick="toggleTaskMenu(' . htmlspecialchars($task['id']) . ', this)">⋮</button>';

                                echo '<div id="task-menu-' . htmlspecialchars($task['id']) . '" class="task-actions-menu">';
                                echo '<button class="menu-action-button" title="Feladat szerkesztése" onclick="toggleEdit(' . htmlspecialchars($task['id']) . ', event)">✏️ Szerkesztés</button>';
                                echo '<button class="menu-action-button" title="Java Kód generálása" onclick="generateJavaCodeModal(' . htmlspecialchars($task['id']) . ', \'' . $safeDescription . '\')">💻 Kód generálása</button>';
echo '<button class="menu-action-button delete-action" title="Feladat törlése" onclick="deleteTask(' . htmlspecialchars($task['id']) . ', \'' . htmlspecialchars($title) . '\', \'' . $safeDescription . '\')">🗑️ Törlés</button>';                                echo '</div>';
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
                    <img width="32" height="32" src="https://img.icons8.com/windows/32/228BE6/github.png"
                        alt="github" />
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
                    if (!$isServerConfigured):
                        ?>
                        ⚠️ **HIBA:** A szerver oldali repo adatok (GITHUB_REPO) hiányoznak a .env fájlból.
                    <?php else: ?>
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

        <script src="script.js"></script>

</body>

</html>