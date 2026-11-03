<?php
/**
 * STRUKTUR ORGANISASI (BAGAN) — isian NAMA + PAS FOTO setiap kotak.
 *
 * ⚠️ Letak, ukuran, dan garis penghubung bagan bersifat **TETAP** (lihat `struktur_peta()` pada
 * `inc/profil.php`) mengikuti dokumen contoh pemilik. Karena itu halaman ini TIDAK lagi mengatur
 * letak/urutan/atasan kotak — admin cukup mengisi **Nama** dan mengunggah **Pas Foto** pada tiap
 * kotak, plus mencentang/menghilangkan centang "tampilkan" bila ada kotak yang tidak dipakai.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/profil.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    // SATU formulir memuat seluruh slot → simpan semuanya sekaligus. Slot yang medannya tidak
    // dikirim (mis. formulir lama yang masih terbuka) DILEWATI supaya tidak mengosongkan isian.
    $disimpan = 0;
    $pesan = [];
    $galat = [];
    foreach (struktur_slot() as $slot) {
        $kode = (string) $slot['kode'];
        if (!array_key_exists('nama_' . $kode, $_POST)) {
            continue;
        }
        $baris = struktur_baris_kode($kode);
        $nama = trim((string) ($_POST['nama_' . $kode] ?? ''));
        if ($nama === '') {
            $nama = (string) $slot['label'];   // kosong = pakai nama bawaan kotak
        }
        $fotoLama = $baris ? (string) ($baris['foto'] ?? '') : '';
        $g = proses_gambar('foto_' . $kode, $fotoLama, !empty($_POST['foto_hapus_' . $kode]), 'Pas foto ' . $slot['label']);
        $aktif = empty($_POST['aktif_' . $kode]) ? 0 : 1;
        if ($baris) {
            q('UPDATE struktur SET nama = ?, foto = ?, aktif = ? WHERE id = ?',
                [potong($nama, 120, ''), $g['url'], $aktif, (int) $baris['id']]);
        } else {
            // `aksen` selalu 0 — blok merah di dalam kotak sudah dihapus (permintaan pemilik).
            q("INSERT INTO struktur (nama, induk_id, urutan, garis, aksen, aktif, kode, foto, dibuat)
               VALUES (?, 0, ?, 'penuh', 0, ?, ?, ?, ?)", [
                potong($nama, 120, ''), (int) ($slot['y'] ?? 0),
                $aktif, $kode, $g['url'], date('Y-m-d H:i:s'),
            ]);
        }
        $disimpan++;
        if ($g['pesan'] !== '') {
            $pesan[] = $slot['label'] . ': ' . $g['pesan'];
        }
        if ($g['galat']) {
            $galat[] = $slot['label'] . ': ' . $g['pesan'];
        }
    }
    flash('Bagan disimpan (' . $disimpan . ' kotak). ' . implode(' ', $pesan),
        $galat ? 'err' : 'ok');
    redirect('struktur.php');
}

if ($aksi === 'nama_bawaan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    // Kembalikan SELURUH nama ke label bawaan peta (foto & status tampil tidak diubah).
    $jumlah = 0;
    foreach (struktur_slot() as $slot) {
        $baris = struktur_baris_kode((string) $slot['kode']);
        if (!$baris) {
            continue;
        }
        q('UPDATE struktur SET nama = ? WHERE id = ?', [(string) $slot['label'], (int) $baris['id']]);
        $jumlah++;
    }
    flash('Nama ' . $jumlah . ' kotak dikembalikan ke nama bawaan bagan.', 'ok');
    redirect('struktur.php');
}

$peta = struktur_peta();
$barisKode = struktur_semua_kode();
$ringkas = struktur_ringkasan();
$menuStruktur = one("SELECT * FROM menu WHERE jenis = 'struktur_organisasi' LIMIT 1");

/**
 * Satu baris isian (slot) — dipakai di dalam formulir TAMBAH, bukan FORM terpisah.
 * (Satu formulir memuat seluruh slot supaya sekali Simpan semua isian terkirim.)
 */
admin_atas('Struktur Organisasi (Bagan)');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2 style="margin:0 0 4px">Bagan Struktur Organisasi</h2>
            <p class="mut kecil mb0">Susunan bagan sudah <strong>permanen</strong> seperti dokumen contoh —
                Bapak/Ibu cukup mengisi <strong>Nama</strong> dan mengunggah <strong>Pas Foto</strong> tiap kotak.
                Kotak yang dikosongkan tetap memakai nama bawaannya.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <span class="pil pil-emas"><?= (int) $ringkas['nama'] ?>/<?= (int) $ringkas['total'] ?> kotak bernama</span>
            <span class="pil pil-baru"><?= (int) $ringkas['foto'] ?>/<?= (int) $ringkas['total'] ?> berfoto</span>
            <?php if ($menuStruktur): ?>
                <a class="tombol tombol-garis tombol-kecil" target="_blank" rel="noopener" href="../halaman/<?= e((string) $menuStruktur['slug']) ?>"><?= ikon('mata', 15) ?> Lihat Bagan</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($ringkas['kosong']): ?>
        <div class="alert alert-info">
            <strong>Kotak yang masih memakai nama bawaan:</strong> <?= e(implode(', ', $ringkas['kosong'])) ?>.
            Isi nama sebenarnya pada kolom Nama di bawah (NIP/tugas tidak perlu — cukup nama).
        </div>
    <?php endif; ?>
    <div class="rasta" style="gap:12px;flex-wrap:wrap">
        <form method="post" action="struktur.php" data-konfirmasi="Kembalikan SELURUH nama kotak ke nama bawaan bagan? Foto tidak diubah.">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="nama_bawaan">
            <button type="submit" class="tombol tombol-garis tombol-kecil"><?= ikon('undo', 15) ?> Kembalikan Nama Bawaan</button>
        </form>
    </div>
</div>

<form method="post" action="struktur.php" enctype="multipart/form-data" id="formStruktur">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <div class="org-isian-grid">
        <?php foreach ($peta['slot'] as $slot): ?>
            <?php
            $kode = (string) $slot['kode'];
            $baris = $barisKode[$kode] ?? null;
            $namaDb = $baris ? trim((string) $baris['nama']) : '';
            $namaNilai = ($namaDb !== '' && !in_array($namaDb, ['-', '–', '—', '.', '_'], true)) ? $namaDb : '';
            $foto = $baris ? trim((string) ($baris['foto'] ?? '')) : '';
            $aktif = !$baris || (int) $baris['aktif'] === 1;
            ?>
            <div class="kartu org-isian" id="<?= e($kode) ?>">
                <div class="kartu-kepala">
                    <div>
                        <h3 style="margin:0;font-size:1rem"><?= e((string) $slot['label']) ?></h3>
                        <p class="mut kecil mb0">Kotak ini sudah punya posisi tetap pada bagan.</p>
                    </div>
                </div>
                <div class="org-isian-isi">
                    <div class="org-isian-foto">
                        <?php if ($foto !== ''): ?>
                            <img src="<?= e($foto) ?>" alt="Pas foto <?= e((string) $slot['label']) ?>">
                        <?php else: ?>
                            <span class="kotak-kosong"><span>Belum ada foto</span></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-baris">
                        <label for="nama_<?= e($kode) ?>">Nama (mis. nama pejabat/penanggung jawab)</label>
                        <input type="text" id="nama_<?= e($kode) ?>" name="nama_<?= e($kode) ?>" maxlength="120"
                               placeholder="<?= e((string) $slot['label']) ?>" value="<?= e($namaNilai) ?>">
                    </div>
                </div>
                <div class="form-baris">
                    <label for="foto_<?= e($kode) ?>">Pas Foto (3x4, opsional)</label>
                    <input type="file" id="foto_<?= e($kode) ?>" name="foto_<?= e($kode) ?>" accept=".jpg,.jpeg,.png,.gif,.webp,.svg">
                </div>
                <div class="rasta" style="gap:14px;flex-wrap:wrap">
                    <?php if ($foto !== ''): ?>
                        <label class="rasta" style="gap:9px;font-weight:500">
                            <input type="checkbox" name="foto_hapus_<?= e($kode) ?>" value="1"> Hapus foto
                        </label>
                    <?php endif; ?>
                    <label class="rasta" style="gap:9px;font-weight:500">
                        <input type="checkbox" name="aktif_<?= e($kode) ?>" value="1" <?= $aktif ? 'checked' : '' ?>> Tampilkan kotak
                    </label>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="aksi-form mt16" style="margin-top:20px">
        <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Semua Kotak</button>
        <?php if ($menuStruktur): ?>
            <a class="tombol tombol-garis" target="_blank" rel="noopener" href="../halaman/<?= e((string) $menuStruktur['slug']) ?>"><?= ikon('mata', 16) ?> Lihat Bagan</a>
        <?php endif; ?>
    </div>
</form>
<?php admin_bawah(); ?>
