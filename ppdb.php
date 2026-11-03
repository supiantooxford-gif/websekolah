<?php
/**
 * PPDB — informasi penerimaan peserta didik baru + formulir pendaftaran online.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// ALAMAT RAPI: alamat lama `ppdb.php?…` dialihkan 301 ke `ppdb` (nomor pendaftaran ikut dibawa).
rute_lama_301('ppdb', $_GET);

$galat = [];
$gel = gelombang_aktif();
$dibuka = ppdb_dibuka();
$gelombang = all('SELECT * FROM ppdb_gelombang ORDER BY urutan, id');

// ⚠️ `ppdb_butir()` kini dipusatkan di `inc/helpers.php` (dipakai juga oleh inc/asisten.php).
// ---------------------------------------------------------------- proses pendaftaran
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $angkaA = (int) ($_SESSION['ppdb_angka_a'] ?? 0);
    $angkaB = (int) ($_SESSION['ppdb_angka_b'] ?? 0);
    $kiriman = (int) ($_SESSION['ppdb_terkirim'] ?? 0);

    if (!csrf_sah()) {
        $galat[] = 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu kirim ulang.';
    }
    if (!$dibuka) {
        $galat[] = ppdb_tutup_pesan() . ' Silakan hubungi panitia PPDB untuk informasi lebih lanjut.';
    }
    if ($kiriman >= PPDB_MAKS_SESI) {
        $galat[] = 'Anda sudah mengirim formulir beberapa kali dalam sesi ini. Hubungi panitia bila pendaftaran Anda belum tersimpan.';
    }
    if (kiriman_terlalu_besar()) {
        $galat[] = 'Total ukuran berkas yang diunggah melebihi batas server. Kurangi ukuran berkas atau unggah berkas terpisah.';
    }

    $d = [];
    foreach (['nama', 'nisn', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'asal_sekolah', 'nama_ayah',
        'nama_ibu', 'wali', 'telepon', 'email', 'alamat', 'jalur', 'catatan_pendaftar'] as $k) {
        $d[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $d['jenis_kelamin'] = $d['jenis_kelamin'] === 'P' ? 'P' : 'L';

    if ($d['nama'] === '') {
        $galat[] = 'Nama lengkap wajib diisi.';
    }
    if ($d['nisn'] !== '' && !preg_match('#^\d{6,12}$#', $d['nisn'])) {
        $galat[] = 'NISN harus berupa 6–12 angka.';
    }
    if ($d['telepon'] === '') {
        $galat[] = 'Nomor HP/WhatsApp wajib diisi agar panitia dapat menghubungi Anda.';
    }
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $galat[] = 'Alamat email tidak valid.';
    }
    if ($d['tanggal_lahir'] !== '' && !preg_match('#^\d{4}-\d{2}-\d{2}$#', $d['tanggal_lahir'])) {
        $galat[] = 'Format tanggal lahir tidak dikenali.';
    }
    if ((int) ($_POST['jawab'] ?? -999) !== $angkaA + $angkaB) {
        $galat[] = 'Jawaban pengaman tidak tepat. Silakan hitung kembali.';
    }
    if (empty($_POST['setuju'])) {
        $galat[] = 'Centang pernyataan bahwa data yang diisi benar.';
    }

    // unggahan berkas (opsional, maksimal 15 MB per berkas)
    $berkas = [];
    if (!$galat) {
        foreach (array_keys(PPDB_BERKAS_LABEL) as $kunci) {
            if (count($berkas) >= PPDB_MAKS_BERKAS) {
                break;
            }
            $up = unggah_gambar($kunci, PPDB_BERKAS_LABEL[$kunci] . ' (' . $d['nama'] . ')', BERKAS_EKSTENSI, BERKAS_MAKS_BYTE);
            if (!empty($up['kosong'])) {
                continue;
            }
            if (!$up['ok']) {
                $galat[] = PPDB_BERKAS_LABEL[$kunci] . ': ' . $up['pesan'];
                continue;
            }
            $berkas[$kunci] = ['url' => $up['url'], 'nama' => PPDB_BERKAS_LABEL[$kunci]];
        }
    }

    if (!$galat) {
        $nomor = ppdb_nomor_baru((int) ($gel['id'] ?? 0));
        q('INSERT INTO ppdb_pendaftar (nomor, gelombang_id, nama, nisn, jenis_kelamin, tempat_lahir, tanggal_lahir,
            asal_sekolah, nama_ayah, nama_ibu, wali, telepon, email, alamat, jalur, catatan_pendaftar, berkas,
            status, nilai_seleksi, catatan_panitia, tanggal_daftar, diubah, ip)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $nomor, (int) ($gel['id'] ?? 0), $d['nama'], $d['nisn'], $d['jenis_kelamin'], $d['tempat_lahir'], $d['tanggal_lahir'],
            $d['asal_sekolah'], $d['nama_ayah'], $d['nama_ibu'], $d['wali'], $d['telepon'], $d['email'], $d['alamat'],
            $d['jalur'] !== '' ? $d['jalur'] : (string) ($gel['jalur'] ?? ''),
            potong($d['catatan_pendaftar'], 1000, ''), json_encode($berkas, JSON_UNESCAPED_UNICODE),
            'baru', '', '', date('Y-m-d H:i:s'), '', ip_pengunjung(),
        ]);
        $_SESSION['ppdb_terkirim'] = $kiriman + 1;
        unset($_SESSION['ppdb_angka_a'], $_SESSION['ppdb_angka_b']);
        redirect('ppdb?nomor=' . rawurlencode($nomor));
    }
}

$angkaA = random_int(3, 9);
$angkaB = random_int(1, 9);
$_SESSION['ppdb_angka_a'] = $angkaA;
$_SESSION['ppdb_angka_b'] = $angkaB;

$nomorSukses = trim((string) ($_GET['nomor'] ?? ''));
$s = sekolah();

situs_atas(['judul' => 'PPDB Online', 'aktif' => 'ppdb']);
hero_halaman('Penerimaan Peserta Didik Baru',
    'Tahun Ajaran ' . ($gel['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1))) . ' — ' . $s['nama'],
    [['label' => 'PPDB', 'tautan' => '']]);
?>
<section class="isi-halaman">
    <div class="wadah">
        <?php if ($nomorSukses !== ''): ?>
            <div class="kartu-hasil tengah mb24" style="margin-bottom:26px">
                <div style="color:var(--hijau);margin-bottom:10px"><?= ikon('tanda', 54) ?></div>
                <h2 style="margin:0 0 6px">Pendaftaran Berhasil</h2>
                <p class="mut">Simpan nomor pendaftaran berikut untuk memeriksa hasil seleksi.</p>
                <div class="nomor"><?= e($nomorSukses) ?></div>
                <p class="kecil mut">Panitia akan memverifikasi data dan berkas Anda. Hasil seleksi dapat dilihat pada halaman
                    <a href="ppdb/status">Cek Status Pendaftaran</a>.</p>
                <div class="aksi-form" style="justify-content:center">
                    <a class="tombol tombol-emas" href="ppdb/status"><?= ikon('search', 16) ?> Cek Status Pendaftaran</a>
                    <a class="tombol tombol-garis" href="ppdb">Kembali ke Info PPDB</a>
                </div>
            </div>
        <?php endif; ?>

        <?php $alurPpdb = ppdb_butir(pengaturan('ppdb_alur')); ?>
        <?php if ($alurPpdb): ?>
            <div class="langkah-grid">
                <?php foreach ($alurPpdb as $i => $l): ?>
                    <div class="langkah">
                        <div class="no"><?= $i + 1 ?></div>
                        <div class="isi"><h3><?= e($l) ?></h3></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="langkah-ket">Ikuti langkah di atas agar proses pendaftaran berjalan lancar.</p>
        <?php endif; ?>

        <div class="grid g21 mt24" style="margin-top:28px">
            <div>
                <div class="artikel">
                    <h2 style="margin-top:0">Informasi PPDB</h2>
                    <?= nl(pengaturan('ppdb_isi')) ?>
                    <div class="grid g2 mt16">
                        <div>
                            <h3>Persyaratan</h3>
                            <?php /* Diberi NOMOR 1, 2, 3, … atas permintaan pemilik agar daftarnya rapi.
                                     Nomornya dicetak dari PHP (bukan CSS counter) supaya pasti terlihat
                                     dan mudah diperiksa; satu baris pengaturan = satu nomor. */ ?>
                            <ol class="daftar-syarat bernomor">
                                <?php foreach (ppdb_butir(pengaturan('ppdb_syarat')) as $i => $sy): ?>
                                    <li><span class="nomor"><?= $i + 1 ?></span><span><?= e($sy) ?></span></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                        <div>
                            <h3>Jadwal &amp; Ketentuan</h3>
                            <ul class="daftar-syarat">
                                <?php foreach (ppdb_butir(pengaturan('ppdb_jadwal')) as $jd): ?>
                                    <li><?= ikon('kalender', 16) ?><span><?= e($jd) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="artikel mt24" style="margin-top:26px">
                    <h2 style="margin-top:0">Formulir Pendaftaran Online</h2>
                    <?php if (!$dibuka): ?>
                        <div class="alert alert-info">
                            <strong><?= e(ppdb_tutup_pesan()) ?></strong>
                            Silakan cek kembali halaman ini secara berkala, atau hubungi panitia
                            <?= e(pengaturan('ppdb_kontak', 'PPDB')) ?> untuk informasi jadwal pembukaan.
                        </div>
                    <?php else: ?>
                        <?php foreach ($galat as $g): ?><div class="alert alert-err"><?= e($g) ?></div><?php endforeach; ?>
                        <form method="post" action="ppdb" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <h3>Data Calon Peserta Didik</h3>
                            <div class="form-2">
                                <div class="form-baris">
                                    <label for="nama">Nama Lengkap <span class="wajib">*</span></label>
                                    <input type="text" id="nama" name="nama" maxlength="120" required value="<?= e($_POST['nama'] ?? '') ?>">
                                </div>
                                <div class="form-baris">
                                    <label for="nisn">NISN</label>
                                    <input type="text" id="nisn" name="nisn" maxlength="12" inputmode="numeric" value="<?= e($_POST['nisn'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-3">
                                <div class="form-baris">
                                    <label for="jenis_kelamin">Jenis Kelamin <span class="wajib">*</span></label>
                                    <select id="jenis_kelamin" name="jenis_kelamin" required>
                                        <option value="L" <?= ($_POST['jenis_kelamin'] ?? '') === 'P' ? '' : 'selected' ?>>Laki-laki</option>
                                        <option value="P" <?= ($_POST['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                                    </select>
                                </div>
                                <div class="form-baris">
                                    <label for="tempat_lahir">Tempat Lahir</label>
                                    <input type="text" id="tempat_lahir" name="tempat_lahir" maxlength="80" value="<?= e($_POST['tempat_lahir'] ?? '') ?>">
                                </div>
                                <div class="form-baris">
                                    <label for="tanggal_lahir">Tanggal Lahir</label>
                                    <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= e($_POST['tanggal_lahir'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-2">
                                <div class="form-baris">
                                    <label for="asal_sekolah">Sekolah/Madrasah Asal</label>
                                    <input type="text" id="asal_sekolah" name="asal_sekolah" maxlength="160" value="<?= e($_POST['asal_sekolah'] ?? '') ?>">
                                </div>
                                <div class="form-baris">
                                    <label for="jalur">Jalur Pendaftaran</label>
                                    <select id="jalur" name="jalur">
                                        <?php
                                        $pilihanJalur = [];
                                        foreach ($gelombang as $g) {
                                            foreach (ppdb_butir((string) $g['jalur']) as $j) {
                                                $pilihanJalur[$j] = true;
                                            }
                                        }
                                        if (!$pilihanJalur) {
                                            $pilihanJalur = ['Reguler' => true];
                                        }
                                        ?>
                                        <?php foreach (array_keys($pilihanJalur) as $j): ?>
                                            <option value="<?= e($j) ?>" <?= ($_POST['jalur'] ?? '') === $j ? 'selected' : '' ?>><?= e($j) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <h3>Data Orang Tua / Wali</h3>
                            <div class="form-3">
                                <div class="form-baris">
                                    <label for="nama_ayah">Nama Ayah</label>
                                    <input type="text" id="nama_ayah" name="nama_ayah" maxlength="120" value="<?= e($_POST['nama_ayah'] ?? '') ?>">
                                </div>
                                <div class="form-baris">
                                    <label for="nama_ibu">Nama Ibu</label>
                                    <input type="text" id="nama_ibu" name="nama_ibu" maxlength="120" value="<?= e($_POST['nama_ibu'] ?? '') ?>">
                                </div>
                                <div class="form-baris">
                                    <label for="wali">Nama Wali</label>
                                    <input type="text" id="wali" name="wali" maxlength="120" value="<?= e($_POST['wali'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-3">
                                <div class="form-baris">
                                    <label for="telepon">No. HP / WhatsApp <span class="wajib">*</span></label>
                                    <input type="tel" id="telepon" name="telepon" maxlength="20" required value="<?= e($_POST['telepon'] ?? '') ?>">
                                </div>
                                <div class="form-baris">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" maxlength="120" value="<?= e($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="form-baris">
                                    <label for="alamat">Alamat</label>
                                    <input type="text" id="alamat" name="alamat" maxlength="200" value="<?= e($_POST['alamat'] ?? '') ?>">
                                </div>
                            </div>

                            <h3>Berkas Persyaratan</h3>
                            <p class="kecil mut">Berkas boleh berupa PDF atau gambar (JPG/PNG), maksimal 15 MB per berkas. Unggahan bersifat opsional namun mempercepat verifikasi.</p>
                            <div class="form-2">
                                <?php foreach (PPDB_BERKAS_LABEL as $kunci => $label): ?>
                                    <div class="form-baris">
                                        <label for="f_<?= e($kunci) ?>"><?= e($label) ?></label>
                                        <input type="file" id="f_<?= e($kunci) ?>" name="<?= e($kunci) ?>" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="form-baris">
                                <label for="catatan_pendaftar">Catatan Tambahan</label>
                                <textarea id="catatan_pendaftar" name="catatan_pendaftar" maxlength="1000"><?= e($_POST['catatan_pendaftar'] ?? '') ?></textarea>
                            </div>
                            <div class="form-baris">
                                <label for="jawab">Pengaman: berapa hasil <?= $angkaA ?> + <?= $angkaB ?> ? <span class="wajib">*</span></label>
                                <input type="number" id="jawab" name="jawab" required style="max-width:160px">
                            </div>
                            <div class="form-baris">
                                <label class="rasta" style="font-weight:500;gap:10px">
                                    <input type="checkbox" name="setuju" value="1" <?= !empty($_POST['setuju']) ? 'checked' : '' ?> required>
                                    <span>Saya menyatakan bahwa data yang saya isi adalah benar dan dapat dipertanggungjawabkan.</span>
                                </label>
                            </div>
                            <div class="aksi-form">
                                <button type="submit" class="tombol tombol-emas"><?= ikon('simpan', 17) ?> Kirim Pendaftaran</button>
                                <a class="tombol tombol-garis" href="ppdb/status">Cek Status Pendaftaran</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <aside>
                <div class="sisi-kartu">
                    <h3>Gelombang Pendaftaran</h3>
                    <?php foreach ($gelombang as $g): ?>
                        <div class="agenda-baris" style="padding:10px 0">
                            <div>
                                <strong style="display:block;color:var(--hijau-tua)"><?= e($g['nama']) ?></strong>
                                <span class="kecil mut">
                                    <?= e(tanggal_id($g['mulai']) ?: '-') ?> s.d. <?= e(tanggal_id($g['selesai']) ?: '-') ?>
                                    <?php if ((int) $g['kuota'] > 0): ?> · Kuota <?= (int) $g['kuota'] ?><?php endif; ?>
                                </span>
                                <div><?= ((int) $g['aktif'] === 1) ? '<span class="pil pil-terima">Dibuka</span>' : '<span class="pil pil-baru">Ditutup</span>' ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="sisi-kartu mt16">
                    <h3>Butuh Bantuan?</h3>
                    <p class="kecil"><?= e(pengaturan('ppdb_kontak')) ?></p>
                    <?php $wa = sek('whatsapp', sek('telepon')); ?>
                    <?php if ($wa !== ''): ?>
                        <a class="tombol tombol-kecil" href="https://wa.me/<?= e(wa_internasional($wa)) ?>" target="_blank" rel="noopener noreferrer"><?= ikon('whatsapp', 16) ?> Hubungi Panitia</a>
                    <?php endif; ?>
                </div>
                <div class="sisi-kartu mt16">
                    <h3>Hasil Seleksi</h3>
                    <p class="kecil">Cek status pendaftaran Anda dengan nomor pendaftaran dan NISN.</p>
                    <a class="tombol tombol-garis tombol-kecil" href="ppdb/status"><?= ikon('search', 16) ?> Cek Status</a>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php situs_bawah(); ?>
