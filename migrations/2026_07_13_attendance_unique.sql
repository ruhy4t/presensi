SET @attendance_duplicates := (
    SELECT COUNT(*) FROM (
        SELECT kegiatan_id, nama, COALESCE(hp, '')
        FROM attendances
        GROUP BY kegiatan_id, nama, COALESCE(hp, '')
        HAVING COUNT(*) > 1
    ) AS duplicate_groups
);

SET @attendance_unique_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendances' AND INDEX_NAME = 'uq_attendances_kegiatan_identity'
);

SET @attendance_unique_sql := IF(
    @attendance_unique_exists > 0,
    'SELECT 1',
    IF(
        @attendance_duplicates = 0,
        'CREATE UNIQUE INDEX uq_attendances_kegiatan_identity ON attendances (kegiatan_id, nama, hp)',
        'SELECT * FROM migration_blocked_duplicate_attendances_must_be_cleaned'
    )
);
PREPARE attendance_unique_stmt FROM @attendance_unique_sql;
EXECUTE attendance_unique_stmt;
DEALLOCATE PREPARE attendance_unique_stmt;
