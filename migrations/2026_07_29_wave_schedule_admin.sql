-- Jadwal per gelombang dan koreksi kehadiran oleh admin.
-- Jalankan setelah 2026_07_29_radius_gelombang.sql.
-- Idempoten dan aman diimpor ulang melalui phpMyAdmin.

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan_gelombang' AND COLUMN_NAME = 'tanggal'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan_gelombang ADD COLUMN tanggal DATE NULL AFTER nama',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan_gelombang' AND COLUMN_NAME = 'waktu_mulai'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan_gelombang ADD COLUMN waktu_mulai TIME NULL AFTER tanggal',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan_gelombang' AND COLUMN_NAME = 'waktu_selesai'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan_gelombang ADD COLUMN waktu_selesai TIME NULL AFTER waktu_mulai',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan_gelombang' AND COLUMN_NAME = 'presensi_mulai'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan_gelombang ADD COLUMN presensi_mulai TIME NULL AFTER waktu_selesai',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan_gelombang' AND COLUMN_NAME = 'presensi_selesai'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan_gelombang ADD COLUMN presensi_selesai TIME NULL AFTER presensi_mulai',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan_gelombang' AND COLUMN_NAME = 'kuota'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan_gelombang ADD COLUMN kuota INT UNSIGNED NULL AFTER presensi_selesai',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan_gelombang' AND INDEX_NAME = 'idx_gelombang_schedule'
);
SET @ddl := IF(@index_exists = 0,
    'ALTER TABLE kegiatan_gelombang ADD KEY idx_gelombang_schedule (kegiatan_id, tanggal, presensi_mulai, presensi_selesai)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE participant_registrations
    MODIFY COLUMN status ENUM('registered','attended','cancelled') NOT NULL DEFAULT 'registered';

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'confirmation_source'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN confirmation_source ENUM(''participant'',''admin'') NULL AFTER attendance_distance_meters',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'confirmed_by_user_id'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN confirmed_by_user_id INT UNSIGNED NULL AFTER confirmation_source',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'confirmation_note'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN confirmation_note VARCHAR(500) NULL AFTER confirmed_by_user_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'attendance_cancelled_at'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN attendance_cancelled_at DATETIME NULL AFTER confirmation_note',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'attendance_cancelled_by_user_id'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN attendance_cancelled_by_user_id INT UNSIGNED NULL AFTER attendance_cancelled_at',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'attendance_cancellation_reason'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN attendance_cancellation_reason VARCHAR(500) NULL AFTER attendance_cancelled_by_user_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'registration_id'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN registration_id BIGINT UNSIGNED NULL AFTER kegiatan_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'record_status'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN record_status ENUM(''active'',''cancelled'') NOT NULL DEFAULT ''active'' AFTER distance_meters',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'confirmation_source'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN confirmation_source ENUM(''participant'',''admin'') NOT NULL DEFAULT ''participant'' AFTER record_status',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'confirmed_by_user_id'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN confirmed_by_user_id INT UNSIGNED NULL AFTER confirmation_source',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'admin_note'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN admin_note VARCHAR(500) NULL AFTER confirmed_by_user_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'cancelled_at'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN cancelled_at DATETIME NULL AFTER admin_note',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'cancelled_by_user_id'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN cancelled_by_user_id INT UNSIGNED NULL AFTER cancelled_at',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'cancellation_reason'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN cancellation_reason VARCHAR(500) NULL AFTER cancelled_by_user_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND INDEX_NAME = 'idx_attendances_registration'
);
SET @ddl := IF(@index_exists = 0,
    'ALTER TABLE attendances ADD KEY idx_attendances_registration (registration_id, record_status)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS attendance_adjustments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kegiatan_id INT NOT NULL,
    registration_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    action_type ENUM('move_wave','admin_confirm','cancel_attendance') NOT NULL,
    from_gelombang_id BIGINT UNSIGNED NULL,
    to_gelombang_id BIGINT UNSIGNED NULL,
    reason VARCHAR(500) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_adjustment_registration (registration_id, created_at),
    KEY idx_adjustment_kegiatan (kegiatan_id, created_at),
    KEY idx_adjustment_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE participant_registrations
SET confirmation_source = 'participant'
WHERE status = 'attended' AND confirmation_source IS NULL;

UPDATE attendances a
INNER JOIN participants p ON p.nama_lengkap = a.nama AND p.hp = a.hp
INNER JOIN participant_registrations pr
    ON pr.participant_id = p.id AND pr.kegiatan_id = a.kegiatan_id
SET a.registration_id = pr.id
WHERE a.registration_id IS NULL;
