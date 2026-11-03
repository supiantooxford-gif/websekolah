<?php
/**
 * Lembar CETAK Data Alumni (A4 landscape) — panel admin.
 *
 * Permintaan pemilik: *"…tambahkan fitur untuk cetak data alumni pada akun admin"*.
 *
 * Isinya mengikuti **saringan yang sedang aktif** pada halaman Data Alumni (`admin/alumni.php`):
 * kata kunci (`cari`), tahun keluar (`tahun`), dan sumber (`sumber`) — sama seperti tombol
 * "Cetak Data Guru/Siswa", sehingga yang tercetak = yang tampil di layar (TANPA batas halaman).
 *
 * Kolom: No, Nama Alumni, NIS, NISN, Jenis Kelamin, Tahun Masuk, Tahun Keluar, Keterangan, Sumber.
 * Ditutup baris JUMLAH (L/P) dan tanda tangan kepala sekolah/madrasah di sisi KANAN.
 *
 * Penjaga: `require_login()` — sama seperti halaman Data Alumni (Administrator & akun Editor);
 * peran "Editor Berita" ditolak oleh `boleh_buka_berkas()` (403) seperti berkas panel lainnya.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

/** Apakah peran pengguna saat ini boleh membuka halaman alumni (termasuk lembar cetaknya)? */
if (!boleh_buka_berkas('alumni.php')) {
    http_response_code(403);
    admin_atas('Akses ditolak');
    echo '<div class="alert alert-err">Akun ini tidak memiliki akses ke Data Alumni.</div>';
    admin_bawah();
    exit;
}

// ---------------------------------------------------------------- saringan (sama dengan alumni.php)
$cari = trim((string) ($_GET['cari'] ?? ''));
$tahunF = (int) ($_GET['tahun'] ?? 0);
$sumberF = (string) ($_GET['sumber'] ?? '');
$w = ['1 = 1'];
$p = [];
if ($cari !== '') {
    $w[] = '(nama LIKE ? OR nis LIKE ? OR nisn LIKE ?)';
    array_push($p, '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%');
}
if ($tahunF > 0) {
    $w[] = 'CAST(tahun_keluar AS INTEGER) = ?';
    $p[] = $tahunF;
}
if ($sumberF !== '') {
    $w[] = 'sumber = ?';
    $p[] = $sumberF;
}
$daftar = all('SELECT * FROM alumni WHERE ' . implode(' AND ', $w)
    . ' ORDER BY CAST(tahun_keluar AS INTEGER) DESC, nama', $p);

// Saringan yang berlaku — dicetak pada lembar supaya pembaca tahu cakupan datanya.
$saringTeks = [];
if ($cari !== '') {
    $saringTeks[] = 'kata kunci “' . $cari . '”';
}
if ($tahunF > 0) {
    $saringTeks[] = 'tahun keluar ' . $tahunF;
}
if ($sumberF !== '') {
    $saringTeks[] = 'sumber ' . alumni_sumber_label($sumberF);
}

$laki = 0;
$perempuan = 0;
$tahunKeluar = [];
$lulusData = 0;
foreach ($daftar as $a) {
    if (strtoupper((string) $a['jenis_kelamin']) === 'P') {
        $perempuan++;
    } else {
        $laki++;
    }
    $th = trim((string) $a['tahun_keluar']);
    if ($th !== '') {
        $tahunKeluar[$th] = true;
    }
    if ((string) $a['sumber'] !== 'manual') {
        $lulusData++;
    }
}
ksort($tahunKeluar);

$s = sekolah();
$auto = (string) ($_GET['auto'] ?? '') === '1';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Alumni — <?= e($s['nama']) ?></title>
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
  /* Lembar selebar A4 tidak mungkin muat di layar HP — dibungkus wadah bergulir agar halaman
     TIDAK meluber mendatar; saat dicetak wadahnya dilepas kembali. */
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
    <a class="tombol tombol-garis tombol-kecil"
       href="alumni.php?<?= e(http_build_query(['cari' => $cari, 'tahun' => $tahunF > 0 ? $tahunF : '', 'sumber' => $sumberF])) ?>">Kembali ke Data Alumni</a>
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

    <div class="judul">DATA ALUMNI <?= ($s['jenis'] ?? 'madrasah') === 'madrasah' ? 'MADRASAH' : '' ?></div>
    <div class="meta">
        <span>Jumlah data: <strong><?= count($daftar) ?></strong>
            (Laki-laki <?= $laki ?>, Perempuan <?= $perempuan ?>)<?php
            if ($tahunKeluar) {
                echo ' · Tahun keluar: ' . e(implode(', ', array_keys($tahunKeluar)));
            } ?></span>
        <span><?= $saringTeks ? 'Saringan: ' . e(implode(' · ', $saringTeks)) : 'Seluruh data alumni (diisi manual / impor)' ?></span>
        <span>Dicetak: <?= e(tanggal_id(date('Y-m-d'), true)) ?></span>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:32px">No</th>
                <th>Nama Alumni</th>
                <th style="width:90px">NIS</th>
                <th style="width:100px">NISN</th>
                <th style="width:80px">Jenis Kelamin</th>
                <th style="width:70px">Th. Masuk</th>
                <th style="width:70px">Th. Keluar</th>
                <th>Keterangan</th>
                <th style="width:110px">Sumber</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$daftar): ?>
                <tr><td colspan="9" class="tengah">Tidak ada data alumni untuk ditampilkan.</td></tr>
            <?php endif; ?>
            <?php foreach ($daftar as $i => $a): ?>
                <tr>
                    <td class="tengah"><?= $i + 1 ?></td>
                    <td><?= e((string) $a['nama']) ?></td>
                    <td><?= e(trim((string) $a['nis']) !== '' ? (string) $a['nis'] : '-') ?></td>
                    <td><?= e(trim((string) $a['nisn']) !== '' ? (string) $a['nisn'] : '-') ?></td>
                    <td class="tengah"><?= e(alumni_gender_label((string) $a['jenis_kelamin'])) ?></td>
                    <td class="tengah"><?= e(trim((string) $a['tahun_masuk']) !== '' ? (string) $a['tahun_masuk'] : '-') ?></td>
                    <td class="tengah"><?= e(trim((string) $a['tahun_keluar']) !== '' ? (string) $a['tahun_keluar'] : '-') ?></td>
                    <td><?= e(trim((string) $a['keterangan']) !== '' ? (string) $a['keterangan'] : '-') ?></td>
                    <td><?= e(alumni_sumber_label((string) $a['sumber'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="tengah">JUMLAH</td>
                <td class="tengah"><?= $laki ?> L / <?= $perempuan ?> P</td>
                <td colspan="4"><?= count($daftar) ?> alumni<?= $saringTeks ? ' (sesuai saringan)' : '' ?>
                    <?php if ($lulusData > 0): ?>· <?= $lulusData ?> berasal dari data kelulusan<?php endif; ?></td>
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
