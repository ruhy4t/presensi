<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Services/KegiatanStatusService.php';
require_once __DIR__ . '/../Services/KegiatanUrlService.php';

class KegiatanController
{

    public function __construct()
    {
        AuthMiddleware::check();
    }

    public function index()
    {
        global $pdo;
        $user = AuthMiddleware::user();

        try {
            $this->ensureCatatanColumn($pdo);
            KegiatanStatusService::autoActivateToday($pdo);

            if ($user['role'] === 'admin') {
                $stmt = $pdo->prepare("
                    SELECT k.*, u.fullname as creator_name,
                           (SELECT COUNT(*) FROM attendances a WHERE a.kegiatan_id = k.id) as attendance_count,
                           (SELECT COUNT(*) FROM participant_registrations pr WHERE pr.kegiatan_id = k.id) as registration_count,
                           (SELECT COUNT(*) FROM participant_registrations pr WHERE pr.kegiatan_id = k.id AND pr.status = 'attended') as confirmed_count,
                           (SELECT COUNT(*) FROM participant_registrations pr WHERE pr.kegiatan_id = k.id AND pr.status = 'registered') as unconfirmed_count
                    FROM kegiatan k
                    LEFT JOIN users u ON k.user_id = u.id
                    WHERE k.status != 'Dihapus'
                    ORDER BY k.created_at DESC
                ");
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare("
                    SELECT k.*,
                           (SELECT COUNT(*) FROM attendances a WHERE a.kegiatan_id = k.id) as attendance_count,
                           (SELECT COUNT(*) FROM participant_registrations pr WHERE pr.kegiatan_id = k.id) as registration_count,
                           (SELECT COUNT(*) FROM participant_registrations pr WHERE pr.kegiatan_id = k.id AND pr.status = 'attended') as confirmed_count,
                           (SELECT COUNT(*) FROM participant_registrations pr WHERE pr.kegiatan_id = k.id AND pr.status = 'registered') as unconfirmed_count
                    FROM kegiatan k
                    WHERE k.user_id = ? AND k.status != 'Dihapus'
                    ORDER BY k.created_at DESC
                ");
                $stmt->execute([$user['id']]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public function store()
    {
        global $pdo;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            return;

        // CSRF Check
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            die("CSRF Token Invalid");
        }

        $nama = trim($_POST['nama_kegiatan'] ?? '');
        $jenis_kegiatan = $_POST['jenis_kegiatan'] ?? 'Daring';
        $nomor_surat_undangan = trim($_POST['nomor_surat_undangan'] ?? '');
        $perlu_biodata = $_POST['perlu_biodata'] ?? 'Ya';
        $tanggal_pelaksanaan = $_POST['tanggal_pelaksanaan'] ?? null;
        $waktu_pelaksanaan = $_POST['waktu_pelaksanaan'] ?? null;
        $tempat_pelaksanaan = $_POST['tempat_pelaksanaan'] ?? null;
        $catatan = trim($_POST['catatan'] ?? '');
        $pejabat_penanggung_jawab = trim($_POST['pejabat_penanggung_jawab'] ?? '');
        $jabatan_penanggung_jawab = trim($_POST['jabatan_penanggung_jawab'] ?? '');
        $nip_penanggung_jawab = trim($_POST['nip_penanggung_jawab'] ?? '');
        if(empty($nip_penanggung_jawab)) $nip_penanggung_jawab = '-';
        $user_id = $_SESSION['user_id'];

        if (strlen($nama) < 3) {
            $_SESSION['flash_error'] = "Nama kegiatan minimal 3 karakter.";
            header('Location: /dashboard');
            exit;
        }

        if (!in_array($jenis_kegiatan, ['Daring', 'Luring'], true)) {
            $jenis_kegiatan = 'Daring';
        }

        if (!in_array($perlu_biodata, ['Ya', 'Tidak'], true)) {
            $perlu_biodata = 'Ya';
        }

        if ($perlu_biodata === 'Ya' && $nomor_surat_undangan === '') {
            $_SESSION['flash_error'] = "Nomor surat undangan wajib diisi jika biodata diperlukan.";
            header('Location: /dashboard');
            exit;
        }

        try {
            KegiatanStatusService::ensureManualStatusColumn($pdo);
            KegiatanUrlService::ensureTokenColumn($pdo);
            $this->ensureCatatanColumn($pdo);

            $status = KegiatanStatusService::automaticStatusForDate($tanggal_pelaksanaan);

            $attendanceToken = KegiatanUrlService::generateUniqueToken($pdo);
            $stmt = $pdo->prepare("INSERT INTO kegiatan (user_id, nama_kegiatan, jenis_kegiatan, nomor_surat_undangan, perlu_biodata, tanggal_pelaksanaan, waktu_pelaksanaan, tempat_pelaksanaan, catatan, pejabat_penanggung_jawab, jabatan_penanggung_jawab, nip_penanggung_jawab, status, status_manual, attendance_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
            $stmt->execute([$user_id, $nama, $jenis_kegiatan, $nomor_surat_undangan ?: null, $perlu_biodata, $tanggal_pelaksanaan, $waktu_pelaksanaan, $tempat_pelaksanaan, $catatan ?: null, $pejabat_penanggung_jawab, $jabatan_penanggung_jawab, $nip_penanggung_jawab, $status, $attendanceToken]);

            $_SESSION['flash_success'] = "Kegiatan berhasil ditambahkan.";
            $this->logActivity("ADD_KEGIATAN", "Added: $nama");
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Gagal menambah kegiatan.";
        }

        header('Location: /dashboard');
        exit;
    }

    public function update()
    {
        global $pdo;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            return;

        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            die("CSRF Token Invalid");
        }

        $id = $_POST['kegiatan_id'] ?? null;
        $nama = trim($_POST['nama_kegiatan'] ?? '');
        $jenis_kegiatan = $_POST['jenis_kegiatan'] ?? 'Daring';
        $nomor_surat_undangan = trim($_POST['nomor_surat_undangan'] ?? '');
        $perlu_biodata = $_POST['perlu_biodata'] ?? 'Ya';
        $tanggal_pelaksanaan = $_POST['tanggal_pelaksanaan'] ?? null;
        $waktu_pelaksanaan = $_POST['waktu_pelaksanaan'] ?? null;
        $tempat_pelaksanaan = $_POST['tempat_pelaksanaan'] ?? null;
        $catatan = trim($_POST['catatan'] ?? '');
        $pejabat_penanggung_jawab = trim($_POST['pejabat_penanggung_jawab'] ?? '');
        $jabatan_penanggung_jawab = trim($_POST['jabatan_penanggung_jawab'] ?? '');
        $nip_penanggung_jawab = trim($_POST['nip_penanggung_jawab'] ?? '');
        if(empty($nip_penanggung_jawab)) $nip_penanggung_jawab = '-';

        if (!in_array($jenis_kegiatan, ['Daring', 'Luring'], true)) {
            $jenis_kegiatan = 'Daring';
        }

        if (!in_array($perlu_biodata, ['Ya', 'Tidak'], true)) {
            $perlu_biodata = 'Ya';
        }

        if ($perlu_biodata === 'Ya' && $nomor_surat_undangan === '') {
            $_SESSION['flash_error'] = "Nomor surat undangan wajib diisi jika biodata diperlukan.";
            header('Location: /dashboard');
            exit;
        }
        
        $user_id = $_SESSION['user_id'];
        $user = AuthMiddleware::user();

        try {
            KegiatanStatusService::ensureManualStatusColumn($pdo);
            $this->ensureCatatanColumn($pdo);

            // Check ownership or admin
            $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE id = ?");
            $stmt->execute([$id]);
            $kegiatan = $stmt->fetch();

            if (!$kegiatan || ($user['role'] !== 'admin' && $kegiatan['user_id'] != $user_id)) {
                $_SESSION['flash_error'] = "Akses ditolak.";
                header('Location: /dashboard');
                exit;
            }

            $params = [$nama, $jenis_kegiatan, $nomor_surat_undangan ?: null, $perlu_biodata, $tanggal_pelaksanaan, $waktu_pelaksanaan, $tempat_pelaksanaan, $catatan ?: null, $pejabat_penanggung_jawab, $jabatan_penanggung_jawab, $nip_penanggung_jawab];
            $statusSql = '';

            if ((int) ($kegiatan['status_manual'] ?? 0) === 0 && in_array($kegiatan['status'], ['Aktif', 'Non-Aktif'], true)) {
                $statusSql = ", status = ?";
                $params[] = KegiatanStatusService::automaticStatusForDate($tanggal_pelaksanaan);
            }

            $stmt = $pdo->prepare("UPDATE kegiatan SET nama_kegiatan = ?, jenis_kegiatan = ?, nomor_surat_undangan = ?, perlu_biodata = ?, tanggal_pelaksanaan = ?, waktu_pelaksanaan = ?, tempat_pelaksanaan = ?, catatan = ?, pejabat_penanggung_jawab = ?, jabatan_penanggung_jawab = ?, nip_penanggung_jawab = ?$statusSql WHERE id = ?");
            $params[] = $id;
            $stmt->execute($params);

            $_SESSION['flash_success'] = "Kegiatan berhasil diperbarui.";
            $this->logActivity("EDIT_KEGIATAN", "Edited ID: $id");
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Gagal memperbarui kegiatan.";
        }

        header('Location: /dashboard');
        exit;
    }

    public function updateStatus()
    {
        global $pdo;

        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            die("CSRF Token Invalid");
        }

        $id = $_POST['kegiatan_id'] ?? null;
        $status = $_POST['status'] ?? null;
        $validStatuses = ['Aktif', 'Non-Aktif', 'Diarsipkan', 'Dihapus'];

        if (!in_array($status, $validStatuses)) {
            $_SESSION['flash_error'] = "Status tidak valid.";
            header('Location: /dashboard');
            exit;
        }

        $user_id = $_SESSION['user_id'];
        $user = AuthMiddleware::user();

        try {
            $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE id = ?");
            $stmt->execute([$id]);
            $kegiatan = $stmt->fetch();

            if (!$kegiatan || ($user['role'] !== 'admin' && $kegiatan['user_id'] != $user_id)) {
                $_SESSION['flash_error'] = "Akses ditolak.";
                header('Location: /dashboard');
                exit;
            }

            if ($status === 'Dihapus') {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM attendances WHERE kegiatan_id = ?");
                $stmtCheck->execute([$id]);
                $count = $stmtCheck->fetchColumn();

                if ($count > 0) {
                    $_SESSION['flash_error'] = "Kegiatan tidak bisa dihapus karena sudah memiliki data peserta.";
                    header('Location: /dashboard');
                    exit;
                }
            }

            $statusManual = KegiatanStatusService::manualFlagForStatus($status);
            $stmt = $pdo->prepare("UPDATE kegiatan SET status = ?, status_manual = ? WHERE id = ?");
            $stmt->execute([$status, $statusManual, $id]);

            $_SESSION['flash_success'] = "Status kegiatan berhasil diubah menjadi $status.";
            $this->logActivity("UPDATE_STATUS_KEGIATAN", "Status changed to $status for ID: $id");
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Gagal mengubah status kegiatan.";
        }

        header('Location: /dashboard');
        exit;
    }

    private function logActivity($action, $details)
    {
        global $pdo;
        $userId = $_SESSION['user_id'];
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $ip]);
    }

    private function ensureCatatanColumn(PDO $pdo)
    {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM kegiatan LIKE 'catatan'");
        $stmt->execute();

        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE kegiatan ADD COLUMN catatan TEXT NULL AFTER tempat_pelaksanaan");
        }
    }

}
