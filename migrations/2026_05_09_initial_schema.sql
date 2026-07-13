CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(200) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kegiatan (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    nama_kegiatan VARCHAR(255) NOT NULL,
    tanggal_pelaksanaan DATE NULL,
    tanggal_selesai DATE NULL,
    waktu_pelaksanaan VARCHAR(100) NULL,
    tempat_pelaksanaan VARCHAR(255) NULL,
    pejabat_penanggung_jawab VARCHAR(200) NULL,
    jabatan_penanggung_jawab VARCHAR(200) NULL,
    nip_penanggung_jawab VARCHAR(50) NULL,
    status ENUM('Aktif','Non-Aktif','Diarsipkan','Dihapus') NOT NULL DEFAULT 'Non-Aktif',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kegiatan_user (user_id),
    KEY idx_kegiatan_status_tanggal (status, tanggal_pelaksanaan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kegiatan_id INT NOT NULL,
    nama VARCHAR(200) NOT NULL,
    instansi VARCHAR(200) NULL,
    jabatan VARCHAR(150) NULL,
    hp VARCHAR(30) NULL,
    signature_file VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attendances_kegiatan_identity (kegiatan_id, nama, hp),
    KEY idx_attendances_kegiatan (kegiatan_id),
    KEY idx_attendances_identity (kegiatan_id, nama, hp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user_created (user_id, created_at),
    KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
