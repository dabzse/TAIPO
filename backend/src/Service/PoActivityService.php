<?php

namespace App\Service;

use PDO;
use Exception;
use App\Config;
use App\Prompts;
use App\Service\GeminiService;
use App\Service\TawosService;

class PoActivityService
{
    private PDO $pdo;
    private GeminiService $geminiService;
    private HistoryService $historyService;
    private TaskAiService $taskAiService;
    private ?TawosService $tawosService;
    private ?int $currentUserId;
    private string $dbType;

    public function __construct(PDO $pdo, GeminiService $geminiService, TaskAiService $taskAiService, HistoryService $historyService, string $dbType = 'sqlite', ?TawosService $tawosService = null)
    {
        $this->pdo = $pdo;
        $this->geminiService = $geminiService;
        $this->historyService = $historyService;
        $this->taskAiService = $taskAiService;
        $this->dbType = $dbType;
        $this->tawosService = $tawosService;

        // Apply Timezone from Config
        $timezone = Config::getSimTimezone();
        date_default_timezone_set($timezone);
    }

    public function tick(string $projectName, ?int $userId = null): void
    {
        if (empty($projectName)) {
            return;
        }

        $this->currentUserId = $userId;

        // 1. Fetch project simulation metadata
        $project = $this->getProjectData($projectName);
        if (!$project || !$project['is_active']) {
            return;
        }

        // 2. Check if we are in "Working Hours"
        if (!$this->isWorkingHours()) {
            return;
        }

        // 3. Process Autonomous Actions
        $this->processAcceptance($project);
        $this->processChangeRequests($project);
        $this->processComments($project);
    }

    private function isWorkingHours(): bool
    {
        $hour = (int)date('H');
        $dayOfWeek = (int)date('N'); // 1 (Mon) to 7 (Sun)

        $minHour = Config::getSimMinActiveHour();
        $maxHour = Config::getSimMaxActiveHour();

        // Mon-Fri, within configured range
        return ($dayOfWeek >= 1 && $dayOfWeek <= 5) && ($hour >= $minHour && $hour < $maxHour);
    }

    private function getProjectData(string $projectName): ?array
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT id, name, team_id, is_active, last_comment_at, next_comment_at, last_cr_at, next_cr_at FROM {$prefix}projects WHERE name = :name");
        $stmt->execute([':name' => $projectName]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function processComments(array $project): void
    {
        $now = time();
        $nextAt = $project['next_comment_at'] ? strtotime($project['next_comment_at']) : null;

        // If next_comment_at is not set, schedule it now
        if (!$nextAt) {
            $this->scheduleNextActivity($project['id'], $project['team_id'], 'comment');
            return;
        }

        if ($now < $nextAt) {
            return;
        }

        // Trigger Comment
        try {
            $task = $this->getRandomTaskForComment($project['name']);
            if (!$task) {
                // If no task, we still mark as "done" for this cycle but maybe schedule sooner?
                // For now, just reschedule normally.
                $this->scheduleNextActivity($project['id'], $project['team_id'], 'comment');
                return;
            }

            $context = $this->getProjectSummary($project['name']);

            // Enrich prompt with real TAWOS data
            $tawosComment = $this->tawosService?->getRandomComment('Story');
            $prompt = Prompts::getPoCheckInPrompt($task['title'], $task['description'], $context, $tawosComment);

            $this->geminiService->setContext($this->currentUserId, $project['team_id']);
            $comment = $this->geminiService->askTaipo($prompt);

            $this->addPoComment($task['id'], $comment);

            $this->historyService->setContext(null, $project['team_id']);
            $this->historyService->log($task['id'], 'ai_comment', null, $comment);

            // Update last_comment_at and schedule NEXT
            $this->updateProjectTimestamp($project['id'], 'last_comment_at');
            $this->scheduleNextActivity($project['id'], $project['team_id'], 'comment');
        } catch (Exception $e) {
            error_log("PoActivityService error (Comment): " . $e->getMessage());
        }
    }

    private function processChangeRequests(array $project): void
    {
        $now = time();
        $nextAt = $project['next_cr_at'] ? strtotime($project['next_cr_at']) : null;

        if (!$nextAt) {
            $this->scheduleNextActivity($project['id'], $project['team_id'], 'cr');
            return;
        }

        if ($now < $nextAt) {
            return;
        }

        // Trigger Change Request
        try {
            $requirements = $this->getRequirements($project['name']);
            $boardStatus = $this->getProjectSummary($project['name']);

            // Enrich prompt with real TAWOS change pattern
            $tawosPattern = $this->tawosService?->getSampleChangePattern();
            $prompt = Prompts::getChangeRequestPrompt($project['name'], $requirements, $boardStatus, $tawosPattern);

            $this->geminiService->setContext($this->currentUserId, $project['team_id']);
            $rawCr = $this->geminiService->askTaipo($prompt);

            $crData = $this->parseCrResponse($rawCr);
            if ($crData) {
                $taskId = $this->addCrTask($project['name'], $crData['title'], $crData['story']);

                $this->historyService->setContext(null, $project['team_id']);
                $this->historyService->log($taskId, 'ai_change_request', null, $crData['title'], $crData['story']);

                $this->updateProjectTimestamp($project['id'], 'last_cr_at');
                $this->scheduleNextActivity($project['id'], $project['team_id'], 'cr');
            }
        } catch (Exception $e) {
            error_log("PoActivityService error (CR): " . $e->getMessage());
        }
    }

    private function scheduleNextActivity(int $projectId, ?int $teamId, string $type): void
    {
        $prefix = Config::getTablePrefix();
        $column = ($type === 'comment') ? 'next_comment_at' : 'next_cr_at';

        $min = null;
        $max = null;

        if ($teamId) {
            $stmt = $this->pdo->prepare("SELECT sim_min_feedback_sec, sim_max_feedback_sec, sim_min_cr_sec, sim_max_cr_sec FROM {$prefix}teams WHERE id = :id");
            $stmt->execute([':id' => $teamId]);
            $teamSettings = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($teamSettings) {
                if ($type === 'comment') {
                    $min = $teamSettings['sim_min_feedback_sec'];
                    $max = $teamSettings['sim_max_feedback_sec'];
                } else {
                    $min = $teamSettings['sim_min_cr_sec'];
                    $max = $teamSettings['sim_max_cr_sec'];
                }
            }
        }

        if ($type === 'comment') {
            $min = $min ?? Config::getSimMinFeedbackInterval();
            $max = $max ?? Config::getSimMaxFeedbackInterval();
        } else {
            $min = $min ?? Config::getSimMinCrInterval();
            $max = $max ?? Config::getSimMaxCrInterval();
        }

        $interval = rand($min, $max);
        $nextAt = date('Y-m-d H:i:s', time() + $interval);

        $stmt = $this->pdo->prepare("UPDATE {$prefix}projects SET $column = :next_at WHERE id = :id");
        $stmt->execute([':next_at' => $nextAt, ':id' => $projectId]);
    }

    private function getRandomTaskForComment(string $projectName): ?array
    {
        $prefix = Config::getTablePrefix();
        $randomFunc = ($this->dbType === 'mysql') ? 'RAND()' : 'RANDOM()';

        // Prefer tasks in WIP stages
        $query = "SELECT id, title, description FROM {$prefix}tasks
                  WHERE project_name = :name
                  AND status IN ('IMPLEMENTATION WIP:3', 'TESTING WIP:2', 'REVIEW WIP:2')
                  ORDER BY {$randomFunc} LIMIT 1";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':name' => $projectName]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            // Fallback to Backlog
            $query = "SELECT id, title, description FROM {$prefix}tasks
                      WHERE project_name = :name
                      AND status = 'SPRINT BACKLOG'
                      ORDER BY {$randomFunc} LIMIT 1";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':name' => $projectName]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $task ?: null;
    }

    private function getProjectSummary(string $projectName): string
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT title, status FROM {$prefix}tasks WHERE project_name = :name LIMIT 15");
        $stmt->execute([':name' => $projectName]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = "Active tasks summary:\n";
        foreach ($tasks as $t) {
            $summary .= "- [{$t['status']}] {$t['title']}\n";
        }
        return $summary;
    }

    private function getRequirements(string $projectName): string
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT content FROM {$prefix}requirements WHERE project_name = :name ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([':name' => $projectName]);
        return $stmt->fetchColumn() ?: "No requirements specified.";
    }

    private function addPoComment(int $taskId, string $comment): void
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT po_comments FROM {$prefix}tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $current = $stmt->fetchColumn() ?: "";

        $separator = $current ? "\n\n---\n\n" : "";
        $newComments = $current . $separator . "**TAIPO Check-in:**\n" . $comment;

        $update = $this->pdo->prepare("UPDATE {$prefix}tasks SET po_comments = :comments WHERE id = :id");
        $update->execute([':comments' => $newComments, ':id' => $taskId]);
    }

    private function addCrTask(string $projectName, string $title, string $description): int
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("INSERT INTO {$prefix}tasks (project_name, title, description, status, is_important, po_comments)
                                    VALUES (:name, :title, :desc, 'SPRINT BACKLOG', 1, 'TAIPO: Automated Change Request generated based on project dynamics.')");
        $stmt->execute([
            ':name' => $projectName,
            ':title' => $title,
            ':desc' => $description
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function updateProjectTimestamp(int $projectId, string $column): void
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("UPDATE {$prefix}projects SET $column = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':id' => $projectId]);
    }

    private function processAcceptance(array $project): void
    {
        // Acceptances are also scheduled to avoid doing it every tick
        // We reuse the comment interval logic for now or add a new one if needed.
        // For simplicity, we just check if any task is in REVIEW state and do it occasionally.

        $now = time();
        $nextAt = $project['next_comment_at'] ? strtotime($project['next_comment_at']) : null;

        // Only attempt review when a comment is also scheduled (roughly same frequency)
        if ($now < $nextAt) {
            return;
        }

        try {
            $task = $this->getReviewCandidateTask($project['name']);
            if (!$task) {
                return;
            }

            // Perform automated review
            $this->taskAiService->reviewTaskForAcceptance($task['id'], $this->currentUserId);
        } catch (Exception $e) {
            error_log("PoActivityService error (Acceptance): " . $e->getMessage());
        }
    }

    private function getReviewCandidateTask(string $projectName): ?array
    {
        $prefix = Config::getTablePrefix();
        $randomFunc = ($this->dbType === 'mysql') ? 'RAND()' : 'RANDOM()';

        $query = "SELECT id FROM {$prefix}tasks
                  WHERE project_name = :name
                  AND status = 'REVIEW WIP:2'
                  ORDER BY {$randomFunc} LIMIT 1";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':name' => $projectName]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function parseCrResponse(string $raw): ?array
    {
        $title = "";
        $story = "";

        if (preg_match('/\[TITLE\]:(.*)/i', $raw, $m)) {
            $title = trim($m[1]);
        }
        if (preg_match('/\[STORY\]:(.*)/i', $raw, $m)) {
            $story = trim($m[1]);
        }

        if ($title && $story) {
            return ['title' => $title, 'story' => $story];
        }

        return null;
    }
}
