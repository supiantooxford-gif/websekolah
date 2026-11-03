<?php
/**
 * Detail berita.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$slug = trim((string) ($_GET['slug'] ?? ''));

// ALAMAT RAPI: alamat lama `berita_detail.php?slug=…` dialihkan 301 ke `berita/<slug>`.
if ($slug !== '') {
    rute_lama_301('berita/' . rawurlencode($slug));
}

$b = $slug !== '' ? one('SELECT b.*, k.nama AS kategori FROM berita b LEFT JOIN berita_kategori k ON k.id = b.kategori_id
                         WHERE b.slug = ? AND b.terbit = 1', [$slug]) : null;
if (!$b) {
    http_response_code(404);
    situs_atas(['judul' => 'Berita tidak ditemukan']);
    echo '<section class="isi-halaman"><div class="wadah"><div class="artikel tengah"><h1>Berita tidak ditemukan</h1>'
        . '<p class="mut">Berita yang Anda cari tidak tersedia atau sudah tidak diterbitkan.</p>'
        . '<a class="tombol" href="berita">Lihat berita lain</a></div></div></section>';
    situs_bawah();
    exit;
}
q('UPDATE berita SET dilihat = dilihat + 1 WHERE id = ?', [$b['id']]);
$lain = all('SELECT * FROM berita WHERE terbit = 1 AND id <> ? ORDER BY tanggal DESC, id DESC LIMIT 3', [$b['id']]);

situs_atas(['judul' => $b['judul'], 'aktif' => 'berita', 'deskripsi' => $b['ringkasan'] !== '' ? $b['ringkasan'] : $b['judul']]);
hero_halaman('Berita', '', [['label' => 'Berita', 'tautan' => 'berita'], ['label' => potong($b['judul'], 40), 'tautan' => '']]);
?>
<section class="isi-halaman">
    <div class="wadah">
        <div class="grid g21">
            <article class="artikel artikel-detail">
                <h1><?= e($b['judul']) ?></h1>
                <div class="meta-artikel">
                    <span><?= ikon('kalender', 15) ?><?= e(tanggal_id($b['tanggal'], true)) ?></span>
                    <?php if ($b['penulis'] !== ''): ?><span><?= ikon('pengguna', 15) ?><?= e($b['penulis']) ?></span><?php endif; ?>
                    <?php if ($b['kategori']): ?><span class="pil pil-emas"><?= e($b['kategori']) ?></span><?php endif; ?>
                    <span><?= ikon('mata', 15) ?><?= angka($b['dilihat']) ?> kali dibaca</span>
                </div>
                <?php if ($b['gambar'] !== ''): ?>
                    <div class="sampul"><img src="<?= e($b['gambar']) ?>" alt="<?= e($b['judul']) ?>"></div>
                <?php endif; ?>
                <?php if ($b['ringkasan'] !== ''): ?>
                    <p style="font-size:1.05rem;color:#35513f;font-weight:600"><?= e($b['ringkasan']) ?></p>
                <?php endif; ?>
                <?= bersihkan_html((string) $b['isi']) ?>
                <hr style="border:0;border-top:1px solid var(--garis);margin:26px 0 18px">
                <div class="rasta" style="justify-content:space-between;flex-wrap:wrap">
                    <a class="tombol tombol-garis tombol-kecil" href="berita"><?= ikon('panah-kiri', 15) ?> Semua Berita</a>
                    <?php
                    // Alamat yang dibagikan = alamat HALAMAN INI apa adanya (rute_url_kanonik()),
                    // jadi tetap benar baik pada alamat bawaan (…/web-sekolah/…) maupun domain sendiri.
                    $bagikan = rute_url_kanonik();
                    ?>
                    <a class="tombol tombol-kecil" href="https://wa.me/?text=<?= rawurlencode($b['judul'] . ($bagikan !== '' ? ' — ' . $bagikan : '')) ?>" target="_blank" rel="noopener noreferrer"><?= ikon('whatsapp', 15) ?> Bagikan</a>
                </div>
            </article>
            <aside>
                <div class="sisi-kartu">
                    <h3>Berita Lainnya</h3>
                    <div class="daftar-berita">
                        <?php if (!$lain): ?><p class="mut mb0 kecil">Belum ada berita lain.</p><?php endif; ?>
                        <?php foreach ($lain as $l): ?>
                            <a class="berita-baris" style="padding:8px" href="<?= e(berita_url((string) $l['slug'])) ?>">
                                <div class="berita-thumb" style="flex:0 0 82px;height:64px"><?= kotak_gambar($l['gambar'], 'Berita') ?></div>
                                <div class="isi">
                                    <div class="berita-tanggal"><?= e(tanggal_id($l['tanggal'])) ?></div>
                                    <h3 style="font-size:.9rem"><?= e(potong($l['judul'], 58)) ?></h3>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sisi-kartu mt16">
                    <h3>Kategori</h3>
                    <ul class="sisi-menu">
                        <?php foreach (all('SELECT * FROM berita_kategori WHERE aktif = 1 ORDER BY urutan, id') as $k): ?>
                            <li><a href="<?= e(berita_kategori_url((string) $k['slug'])) ?>"><?= e($k['nama']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php situs_bawah(); ?>
