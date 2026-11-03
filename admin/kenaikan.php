<?php
/**
 * Kenaikan kelas & kelulusan siswa (beserta riwayat yang dapat dibatalkan).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$kelasAsal = (int) ($_POST['kelas_asal'] ?? $_GET['kelas_asal'] ?? 0);
$pesanProses = '';
$galatProses = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'batalkan') {
        $id = (int) ($_POST['id'] ?? 0);
        $k = one('SELECT * FROM kenaikan WHERE id = ?', [$id]);
        if (!$k || (int) $k['dibatalkan'] === 1) {
            flash('Riwayat tidak ditemukan atau sudah dibatalkan.', 'err');
        } else {
            $s = one('SELECT * FROM siswa WHERE id = ?', [(int) $k['siswa_id']]);
            if ($s) {
                if ($k['jenis'] === 'lulus') {
                    q("UPDATE siswa SET status = 'aktif', tahun_lulus = '' WHERE id = ?", [(int) $k['siswa_id']]);
                } else {
                    // kembalikan ke kelas asal bila kelas itu masih ada
                    $asal = one('SELECT id FROM kelas WHERE nama = ?', [(string) $k['kelas_asal']]);
                    q("UPDATE siswa SET kelas_id = ?, status = 'aktif' WHERE id = ?", [(int) ($asal['id'] ?? 0), (int) $k['siswa_id']]);
                }
                q('UPDATE kenaikan SET dibatalkan = 1 WHERE id = ?', [$id]);
                flash('Proses “' . e($k['jenis'] === 'lulus' ? 'kelulusan' : 'kenaikan kelas') . '” untuk ' . e($s['nama']) . ' dibatalkan.', 'ok');
            }
        }
        redirect('kenaikan.php?kelas_asal=' . (int) ($_POST['kelas_kembali'] ?? 0));
    }

    $pilih = array_map('intval', (array) ($_POST['siswa'] ?? []));
    $jenis = (string) ($_POST['jenis'] ?? 'naik');
    $tahun = (string) ($_POST['tahun_ajaran'] ?? '');
    $catatan = potong((string) ($_POST['catatan'] ?? ''), 300, '');
    // Keterangan alumni (kondisi setelah lulus) — hanya dipakai pada proses kelulusan.
    $ketAlumni = potong((string) ($_POST['keterangan_alumni'] ?? ''), 200, '');
    $tahunKeluar = trim((string) ($_POST['tahun_keluar'] ?? ''));

    if (!$pilih) {
        $galatProses = 'Pilih minimal satu siswa.';
    } elseif ($jenis === 'naik' && (int) ($_POST['kelas_tujuan'] ?? 0) <= 0) {
        $galatProses = 'Pilih kelas tujuan.';
    } elseif ($jenis === 'naik' && (int) $_POST['kelas_tujuan'] === $kelasAsal) {
        $galatProses = 'Kelas tujuan tidak boleh sama dengan kelas asal.';
    } else {
        $namaAsal = (string) (one('SELECT nama FROM kelas WHERE id = ?', [$kelasAsal])['nama'] ?? '');
        $jml = 0;
        $oleh = (string) (current_user()['nama'] ?? '');
        db()->exec('BEGIN IMMEDIATE');
        try {
            foreach ($pilih as $sid) {
                $s = one('SELECT * FROM siswa WHERE id = ? AND kelas_id = ? AND status = ?', [$sid, $kelasAsal, 'aktif']);
                if (!$s) {
                    continue;
                }
                if ($jenis === 'lulus') {
                    $tl = $tahunKeluar !== '' ? substr($tahunKeluar, 0, 4) : ($tahun !== '' ? substr($tahun, 0, 4) : date('Y'));
                    q("UPDATE siswa SET status = 'lulus', tahun_lulus = ?, keterangan = CASE WHEN ? <> '' THEN ? ELSE keterangan END WHERE id = ?",
                        [$tl, $ketAlumni, $ketAlumni, $sid]);
                } else {
                    $tujuan = (int) $_POST['kelas_tujuan'];
                    q("UPDATE siswa SET kelas_id = ?, status = 'aktif' WHERE id = ?", [$tujuan, $sid]);
                    $tl = '';
                }
                $namaTujuan = $jenis === 'lulus' ? 'Lulus' : (string) (one('SELECT nama FROM kelas WHERE id = ?', [(int) ($_POST['kelas_tujuan'] ?? 0)])['nama'] ?? '');
                q('INSERT INTO kenaikan (siswa_id, kelas_asal, kelas_tujuan, jenis, tahun_ajaran, catatan, oleh, waktu, dibatalkan)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)',
                    [$sid, $namaAsal, $namaTujuan, $jenis === 'lulus' ? 'lulus' : 'naik', $tahun, $catatan, $oleh, date('Y-m-d H:i:s')]);
                $jml++;
            }
            db()->exec('COMMIT');
        } catch (Throwable $e) {
            db()->exec('ROLLBACK');
            $galatProses = 'Proses gagal: ' . $e->getMessage();
        }
        if ($galatProses === '') {
            flash($jml . ' siswa berhasil diproses (' . ($jenis === 'lulus' ? 'diluluskan' : 'dinaikkan') . ').', 'ok');
        }
        redirect('kenaikan.php?kelas_asal=' . $kelasAsal);
    }
}

$kelas = all('SELECT k.*, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.status = ?) AS jml FROM kelas k ORDER BY k.urutan, k.id', ['aktif']);
$siswa = $kelasAsal > 0
    ? all("SELECT * FROM siswa WHERE kelas_id = ? AND status = 'aktif' ORDER BY nama", [$kelasAsal])
    : [];
$riwayat = all("SELECT r.*, s.nama AS siswa FROM kenaikan r LEFT JOIN siswa s ON s.id = r.siswa_id
                ORDER BY r.id DESC LIMIT 40");
$taSekolah = sek('tahun_ajaran') ?: (date('Y') . '/' . (date('Y') + 1));

admin_atas('Kenaikan & Kelulusan');
?>
<?php if ($galatProses !== ''): ?><div class="alert alert-err"><?= e($galatProses) ?></div><?php endif; ?>

<div class="kartu mb24">
    <h2>Kenaikan Kelas &amp; Kelulusan</h2>
    <p class="mut kecil">Pilih kelas asal, centang siswa yang diproses, lalu tentukan apakah mereka naik ke kelas berikutnya atau diluluskan. Siswa yang tidak dicentang tidak diubah (misalnya tinggal kelas).</p>

    <form class="saringan" method="get" action="kenaikan.php" id="formPilihKelas">
        <div class="medan">
            <label for="kelas_asal">Kelas Asal</label>
            <select id="kelas_asal" name="kelas_asal" data-auto>
                <option value="0">— pilih kelas —</option>
                <?php foreach ($kelas as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $kelasAsal === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?> (<?= (int) $k['jml'] ?> siswa aktif)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($kelasAsal > 0): ?>
        <form method="post" action="kenaikan.php" id="formKenaikan">
            <?= csrf_field() ?>
            <input type="hidden" name="kelas_asal" value="<?= (int) $kelasAsal ?>">
            <input type="hidden" name="kelas_kembali" value="<?= (int) $kelasAsal ?>">
            <?php if (!$siswa): ?>
                <div class="alert alert-info">Tidak ada siswa aktif pada kelas ini.</div>
            <?php else: ?>
                <div class="form-3">
                    <div class="form-baris">
                        <label for="jenis">Jenis Proses</label>
                        <select id="jenis" name="jenis">
                            <option value="naik">Naikkan ke kelas berikutnya</option>
                            <option value="lulus">Luluskan (alumni)</option>
                        </select>
                    </div>
                    <div class="form-baris">
                        <label for="kelas_tujuan">Kelas Tujuan (untuk proses naik)</label>
                        <select id="kelas_tujuan" name="kelas_tujuan">
                            <option value="0">— pilih kelas tujuan —</option>
                            <?php foreach ($kelas as $k): ?>
                                <?php if ((int) $k['id'] === $kelasAsal) { continue; } ?>
                                <option value="<?= (int) $k['id'] ?>"><?= e($k['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-baris">
                        <label for="tahun_ajaran">Tahun Ajaran</label>
                        <input type="text" id="tahun_ajaran" name="tahun_ajaran" maxlength="20" value="<?= e($taSekolah) ?>">
                    </div>
                </div>
                <div class="form-3">
                    <div class="form-baris">
                        <label for="tahun_keluar">Tahun Keluar (untuk proses Luluskan)</label>
                        <input type="text" id="tahun_keluar" name="tahun_keluar" maxlength="10" placeholder="<?= date('Y') ?>">
                        <div class="bantuan">Tampil sebagai “Tahun Keluar” di halaman Data Alumni.</div>
                    </div>
                    <div class="form-baris">
                        <label for="keterangan_alumni">Keterangan Setelah Lulus (opsional)</label>
                        <input type="text" id="keterangan_alumni" name="keterangan_alumni" maxlength="200"
                               placeholder="mis. Kuliah di Universitas Negeri Gorontalo">
                        <div class="bantuan">Boleh dikosongkan dan diisi menyusul pada Data Siswa.</div>
                    </div>
                    <div class="form-baris">
                        <label for="catatan">Catatan Proses</label>
                        <input type="text" id="catatan" name="catatan" maxlength="300" placeholder="mis. hasil rapat kenaikan kelas">
                    </div>
                </div>

                <div class="tabel-wrap">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th style="width:40px"><input type="checkbox" id="centangSemua" aria-label="Pilih semua"></th>
                                <th>Nama Siswa</th><th>NIS</th><th>JK</th><th>Kelas Sekarang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siswa as $s): ?>
                                <tr>
                                    <td><input type="checkbox" name="siswa[]" value="<?= (int) $s['id'] ?>" class="centang-siswa"></td>
                                    <td><strong><?= e($s['nama']) ?></strong></td>
                                    <td class="kecil"><?= e($s['nis'] ?: '-') ?></td>
                                    <td class="kecil"><?= $s['jenis_kelamin'] === 'P' ? 'P' : 'L' ?></td>
                                    <td class="kecil"><?= e((string) (one('SELECT nama FROM kelas WHERE id = ?', [(int) $s['kelas_id']])['nama'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="aksi-form">
                    <button type="submit" class="tombol" data-konfirmasi="Proses data siswa yang dicentang?"><?= ikon('naik', 17) ?> Proses Sekarang</button>
                    <span class="kecil mut"><?= count($siswa) ?> siswa aktif pada kelas ini.</span>
                </div>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <div class="kotak-info">Pilih kelas asal terlebih dahulu untuk menampilkan daftar siswa.</div>
    <?php endif; ?>
</div>

<div class="kartu">
    <h2>Riwayat Kenaikan &amp; Kelulusan</h2>
    <p class="mut kecil">Riwayat tersimpan sebagai jejak audit. Proses dapat dibatalkan sehingga kelas &amp; status siswa kembali seperti semula.</p>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Waktu</th><th>Nama Siswa</th><th>Jenis</th><th>Kelas Asal</th><th>Kelas Tujuan</th><th>Tahun Ajaran</th><th>Oleh</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$riwayat): ?><tr><td colspan="9" class="mut">Belum ada riwayat.</td></tr><?php endif; ?>
                <?php foreach ($riwayat as $r): ?>
                    <tr>
                        <td class="kecil"><?= e(waktu_id($r['waktu'])) ?></td>
                        <td><?= e($r['siswa'] ?: '(siswa dihapus)') ?></td>
                        <td class="kecil"><?= $r['jenis'] === 'lulus' ? 'Kelulusan' : 'Kenaikan kelas' ?></td>
                        <td class="kecil"><?= e($r['kelas_asal']) ?></td>
                        <td class="kecil"><?= e($r['kelas_tujuan']) ?></td>
                        <td class="kecil"><?= e($r['tahun_ajaran'] ?: '-') ?></td>
                        <td class="kecil"><?= e($r['oleh'] ?: '-') ?></td>
                        <td><?= ((int) $r['dibatalkan'] === 1) ? '<span class="pil pil-batal">Dibatalkan</span>' : '<span class="pil pil-terima">Berlaku</span>' ?></td>
                        <td class="aksi-sel">
                            <?php if ((int) $r['dibatalkan'] === 0): ?>
                                <form method="post" style="display:inline" data-konfirmasi="Batalkan proses ini? Kelas &amp; status siswa akan dikembalikan.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="batalkan">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="kelas_kembali" value="<?= (int) $kelasAsal ?>">
                                    <button type="submit" class="tombol-ikon bahaya" title="Batalkan"><?= ikon('tutup', 15) ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
  var semua = document.getElementById('centangSemua');
  if (semua) {
    semua.addEventListener('change', function () {
      document.querySelectorAll('.centang-siswa').forEach(function (c) { c.checked = semua.checked; });
    });
  }
  var jenis = document.getElementById('jenis');
  var tujuan = document.getElementById('kelas_tujuan');
  if (jenis && tujuan) {
    var atur = function () {
      var lulus = jenis.value === 'lulus';
      tujuan.disabled = lulus;
      tujuan.closest('.form-baris').style.opacity = lulus ? '.55' : '1';
    };
    jenis.addEventListener('change', atur);
    atur();
  }
})();
</script>
<?php admin_bawah(); ?>
