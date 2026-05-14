<?php

class KegiatanStatusService
{
    private static $manualColumnChecked = false;

    public static function ensureManualStatusColumn(PDO $pdo)
    {
        if (self::$manualColumnChecked) {
            return;
        }

        $stmt = $pdo->prepare("SHOW COLUMNS FROM kegiatan LIKE 'status_manual'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE kegiatan ADD COLUMN status_manual TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        }

        self::$manualColumnChecked = true;
    }

    public static function autoActivateToday(PDO $pdo)
    {
        self::syncAutomaticStatuses($pdo);
    }

    public static function syncAutomaticStatuses(PDO $pdo)
    {
        self::ensureManualStatusColumn($pdo);

        $todayDate = self::todayDate();
        $bulanIndo = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember'
        ];
        $todayTimestamp = strtotime($todayDate);
        $todayIndo = date('d', $todayTimestamp) . ' ' . $bulanIndo[date('m', $todayTimestamp)] . ' ' . date('Y', $todayTimestamp);
        $todayIndoNoZero = (int) date('d', $todayTimestamp) . ' ' . $bulanIndo[date('m', $todayTimestamp)] . ' ' . date('Y', $todayTimestamp);

        $stmt = $pdo->prepare("
            UPDATE kegiatan
            SET status = 'Aktif'
            WHERE status = 'Non-Aktif'
              AND status_manual = 0
              AND tanggal_pelaksanaan IN (?, ?, ?)
        ");
        $stmt->execute([$todayDate, $todayIndo, $todayIndoNoZero]);

        $stmt = $pdo->prepare("
            UPDATE kegiatan
            SET status = 'Non-Aktif'
            WHERE status = 'Aktif'
              AND status_manual = 0
              AND tanggal_pelaksanaan IS NOT NULL
              AND tanggal_pelaksanaan != ''
              AND tanggal_pelaksanaan REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
              AND tanggal_pelaksanaan < ?
        ");
        $stmt->execute([$todayDate]);

        $stmt = $pdo->prepare("
            UPDATE kegiatan
            SET status = 'Non-Aktif'
            WHERE status = 'Aktif'
              AND status_manual = 0
              AND tanggal_pelaksanaan IS NOT NULL
              AND tanggal_pelaksanaan != ''
              AND tanggal_pelaksanaan REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
              AND tanggal_pelaksanaan > ?
        ");
        $stmt->execute([$todayDate]);
    }

    public static function manualFlagForStatus($status)
    {
        return in_array($status, ['Aktif', 'Non-Aktif'], true) ? 1 : 0;
    }

    public static function automaticStatusForDate($tanggalPelaksanaan)
    {
        $tanggalPelaksanaan = trim((string) $tanggalPelaksanaan);
        if ($tanggalPelaksanaan === '') {
            return 'Aktif';
        }

        $todayDate = self::todayDate();
        return $tanggalPelaksanaan === $todayDate ? 'Aktif' : 'Non-Aktif';
    }

    public static function todayDate()
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    }
}
