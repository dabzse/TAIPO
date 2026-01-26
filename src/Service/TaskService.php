<?php

namespace App\Service;

use PDO;
use Exception;
use App\Utils;
use App\Exception\DatabaseConnectionException;
use App\Exception\TaskNotFoundException;
use App\Exception\WipLimitExceededException;

class TaskService
{
    private PDO $pdo;

    public function __construct(string $dbFile)
    {
        $this->connect($dbFile);
        $this->ensureTablesExist();
    }

    private function connect(string $dbFile): void
    {
        try {
            $this->pdo = new PDO('sqlite:' . $dbFile);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            throw new DatabaseConnectionException("Hiba az adatbázis inicializálásakor: " . $e->getMessage());
        }
    }

    private function ensureTablesExist(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_name TEXT NOT NULL,
                description TEXT NOT NULL,
                status TEXT NOT NULL CHECK (status IN ('SPRINTBACKLOG','IMPLEMENTÁCIÓ WIP:3', 'TESZTELÉS WIP:2', 'REVIEW WIP:2', 'KÉSZ')),
                is_important INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    public function getProjects(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT project_name FROM tasks ORDER BY project_name ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTasksByProject(string $projectName): array
    {
        $stmt = $this->pdo->prepare("SELECT id, description, status, is_important FROM tasks WHERE project_name = :projectName ORDER BY id ASC");
        $stmt->execute([':projectName' => $projectName]);
        $tasks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tasks[] = $row;
        }
        return $tasks;
    }

    public function addTask(string $projectName, string $description): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO tasks (project_name, description, status) VALUES (:project_name, :description, 'SPRINTBACKLOG')");
        $stmt->execute([
            ':project_name' => $projectName,
            ':description' => $description
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
            throw new TaskNotFoundException("Feladat nem található.");
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
                throw new WipLimitExceededException("WIP Korlát túllépés: A(z) '{$newStatus}' oszlop maximális korlátja {$wipLimit} feladat.", 403);
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
}
