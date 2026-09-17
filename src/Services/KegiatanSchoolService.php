<?php

class KegiatanSchoolService
{
    public static function parse(string $input): array
    {
        $names = [];
        foreach (preg_split('/\R/u', $input) as $line) {
            $name = trim($line);
            if ($name === '') continue;
            if (mb_strlen($name) > 200) throw new RuntimeException('Nama sekolah maksimal 200 karakter.');
            $names[mb_strtolower($name)] = $name;
        }
        if (count($names) > 500) throw new RuntimeException('Maksimal 500 sekolah per kegiatan.');
        return array_values($names);
    }

    public static function save(PDO $pdo, int $id, string $input): void
    {
        $names = self::parse($input);
        $pdo->prepare('DELETE FROM kegiatan_schools WHERE kegiatan_id = ?')->execute([$id]);
        $stmt = $pdo->prepare('INSERT INTO kegiatan_schools (kegiatan_id, name, sort_order) VALUES (?, ?, ?)');
        foreach ($names as $order => $name) $stmt->execute([$id, $name, $order]);
    }

    public static function options(PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare('SELECT name FROM kegiatan_schools WHERE kegiatan_id = ? ORDER BY sort_order, name');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function valid(PDO $pdo, int $id, string $name): bool
    {
        $options = self::options($pdo, $id);
        return $options === [] || in_array($name, $options, true);
    }
}
