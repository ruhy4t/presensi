-- Presensi berbasis radius dan kegiatan per gelombang.
-- Idempoten: aman diimpor ulang melalui phpMyAdmin.

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND COLUMN_NAME = 'radius_enabled'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN radius_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER tempat_pelaksanaan',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND COLUMN_NAME = 'latitude'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN latitude DECIMAL(10,7) NULL AFTER radius_enabled',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND COLUMN_NAME = 'longitude'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND COLUMN_NAME = 'radius_meters'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN radius_meters SMALLINT UNSIGNED NULL AFTER longitude',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND COLUMN_NAME = 'gelombang_enabled'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN gelombang_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER radius_meters',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS kegiatan_gelombang (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kegiatan_id INT NOT NULL,
    nama VARCHAR(100) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gelombang_kegiatan_nama (kegiatan_id, nama),
    KEY idx_gelombang_kegiatan_active (kegiatan_id, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'gelombang_id'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN gelombang_id BIGINT UNSIGNED NULL AFTER participant_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND INDEX_NAME = 'idx_registration_gelombang'
);
SET @ddl := IF(@index_exists = 0,
    'ALTER TABLE participant_registrations ADD KEY idx_registration_gelombang (kegiatan_id, gelombang_id)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'attendance_latitude'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN attendance_latitude DECIMAL(10,7) NULL AFTER attendance_confirmed_at',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'attendance_longitude'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN attendance_longitude DECIMAL(10,7) NULL AFTER attendance_latitude',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'attendance_accuracy_meters'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN attendance_accuracy_meters DECIMAL(8,2) NULL AFTER attendance_longitude',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participant_registrations' AND COLUMN_NAME = 'attendance_distance_meters'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE participant_registrations ADD COLUMN attendance_distance_meters DECIMAL(10,2) NULL AFTER attendance_accuracy_meters',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'gelombang_id'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN gelombang_id BIGINT UNSIGNED NULL AFTER kegiatan_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'latitude'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN latitude DECIMAL(10,7) NULL AFTER signature_file',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'longitude'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'accuracy_meters'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN accuracy_meters DECIMAL(8,2) NULL AFTER longitude',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND COLUMN_NAME = 'distance_meters'
);
SET @ddl := IF(@column_exists = 0,
    'ALTER TABLE attendances ADD COLUMN distance_meters DECIMAL(10,2) NULL AFTER accuracy_meters',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND INDEX_NAME = 'idx_attendances_gelombang'
);
SET @ddl := IF(@index_exists = 0,
    'ALTER TABLE attendances ADD KEY idx_attendances_gelombang (kegiatan_id, gelombang_id)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
