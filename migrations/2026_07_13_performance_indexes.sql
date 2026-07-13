SET @idx_kegiatan_user_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND INDEX_NAME = 'idx_kegiatan_user'
);
SET @idx_kegiatan_user_sql := IF(
    @idx_kegiatan_user_exists = 0,
    'CREATE INDEX idx_kegiatan_user ON kegiatan (user_id)',
    'SELECT 1'
);
PREPARE idx_kegiatan_user_stmt FROM @idx_kegiatan_user_sql;
EXECUTE idx_kegiatan_user_stmt;
DEALLOCATE PREPARE idx_kegiatan_user_stmt;

SET @idx_kegiatan_status_tanggal_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND INDEX_NAME = 'idx_kegiatan_status_tanggal'
);
SET @idx_kegiatan_status_tanggal_sql := IF(
    @idx_kegiatan_status_tanggal_exists = 0,
    'CREATE INDEX idx_kegiatan_status_tanggal ON kegiatan (status, tanggal_pelaksanaan)',
    'SELECT 1'
);
PREPARE idx_kegiatan_status_tanggal_stmt FROM @idx_kegiatan_status_tanggal_sql;
EXECUTE idx_kegiatan_status_tanggal_stmt;
DEALLOCATE PREPARE idx_kegiatan_status_tanggal_stmt;

SET @idx_attendances_kegiatan_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND INDEX_NAME = 'idx_attendances_kegiatan'
);
SET @idx_attendances_kegiatan_sql := IF(
    @idx_attendances_kegiatan_exists = 0,
    'CREATE INDEX idx_attendances_kegiatan ON attendances (kegiatan_id)',
    'SELECT 1'
);
PREPARE idx_attendances_kegiatan_stmt FROM @idx_attendances_kegiatan_sql;
EXECUTE idx_attendances_kegiatan_stmt;
DEALLOCATE PREPARE idx_attendances_kegiatan_stmt;

SET @idx_attendances_identity_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND INDEX_NAME = 'idx_attendances_identity'
);
SET @idx_attendances_identity_sql := IF(
    @idx_attendances_identity_exists = 0,
    'CREATE INDEX idx_attendances_identity ON attendances (kegiatan_id, nama, hp)',
    'SELECT 1'
);
PREPARE idx_attendances_identity_stmt FROM @idx_attendances_identity_sql;
EXECUTE idx_attendances_identity_stmt;
DEALLOCATE PREPARE idx_attendances_identity_stmt;
