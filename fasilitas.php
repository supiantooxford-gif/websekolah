<?php
/**
 * Fasilitas (sarana & prasarana) — kartu dari data yang diisi admin.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// ALAMAT RAPI: alamat lama `fasilitas.php` dialihkan 301 ke `fasilitas`.
rute_lama_301('fasilitas', $_GET);

$menu = menu_by_slug('fasilitas');
$daftar = all('SELECT * FROM fasilitas WHERE aktif = 1 ORDER BY urutan, id');

situs_atas(['judul' => 'Fasilitas', 'aktif' => 'fasilitas']);
hero_halaman('Fasilitas', $menu['subjudul'] ?? 'Sarana dan prasarana pendukung pembelajaran', [['label' => 'Fasilitas', 'tautan' => '']]);
?>
<section class="isi-halaman">
    <div class="wadah">
        <?php if ($menu && trim((string) $menu['isi']) !== ''): ?>
            <div class="artikel mb24" style="margin-bottom:26px"><?= bersihkan_html((string) $menu['isi']) ?></div>
        <?php endif; ?>

        <?php if (!$daftar): ?>
            <div class="kartu tengah"><p class="mut mb0">Daftar fasilitas belum diisi admin.</p></div>
        <?php else: ?>
            <div class="fasilitas-grid">
                <?php foreach ($daftar as $f): ?>
                    <article class="fasilitas-kartu">
                        <div class="gbr"><?= kotak_gambar($f['gambar'], $f['nama']) ?></div>
                        <div class="isi">
                            <h3><?= e($f['nama']) ?></h3>
                            <p class="mut kecil mb0"><?= e($f['keterangan']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php $anak = $menu ? menu_anak((int) $menu['id']) : []; ?>
        <?php if ($anak): ?>
            <div class="kartu mt24" style="margin-top:28px">
                <h3 style="margin-top:0">Selengkapnya</h3>
                <div class="album-chip" style="margin-bottom:0">
                    <?php foreach ($anak as $a): ?>
                        <a href="<?= e(menu_url($a)) ?>"><?= e($a['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php situs_bawah(); ?>
