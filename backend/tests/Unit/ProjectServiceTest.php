<?php

namespace Tests\Unit;

use PDO;
use App\Configuration\DatabaseConfig;
use App\Exception\ProjectAlreadyExistsException;
use App\Exception\ProjectNotFoundException;
use App\Service\ProjectService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ProjectService: creation, renaming, deletion,
 * duplicate name handling, and team assignment.
 */
class ProjectServiceTest extends TestCase
{
    private PDO $pdo;
    private ProjectService $projectService;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['TABLE_PREFIX'] = '';

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $config = DatabaseConfig::get();
        foreach ($config['pragma'] as $key => $value) {
            $this->pdo->exec("PRAGMA $key = $value");
        }
        foreach ($config['schema'] as $sql) {
            $this->pdo->exec($sql);
        }

        // Seed a test user
        $this->pdo->exec("INSERT INTO users (username, password_hash) VALUES ('admin', 'hash')");

        $this->projectService = new ProjectService($this->pdo);
    }

    public function testCreateProjectReturnsPositiveId(): void
    {
        $name = 'My Project';
        $id = $this->projectService->createProject($name, 1);
        $this->assertGreaterThan(0, $id);
        $this->assertSame('My Project', $name);
    }

    public function testCreateDuplicateProjectAutoRenames(): void
    {
        $name1 = 'DupeProject';
        $this->projectService->createProject($name1, 1);

        $name2 = 'DupeProject';
        $this->projectService->createProject($name2, 1);

        // Second project should have been renamed (appended date/counter)
        $this->assertNotSame('DupeProject', $name2);
        $this->assertStringContainsString('DupeProject', $name2);
    }

    public function testGetAllProjectsForInstructor(): void
    {
        $n1 = 'P1';
        $this->projectService->createProject($n1, 1);
        $n2 = 'P2';
        $this->projectService->createProject($n2, 1);

        // Instructor sees all projects
        $projects = $this->projectService->getAllProjects(1, true);
        $this->assertCount(2, $projects);
        $this->assertEquals(1, $projects[0]['is_active']);
    }

    public function testToggleProjectActivity(): void
    {
        $name = 'ToggleMe';
        $id = $this->projectService->createProject($name, 1);

        // Default is active (1)
        $projects = $this->projectService->getAllProjects(1, true);
        $this->assertEquals(1, $projects[0]['is_active']);

        // Toggle to inactive (0)
        $this->projectService->toggleProjectActivity($id, false);
        $projects = $this->projectService->getAllProjects(1, true);
        $this->assertEquals(0, $projects[0]['is_active']);

        // Toggle back to active (1)
        $this->projectService->toggleProjectActivity($id, true);
        $projects = $this->projectService->getAllProjects(1, true);
        $this->assertEquals(1, $projects[0]['is_active']);
    }

    public function testGetAllProjectsForNonOwner(): void
    {
        $n1 = 'P1';
        $this->projectService->createProject($n1, 1);

        // User 2 has no team and is not the owner
        $projects = $this->projectService->getAllProjects(2, false);
        $this->assertCount(0, $projects);
    }

    public function testRenameProjectSucceeds(): void
    {
        $name = 'OldName';
        $id = $this->projectService->createProject($name, 1);

        $this->projectService->renameProject($id, 'NewName');

        $projects = $this->projectService->getAllProjects(1, true);
        $names = array_column($projects, 'name');
        $this->assertContains('NewName', $names);
        $this->assertNotContains('OldName', $names);
    }

    public function testRenameProjectThrowsOnDuplicate(): void
    {
        $n1 = 'Alpha';
        $id1 = $this->projectService->createProject($n1, 1);
        $n2 = 'Beta';
        $this->projectService->createProject($n2, 1);

        $this->expectException(ProjectAlreadyExistsException::class);
        $this->projectService->renameProject($id1, 'Beta');
    }

    public function testDeleteProjectRemovesProjectAndTasks(): void
    {
        $name = 'ToDelete';
        $id = $this->projectService->createProject($name, 1);

        // Add a task to the project
        $stmt = $this->pdo->prepare("INSERT INTO tasks (project_name, title, description, status) VALUES (?, 'T', 'D', 'SPRINT BACKLOG')");
        $stmt->execute([$name]);

        // Delete
        $this->projectService->deleteProject($id);

        // Verify project is gone
        $projects = $this->projectService->getAllProjects(1, true);
        $this->assertCount(0, $projects);

        // Verify tasks are also deleted
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_name = ?");
        $stmt->execute([$name]);
        $this->assertEquals(0, $stmt->fetchColumn());
    }

    public function testDeleteProjectThrowsWhenNotFound(): void
    {
        $this->expectException(ProjectNotFoundException::class);
        $this->projectService->deleteProject(9999);
    }

    public function testSetProjectTeamUpdatesTeamId(): void
    {
        $name = 'TeamProject';
        $id = $this->projectService->createProject($name, 1);

        // Create a team
        $this->pdo->exec("INSERT INTO teams (name) VALUES ('Team Alpha')");
        $teamId = (int) $this->pdo->lastInsertId();

        $this->projectService->setProjectTeam($id, $teamId);

        $projects = $this->projectService->getAllProjects(1, true);
        $project = array_filter($projects, fn($p) => $p['name'] === 'TeamProject');
        $project = array_values($project)[0];
        $this->assertEquals($teamId, $project['team_id']);
    }
}
