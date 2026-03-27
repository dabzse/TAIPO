<?php

namespace App;

use PDO;
use Exception;
use App\Exception\DatabaseConnectionException;
use App\Config\DatabaseConfig;

class Database
{
    public const SQLITE_AUTOINCREMENT = 'INTEGER PRIMARY KEY AUTOINCREMENT';

    private PDO $pdo;
    private array $dbConfig;

    public function __construct()
    {
        $this->dbConfig = Config::getDatabaseConfig();

        try {
            $this->connect();
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->init();
        } catch (Exception $e) {
            throw new DatabaseConnectionException("Error initializing database: " . $e->getMessage());
        }
    }

    private function connect(): void
    {
        $sqliteFile = $this->dbConfig['sqlite_file'] ?? null;

        // Use SQLite if SQLITE_FILE_NAME is set and not "None"
        if (!empty($sqliteFile) && strcasecmp($sqliteFile, 'None') !== 0) {
            $this->connectSqlite($sqliteFile);
            return;
        }

        $dbType = strtolower($this->dbConfig['type'] ?? 'mysql');

        switch ($dbType) {
            case 'mysql':
            case 'mariadb':
                $this->connectMysql();
                break;
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                $this->connectPgsql();
                break;
            case 'sqlsrv':
            case 'mssql':
                $this->connectSqlsrv();
                break;
            case 'oci':
            case 'oracle':
                $this->connectOci();
                break;
            default:
                // Fallback to SQLite if nothing else is configured properly
                $this->connectSqlite('kanban.sqlite');
                break;
        }
    }

    private function connectSqlite(string $dbFile): void
    {
        // Support both absolute and relative paths
        if (!preg_match('/^(\/|[A-Z]:\\\\)/i', $dbFile)) {
            $dbFile = __DIR__ . '/../' . $dbFile;
        }

        $this->pdo = new PDO('sqlite:' . $dbFile);

        // Apply Pragma if defined in DatabaseConfig (for SQLite specific optimizations)
        $fullConfig = DatabaseConfig::get();
        if (isset($fullConfig['pragma'])) {
            foreach ($fullConfig['pragma'] as $key => $value) {
                $this->pdo->exec("PRAGMA $key = $value");
            }
        }
    }

    private function connectMysql(): void
    {
        $host = $this->dbConfig['host'];
        $dbName = $this->dbConfig['dbname'];
        $user = $this->dbConfig['user'];
        $pass = $this->dbConfig['password'];
        $port = $this->dbConfig['port'];

        $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass);
    }

    private function connectPgsql(): void
    {
        $host = $this->dbConfig['host'];
        $dbName = $this->dbConfig['dbname'];
        $user = $this->dbConfig['user'];
        $pass = $this->dbConfig['password'];
        $port = $this->dbConfig['port'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbName;";
        $this->pdo = new PDO($dsn, $user, $pass);
    }

    private function connectSqlsrv(): void
    {
        $host = $this->dbConfig['host'];
        $dbName = $this->dbConfig['dbname'];
        $user = $this->dbConfig['user'];
        $pass = $this->dbConfig['password'];

        // TrustServerCertificate=1 is often needed for local dev
        $dsn = "sqlsrv:Server=$host;Database=$dbName;TrustServerCertificate=1";
        $this->pdo = new PDO($dsn, $user, $pass);
    }

    private function connectOci(): void
    {
        $host = $this->dbConfig['host'];
        $dbName = $this->dbConfig['dbname'];
        $user = $this->dbConfig['user'];
        $pass = $this->dbConfig['password'];
        $port = $this->dbConfig['port'];

        // TNS format
        $tns = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$host)(PORT=$port))(CONNECT_DATA=(SERVICE_NAME=$dbName)))";
        $this->pdo = new PDO("oci:dbname=" . $tns, $user, $pass);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    private function init(): void
    {
        $dbType = $this->getDbType();
        $prefix = Config::getTablePrefix();
        $fullConfig = DatabaseConfig::get();

        // 1. Create Tables from Schema
        if (isset($fullConfig['schema'])) {
            foreach ($fullConfig['schema'] as $tableName => $createSql) {
                $prefixedTableName = $prefix . $tableName;
                $createSql = preg_replace('/CREATE TABLE IF NOT EXISTS (\w+)/i', "CREATE TABLE IF NOT EXISTS $prefixedTableName", $createSql);

                // Driver-specific normalization
                $createSql = $this->normalizeSchema($createSql, $dbType);

                $this->pdo->exec($createSql);
            }
        }

        // 2. Run Migrations (Safe column additions)
        $this->ensureColumnsExist($prefix . 'tasks', ['is_subtask', 'po_comments', 'generated_code', 'position', 'title', 'updated_at']);

        // 3. Data Migration: Split Description into Title/Description if Title is NULL
        $this->migrateTaskTitles();

        // 4. Projects Migration (if tasks table has projects but projects table is empty)
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$prefix}projects");
            $count = $stmt->fetchColumn();
            if ($count === false || (int)$count === 0) {
                $ignoreKeyword = ($dbType === 'mysql') ? 'IGNORE' : 'OR IGNORE';
                if ($dbType === 'pgsql') {
                    $this->pdo->exec("
                        INSERT INTO {$prefix}projects (name)
                        SELECT DISTINCT project_name
                        FROM {$prefix}tasks
                        WHERE project_name IS NOT NULL AND project_name != ''
                        ON CONFLICT (name) DO NOTHING
                    ");
                } elseif ($dbType === 'sqlsrv' || $dbType === 'oci') {
                    // Manual check or specific syntax for SQLServer/Oracle
                    // For simplicity in init, we skip the bulk copy if no easy IGNORE syntax exists
                } else {
                    $this->pdo->exec("
                        INSERT $ignoreKeyword INTO {$prefix}projects (name)
                        SELECT DISTINCT project_name
                        FROM {$prefix}tasks
                        WHERE project_name IS NOT NULL AND project_name != ''
                    ");
                }
            }
        } catch (Exception $e) {
            // Table might not exist yet or specific driver error
        }

        // 5. Create users Table
        $userTableSql = $this->getUserTableSql($dbType, $prefix);
        $this->pdo->exec($userTableSql);

        // 6. Link projects to users (Safe Migration)
        $this->ensureColumnsExist($prefix . 'projects', ['user_id', 'is_archived']);
        $this->ensureColumnsExist($prefix . 'tasks', ['parent_id']);
    }

    private function normalizeSchema(string $sql, string $dbType): string
    {
        if ($dbType === 'mysql') {
            $sql = str_ireplace(self::SQLITE_AUTOINCREMENT, 'INT AUTO_INCREMENT PRIMARY KEY', $sql);
            if (stripos($sql, 'ENGINE=') === false) {
                $sql = rtrim($sql, ';') . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
            }
        } elseif ($dbType === 'pgsql') {
            $sql = str_ireplace(self::SQLITE_AUTOINCREMENT, 'SERIAL PRIMARY KEY', $sql);
            $sql = str_ireplace('DATETIME', 'TIMESTAMP', $sql);
        } elseif ($dbType === 'sqlsrv') {
            $sql = str_ireplace(self::SQLITE_AUTOINCREMENT, 'INT IDENTITY(1,1) PRIMARY KEY', $sql);
        } elseif ($dbType === 'oci') {
            $sql = str_ireplace(self::SQLITE_AUTOINCREMENT, 'NUMBER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY', $sql);
            $sql = str_ireplace('TEXT', 'CLOB', $sql);
            $sql = str_ireplace('DATETIME', 'TIMESTAMP', $sql);
        }
        return $sql;
    }

    private function getUserTableSql(string $dbType, string $prefix): string
    {
        $tableName = "{$prefix}users";
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";

        if ($dbType === 'mysql') {
            $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(191) NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        } elseif ($dbType === 'pgsql') {
            $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                id SERIAL PRIMARY KEY,
                username VARCHAR(191) NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
        } elseif ($dbType === 'sqlsrv') {
            $sql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='$tableName' AND xtype='U')
                CREATE TABLE $tableName (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    username NVARCHAR(191) NOT NULL UNIQUE,
                    password_hash NVARCHAR(MAX) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )";
        } elseif ($dbType === 'oci') {
            $sql = "BEGIN
                EXECUTE IMMEDIATE 'CREATE TABLE $tableName (
                    id NUMBER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                    username VARCHAR2(191) NOT NULL UNIQUE,
                    password_hash CLOB NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )';
                EXCEPTION WHEN OTHERS THEN IF SQLCODE = -955 THEN NULL; ELSE RAISE; END IF;
            END;";
        }

        return $sql;
    }

    private function ensureColumnsExist(string $tableName, array $columnsToCheck): void
    {
        try {
            $existingColumns = $this->getExistingColumns($tableName);
            foreach ($columnsToCheck as $col) {
                if (!in_array(strtolower($col), array_map('strtolower', $existingColumns))) {
                    $this->addColumn($tableName, $col);
                }
            }
        } catch (Exception $e) {
            // Log or ignore
        }
    }

    private function getExistingColumns(string $tableName): array
    {
        $dbType = $this->getDbType();
        $cols = [];

        if ($dbType === 'mysql') {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM `$tableName` ");
            $cols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        } elseif ($dbType === 'pgsql') {
            $pureTableName = strpos($tableName, '.') !== false ? substr($tableName, strpos($tableName, '.') + 1) : $tableName;
            $stmt = $this->pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = :table");
            $stmt->execute([':table' => $pureTableName]);
            $cols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'column_name');
        } elseif ($dbType === 'sqlsrv') {
            $stmt = $this->pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table");
            $stmt->execute([':table' => $tableName]);
            $cols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
        } elseif ($dbType === 'oci') {
            $stmt = $this->pdo->prepare("SELECT COLUMN_NAME FROM USER_TAB_COLUMNS WHERE TABLE_NAME = :table");
            $stmt->execute([':table' => strtoupper($tableName)]);
            $cols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
        } else {
            $columnsData = $this->pdo->query("PRAGMA table_info($tableName)")->fetchAll(PDO::FETCH_ASSOC);
            $cols = array_column($columnsData, 'name');
        }

        return $cols;
    }

    private function addColumn(string $tableName, string $col): void
    {
        $dbType = $this->getDbType();
        $definition = $this->getColumnDefinition($col, $dbType);
        $type = $definition['type'];
        $default = $definition['default'];

        $quote = ($dbType === 'mysql') ? '`' : '"';
        if ($dbType === 'sqlsrv') {
            $quote = ''; // sqlsrv handles it or uses []
        }

        $this->pdo->exec("ALTER TABLE {$quote}{$tableName}{$quote} ADD {$quote}{$col}{$quote} $type" . ($default !== 'NULL' ? " DEFAULT $default" : ""));
    }

    private function getColumnDefinition(string $col, string $dbType): array
    {
        $definitions = [
            'is_subtask' => ['type' => 'INTEGER', 'default' => '0'],
            'position' => ['type' => 'INTEGER', 'default' => '0'],
            'user_id' => ['type' => 'INTEGER', 'default' => 'NULL'],
            'parent_id' => ['type' => 'INTEGER', 'default' => 'NULL'],
            'updated_at' => [
                'type' => ($dbType === 'pgsql' || $dbType === 'oci' ? 'TIMESTAMP' : 'DATETIME'),
                'default' => ($dbType === 'pgsql' || $dbType === 'oci' ? 'CURRENT_TIMESTAMP' : "'2026-03-16 13:20:00'")
            ],
            'is_archived' => ['type' => 'INTEGER', 'default' => '0'],
            'title' => ['type' => ($dbType === 'oci' ? 'VARCHAR2(255)' : 'VARCHAR(255)'), 'default' => 'NULL'],
            'po_comments' => ['type' => ($dbType === 'oci' ? 'CLOB' : 'TEXT'), 'default' => 'NULL'],
            'generated_code' => ['type' => ($dbType === 'oci' ? 'CLOB' : 'TEXT'), 'default' => 'NULL'],
        ];

        return $definitions[$col] ?? ['type' => ($dbType === 'oci' ? 'VARCHAR2(255)' : 'TEXT'), 'default' => 'NULL'];
    }

    private function getDbType(): string
    {
        $sqliteFile = $this->dbConfig['sqlite_file'] ?? null;
        if (!empty($sqliteFile) && strcasecmp($sqliteFile, 'None') !== 0) {
            return 'sqlite';
        }

        $type = strtolower($this->dbConfig['type'] ?? 'mysql');
        $resolvedType = $type;

        if ($type === 'mariadb') {
            $resolvedType = 'mysql';
        } elseif ($type === 'postgres' || $type === 'postgresql') {
            $resolvedType = 'pgsql';
        } elseif ($type === 'mssql') {
            $resolvedType = 'sqlsrv';
        } elseif ($type === 'oracle') {
            $resolvedType = 'oci';
        }

        return $resolvedType;
    }

    private function migrateTaskTitles(): void
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->query("SELECT count(*) FROM {$prefix}tasks WHERE title IS NULL");
        if ($stmt->fetchColumn() == 0) {
            return;
        }

        $tasks = $this->pdo->query("SELECT id, description FROM {$prefix}tasks WHERE title IS NULL")->fetchAll(PDO::FETCH_ASSOC);

        $this->pdo->beginTransaction();
        try {
            $updateStmt = $this->pdo->prepare("UPDATE {$prefix}tasks SET title = :title, description = :description WHERE id = :id");

            foreach ($tasks as $task) {
                $fullDesc = $task['description'] ?? '';
                $fullDesc = str_replace(["\r\n", "\r"], "\n", $fullDesc);
                $lines = explode("\n", $fullDesc);
                $title = trim($lines[0] ?? '');
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
