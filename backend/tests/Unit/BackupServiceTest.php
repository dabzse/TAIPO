<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Service\BackupService;
use App\Configuration\DatabaseConfig;
use App\Config;
use PDO;

class BackupServiceTest extends TestCase
{
    private PDO $pdo;
    private BackupService $backupService;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Initialize an in-memory SQLite database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $config = DatabaseConfig::get();
        foreach ($config['pragma'] as $key => $value) {
            $this->pdo->exec("PRAGMA $key = $value");
        }

        // Apply normal sqlite substitutions to match App\Database class syncSchema logic
        $prefix = Config::getTablePrefix();
        $allTableNames = array_keys($config['schema']);
        foreach ($config['schema'] as $sql) {
            foreach ($allTableNames as $tn) {
                $sql = preg_replace('/\b' . preg_quote($tn, '/') . '\b/i', $prefix . $tn, $sql);
            }
            $this->pdo->exec($sql);
        }

        $this->backupService = new BackupService($this->pdo);
    }

    /**
     * Test exporting backup generates valid JSON and contains all schema tables.
     */
    public function testExportBackup()
    {
        $prefix = Config::getTablePrefix();
        // Seed some sample data
        $this->pdo->exec("INSERT INTO {$prefix}settings (`key`, `value`) VALUES ('site_title', 'TAIPO Test')");
        $this->pdo->exec("INSERT INTO {$prefix}roles (name, description) VALUES ('Instructor', 'Mage')");
        $this->pdo->exec("INSERT INTO {$prefix}users (username, password_hash, is_instructor) VALUES ('instructor_test', 'hash', 1)");

        $json = $this->backupService->exportBackup();
        $this->assertJson($json);

        $data = json_decode($json, true);
        $this->assertSame('1.0', $data['version']);
        $this->assertArrayHasKey('tables', $data);
        $this->assertArrayHasKey('settings', $data['tables']);
        $this->assertCount(1, $data['tables']['settings']);
        $this->assertSame('site_title', $data['tables']['settings'][0]['key']);
        $this->assertSame('TAIPO Test', $data['tables']['settings'][0]['value']);
    }

    /**
     * Test that importing/restoring from a backup works correctly.
     */
    public function testImportBackup()
    {
        $prefix = Config::getTablePrefix();
        // 1. Seed initial data
        $this->pdo->exec("INSERT INTO {$prefix}settings (`key`, `value`) VALUES ('site_title', 'Original Title')");
        $this->pdo->exec("INSERT INTO {$prefix}roles (id, name, description) VALUES (1, 'Admin', 'Root')");
        $this->pdo->exec("INSERT INTO {$prefix}users (id, username, password_hash, is_instructor) VALUES (1, 'admin_user', 'hash', 1)");
        $this->pdo->exec("INSERT INTO {$prefix}projects (name, user_id) VALUES ('ProjectAlpha', 1)");

        // 2. Export to backup JSON
        $backupJson = $this->backupService->exportBackup();

        // 3. Mutate the database (simulate manual changes, additions, deletions)
        $this->pdo->exec("UPDATE {$prefix}settings SET `value` = 'Mutated Title' WHERE `key` = 'site_title'");
        $this->pdo->exec("INSERT INTO {$prefix}projects (name, user_id) VALUES ('ProjectBeta', 1)");

        // 4. Restore the database using backup
        $result = $this->backupService->importBackup($backupJson);
        $this->assertTrue($result);

        // 5. Verify database has reverted to exact original state
        $stmtSettings = $this->pdo->query("SELECT `value` FROM {$prefix}settings WHERE `key` = 'site_title'");
        $this->assertSame('Original Title', $stmtSettings->fetchColumn());

        $stmtProjects = $this->pdo->query("SELECT COUNT(*) FROM {$prefix}projects");
        $this->assertEquals(1, $stmtProjects->fetchColumn()); // ProjectBeta should be gone

        $stmtProjectName = $this->pdo->query("SELECT name FROM {$prefix}projects");
        $this->assertSame('ProjectAlpha', $stmtProjectName->fetchColumn());
    }

    /**
     * Test import backup throws exception on invalid or corrupt formats.
     */
    public function testImportBackupThrowsOnInvalidFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid backup data format.");
        $this->backupService->importBackup('{"invalid": "json"}');
    }
}
