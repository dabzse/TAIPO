<?php

namespace App\Service;

use PDO;
use Exception;
use App\Utils;
use App\Service\GeminiService;
use App\Service\ProjectAccessTrait;
use App\Exception\TaskNotFoundException;
use App\Exception\WipLimitExceededException;
use App\Exception\ProjectUnauthorizedException;
use App\Config;

class TaskService
{
    use ProjectAccessTrait;

    public const STATUS_SPRINT_BACKLOG = 'SPRINT BACKLOG';

    private GeminiService $geminiService;

    public function __construct(PDO $pdo, GeminiService $geminiService)
    {
        $this->pdo = $pdo;
        $this->geminiService = $geminiService;
    }

    public function getProjects(): array
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->query("SELECT DISTINCT project_name FROM {$prefix}tasks ORDER BY project_name ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTaskById(int $taskId): ?array
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT id, title, description, status, is_important, generated_code, is_subtask, po_comments, position, parent_id, project_name, updated_at FROM {$prefix}tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        return $task ?: null;
    }

    public function getTasksByProject(string $projectName, int $userId = 0, bool $isInstructor = false): array
    {
        if (!$this->isAuthorized($projectName, $userId, $isInstructor)) {
            return [];
        }

        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT id, title, description, status, is_important, generated_code, is_subtask, po_comments, position, parent_id, updated_at FROM {$prefix}tasks WHERE project_name = :projectName ORDER BY position ASC, id ASC");
        $stmt->execute([':projectName' => $projectName]);
        $tasks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tasks[] = $row;
        }
        return $tasks;
    }

    public function reorderTasks(string $projectName, string $status, array $taskIds, int $userId = 0, bool $isInstructor = false): void
    {
        if (!$this->isAuthorized($projectName, $userId, $isInstructor)) {
            throw new ProjectUnauthorizedException($projectName);
        }

        try {
            $this->pdo->beginTransaction();

            $prefix = Config::getTablePrefix();
            $priority = 0;
            foreach ($taskIds as $taskId) {
                // Verify task belongs to project (security check)
                $stmt = $this->pdo->prepare("UPDATE {$prefix}tasks SET status = :status, position = :position WHERE id = :id AND project_name = :project_name");
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

    public function addTask(string $projectName, string $title, string $description, int $isImportant = 0, int $userId = 0, bool $isInstructor = false): int
    {
        if (!$this->isAuthorized($projectName, $userId, $isInstructor)) {
            throw new ProjectUnauthorizedException($projectName);
        }

        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("INSERT INTO {$prefix}tasks (project_name, title, description, status, is_important) VALUES (:project_name, :title, :description, '" . self::STATUS_SPRINT_BACKLOG . "', :is_important)");
        $stmt->execute([
            ':project_name' => $projectName,
            ':title' => $title,
            ':description' => $description,
            ':is_important' => $isImportant
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteTask(int $taskId, int $userId = 0, bool $isInstructor = false): string
    {
        $prefix = Config::getTablePrefix();
        // Get status and project_name before deleting
        $infoStmt = $this->pdo->prepare("SELECT status, project_name FROM {$prefix}tasks WHERE id = :id");
        $infoStmt->execute([':id' => $taskId]);
        $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

        if ($info === false) {
            throw new TaskNotFoundException("Task not found.");
        }

        if (!$this->isAuthorized($info['project_name'], $userId, $isInstructor)) {
            throw new ProjectUnauthorizedException($info['project_name']);
        }

        $stmt = $this->pdo->prepare("DELETE FROM {$prefix}tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);

        return $info['status'];
    }

    public function toggleImportance(int $taskId, int $isImportant, int $userId = 0, bool $isInstructor = false): int
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT project_name FROM {$prefix}tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $projectName = $stmt->fetchColumn();

        if (!$projectName) {
            return 0;
        }

        if (!$this->isAuthorized($projectName, $userId, $isInstructor)) {
            throw new ProjectUnauthorizedException($projectName);
        }

        $stmt = $this->pdo->prepare("UPDATE {$prefix}tasks SET is_important = :is_important WHERE id = :id");
        $stmt->execute([
            ':is_important' => $isImportant,
            ':id' => $taskId
        ]);
        return $stmt->rowCount();
    }

    public function updateTask(int $taskId, string $title, string $description, ?string $lastUpdatedAt = null, int $userId = 0, bool $isInstructor = false): int
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT project_name FROM {$prefix}tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $projectName = $stmt->fetchColumn();

        if (!$projectName) {
            return 0;
        }

        if (!$this->isAuthorized($projectName, $userId, $isInstructor)) {
            throw new ProjectUnauthorizedException($projectName);
        }

        $query = "UPDATE {$prefix}tasks SET title = :title, description = :description, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $params = [
            ':title' => $title,
            ':description' => $description,
            ':id' => $taskId
        ];

        if ($lastUpdatedAt !== null) {
            $query .= " AND updated_at = :last_updated_at";
            $params[':last_updated_at'] = $lastUpdatedAt;
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function updateStatus(int $taskId, string $newStatus, string $projectName, int $userId = 0, bool $isInstructor = false): void
    {
        if (!$this->isAuthorized($projectName, $userId, $isInstructor)) {
            throw new ProjectUnauthorizedException($projectName);
        }

        $wipLimit = Utils::getWIPLimit($newStatus);

        $prefix = Config::getTablePrefix();
        if ($wipLimit !== null) {
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$prefix}tasks WHERE project_name = :projectName AND status = :status");
            $countStmt->execute([
                ':projectName' => $projectName,
                ':status' => $newStatus
            ]);
            $currentTaskCount = $countStmt->fetchColumn();

            if ($currentTaskCount >= $wipLimit) {
                throw new WipLimitExceededException("WIP Limit Exceeded: The limit for '{$newStatus}' column is {$wipLimit} tasks.", 403);
            }
        }

        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("UPDATE {$prefix}tasks SET status = :status WHERE id = :id AND project_name = :project_name");
        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $taskId,
            ':project_name' => $projectName
        ]);
    }

    public function replaceProjectTasks(string $projectName, array $newTasks, int $userId = 0, bool $isInstructor = false): int
    {
        if (!$this->isAuthorized($projectName, $userId, $isInstructor)) {
            throw new ProjectUnauthorizedException($projectName);
        }

        try {
            $this->pdo->beginTransaction();

            $prefix = Config::getTablePrefix();
            $stmt = $this->pdo->prepare("DELETE FROM {$prefix}tasks WHERE project_name = :projectName");
            $stmt->execute([':projectName' => $projectName]);

            $insertStmt = $this->pdo->prepare(
                "INSERT INTO {$prefix}tasks (project_name, title, description, status, is_important) VALUES (:project_name, :title, :description, :status, :is_important)"
            );

            $count = 0;
            foreach ($newTasks as $task) {
                $tTitle = $task['title'] ?? '';
                $tDesc = $task['description'] ?? '';
                if (empty($tTitle) && !empty($tDesc)) {
                    $lines = explode("\n", $tDesc);
                    $tTitle = trim($lines[0]);
                    $tDesc = count($lines) > 1 ? trim(implode("\n", array_slice($lines, 1))) : '';
                }

                $insertStmt->execute([
                    ':project_name' => $projectName,
                    ':title' => $tTitle,
                    ':description' => $tDesc,
                    ':status' => $task['status'],
                    ':is_important' => $task['is_important'] ?? 0
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
}
