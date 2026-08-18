<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Services/ListFilterService.php';

class ParticipantController
{
    public function __construct()
    {
        AuthMiddleware::check();
    }

    public function index(): void
    {
        global $pdo;

        $user = AuthMiddleware::user();
        $perPage = 20;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'q' => ListFilterService::search($_GET['q'] ?? ''),
            'status' => ListFilterService::registrationStatus($_GET['status'] ?? '')
        ];

        $where = [];
        $params = [];
        if (($user['role'] ?? '') !== 'admin') {
            $where[] = 'k.user_id = :user_id';
            $params[':user_id'] = (int) $user['id'];
        }
        if ($filters['q'] !== '') {
            $where[] = "(
                p.nama_lengkap LIKE :search_name OR p.nik LIKE :search_nik
                OR p.nip LIKE :search_nip OR p.unit_kerja LIKE :search_unit
                OR k.nama_kegiatan LIKE :search_activity
            )";
            $search = ListFilterService::like($filters['q']);
            foreach (['name', 'nik', 'nip', 'unit', 'activity'] as $field) {
                $params[':search_' . $field] = $search;
            }
        }
        if ($filters['status'] !== '') {
            $where[] = 'pr.status = :status';
            $params[':status'] = $filters['status'];
        }
        $whereSql = $where === [] ? '1 = 1' : implode(' AND ', $where);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM participant_registrations pr
            INNER JOIN participants p ON p.id = pr.participant_id
            INNER JOIN kegiatan k ON k.id = pr.kegiatan_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $totalRows = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("
            SELECT pr.id AS registration_id, pr.status, pr.biodata_submitted_at,
                   pr.attendance_confirmed_at, p.id AS participant_id, p.nama_lengkap,
                   p.nik, p.nip, p.jabatan, p.unit_kerja, p.hp, p.email,
                   k.id AS kegiatan_id, k.nama_kegiatan, k.tanggal_pelaksanaan,
                   k.tanggal_selesai, k.tempat_pelaksanaan,
                   kg.nama AS gelombang_nama
            FROM participant_registrations pr
            INNER JOIN participants p ON p.id = pr.participant_id
            INNER JOIN kegiatan k ON k.id = pr.kegiatan_id
            LEFT JOIN kegiatan_gelombang kg ON kg.id = pr.gelombang_id
            WHERE {$whereSql}
            ORDER BY k.tanggal_pelaksanaan DESC, pr.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, $name === ':user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $participantHistory = $stmt->fetchAll();

        $summaryStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT pr.participant_id) AS participants,
                   COUNT(*) AS registrations,
                   SUM(pr.status = 'attended') AS attended
            FROM participant_registrations pr
            INNER JOIN kegiatan k ON k.id = pr.kegiatan_id
            WHERE " . (($user['role'] ?? '') === 'admin' ? '1 = 1' : 'k.user_id = ?')
        );
        $summaryStmt->execute(($user['role'] ?? '') === 'admin' ? [] : [(int) $user['id']]);
        $participantSummary = $summaryStmt->fetch() ?: ['participants' => 0, 'registrations' => 0, 'attended' => 0];

        $pagination = [
            'page' => $page,
            'total' => $totalRows,
            'total_pages' => $totalPages,
            'from' => $totalRows === 0 ? 0 : $offset + 1,
            'to' => min($offset + $perPage, $totalRows)
        ];

        require __DIR__ . '/../Views/participants.php';
    }
}
