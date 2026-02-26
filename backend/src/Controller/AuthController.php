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

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username and password are required.']);
        } elseif (strlen($username) < Config::getMinUsernameLength() || strlen($username) > 50 || !preg_match('/^\w+$/', $username)) {
            // Validate username length and characters
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid username format (min ' . Config::getMinUsernameLength() . '-50 letters/numbers/underscore).']);
        } elseif (strlen($password) < Config::getMinPasswordLength()) {
            // Validate password length
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password must be at least ' . Config::getMinPasswordLength() . ' characters long.']);
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

        // Check if user already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);

        if ($stmt->fetch()) {
            $this->pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Username already exists.']);
        } else {
            // Secure Hash
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:username, :hash)");
            $stmt->execute([':username' => $username, ':hash' => $hash]);
            $userId = (int) $this->pdo->lastInsertId();

            // If this is the FIRST user, assign all current projects to this user
            $countStmt = $this->pdo->query("SELECT COUNT(*) FROM users");
            if ($countStmt->fetchColumn() == 1) {
                // First user! Claim all projects and tasks.
                $this->pdo->exec("UPDATE projects SET user_id = $userId WHERE user_id IS NULL");
                // Tasks are queried by project, but if we need a direct relation in future, its handled globally
            }

            $this->pdo->commit();

            // Auto-login
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;

            header(Config::APP_JSON);
            echo json_encode(['success' => true, 'user' => ['id' => $userId, 'username' => $username]]);
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
            $stmt = $this->pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                header(Config::APP_JSON);
                echo json_encode(['success' => true, 'user' => ['id' => $user['id'], 'username' => $user['username']]]);
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
            echo json_encode(['success' => true, 'authenticated' => true, 'user' => ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username']], 'config' => $config]);
        } else {
            echo json_encode(['success' => true, 'authenticated' => false, 'config' => $config]);
        }
    }
}
