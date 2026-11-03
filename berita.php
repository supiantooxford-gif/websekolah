<?php
/**
 * Daftar berita (dengan saringan kategori & pencarian).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$katSlug = trim((string) ($_GET['kategori'] ?? ''));
$cari = trim((string) ($_GET['q'] ?? ''));
$kat = $katSlug !== '' ? one('SELECT * FROM berita_kategori WHERE slug = ? AND aktif = 1', [$katSlug]) : null;

// ALAMAT RAPI: alamat lama `berita.php?…` dialihkan 301 (satu alamat resmi saja). Kategori memakai
// alamat tersendiri (`berita/kategori/<slug>`); saringan lain (pencarian, halaman) ikut dibawa.
if ($katSlug !== '' && $kat) {
    rute_lama_301('berita/kategori/' . rawurlencode($katSlug), array_diff_key($_GET, ['kategori' => 1]));
}
rute_lama_301('berita', $_GET);

$w = ['b.terbit = 1'];
$p = [];
if ($kat) {
    $w[] = 'b.kategori_id = ?';
    $p[] = (int) $kat['id'];
}
if ($cari !== '') {
    $w[] = '(b.judul LIKE ? OR b.ringkasan LIKE ? OR b.isi LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like);
}
$where = 'WHERE ' . implode(' AND ', $w);
$total = jumlah("SELECT COUNT(*) FROM berita b $where", $p);
$perHal = 9;
$hal = halaman_ini();
$mulai = ($hal - 1) * $perHal;
$daftar = all("SELECT b.*, k.nama AS kategori FROM berita b LEFT JOIN berita_kategori k ON k.id = b.kategori_id
               $where ORDER BY b.tanggal DESC, b.id DESC LIMIT $perHal OFFSET $mulai", $p);
$kategori = all('SELECT * FROM berita_kategori WHERE aktif = 1 ORDER BY urutan, id');

situs_atas(['judul' => 'Berita', 'aktif' => 'berita']);
hero_halaman('Berita', 'Kabar terbaru seputar kegiatan dan prestasi madrasah', [['label' => 'Berita', 'tautan' => '']]);
?>
<section class="isi-halaman">
    <div class="wadah">
        <div class="album-chip">
            <a class="<?= $kat ? '' : 'aktif' ?>" href="berita">Semua Kategori</a>
            <?php foreach ($kategori as $k): ?>
                <a class="<?= $kat && (int) $kat['id'] === (int) $k['id'] ? 'aktif' : '' ?>" href="<?= e(berita_kategori_url((string) $k['slug'])) ?>"><?= e($k['nama']) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($cari !== ''): ?>
            <p class="mut">Hasil pencarian &ldquo;<?= e($cari) ?>&rdquo; — <?= angka($total) ?> berita ditemukan. <a href="berita">Tampilkan semua</a></p>
        <?php endif; ?>

        <?php if (!$daftar): ?>
            <div class="kartu tengah">
                <p class="mut mb0">Belum ada berita<?= $cari !== '' ? ' yang cocok dengan pencarian Anda' : '' ?>.</p>
            </div>
        <?php else: ?>
            <div class="berita-grid">
                <?php foreach ($daftar as $b): ?>
                    <article class="berita-kartu">
                        <a class="gbr" href="<?= e(berita_url((string) $b['slug'])) ?>"><?= kotak_gambar($b['gambar'], 'Berita') ?></a>
                        <div class="isi">
                            <div class="berita-tanggal"><?= ikon('kalender', 14) ?><?= e(tanggal_id($b['tanggal'])) ?>
                                <?php if ($b['kategori']): ?><span class="pil pil-emas"><?= e($b['kategori']) ?></span><?php endif; ?>
                            </div>
                            <h3><a href="<?= e(berita_url((string) $b['slug'])) ?>"><?= e($b['judul']) ?></a></h3>
                            <p><?= e(potong($b['ringkasan'] !== '' ? $b['ringkasan'] : $b['isi'], 130)) ?></p>
                            <div class="kaki">
                                <span><?= ikon('mata', 14) ?> <?= angka($b['dilihat']) ?> kali dibaca</span>
                                <a href="<?= e(berita_url((string) $b['slug'])) ?>">Selengkapnya →</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?= paginasi($total, $perHal, $hal) ?>
        <?php endif; ?>
    </div>
</section>
<?php situs_bawah(); ?>
