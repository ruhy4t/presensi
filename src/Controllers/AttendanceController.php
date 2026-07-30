<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/KegiatanStatusService.php';
require_once __DIR__ . '/../Services/KegiatanUrlService.php';
require_once __DIR__ . '/../Services/AttendanceLocationService.php';
require_once __DIR__ . '/../Services/WaveScheduleService.php';

class AttendanceController
{
    public function show($kegiatanId)
    {
        $this->showByColumn('id', $kegiatanId);
    }

    public function showByToken($token)
    {
        $token = $this->normalizeAttendanceToken($token);
        if ($token === '') {
            die("Kegiatan tidak ditemukan atau sudah ditutup.");
        }

        $this->showByColumn('attendance_token', $token);
    }

    private function showByColumn($column, $value)
    {
        global $pdo;

        try {
            KegiatanStatusService::autoActivateToday($pdo);
            KegiatanUrlService::ensureTokenColumn($pdo);

            $lookupColumn = $column === 'attendance_token' ? 'attendance_token' : 'id';
            $legacyOnlySql = $lookupColumn === 'id' ? 'AND attendance_token IS NULL' : '';

            $stmt = $pdo->prepare("
                SELECT *
                FROM kegiatan
                WHERE $lookupColumn = ?
                  AND status NOT IN ('Dihapus', 'Diarsipkan')
                  AND NOT (status = 'Non-Aktif' AND status_manual = 1)
                  AND NOT (
                      status = 'Non-Aktif'
                      AND status_manual = 0
                      AND tanggal_pelaksanaan REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                      AND COALESCE(tanggal_selesai, tanggal_pelaksanaan) < ?
                  )
                  $legacyOnlySql
                LIMIT 1
            ");
            $stmt->execute([$value, KegiatanStatusService::todayDate()]);
            $kegiatan = $stmt->fetch();

            if (!$kegiatan) {
                die("Kegiatan tidak ditemukan atau sudah ditutup.");
            }

            $timing = $this->getEventTiming($kegiatan);
            $tanggalPelaksanaan = $timing['date'];
            $isBeforeEvent = $timing['is_before_confirmation'] && !$timing['is_after_event'];
            $isEventDay = $timing['is_event_day'] && $timing['can_confirm_attendance'];
            $isAfterEvent = $timing['is_after_event'];
            $confirmationOpenLabel = $timing['confirmation_open_label'];
            $needsBiodata = ($kegiatan['perlu_biodata'] ?? 'Ya') === 'Ya';
            $gelombangOptions = $this->getGelombangOptions((int) $kegiatan['id']);
            $radiusEnabled = AttendanceLocationService::isEnabled($kegiatan);

            require __DIR__ . '/../Views/attendance_form.php';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            die("Error System");
        }
    }

    public function prefill()
    {
        global $pdo;

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Invalid Request');
            return;
        }

        if (!$this->isValidCsrf()) {
            $this->jsonError('Sesi tidak valid atau kadaluarsa. Silakan muat ulang halaman.');
            return;
        }

        $kegiatanId = $_POST['kegiatan_id'] ?? null;
        $token = $this->normalizeToken($_POST['token'] ?? '');
        $nomorSurat = trim($_POST['nomor_surat_undangan'] ?? '');

        if (!$kegiatanId || !$token) {
            $this->jsonError('Token wajib diisi.');
            return;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT pr.id AS registration_id, pr.status AS registration_status,
                       pr.biodata_submitted_at, pr.attendance_confirmed_at, pr.gelombang_id,
                       kg.nama AS gelombang_nama, kg.tanggal AS gelombang_tanggal,
                       kg.waktu_mulai AS gelombang_waktu_mulai, kg.waktu_selesai AS gelombang_waktu_selesai,
                       kg.presensi_mulai AS gelombang_presensi_mulai,
                       kg.presensi_selesai AS gelombang_presensi_selesai, p.*
                FROM participant_registrations pr
                INNER JOIN participants p ON p.id = pr.participant_id
                INNER JOIN kegiatan k ON k.id = pr.kegiatan_id
                LEFT JOIN kegiatan_gelombang kg ON kg.id = pr.gelombang_id
                WHERE pr.kegiatan_id = ?
                  AND pr.token_code = ?
                  AND (
                      NULLIF(TRIM(k.nomor_surat_undangan), '') IS NULL
                      OR TRIM(k.nomor_surat_undangan) = '-'
                      OR LOWER(TRIM(k.nomor_surat_undangan)) = LOWER(TRIM(?))
                  )
                LIMIT 1
            ");
            $stmt->execute([$kegiatanId, $token, $nomorSurat]);
            $row = $stmt->fetch();

            if (!$row) {
                $this->jsonError('Data tidak ditemukan. Periksa kembali token dan nomor surat undangan.');
                return;
            }

            $waveTiming = null;
            if (!empty($row['gelombang_id'])) {
                $waveTiming = WaveScheduleService::timing([
                    'tanggal' => $row['gelombang_tanggal'],
                    'presensi_mulai' => $row['gelombang_presensi_mulai'],
                    'presensi_selesai' => $row['gelombang_presensi_selesai'],
                ]);
            }

            $this->jsonSuccess('Data ditemukan.', [
                'registration_id' => $row['registration_id'],
                'registration_status' => $row['registration_status'],
                'participant' => [
                    'nama_lengkap' => $row['nama_lengkap'],
                    'tempat_lahir' => $row['tempat_lahir'],
                    'tanggal_lahir' => $row['tanggal_lahir'],
                    'pangkat_gol' => $row['pangkat_gol'],
                    'nip' => $row['nip'],
                    'jabatan' => $row['jabatan'],
                    'unit_kerja' => $row['unit_kerja'],
                    'alamat_unit_kerja' => $row['alamat_unit_kerja'],
                    'telepon_unit_kerja' => $row['telepon_unit_kerja'],
                    'alamat_rumah' => $row['alamat_rumah'],
                    'hp' => $row['hp'],
                    'email' => $row['email'],
                    'biodata_submitted_at' => $row['biodata_submitted_at'],
                    'attendance_confirmed_at' => $row['attendance_confirmed_at']
                ],
                'gelombang_id' => $row['gelombang_id'],
                'gelombang_nama' => $row['gelombang_nama'],
                'gelombang_jadwal' => $waveTiming['label'] ?? null,
                'gelombang_can_confirm' => $waveTiming['can_confirm'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $this->jsonError('Terjadi kesalahan sistem.');
        }
    }

    public function store()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonError('Invalid Request');
            return;
        }

        if (!$this->isValidCsrf()) {
            $this->jsonError('Sesi tidak valid atau kadaluarsa (CSRF Error). Silakan muat ulang halaman.');
            return;
        }

        $mode = $_POST['mode'] ?? 'legacy';

        if ($mode === 'biodata') {
            $this->storeBiodata();
            return;
        }

        if ($mode === 'token_confirm') {
            $this->confirmByToken();
            return;
        }

        $this->storeLegacyAttendance();
    }

    private function storeBiodata()
    {
        global $pdo;

        $kegiatanId = $_POST['kegiatan_id'] ?? null;
        $data = $this->collectBiodataInput();
        $signatureData = $_POST['signature'] ?? '';

        if (!$kegiatanId) {
            $this->jsonError('ID kegiatan tidak valid.');
            return;
        }

        $validationError = $this->validateBiodata($data, $signatureData);
        if ($validationError) {
            $this->jsonError($validationError);
            return;
        }

        $kegiatan = $this->getKegiatan($kegiatanId);
        if (!$kegiatan) {
            $this->jsonError('Kegiatan tidak ditemukan atau sudah ditutup.');
            return;
        }

        $timing = $this->getEventTiming($kegiatan);
        if ($timing['is_after_event'] && $kegiatan['status'] !== 'Aktif') {
            $this->jsonError('Kegiatan sudah selesai.');
            return;
        }

        $gelombangId = $this->resolveGelombangId($kegiatan, $_POST['gelombang_id'] ?? null);
        if ((int) ($kegiatan['gelombang_enabled'] ?? 0) === 1 && $gelombangId === null) {
            $this->jsonError('Gelombang wajib dipilih sesuai undangan.');
            return;
        }
        $canConfirmNow = $this->canParticipantConfirmNow($kegiatan, $gelombangId);

        $signatureFile = $this->saveSignature($signatureData);
        if (!$signatureFile) {
            $this->jsonError('Tanda tangan tidak valid atau gagal disimpan.');
            return;
        }

        try {
            $pdo->beginTransaction();

            $participantId = $this->upsertParticipant($data, $signatureFile);
            $registration = $this->upsertRegistration($kegiatanId, $participantId, $gelombangId);

            if ($canConfirmNow) {
                if (empty($_POST['confirm_hadir'])) {
                    $pdo->rollBack();
                    $this->deleteSignatureFile($signatureFile);
                    $this->jsonError('Konfirmasi kehadiran wajib dicentang.');
                    return;
                }

                $participant = $this->getParticipant($participantId);
                $participant['gelombang_id'] = $registration['gelombang_id'] ?? $gelombangId;
                $confirmResult = $this->confirmAttendance($registration['id'], $kegiatanId, $participant, false, $kegiatan);
                if (!$confirmResult['ok']) {
                    $pdo->rollBack();
                    $this->deleteSignatureFile($signatureFile);
                    $this->jsonError($confirmResult['message']);
                    return;
                }

                $pdo->commit();
                $this->jsonSuccess('Biodata dan konfirmasi kehadiran berhasil disimpan.', [
                    'attendance_confirmed' => true,
                    'token' => null
                ]);
                return;
            }

            $pdo->commit();
            $this->jsonSuccess('Biodata berhasil disimpan. Simpan token ini untuk konfirmasi kehadiran saat presensi sudah dibuka.', [
                'attendance_confirmed' => false,
                'token' => $registration['token_code']
            ]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->deleteSignatureFile($signatureFile);
            error_log($e->getMessage());
            $this->jsonError($e->getCode() === '23000' ? 'Peserta sudah tercatat pada kegiatan ini.' : 'Terjadi kesalahan sistem saat menyimpan biodata.');
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->deleteSignatureFile($signatureFile);
            $this->jsonError($e->getMessage());
        }
    }

    private function confirmByToken()
    {
        global $pdo;

        $kegiatanId = $_POST['kegiatan_id'] ?? null;
        $token = $this->normalizeToken($_POST['token'] ?? '');
        $nomorSurat = trim($_POST['nomor_surat_undangan'] ?? '');

        if (!$kegiatanId || !$token) {
            $this->jsonError('Token wajib diisi.');
            return;
        }

        if (empty($_POST['confirm_hadir'])) {
            $this->jsonError('Konfirmasi kehadiran wajib dicentang.');
            return;
        }

        $kegiatan = $this->getKegiatan($kegiatanId);
        if (!$kegiatan) {
            $this->jsonError('Kegiatan tidak ditemukan atau sudah ditutup.');
            return;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT pr.id AS registration_id, pr.status, pr.gelombang_id, p.*
                FROM participant_registrations pr
                INNER JOIN participants p ON p.id = pr.participant_id
                INNER JOIN kegiatan k ON k.id = pr.kegiatan_id
                WHERE pr.kegiatan_id = ?
                  AND pr.token_code = ?
                  AND (
                      NULLIF(TRIM(k.nomor_surat_undangan), '') IS NULL
                      OR TRIM(k.nomor_surat_undangan) = '-'
                      OR LOWER(TRIM(k.nomor_surat_undangan)) = LOWER(TRIM(?))
                  )
                LIMIT 1
            ");
            $stmt->execute([$kegiatanId, $token, $nomorSurat]);
            $participant = $stmt->fetch();

            if (!$participant) {
                $pdo->rollBack();
                $this->jsonError('Token atau nomor surat undangan tidak sesuai.');
                return;
            }

            $result = $this->confirmAttendance($participant['registration_id'], $kegiatanId, $participant, true, $kegiatan);
            if (!$result['ok']) {
                $pdo->rollBack();
                $this->jsonError($result['message']);
                return;
            }

            $pdo->commit();
            $this->jsonSuccess('Konfirmasi kehadiran berhasil disimpan.', [
                'attendance_confirmed' => true
            ]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $this->jsonError('Terjadi kesalahan sistem saat mengonfirmasi kehadiran.');
        }
    }

    private function confirmAttendance($registrationId, $kegiatanId, array $participant, $tokenUsed, array $kegiatan)
    {
        global $pdo;

        $scheduleResult = $this->participantScheduleResult($kegiatan, $participant['gelombang_id'] ?? null);
        if (!$scheduleResult['ok']) {
            return $scheduleResult;
        }

        $locationResult = AttendanceLocationService::evaluate(
            $kegiatan,
            $_POST['latitude'] ?? null,
            $_POST['longitude'] ?? null,
            $_POST['accuracy'] ?? null
        );
        if (!$locationResult['ok']) {
            return $locationResult;
        }
        $location = $locationResult['location'];

        $stmt = $pdo->prepare("SELECT status FROM participant_registrations WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$registrationId]);
        $registration = $stmt->fetch();

        if (!$registration) {
            return ['ok' => false, 'message' => 'Data registrasi tidak ditemukan.'];
        }

        if ($registration['status'] === 'attended') {
            return ['ok' => false, 'message' => 'Peserta sudah tercatat hadir.'];
        }
        if ($registration['status'] === 'cancelled') {
            return ['ok' => false, 'message' => 'Kehadiran Anda telah dibatalkan panitia. Hubungi panitia untuk konfirmasi ulang.'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO attendances
                (kegiatan_id, registration_id, gelombang_id, nama, instansi, jabatan, hp, signature_file,
                 latitude, longitude, accuracy_meters, distance_meters, record_status, confirmation_source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'participant')
        ");
        $stmt->execute([
            $kegiatanId,
            $registrationId,
            $participant['gelombang_id'] ?? null,
            $participant['nama_lengkap'],
            $participant['unit_kerja'],
            $participant['jabatan'],
            $participant['hp'],
            $participant['signature_file'],
            $location['latitude'] ?? null,
            $location['longitude'] ?? null,
            $location['accuracy'] ?? null,
            $location['distance'] ?? null
        ]);

        $sql = "UPDATE participant_registrations
                SET status = 'attended', attendance_confirmed_at = NOW(),
                    attendance_latitude = ?, attendance_longitude = ?,
                    attendance_accuracy_meters = ?, attendance_distance_meters = ?,
                    confirmation_source = 'participant', confirmed_by_user_id = NULL,
                    confirmation_note = NULL, attendance_cancelled_at = NULL,
                    attendance_cancelled_by_user_id = NULL, attendance_cancellation_reason = NULL";
        if ($tokenUsed) {
            $sql .= ", token_used_at = NOW()";
        }
        $sql .= " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $location['latitude'] ?? null,
            $location['longitude'] ?? null,
            $location['accuracy'] ?? null,
            $location['distance'] ?? null,
            $registrationId
        ]);

        return ['ok' => true, 'message' => 'OK'];
    }

    private function collectBiodataInput()
    {
        return [
            'nama_lengkap' => trim($_POST['nama_lengkap'] ?? ''),
            'tempat_lahir' => trim($_POST['tempat_lahir'] ?? ''),
            'tanggal_lahir' => trim($_POST['tanggal_lahir'] ?? ''),
            'pangkat_gol' => trim($_POST['pangkat_gol'] ?? ''),
            'nip' => trim($_POST['nip'] ?? ''),
            'nik' => $this->normalizeNik($_POST['nik'] ?? ''),
            'jabatan' => trim($_POST['jabatan'] ?? ''),
            'unit_kerja' => trim($_POST['unit_kerja'] ?? ''),
            'alamat_unit_kerja' => trim($_POST['alamat_unit_kerja'] ?? ''),
            'telepon_unit_kerja' => trim($_POST['telepon_unit_kerja'] ?? ''),
            'alamat_rumah' => trim($_POST['alamat_rumah'] ?? ''),
            'hp' => trim($_POST['hp'] ?? ''),
            'email' => trim($_POST['email'] ?? '')
        ];
    }

    private function validateBiodata(array $data, $signatureData)
    {
        $required = [
            'nama_lengkap' => 'Nama lengkap',
            'tempat_lahir' => 'Tempat lahir',
            'tanggal_lahir' => 'Tanggal lahir',
            'nik' => 'NIK',
            'jabatan' => 'Jabatan',
            'unit_kerja' => 'Unit kerja',
            'alamat_unit_kerja' => 'Alamat unit kerja',
            'alamat_rumah' => 'Alamat rumah',
            'hp' => 'No. HP',
            'email' => 'Alamat email'
        ];

        foreach ($required as $field => $label) {
            if ($data[$field] === '') {
                return $label . ' wajib diisi.';
            }
        }

        if (!preg_match('/^\d{16}$/', $data['nik'])) {
            return 'NIK wajib berisi 16 digit angka.';
        }

        if ($data['nip'] !== '' && !preg_match('/^[0-9 ]{8,30}$/', $data['nip'])) {
            return 'NIP hanya boleh berisi angka dan spasi.';
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Alamat email tidak valid.';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['tanggal_lahir'])) {
            return 'Tanggal lahir tidak valid.';
        }

        if (strlen($signatureData) > 1024 * 1024 * 2) {
            return 'Ukuran tanda tangan terlalu besar.';
        }

        if ($signatureData === '') {
            return 'Tanda tangan wajib diisi.';
        }

        return null;
    }

    private function upsertParticipant(array $data, $signatureFile)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT id FROM participants WHERE nik = ? LIMIT 1");
        $stmt->execute([$data['nik']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE participants
                SET nama_lengkap = ?, tempat_lahir = ?, tanggal_lahir = ?, pangkat_gol = ?, nip = ?,
                    jabatan = ?, unit_kerja = ?, alamat_unit_kerja = ?, telepon_unit_kerja = ?,
                    alamat_rumah = ?, hp = ?, email = ?, signature_file = ?, biodata_filled_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['nama_lengkap'],
                $data['tempat_lahir'],
                $data['tanggal_lahir'],
                $data['pangkat_gol'] ?: null,
                $data['nip'] ?: null,
                $data['jabatan'],
                $data['unit_kerja'],
                $data['alamat_unit_kerja'],
                $data['telepon_unit_kerja'] ?: null,
                $data['alamat_rumah'],
                $data['hp'],
                $data['email'],
                $signatureFile,
                $existing['id']
            ]);
            return $existing['id'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO participants (
                nama_lengkap, tempat_lahir, tanggal_lahir, pangkat_gol, nip, nik, jabatan,
                unit_kerja, alamat_unit_kerja, telepon_unit_kerja, alamat_rumah, hp,
                email, signature_file, biodata_filled_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $data['nama_lengkap'],
            $data['tempat_lahir'],
            $data['tanggal_lahir'],
            $data['pangkat_gol'] ?: null,
            $data['nip'] ?: null,
            $data['nik'],
            $data['jabatan'],
            $data['unit_kerja'],
            $data['alamat_unit_kerja'],
            $data['telepon_unit_kerja'] ?: null,
            $data['alamat_rumah'],
            $data['hp'],
            $data['email'],
            $signatureFile
        ]);

        return $pdo->lastInsertId();
    }

    private function upsertRegistration($kegiatanId, $participantId, ?int $gelombangId)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM participant_registrations WHERE kegiatan_id = ? AND participant_id = ? LIMIT 1");
        $stmt->execute([$kegiatanId, $participantId]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ((int) ($existing['gelombang_id'] ?? 0) !== 0
                && (int) $existing['gelombang_id'] !== (int) ($gelombangId ?? 0)) {
                throw new RuntimeException('Gelombang registrasi sudah ditetapkan. Hubungi panitia jika perlu dipindahkan.');
            }
            $this->ensureGelombangCapacity($gelombangId, (int) $existing['id']);
            $stmt = $pdo->prepare("UPDATE participant_registrations SET gelombang_id = ?, biodata_submitted_at = NOW() WHERE id = ?");
            $stmt->execute([$gelombangId, $existing['id']]);
            $existing['gelombang_id'] = $gelombangId;
            return $existing;
        }

        $this->ensureGelombangCapacity($gelombangId);
        $token = $this->generateUniqueToken($kegiatanId);
        $stmt = $pdo->prepare("
            INSERT INTO participant_registrations (kegiatan_id, participant_id, gelombang_id, token_code, biodata_submitted_at, token_generated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$kegiatanId, $participantId, $gelombangId, $token]);

        return [
            'id' => $pdo->lastInsertId(),
            'kegiatan_id' => $kegiatanId,
            'participant_id' => $participantId,
            'gelombang_id' => $gelombangId,
            'token_code' => $token,
            'status' => 'registered'
        ];
    }

    private function generateUniqueToken($kegiatanId)
    {
        global $pdo;

        do {
            $token = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $stmt = $pdo->prepare("SELECT id FROM participant_registrations WHERE kegiatan_id = ? AND token_code = ? LIMIT 1");
            $stmt->execute([$kegiatanId, $token]);
        } while ($stmt->fetch());

        return $token;
    }

    private function ensureGelombangCapacity(?int $gelombangId, ?int $excludeRegistrationId = null): void
    {
        global $pdo;

        if (!$gelombangId) {
            return;
        }

        $stmt = $pdo->prepare("SELECT kuota FROM kegiatan_gelombang WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$gelombangId]);
        $quota = $stmt->fetchColumn();
        if ($quota === false || $quota === null) {
            return;
        }

        $sql = "SELECT COUNT(*) FROM participant_registrations
                WHERE gelombang_id = ? AND status IN ('registered', 'attended')";
        $params = [$gelombangId];
        if ($excludeRegistrationId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeRegistrationId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() >= (int) $quota) {
            throw new RuntimeException('Kuota gelombang yang dipilih sudah penuh. Hubungi panitia.');
        }
    }

    private function saveSignature($signatureData)
    {
        $signatureData = str_replace('data:image/png;base64,', '', $signatureData);
        $signatureData = str_replace(' ', '+', $signatureData);
        $fileData = base64_decode($signatureData, true);

        if (!$fileData) {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileData);
        if ($mimeType !== 'image/png') {
            return null;
        }

        $fileName = uniqid('', true) . '.png';
        $uploadDir = __DIR__ . '/../../public/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        return file_put_contents($uploadDir . $fileName, $fileData) ? $fileName : null;
    }

    private function storeLegacyAttendance()
    {
        global $pdo;

        $kegiatanId = $_POST['kegiatan_id'] ?? null;
        $nama = trim($_POST['nama'] ?? '');
        $instansi = trim($_POST['instansi'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $hp = trim($_POST['hp'] ?? '');
        $signatureData = $_POST['signature'] ?? '';

        if (empty($kegiatanId) || empty($nama)) {
            $this->jsonError('Data tidak lengkap.');
            return;
        }

        if (trim((string) $signatureData) === '') {
            $this->jsonError('Tanda tangan wajib diisi.');
            return;
        }

        $kegiatan = $this->getKegiatan($kegiatanId);
        if (!$kegiatan) {
            $this->jsonError('Kegiatan tidak ditemukan atau sudah ditutup.');
            return;
        }

        $timing = $this->getEventTiming($kegiatan);
        if (!$timing['can_confirm_attendance']) {
            $this->jsonError('Presensi belum dibuka. Konfirmasi kehadiran baru dapat dilakukan mulai ' . $timing['confirmation_open_label'] . ' WIB.');
            return;
        }

        $locationResult = AttendanceLocationService::evaluate(
            $kegiatan,
            $_POST['latitude'] ?? null,
            $_POST['longitude'] ?? null,
            $_POST['accuracy'] ?? null
        );
        if (!$locationResult['ok']) {
            $this->jsonError($locationResult['message']);
            return;
        }
        $location = $locationResult['location'];

        $fileName = $this->saveSignature($signatureData);
        if (!$fileName) {
            $this->jsonError('Tanda tangan tidak valid.');
            return;
        }

        try {
            $pdo->beginTransaction();
            $stmtVal = $pdo->prepare("SELECT id FROM attendances WHERE kegiatan_id = ? AND nama = ? AND hp = ? LIMIT 1 FOR UPDATE");
            $stmtVal->execute([$kegiatanId, $nama, $hp]);
            if ($stmtVal->fetch()) {
                $pdo->rollBack();
                $this->deleteSignatureFile($fileName);
                $this->jsonError('Anda sudah mengisi daftar hadir ini.');
                return;
            }

            $stmt = $pdo->prepare("
                INSERT INTO attendances
                    (kegiatan_id, nama, instansi, jabatan, hp, signature_file,
                     latitude, longitude, accuracy_meters, distance_meters)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $kegiatanId, $nama, $instansi, $jabatan, $hp, $fileName,
                $location['latitude'] ?? null, $location['longitude'] ?? null,
                $location['accuracy'] ?? null, $location['distance'] ?? null
            ]);
            $pdo->commit();

            $this->jsonSuccess('Berhasil Check-In!');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->deleteSignatureFile($fileName);
            error_log($e->getMessage());
            $this->jsonError($e->getCode() === '23000' ? 'Anda sudah mengisi daftar hadir ini.' : 'Terjadi kesalahan sistem.');
        }
    }

    private function getKegiatan($kegiatanId)
    {
        global $pdo;

        KegiatanStatusService::ensureManualStatusColumn($pdo);
        KegiatanStatusService::ensureEndDateColumn($pdo);

        $stmt = $pdo->prepare("
            SELECT *
            FROM kegiatan
            WHERE id = ?
              AND status NOT IN ('Dihapus', 'Diarsipkan')
              AND NOT (status = 'Non-Aktif' AND status_manual = 1)
                  AND NOT (
                      status = 'Non-Aktif'
                      AND status_manual = 0
                      AND tanggal_pelaksanaan REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                      AND COALESCE(tanggal_selesai, tanggal_pelaksanaan) < ?
              )
            LIMIT 1
        ");
        $stmt->execute([$kegiatanId, KegiatanStatusService::todayDate()]);
        return $stmt->fetch();
    }

    private function getParticipant($participantId)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM participants WHERE id = ? LIMIT 1");
        $stmt->execute([$participantId]);
        return $stmt->fetch();
    }

    private function getGelombangOptions(int $kegiatanId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT id, nama, tanggal, waktu_mulai, waktu_selesai,
                   presensi_mulai, presensi_selesai, kuota
            FROM kegiatan_gelombang
            WHERE kegiatan_id = ? AND is_active = 1
            ORDER BY sort_order, id
        ");
        $stmt->execute([$kegiatanId]);
        return $stmt->fetchAll();
    }

    private function resolveGelombangId(array $kegiatan, mixed $gelombangId): ?int
    {
        global $pdo;

        if ((int) ($kegiatan['gelombang_enabled'] ?? 0) !== 1) {
            return null;
        }

        $id = filter_var($gelombangId, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT id FROM kegiatan_gelombang
            WHERE id = ? AND kegiatan_id = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$id, $kegiatan['id']]);

        return $stmt->fetchColumn() ? (int) $id : null;
    }

    private function getGelombangById(int $kegiatanId, ?int $gelombangId): ?array
    {
        global $pdo;

        if (!$gelombangId) {
            return null;
        }
        $stmt = $pdo->prepare("
            SELECT * FROM kegiatan_gelombang
            WHERE id = ? AND kegiatan_id = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$gelombangId, $kegiatanId]);
        return $stmt->fetch() ?: null;
    }

    private function canParticipantConfirmNow(array $kegiatan, ?int $gelombangId): bool
    {
        return $this->participantScheduleResult($kegiatan, $gelombangId)['ok'];
    }

    private function participantScheduleResult(array $kegiatan, ?int $gelombangId): array
    {
        if ((int) ($kegiatan['gelombang_enabled'] ?? 0) === 1) {
            $wave = $this->getGelombangById((int) $kegiatan['id'], $gelombangId);
            if (!$wave) {
                return ['ok' => false, 'message' => 'Gelombang registrasi tidak ditemukan. Hubungi panitia.'];
            }
            $timing = WaveScheduleService::timing($wave);
            if (!$timing['can_confirm']) {
                return [
                    'ok' => false,
                    'message' => 'Presensi gelombang ' . $wave['nama'] . ' hanya dibuka pada ' . $timing['label'] . '.'
                ];
            }
            return ['ok' => true];
        }

        $timing = $this->getEventTiming($kegiatan);
        if (!$timing['can_confirm_attendance']) {
            return [
                'ok' => false,
                'message' => 'Presensi belum dibuka. Konfirmasi kehadiran baru dapat dilakukan mulai ' .
                    $timing['confirmation_open_label'] . ' WIB.'
            ];
        }
        return ['ok' => true];
    }

    private function getEventTiming(array $kegiatan)
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
        $today = $now->format('Y-m-d');
        $tanggalPelaksanaan = $this->normalizeDate($kegiatan['tanggal_pelaksanaan'] ?? '');
        $tanggalSelesai = $this->normalizeDate($kegiatan['tanggal_selesai'] ?? '') ?? $tanggalPelaksanaan;
        $startsAt = $this->eventStartsAt($kegiatan, $tanggalPelaksanaan);
        $confirmationOpensAt = $startsAt ? $startsAt->modify('-90 minutes') : null;
        $isManuallyOpen = ($kegiatan['status'] ?? '') === 'Aktif' && (int) ($kegiatan['status_manual'] ?? 0) === 1;
        $hasOpened = !$confirmationOpensAt || $now >= $confirmationOpensAt;
        $hasEnded = $tanggalSelesai && $tanggalSelesai < $today;
        $canConfirmAttendance = $hasOpened && (!$hasEnded || $isManuallyOpen);

        return [
            'date' => $tanggalPelaksanaan,
            'end_date' => $tanggalSelesai,
            'starts_at' => $startsAt,
            'confirmation_opens_at' => $confirmationOpensAt,
            'confirmation_open_label' => $confirmationOpensAt ? $confirmationOpensAt->format('d/m/Y H:i') : 'sekarang',
            'can_confirm_attendance' => $canConfirmAttendance,
            'is_before_event' => $tanggalPelaksanaan && $tanggalPelaksanaan > $today,
            'is_before_confirmation' => !$canConfirmAttendance,
            'is_event_day' => !$tanggalPelaksanaan || ($tanggalPelaksanaan <= $today && $tanggalSelesai >= $today),
            'is_after_event' => $hasEnded
        ];
    }

    private function deleteSignatureFile($fileName)
    {
        $path = __DIR__ . '/../../public/uploads/' . basename((string) $fileName);
        if ($fileName && is_file($path)) {
            unlink($path);
        }
    }

    private function eventStartsAt(array $kegiatan, $tanggalPelaksanaan)
    {
        if (!$tanggalPelaksanaan) {
            return null;
        }

        $time = '00:00';
        if (preg_match('/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/', (string) ($kegiatan['waktu_pelaksanaan'] ?? ''), $matches)) {
            $time = sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return new DateTimeImmutable($tanggalPelaksanaan . ' ' . $time, new DateTimeZone('Asia/Jakarta'));
    }

    private function normalizeDate($date)
    {
        $date = trim((string) $date);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    private function todayDate()
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    }

    private function normalizeNik($nik)
    {
        return preg_replace('/\D+/', '', (string) $nik);
    }

    private function normalizeToken($token)
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $token));
    }

    private function normalizeAttendanceToken($token)
    {
        return preg_replace('/[^a-f0-9]/', '', strtolower((string) $token));
    }

    private function isValidCsrf()
    {
        return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
    }

    private function jsonSuccess($message, array $extra = [])
    {
        echo json_encode(array_merge(['status' => 'success', 'message' => $message], $extra));
    }

    private function jsonError($message)
    {
        echo json_encode(['status' => 'error', 'message' => $message]);
    }
}
