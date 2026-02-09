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
        $stmt = $this->pdo->prepare("SELECT id, description, status, is_important, generated_code, is_subtask, po_comments FROM tasks WHERE project_name = :projectName ORDER BY id ASC");
        $stmt->execute([':projectName' => $projectName]);
        $tasks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tasks[] = $row;
        }
        return $tasks;
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
}
