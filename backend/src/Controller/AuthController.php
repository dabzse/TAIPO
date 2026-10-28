<?php

namespace App\Controller;

use PDO;
use Exception;
use App\Config;
use App\Configuration\GeminiConfig;
use App\Service\SecurityService;

class AuthController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function handleRegister()
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $error = null;
        if (!Config::isRegistrationEnabled()) {
            header(Config::APP_JSON, true, 403);
            $error = 'Registration is currently disabled.';
        } elseif (empty($username) || empty($password)) {
            http_response_code(400);
            $error = 'Username and password are required.';
        } elseif (strlen($username) < Config::getMinUsernameLength() || strlen($username) > 16 || !preg_match('/^\w+$/', $username)) {
            http_response_code(400);
            $error = 'Invalid username format (' . Config::getMinUsernameLength() . '-16 letters/numbers/underscore).';
        } elseif (strlen($password) < Config::getMinPasswordLength() || strlen($password) > 31) {
            http_response_code(400);
            $error = 'Password must be between ' . Config::getMinPasswordLength() . ' and 31 characters long.';
        }

        if ($error !== null) {
            echo json_encode(['success' => false, 'error' => $error]);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            $prefix = Config::getTablePrefix();
            $stmt = $this->pdo->prepare("SELECT id FROM {$prefix}users WHERE username = :username");
            $stmt->execute([':username' => $username]);

            if ($stmt->fetch()) {
                $this->pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Username already exists.']);
                return;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("INSERT INTO {$prefix}users (username, password_hash) VALUES (:username, :hash)");
            $stmt->execute([':username' => $username, ':hash' => $hash]);
            $userId = (int) $this->pdo->lastInsertId();

            $countStmt = $this->pdo->query("SELECT COUNT(*) FROM {$prefix}users");
            if ($countStmt->fetchColumn() == 1) {
                $this->pdo->exec("UPDATE {$prefix}projects SET user_id = $userId WHERE user_id IS NULL");
            }

            $this->pdo->commit();

            $isInstructor = ($userId === 1);
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['is_instructor'] = $isInstructor;

            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'user' => [
                'id' => $userId,
                'username' => $username,
                'is_instructor' => $isInstructor,
                'must_change_password' => false,
                'last_active_project' => null
            ]]);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            http_response_code(500);
            error_log("Registration error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Server error during registration."]);
        }
    }

    public function handleLogin()
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username and password are required.']);
            return;
        }

        try {
            $prefix = Config::getTablePrefix();
            $stmt = $this->pdo->prepare("SELECT id, username, password_hash, is_instructor, last_active_project, must_change_password FROM {$prefix}users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_instructor'] = (bool)$user['is_instructor'];

                header(Config::APP_JSON);
                echo json_encode(['success' => true, 'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'is_instructor' => (bool)$user['is_instructor'],
                    'must_change_password' => (bool)($user['must_change_password'] ?? false),
                    'last_active_project' => $user['last_active_project']
                ]]);
            } else {
                http_response_code(401);
                error_log("Login failed for username: " . $username); // Security info log, wait 2 sec maybe for timing attacks but it's local
                echo json_encode(['success' => false, 'error' => 'Invalid username or password.']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Login error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => "Server error during login."]);
        }
    }

    public function handleLogout()
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();

        header(Config::APP_JSON);
        echo json_encode(['success' => true]);
    }

    public function handleCheckAuth()
    {
        header(Config::APP_JSON);
        $config = [
            'minUsernameLength' => Config::getMinUsernameLength(),
            'minPasswordLength' => Config::getMinPasswordLength()
        ];
        if (isset($_SESSION['user_id'])) {
            // Also refresh is_instructor from DB just in case it changed
            $prefix = Config::getTablePrefix();
            $stmt = $this->pdo->prepare("SELECT is_instructor, must_change_password, last_active_project FROM {$prefix}users WHERE id = :id");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            $isInstructor = (bool)($userData['is_instructor'] ?? false);
            $mustChangePassword = (bool)($userData['must_change_password'] ?? false);
            $_SESSION['is_instructor'] = $isInstructor;

            echo json_encode([
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'is_instructor' => $isInstructor,
                    'must_change_password' => $mustChangePassword,
                    'last_active_project' => $userData['last_active_project'] ?? null
                ],
                'config' => $config
            ]);
        } else {
            echo json_encode(['success' => true, 'authenticated' => false, 'config' => $config]);
        }
    }

    public function handleUpdateActiveProject()
    {
        $userId = $_SESSION['user_id'] ?? null;
        $projectName = trim($_POST['project_name'] ?? '');

        if (!$userId) {
            header(Config::APP_JSON, true, 401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        try {
            $prefix = Config::getTablePrefix();
            $stmt = $this->pdo->prepare("UPDATE {$prefix}users SET last_active_project = :project WHERE id = :id");
            $stmt->execute([':project' => $projectName ?: null, ':id' => $userId]);

            header(Config::APP_JSON);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function handleGitHubLogin()
    {
        // Directly combine URL with environment variable, without variables
        header("Location: https://github.com/login/oauth/authorize?client_id=" .
            ($_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN')) .
            "&redirect_uri=http://localhost:8000/?action=github_callback&scope=repo,user");
        exit;
    }

    public function handleGitHubCallback()
    {
        // 1. Code extraction from URL
        $code = $_GET['code'] ?? null;
        if (!$code) {
            header("Location: http://localhost:5173/?error=no_code");
            exit;
        }

        // 2. Token request from GitHub (with CURL)
        $ch = curl_init("https://github.com/login/oauth/access_token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id'     => $_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN'),
            'client_secret' => $_ENV['GITHUB_CLIENT_SECRET'] ?? getenv('GITHUB_CLIENT_SECRET'),
            'code'          => $code,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $authData = json_decode(curl_exec($ch), true);
        $accessToken = $authData['access_token'] ?? null;

        if (!$accessToken) {
            header("Location: http://localhost:5173/?error=auth_failed");
            exit;
        }

        // 3. Get user data with the Token
        curl_setopt($ch, CURLOPT_URL, "https://api.github.com/user");
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: AI-Kanban-App'
        ]);

        $userData = json_decode(curl_exec($ch), true);

        // 4. Login into Session, if GitHub login name is available
        if (isset($userData['login'])) {
            // Prevent session fixation attack
            session_regenerate_id(true);

            $_SESSION['user_id'] = 999; // Temporary ID until saved to DB
            $_SESSION['username'] = $userData['login'];
            $_SESSION['is_instructor'] = false;

            // 5. REDIRECT TO FRONTEND
            header("Location: http://localhost:5173");
            exit;
        }

        header("Location: http://localhost:5173/?error=user_data_failed");
        exit;
    }

    public function handleGetApiKeyStatus(): void
    {
        header(Config::APP_JSON);
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        // 1. Student Session Key
        $sessionKey = $_SESSION['user_gemini_api_key'] ?? null;
        $hasSessionKey = $sessionKey && SecurityService::isValidGeminiKey((string)$sessionKey);
        $sessionKeySource = $_SESSION['user_gemini_api_key_source'] ?? null;

        // 2. Student Database Key
        $userKeyPlain = $this->fetchSavedKey('users', (int)$userId);
        $hasSavedUserKey = ($userKeyPlain !== null);

        // 3. Team Session Key & Database Key
        $team = $this->resolveUserTeam((int)$userId);
        $teamId = $team['id'] ?? null;
        $teamName = $team['name'] ?? null;
        $teamSessionKey = $_SESSION['team_gemini_api_key'] ?? null;
        $hasTeamSessionKey = $teamSessionKey && SecurityService::isValidGeminiKey((string)$teamSessionKey);
        $teamKeyPlain = $teamId ? $this->fetchSavedKey('teams', $teamId) : null;
        $hasTeamSavedKey = ($teamKeyPlain !== null);

        // 4. University Key
        $envKey = GeminiConfig::getGeminiApiKey();
        $hasUniversityKey = SecurityService::isValidGeminiKey($envKey);

        // Determine active source and key
        [$activeSource, $activeKey] = $this->determineActiveKey(
            $hasSessionKey ? (string)$sessionKey : null,
            $sessionKeySource,
            $userKeyPlain,
            $hasTeamSessionKey ? (string)$teamSessionKey : null,
            $teamKeyPlain,
            $hasUniversityKey ? $envKey : null
        );

        $savedUserKeyHash = $userKeyPlain ? hash('sha256', $userKeyPlain) : null;
        $savedTeamKeyHash = $teamKeyPlain ? hash('sha256', $teamKeyPlain) : null;

        // GitHub session credentials status
        $githubToken = $_SESSION['github_token'] ?? null;
        $githubUsername = $_SESSION['github_username'] ?? null;
        $githubRepo = $_SESSION['github_repo'] ?? null;
        $hasSessionGitHub = !empty($githubToken);

        $maskedGithubToken = null;
        if ($githubToken && is_string($githubToken)) {
            $len = strlen($githubToken);
            if ($len > 8) {
                $maskedGithubToken = substr($githubToken, 0, 4) . '...' . substr($githubToken, -4);
            } else {
                $maskedGithubToken = '***';
            }
        }

        echo json_encode([
            'success' => true,
            'active_source' => $activeSource,
            'masked_key' => SecurityService::maskApiKey($activeKey),
            'has_session_key' => $hasSessionKey,
            'session_key_source' => $sessionKeySource,
            'has_saved_user_key' => $hasSavedUserKey,
            'saved_user_key_hash' => $savedUserKeyHash,
            'has_team_session_key' => $hasTeamSessionKey,
            'has_team_saved_key' => $hasTeamSavedKey,
            'saved_team_key_hash' => $savedTeamKeyHash,
            'has_university_key' => $hasUniversityKey,
            'team_id' => $teamId,
            'team_name' => $teamName,
            'github_status' => [
                'has_session_github' => $hasSessionGitHub,
                'github_username' => $githubUsername,
                'github_repo' => $githubRepo,
                'masked_github_token' => $maskedGithubToken
            ]
        ]);
    }

    private function determineActiveKey(
        ?string $sessionKey,
        ?string $sessionSource,
        ?string $userKey,
        ?string $teamSessionKey,
        ?string $teamKey,
        ?string $envKey
    ): array {
        $source = 'none';
        $key = null;

        if ($sessionKey) {
            $source = ($sessionSource === 'usb') ? 'student_usb' : 'student_session';
            $key = $sessionKey;
        } elseif ($userKey) {
            $source = 'student_database';
            $key = $userKey;
        } elseif ($teamSessionKey) {
            $source = 'team_session';
            $key = $teamSessionKey;
        } elseif ($teamKey) {
            $source = 'team_database';
            $key = $teamKey;
        } elseif ($envKey) {
            $source = 'university';
            $key = $envKey;
        }

        return [$source, $key];
    }

    private function fetchSavedKey(string $table, int $id): ?string
    {
        try {
            $prefix = Config::getTablePrefix();
            $stmt = $this->pdo->prepare("SELECT api_key_encrypted FROM {$prefix}{$table} WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $enc = $stmt->fetchColumn();
            if ($enc && is_string($enc)) {
                $dec = SecurityService::decryptApiKey($enc);
                if ($dec && SecurityService::isValidGeminiKey($dec)) {
                    return $dec;
                }
            }
        } catch (Exception $e) {
            error_log("Error reading {$table} API key: " . $e->getMessage());
        }
        return null;
    }

    public function handleSetSessionApiKey(): void
    {
        header(Config::APP_JSON);
        $key = trim($_POST['api_key'] ?? '');
        $source = $_POST['source'] ?? 'manual';
        $target = $_POST['target'] ?? 'student';

        if (!SecurityService::isValidGeminiKey($key)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid Gemini API key format (must start with AIza).']);
            return;
        }

        if ($target === 'team') {
            $_SESSION['team_gemini_api_key'] = $key;
            $_SESSION['team_gemini_api_key_source'] = $source;
        } else {
            $_SESSION['user_gemini_api_key'] = $key;
            $_SESSION['user_gemini_api_key_source'] = $source;
        }

        echo json_encode([
            'success' => true,
            'message' => 'API key stored in session memory.',
            'target' => $target,
            'source' => $source
        ]);
    }

    public function handleClearSessionApiKey(): void
    {
        header(Config::APP_JSON);
        $target = $_POST['target'] ?? 'all';

        if ($target === 'team' || $target === 'all') {
            unset($_SESSION['team_gemini_api_key'], $_SESSION['team_gemini_api_key_source']);
        }
        if ($target === 'student' || $target === 'all') {
            unset($_SESSION['user_gemini_api_key'], $_SESSION['user_gemini_api_key_source']);
        }

        echo json_encode(['success' => true, 'message' => 'Session key cleared.']);
    }

    public function handleSetSessionGitHubKeys(): void
    {
        header(Config::APP_JSON);
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $token = trim($_POST['github_token'] ?? '');
        $username = trim($_POST['github_username'] ?? '');
        $repo = trim($_POST['github_repo'] ?? '');

        if (empty($token) || empty($username)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'GitHub token and username are required.']);
            return;
        }

        $_SESSION['github_token'] = $token;
        $_SESSION['github_username'] = $username;
        if (!empty($repo)) {
            $_SESSION['github_repo'] = $repo;
        } else {
            unset($_SESSION['github_repo']);
        }

        $maskedToken = strlen($token) > 8 ? substr($token, 0, 4) . '...' . substr($token, -4) : '***';

        echo json_encode([
            'success' => true,
            'message' => 'GitHub credentials stored in session memory.',
            'github_username' => $username,
            'github_repo' => $repo ?: null,
            'masked_github_token' => $maskedToken
        ]);
    }

    public function handleClearSessionGitHubKeys(): void
    {
        header(Config::APP_JSON);
        unset($_SESSION['github_token'], $_SESSION['github_username'], $_SESSION['github_repo']);
        echo json_encode([
            'success' => true,
            'message' => 'GitHub session credentials cleared.'
        ]);
    }

    public function handleSaveUserApiKey(): void
    {
        header(Config::APP_JSON);
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $key = trim($_POST['api_key'] ?? '');
        $target = $_POST['target'] ?? 'student';

        if (!SecurityService::isValidGeminiKey($key)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid Gemini API key format (must start with AIza).']);
            return;
        }

        try {
            $prefix = Config::getTablePrefix();
            $encrypted = SecurityService::encryptApiKey($key);

            if ($target === 'team') {
                $team = $this->resolveUserTeam((int)$userId);
                $teamId = $team['id'] ?? null;
                if (!$teamId) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'You are not assigned to any team.']);
                    return;
                }
                $stmt = $this->pdo->prepare("UPDATE {$prefix}teams SET api_key_encrypted = :enc WHERE id = :id");
                $stmt->execute([':enc' => $encrypted, ':id' => $teamId]);
                $_SESSION['team_gemini_api_key'] = $key;
            } else {
                $stmt = $this->pdo->prepare("UPDATE {$prefix}users SET api_key_encrypted = :enc WHERE id = :id");
                $stmt->execute([':enc' => $encrypted, ':id' => $userId]);
                $_SESSION['user_gemini_api_key'] = $key;
            }

            echo json_encode([
                'success' => true,
                'message' => 'API key encrypted with AES-256 and saved to database successfully.'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save API key: ' . $e->getMessage()]);
        }
    }

    public function handleDeleteUserApiKey(): void
    {
        header(Config::APP_JSON);
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $target = $_POST['target'] ?? 'student';
        $prefix = Config::getTablePrefix();

        try {
            if ($target === 'team') {
                $team = $this->resolveUserTeam((int)$userId);
                $teamId = $team['id'] ?? null;
                if ($teamId) {
                    $stmt = $this->pdo->prepare("UPDATE {$prefix}teams SET api_key_encrypted = NULL WHERE id = :id");
                    $stmt->execute([':id' => $teamId]);
                }
                unset($_SESSION['team_gemini_api_key'], $_SESSION['team_gemini_api_key_source']);
            } else {
                $stmt = $this->pdo->prepare("UPDATE {$prefix}users SET api_key_encrypted = NULL WHERE id = :id");
                $stmt->execute([':id' => $userId]);
                unset($_SESSION['user_gemini_api_key'], $_SESSION['user_gemini_api_key_source']);
            }

            echo json_encode(['success' => true, 'message' => 'Saved API key deleted from database.']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to delete API key: ' . $e->getMessage()]);
        }
    }

    private function resolveUserTeam(int $userId): ?array
    {
        $prefix = Config::getTablePrefix();
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.id, t.name
                FROM {$prefix}teams t
                JOIN {$prefix}team_users tu ON tu.team_id = t.id
                WHERE tu.user_id = :user_id
                LIMIT 1
            ");
            $stmt->execute([':user_id' => $userId]);
            $team = $stmt->fetch(PDO::FETCH_ASSOC);
            return $team ? ['id' => (int)$team['id'], 'name' => (string)$team['name']] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function handleChangePassword(): void
    {
        header(Config::APP_JSON);
        $userId = $_SESSION['user_id'] ?? null;
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $minLen = Config::getMinPasswordLength();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        } elseif (empty($currentPassword) || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Current password and new password are required.']);
        } elseif (strlen($newPassword) < $minLen || strlen($newPassword) > 31) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "New password must be between {$minLen} and 31 characters."]);
        } else {
            try {
                $prefix = Config::getTablePrefix();
                $stmt = $this->pdo->prepare("SELECT password_hash FROM {$prefix}users WHERE id = :id");
                $stmt->execute([':id' => $userId]);
                $currentHash = $stmt->fetchColumn();

                if (!$currentHash || !password_verify($currentPassword, $currentHash)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Incorrect current password.']);
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $this->pdo->prepare("UPDATE {$prefix}users SET password_hash = :hash, must_change_password = 0 WHERE id = :id");
                    $updateStmt->execute([':hash' => $newHash, ':id' => $userId]);

                    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to update password: ' . $e->getMessage()]);
            }
        }
    }
}
