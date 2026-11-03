<?php
/**
 * Data kelas (dipakai pada data siswa & kenaikan kelas).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan('kelas', (int) ($_GET['id'] ?? 0), $aksi);
    redirect('kelas.php');
}
if ($aksi === 'toggle') {
    q('UPDATE kelas SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('kelas.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $n = jumlah('SELECT COUNT(*) FROM siswa WHERE kelas_id = ? AND status = ?', [$id, 'aktif']);
    q('UPDATE siswa SET kelas_id = 0 WHERE kelas_id = ?', [$id]);
    hapus_baris('kelas', $id);
    flash('Kelas dihapus.' . ($n > 0 ? ' ' . $n . ' siswa aktif dipindahkan ke “tanpa kelas”.' : ''), 'ok');
    redirect('kelas.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nama === '') {
        flash('Nama kelas wajib diisi.', 'err');
        redirect('kelas.php');
    }
    $data = [
        'nama'   => potong($nama, 60, ''),
        'tingkat' => potong((string) ($_POST['tingkat'] ?? ''), 30, ''),
        'wali'   => potong((string) ($_POST['wali'] ?? ''), NAMA_MAKS, ''),
        'aktif'  => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        q('UPDATE kelas SET nama = ?, tingkat = ?, wali = ?, aktif = ? WHERE id = ?',
            [$data['nama'], $data['tingkat'], $data['wali'], $data['aktif'], $id]);
        flash('Kelas disimpan.', 'ok');
    } else {
        $data['urutan'] = urutan_baru('kelas');
        q('INSERT INTO kelas (nama, tingkat, wali, aktif, urutan) VALUES (?, ?, ?, ?, ?)',
            [$data['nama'], $data['tingkat'], $data['wali'], $data['aktif'], $data['urutan']]);
        flash('Kelas ditambahkan.', 'ok');
    }
    redirect('kelas.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM kelas WHERE id = ?', [(int) $_GET['edit']]) : null;
$daftar = all('SELECT k.*, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.status = ?) AS jml
               FROM kelas k ORDER BY k.urutan, k.id', ['aktif']);

admin_atas('Data Kelas');
?>
<div class="grid g21">
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2>Daftar Kelas</h2>
                <p class="mut kecil mb0">Kelas dipakai pada data siswa, kenaikan kelas, dan data alumni.</p>
            </div>
            <a class="tombol tombol-kecil" href="kelas.php?edit=0#formKelas"><?= ikon('plus', 15) ?> Tambah Kelas</a>
        </div>
        <div class="tabel-wrap">
            <table class="tabel">
                <thead><tr><th style="width:48px">Urut</th><th>Nama Kelas</th><th>Tingkat</th><th>Wali Kelas</th><th>Siswa Aktif</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
                <tbody>
                    <?php if (!$daftar): ?><tr><td colspan="7" class="mut">Belum ada data kelas.</td></tr><?php endif; ?>
                    <?php foreach ($daftar as $k): ?>
                        <tr>
                            <td class="aksi-sel"><?= tombol_urut('kelas.php', (int) $k['id']) ?></td>
                            <td><strong><?= e($k['nama']) ?></strong></td>
                            <td class="kecil"><?= e($k['tingkat'] ?: '-') ?></td>
                            <td class="kecil"><?= e($k['wali'] ?: '-') ?></td>
                            <td><?= angka($k['jml']) ?></td>
                            <td><?= ((int) $k['aktif'] === 1) ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                            <td class="aksi-sel">
                                <?= tombol_mini('kelas.php?edit=' . (int) $k['id'] . '#formKelas', 'edit', 'Ubah') ?>
                                <?= tombol_mini('kelas.php?aksi=toggle&id=' . (int) $k['id'], 'mata', 'Aktifkan/nonaktifkan') ?>
                                <?= form_hapus('hapus', (int) $k['id'], 'Hapus kelas ' . $k['nama'] . '?') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="kartu" id="formKelas">
        <h2><?= $edit ? 'Ubah Kelas' : 'Tambah Kelas' ?></h2>
        <form method="post" action="kelas.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-baris">
                <label for="nama">Nama Kelas <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="60" placeholder="VII A" value="<?= e($edit['nama'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="tingkat">Tingkat</label>
                <input type="text" id="tingkat" name="tingkat" maxlength="30" placeholder="VII" value="<?= e($edit['tingkat'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="wali">Wali Kelas</label>
                <input type="text" id="wali" name="wali" maxlength="120" value="<?= e($edit['wali'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Aktif</label>
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan</button>
                <?php if ($edit): ?><a class="tombol tombol-garis" href="kelas.php">Batal</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php admin_bawah(); ?>
