<?php

namespace App\Service;

use PDO;
use App\Config;
use Exception;

class TeamService
{
    private PDO $pdo;
    private string $prefix;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->prefix = Config::getTablePrefix();
    }

    public function createTeam(string $name): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->prefix}teams (name) VALUES (:name)");
        $stmt->execute([':name' => $name]);
        return (int)$this->pdo->lastInsertId();
    }

    public function listTeams(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, created_at,
                   sim_min_feedback_sec, sim_max_feedback_sec,
                   sim_min_cr_sec, sim_max_cr_sec
            FROM {$this->prefix}teams
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateTeam(int $teamId, string $name, array $settings = []): void
    {
        $updates = ["name = :name"];
        $params = [':name' => $name, ':id' => $teamId];

        $allowedSettings = ['sim_min_feedback_sec', 'sim_max_feedback_sec', 'sim_min_cr_sec', 'sim_max_cr_sec'];
        foreach ($allowedSettings as $key) {
            if (array_key_exists($key, $settings)) {
                $updates[] = "$key = :$key";
                $params[":$key"] = $settings[$key] === '' ? null : (int)$settings[$key];
            }
        }

        $setClause = implode(", ", $updates);
        $stmt = $this->pdo->prepare("UPDATE {$this->prefix}teams SET $setClause WHERE id = :id");
        $stmt->execute($params);
    }

    public function deleteTeam(int $teamId): void
    {
        // First delete team users relations
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}team_users WHERE team_id = :team_id");
        $stmt->execute([':team_id' => $teamId]);

        // Delete the team
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}teams WHERE id = :id");
        $stmt->execute([':id' => $teamId]);
    }

    public function listRoles(): array
    {
        $stmt = $this->pdo->prepare("SELECT id, name, description FROM {$this->prefix}roles ORDER BY id ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignUserToTeam(int $teamId, int $userId, int $roleId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->prefix}team_users (team_id, user_id, role_id)
            VALUES (:team_id, :user_id, :role_id)
        ");
        $stmt->execute([
            ':team_id' => $teamId,
            ':user_id' => $userId,
            ':role_id' => $roleId
        ]);
    }

    public function removeUserFromTeam(int $teamId, int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->prefix}team_users WHERE team_id = :team_id AND user_id = :user_id");
        $stmt->execute([':team_id' => $teamId, ':user_id' => $userId]);
    }

    public function updateUserRole(int $teamId, int $userId, int $roleId): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->prefix}team_users SET role_id = :role_id WHERE team_id = :team_id AND user_id = :user_id");
        $stmt->execute([':team_id' => $teamId, ':user_id' => $userId, ':role_id' => $roleId]);
    }

    public function listTeamUsers(int $teamId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id as user_id, u.username, r.id as role_id, r.name as role_name
            FROM {$this->prefix}team_users tu
            JOIN {$this->prefix}users u ON tu.user_id = u.id
            JOIN {$this->prefix}roles r ON tu.role_id = r.id
            WHERE tu.team_id = :team_id
        ");
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserIdByUsername(string $username): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM {$this->prefix}users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    public function userExists(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->prefix}users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function listUserTeams(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name, tu.role_id, r.name as role_name
            FROM {$this->prefix}teams t
            JOIN {$this->prefix}team_users tu ON t.id = tu.team_id
            JOIN {$this->prefix}roles r ON tu.role_id = r.id
            WHERE tu.user_id = :user_id
            ORDER BY t.name ASC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
