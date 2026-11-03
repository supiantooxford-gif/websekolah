<?php
/**
 * Foto Siswa (Massal) — mengisi foto banyak siswa sekaligus.
 *
 * Dua cara:
 *   1. **Unggah dari perangkat** — pilih banyak berkas foto sekaligus; nama berkas dipakai untuk
 *      mencocokkan siswa (mis. `Ahmad Fais Sirojudin.jpg` atau `260205.jpg` untuk NIS).
 *   2. **Ambil dari alamat web** — server mengambil pasangan (nama + foto) dari alamat direktori
 *      (mis. direktori peserta didik madrasah), lalu dicocokkan otomatis berdasarkan NAMA.
 *
 * Pada kedua cara, hasil pencocokan ditampilkan lebih dulu (nama siswa ← kandidat) sehingga admin
 * dapat memeriksa sebelum foto disimpan. Bila ada nama yang tidak cocok, foto itu dilewati dan
 * daftarnya ditampilkan untuk ditindaklanjuti lewat Data Siswa.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

const FOTO_WEB_URL_BAWAAN = 'https://ma.stm.sch.id/student-directory';

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

// ---------------------------------------------------------------- unggah dari perangkat
if ($aksi === 'unggah_massal') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    if (kiriman_terlalu_besar()) {
        flash('Ukuran total berkas melebihi batas server (' . ini_get('post_max_size') . '). Unggah foto dalam beberapa kali.', 'err');
        redirect('siswa_foto.php');
    }
    $hanyaKosong = !empty($_POST['hanya_kosong']);
    $hasilUnggah = unggah_foto_banyak('foto');
    if (!$hasilUnggah['ok']) {
        flash($hasilUnggah['pesan'], 'err');
        foreach (($hasilUnggah['lewat'] ?? []) as $l) {
            flash($l['nama'] . ': ' . $l['alasan'], 'err');
        }
        redirect('siswa_foto.php');
    }
    $cocok = foto_cocokkan($hasilUnggah['kandidat'], $hanyaKosong);
    $jumlah = foto_simpan($cocok['cocok']);
    $_SESSION['foto_laporan'] = [
        'cara' => 'Unggah dari perangkat',
        'jumlah' => $jumlah,
        'cocok' => $cocok['cocok'],
        'tidak' => $cocok['tidak'],
        'lewat' => array_merge($cocok['lewat'], array_map(static fn (array $l): array => ['nama' => $l['nama'], 'alasan' => $l['alasan']], $hasilUnggah['lewat'])),
        'total' => count($hasilUnggah['kandidat']),
    ];
    flash(angka($jumlah) . ' foto siswa tersimpan dari ' . angka(count($hasilUnggah['kandidat'])) . ' berkas yang diunggah.', $jumlah > 0 ? 'ok' : 'err');
    redirect('siswa_foto.php');
}

// ---------------------------------------------------------------- ambil dari web
if ($aksi === 'web_ambil') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $url = trim((string) ($_POST['url'] ?? FOTO_WEB_URL_BAWAAN));
    $ambil = foto_ambil_web($url);
    if (!$ambil['ok']) {
        flash('Gagal mengambil foto: ' . $ambil['pesan'], 'err');
        redirect('siswa_foto.php');
    }
    $hanyaKosong = !empty($_POST['hanya_kosong']);
    $cocok = foto_cocokkan($ambil['kandidat'], $hanyaKosong);
    $_SESSION['foto_web'] = [
        'url' => $url,
        'hanya_kosong' => $hanyaKosong,
        'pesan' => $ambil['pesan'],
        'cocok' => $cocok['cocok'],
        'tidak' => $cocok['tidak'],
        'lewat' => $cocok['lewat'],
        'total' => count($ambil['kandidat']),
    ];
    flash('Berhasil mengambil ' . angka(count($ambil['kandidat'])) . ' nama+foto · ' . angka(count($cocok['cocok'])) . ' cocok dengan data siswa. Periksa lalu tekan “Simpan Foto”.', 'ok');
    redirect('siswa_foto.php#pratinjau');
}
if ($aksi === 'web_simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $data = $_SESSION['foto_web'] ?? null;
    if (!$data || empty($data['cocok'])) {
        flash('Tidak ada hasil pencocokan untuk disimpan. Tekan “Ambil & Cocokkan” terlebih dahulu.', 'err');
        redirect('siswa_foto.php');
    }
    $jumlah = foto_simpan($data['cocok']);
    $_SESSION['foto_laporan'] = [
        'cara' => 'Ambil dari ' . $data['url'],
        'jumlah' => $jumlah,
        'cocok' => $data['cocok'],
        'tidak' => $data['tidak'],
        'lewat' => $data['lewat'],
        'total' => $data['total'],
    ];
    unset($_SESSION['foto_web']);
    flash(angka($jumlah) . ' foto siswa tersimpan dari alamat tersebut.', $jumlah > 0 ? 'ok' : 'err');
    redirect('siswa_foto.php');
}
if ($aksi === 'web_batal') {
    unset($_SESSION['foto_web']);
    flash('Pratinjau dibatalkan.', 'info');
    redirect('siswa_foto.php');
}
if ($aksi === 'laporan_tutup') {
    unset($_SESSION['foto_laporan']);
    redirect('siswa_foto.php');
}

$laporan = $_SESSION['foto_laporan'] ?? null;
$web = $_SESSION['foto_web'] ?? null;
$jumlahSiswa = jumlah('SELECT COUNT(*) FROM siswa');
$punyaFoto = jumlah("SELECT COUNT(*) FROM siswa WHERE foto <> ''");

/** Menampilkan tabel hasil pencocokan. */
function tabel_cocok(array $cocok, string $judul = 'Akan disimpan'): void
{
    ?>
    <h3 class="mt16"><?= e($judul) ?> (<?= angka(count($cocok)) ?>)</h3>
    <?php if (!$cocok): ?>
        <p class="mut kecil">Tidak ada yang cocok.</p>
        <?php return; ?>
    <?php endif; ?>
    <div class="pratinjau-impor">
        <table class="tabel">
            <thead><tr><th style="width:70px">Foto</th><th>Nama pada Data Siswa</th><th>Kelas</th><th>Nama pada Sumber</th></tr></thead>
            <tbody>
                <?php foreach ($cocok as $c): ?>
                    <tr>
                        <td>
                            <?php if (trim((string) $c['foto']) !== ''): ?>
                                <img class="thumb-admin kotak-bulat" src="<?= e((string) $c['foto']) ?>" alt="Foto" loading="lazy">
                            <?php else: ?>
                                <span class="kotak-kosong kotak-bulat thumb-admin">-</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= e((string) $c['siswa_nama']) ?></strong></td>
                        <td class="kecil"><?= e((string) ($c['kelas'] ?? '') ?: '-') ?></td>
                        <td class="kecil mut"><?= e((string) ($c['nama_kandidat'] ?? '') ?: '(dari kode/NIS)') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

admin_atas('Foto Siswa (Massal)');
?>
<div class="grid g2">
    <?php
    kartu_stat('Jumlah Siswa', angka($jumlahSiswa), 'pengguna', 'siswa.php');
    kartu_stat('Sudah Punya Foto', angka($punyaFoto), 'camera', 'siswa.php', 'sisanya masih inisial nama');
    ?>
</div>

<div class="alert alert-info" style="margin-top:22px">
    Pencocokan memakai <strong>nama berkas</strong> (cara pertama) atau <strong>nama pada sumber</strong>
    (cara kedua). Urutan pencocokan: <strong>NIS/NISN</strong> → nama persis → nama tanpa spasi →
    nama yang salah satunya memuat yang lain. Nama yang tidak cocok <strong>tidak</strong> diubah apa pun —
    perbaiki nama siswa di Data Siswa lalu ulangi, atau unggah fotonya satu per satu.
</div>

<?php if ($laporan): ?>
    <div class="kartu mb24">
        <div class="kartu-kepala">
            <div>
                <h2>Hasil Penyimpanan</h2>
                <p class="mut kecil mb0">
                    Cara: <?= e((string) $laporan['cara']) ?> ·
                    <?= angka(count($laporan['cocok'])) ?> cocok · <strong><?= angka($laporan['jumlah']) ?> tersimpan</strong> ·
                    <?= angka(count($laporan['tidak'])) ?> tidak cocok · <?= angka(count($laporan['lewat'])) ?> dilewati
                </p>
            </div>
            <a class="tombol tombol-garis tombol-kecil" href="siswa_foto.php?aksi=laporan_tutup">Tutup</a>
        </div>
        <?php tabel_cocok($laporan['cocok'], 'Foto yang tersimpan'); ?>
        <?php if ($laporan['lewat']): ?>
            <details class="mt16">
                <summary class="kecil mut">Dilewati (<?= angka(count($laporan['lewat'])) ?>)</summary>
                <ul class="kecil" style="margin:8px 0 0;padding-left:20px">
                    <?php foreach (array_slice($laporan['lewat'], 0, 60) as $l): ?>
                        <li><?= e((string) $l['nama']) ?> — <?= e((string) $l['alasan']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
        <?php if ($laporan['tidak']): ?>
            <details class="mt16">
                <summary class="kecil mut">Tidak cocok dengan data siswa (<?= angka(count($laporan['tidak'])) ?>)</summary>
                <p class="kecil mut" style="margin:8px 0">Nama berikut ada pada sumber, tetapi tidak ditemukan pada Data Siswa:</p>
                <ul class="kecil" style="margin:0;padding-left:20px">
                    <?php foreach (array_slice($laporan['tidak'], 0, 60) as $l): ?>
                        <li><?= e((string) $l['nama']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid g2">
    <div class="kartu">
        <h2>1. Unggah Foto Sekaligus dari Perangkat</h2>
        <p class="mut kecil">
            Pilih banyak berkas foto sekaligus (JPG/PNG/GIF/WEBP/SVG, maks 15 MB per berkas).
            Beri nama berkas sesuai nama siswa (mis. <code>Ahmad Fais Sirojudin.jpg</code>) atau sesuai
            <strong>NIS</strong> (mis. <code>260205.jpg</code>).
        </p>
        <form method="post" action="siswa_foto.php" enctype="multipart/form-data" data-konfirmasi="Unggah dan pasang foto untuk berkas yang dipilih?">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="unggah_massal">
            <div class="form-baris">
                <label for="foto">Pilih Berkas Foto (bisa banyak sekaligus)</label>
                <input type="file" id="foto" name="foto[]" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" multiple required>
                <div class="bantuan">Tahan tombol Ctrl/Shift saat memilih untuk menandai banyak berkas.</div>
            </div>
            <div class="form-baris">
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="hanya_kosong" value="1" checked> Hanya isi siswa yang belum punya foto
                </label>
                <div class="bantuan">Hilangkan centang bila foto lama ingin ditimpa.</div>
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('unggah', 17) ?> Unggah &amp; Pasang Sekaligus</button>
            </div>
        </form>
    </div>

    <div class="kartu">
        <h2>2. Ambil Foto Sekaligus dari Alamat Web</h2>
        <p class="mut kecil">
            Foto diambil otomatis dari halaman direktori lalu dicocokkan <strong>berdasarkan nama siswa</strong>.
            Halaman tersebut dibaca per tahun pelajaran &amp; kelas bila memang memakainya.
        </p>
        <form method="post" action="siswa_foto.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="web_ambil">
            <div class="form-baris">
                <label for="url">Alamat direktori (berisi daftar nama + foto)</label>
                <input type="url" id="url" name="url" maxlength="300" value="<?= e((string) ($web['url'] ?? FOTO_WEB_URL_BAWAAN)) ?>">
                <div class="bantuan">Alamat bawaan: <code><?= e(FOTO_WEB_URL_BAWAAN) ?></code></div>
            </div>
            <div class="form-baris">
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="hanya_kosong" value="1" <?= !empty($web['hanya_kosong']) || $web === null ? 'checked' : '' ?>> Hanya isi siswa yang belum punya foto
                </label>
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('unduh', 17) ?> Ambil &amp; Cocokkan</button>
            </div>
        </form>
        <?php if ($web): ?>
            <div class="kotak-info mt16">
                Terakhir diambil dari <code><?= e((string) $web['url']) ?></code>:
                <strong><?= angka($web['total']) ?></strong> nama+foto pada sumber,
                <strong><?= angka(count($web['cocok'])) ?></strong> cocok dengan data siswa.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($web): ?>
    <div class="kartu mt24" id="pratinjau">
        <div class="kartu-kepala">
            <div>
                <h2>Pratinjau Pencocokan dari Web</h2>
                <p class="mut kecil mb0"><?= e((string) $web['pesan']) ?></p>
            </div>
            <div class="rasta" style="gap:8px">
                <?php if (!empty($web['cocok'])): ?>
                    <form method="post" action="siswa_foto.php" data-konfirmasi="Simpan <?= angka(count($web['cocok'])) ?> foto ke data siswa?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="web_simpan">
                        <button type="submit" class="tombol tombol-emas"><?= ikon('simpan', 17) ?> Simpan Foto (<?= angka(count($web['cocok'])) ?>)</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="siswa_foto.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="web_batal">
                    <button type="submit" class="tombol tombol-garis">Batalkan</button>
                </form>
            </div>
        </div>
        <?php tabel_cocok($web['cocok'], 'Cocok dengan data siswa'); ?>
        <?php if ($web['lewat']): ?>
            <details class="mt16">
                <summary class="kecil mut">Dilewati (<?= angka(count($web['lewat'])) ?>) — <?= e((string) ($web['lewat'][0]['alasan'] ?? '')) ?></summary>
                <ul class="kecil" style="margin:8px 0 0;padding-left:20px">
                    <?php foreach (array_slice($web['lewat'], 0, 60) as $l): ?>
                        <li><?= e((string) $l['nama']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
        <?php if ($web['tidak']): ?>
            <details class="mt16">
                <summary class="kecil mut">Tidak cocok dengan data siswa (<?= angka(count($web['tidak'])) ?>)</summary>
                <ul class="kecil" style="margin:8px 0 0;padding-left:20px">
                    <?php foreach (array_slice($web['tidak'], 0, 60) as $l): ?>
                        <li><?= e((string) $l['nama']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php admin_bawah(); ?>
