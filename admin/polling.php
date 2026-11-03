<?php
/**
 * Polling Pendapat — mengatur pertanyaan & pilihan jawaban yang tampil pada beranda,
 * beserta rekap hasil suara.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

/** Polling yang sedang disunting (hanya ada satu yang aktif dipakai website). */
function poll_admin_aktif(): ?array
{
    return one('SELECT * FROM poll ORDER BY id DESC LIMIT 1');
}

// ---------- pertanyaan ----------
if ($aksi === 'simpan_poll') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $pertanyaan = trim((string) ($_POST['pertanyaan'] ?? ''));
    $keterangan = potong((string) ($_POST['keterangan'] ?? ''), 500, '');
    $aktif = empty($_POST['aktif']) ? 0 : 1;
    $poll = poll_admin_aktif();
    if ($pertanyaan === '') {
        flash('Pertanyaan polling wajib diisi.', 'err');
    } elseif ($poll) {
        q('UPDATE poll SET pertanyaan = ?, keterangan = ?, aktif = ? WHERE id = ?',
            [potong($pertanyaan, 240, ''), $keterangan, $aktif, (int) $poll['id']]);
        flash('Pertanyaan polling disimpan.', 'ok');
    } else {
        q("INSERT INTO poll (pertanyaan, keterangan, aktif, dibuat) VALUES (?, ?, ?, datetime('now','localtime'))",
            [potong($pertanyaan, 240, ''), $keterangan, $aktif]);
        flash('Polling dibuat.', 'ok');
    }
    redirect('polling.php');
}

// ---------- pilihan jawaban ----------
if ($aksi === 'simpan_pilihan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $poll = poll_admin_aktif();
    $teks = trim((string) ($_POST['teks'] ?? ''));
    $id = (int) ($_POST['id'] ?? 0);
    if (!$poll) {
        flash('Buat pertanyaan polling terlebih dahulu.', 'err');
    } elseif ($teks === '') {
        flash('Tulisan pilihan jawaban wajib diisi.', 'err');
    } elseif ($id > 0) {
        q('UPDATE poll_pilihan SET teks = ?, aktif = ? WHERE id = ? AND poll_id = ?',
            [potong($teks, 160, ''), empty($_POST['aktif']) ? 0 : 1, $id, (int) $poll['id']]);
        flash('Pilihan jawaban disimpan.', 'ok');
    } else {
        q('INSERT INTO poll_pilihan (poll_id, teks, urutan, aktif) VALUES (?, ?, ?, ?)',
            [(int) $poll['id'], potong($teks, 160, ''), urutan_baru('poll_pilihan', ['poll_id' => (int) $poll['id']]), empty($_POST['aktif']) ? 0 : 1]);
        flash('Pilihan jawaban ditambahkan.', 'ok');
    }
    redirect('polling.php');
}
if ($aksi === 'pilihan_toggle') {
    q('UPDATE poll_pilihan SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('polling.php');
}
if ($aksi === 'pilihan_naik' || $aksi === 'pilihan_turun') {
    $poll = poll_admin_aktif();
    geser_urutan('poll_pilihan', (int) ($_GET['id'] ?? 0), $aksi === 'pilihan_naik' ? 'naik' : 'turun',
        $poll ? ['poll_id' => (int) $poll['id']] : []);
    redirect('polling.php');
}
if ($aksi === 'pilihan_hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $n = jumlah('SELECT COUNT(*) FROM poll_suara WHERE pilihan_id = ?', [$id]);
    q('DELETE FROM poll_suara WHERE pilihan_id = ?', [$id]);   // suara pada pilihan yang dihapus ikut dibuang
    hapus_baris('poll_pilihan', $id);
    flash('Pilihan jawaban dihapus' . ($n > 0 ? ' beserta ' . angka($n) . ' suara padanya' : '') . '.', 'ok');
    redirect('polling.php');
}

// ---------- bersihkan suara ----------
if ($aksi === 'bersihkan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    if (strtoupper(trim((string) ($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
        flash('Penghapusan dibatalkan — ketik HAPUS pada kotak konfirmasi untuk melanjutkan.', 'err');
        redirect('polling.php');
    }
    $n = jumlah('SELECT COUNT(*) FROM poll_suara');
    q('DELETE FROM poll_suara');
    flash(angka($n) . ' suara polling dihapus (pertanyaan & pilihan tetap ada).', 'ok');
    redirect('polling.php');
}

$poll = poll_aktif(false);          // termasuk pilihan yang dinonaktifkan
$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM poll_pilihan WHERE id = ?', [(int) $_GET['edit']]) : null;

admin_atas('Polling Pendapat');
?>
<div class="grid g21">
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2>Pertanyaan pada Beranda</h2>
                <p class="mut kecil mb0">Tampil pada kolom kanan beranda, tepat di bawah kartu PPDB.</p>
            </div>
        </div>
        <form method="post" action="polling.php" id="formPoll">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan_poll">
            <div class="form-baris">
                <label for="pertanyaan">Pertanyaan <span class="wajib">*</span></label>
                <input type="text" id="pertanyaan" name="pertanyaan" required maxlength="240"
                       placeholder="Bagaimana pendapat anda tentang web ini?" value="<?= e($poll['pertanyaan'] ?? 'Bagaimana pendapat anda tentang web ini?') ?>">
            </div>
            <div class="form-baris">
                <label for="keterangan">Keterangan Singkat</label>
                <textarea id="keterangan" name="keterangan" maxlength="500" style="min-height:70px"><?= e($poll['keterangan'] ?? '') ?></textarea>
            </div>
            <div class="form-baris">
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="aktif" value="1" <?= !isset($poll['aktif']) || (int) $poll['aktif'] === 1 ? 'checked' : '' ?>>
                    Tampilkan polling pada beranda
                </label>
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Pertanyaan</button>
            </div>
        </form>

        <h3 class="mt24">Pilihan Jawaban</h3>
        <div class="tabel-wrap">
            <table class="tabel">
                <thead><tr><th style="width:62px">Urut</th><th>Pilihan Jawaban</th><th style="width:90px">Suara</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
                <tbody>
                    <?php if (!$poll || !$poll['pilihan']): ?>
                        <tr><td colspan="5" class="mut">Belum ada pilihan jawaban — tambahkan minimal dua pilihan.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($poll['pilihan'] ?? []) as $pl): ?>
                        <tr>
                            <td class="aksi-sel">
                                <?= tombol_mini('polling.php?aksi=pilihan_naik&id=' . (int) $pl['id'], 'naik', 'Naikkan') ?>
                                <?= tombol_mini('polling.php?aksi=pilihan_turun&id=' . (int) $pl['id'], 'turun', 'Turunkan') ?>
                            </td>
                            <td><strong><?= e($pl['teks']) ?></strong></td>
                            <td><?= angka($pl['suara']) ?></td>
                            <td><?= ((int) $pl['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                            <td class="aksi-sel">
                                <?= tombol_mini('polling.php?edit=' . (int) $pl['id'] . '#formPilihan', 'edit', 'Ubah pilihan') ?>
                                <?= tombol_mini('polling.php?aksi=pilihan_toggle&id=' . (int) $pl['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                                <?= form_hapus('pilihan_hapus', (int) $pl['id'], 'Hapus pilihan “' . $pl['teks'] . '”? Suara pada pilihan ini ikut dihapus.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 class="mt24" id="formPilihan"><?= $edit ? 'Ubah Pilihan Jawaban' : 'Tambah Pilihan Jawaban' ?></h3>
        <form method="post" action="polling.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan_pilihan">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-baris">
                <label for="teks">Tulisan Pilihan <span class="wajib">*</span></label>
                <input type="text" id="teks" name="teks" required maxlength="160" placeholder="mis. Sangat baik &amp; menarik" value="<?= e($edit['teks'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan pilihan ini
                </label>
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambahkan Pilihan' ?></button>
                <?php if ($edit): ?><a class="tombol tombol-garis" href="polling.php#formPilihan">Batal</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div>
        <div class="kartu">
            <h2>Rekap Hasil</h2>
            <?php if (!$poll || $poll['total'] === 0): ?>
                <p class="mut kecil">Belum ada suara yang masuk.</p>
            <?php else: ?>
                <p class="kecil mut mb16"><?= angka($poll['total']) ?> suara masuk. Batang emas = jawaban terbanyak.</p>
                <?php foreach ($poll['pilihan'] as $i => $pl): ?>
                    <div class="poll-baris<?= poll_kelas_teratas($poll, $i) ?>" style="margin-bottom:12px">
                        <div class="poll-baris-kepala">
                            <span><?= e($pl['teks']) ?></span>
                            <strong><?= $pl['persen'] ?>% · <?= angka($pl['suara']) ?></strong>
                        </div>
                        <div class="poll-track"><span style="width:<?= (float) $pl['persen'] ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="aksi-form">
                <a class="tombol tombol-garis tombol-kecil" href="../#polling" target="_blank" rel="noopener"><?= ikon('mata', 15) ?> Lihat di Beranda</a>
            </div>
        </div>

        <div class="kartu mt16">
            <h2>Cara Kerja</h2>
            <ul class="daftar-cek">
                <li><?= ikon('check', 16) ?><span>Pengunjung memilih satu jawaban lalu menekan <strong>Kirim Pendapat</strong>; hasil langsung tampil dalam persentase.</span></li>
                <li><?= ikon('check', 16) ?><span>Satu pengunjung hanya dapat memilih <strong>satu kali</strong> (penanda disimpan pada sesi peramban).</span></li>
                <li><?= ikon('check', 16) ?><span>Menonaktifkan polling membuat kartunya hilang dari beranda tanpa menghapus data.</span></li>
            </ul>
        </div>

        <?php if ($poll && $poll['total'] > 0): ?>
            <?php form_konfirmasi_ketik(
                'bersihkan',
                'Hapus Semua Suara',
                'Seluruh ' . angka($poll['total']) . ' suara polling akan dihapus sehingga perhitungan dimulai dari nol. Pertanyaan dan pilihan jawaban tetap ada.'
            ); ?>
        <?php endif; ?>
    </div>
</div>
<?php admin_bawah(); ?>
