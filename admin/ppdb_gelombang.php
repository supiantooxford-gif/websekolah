<?php
/**
 * PPDB — gelombang & jadwal pendaftaran.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan('ppdb_gelombang', (int) ($_GET['id'] ?? 0), $aksi);
    redirect('ppdb_gelombang.php');
}
if ($aksi === 'toggle') {
    q('UPDATE ppdb_gelombang SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('ppdb_gelombang.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $n = jumlah('SELECT COUNT(*) FROM ppdb_pendaftar WHERE gelombang_id = ?', [$id]);
    q('UPDATE ppdb_pendaftar SET gelombang_id = 0 WHERE gelombang_id = ?', [$id]);
    hapus_baris('ppdb_gelombang', $id);
    flash('Gelombang dihapus.' . ($n > 0 ? ' ' . $n . ' pendaftar tidak lagi terhubung ke gelombang ini (datanya tetap ada).' : ''), 'ok');
    redirect('ppdb_gelombang.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nama === '') {
        flash('Nama gelombang wajib diisi.', 'err');
        redirect('ppdb_gelombang.php');
    }
    $data = [
        'nama'         => potong($nama, JUDUL_MAKS, ''),
        'tahun_ajaran' => potong((string) ($_POST['tahun_ajaran'] ?? ''), 20, ''),
        'mulai'        => (string) ($_POST['mulai'] ?? ''),
        'selesai'      => (string) ($_POST['selesai'] ?? ''),
        'kuota'        => max(0, (int) ($_POST['kuota'] ?? 0)),
        'jalur'        => potong((string) ($_POST['jalur'] ?? ''), 160, ''),
        'keterangan'   => potong((string) ($_POST['keterangan'] ?? ''), 1000, ''),
        'aktif'        => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        q('UPDATE ppdb_gelombang SET nama = ?, tahun_ajaran = ?, mulai = ?, selesai = ?, kuota = ?, jalur = ?, keterangan = ?, aktif = ?
           WHERE id = ?',
            [$data['nama'], $data['tahun_ajaran'], $data['mulai'], $data['selesai'], $data['kuota'], $data['jalur'], $data['keterangan'], $data['aktif'], $id]);
        flash('Gelombang disimpan.', 'ok');
    } else {
        q('INSERT INTO ppdb_gelombang (nama, tahun_ajaran, mulai, selesai, kuota, jalur, keterangan, aktif, urutan)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['nama'], $data['tahun_ajaran'], $data['mulai'], $data['selesai'], $data['kuota'], $data['jalur'],
             $data['keterangan'], $data['aktif'], urutan_baru('ppdb_gelombang')]);
        flash('Gelombang ditambahkan.', 'ok');
    }
    redirect('ppdb_gelombang.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM ppdb_gelombang WHERE id = ?', [(int) $_GET['edit']]) : null;
$daftar = all('SELECT g.*, (SELECT COUNT(*) FROM ppdb_pendaftar p WHERE p.gelombang_id = g.id) AS jml
               FROM ppdb_gelombang g ORDER BY g.urutan, g.id');

admin_atas('Gelombang & Jadwal PPDB');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Gelombang Pendaftaran</h2>
            <p class="mut kecil mb0">Hanya gelombang <strong>aktif</strong> dan berada dalam rentang tanggal yang dipakai formulir pendaftaran.</p>
        </div>
        <a class="tombol tombol-kecil" href="ppdb_gelombang.php?edit=0#formGelombang"><?= ikon('plus', 15) ?> Tambah Gelombang</a>
    </div>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th style="width:48px">Urut</th><th>Nama</th><th>Tahun Ajaran</th><th>Periode</th><th>Kuota</th><th>Jalur</th><th>Pendaftar</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$daftar): ?><tr><td colspan="9" class="mut">Belum ada gelombang.</td></tr><?php endif; ?>
                <?php foreach ($daftar as $g): ?>
                    <tr>
                        <td class="aksi-sel"><?= tombol_urut('ppdb_gelombang.php', (int) $g['id']) ?></td>
                        <td><strong><?= e($g['nama']) ?></strong><div class="kecil mut"><?= e(potong($g['keterangan'], 70)) ?></div></td>
                        <td class="kecil"><?= e($g['tahun_ajaran'] ?: '-') ?></td>
                        <td class="kecil"><?= e(tanggal_id($g['mulai']) ?: '-') ?> s.d. <?= e(tanggal_id($g['selesai']) ?: '-') ?></td>
                        <td><?= (int) $g['kuota'] > 0 ? (int) $g['kuota'] : '-' ?></td>
                        <td class="kecil"><?= e($g['jalur'] ?: '-') ?></td>
                        <td><?= angka($g['jml']) ?></td>
                        <td><?= ((int) $g['aktif'] === 1) ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('ppdb_gelombang.php?edit=' . (int) $g['id'] . '#formGelombang', 'edit', 'Ubah') ?>
                            <?= tombol_mini('ppdb_gelombang.php?aksi=toggle&id=' . (int) $g['id'], 'mata', 'Aktifkan/nonaktifkan') ?>
                            <?= form_hapus('hapus', (int) $g['id'], 'Hapus gelombang ' . $g['nama'] . '?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="kartu" id="formGelombang">
    <h2><?= $edit ? 'Ubah Gelombang' : 'Tambah Gelombang' ?></h2>
    <form method="post" action="ppdb_gelombang.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-3">
            <div class="form-baris">
                <label for="nama">Nama Gelombang <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="160" value="<?= e($edit['nama'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="tahun_ajaran">Tahun Ajaran</label>
                <input type="text" id="tahun_ajaran" name="tahun_ajaran" maxlength="20" value="<?= e($edit['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1))) ?>">
            </div>
            <div class="form-baris">
                <label for="kuota">Kuota</label>
                <input type="number" id="kuota" name="kuota" min="0" value="<?= (int) ($edit['kuota'] ?? 0) ?>">
            </div>
        </div>
        <div class="form-3">
            <div class="form-baris">
                <label for="mulai">Tanggal Mulai</label>
                <input type="date" id="mulai" name="mulai" value="<?= e($edit['mulai'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-baris">
                <label for="selesai">Tanggal Selesai</label>
                <input type="date" id="selesai" name="selesai" value="<?= e($edit['selesai'] ?? date('Y-m-d', strtotime('+45 day'))) ?>">
            </div>
            <div class="form-baris">
                <label for="jalur">Jalur (boleh beberapa, pisahkan dengan koma)</label>
                <input type="text" id="jalur" name="jalur" maxlength="160" placeholder="Reguler, Prestasi" value="<?= e($edit['jalur'] ?? 'Reguler') ?>">
            </div>
        </div>
        <div class="form-baris">
            <label for="keterangan">Keterangan</label>
            <textarea id="keterangan" name="keterangan" maxlength="1000" style="min-height:80px"><?= e($edit['keterangan'] ?? '') ?></textarea>
        </div>
        <div class="form-baris">
            <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Gelombang ini dibuka</label>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Gelombang</button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="ppdb_gelombang.php">Batal</a><?php endif; ?>
        </div>
    </form>
</div>
<?php admin_bawah(); ?>
