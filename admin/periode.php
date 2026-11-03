<?php
/**
 * TAHUN AJARAN & SEMESTER — panel Super Admin (menu "Tahun Ajaran & Semester").
 *
 * Permintaan pemilik: *"Buat menu baru di Super Admin (tambah/ubah tahun ajaran & semester)"* —
 * dipakai oleh menu **Kurikulum → Perangkat Guru**, karena *"perangkat pembelajaran itu per semester"*.
 *
 * Halaman ini mengatur tabel **`periode`** (satu baris = satu semester pada satu tahun ajaran,
 * mis. "2026/2027 · Ganjil"). Satu periode ditandai **"Periode Berjalan"**: itulah yang dibuka lebih
 * dulu oleh halaman publik Perangkat Guru dan yang dipakai sebagai pilihan awal pada panelnya.
 *
 * Urutan & keaktifan mengikuti pola halaman lain di aplikasi ini (tombol ▲▼ + Aktif/Nonaktif), dan
 * penghapusan diperingatkan dengan jumlah berkas perangkat yang memakai periode itu.
 *
 * ⚠️ Formulir "Tambah" mengirim beberapa semester sekaligus (`semester[]` centang Ganjil/Genap +
 * isian "semester lain") supaya satu tahun ajaran cukup ditambahkan sekali.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/perangkat.php';
require_login();

require_admin();   // halaman ini khusus Administrator (Super Admin)

/** Jumlah baris berkas perangkat yang memakai satu periode. */
function periode_berkas_jumlah(string $ta, string $semester): int
{
    if ($ta === '' && $semester === '') {
        return 0;
    }
    return jumlah('SELECT COUNT(*) FROM perangkat_kelas WHERE ta = ? AND semester = ?', [$ta, $semester]);
}

/** Jumlah binaan judul periode untuk pesan (mis. "2026/2027 · Ganjil"). */
function periode_label(string $ta, string $semester): string
{
    return perangkat_periode_label($ta, $semester);
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
    $ta = perangkat_ta_norm((string) ($_POST['ta'] ?? ''));
    $semester = perangkat_semester_norm((string) ($_POST['semester'] ?? ''));
    if ($ta === '' || $semester === '') {
        flash('Tahun ajaran dan semester wajib diisi.', 'err');
        redirect('periode.php' . ($id > 0 ? '?edit=' . $id : '') . '#formPeriode');
    }
    if (!perangkat_ta_sah($ta)) {
        flash('Tahun ajaran harus berbentuk dua tahun berurutan, mis. 2026/2027.', 'err');
        redirect('periode.php' . ($id > 0 ? '?edit=' . $id : '') . '#formPeriode');
    }
    $kembar = one('SELECT * FROM periode WHERE ta = ? AND semester = ? AND id <> ?', [$ta, $semester, $id]);
    if ($kembar) {
        flash('Periode ' . periode_label($ta, $semester) . ' sudah ada pada daftar.', 'err');
        redirect('periode.php#formPeriode');
    }
    $bawaan = empty($_POST['bawaan']) ? 0 : 1;
    $kini = date('Y-m-d H:i:s');
    if ($id > 0) {
        q('UPDATE periode SET ta = ?, semester = ?, aktif = ?, diubah = ? WHERE id = ?',
            [$ta, $semester, empty($_POST['aktif']) ? 0 : 1, $kini, $id]);
        if ($bawaan === 1) {
            q('UPDATE periode SET bawaan = 0');
            q('UPDATE periode SET bawaan = 1 WHERE id = ?', [$id]);
        }
        flash('Periode ' . periode_label($ta, $semester) . ' disimpan.', 'ok');
    } else {
        $urut = urutan_baru('periode');
        q('INSERT INTO periode (ta, semester, urutan, bawaan, aktif, dibuat, diubah) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$ta, $semester, $urut, $bawaan, empty($_POST['aktif']) ? 0 : 1, $kini, $kini]);
        if ($bawaan === 1) {
            $baru = (int) db()->lastInsertId();
            q('UPDATE periode SET bawaan = 0');
            q('UPDATE periode SET bawaan = 1 WHERE id = ?', [$baru]);
        }
        // Bila ini periode PERTAMA, jadikan periode berjalan supaya halaman publik tidak kosong.
        if (jumlah('SELECT COUNT(*) FROM periode') === 1) {
            q('UPDATE periode SET bawaan = 1');
        }
        flash('Periode ' . periode_label($ta, $semester) . ' ditambahkan.', 'ok');
    }
    redirect('periode.php');
}

if ($aksi === 'tambah_ta') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    // Tambah satu TAHUN AJARAN sekaligus beberapa semester (centang Ganjil/Genap + semester lain).
    $ta = perangkat_ta_norm((string) ($_POST['ta_baru'] ?? ''));
    $semester = [];
    foreach ((array) ($_POST['semester'] ?? []) as $sm) {
        $sm = perangkat_semester_norm((string) $sm);
        if ($sm !== '' && !in_array($sm, $semester, true)) {
            $semester[] = $sm;
        }
    }
    foreach (preg_split('/[,;|]+/u', (string) ($_POST['semester_lain'] ?? '')) ?: [] as $sm) {
        $sm = perangkat_semester_norm($sm);
        if ($sm !== '' && !in_array($sm, $semester, true)) {
            $semester[] = $sm;
        }
    }
    if ($ta === '' || !perangkat_ta_sah($ta)) {
        flash('Tahun ajaran harus berbentuk dua tahun berurutan, mis. 2026/2027.', 'err');
        redirect('periode.php#formTambah');
    }
    if (!$semester) {
        flash('Pilih minimal satu semester untuk tahun ajaran itu.', 'err');
        redirect('periode.php#formTambah');
    }
    $kini = date('Y-m-d H:i:s');
    $masuk = 0;
    $lewat = [];
    foreach ($semester as $sm) {
        if (one('SELECT id FROM periode WHERE ta = ? AND semester = ?', [$ta, $sm])) {
            $lewat[] = $sm;
            continue;
        }
        q('INSERT INTO periode (ta, semester, urutan, bawaan, aktif, dibuat, diubah) VALUES (?, ?, ?, 0, 1, ?, ?)',
            [$ta, $sm, urutan_baru('periode'), $kini, $kini]);
        $masuk++;
    }
    // Periode pertama yang masuk → jadikan periode berjalan bila belum ada yang bertanda `bawaan`.
    if (jumlah('SELECT COUNT(*) FROM periode WHERE bawaan = 1') === 0) {
        q('UPDATE periode SET bawaan = 1 WHERE id = (SELECT id FROM periode ORDER BY urutan, id LIMIT 1)');
    }
    $pesan = $masuk > 0
        ? 'Tahun ajaran ' . $ta . ': ' . $masuk . ' semester ditambahkan'
          . ($lewat ? ' (' . implode(', ', $lewat) . ' sudah ada, dilewati).' : '.')
        : 'Seluruh semester tahun ajaran ' . $ta . ' sudah ada pada daftar.';
    flash($pesan, $masuk > 0 ? 'ok' : 'err');
    redirect('periode.php');
}

if ($aksi === 'bawaan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $row = one('SELECT * FROM periode WHERE id = ?', [$id]);
    if (!$row) {
        flash('Periode tidak ditemukan.', 'err');
        redirect('periode.php');
    }
    q('UPDATE periode SET bawaan = 0');
    q('UPDATE periode SET bawaan = 1, aktif = 1 WHERE id = ?', [$id]);
    flash('Periode berjalan disetel ke ' . periode_label((string) $row['ta'], (string) $row['semester']) . '.', 'ok');
    redirect('periode.php');
}

if ($aksi === 'toggle') {
    q('UPDATE periode SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('periode.php');
}

if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan('periode', (int) ($_GET['id'] ?? 0), $aksi);
    redirect('periode.php');
}

if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $row = one('SELECT * FROM periode WHERE id = ?', [$id]);
    if (!$row) {
        flash('Periode tidak ditemukan.', 'err');
        redirect('periode.php');
    }
    $n = periode_berkas_jumlah((string) $row['ta'], (string) $row['semester']);
    if (empty($_POST['setuju'])) {
        // Tanpa persetujuan: berkas perangkat periode itu DIKOSONGKAN dulu supaya tidak tersisa
        // tanpa induknya (tidak ada berkas yang menggantung).
        q('DELETE FROM perangkat_kelas WHERE ta = ? AND semester = ?', [(string) $row['ta'], (string) $row['semester']]);
    }
    q('DELETE FROM periode WHERE id = ?', [$id]);
    // Pastikan selalu ada periode berjalan.
    if (jumlah('SELECT COUNT(*) FROM periode WHERE bawaan = 1') === 0 && jumlah('SELECT COUNT(*) FROM periode') > 0) {
        q('UPDATE periode SET bawaan = 1 WHERE id = (SELECT id FROM periode ORDER BY urutan, id LIMIT 1)');
    }
    flash('Periode ' . periode_label((string) $row['ta'], (string) $row['semester']) . ' dihapus'
        . ($n > 0 ? ' beserta ' . $n . ' baris berkas perangkatnya.' : '.'), 'ok');
    redirect('periode.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM periode WHERE id = ?', [(int) $_GET['edit']]) : null;
$daftar = perangkat_periode_list(false);
$bawaan = perangkat_periode_bawaan();

admin_atas('Tahun Ajaran & Semester', 'periode.php');
?>
<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2>Tahun Ajaran &amp; Semester</h2>
            <p class="mut kecil mb0">
                Daftar periode dipakai menu <b>Kurikulum → Perangkat Guru</b>: berkas perangkat
                pembelajaran (CP, ATP, RPP/Modul Ajar) dipisah <b>per semester</b>, sehingga satu mata
                pelajaran &amp; kelas boleh punya berkas berbeda pada tiap semester.
                Tandai satu periode sebagai <b>Periode Berjalan</b> — itulah yang dibuka lebih dulu
                pengunjung dan yang dipakai sebagai pilihan awal pada panel Perangkat Guru.
            </p>
        </div>
        <span class="pil pil-terima"><?= angka(count($daftar)) ?> periode</span>
    </div>
    <div class="alert alert-info">
        <?= ikon('info', 18) ?>
        <div>
            Periode berjalan saat ini:
            <b><?= e(perangkat_periode_label($bawaan['ta'], $bawaan['semester'])) ?></b>.
            Contoh penulisan: Tahun Ajaran <b>2026/2027</b> &amp; semester <b>Ganjil</b> atau
            <b>Genap</b> (boleh ditulis sendiri, mis. <i>Antara</i>).
        </div>
    </div>
</div>

<div class="grid g21 mt24">
    <div class="kartu">
        <div class="kartu-kepala">
            <div><h2>Daftar Periode</h2></div>
        </div>
        <div class="tabel-wrap">
            <table class="tabel" id="tblPeriode">
                <thead><tr>
                    <th style="width:48px">Urut</th>
                    <th>Tahun Ajaran</th>
                    <th>Semester</th>
                    <th>Berkas Perangkat</th>
                    <th>Status</th>
                    <th class="aksi-sel">Aksi</th>
                </tr></thead>
                <tbody>
                <?php if (!$daftar): ?>
                    <tr><td colspan="6" class="mut">Belum ada periode. Tambahkan tahun ajaran pada kartu
                        di samping supaya halaman Perangkat Guru dapat dibuka per semester.</td></tr>
                <?php endif; ?>
                <?php foreach ($daftar as $r): $n = periode_berkas_jumlah((string) $r['ta'], (string) $r['semester']); ?>
                    <tr>
                        <td class="aksi-sel"><?= tombol_urut('periode.php', (int) $r['id']) ?></td>
                        <td><strong>Tahun Ajaran <?= e((string) $r['ta']) ?></strong></td>
                        <td>Semester <?= e((string) $r['semester']) ?></td>
                        <td><?= angka($n) ?> baris berkas</td>
                        <td>
                            <?php if ((int) $r['aktif'] === 1): ?>
                                <span class="pil pil-terima">Aktif</span>
                            <?php else: ?>
                                <span class="pil pil-baru">Nonaktif</span>
                            <?php endif; ?>
                            <?php if ((int) $r['bawaan'] === 1): ?>
                                <span class="pil pil-baru">Periode Berjalan</span>
                            <?php endif; ?>
                        </td>
                        <td class="aksi-sel">
                            <?= tombol_mini('periode.php?edit=' . (int) $r['id'] . '#formPeriode', 'edit', 'Ubah') ?>
                            <?php if ((int) $r['bawaan'] !== 1): ?>
                                <?php /* Jadikan periode berjalan (POST + CSRF + konfirmasi). */ ?>
                                <form method="post" style="display:inline"
                                      data-konfirmasi="Jadikan periode ini sebagai PERIODE BERJALAN? Halaman Perangkat Guru akan membukanya lebih dulu.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="bawaan">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="tombol-ikon" title="Jadikan periode berjalan">
                                        <?= ikon('check', 15) ?></button>
                                </form>
                            <?php endif; ?>
                            <?= tombol_mini('periode.php?aksi=toggle&id=' . (int) $r['id'], 'mata', 'Aktifkan/nonaktifkan') ?>
                            <?= form_hapus('hapus', (int) $r['id'],
                                'Hapus ' . periode_label((string) $r['ta'], (string) $r['semester'])
                                . ($n > 0 ? ' beserta ' . $n . ' baris berkas perangkatnya' : '') . '?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="kartu" id="formTambah">
            <h2>Tambah Tahun Ajaran</h2>
            <form method="post" action="periode.php">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="tambah_ta">
                <div class="form-baris">
                    <label for="ta_baru">Tahun Ajaran <span class="wajib">*</span></label>
                    <input type="text" id="ta_baru" name="ta_baru" required maxlength="<?= (int) PERANGKAT_TA_MAKS ?>"
                           placeholder="2026/2027" value="">
                </div>
                <div class="form-baris">
                    <label>Semester</label>
                    <?php foreach ((array) PERANGKAT_SEMESTER as $i => $sm): ?>
                        <label class="rasta" style="gap:9px;font-weight:500">
                            <input type="checkbox" name="semester[]" value="<?= e($sm) ?>"<?= $i === 0 ? ' checked' : '' ?>>
                            Semester <?= e($sm) ?>
                        </label>
                    <?php endforeach; ?>
                    <input type="text" name="semester_lain" maxlength="<?= (int) PERANGKAT_SEMESTER_MAKS ?>"
                           placeholder="Semester lain (pisah koma) — mis. Antara">
                    <div class="bantuan">Satu tahun ajaran bisa ditambahkan sekaligus dengan beberapa
                        semesternya.</div>
                </div>
                <div class="aksi-form">
                    <button type="submit" class="tombol"><?= ikon('plus', 17) ?> Tambah Periode</button>
                </div>
            </form>
        </div>

        <div class="kartu mt24" id="formPeriode">
            <h2><?= $edit ? 'Ubah Periode' : 'Tambah Satu Periode' ?></h2>
            <form method="post" action="periode.php">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="simpan">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="form-baris">
                    <label for="ta">Tahun Ajaran <span class="wajib">*</span></label>
                    <input type="text" id="ta" name="ta" required maxlength="<?= (int) PERANGKAT_TA_MAKS ?>"
                           list="saranTa" placeholder="2026/2027" value="<?= e((string) ($edit['ta'] ?? '')) ?>">
                    <datalist id="saranTa">
                        <?php foreach (perangkat_periode_ta_list(false) as $t): ?>
                            <option value="<?= e($t) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-baris">
                    <label for="semester">Semester <span class="wajib">*</span></label>
                    <input type="text" id="semester" name="semester" required
                           maxlength="<?= (int) PERANGKAT_SEMESTER_MAKS ?>" list="saranSemester"
                           placeholder="Ganjil" value="<?= e((string) ($edit['semester'] ?? '')) ?>">
                    <datalist id="saranSemester">
                        <?php foreach ((array) PERANGKAT_SEMESTER as $sm): ?>
                            <option value="<?= e($sm) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-baris">
                    <label class="rasta" style="gap:9px;font-weight:500">
                        <input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>>
                        Aktif (tampil pada filter Perangkat Guru)</label>
                    <label class="rasta" style="gap:9px;font-weight:500">
                        <input type="checkbox" name="bawaan" value="1" <?= (int) ($edit['bawaan'] ?? 0) === 1 ? 'checked' : '' ?>>
                        Jadikan periode berjalan</label>
                </div>
                <div class="aksi-form">
                    <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan</button>
                    <?php if ($edit): ?>
                        <a class="tombol tombol-garis" href="periode.php">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<?php admin_bawah(); ?>
