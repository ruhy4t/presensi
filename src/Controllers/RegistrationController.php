<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Services/ListFilterService.php';
require_once __DIR__ . '/../Services/KegiatanStatusService.php';

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
        $filters = [
            'q' => ListFilterService::search($_GET['q'] ?? ''),
            'status' => ListFilterService::registrationStatus($_GET['status'] ?? '')
        ];

        $where = ['pr.kegiatan_id = :kegiatan_id'];
        $params = [':kegiatan_id' => (int) $kegiatanId];

        if ($filters['q'] !== '') {
            $searchValue = ListFilterService::like($filters['q']);
            $where[] = "(
                p.nama_lengkap LIKE :search_name OR p.nik LIKE :search_nik OR p.nip LIKE :search_nip
                OR p.jabatan LIKE :search_job OR p.unit_kerja LIKE :search_unit
                OR p.hp LIKE :search_phone OR p.email LIKE :search_email OR pr.token_code LIKE :search_token
            )";
            foreach (['name', 'nik', 'nip', 'job', 'unit', 'phone', 'email', 'token'] as $field) {
                $params[':search_' . $field] = $searchValue;
            }
        }

        if ($filters['status'] !== '') {
            $where[] = 'pr.status = :status';
            $params[':status'] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM participant_registrations pr
            INNER JOIN participants p ON p.id = pr.participant_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
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
            WHERE {$whereSql}
            ORDER BY pr.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, $name === ':kegiatan_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
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

        $summaryStmt = $pdo->prepare("
            SELECT COUNT(*) AS total,
                   SUM(status = 'attended') AS attended,
                   SUM(status = 'registered') AS registered
            FROM participant_registrations
            WHERE kegiatan_id = ?
        ");
        $summaryStmt->execute([$kegiatanId]);
        $registrationSummary = $summaryStmt->fetch() ?: ['total' => 0, 'attended' => 0, 'registered' => 0];

        require __DIR__ . '/../Views/registrations.php';
    }

    public function print($registrationId)
    {
        global $pdo;

        $user = AuthMiddleware::user();
        KegiatanStatusService::ensureEndDateColumn($pdo);

        $stmt = $pdo->prepare("
            SELECT pr.*, p.*, k.nama_kegiatan, k.tanggal_pelaksanaan, k.tanggal_selesai, k.waktu_pelaksanaan, k.tempat_pelaksanaan,
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
