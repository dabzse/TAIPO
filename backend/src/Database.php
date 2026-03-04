<?php

namespace App;

use PDO;
use Exception;
use App\Exception\DatabaseConnectionException;
use App\Config\DatabaseConfig;

class Database
{
    private PDO $pdo;
    private array $config;

    public function __construct(string $dbFile)
    {
        $this->config = DatabaseConfig::get();

        try {
            $this->pdo = new PDO('sqlite:' . $dbFile);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Apply Pragma
            if (isset($this->config['pragma'])) {
                foreach ($this->config['pragma'] as $key => $value) {
                    $this->pdo->exec("PRAGMA $key = $value");
                }
            }

            $this->init();
        } catch (Exception $e) {
            throw new DatabaseConnectionException("Error initializing database: " . $e->getMessage());
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    private function init(): void
    {
        // 1. Create Tables
        if (isset($this->config['schema'])) {
            foreach ($this->config['schema'] as $createSql) {
                $this->pdo->exec($createSql);
            }
        }

        // 2. Run Migrations (Safe column additions)
        $this->ensureColumnsExist('tasks', ['is_subtask', 'po_comments', 'generated_code', 'position', 'title']);

        // 3. Data Migration: Split Description into Title/Description if Title is NULL
        $this->migrateTaskTitles();

        // Special case for projects migration if needed (from ProjectService)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM projects");
        if ($stmt->fetchColumn() == 0) {
            // Check if tasks table structure looks ready, maybe wait for it
            $this->pdo->exec("
                INSERT OR IGNORE INTO projects (name)
                SELECT DISTINCT project_name
                FROM tasks
                WHERE project_name IS NOT NULL AND project_name != ''
            ");
        }

        // 4. Create users Table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 5. Link projects to users (Safe Migration)
        $this->ensureColumnsExist('projects', ['user_id', 'is_archived']);
        $this->ensureColumnsExist('tasks', ['parent_id']); // will be used in story 2.3
    }

    private function ensureColumnsExist(string $tableName, array $columnsToCheck): void
    {
        try {
            $existingColumns = $this->getExistingColumns($tableName);

            foreach ($columnsToCheck as $col) {
                if (!in_array($col, $existingColumns)) {
                    $this->addColumn($tableName, $col);
                }
            }
        } catch (Exception $e) {
            // Log or ignore if already exists and we failed check
        }
    }

    private function getExistingColumns(string $tableName): array
    {
        $columnsData = $this->pdo->query("PRAGMA table_info($tableName)")->fetchAll(PDO::FETCH_ASSOC);
        return array_column($columnsData, 'name');
    }

    private function addColumn(string $tableName, string $col): void
    {
        $definition = $this->getColumnDefinition($col);
        $type = $definition['type'];
        $default = $definition['default'];

        $this->pdo->exec("ALTER TABLE $tableName ADD COLUMN $col $type DEFAULT $default");
    }

    private function getColumnDefinition(string $col): array
    {
        $definitions = [
            'is_subtask' => ['type' => 'INTEGER', 'default' => '0'],
            'position' => ['type' => 'INTEGER', 'default' => '0'],
            'user_id' => ['type' => 'INTEGER', 'default' => 'NULL'],
            'parent_id' => ['type' => 'INTEGER', 'default' => 'NULL'],
        ];

        return $definitions[$col] ?? ['type' => 'TEXT', 'default' => 'NULL'];
    }

    private function migrateTaskTitles(): void
    {
        // Check if there are any tasks with NULL title
        $stmt = $this->pdo->query("SELECT count(*) FROM tasks WHERE title IS NULL");
        if ($stmt->fetchColumn() == 0) {
            return;
        }

        $tasks = $this->pdo->query("SELECT id, description FROM tasks WHERE title IS NULL")->fetchAll(PDO::FETCH_ASSOC);

        $this->pdo->beginTransaction();
        try {
            $updateStmt = $this->pdo->prepare("UPDATE tasks SET title = :title, description = :description WHERE id = :id");

            foreach ($tasks as $task) {
                $fullDesc = $task['description'];
                // Normalize newlines
                $fullDesc = str_replace(["\r\n", "\r"], "\n", $fullDesc);
                $lines = explode("\n", $fullDesc);
                $title = trim($lines[0]);
                // Remove title from description, keep the rest
                $description = count($lines) > 1 ? trim(implode("\n", array_slice($lines, 1))) : '';

                $updateStmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':id' => $task['id']
                ]);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Migration failed: " . $e->getMessage());
        }
    }
}
