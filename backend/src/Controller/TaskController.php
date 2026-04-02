<?php

namespace App\Controller;

use App\Service\TaskService;
use App\Service\TaskAiService;
use App\Service\ProjectService;
use App\Config;
use App\Exception\WipLimitExceededException;
use App\Exception\ProjectAlreadyExistsException;
use App\Utils;
use App\Exception\GeminiApiException;
use App\Exception\TaskNotFoundException;
use Exception;

class TaskController
{
    private TaskService $taskService;
    private TaskAiService $taskAiService;
    private ProjectService $projectService;

    public function __construct(TaskService $taskService, TaskAiService $taskAiService, ProjectService $projectService)
    {
        $this->taskService = $taskService;
        $this->taskAiService = $taskAiService;
        $this->projectService = $projectService;
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
                $userId = $_SESSION['user_id'] ?? 0;
                $isInstructor = $_SESSION['is_instructor'] ?? false;
                $newId = $this->taskService->addTask($projectForAdd, $newTitle, $newTaskDescription, $isImportant, $userId, $isInstructor);
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
                $userId = $_SESSION['user_id'] ?? 0;
                $isInstructor = $_SESSION['is_instructor'] ?? false;
                $taskStatus = $this->taskService->deleteTask((int)$taskId, $userId, $isInstructor);
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
                $userId = $_SESSION['user_id'] ?? 0;
                $isInstructor = $_SESSION['is_instructor'] ?? false;
                $affected = $this->taskService->toggleImportance((int)$taskId, (int)$isImportant, $userId, $isInstructor);
                if ($affected === 0) {
                    http_response_code(404);
                    header(Config::APP_JSON);
                    echo json_encode(['success' => false, 'error' => Config::ERROR_TASK_NOT_FOUND]);
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

        $columns = [
            'SPRINT BACKLOG',
            'IMPLEMENTATION WIP:3',
            'TESTING WIP:2',
            'REVIEW WIP:2',
            'DONE'
        ];

        if (is_numeric($taskId) && in_array($newStatus, $columns)) {
            try {
                $userId = $_SESSION['user_id'] ?? 0;
                $isInstructor = $_SESSION['is_instructor'] ?? false;
                $this->taskService->updateStatus((int)$taskId, $newStatus, $currentProjectName, $userId, $isInstructor);
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
                $userId = $_SESSION['user_id'] ?? 0;
                $isInstructor = $_SESSION['is_instructor'] ?? false;
                $affected = $this->taskService->updateTask((int)$taskId, $newTitle, $newDescription, $lastUpdatedAt, $userId, $isInstructor);
                if ($affected === 0) {
                    $taskExists = $this->taskService->getTaskById((int)$taskId);
                    if (!$taskExists) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => Config::ERROR_TASK_NOT_FOUND]);
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
            $userId = $_SESSION['user_id'] ?? 0;
            $isInstructor = $_SESSION['is_instructor'] ?? false;
            $formattedCode = $this->taskAiService->generateCode($description, $taskId ?: null, $userId, $isInstructor);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'code' => $formattedCode]);
        } catch (GeminiApiException $e) {
            $code = ($e->getCode() >= 100 && $e->getCode() <= 599) ? $e->getCode() : 500;
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
        $currentProjectName = strip_tags(trim($_POST['current_project'] ?? ''));
        $desc = trim($_POST['description'] ?? '');
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);

        if (empty($desc) || empty($currentProjectName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing description or project."]);
            return;
        }

        try {
            $userId = $_SESSION['user_id'] ?? 0;
            $isInstructor = $_SESSION['is_instructor'] ?? false;
            $count = $this->taskAiService->decomposeTask($desc, $currentProjectName, $taskId, $userId, $isInstructor);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'count' => $count]);
        } catch (GeminiApiException $e) {
            $code = ($e->getCode() >= 100 && $e->getCode() <= 599) ? $e->getCode() : 500;
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
            $userId = $_SESSION['user_id'] ?? 0;
            $isInstructor = $_SESSION['is_instructor'] ?? false;
            $answer = $this->taskAiService->queryTask($taskId, $query, $userId, $isInstructor);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'answer' => $answer]);
        } catch (GeminiApiException $e) {
            $code = ($e->getCode() >= 100 && $e->getCode() <= 599) ? $e->getCode() : 500;
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
                $userId = $_SESSION['user_id'] ?? 0;
                $isInstructor = $_SESSION['is_instructor'] ?? false;
                $this->taskService->reorderTasks($projectName, $status, $taskIds, $userId, $isInstructor);
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

    public function handleGenerateProjectTasks(): void
    {
        $projectName = $_POST['project_name'] ?? '';
        $aiPrompt = $_POST['ai_prompt'] ?? '';
        if (empty($projectName) || empty($aiPrompt)) {
            header(Config::APP_JSON, true, 400);
            echo json_encode(['success' => false, 'error' => 'Project name and prompt are required.']);
            return;
        }

        try {
            $userId = $_SESSION['user_id'] ?? 0;
            $isInstructor = $_SESSION['is_instructor'] ?? false;
            $teamId = filter_var($_POST['team_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $this->projectService->createProject($projectName, $userId, $teamId);
        } catch (ProjectAlreadyExistsException $e) {
            error_log("Project already exists: " . $e->getMessage());
        }

        try {
            $userId = $_SESSION['user_id'] ?? 0;
            $isInstructor = $_SESSION['is_instructor'] ?? false;
            $this->taskAiService->generateProjectTasks($projectName, $aiPrompt, $userId, $isInstructor);
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

    public function handleCreateFromSpec()
    {
        $spec = $_POST['spec'] ?? '';
        $teamId = filter_var($_POST['team_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if (empty($spec)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Specification is required.']);
            return;
        }

        try {
            $userId = $_SESSION['user_id'] ?? 0;
            $result = $this->taskAiService->analyzeSpec($spec, $userId);
            $projectName = $result['name'];
            $newTasks = $result['tasks'];

            $projectId = $this->projectService->createProject($projectName, $userId, $teamId);
            $this->taskService->replaceProjectTasks($projectName, $newTasks, $userId, true); // Admin access for replacement

            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'project_name' => $projectName, 'id' => $projectId]);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error creating project from spec: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Error processing specification: ' . $e->getMessage()]);
        }
    }

    public function handleCommitToGitHub()
    {
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT);
        $code = $_POST['code'] ?? '';

        $userToken = $_POST['user_token'] ?? null;
        $userUsername = $_POST['user_username'] ?? null;

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
            $dbTask = $this->taskService->getTaskById($taskId);
            if (!$dbTask) {
                throw new TaskNotFoundException(Config::ERROR_TASK_NOT_FOUND);
            }
            $description = $dbTask['description'];

            $safeDescription = preg_replace('/[^a-zA-Z0-9\s]/', '', $description);
            $safeDescription = trim(substr($safeDescription, 0, 50));
            $fileName = 'Task_' . $taskId . '_' . str_replace(' ', '_', $safeDescription) . '.java';
            $filePath = 'src/main/java/' . $fileName;

            $commitMessage = "feat: Adds task implementation for: " . substr($description, 0, 70) . '...';
            $result = $ghService->commitFile($filePath, $code, $commitMessage);

            $userId = $_SESSION['user_id'] ?? 0;
            $isInstructor = $_SESSION['is_instructor'] ?? false;
            $this->taskService->updateStatus($taskId, 'DONE', $dbTask['project_name'], $userId, $isInstructor);

            header(Config::APP_JSON);
            echo json_encode($result);
        } catch (Exception $e) {
            $code = ($e->getCode() >= 100 && $e->getCode() <= 599) ? $e->getCode() : 500;
            http_response_code($code);
            error_log("GitHub commit error: HTTP {$code}. " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
