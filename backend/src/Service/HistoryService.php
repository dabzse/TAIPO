<?php

namespace App\Service;

use PDO;
use App\Config;

/**
 * Service for tracking task history and audit logs.
 * Supports Story 5.1: Traceability and Audit Trail.
 */
class HistoryService
{
    private PDO $pdo;
    private ?int $currentUserId = null;
    private ?int $currentTeamId = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Set the current context for attribution.
     */
    public function setContext(?int $userId, ?int $teamId = null): void
    {
        $this->currentUserId = $userId;
        $this->currentTeamId = $teamId;
    }

    /**
     * Log a task action.
     *
     * @param int $taskId The ID of the task
     * @param string $action The action performed (e.g., 'status_change', 'edit_content', 'ai_comment')
     * @param string|null $oldValue The value before the change
     * @param string|null $newValue The value after the change
     * @param string|null $details Additional JSON or text details
     * @return int The ID of the log entry
     */
    public function log(int $taskId, string $action, ?string $oldValue = null, ?string $newValue = null, ?string $details = null): int
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("INSERT INTO {$prefix}task_history
            (task_id, user_id, team_id, action, old_value, new_value, details)
            VALUES (:task_id, :user_id, :team_id, :action, :old_value, :new_value, :details)");

        $stmt->execute([
            ':task_id' => $taskId,
            ':user_id' => $this->currentUserId,
            ':team_id' => $this->currentTeamId,
            ':action' => $action,
            ':old_value' => $oldValue,
            ':new_value' => $newValue,
            ':details' => $details
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Retrieve history for a specific task.
     */
    public function getTaskHistory(int $taskId): array
    {
        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("SELECT h.*, u.username
            FROM {$prefix}task_history h
            LEFT JOIN {$prefix}users u ON h.user_id = u.id
            WHERE h.task_id = :task_id
            ORDER BY h.created_at DESC");

        $stmt->execute([':task_id' => $taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
