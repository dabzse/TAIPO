<?php

namespace App\Controller;

use App\Service\TeamService;
use App\Config;
use Exception;

class TeamController
{
    private TeamService $teamService;

    public function __construct(TeamService $teamService)
    {
        $this->teamService = $teamService;
    }

    private function requireInstructor(): void
    {
        if (empty($_SESSION['is_instructor'])) {
            header(Config::APP_JSON, true, 403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized. Instructor access required.']);
            exit;
        }
    }

    public function handleListTeams(): void
    {
        $this->requireInstructor();
        header(Config::APP_JSON);
        try {
            $teams = $this->teamService->listTeams();
            echo json_encode(['success' => true, 'data' => $teams]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleCreateTeam(): void
    {
        $this->requireInstructor();
        $name = $_POST['name'] ?? null;
        if (!$name) {
            header(Config::APP_JSON, true, 400);
            echo json_encode(['success' => false, 'error' => 'Team name is required.']);
            return;
        }

        try {
            $id = $this->teamService->createTeam($name);
            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'team_id' => $id]);
        } catch (Exception $e) {
            header(Config::APP_JSON, true, 500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleUpdateTeam(): void
    {
        $this->requireInstructor();
        $teamId = (int)($_POST['team_id'] ?? 0);
        $name = $_POST['name'] ?? null;

        if (!$teamId || !$name) {
            header(Config::APP_JSON, true, 400);
            echo json_encode(['success' => false, 'error' => 'Team ID and new name are required.']);
            return;
        }

        try {
            $this->teamService->updateTeam($teamId, $name);
            header(Config::APP_JSON);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            header(Config::APP_JSON, true, 500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleListRoles(): void
    {
        $this->requireInstructor();
        header(Config::APP_JSON);
        try {
            $roles = $this->teamService->listRoles();
            echo json_encode(['success' => true, 'data' => $roles]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleAssignUser(): void
    {
        $this->requireInstructor();
        $teamId = (int)($_POST['team_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $username = $_POST['username'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 0);

        if (!$teamId || (!$userId && !$username) || !$roleId) {
            header(Config::APP_JSON, true, 400);
            echo json_encode(['success' => false, 'error' => 'Team ID, User ID or Username, and Role ID are required.']);
            return;
        }

        try {
            if (!$userId && $username) {
                $userId = $this->teamService->getUserIdByUsername($username);
                if (!$userId) {
                    throw new \InvalidArgumentException("User not found with username: $username");
                }
            } elseif ($userId) {
                if (!$this->teamService->userExists($userId)) {
                    throw new \InvalidArgumentException("User not found with ID: $userId");
                }
            }

            if (!$userId) {
                throw new \InvalidArgumentException("Either User ID or Username is required");
            }

            $this->teamService->assignUserToTeam($teamId, $userId, $roleId);
            header(Config::APP_JSON);
            echo json_encode(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            header(Config::APP_JSON, true, 400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Exception $e) {
            header(Config::APP_JSON, true, 500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleListTeamUsers(): void
    {
        $this->requireInstructor();
        $teamId = (int)($_GET['team_id'] ?? 0);

        if (!$teamId) {
            header(Config::APP_JSON, true, 400);
            echo json_encode(['success' => false, 'error' => 'Team ID is required.']);
            return;
        }

        header(Config::APP_JSON);
        try {
            $users = $this->teamService->listTeamUsers($teamId);
            echo json_encode(['success' => true, 'data' => $users]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleRemoveUser(): void
    {
        $this->requireInstructor();
        $teamId = (int)($_POST['team_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);

        if (!$teamId || !$userId) {
            header(Config::APP_JSON, true, 400);
            echo json_encode(['success' => false, 'error' => 'Team ID and User ID are required.']);
            return;
        }

        try {
            $this->teamService->removeUserFromTeam($teamId, $userId);
            header(Config::APP_JSON);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            header(Config::APP_JSON, true, 500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleUpdateUserRole(): void
    {
        $this->requireInstructor();
        $teamId = (int)($_POST['team_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $roleId = (int)($_POST['role_id'] ?? 0);

        if (!$teamId || !$userId || !$roleId) {
            header(Config::APP_JSON, true, 400);
            echo json_encode(['success' => false, 'error' => 'Team ID, User ID, and Role ID are required.']);
            return;
        }

        try {
            $this->teamService->updateUserRole($teamId, $userId, $roleId);
            header(Config::APP_JSON);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            header(Config::APP_JSON, true, 500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
