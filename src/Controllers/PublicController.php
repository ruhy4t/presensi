<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/KegiatanStatusService.php';

class PublicController
{

    public function index()
    {
        global $pdo;
        try {
            KegiatanStatusService::autoActivateToday($pdo);

            // Hanya ambil kegiatan yang AKTIF
            $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE status = 'Aktif' ORDER BY created_at DESC");
            $stmt->execute();
            $kegiatanList = $stmt->fetchAll();

            require __DIR__ . '/../Views/home.php';
        } catch (PDOException $e) {
            echo "System Error";
        }
    }
}
