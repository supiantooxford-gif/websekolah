<?php
/**
 * FAQ ASISTEN — pertanyaan & jawaban yang diisi admin.
 *
 * Asisten obrolan pada website menjawab dari DUA sumber:
 *   1. **FAQ** pada halaman ini (pertanyaan + jawaban + kata kunci pemicu), dan
 *   2. data website (profil, kontak, guru/siswa/alumni, prestasi, fasilitas, berita, agenda,
 *      galeri, struktur organisasi, menu, dan PPDB) — otomatis, tanpa perlu diisi di sini.
 *
 * Gunakan halaman ini untuk pertanyaan yang BELUM terjawab otomatis (mis. kebijakan khusus
 * madrasah). Kata kunci dipisah koma dan boleh berupa frasa (mis. "biaya seragam, seragam").
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_admin();

$KUNCI_PERTANYAAN_MAKS = 200;
$KUNCI_JAWABAN_MAKS = 2000;

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

// FOTO ASISTEN — dipakai sebagai ikon tombol obrolan mengambang + avatar balasan asisten.
// Disimpan sebagai URL media proxy pada `pengaturan.asisten_foto` (kosong = ilustrasi bawaan
// `assets/asisten.svg`). JANGAN menyimpan berkasnya di folder aplikasi.
if ($aksi === 'foto_asisten') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $lama = pengaturan('asisten_foto', '');
    $hasil = proses_gambar('asisten_foto', $lama, !empty($_POST['asisten_foto_hapus']), 'Foto asisten');
    if ($hasil['url'] !== $lama) {
        pengaturan_set('asisten_foto', $hasil['url']);
    }
    flash($hasil['pesan'] !== '' ? $hasil['pesan'] : 'Foto asisten tidak berubah.',
        $hasil['galat'] ? 'err' : 'ok');
    redirect('faq.php#fotoAsisten');
}

if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $pertanyaan = trim((string) ($_POST['pertanyaan'] ?? ''));
    $jawaban = trim((string) ($_POST['jawaban'] ?? ''));
    $kata = trim((string) ($_POST['kata_kunci'] ?? ''));
    $aktif = empty($_POST['aktif']) ? 0 : 1;
    $urutan = trim((string) ($_POST['urutan'] ?? ''));

    if ($pertanyaan === '' || $jawaban === '') {
        flash('Pertanyaan dan jawaban wajib diisi.', 'err');
        redirect('faq.php' . ($id > 0 ? '?edit=' . $id : '') . '#formFaq');
    }
    $nilai = [
        potong($pertanyaan, $KUNCI_PERTANYAAN_MAKS, ''),
        potong_baris($jawaban, $KUNCI_JAWABAN_MAKS),
        potong($kata, 300, ''),
        $urutan !== '' ? (int) $urutan : asisten_faq_urutan_baru(),
        $aktif,
    ];
    if ($id > 0) {
        $nilai[] = $id;
        q('UPDATE faq SET pertanyaan = ?, jawaban = ?, kata_kunci = ?, urutan = ?, aktif = ? WHERE id = ?', $nilai);
        flash('Pertanyaan “' . e($pertanyaan) . '” disimpan.', 'ok');
    } else {
        q('INSERT INTO faq (pertanyaan, jawaban, kata_kunci, urutan, aktif, dibuat)
           VALUES (?, ?, ?, ?, ?, ?)', array_merge($nilai, [date('Y-m-d H:i:s')]));
        flash('Pertanyaan “' . e($pertanyaan) . '” ditambahkan.', 'ok');
    }
    redirect('faq.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $f = asisten_faq_by_id($id);
    if (!$f) {
        flash('Pertanyaan tidak ditemukan.', 'err');
    } elseif (hapus_baris('faq', $id)) {
        flash('Pertanyaan “' . e((string) $f['pertanyaan']) . '” dihapus.', 'ok');
    } else {
        flash('Pertanyaan gagal dihapus.', 'err');
    }
    redirect('faq.php');
}
if ($aksi === 'toggle') {
    q('UPDATE faq SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    flash('Status pertanyaan diubah.', 'ok');
    redirect('faq.php');
}
if ($aksi === 'geser' || $aksi === 'naik' || $aksi === 'turun') {
    $arah = (string) ($_GET['arah'] ?? ($aksi === 'geser' ? 'naik' : $aksi));
    geser_urutan('faq', (int) ($_GET['id'] ?? 0), $arah === 'turun' ? 'turun' : 'naik');
    redirect('faq.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? asisten_faq_by_id((int) $_GET['edit']) : null;
$daftar = asisten_faq(false);
$cari = trim((string) ($_GET['cari'] ?? ''));
if ($cari !== '') {
    $daftar = array_values(array_filter($daftar, static fn (array $f): bool =>
        stripos((string) $f['pertanyaan'], $cari) !== false
        || stripos((string) $f['jawaban'], $cari) !== false
        || stripos((string) $f['kata_kunci'], $cari) !== false));
}

admin_atas('FAQ Asisten');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2 style="margin:0 0 4px">Pertanyaan &amp; Jawaban Asisten</h2>
            <p class="mut kecil mb0">Asisten obrolan di website menjawab dari <strong>data website</strong>
                (profil, kontak, guru &amp; siswa, prestasi, fasilitas, berita, agenda, galeri, struktur,
                menu, PPDB) <em>dan</em> daftar di bawah ini. Tambahkan pertanyaan di sini untuk hal yang
                belum terjawab otomatis, mis. kebijakan khusus madrasah.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <span class="pil pil-emas"><?= angka(asisten_faq_jumlah(true)) ?> aktif</span>
            <span class="pil pil-baru"><?= angka(asisten_faq_jumlah(false)) ?> total</span>
            <a class="tombol tombol-garis tombol-kecil" target="_blank" rel="noopener" href="../asisten"><?= ikon('mata', 15) ?> Coba Asisten</a>
            <a class="tombol tombol-kecil" href="faq.php?edit=0#formFaq"><?= ikon('plus', 15) ?> Tambah FAQ</a>
        </div>
    </div>


    <div class="kartu mb24" id="fotoAsisten">
    <div class="kartu-kepala">
        <div>
            <h2 style="margin:0 0 4px">Foto Asisten (Ikon Obrolan)</h2>
            <p class="mut kecil mb0">Foto ini dipakai sebagai ikon <strong>tombol obrolan mengambang</strong>
                di pojok kanan bawah website, avatar pada kepala kotak obrolan, dan avatar setiap balasan
                asisten. Belum diunggah? Yang tampil adalah ilustrasi bawaan.</p>
        </div>
        <span class="pil <?= asisten_foto() !== '' ? 'pil-terima' : 'pil-baru' ?>">
            <?= asisten_foto() !== '' ? 'Foto sendiri' : 'Ilustrasi bawaan' ?></span>
    </div>
    <form method="post" action="faq.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="foto_asisten">
        <div class="gambar-pratinjau">
            <span class="ava-asisten" id="pratinjau_asisten_foto">
                <?php if (asisten_foto() !== ''): ?>
                    <img src="<?= e(asisten_foto()) ?>" alt="Pratinjau foto asisten">
                <?php else: ?>
                    <span class="kosong-kecil">Belum ada foto — ilustrasi bawaan dipakai</span>
                <?php endif; ?>
            </span>
            <div class="pratinjau-medan">
                <div class="form-baris">
                    <label for="asisten_foto">Unggah foto asisten</label>
                    <input type="file" id="asisten_foto" name="asisten_foto"
                           accept=".jpg,.jpeg,.png,.gif,.webp,.svg"
                           data-pratinjau="#pratinjau_asisten_foto">
                    <div class="bantuan">JPG/PNG/WEBP/SVG, maksimal 15 MB. Pakai foto wajah/operator — boleh
                        berlatar transparan. Ikon ditampilkan <strong>bulat</strong>, jadi tepi gambar
                        terpotong mengikuti lingkaran; letakkan wajah di bagian atas gambar.</div>
                </div>
                <?php if (asisten_foto() !== ''): ?>
                    <label class="rasta kecil" style="gap:8px">
                        <input type="checkbox" name="asisten_foto_hapus" value="1">
                        Hapus foto &amp; kembali ke ilustrasi bawaan
                    </label>
                <?php endif; ?>
                <div class="aksi-form">
                    <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Foto Asisten</button>
                    <a class="tombol tombol-garis tombol-kecil" href="../asisten" target="_blank"
                       rel="noopener"><?= ikon('mata', 15) ?> Lihat Asisten</a>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="alert alert-info">
        <strong>Cara asisten memilih jawaban:</strong> pertanyaan pengunjung dicocokkan dengan
        <em>pertanyaan</em> dan <em>kata kunci</em> pada daftar ini lebih dulu; bila tidak ada yang
        cocok, asisten menyusun jawaban dari data website; bila tetap tidak ada, pengunjung diarahkan
        ke halaman Kontak &amp; panitia PPDB. Riwayat obrolan pengunjung <strong>tidak disimpan</strong>.
    </div>
    <form class="saringan" method="get" action="faq.php">
        <div class="medan">
            <label for="saringCariFaq">Cari</label>
            <input type="search" id="saringCariFaq" name="cari" value="<?= e($cari) ?>" placeholder="pertanyaan / jawaban / kata kunci">
        </div>
        <?php if ($cari !== ''): ?>
            <a class="tombol tombol-garis tombol-kecil" href="faq.php">Reset</a>
        <?php endif; ?>
    </form>

    <div class="tabel-wrap">
        <table class="tabel" id="tblFaq">
            <thead>
                <tr><th style="width:52px">No</th><th>Pertanyaan</th><th>Kata Kunci</th>
                    <th style="width:80px">Urutan</th><th style="width:100px">Status</th><th class="aksi-sel">Aksi</th></tr>
            </thead>
            <tbody>
                <?php if (!$daftar): ?>
                    <tr><td colspan="6" class="mut"><?= $cari !== ''
                        ? 'Tidak ada FAQ yang cocok dengan pencarian.'
                        : 'Belum ada FAQ. Asisten tetap menjawab dari data website — tambahkan FAQ untuk pertanyaan khusus.' ?></td></tr>
                <?php endif; ?>
                <?php foreach ($daftar as $i => $f): ?>
                    <tr>
                        <td class="kecil"><?= $i + 1 ?></td>
                        <td>
                            <strong><?= e((string) $f['pertanyaan']) ?></strong>
                            <div class="kecil mut"><?= e(potong((string) $f['jawaban'], 120)) ?></div>
                        </td>
                        <td class="kecil"><?= e((string) $f['kata_kunci'] !== '' ? (string) $f['kata_kunci'] : '-') ?></td>
                        <td class="kecil"><?= (int) $f['urutan'] ?></td>
                        <td><?= ((int) $f['aktif'] === 1) ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('faq.php?edit=' . (int) $f['id'] . '#formFaq', 'edit', 'Ubah FAQ') ?>
                            <?= tombol_mini('faq.php?aksi=geser&arah=naik&id=' . (int) $f['id'], 'naik', 'Naikkan urutan') ?>
                            <?= tombol_mini('faq.php?aksi=geser&arah=turun&id=' . (int) $f['id'], 'turun', 'Turunkan urutan') ?>
                            <?= tombol_mini('faq.php?aksi=toggle&id=' . (int) $f['id'], 'mata', 'Aktifkan/nonaktifkan') ?>
                            <?= form_hapus('hapus', (int) $f['id'], 'Hapus FAQ “' . $f['pertanyaan'] . '”?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="kartu" id="formFaq">
    <h2><?= $edit ? 'Ubah FAQ: ' . e((string) $edit['pertanyaan']) : 'Tambah FAQ' ?></h2>
    <form method="post" action="faq.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-baris">
            <label for="pertanyaan">Pertanyaan <span class="wajib">*</span></label>
            <input type="text" id="pertanyaan" name="pertanyaan" required maxlength="<?= (int) $KUNCI_PERTANYAAN_MAKS ?>"
                   placeholder="mis. Apakah tersedia antar-jemput siswa?" value="<?= e((string) ($edit['pertanyaan'] ?? '')) ?>">
            <div class="bantuan">Tulis seperti pertanyaan pengunjung. Teks ini juga tampil sebagai
                <strong>pilihan pertanyaan</strong> pada kotak obrolan.</div>
        </div>
        <div class="form-baris">
            <label for="kata_kunci">Kata Kunci Pemicu (dipisah koma)</label>
            <input type="text" id="kata_kunci" name="kata_kunci" maxlength="300"
                   placeholder="mis. antar jemput, anter jemput, transportasi" value="<?= e((string) ($edit['kata_kunci'] ?? '')) ?>">
            <div class="bantuan">Membantu asisten mengenali pertanyaan walau kalimatnya berbeda.
                Boleh berupa frasa; kosongkan bila cukup mengandalkan teks pertanyaan.</div>
        </div>
        <div class="form-baris">
            <label for="jawaban">Jawaban <span class="wajib">*</span></label>
            <textarea id="jawaban" name="jawaban" required style="min-height:150px"><?= e((string) ($edit['jawaban'] ?? '')) ?></textarea>
            <div class="bantuan">Satu baris = satu baris pada balasan obrolan (tekan Enter untuk baris baru).</div>
        </div>
        <div class="form-2">
            <div class="form-baris">
                <label for="urutan">Urutan</label>
                <input type="number" id="urutan" name="urutan" min="0" max="9999" value="<?= (int) ($edit['urutan'] ?? 0) ?>">
                <div class="bantuan">Kosongkan untuk diletakkan paling akhir.</div>
            </div>
            <div class="form-baris">
                <label>Status</label>
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>>
                    Tampilkan FAQ ini pada asisten
                </label>
            </div>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan FAQ</button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="faq.php">Batal</a><?php endif; ?>
        </div>
    </form>
</div>
<?php admin_bawah(); ?>
