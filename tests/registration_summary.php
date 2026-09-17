<?php

require_once dirname(__DIR__) . '/src/Services/RegistrationSummaryService.php';

function summaryTestDatabase(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE participant_registrations (kegiatan_id INTEGER, status TEXT)');
    return $pdo;
}

test('empty activity list requires no registration table query', function (): void {
    $pdo = new PDO('sqlite::memory:');
    assertSameValue([], RegistrationSummaryService::attach($pdo, []));
});

test('batched summaries match original counts and preserve activity scope and order', function (): void {
    $pdo = summaryTestDatabase();
    $pdo->exec("INSERT INTO participant_registrations VALUES
        (1, 'registered'), (1, 'attended'), (1, 'attended'), (1, 'cancelled'),
        (3, 'cancelled'), (99, 'attended')");
    $activities = [
        ['id' => 3, 'nama_kegiatan' => 'Cancelled only'],
        ['id' => 2, 'nama_kegiatan' => 'Empty'],
        ['id' => 1, 'nama_kegiatan' => 'Mixed', 'attendance_count' => 7],
    ];
    $expected = $activities;
    foreach ($expected as &$activity) {
        $stmt = $pdo->prepare("SELECT
            (SELECT COUNT(*) FROM participant_registrations WHERE kegiatan_id = :id) AS registration_count,
            (SELECT COUNT(*) FROM participant_registrations WHERE kegiatan_id = :id AND status = 'attended') AS confirmed_count,
            (SELECT COUNT(*) FROM participant_registrations WHERE kegiatan_id = :id AND status = 'registered') AS unconfirmed_count");
        $stmt->execute([':id' => $activity['id']]);
        foreach ($stmt->fetch(PDO::FETCH_ASSOC) as $field => $value) {
            $activity[$field] = (int) $value;
        }
    }
    unset($activity);
    assertSameValue($expected, RegistrationSummaryService::attach($pdo, $activities));
});

test('summaries handle multiple batches and duplicate activity IDs', function (): void {
    $pdo = summaryTestDatabase();
    $pdo->exec("INSERT INTO participant_registrations VALUES (1, 'attended'), (501, 'registered')");
    $activities = array_map(static fn(int $id): array => ['id' => $id], range(1, 501));
    $activities[] = ['id' => 1];
    $result = RegistrationSummaryService::attach($pdo, $activities);
    assertSameValue(502, count($result));
    assertSameValue(1, $result[0]['confirmed_count']);
    assertSameValue(0, $result[499]['registration_count']);
    assertSameValue(1, $result[500]['unconfirmed_count']);
    assertSameValue($result[0], $result[501]);
});
