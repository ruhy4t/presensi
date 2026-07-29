<?php

class WaveScheduleService
{
    public static function validate(array $waves): ?string
    {
        if ($waves === []) {
            return 'Isi minimal satu jadwal gelombang.';
        }

        $seenNames = [];
        foreach ($waves as $index => $wave) {
            $position = $index + 1;
            if (trim((string) ($wave['nama'] ?? '')) === '') {
                return "Nama gelombang ke-{$position} wajib diisi.";
            }
            $nameKey = function_exists('mb_strtolower')
                ? mb_strtolower(trim((string) $wave['nama']))
                : strtolower(trim((string) $wave['nama']));
            if (isset($seenNames[$nameKey])) {
                return 'Nama gelombang tidak boleh sama.';
            }
            $seenNames[$nameKey] = true;
            if (!self::validDate($wave['tanggal'] ?? null)) {
                return "Tanggal gelombang ke-{$position} tidak valid.";
            }

            foreach (['waktu_mulai', 'waktu_selesai', 'presensi_mulai', 'presensi_selesai'] as $field) {
                if (!self::validTime($wave[$field] ?? null)) {
                    return "Jam pada gelombang ke-{$position} belum lengkap atau tidak valid.";
                }
            }

            if ($wave['waktu_selesai'] <= $wave['waktu_mulai']) {
                return "Jam selesai gelombang ke-{$position} harus setelah jam mulai.";
            }
            if ($wave['presensi_selesai'] <= $wave['presensi_mulai']) {
                return "Batas akhir presensi gelombang ke-{$position} harus setelah waktu pembukaan.";
            }

            $quota = $wave['kuota'] ?? null;
            if ($quota !== null && ($quota < 1 || $quota > 100000)) {
                return "Kuota gelombang ke-{$position} harus antara 1 sampai 100.000.";
            }
        }

        return null;
    }

    public static function eventRange(array $waves): array
    {
        $dates = array_values(array_filter(array_column($waves, 'tanggal')));
        sort($dates);
        $start = $dates[0] ?? null;
        $end = $dates !== [] ? $dates[count($dates) - 1] : null;

        return [
            'start' => $start,
            'end' => $end !== null && $end !== $start ? $end : null,
        ];
    }

    public static function timing(array $wave, ?DateTimeImmutable $now = null): array
    {
        $timezone = new DateTimeZone('Asia/Jakarta');
        $now = $now ? $now->setTimezone($timezone) : new DateTimeImmutable('now', $timezone);
        $date = (string) ($wave['tanggal'] ?? '');
        $open = self::dateTime($date, $wave['presensi_mulai'] ?? null, $timezone);
        $close = self::dateTime($date, $wave['presensi_selesai'] ?? null, $timezone);
        $canConfirm = $open !== null && $close !== null && $now >= $open && $now <= $close;

        return [
            'can_confirm' => $canConfirm,
            'opens_at' => $open,
            'closes_at' => $close,
            'label' => $open && $close
                ? $open->format('d/m/Y H:i') . '–' . $close->format('H:i') . ' WIB'
                : 'jadwal belum ditentukan',
            'is_before' => $open !== null && $now < $open,
            'is_after' => $close !== null && $now > $close,
        ];
    }

    public static function normalizeTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            return null;
        }
        return substr($value, 0, 5);
    }

    private static function validDate(mixed $value): bool
    {
        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function validTime(mixed $value): bool
    {
        return self::normalizeTime($value) !== null;
    }

    private static function dateTime(string $date, mixed $time, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $normalizedTime = self::normalizeTime($time);
        if (!self::validDate($date) || $normalizedTime === null) {
            return null;
        }

        return new DateTimeImmutable($date . ' ' . $normalizedTime, $timezone);
    }
}
