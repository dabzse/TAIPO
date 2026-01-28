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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $json_data = file_get_contents('php://input');
    $_POST = array_merge($_POST, json_decode($json_data, true) ?? []);
}

$apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
$dbFile = 'kanban.sqlite';

$columns = [
    'SPRINT BACKLOG' => 'info',
    'IMPLEMENTATION WIP:3' => 'danger',
    'TESTING WIP:2' => 'warning',
    'REVIEW WIP:2' => 'primary',
    'DONE' => 'success',
];
$kanbanTasks = [
    'SPRINT BACKLOG' => [],
    'IMPLEMENTATION WIP:3' => [],
    'TESTING WIP:2' => [],
    'REVIEW WIP:2' => [],
    'DONE' => [],
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
            status TEXT NOT NULL CHECK (status IN ('SPRINT BACKLOG','IMPLEMENTATION WIP:3', 'TESTING WIP:2', 'REVIEW WIP:2', 'DONE')),
            is_important INTEGER DEFAULT 0,
            generated_code TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            po_comments TEXT DEFAULT NULL,
            is_subtask INTEGER DEFAULT 0
        );
    ");

    $stmtProjects = $db->query("SELECT DISTINCT project_name FROM tasks ORDER BY project_name ASC");
    $existingProjects = $stmtProjects->fetchAll(PDO::FETCH_COLUMN);

    if (empty($currentProjectName) && !empty($existingProjects)) {
        $currentProjectName = $existingProjects[0];
    }

} catch (Exception $e) {
    $error = "Error initializing database: " . $e->getMessage();
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
            $language = $matches[1][$index] ?: 'java';
            $taskId = $_POST['task_id'] ?? '';
            $description = htmlspecialchars($_POST['description'] ?? '');

            $output .= '<div class="code-block-wrapper">';
            $output .= '<div class="code-language-header"><span>' . htmlspecialchars(ucfirst($language)) . '</span><span class="header-actions">';

            if (!empty($taskId)) {
                $output .= '<button class="github-commit-button-inline" title="Commit to GitHub" 
                            data-task-id="' . $taskId . '" 
                            data-description="' . $description . '" 
                            onclick="commitJavaCodeToGitHubInline(this)">
                            <svg height="16" viewBox="0 0 16 16" width="16" style="fill: currentColor; vertical-align: middle;">
                                <path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path>
                            </svg>
                        </button>';
            }

            $output .= '<button class="copy-icon" title="Copy code" onclick="copyCodeBlock(this)">📋</button></span></div>';
            $output .= '<pre><code id="editable-java-code" contenteditable="true" class="language-' . htmlspecialchars($language) . '" spellcheck="false">' . htmlspecialchars($code) . '</code></pre>';
            $output .= '</div>';
        }
        return $output;
    }
    return '<div class="code-block-wrapper"><div class="code-language-header"><span>Java</span><span class="header-actions"><button class="copy-icon" onclick="copyCodeBlock(this)">📋</button></span></div><pre><code id="editable-java-code" contenteditable="true" spellcheck="false">' . htmlspecialchars($markdown) . '</code></pre></div>';
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
        throw new Exception("cURL error: " . $curlError);
    }

    $result = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorMessage = $result['error']['message'] ?? 'Unknown error';
        throw new Exception("API error ({$httpCode}): " . $errorMessage . " - Response: " . $response);
    }

    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $blockReason = $result['candidates'][0]['finishReason'] ?? 'unknown';
        throw new Exception("API response format error (or blocked). Reason: " . $blockReason . ". Response: " . substr($response, 0, 500));
    }

    return $result['candidates'][0]['content']['parts'][0]['text'];
}

if (isset($_POST['action']) && $_POST['action'] === 'add_task') {
    $newTaskDescription = trim($_POST['description'] ?? '');
    $projectForAdd = trim($_POST['current_project'] ?? '');

    if (!empty($newTaskDescription) && !empty($projectForAdd)) {
        try {
            $insertStmt = $db->prepare("INSERT INTO tasks (project_name, description, status) VALUES (:project_name, :description, 'SPRINT BACKLOG')");
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
            error_log("Error adding task: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Server error: " . $e->getMessage()]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Project name and task description are required."]);
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
            error_log("Error deleting task: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Server error during deletion."]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Invalid ID for deletion."]);
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
            error_log("Error updating importance: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Server error."]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Invalid ID."]);
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
                    echo json_encode(['success' => false, 'error' => "WIP Limit exceeded: The '{$newStatus}' column has a maximum limit of {$wipLimit} tasks."]);
                    exit;
                }
            } catch (Exception $e) {
                http_response_code(500);
                error_log("WIP check error: " . $e->getMessage());
                echo "Server error during WIP check: " . $e->getMessage();
                exit;
            }
        }
        try {
            $updateStmt = $db->prepare("UPDATE tasks SET status = :status WHERE id = :id");
            $updateStmt->execute([
                ':status' => $newStatus,
                ':id' => $taskId
            ]);

            echo "Update successful: ID {$taskId}, new status: {$newStatus}";
            http_response_code(200);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            error_log("Database update error: " . $e->getMessage());
            echo "Server error during status update.";
            exit;
        }
    } else {
        http_response_code(400);
        echo "Invalid ID or status value.";
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
            error_log("Error updating task description: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Server error during description update."]);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Invalid ID or empty description."]);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'generate_java_code') {
    $taskId = $_POST['task_id'] ?? null;
    $description = trim($_POST['description'] ?? '');

    if (!$taskId) {
        echo json_encode(['success' => false, 'error' => "Missing Task ID"]);
        exit;
    }

    $stmtCheck = $db->prepare("SELECT generated_code FROM tasks WHERE id = :id");
    $stmtCheck->execute([':id' => $taskId]);
    $savedCode = $stmtCheck->fetchColumn();

    if (!empty($savedCode)) {
        echo json_encode(['success' => true, 'code' => formatCodeBlocks($savedCode), 'cached' => true]);
        exit;
    }

    $stmtCtx = $db->prepare("SELECT description FROM tasks WHERE project_name = :projectName");
    $stmtCtx->execute([':projectName' => $currentProjectName]);
    $allTasks = $stmtCtx->fetchAll(PDO::FETCH_COLUMN);
    $contextTasks = implode(", ", $allTasks);

    $prompt = "You are an expert Java developer. Your goal is to implement a sub-task for the project: '{$currentProjectName}'.
    The entire project consists of these tasks: [{$contextTasks}].
    Current task to implement: '{$description}'.

    Instructions:
    1. Use the package name: 'com.ai.project'.
    2. If this task implies a main entry point, name the class 'Main'.
    3. Otherwise, use a clear, logical class name based on the task (e.g., 'SnakeGame', 'Grid').
    4. Ensure variables and method names are consistent across tasks.
    5. Provide ONLY the complete Java code in a single Markdown block.
    6. The code must be production-ready and compileable within this project context.";
    try {
        $rawText = callGeminiAPI($apiKey, $prompt);
        $updateStmt = $db->prepare("UPDATE tasks SET generated_code = :code WHERE id = :id");
        $updateStmt->execute([':code' => $rawText, ':id' => $taskId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'code' => formatCodeBlocks($rawText), 'cached' => false]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'commit_to_github') {
    $taskId = $_POST['task_id'] ?? null;
    $description = $_POST['description'] ?? '';
    $code = $_POST['code'] ?? null;

    $userToken = $_POST['user_token'] ?? null;
    $userUsername = $_POST['user_username'] ?? null;
    $userRepo = $_POST['user_repo'] ?? null;

    $githubToken = $userToken ?: ($_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN'));
    $githubUsername = $userUsername ?: ($_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME'));
    $githubRepo = $userRepo ?: ($_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO'));

    if (empty($githubToken) || empty($githubUsername) || empty($githubRepo)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => "Missing GitHub credentials."]);
        exit;
    }

    if (empty($taskId) || empty($code)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => "Code or task ID missing."]);
        exit;
    }

    $fileName = "Task_" . $taskId . ".java";
    if (preg_match('/public class\s+(\w+)/', $code, $matches)) {
        $fileName = $matches[1] . ".java";
    }

    $filePath = 'src/main/java/com/ai/project/' . $fileName;
    $url = "https://api.github.com/repos/{$githubUsername}/{$githubRepo}/contents/{$filePath}";

    $ch_get = curl_init($url);
    curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_get, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $githubToken,
        'User-Agent: AI-Kanban-App'
    ]);
    $get_response = curl_exec($ch_get);
    $get_data = json_decode($get_response, true);
    $sha = isset($get_data['sha']) ? $get_data['sha'] : null;
    curl_close($ch_get);

    $encodedContent = base64_encode($code);
    $commitMessage = ($sha ? "fix: Update" : "feat: Add") . " implementation for task: " . substr($description, 0, 50);

    $payload = [
        'message' => $commitMessage,
        'content' => $encodedContent,
        'branch' => 'main'
    ];

    if ($sha) {
        $payload['sha'] = $sha;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $githubToken,
        'User-Agent: AI-Kanban-App',
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    header('Content-Type: application/json');
    if ($httpCode === 200 || $httpCode === 201) {
        echo json_encode(['success' => true, 'filePath' => $filePath, 'method' => $sha ? 'updated' : 'created']);
    } else {
        $githubError = json_decode($response, true);
        echo json_encode([
            'success' => false,
            'error' => "GitHub API error ($httpCode): " . ($githubError['message'] ?? 'Unknown error')
        ]);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'decompose_task') {
    $taskId = $_POST['task_id'];
    $desc = $_POST['description'];
    $proj = $_POST['current_project'];

    $prompt = "Decompose this user story into 3-5 concrete, executable technical tasks: '{$desc}'. 
               Your response must ONLY be the list of tasks, with each task on a new line.";

    $rawTasks = callGeminiAPI($apiKey, $prompt);
    $lines = explode("\n", $rawTasks);
    $count = 0;
    
    foreach ($lines as $line) {
        if (trim($line)) {
            $stmt = $db->prepare("INSERT INTO tasks (project_name, description, status, is_subtask, po_comments) VALUES (?, ?, 'SPRINT BACKLOG', 1, ?)");
            
            $poFeedback = "TAIPO PO: Based on original story: \"{$desc}\"";
            
            $stmt->execute([$proj, trim($line), $poFeedback]);
            $count++;
        }
    }
    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($projectName) && !isset($_POST['action'])) {

    $rawPrompt = trim($_POST['ai_prompt'] ?? '');

    if (empty($apiKey) || strpos($apiKey, 'AIza') !== 0) {
        $error = "Error: Gemini API key is not set.";
    } elseif (empty($rawPrompt)) {
        $error = "Error: The AI prompt field cannot be empty!";
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
                $finalStatus = 'SPRINT BACKLOG';

                if (preg_match('/^\[(SPRINT BACKLOG|IMPLEMENTATION|TESTING|REVIEW|DONE)\]:\s*(.*)/iu', $line, $matches)) {
                    $taskDescription = trim($matches[2]);
                    $finalStatus = strtoupper($matches[1]);
                    if ($finalStatus == 'IMPLEMENTATION')
                        $finalStatus = 'IMPLEMENTATION WIP:3';
                    if ($finalStatus == 'TESTING')
                        $finalStatus = 'TESTING WIP:2';
                    if ($finalStatus == 'REVIEW')
                        $finalStatus = 'REVIEW WIP:2';
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
                $error = "Warning: Only {$tasksAdded} tasks were generated...";
            }
            header("Location: index.php?project=" . urlencode($projectName) . "&generated=1");
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Error during Gemini API call/save: " . $e->getMessage();
        }
    }
}
skip_post_handlers:

if ($db && !empty($currentProjectName) && !$error) {
    try {
        $stmt = $db->prepare("SELECT id, description, status, is_important, generated_code, po_comments, is_subtask FROM tasks WHERE project_name = :projectName ORDER BY id ASC");
        $stmt->execute([':projectName' => $currentProjectName]);

        $kanbanTasks = ['SPRINT BACKLOG' => [], 'IMPLEMENTATION WIP:3' => [], 'TESTING WIP:2' => [], 'REVIEW WIP:2' => [], 'DONE' => [],];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($kanbanTasks[$row['status']])) {
                $kanbanTasks[$row['status']][] = [
                    'id' => $row['id'],
                    'description' => $row['description'],
                    'is_important' => $row['is_important'],
                    'has_code' => !empty($row['generated_code']),
                    'po_comments' => $row['po_comments'],
                    'is_subtask' => $row['is_subtask']
                ];
            }
        }
    } catch (Exception $e) {
        $error = "Error reading data: " . $e->getMessage();
    }
}

$isServerConfigured = !empty($_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO')) && !empty($_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Driven Kanban</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="project-menu-container">
        <button class="menu-toggle-button menu-icon" onclick="toggleMenu()" title="Project Settings">
            ☰
        </button>

        <div class="project-menu-dropdown" id="projectDropdown">
            <button type="button" class="menu-close-button" onclick="toggleMenu()" title="Close menu">x</button>
            <form method="POST" action="index.php" id="projectForm" class="menu-form">
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
                    <button type="button" class="help-button" onclick="loadDefaultPrompt()" title="Load default prompt">
                        ❓
                    </button>
                </p>

                <?php
                $defaultPrompt = "Plan a project named {{PROJECT_NAME}}! Generate at least 10 tasks for the Kanban board covering basic development steps. Provide each task on a new line without any prefix (e.g. [SPRINT BACKLOG]:) so they all go into the **SPRINT BACKLOG** column. Do not include introductory text."; ?>
                <textarea id="ai_prompt" name="ai_prompt" rows="5" class="prompt-textarea" required
                    placeholder="AI prompt..."
                    data-default-prompt="<?php echo htmlspecialchars($defaultPrompt); ?>"></textarea>
                <?php if (!empty($existingProjects)): ?>
                    <p class="menu-label" style="margin-top: 15px;">Or choose an existing project:
                    </p>
                    <select id="project_selector" onchange="loadProject(this.value)" class="project-select-dropdown">

                        <option value="" <?php echo empty($currentProjectName) ? 'selected' : ''; ?>>-- Load Project --
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
        <?php if (isset($currentProjectName) && $currentProjectName): ?>
            <div class="project-status-info">
                Current Project: <strong><?php echo htmlspecialchars($currentProjectName); ?></strong>
            </div>
        <?php else: ?>
            <div class="project-status-info" style="font-style: italic; color: #6c757d; padding: 5px;">
                Generate a project in the menu!
            </div>
        <?php endif; ?>

        <div class="message-container">
            <?php if (isset($error)): ?>
                <div class="error-box">
                    ❌ Error: <?php echo htmlspecialchars($error); ?>
                </div>
            <?php elseif (isset($tasksAdded) && $tasksAdded > 0): ?>
                <div class="success-box" id="global-message-box">
                    ✅ Tasks successfully generated for project "<?php echo htmlspecialchars($currentProjectName); ?>"!
                </div>
            <?php elseif (isset($_GET['generated']) && $_GET['generated'] === '1'): ?>
                <div class="success-box" id="global-message-box">
                    ✅ Tasks successfully generated for project "<?php echo htmlspecialchars($currentProjectName); ?>"!
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
                    <?php
                    $isBacklogColumn = (strpos(strtoupper($title), 'BACKLOG') !== false);

                    if ($isBacklogColumn && isset($currentProjectName) && !empty($currentProjectName)):
                        ?>
                        <button class="add-task-icon-only" id="addTaskToggle" onclick="toggleTaskInput()" title="Add new task">
                            ➕
                        </button>
                        <div class="add-task-input-form" id="addTaskInputForm" style="display: none;">
                            <input type="text" id="inline_task_description" placeholder="Task description" required>
                            <button type="button" class="submit-button add-task-submit" onclick="addTask(true)">
                                Add
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="task-list" id="col-<?php echo createSafeId($title); ?>">
                        <?php
                        $hasTasks = !empty($kanbanTasks[$title]);

                        if ($hasTasks) {
                            foreach ($kanbanTasks[$title] as $task) {
                                $isSubtask = (isset($task['is_subtask']) && (int) $task['is_subtask'] === 1);
                                $isSubtaskClass = $isSubtask ? ' is-subtask' : '';
                                $poComments = $task['po_comments'] ?? '';
                                $safeDescription = htmlspecialchars(addslashes($task['description']));
                                $isImportant = (int) ($task['is_important'] ?? 0);
                                $hasCodeClass = (!empty($task['has_code'])) ? ' has-ai-code' : '';

                                echo '<div class="task-card' . ($isImportant ? ' is-important' : '') . $hasCodeClass . $isSubtaskClass . '" draggable="true" ondragstart="drag(event)" id="task-' . htmlspecialchars($task['id']) . '" data-current-status="' . htmlspecialchars($title) . '">';

                                if (!empty($task['has_code'])) {
                                    echo '<div class="ai-code-indicator" title="AI code already generated">🤖</div>';
                                }

                                echo '<button class="importance-toggle" onclick="toggleImportance(' . htmlspecialchars($task['id']) . ')" data-is-important="' . $isImportant . '" title="Set importance">';
                                echo ($isImportant ? '⭐' : '☆');
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
                            echo '<p class="card-description" style="color: #6c757d; font-style: italic;">No tasks in this column.</p></div>';
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
                    <img width="32" height="32" src="https://img.icons8.com/windows/32/228BE6/github.png"
                        alt="github" />
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
                    if (!$isServerConfigured):
                        ?>
                        ⚠️ **ERROR:** Server-side repo data (GITHUB_REPO) is missing from the .env file.
                    <?php else: ?>
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

        <script src="script.js"></script>

</body>

</html>