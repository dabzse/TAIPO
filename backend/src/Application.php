<?php

namespace App;

use App\Service\TaskService;
use App\Service\GitHubService;
use App\Utils;
use App\Config;
use App\Exception\WipLimitExceededException;
use App\Core\View;
use Exception;
use Dotenv\Dotenv;

class Application
{
    private TaskService $taskService;
    private GitHubService $githubService;

    public function run()
    {
        // Allow CORS
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $this->initEnvAndInput();

        $apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
        $dbFile = __DIR__ . '/../kanban.sqlite';

        $error = $this->initServices($dbFile);

        $columns = [
            'SPRINT BACKLOG' => 'info',
            'IMPLEMENTATION WIP:3' => 'danger',
            'TESTING WIP:2' => 'warning',
            'REVIEW WIP:2' => 'primary',
            'DONE' => 'success',
        ];

        $projectName = trim($_POST['project_name'] ?? '');
        $existingProjects = [];
        $currentProjectName = $this->resolveCurrentProject($projectName, $existingProjects, $error);

        if (!$error) {
            $this->dispatchActions($currentProjectName, $columns, $apiKey, $error);
        }

        $kanbanTasks = $this->loadKanbanTasks($currentProjectName, $columns, $error);

        // Check if client expects JSON (API mode)
        $isApiRequest = (
            (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            isset($_GET['api'])
        );

        if ($isApiRequest) {
            header(Config::APP_JSON);
            echo json_encode([
                'currentProjectName' => $currentProjectName,
                'existingProjects' => $existingProjects,
                'error' => $error,
                'columns' => array_keys($columns), // frontend might need keys or full obj
                'tasks' => $kanbanTasks
            ]);
            exit;
        }

        $isServerConfigured = !empty($_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO')) && !empty($_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME'));

        // Render the view using the namespace-imported View class
        View::render('index.view.php', [
            'currentProjectName' => $currentProjectName,
            'existingProjects' => $existingProjects,
            'error' => $error,
            'isServerConfigured' => $isServerConfigured,
            'columns' => $columns,
            'kanbanTasks' => $kanbanTasks
        ]);
    }

    private function initEnvAndInput(): void
    {
        try {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->safeLoad();
        } catch (Exception $e) {
            Utils::loadEnv(__DIR__ . '/../.env');
        }

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        ) {
            $json_data = file_get_contents('php://input');
            $_POST = array_merge($_POST, json_decode($json_data, true) ?? []);
        }
    }

    private function initServices(string $dbFile): ?string
    {
        $error = null;
        try {
            $this->taskService = new TaskService($dbFile);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        $this->githubService = new GitHubService(
            $_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN'),
            $_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME'),
            $_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO')
        );

        return $error;
    }

    private function resolveCurrentProject(string $projectName, array &$existingProjects, ?string &$error): string
    {
        $currentProjectName = trim($_GET['project'] ?? $projectName ?? '');
        $currentProjectName = trim($_POST['current_project'] ?? $currentProjectName);

        if (!$error) {
            try {
                $existingProjects = $this->taskService->getProjects();
                if (empty($currentProjectName) && !empty($existingProjects)) {
                    $currentProjectName = $existingProjects[0];
                }
            } catch (Exception $e) {
                $error = "Error loading projects: " . $e->getMessage();
            }
        }

        return $currentProjectName;
    }

    private function dispatchActions(string &$currentProjectName, array $columns, ?string $apiKey, ?string &$error): void
    {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            switch ($action) {
                case 'add_task':
                    $this->handleAddTask();
                    break;
                case 'delete_task':
                    $this->handleDeleteTask();
                    break;
                case 'toggle_importance':
                    $this->handleToggleImportance();
                    break;
                case 'update_status':
                    $this->handleUpdateStatus($currentProjectName, $columns);
                    break;
                case 'edit_task':
                    $this->handleEditTask();
                    break;
                case 'generate_java_code':
                    $this->handleGenerateJavaCode($apiKey);
                    break;
                case 'commit_to_github':
                    $this->handleCommitToGithub();
                    break;
                case 'decompose_task':
                    $this->handleDecomposeTask($currentProjectName, $apiKey);
                    break;
                default:
                    break;
            }
        }

        $projectName = trim($_POST['project_name'] ?? '');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($projectName) && !isset($_POST['action'])) {
            $error = $this->handleProjectGeneration($projectName, $apiKey);
        }
    }

    private function loadKanbanTasks(string $currentProjectName, array $columns, ?string &$error): array
    {
        $kanbanTasks = [];
        foreach ($columns as $col => $style) {
            $kanbanTasks[$col] = [];
        }

        if (!empty($currentProjectName) && !$error) {
            try {
                $tasks = $this->taskService->getTasksByProject($currentProjectName);
                foreach ($tasks as $task) {
                    if (isset($kanbanTasks[$task['status']])) {
                        $kanbanTasks[$task['status']][] = $task;
                    }
                }
            } catch (Exception $e) {
                $error = "Error reading data: " . $e->getMessage();
            }
        }

        return $kanbanTasks;
    }

    private function handleAddTask()
    {
        $newTaskDescription = trim($_POST['description'] ?? '');
        $projectForAdd = trim($_POST['current_project'] ?? '');
        $isImportant = (int)($_POST['is_important'] ?? 0);

        if (!empty($newTaskDescription) && !empty($projectForAdd)) {
            try {
                $newId = $this->taskService->addTask($projectForAdd, $newTaskDescription, $isImportant);
                header(Config::APP_JSON);
                echo json_encode(['success' => true, 'id' => $newId, 'description' => $newTaskDescription, 'is_important' => $isImportant]);
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

    private function handleDeleteTask()
    {
        $taskId = $_POST['task_id'] ?? null;

        if (is_numeric($taskId)) {
            try {
                $taskStatus = $this->taskService->deleteTask((int)$taskId);
                header(Config::APP_JSON);
                echo json_encode(['success' => true, 'status' => $taskStatus]);
                http_response_code(200);
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                http_response_code(500);
                error_log("Error deleting task: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => "Server error during deletion: " . $e->getMessage()]);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Invalid ID for deletion."]);
            exit;
        }
    }

    private function handleToggleImportance()
    {
        $taskId = $_POST['task_id'] ?? null;
        $isImportant = filter_var($_POST['is_important'] ?? 0, FILTER_VALIDATE_INT);

        if (is_numeric($taskId)) {
            try {
                $this->taskService->toggleImportance((int)$taskId, (int)$isImportant);
                header(Config::APP_JSON);
                echo "Success: Importance toggled for task ID {$taskId}";
                http_response_code(200);
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                error_log("Error toggling importance: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => "Server error during importance toggle."]);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Invalid ID for importance toggle."]);
            exit;
        }
    }

    private function handleUpdateStatus($currentProjectName, $columns)
    {
        $taskId = $_POST['task_id'] ?? null;
        $newStatus = $_POST['new_status'] ?? null;
        //$oldStatus = $_POST['old_status'] ?? null; // Unused in logic but present in POST
        $projectNameForWIP = trim($_POST['current_project'] ?? $currentProjectName);

        if (is_numeric($taskId) && in_array($newStatus, array_keys($columns))) {
            try {
                $this->taskService->updateStatus((int)$taskId, $newStatus, $projectNameForWIP);
                echo "Success: ID {$taskId}, new status: {$newStatus}";
                http_response_code(200);
                exit;
            } catch (WipLimitExceededException $e) {
                http_response_code(403);
                header(Config::APP_JSON);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            } catch (Exception $e) {
                $code = $e->getCode() ?: 500;
                http_response_code($code);
                error_log("Database update error: " . $e->getMessage());
                echo "Server error during status update: " . $e->getMessage();
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Error: Invalid ID or status value."]);
            exit;
        }
    }

    private function handleEditTask()
    {
        $taskId = $_POST['task_id'] ?? null;
        $newDescription = trim($_POST['description'] ?? '');

        if (is_numeric($taskId) && !empty($newDescription)) {
            try {
                $this->taskService->updateDescription((int)$taskId, $newDescription);
                header(Config::APP_JSON);
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
            echo json_encode(['success' => false, 'error' => "Error: Invalid ID or empty description."]);
            exit;
        }
    }

    private function handleGenerateJavaCode($apiKey)
    {
        $description = trim($_POST['description'] ?? '');

        if (empty($apiKey) || strpos($apiKey, 'AIza') !== 0) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => "Error: Gemini API key is not set."]);
            exit;
        }

        if (empty($description)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Error: Task description is missing."]);
            exit;
        }

        $prompt = "Generate a **complete, but very concise** Java class or function to solve the task: '{$description}'. The code should be **functional**, but only include the necessary imports and logic. Do not generate long explanatory comments or introduction text! Use a single Markdown code block (```java ... ```).";

        try {
            $rawText = Utils::callGeminiAPI($apiKey, $prompt);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'code' => Utils::formatCodeBlocks($rawText)]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Code generation error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Gemini API error: " . $e->getMessage()]);
            exit;
        }
    }

    private function handleCommitToGithub()
    {
        $taskId = $_POST['task_id'] ?? null;
        $description = $_POST['description'] ?? null;
        $code = $_POST['code'] ?? null;

        $userToken = $_POST['user_token'] ?? null;
        $userUsername = $_POST['user_username'] ?? null;

        // Create a temporary GitHub service with user provided credentials if available
        $token = $userToken ?: ($_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN'));
        $username = $userUsername ?: ($_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME'));
        $repo = $_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO');

        $ghService = new GitHubService($token, $username, $repo);

        if (empty($taskId) || empty($code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Error: Task ID or code is missing for the commit."]);
            exit;
        }

        $safeDescription = preg_replace('/[^a-zA-Z0-9\s]/', '', $description);
        $safeDescription = trim(substr($safeDescription, 0, 50));
        $fileName = 'Task_' . $taskId . '_' . str_replace(' ', '_', $safeDescription) . '.java';
        $filePath = 'src/main/java/' . $fileName;

        $commitMessage = "feat: Adds task implementation for: " . substr($description, 0, 70) . '...';

        try {
            $result = $ghService->commitFile($filePath, $code, $commitMessage);
            header(Config::APP_JSON);
            echo json_encode($result);
            exit;
        } catch (Exception $e) {
            $code = $e->getCode() ?: 500;
            http_response_code($code);
            error_log("GitHub commit hiba: HTTP {$code}. " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    private function handleProjectGeneration($projectName, $apiKey)
    {
        $rawPrompt = trim($_POST['ai_prompt'] ?? '');

        if (empty($apiKey) || strpos($apiKey, 'AIza') !== 0) {
            return "Error: Gemini API key is not set.";
        } elseif (empty($rawPrompt)) {
            return "Error: The AI prompt field cannot be empty.";
        }

        $prompt = str_replace('{{PROJECT_NAME}}', $projectName, $rawPrompt);

        try {
            $rawText = Utils::callGeminiAPI($apiKey, $prompt);

            $lines = explode("\n", $rawText);
            $newTasks = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $taskDescription = $line;
                $finalStatus = 'SPRINTBACKLOG';

                if (preg_match('/^\[(SPRINTBACKLOG|IMPLEMENTATION|TESTING|REVIEW|DONE)\]:\s*(.*)/iu', $line, $matches)) {
                    $taskDescription = trim($matches[2]);
                    $finalStatus = strtoupper($matches[1]);
                }

                if (!empty($taskDescription) && strlen($taskDescription) > 5) {
                    $newTasks[] = [
                        'description' => $taskDescription,
                        'status' => $finalStatus
                    ];
                }
            }

            $tasksAdded = $this->taskService->replaceProjectTasks($projectName, $newTasks);

            if ($tasksAdded < 5) {
                // If specific requirement was not met, we could set a variable to warn in view.
                // But since we are redirecting, we can't easily pass it unless via session or query param.
                // The original code passed 'tasksAdded' implicitly if variables were shared.
                // Here we redirect, so we lose it unless we stay.
                // The original code continued execution falling through to View render?
                // Step 425: header("Location: ...") exit;
                // So original code also exited.
            }

            header("Location: " . basename($_SERVER['SCRIPT_NAME']) . "?project=" . urlencode($projectName));
            exit;
        } catch (Exception $e) {
            return "Error during Gemini API call/save: " . $e->getMessage();
        }
    }

    private function handleDecomposeTask($currentProjectName, $apiKey)
    {
        $taskId = $_POST['task_id'] ?? null; // Not used in service but kept for consistency if needed later
        $desc = $_POST['description'] ?? '';

        if (empty($desc) || empty($currentProjectName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing description or project."]);
            exit;
        }

        try {
            $count = $this->taskService->decomposeTask($desc, $currentProjectName, $apiKey);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'count' => $count]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => "Error: " . $e->getMessage()]);
            exit;
        }
    }
}
