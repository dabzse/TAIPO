<?php

namespace Tests\Unit;

use PDO;
use PDOStatement;

use App\Service\GeminiService;
use App\Service\HistoryService;
use App\Service\TaskAiService;
use App\Service\TaskService;

use PHPUnit\Framework\TestCase;

class TaskAiReviewTest extends TestCase
{
    private $pdo;
    private $geminiService;
    private $historyService;
    private $taskService;
    private $taskAiService;

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

    public function testReviewTaskForAcceptanceAccepted()
    {
        $taskId = 1;
        $taskData = [
            'id' => 1,
            'project_name' => 'TestProj',
            'title' => 'Test Task',
            'description' => 'Test Desc',
            'po_comments' => '',
            'generated_code' => 'print("hello")',
            'status' => 'REVIEW WIP:2'
        ];

        // Mock PDO for fetching task
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($taskData);
        $this->pdo->method('prepare')->willReturn($stmt);

        // Mock Gemini response
        $this->geminiService->method('askTaipo')->willReturn("[STATUS]: ACCEPTED\n[REASON]: Implementation is correct.\n[SUGGESTIONS]: Great job!");

        // Mock TaskService updateStatus
        $this->taskService->expects($this->once())
            ->method('updateStatus')
            ->with($taskId, 'DONE', 'TestProj', 0, false);

        $result = $this->taskAiService->reviewTaskForAcceptance($taskId);

        $this->assertEquals('ACCEPTED', $result['status']);
        $this->assertEquals('DONE', $result['new_status']);
    }

    public function testReviewTaskForAcceptanceRejected()
    {
        $taskId = 1;
        $taskData = [
            'id' => 1,
            'project_name' => 'TestProj',
            'title' => 'Test Task',
            'description' => 'Test Desc',
            'po_comments' => '',
            'generated_code' => 'print("hello")',
            'status' => 'REVIEW WIP:2'
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($taskData);
        $this->pdo->method('prepare')->willReturn($stmt);

        $this->geminiService->method('askTaipo')->willReturn("[STATUS]: REJECTED\n[REASON]: Missing error handling.\n[SUGGESTIONS]: Add try-except block.");

        $this->taskService->expects($this->once())
            ->method('updateStatus')
            ->with($taskId, 'SPRINT BACKLOG', 'TestProj', 0, false);

        $result = $this->taskAiService->reviewTaskForAcceptance($taskId);

        $this->assertEquals('REJECTED', $result['status']);
        $this->assertEquals('SPRINT BACKLOG', $result['new_status']);
    }
}
