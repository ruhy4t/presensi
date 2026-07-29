<?php
function formatTanggalIndoBiodata($tgl, $withDay = false) {
    if (!$tgl || $tgl === '-') return '-';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
        $bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
        $parts = explode('-', $tgl);
        $tanggal = (int)$parts[2] . ' ' . $bulan[$parts[1]] . ' ' . $parts[0];

        if ($withDay) {
            $hariInggris = date('l', strtotime($tgl));
            $hariIndo = ['Monday'=>'Senin', 'Tuesday'=>'Selasa', 'Wednesday'=>'Rabu', 'Thursday'=>'Kamis', 'Friday'=>'Jumat', 'Saturday'=>'Sabtu', 'Sunday'=>'Minggu'];
            return ($hariIndo[$hariInggris] ?? '') . ', ' . $tanggal;
        }

        return $tanggal;
    }
    return htmlspecialchars($tgl);
}

function biodataValue($value) {
    return htmlspecialchars($value !== null && $value !== '' ? $value : '-');
}

$tanggalCetak = formatTanggalIndoBiodata(date('Y-m-d'));
$ttl = biodataValue($biodata['tempat_lahir']) . ' / ' . formatTanggalIndoBiodata($biodata['tanggal_lahir']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Biodata Peserta - <?= htmlspecialchars($biodata['nama_lengkap']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 1.6cm 1.7cm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #000;
            margin: 0;
            font-size: 11pt;
        }

        .no-print {
            background: #333;
            color: #fff;
            padding: 10px;
            text-align: center;
            margin-bottom: 15px;
        }

        .no-print a {
            color: #8cc8ff;
            margin-left: 18px;
        }

        .kop {
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 4px double #000;
            padding-bottom: 9px;
            position: relative;
        }

        .kop img {
            position: absolute;
            left: 8px;
            width: 70px;
        }

        .kop-text {
            text-align: center;
            line-height: 1.25;
        }

        .kop-title {
            font-size: 13pt;
            margin: 0;
        }

        .kop-agency {
            font-size: 15pt;
            font-weight: bold;
            margin: 0;
        }

        .kop-small {
            font-size: 9pt;
            margin: 1px 0;
        }

        .title {
            text-align: center;
            margin: 22px 0 30px;
            font-weight: bold;
            line-height: 1.25;
        }

        .title h1 {
            font-size: 12pt;
            margin: 0;
        }

        .title p {
            margin: 2px 0;
            text-transform: uppercase;
        }

        table.biodata {
            width: 100%;
            border-collapse: collapse;
        }

        table.biodata td {
            vertical-align: top;
            padding: 6px 0;
        }

        .no {
            width: 32px;
            text-align: right;
            padding-right: 12px !important;
        }

        .label {
            width: 185px;
        }

        .colon {
            width: 18px;
            text-align: center;
        }

        .value {
            border-bottom: 1px dotted #000;
            min-height: 18px;
            line-height: 1.45;
        }

        .value.multiline {
            min-height: 42px;
            white-space: pre-wrap;
        }

        .signature-wrap {
            margin-top: 36px;
            margin-left: auto;
            width: 240px;
            text-align: left;
        }

        .signature-img {
            margin-top: 28px;
            height: 60px;
            max-width: 220px;
            object-fit: contain;
        }

        .signature-name {
            border-bottom: 1px dotted #000;
            margin-top: 8px;
            min-height: 18px;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body onload="window.print()">
    <div class="no-print">
        Klik CTRL + P untuk menyimpan sebagai PDF.
        <a href="/registrations?id=<?= (int)$biodata['kegiatan_id'] ?>">Kembali ke Daftar Peserta</a>
    </div>

    <div class="kop">
        <img src="/logo-bogor.png" alt="Logo Kota Bogor">
        <div class="kop-text">
            <p class="kop-title">PEMERINTAH KOTA BOGOR</p>
            <p class="kop-agency">DINAS PENDIDIKAN</p>
            <p class="kop-small">Jl. Raya Pajajaran No. 125 Telp/Fax. (0251) 8341101 Bogor 16153</p>
            <p class="kop-small">Web : Disdik.Kotabogor.go.id &nbsp;&nbsp; Email : Disdik@Kotabogor.go.id</p>
        </div>
    </div>

    <div class="title">
        <h1>BIODATA PESERTA</h1>
        <p><?= htmlspecialchars($biodata['nama_kegiatan']) ?></p>
    </div>

    <table class="biodata">
        <tr>
            <td class="no">1.</td>
            <td class="label">Nama (lengkap dengan gelar)</td>
            <td class="colon">:</td>
            <td class="value"><?= biodataValue($biodata['nama_lengkap']) ?></td>
        </tr>
        <tr>
            <td class="no">2.</td>
            <td class="label">Tempat / Tanggal lahir</td>
            <td class="colon">:</td>
            <td class="value"><?= $ttl ?></td>
        </tr>
        <tr>
            <td class="no">3.</td>
            <td class="label">Pangkat / Gol. Ruang</td>
            <td class="colon">:</td>
            <td class="value"><?= biodataValue($biodata['pangkat_gol']) ?></td>
        </tr>
        <tr>
            <td class="no">4.</td>
            <td class="label">NIP</td>
            <td class="colon">:</td>
            <td class="value"><?= biodataValue($biodata['nip']) ?></td>
        </tr>
        <tr>
            <td class="no">5.</td>
            <td class="label">NIK</td>
            <td class="colon">:</td>
            <td class="value"><?= biodataValue($biodata['nik']) ?></td>
        </tr>
        <tr>
            <td class="no">6.</td>
            <td class="label">Jabatan</td>
            <td class="colon">:</td>
            <td class="value"><?= biodataValue($biodata['jabatan']) ?></td>
        </tr>
        <tr>
            <td class="no">7.</td>
            <td class="label">Unit Kerja</td>
            <td class="colon">:</td>
            <td class="value"><?= biodataValue($biodata['unit_kerja']) ?></td>
        </tr>
        <tr>
            <td class="no">8.</td>
            <td class="label">Alamat Unit Kerja</td>
            <td class="colon">:</td>
            <td class="value multiline"><?= biodataValue($biodata['alamat_unit_kerja']) ?></td>
        </tr>
        <tr>
            <td class="no">9.</td>
            <td class="label">No. Telepon Unit Kerja</td>
            <td class="colon">:</td>
            <td class="value"><?= biodataValue($biodata['telepon_unit_kerja']) ?></td>
        </tr>
        <tr>
            <td class="no">10.</td>
            <td class="label">Alamat Rumah</td>
            <td class="colon">:</td>
            <td class="value multiline"><?= biodataValue($biodata['alamat_rumah']) ?></td>
        </tr>
        <tr>
            <td class="no">11.</td>
            <td class="label">No. HP</td>
            <td class="colon">:</td>
            <td class="value"><?= biodataValue($biodata['hp']) ?></td>
        </tr>
        <tr>
            <td class="no">12.</td>
            <td class="label">Alamat Email</td>
            <td class="colon">:</td>
            <td class="value"><?= biodataValue($biodata['email']) ?></td>
        </tr>
        <?php if (!empty($biodata['gelombang_nama'])): ?>
        <tr>
            <td class="no">13.</td>
            <td class="label">Gelombang Kehadiran</td>
            <td class="colon">:</td>
            <td class="value"><?= biodataValue($biodata['gelombang_nama']) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="signature-wrap">
        <p>Bogor, <?= $tanggalCetak ?></p>
        <img src="/uploads/<?= htmlspecialchars($biodata['signature_file']) ?>" class="signature-img" alt="Tanda Tangan">
        <div class="signature-name"><?= biodataValue($biodata['nama_lengkap']) ?></div>
        <div>NIP. <?= biodataValue($biodata['nip']) ?></div>
    </div>

</body>

</html>
