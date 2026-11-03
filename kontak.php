<?php
/**
 * Kontak: informasi kontak, peta, dan formulir pesan (masuk ke panel admin).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// ALAMAT RAPI: alamat lama `kontak.php` dialihkan 301 ke `kontak` (parameter ikut dibawa).
rute_lama_301('kontak', $_GET);

$galat = [];
$sukses = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_sah()) {
        $galat[] = 'Sesi formulir sudah kedaluwarsa. Silakan muat ulang halaman lalu kirim ulang.';
    }
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $telepon = trim((string) ($_POST['telepon'] ?? ''));
    $subjek = trim((string) ($_POST['subjek'] ?? ''));
    $pesan = trim((string) ($_POST['pesan'] ?? ''));

    $soal = (int) ($_SESSION['soal_pesan'] ?? -1);
    $jawab = (int) ($_POST['jawab'] ?? -999);
    $sesi = (int) ($_SESSION['pesan_terkirim'] ?? 0);

    if ($jawab !== $soal) {
        $galat[] = 'Jawaban pengaman tidak tepat. Silakan hitung kembali.';
    }
    if ($nama === '' || $pesan === '') {
        $galat[] = 'Nama dan pesan wajib diisi.';
    }
    if ($email === '' && $telepon === '') {
        $galat[] = 'Isi minimal satu kontak yang dapat dihubungi (email atau telepon).';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $galat[] = 'Alamat email tidak valid.';
    }
    if ($sesi >= 3) {
        $galat[] = 'Anda sudah mengirim pesan beberapa kali. Mohon tunggu, atau hubungi kami melalui telepon/WhatsApp.';
    }
    if (!$galat) {
        q('INSERT INTO kontak_pesan (nama, email, telepon, subjek, pesan, dibaca, waktu, ip) VALUES (?, ?, ?, ?, ?, 0, ?, ?)', [
            potong($nama, NAMA_MAKS, ''), potong($email, 120, ''), potong($telepon, 40, ''),
            potong($subjek !== '' ? $subjek : 'Pesan dari website', JUDUL_MAKS, ''),
            potong($pesan, PESAN_MAKS, ''), date('Y-m-d H:i:s'), ip_pengunjung(),
        ]);
        $_SESSION['pesan_terkirim'] = $sesi + 1;
        redirect('kontak?terkirim=1');
    }
}
if (isset($_GET['terkirim'])) {
    $sukses = 'Terima kasih! Pesan Anda sudah terkirim dan akan segera kami balas.';
}

$angkaA = random_int(3, 9);
$angkaB = random_int(1, 9);
$_SESSION['soal_pesan'] = $angkaA + $angkaB;
$s = sekolah();
$sosial = all('SELECT * FROM sosial WHERE aktif = 1 ORDER BY urutan, id');

situs_atas(['judul' => 'Kontak', 'aktif' => 'kontak']);
hero_halaman('Kontak', 'Hubungi kami untuk informasi, kerja sama, atau pertanyaan', [['label' => 'Kontak', 'tautan' => '']]);
?>
<section class="isi-halaman">
    <div class="wadah">
        <div class="grid g2">
            <div class="artikel">
                <h2 style="margin-top:0">Informasi Kontak</h2>
                <ul class="kontak-info">
                    <?php if (alamat_lengkap() !== ''): ?>
                        <li><span class="ikon"><?= ikon('lokasi', 20) ?></span><div><strong>Alamat</strong><span><?= e(alamat_lengkap()) ?></span></div></li>
                    <?php endif; ?>
                    <?php if (sek('telepon') !== ''): ?>
                        <li><span class="ikon"><?= ikon('telepon', 20) ?></span><div><strong>Telepon</strong><span><?= e(sek('telepon')) ?></span></div></li>
                    <?php endif; ?>
                    <?php if (sek('whatsapp') !== ''): ?>
                        <li><span class="ikon"><?= ikon('whatsapp', 20) ?></span><div><strong>WhatsApp</strong>
                            <span><a href="https://wa.me/<?= e(wa_internasional(sek('whatsapp'))) ?>" target="_blank" rel="noopener noreferrer"><?= e(sek('whatsapp')) ?></a></span></div></li>
                    <?php endif; ?>
                    <?php if (sek('email') !== ''): ?>
                        <li><span class="ikon"><?= ikon('mail', 20) ?></span><div><strong>Email</strong><span><a href="mailto:<?= e(sek('email')) ?>"><?= e(sek('email')) ?></a></span></div></li>
                    <?php endif; ?>
                    <?php if (sek('website') !== ''): ?>
                        <li><span class="ikon"><?= ikon('globe', 20) ?></span><div><strong>Website</strong><span><?= e(sek('website')) ?></span></div></li>
                    <?php endif; ?>
                    <?php if (sek('jam_operasional') !== ''): ?>
                        <li><span class="ikon"><?= ikon('jam', 20) ?></span><div><strong>Jam Layanan</strong><span><?= e(sek('jam_operasional')) ?></span></div></li>
                    <?php endif; ?>
                </ul>
                <?php $maps = sek('maps'); ?>
                <?php if ($maps !== ''): ?>
                    <?php if (preg_match('#^https?://#i', $maps)): ?>
                        <iframe class="peta" src="<?= e($maps) ?>" loading="lazy" title="Peta lokasi" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    <?php else: ?>
                        <p class="kecil mut mt12"><?= e($maps) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($sosial): ?>
                    <div class="medsos-baris">
                        <?php foreach ($sosial as $so): ?>
                            <a href="<?= e($so['tautan']) ?>" target="_blank" rel="noopener noreferrer"><?= ikon_sosial($so['ikon'], 19) ?><?= e($so['nama']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="artikel">
                <h2 style="margin-top:0">Kirim Pesan</h2>
                <?php if ($sukses !== ''): ?><div class="alert alert-ok"><?= e($sukses) ?></div><?php endif; ?>
                <?php foreach ($galat as $g): ?><div class="alert alert-err"><?= e($g) ?></div><?php endforeach; ?>
                <form method="post" action="kontak">
                    <?= csrf_field() ?>
                    <div class="form-2">
                        <div class="form-baris">
                            <label for="nama">Nama Lengkap <span class="wajib">*</span></label>
                            <input type="text" id="nama" name="nama" maxlength="120" required value="<?= e($_POST['nama'] ?? '') ?>">
                        </div>
                        <div class="form-baris">
                            <label for="telepon">Nomor HP / WhatsApp</label>
                            <input type="tel" id="telepon" name="telepon" maxlength="40" value="<?= e($_POST['telepon'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-2">
                        <div class="form-baris">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" maxlength="120" value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="form-baris">
                            <label for="subjek">Subjek</label>
                            <input type="text" id="subjek" name="subjek" maxlength="160" value="<?= e($_POST['subjek'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-baris">
                        <label for="pesan">Pesan <span class="wajib">*</span></label>
                        <textarea id="pesan" name="pesan" maxlength="3000" required><?= e($_POST['pesan'] ?? '') ?></textarea>
                    </div>
                    <div class="form-baris">
                        <label for="jawab">Pengaman: berapa hasil <?= (int) $angkaA ?> + <?= (int) $angkaB ?> ? <span class="wajib">*</span></label>
                        <input type="number" id="jawab" name="jawab" required style="max-width:160px">
                        <div class="bantuan">Untuk mencegah pesan otomatis (spam).</div>
                    </div>
                    <div class="aksi-form">
                        <button type="submit" class="tombol"><?= ikon('mail', 17) ?> Kirim Pesan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<?php situs_bawah(); ?>
