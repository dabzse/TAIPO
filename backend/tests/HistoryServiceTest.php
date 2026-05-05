<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Service\HistoryService;
use App\Config;

class HistoryServiceTest extends TestCase
{
    private PDO $pdo;
    private HistoryService $historyService;

    protected function setUp(): void
    {
        // Use in-memory SQLite for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Define table prefix if needed for tests
        $_ENV['TABLE_PREFIX'] = 'test_';

        $prefix = Config::getTablePrefix();

        // Create necessary tables
        $this->pdo->exec("CREATE TABLE {$prefix}users (id INTEGER PRIMARY KEY, username TEXT)");
        $this->pdo->exec("CREATE TABLE {$prefix}task_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id INTEGER NOT NULL,
            user_id INTEGER,
            team_id INTEGER,
            action TEXT NOT NULL,
            old_value TEXT,
            new_value TEXT,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->historyService = new HistoryService($this->pdo);
    }

    public function testLogAndRetrieveHistory()
    {
        $this->historyService->setContext(1, 10);
        $logId = $this->historyService->log(123, 'status_change', 'TODO', 'DONE', 'Completed early');

        $this->assertIsInt($logId);
        $this->assertGreaterThan(0, $logId);

        $history = $this->historyService->getTaskHistory(123);

        $this->assertCount(1, $history);
        $this->assertEquals('status_change', $history[0]['action']);
        $this->assertEquals('TODO', $history[0]['old_value']);
        $this->assertEquals('DONE', $history[0]['new_value']);
        $this->assertEquals('Completed early', $history[0]['details']);
        $this->assertEquals(1, $history[0]['user_id']);
        $this->assertEquals(10, $history[0]['team_id']);
    }

    public function testRetrievalOrder()
    {
        $this->historyService->log(123, 'action1');
        sleep(1); // Ensure timestamp difference if not using microtime
        $this->historyService->log(123, 'action2');

        $history = $this->historyService->getTaskHistory(123);

        $this->assertCount(2, $history);
        $this->assertEquals('action2', $history[0]['action']); // DESC order
        $this->assertEquals('action1', $history[1]['action']);
    }
}
