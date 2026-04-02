<?php

namespace App\Service;

use App\Config;
use PDO;

trait ProjectAccessTrait
{
    protected $pdo;

    public function isAuthorized(string $projectName, int $userId, bool $isInstructor): bool
    {
        if ($isInstructor) {
            return true;
        }

        $prefix = Config::getTablePrefix();
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM {$prefix}projects p
            LEFT JOIN {$prefix}team_users tu ON p.team_id = tu.team_id
            WHERE p.name = :name AND (p.user_id = :user_id OR tu.user_id = :user_id)
        ");
        $stmt->execute([':name' => $projectName, ':user_id' => $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    protected function getProjectContextInfo(string $projectName): array
    {
        try {
            $prefix = Config::getTablePrefix();
            $stmt = $this->pdo->prepare("SELECT team_id, user_id FROM {$prefix}projects WHERE name = :name");
            $stmt->execute([':name' => $projectName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
