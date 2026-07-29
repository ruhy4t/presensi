# Presensi

Versi aplikasi: **1.1.0** (29 Juli 2026)

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

## Persiapan Lokal

Konfigurasi bawaan tetap kompatibel dengan Laragon (`root` tanpa password dan
database `daftar_hadir_db`). Untuk konfigurasi yang lebih aman, salin
`.env.example` menjadi `.env`, lalu sesuaikan nilainya. File `.env` tidak akan
masuk ke Git.

Jalankan migration secara eksplisit setelah membuat backup:

```powershell
php scripts/backup.php
php scripts/migrate.php
```

Migration bersifat idempoten: aman dijalankan pada database lama maupun
database baru, dan migration yang sudah tercatat tidak akan dijalankan ulang.
Backup disimpan di `var/backups/`, di luar document root dan diabaikan Git.

### Pembaruan versi 1.1.0

- Radius lokasi opsional per kegiatan dengan validasi ulang jarak di server.
- Penyimpanan koordinat, akurasi GPS, dan jarak sebagai bukti presensi.
- Kegiatan per gelombang dan pilihan gelombang pada formulir biodata.
- Gelombang tampil pada daftar peserta, biodata cetak, daftar hadir, dan ekspor.
- Versi aplikasi tampil pada sidebar dan halaman presensi publik.

Untuk pembaruan manual melalui phpMyAdmin, impor
`migrations/2026_07_29_radius_gelombang.sql` sebelum mengunggah kode versi ini.
Kegiatan lama tetap memiliki radius dan gelombang dalam keadaan nonaktif. Fitur
tersebut dapat diaktifkan melalui menu **Edit Kegiatan**.

## Pemeriksaan Sebelum Deployment

```powershell
php tests/run.php
php scripts/doctor.php
```

`tests/run.php` tidak membutuhkan database dan memeriksa kontrak perilaku utama.
`doctor.php` bersifat read-only dan memeriksa PHP, ekstensi, koneksi, serta tabel
inti. GitHub Actions juga menjalankan pemeriksaan sintaks dan regression checks
pada setiap push atau pull request.

Jika database lokal tersedia, jalankan integration check read-only untuk filter
peserta dan laporan:

```powershell
php tests/integration.php
```

Urutan deployment yang disarankan:

1. Jalankan regression checks.
2. Backup database dan folder `public/uploads`.
3. Jalankan migration (atau impor SQL migration terbaru melalui phpMyAdmin).
4. Jalankan health check.
5. Uji login, pembuatan kegiatan, registrasi, presensi, dan ekspor laporan.

Untuk hosting tanpa akses CLI, backup database melalui phpMyAdmin lalu impor
file migration baru melalui menu **Import**. Kegiatan satu hari tetap memakai
`tanggal_pelaksanaan`; `tanggal_selesai` hanya diisi untuk kegiatan beberapa
hari. Satu peserta tetap hanya dapat tercatat hadir satu kali untuk keseluruhan
kegiatan.

## Catatan Keamanan

Sebelum deployment publik, pastikan konfigurasi database, kredensial, file
upload, dan pengaturan server sudah disesuaikan dengan lingkungan produksi.
