<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';

$admin = $pdo->query("SELECT id, username, fullname, role FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1")->fetch();
if (!$admin) {
    echo "SKIP  Tidak ada admin aktif untuk integration check.\n";
    exit(0);
}

$_SESSION = [
    'user_id' => $admin['id'],
    'username' => $admin['username'],
    'fullname' => $admin['fullname'],
    'role' => $admin['role'],
];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$sample = $pdo->query("
    SELECT pr.kegiatan_id, pr.status, p.nama_lengkap
    FROM participant_registrations pr
    INNER JOIN participants p ON p.id = pr.participant_id
    LIMIT 1
")->fetch();

if ($sample) {
    $_GET = [
        'id' => $sample['kegiatan_id'],
        'q' => $sample['nama_lengkap'],
        'status' => $sample['status'],
    ];
    require_once dirname(__DIR__) . '/src/Controllers/RegistrationController.php';
    ob_start();
    (new RegistrationController())->index($sample['kegiatan_id']);
    $registrationHtml = ob_get_clean();

    if (!str_contains($registrationHtml, 'Cari peserta') || !str_contains($registrationHtml, htmlspecialchars($sample['nama_lengkap']))) {
        throw new RuntimeException('Filter daftar peserta tidak mengembalikan data contoh.');
    }
    echo "PASS  Filter daftar peserta pada database aktual.\n";
} else {
    echo "SKIP  Belum ada registrasi untuk integration check.\n";
}

$_GET = ['status' => 'Diarsipkan'];
require_once dirname(__DIR__) . '/src/Controllers/ReportController.php';
ob_start();
(new ReportController())->index();
$reportHtml = ob_get_clean();

if (!str_contains($reportHtml, 'Terapkan Filter') || !str_contains($reportHtml, 'value="Diarsipkan" selected')) {
    throw new RuntimeException('Filter laporan tidak dirender dengan benar.');
}
echo "PASS  Filter dan ringkasan laporan pada database aktual.\n";
