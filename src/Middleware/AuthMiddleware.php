<?php

class AuthMiddleware
{
    public static function check()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        self::refreshCurrentUser();
    }

    public static function checkAdmin()
    {
        self::check();
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            die("403 Forbidden - Access Denied");
        }
    }

    public static function user()
    {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'fullname' => $_SESSION['fullname'] ?? null
        ];
    }

    private static function refreshCurrentUser()
    {
        global $pdo;

        if (!isset($pdo) || empty($_SESSION['user_id'])) {
            return;
        }

        try {
            $hasActiveColumn = false;
            foreach ($pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'") as $column) {
                $hasActiveColumn = true;
                break;
            }

            $columns = $hasActiveColumn ? 'id, username, fullname, role, is_active' : 'id, username, fullname, role';
            $stmt = $pdo->prepare("SELECT {$columns} FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || ($hasActiveColumn && (int) $user['is_active'] !== 1)) {
                session_unset();
                session_destroy();
                header('Location: /login');
                exit;
            }

            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
        } catch (Exception $e) {
            // Keep middleware tolerant if older databases have not run the latest migration.
        }
    }
}
