<?php

namespace App\Controller;

use App\Service\TaskService;
use App\Config;
use App\Exception\WipLimitExceededException;
use App\Utils;
use App\Exception\GeminiApiException;
use Exception;

class TaskController
{
    private TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function handleAddTask()
    {
        $newTitle = strip_tags(trim($_POST['title'] ?? ''));
        $newTaskDescription = trim($_POST['description'] ?? '');
        $projectForAdd = strip_tags(trim($_POST['current_project'] ?? ''));
        $isImportant = filter_var($_POST['is_important'] ?? 0, FILTER_VALIDATE_INT);

        if (!empty($newTitle) && !empty($projectForAdd)) {
            if (strlen($newTitle) > Config::getMaxTitleLength() || strlen($newTaskDescription) > Config::getMaxDescriptionLength()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Title or description exceeds max length."]);
                return;
            }
            try {
                // If description is empty, that's fine now, title is required.
                $newId = $this->taskService->addTask($projectForAdd, $newTitle, $newTaskDescription, $isImportant);
                header(Config::APP_JSON);
                echo json_encode(['success' => true, 'id' => $newId, 'title' => $newTitle, 'description' => $newTaskDescription, 'is_important' => $isImportant]);
            } catch (Exception $e) {
                http_response_code(500);
                error_log("Error adding task: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => "Server error: " . $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Project name and task title are required."]);
        }
    }

    public function handleDeleteTask()
    {
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);

        if (is_numeric($taskId)) {
            try {
                $taskStatus = $this->taskService->deleteTask((int)$taskId);
                header(Config::APP_JSON);
                echo json_encode(['success' => true, 'status' => $taskStatus]);
            } catch (Exception $e) {
                http_response_code(500);
                error_log("Error deleting task: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => "Server error during deletion: " . $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Invalid ID for deletion."]);
        }
    }

    public function handleToggleImportance()
    {
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);
        $isImportant = filter_var($_POST['is_important'] ?? 0, FILTER_VALIDATE_INT);

        if (is_numeric($taskId)) {
            try {
                $affected = $this->taskService->toggleImportance((int)$taskId, (int)$isImportant);
                if ($affected === 0) {
                    http_response_code(404);
                    header(Config::APP_JSON);
                    echo json_encode(['success' => false, 'error' => "Task not found."]);
                    return;
                }
                header(Config::APP_JSON);
                echo "Success: Importance toggled for task ID {$taskId}";
            } catch (Exception $e) {
                http_response_code(500);
                error_log("Error toggling importance: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => "Server error during importance toggle."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Invalid ID for importance toggle."]);
        }
    }

    public function handleUpdateStatus()
    {
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);
        $newStatus = strip_tags(trim($_POST['new_status'] ?? ''));
        $currentProjectName = strip_tags(trim($_POST['current_project'] ?? ''));

        // Use fixed columns helper or passed config, for now hardcode columns check to match App
        $columns = [
            'SPRINT BACKLOG',
            'IMPLEMENTATION WIP:3',
            'TESTING WIP:2',
            'REVIEW WIP:2',
            'DONE'
        ];

        if (is_numeric($taskId) && in_array($newStatus, $columns)) {
            try {
                $this->taskService->updateStatus((int)$taskId, $newStatus, $currentProjectName);
                echo "Success: ID {$taskId}, new status: {$newStatus}";
            } catch (WipLimitExceededException $e) {
                http_response_code(403);
                header(Config::APP_JSON);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            } catch (Exception $e) {
                $code = $e->getCode() ?: 500;
                http_response_code($code);
                error_log("Database update error: " . $e->getMessage());
                echo "Server error during status update: " . $e->getMessage();
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Error: Invalid ID or status value."]);
        }
    }

    public function handleEditTask()
    {
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);
        $newTitle = strip_tags(trim($_POST['title'] ?? ''));
        $newDescription = trim($_POST['description'] ?? '');
        $lastUpdatedAt = $_POST['last_updated_at'] ?? null;

        if (is_numeric($taskId) && !empty($newTitle)) {
            if (strlen($newTitle) > Config::getMaxTitleLength() || strlen($newDescription) > Config::getMaxDescriptionLength()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Title or description exceeds max length."]);
                return;
            }
            try {
                $affected = $this->taskService->updateTask((int)$taskId, $newTitle, $newDescription, $lastUpdatedAt);
                if ($affected === 0) {
                    // Could be not found OR concurrency conflict if last_updated_at was provided
                    $taskExists = $this->taskService->getTaskById((int)$taskId);
                    if (!$taskExists) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => "Task not found."]);
                    } else {
                        http_response_code(409); // Conflict
                        echo json_encode(['success' => false, 'error' => "CONFLICT: This task was modified by someone else. Please refresh and try again."]);
                    }
                    return;
                }
                header(Config::APP_JSON);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                http_response_code(500);
                error_log("Error updating task: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => "Server error during task update."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Error: Invalid ID or empty title."]);
        }
    }

    public function handleGenerateCode()
    {
        $description = trim($_POST['description'] ?? '');
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);

        if (empty($description) || strlen($description) > Config::getMaxDescriptionLength() * 2) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Error: Task description is missing."]);
            return;
        }

        try {
            $formattedCode = $this->taskService->generateCode($description, $taskId ?: null);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'code' => $formattedCode]);
        } catch (GeminiApiException $e) {
            $code = $e->getCode();
            $code = ($code >= 100 && $code <= 599) ? $code : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Code generation error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Gemini API error: " . $e->getMessage()]);
        }
    }

    public function handleDecomposeTask()
    {
        // ProjectID isn't actually used by service decompose,
        // but the current implementation requires project name to decompose into.
        // Wait, the Service needs `projectName`.
        $currentProjectName = strip_tags(trim($_POST['current_project'] ?? ''));
        $desc = trim($_POST['description'] ?? '');
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);

        if (empty($desc) || empty($currentProjectName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing description or project."]);
            return;
        }

        try {
            $count = $this->taskService->decomposeTask($desc, $currentProjectName, $taskId);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'count' => $count]);
        } catch (GeminiApiException $e) {
            $code = $e->getCode();
            $code = ($code >= 100 && $code <= 599) ? $code : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => "Error: " . $e->getMessage()]);
        }
    }

    public function handleQueryTask()
    {
        $taskId = $_POST['task_id'] ?? null;
        $query = trim($_POST['query'] ?? '');

        if (empty($taskId) || empty($query)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Task ID and query are required."]);
            return;
        }

        try {
            $answer = $this->taskService->queryTask($taskId, $query);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'answer' => $answer]);
        } catch (GeminiApiException $e) {
            $code = $e->getCode();
            $code = ($code >= 100 && $code <= 599) ? $code : 500;
            http_response_code($code);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Query task error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Gemini API error: " . $e->getMessage()]);
        }
    }
    public function handleReorderTasks()
    {
        $projectName = $_POST['project_name'] ?? '';
        $status = $_POST['status'] ?? '';
        $taskIds = $_POST['task_ids'] ?? [];

        if (!empty($projectName) && !empty($status) && is_array($taskIds)) {
            try {
                $this->taskService->reorderTasks($projectName, $status, $taskIds);
                header(Config::APP_JSON);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                http_response_code(500);
                error_log("Error reordering tasks: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => "Server error during reorder."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Invalid parameters for reorder."]);
        }
    }
    public function handleCommitToGitHub()
    {
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);
        $description = $_POST['description'] ?? '';
        $code = $_POST['code'] ?? '';

        $userToken = $_POST['user_token'] ?? null;
        $userUsername = $_POST['user_username'] ?? null;

        // Create a temporary GitHub service with user provided credentials if available
        $token = $userToken ?: ($_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN'));
        $username = $userUsername ?: ($_ENV['GITHUB_USERNAME'] ?? getenv('GITHUB_USERNAME'));
        $repo = $_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO');

        $ghService = new \App\Service\GitHubService($token, $username, $repo);

        if (empty($taskId) || empty($code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Error: Task ID or code is missing for the commit."]);
            return;
        }

        try {
            // Resilience: Fetch latest description from DB
            $dbTask = $this->taskService->getTaskById($taskId);
            if ($dbTask) {
                $description = $dbTask['description'];
            }

            $safeDescription = preg_replace('/[^a-zA-Z0-9\s]/', '', $description);
            $safeDescription = trim(substr($safeDescription, 0, 50));
            $fileName = 'Task_' . $taskId . '_' . str_replace(' ', '_', $safeDescription) . '.java';
            $filePath = 'src/main/java/' . $fileName;

            $commitMessage = "feat: Adds task implementation for: " . substr($description, 0, 70) . '...';

            $result = $ghService->commitFile($filePath, $code, $commitMessage);

            // Mark task as done if commit successful
            $this->taskService->updateStatus($taskId, 'DONE', $dbTask['project_name']);

            header(Config::APP_JSON);
            echo json_encode($result);
        } catch (Exception $e) {
            $code = $e->getCode() ?: 500;
            $code = ($code >= 100 && $code <= 599) ? $code : 500;
            http_response_code($code);
            error_log("GitHub commit error: HTTP {$code}. " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
