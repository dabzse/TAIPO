<?php

namespace App;

use PDO;
use Exception;
use App\Configuration\DatabaseConfig;
use App\Exception\DatabaseConnectionException;

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
        $prefix = Config::getTablePrefix();
        $dbType = $this->getDbType();

        // 1. Maintenance Mode - Clear stale constraints if requested
        if (isset($_GET['rebuild_teams']) && $_GET['rebuild_teams'] === '1') {
            try {
                $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $this->pdo->exec("DROP TABLE IF EXISTS {$prefix}team_users");
                $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                echo "[REPAIR] ✅ Successfully dropped stale 'team_users' table.\n";
            } catch (Exception $e) {
                error_log("Maintenance error: " . $e->getMessage());
            }
        }

        // 2. Create/Sync Schema
        $this->syncSchema($dbType, $prefix);

        // 3. Independent Migrations and Column Checks
        $this->runMigrations($prefix);

        // 4. Seed Standard Data
        $this->seedDefaultRoles($prefix);
    }


    private function syncSchema(string $dbType, string $prefix): void
    {
        $fullConfig = DatabaseConfig::get();
        if (!isset($fullConfig['schema'])) {
            return;
        }

        if ($dbType === 'mysql') {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        }

        $allTableNames = array_keys($fullConfig['schema']);
        foreach ($fullConfig['schema'] as $createSql) {
            // Apply Global Prefixing to all table references
            foreach ($allTableNames as $tn) {
                $createSql = preg_replace('/\b' . preg_quote($tn, '/') . '\b/i', $prefix . $tn, $createSql);
            }

            $createSql = $this->normalizeSchema($createSql, $dbType);
            $this->pdo->exec($createSql);
        }

        if ($dbType === 'mysql') {
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
    }

    private function runMigrations(string $prefix): void
    {
        // Task migrations
        $this->ensureColumnsExist($prefix . 'tasks', ['is_subtask', 'po_comments', 'generated_code', 'position', 'title', 'updated_at', 'parent_id']);
        $this->migrateTaskTitles();

        // Project and Usage migrations
        $this->ensureColumnsExist($prefix . 'projects', ['user_id', 'team_id', 'is_archived', 'last_comment_at', 'last_cr_at', 'is_active', 'next_comment_at', 'next_cr_at']);
        $this->ensureColumnsExist($prefix . 'api_usage', ['user_id', 'team_id']);

        // User migrations
        $this->ensureColumnsExist($prefix . 'users', ['is_instructor']);
    }

    private function seedDefaultRoles(string $prefix): void
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$prefix}roles");
        if ($stmt && (int)$stmt->fetchColumn() === 0) {
            $roles = [
                ['Instructor', 'Course instructor managing teams'],
                ['Student', 'Student working on a project'],
                ['Product Owner', 'PO for the team']
            ];
            $insert = $this->pdo->prepare("INSERT INTO {$prefix}roles (name, description) VALUES (?, ?)");
            foreach ($roles as $role) {
                $insert->execute($role);
            }
        }

        // Seed user ID 1 as Instructor globally
        try {
            $this->pdo->exec("UPDATE {$prefix}users SET is_instructor = 1 WHERE id = 1");
        } catch (Exception $e) {
            // Ignore if users table doesn't have records yet
        }
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
        $definitions = $this->getDefinitionsLookup($dbType);

        if (isset($definitions[$col])) {
            return $definitions[$col];
        }

        // Fallback for unknown columns
        return [
            'type' => ($dbType === 'oci' ? 'VARCHAR2(255)' : 'TEXT'),
            'default' => 'NULL'
        ];
    }

    private function getDefinitionsLookup(string $dbType): array
    {
        $isTimeType = ($dbType === 'pgsql' || $dbType === 'oci');
        $timeType = $isTimeType ? 'TIMESTAMP' : 'DATETIME';
        $timeDefault = $isTimeType ? 'CURRENT_TIMESTAMP' : "'2026-03-16 13:20:00'";
        $clobType = ($dbType === 'oci' ? 'CLOB' : 'TEXT');
        $titleType = ($dbType === 'oci' ? 'VARCHAR2(255)' : 'VARCHAR(255)');

        return [
            'is_subtask' => ['type' => 'INTEGER', 'default' => '0'],
            'position' => ['type' => 'INTEGER', 'default' => '0'],
            'user_id' => ['type' => 'INTEGER', 'default' => 'NULL'],
            'team_id' => ['type' => 'INTEGER', 'default' => 'NULL'],
            'parent_id' => ['type' => 'INTEGER', 'default' => 'NULL'],
            'updated_at' => ['type' => $timeType, 'default' => $timeDefault],
            'is_archived' => ['type' => 'INTEGER', 'default' => '0'],
            'is_active' => ['type' => 'INTEGER', 'default' => '1'],
            'last_comment_at' => ['type' => $timeType, 'default' => 'NULL'],
            'next_comment_at' => ['type' => $timeType, 'default' => 'NULL'],
            'last_cr_at' => ['type' => $timeType, 'default' => 'NULL'],
            'next_cr_at' => ['type' => $timeType, 'default' => 'NULL'],
            'title' => ['type' => $titleType, 'default' => 'NULL'],
            'po_comments' => ['type' => $clobType, 'default' => 'NULL'],
            'generated_code' => ['type' => $clobType, 'default' => 'NULL'],
            'is_instructor' => ['type' => 'INTEGER', 'default' => '0'],
        ];
    }

    public function getDbType(): string
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
