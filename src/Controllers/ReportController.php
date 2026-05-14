<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Services/KegiatanStatusService.php';

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

        if ($user['role'] === 'admin') {
            $stmt = $pdo->prepare("SELECT k.*, u.fullname as creator_name, (SELECT COUNT(*) FROM attendances a WHERE a.kegiatan_id = k.id) as attendance_count FROM kegiatan k LEFT JOIN users u ON k.user_id = u.id WHERE k.status != 'Dihapus' ORDER BY k.created_at DESC");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT k.*, u.fullname as creator_name, (SELECT COUNT(*) FROM attendances a WHERE a.kegiatan_id = k.id) as attendance_count FROM kegiatan k LEFT JOIN users u ON k.user_id = u.id WHERE k.user_id = ? AND k.status != 'Dihapus' ORDER BY k.created_at DESC");
            $stmt->execute([$user['id']]);
        }
        $kegiatanList = $stmt->fetchAll();

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
        $stmt2 = $pdo->prepare("SELECT * FROM attendances WHERE kegiatan_id = ? ORDER BY created_at ASC");
        $stmt2->execute([$kegiatanId]);
        $attendanceData = $stmt2->fetchAll();

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
        $stmt2 = $pdo->prepare("SELECT * FROM attendances WHERE kegiatan_id = ? ORDER BY created_at ASC");
        $stmt2->execute([$kegiatanId]);
        $attendanceData = $stmt2->fetchAll();

        $format = $_GET['format'] ?? 'csv';
        $filename = "Presensi_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $kegiatan['nama_kegiatan']) . "_" . date('Ymd');

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['No', 'Nama Lengkap', 'Instansi', 'Jabatan', 'No. HP / WA', 'Waktu Hadir']);
            $no = 1;
            foreach ($attendanceData as $row) {
                fputcsv($output, [
                    $no++,
                    $row['nama'],
                    $row['instansi'],
                    $row['jabatan'],
                    $row['hp'],
                    $row['created_at']
                ]);
            }
            fclose($output);
            exit;
        } elseif ($format === 'xls') {
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=" . $filename . ".xls");
            echo '<table border="1">';
            echo '<tr><th>No</th><th>Nama Lengkap</th><th>Instansi</th><th>Jabatan</th><th>No. HP / WA</th><th>Waktu Hadir</th></tr>';
            $no = 1;
            foreach ($attendanceData as $row) {
                echo '<tr>';
                echo '<td>' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
                echo '<td>' . htmlspecialchars($row['instansi']) . '</td>';
                echo '<td>' . htmlspecialchars($row['jabatan']) . '</td>';
                echo '<td>' . htmlspecialchars($row['hp']) . '</td>';
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
