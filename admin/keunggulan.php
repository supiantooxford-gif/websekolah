<?php
/**
 * Keunggulan (strip di bawah slider) & Program Digitalisasi Madrasah (slider beranda).
 *
 * Kedua daftar punya kartu & FORMULIR TAMBAH SENDIRI-SENDIRI supaya jelas jenis mana yang
 * sedang ditambahkan (versi pertama memakai satu formulir di bawah halaman sehingga pengguna
 * tidak menemukan cara menambah keunggulan).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$IKON = ['book', 'users', 'building', 'medal', 'atom', 'heart', 'trophy', 'star', 'shield',
    'globe', 'grafik', 'papan', 'gambar', 'semua', 'clipboard', 'kalender', 'jam', 'check',
    'doc', 'mata', 'setelan', 'kunci', 'camera', 'video'];

const KEU_TABEL = ['keunggulan' => 'Keunggulan', 'program' => 'Program Digitalisasi Madrasah'];

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');
$tabel = (string) ($_POST['tabel'] ?? $_GET['tabel'] ?? 'keunggulan');
$tabel = array_key_exists($tabel, KEU_TABEL) ? $tabel : 'keunggulan';
$jangkar = $tabel === 'program' ? '#formProgram' : '#formKeunggulan';

if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan($tabel, (int) ($_GET['id'] ?? 0), $aksi);
    redirect('keunggulan.php?tabel=' . $tabel);
}
if ($aksi === 'toggle') {
    q('UPDATE ' . $tabel . ' SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('keunggulan.php?tabel=' . $tabel);
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris($tabel, (int) ($_POST['id'] ?? 0));
    flash('Data ' . KEU_TABEL[$tabel] . ' dihapus.', 'ok');
    redirect('keunggulan.php?tabel=' . $tabel);
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $judul = trim((string) ($_POST['judul'] ?? ''));
    if ($judul === '') {
        flash('Judul wajib diisi.', 'err');
        redirect('keunggulan.php?tabel=' . $tabel . $jangkar);
    }
    $data = [
        'judul'      => potong($judul, JUDUL_MAKS, ''),
        'keterangan' => potong((string) ($_POST['keterangan'] ?? ''), RINGKASAN_MAKS, ''),
        'ikon'       => in_array((string) ($_POST['ikon'] ?? ''), $IKON, true) ? (string) $_POST['ikon'] : 'star',
        'aktif'      => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($tabel === 'program') {
        $data['tautan'] = potong((string) ($_POST['tautan'] ?? ''), URL_MAKS, '');
    }
    if ($id > 0) {
        $set = [];
        $val = [];
        foreach ($data as $k => $v) {
            $set[] = $k . ' = ?';
            $val[] = $v;
        }
        $val[] = $id;
        q('UPDATE ' . $tabel . ' SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        flash('Data ' . KEU_TABEL[$tabel] . ' “' . e($data['judul']) . '” disimpan.', 'ok');
    } else {
        $data['urutan'] = urutan_baru($tabel);
        $kol = array_keys($data);
        q('INSERT INTO ' . $tabel . ' (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        flash('Data ' . KEU_TABEL[$tabel] . ' “' . e($data['judul']) . '” ditambahkan.', 'ok');
    }
    redirect('keunggulan.php?tabel=' . $tabel);
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId > 0 ? one('SELECT * FROM ' . $tabel . ' WHERE id = ?', [$editId]) : null;
$keunggulan = all('SELECT * FROM keunggulan ORDER BY urutan, id');
$program = all('SELECT * FROM program ORDER BY urutan, id');

/** Menampilkan formulir tambah/ubah untuk satu tabel. */
function formulir_keunggulan(string $tabel, array $IKON, ?array $edit): void
{
    $p = $tabel === 'program';
    $id = $p ? 'Program' : 'Keunggulan';
    $nilai = static fn (string $k, string $b = ''): string => (string) ($edit[$k] ?? $b);
    ?>
    <h3 class="mt24"><?= $edit ? 'Ubah Data ' . KEU_TABEL[$tabel] : 'Tambah ' . KEU_TABEL[$tabel] ?></h3>
    <!-- id diletakkan pada FORM (bukan judul) supaya tautan #formKeunggulan/#formProgram tepat
         mengarah ke formulir tambah dan dapat ditargetkan dengan andal. -->
    <form method="post" action="keunggulan.php" id="form<?= $id ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="tabel" value="<?= e($tabel) ?>">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-2">
            <div class="form-baris">
                <label for="judul_<?= $p ? 'p' : 'k' ?>">Judul <span class="wajib">*</span></label>
                <input type="text" id="judul_<?= $p ? 'p' : 'k' ?>" name="judul" required maxlength="160"
                       placeholder="<?= $p ? 'mis. Tahfidz Al-Qur’an' : 'mis. Kurikulum Terpadu' ?>" value="<?= e($nilai('judul')) ?>">
            </div>
            <div class="form-baris">
                <label for="ikon_<?= $p ? 'p' : 'k' ?>">Ikon</label>
                <select id="ikon_<?= $p ? 'p' : 'k' ?>" name="ikon">
                    <?php foreach ($IKON as $i): ?>
                        <option value="<?= e($i) ?>" <?= $nilai('ikon') === $i ? 'selected' : '' ?>><?= e($i) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-baris">
            <label for="keterangan_<?= $p ? 'p' : 'k' ?>">Keterangan Singkat</label>
            <textarea id="keterangan_<?= $p ? 'p' : 'k' ?>" name="keterangan" maxlength="400" style="min-height:80px"><?= e($nilai('keterangan')) ?></textarea>
        </div>
        <?php if ($p): ?>
            <div class="form-baris">
                <label for="tautan_p">Tautan Kartu (opsional)</label>
                <input type="text" id="tautan_p" name="tautan" maxlength="300" placeholder="halaman/kurikulum" value="<?= e($nilai('tautan')) ?>">
            </div>
        <?php endif; ?>
        <div class="form-baris">
            <label class="rasta" style="gap:9px;font-weight:500">
                <input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan pada beranda
            </label>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambahkan' ?></button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="keunggulan.php?tabel=<?= e($tabel) ?>">Batal</a><?php endif; ?>
        </div>
    </form>
    <?php
}
?>
<?php admin_atas('Keunggulan & Program'); ?>
<div class="alert alert-info">
    <strong>Keunggulan</strong> tampil sebagai strip ikon di bawah slider beranda (disarankan 4 butir agar
    sebaris rapi). <strong>Program Digitalisasi Madrasah</strong> tampil sebagai <strong>slider kartu</strong> di beranda —
    isi dengan nama aplikasi beserta tautannya (mis. Aplikasi e-GURU, Supervisi Akademik, CBT).
</div>

<div class="grid g2">
    <!-- ==================== KEUNGGULAN ==================== -->
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2>Keunggulan (Strip di Bawah Slider)</h2>
                <p class="mut kecil mb0"><?= angka(count($keunggulan)) ?> butir · <?= angka(count(array_filter($keunggulan, static fn (array $r): bool => (int) $r['aktif'] === 1))) ?> ditampilkan.</p>
            </div>
            <a class="tombol tombol-kecil" href="keunggulan.php?tabel=keunggulan#formKeunggulan"><?= ikon('plus', 15) ?> Tambah Keunggulan</a>
        </div>
        <div class="tabel-wrap">
            <table class="tabel">
                <thead><tr><th style="width:48px">Urut</th><th>Judul</th><th style="width:52px">Ikon</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
                <tbody>
                    <?php if (!$keunggulan): ?><tr><td colspan="5" class="mut">Belum ada keunggulan — tekan “Tambah Keunggulan”.</td></tr><?php endif; ?>
                    <?php foreach ($keunggulan as $k): ?>
                        <tr>
                            <td class="aksi-sel"><?= tombol_urut('keunggulan.php', (int) $k['id'], ['tabel' => 'keunggulan']) ?></td>
                            <td><strong><?= e($k['judul']) ?></strong><div class="kecil mut"><?= e(potong($k['keterangan'], 70)) ?></div></td>
                            <td><?= ikon($k['ikon'], 20) ?></td>
                            <td><?= ((int) $k['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                            <td class="aksi-sel">
                                <?= tombol_mini('keunggulan.php?tabel=keunggulan&edit=' . (int) $k['id'] . '#formKeunggulan', 'edit', 'Ubah') ?>
                                <?= tombol_mini('keunggulan.php?aksi=toggle&id=' . (int) $k['id'] . '&tabel=keunggulan', 'mata', 'Tampilkan/sembunyikan') ?>
                                <?= form_hapus('hapus', (int) $k['id'], 'Hapus keunggulan “' . $k['judul'] . '”?', ['tabel' => 'keunggulan']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php formulir_keunggulan('keunggulan', $IKON, $tabel === 'keunggulan' ? $edit : null); ?>
    </div>

    <!-- ==================== PROGRAM UNGGULAN ==================== -->
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2>Program Digitalisasi Madrasah</h2>
                <p class="mut kecil mb0"><?= angka(count($program)) ?> program · <?= angka(count(array_filter($program, static fn (array $r): bool => (int) $r['aktif'] === 1))) ?> ditampilkan (maksimal 4 teratas).</p>
            </div>
            <a class="tombol tombol-kecil" href="keunggulan.php?tabel=program#formProgram"><?= ikon('plus', 15) ?> Tambah Program</a>
        </div>
        <div class="tabel-wrap">
            <table class="tabel">
                <thead><tr><th style="width:48px">Urut</th><th>Judul</th><th style="width:52px">Ikon</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
                <tbody>
                    <?php if (!$program): ?><tr><td colspan="5" class="mut">Belum ada program — tekan “Tambah Program”.</td></tr><?php endif; ?>
                    <?php foreach ($program as $k): ?>
                        <tr>
                            <td class="aksi-sel"><?= tombol_urut('keunggulan.php', (int) $k['id'], ['tabel' => 'program']) ?></td>
                            <td><strong><?= e($k['judul']) ?></strong><div class="kecil mut"><?= e(potong($k['keterangan'], 70)) ?></div></td>
                            <td><?= ikon($k['ikon'], 20) ?></td>
                            <td><?= ((int) $k['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                            <td class="aksi-sel">
                                <?= tombol_mini('keunggulan.php?tabel=program&edit=' . (int) $k['id'] . '#formProgram', 'edit', 'Ubah') ?>
                                <?= tombol_mini('keunggulan.php?aksi=toggle&id=' . (int) $k['id'] . '&tabel=program', 'mata', 'Tampilkan/sembunyikan') ?>
                                <?= form_hapus('hapus', (int) $k['id'], 'Hapus program “' . $k['judul'] . '”?', ['tabel' => 'program']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php formulir_keunggulan('program', $IKON, $tabel === 'program' ? $edit : null); ?>
    </div>
</div>
<?php admin_bawah(); ?>
