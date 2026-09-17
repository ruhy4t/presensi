-- Pilih database presensi di phpMyAdmin, lalu import file ini.
-- Aman dijalankan ulang; tidak menghapus tabel atau data yang ada.

CREATE TABLE IF NOT EXISTS kegiatan_short_links (
    code CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    kegiatan_id INT NOT NULL,
    PRIMARY KEY (code),
    UNIQUE KEY uq_short_link_kegiatan (kegiatan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kegiatan_schools (
    kegiatan_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (kegiatan_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kegiatan_link_aliases (
    alias VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    kegiatan_id INT NOT NULL,
    PRIMARY KEY (alias),
    UNIQUE KEY uq_alias_kegiatan (kegiatan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
