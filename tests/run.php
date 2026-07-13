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

echo "\nResult: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
