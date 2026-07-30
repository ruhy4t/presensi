<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Services/KegiatanStatusService.php';
require_once __DIR__ . '/../Services/ListFilterService.php';

class ReportController
{

    public function __construct()
    {
        AuthMiddleware::check();
    }

    public function index()
    {
        global $pdo;
        $user = AuthMiddleware::user();

        KegiatanStatusService::syncAutomaticStatuses($pdo);

        $filters = [
            'q' => ListFilterService::search($_GET['q'] ?? ''),
            'status' => ListFilterService::kegiatanStatus($_GET['status'] ?? ''),
            'date_from' => ListFilterService::date($_GET['date_from'] ?? ''),
            'date_to' => ListFilterService::date($_GET['date_to'] ?? '')
        ];
        if ($filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) {
            [$filters['date_from'], $filters['date_to']] = [$filters['date_to'], $filters['date_from']];
        }

        $where = ["k.status != 'Dihapus'"];
        $params = [];

        if ($user['role'] !== 'admin') {
            $where[] = 'k.user_id = :user_id';
            $params[':user_id'] = (int) $user['id'];
        }
        if ($filters['q'] !== '') {
            $searchValue = ListFilterService::like($filters['q']);
            $where[] = '(k.nama_kegiatan LIKE :search_name OR k.tempat_pelaksanaan LIKE :search_place OR u.fullname LIKE :search_creator)';
            $params[':search_name'] = $searchValue;
            $params[':search_place'] = $searchValue;
            $params[':search_creator'] = $searchValue;
        }
        if ($filters['status'] !== '') {
            $where[] = 'k.status = :status';
            $params[':status'] = $filters['status'];
        }
        if ($filters['date_from'] !== '') {
            $where[] = 'COALESCE(k.tanggal_selesai, k.tanggal_pelaksanaan) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if ($filters['date_to'] !== '') {
            $where[] = 'k.tanggal_pelaksanaan <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $pdo->prepare("
            SELECT k.*, u.fullname AS creator_name,
                   (SELECT COUNT(*) FROM attendances a WHERE a.kegiatan_id = k.id AND a.record_status = 'active') AS attendance_count
            FROM kegiatan k
            LEFT JOIN users u ON k.user_id = u.id
            WHERE {$whereSql}
            ORDER BY k.created_at DESC
        ");
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, $name === ':user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $kegiatanList = $stmt->fetchAll();
        $reportSummary = [
            'total_kegiatan' => count($kegiatanList),
            'aktif' => count(array_filter($kegiatanList, static fn(array $row): bool => $row['status'] === 'Aktif')),
            'total_hadir' => array_sum(array_map(static fn(array $row): int => (int) $row['attendance_count'], $kegiatanList))
        ];

        require __DIR__ . '/../Views/reports.php';
    }

    public function print($kegiatanId)
    {
        global $pdo;
        $user = AuthMiddleware::user();

        KegiatanStatusService::syncAutomaticStatuses($pdo);

        // 1. Get Kegiatan & Verify Access
        $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE id = ?");
        $stmt->execute([$kegiatanId]);
        $kegiatan = $stmt->fetch();

        if (!$kegiatan)
            die("Kegiatan tidak ditemukan.");

        // Access Check: Must be owner OR Admin
        if ($user['role'] !== 'admin' && $kegiatan['user_id'] != $user['id']) {
            die("Akses Ditolak.");
        }

        // 2. Get Attendances
        $stmt2 = $pdo->prepare("
            SELECT a.*, kg.nama AS gelombang_nama
            FROM attendances a
            LEFT JOIN kegiatan_gelombang kg ON kg.id = a.gelombang_id
            WHERE a.kegiatan_id = ? AND a.record_status = 'active'
            ORDER BY COALESCE(kg.sort_order, 65535), a.created_at ASC
        ");
        $stmt2->execute([$kegiatanId]);
        $attendanceData = $stmt2->fetchAll();

        $gelombangNames = [];
        if ((int) ($kegiatan['gelombang_enabled'] ?? 0) === 1) {
            $gelombangStmt = $pdo->prepare("
                SELECT nama
                FROM kegiatan_gelombang
                WHERE kegiatan_id = ? AND is_active = 1
                ORDER BY sort_order, id
            ");
            $gelombangStmt->execute([$kegiatanId]);
            $gelombangNames = $gelombangStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // 3. Render Print View
        require __DIR__ . '/../Views/print_attendance.php';
    }

    public function export($kegiatanId)
    {
        global $pdo;
        $user = AuthMiddleware::user();

        KegiatanStatusService::syncAutomaticStatuses($pdo);

        // 1. Get Kegiatan & Verify Access
        $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE id = ?");
        $stmt->execute([$kegiatanId]);
        $kegiatan = $stmt->fetch();

        if (!$kegiatan)
            die("Kegiatan tidak ditemukan.");

        if ($user['role'] !== 'admin' && $kegiatan['user_id'] != $user['id']) {
            die("Akses Ditolak.");
        }

        // 2. Get Attendances
        $stmt2 = $pdo->prepare("
            SELECT a.*, kg.nama AS gelombang_nama
            FROM attendances a
            LEFT JOIN kegiatan_gelombang kg ON kg.id = a.gelombang_id
            WHERE a.kegiatan_id = ? AND a.record_status = 'active'
            ORDER BY COALESCE(kg.sort_order, 65535), a.created_at ASC
        ");
        $stmt2->execute([$kegiatanId]);
        $attendanceData = $stmt2->fetchAll();

        $format = $_GET['format'] ?? 'csv';
        $filename = "Presensi_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $kegiatan['nama_kegiatan']) . "_" . date('Ymd');

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['No', 'Nama Lengkap', 'Instansi', 'Jabatan', 'No. HP / WA', 'Gelombang', 'Sumber Konfirmasi', 'Jarak (meter)', 'Akurasi GPS (meter)', 'Waktu Hadir']);
            $no = 1;
            foreach ($attendanceData as $row) {
                fputcsv($output, [
                    $no++,
                    $row['nama'],
                    $row['instansi'],
                    $row['jabatan'],
                    $row['hp'],
                    $row['gelombang_nama'] ?? '',
                    ($row['confirmation_source'] ?? 'participant') === 'admin' ? 'Admin' : 'Peserta',
                    $row['distance_meters'] ?? '',
                    $row['accuracy_meters'] ?? '',
                    $row['created_at']
                ]);
            }
            fclose($output);
            exit;
        } elseif ($format === 'xls') {
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=" . $filename . ".xls");
            echo '<table border="1">';
            echo '<tr><th>No</th><th>Nama Lengkap</th><th>Instansi</th><th>Jabatan</th><th>No. HP / WA</th><th>Gelombang</th><th>Sumber Konfirmasi</th><th>Jarak (meter)</th><th>Akurasi GPS (meter)</th><th>Waktu Hadir</th></tr>';
            $no = 1;
            foreach ($attendanceData as $row) {
                echo '<tr>';
                echo '<td>' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
                echo '<td>' . htmlspecialchars($row['instansi']) . '</td>';
                echo '<td>' . htmlspecialchars($row['jabatan']) . '</td>';
                echo '<td>' . htmlspecialchars($row['hp']) . '</td>';
                echo '<td>' . htmlspecialchars($row['gelombang_nama'] ?? '') . '</td>';
                echo '<td>' . (($row['confirmation_source'] ?? 'participant') === 'admin' ? 'Admin' : 'Peserta') . '</td>';
                echo '<td>' . htmlspecialchars((string) ($row['distance_meters'] ?? '')) . '</td>';
                echo '<td>' . htmlspecialchars((string) ($row['accuracy_meters'] ?? '')) . '</td>';
                echo '<td>' . $row['created_at'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            exit;
        } else {
            die("Format tidak didukung.");
        }
    }
}
