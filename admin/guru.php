<?php
/**
 * Data guru & tenaga kependidikan.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/guru.php';   // TMT, keterangan, masa kerja
require_login();

const GURU_JABATAN = ['Kepala Madrasah', 'Kepala Sekolah', 'Wakil Kepala', 'Guru', 'Guru BK', 'Tenaga Kependidikan', 'Kepala Tata Usaha', 'Pustakawan', 'Laboran', 'Penjaga Sekolah'];

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'toggle') {
    q('UPDATE guru SET status = CASE status WHEN ? THEN ? ELSE ? END WHERE id = ?',
        ['aktif', 'nonaktif', 'aktif', (int) ($_GET['id'] ?? 0)]);
    flash('Status guru diubah.', 'ok');
    redirect('guru.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('guru', (int) ($_POST['id'] ?? 0));
    flash('Data guru dihapus.', 'ok');
    redirect('guru.php');
}
if ($aksi === 'hapus_semua') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    if (strtoupper(trim((string) ($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
        flash('Penghapusan dibatalkan — ketik HAPUS pada kotak konfirmasi untuk melanjutkan.', 'err');
        redirect('guru.php');
    }
    $n = jumlah('SELECT COUNT(*) FROM guru');
    q('DELETE FROM guru');
    flash(angka($n) . ' data guru &amp; tenaga kependidikan dihapus seluruhnya.', 'ok');
    redirect('guru.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nama === '') {
        flash('Nama guru wajib diisi.', 'err');
        redirect('guru.php' . ($id > 0 ? '?edit=' . $id : '') . '#formGuru');
    }
    $lama = $id > 0 ? one('SELECT * FROM guru WHERE id = ?', [$id]) : null;
    $g = proses_gambar('foto', (string) ($lama['foto'] ?? ''), !empty($_POST['foto_hapus']), 'Foto');
    $data = [
        'nama'          => potong($nama, NAMA_MAKS, ''),
        'nip'           => potong(trim((string) ($_POST['nip'] ?? '')), 40, ''),
        'jenis_kelamin' => (string) ($_POST['jenis_kelamin'] ?? 'L') === 'P' ? 'P' : 'L',
        'jabatan'       => potong((string) ($_POST['jabatan'] ?? 'Guru'), 80, ''),
        'mapel'         => potong((string) ($_POST['mapel'] ?? ''), 160, ''),
        'email'         => potong((string) ($_POST['email'] ?? ''), 120, ''),
        'telepon'       => potong((string) ($_POST['telepon'] ?? ''), 40, ''),
        'foto'          => $g['url'],
        'status'        => (string) ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif',
        // v1.16.0 — TMT (tanggal mulai tugas) & Keterangan (Satmikal / Non Satmikal).
        'tmt'           => guru_tmt_sah((string) ($_POST['tmt'] ?? '')),
        'keterangan'    => guru_keterangan_sah((string) ($_POST['keterangan'] ?? '')),
    ];
    if ($id > 0) {
        q('UPDATE guru SET nama = ?, nip = ?, jenis_kelamin = ?, jabatan = ?, mapel = ?, email = ?, telepon = ?, foto = ?, status = ?, tmt = ?, keterangan = ? WHERE id = ?',
            [$data['nama'], $data['nip'], $data['jenis_kelamin'], $data['jabatan'], $data['mapel'], $data['email'],
             $data['telepon'], $data['foto'], $data['status'], $data['tmt'], $data['keterangan'], $id]);
        flash('Data guru disimpan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    } else {
        q('INSERT INTO guru (nama, nip, jenis_kelamin, jabatan, mapel, email, telepon, foto, status, tmt, keterangan, urutan, dibuat)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$data['nama'], $data['nip'], $data['jenis_kelamin'], $data['jabatan'], $data['mapel'], $data['email'],
             $data['telepon'], $data['foto'], $data['status'], $data['tmt'], $data['keterangan'],
             urutan_baru('guru'), date('Y-m-d H:i:s')]);
        flash('Data guru ditambahkan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    }
    redirect('guru.php');
}

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
$where = 'WHERE ' . implode(' AND ', $w);
$perHal = 20;
$hal = halaman_ini();
$total = jumlah("SELECT COUNT(*) FROM guru $where", $p);
$daftar = all("SELECT * FROM guru $where ORDER BY (status = 'nonaktif'), urutan, nama LIMIT $perHal OFFSET " . (($hal - 1) * $perHal), $p);
$jabatanAda = array_values(array_unique(array_merge(GURU_JABATAN, array_column(all('SELECT DISTINCT jabatan FROM guru'), 'jabatan'))));
sort($jabatanAda);
$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM guru WHERE id = ?', [(int) $_GET['edit']]) : null;

admin_atas('Data Guru & Tendik');
?>
<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2>Daftar Guru &amp; Tenaga Kependidikan</h2>
            <p class="mut kecil mb0"><?= angka($total) ?> data ditemukan · <?= angka(jumlah("SELECT COUNT(*) FROM guru WHERE status = 'aktif'")) ?> berstatus aktif. Data aktif dihitung pada statistik website.</p>
        </div>
        <div class="rasta" style="gap:8px;flex-wrap:wrap">
            <a class="tombol tombol-kecil" href="guru.php?edit=0#formGuru"><?= ikon('plus', 15) ?> Tambah Guru</a>
            <a class="tombol tombol-garis tombol-kecil" href="cetak_guru.php?<?= e(http_build_query(['cari' => $cari, 'status' => $status, 'jabatan' => $jabatan])) ?>"><?= ikon('print', 15) ?> Cetak Data Guru</a>
            <a class="tombol tombol-garis tombol-kecil" href="guru_import.php"><?= ikon('unggah', 15) ?> Impor Excel/CSV</a>
            <a class="tombol tombol-garis tombol-kecil" href="guru_export.php?<?= e(http_build_query(['cari' => $cari, 'status' => $status, 'jabatan' => $jabatan])) ?>"><?= ikon('unduh', 15) ?> Ekspor</a>
        </div>
    </div>

    <form class="saringan" method="get" action="guru.php">
        <div class="medan">
            <label for="saringCari">Cari nama / NIP / mata pelajaran</label>
            <input type="search" id="saringCari" name="cari" value="<?= e($cari) ?>">
        </div>
        <div class="medan">
            <label for="saringStatus">Status</label>
            <select id="saringStatus" name="status" data-auto>
                <option value="">Semua</option>
                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <div class="medan">
            <label for="saringJabatan">Jabatan</label>
            <select id="saringJabatan" name="jabatan" data-auto>
                <option value="">Semua jabatan</option>
                <?php foreach ($jabatanAda as $j): ?>
                    <?php if (trim((string) $j) === '') { continue; } ?>
                    <option value="<?= e($j) ?>" <?= $jabatan === $j ? 'selected' : '' ?>><?= e($j) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="tombol tombol-kecil"><?= ikon('search', 15) ?> Terapkan</button>
        <a class="tombol tombol-garis tombol-kecil" href="guru.php">Reset</a>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th style="width:70px">Foto</th><th>Nama</th><th>NIP/NUPTK</th><th>Jabatan</th><th>Mata Pelajaran</th><th>Kontak</th>
                <th style="width:120px">TMT</th><th style="width:150px">Masa Kerja</th><th style="width:120px">Keterangan</th>
                <th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$daftar): ?><tr><td colspan="11" class="mut">Belum ada data guru.</td></tr><?php endif; ?>
                <?php foreach ($daftar as $g): ?>
                    <tr>
                        <td><?= kotak_gambar($g['foto'], 'Foto', 'thumb-admin kotak-bulat') ?></td>
                        <td><strong><?= e($g['nama']) ?></strong><div class="kecil mut"><?= $g['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></div></td>
                        <td class="kecil"><?= e($g['nip'] ?: '-') ?></td>
                        <td class="kecil"><?= e($g['jabatan']) ?></td>
                        <td class="kecil"><?= e($g['mapel'] ?: '-') ?></td>
                        <td class="kecil">
                            <?= $g['telepon'] !== '' ? e($g['telepon']) : '' ?>
                            <?= $g['email'] !== '' ? '<div class="mut">' . e($g['email']) . '</div>' : '' ?>
                            <?= $g['telepon'] === '' && $g['email'] === '' ? '-' : '' ?>
                        </td>
                        <td class="kecil"><?= guru_tmt_teks($g['tmt'] ?? '') !== '' ? e(guru_tmt_teks($g['tmt'] ?? '')) : '-' ?></td>
                        <td class="kecil"><b><?= e(guru_masa_kerja($g['tmt'] ?? '')['teks']) ?></b></td>
                        <td class="kecil"><?= trim((string) ($g['keterangan'] ?? '')) !== ''
                            ? '<span class="pil pil-baru">' . e((string) $g['keterangan']) . '</span>' : '-' ?></td>
                        <td><?= $g['status'] === 'aktif' ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('guru.php?edit=' . (int) $g['id'] . '#formGuru', 'edit', 'Ubah') ?>
                            <?= tombol_mini('guru.php?aksi=toggle&id=' . (int) $g['id'], 'mata', 'Aktifkan/nonaktifkan') ?>
                            <?= form_hapus('hapus', (int) $g['id'], 'Hapus data guru ' . $g['nama'] . '?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi($total, $perHal, $hal) ?>
</div>

<div class="kartu mt24" id="formGuru">
    <h2><?= $edit ? 'Ubah Data Guru' : 'Tambah Data Guru' ?></h2>
    <form method="post" action="guru.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-3">
            <div class="form-baris">
                <label for="nama">Nama Lengkap &amp; Gelar <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="120" value="<?= e($edit['nama'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="nip">NIP / NUPTK</label>
                <input type="text" id="nip" name="nip" maxlength="40" value="<?= e($edit['nip'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="jenis_kelamin">Jenis Kelamin</label>
                <select id="jenis_kelamin" name="jenis_kelamin">
                    <option value="L" <?= ($edit['jenis_kelamin'] ?? 'L') === 'P' ? '' : 'selected' ?>>Laki-laki</option>
                    <option value="P" <?= ($edit['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>
        </div>
        <div class="form-3">
            <div class="form-baris">
                <label for="jabatan">Jabatan</label>
                <select id="jabatan" name="jabatan">
                    <?php foreach ($jabatanAda as $j): ?>
                        <?php if (trim((string) $j) === '') { continue; } ?>
                        <option value="<?= e($j) ?>" <?= ($edit['jabatan'] ?? 'Guru') === $j ? 'selected' : '' ?>><?= e($j) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-baris">
                <label for="mapel">Mata Pelajaran / Tugas</label>
                <input type="text" id="mapel" name="mapel" maxlength="160" value="<?= e($edit['mapel'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="telepon">Telepon / WhatsApp</label>
                <input type="tel" id="telepon" name="telepon" maxlength="40" value="<?= e($edit['telepon'] ?? '') ?>">
            </div>
        </div>
        <div class="form-2">
            <div class="form-baris">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="120" value="<?= e($edit['email'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="aktif" <?= ($edit['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= ($edit['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
                <div class="bantuan">Hanya guru berstatus aktif yang dihitung pada statistik “Guru &amp; Tendik” dan tampil di halaman Tenaga Pendidik.</div>
            </div>
        </div>
        <?php /* v1.16.0 — permintaan pemilik: isian TMT (tanggal-bulan-tahun) & Keterangan
                 (Satmikal / Non Satmikal) pada form Tambah/Ubah Data Guru. */ ?>
        <div class="form-2">
            <div class="form-baris">
                <label for="tmt">TMT Guru</label>
                <input type="date" id="tmt" name="tmt" value="<?= e((string) ($edit['tmt'] ?? '')) ?>">
                <div class="bantuan">Tanggal Mulai Tugas (format tanggal-bulan-tahun). Dipakai menghitung
                    <b>masa kerja</b>: <?= e(guru_masa_kerja($edit['tmt'] ?? '')['teks']) ?>.</div>
            </div>
            <div class="form-baris">
                <label for="keterangan">Keterangan</label>
                <select id="keterangan" name="keterangan">
                    <option value="">— pilih keterangan —</option>
                    <?php foreach (GURU_KETERANGAN as $kt): ?>
                        <option value="<?= e($kt) ?>" <?= (string) ($edit['keterangan'] ?? '') === $kt ? 'selected' : '' ?>><?= e($kt) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="bantuan">Pilih <b>Satmikal</b> atau <b>Non Satmikal</b>.</div>
            </div>
        </div>
        <?php blok_gambar('foto', (string) ($edit['foto'] ?? ''), 'Foto (opsional)'); ?>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Data Guru</button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="guru.php">Batal</a><?php endif; ?>
        </div>
    </form>

    <?php if (jumlah('SELECT COUNT(*) FROM guru') > 0): ?>
        <?php form_konfirmasi_ketik(
            'hapus_semua',
            'Hapus Semua Data Guru',
            'Seluruh data guru & tenaga kependidikan (' . angka(jumlah('SELECT COUNT(*) FROM guru')) . ' baris) akan dihapus permanen. Tindakan ini tidak dapat dibatalkan — bila perlu, unduh dulu berkas ekspornya.'
        ); ?>
    <?php endif; ?>
</div>
<?php admin_bawah(); ?>
