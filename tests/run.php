<?php

declare(strict_types=1);

$passed = 0;
$failed = 0;

function test(string $description, callable $callback): void
{
    global $passed, $failed;
    try {
        $callback();
        $passed++;
        echo "PASS  {$description}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL  {$description}\n      {$e->getMessage()}\n";
    }
}

function assertSameValue(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
        );
    }
}

function assertContainsText(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException("Missing expected text: {$needle}");
    }
}

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/src/Services/KegiatanStatusService.php';
require_once dirname(__DIR__) . '/src/Services/KegiatanUrlService.php';
require_once dirname(__DIR__) . '/src/Services/ListFilterService.php';
require_once dirname(__DIR__) . '/src/Services/AttendanceLocationService.php';
require_once dirname(__DIR__) . '/src/Services/WaveScheduleService.php';
require_once dirname(__DIR__) . '/scripts/sql.php';

test('manual active status remains marked as manual', function (): void {
    assertSameValue(1, KegiatanStatusService::manualFlagForStatus('Aktif'));
    assertSameValue(1, KegiatanStatusService::manualFlagForStatus('Non-Aktif'));
});

test('archive and delete statuses do not use the manual active flag', function (): void {
    assertSameValue(0, KegiatanStatusService::manualFlagForStatus('Diarsipkan'));
    assertSameValue(0, KegiatanStatusService::manualFlagForStatus('Dihapus'));
});

test('today is active and other dates are inactive automatically', function (): void {
    $today = KegiatanStatusService::todayDate();
    $tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
    assertSameValue('Aktif', KegiatanStatusService::automaticStatusForDate($today));
    assertSameValue('Non-Aktif', KegiatanStatusService::automaticStatusForDate($tomorrow));
});

test('multi-day kegiatan stays active throughout its date range', function (): void {
    $today = KegiatanStatusService::todayDate();
    $yesterday = (new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
    $tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
    assertSameValue('Aktif', KegiatanStatusService::automaticStatusForDate($yesterday, $tomorrow));
    assertSameValue('Non-Aktif', KegiatanStatusService::automaticStatusForDate($tomorrow, null));
});

test('schedule validation rejects reversed date ranges', function (): void {
    $valid = KegiatanStatusService::normalizeRange('2026-07-13', '2026-07-15');
    assertSameValue('2026-07-13', $valid['start']);
    assertSameValue('2026-07-15', $valid['end']);
    assertSameValue(null, $valid['error']);

    $invalid = KegiatanStatusService::normalizeRange('2026-07-15', '2026-07-13');
    assertContainsText('tidak boleh sebelum', $invalid['error']);
});

test('single-day schedule remains backward compatible', function (): void {
    $range = KegiatanStatusService::normalizeRange('2026-07-13', '2026-07-13');
    assertSameValue('2026-07-13', $range['start']);
    assertSameValue(null, $range['end']);
});

test('new kegiatan uses opaque attendance URL', function (): void {
    assertSameValue('/attendance?token=abc123', KegiatanUrlService::attendancePath([
        'id' => 9,
        'attendance_token' => 'abc123',
    ]));
});

test('legacy kegiatan URL remains supported', function (): void {
    assertSameValue('/attendance?id=9', KegiatanUrlService::attendancePath([
        'id' => 9,
        'attendance_token' => null,
    ]));
});

test('initial schema covers every table used by the application', function (): void {
    $schema = file_get_contents(dirname(__DIR__) . '/migrations/2026_05_09_initial_schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Initial schema cannot be read.');
    }
    foreach (['users', 'kegiatan', 'attendances', 'audit_logs'] as $table) {
        assertContainsText("CREATE TABLE IF NOT EXISTS {$table}", $schema);
    }
});

test('sensitive and generated files stay ignored by Git', function (): void {
    $gitignore = file_get_contents(dirname(__DIR__) . '/.gitignore');
    if ($gitignore === false) {
        throw new RuntimeException('.gitignore cannot be read.');
    }
    assertContainsText('.env', $gitignore);
    assertContainsText('/var/backups/', $gitignore);
    assertContainsText('public/uploads/*', $gitignore);
});

test('migration parser preserves semicolons inside SQL strings', function (): void {
    $statements = splitSqlStatements("SET @sql = 'SELECT 1; SELECT 2';\nSELECT 3;");
    assertSameValue(2, count($statements));
    assertContainsText("'SELECT 1; SELECT 2'", $statements[0]);
    assertSameValue('SELECT 3', $statements[1]);
});

test('list filters accept only known statuses', function (): void {
    assertSameValue('attended', ListFilterService::registrationStatus('attended'));
    assertSameValue('', ListFilterService::registrationStatus('unknown'));
    assertSameValue('cancelled', ListFilterService::registrationStatus('cancelled'));
    assertSameValue('Diarsipkan', ListFilterService::kegiatanStatus('Diarsipkan'));
    assertSameValue('', ListFilterService::kegiatanStatus('Dihapus'));
});

test('list filters reject impossible dates and normalize search text', function (): void {
    assertSameValue('2026-07-13', ListFilterService::date('2026-07-13'));
    assertSameValue('', ListFilterService::date('2026-02-31'));
    assertSameValue('peserta', ListFilterService::search("  pes\x00erta  "));
});

test('LIKE filters escape user wildcard characters', function (): void {
    assertSameValue('%100\\%\\_hadir%', ListFilterService::like('100%_hadir'));
});

test('biodata form requires home address', function (): void {
    $view = file_get_contents(dirname(__DIR__) . '/src/Views/attendance_form.php');
    $controller = file_get_contents(dirname(__DIR__) . '/src/Controllers/AttendanceController.php');
    if ($view === false || $controller === false) {
        throw new RuntimeException('Biodata files cannot be read.');
    }
    assertContainsText('x-model="form.alamat_rumah"', $view);
    assertContainsText("'alamat_rumah' => 'Alamat rumah'", $controller);
});

test('shared sidebar is used by all authenticated menus', function (): void {
    foreach (['dashboard.php', 'users.php', 'reports.php'] as $viewName) {
        $view = file_get_contents(dirname(__DIR__) . '/src/Views/' . $viewName);
        if ($view === false) {
            throw new RuntimeException("Cannot read {$viewName}");
        }
        assertContainsText("partials/sidebar.php", $view);
    }
});

test('attendance writes lock identity rows before inserting', function (): void {
    $controller = file_get_contents(dirname(__DIR__) . '/src/Controllers/AttendanceController.php');
    if ($controller === false) {
        throw new RuntimeException('Attendance controller cannot be read.');
    }
    assertContainsText('LIMIT 1 FOR UPDATE', $controller);
    assertContainsText('uq_attendances_kegiatan_identity', file_get_contents(dirname(__DIR__) . '/migrations/2026_07_13_attendance_unique.sql'));
});

test('location distance calculation is accurate enough for attendance radius', function (): void {
    $distance = AttendanceLocationService::distanceMeters(-6.597147, 106.806039, -6.597047, 106.806039);
    if ($distance < 10 || $distance > 12) {
        throw new RuntimeException('Unexpected location distance: ' . $distance);
    }
});

test('radius validation rejects out-of-range and inaccurate locations', function (): void {
    $kegiatan = [
        'radius_enabled' => 1,
        'latitude' => -6.597147,
        'longitude' => 106.806039,
        'radius_meters' => 100,
    ];
    assertSameValue(null, AttendanceLocationService::validateConfiguration(true, -6.597147, 106.806039, 100));
    assertContainsText('10 sampai 5.000', AttendanceLocationService::validateConfiguration(true, -6.597147, 106.806039, 5));

    $inside = AttendanceLocationService::evaluate($kegiatan, -6.597047, 106.806039, 15);
    assertSameValue(true, $inside['ok']);

    $outside = AttendanceLocationService::evaluate($kegiatan, -6.587147, 106.806039, 15);
    assertSameValue(false, $outside['ok']);
    assertContainsText('di luar radius', $outside['message']);

    $inaccurate = AttendanceLocationService::evaluate($kegiatan, -6.597147, 106.806039, 150);
    assertSameValue(false, $inaccurate['ok']);
});

test('radius and wave migration remains phpMyAdmin importable and indexed', function (): void {
    $migration = file_get_contents(dirname(__DIR__) . '/migrations/2026_07_29_radius_gelombang.sql');
    if ($migration === false) {
        throw new RuntimeException('Radius migration cannot be read.');
    }
    foreach (['radius_enabled', 'kegiatan_gelombang', 'gelombang_id', 'attendance_distance_meters'] as $needle) {
        assertContainsText($needle, $migration);
    }
    assertContainsText('idx_gelombang_kegiatan_active', $migration);
    assertContainsText('idx_attendances_gelombang', $migration);
});

test('legacy participant tokens remain usable when an old activity has no invitation number', function (): void {
    $controller = file_get_contents(dirname(__DIR__) . '/src/Controllers/AttendanceController.php');
    $attendanceView = file_get_contents(dirname(__DIR__) . '/src/Views/attendance_form.php');
    if ($controller === false || $attendanceView === false) {
        throw new RuntimeException('Attendance source cannot be read.');
    }

    assertContainsText("NULLIF(TRIM(k.nomor_surat_undangan), '') IS NULL", $controller);
    assertContainsText("TRIM(k.nomor_surat_undangan) = '-'", $controller);
    assertContainsText('$requiresInvitationNumber', $attendanceView);
});

test('printed attendance moves wave information above the participant table', function (): void {
    $printView = file_get_contents(dirname(__DIR__) . '/src/Views/print_attendance.php');
    $reportController = file_get_contents(dirname(__DIR__) . '/src/Controllers/ReportController.php');
    if ($printView === false || $reportController === false) {
        throw new RuntimeException('Printed attendance sources cannot be read.');
    }

    if (str_contains($printView, '<th>Sumber</th>')
        || str_contains($printView, "=== 'admin' ? 'Admin' : 'Peserta'")
        || str_contains($printView, 'class="col-gelombang"')) {
        throw new RuntimeException('Source or wave information is still rendered as a participant table column.');
    }
    assertContainsText("\$kegiatan['gelombang_enabled']", $printView);
    assertContainsText("implode(', ', \$gelombangNames)", $printView);
    assertContainsText('SELECT nama', $reportController);
    assertContainsText('FROM kegiatan_gelombang', $reportController);
    assertContainsText('class="col-ttd">Tanda Tangan', $printView);
});

test('application version is visible in authenticated and public interfaces', function (): void {
    $appConfig = file_get_contents(dirname(__DIR__) . '/config/app.php');
    $sidebar = file_get_contents(dirname(__DIR__) . '/src/Views/partials/sidebar.php');
    $attendanceView = file_get_contents(dirname(__DIR__) . '/src/Views/attendance_form.php');
    assertContainsText("APP_VERSION = '1.2.0'", $appConfig);
    assertContainsText('APP_VERSION', $sidebar);
    assertContainsText('APP_VERSION', $attendanceView);
});

test('wave schedules validate dates, times, quota, and duplicate names', function (): void {
    $valid = [[
        'nama' => 'Gelombang 1',
        'tanggal' => '2026-08-05',
        'waktu_mulai' => '08:00',
        'waktu_selesai' => '12:00',
        'presensi_mulai' => '07:30',
        'presensi_selesai' => '12:00',
        'kuota' => 50,
    ]];
    assertSameValue(null, WaveScheduleService::validate($valid));

    $duplicate = array_merge($valid, [array_merge($valid[0], ['tanggal' => '2026-08-06'])]);
    assertContainsText('tidak boleh sama', WaveScheduleService::validate($duplicate));

    $invalidTime = $valid;
    $invalidTime[0]['presensi_selesai'] = '07:00';
    assertContainsText('harus setelah', WaveScheduleService::validate($invalidTime));
});

test('event range and participant confirmation follow wave schedule', function (): void {
    $waves = [
        ['tanggal' => '2026-08-06'],
        ['tanggal' => '2026-08-05'],
    ];
    assertSameValue(['start' => '2026-08-05', 'end' => '2026-08-06'], WaveScheduleService::eventRange($waves));

    $wave = [
        'tanggal' => '2026-08-05',
        'presensi_mulai' => '07:30',
        'presensi_selesai' => '12:00',
    ];
    $inside = WaveScheduleService::timing($wave, new DateTimeImmutable('2026-08-05 08:00:00', new DateTimeZone('Asia/Jakarta')));
    $outside = WaveScheduleService::timing($wave, new DateTimeImmutable('2026-08-05 13:00:00', new DateTimeZone('Asia/Jakarta')));
    assertSameValue(true, $inside['can_confirm']);
    assertSameValue(false, $outside['can_confirm']);
});

test('admin attendance corrections require audit and preserve cancelled records', function (): void {
    $controller = file_get_contents(dirname(__DIR__) . '/src/Controllers/RegistrationController.php');
    $migration = file_get_contents(dirname(__DIR__) . '/migrations/2026_07_29_wave_schedule_admin.sql');
    if ($controller === false || $migration === false) {
        throw new RuntimeException('Admin attendance files cannot be read.');
    }
    foreach (['move_wave', 'admin_confirm', 'cancel_attendance', 'attendance_adjustments'] as $needle) {
        assertContainsText($needle, $controller . $migration);
    }
    assertContainsText("record_status = 'cancelled'", $controller);
    assertContainsText('confirmation_source', $migration);
    assertContainsText('CONVERT(p.nama_lengkap USING utf8mb4) COLLATE utf8mb4_unicode_ci', $migration);
    assertContainsText('CONVERT(a.nama USING utf8mb4) COLLATE utf8mb4_unicode_ci', $migration);
});

echo "\nResult: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
