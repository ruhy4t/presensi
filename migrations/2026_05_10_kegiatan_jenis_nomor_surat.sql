SET @jenis_kegiatan_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND COLUMN_NAME = 'jenis_kegiatan'
);
SET @jenis_kegiatan_sql := IF(
    @jenis_kegiatan_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN jenis_kegiatan ENUM(''Daring'',''Luring'') NOT NULL DEFAULT ''Daring'' AFTER nama_kegiatan',
    'SELECT 1'
);
PREPARE jenis_kegiatan_stmt FROM @jenis_kegiatan_sql;
EXECUTE jenis_kegiatan_stmt;
DEALLOCATE PREPARE jenis_kegiatan_stmt;

SET @nomor_surat_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND COLUMN_NAME = 'nomor_surat_undangan'
);
SET @nomor_surat_sql := IF(
    @nomor_surat_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN nomor_surat_undangan VARCHAR(100) NULL AFTER jenis_kegiatan',
    'SELECT 1'
);
PREPARE nomor_surat_stmt FROM @nomor_surat_sql;
EXECUTE nomor_surat_stmt;
DEALLOCATE PREPARE nomor_surat_stmt;
