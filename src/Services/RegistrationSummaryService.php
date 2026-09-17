<?php

class RegistrationSummaryService
{
    public static function attach(PDO $pdo, array $kegiatanList): array
    {
        if ($kegiatanList === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map(
            static fn(array $row): int => (int) $row['id'],
            $kegiatanList
        )));
        $summaries = [];
        // Bound placeholders while counting only activities already authorized by the caller.
        foreach (array_chunk($ids, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare("
                SELECT kegiatan_id, COUNT(*) AS registration_count,
                       SUM(CASE WHEN status = 'attended' THEN 1 ELSE 0 END) AS confirmed_count,
                       SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) AS unconfirmed_count
                FROM participant_registrations
                WHERE kegiatan_id IN ({$placeholders})
                GROUP BY kegiatan_id
            ");
            $stmt->execute($chunk);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $summaries[(int) $row['kegiatan_id']] = $row;
            }
        }

        foreach ($kegiatanList as &$kegiatan) {
            $summary = $summaries[(int) $kegiatan['id']] ?? [];
            foreach (['registration_count', 'confirmed_count', 'unconfirmed_count'] as $field) {
                $kegiatan[$field] = (int) ($summary[$field] ?? 0);
            }
        }
        unset($kegiatan);
        return $kegiatanList;
    }
}
