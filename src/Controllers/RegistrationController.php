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
            SELECT pr.*, p.nama_lengkap, p.nik, p.nip, p.jabatan, p.unit_kerja, p.hp, p.email,
                   kg.nama AS gelombang_nama, kg.tanggal AS gelombang_tanggal,
                   kg.waktu_mulai AS gelombang_waktu_mulai, kg.waktu_selesai AS gelombang_waktu_selesai,
                   confirmed_user.fullname AS confirmed_by_name,
                   cancelled_user.fullname AS cancelled_by_name
            FROM participant_registrations pr
            INNER JOIN participants p ON p.id = pr.participant_id
            LEFT JOIN kegiatan_gelombang kg ON kg.id = pr.gelombang_id
            LEFT JOIN users confirmed_user ON confirmed_user.id = pr.confirmed_by_user_id
            LEFT JOIN users cancelled_user ON cancelled_user.id = pr.attendance_cancelled_by_user_id
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
        if ($registrations !== []) {
            $registrationIds = array_map(static fn(array $row): int => (int) $row['id'], $registrations);
            $historyPlaceholders = implode(',', array_fill(0, count($registrationIds), '?'));
            $historyStmt = $pdo->prepare("
                SELECT aa.*, u.fullname AS admin_name,
                       wave_from.nama AS from_wave_name, wave_to.nama AS to_wave_name
                FROM attendance_adjustments aa
                INNER JOIN users u ON u.id = aa.user_id
                LEFT JOIN kegiatan_gelombang wave_from ON wave_from.id = aa.from_gelombang_id
                LEFT JOIN kegiatan_gelombang wave_to ON wave_to.id = aa.to_gelombang_id
                WHERE aa.registration_id IN ({$historyPlaceholders})
                ORDER BY aa.created_at DESC, aa.id DESC
            ");
            $historyStmt->execute($registrationIds);
            $historyByRegistration = [];
            foreach ($historyStmt->fetchAll() as $history) {
                $historyByRegistration[(int) $history['registration_id']][] = $history;
            }
            foreach ($registrations as &$registration) {
                $registration['adjustment_history'] = $historyByRegistration[(int) $registration['id']] ?? [];
            }
            unset($registration);
        }
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
                   SUM(status = 'registered') AS registered,
                   SUM(status = 'cancelled') AS cancelled
            FROM participant_registrations
            WHERE kegiatan_id = ?
        ");
        $summaryStmt->execute([$kegiatanId]);
        $registrationSummary = $summaryStmt->fetch() ?: ['total' => 0, 'attended' => 0, 'registered' => 0, 'cancelled' => 0];

        $waveStmt = $pdo->prepare("
            SELECT id, nama, tanggal, waktu_mulai, waktu_selesai, kuota
            FROM kegiatan_gelombang
            WHERE kegiatan_id = ? AND is_active = 1
            ORDER BY sort_order, id
        ");
        $waveStmt->execute([$kegiatanId]);
        $gelombangOptions = $waveStmt->fetchAll();

        require __DIR__ . '/../Views/registrations.php';
    }

    public function handleAction(): void
    {
        global $pdo;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            die('Metode tidak diizinkan.');
        }
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            http_response_code(419);
            die('Sesi tidak valid. Muat ulang halaman.');
        }

        $registrationId = filter_var($_POST['registration_id'] ?? null, FILTER_VALIDATE_INT);
        $action = (string) ($_POST['attendance_action'] ?? '');
        $reason = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['reason'] ?? '')) ?? '');
        if (!$registrationId || !in_array($action, ['move_wave', 'admin_confirm', 'cancel_attendance'], true)) {
            $this->redirectWithMessage(null, 'Aksi peserta tidak valid.', false);
        }
        if (strlen($reason) < 5 || strlen($reason) > 500) {
            $this->redirectWithMessage(null, 'Alasan tindakan wajib diisi minimal 5 karakter dan maksimal 500 karakter.', false);
        }

        $user = AuthMiddleware::user();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                SELECT pr.*, p.nama_lengkap, p.unit_kerja, p.jabatan, p.hp, p.signature_file,
                       k.user_id AS kegiatan_user_id, k.gelombang_enabled
                FROM participant_registrations pr
                INNER JOIN participants p ON p.id = pr.participant_id
                INNER JOIN kegiatan k ON k.id = pr.kegiatan_id
                WHERE pr.id = ?
                LIMIT 1 FOR UPDATE
            ");
            $stmt->execute([$registrationId]);
            $registration = $stmt->fetch();

            if (!$registration || ($user['role'] !== 'admin' && (int) $registration['kegiatan_user_id'] !== (int) $user['id'])) {
                throw new RuntimeException('Data tidak ditemukan atau akses ditolak.');
            }

            if ($action === 'move_wave') {
                $this->moveWave($registration, $reason, (int) $user['id']);
                $message = 'Peserta berhasil dipindahkan ke gelombang baru.';
            } elseif ($action === 'admin_confirm') {
                $this->confirmManually($registration, $reason, (int) $user['id']);
                $message = 'Kehadiran peserta berhasil dikonfirmasi oleh admin.';
            } else {
                $this->cancelAttendance($registration, $reason, (int) $user['id']);
                $message = 'Konfirmasi kehadiran peserta berhasil dibatalkan.';
            }

            $pdo->commit();
            $this->redirectWithMessage((int) $registration['kegiatan_id'], $message, true);
        } catch (RuntimeException | PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException) {
                error_log($e->getMessage());
                $message = 'Tindakan gagal disimpan karena terjadi kesalahan sistem.';
            } else {
                $message = $e->getMessage();
            }
            $this->redirectWithMessage(isset($registration['kegiatan_id']) ? (int) $registration['kegiatan_id'] : null, $message, false);
        }
    }

    private function moveWave(array $registration, string $reason, int $userId): void
    {
        global $pdo;

        if ($registration['status'] === 'attended') {
            throw new RuntimeException('Batalkan kehadiran terlebih dahulu sebelum memindahkan gelombang.');
        }
        $targetWaveId = filter_var($_POST['gelombang_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$targetWaveId) {
            throw new RuntimeException('Gelombang tujuan wajib dipilih.');
        }

        $wave = $this->getActiveWave((int) $registration['kegiatan_id'], (int) $targetWaveId);
        if (!$wave) {
            throw new RuntimeException('Gelombang tujuan tidak ditemukan atau sudah dinonaktifkan.');
        }
        if ((int) ($registration['gelombang_id'] ?? 0) === (int) $targetWaveId) {
            throw new RuntimeException('Peserta sudah berada pada gelombang tersebut.');
        }
        $this->ensureWaveCapacity($wave, (int) $registration['id']);

        $pdo->prepare("UPDATE participant_registrations SET gelombang_id = ? WHERE id = ?")
            ->execute([$targetWaveId, $registration['id']]);
        $pdo->prepare("UPDATE attendances SET gelombang_id = ? WHERE registration_id = ?")
            ->execute([$targetWaveId, $registration['id']]);
        $this->recordAdjustment($registration, 'move_wave', $reason, $userId, $registration['gelombang_id'], $targetWaveId);
    }

    private function confirmManually(array $registration, string $reason, int $userId): void
    {
        global $pdo;

        if ($registration['status'] === 'attended') {
            throw new RuntimeException('Peserta sudah tercatat hadir.');
        }
        if ((int) ($registration['gelombang_enabled'] ?? 0) === 1
            && !$this->getActiveWave((int) $registration['kegiatan_id'], (int) ($registration['gelombang_id'] ?? 0))) {
            throw new RuntimeException('Tetapkan gelombang peserta sebelum melakukan konfirmasi manual.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO attendances
                (kegiatan_id, registration_id, gelombang_id, nama, instansi, jabatan, hp,
                 signature_file, latitude, longitude, accuracy_meters, distance_meters,
                 record_status, confirmation_source, confirmed_by_user_id, admin_note,
                 cancelled_at, cancelled_by_user_id, cancellation_reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, 'active', 'admin', ?, ?, NULL, NULL, NULL, NOW())
            ON DUPLICATE KEY UPDATE
                registration_id = VALUES(registration_id), gelombang_id = VALUES(gelombang_id),
                nama = VALUES(nama), instansi = VALUES(instansi), jabatan = VALUES(jabatan),
                hp = VALUES(hp), signature_file = VALUES(signature_file),
                latitude = NULL, longitude = NULL, accuracy_meters = NULL, distance_meters = NULL,
                record_status = 'active', confirmation_source = 'admin',
                confirmed_by_user_id = VALUES(confirmed_by_user_id), admin_note = VALUES(admin_note),
                cancelled_at = NULL, cancelled_by_user_id = NULL, cancellation_reason = NULL,
                created_at = NOW()
        ");
        $stmt->execute([
            $registration['kegiatan_id'], $registration['id'], $registration['gelombang_id'],
            $registration['nama_lengkap'], $registration['unit_kerja'], $registration['jabatan'],
            $registration['hp'], $registration['signature_file'], $userId, $reason
        ]);

        $pdo->prepare("
            UPDATE participant_registrations
            SET status = 'attended', attendance_confirmed_at = NOW(),
                attendance_latitude = NULL, attendance_longitude = NULL,
                attendance_accuracy_meters = NULL, attendance_distance_meters = NULL,
                confirmation_source = 'admin', confirmed_by_user_id = ?, confirmation_note = ?,
                attendance_cancelled_at = NULL, attendance_cancelled_by_user_id = NULL,
                attendance_cancellation_reason = NULL
            WHERE id = ?
        ")->execute([$userId, $reason, $registration['id']]);

        $this->recordAdjustment($registration, 'admin_confirm', $reason, $userId);
    }

    private function cancelAttendance(array $registration, string $reason, int $userId): void
    {
        global $pdo;

        if ($registration['status'] !== 'attended') {
            throw new RuntimeException('Peserta belum memiliki konfirmasi kehadiran aktif.');
        }

        $pdo->prepare("
            UPDATE participant_registrations
            SET status = 'cancelled', attendance_cancelled_at = NOW(),
                attendance_cancelled_by_user_id = ?, attendance_cancellation_reason = ?
            WHERE id = ?
        ")->execute([$userId, $reason, $registration['id']]);

        $pdo->prepare("
            UPDATE attendances
            SET record_status = 'cancelled', cancelled_at = NOW(),
                cancelled_by_user_id = ?, cancellation_reason = ?
            WHERE registration_id = ?
               OR (registration_id IS NULL AND kegiatan_id = ? AND nama = ? AND hp = ?)
        ")->execute([
            $userId, $reason, $registration['id'], $registration['kegiatan_id'],
            $registration['nama_lengkap'], $registration['hp']
        ]);

        $this->recordAdjustment($registration, 'cancel_attendance', $reason, $userId);
    }

    private function getActiveWave(int $kegiatanId, int $waveId): ?array
    {
        global $pdo;
        if ($waveId < 1) {
            return null;
        }
        $stmt = $pdo->prepare("
            SELECT * FROM kegiatan_gelombang
            WHERE id = ? AND kegiatan_id = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$waveId, $kegiatanId]);
        return $stmt->fetch() ?: null;
    }

    private function ensureWaveCapacity(array $wave, int $excludeRegistrationId): void
    {
        global $pdo;
        if ($wave['kuota'] === null) {
            return;
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM participant_registrations
            WHERE gelombang_id = ? AND status IN ('registered', 'attended') AND id != ?
        ");
        $stmt->execute([$wave['id'], $excludeRegistrationId]);
        if ((int) $stmt->fetchColumn() >= (int) $wave['kuota']) {
            throw new RuntimeException('Kuota gelombang tujuan sudah penuh.');
        }
    }

    private function recordAdjustment(
        array $registration,
        string $action,
        string $reason,
        int $userId,
        mixed $fromWaveId = null,
        mixed $toWaveId = null
    ): void {
        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO attendance_adjustments
                (kegiatan_id, registration_id, user_id, action_type,
                 from_gelombang_id, to_gelombang_id, reason, details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $registration['kegiatan_id'], $registration['id'], $userId, $action,
            $fromWaveId, $toWaveId, $reason,
            json_encode(['previous_status' => $registration['status']], JSON_UNESCAPED_UNICODE)
        ]);
    }

    private function redirectWithMessage(?int $kegiatanId, string $message, bool $success): never
    {
        $_SESSION[$success ? 'flash_success' : 'flash_error'] = $message;
        header('Location: ' . ($kegiatanId ? '/registrations?id=' . $kegiatanId : '/dashboard'));
        exit;
    }

    public function print($registrationId)
    {
        global $pdo;

        $user = AuthMiddleware::user();
        KegiatanStatusService::ensureEndDateColumn($pdo);

        $stmt = $pdo->prepare("
            SELECT pr.*, p.*, kg.nama AS gelombang_nama, kg.tanggal AS gelombang_tanggal,
                   kg.waktu_mulai AS gelombang_waktu_mulai, kg.waktu_selesai AS gelombang_waktu_selesai,
                   k.nama_kegiatan, k.tanggal_pelaksanaan, k.tanggal_selesai, k.waktu_pelaksanaan, k.tempat_pelaksanaan,
                   k.user_id AS kegiatan_user_id
            FROM participant_registrations pr
            INNER JOIN participants p ON p.id = pr.participant_id
            INNER JOIN kegiatan k ON k.id = pr.kegiatan_id
            LEFT JOIN kegiatan_gelombang kg ON kg.id = pr.gelombang_id
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
