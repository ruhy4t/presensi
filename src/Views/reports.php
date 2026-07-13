<?php
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

$user = AuthMiddleware::user();

function formatTanggalIndoReport($tgl) {
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
    <title>Laporan Kegiatan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-gray-50 p-8">

    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-2xl font-bold text-gray-800"><i class="bi bi-file-earmark-pdf-fill text-red-500 mr-2"></i> Laporan Kegiatan</h1>
            <a href="/dashboard" class="text-blue-600 hover:underline"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Kegiatan Ditampilkan</p>
                <p class="mt-1 text-2xl font-bold text-gray-900"><?= number_format($reportSummary['total_kegiatan']) ?></p>
            </div>
            <div class="rounded-xl border border-green-100 bg-green-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-green-600">Kegiatan Aktif</p>
                <p class="mt-1 text-2xl font-bold text-green-900"><?= number_format($reportSummary['aktif']) ?></p>
            </div>
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-600">Total Kehadiran</p>
                <p class="mt-1 text-2xl font-bold text-blue-900"><?= number_format($reportSummary['total_hadir']) ?></p>
            </div>
        </div>

        <form method="GET" action="/reports" class="mb-6 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
                <label class="relative lg:col-span-2">
                    <span class="sr-only">Cari kegiatan</span>
                    <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                    <input type="search" name="q" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES) ?>"
                           placeholder="Cari nama, tempat, atau pembuat"
                           class="w-full rounded-lg border border-gray-200 py-2 pl-10 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </label>
                <select name="status" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">Semua status</option>
                    <?php foreach (['Aktif', 'Non-Aktif', 'Diarsipkan'] as $statusOption): ?>
                        <option value="<?= $statusOption ?>" <?= $filters['status'] === $statusOption ? 'selected' : '' ?>><?= $statusOption ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" title="Tanggal mulai"
                       class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" title="Tanggal akhir"
                       class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i class="bi bi-funnel mr-1"></i> Terapkan Filter
                </button>
                <?php if (array_filter($filters, static fn($value) => $value !== '') !== []): ?>
                    <a href="/reports" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-600">Nama Kegiatan</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-600">Pelaksanaan</th>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-600">Peserta</th>
                            <?php if ($user['role'] === 'admin'): ?>
                                <th class="px-6 py-4 text-sm font-semibold text-gray-600">Pembuat</th>
                            <?php endif; ?>
                            <th class="px-6 py-4 text-sm font-semibold text-gray-600 text-center">Aksi Laporan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($kegiatanList)): ?>
                            <tr>
                                <td colspan="<?= $user['role'] === 'admin' ? 5 : 4 ?>" class="px-6 py-12 text-center text-gray-500">
                                    <i class="bi bi-inbox text-3xl block mb-2 text-gray-300"></i>
                                    <?= array_filter($filters, static fn($value) => $value !== '') !== [] ? 'Tidak ada kegiatan yang sesuai dengan filter.' : 'Belum ada data kegiatan.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($kegiatanList as $k): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($k['nama_kegiatan']) ?></div>
                                    <div class="text-xs text-gray-500 mt-1">Status: 
                                        <span class="<?= $k['status'] === 'Aktif' ? 'text-green-600' : 'text-gray-600' ?> font-semibold"><?= htmlspecialchars($k['status']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-800"><i class="bi bi-calendar-event mr-1 text-gray-400"></i> <?= formatTanggalIndoReport($k['tanggal_pelaksanaan'] ?? '-') ?></div>
                                    <div class="text-xs text-gray-500 mt-1"><i class="bi bi-geo-alt mr-1 text-gray-400"></i> <?= htmlspecialchars($k['tempat_pelaksanaan'] ?? '-') ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= number_format($k['attendance_count'] ?? 0) ?> Hadir
                                    </span>
                                </td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <i class="bi bi-person mr-1 text-gray-400"></i> <?= htmlspecialchars($k['creator_name'] ?? '-') ?>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="/registrations?id=<?= $k['id'] ?>" title="Peserta & Token"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                                            <i class="bi bi-person-lines-fill"></i>
                                        </a>
                                        <a href="/report/print?id=<?= $k['id'] ?>&source=laporan" target="_blank" title="Cetak / Download PDF"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors">
                                            <i class="bi bi-file-pdf-fill"></i>
                                        </a>
                                        <a href="/report/export?id=<?= $k['id'] ?>&format=xls" title="Export Excel"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 transition-colors">
                                            <i class="bi bi-file-earmark-excel-fill"></i>
                                        </a>
                                        <a href="/report/export?id=<?= $k['id'] ?>&format=csv" title="Export CSV"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                                            <i class="bi bi-filetype-csv"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>

</html>
