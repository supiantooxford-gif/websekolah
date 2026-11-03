<?php
/**
 * Ekspor data siswa — Excel (.xlsx, dibuat di peramban) atau CSV (dari server).
 * Susunan kolom mengikuti Ekspor Data Siswa e-GURU (siap diunggah ke e-GURU/e-PERPUS).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$cari = trim((string) ($_GET['cari'] ?? ''));
$kelasFilter = (int) ($_GET['kelas'] ?? 0);
$statusFilter = (string) ($_GET['status'] ?? '');
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
if ($statusFilter !== '') {
    $w[] = 's.status = ?';
    $p[] = $statusFilter;
}
$daftar = all("SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
               WHERE " . implode(' AND ', $w) . " ORDER BY k.urutan, s.nama", $p);

$kolom = ['Nama', 'Jenis Anggota', 'NIS', 'NISN', 'Jenis Kelamin', 'Kelas', 'Tanggal Daftar',
    'Tanggal Lahir', 'Nama Wali', 'Telepon', 'Alamat', 'Status Siswa'];

$labelStatus = ['aktif' => 'Aktif', 'lulus' => 'Lulus', 'keluar' => 'Keluar', 'nonaktif' => 'Nonaktif'];
$baris = [];
foreach ($daftar as $s) {
    $wali = (string) $s['wali'] !== '' ? (string) $s['wali'] : (string) $s['nama_ayah'];
    $baris[] = [
        $s['nama'], 'Siswa', (string) $s['nis'], (string) $s['nisn'],
        $s['jenis_kelamin'] === 'P' ? 'P' : 'L',
        (string) $s['kelas'],
        (string) substr((string) $s['tanggal_masuk'], 0, 10),
        (string) $s['tanggal_lahir'],
        $wali,
        (string) $s['telepon'],
        (string) $s['alamat'],
        $labelStatus[$s['status']] ?? 'Aktif',
    ];
}

if (($_GET['unduh'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="data-siswa-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $keluar = fopen('php://output', 'w');
    fputcsv($keluar, $kolom, ';');
    foreach ($baris as $b) {
        fputcsv($keluar, $b, ';');
    }
    fclose($keluar);
    exit;
}

admin_atas('Ekspor Data Siswa');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Ekspor Data Siswa</h2>
            <p class="mut kecil mb0"><?= angka(count($baris)) ?> data akan diekspor dengan susunan kolom yang sama seperti Ekspor Data Siswa e-GURU.</p>
        </div>
        <a class="tombol tombol-garis tombol-kecil" href="siswa.php"><?= ikon('panah-kiri', 15) ?> Daftar Siswa</a>
    </div>
    <div class="aksi-form">
        <button type="button" class="tombol" id="unduhXlsx"><?= ikon('unduh', 17) ?> Unduh Excel (.xlsx)</button>
        <a class="tombol tombol-garis" href="siswa_export.php?unduh=csv&amp;<?= e(http_build_query(['cari' => $cari, 'kelas' => $kelasFilter, 'status' => $statusFilter])) ?>"><?= ikon('unduh', 17) ?> Unduh CSV</a>
        <a class="tombol tombol-garis" href="siswa_import.php"><?= ikon('unggah', 16) ?> Impor Kembali</a>
    </div>
</div>

<div class="kartu">
    <h2>Pratinjau (25 baris pertama)</h2>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><?php foreach ($kolom as $k): ?><th><?= e($k) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
                <?php foreach (array_slice($baris, 0, 25) as $b): ?>
                    <tr><?php foreach ($b as $sel): ?><td class="kecil"><?= e((string) $sel) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script id="dataEkspor" type="application/json"><?= json_encode(['kolom' => $kolom, 'baris' => $baris, 'nama' => 'data-siswa-' . date('Y-m-d') . '.xlsx'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="../assets/vendor/xlsx.full.min.js?v=<?= APP_VERSION ?>"></script>
<script>
(function () {
  var tombol = document.getElementById('unduhXlsx');
  var wadah = document.getElementById('dataEkspor');
  if (!tombol || !wadah) { return; }
  tombol.addEventListener('click', function () {
    if (typeof XLSX === 'undefined') {
      window.alert('Pustaka Excel belum termuat. Gunakan tombol “Unduh CSV”.');
      return;
    }
    var d = JSON.parse(wadah.textContent);
    var lembar = XLSX.utils.aoa_to_sheet([d.kolom].concat(d.baris));
    var buku = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(buku, lembar, 'Data Siswa');
    XLSX.writeFile(buku, d.nama);
  });
})();
</script>
<?php admin_bawah(); ?>
