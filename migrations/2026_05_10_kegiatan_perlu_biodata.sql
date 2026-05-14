ALTER TABLE kegiatan
    ADD COLUMN perlu_biodata ENUM('Ya','Tidak') NOT NULL DEFAULT 'Ya' AFTER nomor_surat_undangan;
