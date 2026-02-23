<?php

namespace App\Service;

use PDO;
use Exception;
use App\Utils;
use App\Service\GeminiService;
use App\Exception\TaskNotFoundException;
use App\Exception\WipLimitExceededException;
use App\Config;

class TaskService
{
    private PDO $pdo;
    private GeminiService $geminiService;

    public function __construct(PDO $pdo, GeminiService $geminiService)
    {
        $this->pdo = $pdo;
        $this->geminiService = $geminiService;
    }

    public function getProjects(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT project_name FROM tasks ORDER BY project_name ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTasksByProject(string $projectName): array
    {
        $stmt = $this->pdo->prepare("SELECT id, title, description, status, is_important, generated_code, is_subtask, po_comments, position FROM tasks WHERE project_name = :projectName ORDER BY position ASC, id ASC");
        $stmt->execute([':projectName' => $projectName]);
        $tasks = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tasks[] = $row;
        }
        return $tasks;
    }

    public function reorderTasks(string $projectName, string $status, array $taskIds): void
    {
        try {
            $this->pdo->beginTransaction();

            $priority = 0;
            foreach ($taskIds as $taskId) {
                // Verify task belongs to project (security check)
                $stmt = $this->pdo->prepare("UPDATE tasks SET status = :status, position = :position WHERE id = :id AND project_name = :project_name");
                $stmt->execute([
                    ':status' => $status,
                    ':position' => $priority,
                    ':id' => $taskId,
                    ':project_name' => $projectName
                ]);
                $priority++;
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function addTask(string $projectName, string $title, string $description, int $isImportant = 0): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO tasks (project_name, title, description, status, is_important) VALUES (:project_name, :title, :description, '" . self::STATUS_SPRINT_BACKLOG . "', :is_important)");
        $stmt->execute([
            ':project_name' => $projectName,
            ':title' => $title,
            ':description' => $description,
            ':is_important' => $isImportant
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteTask(int $taskId): string
    {
        // Get status before deleting
        $statusStmt = $this->pdo->prepare("SELECT status FROM tasks WHERE id = :id");
        $statusStmt->execute([':id' => $taskId]);
        $status = $statusStmt->fetchColumn();

        if ($status === false) {
            throw new TaskNotFoundException("Task not found.");
        }

        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);

        return $status;
    }

    public function toggleImportance(int $taskId, int $isImportant): void
    {
        $stmt = $this->pdo->prepare("UPDATE tasks SET is_important = :is_important WHERE id = :id");
        $stmt->execute([
            ':is_important' => $isImportant,
            ':id' => $taskId
        ]);
    }

    public function updateTask(int $taskId, string $title, string $description): void
    {
        $stmt = $this->pdo->prepare("UPDATE tasks SET title = :title, description = :description WHERE id = :id");
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':id' => $taskId
        ]);
    }

    public function updateStatus(int $taskId, string $newStatus, string $projectName): void
    {
        $wipLimit = Utils::getWIPLimit($newStatus);

        if ($wipLimit !== null) {
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE project_name = :projectName AND status = :status");
            $countStmt->execute([
                ':projectName' => $projectName,
                ':status' => $newStatus
            ]);
            $currentTaskCount = $countStmt->fetchColumn();

            if ($currentTaskCount >= $wipLimit) {
                throw new WipLimitExceededException("WIP Limit Exceeded: The limit for '{$newStatus}' column is {$wipLimit} tasks.", 403);
            }
        }

        $stmt = $this->pdo->prepare("UPDATE tasks SET status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $taskId
        ]);
    }

    public function replaceProjectTasks(string $projectName, array $newTasks): int
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE project_name = :projectName");
            $stmt->execute([':projectName' => $projectName]);

            $insertStmt = $this->pdo->prepare(
                "INSERT INTO tasks (project_name, title, description, status, is_important) VALUES (:project_name, :title, :description, :status, :is_important)"
            );

            $count = 0;
            foreach ($newTasks as $task) {
                // Fallback for replaceProjectTasks if source doesn't have title
                $tTitle = $task['title'] ?? '';
                $tDesc = $task['description'] ?? '';
                if (empty($tTitle) && !empty($tDesc)) {
                    // Simple split if only description is present
                    $lines = explode("\n", $tDesc);
                    $tTitle = trim($lines[0]);
                    $tDesc = count($lines) > 1 ? trim(implode("\n", array_slice($lines, 1))) : '';
                }

                $insertStmt->execute([
                    ':project_name' => $projectName,
                    ':title' => $tTitle,
                    ':description' => $tDesc,
                    ':status' => $task['status'],
                    ':is_important' => $task['is_important'] ?? 0
                ]);
                $count++;
            }

            $this->pdo->commit();
            return $count;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function generateProjectTasks(string $projectName, string $rawPrompt): int
    {
        $prompt = str_replace('{{PROJECT_NAME}}', $projectName, $rawPrompt);
        $prompt .= "\n\nPlease generate a list of user stories for this project.
                    Each user story must follow the standard format: 'As a [user], I want to [action], so that [benefit]'.
                    Format each line as: [STATUS|PRIORITY]: [Short Title] | [User Story Text]
                    The PRIORITY must be an integer from 0 (None) to 3 (High).
                    The Short Title must be under " . Config::getMaxTitleLength() . " characters.
                    Available statuses: SPRINTBACKLOG, IMPLEMENTATION, TESTING, REVIEW, DONE.
                    Example: [SPRINTBACKLOG|2]: Login Feature | As a user, I want to log in, so that I can access my profile.";

        $rawText = $this->geminiService->askTaipo($prompt);
        $lines = explode("\n", $rawText);
        $newTasks = [];

        foreach ($lines as $line) {
            $taskData = $this->parseTaskLine($line);
            if ($taskData) {
                // Ensure all initially generated tasks start in the SPRINT BACKLOG,
                // regardless of how the AI model labeled them.
                $taskData['status'] = 'SPRINT BACKLOG';
                $newTasks[] = $taskData;
            }
        }

        return $this->replaceProjectTasks($projectName, $newTasks);
    }

    private function parseTaskLine(string $line): ?array
    {
        $line = trim($line);
        if (empty($line)) {
            return null;
        }

        $title = '';
        $description = '';
        $status = '';
        $isImportant = 0;
        $isValid = false;

        if (preg_match('/^\[(SPRINTBACKLOG|IMPLEMENTATION|TESTING|REVIEW|DONE)(?:\|([0-3]))?\]:\s*(.*?)\s*\|\s*(.*)/iu', $line, $matches)) {
            $rawStatus = strtoupper($matches[1]);
            $isImportant = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : 0;
            $title = trim($matches[3]);
            $description = trim($matches[4]);
            $status = $this->mapStatus($rawStatus);
            $isValid = true;
        } elseif (preg_match('/^\[(SPRINTBACKLOG|IMPLEMENTATION|TESTING|REVIEW|DONE)(?:\|([0-3]))?\]:\s*(.*)/iu', $line, $matches)) {
            $rawStatus = strtoupper($matches[1]);
            $isImportant = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : 0;
            $description = trim($matches[3]);
            $maxLen = Config::getMaxTitleLength();
            $title = substr($description, 0, $maxLen) . (strlen($description) > $maxLen ? '...' : '');
            $status = $this->mapStatus($rawStatus);
            $isValid = true;
        }

        if ($isValid && !empty($description)) {
            return [
                'title' => $title,
                'description' => $description,
                'status' => $status,
                'is_important' => $isImportant
            ];
        }
        return null;
    }

    public const STATUS_SPRINT_BACKLOG = 'SPRINT BACKLOG';

    private function mapStatus(string $rawStatus): string
    {
        $statusMap = [
            'SPRINTBACKLOG' => self::STATUS_SPRINT_BACKLOG,
            'IMPLEMENTATION' => 'IMPLEMENTATION WIP:3',
            'TESTING' => 'TESTING WIP:2',
            'REVIEW' => 'REVIEW WIP:2',
            'DONE' => 'DONE'
        ];

        return $statusMap[$rawStatus] ?? self::STATUS_SPRINT_BACKLOG;
    }

    public function analyzeSpec(string $spec): array
    {
        $prompt = "Analyze the following project specification and:
        1. Suggest a short, creative, and unique Project Name (max 5 words).
        2. Extract a list of User Stories/Tasks. Each task must follow the format: 'As a [user], I want to [action], so that [benefit]'.

        Specification:
        {$spec}

        Output format:
        PROJECT_NAME: [Name]
        [SPRINTBACKLOG|PRIORITY]: [Short Title] | [User Story Text]
        ...
        The PRIORITY must be an integer from 0 (None) to 3 (High).
        The Short Title must be under {Config::getMaxTitleLength()} characters.
        ";

        $rawText = $this->geminiService->askTaipo($prompt);
        $lines = explode("\n", $rawText);
        $projectName = "New Project";
        $newTasks = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if (strpos($line, 'PROJECT_NAME:') === 0) {
                $projectName = trim(substr($line, strlen('PROJECT_NAME:')));
                // Remove quotes if present
                $projectName = trim($projectName, '"\'');
                continue;
            }

            $taskData = $this->parseTaskLine($line);
            if ($taskData) {
                // Ensure all spec-generated tasks start in the SPRINT BACKLOG
                $taskData['status'] = self::STATUS_SPRINT_BACKLOG;
                $newTasks[] = $taskData;
            }
        }

        return [
            'name' => $projectName,
            'tasks' => $newTasks
        ];
    }

    public function decomposeTask(string $description, string $projectName): int
    {
        $prompt = "Decompose this user story into 3-5 concrete technical subtasks: '{$description}'.
                    Each subtask must be a User Story following the standard format: 'As a [actor], I want to [action], so that [benefit]'.
                    Format each line as: [Short Title] | [User Story Text]
                    The Short Title must be under 40 characters.
                    Do not include statuses.";

        $rawTasks = $this->geminiService->askTaipo($prompt);
        $lines = explode("\n", $rawTasks);
        $count = 0;

        $stmt = $this->pdo->prepare("INSERT INTO tasks (project_name, title, description, status, is_subtask, po_comments) VALUES (?, ?, ?, '" . self::STATUS_SPRINT_BACKLOG . "', 1, ?)");

        $poFeedback = "TAIPO: Based on original story: \"{$description}\"";

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line) {
                $title = '';
                $taskDesc = $line;

                if (strpos($line, '|') !== false) {
                    $parts = explode('|', $line, 2);
                    $title = trim($parts[0]);
                    $taskDesc = trim($parts[1]);
                } else {
                    $maxLen = Config::getMaxTitleLength();
                    $title = substr($line, 0, $maxLen) . (strlen($line) > $maxLen ? '...' : '');
                }

                $stmt->execute([$projectName, $title, $taskDesc, $poFeedback]);
                $count++;
            }
        }
        return $count;
    }

    public function queryTask(int $taskId, string $query): string
    {
        // 1. Fetch current task details INCLUDING project_name
        $stmt = $this->pdo->prepare("SELECT description, po_comments, project_name, status FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            throw new TaskNotFoundException("Task not found.");
        }

        $projectName = $task['project_name'];

        // 2. Fetch all OTHER tasks in the same project to provide context
        $stmtAll = $this->pdo->prepare("SELECT id, description, status FROM tasks WHERE project_name = :project_name ORDER BY status, id");
        $stmtAll->execute([':project_name' => $projectName]);
        $allTasks = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        // 3. Construct Project Context String
        $projectContext = "Project Name: " . $projectName . "\n";
        $projectContext .= "Other Tasks in Project:\n";
        foreach ($allTasks as $t) {
            // Mark the current task distinctly
            $marker = ($t['id'] == $taskId) ? " (CURRENT TASK)" : "";
            $projectContext .= "- [{$t['status']}] {$t['description']}{$marker}\n";
        }

        // 4. Construct Task Specific Context
        $taskContext = "Current Task Description: " . $task['description'];
        $taskContext .= "\nCurrent Task Status: " . $task['status'];
        if (!empty($task['po_comments'])) {
            $taskContext .= "\nProduct Owner Comments: " . $task['po_comments'];
        }

        // 5. Build Final Prompt
        $prompt = "You are TAIPO, an intelligent coding assistant for the project '{$projectName}'.

        Global Project Context:
        {$projectContext}

        Focus on this Specific Task:
        {$taskContext}

        User Question:
        {$query}

        Instructions:
        - Answer the user's question specifically related to the current task.
        - Use the global project context to understand dependencies or overall goals, but focus on the specific task.
        - Refrain from lengthy intros.
        - Provide code snippets if asked.";

        $answer = $this->geminiService->askTaipo($prompt);

        // Persist the Q&A to po_comments
        $currentComments = $task['po_comments'] ?? '';
        $separator = $currentComments ? "\n\n---\n\n" : "";
        $newEntry = "**Q:** {$query}\n**A:** {$answer}";
        $newComments = $currentComments . $separator . $newEntry;

        $updateStmt = $this->pdo->prepare("UPDATE tasks SET po_comments = :comments WHERE id = :id");
        $updateStmt->execute([':comments' => $newComments, ':id' => $taskId]);

        return $answer;
    }

    public function generateCode(string $description): string
    {
        $prompt = "Generate a **complete, but very concise** solution (code) to the task: '{$description}'. The code should be **functional**, but only include the necessary imports and logic. Do not generate long explanatory comments or introduction text! Use a single Markdown code block (```language ... ```). If the language is not specified, infer it from the context or use a popular one suitable for the task.";

        $rawText = $this->geminiService->askTaipo($prompt);
        return Utils::formatCodeBlocks($rawText);
    }
}
