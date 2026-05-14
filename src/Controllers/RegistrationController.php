<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class RegistrationController
{
    public function __construct()
    {
        AuthMiddleware::check();
    }

    public function index($kegiatanId)
    {
        global $pdo;

        $user = AuthMiddleware::user();
        $kegiatan = $this->getKegiatanForUser($kegiatanId, $user);

        if (!$kegiatan) {
            die("Kegiatan tidak ditemukan atau akses ditolak.");
        }

        $perPage = 10;
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM participant_registrations pr
            INNER JOIN participants p ON p.id = pr.participant_id
            WHERE pr.kegiatan_id = ?
        ");
        $countStmt->execute([$kegiatanId]);
        $totalRegistrations = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRegistrations / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT pr.*, p.nama_lengkap, p.nik, p.nip, p.jabatan, p.unit_kerja, p.hp, p.email
            FROM participant_registrations pr
            INNER JOIN participants p ON p.id = pr.participant_id
            WHERE pr.kegiatan_id = :kegiatan_id
            ORDER BY pr.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':kegiatan_id', (int) $kegiatanId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $registrations = $stmt->fetchAll();
        $pagination = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalRegistrations,
            'total_pages' => $totalPages,
            'from' => $totalRegistrations === 0 ? 0 : $offset + 1,
            'to' => min($offset + $perPage, $totalRegistrations)
        ];

        require __DIR__ . '/../Views/registrations.php';
    }

    public function print($registrationId)
    {
        global $pdo;

        $user = AuthMiddleware::user();

        $stmt = $pdo->prepare("
            SELECT pr.*, p.*, k.nama_kegiatan, k.tanggal_pelaksanaan, k.waktu_pelaksanaan, k.tempat_pelaksanaan,
                   k.user_id AS kegiatan_user_id
            FROM participant_registrations pr
            INNER JOIN participants p ON p.id = pr.participant_id
            INNER JOIN kegiatan k ON k.id = pr.kegiatan_id
            WHERE pr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$registrationId]);
        $biodata = $stmt->fetch();

        if (!$biodata || ($user['role'] !== 'admin' && $biodata['kegiatan_user_id'] != $user['id'])) {
            die("Data tidak ditemukan atau akses ditolak.");
        }

        require __DIR__ . '/../Views/print_biodata.php';
    }

    private function getKegiatanForUser($kegiatanId, array $user)
    {
        global $pdo;

        if ($user['role'] === 'admin') {
            $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE id = ? LIMIT 1");
            $stmt->execute([$kegiatanId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$kegiatanId, $user['id']]);
        }

        return $stmt->fetch();
    }
}
