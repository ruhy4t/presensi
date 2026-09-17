CREATE TABLE IF NOT EXISTS kegiatan_short_links (
    code CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    kegiatan_id INT NOT NULL,
    PRIMARY KEY (code),
    UNIQUE KEY uq_short_link_kegiatan (kegiatan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
