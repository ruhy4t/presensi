<?php
require_once __DIR__ . '/../../config/app.php';
function formatTanggalIndoPublic($tgl) {
    if (!$tgl || $tgl === '-') return '-';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
        $hari_inggris = date('l', strtotime($tgl));
        $hari_indo = ['Monday'=>'Senin', 'Tuesday'=>'Selasa', 'Wednesday'=>'Rabu', 'Thursday'=>'Kamis', 'Friday'=>'Jumat', 'Saturday'=>'Sabtu', 'Sunday'=>'Minggu'];
        $hari = $hari_indo[$hari_inggris] ?? '';

        $bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
        $parts = explode('-', $tgl);
        return $hari . ', ' . $parts[2] . ' ' . $bulan[$parts[1]] . ' ' . $parts[0];
    }
    return htmlspecialchars($tgl);
}

$eventMode = $isBeforeEvent ? 'before' : ($isEventDay ? 'day' : 'after');
$needsBiodata = $needsBiodata ?? true;
$useLegacyAttendance = !$needsBiodata;
$confirmationOpenLabel = $confirmationOpenLabel ?? 'sekarang';
$radiusEnabled = $radiusEnabled ?? false;
$gelombangOptions = $gelombangOptions ?? [];
$invitationNumber = trim((string) ($kegiatan['nomor_surat_undangan'] ?? ''));
$requiresInvitationNumber = $invitationNumber !== '' && $invitationNumber !== '-';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biodata Peserta - <?= htmlspecialchars($kegiatan['nama_kegiatan']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <style>
        body {
            background-color: #f3f4f6;
            background-image: linear-gradient(315deg, #d7dee9 0%, #f7fafc 74%);
        }
    </style>
</head>

<body class="min-h-screen py-8 px-4">
    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden" x-data="<?= $useLegacyAttendance ? 'legacyAttendanceForm()' : 'biodataAttendanceForm()' ?>">
        <div class="bg-blue-700 px-6 md:px-8 py-6 text-white">
            <p class="text-sm text-blue-100 font-semibold uppercase tracking-wide"><?= $useLegacyAttendance ? 'Daftar Hadir' : 'Biodata Peserta' ?></p>
            <h1 class="text-2xl md:text-3xl font-bold mt-1"><?= htmlspecialchars($kegiatan['nama_kegiatan']) ?></h1>
            <p class="mt-2 text-blue-100 text-sm">
                <i class="bi bi-calendar-event mr-1"></i>
                <?= formatTanggalIndoPublic($kegiatan['tanggal_pelaksanaan'] ?? '-') ?>
                <?php if (!empty($kegiatan['tanggal_selesai']) && $kegiatan['tanggal_selesai'] !== $kegiatan['tanggal_pelaksanaan']): ?>
                    s.d. <?= formatTanggalIndoPublic($kegiatan['tanggal_selesai']) ?>
                <?php endif; ?>
            </p>
            <?php if ($radiusEnabled): ?>
                <p class="mt-3 inline-flex items-center rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold text-emerald-50">
                    <i class="bi bi-geo-alt-fill mr-1"></i>Presensi dibatasi radius <?= number_format((int) $kegiatan['radius_meters'], 0, ',', '.') ?> meter
                </p>
            <?php endif; ?>
        </div>

        <?php if ($useLegacyAttendance): ?>
        <div x-show="!isSuccess" class="p-6 md:p-8">
            <?php if ($eventMode === 'before'): ?>
                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                    Presensi menggunakan waktu Asia/Jakarta (UTC+7). Konfirmasi kehadiran baru dibuka mulai <?= htmlspecialchars($confirmationOpenLabel) ?> WIB.
                </div>
            <?php endif; ?>
            <form @submit.prevent="submitLegacy" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="kegiatan_id" value="<?= (int)$kegiatan['id'] ?>">

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Nama Lengkap</label>
                    <input type="text" x-model="form.nama" class="field" placeholder="Isi nama lengkap" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Instansi</label>
                        <input type="text" x-model="form.instansi" class="field" placeholder="Instansi" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Jabatan</label>
                        <input type="text" x-model="form.jabatan" class="field" placeholder="Jabatan" required>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">No. HP / WhatsApp</label>
                    <input type="tel" x-model="form.hp" class="field" placeholder="08xxxxxxxxxx" required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Tanda Tangan</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg bg-gray-50 touch-none">
                        <canvas id="signature-pad" class="w-full h-44"></canvas>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <button type="button" @click="clearSignature" class="text-xs text-red-500 font-bold hover:underline">Hapus Tanda Tangan</button>
                        <div class="flex gap-2 items-center">
                            <span class="text-xs text-gray-500 font-semibold">Warna:</span>
                            <button type="button" @click="changeColor('rgb(0, 0, 0)')" class="w-6 h-6 rounded-full bg-black border-2 transition-all" :class="penColor === 'rgb(0, 0, 0)' ? 'border-gray-400 scale-110 shadow-md' : 'border-transparent'" title="Hitam"></button>
                            <button type="button" @click="changeColor('rgb(37, 99, 235)')" class="w-6 h-6 rounded-full bg-blue-600 border-2 transition-all" :class="penColor === 'rgb(37, 99, 235)' ? 'border-blue-300 scale-110 shadow-md' : 'border-transparent'" title="Biru"></button>
                        </div>
                    </div>
                </div>

                <div x-show="errorMessage" class="bg-red-100 text-red-700 p-3 rounded text-sm text-center" x-text="errorMessage"></div>
                <div x-show="successMessage" class="bg-green-100 text-green-700 p-3 rounded text-sm text-center" x-text="successMessage"></div>

                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-500/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="loading">
                    <span x-show="!loading">Kirim Presensi</span>
                    <span x-show="loading">Mengirim...</span>
                </button>
            </form>
        </div>
        <?php else: ?>
        <div x-show="!isSuccess" class="p-6 md:p-8">
            <?php if ($eventMode === 'before'): ?>
                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                    Pengisian ini adalah pra-registrasi. Setelah biodata dikirim, sistem akan memberikan token khusus. Konfirmasi kehadiran baru dibuka mulai <?= htmlspecialchars($confirmationOpenLabel) ?> WIB.
                </div>
            <?php elseif ($eventMode === 'after'): ?>
                <div class="mb-6 rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                    Tanggal kegiatan sudah lewat. Jika kegiatan masih dibuka oleh panitia, peserta yang sudah mengisi biodata tetap dapat konfirmasi hadir menggunakan token dan nomor surat undangan.
                </div>
            <?php endif; ?>

            <?php if ($eventMode !== 'before'): ?>
                <div class="mb-6 grid grid-cols-2 rounded-xl bg-gray-100 p-1">
                    <button type="button" @click="activeTab = 'token'"
                        class="rounded-lg py-3 text-sm font-bold transition"
                        :class="activeTab === 'token' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600'">
                        Sudah Punya Token
                    </button>
                    <button type="button" @click="activeTab = 'biodata'"
                        class="rounded-lg py-3 text-sm font-bold transition"
                        :class="activeTab === 'biodata' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600'">
                        Isi Biodata Baru
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($eventMode !== 'before'): ?>
                <div x-show="activeTab === 'token'">
                    <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
                        Peserta yang sudah mengisi biodata cukup memasukkan token<?= $requiresInvitationNumber ? ' dan nomor surat undangan' : '' ?> untuk konfirmasi kehadiran. Token muncul setelah biodata berhasil disimpan dan juga dapat dilihat panitia pada menu Peserta & Token.
                        <?php if ($requiresInvitationNumber): ?>
                            <div class="mt-2 font-semibold">Nomor surat undangan: <?= htmlspecialchars($invitationNumber) ?></div>
                        <?php endif; ?>
                    </div>
                    <form @submit.prevent="fetchPrefill" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="kegiatan_id" value="<?= (int)$kegiatan['id'] ?>">

                        <div class="grid grid-cols-1 <?= $requiresInvitationNumber ? 'md:grid-cols-2' : '' ?> gap-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Token</label>
                                <input type="text" x-model="tokenForm.token"
                                    class="w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-200 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-200 transition-all outline-none uppercase"
                                    placeholder="Contoh: A1B2C3D4" required>
                            </div>
                            <?php if ($requiresInvitationNumber): ?>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Nomor Surat Undangan</label>
                                    <input type="text" x-model="tokenForm.nomor_surat_undangan"
                                        class="w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-200 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-200 transition-all outline-none"
                                        placeholder="Nomor surat undangan" required>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit"
                            class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl shadow-lg shadow-blue-500/30 transition-all disabled:opacity-50"
                            :disabled="loading">
                            <span x-show="!loading">Cek Biodata</span>
                            <span x-show="loading">Memeriksa...</span>
                        </button>
                    </form>

                    <div x-show="prefillData" class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-5" style="display: none;">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900" x-text="prefillData?.nama_lengkap"></h2>
                                <p class="text-sm text-gray-600" x-text="prefillData?.jabatan + ' - ' + prefillData?.unit_kerja"></p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold"
                                :class="prefillStatus === 'attended' ? 'bg-green-100 text-green-700' : (prefillStatus === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700')"
                                x-text="prefillStatus === 'attended' ? 'Sudah Hadir' : (prefillStatus === 'cancelled' ? 'Dibatalkan Panitia' : 'Belum Hadir')"></span>
                        </div>

                        <dl class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div><dt class="font-semibold text-gray-500">NIP</dt><dd x-text="prefillData?.nip || '-'"></dd></div>
                            <div><dt class="font-semibold text-gray-500">TTL</dt><dd x-text="prefillData?.tempat_lahir + ', ' + prefillData?.tanggal_lahir"></dd></div>
                             <div><dt class="font-semibold text-gray-500">No. HP</dt><dd x-text="prefillData?.hp"></dd></div>
                            <div x-show="prefillGelombang"><dt class="font-semibold text-gray-500">Gelombang</dt><dd x-text="prefillGelombang"></dd></div>
                            <div x-show="prefillJadwal"><dt class="font-semibold text-gray-500">Jadwal Presensi</dt><dd x-text="prefillJadwal"></dd></div>
                            <div class="md:col-span-2"><dt class="font-semibold text-gray-500">Alamat Unit Kerja</dt><dd x-text="prefillData?.alamat_unit_kerja"></dd></div>
                            <div class="md:col-span-2"><dt class="font-semibold text-gray-500">Alamat Rumah</dt><dd x-text="prefillData?.alamat_rumah || '-' "></dd></div>
                        </dl>

                        <form @submit.prevent="confirmTokenAttendance" class="mt-5 space-y-4">
                            <div x-show="eventMode === 'before'" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm font-semibold text-yellow-800">
                                Biodata sudah ditemukan. Konfirmasi hadir baru dibuka mulai <?= htmlspecialchars($confirmationOpenLabel) ?> WIB.
                            </div>
                            <button type="submit"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-green-500/30 transition-all disabled:opacity-50"
                                :disabled="loading || prefillStatus === 'attended' || prefillStatus === 'cancelled' || prefillCanConfirm === false || eventMode === 'before'">
                                Konfirmasi Kehadiran
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div x-show="activeTab === 'biodata'">
                <form @submit.prevent="submitBiodata" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                     <input type="hidden" name="kegiatan_id" value="<?= (int)$kegiatan['id'] ?>">

                    <?php if ((int) ($kegiatan['gelombang_enabled'] ?? 0) === 1): ?>
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                            <label class="block text-sm font-bold text-indigo-900 mb-1">Gelombang sesuai undangan *</label>
                            <select x-model="form.gelombang_id" class="field bg-white" required>
                                <option value="">Pilih gelombang</option>
                                <?php foreach ($gelombangOptions as $gelombang): ?>
                                    <option value="<?= (int) $gelombang['id'] ?>">
                                        <?= htmlspecialchars($gelombang['nama']) ?> —
                                        <?php if (!empty($gelombang['tanggal'])): ?>
                                            <?= htmlspecialchars(date('d/m/Y', strtotime($gelombang['tanggal']))) ?>
                                            <?= htmlspecialchars(substr((string) $gelombang['waktu_mulai'], 0, 5)) ?>–<?= htmlspecialchars(substr((string) $gelombang['waktu_selesai'], 0, 5)) ?>
                                        <?php else: ?>
                                            Jadwal belum diatur
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-1 text-xs text-indigo-700">Pastikan pilihan sama dengan gelombang yang tercantum pada undangan.</p>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Nama Lengkap dengan Gelar</label>
                            <input type="text" x-model="form.nama_lengkap" class="field" placeholder="Nama lengkap" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Tempat Lahir</label>
                            <input type="text" x-model="form.tempat_lahir" class="field" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Tanggal Lahir</label>
                            <input type="date" x-model="form.tanggal_lahir" class="field" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Pangkat / Gol. Ruang</label>
                            <input type="text" x-model="form.pangkat_gol" class="field" placeholder="Opsional untuk non ASN">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">NIP</label>
                            <input type="text" x-model="form.nip" class="field" placeholder="Opsional jika tidak ada">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">NIK</label>
                            <input type="text" x-model="form.nik" inputmode="numeric" maxlength="16" class="field" placeholder="16 digit NIK" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Jabatan</label>
                            <input type="text" x-model="form.jabatan" class="field" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Unit Kerja</label>
                            <input type="text" x-model="form.unit_kerja" class="field" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">No. Telepon Unit Kerja</label>
                            <input type="text" x-model="form.telepon_unit_kerja" class="field">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Alamat Unit Kerja</label>
                            <textarea x-model="form.alamat_unit_kerja" class="field min-h-[90px]" required></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Alamat Rumah</label>
                            <textarea x-model="form.alamat_rumah" class="field min-h-[90px]" placeholder="Alamat tempat tinggal lengkap" required></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">No. HP</label>
                            <input type="tel" x-model="form.hp" class="field" placeholder="08xxxxxxxxxx" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Alamat Email</label>
                            <input type="email" x-model="form.email" class="field" placeholder="nama@email.com" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Tanda Tangan</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg bg-gray-50 touch-none">
                            <canvas id="signature-pad" class="w-full h-44"></canvas>
                        </div>
                        <div class="flex items-center justify-between mt-2">
                            <button type="button" @click="clearSignature" class="text-xs text-red-500 font-bold hover:underline">Hapus Tanda Tangan</button>
                            <div class="flex gap-2 items-center">
                                <span class="text-xs text-gray-500 font-semibold">Warna:</span>
                                <button type="button" @click="changeColor('rgb(0, 0, 0)')" class="w-6 h-6 rounded-full bg-black border-2 transition-all" :class="penColor === 'rgb(0, 0, 0)' ? 'border-gray-400 scale-110 shadow-md' : 'border-transparent'" title="Hitam"></button>
                                <button type="button" @click="changeColor('rgb(37, 99, 235)')" class="w-6 h-6 rounded-full bg-blue-600 border-2 transition-all" :class="penColor === 'rgb(37, 99, 235)' ? 'border-blue-300 scale-110 shadow-md' : 'border-transparent'" title="Biru"></button>
                            </div>
                        </div>
                    </div>

                    <?php if ($eventMode !== 'before'): ?>
                        <label x-show="shouldRequestLocationForBiodata()" class="flex items-start gap-3 rounded-lg bg-green-50 border border-green-200 p-4">
                            <input type="checkbox" x-model="form.confirm_hadir" class="mt-1 h-4 w-4 text-green-600 rounded">
                            <span class="text-sm font-semibold text-green-800">Saya menyatakan hadir pada kegiatan ini.</span>
                        </label>
                    <?php endif; ?>

                    <div x-show="errorMessage" class="bg-red-100 text-red-700 p-3 rounded text-sm text-center" x-text="errorMessage"></div>
                    <div x-show="successMessage" class="bg-green-100 text-green-700 p-3 rounded text-sm text-center" x-text="successMessage"></div>

                    <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-500/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="loading">
                        <span x-show="!loading" x-text="shouldRequestLocationForBiodata() ? 'Kirim Biodata dan Kehadiran' : 'Simpan Biodata dan Ambil Token'"></span>
                        <span x-show="loading">Mengirim...</span>
                    </button>
                </form>
            </div>

            <div x-show="errorMessage && activeTab === 'token'" class="mt-5 bg-red-100 text-red-700 p-3 rounded text-sm text-center" x-text="errorMessage"></div>
            <div x-show="successMessage && activeTab === 'token'" class="mt-5 bg-green-100 text-green-700 p-3 rounded text-sm text-center" x-text="successMessage"></div>
        </div>
        <?php endif; ?>

        <div x-show="isSuccess" class="px-8 py-12 text-center" style="display: none;">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-100 text-green-500 mb-6">
                <i class="bi bi-check2 text-4xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Data Tersimpan</h2>
            <p class="text-gray-600 mb-6" x-text="successMessage"></p>
            <div x-show="tokenResult" class="max-w-sm mx-auto bg-gray-900 text-white rounded-xl p-5 mb-6">
                <p class="text-xs uppercase tracking-wide text-gray-300">Token Kehadiran</p>
                <p class="text-3xl font-black tracking-[0.25em] mt-2" x-text="tokenResult"></p>
                <p class="text-xs text-gray-300 mt-3">Simpan token ini, akan digunakan untuk konfirmasi kehadiran pada saat pelaksanaan kegiatan.</p>
                <?php if ($requiresInvitationNumber): ?>
                    <p class="mt-3 rounded-lg bg-white/10 px-3 py-2 text-xs text-gray-100">Nomor surat: <?= htmlspecialchars($invitationNumber) ?></p>
                <?php endif; ?>
            </div>
            <button type="button" @click="resetForm" class="w-full max-w-sm bg-gray-800 hover:bg-gray-900 text-white font-bold py-4 rounded-xl shadow-lg transition-all">
                Kembali ke Form
            </button>
        </div>
        <p class="bg-gray-50 py-3 text-center text-[11px] font-semibold tracking-wide text-gray-400">
            <?= htmlspecialchars(APP_NAME) ?> v<?= htmlspecialchars(APP_VERSION) ?>
        </p>
    </div>

    <script>
        const attendanceRadiusEnabled = <?= $radiusEnabled ? 'true' : 'false' ?>;

        function requestAttendanceLocation(required = true) {
            if (!attendanceRadiusEnabled || !required) {
                return Promise.resolve(null);
            }
            if (!window.isSecureContext || !navigator.geolocation) {
                return Promise.reject(new Error('Lokasi hanya dapat dibaca melalui HTTPS dan browser yang mendukung GPS.'));
            }

            return new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(
                    position => resolve(position.coords),
                    error => {
                        const message = error.code === 1
                            ? 'Izin lokasi ditolak. Izinkan akses lokasi pada browser untuk melakukan presensi.'
                            : 'Lokasi belum dapat dibaca. Aktifkan GPS, tunggu hingga akurat, lalu coba kembali.';
                        reject(new Error(message));
                    },
                    { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
                );
            });
        }

        function appendAttendanceLocation(formData, coords) {
            if (!coords) return;
            formData.append('latitude', coords.latitude.toFixed(7));
            formData.append('longitude', coords.longitude.toFixed(7));
            formData.append('accuracy', coords.accuracy.toFixed(2));
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('legacyAttendanceForm', () => ({
                form: {
                    nama: '',
                    instansi: '',
                    jabatan: '',
                    hp: ''
                },
                signaturePad: null,
                loading: false,
                errorMessage: '',
                successMessage: '',
                isSuccess: false,
                penColor: 'rgb(0, 0, 0)',

                resizeSignatureCanvas() {
                    const canvas = document.getElementById('signature-pad');
                    if (!canvas || canvas.offsetWidth === 0) {
                        return;
                    }
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                    if (this.signaturePad) {
                        this.signaturePad.clear();
                    }
                },

                init() {
                    const canvas = document.getElementById('signature-pad');
                    window.addEventListener("resize", () => this.resizeSignatureCanvas());
                    this.resizeSignatureCanvas();
                    this.signaturePad = new SignaturePad(canvas, {
                        backgroundColor: 'rgba(255, 255, 255, 0)',
                        penColor: this.penColor,
                        minWidth: 1.5,
                        maxWidth: 3.5
                    });
                    setTimeout(() => this.resizeSignatureCanvas(), 50);
                },

                changeColor(color) {
                    this.penColor = color;
                    if (this.signaturePad) {
                        this.signaturePad.penColor = color;
                    }
                },

                clearSignature() {
                    this.signaturePad.clear();
                },

                csrfToken() {
                    return document.querySelector('[name="csrf_token"]').value;
                },

                kegiatanId() {
                    return document.querySelector('[name="kegiatan_id"]').value;
                },

                async submitLegacy() {
                    if (this.signaturePad.isEmpty()) {
                        this.errorMessage = 'Tanda tangan wajib diisi.';
                        return;
                    }

                    this.loading = true;
                    this.errorMessage = '';
                    this.successMessage = '';

                    let coords;
                    try {
                        coords = await requestAttendanceLocation();
                    } catch (error) {
                        this.loading = false;
                        this.errorMessage = error.message;
                        return;
                    }

                    const formData = new FormData();
                    formData.append('mode', 'legacy');
                    formData.append('kegiatan_id', this.kegiatanId());
                    formData.append('csrf_token', this.csrfToken());
                    formData.append('nama', this.form.nama);
                    formData.append('instansi', this.form.instansi);
                    formData.append('jabatan', this.form.jabatan);
                    formData.append('hp', this.form.hp);
                    formData.append('signature', this.signaturePad.toDataURL('image/png'));
                    appendAttendanceLocation(formData, coords);

                    fetch('/attendance/store', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            this.loading = false;
                            if (data.status === 'success') {
                                this.successMessage = 'Data presensi Anda sudah berhasil dikirim dan tersimpan di sistem.';
                                this.isSuccess = true;
                                this.signaturePad.clear();
                                window.scrollTo(0, 0);
                            } else {
                                this.errorMessage = data.message;
                            }
                        })
                        .catch(() => {
                            this.loading = false;
                            this.errorMessage = 'Terjadi kesalahan jaringan.';
                        });
                },

                resetForm() {
                    this.isSuccess = false;
                    this.successMessage = '';
                    this.errorMessage = '';
                    window.scrollTo(0, 0);
                }
            }));

            Alpine.data('biodataAttendanceForm', () => ({
                eventMode: '<?= $eventMode ?>',
                activeTab: '<?= $eventMode === 'before' ? 'biodata' : 'token' ?>',
                form: {
                    nama_lengkap: '',
                    tempat_lahir: '',
                    tanggal_lahir: '',
                    pangkat_gol: '',
                    nip: '',
                    nik: '',
                    jabatan: '',
                    unit_kerja: '',
                    alamat_unit_kerja: '',
                    telepon_unit_kerja: '',
                    alamat_rumah: '',
                    hp: '',
                    email: '',
                    gelombang_id: '',
                    confirm_hadir: <?= $eventMode === 'before' ? 'false' : 'false' ?>
                },
                tokenForm: {
                    token: '',
                    nomor_surat_undangan: ''
                },
                prefillData: null,
                prefillStatus: null,
                prefillGelombang: null,
                prefillJadwal: null,
                prefillCanConfirm: null,
                signaturePad: null,
                loading: false,
                errorMessage: '',
                successMessage: '',
                tokenResult: '',
                isSuccess: false,
                penColor: 'rgb(0, 0, 0)',

                resizeSignatureCanvas() {
                    const canvas = document.getElementById('signature-pad');
                    if (!canvas || canvas.offsetWidth === 0) {
                        return;
                    }
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                    if (this.signaturePad) {
                        this.signaturePad.clear();
                    }
                },

                init() {
                    const canvas = document.getElementById('signature-pad');

                    window.addEventListener("resize", () => this.resizeSignatureCanvas());
                    this.$watch('activeTab', () => {
                        setTimeout(() => this.resizeSignatureCanvas(), 50);
                    });
                    this.resizeSignatureCanvas();

                    this.signaturePad = new SignaturePad(canvas, {
                        backgroundColor: 'rgba(255, 255, 255, 0)',
                        penColor: this.penColor,
                        minWidth: 1.5,
                        maxWidth: 3.5
                    });

                    setTimeout(() => this.resizeSignatureCanvas(), 50);
                },

                changeColor(color) {
                    this.penColor = color;
                    if (this.signaturePad) {
                        this.signaturePad.penColor = color;
                    }
                },

                clearSignature() {
                    this.signaturePad.clear();
                },

                csrfToken() {
                    return document.querySelector('[name="csrf_token"]').value;
                },

                kegiatanId() {
                    return document.querySelector('[name="kegiatan_id"]').value;
                },

                async submitBiodata() {
                    if (this.signaturePad.isEmpty()) {
                        this.errorMessage = 'Tanda tangan wajib diisi.';
                        return;
                    }

                    if (this.shouldRequestLocationForBiodata() && !this.form.confirm_hadir) {
                        this.errorMessage = 'Konfirmasi kehadiran wajib dicentang.';
                        return;
                    }

                    this.loading = true;
                    this.errorMessage = '';
                    this.successMessage = '';
                    this.tokenResult = '';

                    let coords;
                    try {
                        coords = await requestAttendanceLocation(this.shouldRequestLocationForBiodata());
                    } catch (error) {
                        this.loading = false;
                        this.errorMessage = error.message;
                        return;
                    }

                    const formData = new FormData();
                    formData.append('mode', 'biodata');
                    formData.append('kegiatan_id', this.kegiatanId());
                    formData.append('csrf_token', this.csrfToken());
                    Object.keys(this.form).forEach((key) => {
                        formData.append(key, this.form[key] === true ? '1' : this.form[key]);
                    });
                    formData.append('signature', this.signaturePad.toDataURL('image/png'));
                    appendAttendanceLocation(formData, coords);

                    fetch('/attendance/store', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            this.loading = false;
                            if (data.status === 'success') {
                                this.successMessage = data.message;
                                this.tokenResult = data.token || '';
                                this.isSuccess = true;
                                window.scrollTo(0, 0);
                            } else {
                                this.errorMessage = data.message;
                            }
                        })
                        .catch(() => {
                            this.loading = false;
                            this.errorMessage = 'Terjadi kesalahan jaringan.';
                        });
                },

                fetchPrefill() {
                    this.loading = true;
                    this.errorMessage = '';
                    this.successMessage = '';
                    this.prefillData = null;
                    this.prefillStatus = null;
                    this.prefillGelombang = null;
                    this.prefillJadwal = null;
                    this.prefillCanConfirm = null;

                    const formData = new FormData();
                    formData.append('kegiatan_id', this.kegiatanId());
                    formData.append('csrf_token', this.csrfToken());
                    formData.append('token', this.tokenForm.token);
                    formData.append('nomor_surat_undangan', this.tokenForm.nomor_surat_undangan);

                    fetch('/attendance/prefill', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            this.loading = false;
                            if (data.status === 'success') {
                                this.prefillData = data.participant;
                                this.prefillStatus = data.registration_status;
                                this.prefillGelombang = data.gelombang_nama || null;
                                this.prefillJadwal = data.gelombang_jadwal || null;
                                this.prefillCanConfirm = data.gelombang_can_confirm;
                                this.successMessage = data.message;
                            } else {
                                this.errorMessage = data.message;
                            }
                        })
                        .catch(() => {
                            this.loading = false;
                            this.errorMessage = 'Terjadi kesalahan jaringan.';
                        });
                },

                async confirmTokenAttendance() {
                    this.loading = true;
                    this.errorMessage = '';
                    this.successMessage = '';

                    let coords;
                    try {
                        coords = await requestAttendanceLocation();
                    } catch (error) {
                        this.loading = false;
                        this.errorMessage = error.message;
                        return;
                    }

                    const formData = new FormData();
                    formData.append('mode', 'token_confirm');
                    formData.append('kegiatan_id', this.kegiatanId());
                    formData.append('csrf_token', this.csrfToken());
                    formData.append('token', this.tokenForm.token);
                    formData.append('nomor_surat_undangan', this.tokenForm.nomor_surat_undangan);
                    formData.append('confirm_hadir', '1');
                    appendAttendanceLocation(formData, coords);

                    fetch('/attendance/store', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            this.loading = false;
                            if (data.status === 'success') {
                                this.successMessage = data.message;
                                this.tokenResult = '';
                                this.isSuccess = true;
                                window.scrollTo(0, 0);
                            } else {
                                this.errorMessage = data.message;
                            }
                        })
                        .catch(() => {
                            this.loading = false;
                            this.errorMessage = 'Terjadi kesalahan jaringan.';
                        });
                },

                shouldRequestLocationForBiodata() {
                    if (this.eventMode === 'before') return false;
                    const schedules = <?= json_encode(array_map(static fn(array $wave): array => [
                        'id' => (string) $wave['id'],
                        'tanggal' => $wave['tanggal'],
                        'mulai' => substr((string) $wave['presensi_mulai'], 0, 5),
                        'selesai' => substr((string) $wave['presensi_selesai'], 0, 5),
                    ], $gelombangOptions), JSON_UNESCAPED_SLASHES) ?>;
                    if (schedules.length === 0) return true;
                    const selected = schedules.find(item => item.id === String(this.form.gelombang_id));
                    if (!selected) return false;
                    const now = new Date();
                    const opensAt = new Date(selected.tanggal + 'T' + selected.mulai + ':00+07:00');
                    const closesAt = new Date(selected.tanggal + 'T' + selected.selesai + ':00+07:00');
                    return now >= opensAt && now <= closesAt;
                },

                resetForm() {
                    this.isSuccess = false;
                    this.successMessage = '';
                    this.errorMessage = '';
                    this.tokenResult = '';
                    window.scrollTo(0, 0);
                }
            }))
        });
    </script>
    <style>
        .field {
            width: 100%;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 0.75rem 1rem;
            outline: none;
            transition: all 0.15s ease;
        }

        .field:focus {
            border-color: #3b82f6;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
        }
    </style>
</body>

</html>
