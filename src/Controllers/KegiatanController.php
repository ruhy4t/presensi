<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Services/KegiatanStatusService.php';
require_once __DIR__ . '/../Services/KegiatanUrlService.php';
require_once __DIR__ . '/../Services/AttendanceLocationService.php';

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
                           (SELECT GROUP_CONCAT(kg.nama ORDER BY kg.sort_order SEPARATOR '\n') FROM kegiatan_gelombang kg WHERE kg.kegiatan_id = k.id AND kg.is_active = 1) as gelombang_names,
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
                           (SELECT GROUP_CONCAT(kg.nama ORDER BY kg.sort_order SEPARATOR '\n') FROM kegiatan_gelombang kg WHERE kg.kegiatan_id = k.id AND kg.is_active = 1) as gelombang_names,
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
        $schedule = KegiatanStatusService::normalizeRange($_POST['tanggal_pelaksanaan'] ?? '', $_POST['tanggal_selesai'] ?? '');
        $tanggal_pelaksanaan = $schedule['start'];
        $tanggal_selesai = $schedule['end'];
        $waktu_pelaksanaan = $_POST['waktu_pelaksanaan'] ?? null;
        $tempat_pelaksanaan = $_POST['tempat_pelaksanaan'] ?? null;
        $radius_enabled = !empty($_POST['radius_enabled']);
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $radius_meters = trim($_POST['radius_meters'] ?? '');
        $gelombang_enabled = !empty($_POST['gelombang_enabled']);
        $gelombangNames = $this->parseGelombangNames($_POST['gelombang_names'] ?? '');
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

        if ($schedule['error']) {
            $_SESSION['flash_error'] = $schedule['error'];
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

        $locationError = AttendanceLocationService::validateConfiguration(
            $radius_enabled,
            $latitude,
            $longitude,
            $radius_meters
        );
        if ($locationError !== null) {
            $_SESSION['flash_error'] = $locationError;
            header('Location: /dashboard');
            exit;
        }

        if ($gelombang_enabled && $perlu_biodata !== 'Ya') {
            $_SESSION['flash_error'] = "Gelombang hanya dapat digunakan pada kegiatan yang memerlukan biodata.";
            header('Location: /dashboard');
            exit;
        }
        if ($gelombang_enabled && count($gelombangNames) < 1) {
            $_SESSION['flash_error'] = "Isi minimal satu nama gelombang.";
            header('Location: /dashboard');
            exit;
        }

        try {
            KegiatanStatusService::ensureManualStatusColumn($pdo);
            KegiatanUrlService::ensureTokenColumn($pdo);
            $this->ensureCatatanColumn($pdo);

            KegiatanStatusService::ensureEndDateColumn($pdo);
            $status = KegiatanStatusService::automaticStatusForDate($tanggal_pelaksanaan, $tanggal_selesai);

            $attendanceToken = KegiatanUrlService::generateUniqueToken($pdo);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO kegiatan (user_id, nama_kegiatan, jenis_kegiatan, nomor_surat_undangan, perlu_biodata, tanggal_pelaksanaan, tanggal_selesai, waktu_pelaksanaan, tempat_pelaksanaan, radius_enabled, latitude, longitude, radius_meters, gelombang_enabled, catatan, pejabat_penanggung_jawab, jabatan_penanggung_jawab, nip_penanggung_jawab, status, status_manual, attendance_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
            $stmt->execute([
                $user_id, $nama, $jenis_kegiatan, $nomor_surat_undangan ?: null, $perlu_biodata,
                $tanggal_pelaksanaan, $tanggal_selesai, $waktu_pelaksanaan, $tempat_pelaksanaan,
                $radius_enabled ? 1 : 0, $radius_enabled ? $latitude : null,
                $radius_enabled ? $longitude : null, $radius_enabled ? (int) $radius_meters : null,
                $gelombang_enabled ? 1 : 0, $catatan ?: null, $pejabat_penanggung_jawab,
                $jabatan_penanggung_jawab, $nip_penanggung_jawab, $status, $attendanceToken
            ]);
            $this->syncGelombang((int) $pdo->lastInsertId(), $gelombang_enabled ? $gelombangNames : []);
            $pdo->commit();

            $_SESSION['flash_success'] = "Kegiatan berhasil ditambahkan.";
            $this->logActivity("ADD_KEGIATAN", "Added: $nama");
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
        $schedule = KegiatanStatusService::normalizeRange($_POST['tanggal_pelaksanaan'] ?? '', $_POST['tanggal_selesai'] ?? '');
        $tanggal_pelaksanaan = $schedule['start'];
        $tanggal_selesai = $schedule['end'];
        $waktu_pelaksanaan = $_POST['waktu_pelaksanaan'] ?? null;
        $tempat_pelaksanaan = $_POST['tempat_pelaksanaan'] ?? null;
        $radius_enabled = !empty($_POST['radius_enabled']);
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $radius_meters = trim($_POST['radius_meters'] ?? '');
        $gelombang_enabled = !empty($_POST['gelombang_enabled']);
        $gelombangNames = $this->parseGelombangNames($_POST['gelombang_names'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');
        $pejabat_penanggung_jawab = trim($_POST['pejabat_penanggung_jawab'] ?? '');
        $jabatan_penanggung_jawab = trim($_POST['jabatan_penanggung_jawab'] ?? '');
        $nip_penanggung_jawab = trim($_POST['nip_penanggung_jawab'] ?? '');
        if(empty($nip_penanggung_jawab)) $nip_penanggung_jawab = '-';

        if (!in_array($jenis_kegiatan, ['Daring', 'Luring'], true)) {
            $jenis_kegiatan = 'Daring';
        }

        if ($schedule['error']) {
            $_SESSION['flash_error'] = $schedule['error'];
            header('Location: /dashboard');
            exit;
        }

        if (!in_array($perlu_biodata, ['Ya', 'Tidak'], true)) {
            $perlu_biodata = 'Ya';
        }

        if ($perlu_biodata === 'Ya' && $nomor_surat_undangan === '') {
            $_SESSION['flash_error'] = "Nomor surat undangan wajib diisi jika biodata diperlukan.";
            header('Location: /dashboard');
            exit;
        }

        $locationError = AttendanceLocationService::validateConfiguration(
            $radius_enabled,
            $latitude,
            $longitude,
            $radius_meters
        );
        if ($locationError !== null) {
            $_SESSION['flash_error'] = $locationError;
            header('Location: /dashboard');
            exit;
        }
        if ($gelombang_enabled && $perlu_biodata !== 'Ya') {
            $_SESSION['flash_error'] = "Gelombang hanya dapat digunakan pada kegiatan yang memerlukan biodata.";
            header('Location: /dashboard');
            exit;
        }
        if ($gelombang_enabled && count($gelombangNames) < 1) {
            $_SESSION['flash_error'] = "Isi minimal satu nama gelombang.";
            header('Location: /dashboard');
            exit;
        }
        
        $user_id = $_SESSION['user_id'];
        $user = AuthMiddleware::user();

        try {
            KegiatanStatusService::ensureManualStatusColumn($pdo);
            KegiatanStatusService::ensureEndDateColumn($pdo);
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

            $params = [
                $nama, $jenis_kegiatan, $nomor_surat_undangan ?: null, $perlu_biodata,
                $tanggal_pelaksanaan, $tanggal_selesai, $waktu_pelaksanaan, $tempat_pelaksanaan,
                $radius_enabled ? 1 : 0, $radius_enabled ? $latitude : null,
                $radius_enabled ? $longitude : null, $radius_enabled ? (int) $radius_meters : null,
                $gelombang_enabled ? 1 : 0, $catatan ?: null, $pejabat_penanggung_jawab,
                $jabatan_penanggung_jawab, $nip_penanggung_jawab
            ];
            $statusSql = '';

            if ((int) ($kegiatan['status_manual'] ?? 0) === 0 && in_array($kegiatan['status'], ['Aktif', 'Non-Aktif'], true)) {
                $statusSql = ", status = ?";
                $params[] = KegiatanStatusService::automaticStatusForDate($tanggal_pelaksanaan, $tanggal_selesai);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE kegiatan SET nama_kegiatan = ?, jenis_kegiatan = ?, nomor_surat_undangan = ?, perlu_biodata = ?, tanggal_pelaksanaan = ?, tanggal_selesai = ?, waktu_pelaksanaan = ?, tempat_pelaksanaan = ?, radius_enabled = ?, latitude = ?, longitude = ?, radius_meters = ?, gelombang_enabled = ?, catatan = ?, pejabat_penanggung_jawab = ?, jabatan_penanggung_jawab = ?, nip_penanggung_jawab = ?$statusSql WHERE id = ?");
            $params[] = $id;
            $stmt->execute($params);
            $this->syncGelombang((int) $id, $gelombang_enabled ? $gelombangNames : []);
            $pdo->commit();

            $_SESSION['flash_success'] = "Kegiatan berhasil diperbarui.";
            $this->logActivity("EDIT_KEGIATAN", "Edited ID: $id");
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
            KegiatanStatusService::ensureManualStatusColumn($pdo);

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
        try {
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $details, $ip]);
        } catch (Throwable $e) {
            // Audit logging must never make a successful kegiatan operation look failed.
            error_log($e->getMessage());
        }
    }

    private function parseGelombangNames(string $input): array
    {
        $names = [];
        foreach (preg_split('/\R/u', $input) ?: [] as $line) {
            $name = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
            if ($name === '') {
                continue;
            }
            $name = function_exists('mb_substr') ? mb_substr($name, 0, 100) : substr($name, 0, 100);
            $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            $names[$key] = $name;
            if (count($names) >= 50) {
                break;
            }
        }

        return array_values($names);
    }

    private function syncGelombang(int $kegiatanId, array $names): void
    {
        global $pdo;

        $pdo->prepare("UPDATE kegiatan_gelombang SET is_active = 0 WHERE kegiatan_id = ?")
            ->execute([$kegiatanId]);

        $stmt = $pdo->prepare("
            INSERT INTO kegiatan_gelombang (kegiatan_id, nama, sort_order, is_active)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), is_active = 1
        ");
        foreach ($names as $index => $name) {
            $stmt->execute([$kegiatanId, $name, $index + 1]);
        }
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
