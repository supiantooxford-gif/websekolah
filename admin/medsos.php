<?php
/**
 * Media Sosial — mengatur tautan media sosial yang tampil pada bilah atas, kaki halaman,
 * dan bagian "Galeri & Media Sosial" di beranda.
 *
 * Catatan: dahulu halaman ini juga mengelola "Slider Kecil" (strip gambar + tautan). Strip itu
 * sudah TIDAK ditampilkan lagi di beranda (permintaan pemilik), jadi bagian itu dihapus dari
 * halaman ini. Data lama pada tabel `slider_kecil` sengaja DIBIARKAN UTUH di basis data
 * (tidak dihapus) bila suatu saat diperlukan kembali.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan('sosial', (int) ($_GET['id'] ?? 0), $aksi);
    redirect('medsos.php');
}
if ($aksi === 'toggle') {
    q('UPDATE sosial SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('medsos.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('sosial', (int) ($_POST['id'] ?? 0));
    flash('Tautan media sosial dihapus.', 'ok');
    redirect('medsos.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nama === '') {
        flash('Nama media sosial wajib diisi.', 'err');
        redirect('medsos.php#formSosial');
    }
    $data = [
        'nama'   => potong($nama, 60, ''),
        'ikon'   => potong((string) ($_POST['ikon'] ?? 'globe'), 30, ''),
        'tautan' => potong((string) ($_POST['tautan'] ?? ''), URL_MAKS, ''),
        'aktif'  => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        q('UPDATE sosial SET nama = ?, ikon = ?, tautan = ?, aktif = ? WHERE id = ?',
            [$data['nama'], $data['ikon'], $data['tautan'], $data['aktif'], $id]);
        flash('Media sosial “' . e($data['nama']) . '” disimpan.', 'ok');
    } else {
        q('INSERT INTO sosial (nama, ikon, tautan, aktif, urutan) VALUES (?, ?, ?, ?, ?)',
            [$data['nama'], $data['ikon'], $data['tautan'], $data['aktif'], urutan_baru('sosial')]);
        flash('Media sosial “' . e($data['nama']) . '” ditambahkan.', 'ok');
    }
    redirect('medsos.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM sosial WHERE id = ?', [(int) $_GET['edit']]) : null;
$daftar = all('SELECT * FROM sosial ORDER BY urutan, id');
$IKON = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube',
    'whatsapp' => 'WhatsApp', 'tiktok' => 'TikTok', 'globe' => 'Lainnya / Website'];

admin_atas('Media Sosial');
?>
<div class="grid g21">
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2>Daftar Media Sosial</h2>
                <p class="mut kecil mb0"><?= angka(count($daftar)) ?> tautan · <?= angka(count(array_filter($daftar, static fn (array $r): bool => (int) $r['aktif'] === 1))) ?> ditampilkan.</p>
            </div>
            <a class="tombol tombol-kecil" href="medsos.php?edit=0#formSosial"><?= ikon('plus', 15) ?> Tambah Media Sosial</a>
        </div>

        <div class="tabel-wrap">
            <table class="tabel">
                <thead><tr><th style="width:62px">Urut</th><th>Nama</th><th>Ikon</th><th>Tautan</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
                <tbody>
                    <?php if (!$daftar): ?><tr><td colspan="6" class="mut">Belum ada data — tekan “Tambah Media Sosial”.</td></tr><?php endif; ?>
                    <?php foreach ($daftar as $so): ?>
                        <tr>
                            <td class="aksi-sel"><?= tombol_urut('medsos.php', (int) $so['id']) ?></td>
                            <td class="rasta" style="gap:10px"><?= ikon_sosial($so['ikon'], 22) ?><strong><?= e($so['nama']) ?></strong></td>
                            <td class="kecil mut"><?= e($so['ikon']) ?></td>
                            <td class="kecil"><?= e(potong($so['tautan'], 60)) ?></td>
                            <td><?= ((int) $so['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                            <td class="aksi-sel">
                                <?= tombol_mini('medsos.php?edit=' . (int) $so['id'] . '#formSosial', 'edit', 'Ubah') ?>
                                <?= tombol_mini('medsos.php?aksi=toggle&id=' . (int) $so['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                                <?= form_hapus('hapus', (int) $so['id'], 'Hapus media sosial ' . $so['nama'] . '?') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 class="mt24" id="formSosial"><?= $edit ? 'Ubah Media Sosial' : 'Tambah Media Sosial' ?></h3>
        <form method="post" action="medsos.php" id="formMedsos">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-2">
                <div class="form-baris">
                    <label for="nama">Nama <span class="wajib">*</span></label>
                    <input type="text" id="nama" name="nama" required maxlength="60" placeholder="Instagram" value="<?= e($edit['nama'] ?? '') ?>">
                </div>
                <div class="form-baris">
                    <label for="ikon">Ikon</label>
                    <select id="ikon" name="ikon">
                        <?php foreach ($IKON as $ik => $lb): ?>
                            <option value="<?= e($ik) ?>" <?= ($edit['ikon'] ?? '') === $ik ? 'selected' : '' ?>><?= e($lb) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="bantuan">Ikon tampil pada tombol media sosial di beranda (berukuran besar).</div>
                </div>
            </div>
            <div class="form-baris">
                <label for="tautan">Alamat / Tautan</label>
                <input type="url" id="tautan" name="tautan" maxlength="300" placeholder="https://instagram.com/..." value="<?= e($edit['tautan'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan
                </label>
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan</button>
                <?php if ($edit): ?><a class="tombol tombol-garis" href="medsos.php">Batal</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div>
        <div class="kartu">
            <h2>Tampil di Mana?</h2>
            <ul class="daftar-cek">
                <li><?= ikon('check', 16) ?><span><strong>Bilah atas</strong> — deretan ikon kecil di kanan atas.</span></li>
                <li><?= ikon('check', 16) ?><span><strong>Bagian “Galeri &amp; Media Sosial”</strong> di beranda — tombol besar berikon, lebarnya sejajar dengan galeri di atasnya.</span></li>
                <li><?= ikon('check', 16) ?><span><strong>Kaki halaman</strong> — kolom “Ikuti Kami”.</span></li>
            </ul>
            <div class="aksi-form">
                <a class="tombol tombol-garis tombol-kecil" href="../#galeri" target="_blank" rel="noopener"><?= ikon('mata', 15) ?> Lihat di Beranda</a>
            </div>
        </div>
        <div class="kartu mt16">
            <h2>Tombol WhatsApp</h2>
            <p class="kecil mut mb0">Nomor WhatsApp untuk tombol mengapung di kanan bawah diatur pada menu
                <strong>Identitas Sekolah</strong> (medan “Nomor WhatsApp”).</p>
        </div>
    </div>
</div>
<?php admin_bawah(); ?>
