<?php
require_once __DIR__ . '/../../config/database.php';

class AuthController
{

    public function handleRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->login();
        } elseif (isset($_GET['action']) && $_GET['action'] === 'logout') {
            $this->logout();
        } else {
            // Show Login View
            require __DIR__ . '/../Views/login.php';
        }
    }

    private function login()
    {
        global $pdo;

        // CSRF Check
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            die("CSRF Token Mismatch");
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $_SESSION['error'] = "Username dan Password wajib diisi.";
            header('Location: /login');
            exit;
        }

        // Rate Limiting (Simple Session based)
        if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 5) {
            if (time() - $_SESSION['last_attempt_time'] < 300) { // 5 minutes block
                $_SESSION['error'] = "Terlalu banyak percobaan login. Tunggu 5 menit.";
                header('Location: /login');
                exit;
            } else {
                // Reset after timeout
                $_SESSION['login_attempts'] = 0;
            }
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if (isset($user['is_active']) && (int) $user['is_active'] !== 1) {
                    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                    $_SESSION['last_attempt_time'] = time();
                    $_SESSION['error'] = "Akun Anda sedang nonaktif. Silakan hubungi administrator.";
                    header('Location: /login');
                    exit;
                }

                // Login Success
                session_regenerate_id(true); // Prevent Session Fixation
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_attempts'] = 0;

                // Log Activity
                $this->logActivity($user['id'], 'LOGIN', 'User logged in successfully');

                header('Location: /dashboard');
                exit;
            } else {
                // Login Failed
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                $_SESSION['last_attempt_time'] = time();
                $_SESSION['error'] = "Username atau Password salah.";

                // Log Failed Attempt
                // $this->logActivity(null, 'LOGIN_FAILED', "Failed login for username: $username");

                header('Location: /login');
                exit;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['error'] = "Terjadi kesalahan sistem.";
            header('Location: /login');
            exit;
        }
    }

    private function logout()
    {
        session_unset();
        session_destroy();
        header('Location: /login');
        exit;
    }

    private function logActivity($userId, $action, $details)
    {
        global $pdo;
        $ip = $_SERVER['REMOTE_ADDR'];
        try {
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $details, $ip]);
        } catch (Exception $e) {
            // Silently fail logging to not disrupt flow
        }
    }
}

// Instantiate and handle if accessed directly via router inclusion
if (isset($uri) && ($uri === '/login' || $uri === '/logout')) {
    $controller = new AuthController();
    $controller->handleRequest();
}
