<?php
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$formatDate = static function ($value): string {
    if (!$value) return '-';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
    if (!$date || $date->format('Y-m-d') !== $value) return (string) $value;
    $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $date->format('j') . ' ' . $months[(int) $date->format('n') - 1] . ' ' . $date->format('Y');
};
$dateLabel = $formatDate($kegiatan['tanggal_pelaksanaan'] ?? null);
if (!empty($kegiatan['tanggal_selesai']) && $kegiatan['tanggal_selesai'] !== $kegiatan['tanggal_pelaksanaan']) {
    $dateLabel .= ' s.d. ' . $formatDate($kegiatan['tanggal_selesai']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak QR - <?= $escape($kegiatan['nama_kegiatan']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #edf1f5; color: #172033; font-family: Arial, sans-serif; }
        .toolbar { text-align: center; padding: 20px; }
        button { padding: 12px 24px; background: #1d4ed8; border: 0; border-radius: 8px; color: white; font-size: 16px; cursor: pointer; }
        button:disabled { opacity: .5; cursor: wait; }
        .poster { background: white; max-width: 186mm; margin: 0 auto 24px; padding: 14mm; text-align: center; }
        .eyebrow { font-size: 13px; letter-spacing: 3px; text-transform: uppercase; }
        h1 { font-size: 28px; line-height: 1.3; margin: 12px 0 24px; overflow-wrap: anywhere; }
        h2 { font-size: 23px; margin: 24px 0 8px; }
        .details { text-align: left; border-top: 2px solid #172033; border-bottom: 1px solid #cbd5e1; padding: 14px 0; }
        .row { display: grid; grid-template-columns: 125px 1fr; gap: 12px; margin: 9px 0; font-size: 15px; line-height: 1.5; overflow-wrap: anywhere; }
        .value { white-space: pre-wrap; }
        .qr-block { break-inside: avoid; }
        #qrcode { display: inline-block; background: white; padding: 20px; margin: 10px auto; }
        #qrcode canvas, #qrcode img { width: 76mm !important; height: 76mm !important; max-width: 100%; }
        .instruction { font-size: 15px; line-height: 1.6; }
        .url { font-size: 11px; overflow-wrap: anywhere; color: #475569; }
        @page { size: A4 portrait; margin: 12mm; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .poster { max-width: none; margin: 0; padding: 5mm; }
            h1, h2 { break-after: avoid; }
        }
        @media screen and (max-width: 600px) { .poster { padding: 24px; } .row { grid-template-columns: 95px 1fr; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button id="print-button" type="button" disabled>Cetak / Simpan PDF</button>
        <p id="qr-status" role="status">Menyiapkan QR Code...</p>
    </div>
    <main class="poster">
        <div class="eyebrow">Konfirmasi Kehadiran</div>
        <h1><?= $escape($kegiatan['nama_kegiatan']) ?></h1>
        <div class="details">
            <div class="row"><strong>Tanggal</strong><span><?= $escape($dateLabel) ?></span></div>
            <?php foreach (['waktu_pelaksanaan' => 'Waktu', 'tempat_pelaksanaan' => 'Tempat', 'jenis_kegiatan' => 'Jenis kegiatan', 'nomor_surat_undangan' => 'No. undangan'] as $field => $label): ?>
                <?php if (trim((string) ($kegiatan[$field] ?? '')) !== '' && $kegiatan[$field] !== '-'): ?>
                    <div class="row"><strong><?= $label ?></strong><span class="value"><?= $escape($kegiatan[$field]) ?></span></div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php foreach ($waves as $wave): ?>
                <div class="row"><strong><?= $escape($wave['nama']) ?></strong><span><?= $escape($formatDate($wave['tanggal'])) ?><?= !empty($wave['waktu_mulai']) ? ' · ' . $escape(substr($wave['waktu_mulai'], 0, 5)) . '–' . $escape(substr($wave['waktu_selesai'], 0, 5)) . ' WIB' : '' ?></span></div>
            <?php endforeach; ?>
        </div>
        <section class="qr-block">
            <h2>Scan di sini untuk konfirmasi kehadiran</h2>
            <div id="qrcode" aria-label="QR Code tautan presensi"></div>
            <p class="instruction">Buka kamera ponsel, pindai QR Code, lalu ikuti petunjuk pada formulir presensi.</p>
            <p class="url"><?= $escape($attendanceUrl) ?></p>
        </section>
    </main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        const printButton = document.getElementById('print-button');
        const status = document.getElementById('qr-status');
        try {
            new QRCode(document.getElementById('qrcode'), {
                text: <?= json_encode($attendanceUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                width: 600, height: 600, correctLevel: QRCode.CorrectLevel.M
            });
            printButton.disabled = false;
            status.textContent = 'Siap dicetak pada kertas A4 atau disimpan sebagai PDF.';
        } catch (error) {
            status.textContent = 'QR Code gagal dimuat. Periksa koneksi internet, lalu muat ulang halaman.';
        }
        printButton.addEventListener('click', () => window.print());
    </script>
</body>
</html>
