<?php

namespace Tests\Unit;

use PDO;
use PDOStatement;

use App\Exception\GeminiApiException;

use App\Service\GeminiService;
use App\Service\HistoryService;
use App\Service\TaskAiService;
use App\Service\TaskService;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class TaskAiPriorityTest extends TestCase
{
    private MockObject|PDO $pdo;
    private MockObject|GeminiService $geminiService;
    private MockObject|HistoryService $historyService;
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

    public function testSuggestPrioritySuccess()
    {
        $taskId = 1;
        $taskData = [
            'id' => 1,
            'project_name' => 'TestProj',
            'title' => 'Test Task',
            'description' => 'Test Desc'
        ];

        // Mock PDO for fetching task
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($taskData);
        $stmt->method('fetchAll')->willReturn([]); // For requirements and other tasks
        $this->pdo->method('prepare')->willReturn($stmt);

        // Mock Gemini response
        $aiResponse = "[PRIORITY]: 2\n[RATIONALE]: This is a core feature.\n[VALUE]: High business value.";
        $this->geminiService->method('askTaipo')->willReturn($aiResponse);

        $result = $this->taskAiService->suggestPriority($taskId);

        $this->assertEquals(2, $result['priority']);
        $this->assertEquals('This is a core feature.', $result['rationale']);
        $this->assertEquals('High business value.', $result['value']);
    }

    public function testSuggestPriorityFailToParse()
    {
        $taskId = 1;
        $taskData = [
            'id' => 1,
            'project_name' => 'TestProj',
            'title' => 'Test Task',
            'description' => 'Test Desc'
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($taskData);
        $stmt->method('fetchAll')->willReturn([]);
        $this->pdo->method('prepare')->willReturn($stmt);

        // Invalid response
        $this->geminiService->method('askTaipo')->willReturn("Invalid response format");

        $this->expectException(GeminiApiException::class);
        $this->taskAiService->suggestPriority($taskId);
    }
}
