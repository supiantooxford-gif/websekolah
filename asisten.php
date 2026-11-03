<?php
/**
 * HALAMAN ASISTEN (cadangan tanpa JavaScript).
 *
 * Kotak obrolan mengambang di halaman-halaman lain berjalan dengan JavaScript. Bila skrip tidak
 * dimuat, tombol mengambang mengarah ke halaman ini dan pengunjung tetap dapat bertanya lewat
 * formulir biasa (POST → Redirect → GET, pola yang sama dengan formulir kontak).
 *
 * Riwayat percakapan halaman ini hanya disimpan SEMENTARA di sesi pengunjung
 * (`$_SESSION['asisten_riwayat']`, maksimal ASISTEN_RIWAYAT_MAKS pesan) — TIDAK masuk basis data.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$pesanBaru = '';
$galat = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $pesan = trim((string) ($_POST['pesan'] ?? ''));
    if (function_exists('mb_substr')) {
        $pesan = mb_substr($pesan, 0, ASISTEN_PESAN_MAKS, 'UTF-8');
    } else {
        $pesan = substr($pesan, 0, ASISTEN_PESAN_MAKS);
    }
    if (trim(preg_replace('#\s+#', ' ', $pesan)) === '') {
        $galat = 'Silakan tuliskan pertanyaan terlebih dahulu.';
    } elseif (asisten_sisa_tanya() <= 0) {
        $galat = asisten_pesan_batas();
    } else {
        asisten_catat_tanya();
        $hasil = asisten_jawab($pesan);
        $riwayat = $_SESSION['asisten_riwayat'] ?? [];
        $riwayat[] = ['tanya' => $pesan, 'jawab' => $hasil['jawaban'], 'sumber' => $hasil['sumber'], 'waktu' => date('H:i')];
        $_SESSION['asisten_riwayat'] = array_slice($riwayat, -ASISTEN_RIWAYAT_MAKS);
        redirect('asisten#percakapan');
    }
}

// ALAMAT RAPI: alamat lama `asisten.php` dialihkan 301 ke `asisten` (pertanyaan & aksi dibawa).
// Diletakkan SESUDAH blok POST di atas: kiriman formulir gaya lama tetap diproses lebih dulu.
rute_lama_301('asisten', $_GET);

if (($_GET['aksi'] ?? '') === 'bersihkan') {
    unset($_SESSION['asisten_riwayat']);
    flash('Percakapan dikosongkan.', 'ok');
    redirect('asisten');
}

// Klik salah satu pertanyaan pilihan (`?tanya=…`) ditangani sama seperti mengirim formulir,
// sehingga daftar pertanyaan pada kolom kanan tetap berfungsi walau skrip gagal dimuat.
$tanyaGet = trim((string) ($_GET['tanya'] ?? ''));
if ($tanyaGet !== '' && asisten_sisa_tanya() > 0) {
    asisten_catat_tanya();
    $hasil = asisten_jawab($tanyaGet);
    $riwayat = $_SESSION['asisten_riwayat'] ?? [];
    $riwayat[] = ['tanya' => $tanyaGet, 'jawab' => $hasil['jawaban'], 'sumber' => $hasil['sumber'], 'waktu' => date('H:i')];
    $_SESSION['asisten_riwayat'] = array_slice($riwayat, -ASISTEN_RIWAYAT_MAKS);
    redirect('asisten#percakapan');
}

$riwayat = $_SESSION['asisten_riwayat'] ?? [];
$saran = asisten_saran(6);

situs_atas(['judul' => 'Tanya Asisten', 'aktif' => '']);
hero_halaman('Tanya Asisten Website',
    'Silakan bertanya tentang profil, PPDB, data guru & siswa, prestasi, galeri, agenda, atau kontak madrasah.',
    [['label' => 'Asisten', 'tautan' => '']]);
?>
<section class="isi-halaman">
    <div class="wadah">
        <div class="grid g21">
            <div class="kartu" id="percakapan">
                <div class="kartu-kepala">
                    <div>
                        <h2 style="margin:0 0 4px">Asisten AI <?= e(sekolah()['nama']) ?></h2>
                        <p class="mut kecil mb0">Jawaban disusun otomatis dari data website
                            (<?= angka(asisten_angka()['halaman']) ?> halaman)<?= asisten_faq_jumlah(true) > 0
                                ? ' dan ' . angka(asisten_faq_jumlah(true)) . ' pertanyaan yang diisi admin' : '' ?>.
                            Percakapan tidak disimpan.</p>
                    </div>
                    <?php if ($riwayat): ?>
                        <a class="tombol tombol-garis tombol-kecil" href="asisten?aksi=bersihkan"
                           data-konfirmasi="Kosongkan percakapan pada halaman ini?"><?= ikon('trash', 15) ?> Kosongkan</a>
                    <?php endif; ?>
                </div>

                <?php if ($galat !== ''): ?><div class="alert alert-err"><?= e($galat) ?></div><?php endif; ?>

                <div class="asisten-riwayat">
                    <div class="asisten-balon asisten-asisten">
                        <?= asisten_ava('asisten-ikon') ?>
                        <div class="asisten-teks"><?= nl($riwayat ? 'Lanjutkan pertanyaan Bapak/Ibu.' : asisten_sapaan()) ?></div>
                    </div>
                    <?php foreach ($riwayat as $r): ?>
                        <div class="asisten-balon asisten-pengguna">
                            <div class="asisten-teks"><?= nl((string) $r['tanya']) ?></div>
                        </div>
                        <div class="asisten-balon asisten-asisten">
                            <?= asisten_ava('asisten-ikon') ?>
                            <div class="asisten-teks"><?= nl((string) $r['jawab']) ?>
                                <span class="asisten-ket"><?= e((string) $r['waktu']) ?> ·
                                    <?= $r['sumber'] === 'faq' ? 'jawaban admin' : ($r['sumber'] === 'website' ? 'data website' : 'sistem') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="post" action="asisten" class="asisten-form">
                    <?= csrf_field() ?>
                    <div class="form-baris">
                        <label for="pesan">Pertanyaan Anda</label>
                        <input type="text" id="pesan" name="pesan" maxlength="<?= (int) ASISTEN_PESAN_MAKS ?>"
                               required placeholder="mis. bagaimana cara mendaftar murid baru?">
                    </div>
                    <div class="aksi-form">
                        <button type="submit" class="tombol"><?= ikon('send', 16) ?> Kirim Pertanyaan</button>
                    </div>
                </form>
            </div>

            <aside>
                <div class="sisi-kartu">
                    <h3>Pertanyaan yang sering ditanyakan</h3>
                    <ul class="sisi-menu">
                        <?php foreach ($saran as $p): ?>
                            <li><a href="asisten?tanya=<?= rawurlencode($p) ?>#percakapan"><?= e($p) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="sisi-kartu mt16">
                    <h3>Butuh Bantuan Lain?</h3>
                    <p class="kecil mut">Asisten ini menjawab berdasarkan data pada website. Untuk hal yang belum
                        tercakup, silakan hubungi madrasah melalui halaman Kontak.</p>
                    <a class="tombol tombol-garis tombol-kecil" href="kontak"><?= ikon('mail', 15) ?> Buka Halaman Kontak</a>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php situs_bawah(); ?>
