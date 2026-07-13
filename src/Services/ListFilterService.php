<?php

class ListFilterService
{
    public static function search(mixed $value, int $maxLength = 100): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength)
            : substr($value, 0, $maxLength);
    }

    public static function registrationStatus(mixed $value): string
    {
        $value = (string) $value;
        return in_array($value, ['registered', 'attended'], true) ? $value : '';
    }

    public static function kegiatanStatus(mixed $value): string
    {
        $value = (string) $value;
        return in_array($value, ['Aktif', 'Non-Aktif', 'Diarsipkan'], true) ? $value : '';
    }

    public static function date(mixed $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    public static function like(string $value): string
    {
        return '%' . strtr($value, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
    }
}
