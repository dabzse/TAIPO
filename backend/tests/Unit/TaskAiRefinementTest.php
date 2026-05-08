<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Service\TaskAiService;
use App\Service\GeminiService;
use App\Service\TaskService;
use App\Service\HistoryService;
use PDO;
use PDOStatement;

class TaskAiRefinementTest extends TestCase
{
    private MockObject|GeminiService $geminiService;
    private MockObject|HistoryService $historyService;
    private MockObject|PDO $pdo;
    private MockObject|TaskService $taskService;
    private TaskAiService $taskAiService;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->geminiService = $this->createMock(GeminiService::class);
        $this->taskService = $this->createMock(TaskService::class);
        $this->historyService = $this->createMock(HistoryService::class);

        $this->taskAiService = new TaskAiService(
            $this->pdo,
            $this->geminiService,
            $this->taskService,
            $this->historyService
        );
    }

    public function testRefineTaskDescription()
    {
        $taskId = 123;
        $taskData = [
            'id' => 123,
            'title' => 'Test Task',
            'description' => 'Original description',
            'project_name' => 'TestProject'
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($taskData);

        // Mock project info stmt
        $stmtInfo = $this->createMock(PDOStatement::class);
        $stmtInfo->method('fetch')->willReturn(['summary' => 'Context info', 'team_id' => 1]);

        $this->pdo->method('prepare')->willReturnOnConsecutiveCalls($stmt, $stmtInfo);

        $this->geminiService->expects($this->once())
                            ->method('askTaipo')
                            ->willReturn('Enhanced description with Acceptance Criteria.');

        $result = $this->taskAiService->refineTaskDescription($taskId);

        $this->assertEquals('Enhanced description with Acceptance Criteria.', $result);
    }
}
