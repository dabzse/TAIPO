<?php

namespace App\Service;

use PDO;
use Exception;
use App\Config;
use App\Exception\ProjectAlreadyExistsException;
use App\Exception\ProjectNotFoundException;

class ProjectService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllProjects(int $userId, bool $isInstructor): array
    {
        $prefix = Config::getTablePrefix();

        if ($isInstructor) {
            $stmt = $this->pdo->prepare("SELECT id, name, team_id, is_active, created_at FROM {$prefix}projects ORDER BY name ASC");
            $stmt->execute();
        } else {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT p.id, p.name, p.team_id, p.is_active, p.created_at
                FROM {$prefix}projects p
                LEFT JOIN {$prefix}team_users tu ON p.team_id = tu.team_id
                WHERE p.user_id = :user_id OR tu.user_id = :user_id
                ORDER BY p.name ASC
            ");
            $stmt->execute([':user_id' => $userId]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createProject(string &$name, ?int $userId = null, ?int $teamId = null): int
    {
        $prefix = Config::getTablePrefix();
        $originalName = $name;
        $counter = 1;
        while (true) {
            $stmt = $this->pdo->prepare("SELECT id FROM {$prefix}projects WHERE name = :name");
            $stmt->execute([':name' => $name]);
            if (!$stmt->fetch()) {
                break;
            }
            $name = $originalName . ' (' . date('Y-m-d H:i') . ')';
            if ($counter > 1) {
                $name .= " $counter";
            }
            $counter++;
        }

        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("INSERT INTO {$prefix}projects (name, user_id, team_id) VALUES (:name, :user_id, :team_id)");
        $stmt->execute([':name' => $name, ':user_id' => $userId, ':team_id' => $teamId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setProjectTeam(int $projectId, ?int $teamId): void
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("UPDATE {$prefix}projects SET team_id = :team_id WHERE id = :id");
        $stmt->execute([':team_id' => $teamId, ':id' => $projectId]);
    }

    public function updateProject(int $id, string $newName): void
    {
        $prefix = Config::getTablePrefix();
        // Check if new name exists for other project
        $stmt = $this->pdo->prepare("SELECT id FROM {$prefix}projects WHERE name = :name AND id != :id");
        $stmt->execute([':name' => $newName, ':id' => $id]);
        if ($stmt->fetch()) {
            throw new ProjectAlreadyExistsException("Project '{$newName}' already exists.");
        }

        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("UPDATE {$prefix}projects SET name = :name WHERE id = :id");
        $stmt->execute([':name' => $newName, ':id' => $id]);
    }

    // We need to handle the string-based foreign key in tasks table.
    // This is a bit tricky since we just migrated but the code still relies on project_name string.
    // For this refactor, we will support renaming tasks.project_name as well.
    public function renameProject(int $id, string $newName): void
    {
        $this->pdo->beginTransaction();
        try {
            $prefix = Config::getTablePrefix();
            // Get old name
            $stmt = $this->pdo->prepare("SELECT name FROM {$prefix}projects WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $oldName = $stmt->fetchColumn();

            if (!$oldName) {
                throw new ProjectNotFoundException("Project not found.");
            }

            $prefix = Config::getTablePrefix();
            // Check duplicate
            $stmt = $this->pdo->prepare("SELECT id FROM {$prefix}projects WHERE name = :name AND id != :id");
            $stmt->execute([':name' => $newName, ':id' => $id]);
            if ($stmt->fetch()) {
                throw new ProjectAlreadyExistsException("Project '{$newName}' already exists.");
            }

            // Update project
            $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("UPDATE {$prefix}projects SET name = :name WHERE id = :id");
            $stmt->execute([':name' => $newName, ':id' => $id]);

            $prefix = Config::getTablePrefix();
            // Update tasks
            $stmt = $this->pdo->prepare("UPDATE {$prefix}tasks SET project_name = :newName WHERE project_name = :oldName");
            $stmt->execute([':newName' => $newName, ':oldName' => $oldName]);

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteProject(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $prefix = Config::getTablePrefix();
            // Get name to delete tasks
            $stmt = $this->pdo->prepare("SELECT name FROM {$prefix}projects WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $name = $stmt->fetchColumn();

            if (!$name) {
                throw new ProjectNotFoundException("Project not found.");
            }

            if ($name) {
                $prefix = Config::getTablePrefix();
                // Delete tasks
                $stmt = $this->pdo->prepare("DELETE FROM {$prefix}tasks WHERE project_name = :name");
                $stmt->execute([':name' => $name]);
            }

            $prefix = Config::getTablePrefix();
            // Delete project
            $stmt = $this->pdo->prepare("DELETE FROM {$prefix}projects WHERE id = :id");
            $stmt->execute([':id' => $id]);

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function toggleProjectActivity(int $projectId, bool $isActive): void
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("UPDATE {$prefix}projects SET is_active = :is_active WHERE id = :id");
        $stmt->execute([':is_active' => $isActive ? 1 : 0, ':id' => $projectId]);
    }
}
