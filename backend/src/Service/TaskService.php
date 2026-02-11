<?php

namespace App\Service;

use PDO;
use Exception;
use App\Utils;
use App\Exception\TaskNotFoundException;
use App\Exception\WipLimitExceededException;

class TaskService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getProjects(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT project_name FROM tasks ORDER BY project_name ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTasksByProject(string $projectName): array
    {
        $stmt = $this->pdo->prepare("SELECT id, description, status, is_important, generated_code, is_subtask, po_comments, position FROM tasks WHERE project_name = :projectName ORDER BY position ASC, id ASC");
        $stmt->execute([':projectName' => $projectName]);
        $tasks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tasks[] = $row;
        }
        return $tasks;
    }

    public function reorderTasks(string $projectName, string $status, array $taskIds): void
    {
        try {
            $this->pdo->beginTransaction();

            $priority = 0;
            foreach ($taskIds as $taskId) {
                // Verify task belongs to project (security check)
                $stmt = $this->pdo->prepare("UPDATE tasks SET status = :status, position = :position WHERE id = :id AND project_name = :project_name");
                $stmt->execute([
                    ':status' => $status,
                    ':position' => $priority,
                    ':id' => $taskId,
                    ':project_name' => $projectName
                ]);
                $priority++;
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function addTask(string $projectName, string $description, int $isImportant = 0): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO tasks (project_name, description, status, is_important) VALUES (:project_name, :description, 'SPRINT BACKLOG', :is_important)");
        $stmt->execute([
            ':project_name' => $projectName,
            ':description' => $description,
            ':is_important' => $isImportant
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteTask(int $taskId): string
    {
        // Get status before deleting
        $statusStmt = $this->pdo->prepare("SELECT status FROM tasks WHERE id = :id");
        $statusStmt->execute([':id' => $taskId]);
        $status = $statusStmt->fetchColumn();

        if ($status === false) {
            throw new TaskNotFoundException("Task not found.");
        }

        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);

        return $status;
    }

    public function toggleImportance(int $taskId, int $isImportant): void
    {
        $stmt = $this->pdo->prepare("UPDATE tasks SET is_important = :is_important WHERE id = :id");
        $stmt->execute([
            ':is_important' => $isImportant,
            ':id' => $taskId
        ]);
    }

    public function updateDescription(int $taskId, string $description): void
    {
        $stmt = $this->pdo->prepare("UPDATE tasks SET description = :description WHERE id = :id");
        $stmt->execute([
            ':description' => $description,
            ':id' => $taskId
        ]);
    }

    public function updateStatus(int $taskId, string $newStatus, string $projectName): void
    {
        $wipLimit = Utils::getWIPLimit($newStatus);

        if ($wipLimit !== null) {
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_name = :projectName AND status = :status");
            $countStmt->execute([
                ':projectName' => $projectName,
                ':status' => $newStatus
            ]);
            $currentTaskCount = $countStmt->fetchColumn();

            if ($currentTaskCount >= $wipLimit) {
                throw new WipLimitExceededException("WIP Limit Exceeded: The limit for '{$newStatus}' column is {$wipLimit} tasks.", 403);
            }
        }

        $stmt = $this->pdo->prepare("UPDATE tasks SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $taskId
        ]);
    }

    public function replaceProjectTasks(string $projectName, array $newTasks): int
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE project_name = :projectName");
            $stmt->execute([':projectName' => $projectName]);

            $insertStmt = $this->pdo->prepare(
                "INSERT INTO tasks (project_name, description, status) VALUES (:project_name, :description, :status)"
            );

            $count = 0;
            foreach ($newTasks as $task) {
                $insertStmt->execute([
                    ':project_name' => $projectName,
                    ':description' => $task['description'],
                    ':status' => $task['status']
                ]);
                $count++;
            }

            $this->pdo->commit();
            return $count;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function decomposeTask(string $description, string $projectName, string $apiKey): int
    {
        $prompt = "Decompose this user story into 3-5 concrete, executable technical tasks: '{$description}'.
                    Your response must ONLY be the list of tasks, with each task on a new line.";

        $rawTasks = Utils::callGeminiAPI($apiKey, $prompt);
        $lines = explode("\n", $rawTasks);
        $count = 0;

        $stmt = $this->pdo->prepare("INSERT INTO tasks (project_name, description, status, is_subtask, po_comments) VALUES (?, ?, 'SPRINT BACKLOG', 1, ?)");

        $poFeedback = "TAIPO: Based on original story: \"{$description}\"";

        foreach ($lines as $line) {
            if (trim($line)) {
                $stmt->execute([$projectName, trim($line), $poFeedback]);
                $count++;
            }
        }
        return $count;
    }

    public function queryTask(int $taskId, string $query, string $apiKey): string
    {
        // 1. Fetch current task details INCLUDING project_name
        $stmt = $this->pdo->prepare("SELECT description, po_comments, project_name, status FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            throw new TaskNotFoundException("Task not found.");
        }

        $projectName = $task['project_name'];

        // 2. Fetch all OTHER tasks in the same project to provide context
        $stmtAll = $this->pdo->prepare("SELECT id, description, status FROM tasks WHERE project_name = :project_name ORDER BY status, id");
        $stmtAll->execute([':project_name' => $projectName]);
        $allTasks = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        // 3. Construct Project Context String
        $projectContext = "Project Name: " . $projectName . "\n";
        $projectContext .= "Other Tasks in Project:\n";
        foreach ($allTasks as $t) {
            // Mark the current task distinctly
            $marker = ($t['id'] == $taskId) ? " (CURRENT TASK)" : "";
            $projectContext .= "- [{$t['status']}] {$t['description']}{$marker}\n";
        }

        // 4. Construct Task Specific Context
        $taskContext = "Current Task Description: " . $task['description'];
        $taskContext .= "\nCurrent Task Status: " . $task['status'];
        if (!empty($task['po_comments'])) {
            $taskContext .= "\nProduct Owner Comments: " . $task['po_comments'];
        }

        // 5. Build Final Prompt
        $prompt = "You are TAIPO, an intelligent coding assistant for the project '{$projectName}'.

        Global Project Context:
        {$projectContext}

        Focus on this Specific Task:
        {$taskContext}

        User Question:
        {$query}

        Instructions:
        - Answer the user's question specifically related to the current task.
        - Use the global project context to understand dependencies or overall goals, but focus on the specific task.
        - Refrain from lengthy intros.
        - Provide code snippets if asked.";

        return Utils::callGeminiAPI($apiKey, $prompt);
    }
}
