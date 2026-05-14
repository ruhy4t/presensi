SET @attendance_token_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'kegiatan'
      AND COLUMN_NAME = 'attendance_token'
);

SET @attendance_token_sql := IF(
    @attendance_token_exists = 0,
    'ALTER TABLE kegiatan ADD COLUMN attendance_token VARCHAR(80) NULL DEFAULT NULL AFTER status',
    'SELECT 1'
);

PREPARE attendance_token_stmt FROM @attendance_token_sql;
EXECUTE attendance_token_stmt;
DEALLOCATE PREPARE attendance_token_stmt;

SET @attendance_token_index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'kegiatan'
      AND INDEX_NAME = 'uq_kegiatan_attendance_token'
);

SET @attendance_token_index_sql := IF(
    @attendance_token_index_exists = 0,
    'CREATE UNIQUE INDEX uq_kegiatan_attendance_token ON kegiatan (attendance_token)',
    'SELECT 1'
);

PREPARE attendance_token_index_stmt FROM @attendance_token_index_sql;
EXECUTE attendance_token_index_stmt;
DEALLOCATE PREPARE attendance_token_index_stmt;
