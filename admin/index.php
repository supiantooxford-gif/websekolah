<?php
/**
 * Dashboard panel admin.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$stat = statistik();
$pesanBaru = jumlah('SELECT COUNT(*) FROM kontak_pesan WHERE dibaca = 0');
$pendaftarBaru = jumlah("SELECT COUNT(*) FROM ppdb_pendaftar WHERE status IN ('baru','verifikasi')");
$pendaftar = all('SELECT * FROM ppdb_pendaftar ORDER BY id DESC LIMIT 6');
$pesan = all('SELECT * FROM kontak_pesan ORDER BY dibaca, id DESC LIMIT 5');
$berita = all('SELECT id, judul, tanggal, terbit, dilihat FROM berita ORDER BY id DESC LIMIT 5');
$gel = gelombang_aktif();
$jumlah = [
    'menu'   => jumlah('SELECT COUNT(*) FROM menu'),
    'berita' => jumlah('SELECT COUNT(*) FROM berita'),
    'foto'   => jumlah('SELECT COUNT(*) FROM galeri_foto'),
    'video'  => jumlah('SELECT COUNT(*) FROM galeri_video'),
];
$beritaDraf = jumlah('SELECT COUNT(*) FROM berita WHERE terbit = 0');

// ---------------- Dasbor khusus peran "Editor Berita" ----------------
// Hanya menampilkan hal-hal seputar berita; kartu PPDB/pesan/galeri disembunyikan karena
// halaman-halaman itu memang ditolak (403) untuk peran ini.
if (is_editor_berita()) {
    admin_atas('Dashboard');
    ?>
    <div class="grid g3">
        <?php
        kartu_stat('Total Berita', angka($jumlah['berita']), 'newspaper', 'berita.php');
        kartu_stat('Belum Terbit (Draf)', angka($beritaDraf), 'info', 'berita.php?status=draf');
        kartu_stat('Sedang Terbit', angka(max(0, $jumlah['berita'] - $beritaDraf)), 'check', 'berita.php?status=terbit');
        ?>
    </div>

    <div class="kartu mt24" style="margin-top:22px">
        <div class="kartu-kepala">
            <div>
                <h2 style="margin:0 0 4px">Berita Terakhir</h2>
                <p class="mut kecil mb0">Akun Anda untuk mengisi berita website dan mengelola
                    <strong>Galeri Foto &amp; Video</strong> (album, foto, dan video YouTube).</p>
            </div>
            <a class="tombol tombol-emas tombol-kecil" href="berita_edit.php"><?= ikon('plus', 15) ?> Tulis Berita</a>
        </div>
        <?php if (!$berita): ?>
            <p class="mut mb0">Belum ada berita. Klik “Tulis Berita” untuk membuat berita pertama.</p>
        <?php else: ?>
            <div class="tabel-wrap">
                <table class="tabel">
                    <thead><tr><th>Judul</th><th>Tanggal</th><th>Dibaca</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($berita as $b): ?>
                            <tr>
                                <td><a href="berita_edit.php?id=<?= (int) $b['id'] ?>"><?= e(potong($b['judul'], 52)) ?></a></td>
                                <td><?= e(tanggal_id($b['tanggal'])) ?></td>
                                <td><?= angka($b['dilihat']) ?></td>
                                <td><?= ((int) $b['terbit'] === 1) ? '<span class="pil pil-terima">Terbit</span>' : '<span class="pil pil-baru">Draf</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="mt16" style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
            <a class="tombol tombol-kecil" href="berita.php"><?= ikon('newspaper', 15) ?> Semua Berita</a>
            <a class="tombol tombol-kecil" href="berita_kategori.php"><?= ikon('papan', 15) ?> Kategori Berita</a>
            <a class="tombol tombol-kecil" href="galeri.php"><?= ikon('camera', 15) ?> Galeri Foto &amp; Video</a>
            <a class="tombol tombol-garis tombol-kecil" href="../galeri" target="_blank"><?= ikon('mata', 15) ?> Lihat Galeri di Website</a>
        </div>
    </div>
    <?php
    admin_bawah();
    exit;
}

admin_atas('Dashboard');
?>
<div class="grid g4">
    <?php
    kartu_stat('Guru &amp; Tendik', angka($stat['guru']), 'users', 'guru.php');
    kartu_stat('Siswa Aktif', angka($stat['siswa']), 'pengguna', 'siswa.php');
    kartu_stat('Alumni', angka($stat['alumni']), 'medal', 'siswa.php?status=lulus');
    kartu_stat('Pendaftar PPDB', angka($stat['pendaftar']), 'clipboard', 'ppdb_pendaftar.php');
    ?>
</div>

<div class="grid g2 mt24" style="margin-top:22px">
    <div class="kartu">
        <div class="kartu-kepala">
            <h2>Pendaftar PPDB Terbaru</h2>
            <a class="tombol tombol-garis tombol-kecil" href="ppdb_pendaftar.php">Semua Pendaftar <?= ikon('panah', 15) ?></a>
        </div>
        <?php if (!$pendaftar): ?>
            <p class="mut mb0">Belum ada pendaftar PPDB.</p>
        <?php else: ?>
            <div class="tabel-wrap">
                <table class="tabel">
                    <thead><tr><th>Nomor</th><th>Nama</th><th>Tanggal</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($pendaftar as $r): ?>
                            <tr>
                                <td><a href="ppdb_pendaftar.php?id=<?= (int) $r['id'] ?>"><?= e($r['nomor']) ?></a></td>
                                <td><?= e($r['nama']) ?></td>
                                <td><?= e(tanggal_id($r['tanggal_daftar'])) ?></td>
                                <td><span class="<?= e(ppdb_status_kelas((string) $r['status'])) ?>"><?= e(ppdb_status_label((string) $r['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="kecil mut mt16" style="margin-top:14px">
            Gelombang aktif: <strong><?= e($gel['nama'] ?? 'belum ada') ?></strong>
            · Pendaftaran online: <strong><?= ppdb_dibuka() ? 'DIBUKA' : 'DITUTUP' ?></strong>
            · Pengumuman seleksi: <strong><?= pengaturan('ppdb_umumkan') === '1' ? 'sudah diumumkan' : 'belum diumumkan' ?></strong>
        </p>
        <a class="tombol tombol-kecil" href="ppdb.php"><?= ikon('setelan', 16) ?> Pengaturan PPDB</a>
    </div>

    <div class="kartu">
        <div class="kartu-kepala">
            <h2>Pesan Masuk <?= $pesanBaru > 0 ? '<span class="pil pil-tolak">' . $pesanBaru . ' baru</span>' : '' ?></h2>
            <a class="tombol tombol-garis tombol-kecil" href="pesan.php">Semua Pesan <?= ikon('panah', 15) ?></a>
        </div>
        <?php if (!$pesan): ?>
            <p class="mut mb0">Belum ada pesan dari pengunjung website.</p>
        <?php else: ?>
            <?php foreach ($pesan as $p): ?>
                <div class="agenda-baris" style="padding:10px 0">
                    <div style="min-width:0">
                        <strong style="display:block;font-size:.92rem">
                            <?php if ((int) $p['dibaca'] === 0): ?><span class="pil pil-tolak">baru</span> <?php endif; ?>
                            <?= e($p['nama']) ?>
                        </strong>
                        <span class="kecil mut"><?= e(tanggal_id($p['waktu'])) ?> · <?= e($p['subjek']) ?></span>
                        <p class="kecil mb0"><?= e(potong($p['pesan'], 110)) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid g2 mt24" style="margin-top:22px">
    <div class="kartu">
        <div class="kartu-kepala">
            <h2>Berita Terakhir</h2>
            <a class="tombol tombol-kecil" href="berita_edit.php"><?= ikon('plus', 15) ?> Tulis Berita</a>
        </div>
        <?php if (!$berita): ?>
            <p class="mut mb0">Belum ada berita.</p>
        <?php else: ?>
            <div class="tabel-wrap">
                <table class="tabel">
                    <thead><tr><th>Judul</th><th>Tanggal</th><th>Dibaca</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($berita as $b): ?>
                            <tr>
                                <td><a href="berita_edit.php?id=<?= (int) $b['id'] ?>"><?= e(potong($b['judul'], 52)) ?></a></td>
                                <td><?= e(tanggal_id($b['tanggal'])) ?></td>
                                <td><?= angka($b['dilihat']) ?></td>
                                <td><?= ((int) $b['terbit'] === 1) ? '<span class="pil pil-terima">Terbit</span>' : '<span class="pil pil-baru">Draf</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="kartu">
        <h2>Ringkasan &amp; Pintasan</h2>
        <div class="grid g2">
            <?php
            kartu_stat('Menu & Halaman', angka($jumlah['menu']), 'papan', 'menu.php');
            kartu_stat('Berita', angka($jumlah['berita']), 'newspaper', 'berita.php');
            kartu_stat('Foto Galeri', angka($jumlah['foto']), 'camera', 'galeri.php');
            kartu_stat('Video Galeri', angka($jumlah['video']), 'video', 'galeri.php');
            ?>
        </div>
        <div class="mt16" style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
            <a class="tombol tombol-kecil" href="menu.php"><?= ikon('papan', 15) ?> Kelola Menu</a>
            <a class="tombol tombol-kecil" href="slider.php"><?= ikon('gambar', 15) ?> Slider Beranda</a>
            <a class="tombol tombol-kecil" href="identitas.php"><?= ikon('building', 15) ?> Identitas</a>
            <a class="tombol tombol-garis tombol-kecil" href="../" target="_blank"><?= ikon('mata', 15) ?> Lihat Website</a>
        </div>
    </div>
</div>
<?php admin_bawah(); ?>
