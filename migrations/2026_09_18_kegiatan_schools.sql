CREATE TABLE IF NOT EXISTS kegiatan_schools (
    kegiatan_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (kegiatan_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
