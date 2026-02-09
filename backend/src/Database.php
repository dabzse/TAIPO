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
        $this->ensureColumnsExist('tasks', ['is_subtask', 'po_comments', 'generated_code']);

        // Special case for projects migration if needed (from ProjectService)
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM projects");
        if ($stmt->fetchColumn() == 0) {
            // Only run if tasks table exists which it should by now
            $this->pdo->exec("
                INSERT OR IGNORE INTO projects (name)
                SELECT DISTINCT project_name
                FROM tasks
                WHERE project_name IS NOT NULL AND project_name != ''
            ");
        }
    }

    private function ensureColumnsExist(string $tableName, array $columnsToCheck): void
    {
        try {
            $columnsData = $this->pdo->query("PRAGMA table_info($tableName)")->fetchAll(PDO::FETCH_ASSOC);
            $existingColumns = array_column($columnsData, 'name');

            foreach ($columnsToCheck as $col) {
                if (!in_array($col, $existingColumns)) {
                    $default = 'NULL';
                    $type = 'TEXT';

                    if ($col === 'is_subtask') {
                        $type = 'INTEGER';
                        $default = '0';
                    }
                    if ($col === 'po_comments') {
                        $type = 'TEXT';
                        $default = 'NULL';
                    }
                    if ($col === 'generated_code') {
                        $type = 'TEXT';
                        $default = 'NULL';
                    }

                    $this->pdo->exec("ALTER TABLE $tableName ADD COLUMN $col $type DEFAULT $default");
                }
            }
        } catch (Exception $e) {
            // Log or ignore if already exists and we failed check
        }
    }
}
