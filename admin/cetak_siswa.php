<?php
/**
 * Lembar CETAK Data Siswa (A4 landscape).
 * Mengikuti saringan yang sedang aktif pada halaman Data Siswa.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

const SISWA_STATUS_CETAK = ['aktif' => 'Aktif', 'lulus' => 'Lulus', 'keluar' => 'Keluar', 'nonaktif' => 'Nonaktif'];

$cari = trim((string) ($_GET['cari'] ?? ''));
$kelasFilter = (int) ($_GET['kelas'] ?? 0);
$status = (string) ($_GET['status'] ?? '');
$w = ['1 = 1'];
$p = [];
if ($cari !== '') {
    $w[] = '(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)';
    array_push($p, '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%');
}
if ($kelasFilter > 0) {
    $w[] = 's.kelas_id = ?';
    $p[] = $kelasFilter;
}
if ($status !== '' && array_key_exists($status, SISWA_STATUS_CETAK)) {
    $w[] = 's.status = ?';
    $p[] = $status;
}
$daftar = all("SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
               WHERE " . implode(' AND ', $w) . " ORDER BY (s.status <> 'aktif'), k.urutan, s.nama", $p);

$saringTeks = [];
if ($cari !== '') {
    $saringTeks[] = 'kata kunci “' . $cari . '”';
}
if ($kelasFilter > 0) {
    $namaKelas = (string) (one('SELECT nama FROM kelas WHERE id = ?', [$kelasFilter])['nama'] ?? '');
    $saringTeks[] = 'kelas ' . $namaKelas;
}
if ($status !== '') {
    $saringTeks[] = 'status ' . (SISWA_STATUS_CETAK[$status] ?? $status);
}
$laki = 0;
$perempuan = 0;
foreach ($daftar as $r) {
    if ($r['jenis_kelamin'] === 'P') {
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
<title>Data Siswa — <?= e($s['nama']) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>">
<style>
  @page{size:A4 landscape;margin:10mm}
  body{background:#eef2f0;margin:0;font-size:12px}
  .lembar{background:#fff;max-width:1123px;margin:16px auto;padding:11mm 9mm;box-shadow:0 6px 24px rgba(0,0,0,.12)}
  .kop{display:flex;align-items:center;gap:14px;border-bottom:3px double var(--hijau-tua);padding-bottom:8px}
  .kop img{width:54px;height:54px;object-fit:contain}
  .kop-teks{flex:1;text-align:center}
  .kop-teks .nama{font-family:var(--serif);font-weight:800;font-size:16px;color:var(--hijau-tua);text-transform:uppercase}
  .kop-teks .baris{font-size:11px;color:#33463d}
  .judul{text-align:center;margin:10px 0 3px;font-family:var(--serif);font-size:14px;font-weight:800;letter-spacing:.02em}
  .meta{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:10.5px;color:#4c5a53;margin-bottom:8px}
  table.data{width:100%;border-collapse:collapse;table-layout:fixed}
  table.data th,table.data td{border:1px solid #9fb3aa;padding:3px 5px;vertical-align:top;word-wrap:break-word}
  table.data th{background:#e8f2ec;font-size:9.5px;text-transform:uppercase;letter-spacing:.02em;color:#0b4a33}
  table.data td{font-size:10.5px;line-height:1.35}
  table.data tfoot td{background:#fdf6e3;font-weight:700}
  .tengah{text-align:center}
  .kaki{margin-top:14px;display:flex;justify-content:flex-end}
  .ttd{text-align:center;min-width:260px;font-size:11px}
  .ttd .nama{font-weight:700;text-decoration:underline;margin-top:44px}
  .alat{max-width:1123px;margin:14px auto 0;display:flex;gap:10px;justify-content:flex-end}
  /* Lembar selebar A4 tidak mungkin muat di layar HP — dibungkus wadah bergulir agar
     halaman TIDAK meluber mendatar; saat dicetak wadahnya dilepas kembali. */
  .lembar-gulir{overflow-x:auto;padding-bottom:6px}
  @media screen{ table.data{min-width:1000px} }
  @media print{
    body{background:#fff}
    .lembar{margin:0;box-shadow:none;max-width:none;padding:0}
    .alat{display:none}
    .lembar-gulir{overflow:visible}
    table.data tr{page-break-inside:avoid}
    thead{display:table-header-group}
  }
</style>
</head>
<body>
<div class="alat">
    <a class="tombol tombol-garis tombol-kecil" href="siswa.php?<?= e(http_build_query(['cari' => $cari, 'kelas' => $kelasFilter, 'status' => $status])) ?>">Kembali ke Data Siswa</a>
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
                <?php if (sek('npsn') !== '') $k[] = 'NPSN: ' . sek('npsn'); ?>
                <?= e(implode(' | ', $k)) ?>
            </div>
        </div>
    </div>

    <div class="judul">DATA SISWA<?= $s['jenis'] === 'madrasah' ? ' MADRASAH' : '' ?></div>
    <div class="meta">
        <span>Jumlah data: <strong><?= count($daftar) ?></strong> (Laki-laki <?= $laki ?>, Perempuan <?= $perempuan ?>)</span>
        <span><?= $saringTeks ? 'Saringan: ' . e(implode(' · ', $saringTeks)) : 'Seluruh data siswa' ?></span>
        <span>Dicetak: <?= e(tanggal_id(date('Y-m-d'), true)) ?></span>
    </div>

    <table class="data">
        <colgroup>
            <col style="width:30px"><col style="width:70px"><col style="width:80px"><col>
            <col style="width:30px"><col style="width:120px"><col style="width:55px"><col style="width:120px">
            <col style="width:90px"><col>
        </colgroup>
        <thead>
            <tr>
                <th>No</th><th>NIS</th><th>NISN</th><th>Nama Siswa</th><th>JK</th>
                <th>Tempat &amp; Tanggal Lahir</th><th>Kelas</th><th>Nama Ayah / Ibu</th>
                <th>Telepon</th><th>Alamat</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$daftar): ?>
                <tr><td colspan="10" class="tengah">Tidak ada data untuk ditampilkan.</td></tr>
            <?php endif; ?>
            <?php foreach ($daftar as $i => $r): ?>
                <tr>
                    <td class="tengah"><?= $i + 1 ?></td>
                    <td><?= e($r['nis'] ?: '-') ?></td>
                    <td><?= e($r['nisn'] ?: '-') ?></td>
                    <td><?= e($r['nama']) ?></td>
                    <td class="tengah"><?= $r['jenis_kelamin'] === 'P' ? 'P' : 'L' ?></td>
                    <td><?= e(trim(($r['tempat_lahir'] ?: '-') . ($r['tanggal_lahir'] !== '' ? ', ' . tanggal_id($r['tanggal_lahir']) : ''))) ?></td>
                    <td class="tengah"><?= e($r['kelas'] ?: '-') ?></td>
                    <td><?= e(trim(($r['nama_ayah'] ?: '-') . ' / ' . ($r['nama_ibu'] ?: '-'))) ?></td>
                    <td><?= e($r['telepon'] ?: '-') ?></td>
                    <td><?= e($r['alamat'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="tengah">JUMLAH</td>
                <td class="tengah"><?= $laki ?> L / <?= $perempuan ?> P</td>
                <td colspan="5"><?= count($daftar) ?> siswa<?= $saringTeks ? ' (sesuai saringan)' : '' ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="kaki">
        <div class="ttd">
            <div><?= e(sek('kecamatan') !== '' ? sek('kecamatan') . ', ' . tanggal_id(date('Y-m-d')) : tanggal_id(date('Y-m-d'))) ?></div>
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
