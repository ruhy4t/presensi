SET @kegiatan_catatan_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND COLUMN_NAME = 'catatan'
);
SET @kegiatan_catatan_sql := IF(
    @kegiatan_catatan_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN catatan TEXT NULL AFTER tempat_pelaksanaan',
    'SELECT 1'
);
PREPARE kegiatan_catatan_stmt FROM @kegiatan_catatan_sql;
EXECUTE kegiatan_catatan_stmt;
DEALLOCATE PREPARE kegiatan_catatan_stmt;
