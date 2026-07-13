SET @perlu_biodata_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kegiatan' AND COLUMN_NAME = 'perlu_biodata'
);
SET @perlu_biodata_sql := IF(
    @perlu_biodata_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN perlu_biodata ENUM(''Ya'',''Tidak'') NOT NULL DEFAULT ''Ya'' AFTER nomor_surat_undangan',
    'SELECT 1'
);
PREPARE perlu_biodata_stmt FROM @perlu_biodata_sql;
EXECUTE perlu_biodata_stmt;
DEALLOCATE PREPARE perlu_biodata_stmt;
