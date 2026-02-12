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
        $newTitle = trim($_POST['title'] ?? '');
        $newTaskDescription = trim($_POST['description'] ?? '');
        $projectForAdd = trim($_POST['current_project'] ?? '');
        $isImportant = (int)($_POST['is_important'] ?? 0);

        if (!empty($newTitle) && !empty($projectForAdd)) {
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
        $taskId = $_POST['task_id'] ?? null;

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
        $taskId = $_POST['task_id'] ?? null;
        $isImportant = filter_var($_POST['is_important'] ?? 0, FILTER_VALIDATE_INT);

        if (is_numeric($taskId)) {
            try {
                $this->taskService->toggleImportance((int)$taskId, (int)$isImportant);
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
        $taskId = $_POST['task_id'] ?? null;
        $newStatus = $_POST['new_status'] ?? null;
        $currentProjectName = $_POST['current_project'] ?? '';

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
        $taskId = $_POST['task_id'] ?? null;
        $newTitle = trim($_POST['title'] ?? '');
        $newDescription = trim($_POST['description'] ?? '');

        if (is_numeric($taskId) && !empty($newTitle)) {
            try {
                $this->taskService->updateTask((int)$taskId, $newTitle, $newDescription);
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

    public function handleGenerateJavaCode()
    {
        $description = trim($_POST['description'] ?? '');

        if (empty($description)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Error: Task description is missing."]);
            return;
        }

        try {
            $formattedCode = $this->taskService->generateJavaCode($description);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'code' => $formattedCode]);
        } catch (GeminiApiException $e) {
            $code = $e->getCode() ?: 500;
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
        $currentProjectName = $_POST['current_project'] ?? '';
        $desc = $_POST['description'] ?? '';

        if (empty($desc) || empty($currentProjectName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing description or project."]);
            return;
        }

        try {
            $count = $this->taskService->decomposeTask($desc, $currentProjectName);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'count' => $count]);
        } catch (GeminiApiException $e) {
            $code = $e->getCode() ?: 500;
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
            $code = $e->getCode() ?: 500;
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
}
