# Presensi

Aplikasi presensi berbasis PHP untuk mengelola kegiatan, registrasi peserta,
kehadiran, laporan, dan cetak biodata/daftar hadir.

## Status Lisensi

Kode sumber ini bersifat source-available. Repository boleh dibuka untuk
dilihat, tetapi penggunaan, penyalinan, modifikasi, distribusi, deployment,
atau pembuatan karya turunan hanya boleh dilakukan dengan izin tertulis dari
pemilik repository.

Lihat file [LICENSE](LICENSE) untuk ketentuan lengkap.

## Disclaimer AI

Aplikasi ini dibangun dengan bantuan penuh AI. Walaupun begitu, penggunaan,
pengujian, validasi keamanan, validasi data, dan keputusan deployment tetap
menjadi tanggung jawab pemilik atau pengelola aplikasi.

## Struktur Singkat

- `public/` berisi front controller dan aset publik.
- `src/Controllers/` berisi controller aplikasi.
- `src/Views/` berisi tampilan aplikasi.
- `src/Services/` berisi layanan pendukung.
- `migrations/` berisi perubahan struktur database.
- `config/database.php` berisi konfigurasi koneksi database lokal.

## Catatan Keamanan

Sebelum deployment publik, pastikan konfigurasi database, kredensial, file
upload, dan pengaturan server sudah disesuaikan dengan lingkungan produksi.
