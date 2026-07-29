<?php
require_once __DIR__ . '/../../../config/app.php';
$activeMenu = $activeMenu ?? '';
$sidebarBase = 'flex items-center px-4 py-3 rounded-lg transition-colors';
$sidebarInactive = ' text-slate-300 hover:bg-slate-800 hover:text-white';
$sidebarActive = ' bg-blue-600 text-white shadow-lg shadow-blue-500/30';
?>
<aside
    class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white transition-transform transform lg:translate-x-0 lg:static lg:inset-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    <div class="flex items-center justify-center h-16 bg-slate-800 shadow-md">
        <div class="text-center">
            <h1 class="text-xl font-bold tracking-wider"><?= htmlspecialchars(APP_NAME) ?></h1>
            <p class="text-[10px] font-semibold tracking-widest text-slate-400">v<?= htmlspecialchars(APP_VERSION) ?></p>
        </div>
    </div>
    <nav class="mt-5 px-4 space-y-2">
        <a href="/dashboard" class="<?= $sidebarBase . ($activeMenu === 'dashboard' ? $sidebarActive : $sidebarInactive) ?>">
            <i class="bi bi-grid-fill mr-3"></i> Dashboard
        </a>

        <?php if (($user['role'] ?? '') === 'admin'): ?>
            <a href="/users" class="<?= $sidebarBase . ($activeMenu === 'users' ? $sidebarActive : $sidebarInactive) ?>">
                <i class="bi bi-people-fill mr-3"></i> Kelola User
            </a>
        <?php endif; ?>

        <a href="/reports" class="<?= $sidebarBase . ($activeMenu === 'reports' ? $sidebarActive : $sidebarInactive) ?>">
            <i class="bi bi-file-earmark-pdf-fill mr-3"></i> Laporan
        </a>

        <a href="/logout?action=logout"
            class="flex items-center px-4 py-3 text-red-400 hover:bg-red-900/20 hover:text-red-300 rounded-lg transition-colors mt-8">
            <i class="bi bi-box-arrow-left mr-3"></i> Logout
        </a>
    </nav>

    <div class="absolute bottom-0 left-0 w-full p-4 bg-slate-800/50">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-lg font-bold">
                <?= htmlspecialchars(strtoupper(substr((string) ($user['username'] ?? '?'), 0, 1))) ?>
            </div>
            <div>
                <p class="text-sm font-semibold truncate w-32"><?= htmlspecialchars($user['fullname'] ?? '-') ?></p>
                <span class="text-xs text-slate-400 capitalize"><?= htmlspecialchars($user['role'] ?? '-') ?></span>
            </div>
        </div>
    </div>
</aside>
