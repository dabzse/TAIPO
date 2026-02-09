<?php

namespace App\Service;

use PDO;
use Exception;
use App\Exception\ProjectNotFoundException;
use App\Exception\ProjectAlreadyExistsException;

class ProjectService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllProjects(): array
    {
        $stmt = $this->pdo->query("SELECT id, name, created_at FROM projects ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createProject(string $name): int
    {
        // Check if exists
        $stmt = $this->pdo->prepare("SELECT id FROM projects WHERE name = :name");
        $stmt->execute([':name' => $name]);
        if ($stmt->fetch()) {
            throw new ProjectAlreadyExistsException("Project '{$name}' already exists.");
        }

        $stmt = $this->pdo->prepare("INSERT INTO projects (name) VALUES (:name)");
        $stmt->execute([':name' => $name]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateProject(int $id, string $newName): void
    {
        // Check if new name exists for other project
        $stmt = $this->pdo->prepare("SELECT id FROM projects WHERE name = :name AND id != :id");
        $stmt->execute([':name' => $newName, ':id' => $id]);
        if ($stmt->fetch()) {
            throw new ProjectAlreadyExistsException("Project '{$newName}' already exists.");
        }

        $stmt = $this->pdo->prepare("UPDATE projects SET name = :name WHERE id = :id");
        $stmt->execute([':name' => $newName, ':id' => $id]);
    }

    // We need to handle the string-based foreign key in tasks table.
    // This is a bit tricky since we just migrated but the code still relies on project_name string.
    // For this refactor, we will support renaming tasks.project_name as well.
    public function renameProject(int $id, string $newName): void
    {
        $this->pdo->beginTransaction();
        try {
            // Get old name
            $stmt = $this->pdo->prepare("SELECT name FROM projects WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $oldName = $stmt->fetchColumn();

            if (!$oldName) {
                throw new ProjectNotFoundException("Project not found.");
            }

            // Check duplicate
            $stmt = $this->pdo->prepare("SELECT id FROM projects WHERE name = :name AND id != :id");
            $stmt->execute([':name' => $newName, ':id' => $id]);
            if ($stmt->fetch()) {
                throw new ProjectAlreadyExistsException("Project '{$newName}' already exists.");
            }

            // Update project
            $stmt = $this->pdo->prepare("UPDATE projects SET name = :name WHERE id = :id");
            $stmt->execute([':name' => $newName, ':id' => $id]);

            // Update tasks
            $stmt = $this->pdo->prepare("UPDATE tasks SET project_name = :newName WHERE project_name = :oldName");
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
            // Get name to delete tasks
            $stmt = $this->pdo->prepare("SELECT name FROM projects WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $name = $stmt->fetchColumn();

            if (!$name) {
                throw new ProjectNotFoundException("Project not found.");
            }

            if ($name) {
                // Delete tasks
                $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE project_name = :name");
                $stmt->execute([':name' => $name]);
            }

            // Delete project
            $stmt = $this->pdo->prepare("DELETE FROM projects WHERE id = :id");
            $stmt->execute([':id' => $id]);

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
