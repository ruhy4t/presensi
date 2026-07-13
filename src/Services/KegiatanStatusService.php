<?php

class KegiatanStatusService
{
    private static $manualColumnChecked = false;
    private static $endDateColumnChecked = false;

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
        self::ensureEndDateColumn($pdo);

        $todayDate = self::todayDate();
        [$todayIndo, $todayIndoNoZero] = self::indonesianDateVariants($todayDate);

        // Preserve support for legacy databases that stored Indonesian date text.
        $stmt = $pdo->prepare("
            UPDATE kegiatan
            SET status = 'Aktif'
            WHERE status = 'Non-Aktif'
              AND status_manual = 0
              AND tanggal_selesai IS NULL
              AND tanggal_pelaksanaan IN (?, ?)
        ");
        $stmt->execute([$todayIndo, $todayIndoNoZero]);

        $stmt = $pdo->prepare("
            UPDATE kegiatan
            SET status = 'Aktif'
            WHERE status = 'Non-Aktif'
              AND status_manual = 0
              AND tanggal_pelaksanaan IS NOT NULL
              AND tanggal_pelaksanaan REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
              AND tanggal_pelaksanaan <= ?
              AND COALESCE(tanggal_selesai, tanggal_pelaksanaan) >= ?
        ");
        $stmt->execute([$todayDate, $todayDate]);

        $stmt = $pdo->prepare("
            UPDATE kegiatan
            SET status = 'Non-Aktif'
            WHERE status = 'Aktif'
              AND status_manual = 0
              AND tanggal_pelaksanaan IS NOT NULL
              AND tanggal_pelaksanaan REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
              AND (
                  tanggal_pelaksanaan > ?
                  OR COALESCE(tanggal_selesai, tanggal_pelaksanaan) < ?
              )
        ");
        $stmt->execute([$todayDate, $todayDate]);
    }

    public static function manualFlagForStatus($status)
    {
        return in_array($status, ['Aktif', 'Non-Aktif'], true) ? 1 : 0;
    }

    public static function automaticStatusForDate($tanggalPelaksanaan, $tanggalSelesai = null)
    {
        $tanggalPelaksanaan = self::normalizeDate($tanggalPelaksanaan);
        if ($tanggalPelaksanaan === null) {
            return 'Aktif';
        }

        $tanggalSelesai = self::normalizeDate($tanggalSelesai) ?? $tanggalPelaksanaan;
        $todayDate = self::todayDate();
        return $tanggalPelaksanaan <= $todayDate && $tanggalSelesai >= $todayDate ? 'Aktif' : 'Non-Aktif';
    }

    public static function normalizeRange($tanggalPelaksanaan, $tanggalSelesai): array
    {
        $startRaw = trim((string) $tanggalPelaksanaan);
        $endRaw = trim((string) $tanggalSelesai);
        $start = self::normalizeDate($startRaw);
        $end = self::normalizeDate($endRaw);

        if ($startRaw !== '' && $start === null) {
            return ['start' => null, 'end' => null, 'error' => 'Tanggal mulai tidak valid.'];
        }
        if ($endRaw !== '' && $end === null) {
            return ['start' => $start, 'end' => null, 'error' => 'Tanggal selesai tidak valid.'];
        }
        if ($end !== null && $start === null) {
            return ['start' => null, 'end' => null, 'error' => 'Tanggal mulai wajib diisi jika tanggal selesai digunakan.'];
        }
        if ($start !== null && $end !== null && $end < $start) {
            return ['start' => $start, 'end' => $end, 'error' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.'];
        }

        return [
            'start' => $start,
            'end' => $end !== null && $end !== $start ? $end : null,
            'error' => null
        ];
    }

    public static function normalizeDate($date): ?string
    {
        $date = trim((string) $date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
    }

    public static function ensureEndDateColumn(PDO $pdo): void
    {
        if (self::$endDateColumnChecked) {
            return;
        }

        $stmt = $pdo->prepare("SHOW COLUMNS FROM kegiatan LIKE 'tanggal_selesai'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE kegiatan ADD COLUMN tanggal_selesai DATE NULL AFTER tanggal_pelaksanaan");
        }
        self::$endDateColumnChecked = true;
    }

    public static function todayDate()
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    }

    private static function indonesianDateVariants(string $date): array
    {
        $months = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];
        $timestamp = strtotime($date);
        $month = $months[date('m', $timestamp)];
        return [
            date('d', $timestamp) . ' ' . $month . ' ' . date('Y', $timestamp),
            (int) date('d', $timestamp) . ' ' . $month . ' ' . date('Y', $timestamp)
        ];
    }
}
