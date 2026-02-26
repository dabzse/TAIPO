<?php

namespace App;

use App\Service\TaskService;
use App\Service\ProjectService;
use App\Service\GitHubService;
use App\Service\GeminiService;
use App\Controller\TaskController;
use App\Controller\ProjectController;
use App\Controller\SettingsController;
use App\Controller\RequirementController;
use App\Controller\AuthController;
use App\Service\SettingsService;
use App\Service\RequirementService;
use App\Exception\GeminiApiException;
use App\Exception\ProjectAlreadyExistsException;
use App\Utils;
use App\Config;
use Exception;
use App\Database;
use Dotenv\Dotenv;

class Application
{
    private TaskService $taskService;
    private ProjectService $projectService;
    private GitHubService $githubService;
    private GeminiService $geminiService;
    private TaskController $taskController;
    private ProjectController $projectController;
    private SettingsController $settingsController;
    private RequirementService $requirementService;
    private RequirementController $requirementController;
    private AuthController $authController;

    public function run()
    {
        // Allow CORS with safety checks
        $allowedOrigins = explode(',', $_ENV['ALLOWED_ORIGINS'] ?? getenv('ALLOWED_ORIGINS') ?: '');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (!empty($origin) && in_array($origin, $allowedOrigins)) {
            // Allow if origin is in whitelist or if it's a same-origin request (often empty origin for standard navigation)
            // But relying on empty origin for API calls from browsers is tricky, usually browsers send Origin.
            // For now, let's just echo back the origin if it matches.
            header("Access-Control-Allow-Origin: $origin");
        }
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Start session before any output
        session_set_cookie_params([
            'lifetime' => 86400 * 30, // 30 days
            'path' => '/',
            // Domain omitted to allow the browser to use the request host (fixes localhost Vite proxy issues)
            'secure' => isset($_ENV['FORCE_HTTPS']) && $_ENV['FORCE_HTTPS'] === 'true', // Use environment variable for HTTPS
            'httponly' => true,
            'samesite' => 'Lax' // Or 'Strict' depending on cross-site needs
        ]);
        session_start();

        $this->initEnvAndInput();

        $dbFile = __DIR__ . '/../kanban.sqlite';

        $error = $this->initServices($dbFile);

        if ($error) {
            // If DB connection fails, we can't do much but show error
            echo "Critical Error: " . $error;
            exit;
        }

        // Router / Dispatcher Logic

        $action = $_POST['action'] ?? $_GET['action'] ?? null;

        // Existing actions delegating to Controllers
        if ($action) {
            $this->routeApiAction($action);
        }

        // Default View Rendering
        $this->handleApiData($error);
    }

    private function routeApiAction(string $action): void
    {
        // Public Actions (No Auth Required)
        switch ($action) {
            case 'login':
                $this->authController->handleLogin();
                exit;
            case 'register':
                $this->authController->handleRegister();
                exit;
            case 'check_auth':
                $this->authController->handleCheckAuth();
                exit;
            default:
                break;
        }

        // AUTHENTICATION CHECK
        if (!isset($_SESSION['user_id'])) {
            header(Config::APP_JSON, true, 401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
            exit;
        }

        // Protected Actions
        switch ($action) {
            case 'logout':
                $this->authController->handleLogout();
                exit;
                // Task Actions
            case 'add_task':
                $this->taskController->handleAddTask();
                exit;
            case 'delete_task':
                $this->taskController->handleDeleteTask();
                exit;
            case 'toggle_importance':
                $this->taskController->handleToggleImportance();
                exit;
            case 'update_status':
                $this->taskController->handleUpdateStatus();
                exit;
            case 'reorder_tasks':
                $this->taskController->handleReorderTasks();
                exit;
            case 'edit_task':
                $this->taskController->handleEditTask();
                exit;
            case 'generate_code':
                $this->taskController->handleGenerateCode();
                exit;
            case 'generate_project_tasks':
                $this->handleGenerateProjectTasks();
                exit;

            case 'decompose_task':
                $this->taskController->handleDecomposeTask();
                exit;
            case 'commit_to_github':
                $this->handleCommitToGithub();
                exit;
            case 'query_task':
                $this->taskController->handleQueryTask();
                exit;

                // Project Actions
            case 'create_project':
                $this->projectController->handleCreate();
                exit;
            case 'list_projects':
                $this->projectController->handleList();
                exit;
            case 'update_project':
                $this->projectController->handleUpdate();
                exit;
            case 'delete_project':
                $this->projectController->handleDelete();
                exit;
            case 'create_project_from_spec':
                $this->projectController->handleCreateFromSpec();
                exit;
            case 'get_project_defaults':
                $this->projectController->handleGetDefaults();
                exit;

                // Settings Actions
            case 'get_setting':
                $key = $_GET['key'] ?? '';
                $this->settingsController->handleGetSetting($key);
                exit;
            case 'save_setting':
                $this->settingsController->handleSaveSetting();
                exit;

                // Requirement Actions
            case 'save_requirement':
                $this->requirementController->handleSaveRequirement();
                exit;
            case 'get_requirements':
                $this->requirementController->handleGetRequirements();
                exit;

                // API Cost Actions
            case 'get_api_usage':
                header(Config::APP_JSON);
                try {
                    $usageData = $this->geminiService->getAggregatedApiUsage();
                    $costConfig = [];
                    foreach ($usageData as $usageItem) {
                        $model = $usageItem['model'];
                        $costConfig[$model] = [
                            'promptCostPerMillion' => Config::getModelPromptCost($model),
                            'candidateCostPerMillion' => Config::getModelCandidateCost($model)
                        ];
                    }
                    echo json_encode(['success' => true, 'data' => $usageData, 'config' => $costConfig]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => implode(" ", [$e->getMessage(), $e->getTraceAsString()])]);
                }
                exit;
            default:
                break;
        }
    }

    private function handleGenerateProjectTasks(): void
    {
        $projectName = $_POST['project_name'] ?? '';
        $aiPrompt = $_POST['ai_prompt'] ?? '';
        if (empty($projectName) || empty($aiPrompt)) {
            header(Config::APP_JSON, true, 400);
            echo json_encode(['success' => false, 'error' => 'Project name and prompt are required.']);
            return;
        }
        try {
            try {
                $this->projectService->createProject($projectName);
            } catch (ProjectAlreadyExistsException $e) {
                // Project exists, we will replace tasks inside it
            }

            $this->taskService->generateProjectTasks($projectName, $aiPrompt);
            echo json_encode(['success' => true]);
        } catch (GeminiApiException $e) {
            $code = $e->getCode() ?: 502;
            header(Config::APP_JSON, true, $code);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            header(Config::APP_JSON, true, 500);
            error_log("General error generating tasks: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Server error: " . $e->getMessage()]);
        }
    }


    private function handleApiData($error)
    {
        $columns = [
            'SPRINT BACKLOG' => 'info',
            'IMPLEMENTATION WIP:3' => 'danger',
            'TESTING WIP:2' => 'warning',
            'REVIEW WIP:2' => 'primary',
            'DONE' => 'success',
        ];

        // Resolve current project
        // We now fetch projects via ProjectService but need to maintain compatibility with existing functionality
        // for now we still use TaskService->getProjects() or ProjectService->getAllProjects()
        // Wait, TaskService->getProjects() uses `SELECT DISTINCT project_name...`
        // ProjectService->getAllProjects() uses `projects` table.
        // We should switch to ProjectService completely for list of projects.

        $existingProjects = [];
        $projectsData = [];
        try {
            $projectsData = $this->projectService->getAllProjects();
            $existingProjects = array_column($projectsData, 'name');
        } catch (Exception $e) {
            $error = "Error loading projects: " . $e->getMessage();
        }

        $projectName = trim($_POST['project_name'] ?? '');
        $currentProjectName = trim($_GET['project'] ?? $projectName ?? '');
        $currentProjectName = trim($_POST['current_project'] ?? $currentProjectName);

        if (empty($currentProjectName) && !empty($existingProjects)) {
            $currentProjectName = $existingProjects[0];
        }

        $kanbanTasks = [];
        // Only load tasks if authenticated
        if (isset($_SESSION['user_id'])) {
            $kanbanTasks = $this->loadKanbanTasks($currentProjectName, $columns, $error);
        }

        header(Config::APP_JSON);
        echo json_encode([
            'authenticated' => isset($_SESSION['user_id']),
            'currentProjectName' => $currentProjectName,
            'existingProjects' => $existingProjects,
            'projects' => $projectsData,
            'error' => $error,
            'columns' => array_keys($columns),
            'tasks' => $kanbanTasks,
            'config' => [
                'projectName' => Config::getProjectName(),
                'maxTitleLength' => Config::getMaxTitleLength(),
                'maxDescriptionLength' => Config::getMaxDescriptionLength(),
                'maxQueryLength' => Config::getMaxQueryLength(),
                'minUsernameLength' => Config::getMinUsernameLength(),
                'minPasswordLength' => Config::getMinPasswordLength(),
            ]
        ]);
        exit;
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
            $database = new Database($dbFile);
            $pdo = $database->getPdo();

            $this->geminiService = new GeminiService($pdo);
            $this->taskService = new TaskService($pdo, $this->geminiService);
            $this->projectService = new ProjectService($pdo);

            $this->taskController = new TaskController($this->taskService);
            $this->projectController = new ProjectController($this->projectService, $this->taskService);
            $this->settingsController = new SettingsController(new SettingsService($pdo));

            $this->requirementService = new RequirementService($pdo);
            $this->requirementController = new RequirementController($this->requirementService);
            $this->authController = new AuthController($pdo);
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

    // Remaining methods (commit, generate, load tasks) kept here for now or moved partially.
    // Ideally commit logic should also move to TaskController or GitHubController

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
            error_log("GitHub commit error: HTTP {$code}. " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
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
}
