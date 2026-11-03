<?php
/**
 * Agenda kegiatan madrasah.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// ALAMAT RAPI: alamat lama `agenda.php` dialihkan 301 ke `agenda`.
rute_lama_301('agenda', $_GET);

$hari = date('Y-m-d');
$mendatang = all("SELECT * FROM agenda WHERE aktif = 1 AND (tanggal = '' OR tanggal >= ?) ORDER BY tanggal, id", [$hari]);
$lalu = all("SELECT * FROM agenda WHERE aktif = 1 AND tanggal <> '' AND tanggal < ? ORDER BY tanggal DESC LIMIT 12", [$hari]);

situs_atas(['judul' => 'Agenda', 'aktif' => 'agenda']);
hero_halaman('Agenda ' . jenis_label(), 'Kegiatan yang akan datang dan yang telah dilaksanakan', [['label' => 'Agenda', 'tautan' => '']]);

/** @param array<int,array<string,mixed>> $baris */
function daftar_agenda(array $baris, bool $lampau = false): void
{
    if (!$baris) {
        echo '<p class="mut">Belum ada agenda pada bagian ini.</p>';
        return;
    }
    echo '<div class="grid g2">';
    foreach ($baris as $a) {
        $ts = strtotime($a['tanggal'] ?: 'now');
        ?>
        <div class="kartu">
            <div class="rasta" style="gap:14px;align-items:flex-start">
                <div class="agenda-tgl<?= $lampau ? ' lampau' : '' ?>">
                    <span class="hari"><?= $ts ? date('d', $ts) : '-' ?></span>
                    <span class="bln"><?= $ts ? substr(BULAN_ID[(int) date('n', $ts)], 0, 3) : '' ?></span>
                </div>
                <div>
                    <h3 style="margin:0 0 4px"><?= e($a['judul']) ?></h3>
                    <div class="agenda-meta">
                        <span><?= ikon('kalender', 14) ?><?= e(tanggal_id($a['tanggal'], true)) ?></span>
                        <?php if ($a['waktu'] !== ''): ?><span><?= ikon('jam', 14) ?><?= e($a['waktu']) ?></span><?php endif; ?>
                        <?php if ($a['tempat'] !== ''): ?><span><?= ikon('lokasi', 14) ?><?= e($a['tempat']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($a['keterangan'] !== ''): ?>
                        <p class="mut kecil mb0" style="margin-top:6px"><?= e($a['keterangan']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    echo '</div>';
}
?>
<section class="isi-halaman">
    <div class="wadah">
        <div class="artikel">
            <h2 style="margin-top:0">Agenda Mendatang</h2>
            <?php daftar_agenda($mendatang); ?>
        </div>
        <?php if ($lalu): ?>
            <div class="artikel mt24" style="margin-top:26px">
                <h2 style="margin-top:0">Agenda Sebelumnya</h2>
                <?php daftar_agenda($lalu, true); ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php situs_bawah(); ?>
