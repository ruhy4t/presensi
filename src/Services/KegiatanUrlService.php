<?php

class KegiatanUrlService
{
    private static $tokenColumnChecked = false;

    public static function ensureTokenColumn(PDO $pdo)
    {
        if (self::$tokenColumnChecked) {
            return;
        }

        $stmt = $pdo->prepare("SHOW COLUMNS FROM kegiatan LIKE 'attendance_token'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE kegiatan ADD COLUMN attendance_token VARCHAR(80) NULL DEFAULT NULL AFTER status");
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'kegiatan'
              AND INDEX_NAME = 'uq_kegiatan_attendance_token'
        ");
        $stmt->execute();
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("CREATE UNIQUE INDEX uq_kegiatan_attendance_token ON kegiatan (attendance_token)");
        }

        self::$tokenColumnChecked = true;
    }

    public static function generateUniqueToken(PDO $pdo)
    {
        self::ensureTokenColumn($pdo);

        do {
            $token = bin2hex(random_bytes(24));
            $stmt = $pdo->prepare("SELECT id FROM kegiatan WHERE attendance_token = ? LIMIT 1");
            $stmt->execute([$token]);
        } while ($stmt->fetch());

        return $token;
    }

    public static function attendancePath(array $kegiatan)
    {
        if (!empty($kegiatan['attendance_token'])) {
            return '/attendance?token=' . urlencode($kegiatan['attendance_token']);
        }

        return '/attendance?id=' . urlencode((string) ($kegiatan['id'] ?? ''));
    }
}
