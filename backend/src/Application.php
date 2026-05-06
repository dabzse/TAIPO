<?php

namespace App;

use App\Config;
use App\Database;
use App\Utils;
use Dotenv\Dotenv;
use Exception;

use App\Configuration\GeminiConfig;

use App\Controller\AuthController;
use App\Controller\ProjectController;
use App\Controller\RequirementController;
use App\Controller\SettingsController;
use App\Controller\TaskController;
use App\Controller\TeamController;

use App\Exception\GeminiApiException;
use App\Exception\ProjectAlreadyExistsException;

use App\Service\ApplicationService;
use App\Service\GeminiService;
use App\Service\GitHubService;
use App\Service\HistoryService;
use App\Service\PoActivityService;
use App\Service\ProjectService;
use App\Service\RequirementService;
use App\Service\SettingsService;
use App\Service\TaskAiService;
use App\Service\TaskService;
use App\Service\TawosService;
use App\Service\TeamService;


class Application
{
    private AuthController $authController;
    private GeminiService $geminiService;
    private GitHubService $githubService;
    private PoActivityService $poActivityService;
    private ProjectController $projectController;
    private ProjectService $projectService;
    private RequirementController $requirementController;
    private RequirementService $requirementService;
    private SettingsController $settingsController;
    private TaskAiService $taskAiService;
    private TaskController $taskController;
    private TaskService $taskService;
    private TawosService $tawosService;
    private TeamController $teamController;
    private TeamService $teamService;

    public function run()
    {
        // Load environment variables and parse JSON input first
        $this->initEnvAndInput();

        // Allow CORS with safety checks
        $allowedOrigins = explode(',', $_ENV['ALLOWED_ORIGINS'] ?? getenv('ALLOWED_ORIGINS') ?: '');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (!empty($origin) && in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        }
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");

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

        $this->enforceHttps();


        $error = $this->initServices();

        if ($error) {
            // If DB connection fails, we can't do much but show error via JSON
            header(Config::APP_JSON, true, 500);
            echo json_encode([
                'success' => false,
                'authenticated' => false,
                'error' => "Critical Error: " . $error
            ]);
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
        if ($this->isPublicAction($action)) {
            $this->handleAuthAction($action);
            exit;
        }

        // AUTHENTICATION CHECK
        if (!isset($_SESSION['user_id'])) {
            header(Config::APP_JSON, true, 401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in.']);
            exit;
        }

        $this->routeProtectedAction($action);
    }

    private function isPublicAction(string $action): bool
    {
        return in_array($action, ['login', 'register', 'check_auth', 'github_login', 'github_callback']);
    }

    private function routeProtectedAction(string $action): void
    {
        if ($this->tryRouteSubActions($action)) {
            exit;
        }

        // Remaining Protected Actions
        switch ($action) {
            case 'logout':
                $this->authController->handleLogout();
                exit;

            case 'save_active_project':
                $this->authController->handleUpdateActiveProject();
                exit;

            case 'get_setting':
            case 'save_setting':
                $this->handleSettingAction($action);
                exit;

            case 'save_requirement':
            case 'get_requirements':
                $this->handleRequirementAction($action);
                exit;

            // API Cost Actions
            case 'get_api_usage':
                $this->handleGetApiUsage();
                exit;

            // Instructor / Simulation Actions
            case 'get_dashboard':
                $this->handleDashboardAction();
                exit;

            default:
                break;
        }

        if (in_array($action, ['get_tawos_stats', 'get_tawos_sample'])) {
            $this->handleTawosAction($action);
            exit;
        }
    }

    private function tryRouteSubActions(string $action): bool
    {
        $actionsMap = [
            'handleTaskAction' => [
                'add_task', 'delete_task', 'toggle_importance', 'update_status',
                'reorder_tasks', 'edit_task', 'generate_code', 'generate_project_tasks',
                'decompose_task', 'commit_to_github', 'query_task', 'create_project_from_spec',
                'get_task_history'
            ],
            'handleProjectAction' => [
                'create_project', 'list_projects', 'update_project', 'delete_project',
                'get_project_defaults', 'set_project_team', 'toggle_project_activity',
                'list_user_teams'
            ],
            'handleTeamAction' => [
                'list_team_users', 'remove_team_user', 'update_team_user_role',
                'list_teams', 'create_team', 'list_roles', 'assign_team_user', 'update_team'
            ]
        ];

        foreach ($actionsMap as $method => $actions) {
            if (in_array($action, $actions)) {
                $this->$method($action);
                return true;
            }
        }

        return false;
    }

    private function handleGetApiUsage(): void
    {
        header(Config::APP_JSON);
        try {
            $userId = $_SESSION['user_id'];
            $isInstructor = $_SESSION['is_instructor'] ?? false;
            $teamIds = [];
            if (!$isInstructor) {
                $userTeams = $this->teamService->listUserTeams($userId);
                $teamIds = array_column($userTeams, 'id');
            }

            $usageData = $this->geminiService->getAggregatedApiUsage($isInstructor, $userId, $teamIds);
            $costConfig = [];
            foreach ($usageData as $usageItem) {
                $model = $usageItem['model'];
                $costConfig[$model] = [
                    'promptCostPerMillion' => GeminiConfig::getModelPromptCost($model),
                    'candidateCostPerMillion' => GeminiConfig::getModelCandidateCost($model)
                ];
            }
            echo json_encode(['success' => true, 'data' => $usageData, 'config' => $costConfig]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function handleDashboardAction(): void
    {
        if (!($_SESSION['is_instructor'] ?? false)) {
            header(Config::APP_JSON, true, 403);
            echo json_encode(['success' => false, 'error' => 'Forbidden. Instructor role required.']);
            exit;
        }
        $this->handleDashboard();
    }


    private function handleTawosAction(string $action): void
    {
        header(Config::APP_JSON);
        try {
            if ($action === 'get_tawos_stats') {
                echo json_encode(['success' => true, 'data' => $this->tawosService->getStats()]);
            } elseif ($action === 'get_tawos_sample') {
                $limit = (int)($_GET['limit'] ?? 5);
                echo json_encode(['success' => true, 'data' => $this->tawosService->getSample(min($limit, 20))]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function handleDashboard(): void
    {
        header(Config::APP_JSON);
        try {
            $config = $this->getDashboardConfig();
            $tawos = $this->tawosService->getStats();
            $userId = $_SESSION['user_id'] ?? 0;
            $projects = $this->projectService->getAllProjects($userId, true);
            $this->attachProjectMetrics($projects);

            echo json_encode([
                'success' => true,
                'config' => $config,
                'tawos' => $tawos,
                'projects' => $projects,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function enforceHttps(): void
    {
        if (Config::isOffline()) {
            return;
        }

        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

        if (!$isSecure) {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $redirectUrl = 'https://' . $host . $uri;
            header("Location: $redirectUrl", true, 301);
            exit;
        }
    }

    private function handleApiData(?string $error)
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
            $userId = $_SESSION['user_id'] ?? 0;
            $isInstructor = $_SESSION['is_instructor'] ?? false;
            $projectsData = $this->projectService->getAllProjects($userId, $isInstructor);
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
            $this->poActivityService->tick($currentProjectName, (int)$_SESSION['user_id']);
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
                'registrationEnabled' => Config::isRegistrationEnabled(),
            ]
        ]);
        exit;
    }

    private function initEnvAndInput(): void
    {
        // Try to load .env from backend directory
        $envPath = realpath(__DIR__ . '/../');

        if (file_exists($envPath . '/.env')) {
            try {
                $dotenv = Dotenv::createImmutable($envPath);
                $dotenv->safeLoad();
            } catch (Exception $e) {
                // If Dotenv class is not available, use our own Utils
                Utils::loadEnv($envPath . '/.env');
            }
        } else {
            // If .env file is not found, manually set critical values:
            $_ENV['ALLOWED_ORIGINS'] = 'http://localhost:5173';
            $_ENV['FORCE_HTTPS'] = 'false';
            putenv("FORCE_HTTPS=false");
        }

        // JSON input handling (needed for Vite/Axios)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $json = file_get_contents('php://input');
            $_POST = array_merge($_POST, json_decode($json, true) ?? []);
        }
    }

    private function initServices(): ?string
    {
        $error = null;
        try {
            $database = new Database();
            $pdo = $database->getPdo();

            $this->geminiService = new GeminiService($pdo);
            $historyService = new HistoryService($pdo);
            $this->taskService = new TaskService($pdo, $this->geminiService, $historyService);
            $this->taskAiService = new TaskAiService($pdo, $this->geminiService, $this->taskService, $historyService);
            $this->projectService = new ProjectService($pdo);
            $this->githubService = new GitHubService($_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN'), $_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME'), $_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO'));

            $this->taskController = new TaskController($this->taskService, $this->taskAiService, $this->projectService, $historyService);
            $this->projectController = new ProjectController($this->projectService);
            $this->settingsController = new SettingsController(new SettingsService($pdo));

            $this->requirementService = new RequirementService($pdo);
            $this->requirementController = new RequirementController($this->requirementService);
            $this->authController = new AuthController($pdo);
            $this->teamService = new TeamService($pdo);
            $this->teamController = new TeamController($this->teamService);

            $this->tawosService = new TawosService($pdo, $database->getDbType());
            $this->tawosService->autoSeed();

            $this->poActivityService = new PoActivityService($pdo, $this->geminiService, $historyService, $database->getDbType(), $this->tawosService);
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

    // Remaining methods (generate, load tasks) kept here for now or moved partially.
    // Ideally some logic should move to TaskController where appropriate.

    private function loadKanbanTasks(string $currentProjectName, array $columns, ?string &$error): array
    {
        $kanbanTasks = [];
        foreach ($columns as $col => $style) {
            $kanbanTasks[$col] = [];
        }

        if (!empty($currentProjectName) && !$error) {
            try {
                $userId = $_SESSION['user_id'] ?? 0;
                $isInstructor = $_SESSION['is_instructor'] ?? false;
                $tasks = $this->taskService->getTasksByProject($currentProjectName, $userId, $isInstructor);
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

    private function handleTeamAction(string $action): void
    {
        switch ($action) {
            case 'list_teams':
                $this->teamController->handleListTeams();
                break;
            case 'create_team':
                $this->teamController->handleCreateTeam();
                break;
            case 'update_team':
                $this->teamController->handleUpdateTeam();
                break;
            case 'list_roles':
                $this->teamController->handleListRoles();
                break;
            case 'assign_team_user':
                $this->teamController->handleAssignUser();
                break;
            case 'list_team_users':
                $this->teamController->handleListTeamUsers();
                break;
            case 'remove_team_user':
                $this->teamController->handleRemoveUser();
                break;
            case 'update_team_user_role':
                $this->teamController->handleUpdateUserRole();
                break;
            default:
                break;
        }
    }

    private function handleTaskAction(string $action): void
    {
        switch ($action) {
            case 'add_task':
                $this->taskController->handleAddTask();
                break;
            case 'delete_task':
                $this->taskController->handleDeleteTask();
                break;
            case 'toggle_importance':
                $this->taskController->handleToggleImportance();
                break;
            case 'update_status':
                $this->taskController->handleUpdateStatus();
                break;
            case 'reorder_tasks':
                $this->taskController->handleReorderTasks();
                break;
            case 'edit_task':
                $this->taskController->handleEditTask();
                break;
            case 'generate_code':
                $this->taskController->handleGenerateCode();
                break;
            case 'generate_project_tasks':
                $this->taskController->handleGenerateProjectTasks();
                break;
            case 'decompose_task':
                $this->taskController->handleDecomposeTask();
                break;
            case 'commit_to_github':
                $this->taskController->handleCommitToGitHub();
                break;
            case 'query_task':
                $this->taskController->handleQueryTask();
                break;
            case 'create_project_from_spec':
                $this->taskController->handleCreateFromSpec();
                break;
            case 'get_task_history':
                $this->taskController->handleGetTaskHistory();
                break;
            default:
                break;
        }
    }

    private function handleProjectAction(string $action): void
    {
        switch ($action) {
            case 'create_project':
                $this->projectController->handleCreate();
                break;
            case 'list_projects':
                $this->projectController->handleList();
                break;
            case 'update_project':
                $this->projectController->handleUpdate();
                break;
            case 'delete_project':
                $this->projectController->handleDelete();
                break;
            case 'get_project_defaults':
                $this->projectController->handleGetDefaults();
                exit;break;
            case 'toggle_project_activity':
                $this->projectController->handleToggleActivity();
                exit;break;
            case 'set_project_team':
                $id = (int)($_POST['id'] ?? 0);
                $teamId = (int)($_POST['team_id'] ?? 0) ?: null;
                $this->projectService->setProjectTeam($id, $teamId);
                header(Config::APP_JSON);
                echo json_encode(['success' => true]);
                break;
            case 'list_user_teams':
                header(Config::APP_JSON);
                $isInstructor = $_SESSION['is_instructor'] ?? false;
                if ($isInstructor) {
                    $teams = $this->teamService->listTeams();
                } else {
                    $teams = $this->teamService->listUserTeams($_SESSION['user_id']);
                }
                echo json_encode(['success' => true, 'data' => $teams]);
                break;
            default:
                break;
        }
    }

    private function handleSettingAction(string $action): void
    {
        if ($action === 'get_setting') {
            $this->settingsController->handleGetSetting($_GET['key'] ?? '');
        } elseif ($action === 'save_setting') {
            $this->settingsController->handleSaveSetting();
        }
    }

    private function handleRequirementAction(string $action): void
    {
        if ($action === 'save_requirement') {
            $this->requirementController->handleSaveRequirement();
        } elseif ($action === 'get_requirements') {
            $this->requirementController->handleGetRequirements();
        }
    }

    private function handleAuthAction(string $action): void
    {
        switch ($action) {
            case 'login':
                $this->authController->handleLogin();
                break;
            case 'register':
                $this->authController->handleRegister();
                break;
            case 'check_auth':
                $this->authController->handleCheckAuth();
                break;
            case 'github_login':
                $this->authController->handleGitHubLogin();
                break;
            case 'github_callback':
                $this->authController->handleGitHubCallback();
                break;
            default:
                break;
        }
    }

    private function getDashboardConfig(): array
    {
        $sensitiveKeys = ['GEMINI_API_KEY', 'GITHUB_TOKEN', 'GITHUB_CLIENT_SECRET'];
        $fullyHiddenKeys = ['DB_NAME', 'DB_USER', 'DB_PASS'];

        $config = [
            'Project' => [
                'PROJECT_NAME' => $_ENV['PROJECT_NAME'] ?? '',
                'MAX_TITLE_LENGTH' => $_ENV['MAX_TITLE_LENGTH'] ?? '42',
                'MAX_DESCRIPTION_LENGTH' => $_ENV['MAX_DESCRIPTION_LENGTH'] ?? '512',
                'MAX_QUERY_LENGTH' => $_ENV['MAX_QUERY_LENGTH'] ?? '1320',
            ],
            'Gemini API' => [
                'GEMINI_API_KEY' => $_ENV['GEMINI_API_KEY'] ?? '',
                'GEMINI_BASE_MODEL' => $_ENV['GEMINI_BASE_MODEL'] ?? '',
                'GEMINI_FALLBACK_MODEL' => $_ENV['GEMINI_FALLBACK_MODEL'] ?? '',
                'GEMINI_BASE_URL' => $_ENV['GEMINI_BASE_URL'] ?? '',
                'GEMINI_FALLBACK_URL' => $_ENV['GEMINI_FALLBACK_URL'] ?? '',
                'GEMINI_TEMPERATURE' => $_ENV['GEMINI_TEMPERATURE'] ?? '0.7',
                'GEMINI_TOP_K' => $_ENV['GEMINI_TOP_K'] ?? '40',
                'GEMINI_TOP_P' => $_ENV['GEMINI_TOP_P'] ?? '0.95',
                'GEMINI_MAX_OUTPUT_TOKENS' => $_ENV['GEMINI_MAX_OUTPUT_TOKENS'] ?? '4096',
            ],
            'Gemini Costs' => [
                'GEMINI_BASE_MODEL_PROMPT_COST_PER_MILLION' => $_ENV['GEMINI_BASE_MODEL_PROMPT_COST_PER_MILLION'] ?? '',
                'GEMINI_BASE_MODEL_CANDIDATE_COST_PER_MILLION' => $_ENV['GEMINI_BASE_MODEL_CANDIDATE_COST_PER_MILLION'] ?? '',
                'GEMINI_FALLBACK_MODEL_PROMPT_COST_PER_MILLION' => $_ENV['GEMINI_FALLBACK_MODEL_PROMPT_COST_PER_MILLION'] ?? '',
                'GEMINI_FALLBACK_MODEL_CANDIDATE_COST_PER_MILLION' => $_ENV['GEMINI_FALLBACK_MODEL_CANDIDATE_COST_PER_MILLION'] ?? '',
            ],
            'PO Simulation' => [
                'SIM_TIMEZONE' => $_ENV['SIM_TIMEZONE'] ?? 'UTC',
                'SIM_MIN_ACTIVE_HOUR' => $_ENV['SIM_MIN_ACTIVE_HOUR'] ?? '8',
                'SIM_MAX_ACTIVE_HOUR' => $_ENV['SIM_MAX_ACTIVE_HOUR'] ?? '16',
                'SIM_MIN_FEEDBACK_SEC' => $_ENV['SIM_MIN_FEEDBACK_SEC'] ?? '7200',
                'SIM_MAX_FEEDBACK_SEC' => $_ENV['SIM_MAX_FEEDBACK_SEC'] ?? '10800',
                'SIM_MIN_CR_SEC' => $_ENV['SIM_MIN_CR_SEC'] ?? '86400',
                'SIM_MAX_CR_SEC' => $_ENV['SIM_MAX_CR_SEC'] ?? '259200',
            ],
            'Users' => [
                'MIN_USERNAME_LENGTH' => $_ENV['MIN_USERNAME_LENGTH'] ?? '6',
                'MIN_PASSWORD_LENGTH' => $_ENV['MIN_PASSWORD_LENGTH'] ?? '8',
                'REGISTRATION_ENABLED' => $_ENV['REGISTRATION_ENABLED'] ?? 'true',
            ],
            'GitHub' => [
                'GITHUB_USERNAME' => $_ENV['GITHUB_USERNAME'] ?? '',
                'GITHUB_REPO' => $_ENV['GITHUB_REPO'] ?? '',
                'GITHUB_TOKEN' => $_ENV['GITHUB_TOKEN'] ?? '',
                'GITHUB_USERAGENT' => $_ENV['GITHUB_USERAGENT'] ?? '',
            ],
            'Database' => [
                'DB_TYPE' => $_ENV['DB_TYPE'] ?? 'sqlite',
                'SQLITE_FILE_NAME' => $_ENV['SQLITE_FILE_NAME'] ?? '',
                'DB_HOST' => $_ENV['DB_HOST'] ?? '',
                'DB_NAME' => $_ENV['DB_NAME'] ?? '',
                'DB_USER' => $_ENV['DB_USER'] ?? '',
                'DB_PASS' => $_ENV['DB_PASS'] ?? '',
                'TABLE_PREFIX' => $_ENV['TABLE_PREFIX'] ?? '',
            ],
            'Network' => [
                'ALLOWED_ORIGINS' => $_ENV['ALLOWED_ORIGINS'] ?? '',
                'SESSION_COOKIE_SECURE_FLAG' => $_ENV['FORCE_HTTPS'] ?? 'false',
                'ENFORCE_HTTPS_REDIRECT' => Config::isOffline() ? 'false' : 'true',
            ],
        ];

        foreach ($config as &$items) {
            foreach ($items as $key => &$value) {
                if (in_array($key, $fullyHiddenKeys)) {
                    $value = empty($value) ? '' : '••••••••';
                } elseif (in_array($key, $sensitiveKeys) && strlen($value) > 4) {
                    $value = str_repeat('•', min(strlen($value) - 4, 20)) . substr($value, -4);
                }
            }
        }
        return $config;
    }

    private function attachProjectMetrics(array &$projects): void
    {
        $metrics = $this->projectService->getProjectMetrics();
        foreach ($projects as &$project) {
            $name = $project['name'];
            $m = $metrics[$name] ?? ['total_tasks' => 0, 'done_tasks' => 0, 'last_wip_update' => null];

            $stalled = false;
            if ($m['total_tasks'] > 0 && $m['done_tasks'] < $m['total_tasks'] && $m['last_wip_update']) {
                $lastUpdate = new \DateTime($m['last_wip_update']);
                if ((new \DateTime())->diff($lastUpdate)->days >= 3) {
                    $stalled = true;
                }
            }

            $project['metrics'] = [
                'total_tasks' => (int)$m['total_tasks'],
                'done_tasks' => (int)$m['done_tasks'],
                'completion_rate' => $m['total_tasks'] > 0 ? round(($m['done_tasks'] / $m['total_tasks']) * 100) : 0,
                'stalled' => $stalled
            ];
        }
    }
}
