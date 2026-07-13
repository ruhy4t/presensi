SET @users_is_active_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active'
);
SET @users_is_active_sql := IF(
    @users_is_active_exists = 0,
    'ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role',
    'SELECT 1'
);
PREPARE users_is_active_stmt FROM @users_is_active_sql;
EXECUTE users_is_active_stmt;
DEALLOCATE PREPARE users_is_active_stmt;
