<?php
/**
 * Slider besar halaman beranda (judul, gambar, keterangan, tombol).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan('slider', (int) ($_GET['id'] ?? 0), $aksi);
    redirect('slider.php');
}
if ($aksi === 'toggle') {
    q('UPDATE slider SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('slider.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('slider', (int) ($_POST['id'] ?? 0));
    flash('Slide dihapus.', 'ok');
    redirect('slider.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $lama = $id > 0 ? one('SELECT * FROM slider WHERE id = ?', [$id]) : null;
    $judul = trim((string) ($_POST['judul'] ?? ''));
    if ($judul === '') {
        flash('Judul slide wajib diisi.', 'err');
        redirect('slider.php' . ($id > 0 ? '?edit=' . $id : ''));
    }
    $g = proses_gambar('gambar', (string) ($lama['gambar'] ?? ''), !empty($_POST['gambar_hapus']), 'Gambar slide');
    $data = [
        'badge'         => potong((string) ($_POST['badge'] ?? ''), 120, ''),
        'judul'         => potong($judul, JUDUL_MAKS, ''),
        'subjudul'      => potong((string) ($_POST['subjudul'] ?? ''), JUDUL_MAKS, ''),
        'keterangan'    => potong((string) ($_POST['keterangan'] ?? ''), RINGKASAN_MAKS, ''),
        'gambar'        => $g['url'],
        'tombol_teks'   => potong((string) ($_POST['tombol_teks'] ?? ''), 60, ''),
        'tombol_tautan' => potong((string) ($_POST['tombol_tautan'] ?? ''), URL_MAKS, ''),
        'aktif'         => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        $set = [];
        $val = [];
        foreach ($data as $k => $v) {
            $set[] = $k . ' = ?';
            $val[] = $v;
        }
        $val[] = $id;
        q('UPDATE slider SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        flash('Slide disimpan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    } else {
        $data['urutan'] = urutan_baru('slider');
        $kol = array_keys($data);
        q('INSERT INTO slider (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        flash('Slide ditambahkan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    }
    redirect('slider.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM slider WHERE id = ?', [(int) $_GET['edit']]) : null;
$daftar = all('SELECT * FROM slider ORDER BY urutan, id');

admin_atas('Slider Beranda');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Slider Besar Halaman Depan</h2>
            <p class="mut kecil mb0">Gambar besar yang bergantian di bagian atas beranda, lengkap dengan judul dan keterangannya. Bila gambar tidak diunggah, slide tetap tampil dengan latar berwarna.</p>
        </div>
    </div>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th style="width:52px">Urut</th><th style="width:120px">Gambar</th><th>Judul &amp; Keterangan</th><th>Tombol</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$daftar): ?><tr><td colspan="6" class="mut">Belum ada slide. Tambahkan pada formulir di bawah.</td></tr><?php endif; ?>
                <?php foreach ($daftar as $s): ?>
                    <tr>
                        <td class="aksi-sel"><?= tombol_urut('slider.php', (int) $s['id']) ?></td>
                        <td><?= kotak_gambar($s['gambar'], 'Slide', 'thumb-admin') ?></td>
                        <td>
                            <strong><?= e($s['judul']) ?><?= $s['subjudul'] !== '' ? ' — ' . e($s['subjudul']) : '' ?></strong>
                            <div class="kecil mut"><?= e($s['badge']) ?></div>
                            <div class="kecil"><?= e(potong($s['keterangan'], 110)) ?></div>
                        </td>
                        <td class="kecil"><?= e($s['tombol_teks']) ?><?= $s['tombol_tautan'] !== '' ? '<div class="mut">' . e($s['tombol_tautan']) . '</div>' : '' ?></td>
                        <td><?= ((int) $s['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Disembunyikan</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('slider.php?edit=' . (int) $s['id'] . '#formSlider', 'edit', 'Ubah slide') ?>
                            <?= tombol_mini('slider.php?aksi=toggle&id=' . (int) $s['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                            <?= form_hapus('hapus', (int) $s['id'], 'Hapus slide ini?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="kartu" id="formSlider">
    <h2><?= $edit ? 'Ubah Slide' : 'Tambah Slide Baru' ?></h2>
    <form method="post" action="slider.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-2">
            <div class="form-baris">
                <label for="badge">Label Kecil (badge)</label>
                <input type="text" id="badge" name="badge" maxlength="120" placeholder="Selamat Datang di Website Resmi" value="<?= e($edit['badge'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label>Status</label>
                <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan slide ini</label>
            </div>
        </div>
        <div class="form-2">
            <div class="form-baris">
                <label for="judul">Judul Slide <span class="wajib">*</span></label>
                <input type="text" id="judul" name="judul" required maxlength="160" value="<?= e($edit['judul'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="subjudul">Baris Kedua Judul (berwarna emas)</label>
                <input type="text" id="subjudul" name="subjudul" maxlength="160" value="<?= e($edit['subjudul'] ?? '') ?>">
            </div>
        </div>
        <div class="form-baris">
            <label for="keterangan">Keterangan Slide</label>
            <textarea id="keterangan" name="keterangan" maxlength="400" style="min-height:80px"><?= e($edit['keterangan'] ?? '') ?></textarea>
        </div>
        <?php blok_gambar('gambar', (string) ($edit['gambar'] ?? ''), 'Gambar Slide', true, 'Disarankan 1600 x 900 px (mendatar), maksimal 15 MB.'); ?>
        <div class="form-2">
            <div class="form-baris">
                <label for="tombol_teks">Tulisan Tombol</label>
                <input type="text" id="tombol_teks" name="tombol_teks" maxlength="60" placeholder="Kenali Lebih Dekat" value="<?= e($edit['tombol_teks'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="tombol_tautan">Tautan Tombol</label>
                <input type="text" id="tombol_tautan" name="tombol_tautan" maxlength="300" placeholder="halaman/profil" value="<?= e($edit['tombol_tautan'] ?? '') ?>">
                <div class="bantuan">Boleh alamat halaman sendiri (mis. <code>ppdb.php</code>) atau alamat lengkap.</div>
            </div>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Slide</button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="slider.php">Batal &amp; Tambah Baru</a><?php endif; ?>
        </div>
    </form>
</div>
<?php admin_bawah(); ?>
