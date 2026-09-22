<?php

namespace App;

use App\Config;
use App\Database;
use App\Utils;
use Dotenv\Dotenv;
use Exception;

use App\Configuration\GeminiConfig;
use App\Configuration\RolePermissions;

use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\ProjectController;
use App\Controller\RequirementController;
use App\Controller\SettingsController;
use App\Controller\TaskController;
use App\Controller\TeamController;

use App\Exception\GeminiApiException;
use App\Exception\ProjectAlreadyExistsException;

use App\Service\ApplicationService;
use App\Service\BackupService;
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
use App\Service\PermissionCheckTrait;


class Application
{
    use PermissionCheckTrait;
    private AuthController $authController;
    private BackupService $backupService;
    private DashboardController $dashboardController;
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
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-User-Gemini-Api-Key");
        header("Access-Control-Allow-Credentials: true");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Start session before any output
        $isSecure = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            (isset($_ENV['FORCE_HTTPS']) && $_ENV['FORCE_HTTPS'] === 'true')) && !Config::isOffline();

        session_set_cookie_params([ // NOSONAR - 'secure' flag is set dynamically based on HTTPS config
            'lifetime' => 86400 * 30, // 30 days
            'path' => '/',
            'secure' => $isSecure,
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

        // ROLE-BASED PERMISSION CHECK
        if (!$this->checkActionPermission($action)) {
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

            case 'get_api_key_status':
                $this->authController->handleGetApiKeyStatus();
                exit;

            case 'set_session_api_key':
                $this->authController->handleSetSessionApiKey();
                exit;

            case 'clear_session_api_key':
                $this->authController->handleClearSessionApiKey();
                exit;

            case 'save_user_api_key':
                $this->authController->handleSaveUserApiKey();
                exit;

            case 'delete_user_api_key':
                $this->authController->handleDeleteUserApiKey();
                exit;

            case 'save_requirement':
            case 'get_requirements':
                $this->handleRequirementAction($action);
                exit;

                // API Cost Actions
            case 'get_api_usage':
                $this->dashboardController->handleGetApiUsage();
                exit;

                // Instructor / Simulation Actions
            case 'get_dashboard':
                $this->dashboardController->handleDashboardAction();
                exit;

            case 'export_backup':
                $this->dashboardController->handleExportBackup();
                exit;

            case 'import_backup':
                $this->dashboardController->handleImportBackup();
                exit;

            default:
                break;
        }

        if (in_array($action, ['get_tawos_stats', 'get_tawos_sample', 'search_tawos'])) {
            $this->dashboardController->handleTawosAction($action);
            exit;
        }
    }

    private function tryRouteSubActions(string $action): bool
    {
        $actionsMap = [
            'handleTaskAction' => [
                'add_task',
                'delete_task',
                'toggle_importance',
                'update_status',
                'reorder_tasks',
                'edit_task',
                'generate_code',
                'generate_project_tasks',
                'decompose_task',
                'commit_to_github',
                'query_task',
                'create_project_from_spec',
                'get_task_history',
                'get_project_history',
                'review_task',
                'refine_task',
                'suggest_priority'
            ],
            'handleProjectAction' => [
                'create_project',
                'list_projects',
                'update_project',
                'delete_project',
                'get_project_defaults',
                'set_project_team',
                'toggle_project_activity',
                'list_user_teams'
            ],
            'handleTeamAction' => [
                'list_team_users',
                'remove_team_user',
                'update_team_user_role',
                'list_teams',
                'create_team',
                'list_roles',
                'assign_team_user',
                'update_team'
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

    private function handleDashboardAction(): void
    {
        $this->dashboardController->handleDashboardAction();
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

        $currentProjectName = $this->resolveCurrentProjectName($existingProjects);

        $kanbanTasks = [];
        $tickProjectName = null;
        $tickUserId = null;
        if (isset($_SESSION['user_id'])) {
            $tickProjectName = $currentProjectName;
            $tickUserId = (int)$_SESSION['user_id'];
            $kanbanTasks = $this->loadKanbanTasks($currentProjectName, $columns, $error);
        }

        $userRole = null;
        $allowedActions = [];
        if (isset($_SESSION['user_id']) && !empty($currentProjectName)) {
            $userRole = $this->resolveUserRole((int)$_SESSION['user_id'], $currentProjectName);
            $allowedActions = RolePermissions::getAllowedActions($userRole);
        }

        session_write_close();

        header(Config::APP_JSON);
        echo json_encode([
            'authenticated' => isset($tickUserId),
            'currentProjectName' => $currentProjectName,
            'existingProjects' => $existingProjects,
            'projects' => $projectsData,
            'error' => $error,
            'columns' => array_keys($columns),
            'tasks' => $kanbanTasks,
            'userRole' => $userRole,
            'allowedActions' => $allowedActions,
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

        $this->dispatchPoActivityTick($tickProjectName, $tickUserId);
        exit;
    }

    private function resolveCurrentProjectName(array $existingProjects): string
    {
        $projectName = trim($_POST['project_name'] ?? '');
        $current = trim($_GET['project'] ?? $projectName);
        $current = trim($_POST['current_project'] ?? $current);

        if (empty($current) && !empty($existingProjects)) {
            return $existingProjects[0];
        }

        return $current;
    }

    private function dispatchPoActivityTick(?string $projectName, ?int $userId): void
    {
        if (!$projectName || !$userId) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            ignore_user_abort(true);
            $this->poActivityService->tick($projectName, $userId);
            return;
        }

        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();

        $cliScript = dirname(__DIR__) . '/tools/sim_tick.php';
        if (file_exists($cliScript)) {
            $cmd = sprintf(
                'php %s %s %d > /dev/null 2>&1 &',
                escapeshellarg($cliScript),
                escapeshellarg($projectName),
                $userId
            );
            exec($cmd);
        }
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

            $this->poActivityService = new PoActivityService($pdo, $this->geminiService, $this->taskAiService, $historyService, $database->getDbType(), $this->tawosService);

            $this->backupService = new BackupService($pdo);

            $this->dashboardController = new DashboardController(
                $this->backupService,
                $this->geminiService,
                $this->projectService,
                $this->tawosService,
                $this->teamService
            );
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
            case 'get_project_history':
                $this->taskController->handleGetProjectHistory();
                break;
            case 'review_task':
                $this->taskController->handleReviewTask();
                break;
            case 'refine_task':
                $this->taskController->handleRefineTask();
                break;
            case 'suggest_priority':
                $this->taskController->handleSuggestPriority();
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
                exit;
                break;
            case 'toggle_project_activity':
                $this->projectController->handleToggleActivity();
                exit;
                break;
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
}
