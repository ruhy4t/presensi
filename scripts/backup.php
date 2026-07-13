<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';

$configuredDir = (string) envValue('BACKUP_DIR', 'var/backups');
$backupDir = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $configuredDir)
    ? $configuredDir
    : dirname(__DIR__) . '/' . trim($configuredDir, '/\\');

if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    throw new RuntimeException('Tidak dapat membuat direktori backup.');
}

$timestamp = date('Ymd_His');
$filename = 'presensi_' . $timestamp . '.sql';
$temporaryPath = $backupDir . DIRECTORY_SEPARATOR . $filename . '.tmp';
$finalPath = $backupDir . DIRECTORY_SEPARATOR . $filename;
$handle = fopen($temporaryPath, 'xb');
if ($handle === false) {
    throw new RuntimeException('Tidak dapat membuat file backup.');
}

try {
    fwrite($handle, "-- Presensi database backup\n-- Generated: " . date(DATE_ATOM) . "\n\n");
    fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';
        $createRow = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_NUM);
        if (!$createRow) {
            continue;
        }

        fwrite($handle, "DROP TABLE IF EXISTS {$quotedTable};\n{$createRow[1]};\n\n");
        $rows = $pdo->query("SELECT * FROM {$quotedTable}");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(
                static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`',
                array_keys($row)
            );
            $values = array_map(
                static fn(mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
                array_values($row)
            );
            fwrite($handle, "INSERT INTO {$quotedTable} (" . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
        }
        fwrite($handle, "\n");
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
    $handle = null;

    if (!rename($temporaryPath, $finalPath)) {
        throw new RuntimeException('Gagal menyelesaikan file backup.');
    }
} catch (Throwable $e) {
    if (is_resource($handle)) {
        fclose($handle);
    }
    if (is_file($temporaryPath)) {
        unlink($temporaryPath);
    }
    throw $e;
}

$retentionDays = max(1, (int) envValue('BACKUP_RETENTION_DAYS', 14));
$cutoff = time() - ($retentionDays * 86400);
foreach (glob($backupDir . DIRECTORY_SEPARATOR . 'presensi_*.sql') ?: [] as $oldBackup) {
    if ($oldBackup !== $finalPath && is_file($oldBackup) && filemtime($oldBackup) < $cutoff) {
        unlink($oldBackup);
    }
}

echo "Backup selesai: {$finalPath}\n";
