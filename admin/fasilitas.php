<?php
/**
 * Fasilitas (sarana & prasarana).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan('fasilitas', (int) ($_GET['id'] ?? 0), $aksi);
    redirect('fasilitas.php');
}
if ($aksi === 'toggle') {
    q('UPDATE fasilitas SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('fasilitas.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('fasilitas', (int) ($_POST['id'] ?? 0));
    flash('Fasilitas dihapus.', 'ok');
    redirect('fasilitas.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nama === '') {
        flash('Nama fasilitas wajib diisi.', 'err');
        redirect('fasilitas.php');
    }
    $lama = $id > 0 ? one('SELECT * FROM fasilitas WHERE id = ?', [$id]) : null;
    $g = proses_gambar('gambar', (string) ($lama['gambar'] ?? ''), !empty($_POST['gambar_hapus']), 'Gambar fasilitas');
    $data = [
        'nama'       => potong($nama, JUDUL_MAKS, ''),
        'keterangan' => potong((string) ($_POST['keterangan'] ?? ''), RINGKASAN_MAKS, ''),
        'gambar'     => $g['url'],
        'aktif'      => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        q('UPDATE fasilitas SET nama = ?, keterangan = ?, gambar = ?, aktif = ? WHERE id = ?',
            [$data['nama'], $data['keterangan'], $data['gambar'], $data['aktif'], $id]);
        flash('Fasilitas disimpan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    } else {
        q('INSERT INTO fasilitas (nama, keterangan, gambar, aktif, urutan) VALUES (?, ?, ?, ?, ?)',
            [$data['nama'], $data['keterangan'], $data['gambar'], $data['aktif'], urutan_baru('fasilitas')]);
        flash('Fasilitas ditambahkan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    }
    redirect('fasilitas.php');
}
if ($aksi === 'isi_simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $menu = menu_by_slug('fasilitas');
    if ($menu) {
        q('UPDATE menu SET isi = ?, subjudul = ? WHERE id = ?', [
            potong_html(bersihkan_html((string) ($_POST['isi'] ?? '')), TEKS_PANJANG_MAKS),
            potong((string) ($_POST['subjudul'] ?? ''), 240, ''),
            (int) $menu['id'],
        ]);
        flash('Pengantar halaman fasilitas disimpan.', 'ok');
    }
    redirect('fasilitas.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM fasilitas WHERE id = ?', [(int) $_GET['edit']]) : null;
$daftar = all('SELECT * FROM fasilitas ORDER BY urutan, id');
$menu = menu_by_slug('fasilitas');

admin_atas('Fasilitas');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Daftar Fasilitas</h2>
            <p class="mut kecil mb0">Kartu fasilitas yang tampil pada halaman Fasilitas.</p>
        </div>
        <a class="tombol tombol-kecil" href="fasilitas.php?edit=0#formFasilitas"><?= ikon('plus', 15) ?> Tambah Fasilitas</a>
    </div>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th style="width:48px">Urut</th><th style="width:100px">Gambar</th><th>Nama</th><th>Keterangan</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$daftar): ?><tr><td colspan="6" class="mut">Belum ada data fasilitas.</td></tr><?php endif; ?>
                <?php foreach ($daftar as $f): ?>
                    <tr>
                        <td class="aksi-sel"><?= tombol_urut('fasilitas.php', (int) $f['id']) ?></td>
                        <td><?= kotak_gambar($f['gambar'], 'Fasilitas', 'thumb-admin') ?></td>
                        <td><strong><?= e($f['nama']) ?></strong></td>
                        <td class="kecil mut"><?= e(potong($f['keterangan'], 90)) ?></td>
                        <td><?= ((int) $f['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('fasilitas.php?edit=' . (int) $f['id'] . '#formFasilitas', 'edit', 'Ubah') ?>
                            <?= tombol_mini('fasilitas.php?aksi=toggle&id=' . (int) $f['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                            <?= form_hapus('hapus', (int) $f['id'], 'Hapus fasilitas “' . $f['nama'] . '”?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid g21">
    <div class="kartu" id="formFasilitas">
        <h2><?= $edit ? 'Ubah Fasilitas' : 'Tambah Fasilitas' ?></h2>
        <form method="post" action="fasilitas.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-baris">
                <label for="nama">Nama Fasilitas <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="160" value="<?= e($edit['nama'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="keterangan">Keterangan</label>
                <textarea id="keterangan" name="keterangan" maxlength="400" style="min-height:90px"><?= e($edit['keterangan'] ?? '') ?></textarea>
            </div>
            <?php blok_gambar('gambar', (string) ($edit['gambar'] ?? ''), 'Gambar Fasilitas'); ?>
            <div class="form-baris">
                <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan</label>
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan</button>
                <?php if ($edit): ?><a class="tombol tombol-garis" href="fasilitas.php">Batal</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="kartu">
        <h2>Pengantar Halaman Fasilitas</h2>
        <p class="mut kecil">Teks pembuka yang tampil di atas daftar fasilitas pada halaman publik.</p>
        <form method="post" action="fasilitas.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="isi_simpan">
            <div class="form-baris">
                <label for="subjudul">Anak Judul Halaman</label>
                <input type="text" id="subjudul" name="subjudul" maxlength="240" value="<?= e($menu['subjudul'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="isi">Teks Pengantar</label>
                <?php editor_teks('isi', (string) ($menu['isi'] ?? ''), 'isi', 220, 'Boleh memakai format tebal, perataan, dan gambar.'); ?>
            </div>
            <div class="aksi-form"><button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Pengantar</button></div>
        </form>
    </div>
</div>
<?php admin_bawah(); ?>
