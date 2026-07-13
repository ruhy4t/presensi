<?php
require_once __DIR__ . '/../Services/KegiatanUrlService.php';

function formatTanggalIndoHome($tgl) {
    if (!$tgl || $tgl === '-') return '-';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
        $hari_inggris = date('l', strtotime($tgl));
        $hari_indo = ['Monday'=>'Senin', 'Tuesday'=>'Selasa', 'Wednesday'=>'Rabu', 'Thursday'=>'Kamis', 'Friday'=>'Jumat', 'Saturday'=>'Sabtu', 'Sunday'=>'Minggu'];
        $hari = $hari_indo[$hari_inggris] ?? '';
        
        $bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
        $parts = explode('-', $tgl);
        return $hari . ', ' . $parts[2] . ' ' . $bulan[$parts[1]] . ' ' . $parts[0];
    }
    return htmlspecialchars($tgl);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Hadir Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .hero-pattern {
            background-color: #f3f4f6;
            background-image: radial-gradient(#3b82f6 0.5px, transparent 0.5px), radial-gradient(#3b82f6 0.5px, #f3f4f6 0.5px);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

    <!-- Navbar -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="bi bi-clipboard-check-fill text-blue-600 text-2xl mr-2"></i>
                    <span class="font-bold text-xl text-gray-800">E-Presensi</span>
                </div>
                <div class="flex items-center">
                    <a href="/login" class="text-sm font-medium text-gray-500 hover:text-blue-600 transition-colors">
                        <i class="bi bi-shield-lock"></i> Login Admin
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero / Content -->
    <main class="flex-grow hero-pattern py-10 px-4">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-10">
                <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-2">Pilih Kegiatan</h1>
                <p class="text-gray-600">Silakan pilih kegiatan yang sedang berlangsung untuk mengisi daftar hadir.</p>
            </div>

            <div class="grid gap-4">
                <?php if (empty($kegiatanList)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-8 text-center border border-gray-100">
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 text-blue-500 mb-4">
                            <i class="bi bi-calendar-x text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">Tidak ada kegiatan aktif</h3>
                        <p class="text-gray-500 mt-1">Saat ini belum ada kegiatan yang dibuka untuk presensi.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($kegiatanList as $k): ?>
                        <a href="<?= htmlspecialchars(KegiatanUrlService::attendancePath($k), ENT_QUOTES, 'UTF-8') ?>"
                            class="group block bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-200 hover:border-blue-400 transition-all duration-200 overflow-hidden">
                            <div class="p-6 flex items-center justify-between">
                                <div class="flex-1 pr-4">
                                    <h2 class="text-lg font-bold text-gray-800 group-hover:text-blue-600 transition-colors">
                                        <?= htmlspecialchars($k['nama_kegiatan']) ?>
                                    </h2>
                                    <p class="text-sm text-gray-500 mt-1">
                                        <i class="bi bi-calendar3 mr-1"></i>
                                        <?= formatTanggalIndoHome($k['tanggal_pelaksanaan'] ?? '-') ?>
                                        <?php if (!empty($k['tanggal_selesai']) && $k['tanggal_selesai'] !== $k['tanggal_pelaksanaan']): ?>
                                            s.d. <?= formatTanggalIndoHome($k['tanggal_selesai']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="flex-shrink-0">
                                    <span
                                        class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-50 group-hover:bg-blue-600 group-hover:text-white text-blue-600 transition-all">
                                        <i class="bi bi-arrow-right text-lg"></i>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-6 mt-auto">
        <div class="max-w-5xl mx-auto px-4 text-center text-sm text-gray-500">
            &copy;
            <?= date('Y') ?> Sistem Daftar Hadir Digital
        </div>
    </footer>

</body>

</html>
