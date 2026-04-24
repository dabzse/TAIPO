<?php

namespace App\Controller;

use PDO;
use Exception;
use App\Config;

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

        if (!Config::isRegistrationEnabled()) {
            header(Config::APP_JSON, true, 403);
            echo json_encode(['success' => false, 'error' => 'Registration is currently disabled.']);
            return;
        }

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username and password are required.']);
        } elseif (strlen($username) < Config::getMinUsernameLength() || strlen($username) > 16 || !preg_match('/^\w+$/', $username)) {
            // Validate username length and characters
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid username format (' . Config::getMinUsernameLength() . '-16 letters/numbers/underscore).']);
        } elseif (strlen($password) < Config::getMinPasswordLength() || strlen($password) > 31) {
            // Validate password length
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be between ' . Config::getMinPasswordLength() . ' and 31 characters long.']);
        } else {
            try {
                $this->doRegister($username, $password);
            } catch (Exception $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                http_response_code(500);
                error_log("Registration error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => "Server error during registration."]);
            }
        }
    }

    private function doRegister(string $username, string $password): void
    {
        $this->pdo->beginTransaction();

        $prefix = Config::getTablePrefix();
        // Check if user already exists
        $stmt = $this->pdo->prepare("SELECT id FROM {$prefix}users WHERE username = :username");
        $stmt->execute([':username' => $username]);

        if ($stmt->fetch()) {
            $this->pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Username already exists.']);
        } else {
            $prefix = Config::getTablePrefix();
            // Secure Hash
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("INSERT INTO {$prefix}users (username, password_hash) VALUES (:username, :hash)");
            $stmt->execute([':username' => $username, ':hash' => $hash]);
            $userId = (int) $this->pdo->lastInsertId();

            $prefix = Config::getTablePrefix();
            // If this is the FIRST user, assign all current projects to this user
            $countStmt = $this->pdo->query("SELECT COUNT(*) FROM {$prefix}users");
            if ($countStmt->fetchColumn() == 1) {
                // First user! Claim all projects and tasks.
                $this->pdo->exec("UPDATE {$prefix}projects SET user_id = $userId WHERE user_id IS NULL");
                // Tasks are queried by project, but if we need a direct relation in future, its handled globally
            }

            $this->pdo->commit();

            // Auto-login
            $isInstructor = ($userId === 1); // By default, user id 1 is toggled to instructor
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['is_instructor'] = $isInstructor;

            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'user' => [
                'id' => $userId,
                'username' => $username,
                'is_instructor' => $isInstructor
            ]]);
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
            $stmt = $this->pdo->prepare("SELECT id, username, password_hash, is_instructor FROM {$prefix}users WHERE username = :username");
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
                    'is_instructor' => (bool)$user['is_instructor']
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
            $stmt = $this->pdo->prepare("SELECT is_instructor FROM {$prefix}users WHERE id = :id");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $isInstructor = (bool)$stmt->fetchColumn();
            $_SESSION['is_instructor'] = $isInstructor;

            echo json_encode([
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'is_instructor' => $isInstructor
                ],
                'config' => $config
            ]);
        } else {
            echo json_encode(['success' => true, 'authenticated' => false, 'config' => $config]);
        }
    }
    public function handleGitHubLogin()
    {
        // Közvetlenül a környezeti változóból fűzzük össze az URL-t, változók nélkül
        header("Location: https://github.com/login/oauth/authorize?client_id=" .
            ($_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN')) .
            "&redirect_uri=http://localhost:8000/?action=github_callback&scope=repo,user");
        exit;
    }

    public function handleGitHubCallback()
    {
        // 1. Kód kinyerése az URL-ből
        $code = $_GET['code'] ?? null;
        if (!$code) {
            header("Location: http://localhost:5173/?error=no_code");
            exit;
        }

        // 2. Token kérése a GitHub-tól (CURL-lel)
        $ch = curl_init("https://github.com/login/oauth/access_token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id'     => $_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN'),
            'client_secret' => $_ENV['GITHUB_CLIENT_SECRET'] ?? getenv('GITHUB_CLIENT_SECRET'),
            'code'          => $code,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $authData = json_decode(curl_exec($ch), true);
        $accessToken = $authData['access_token'] ?? null;

        if (!$accessToken) {
            header("Location: http://localhost:5173/?error=auth_failed");
            exit;
        }

        // 3. Felhasználói adatok lekérése a Token segítségével
        curl_setopt($ch, CURLOPT_URL, "https://api.github.com/user");
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: AI-Kanban-App'
        ]);

        $userData = json_decode(curl_exec($ch), true);
        curl_close($ch);

        // 4. Beléptetés a Sessionbe, ha megvan a GitHub login név
        if (isset($userData['login'])) {
            // Megakadályozzuk a session fixation támadást
            session_regenerate_id(true);

            $_SESSION['user_id'] = 999; // Ideiglenes ID, amíg nincs DB-be mentve
            $_SESSION['username'] = $userData['login'];
            $_SESSION['is_instructor'] = false;

            // 5. VISSZAIRÁNYÍTÁS A FRONTENDRE
            header("Location: http://localhost:5173");
            exit;
        }

        header("Location: http://localhost:5173/?error=user_data_failed");
        exit;
    }
}
