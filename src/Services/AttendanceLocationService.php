<?php

class AttendanceLocationService
{
    public const MIN_RADIUS_METERS = 10;
    public const MAX_RADIUS_METERS = 5000;
    public const MAX_ACCEPTED_ACCURACY_METERS = 250;

    public static function isEnabled(array $kegiatan): bool
    {
        return (int) ($kegiatan['radius_enabled'] ?? 0) === 1;
    }

    public static function validateConfiguration(bool $enabled, mixed $latitude, mixed $longitude, mixed $radius): ?string
    {
        if (!$enabled) {
            return null;
        }

        if (!self::validLatitude($latitude) || !self::validLongitude($longitude)) {
            return 'Koordinat lokasi kegiatan tidak valid.';
        }

        $radiusValue = filter_var($radius, FILTER_VALIDATE_INT);
        if ($radiusValue === false || $radiusValue < self::MIN_RADIUS_METERS || $radiusValue > self::MAX_RADIUS_METERS) {
            return 'Radius kegiatan harus antara 10 sampai 5.000 meter.';
        }

        return null;
    }

    public static function evaluate(array $kegiatan, mixed $latitude, mixed $longitude, mixed $accuracy): array
    {
        if (!self::isEnabled($kegiatan)) {
            return ['ok' => true, 'location' => null];
        }

        if (!self::validLatitude($latitude) || !self::validLongitude($longitude) || !is_numeric($accuracy)) {
            return ['ok' => false, 'message' => 'Lokasi perangkat wajib diaktifkan untuk melakukan presensi.'];
        }

        $accuracyValue = (float) $accuracy;
        $radius = (float) $kegiatan['radius_meters'];
        $maximumAccuracy = min(self::MAX_ACCEPTED_ACCURACY_METERS, max(30.0, $radius));
        if ($accuracyValue < 0 || $accuracyValue > $maximumAccuracy) {
            return [
                'ok' => false,
                'message' => 'Akurasi lokasi belum memadai. Aktifkan GPS dan coba kembali di area terbuka.'
            ];
        }

        $distance = self::distanceMeters(
            (float) $kegiatan['latitude'],
            (float) $kegiatan['longitude'],
            (float) $latitude,
            (float) $longitude
        );
        if ($distance > $radius) {
            return [
                'ok' => false,
                'message' => 'Anda berada di luar radius kegiatan (' . number_format($distance, 0, ',', '.') .
                    ' m dari lokasi; batas ' . number_format($radius, 0, ',', '.') . ' m).'
            ];
        }

        return [
            'ok' => true,
            'location' => [
                'latitude' => round((float) $latitude, 7),
                'longitude' => round((float) $longitude, 7),
                'accuracy' => round($accuracyValue, 2),
                'distance' => round($distance, 2),
            ],
        ];
    }

    public static function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371000.0;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private static function validLatitude(mixed $value): bool
    {
        return is_numeric($value) && (float) $value >= -90 && (float) $value <= 90;
    }

    private static function validLongitude(mixed $value): bool
    {
        return is_numeric($value) && (float) $value >= -180 && (float) $value <= 180;
    }
}
