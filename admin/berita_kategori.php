<?php
/**
 * Kategori berita.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan('berita_kategori', (int) ($_GET['id'] ?? 0), $aksi);
    redirect('berita_kategori.php');
}
if ($aksi === 'toggle') {
    q('UPDATE berita_kategori SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('berita_kategori.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $n = jumlah('SELECT COUNT(*) FROM berita WHERE kategori_id = ?', [$id]);
    if ($n > 0) {
        q('UPDATE berita SET kategori_id = 0 WHERE kategori_id = ?', [$id]);
        flash('Kategori dihapus; ' . $n . ' berita dipindahkan ke “Tanpa kategori”.', 'ok');
    } else {
        flash('Kategori dihapus.', 'ok');
    }
    hapus_baris('berita_kategori', $id);
    redirect('berita_kategori.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nama === '') {
        flash('Nama kategori wajib diisi.', 'err');
        redirect('berita_kategori.php');
    }
    $s = slug($nama, 'kategori');
    $n = 2;
    while (one('SELECT id FROM berita_kategori WHERE slug = ? AND id <> ?', [$s, $id])) {
        $s = slug($nama, 'kategori') . '-' . $n;
        $n++;
    }
    if ($id > 0) {
        q('UPDATE berita_kategori SET nama = ?, slug = ?, aktif = ? WHERE id = ?',
            [potong($nama, 60, ''), $s, empty($_POST['aktif']) ? 0 : 1, $id]);
        flash('Kategori disimpan.', 'ok');
    } else {
        q('INSERT INTO berita_kategori (nama, slug, urutan, aktif) VALUES (?, ?, ?, ?)',
            [potong($nama, 60, ''), $s, urutan_baru('berita_kategori'), empty($_POST['aktif']) ? 0 : 1]);
        flash('Kategori ditambahkan.', 'ok');
    }
    redirect('berita_kategori.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM berita_kategori WHERE id = ?', [(int) $_GET['edit']]) : null;
$daftar = all('SELECT k.*, (SELECT COUNT(*) FROM berita b WHERE b.kategori_id = k.id) AS jml FROM berita_kategori k ORDER BY k.urutan, k.id');

admin_atas('Kategori Berita');
?>
<div class="grid g21">
    <div class="kartu">
        <h2>Daftar Kategori</h2>
        <div class="tabel-wrap">
            <table class="tabel">
                <thead><tr><th style="width:48px">Urut</th><th>Nama</th><th>Slug</th><th>Jumlah Berita</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
                <tbody>
                    <?php if (!$daftar): ?><tr><td colspan="6" class="mut">Belum ada kategori.</td></tr><?php endif; ?>
                    <?php foreach ($daftar as $k): ?>
                        <tr>
                            <td class="aksi-sel"><?= tombol_urut('berita_kategori.php', (int) $k['id']) ?></td>
                            <td><strong><?= e($k['nama']) ?></strong></td>
                            <td class="kecil mut"><?= e($k['slug']) ?></td>
                            <td><?= angka($k['jml']) ?></td>
                            <td><?= ((int) $k['aktif'] === 1) ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                            <td class="aksi-sel">
                                <?= tombol_mini('berita_kategori.php?edit=' . (int) $k['id'] . '#formKategori', 'edit', 'Ubah') ?>
                                <?= tombol_mini('berita_kategori.php?aksi=toggle&id=' . (int) $k['id'], 'mata', 'Aktifkan/nonaktifkan') ?>
                                <?= form_hapus('hapus', (int) $k['id'], 'Hapus kategori “' . $k['nama'] . '”? Berita pada kategori ini tidak terhapus.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="kartu" id="formKategori">
        <h2><?= $edit ? 'Ubah Kategori' : 'Tambah Kategori' ?></h2>
        <form method="post" action="berita_kategori.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-baris">
                <label for="nama">Nama Kategori</label>
                <input type="text" id="nama" name="nama" required maxlength="60" value="<?= e($edit['nama'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Aktif</label>
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan</button>
                <?php if ($edit): ?><a class="tombol tombol-garis" href="berita_kategori.php">Batal</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php admin_bawah(); ?>
