SET @tanggal_selesai_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND COLUMN_NAME = 'tanggal_selesai'
);
SET @tanggal_selesai_sql := IF(
    @tanggal_selesai_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN tanggal_selesai DATE NULL AFTER tanggal_pelaksanaan',
    'SELECT 1'
);
PREPARE tanggal_selesai_stmt FROM @tanggal_selesai_sql;
EXECUTE tanggal_selesai_stmt;
DEALLOCATE PREPARE tanggal_selesai_stmt;

SET @idx_kegiatan_rentang_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND INDEX_NAME = 'idx_kegiatan_rentang'
);
SET @idx_kegiatan_rentang_sql := IF(
    @idx_kegiatan_rentang_exists = 0,
    'CREATE INDEX idx_kegiatan_rentang ON kegiatan (tanggal_pelaksanaan, tanggal_selesai, status)',
    'SELECT 1'
);
PREPARE idx_kegiatan_rentang_stmt FROM @idx_kegiatan_rentang_sql;
EXECUTE idx_kegiatan_rentang_stmt;
DEALLOCATE PREPARE idx_kegiatan_rentang_stmt;
