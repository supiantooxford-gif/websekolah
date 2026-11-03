<?php
/**
 * PROFIL LENGKAP (ISIAN) — isi halaman "Profil Lengkap" pada website.
 *
 * Isinya adalah kolom tabel `sekolah` (identitas tambahan + VISI/MISI/TUJUAN) sehingga tidak ada
 * tabel baru; halaman publiknya dirender `inc/profil.php` lewat menu berjenis `profil_lengkap`.
 * Kolom Visi/Misi/Tujuan memakai `potong_baris()` — satu baris = satu butir (jangan `potong()`,
 * yang melebur seluruh baris baru menjadi spasi).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

// `akreditasi` (NILAI/peringkat) & `akreditasi_tgl` (tanggal) diisi dari halaman ini karena
// keduanya tampil sebagai satu baris "Akreditasi" pada tabel IDENTITAS halaman Profil Lengkap.
$KUNCI = ['kode_pos', 'npwp', 'status_madrasah', 'waktu_belajar', 'sk_ijop', 'sk_ijop_tgl',
    'tahun_berdiri', 'akreditasi', 'akreditasi_tgl', 'penyelenggara', 'sk_menkumham',
    'jarak_kecamatan', 'jarak_kabupaten', 'bangunan'];
// Medan multi-baris (satu baris = satu butir/paragraf).
$KUNCI_BARIS = ['visi', 'misi', 'tujuan'];

// Dokumen IJOP & Akreditasi (gambar/PDF) — unggah lewat media proxy, yang disimpan hanya URL-nya.
$BERKAS = ['berkas_ijop' => 'Dokumen IJOP', 'berkas_akreditasi' => 'Dokumen Akreditasi'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $data = [];
    foreach ($KUNCI as $k) {
        $data[$k] = potong(trim((string) ($_POST[$k] ?? '')), 200, '');
    }
    foreach ($KUNCI_BARIS as $k) {
        $data[$k] = potong_baris((string) ($_POST[$k] ?? ''), 4000);
    }
    // Unggah/hapus dokumen: bagian yang TIDAK dikirim (formulir lama) tidak diubah.
    $pesanBerkas = [];
    $galatBerkas = [];
    $lama = sekolah();
    foreach ($BERKAS as $k => $label) {
        $urlLama = (string) ($lama[$k] ?? '');
        if (!empty($_POST[$k . '_hapus'])) {
            $data[$k] = '';
            $pesanBerkas[] = $label . ' dihapus.';
            continue;
        }
        $up = unggah_berkas($k, $label, BERKAS_EKSTENSI, BERKAS_MAKS_BYTE);
        if (!empty($up['kosong'])) {
            continue;   // tidak ada berkas baru → biarkan yang lama
        }
        if (empty($up['ok'])) {
            $galatBerkas[] = $up['pesan'];
            continue;
        }
        $data[$k] = (string) $up['url'];
        $pesanBerkas[] = $label . ' berhasil diunggah.';
    }
    sekolah_simpan($data);
    flash('Isian Profil Lengkap disimpan. ' . trim(implode(' ', $pesanBerkas) . ' ' . implode(' ', $galatBerkas)),
        $galatBerkas ? 'err' : 'ok');
    redirect('profil.php');
}

$s = sekolah();
$menuProfil = one("SELECT * FROM menu WHERE jenis = 'profil_lengkap' LIMIT 1");
admin_atas('Profil Lengkap (Isian)');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2 style="margin:0 0 4px">Isian Halaman “Profil Lengkap”</h2>
            <p class="mut kecil mb0">Seluruh tulisan di bawah ini tampil pada halaman <strong>Profil Lengkap</strong>
                di website. Nama madrasah, alamat, NPSN/NSM, telepon, email, situs, akreditasi, dan nama kepala
                diambil dari <a href="identitas.php">Identitas Sekolah</a>.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="tombol tombol-garis tombol-kecil" href="identitas.php"><?= ikon('building', 15) ?> Identitas Sekolah</a>
            <?php if ($menuProfil): ?>
                <a class="tombol tombol-garis tombol-kecil" target="_blank" rel="noopener" href="../halaman/<?= e((string) $menuProfil['slug']) ?>"><?= ikon('mata', 15) ?> Lihat Halaman</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<form method="post" action="profil.php" enctype="multipart/form-data" id="formProfilLengkap">
    <?= csrf_field() ?>
    <div class="grid g21">
        <div class="kartu">
            <h2>Identitas Tambahan</h2>
            <p class="mut kecil">Daftar ini menjadi baris tabel <strong>IDENTITAS MADRASAH</strong> pada halaman profil.
                Baris yang dikosongkan tidak ditampilkan.</p>
            <div class="form-2">
                <div class="form-baris">
                    <label for="kode_pos">Kode Pos</label>
                    <input type="text" id="kode_pos" name="kode_pos" maxlength="10" value="<?= e(sekolah()['kode_pos'] ?? '') ?>">
                </div>
                <div class="form-baris">
                    <label for="npwp">NPWP</label>
                    <input type="text" id="npwp" name="npwp" maxlength="40" value="<?= e(sekolah()['npwp'] ?? '') ?>">
                </div>
            </div>
            <div class="form-2">
                <div class="form-baris">
                    <label for="status_madrasah">Status Madrasah</label>
                    <input type="text" id="status_madrasah" name="status_madrasah" maxlength="30" list="saranStatus" value="<?= e(sekolah()['status_madrasah'] ?? '') ?>">
                    <datalist id="saranStatus"><option value="Swasta"></option><option value="Negeri"></option></datalist>
                </div>
                <div class="form-baris">
                    <label for="waktu_belajar">Waktu Belajar</label>
                    <input type="text" id="waktu_belajar" name="waktu_belajar" maxlength="30" list="saranWaktu" value="<?= e(sekolah()['waktu_belajar'] ?? '') ?>">
                    <datalist id="saranWaktu"><option value="Pagi"></option><option value="Siang"></option><option value="Pagi dan Siang"></option></datalist>
                </div>
            </div>
            <div class="form-2">
                <div class="form-baris">
                    <label for="sk_ijop">No. SK IJOP</label>
                    <input type="text" id="sk_ijop" name="sk_ijop" maxlength="60" value="<?= e(sekolah()['sk_ijop'] ?? '') ?>">
                </div>
                <div class="form-baris">
                    <label for="sk_ijop_tgl">Tanggal SK IJOP</label>
                    <input type="text" id="sk_ijop_tgl" name="sk_ijop_tgl" maxlength="40" placeholder="mis. 21 Maret 2022" value="<?= e(sekolah()['sk_ijop_tgl'] ?? '') ?>">
                </div>
            </div>
            <div class="form-baris">
                <label for="tahun_berdiri">Tahun Berdiri</label>
                <input type="text" id="tahun_berdiri" name="tahun_berdiri" maxlength="40" placeholder="mis. 10 Juli 2020" value="<?= e(sekolah()['tahun_berdiri'] ?? '') ?>">
            </div>
            <div class="form-2">
                <div class="form-baris">
                    <label for="akreditasi">Nilai / Peringkat Akreditasi</label>
                    <input type="text" id="akreditasi" name="akreditasi" maxlength="20" list="saranAkreditasi"
                           placeholder="mis. A" value="<?= e(sekolah()['akreditasi'] ?? '') ?>">
                    <div class="bantuan">Tampil pada baris <strong>Akreditasi</strong> di tabel Identitas
                        halaman Profil Lengkap. Boleh peringkat (A/B/C) atau nilai angka (mis. 95).</div>
                    <datalist id="saranAkreditasi">
                        <option value="A"></option><option value="B"></option><option value="C"></option>
                        <option value="Unggul"></option><option value="Baik Sekali"></option><option value="Baik"></option>
                    </datalist>
                </div>
                <div class="form-baris">
                    <label for="akreditasi_tgl">Tanggal Akreditasi</label>
                    <input type="text" id="akreditasi_tgl" name="akreditasi_tgl" maxlength="40"
                           placeholder="mis. 21 Maret 2022" value="<?= e(sekolah()['akreditasi_tgl'] ?? '') ?>">
                    <div class="bantuan">Ditampilkan sebagai kolom “Tanggal” pada baris Akreditasi.</div>
                </div>
            </div>
            <div class="form-baris">
                <label for="penyelenggara">Penyelenggara</label>
                <input type="text" id="penyelenggara" name="penyelenggara" maxlength="160" value="<?= e(sekolah()['penyelenggara'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="sk_menkumham">SK Menkumham</label>
                <input type="text" id="sk_menkumham" name="sk_menkumham" maxlength="120" value="<?= e(sekolah()['sk_menkumham'] ?? '') ?>">
            </div>
            <div class="form-2">
                <div class="form-baris">
                    <label for="jarak_kecamatan">Jarak ke Kecamatan</label>
                    <input type="text" id="jarak_kecamatan" name="jarak_kecamatan" maxlength="30" placeholder="mis. 5 KM" value="<?= e(sekolah()['jarak_kecamatan'] ?? '') ?>">
                </div>
                <div class="form-baris">
                    <label for="jarak_kabupaten">Jarak ke Kabupaten</label>
                    <input type="text" id="jarak_kabupaten" name="jarak_kabupaten" maxlength="30" placeholder="mis. 50 KM" value="<?= e(sekolah()['jarak_kabupaten'] ?? '') ?>">
                </div>
            </div>
            <div class="form-baris">
                <label for="bangunan">Bangunan Madrasah</label>
                <input type="text" id="bangunan" name="bangunan" maxlength="60" list="saranBangunan" value="<?= e(sekolah()['bangunan'] ?? '') ?>">
                <datalist id="saranBangunan">
                    <option value="Milik Sendiri"></option><option value="Sewa"></option>
                    <option value="Pinjam Pakai"></option><option value="Milik Yayasan"></option>
                </datalist>
            </div>

            <h3 class="mt24">Dokumen (IJOP &amp; Akreditasi)</h3>
            <p class="mut kecil">Unggah berkas <strong>IJOP</strong> dan <strong>Akreditasi</strong> (gambar JPG/PNG atau PDF, maks
                <?= e(ukuran_teks(BERKAS_MAKS_BYTE)) ?>). Keduanya tampil sebagai tombol
                <strong>Lihat Ijop</strong> &amp; <strong>Lihat Akreditasi</strong> pada halaman Profil Lengkap —
                tombol tidak muncul bila berkasnya belum diunggah.</p>
            <?php foreach ($BERKAS as $k => $label): ?>
                <?php $url = trim((string) (sekolah()[$k] ?? '')); ?>
                <div class="form-baris">
                    <label for="<?= e($k) ?>"><?= e($label) ?></label>
                    <div class="rasta" style="gap:12px;flex-wrap:wrap;align-items:center">
                        <span class="pratinjau-berkas">
                            <?php if ($url !== ''): ?>
                                <a class="btn-lihat" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= ikon('mata', 14) ?> Lihat berkas</a>
                            <?php else: ?>
                                <span class="mut kecil">belum ada berkas</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <input type="file" id="<?= e($k) ?>" name="<?= e($k) ?>" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <?php if ($url !== ''): ?>
                        <label class="rasta" style="gap:9px;font-weight:500;margin-top:6px">
                            <input type="checkbox" name="<?= e($k) ?>_hapus" value="1"> Hapus berkas ini
                        </label>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div>
            <div class="kartu">
                <h2>Visi</h2>
                <div class="form-baris">
                    <label for="visi">Visi Madrasah</label>
                    <textarea id="visi" name="visi" style="min-height:110px"><?= e(sekolah()['visi'] ?? '') ?></textarea>
                    <div class="bantuan">Ditampilkan dengan huruf besar pada halaman profil.</div>
                </div>
            </div>
            <div class="kartu mt24" style="margin-top:22px">
                <h2>Misi</h2>
                <div class="form-baris">
                    <label for="misi">Misi Madrasah (satu per baris, jadi nomor 1, 2, 3, …)</label>
                    <textarea id="misi" name="misi" style="min-height:150px"><?= e(sekolah()['misi'] ?? '') ?></textarea>
                    <div class="bantuan">Tekan <strong>Enter</strong> setiap selesai satu misi — satu baris menjadi satu butir bernomor.</div>
                </div>
            </div>
            <div class="kartu mt24" style="margin-top:22px">
                <h2>Tujuan</h2>
                <div class="form-baris">
                    <label for="tujuan">Tujuan Madrasah (satu per baris, jadi nomor 1, 2, 3, …)</label>
                    <textarea id="tujuan" name="tujuan" style="min-height:150px"><?= e(sekolah()['tujuan'] ?? '') ?></textarea>
                    <div class="bantuan">Tekan <strong>Enter</strong> setiap selesai satu tujuan.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="aksi-form mt16" style="margin-top:20px">
        <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Isian Profil</button>
        <?php if ($menuProfil): ?>
            <a class="tombol tombol-garis" target="_blank" rel="noopener" href="../halaman/<?= e((string) $menuProfil['slug']) ?>"><?= ikon('mata', 16) ?> Lihat Halaman Profil</a>
        <?php endif; ?>
    </div>
</form>
<?php admin_bawah(); ?>
