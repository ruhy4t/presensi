<?php
require_once __DIR__ . '/env.php';

$appTimezone = (string) envValue('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set($appTimezone);
ini_set('date.timezone', $appTimezone);

// Defaults preserve the existing Laragon setup. Production values belong in .env.
defined('DB_HOST') || define('DB_HOST', (string) envValue('DB_HOST', 'localhost'));
defined('DB_PORT') || define('DB_PORT', (int) envValue('DB_PORT', 3306));
defined('DB_NAME') || define('DB_NAME', (string) envValue('DB_NAME', 'daftar_hadir_db'));
defined('DB_USER') || define('DB_USER', (string) envValue('DB_USER', 'root'));
defined('DB_PASS') || define('DB_PASS', (string) envValue('DB_PASS', ''));

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (\PDOException $e) {
    error_log($e->getMessage());
    if (envValue('APP_DEBUG', false)) {
        die("Database Connection Failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    die("Database Connection Failed. Please check config.");
}
