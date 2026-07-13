<?php
require_once __DIR__ . '/../Controllers/UserController.php';

$controller = new UserController();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->handlePost();
}
$users = $controller->index();
$user = AuthMiddleware::user();
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-gray-50 text-gray-800 font-sans antialiased">
    <div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: false }">
        <?php $activeMenu = 'users'; require __DIR__ . '/partials/sidebar.php'; ?>
        <div class="flex flex-1 flex-col overflow-hidden">
            <header class="flex items-center justify-between px-6 py-4 bg-white border-b shadow-sm lg:hidden">
                <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 focus:outline-none"><i class="bi bi-list text-2xl"></i></button>
                <span class="font-semibold text-lg">Kelola User</span>
                <div class="w-8"></div>
            </header>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6 md:p-8">

    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Manajemen User</h1>
            <a href="/dashboard" class="text-blue-600 hover:underline"><i class="bi bi-arrow-left"></i> Kembali ke
                Dashboard</a>
        </div>

        <!-- Add User Form -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
            <h2 class="text-lg font-semibold mb-4 border-b pb-2">Tambah User Baru</h2>

            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                    <?= $_SESSION['flash_error'] ?>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['flash_success'])): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded mb-4">
                    <?= $_SESSION['flash_success'] ?>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="store">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" name="username"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                    <input type="text" name="fullname"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2"
                        required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Role</label>
                    <select name="role"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 border p-2">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="md:col-span-2 text-right">
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700">Simpan User</button>
                </div>
            </form>
        </div>

        <!-- User List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
            <table class="w-full min-w-[980px] text-left">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-sm font-medium text-gray-500">Username</th>
                        <th class="px-6 py-3 text-sm font-medium text-gray-500">Nama Lengkap</th>
                        <th class="px-6 py-3 text-sm font-medium text-gray-500">Role</th>
                        <th class="px-6 py-3 text-sm font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3 text-sm font-medium text-gray-500">Dibuat</th>
                        <th class="px-6 py-3 text-sm font-medium text-gray-500 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($users as $u): ?>
                        <?php $isCurrentUser = (int) $u['id'] === $currentUserId; ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">
                                <?= htmlspecialchars($u['username']) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                <?= htmlspecialchars($u['fullname']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $u['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= (int) $u['is_active'] === 1 ? 'bg-blue-100 text-blue-800' : 'bg-gray-200 text-gray-700' ?>">
                                    <?= (int) $u['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-400 text-sm">
                                <?= $u['created_at'] ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" onclick="toggleUserPanel('edit-<?= (int) $u['id'] ?>')"
                                        class="inline-flex items-center gap-1 rounded border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button type="button" onclick="toggleUserPanel('reset-<?= (int) $u['id'] ?>')"
                                        class="inline-flex items-center gap-1 rounded border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-medium text-sky-700 hover:bg-sky-100">
                                        <i class="bi bi-key"></i> Reset Password
                                    </button>
                                    <?php if ($isCurrentUser): ?>
                                        <span
                                            class="inline-flex items-center gap-1 rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-500">
                                            <i class="bi bi-shield-check"></i> Akun Anda
                                        </span>
                                    <?php else: ?>
                                        <form method="POST" class="inline"
                                            onsubmit="return confirm('<?= (int) $u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> akun <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>?')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= (int) $u['is_active'] === 1 ? 0 : 1 ?>">
                                            <button type="submit"
                                                class="inline-flex items-center gap-1 rounded border px-3 py-1.5 text-xs font-medium <?= (int) $u['is_active'] === 1 ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100' ?>">
                                                <i class="bi <?= (int) $u['is_active'] === 1 ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
                                                <?= (int) $u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <tr id="edit-<?= (int) $u['id'] ?>" class="hidden bg-amber-50/60">
                            <td colspan="6" class="px-6 py-4">
                                <form method="POST" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Username</label>
                                        <input type="text" name="username" value="<?= htmlspecialchars($u['username']) ?>"
                                            class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Nama Lengkap</label>
                                        <input type="text" name="fullname" value="<?= htmlspecialchars($u['fullname']) ?>"
                                            class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Role</label>
                                        <?php if ($isCurrentUser): ?>
                                            <input type="hidden" name="role" value="<?= htmlspecialchars($u['role']) ?>">
                                            <select disabled
                                                class="mt-1 w-full rounded-md border border-gray-300 bg-gray-100 p-2 text-sm text-gray-500 shadow-sm">
                                                <option selected><?= ucfirst($u['role']) ?></option>
                                            </select>
                                        <?php else: ?>
                                            <select name="role"
                                                class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <button type="submit"
                                            class="rounded bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-amber-700">Simpan</button>
                                        <button type="button" onclick="toggleUserPanel('edit-<?= (int) $u['id'] ?>')"
                                            class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-white">Batal</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <tr id="reset-<?= (int) $u['id'] ?>" class="hidden bg-sky-50/60">
                            <td colspan="6" class="px-6 py-4">
                                <form method="POST" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Password Baru</label>
                                        <input type="password" name="password"
                                            class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600">Konfirmasi Password</label>
                                        <input type="password" name="password_confirmation"
                                            class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required>
                                    </div>
                                    <div class="flex items-end gap-2 md:col-span-2">
                                        <button type="submit"
                                            class="rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-sky-700">Reset Password</button>
                                        <button type="button" onclick="toggleUserPanel('reset-<?= (int) $u['id'] ?>')"
                                            class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-white">Batal</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
            </main>
        </div>
    </div>

    <script>
        function toggleUserPanel(id) {
            const panel = document.getElementById(id);
            if (!panel) return;
            panel.classList.toggle('hidden');
        }
    </script>
</body>

</html>
