<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$checks = [];
require_once dirname(__DIR__) . '/config/env.php';

function healthCheck(string $label, callable $callback): void
{
    global $checks;
    try {
        $detail = $callback();
        $checks[] = ['ok' => true, 'label' => $label, 'detail' => (string) ($detail ?? 'OK')];
    } catch (Throwable $e) {
        $checks[] = ['ok' => false, 'label' => $label, 'detail' => $e->getMessage()];
    }
}

healthCheck('PHP 8.1+', function (): string {
    if (PHP_VERSION_ID < 80100) {
        throw new RuntimeException('Versi aktif: ' . PHP_VERSION);
    }
    return PHP_VERSION;
});

healthCheck('Ekstensi PHP', function (): string {
    $required = ['pdo_mysql', 'fileinfo', 'json'];
    $missing = array_values(array_filter($required, static fn(string $extension): bool => !extension_loaded($extension)));
    if ($missing !== []) {
        throw new RuntimeException('Tidak tersedia: ' . implode(', ', $missing));
    }
    return implode(', ', $required);
});

healthCheck('Proteksi upload', function (): string {
    $protection = dirname(__DIR__) . '/public/uploads/.htaccess';
    if (!is_file($protection)) {
        throw new RuntimeException('.htaccess upload tidak ditemukan');
    }
    return 'aktif';
});

healthCheck('Koneksi database', function (): string {
    global $pdo;
    $host = (string) envValue('DB_HOST', 'localhost');
    $port = (int) envValue('DB_PORT', 3306);
    $database = (string) envValue('DB_NAME', 'daftar_hadir_db');
    $username = (string) envValue('DB_USER', 'root');
    $password = (string) envValue('DB_PASS', '');
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
});

healthCheck('Tabel aplikasi', function (): string {
    global $pdo;
    if (!isset($pdo)) {
        throw new RuntimeException('Koneksi database belum tersedia');
    }
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $required = ['users', 'kegiatan', 'attendances', 'audit_logs', 'participants', 'participant_registrations'];
    $missing = array_values(array_diff($required, $tables));
    if ($missing !== []) {
        throw new RuntimeException('Tabel belum tersedia: ' . implode(', ', $missing));
    }
    return count($required) . ' tabel inti tersedia';
});

$failed = 0;
foreach ($checks as $check) {
    echo ($check['ok'] ? 'PASS' : 'FAIL') . '  ' . $check['label'] . ': ' . $check['detail'] . "\n";
    if (!$check['ok']) {
        $failed++;
    }
}

exit($failed === 0 ? 0 : 1);
