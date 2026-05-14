ALTER TABLE kegiatan
    ADD COLUMN jenis_kegiatan ENUM('Daring','Luring') NOT NULL DEFAULT 'Daring' AFTER nama_kegiatan,
    ADD COLUMN nomor_surat_undangan VARCHAR(100) NULL AFTER jenis_kegiatan;
