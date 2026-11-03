<?php
/**
 * Ekspor data guru — unduh Excel (.xlsx, dibuat di peramban) atau CSV (dari server).
 * Susunan kolom mengikuti Ekspor Data Guru e-GURU agar berkasnya bisa dipakai ulang.
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

$kolom = ['Nama', 'Jenis Anggota', 'NIP/NUPTK', 'Jenis Kelamin', 'Jabatan', 'Mata Pelajaran',
    'Kelas Diajar', 'Telepon', 'Email', 'Akun (Nama Pengguna)', 'Status', 'Tanggal Daftar'];

$baris = [];
foreach ($daftar as $g) {
    $baris[] = [
        $g['nama'],
        stripos((string) $g['jabatan'], 'kepala') !== false || stripos((string) $g['jabatan'], 'tata usaha') !== false
            || stripos((string) $g['jabatan'], 'tenaga') !== false || stripos((string) $g['jabatan'], 'pustaka') !== false
            || stripos((string) $g['jabatan'], 'laboran') !== false ? 'Tendik' : 'Guru',
        (string) $g['nip'],
        $g['jenis_kelamin'] === 'P' ? 'P' : 'L',
        (string) $g['jabatan'],
        (string) $g['mapel'],
        '',
        (string) $g['telepon'],
        (string) $g['email'],
        '',
        $g['status'] === 'aktif' ? 'Aktif' : 'Nonaktif',
        (string) substr((string) $g['dibuat'], 0, 10),
    ];
}

if (($_GET['unduh'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="data-guru-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $keluar = fopen('php://output', 'w');
    fputcsv($keluar, $kolom, ';');
    foreach ($baris as $b) {
        fputcsv($keluar, $b, ';');
    }
    fclose($keluar);
    exit;
}

admin_atas('Ekspor Data Guru');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Ekspor Data Guru &amp; Tendik</h2>
            <p class="mut kecil mb0"><?= angka(count($baris)) ?> data akan diekspor. Susunan kolomnya sama dengan Ekspor Data Guru e-GURU sehingga berkasnya bisa diunggah kembali ke aplikasi lain (mis. e-GURU, e-PERPUS).</p>
        </div>
        <a class="tombol tombol-garis tombol-kecil" href="guru.php"><?= ikon('panah-kiri', 15) ?> Daftar Guru</a>
    </div>

    <?php if ($cari !== '' || $status !== '' || $jabatan !== ''): ?>
        <div class="alert alert-info">Ekspor mengikuti saringan yang sedang aktif: <?= e(implode(' · ', array_filter(['cari: ' . $cari, 'status: ' . $status, 'jabatan: ' . $jabatan]))) ?></div>
    <?php endif; ?>

    <div class="aksi-form">
        <button type="button" class="tombol" id="unduhXlsx"><?= ikon('unduh', 17) ?> Unduh Excel (.xlsx)</button>
        <a class="tombol tombol-garis" href="guru_export.php?unduh=csv&amp;<?= e(http_build_query(['cari' => $cari, 'status' => $status, 'jabatan' => $jabatan])) ?>"><?= ikon('unduh', 17) ?> Unduh CSV</a>
        <a class="tombol tombol-garis" href="guru_import.php"><?= ikon('unggah', 16) ?> Impor Kembali</a>
    </div>
    <p class="kecil mut mt12" style="margin-top:12px">Berkas Excel dianjurkan karena CSV dapat berubah saat dibaca aplikasi lain.</p>
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

<script id="dataEkspor" type="application/json"><?= json_encode(['kolom' => $kolom, 'baris' => $baris, 'nama' => 'data-guru-' . date('Y-m-d') . '.xlsx'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
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
    XLSX.utils.book_append_sheet(buku, lembar, 'Data Guru');
    XLSX.writeFile(buku, d.nama);
  });
})();
</script>
<?php admin_bawah(); ?>
