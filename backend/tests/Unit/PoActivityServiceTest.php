<?php

namespace Tests\Unit;

use PDO;
use App\Configuration\DatabaseConfig;
use App\Service\GeminiService;
use App\Service\PoActivityService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PoActivityService: heartbeat timing, working hours check,
 * CR response parsing, and comment generation triggers.
 * These tests do NOT make real API calls — they test the internal logic.
 */
class PoActivityServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['TABLE_PREFIX'] = '';
        $_ENV['GEMINI_API_KEY'] = 'AIzaSyTEST_FAKE_KEY';
        $_ENV['GEMINI_BASE_MODEL'] = 'test-model';
        $_ENV['GEMINI_FALLBACK_MODEL'] = 'test-fallback';
        $_ENV['GEMINI_BASE_URL'] = 'https://example.com/api';
        $_ENV['GEMINI_FALLBACK_URL'] = 'https://example.com/api';
        $_ENV['GEMINI_TEMPERATURE'] = '0.5';
        $_ENV['GEMINI_TOP_K'] = '40';
        $_ENV['GEMINI_TOP_P'] = '0.9';
        $_ENV['GEMINI_MAX_OUTPUT_TOKENS'] = '1024';

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $config = DatabaseConfig::get();
        foreach ($config['pragma'] as $key => $value) {
            $this->pdo->exec("PRAGMA $key = $value");
        }
        foreach ($config['schema'] as $sql) {
            $this->pdo->exec($sql);
        }
    }

    public function testTickDoesNothingWithEmptyProjectName(): void
    {
        $gemini = new GeminiService(null);
        $service = new PoActivityService($this->pdo, $gemini, 'sqlite');

        // Should not throw
        $service->tick('', 1);
        $this->assertTrue(true);
    }

    public function testTickDoesNothingForNonexistentProject(): void
    {
        $gemini = new GeminiService(null);
        $service = new PoActivityService($this->pdo, $gemini, 'sqlite');

        // No project exists, should exit gracefully
        $service->tick('NonExistent', 1);
        $this->assertTrue(true);
    }

    public function testTickDoesNothingIfProjectInactive(): void
    {
        $this->pdo->exec("INSERT INTO projects (name, is_active) VALUES ('InactiveProject', 0)");

        $gemini = $this->createMock(GeminiService::class);
        $gemini->expects($this->never())->method('askTaipo');

        $service = new PoActivityService($this->pdo, $gemini, 'sqlite');
        $service->tick('InactiveProject', 1);

        $this->assertTrue(true);
    }

    public function testScheduleNextActivity(): void
    {
        $this->pdo->exec("INSERT INTO projects (name, is_active) VALUES ('ScheduleTest', 1)");
        $projectId = (int) $this->pdo->lastInsertId();

        $_ENV['SIM_MIN_FEEDBACK_SEC'] = 3600;
        $_ENV['SIM_MAX_FEEDBACK_SEC'] = 3600; // Fixed 1h for testing

        $gemini = new GeminiService(null);
        $service = new PoActivityService($this->pdo, $gemini, 'sqlite');

        $method = new \ReflectionMethod(PoActivityService::class, 'scheduleNextActivity');
        $method->invoke($service, $projectId, 'comment');

        $stmt = $this->pdo->query("SELECT next_comment_at FROM projects WHERE id = $projectId");
        $nextAt = $stmt->fetchColumn();

        $this->assertNotNull($nextAt);
        $expected = date('Y-m-d H:i:s', time() + 3600);
        // Allow 2 seconds difference for execution time
        $this->assertLessThan(2, abs(strtotime($nextAt) - strtotime($expected)));
    }

    public function testParseCrResponseWithValidInput(): void
    {
        $gemini = new GeminiService(null);
        $service = new PoActivityService($this->pdo, $gemini, 'sqlite');

        // Use Reflection to test private method
        $method = new \ReflectionMethod(PoActivityService::class, 'parseCrResponse');


        $input = "[TITLE]: Add user notification system\n[STORY]: As a user I want notifications so I stay informed.";
        $result = $method->invoke($service, $input);

        $this->assertNotNull($result);
        $this->assertSame('Add user notification system', $result['title']);
        $this->assertSame('As a user I want notifications so I stay informed.', $result['story']);
    }

    public function testParseCrResponseReturnsNullForInvalidInput(): void
    {
        $gemini = new GeminiService(null);
        $service = new PoActivityService($this->pdo, $gemini, 'sqlite');

        $method = new \ReflectionMethod(PoActivityService::class, 'parseCrResponse');

        $input = "This is just random text without proper markers.";
        $result = $method->invoke($service, $input);

        $this->assertNull($result);
    }

    public function testParseCrResponseHandlesMissingStory(): void
    {
        $gemini = new GeminiService(null);
        $service = new PoActivityService($this->pdo, $gemini, 'sqlite');

        $method = new \ReflectionMethod(PoActivityService::class, 'parseCrResponse');

        $input = "[TITLE]: Only title provided";
        $result = $method->invoke($service, $input);

        $this->assertNull($result, "Should return null when [STORY] is missing");
    }

    public function testIsWorkingHoursMethod(): void
    {
        $gemini = new GeminiService(null);
        $service = new PoActivityService($this->pdo, $gemini, 'sqlite');

        $method = new \ReflectionMethod(PoActivityService::class, 'isWorkingHours');

        // Test with current time but different active ranges
        $currentHour = (int)date('H');

        // Ensure current hour is within range
        $_ENV['SIM_MIN_ACTIVE_HOUR'] = $currentHour;
        $_ENV['SIM_MAX_ACTIVE_HOUR'] = $currentHour + 1;

        $dayOfWeek = (int)date('N');
        if ($dayOfWeek <= 5) {
            $this->assertTrue($method->invoke($service));
        } else {
            $this->assertFalse($method->invoke($service));
        }

        // Ensure current hour is OUTSIDE range
        $_ENV['SIM_MIN_ACTIVE_HOUR'] = ($currentHour + 1) % 24;
        $_ENV['SIM_MAX_ACTIVE_HOUR'] = ($currentHour + 2) % 24;
        $this->assertFalse($method->invoke($service));
    }

    public function testAddPoCommentAppendsToExisting(): void
    {
        $gemini = new GeminiService(null);
        $service = new PoActivityService($this->pdo, $gemini, 'sqlite');

        // Create project and task
        $this->pdo->exec("INSERT INTO projects (name, user_id) VALUES ('TestPO', 1)");
        $this->pdo->exec("INSERT INTO tasks (project_name, title, description, status, po_comments) VALUES ('TestPO', 'Task', 'Desc', 'SPRINT BACKLOG', 'First comment')");

        $method = new \ReflectionMethod(PoActivityService::class, 'addPoComment');
        $method->invoke($service, 1, 'Second comment');

        $stmt = $this->pdo->query("SELECT po_comments FROM tasks WHERE id = 1");
        $comments = $stmt->fetchColumn();

        $this->assertStringContainsString('First comment', $comments);
        $this->assertStringContainsString('Second comment', $comments);
        $this->assertStringContainsString('---', $comments);
    }

    public function testAddCrTaskCreatesBacklogItem(): void
    {
        $gemini = new GeminiService(null);
        $service = new PoActivityService($this->pdo, $gemini, 'sqlite');

        $this->pdo->exec("INSERT INTO projects (name, user_id) VALUES ('CRProject', 1)");

        $method = new \ReflectionMethod(PoActivityService::class, 'addCrTask');
        $method->invoke($service, 'CRProject', 'CR Title', 'CR Story Description');

        $stmt = $this->pdo->query("SELECT * FROM tasks WHERE project_name = 'CRProject'");
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($task);
        $this->assertSame('CR Title', $task['title']);
        $this->assertSame('SPRINT BACKLOG', $task['status']);
        $this->assertEquals(1, $task['is_important']);
    }
}
