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
        $this->initEnvAndInput();

        $apiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
        $dbFile = __DIR__ . '/../kanban.sqlite';

        $error = $this->initServices($dbFile);

        $columns = [
            'SPRINTBACKLOG' => 'info',
            'IMPLEMENTÁCIÓ WIP:3' => 'danger',
            'TESZTELÉS WIP:2' => 'warning',
            'REVIEW WIP:2' => 'primary',
            'KÉSZ' => 'success',
        ];

        $projectName = trim($_POST['project_name'] ?? '');
        $existingProjects = [];
        $currentProjectName = $this->resolveCurrentProject($projectName, $existingProjects, $error);

        if (!$error) {
            $this->dispatchActions($currentProjectName, $columns, $apiKey, $error);
        }

        $kanbanTasks = $this->loadKanbanTasks($currentProjectName, $columns, $error);

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
                $error = "Hiba a projektek betöltésekor: " . $e->getMessage();
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
                $error = "Hiba történt az adatok olvasása során: " . $e->getMessage();
            }
        }

        return $kanbanTasks;
    }

    private function handleAddTask()
    {
        $newTaskDescription = trim($_POST['description'] ?? '');
        $projectForAdd = trim($_POST['current_project'] ?? '');

        if (!empty($newTaskDescription) && !empty($projectForAdd)) {
            try {
                $newId = $this->taskService->addTask($projectForAdd, $newTaskDescription);
                header(Config::APP_JSON);
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
                error_log("Hiba történt a feladat törlése során: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => "Szerver oldali hiba a törlés során: " . $e->getMessage()]);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Hibás ID a törléshez."]);
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

    private function handleUpdateStatus($currentProjectName, $columns)
    {
        $taskId = $_POST['task_id'] ?? null;
        $newStatus = $_POST['new_status'] ?? null;
        //$oldStatus = $_POST['old_status'] ?? null; // Unused in logic but present in POST
        $projectNameForWIP = trim($_POST['current_project'] ?? $currentProjectName);

        if (is_numeric($taskId) && in_array($newStatus, array_keys($columns))) {
            try {
                $this->taskService->updateStatus((int)$taskId, $newStatus, $projectNameForWIP);
                echo "Sikeres frissítés: ID {$taskId}, új státusz: {$newStatus}";
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
                error_log("Hiba történt az adatbázis frissítése során: " . $e->getMessage());
                echo "Szerver oldali hiba a státusz frissítése során: " . $e->getMessage();
                exit;
            }
        } else {
            http_response_code(400);
            echo "Hibás ID vagy státusz érték.";
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

    private function handleGenerateJavaCode($apiKey)
    {
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
            $rawText = Utils::callGeminiAPI($apiKey, $prompt);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'code' => Utils::formatCodeBlocks($rawText)]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Kódgenerálási hiba: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Gemini API hiba: " . $e->getMessage()]);
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
            echo json_encode(['success' => false, 'error' => "Hiba: A kód vagy a feladat adatai hiányoznak a commitoláshoz."]);
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
            return "Hiba: A Gemini API kulcs nincs beállítva.";
        } elseif (empty($rawPrompt)) {
            return "Hiba: Az AI utasítás (Prompt) mező nem lehet üres!";
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

                if (preg_match('/^\[(SPRINTBACKLOG|IMPLEMENTÁCIÓ|TESZTELÉS|REVIEW|KÉSZ)\]:\s*(.*)/iu', $line, $matches)) {
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
            return "Hiba történt a Gemini API hívás/mentés során: " . $e->getMessage();
        }
    }
}
