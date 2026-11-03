<?php
/**
 * Log masuk panel admin (berhasil maupun gagal) — hanya administrator.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_admin();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'bersihkan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $mode = (string) ($_POST['mode'] ?? 'lama');
    if ($mode === 'semua') {
        $n = jumlah('SELECT COUNT(*) FROM log_masuk');
        q('DELETE FROM log_masuk');
        flash('Seluruh riwayat log (' . angka($n) . ' baris) dihapus.', 'ok');
    } else {
        $hari = max(1, (int) ($_POST['hari'] ?? 30));
        $batas = date('Y-m-d H:i:s', time() - $hari * 86400);
        $n = jumlah('SELECT COUNT(*) FROM log_masuk WHERE waktu < ?', [$batas]);
        q('DELETE FROM log_masuk WHERE waktu < ?', [$batas]);
        flash($n > 0
            ? angka($n) . ' baris log lebih lama dari ' . $hari . ' hari dihapus.'
            : 'Tidak ada baris log yang lebih lama dari ' . $hari . ' hari (' . angka(jumlah('SELECT COUNT(*) FROM log_masuk')) . ' baris tersimpan, paling lama ' . e(waktu_id(nilai('SELECT MIN(waktu) FROM log_masuk')) ?: '-') . ').', 'info');
    }
    redirect('log.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('log_masuk', (int) ($_POST['id'] ?? 0));
    flash('Satu baris log dihapus.', 'ok');
    redirect('log.php');
}

$saring = (string) ($_GET['saring'] ?? '');
$cari = trim((string) ($_GET['cari'] ?? ''));
$w = ['1 = 1'];
$p = [];
if ($saring === 'gagal') {
    $w[] = 'berhasil = 0';
} elseif ($saring === 'masuk') {
    $w[] = "berhasil = 1 AND keterangan = ''";
} elseif ($saring === 'keluar') {
    $w[] = "berhasil = 1 AND keterangan = 'keluar'";
}
if ($cari !== '') {
    $w[] = '(username LIKE ? OR nama LIKE ? OR ip LIKE ?)';
    array_push($p, '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%');
}
$where = 'WHERE ' . implode(' AND ', $w);
$perHal = 25;
$hal = halaman_ini();
$total = jumlah("SELECT COUNT(*) FROM log_masuk $where", $p);
$daftar = all("SELECT * FROM log_masuk $where ORDER BY id DESC LIMIT $perHal OFFSET " . (($hal - 1) * $perHal), $p);
$terlama = nilai('SELECT MIN(waktu) FROM log_masuk');
$hit = [
    'total' => jumlah('SELECT COUNT(*) FROM log_masuk'),
    'gagal' => jumlah('SELECT COUNT(*) FROM log_masuk WHERE berhasil = 0'),
    'hari'  => jumlah("SELECT COUNT(*) FROM log_masuk WHERE waktu >= date('now','localtime')"),
];
// Penyaring didahulukan SEBELUM blok aksi di atas agar tidak ada variabel yang dipakai lebih awal.

admin_atas('Log Masuk');
?>
<div class="grid g4">
    <?php
    kartu_stat('Total Baris Log', angka($hit['total']), 'grafik');
    kartu_stat('Percobaan Gagal', angka($hit['gagal']), 'info');
    kartu_stat('Aktivitas Hari Ini', angka($hit['hari']), 'clipboard');
    kartu_stat('Riwayat Terlama', $terlama ? tanggal_id((string) $terlama) : '-', 'jam');
    ?>
</div>

<div class="kartu mt24" style="margin-top:22px">
    <div class="kartu-kepala">
        <div>
            <h2>Riwayat Masuk &amp; Keluar</h2>
            <p class="mut kecil mb0"><?= angka($total) ?> baris sesuai saringan. Riwayat gagal masuk berguna untuk mendeteksi percobaan peretasan.</p>
        </div>
    </div>

    <form class="saringan" method="get" action="log.php">
        <div class="medan">
            <label for="cari">Cari nama pengguna / nama / IP</label>
            <input type="search" id="cari" name="cari" value="<?= e($cari) ?>">
        </div>
        <div class="medan">
            <label for="saring">Jenis aktivitas</label>
            <select id="saring" name="saring" data-auto>
                <option value="">Semua</option>
                <option value="masuk" <?= $saring === 'masuk' ? 'selected' : '' ?>>Berhasil masuk</option>
                <option value="keluar" <?= $saring === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                <option value="gagal" <?= $saring === 'gagal' ? 'selected' : '' ?>>Gagal masuk</option>
            </select>
        </div>
        <button type="submit" class="tombol tombol-kecil"><?= ikon('search', 15) ?> Terapkan</button>
        <a class="tombol tombol-garis tombol-kecil" href="log.php">Reset</a>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Waktu</th><th>Nama Pengguna</th><th>Nama</th><th>Aktivitas</th><th>Keterangan</th><th>IP</th><th>Peramban</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$daftar): ?><tr><td colspan="8" class="mut">Belum ada riwayat.</td></tr><?php endif; ?>
                <?php foreach ($daftar as $l): ?>
                    <tr>
                        <td class="kecil"><?= e(waktu_id($l['waktu'])) ?></td>
                        <td class="kecil"><?= e($l['username']) ?></td>
                        <td class="kecil"><?= e($l['nama'] ?: '-') ?></td>
                        <td>
                            <?php if ((int) $l['berhasil'] === 0): ?>
                                <span class="pil pil-tolak">Gagal</span>
                            <?php elseif ($l['keterangan'] === 'keluar'): ?>
                                <span class="pil pil-baru">Keluar</span>
                            <?php else: ?>
                                <span class="pil pil-terima">Masuk</span>
                            <?php endif; ?>
                        </td>
                        <td class="kecil mut"><?= e($l['keterangan'] ?: '-') ?></td>
                        <td class="kecil"><?= e($l['ip']) ?></td>
                        <td class="kecil"><?= e(potong($l['peramban'], 46)) ?></td>
                        <td class="aksi-sel"><?= form_hapus('hapus', (int) $l['id'], 'Hapus satu baris log ini?') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi($total, $perHal, $hal) ?>
</div>

<div class="kartu mt24">
    <h2>Bersihkan Riwayat Log</h2>
    <p class="mut kecil">Pilih batas umur riwayat yang ingin dihapus, atau kosongkan seluruh riwayat.</p>
    <form method="post" action="log.php" data-konfirmasi="Hapus riwayat log yang dipilih?">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="bersihkan">
        <div class="form-2">
            <div class="form-baris">
                <label for="hari">Hapus log lebih lama dari (hari)</label>
                <input type="number" id="hari" name="hari" min="1" max="3650" value="30">
            </div>
            <div class="form-baris">
                <label for="mode">Pilihan</label>
                <select id="mode" name="mode">
                    <option value="lama">Hapus yang lebih lama dari jumlah hari di atas</option>
                    <option value="semua">Hapus SELURUH riwayat log</option>
                </select>
            </div>
        </div>
        <div class="aksi-form"><button type="submit" class="tombol tombol-bahaya"><?= ikon('trash', 17) ?> Bersihkan Log</button></div>
    </form>
</div>
<?php admin_bawah(); ?>
