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

    public static function activityInitials(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';
        foreach ($words as $word) $initials .= $word[0];
        $initials = substr($initials, 0, 24);
        if ($initials === '' || !preg_match('/^[a-z]/', $initials)) $initials = 'k' . $initials;
        if (in_array($initials, ['login', 'logout', 'dashboard', 'attendance', 'participants', 'users', 'reports', 'registrations', 'biodata', 'report', 's', 'public', 'uploads', 'config', 'src', 'scripts', 'tests', 'deployment', 'migrations'], true)) {
            $initials .= '-k';
        }
        return $initials;
    }

    public static function shortAttendancePath(PDO $pdo, array $kegiatan): string
    {
        $find = $pdo->prepare('SELECT alias FROM kegiatan_link_aliases WHERE kegiatan_id = ?');
        $find->execute([(int) $kegiatan['id']]);
        $existing = $find->fetchColumn();
        if ($existing !== false) return '/' . $existing;
        $base = self::activityInitials((string) ($kegiatan['nama_kegiatan'] ?? 'Kegiatan'));
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $alias = $base . ($attempt === 0 ? '' : '-' . ($attempt + 1));
            try {
                $stmt = $pdo->prepare('INSERT INTO kegiatan_link_aliases (alias, kegiatan_id) VALUES (?, ?)');
                $stmt->execute([$alias, (int) $kegiatan['id']]);
                return '/' . $alias;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') throw $e;
                $find->execute([(int) $kegiatan['id']]);
                $existing = $find->fetchColumn();
                if ($existing !== false) return '/' . $existing;
            }
        }
        // Keep a usable URL even when many activities share the same initials.
        return self::legacyShortAttendancePath($pdo, $kegiatan);
    }

    public static function resolveAlias(PDO $pdo, string $alias): ?array
    {
        if (!preg_match('/^[a-z][a-z0-9-]{0,47}$/D', $alias)) return null;
        $stmt = $pdo->prepare('SELECT k.* FROM kegiatan_link_aliases a INNER JOIN kegiatan k ON k.id = a.kegiatan_id WHERE a.alias = ?');
        $stmt->execute([$alias]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function legacyShortAttendancePath(PDO $pdo, array $kegiatan): string
    {
        $find = $pdo->prepare('SELECT code FROM kegiatan_short_links WHERE kegiatan_id = ?');
        $find->execute([(int) $kegiatan['id']]);
        $code = $find->fetchColumn();
        if ($code !== false) return '/s/' . $code;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = bin2hex(random_bytes(8));
            try {
                $stmt = $pdo->prepare('INSERT INTO kegiatan_short_links (code, kegiatan_id) VALUES (?, ?)');
                $stmt->execute([$code, (int) $kegiatan['id']]);
                return '/s/' . $code;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') throw $e;
                // A concurrent request may have already created this activity's link.
                $find->execute([(int) $kegiatan['id']]);
                $existing = $find->fetchColumn();
                if ($existing !== false) return '/s/' . $existing;
            }
        }
        throw new RuntimeException('Tidak dapat membuat tautan pendek.');
    }

    public static function resolveShortCode(PDO $pdo, string $code): ?array
    {
        if (!preg_match('/^[a-f0-9]{16}$/D', $code)) return null;
        $stmt = $pdo->prepare('SELECT k.* FROM kegiatan_short_links s INNER JOIN kegiatan k ON k.id = s.kegiatan_id WHERE s.code = ?');
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function attendancePath(array $kegiatan)
    {
        if (!empty($kegiatan['attendance_token'])) {
            return '/attendance?token=' . urlencode($kegiatan['attendance_token']);
        }

        return '/attendance?id=' . urlencode((string) ($kegiatan['id'] ?? ''));
    }
}
