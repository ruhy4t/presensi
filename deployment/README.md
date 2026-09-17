# Update tautan pendek dan daftar sekolah

1. Cadangkan database produksi.
2. Di phpMyAdmin, pilih database presensi dan import update_short_links_schools.sql.
   File ini hanya membuat tabel yang belum ada, aman diimport ulang, dan mencakup tautan pendek, alias inisial kegiatan, serta daftar sekolah.
3. Unggah file aplikasi terbaru berikut dengan struktur folder yang sama:
   - public/index.php
   - src/Services/KegiatanUrlService.php
   - src/Services/KegiatanSchoolService.php
   - src/Services/RegistrationSummaryService.php
   - src/Controllers/KegiatanController.php
   - src/Controllers/AttendanceController.php
   - src/Controllers/RegistrationController.php
   - src/Controllers/ReportController.php
   - src/Views/dashboard.php
   - src/Views/attendance_form.php
   - src/Views/print_qr.php
4. Pada tambah/edit kegiatan, isi Daftar Sekolah satu nama per baris. Kosongkan untuk instansi/unit kerja bebas.
5. Periksa formulir biodata, tautan pendek, cetak QR, dan laporan.

SQL ini menambahkan dua tabel untuk fitur baru dan mengasumsikan skema aplikasi sebelumnya sudah terpasang.
Jangan import backup users lokal ke produksi. Tidak perlu mengunggah tests atau konfigurasi lokal.

Jika pembaruan sekolah dan tautan pendek sudah terpasang, import update_link_initials.sql lalu unggah src/Services/KegiatanUrlService.php dan public/index.php untuk alias inisial. Tautan lama tetap berlaku.
