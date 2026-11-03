<?php
/**
 * Data siswa (termasuk impor, ekspor, dan pintasan ke kenaikan/kelulusan).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$STATUS_SISWA = ['aktif' => 'Aktif', 'lulus' => 'Lulus', 'keluar' => 'Keluar / Pindah', 'nonaktif' => 'Nonaktif'];

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('siswa', (int) ($_POST['id'] ?? 0));
    flash('Data siswa dihapus.', 'ok');
    redirect('siswa.php');
}
if ($aksi === 'hapus_semua') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    if (strtoupper(trim((string) ($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
        flash('Penghapusan dibatalkan — ketik HAPUS pada kotak konfirmasi untuk melanjutkan.', 'err');
        redirect('siswa.php');
    }
    $n = jumlah('SELECT COUNT(*) FROM siswa');
    db()->exec('BEGIN IMMEDIATE');
    try {
        q('DELETE FROM kenaikan');
        q('DELETE FROM siswa');
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        flash('Gagal menghapus data siswa: ' . e($ex->getMessage()), 'err');
        redirect('siswa.php');
    }
    flash(angka($n) . ' data siswa (beserta riwayat kenaikan/kelulusannya) dihapus seluruhnya.', 'ok');
    redirect('siswa.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nama === '') {
        flash('Nama siswa wajib diisi.', 'err');
        redirect('siswa.php' . ($id > 0 ? '?edit=' . $id : '') . '#formSiswa');
    }
    $lama = $id > 0 ? one('SELECT * FROM siswa WHERE id = ?', [$id]) : null;
    $g = proses_gambar('foto', (string) ($lama['foto'] ?? ''), !empty($_POST['foto_hapus']), 'Foto');
    $status = array_key_exists((string) ($_POST['status'] ?? ''), $STATUS_SISWA) ? (string) $_POST['status'] : 'aktif';
    $data = [
        'nis'           => potong(trim((string) ($_POST['nis'] ?? '')), 30, ''),
        'nisn'          => potong(trim((string) ($_POST['nisn'] ?? '')), 30, ''),
        'nama'          => potong($nama, NAMA_MAKS, ''),
        'jenis_kelamin' => (string) ($_POST['jenis_kelamin'] ?? 'L') === 'P' ? 'P' : 'L',
        'tempat_lahir'  => potong((string) ($_POST['tempat_lahir'] ?? ''), 80, ''),
        'tanggal_lahir' => (string) ($_POST['tanggal_lahir'] ?? ''),
        'kelas_id'      => (int) ($_POST['kelas_id'] ?? 0),
        'nama_ayah'     => potong((string) ($_POST['nama_ayah'] ?? ''), NAMA_MAKS, ''),
        'nama_ibu'      => potong((string) ($_POST['nama_ibu'] ?? ''), NAMA_MAKS, ''),
        'wali'          => potong((string) ($_POST['wali'] ?? ''), NAMA_MAKS, ''),
        'telepon'       => potong((string) ($_POST['telepon'] ?? ''), 40, ''),
        'alamat'        => potong((string) ($_POST['alamat'] ?? ''), 300, ''),
        'status'        => $status,
        'tahun_lulus'   => $status === 'lulus' ? potong((string) ($_POST['tahun_lulus'] ?? ''), 10, '') : '',
        'keterangan'    => potong((string) ($_POST['keterangan'] ?? ''), 200, ''),
        'foto'          => $g['url'],
    ];
    if ($id > 0) {
        $set = [];
        $val = [];
        foreach ($data as $k => $v) {
            $set[] = $k . ' = ?';
            $val[] = $v;
        }
        $val[] = $id;
        q('UPDATE siswa SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        flash('Data siswa disimpan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    } else {
        $data['tanggal_masuk'] = (string) ($_POST['tanggal_masuk'] ?? '') !== '' ? (string) $_POST['tanggal_masuk'] : date('Y-m-d');
        $data['dibuat'] = date('Y-m-d H:i:s');
        $kol = array_keys($data);
        q('INSERT INTO siswa (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        flash('Data siswa ditambahkan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    }
    redirect('siswa.php');
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$kelasFilter = (int) ($_GET['kelas'] ?? 0);
$statusFilter = (string) ($_GET['status'] ?? '');
$w = ['1 = 1'];
$p = [];
if ($cari !== '') {
    $w[] = '(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)';
    array_push($p, '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%');
}
if ($kelasFilter > 0) {
    $w[] = 's.kelas_id = ?';
    $p[] = $kelasFilter;
}
if ($statusFilter !== '' && array_key_exists($statusFilter, $STATUS_SISWA)) {
    $w[] = 's.status = ?';
    $p[] = $statusFilter;
}
$where = 'WHERE ' . implode(' AND ', $w);
$perHal = 25;
$hal = halaman_ini();
$total = jumlah("SELECT COUNT(*) FROM siswa s $where", $p);
$daftar = all("SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
               $where ORDER BY (s.status <> 'aktif'), k.urutan, s.nama LIMIT $perHal OFFSET " . (($hal - 1) * $perHal), $p);
$kelas = all('SELECT * FROM kelas ORDER BY urutan, id');
$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM siswa WHERE id = ?', [(int) $_GET['edit']]) : null;

admin_atas('Data Siswa');
?>
<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2>Daftar Siswa</h2>
            <p class="mut kecil mb0">
                <?= angka($total) ?> data ditemukan · Aktif <?= angka(jumlah("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'")) ?>
                · Lulus <?= angka(jumlah("SELECT COUNT(*) FROM siswa WHERE status = 'lulus'")) ?>.
                Data ini juga tampil di website pada menu Kesiswaan →
                <?php $slugAlumni = menu_slug_jenis('alumni'); $slugDataSiswa = menu_slug_jenis('siswa'); ?>
                <?php if ($slugAlumni !== ''): ?><a href="../halaman/<?= e($slugAlumni) ?>" target="_blank" rel="noopener">Data Alumni</a><?php endif; ?>
                <?php if ($slugDataSiswa !== ''): ?> dan
                    <a href="../halaman/<?= e($slugDataSiswa) ?>" target="_blank" rel="noopener">Data Siswa</a><?php endif; ?>.
            </p>
        </div>
        <div class="rasta" style="gap:8px;flex-wrap:wrap">
            <a class="tombol tombol-kecil" href="siswa.php?edit=0#formSiswa"><?= ikon('plus', 15) ?> Tambah Siswa</a>
            <a class="tombol tombol-garis tombol-kecil" href="cetak_siswa.php?<?= e(http_build_query(['cari' => $cari, 'kelas' => $kelasFilter, 'status' => $statusFilter])) ?>"><?= ikon('print', 15) ?> Cetak Data Siswa</a>
            <a class="tombol tombol-garis tombol-kecil" href="siswa_foto.php"><?= ikon('camera', 15) ?> Foto Massal</a>
            <a class="tombol tombol-garis tombol-kecil" href="siswa_import.php"><?= ikon('unggah', 15) ?> Impor Excel/CSV</a>
            <a class="tombol tombol-garis tombol-kecil" href="siswa_export.php?<?= e(http_build_query(['cari' => $cari, 'kelas' => $kelasFilter, 'status' => $statusFilter])) ?>"><?= ikon('unduh', 15) ?> Ekspor</a>
            <a class="tombol tombol-garis tombol-kecil" href="kenaikan.php"><?= ikon('naik', 15) ?> Kenaikan &amp; Kelulusan</a>
        </div>
    </div>

    <form class="saringan" method="get" action="siswa.php">
        <div class="medan">
            <label for="saringCari">Cari nama / NIS / NISN</label>
            <input type="search" id="saringCari" name="cari" value="<?= e($cari) ?>">
        </div>
        <div class="medan">
            <label for="saringKelas">Kelas</label>
            <select id="saringKelas" name="kelas" data-auto>
                <option value="0">Semua kelas</option>
                <?php foreach ($kelas as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $kelasFilter === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="medan">
            <label for="saringStatus">Status</label>
            <select id="saringStatus" name="status" data-auto>
                <option value="">Semua status</option>
                <?php foreach ($STATUS_SISWA as $k => $lb): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($lb) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="tombol tombol-kecil"><?= ikon('search', 15) ?> Terapkan</button>
        <a class="tombol tombol-garis tombol-kecil" href="siswa.php">Reset</a>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th style="width:60px">Foto</th><th>Nama</th><th>NIS / NISN</th><th>JK</th><th>Kelas</th><th>Tempat &amp; Tgl Lahir</th><th>Orang Tua / Wali</th><th>Kontak</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$daftar): ?><tr><td colspan="10" class="mut">Belum ada data siswa.</td></tr><?php endif; ?>
                <?php foreach ($daftar as $s): ?>
                    <tr>
                        <td><?= kotak_gambar($s['foto'], 'Foto', 'thumb-admin kotak-bulat') ?></td>
                        <td><strong><?= e($s['nama']) ?></strong></td>
                        <td class="kecil"><?= e($s['nis'] ?: '-') ?><div class="mut"><?= e($s['nisn'] ?: '-') ?></div></td>
                        <td class="kecil"><?= $s['jenis_kelamin'] === 'P' ? 'P' : 'L' ?></td>
                        <td class="kecil"><?= e($s['kelas'] ?: '-') ?></td>
                        <td class="kecil"><?= e($s['tempat_lahir']) ?><?= $s['tempat_lahir'] !== '' && $s['tanggal_lahir'] !== '' ? ', ' : '' ?><?= e(tanggal_id($s['tanggal_lahir'])) ?></td>
                        <td class="kecil">
                            <?php if ($s['nama_ayah'] !== '' || $s['nama_ibu'] !== ''): ?>
                                <div>Ayah: <?= e($s['nama_ayah'] ?: '-') ?></div>
                                <div>Ibu: <?= e($s['nama_ibu'] ?: '-') ?></div>
                            <?php endif; ?>
                            <?= $s['wali'] !== '' ? '<div class="mut">Wali: ' . e($s['wali']) . '</div>' : '' ?>
                            <?= $s['nama_ayah'] === '' && $s['nama_ibu'] === '' && $s['wali'] === '' ? '-' : '' ?>
                        </td>
                        <td class="kecil"><?= e($s['telepon'] ?: '-') ?><?php if ($s['alamat'] !== ''): ?><div class="mut"><?= e(potong($s['alamat'], 40)) ?></div><?php endif; ?></td>
                        <td>
                            <span class="pil <?= $s['status'] === 'aktif' ? 'pil-terima' : ($s['status'] === 'lulus' ? 'pil-emas' : 'pil-baru') ?>"><?= e($STATUS_SISWA[$s['status']] ?? $s['status']) ?></span>
                            <?php if ($s['status'] === 'lulus' && $s['tahun_lulus'] !== ''): ?><div class="kecil mut">Lulus <?= e($s['tahun_lulus']) ?></div><?php endif; ?>
                            <?php if (trim((string) $s['keterangan']) !== ''): ?><div class="kecil mut"><?= e(potong($s['keterangan'], 40)) ?></div><?php endif; ?>
                        </td>
                        <td class="aksi-sel">
                            <?= tombol_mini('siswa.php?edit=' . (int) $s['id'] . '#formSiswa', 'edit', 'Ubah') ?>
                            <?= form_hapus('hapus', (int) $s['id'], 'Hapus data siswa ' . $s['nama'] . '?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi($total, $perHal, $hal) ?>
</div>

<div class="kartu mt24" id="formSiswa">
    <h2><?= $edit ? 'Ubah Data Siswa' : 'Tambah Data Siswa' ?></h2>
    <form method="post" action="siswa.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-3">
            <div class="form-baris">
                <label for="nama">Nama Lengkap <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="120" value="<?= e($edit['nama'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="nis">NIS</label>
                <input type="text" id="nis" name="nis" maxlength="30" value="<?= e($edit['nis'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="nisn">NISN</label>
                <input type="text" id="nisn" name="nisn" maxlength="30" value="<?= e($edit['nisn'] ?? '') ?>">
            </div>
        </div>
        <div class="form-3">
            <div class="form-baris">
                <label for="jenis_kelamin">Jenis Kelamin</label>
                <select id="jenis_kelamin" name="jenis_kelamin">
                    <option value="L" <?= ($edit['jenis_kelamin'] ?? 'L') === 'P' ? '' : 'selected' ?>>Laki-laki</option>
                    <option value="P" <?= ($edit['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>
            <div class="form-baris">
                <label for="tempat_lahir">Tempat Lahir</label>
                <input type="text" id="tempat_lahir" name="tempat_lahir" maxlength="80" value="<?= e($edit['tempat_lahir'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="tanggal_lahir">Tanggal Lahir</label>
                <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= e($edit['tanggal_lahir'] ?? '') ?>">
            </div>
        </div>
        <div class="form-3">
            <div class="form-baris">
                <label for="kelas_id">Kelas</label>
                <select id="kelas_id" name="kelas_id">
                    <option value="0">— tanpa kelas —</option>
                    <?php foreach ($kelas as $k): ?>
                        <option value="<?= (int) $k['id'] ?>" <?= (int) ($edit['kelas_id'] ?? 0) === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-baris">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach ($STATUS_SISWA as $k => $lb): ?>
                        <option value="<?= e($k) ?>" <?= ($edit['status'] ?? 'aktif') === $k ? 'selected' : '' ?>><?= e($lb) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-baris">
                <label for="tahun_lulus">Tahun Keluar / Lulus (bila berstatus Lulus)</label>
                <input type="text" id="tahun_lulus" name="tahun_lulus" maxlength="10" placeholder="<?= date('Y') ?>" value="<?= e($edit['tahun_lulus'] ?? '') ?>">
                <div class="bantuan">Tampil sebagai “Tahun Keluar” pada halaman Data Alumni.</div>
            </div>
        </div>
        <div class="form-baris">
            <label for="keterangan">Keterangan Alumni (kondisi setelah lulus)</label>
            <textarea id="keterangan" name="keterangan" maxlength="200" style="min-height:70px"
                      placeholder="mis. Kuliah di Universitas Negeri Gorontalo / Bekerja di PT …"><?= e($edit['keterangan'] ?? '') ?></textarea>
            <div class="bantuan">Tampil pada kolom “Keterangan” di halaman publik <strong>Data Alumni</strong> (Kesiswaan).</div>
        </div>
        <div class="form-3">
            <div class="form-baris">
                <label for="nama_ayah">Nama Ayah</label>
                <input type="text" id="nama_ayah" name="nama_ayah" maxlength="120" value="<?= e($edit['nama_ayah'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="nama_ibu">Nama Ibu</label>
                <input type="text" id="nama_ibu" name="nama_ibu" maxlength="120" value="<?= e($edit['nama_ibu'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="wali">Nama Wali</label>
                <input type="text" id="wali" name="wali" maxlength="120" value="<?= e($edit['wali'] ?? '') ?>">
            </div>
        </div>
        <div class="form-2">
            <div class="form-baris">
                <label for="telepon">Telepon / WhatsApp</label>
                <input type="tel" id="telepon" name="telepon" maxlength="40" value="<?= e($edit['telepon'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="tanggal_masuk">Tanggal Masuk</label>
                <input type="date" id="tanggal_masuk" name="tanggal_masuk" value="<?= e($edit['tanggal_masuk'] ?? date('Y-m-d')) ?>">
            </div>
        </div>
        <div class="form-baris">
            <label for="alamat">Alamat</label>
            <input type="text" id="alamat" name="alamat" maxlength="300" value="<?= e($edit['alamat'] ?? '') ?>">
        </div>
        <?php blok_gambar('foto', (string) ($edit['foto'] ?? ''), 'Foto Siswa (opsional, 3x4)'); ?>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Data Siswa</button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="siswa.php">Batal</a><?php endif; ?>
        </div>
    </form>

    <?php if (jumlah('SELECT COUNT(*) FROM siswa') > 0): ?>
        <?php form_konfirmasi_ketik(
            'hapus_semua',
            'Hapus Semua Data Siswa',
            'Seluruh data siswa (' . angka(jumlah('SELECT COUNT(*) FROM siswa')) . ' baris) beserta riwayat kenaikan/kelulusannya akan dihapus permanen. Unduh berkas ekspor bila datanya masih diperlukan.'
        ); ?>
    <?php endif; ?>
</div>
<?php admin_bawah(); ?>
