SET @status_manual_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'kegiatan'
      AND COLUMN_NAME = 'status_manual'
);

SET @status_manual_sql := IF(
    @status_manual_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN status_manual TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1'
);

PREPARE status_manual_stmt FROM @status_manual_sql;
EXECUTE status_manual_stmt;
DEALLOCATE PREPARE status_manual_stmt;
