<?php
function maskNikList($nik) {
    $nik = (string) $nik;
    return strlen($nik) >= 8 ? substr($nik, 0, 4) . str_repeat('*', max(0, strlen($nik) - 8)) . substr($nik, -4) : $nik;
}

function registrationPageUrl($page, $kegiatanId, array $filters = []) {
    $query = array_filter([
        'id' => $kegiatanId,
        'page' => $page,
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? ''
    ], static fn($value) => $value !== '');
    return '/registrations?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peserta dan Token - <?= htmlspecialchars($kegiatan['nama_kegiatan']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="bg-gray-50 p-6 md:p-8">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Peserta dan Token</h1>
                <p class="text-gray-600 mt-1"><?= htmlspecialchars($kegiatan['nama_kegiatan']) ?></p>
            </div>
            <a href="/dashboard" class="inline-flex items-center gap-2 text-blue-600 hover:underline">
                <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>

        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-600">Nomor Surat Undangan</p>
                <p class="mt-1 font-semibold text-blue-950"><?= htmlspecialchars($kegiatan['nomor_surat_undangan'] ?? '-') ?></p>
            </div>
            <div class="rounded-xl border border-gray-100 bg-white p-4 md:col-span-2">
                <p class="text-sm font-semibold text-gray-900">Token untuk konfirmasi kehadiran ada pada kolom Token.</p>
                <p class="mt-1 text-sm text-gray-600">Pada hari pelaksanaan, peserta yang sudah mengisi biodata cukup memasukkan token tersebut dan nomor surat undangan di halaman presensi.</p>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Total Registrasi</p>
                <p class="mt-1 text-2xl font-bold text-gray-900"><?= number_format((int) $registrationSummary['total']) ?></p>
            </div>
            <div class="rounded-xl border border-green-100 bg-green-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-green-600">Sudah Hadir</p>
                <p class="mt-1 text-2xl font-bold text-green-900"><?= number_format((int) $registrationSummary['attended']) ?></p>
            </div>
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-600">Belum Konfirmasi</p>
                <p class="mt-1 text-2xl font-bold text-blue-900"><?= number_format((int) $registrationSummary['registered']) ?></p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <form method="GET" action="/registrations" class="grid grid-cols-1 gap-3 border-b border-gray-100 bg-gray-50/70 p-4 md:grid-cols-[1fr_220px_auto]">
                <input type="hidden" name="id" value="<?= (int) $kegiatan['id'] ?>">
                <label class="relative">
                    <span class="sr-only">Cari peserta</span>
                    <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                    <input type="search" name="q" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES) ?>"
                           placeholder="Nama, NIK, NIP, unit, HP, email, atau token"
                           class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-10 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </label>
                <select name="status" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">Semua status</option>
                    <option value="registered" <?= $filters['status'] === 'registered' ? 'selected' : '' ?>>Belum konfirmasi</option>
                    <option value="attended" <?= $filters['status'] === 'attended' ? 'selected' : '' ?>>Sudah hadir</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Terapkan</button>
                    <?php if ($filters['q'] !== '' || $filters['status'] !== ''): ?>
                        <a href="/registrations?id=<?= (int) $kegiatan['id'] ?>" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
            <div class="flex flex-col gap-2 border-b border-gray-100 px-4 py-3 md:flex-row md:items-center md:justify-between">
                <p class="text-sm text-gray-600">
                    Menampilkan
                    <span class="font-semibold text-gray-900"><?= number_format($pagination['from']) ?></span>
                    -
                    <span class="font-semibold text-gray-900"><?= number_format($pagination['to']) ?></span>
                    dari
                    <span class="font-semibold text-gray-900"><?= number_format($pagination['total']) ?></span>
                    peserta
                </p>
                <p class="text-sm text-gray-500">10 baris per halaman</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-sm font-semibold text-gray-600">Nama</th>
                            <th class="px-4 py-3 text-sm font-semibold text-gray-600">NIK</th>
                            <th class="px-4 py-3 text-sm font-semibold text-gray-600">Jabatan / Unit Kerja</th>
                            <th class="px-4 py-3 text-sm font-semibold text-gray-600">Gelombang</th>
                            <th class="px-4 py-3 text-sm font-semibold text-gray-600">Token</th>
                            <th class="px-4 py-3 text-sm font-semibold text-gray-600">Status</th>
                            <th class="px-4 py-3 text-sm font-semibold text-gray-600">Biodata Diisi</th>
                            <th class="px-4 py-3 text-sm font-semibold text-gray-600">Konfirmasi Hadir</th>
                            <th class="px-4 py-3 text-sm font-semibold text-gray-600 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($registrations)): ?>
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center text-gray-500">
                                    <i class="bi bi-person-lines-fill text-3xl block mb-2 text-gray-300"></i>
                                    <?= $filters['q'] !== '' || $filters['status'] !== '' ? 'Tidak ada peserta yang sesuai dengan filter.' : 'Belum ada peserta pra-registrasi atau biodata.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($registrations as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($row['hp']) ?> | <?= htmlspecialchars($row['email']) ?></div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700" title="<?= htmlspecialchars($row['nik']) ?>">
                                    <?= htmlspecialchars(maskNikList($row['nik'])) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($row['jabatan']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($row['unit_kerja']) ?></div>
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-indigo-700">
                                    <?= htmlspecialchars($row['gelombang_nama'] ?? '-') ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono font-bold tracking-widest bg-gray-900 text-white px-3 py-1 rounded">
                                        <?= htmlspecialchars($row['token_code']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= $row['status'] === 'attended' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' ?>">
                                        <?= $row['status'] === 'attended' ? 'Sudah Hadir' : 'Terdaftar' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($row['biodata_submitted_at']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($row['attendance_confirmed_at'] ?? '-') ?>
                                    <?php if ($row['attendance_distance_meters'] !== null): ?>
                                        <div class="mt-1 text-xs font-semibold text-emerald-700">
                                            <i class="bi bi-geo-alt-fill"></i>
                                            <?= number_format((float) $row['attendance_distance_meters'], 0, ',', '.') ?> m
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="/biodata/print?id=<?= (int)$row['id'] ?>" target="_blank"
                                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-100"
                                       title="Cetak Biodata PDF">
                                        <i class="bi bi-file-pdf-fill"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-4 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm text-gray-600">
                        Halaman <?= number_format($pagination['page']) ?> dari <?= number_format($pagination['total_pages']) ?>
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php $previousPage = max(1, $pagination['page'] - 1); ?>
                        <?php $nextPage = min($pagination['total_pages'], $pagination['page'] + 1); ?>

                        <a href="<?= $pagination['page'] > 1 ? registrationPageUrl($previousPage, $kegiatan['id'], $filters) : '#' ?>"
                           class="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-sm font-medium <?= $pagination['page'] > 1 ? 'border-gray-200 text-gray-700 hover:bg-gray-50' : 'pointer-events-none border-gray-100 text-gray-300' ?>">
                            <i class="bi bi-chevron-left"></i> Sebelumnya
                        </a>

                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <?php if ($i === 1 || $i === $pagination['total_pages'] || abs($i - $pagination['page']) <= 2): ?>
                                <a href="<?= registrationPageUrl($i, $kegiatan['id'], $filters) ?>"
                                   class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm font-semibold <?= $i === $pagination['page'] ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php elseif ($i === 2 || $i === $pagination['total_pages'] - 1): ?>
                                <span class="inline-flex h-9 min-w-9 items-center justify-center text-sm text-gray-400">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <a href="<?= $pagination['page'] < $pagination['total_pages'] ? registrationPageUrl($nextPage, $kegiatan['id'], $filters) : '#' ?>"
                           class="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-sm font-medium <?= $pagination['page'] < $pagination['total_pages'] ? 'border-gray-200 text-gray-700 hover:bg-gray-50' : 'pointer-events-none border-gray-100 text-gray-300' ?>">
                            Berikutnya <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
