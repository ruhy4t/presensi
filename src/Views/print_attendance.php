<?php
function formatTanggalIndoPrint($tgl, $tampil_hari = true) {
    if (!$tgl || $tgl === '-') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
        $bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
        $parts = explode('-', $tgl);
        $tanggal = $parts[2] . ' ' . $bulan[$parts[1]] . ' ' . $parts[0];
        
        if ($tampil_hari) {
            $hari_inggris = date('l', strtotime($tgl));
            $hari_indo = ['Monday'=>'Senin', 'Tuesday'=>'Selasa', 'Wednesday'=>'Rabu', 'Thursday'=>'Kamis', 'Friday'=>'Jumat', 'Saturday'=>'Sabtu', 'Sunday'=>'Minggu'];
            $hari = $hari_indo[$hari_inggris] ?? '';
            return $hari . ', ' . $tanggal;
        }
        
        return $tanggal;
    }
    return htmlspecialchars($tgl);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Daftar Hadir -
        <?= htmlspecialchars($kegiatan['nama_kegiatan']) ?>
    </title>
    <style>
        @page {
            size: A4;
            margin-top: 2cm;
            margin-bottom: 2cm;
            margin-left: 2cm;
            margin-right: 2cm;
        }

        body {
            font-family: 'Times New Roman', serif;
            margin: 0;
            padding: 0;
        }

        .kop-surat {
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
            margin-bottom: 2px;
            position: relative;
        }

        .kop-surat::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: -5px;
            border-bottom: 1px solid #000;
        }

        .kop-logo {
            width: 85px;
            position: absolute;
            left: 0;
        }

        .kop-teks {
            text-align: center;
            line-height: 1.2;
        }

        .kop-teks .kop-title-1 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }

        .kop-teks .kop-title-2 {
            font-size: 18pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }

        .kop-teks p {
            font-size: 11pt;
            margin: 2px 0;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            margin-top: 25px;
        }

        h1 {
            font-size: 16pt;
            margin: 0;
            text-transform: uppercase;
        }

        .info-table {
            width: auto;
            margin-left: 50px;
            margin-top: 0;
            margin-bottom: 20px;
        }

        .info-table td {
            border: none;
            padding: 4px 0;
            font-size: 12pt;
            vertical-align: top;
        }

        .info-table .col-label {
            width: 100px;
        }

        .info-table .col-titikdua {
            width: 20px;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .attendance-table {
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 8px;
            font-size: 12pt;
        }

        th {
            background-color: #f0f0f0;
            text-align: center;
        }

        .attendance-table .col-no {
            width: 4%;
            text-align: center;
        }

        .col-nama {
            width: 28%;
        }

        .col-instansi {
            width: 27%;
        }

        .col-jabatan {
            width: 25%;
        }

        .attendance-table .col-ttd {
            width: 16%;
            text-align: center;
            padding-left: 2px;
            padding-right: 2px;
        }

        .signature-img {
            max-width: 100%;
            max-height: 40px;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                padding: 0;
                margin: 0;
            }

            thead {
                display: table-header-group;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <div class="no-print"
        style="background: #333; color: #fff; padding: 10px; text-align: center; margin-bottom: 20px;">
        Klik <strong>CTRL + P</strong> untuk menyimpan sebagai PDF.
        <a href="/dashboard" style="color: #4da6ff; margin-left: 20px;">Kembali ke Dashboard</a>
    </div>

    <div class="kop-surat">
        <img src="/logo-bogor.png" class="kop-logo" alt="Logo Kota Bogor">
        <div class="kop-teks">
            <div class="kop-title-1">PEMERINTAH KOTA BOGOR</div>
            <div class="kop-title-2">DINAS PENDIDIKAN</div>
            <p>Jalan Pajajaran Nomor 125 Kota Bogor, 16153</p>
            <p>Telp. 0251- 8341101 , Faksimile 0251- 8341101</p>
            <p>Situs web : https://disdik.kotabogor.go.id Email : disdik@kotabogor.go.id</p>
        </div>
    </div>

    <div class="header">
        <h1>DAFTAR HADIR</h1>
    </div>

    <table class="info-table">
        <tr>
            <td class="col-label">Acara</td>
            <td class="col-titikdua">:</td>
            <td><?= htmlspecialchars($kegiatan['nama_kegiatan']) ?></td>
        </tr>
        <tr>
            <td class="col-label">Tempat</td>
            <td class="col-titikdua">:</td>
            <td><?= htmlspecialchars($kegiatan['tempat_pelaksanaan'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="col-label">Hari, Tanggal</td>
            <td class="col-titikdua">:</td>
            <td><?= formatTanggalIndoPrint($kegiatan['tanggal_pelaksanaan'] ?? '') ?><?php if (!empty($kegiatan['tanggal_selesai']) && $kegiatan['tanggal_selesai'] !== $kegiatan['tanggal_pelaksanaan']): ?> s.d. <?= formatTanggalIndoPrint($kegiatan['tanggal_selesai']) ?><?php endif; ?></td>
        </tr>
        <tr>
            <td class="col-label">Waktu</td>
            <td class="col-titikdua">:</td>
            <td><?= htmlspecialchars($kegiatan['waktu_pelaksanaan'] ?? '') ?></td>
        </tr>
        <?php if ((int) ($kegiatan['gelombang_enabled'] ?? 0) === 1): ?>
            <tr>
                <td class="col-label">Gelombang</td>
                <td class="col-titikdua">:</td>
                <td><?= htmlspecialchars($gelombangNames !== [] ? implode(', ', $gelombangNames) : '-') ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <table class="attendance-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-nama">Nama Lengkap</th>
                <th class="col-instansi">Instansi</th>
                <th class="col-jabatan">Jabatan</th>
                <th class="col-ttd">Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1;
            foreach ($attendanceData as $row): ?>
                <tr>
                    <td class="col-no">
                        <?= $no++ ?>
                    </td>
                    <td class="col-nama">
                        <?= htmlspecialchars($row['nama']) ?>
                    </td>
                    <td class="col-instansi">
                        <?= htmlspecialchars($row['instansi']) ?>
                    </td>
                    <td class="col-jabatan">
                        <?= htmlspecialchars($row['jabatan']) ?>
                    </td>
                    <td class="col-ttd">
                        <img src="/uploads/<?= htmlspecialchars($row['signature_file']) ?>" class="signature-img" alt="TTD">
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 50px; float: right; width: 300px; text-align: center;">
        <p style="margin-bottom: 5px;">Bogor,
            <?= !empty($kegiatan['tanggal_pelaksanaan']) ? formatTanggalIndoPrint($kegiatan['tanggal_pelaksanaan'], false) : date('d F Y') ?>
        </p>
        <p style="margin-top: 0;"><?= htmlspecialchars($kegiatan['jabatan_penanggung_jawab'] ?? 'Mengetahui') ?></p>
        <br><br><br>
        <p style="font-weight: bold; text-decoration: underline; margin-bottom: 2px;">
            <?= htmlspecialchars(!empty($kegiatan['pejabat_penanggung_jawab']) ? $kegiatan['pejabat_penanggung_jawab'] : '_________________________') ?>
        </p>
        <?php if (!empty($kegiatan['nip_penanggung_jawab'])): ?>
            <p style="margin-top: 0;">NIP. <?= htmlspecialchars($kegiatan['nip_penanggung_jawab']) ?></p>
        <?php endif; ?>
    </div>

</body>

</html>
