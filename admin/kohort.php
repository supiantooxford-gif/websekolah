<?php
/**
 * KOHORT SISWA — panel Super Admin (isian tabel analisis kohort).
 *
 * Permintaan pemilik: *"Pada bagian Kohort Siswa isi dengan tabel seperti pada contoh, dibawah tabel
 * tambahkan analisis grafiknya berdasarkan tabel tersebut (yang di tampilkan adalah 5 tahun terakhir.
 * Ketika di input tahun ajaran baru otomatis tahun ajaran paling lama akan hilang dan posisi bergeser.
 * tabel di isi oleh super admin di halaman super admin."*
 *
 * Halaman ini mengisi tabel `kohort` (satu baris = satu tahun ajaran) dengan kolom persis seperti
 * contoh pemilik: PMB (Pendaftar, Diterima) dan Kelas X/XI/XII (Jumlah, Mutasi Masuk, Mutasi Keluar,
 * Tinggal Kelas, Naik Kelas, Drop Out). Halaman publiknya (`inc/kohort.php`) menampilkan **5 tahun
 * ajaran terbaru** + analisis grafiknya; karena itu tahun yang sudah di luar 5 tahun terbaru tetap
 * TERSIMPAN di sini (sebagai arsip) dan ditandai "arsip (tidak tampil)".
 *
 * Penjaga: **`require_kelola_kohort()`** — Administrator (Super Admin) **dan akun Editor**
 * (permintaan pemilik v1.14.0: *"Akun Editor (Yelfis Djamaludin) berikan akses untuk menginput
 * Kohort Siswa"*); akun peran lain (mis. "Editor Berita") tetap **403**.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/kohort.php';

require_kelola_kohort();

/** Ambil & bersihkan satu angka dari kiriman formulir (kosong = 0, dijepit 0…KOHORT_NILAI_MAKS). */
function kohort_input(string $kunci): int
{
    $v = trim((string) ($_POST[$kunci] ?? ''));
    if ($v === '') {
        return 0;
    }
    $v = str_replace(['.', ' '], '', $v);
    if (!is_numeric($v)) {
        return 0;
    }
    return max(0, min((int) $v, (int) KOHORT_NILAI_MAKS));
}

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

// =====================================================================================
// AKSI
// =====================================================================================
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $ta = kohort_ta_norm((string) ($_POST['ta'] ?? ''));
    if ($ta === '' || !kohort_ta_sah($ta)) {
        flash('Tahun ajaran wajib diisi dengan dua tahun berurutan, mis. 2025/2026.', 'err');
        redirect('kohort.php' . ($id > 0 ? '?edit=' . $id : '') . '#formKohort');
    }
    $kembar = one('SELECT * FROM kohort WHERE ta = ? AND id <> ?', [$ta, $id]);
    if ($kembar) {
        flash('Tahun ajaran ' . $ta . ' sudah ada pada tabel kohort — ubah baris itu, bukan menambah baru.', 'err');
        redirect('kohort.php?edit=' . (int) $kembar['id'] . '#formKohort');
    }
    $nilai = [];
    foreach (kohort_kolom() as $kol) {
        $nilai[$kol['kunci']] = kohort_input($kol['kunci']);
    }
    $kini = date('Y-m-d H:i:s');
    if ($id > 0) {
        $set = implode(', ', array_map(static fn (string $k): string => $k . ' = ?', array_keys($nilai)));
        $param = array_merge([$ta], array_values($nilai), [$kini, $id]);
        q('UPDATE kohort SET ta = ?, ' . $set . ', diubah = ? WHERE id = ?', $param);
        flash('Data kohort tahun ajaran ' . $ta . ' disimpan.', 'ok');
    } else {
        $kolom = array_keys($nilai);
        q('INSERT INTO kohort (ta, ' . implode(', ', $kolom) . ', dibuat, diubah) VALUES (?, '
            . implode(', ', array_fill(0, count($kolom), '?')) . ', ?, ?)',
            array_merge([$ta], array_values($nilai), [$kini, $kini]));
        flash('Data kohort tahun ajaran ' . $ta . ' ditambahkan.', 'ok');
    }
    // Keterangan pergeseran: tahun paling lama otomatis tidak tampil bila sudah lebih dari batas.
    $semua = kohort_semua();
    if (count($semua) > (int) KOHORT_TAHUN_TAMPIL) {
        $luar = array_slice($semua, 0, count($semua) - (int) KOHORT_TAHUN_TAMPIL);
        $nama = array_map(static fn (array $r): string => (string) $r['ta'], $luar);
        flash('Halaman publik menampilkan ' . (int) KOHORT_TAHUN_TAMPIL . ' tahun terbaru; tahun '
            . implode(', ', $nama) . ' kini tersimpan sebagai arsip (tidak tampil).', 'info');
    }
    redirect('kohort.php');
}

if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $row = kohort_by_id($id);
    if (!$row) {
        flash('Baris kohort tidak ditemukan.', 'err');
        redirect('kohort.php');
    }
    q('DELETE FROM kohort WHERE id = ?', [$id]);
    flash('Data kohort tahun ajaran ' . kohort_label((string) $row['ta']) . ' dihapus.', 'ok');
    redirect('kohort.php');
}

$edit = kohort_by_id((int) ($_GET['edit'] ?? 0));
$daftar = kohort_semua();
$tampil = kohort_tampil();
$idTampil = array_map(static fn (array $r): int => (int) $r['id'], $tampil);

admin_atas('Kohort Siswa', 'kohort.php');
?>
<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2>Analisis Kohort Siswa</h2>
            <p class="mut kecil mb0">
                Isi tabel kohort untuk setiap tahun ajaran: <b>PMB</b> (pendaftar &amp; diterima) serta
                <b>Kelas X, XI, XII</b> (jumlah siswa, mutasi masuk/keluar, tinggal kelas, naik kelas,
                dan drop out). Halaman publik <b>Siswa → Kohort Siswa</b> menampilkan
                <b><?= (int) KOHORT_TAHUN_TAMPIL ?> tahun ajaran terbaru</b> beserta analisis grafiknya —
                saat tahun ajaran baru diisi, tahun paling lama otomatis keluar dari tabel di website
                (posisi bergeser) dan tetap tersimpan di sini sebagai arsip.
            </p>
        </div>
        <span class="pil pil-terima"><?= angka(count($daftar)) ?> tahun tersimpan</span>
    </div>
    <div class="alert alert-info">
        <?= ikon('info', 18) ?>
        <div>
            Tahun ajaran yang <b>tampil di website</b>:
            <?php if ($tampil): ?>
                <b><?= e(kohort_label((string) $tampil[0]['ta'])) ?></b> s.d.
                <b><?= e(kohort_label((string) $tampil[count($tampil) - 1]['ta'])) ?></b>
                (<?= angka(count($tampil)) ?> tahun terakhir).
            <?php else: ?>
                belum ada — isi pada formulir di samping.
            <?php endif; ?>
            Tahun yang lebih lama tetap tersimpan dan dapat ditampilkan lagi bila tahun yang lebih baru
            dihapus.
        </div>
    </div>
</div>

<div class="grid g21 mt24">
    <div class="kartu">
        <div class="kartu-kepala"><div><h2>Tabel Kohort Tersimpan</h2></div></div>
        <div class="tabel-wrap">
            <table class="tabel" id="tblKohortAdmin">
                <thead><tr>
                    <th>Tahun Ajaran</th>
                    <th class="teks-kanan">Pendaftar</th>
                    <th class="teks-kanan">Diterima</th>
                    <th class="teks-kanan">Jml X</th>
                    <th class="teks-kanan">Jml XI</th>
                    <th class="teks-kanan">Jml XII</th>
                    <th class="teks-kanan">Drop Out</th>
                    <th class="teks-kanan">Lulus</th>
                    <th class="teks-kanan">Kuliah</th>
                    <th class="teks-kanan">Bekerja</th>
                    <th class="teks-kanan">Lainnya</th>
                    <th>Status</th>
                    <th class="aksi-sel">Aksi</th>
                </tr></thead>
                <tbody>
                <?php if (!$daftar): ?>
                    <tr><td colspan="13" class="mut">Belum ada data kohort. Isi tahun ajaran pertama pada
                        formulir di samping — halaman publik baru menampilkan tabel setelah ada isinya.</td></tr>
                <?php endif; ?>
                <?php foreach ($daftar as $r): ?>
                    <?php
                    $tampilIni = in_array((int) $r['id'], $idTampil, true);
                    $drop = kohort_angka($r, 'x_dropout') + kohort_angka($r, 'xi_dropout') + kohort_angka($r, 'xii_dropout');
                    ?>
                    <tr>
                        <td><strong><?= e(kohort_label((string) $r['ta'])) ?></strong></td>
                        <td class="teks-kanan"><?= angka(kohort_angka($r, 'pmb_pendaftar')) ?></td>
                        <td class="teks-kanan"><?= angka(kohort_angka($r, 'pmb_diterima')) ?></td>
                        <td class="teks-kanan"><?= angka(kohort_angka($r, 'x_jumlah')) ?></td>
                        <td class="teks-kanan"><?= angka(kohort_angka($r, 'xi_jumlah')) ?></td>
                        <td class="teks-kanan"><?= angka(kohort_angka($r, 'xii_jumlah')) ?></td>
                        <td class="teks-kanan"><?= angka($drop) ?></td>
                        <td class="teks-kanan"><?= angka(kohort_angka($r, 'lulus_jumlah')) ?></td>
                        <td class="teks-kanan"><?= angka(kohort_angka($r, 'lulus_kuliah')) ?></td>
                        <td class="teks-kanan"><?= angka(kohort_angka($r, 'lulus_bekerja')) ?></td>
                        <td class="teks-kanan"><?= angka(kohort_angka($r, 'lulus_lainnya')) ?></td>
                        <td>
                            <?php if ($tampilIni): ?>
                                <span class="pil pil-terima">Tampil di website</span>
                            <?php else: ?>
                                <span class="pil pil-baru">Arsip (tidak tampil)</span>
                            <?php endif; ?>
                        </td>
                        <td class="aksi-sel">
                            <?= tombol_mini('kohort.php?edit=' . (int) $r['id'] . '#formKohort', 'edit', 'Ubah') ?>
                            <?= form_hapus('hapus', (int) $r['id'],
                                'Hapus data kohort tahun ajaran ' . kohort_label((string) $r['ta']) . '?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="kartu" id="formKohort">
        <h2><?= $edit ? 'Ubah Kohort ' . e(kohort_label((string) $edit['ta'])) : 'Tambah Tahun Ajaran' ?></h2>
        <form method="post" action="kohort.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-baris">
                <label for="ta">Tahun Ajaran <span class="wajib">*</span></label>
                <input type="text" id="ta" name="ta" required maxlength="<?= (int) KOHORT_TA_MAKS ?>"
                       placeholder="2025/2026" value="<?= e((string) ($edit['ta'] ?? '')) ?>">
                <div class="bantuan">Dua tahun berurutan, mis. <b>2025/2026</b>. Satu tahun ajaran hanya
                    boleh ada satu baris.</div>
            </div>

            <h3 class="kohort-form-judul">PMB</h3>
            <div class="grid g2">
                <?php foreach (['pmb_pendaftar' => 'Pendaftar', 'pmb_diterima' => 'Diterima'] as $kunci => $label): ?>
                    <div class="form-baris">
                        <label for="<?= e($kunci) ?>"><?= e($label) ?></label>
                        <input type="number" id="<?= e($kunci) ?>" name="<?= e($kunci) ?>" min="0"
                               max="<?= (int) KOHORT_NILAI_MAKS ?>" step="1"
                               value="<?= (int) ($edit[$kunci] ?? 0) ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <?php foreach (KOHORT_TINGKAT as $kode => $labelTingkat): ?>
                <h3 class="kohort-form-judul"><?= e($labelTingkat) ?></h3>
                <div class="grid g2">
                    <?php foreach (KOHORT_KOLOM_TINGKAT as $kol => $labelKol): ?>
                        <?php $kunci = $kode . '_' . $kol; ?>
                        <div class="form-baris">
                            <label for="<?= e($kunci) ?>"><?= e($labelKol) ?></label>
                            <input type="number" id="<?= e($kunci) ?>" name="<?= e($kunci) ?>" min="0"
                                   max="<?= (int) KOHORT_NILAI_MAKS ?>" step="1"
                                   value="<?= (int) ($edit[$kunci] ?? 0) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php /* Grup LULUS (v1.14.0 — permintaan pemilik: tabel ditambah kolom Kelulusan). */ ?>
            <h3 class="kohort-form-judul">Lulus</h3>
            <div class="grid g2">
                <?php foreach (KOHORT_KOLOM_LULUS as $kol => $labelKol): ?>
                    <?php $kunci = 'lulus_' . $kol; ?>
                    <div class="form-baris">
                        <label for="<?= e($kunci) ?>"><?= e($labelKol) ?></label>
                        <input type="number" id="<?= e($kunci) ?>" name="<?= e($kunci) ?>" min="0"
                               max="<?= (int) KOHORT_NILAI_MAKS ?>" step="1"
                               value="<?= (int) ($edit[$kunci] ?? 0) ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('check', 17) ?> Simpan Data Kohort</button>
                <?php if ($edit): ?>
                    <a class="tombol tombol-garis" href="kohort.php">Batal Ubah</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php
admin_bawah();
