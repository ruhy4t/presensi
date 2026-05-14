<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Daftar Hadir</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <style>
        body {
            background-image: url('https://images.unsplash.com/photo-1557683316-973673baf926?q=80&w=2029&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
        }

        .glass {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }
    </style>
</head>

<body class="h-screen flex items-center justify-center">

    <div class="glass p-8 rounded-2xl w-full max-w-md m-4" x-data="{ loading: false }">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Selamat Datang</h1>
            <p class="text-white/80">Silakan login untuk melanjutkan</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-500/80 text-white p-3 rounded-lg mb-4 text-sm text-center">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form action="/login" method="POST" @submit="loading = true">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-4">
                <label class="block text-white text-sm font-medium mb-2">Username</label>
                <input type="text" name="username"
                    class="w-full px-4 py-3 rounded-lg bg-white/50 border border-transparent focus:border-white focus:bg-white focus:ring-0 text-gray-800 placeholder-gray-500 transition-all outline-none"
                    placeholder="Masukkan username" required>
            </div>

            <div class="mb-6">
                <label class="block text-white text-sm font-medium mb-2">Password</label>
                <input type="password" name="password"
                    class="w-full px-4 py-3 rounded-lg bg-white/50 border border-transparent focus:border-white focus:bg-white focus:ring-0 text-gray-800 placeholder-gray-500 transition-all outline-none"
                    placeholder="Masukkan password" required>
            </div>

            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition-all transform hover:scale-[1.02] active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed flex justify-center items-center gap-2"
                :disabled="loading">
                <span x-show="!loading">Masuk Aplikasi</span>
                <span x-show="loading"
                    class="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full"></span>
            </button>
        </form>

        <div class="mt-8 text-center text-xs text-white/50">
            &copy;
            <?= date('Y') ?> Sistem Daftar Hadir Aman
        </div>
    </div>

</body>

</html>