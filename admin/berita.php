<?php
/**
 * Berita — daftar, saring, terbitkan/sembunyikan, hapus.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'toggle') {
    q('UPDATE berita SET terbit = CASE terbit WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    flash('Status terbit berita diubah.', 'ok');
    redirect('berita.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('berita', (int) ($_POST['id'] ?? 0));
    flash('Berita dihapus.', 'ok');
    redirect('berita.php');
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$katFilter = (int) ($_GET['kategori'] ?? 0);
$status = (string) ($_GET['status'] ?? '');
$w = ['1 = 1'];
$p = [];
if ($cari !== '') {
    $w[] = '(judul LIKE ? OR ringkasan LIKE ?)';
    $p[] = '%' . $cari . '%';
    $p[] = '%' . $cari . '%';
}
if ($katFilter > 0) {
    $w[] = 'kategori_id = ?';
    $p[] = $katFilter;
}
if ($status === 'terbit') {
    $w[] = 'terbit = 1';
} elseif ($status === 'draf') {
    $w[] = 'terbit = 0';
}
$where = 'WHERE ' . implode(' AND ', $w);
$perHal = 12;
$hal = halaman_ini();
$total = jumlah("SELECT COUNT(*) FROM berita $where", $p);
$daftar = all("SELECT b.*, k.nama AS kategori FROM berita b LEFT JOIN berita_kategori k ON k.id = b.kategori_id
               $where ORDER BY b.tanggal DESC, b.id DESC LIMIT $perHal OFFSET " . (($hal - 1) * $perHal), $p);
$kategori = all('SELECT * FROM berita_kategori ORDER BY urutan, id');

admin_atas('Berita');
?>
<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2>Daftar Berita</h2>
            <p class="mut kecil mb0"><?= angka($total) ?> berita ditemukan.</p>
        </div>
        <a class="tombol tombol-kecil" href="berita_edit.php"><?= ikon('plus', 15) ?> Tulis Berita</a>
    </div>

    <form class="saringan" method="get" action="berita.php">
        <div class="medan">
            <label for="saringCari">Cari judul/ringkasan</label>
            <input type="search" id="saringCari" name="cari" value="<?= e($cari) ?>">
        </div>
        <div class="medan">
            <label for="saringKategori">Kategori</label>
            <select id="saringKategori" name="kategori" data-auto>
                <option value="0">Semua kategori</option>
                <?php foreach ($kategori as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $katFilter === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="medan">
            <label for="saringStatus">Status</label>
            <select id="saringStatus" name="status" data-auto>
                <option value="">Semua status</option>
                <option value="terbit" <?= $status === 'terbit' ? 'selected' : '' ?>>Sudah terbit</option>
                <option value="draf" <?= $status === 'draf' ? 'selected' : '' ?>>Draf</option>
            </select>
        </div>
        <button type="submit" class="tombol tombol-kecil"><?= ikon('search', 15) ?> Terapkan</button>
        <a class="tombol tombol-garis tombol-kecil" href="berita.php">Reset</a>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th style="width:110px">Gambar</th><th>Judul</th><th>Kategori</th><th>Tanggal</th><th>Dibaca</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$daftar): ?><tr><td colspan="7" class="mut">Belum ada berita.</td></tr><?php endif; ?>
                <?php foreach ($daftar as $b): ?>
                    <tr>
                        <td><?= kotak_gambar($b['gambar'], 'Berita', 'thumb-admin') ?></td>
                        <td>
                            <strong><?= e(potong($b['judul'], 80)) ?></strong>
                            <div class="kecil mut"><?= e(potong($b['ringkasan'], 90)) ?></div>
                        </td>
                        <td class="kecil"><?= e($b['kategori'] ?: '-') ?></td>
                        <td class="kecil"><?= e(tanggal_id($b['tanggal'])) ?><?= $b['penulis'] !== '' ? '<div class="mut">' . e($b['penulis']) . '</div>' : '' ?></td>
                        <td class="kecil"><?= angka($b['dilihat']) ?></td>
                        <td><?= ((int) $b['terbit'] === 1) ? '<span class="pil pil-terima">Terbit</span>' : '<span class="pil pil-baru">Draf</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('berita_edit.php?id=' . (int) $b['id'], 'edit', 'Ubah berita') ?>
                            <?= tombol_mini('berita.php?aksi=toggle&id=' . (int) $b['id'], 'mata', 'Terbitkan/sembunyikan') ?>
                            <?= tombol_mini('../berita/' . rawurlencode((string) $b['slug']), 'mata', 'Lihat di website') ?>
                            <?= form_hapus('hapus', (int) $b['id'], 'Hapus berita “' . $b['judul'] . '”?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi($total, $perHal, $hal) ?>
</div>
<?php admin_bawah(); ?>
