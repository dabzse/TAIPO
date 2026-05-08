<?php

namespace Tests\Unit;

use App\Service\HistoryService;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

/**
 * Unit tests for HistoryService.
 * Verifies audit trail logging and retrieval for Story 5.1.
 */
class HistoryServiceTest extends TestCase
{
    private $pdo;
    private $historyService;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->historyService = new HistoryService($this->pdo);
    }

    /**
     * Test that log() correctly executes an INSERT statement.
     */
    public function testLogAction()
    {
        $taskId = 101;
        $action = 'status_change';
        $oldValue = 'TODO';
        $newValue = 'IN_PROGRESS';
        $details = 'User moved task';

        $this->historyService->setContext(1, 2);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with($this->callback(function ($params) use ($taskId, $action, $oldValue, $newValue, $details) {
                 return $params[':task_id'] === $taskId &&
                        $params[':user_id'] === 1 &&
                        $params[':team_id'] === 2 &&
                        $params[':action'] === $action &&
                        $params[':old_value'] === $oldValue &&
                        $params[':new_value'] === $newValue &&
                        $params[':details'] === $details;
             }))
             ->willReturn(true);

        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willReturn($stmt);

        $this->pdo->method('lastInsertId')->willReturn("5");

        $id = $this->historyService->log($taskId, $action, $oldValue, $newValue, $details);

        $this->assertEquals(5, $id);
    }

    /**
     * Test log() with null context (AI activity).
     */
    public function testLogActionWithNullContext()
    {
        $taskId = 102;
        $action = 'ai_comment';

        $this->historyService->setContext(null, null);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with($this->callback(function ($params) use ($taskId, $action) {
                 return $params[':task_id'] === $taskId &&
                        $params[':user_id'] === null &&
                        $params[':team_id'] === null &&
                        $params[':action'] === $action;
             }))
             ->willReturn(true);

        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willReturn($stmt);

        $this->historyService->log($taskId, $action);
    }

    /**
     * Test retrieval of task history.
     */
    public function testGetTaskHistory()
    {
        $taskId = 101;
        $mockHistory = [
            ['id' => 5, 'task_id' => 101, 'action' => 'status_change', 'username' => 'testuser']
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with([':task_id' => $taskId]);
        $stmt->method('fetchAll')->willReturn($mockHistory);

        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willReturn($stmt);

        $result = $this->historyService->getTaskHistory($taskId);

        $this->assertCount(1, $result);
        $this->assertEquals('status_change', $result[0]['action']);
        $this->assertEquals('testuser', $result[0]['username']);
    }
}
