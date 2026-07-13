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
    'csrf_token' => str_repeat('a', 64),
];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'presensi.test';
$_SERVER['HTTPS'] = 'off';

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

if (!str_contains($reportHtml, 'APP ABSENSI') || !preg_match('/href="\/reports" class="[^"]*bg-blue-600/', $reportHtml)) {
    throw new RuntimeException('Sidebar laporan tidak tampil atau tidak aktif.');
}
echo "PASS  Sidebar laporan tampil dengan menu aktif.\n";

$_GET = [];
ob_start();
require dirname(__DIR__) . '/src/Views/users.php';
$usersHtml = ob_get_clean();
if (!str_contains($usersHtml, 'APP ABSENSI') || !preg_match('/href="\/users" class="[^"]*bg-blue-600/', $usersHtml)) {
    throw new RuntimeException('Sidebar kelola user tidak tampil atau tidak aktif.');
}
echo "PASS  Sidebar kelola user tampil dengan menu aktif.\n";

ob_start();
require dirname(__DIR__) . '/src/Views/dashboard.php';
$dashboardHtml = ob_get_clean();
if (!str_contains($dashboardHtml, 'Tanggal Selesai') || !preg_match('/href="\/dashboard" class="[^"]*bg-blue-600/', $dashboardHtml)) {
    throw new RuntimeException('Dashboard tidak menampilkan sidebar aktif atau tanggal selesai.');
}
echo "PASS  Dashboard menampilkan sidebar aktif dan rentang tanggal.\n";

$endDateColumn = $pdo->query("SHOW COLUMNS FROM kegiatan LIKE 'tanggal_selesai'")->fetch();
$uniqueAttendanceIndex = $pdo->query("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'attendances'
      AND INDEX_NAME = 'uq_attendances_kegiatan_identity'
      AND NON_UNIQUE = 0
")->fetchColumn();
if (!$endDateColumn || (int) $uniqueAttendanceIndex === 0) {
    throw new RuntimeException('Schema rentang tanggal atau proteksi presensi ganda belum aktif.');
}
echo "PASS  Schema rentang tanggal dan unique attendance aktif.\n";

$attendance = $pdo->query("SELECT kegiatan_id, nama, instansi, jabatan, hp, signature_file FROM attendances LIMIT 1")->fetch();
if ($attendance) {
    $duplicateBlocked = false;
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO attendances (kegiatan_id, nama, instansi, jabatan, hp, signature_file)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(array_values($attendance));
    } catch (PDOException $e) {
        $duplicateBlocked = $e->getCode() === '23000';
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
    if (!$duplicateBlocked) {
        throw new RuntimeException('Database tidak menolak presensi ganda.');
    }
    echo "PASS  Database menolak percobaan presensi kedua.\n";
} else {
    echo "SKIP  Belum ada kehadiran untuk pengujian duplikasi.\n";
}

require_once dirname(__DIR__) . '/src/Controllers/AttendanceController.php';
$timingMethod = new ReflectionMethod(AttendanceController::class, 'getEventTiming');
$attendanceController = new AttendanceController();
$validateBiodata = new ReflectionMethod(AttendanceController::class, 'validateBiodata');
$addressValidation = $validateBiodata->invoke($attendanceController, [
    'nama_lengkap' => 'Peserta Uji',
    'tempat_lahir' => 'Bogor',
    'tanggal_lahir' => '1990-01-01',
    'pangkat_gol' => '',
    'nip' => '',
    'nik' => '3201010101010001',
    'jabatan' => 'Staf',
    'unit_kerja' => 'Unit Uji',
    'alamat_unit_kerja' => 'Alamat Unit',
    'telepon_unit_kerja' => '',
    'alamat_rumah' => '',
    'hp' => '081234567890',
    'email' => 'peserta@example.test',
], 'signature');
if ($addressValidation !== 'Alamat rumah wajib diisi.') {
    throw new RuntimeException('Validasi server belum mewajibkan alamat rumah.');
}
echo "PASS  Server mewajibkan alamat rumah pada biodata.\n";

$today = KegiatanStatusService::todayDate();
$rangeCandidate = $pdo->query("SELECT id FROM kegiatan WHERE status NOT IN ('Diarsipkan', 'Dihapus') LIMIT 1")->fetchColumn();
if ($rangeCandidate) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE kegiatan
            SET tanggal_pelaksanaan = ?, tanggal_selesai = ?, status = 'Non-Aktif', status_manual = 0
            WHERE id = ?
        ");
        $stmt->execute([
            (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d'),
            (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d'),
            $rangeCandidate,
        ]);
        KegiatanStatusService::syncAutomaticStatuses($pdo);
        $stmt = $pdo->prepare('SELECT status FROM kegiatan WHERE id = ?');
        $stmt->execute([$rangeCandidate]);
        if ($stmt->fetchColumn() !== 'Aktif') {
            throw new RuntimeException('Status otomatis tidak aktif di tengah rentang tanggal.');
        }
        $pdo->rollBack();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    echo "PASS  Status database aktif sepanjang rentang tanggal.\n";
}

$timing = $timingMethod->invoke($attendanceController, [
    'tanggal_pelaksanaan' => (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d'),
    'tanggal_selesai' => (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d'),
    'waktu_pelaksanaan' => '08:00',
    'status' => 'Aktif',
    'status_manual' => 0,
]);
if (!$timing['is_event_day'] || !$timing['can_confirm_attendance'] || $timing['is_after_event']) {
    throw new RuntimeException('Kegiatan rentang aktif tidak membuka satu presensi keseluruhan.');
}
echo "PASS  Presensi terbuka sekali selama rentang kegiatan aktif.\n";

$kegiatan = [
    'id' => 999999,
    'nama_kegiatan' => 'Integration Range Preview',
    'tanggal_pelaksanaan' => $today,
    'tanggal_selesai' => (new DateTimeImmutable($today))->modify('+2 days')->format('Y-m-d'),
    'perlu_biodata' => 'Ya',
];
$isBeforeEvent = false;
$isEventDay = true;
$isAfterEvent = false;
$confirmationOpenLabel = 'sekarang';
$needsBiodata = true;
ob_start();
require dirname(__DIR__) . '/src/Views/attendance_form.php';
$attendanceHtml = ob_get_clean();
if (!str_contains($attendanceHtml, 'Alamat Rumah') || !str_contains($attendanceHtml, 's.d.')) {
    throw new RuntimeException('Form biodata rentang belum menampilkan alamat rumah atau tanggal selesai.');
}
echo "PASS  Form biodata merender alamat rumah dan rentang tanggal.\n";
