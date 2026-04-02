<?php

namespace App\Service;

use PDO;
use Exception;
use App\Service\GeminiService;
use App\Service\ProjectAccessTrait;
use App\Exception\TaskNotFoundException;
use App\Exception\ProjectUnauthorizedException;
use App\Config;

class TaskAiService
{
    use ProjectAccessTrait;

    private GeminiService $geminiService;
    private TaskService $taskService;

    public function __construct(PDO $pdo, GeminiService $geminiService, TaskService $taskService)
    {
        $this->pdo = $pdo;
        $this->geminiService = $geminiService;
        $this->taskService = $taskService;
    }

    public function generateProjectTasks(string $projectName, string $rawPrompt, ?int $userId = null, bool $isInstructor = false): int
    {
        if ($userId !== null && !$this->isAuthorized($projectName, $userId, $isInstructor)) {
            throw new ProjectUnauthorizedException($projectName);
        }

        $context = $this->getProjectContextInfo($projectName);
        $this->geminiService->setContext($userId, $context['team_id'] ?? null);

        $prompt = str_replace('{{PROJECT_NAME}}', $projectName, $rawPrompt);
        $prompt .= "\n\nPlease generate a list of high-quality, relevant user stories for this project.
                    Quality Guidelines:
                    - Ensure each story provides clear, actionable value and is highly relevant to the project description.
                    - Make the stories atomic and testable. Avoid vague or overly broad tasks.
                    - Cover core functionalities first, ensuring a logical flow of dependency.

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

        return $this->taskService->replaceProjectTasks($projectName, $newTasks, $userId, $isInstructor);
    }

    public function analyzeSpec(string $spec, ?int $userId = null): array
    {
        $this->geminiService->setContext($userId);
        $prompt = "Analyze the following project specification and:
        1. Suggest a short, creative, and unique Project Name (max 5 words).
        2. Extract a list of high-quality User Stories/Tasks based on the spec.

        Quality Guidelines for Stories:
        - Ensure each story provides clear, actionable value and is strictly relevant to the provided specification.
        - Make each story atomic, testable, and sufficiently detailed. Do not create vague or overly broad tasks.
        - Ensure comprehensive coverage of the core features mentioned in the spec.

        Each task must follow the format: 'As a [user], I want to [action], so that [benefit]'.

        Specification:
        {$spec}

        Output format:
        PROJECT_NAME: [Name]
        [SPRINTBACKLOG|PRIORITY]: [Short Title] | [User Story Text]
        ...
        The PRIORITY must be an integer from 0 (None) to 3 (High).
        The Short Title must be under " . Config::getMaxTitleLength() . " characters.
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
                $taskData['status'] = TaskService::STATUS_SPRINT_BACKLOG;
                $newTasks[] = $taskData;
            }
        }

        return [
            'name' => $projectName,
            'tasks' => $newTasks
        ];
    }

    public function decomposeTask(string $description, string $projectName, ?int $parentId = null, ?int $userId = null, bool $isInstructor = false): int
    {
        if ($userId !== null && !$this->isAuthorized($projectName, $userId, $isInstructor)) {
            throw new ProjectUnauthorizedException($projectName);
        }

        $context = $this->getProjectContextInfo($projectName);
        $this->geminiService->setContext($userId, $context['team_id'] ?? null);

        $finalDescription = $this->getFinalDescription($description, $parentId);
        $contextSummary = $this->getProjectContextSummary($projectName, $parentId);

        $prompt = "You are TAIPO. You are working on the project described below.\n\n" .
                  $contextSummary . "\n\n" .
                  "Decompose this parent user story (which is NOT yet implementation stage) into 3-5 concrete, high-quality technical subtasks: '{$finalDescription}'.\n\n" .
                  "Quality Guidelines:\n" .
                  "- Ensure subtasks are highly relevant to the parent story AND consistent with overall project requirements/context.\n" .
                  "- Make each subtask atomic, tightly scoped, and directly contributing to the parent story's goal.\n" .
                  "- Use clear, professional, component-level language where appropriate.\n\n" .
                  "Each subtask must be a User Story following the standard format: 'As a [actor], I want to [action], so that [benefit]'.\n" .
                  "Format each line as: [Short Title] | [User Story Text]\n" .
                  "The Short Title must be under 40 characters.\n" .
                  "Do not include statuses.";

        $rawTasks = $this->geminiService->askTaipo($prompt);
        return $this->insertSubtasks($projectName, $parentId, $finalDescription, $rawTasks);
    }

    private function getFinalDescription(string $description, ?int $parentId): string
    {
        if ($parentId === null) {
            return $description;
        }

        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT description FROM {$prefix}tasks WHERE id = :id");
        $stmt->execute([':id' => $parentId]);
        $dbDesc = $stmt->fetchColumn();
        return $dbDesc !== false ? $dbDesc : $description;
    }

    private function insertSubtasks(string $projectName, ?int $parentId, string $parentDescription, string $rawTasks): int
    {
        $lines = explode("\n", $rawTasks);
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("INSERT INTO {$prefix}tasks (project_name, title, description, status, is_subtask, po_comments, parent_id) VALUES (?, ?, ?, '" . TaskService::STATUS_SPRINT_BACKLOG . "', 1, ?, ?)");
        $poFeedback = "TAIPO: Based on original story: \"{$parentDescription}\"";
        $count = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) { continue; }

            $parts = explode('|', $line, 2);
            $title = trim($parts[0]);
            $taskDesc = isset($parts[1]) ? trim($parts[1]) : $line;

            if (!isset($parts[1])) {
                $maxLen = Config::getMaxTitleLength();
                $title = substr($line, 0, $maxLen) . (strlen($line) > $maxLen ? '...' : '');
            }

            $stmt->execute([$projectName, $title, $taskDesc, $poFeedback, $parentId]);
            $count++;
        }
        return $count;
    }

    public function queryTask(int $taskId, string $query, ?int $userId = null, bool $isInstructor = false): string
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT description, po_comments, project_name, status, title FROM {$prefix}tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            throw new TaskNotFoundException("Task not found.");
        }

        $projectName = $task['project_name'];
        if ($userId !== null && !$this->isAuthorized($projectName, $userId, $isInstructor)) {
            throw new ProjectUnauthorizedException($projectName);
        }

        $context = $this->getProjectContextInfo($projectName);
        $this->geminiService->setContext($userId, $context['team_id'] ?? null);

        $projectContext = $this->getProjectContextSummary($projectName, $taskId);
        $taskContext = $this->formatTaskContext($task);

        $prompt = "You are TAIPO, an intelligent coding assistant for the project '{$projectName}'.\n\n" .
                  "Project Context (includes requirements and other tasks):\n{$projectContext}\n\n" .
                  "{$taskContext}\n\n" .
                  "User Question: {$query}\n\n" .
                  "Instructions:\n" .
                  "- Answer the user's question specifically related to the current task.\n" .
                  "- Use the project context to understand dependencies, shared requirements, or overall goals, but focus on the specific task.\n" .
                  "- Refrain from lengthy intros.\n" .
                  "- Provide code snippets if asked.";

        $answer = $this->geminiService->askTaipo($prompt);
        $this->persistQueryAnswer($taskId, $query, $answer, $task['po_comments'] ?? '');
        return $answer;
    }

    private function formatTaskContext(array $task): string
    {
        $taskContext = "Focus on this Specific Task:";
        $taskContext .= "\nTitle: " . ($task['title'] ?? '');
        $taskContext .= "\nDescription: " . $task['description'];
        $taskContext .= "\nStatus: " . $task['status'];
        if (!empty($task['po_comments'])) {
            $taskContext .= "\nProduct Owner Comments: " . $task['po_comments'];
        }
        return $taskContext;
    }

    private function persistQueryAnswer(int $taskId, string $query, string $answer, string $currentComments): void
    {
        $separator = $currentComments ? "\n\n---\n\n" : "";
        $newEntry = "**Q:** {$query}\n**A:** {$answer}";
        $newComments = $currentComments . $separator . $newEntry;

        $prefix = Config::getTablePrefix();
        $updateStmt = $this->pdo->prepare("UPDATE {$prefix}tasks SET po_comments = :comments WHERE id = :id");
        $updateStmt->execute([':comments' => $newComments, ':id' => $taskId]);
    }

    public function generateCode(string $description, ?int $taskId = null, ?int $userId = null, bool $isInstructor = false): string
    {
        $prefix = Config::getTablePrefix();
        $finalDescription = $description;
        $projectName = '';

        if ($taskId !== null) {
            $stmt = $this->pdo->prepare("SELECT description, project_name FROM {$prefix}tasks WHERE id = :id");
            $stmt->execute([':id' => $taskId]);
            $dbTask = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dbTask) {
                $finalDescription = $dbTask['description'];
                $projectName = $dbTask['project_name'];
            }
        }

        if ($projectName) {
            if ($userId !== null && !$this->isAuthorized($projectName, $userId, $isInstructor)) {
                throw new ProjectUnauthorizedException($projectName);
            }
            $context = $this->getProjectContextInfo($projectName);
            $this->geminiService->setContext($userId, $context['team_id'] ?? null);
        } else {
            $this->geminiService->setContext($userId);
        }

        $contextSummary = $projectName ? $this->getProjectContextSummary($projectName, $taskId) : "";
        $prompt = "You are TAIPO, an intelligent coding assistant. You are working on the project described below.\n\n" .
                  $contextSummary . "\n\n" .
                  "TASK TO IMPLEMENT: '{$finalDescription}'\n\n" .
                  "Please generate a **complete, but very concise** solution (code). The code should be **functional**, but only include the necessary imports and logic. Do not generate long explanatory comments or introduction text! Use a single Markdown code block (```language ... ```). If the language is not specified, infer it from the context or use a popular one suitable for the task.";

        $rawText = $this->geminiService->askTaipo($prompt);
        $rawText = trim($rawText);

        if ($taskId !== null) {
            $stmt = $this->pdo->prepare("UPDATE {$prefix}tasks SET generated_code = :code WHERE id = :id");
            $stmt->execute([':code' => $rawText, ':id' => $taskId]);
        }

        return $rawText;
    }

    private function getProjectContextSummary(string $projectName, ?int $excludeTaskId = null): string
    {
        $summary = "Project: {$projectName}\n\n";
        $prefix = Config::getTablePrefix();

        $reqStmt = $this->pdo->prepare("SELECT content FROM {$prefix}requirements WHERE project_name = :project_name ORDER BY created_at ASC");
        $reqStmt->execute([':project_name' => $projectName]);
        $requirements = $reqStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($requirements) {
            $summary .= "Project Requirements:\n";
            foreach ($requirements as $req) {
                $summary .= "- {$req}\n";
            }
            $summary .= "\n";
        }

        $summary .= "Current Board Status:\n";
        $stmt = $this->pdo->prepare("SELECT id, title, description, status FROM {$prefix}tasks WHERE project_name = :project_name ORDER BY status, id");
        $stmt->execute([':project_name' => $projectName]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tasks as $task) {
            if ($task['id'] == $excludeTaskId) {
                continue;
            }
            $summary .= "- [{$task['status']}] {$task['title']} | {$task['description']}\n";
        }

        return $summary;
    }

    private function parseTaskLine(string $line): ?array
    {
        $line = trim($line);
        if (empty($line)) {
            return null;
        }

        $result = null;
        if (preg_match('/^\[(SPRINTBACKLOG|IMPLEMENTATION|TESTING|REVIEW|DONE)(?:\|([0-3]))?\]:\s*(.*?)\s*\|\s*(.*)/iu', $line, $matches)) {
            $result = $this->formatTaskData($matches[3], $matches[4], $matches[1], $matches[2]);
        } elseif (preg_match('/^\[(SPRINTBACKLOG|IMPLEMENTATION|TESTING|REVIEW|DONE)(?:\|([0-3]))?\]:\s*(.*)/iu', $line, $matches)) {
            $maxLen = Config::getMaxTitleLength();
            $title = substr($matches[3], 0, $maxLen) . (strlen($matches[3]) > $maxLen ? '...' : '');
            $result = $this->formatTaskData($title, $matches[3], $matches[1], $matches[2]);
        }
        return $result;
    }

    private function formatTaskData(string $title, string $desc, string $rawStatus, string $priority): array
    {
        return [
            'title' => trim($title),
            'description' => trim($desc),
            'status' => $this->mapStatus(strtoupper($rawStatus)),
            'is_important' => $priority !== '' ? (int)$priority : 0
        ];
    }

    private function mapStatus(string $rawStatus): string
    {
        $statusMap = [
            'SPRINTBACKLOG' => TaskService::STATUS_SPRINT_BACKLOG,
            'IMPLEMENTATION' => 'IMPLEMENTATION WIP:3',
            'TESTING' => 'TESTING WIP:2',
            'REVIEW' => 'REVIEW WIP:2',
            'DONE' => 'DONE'
        ];
        return $statusMap[$rawStatus] ?? TaskService::STATUS_SPRINT_BACKLOG;
    }
}
