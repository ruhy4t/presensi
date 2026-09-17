CREATE TABLE IF NOT EXISTS kegiatan_link_aliases (
    alias VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    kegiatan_id INT NOT NULL,
    PRIMARY KEY (alias),
    UNIQUE KEY uq_alias_kegiatan (kegiatan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
