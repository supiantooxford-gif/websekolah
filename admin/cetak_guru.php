<?php
/**
 * Lembar CETAK Data Guru & Tenaga Kependidikan (A4 landscape).
 * Mengikuti saringan yang sedang aktif pada halaman Data Guru.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$cari = trim((string) ($_GET['cari'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$jabatan = trim((string) ($_GET['jabatan'] ?? ''));
$w = ['1 = 1'];
$p = [];
if ($cari !== '') {
    $w[] = '(nama LIKE ? OR nip LIKE ? OR mapel LIKE ?)';
    array_push($p, '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%');
}
if ($status !== '') {
    $w[] = 'status = ?';
    $p[] = $status;
}
if ($jabatan !== '') {
    $w[] = 'jabatan = ?';
    $p[] = $jabatan;
}
$daftar = all('SELECT * FROM guru WHERE ' . implode(' AND ', $w) . ' ORDER BY (status = \'nonaktif\'), urutan, nama', $p);

$saringTeks = [];
if ($cari !== '') {
    $saringTeks[] = 'kata kunci “' . $cari . '”';
}
if ($status !== '') {
    $saringTeks[] = 'status ' . ($status === 'aktif' ? 'Aktif' : 'Nonaktif');
}
if ($jabatan !== '') {
    $saringTeks[] = 'jabatan ' . $jabatan;
}

$laki = 0;
$perempuan = 0;
foreach ($daftar as $g) {
    if ($g['jenis_kelamin'] === 'P') {
        $perempuan++;
    } else {
        $laki++;
    }
}
$s = sekolah();
$auto = (string) ($_GET['auto'] ?? '') === '1';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Guru &amp; Tenaga Kependidikan — <?= e($s['nama']) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>">
<style>
  /* Ukuran halaman ditulis DI SINI (bukan hanya di tema) supaya PDF-nya benar-benar A4 mendatar. */
  @page{size:A4 landscape;margin:10mm}
  body{background:#eef2f0;margin:0;font-size:12px}
  .lembar{background:#fff;max-width:1123px;margin:16px auto;padding:12mm 11mm;box-shadow:0 6px 24px rgba(0,0,0,.12)}
  .kop{display:flex;align-items:center;gap:12px;border-bottom:3px double var(--hijau-tua);padding-bottom:6px}
  .kop img{width:56px;height:56px;object-fit:contain}
  .kop-teks{flex:1;text-align:center}
  .kop-teks .nama{font-family:var(--serif);font-weight:800;font-size:16px;color:var(--hijau-tua);text-transform:uppercase}
  .kop-teks .baris{font-size:11px;color:#33463d}
  .judul{text-align:center;margin:10px 0 3px;font-family:var(--serif);font-size:14px;font-weight:800;letter-spacing:.02em}
  .meta{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:10.5px;color:#4c5a53;margin-bottom:6px}
  table.data{width:100%;border-collapse:collapse}
  table.data th,table.data td{border:1px solid #9fb3aa;padding:3px 5px;vertical-align:top}
  table.data th{background:#e8f2ec;font-size:10px;text-transform:uppercase;letter-spacing:.02em;color:#0b4a33}
  table.data td{font-size:10.5px;line-height:1.35}
  table.data tfoot td{background:#fdf6e3;font-weight:700}
  .tengah{text-align:center}
  .kaki{margin-top:10px;display:flex;justify-content:flex-end}
  .ttd{text-align:center;min-width:260px;font-size:11px}
  .ttd .tempat{margin-bottom:4px}
  .ttd .nama{font-weight:700;text-decoration:underline;margin-top:36px}
  .alat{max-width:1123px;margin:14px auto 0;display:flex;gap:10px;justify-content:flex-end}
  /* Lembar selebar A4 tidak mungkin muat di layar HP — dibungkus wadah bergulir agar
     halaman TIDAK meluber mendatar; saat dicetak wadahnya dilepas kembali. */
  .lembar-gulir{overflow-x:auto;padding-bottom:6px}
  @media screen{ table.data{min-width:900px} }
  @media print{
    body{background:#fff}
    .lembar{margin:0;box-shadow:none;max-width:none;padding:0}
    .alat{display:none}
    .lembar-gulir{overflow:visible}
    table.data{page-break-inside:auto}
    table.data tr{page-break-inside:avoid}
    thead{display:table-header-group}
  }
</style>
</head>
<body>
<div class="alat">
    <a class="tombol tombol-garis tombol-kecil" href="guru.php?<?= e(http_build_query(['cari' => $cari, 'status' => $status, 'jabatan' => $jabatan])) ?>">Kembali ke Data Guru</a>
    <button type="button" class="tombol tombol-kecil" onclick="window.print()"><?= ikon('print', 15) ?> Cetak / Simpan PDF</button>
</div>

<div class="lembar-gulir">
<div class="lembar">
    <div class="kop">
        <?php if (sek('logo') !== ''): ?><img src="<?= e(sek('logo')) ?>" alt="Logo"><?php endif; ?>
        <div class="kop-teks">
            <div class="nama"><?= e($s['nama']) ?></div>
            <?php if (alamat_lengkap() !== ''): ?><div class="baris"><?= e(alamat_lengkap()) ?></div><?php endif; ?>
            <div class="baris">
                <?php $k = []; ?>
                <?php if (sek('telepon') !== '') $k[] = 'Telepon: ' . sek('telepon'); ?>
                <?php if (sek('email') !== '') $k[] = 'email: ' . sek('email'); ?>
                <?php if (sek('website') !== '') $k[] = 'website: ' . sek('website'); ?>
                <?= e(implode(' | ', $k)) ?>
            </div>
            <div class="baris">
                <?php $k2 = []; ?>
                <?php if (sek('npsn') !== '') $k2[] = 'NPSN: ' . sek('npsn'); ?>
                <?php if (sek('nsm') !== '' && ($s['jenis'] ?? '') === 'madrasah') $k2[] = 'NSM: ' . sek('nsm'); ?>
                <?= e(implode(' | ', $k2)) ?>
            </div>
        </div>
    </div>

    <div class="judul">DATA <?= ($s['jenis'] ?? 'madrasah') === 'madrasah' ? 'GURU &amp; TENAGA KEPENDIDIKAN MADRASAH' : 'GURU &amp; TENAGA KEPENDIDIKAN' ?></div>
    <div class="meta">
        <span>Jumlah data: <strong><?= count($daftar) ?></strong> (Laki-laki <?= $laki ?>, Perempuan <?= $perempuan ?>)</span>
        <span><?= $saringTeks ? 'Saringan: ' . e(implode(' · ', $saringTeks)) : 'Seluruh data guru &amp; tenaga kependidikan' ?></span>
        <span>Dicetak: <?= e(tanggal_id(date('Y-m-d'), true)) ?></span>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:34px">No</th>
                <th>Nama Guru / Tenaga Kependidikan</th>
                <th style="width:110px">NIP / NUPTK</th>
                <th style="width:34px">JK</th>
                <th style="width:150px">Jabatan</th>
                <th style="width:150px">Mata Pelajaran</th>
                <th style="width:110px">Telepon</th>
                <th>Email</th>
                <th style="width:70px">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$daftar): ?>
                <tr><td colspan="9" class="tengah">Tidak ada data untuk ditampilkan.</td></tr>
            <?php endif; ?>
            <?php foreach ($daftar as $i => $g): ?>
                <tr>
                    <td class="tengah"><?= $i + 1 ?></td>
                    <td><?= e($g['nama']) ?></td>
                    <td><?= e($g['nip'] ?: '-') ?></td>
                    <td class="tengah"><?= $g['jenis_kelamin'] === 'P' ? 'P' : 'L' ?></td>
                    <td><?= e($g['jabatan'] ?: '-') ?></td>
                    <td><?= e($g['mapel'] ?: '-') ?></td>
                    <td><?= e($g['telepon'] ?: '-') ?></td>
                    <td><?= e($g['email'] ?: '-') ?></td>
                    <td class="tengah"><?= $g['status'] === 'aktif' ? 'Aktif' : 'Nonaktif' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="tengah">JUMLAH</td>
                <td class="tengah"><?= $laki ?> L / <?= $perempuan ?> P</td>
                <td colspan="5"><?= count($daftar) ?> orang<?= $saringTeks ? ' (sesuai saringan)' : '' ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="kaki">
        <div class="ttd">
            <div class="tempat"><?= e(sek('kecamatan') !== '' ? sek('kecamatan') . ', ' . tanggal_id(date('Y-m-d')) : tanggal_id(date('Y-m-d'))) ?></div>
            <div><?= e(kepala_label()) ?></div>
            <div class="nama"><?= e(sek('kepala', '-')) ?></div>
            <?php if (sek('nip_kepala') !== '' && sek('nip_kepala') !== '-'): ?>
                <div>NIP. <?= e(sek('nip_kepala')) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<?php if ($auto): ?><script>window.addEventListener('load', function () { window.print(); });</script><?php endif; ?>
</body>
</html>
