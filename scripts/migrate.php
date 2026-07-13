<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/sql.php';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(255) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$appliedLookup = array_fill_keys($applied, true);
$files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
sort($files, SORT_STRING);
$ran = 0;

foreach ($files as $file) {
    $migration = basename($file);
    if (isset($appliedLookup[$migration])) {
        echo "SKIP  {$migration}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Tidak dapat membaca migration: {$migration}");
    }

    echo "RUN   {$migration}\n";
    foreach (splitSqlStatements($sql) as $statement) {
        $result = $pdo->query($statement);
        if ($result instanceof PDOStatement) {
            $result->closeCursor();
        }
    }

    $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
    $stmt->execute([$migration]);
    $ran++;
}

echo $ran === 0 ? "Database sudah mutakhir.\n" : "Selesai: {$ran} migration dijalankan.\n";
