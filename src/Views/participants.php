<?php
function maskParticipantNik(string $nik): string
{
    return strlen($nik) >= 8
        ? substr($nik, 0, 4) . str_repeat('*', max(0, strlen($nik) - 8)) . substr($nik, -4)
        : $nik;
}

function participantHistoryUrl(int $page, array $filters): string
{
    return '/participants?' . http_build_query(array_filter([
        'page' => $page,
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? ''
    ], static fn($value): bool => $value !== ''));
}

function participantStatusLabel(string $status): array
{
    return match ($status) {
        'attended' => ['Sudah hadir', 'bg-green-100 text-green-700'],
        'cancelled' => ['Dibatalkan', 'bg-red-100 text-red-700'],
        default => ['Terdaftar', 'bg-blue-100 text-blue-700'],
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Peserta</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased">
    <div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: false }">
        <?php $activeMenu = 'participants'; require __DIR__ . '/partials/sidebar.php'; ?>

        <div class="flex flex-1 flex-col overflow-hidden">
            <header class="flex items-center justify-between border-b bg-white px-6 py-4 shadow-sm lg:hidden">
                <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500"><i class="bi bi-list text-2xl"></i></button>
                <span class="text-lg font-semibold">Riwayat Peserta</span>
                <div class="w-8"></div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto p-6 md:p-8">
                <div class="mx-auto max-w-7xl">
                    <div class="mb-7">
                        <h1 class="text-2xl font-bold text-gray-900"><i class="bi bi-person-vcard-fill mr-2 text-blue-600"></i>Riwayat Peserta</h1>
                        <p class="mt-1 text-sm text-gray-600">Rekap peserta dan kegiatan yang pernah mereka ikuti.</p>
                    </div>

                    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                            <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Peserta Unik</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900"><?= number_format((int) $participantSummary['participants']) ?></p>
                        </div>
                        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-600">Total Keikutsertaan</p>
                            <p class="mt-1 text-2xl font-bold text-blue-900"><?= number_format((int) $participantSummary['registrations']) ?></p>
                        </div>
                        <div class="rounded-xl border border-green-100 bg-green-50 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-green-600">Kehadiran Terkonfirmasi</p>
                            <p class="mt-1 text-2xl font-bold text-green-900"><?= number_format((int) $participantSummary['attended']) ?></p>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        <form method="GET" action="/participants" class="grid grid-cols-1 gap-3 border-b bg-gray-50/70 p-4 md:grid-cols-[1fr_220px_auto]">
                            <label class="relative">
                                <span class="sr-only">Cari riwayat peserta</span>
                                <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                                <input type="search" name="q" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES) ?>"
                                    placeholder="Nama, NIK, NIP, unit, atau kegiatan"
                                    class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            </label>
                            <select name="status" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                                <option value="">Semua status</option>
                                <option value="registered" <?= $filters['status'] === 'registered' ? 'selected' : '' ?>>Terdaftar</option>
                                <option value="attended" <?= $filters['status'] === 'attended' ? 'selected' : '' ?>>Sudah hadir</option>
                                <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                            </select>
                            <div class="flex gap-2">
                                <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Terapkan</button>
                                <?php if ($filters['q'] !== '' || $filters['status'] !== ''): ?>
                                    <a href="/participants" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Reset</a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="border-b px-4 py-3 text-sm text-gray-600">
                            Menampilkan <strong><?= number_format($pagination['from']) ?>–<?= number_format($pagination['to']) ?></strong>
                            dari <strong><?= number_format($pagination['total']) ?></strong> riwayat
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="border-b bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600">Peserta</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600">Jabatan / Unit</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600">Kegiatan</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600">Tanggal</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600">Status</th>
                                        <th class="px-4 py-3 text-sm font-semibold text-gray-600">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if ($participantHistory === []): ?>
                                        <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">Belum ada riwayat peserta yang sesuai.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($participantHistory as $row): ?>
                                        <?php [$statusLabel, $statusClass] = participantStatusLabel($row['status']); ?>
                                        <tr class="align-top hover:bg-gray-50">
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                                                <div class="mt-1 text-xs text-gray-500" title="<?= htmlspecialchars($row['nik']) ?>"><?= htmlspecialchars(maskParticipantNik($row['nik'])) ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['hp']) ?></div>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <div class="text-gray-900"><?= htmlspecialchars($row['jabatan']) ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['unit_kerja']) ?></div>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['nama_kegiatan']) ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($row['gelombang_nama'] ?? $row['tempat_pelaksanaan'] ?? '-') ?></div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                                <?= !empty($row['tanggal_pelaksanaan']) ? htmlspecialchars(date('d/m/Y', strtotime($row['tanggal_pelaksanaan']))) : '-' ?>
                                                <?php if (!empty($row['tanggal_selesai']) && $row['tanggal_selesai'] !== $row['tanggal_pelaksanaan']): ?>
                                                    <div class="text-xs text-gray-500">s.d. <?= htmlspecialchars(date('d/m/Y', strtotime($row['tanggal_selesai']))) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                            <td class="px-4 py-3">
                                                <a href="/registrations?id=<?= (int) $row['kegiatan_id'] ?>&q=<?= urlencode($row['nik']) ?>"
                                                    class="whitespace-nowrap text-sm font-semibold text-blue-600 hover:underline">Lihat di kegiatan</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($pagination['total_pages'] > 1): ?>
                            <div class="flex items-center justify-between border-t px-4 py-4">
                                <p class="text-sm text-gray-600">Halaman <?= number_format($pagination['page']) ?> dari <?= number_format($pagination['total_pages']) ?></p>
                                <div class="flex gap-2">
                                    <a href="<?= $pagination['page'] > 1 ? participantHistoryUrl($pagination['page'] - 1, $filters) : '#' ?>"
                                        class="rounded-lg border px-3 py-2 text-sm <?= $pagination['page'] > 1 ? 'text-gray-700 hover:bg-gray-50' : 'pointer-events-none text-gray-300' ?>">Sebelumnya</a>
                                    <a href="<?= $pagination['page'] < $pagination['total_pages'] ? participantHistoryUrl($pagination['page'] + 1, $filters) : '#' ?>"
                                        class="rounded-lg border px-3 py-2 text-sm <?= $pagination['page'] < $pagination['total_pages'] ? 'text-gray-700 hover:bg-gray-50' : 'pointer-events-none text-gray-300' ?>">Berikutnya</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
