<?php
/**
 * PPDB — data pendaftar, verifikasi, seleksi, dan pengumuman.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$STATUS = ['baru' => 'Baru', 'verifikasi' => 'Sedang Diverifikasi', 'diterima' => 'Diterima',
    'cadangan' => 'Cadangan', 'ditolak' => 'Tidak Diterima', 'batal' => 'Dibatalkan'];

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $p = one('SELECT * FROM ppdb_pendaftar WHERE id = ?', [$id]);
    if (!$p) {
        flash('Data pendaftar tidak ditemukan.', 'err');
        redirect('ppdb_pendaftar.php');
    }
    $status = array_key_exists((string) ($_POST['status'] ?? ''), $STATUS) ? (string) $_POST['status'] : 'baru';
    q('UPDATE ppdb_pendaftar SET status = ?, nilai_seleksi = ?, catatan_panitia = ?, diubah = ? WHERE id = ?',
        [$status, potong((string) ($_POST['nilai_seleksi'] ?? ''), 20, ''),
         potong((string) ($_POST['catatan_panitia'] ?? ''), 1000, ''), date('Y-m-d H:i:s'), $id]);
    if (!empty($_POST['beritahu'])) {
        // Catatan: tidak ada layanan surat elektronik, jadi pemberitahuan dilakukan manual.
        flash('Status ' . $p['nama'] . ' diubah menjadi “' . $STATUS[$status] . '”. Hubungi pendaftar melalui ' . e($p['telepon']) . ' untuk memberi tahu hasilnya.', 'info');
    } else {
        flash('Status ' . e($p['nama']) . ' diperbarui menjadi “' . e($STATUS[$status]) . '”.', 'ok');
    }
    redirect('ppdb_pendaftar.php?id=' . $id);
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('ppdb_pendaftar', (int) ($_POST['id'] ?? 0));
    flash('Data pendaftar dihapus.', 'ok');
    redirect('ppdb_pendaftar.php');
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$statusF = (string) ($_GET['status'] ?? '');
$gelF = (int) ($_GET['gelombang'] ?? 0);
$w = ['1 = 1'];
$p = [];
if ($cari !== '') {
    $w[] = '(nama LIKE ? OR nomor LIKE ? OR nisn LIKE ? OR asal_sekolah LIKE ? OR telepon LIKE ?)';
    array_push($p, '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%');
}
if ($statusF !== '' && array_key_exists($statusF, $STATUS)) {
    $w[] = 'status = ?';
    $p[] = $statusF;
}
if ($gelF > 0) {
    $w[] = 'gelombang_id = ?';
    $p[] = $gelF;
}
$where = 'WHERE ' . implode(' AND ', $w);

// ---------------------------------------------------------------- ekspor CSV
if (($_GET['unduh'] ?? '') === 'csv') {
    $baris = all("SELECT * FROM ppdb_pendaftar $where ORDER BY id DESC", $p);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ppdb-pendaftar-' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $keluar = fopen('php://output', 'w');
    fputcsv($keluar, ['Nomor', 'Nama', 'NISN', 'L/P', 'Tempat Lahir', 'Tanggal Lahir', 'Asal Sekolah',
        'Ayah', 'Ibu', 'Wali', 'Telepon', 'Email', 'Alamat', 'Jalur', 'Status', 'Nilai Seleksi',
        'Catatan Panitia', 'Tanggal Daftar'], ';');
    foreach ($baris as $b) {
        fputcsv($keluar, [$b['nomor'], $b['nama'], $b['nisn'], $b['jenis_kelamin'], $b['tempat_lahir'],
            $b['tanggal_lahir'], $b['asal_sekolah'], $b['nama_ayah'], $b['nama_ibu'], $b['wali'],
            $b['telepon'], $b['email'], $b['alamat'], $b['jalur'], $STATUS[$b['status']] ?? $b['status'],
            $b['nilai_seleksi'], $b['catatan_panitia'], $b['tanggal_daftar']], ';');
    }
    fclose($keluar);
    exit;
}

$perHal = 20;
$hal = halaman_ini();
$total = jumlah("SELECT COUNT(*) FROM ppdb_pendaftar $where", $p);
$daftar = all("SELECT * FROM ppdb_pendaftar $where ORDER BY id DESC LIMIT $perHal OFFSET " . (($hal - 1) * $perHal), $p);
$gelombang = all('SELECT * FROM ppdb_gelombang ORDER BY urutan, id');
$detail = (int) ($_GET['id'] ?? 0) > 0 ? one('SELECT * FROM ppdb_pendaftar WHERE id = ?', [(int) $_GET['id']]) : null;

admin_atas('Data Pendaftar PPDB');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Data Pendaftar</h2>
            <p class="mut kecil mb0"><?= angka($total) ?> pendaftar sesuai saringan. Klik nomor pendaftaran untuk melihat detail, berkas, dan menetapkan hasil seleksi.</p>
        </div>
        <div class="rasta" style="gap:8px;flex-wrap:wrap">
            <a class="tombol tombol-garis tombol-kecil" href="ppdb.php"><?= ikon('setelan', 15) ?> Pengaturan PPDB</a>
            <a class="tombol tombol-garis tombol-kecil" href="ppdb_pendaftar.php?unduh=csv&amp;<?= e(http_build_query(['cari' => $cari, 'status' => $statusF, 'gelombang' => $gelF])) ?>"><?= ikon('unduh', 15) ?> Ekspor CSV</a>
        </div>
    </div>

    <form class="saringan" method="get" action="ppdb_pendaftar.php">
        <div class="medan">
            <label for="saringCari">Cari nomor / nama / NISN / asal sekolah</label>
            <input type="search" id="saringCari" name="cari" value="<?= e($cari) ?>">
        </div>
        <div class="medan">
            <label for="saringStatus">Status</label>
            <select id="saringStatus" name="status" data-auto>
                <option value="">Semua status</option>
                <?php foreach ($STATUS as $k => $lb): ?>
                    <option value="<?= e($k) ?>" <?= $statusF === $k ? 'selected' : '' ?>><?= e($lb) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="medan">
            <label for="saringGelombang">Gelombang</label>
            <select id="saringGelombang" name="gelombang" data-auto>
                <option value="0">Semua gelombang</option>
                <?php foreach ($gelombang as $g): ?>
                    <option value="<?= (int) $g['id'] ?>" <?= $gelF === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="tombol tombol-kecil"><?= ikon('search', 15) ?> Terapkan</button>
        <a class="tombol tombol-garis tombol-kecil" href="ppdb_pendaftar.php">Reset</a>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Nomor</th><th>Nama</th><th>Asal Sekolah</th><th>Kontak</th><th>Tgl Daftar</th><th>Berkas</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$daftar): ?><tr><td colspan="8" class="mut">Belum ada pendaftar.</td></tr><?php endif; ?>
                <?php foreach ($daftar as $b): ?>
                    <?php $bk = json_decode((string) $b['berkas'], true) ?: []; ?>
                    <tr>
                        <td class="kecil"><a href="ppdb_pendaftar.php?id=<?= (int) $b['id'] ?>#detail"><?= e($b['nomor']) ?></a></td>
                        <td><strong><?= e($b['nama']) ?></strong><div class="kecil mut"><?= $b['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?><?= $b['nisn'] !== '' ? ' · NISN ' . e($b['nisn']) : '' ?></div></td>
                        <td class="kecil"><?= e($b['asal_sekolah'] ?: '-') ?></td>
                        <td class="kecil"><?= e($b['telepon']) ?><?= $b['email'] !== '' ? '<div class="mut">' . e($b['email']) . '</div>' : '' ?></td>
                        <td class="kecil"><?= e(tanggal_id($b['tanggal_daftar'])) ?></td>
                        <td class="kecil"><?= count($bk) ?> berkas</td>
                        <td><span class="<?= e(ppdb_status_kelas((string) $b['status'])) ?>"><?= e($STATUS[$b['status']] ?? $b['status']) ?></span></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('ppdb_pendaftar.php?id=' . (int) $b['id'] . '#detail', 'mata', 'Detail & seleksi') ?>
                            <?= form_hapus('hapus', (int) $b['id'], 'Hapus data pendaftar ' . $b['nomor'] . '?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi($total, $perHal, $hal) ?>
</div>

<?php if ($detail): ?>
    <?php $bk = json_decode((string) $detail['berkas'], true) ?: []; ?>
    <div class="kartu" id="detail">
        <div class="kartu-kepala">
            <div>
                <h2>Detail Pendaftar — <?= e($detail['nomor']) ?></h2>
                <p class="mut kecil mb0">Mendaftar pada <?= e(waktu_id($detail['tanggal_daftar'])) ?> dari <?= e($detail['ip']) ?></p>
            </div>
            <span class="<?= e(ppdb_status_kelas((string) $detail['status'])) ?>"><?= e($STATUS[$detail['status']] ?? $detail['status']) ?></span>
        </div>
        <div class="grid g2">
            <div class="tabel-wrap">
                <table class="tabel">
                    <tbody>
                        <tr><th style="width:190px">Nama Lengkap</th><td><?= e($detail['nama']) ?></td></tr>
                        <tr><th>NISN</th><td><?= e($detail['nisn'] ?: '-') ?></td></tr>
                        <tr><th>Jenis Kelamin</th><td><?= $detail['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></td></tr>
                        <tr><th>Tempat, Tanggal Lahir</th><td><?= e($detail['tempat_lahir']) ?><?= $detail['tanggal_lahir'] !== '' ? ', ' . e(tanggal_id($detail['tanggal_lahir'])) : '' ?></td></tr>
                        <tr><th>Sekolah/Madrasah Asal</th><td><?= e($detail['asal_sekolah'] ?: '-') ?></td></tr>
                        <tr><th>Jalur</th><td><?= e($detail['jalur'] ?: '-') ?></td></tr>
                        <tr><th>Nama Ayah / Ibu / Wali</th><td><?= e($detail['nama_ayah'] ?: '-') ?> / <?= e($detail['nama_ibu'] ?: '-') ?> / <?= e($detail['wali'] ?: '-') ?></td></tr>
                        <tr><th>Telepon / Email</th><td><?= e($detail['telepon']) ?><?= $detail['email'] !== '' ? ' · ' . e($detail['email']) : '' ?></td></tr>
                        <tr><th>Alamat</th><td><?= e($detail['alamat'] ?: '-') ?></td></tr>
                        <?php if (trim((string) $detail['catatan_pendaftar']) !== ''): ?>
                            <tr><th>Catatan Pendaftar</th><td><?= nl($detail['catatan_pendaftar']) ?></td></tr>
                        <?php endif; ?>
                        <tr>
                            <th>Berkas Terunggah</th>
                            <td>
                                <?php if (!$bk): ?>
                                    <span class="mut">Pendaftar belum mengunggah berkas.</span>
                                <?php else: ?>
                                    <?php foreach ($bk as $kunci => $b): ?>
                                        <div class="rasta" style="gap:8px;margin-bottom:6px">
                                            <?= ikon('unduh', 15) ?>
                                            <a href="<?= e($b['url'] ?? '#') ?>" target="_blank" rel="noopener"><?= e($b['nama'] ?? $kunci) ?></a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div>
                <h3>Penetapan Hasil Seleksi</h3>
                <form method="post" action="ppdb_pendaftar.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="simpan">
                    <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
                    <div class="form-baris">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <?php foreach ($STATUS as $k => $lb): ?>
                                <option value="<?= e($k) ?>" <?= $detail['status'] === $k ? 'selected' : '' ?>><?= e($lb) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-baris">
                        <label for="nilai_seleksi">Nilai Seleksi</label>
                        <input type="text" id="nilai_seleksi" name="nilai_seleksi" maxlength="20" value="<?= e($detail['nilai_seleksi']) ?>">
                    </div>
                    <div class="form-baris">
                        <label for="catatan_panitia">Keterangan / Alasan (tampil pada halaman cek status)</label>
                        <textarea id="catatan_panitia" name="catatan_panitia" maxlength="1000" style="min-height:100px"><?= e($detail['catatan_panitia']) ?></textarea>
                    </div>
                    <div class="form-baris">
                        <label class="rasta" style="gap:9px;font-weight:500">
                            <input type="checkbox" name="beritahu" value="1"> Tampilkan pengingat untuk menghubungi pendaftar
                        </label>
                    </div>
                    <div class="aksi-form">
                        <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Hasil Seleksi</button>
                        <?php if (pengaturan('ppdb_umumkan') === '1'): ?>
                            <a class="tombol tombol-garis" href="../ppdb/status" target="_blank" rel="noopener"><?= ikon('mata', 16) ?> Pengumuman Publik</a>
                        <?php else: ?>
                            <a class="tombol tombol-garis" href="ppdb.php">Aktifkan Pengumuman</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="kotak-info mt16" style="margin-top:16px">
                    Pesan WhatsApp cepat:
                    <a target="_blank" rel="noopener" href="https://wa.me/<?= e(wa_internasional((string) $detail['telepon'])) ?>?text=<?= rawurlencode('Assalamualaikum ' . $detail['nama'] . ', berikut hasil seleksi PPDB dengan nomor ' . $detail['nomor'] . '. Silakan periksa pada tautan cek status pendaftaran.') ?>">
                        <?= ikon('whatsapp', 15) ?> Hubungi <?= e($detail['nama']) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php admin_bawah(); ?>
