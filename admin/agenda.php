<?php
/**
 * Agenda kegiatan.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'toggle') {
    q('UPDATE agenda SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('agenda.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('agenda', (int) ($_POST['id'] ?? 0));
    flash('Agenda dihapus.', 'ok');
    redirect('agenda.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $judul = trim((string) ($_POST['judul'] ?? ''));
    if ($judul === '') {
        flash('Judul agenda wajib diisi.', 'err');
        redirect('agenda.php' . ($id > 0 ? '?edit=' . $id : ''));
    }
    $lama = $id > 0 ? one('SELECT * FROM agenda WHERE id = ?', [$id]) : null;
    $g = proses_gambar('gambar', (string) ($lama['gambar'] ?? ''), !empty($_POST['gambar_hapus']), 'Gambar agenda');
    $data = [
        'judul'      => potong($judul, JUDUL_MAKS, ''),
        'tanggal'    => (string) ($_POST['tanggal'] ?? ''),
        'waktu'      => potong((string) ($_POST['waktu'] ?? ''), 60, ''),
        'tempat'     => potong((string) ($_POST['tempat'] ?? ''), NAMA_MAKS, ''),
        'keterangan' => potong((string) ($_POST['keterangan'] ?? ''), 1000, ''),
        'gambar'     => $g['url'],
        'aktif'      => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        $set = [];
        $val = [];
        foreach ($data as $k => $v) {
            $set[] = $k . ' = ?';
            $val[] = $v;
        }
        $val[] = $id;
        q('UPDATE agenda SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        flash('Agenda disimpan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    } else {
        $kol = array_keys($data);
        q('INSERT INTO agenda (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        flash('Agenda ditambahkan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    }
    redirect('agenda.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM agenda WHERE id = ?', [(int) $_GET['edit']]) : null;
$daftar = all("SELECT * FROM agenda ORDER BY (tanggal = '') DESC, tanggal DESC, id DESC");

admin_atas('Agenda');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Daftar Agenda</h2>
            <p class="mut kecil mb0">Agenda mendatang tampil pada beranda; seluruh agenda tampil di halaman Agenda.</p>
        </div>
        <a class="tombol tombol-kecil" href="agenda.php?edit=0#formAgenda"><?= ikon('plus', 15) ?> Tambah Agenda</a>
    </div>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Tanggal</th><th>Judul</th><th>Waktu &amp; Tempat</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$daftar): ?><tr><td colspan="5" class="mut">Belum ada agenda.</td></tr><?php endif; ?>
                <?php foreach ($daftar as $a): ?>
                    <tr>
                        <td class="kecil"><?= e(tanggal_id($a['tanggal'], true) ?: '-') ?></td>
                        <td>
                            <strong><?= e($a['judul']) ?></strong>
                            <?php if ($a['keterangan'] !== ''): ?><div class="kecil mut"><?= e(potong($a['keterangan'], 90)) ?></div><?php endif; ?>
                        </td>
                        <td class="kecil"><?= e($a['waktu']) ?><?= $a['tempat'] !== '' ? '<div class="mut">' . e($a['tempat']) . '</div>' : '' ?></td>
                        <td><?= ((int) $a['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('agenda.php?edit=' . (int) $a['id'] . '#formAgenda', 'edit', 'Ubah agenda') ?>
                            <?= tombol_mini('agenda.php?aksi=toggle&id=' . (int) $a['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                            <?= form_hapus('hapus', (int) $a['id'], 'Hapus agenda “' . $a['judul'] . '”?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="kartu" id="formAgenda">
    <h2><?= $edit ? 'Ubah Agenda' : 'Tambah Agenda Baru' ?></h2>
    <form method="post" action="agenda.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-baris">
            <label for="judul">Judul Kegiatan <span class="wajib">*</span></label>
            <input type="text" id="judul" name="judul" required maxlength="160" value="<?= e($edit['judul'] ?? '') ?>">
        </div>
        <div class="form-3">
            <div class="form-baris">
                <label for="tanggal">Tanggal</label>
                <input type="date" id="tanggal" name="tanggal" value="<?= e($edit['tanggal'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-baris">
                <label for="waktu">Waktu</label>
                <input type="text" id="waktu" name="waktu" maxlength="60" placeholder="08.00 - 11.00 WITA" value="<?= e($edit['waktu'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="tempat">Tempat</label>
                <input type="text" id="tempat" name="tempat" maxlength="120" placeholder="Aula Madrasah" value="<?= e($edit['tempat'] ?? '') ?>">
            </div>
        </div>
        <div class="form-baris">
            <label for="keterangan">Keterangan</label>
            <textarea id="keterangan" name="keterangan" maxlength="1000" style="min-height:100px"><?= e($edit['keterangan'] ?? '') ?></textarea>
        </div>
        <?php blok_gambar('gambar', (string) ($edit['gambar'] ?? ''), 'Gambar Kegiatan (opsional)'); ?>
        <div class="form-baris">
            <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan agenda</label>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Agenda</button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="agenda.php">Batal</a><?php endif; ?>
        </div>
    </form>
</div>
<?php admin_bawah(); ?>
