<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class UserController
{

    public function __construct()
    {
        AuthMiddleware::checkAdmin();
    }

    public function index()
    {
        global $pdo;
        $this->ensureActiveColumn();
        $stmt = $pdo->query("SELECT id, username, fullname, role, is_active, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function handlePost()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            die("CSRF Token Mismatch");
        }

        $action = $_POST['action'] ?? 'store';

        if ($action === 'update') {
            $this->update();
        } elseif ($action === 'reset_password') {
            $this->resetPassword();
        } elseif ($action === 'toggle_status') {
            $this->toggleStatus();
        } else {
            $this->store();
        }
    }

    public function store()
    {
        global $pdo;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            return;

        $this->ensureActiveColumn();

        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $this->normalizeRole($_POST['role'] ?? 'user');

        if (empty($username) || empty($password) || empty($fullname)) {
            $_SESSION['flash_error'] = "Semua field wajib diisi.";
        } else {
            $hash = password_hash($password, PASSWORD_ARGON2ID);

            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, role, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$username, $hash, $fullname, $role]);
                $_SESSION['flash_success'] = "User berhasil ditambahkan.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['flash_error'] = "Username sudah ada.";
                } else {
                    $_SESSION['flash_error'] = "Gagal menambah user.";
                }
            }
        }

        header('Location: /users');
        exit;
    }

    public function update()
    {
        global $pdo;

        $this->ensureActiveColumn();

        $id = (int) ($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $role = $this->normalizeRole($_POST['role'] ?? 'user');
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

        if ($id <= 0 || empty($username) || empty($fullname)) {
            $_SESSION['flash_error'] = "Data user tidak lengkap.";
        } elseif ($id === $currentUserId && $role !== ($_SESSION['role'] ?? '')) {
            $_SESSION['flash_error'] = "Anda tidak bisa mengubah role akun sendiri.";
        } elseif ($this->isLastActiveAdmin($id) && $role !== 'admin') {
            $_SESSION['flash_error'] = "Tidak bisa mengubah role admin aktif terakhir.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, fullname = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $fullname, $role, $id]);

                if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
                    $_SESSION['username'] = $username;
                    $_SESSION['fullname'] = $fullname;
                    $_SESSION['role'] = $role;
                }

                $_SESSION['flash_success'] = "User berhasil diperbarui.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $_SESSION['flash_error'] = "Username sudah digunakan.";
                } else {
                    $_SESSION['flash_error'] = "Gagal memperbarui user.";
                }
            }
        }

        header('Location: /users');
        exit;
    }

    public function resetPassword()
    {
        global $pdo;

        $id = (int) ($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $confirmation = $_POST['password_confirmation'] ?? '';

        if ($id <= 0 || empty($password)) {
            $_SESSION['flash_error'] = "Password baru wajib diisi.";
        } elseif ($password !== $confirmation) {
            $_SESSION['flash_error'] = "Konfirmasi password tidak sama.";
        } else {
            $hash = password_hash($password, PASSWORD_ARGON2ID);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $id]);
            $_SESSION['flash_success'] = "Password user berhasil direset.";
        }

        header('Location: /users');
        exit;
    }

    public function toggleStatus()
    {
        global $pdo;

        $this->ensureActiveColumn();

        $id = (int) ($_POST['id'] ?? 0);
        $status = (int) ($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['flash_error'] = "User tidak ditemukan.";
        } elseif ($id === $currentUserId && $status === 0) {
            $_SESSION['flash_error'] = "Anda tidak bisa menonaktifkan akun sendiri.";
        } elseif ($status === 0 && $this->isLastActiveAdmin($id)) {
            $_SESSION['flash_error'] = "Tidak bisa menonaktifkan admin aktif terakhir.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND id <> ?");
            $stmt->execute([$status, $id, $currentUserId]);

            if ($stmt->rowCount() === 0) {
                $_SESSION['flash_error'] = "Status user tidak berubah.";
            } else {
                $_SESSION['flash_success'] = $status === 1 ? "User berhasil diaktifkan." : "User berhasil dinonaktifkan.";
            }
        }

        header('Location: /users');
        exit;
    }

    private function normalizeRole($role)
    {
        return $role === 'admin' ? 'admin' : 'user';
    }

    private function ensureActiveColumn()
    {
        global $pdo;

        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
        }
    }

    private function isLastActiveAdmin($id)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user || $user['role'] !== 'admin' || (int) $user['is_active'] !== 1) {
            return false;
        }

        $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
        return (int) $countStmt->fetchColumn() <= 1;
    }
}
