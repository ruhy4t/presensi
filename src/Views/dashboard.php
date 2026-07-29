<?php
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Controllers/KegiatanController.php';
require_once __DIR__ . '/../Services/KegiatanUrlService.php';

AuthMiddleware::check();
$user = AuthMiddleware::user();
$controller = new KegiatanController();

$kegiatanList = $controller->index();

$countAktif = 0;
$countNonAktif = 0;
$countArsip = 0;
foreach ($kegiatanList as $k) {
    if ($k['status'] === 'Aktif') $countAktif++;
    elseif ($k['status'] === 'Non-Aktif') $countNonAktif++;
    elseif ($k['status'] === 'Diarsipkan') $countArsip++;
}

function formatTanggalIndo($tgl) {
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

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];

$kegiatanListJson = array_map(function($keg) use ($user) {
    return [
        'id' => $keg['id'],
        'nama_kegiatan' => htmlspecialchars($keg['nama_kegiatan'] ?? ''),
        'jenis_kegiatan' => htmlspecialchars($keg['jenis_kegiatan'] ?? 'Daring'),
        'nomor_surat_undangan' => htmlspecialchars($keg['nomor_surat_undangan'] ?? ''),
        'perlu_biodata' => htmlspecialchars($keg['perlu_biodata'] ?? 'Ya'),
        'status' => $keg['status'],
        'tanggal_pelaksanaan' => $keg['tanggal_pelaksanaan'] ?? '',
        'tanggal_selesai' => $keg['tanggal_selesai'] ?? '',
        'tanggal_pelaksanaan_indo' => formatTanggalIndo($keg['tanggal_pelaksanaan'] ?? '-'),
        'rentang_tanggal_indo' => formatTanggalIndo($keg['tanggal_pelaksanaan'] ?? '-')
            . (!empty($keg['tanggal_selesai']) && $keg['tanggal_selesai'] !== $keg['tanggal_pelaksanaan']
                ? ' s.d. ' . formatTanggalIndo($keg['tanggal_selesai'])
                : ''),
        'waktu_pelaksanaan' => htmlspecialchars($keg['waktu_pelaksanaan'] ?? '-'),
        'tempat_pelaksanaan' => htmlspecialchars($keg['tempat_pelaksanaan'] ?? ''),
        'radius_enabled' => (int) ($keg['radius_enabled'] ?? 0),
        'latitude' => $keg['latitude'] ?? '',
        'longitude' => $keg['longitude'] ?? '',
        'radius_meters' => $keg['radius_meters'] ?? 100,
        'gelombang_enabled' => (int) ($keg['gelombang_enabled'] ?? 0),
        'gelombang_names' => $keg['gelombang_names'] ?? '',
        'catatan' => htmlspecialchars($keg['catatan'] ?? ''),
        'pejabat_penanggung_jawab' => htmlspecialchars($keg['pejabat_penanggung_jawab'] ?? ''),
        'jabatan_penanggung_jawab' => htmlspecialchars($keg['jabatan_penanggung_jawab'] ?? ''),
        'nip_penanggung_jawab' => htmlspecialchars($keg['nip_penanggung_jawab'] ?? ''),
        'attendance_url' => KegiatanUrlService::attendancePath($keg),
        'attendance_count' => (int) ($keg['attendance_count'] ?? 0),
        'registration_count' => (int) ($keg['registration_count'] ?? 0),
        'confirmed_count' => (int) ($keg['confirmed_count'] ?? 0),
        'unconfirmed_count' => (int) ($keg['unconfirmed_count'] ?? 0),
        'creator_name' => htmlspecialchars($keg['creator_name'] ?? 'Unknown'),
        'created_at_fmt' => date('d M Y', strtotime($keg['created_at'] ?? 'now')),
        'isAdmin' => ($user['role'] === 'admin')
    ];
}, $kegiatanList);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Daftar Hadir</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 231, 235, 0.5);
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 font-sans antialiased">

    <div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: false }">

        <?php $activeMenu = 'dashboard'; require __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- Header -->
            <header class="flex items-center justify-between px-6 py-4 bg-white border-b shadow-sm lg:hidden">
                <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 focus:outline-none">
                    <i class="bi bi-list text-2xl"></i>
                </button>
                <span class="font-semibold text-lg">Dashboard</span>
                <div class="w-8"></div> <!-- Spacer -->
            </header>

            <!-- Content Body -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6" x-data="{ 
                kegiatans: <?= htmlspecialchars(json_encode($kegiatanListJson), ENT_QUOTES, 'UTF-8') ?>,
                searchQuery: '', 
                currentFilter: 'all',
                currentPage: 1,
                itemsPerPage: 6,
                showQrModal: false, 
                qrUrl: '', 
                qrTitle: '', 
                editData: {},
                showEditModal: false,
                get filteredItems() {
                    return this.kegiatans.filter(k => 
                        (this.currentFilter === 'all' || k.status === this.currentFilter) && 
                        (this.searchQuery === '' || k.nama_kegiatan.toLowerCase().includes(this.searchQuery.toLowerCase()))
                    );
                },
                get paginatedItems() {
                    let start = (this.currentPage - 1) * this.itemsPerPage;
                    return this.filteredItems.slice(start, start + this.itemsPerPage);
                },
                get totalPages() {
                    return Math.ceil(this.filteredItems.length / this.itemsPerPage) || 1;
                },
                init() {
                    this.$watch('searchQuery', () => { this.currentPage = 1; });
                    this.$watch('currentFilter', () => { this.currentPage = 1; });
                },
                openEditModal(keg) {
                    this.editData = keg;
                    this.showEditModal = true;
                },
                generateQr(url, title) { 
                    this.qrUrl = url; 
                    this.qrTitle = title; 
                    this.showQrModal = true; 
                    setTimeout(() => {
                        document.getElementById('qrcode-container').innerHTML = ''; 
                        new QRCode(document.getElementById('qrcode-container'), {text: url, width: 256, height: 256}); 
                    }, 50);
                } 
            }">

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div @click="currentFilter = 'all'" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 cursor-pointer transition-all hover:shadow-md" :class="currentFilter === 'all' ? 'ring-2 ring-blue-500' : ''">
                        <p class="text-sm text-gray-500 font-medium">Total Kegiatan</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?= count($kegiatanList) ?></h3>
                    </div>
                    <div @click="currentFilter = 'Aktif'" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 cursor-pointer transition-all hover:shadow-md" :class="currentFilter === 'Aktif' ? 'ring-2 ring-green-500' : ''">
                        <p class="text-sm text-green-600 font-medium">Aktif</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?= $countAktif ?></h3>
                    </div>
                    <div @click="currentFilter = 'Non-Aktif'" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 cursor-pointer transition-all hover:shadow-md" :class="currentFilter === 'Non-Aktif' ? 'ring-2 ring-gray-500' : ''">
                        <p class="text-sm text-gray-600 font-medium">Non-Aktif</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?= $countNonAktif ?></h3>
                    </div>
                    <div @click="currentFilter = 'Diarsipkan'" class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 cursor-pointer transition-all hover:shadow-md" :class="currentFilter === 'Diarsipkan' ? 'ring-2 ring-yellow-500' : ''">
                        <p class="text-sm text-yellow-600 font-medium">Diarsipkan</p>
                        <h3 class="text-2xl font-bold text-gray-800"><?= $countArsip ?></h3>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <h2 class="text-2xl font-bold text-gray-800">Daftar Kegiatan</h2>
                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <div class="relative w-full md:w-64">
                            <input type="text" x-model="searchQuery" placeholder="Cari kegiatan..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                        </div>
                        <button @click="$dispatch('open-add-modal')"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg shadow-lg shadow-blue-500/30 transition-all flex items-center gap-2 whitespace-nowrap">
                            <i class="bi bi-plus-lg"></i> Tambah
                        </button>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash_success'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded shadow-sm"
                        role="alert">
                        <p class="font-bold">Sukses</p>
                        <p>
                            <?= $_SESSION['flash_success'] ?>
                        </p>
                    </div>
                    <?php unset($_SESSION['flash_success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['flash_error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded shadow-sm" role="alert">
                        <p class="font-bold">Error</p>
                        <p>
                            <?= $_SESSION['flash_error'] ?>
                        </p>
                    </div>
                    <?php unset($_SESSION['flash_error']); ?>
                <?php endif; ?>

                <!-- Activity List Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <template x-for="kegiatan in paginatedItems" :key="kegiatan.id">
                        <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow border border-gray-100 flex flex-col h-full">
                            <div class="p-5 flex-1">
                                <div class="flex justify-between items-start mb-3 gap-3">
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            class="px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            :class="kegiatan.status === 'Aktif' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" x-text="kegiatan.status">
                                        </span>
                                        <span x-show="kegiatan.radius_enabled == 1"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                            <i class="bi bi-geo-alt-fill mr-1"></i>Radius
                                        </span>
                                        <span x-show="kegiatan.gelombang_enabled == 1"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            <i class="bi bi-layers-fill mr-1"></i>Gelombang
                                        </span>
                                        <span
                                            class="px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            :class="kegiatan.jenis_kegiatan === 'Daring' ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800'"
                                            x-text="kegiatan.jenis_kegiatan">
                                        </span>
                                        <span
                                            class="px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            :class="kegiatan.perlu_biodata === 'Ya' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-700'"
                                            x-text="kegiatan.perlu_biodata === 'Ya' ? 'Biodata' : 'Presensi'">
                                        </span>
                                    </div>
                                    <div class="dropdown relative" x-data="{ open: false }">
                                        <button @click="open = !open" @click.away="open = false"
                                            class="text-gray-400 hover:text-gray-600">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <div x-show="open"
                                            class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 border border-gray-100"
                                            style="display: none;">
                                            <button @click="$dispatch('open-edit-modal', kegiatan)" class="w-full text-left block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="bi bi-pencil mr-2"></i>Edit Kegiatan</button>
                                            
                                            <form action="/dashboard" method="POST" class="w-full m-0">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="kegiatan_id" :value="kegiatan.id">
                                                <input type="hidden" name="status" :value="kegiatan.status === 'Aktif' ? 'Non-Aktif' : 'Aktif'">
                                                <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <i class="bi mr-2" :class="kegiatan.status === 'Aktif' ? 'bi-x-circle' : 'bi-check-circle'"></i><span x-text="kegiatan.status === 'Aktif' ? 'Non-Aktifkan' : 'Aktifkan'"></span>
                                                </button>
                                            </form>
                                            
                                            <form action="/dashboard" method="POST" class="w-full m-0">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="kegiatan_id" :value="kegiatan.id">
                                                <input type="hidden" name="status" value="Diarsipkan">
                                                <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="bi bi-archive mr-2"></i>Arsipkan</button>
                                            </form>

                                            <button @click="generateQr('<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>' + kegiatan.attendance_url, kegiatan.nama_kegiatan)"
                                                class="w-full text-left block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="bi bi-qr-code mr-2"></i>Tampilkan QR Code</button>
                                            <a :href="'/registrations?id=' + kegiatan.id"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="bi bi-person-lines-fill mr-2"></i>Peserta & Token</a>
                                            <a :href="'/report/print?id=' + kegiatan.id" target="_blank"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="bi bi-printer mr-2"></i>Cetak / PDF</a>
                                            <a :href="'/report/export?id=' + kegiatan.id + '&format=csv'"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="bi bi-filetype-csv mr-2"></i>Export CSV</a>
                                            <a :href="'/report/export?id=' + kegiatan.id + '&format=xls'"
                                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="bi bi-filetype-xls mr-2"></i>Export XLS</a>
                                            <div class="border-t border-gray-100 my-1"></div>
                                            
                                            <form action="/dashboard" method="POST" class="w-full m-0" @submit.prevent="if(kegiatan.attendance_count > 0) { alert('Kegiatan tidak bisa dihapus karena sudah memiliki peserta.'); } else if(confirm('Yakin ingin menghapus?')) { $el.submit(); }">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="kegiatan_id" :value="kegiatan.id">
                                                <input type="hidden" name="status" value="Dihapus">
                                                <button type="submit" class="w-full text-left block px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="bi bi-trash mr-2"></i>Hapus</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800 mb-2 truncate"
                                    :title="kegiatan.nama_kegiatan" x-text="kegiatan.nama_kegiatan">
                                </h3>
                                <div class="text-sm text-gray-500 space-y-1">
                                    <p><i class="bi bi-calendar-event mr-2"></i>
                                        <span x-text="kegiatan.rentang_tanggal_indo"></span>
                                    </p>
                                    <p><i class="bi bi-clock mr-2"></i>
                                        <span x-text="kegiatan.waktu_pelaksanaan"></span>
                                    </p>
                                    <p><i class="bi bi-people-fill mr-2"></i>
                                        <span x-text="kegiatan.attendance_count + ' Peserta'"></span>
                                    </p>
                                    <template x-if="kegiatan.catatan">
                                        <p class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-amber-900">
                                            <i class="bi bi-sticky mt-0.5"></i>
                                            <span class="line-clamp-2" :title="kegiatan.catatan" x-text="kegiatan.catatan"></span>
                                        </p>
                                    </template>
                                    <div class="grid grid-cols-3 gap-2 pt-2">
                                        <div class="rounded-lg bg-blue-50 px-2 py-2 text-center">
                                            <p class="text-[11px] font-semibold text-blue-700">Registrasi</p>
                                            <p class="text-base font-bold text-blue-900" x-text="kegiatan.registration_count"></p>
                                        </div>
                                        <div class="rounded-lg bg-green-50 px-2 py-2 text-center">
                                            <p class="text-[11px] font-semibold text-green-700">Hadir</p>
                                            <p class="text-base font-bold text-green-900" x-text="kegiatan.confirmed_count"></p>
                                        </div>
                                        <div class="rounded-lg bg-yellow-50 px-2 py-2 text-center">
                                            <p class="text-[11px] font-semibold text-yellow-700">Belum Konfirmasi</p>
                                            <p class="text-base font-bold text-yellow-900" x-text="kegiatan.unconfirmed_count"></p>
                                        </div>
                                    </div>
                                    <template x-if="kegiatan.isAdmin">
                                        <p><i class="bi bi-person mr-2"></i>
                                            <span x-text="kegiatan.creator_name"></span>
                                        </p>
                                    </template>
                                </div>
                            </div>
                            <a :href="'/report/print?id=' + kegiatan.id" target="_blank"
                                class="px-5 py-3 border-t border-gray-100 flex justify-between items-center group cursor-pointer transition-colors rounded-b-xl"
                                :class="kegiatan.status !== 'Aktif' ? 'bg-blue-50 hover:bg-blue-100' : 'bg-gray-50 hover:bg-blue-50'">
                                <span class="text-sm font-medium" :class="kegiatan.status !== 'Aktif' ? 'text-blue-700 font-bold' : 'text-gray-600 group-hover:text-blue-600'"
                                      x-text="kegiatan.status !== 'Aktif' ? 'Cetak Ulang Daftar Hadir (PDF)' : 'Lihat Laporan Absensi'">
                                </span>
                                <i class="bi bi-printer transition-colors" :class="kegiatan.status !== 'Aktif' ? 'text-blue-700' : 'text-gray-400 group-hover:text-blue-600'"></i>
                            </a>
                        </div>
                    </template>

                    <template x-if="filteredItems.length === 0">
                        <div class="col-span-full text-center py-12 bg-white rounded-xl border border-dashed border-gray-300">
                            <div class="text-gray-300 text-5xl mb-4"><i class="bi bi-inbox"></i></div>
                            <p class="text-gray-500 font-medium">Belum ada kegiatan.</p>
                            <p class="text-sm text-gray-400">Silakan tambahkan kegiatan baru atau ubah kata kunci pencarian.</p>
                        </div>
                    </template>
                </div>

                <!-- Pagination Controls -->
                <template x-if="totalPages > 1">
                    <div class="flex justify-center items-center mt-8 gap-2">
                        <button @click="currentPage--" :disabled="currentPage === 1" 
                                class="px-4 py-2 border rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors bg-white">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        
                        <div class="flex gap-1">
                            <template x-for="page in totalPages" :key="page">
                                <button @click="currentPage = page" 
                                        class="w-10 h-10 rounded-lg border font-medium transition-colors"
                                        :class="currentPage === page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 hover:bg-gray-50'"
                                        x-text="page">
                                </button>
                            </template>
                        </div>

                        <button @click="currentPage++" :disabled="currentPage === totalPages"
                                class="px-4 py-2 border rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors bg-white">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </template>

                <!-- Modal QR Code -->
                <div x-show="showQrModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="showQrModal = false">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 text-center">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2" x-text="qrTitle"></h3>
                                <p class="text-sm text-gray-500 mb-4">Scan QR Code di bawah untuk absen</p>
                                <div id="qrcode-container" class="flex justify-center p-4 bg-white border rounded-lg mx-auto w-fit"></div>
                                <div class="mt-4">
                                    <a :href="qrUrl" target="_blank" class="text-sm text-blue-600 hover:underline break-all" x-text="qrUrl"></a>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" @click="showQrModal = false"
                                    class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                                    Tutup
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>

        <!-- Modal Tambah Kegiatan -->
        <div x-data="{ open: false }" @open-add-modal.window="open = true" x-show="open"
            class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">

            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="open = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl lg:max-w-3xl w-full">
                    <form action="/dashboard" method="POST">
                        <input type="hidden" name="action" value="add_kegiatan">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-5 max-h-[calc(100vh-11rem)] overflow-y-auto">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Tambah Kegiatan Baru</h3>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Nama Kegiatan *</label>
                                <input type="text" name="nama_kegiatan" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500"
                                    placeholder="Contoh: Rapat Evaluasi Anggaran">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Jenis Kegiatan *</label>
                                    <select name="jenis_kegiatan"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                        <option value="Daring">Daring</option>
                                        <option value="Luring">Luring</option>
                                    </select>
                                </div>
                                <div x-data="{ perlu: 'Ya' }">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Perlu Biodata *</label>
                                    <select name="perlu_biodata" x-model="perlu" @change="$dispatch('perlu-biodata-add', perlu)"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                    </select>
                                </div>
                                <div x-data="{ perlu: 'Ya' }" @perlu-biodata-add.window="perlu = $event.detail" class="md:col-span-2">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Nomor Surat Undangan</label>
                                    <input type="text" name="nomor_surat_undangan" :required="perlu === 'Ya'"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500"
                                        placeholder="Contoh: 400.3/123-Disdik">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai</label>
                                    <input type="date" name="tanggal_pelaksanaan" id="add-tanggal-mulai" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Selesai <span class="font-normal text-gray-400">(opsional)</span></label>
                                    <input type="date" name="tanggal_selesai" :min="document.getElementById('add-tanggal-mulai')?.value || ''" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Jam Pelaksanaan</label>
                                    <input type="text" name="waktu_pelaksanaan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500" placeholder="08:00 s.d Selesai">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Tempat Pelaksanaan</label>
                                <input type="text" name="tempat_pelaksanaan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500" placeholder="Contoh: Ruang Rapat Lt. 2">
                            </div>
                            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4"
                                x-data="{ enabled: false, lat: '', lng: '', locating: false }">
                                <label class="flex items-center gap-3 font-bold text-emerald-900">
                                    <input type="checkbox" name="radius_enabled" value="1" x-model="enabled" class="h-4 w-4 rounded">
                                    Batasi presensi berdasarkan radius lokasi
                                </label>
                                <div x-show="enabled" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Latitude *</label>
                                        <input type="number" step="0.0000001" min="-90" max="90" name="latitude" x-model="lat" :required="enabled"
                                            class="w-full rounded border px-3 py-2" placeholder="-6.597147">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Longitude *</label>
                                        <input type="number" step="0.0000001" min="-180" max="180" name="longitude" x-model="lng" :required="enabled"
                                            class="w-full rounded border px-3 py-2" placeholder="106.806039">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Radius (meter) *</label>
                                        <input type="number" min="10" max="5000" name="radius_meters" value="100" :required="enabled"
                                            class="w-full rounded border px-3 py-2">
                                    </div>
                                    <div class="md:col-span-3">
                                        <button type="button" :disabled="locating"
                                            @click="locating=true; navigator.geolocation.getCurrentPosition(p => { lat=p.coords.latitude.toFixed(7); lng=p.coords.longitude.toFixed(7); locating=false }, () => { locating=false; alert('Lokasi tidak dapat dibaca. Pastikan izin lokasi aktif dan situs memakai HTTPS.') }, {enableHighAccuracy:true, timeout:10000})"
                                            class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">
                                            <i class="bi bi-crosshair mr-1"></i><span x-text="locating ? 'Membaca lokasi...' : 'Gunakan lokasi perangkat ini'"></span>
                                        </button>
                                        <p class="mt-2 text-xs text-emerald-800">Kegiatan lama tetap berjalan seperti biasa sampai fitur ini diaktifkan.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4" x-data="{ enabled: false }">
                                <label class="flex items-center gap-3 font-bold text-indigo-900">
                                    <input type="checkbox" name="gelombang_enabled" value="1" x-model="enabled" class="h-4 w-4 rounded">
                                    Kegiatan dibagi menjadi beberapa gelombang
                                </label>
                                <div x-show="enabled" class="mt-4">
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Daftar Gelombang *</label>
                                    <textarea name="gelombang_names" rows="4" :required="enabled"
                                        class="w-full rounded border px-3 py-2"
                                        placeholder="Gelombang 1&#10;Gelombang 2&#10;Gelombang 3"></textarea>
                                    <p class="mt-1 text-xs text-indigo-800">Tulis satu nama gelombang per baris. Pilihan akan tampil pada formulir biodata.</p>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Catatan Internal</label>
                                <textarea name="catatan" rows="3"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500"
                                    placeholder="Contoh: Bimtek dengan operator SD"></textarea>
                                <p class="mt-1 text-xs text-gray-500">Hanya tersimpan di sistem, tidak ikut dicetak.</p>
                            </div>
                            <div class="mb-4 border-t pt-4">
                                <h4 class="font-medium text-gray-800 mb-2">Info Penanggung Jawab (Untuk Cetak)</h4>
                                <div class="mb-3">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Nama Pejabat</label>
                                    <input type="text" name="pejabat_penanggung_jawab" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500" placeholder="Nama Lengkap Pejabat">
                                </div>
                                <div class="mb-3">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Jabatan</label>
                                    <input type="text" name="jabatan_penanggung_jawab" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500" placeholder="Contoh: Kepala Bagian Umum">
                                </div>
                                <div class="mb-3">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">NIP (Opsional)</label>
                                    <input type="text" name="nip_penanggung_jawab" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500" placeholder="Biarkan kosong jika tidak ada">
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                                Simpan
                            </button>
                            <button type="button" @click="open = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Kegiatan (Dipindah ke dalam scope main atau bisa juga diakses dengan custom event) -->
        <!-- Karena Alpine x-data tidak tembus, kita ubah Edit Modal menggunakan event listener seperti Add Modal -->
        <div x-data="{ openEdit: false, editData: {} }" @open-edit-modal.window="editData = $event.detail; openEdit = true" x-show="openEdit" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="openEdit = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl lg:max-w-3xl w-full">
                    <form action="/dashboard" method="POST">
                        <input type="hidden" name="action" value="edit_kegiatan">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="kegiatan_id" x-model="editData.id">

                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-5 max-h-[calc(100vh-11rem)] overflow-y-auto">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Kegiatan</h3>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Nama Kegiatan *</label>
                                <input type="text" name="nama_kegiatan" required x-model="editData.nama_kegiatan"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Jenis Kegiatan *</label>
                                    <select name="jenis_kegiatan" x-model="editData.jenis_kegiatan"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                        <option value="Daring">Daring</option>
                                        <option value="Luring">Luring</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Perlu Biodata *</label>
                                    <select name="perlu_biodata" x-model="editData.perlu_biodata"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                        <option value="Ya">Ya</option>
                                        <option value="Tidak">Tidak</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Nomor Surat Undangan</label>
                                    <input type="text" name="nomor_surat_undangan" x-model="editData.nomor_surat_undangan" :required="editData.perlu_biodata === 'Ya'"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai</label>
                                    <input type="date" name="tanggal_pelaksanaan" x-model="editData.tanggal_pelaksanaan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Selesai <span class="font-normal text-gray-400">(opsional)</span></label>
                                    <input type="date" name="tanggal_selesai" x-model="editData.tanggal_selesai" :min="editData.tanggal_pelaksanaan || ''" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Jam Pelaksanaan</label>
                                    <input type="text" name="waktu_pelaksanaan" x-model="editData.waktu_pelaksanaan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Tempat Pelaksanaan</label>
                                <input type="text" name="tempat_pelaksanaan" x-model="editData.tempat_pelaksanaan" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                            </div>
                            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                <label class="flex items-center gap-3 font-bold text-emerald-900">
                                    <input type="checkbox" name="radius_enabled" value="1" x-model="editData.radius_enabled" class="h-4 w-4 rounded">
                                    Batasi presensi berdasarkan radius lokasi
                                </label>
                                <div x-show="editData.radius_enabled" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Latitude *</label>
                                        <input type="number" step="0.0000001" min="-90" max="90" name="latitude" x-model="editData.latitude" :required="!!editData.radius_enabled" class="w-full rounded border px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Longitude *</label>
                                        <input type="number" step="0.0000001" min="-180" max="180" name="longitude" x-model="editData.longitude" :required="!!editData.radius_enabled" class="w-full rounded border px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 mb-1">Radius (meter) *</label>
                                        <input type="number" min="10" max="5000" name="radius_meters" x-model="editData.radius_meters" :required="!!editData.radius_enabled" class="w-full rounded border px-3 py-2">
                                    </div>
                                    <div class="md:col-span-3">
                                        <button type="button"
                                            @click="navigator.geolocation.getCurrentPosition(p => { editData.latitude=p.coords.latitude.toFixed(7); editData.longitude=p.coords.longitude.toFixed(7) }, () => alert('Lokasi tidak dapat dibaca. Pastikan izin lokasi aktif dan situs memakai HTTPS.'), {enableHighAccuracy:true, timeout:10000})"
                                            class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">
                                            <i class="bi bi-crosshair mr-1"></i>Perbarui dari lokasi perangkat
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                                <label class="flex items-center gap-3 font-bold text-indigo-900">
                                    <input type="checkbox" name="gelombang_enabled" value="1" x-model="editData.gelombang_enabled" class="h-4 w-4 rounded">
                                    Kegiatan dibagi menjadi beberapa gelombang
                                </label>
                                <div x-show="editData.gelombang_enabled" class="mt-4">
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Daftar Gelombang *</label>
                                    <textarea name="gelombang_names" rows="4" x-model="editData.gelombang_names" :required="!!editData.gelombang_enabled"
                                        class="w-full rounded border px-3 py-2"></textarea>
                                    <p class="mt-1 text-xs text-indigo-800">Satu nama per baris. Gelombang yang sudah dipakai peserta akan disimpan sebagai riwayat bila dihapus dari daftar aktif.</p>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Catatan Internal</label>
                                <textarea name="catatan" rows="3" x-model="editData.catatan"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500"></textarea>
                                <p class="mt-1 text-xs text-gray-500">Hanya tersimpan di sistem, tidak ikut dicetak.</p>
                            </div>
                            <div class="mb-4 border-t pt-4">
                                <h4 class="font-medium text-gray-800 mb-2">Info Penanggung Jawab</h4>
                                <div class="mb-3">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Nama Pejabat</label>
                                    <input type="text" name="pejabat_penanggung_jawab" x-model="editData.pejabat_penanggung_jawab" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                </div>
                                <div class="mb-3">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Jabatan</label>
                                    <input type="text" name="jabatan_penanggung_jawab" x-model="editData.jabatan_penanggung_jawab" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                </div>
                                <div class="mb-3">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">NIP (Opsional)</label>
                                    <input type="text" name="nip_penanggung_jawab" x-model="editData.nip_penanggung_jawab" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                                Simpan Perubahan
                            </button>
                            <button type="button" @click="openEdit = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


    </div>

</body>

</html>
