<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Service\TawosService;
use PDO;

use App\Config;

class TawosServiceTest extends TestCase
{
    private PDO $pdo;
    private TawosService $service;
    private string $prefix;

    protected function setUp(): void
    {
        $this->prefix = Config::getTablePrefix();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create schema with proper prefix
        $this->pdo->exec("CREATE TABLE {$this->prefix}tawos_issues (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            issue_key VARCHAR(64),
            title VARCHAR(512),
            description_text TEXT,
            type VARCHAR(64),
            priority VARCHAR(64),
            status VARCHAR(64),
            resolution VARCHAR(64),
            story_point REAL DEFAULT NULL,
            comment_text TEXT DEFAULT NULL,
            project_name VARCHAR(256),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->service = new TawosService($this->pdo, 'sqlite');
    }

    public function testIsSeededReturnsFalseWhenEmpty(): void
    {
        $this->assertFalse($this->service->isSeeded());
    }

    public function testSeedFromCsvInsertsRecords(): void
    {
        $csvPath = realpath(__DIR__ . '/../../data/tawos_seed.csv');
        if (!$csvPath) {
            $this->markTestSkipped('tawos_seed.csv not found');
        }

        $count = $this->service->seedFromCsv($csvPath);
        $this->assertGreaterThan(0, $count);
        $this->assertTrue($this->service->isSeeded());
    }

    public function testSeedFromCsvReturnsZeroForMissingFile(): void
    {
        $count = $this->service->seedFromCsv('/nonexistent/path.csv');
        $this->assertSame(0, $count);
    }

    public function testGetRelevantIssuesFiltersByType(): void
    {
        $this->insertSampleData();

        $stories = $this->service->getRelevantIssues('Story', 10);
        $this->assertNotEmpty($stories);
        foreach ($stories as $issue) {
            $this->assertSame('Story', $issue['type']);
        }

        $bugs = $this->service->getRelevantIssues('Bug', 10);
        foreach ($bugs as $issue) {
            $this->assertSame('Bug', $issue['type']);
        }
    }

    public function testGetRandomCommentReturnsString(): void
    {
        $this->insertSampleData();
        $comment = $this->service->getRandomComment();
        $this->assertNotNull($comment);
        $this->assertIsString($comment);
    }

    public function testGetRandomCommentReturnsNullWhenEmpty(): void
    {
        $comment = $this->service->getRandomComment();
        $this->assertNull($comment);
    }

    public function testGetSampleChangePatternReturnsArray(): void
    {
        $this->insertSampleData();
        $pattern = $this->service->getSampleChangePattern();
        $this->assertNotNull($pattern);
        $this->assertArrayHasKey('title', $pattern);
        $this->assertArrayHasKey('description_text', $pattern);
        $this->assertArrayHasKey('type', $pattern);
        $this->assertSame('Story', $pattern['type']);
    }

    public function testGetStatsReturnsExpectedStructure(): void
    {
        $this->insertSampleData();
        $stats = $this->service->getStats();
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('types', $stats);
        $this->assertArrayHasKey('projects', $stats);
        $this->assertGreaterThan(0, $stats['total']);
    }

    public function testGetSampleRespectsLimit(): void
    {
        $this->insertSampleData();
        $sample = $this->service->getSample(2);
        $this->assertLessThanOrEqual(2, count($sample));
    }

    private function insertSampleData(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->prefix}tawos_issues (issue_key, title, description_text, type, priority, status, resolution, story_point, comment_text, project_name)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute(['PROJ-1', 'Auth Module', 'As a user, I want to log in.', 'Story', 'Major', 'Closed', 'Done', 8, 'Please prioritize the login flow.', 'TestProject']);
        $stmt->execute(['PROJ-2', 'Fix XSS Bug', 'User input not sanitized.', 'Bug', 'Critical', 'Closed', 'Fixed', 3, 'Sanitize all user input.', 'TestProject']);
        $stmt->execute(['PROJ-3', 'Add Search', 'As a user, I want search.', 'Story', 'Major', 'Open', 'Unresolved', 5, 'Use debounced input.', 'TestProject']);
        $stmt->execute(['PROJ-4', 'Setup CI', 'Automated build pipeline.', 'Task', 'Major', 'Closed', 'Done', 5, 'Use GitHub Actions.', 'DevOps']);
    }
}
