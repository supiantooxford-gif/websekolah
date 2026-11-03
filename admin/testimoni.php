<?php
/**
 * Testimoni "Apa kata mereka".
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan('testimoni', (int) ($_GET['id'] ?? 0), $aksi);
    redirect('testimoni.php');
}
if ($aksi === 'toggle') {
    q('UPDATE testimoni SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('testimoni.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('testimoni', (int) ($_POST['id'] ?? 0));
    flash('Testimoni dihapus.', 'ok');
    redirect('testimoni.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nama === '') {
        flash('Nama wajib diisi.', 'err');
        redirect('testimoni.php');
    }
    $lama = $id > 0 ? one('SELECT * FROM testimoni WHERE id = ?', [$id]) : null;
    $g = proses_gambar('foto', (string) ($lama['foto'] ?? ''), !empty($_POST['foto_hapus']), 'Foto');
    $data = [
        'nama'    => potong($nama, NAMA_MAKS, ''),
        'jabatan' => potong((string) ($_POST['jabatan'] ?? ''), NAMA_MAKS, ''),
        'isi'     => potong((string) ($_POST['isi'] ?? ''), 1000, ''),
        'foto'    => $g['url'],
        'aktif'   => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        q('UPDATE testimoni SET nama = ?, jabatan = ?, isi = ?, foto = ?, aktif = ? WHERE id = ?',
            [$data['nama'], $data['jabatan'], $data['isi'], $data['foto'], $data['aktif'], $id]);
        flash('Testimoni disimpan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    } else {
        q('INSERT INTO testimoni (nama, jabatan, isi, foto, aktif, urutan) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['nama'], $data['jabatan'], $data['isi'], $data['foto'], $data['aktif'], urutan_baru('testimoni')]);
        flash('Testimoni ditambahkan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    }
    redirect('testimoni.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM testimoni WHERE id = ?', [(int) $_GET['edit']]) : null;
$daftar = all('SELECT * FROM testimoni ORDER BY urutan, id');

admin_atas('Apa Kata Mereka');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Daftar Testimoni</h2>
            <p class="mut kecil mb0">Kesan dan pesan dari peserta didik, orang tua, alumni, atau masyarakat.</p>
        </div>
        <a class="tombol tombol-kecil" href="testimoni.php?edit=0#formTestimoni"><?= ikon('plus', 15) ?> Tambah Testimoni</a>
    </div>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th style="width:48px">Urut</th><th style="width:80px">Foto</th><th>Nama &amp; Jabatan</th><th>Isi</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$daftar): ?><tr><td colspan="6" class="mut">Belum ada testimoni.</td></tr><?php endif; ?>
                <?php foreach ($daftar as $t): ?>
                    <tr>
                        <td class="aksi-sel"><?= tombol_urut('testimoni.php', (int) $t['id']) ?></td>
                        <td><?= kotak_gambar($t['foto'], 'Foto', 'thumb-admin kotak-bulat') ?></td>
                        <td><strong><?= e($t['nama']) ?></strong><div class="kecil mut"><?= e($t['jabatan']) ?></div></td>
                        <td class="kecil"><?= e(potong($t['isi'], 120)) ?></td>
                        <td><?= ((int) $t['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('testimoni.php?edit=' . (int) $t['id'] . '#formTestimoni', 'edit', 'Ubah') ?>
                            <?= tombol_mini('testimoni.php?aksi=toggle&id=' . (int) $t['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                            <?= form_hapus('hapus', (int) $t['id'], 'Hapus testimoni dari ' . $t['nama'] . '?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="kartu" id="formTestimoni">
    <h2><?= $edit ? 'Ubah Testimoni' : 'Tambah Testimoni' ?></h2>
    <form method="post" action="testimoni.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-2">
            <div class="form-baris">
                <label for="nama">Nama <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="120" value="<?= e($edit['nama'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="jabatan">Jabatan / Keterangan</label>
                <input type="text" id="jabatan" name="jabatan" maxlength="120" placeholder="Alumni / Orang Tua Peserta Didik" value="<?= e($edit['jabatan'] ?? '') ?>">
            </div>
        </div>
        <div class="form-baris">
            <label for="isi">Isi Testimoni</label>
            <textarea id="isi" name="isi" maxlength="1000" style="min-height:120px"><?= e($edit['isi'] ?? '') ?></textarea>
        </div>
        <?php blok_gambar('foto', (string) ($edit['foto'] ?? ''), 'Foto (opsional)'); ?>
        <div class="form-baris">
            <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan</label>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan</button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="testimoni.php">Batal</a><?php endif; ?>
        </div>
    </form>
</div>
<?php admin_bawah(); ?>
