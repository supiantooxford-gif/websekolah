<?php
/**
 * Galeri foto & video (video memakai embed YouTube).
 *
 * Susunan halaman (permintaan pemilik) — DUA bagian:
 *   1. **Album Foto** (di atas): kartu per album + pilihan TAHUN, dilengkapi **FILTER TAHUN**
 *      (`?tahun=YYYY`) sehingga pengunjung dapat melihat album per tahun; pengunjung membuka isi
 *      album (`?album=slug`) yang berisi **FOTO saja**.
 *   2. **Video Kegiatan** (di bawah): 12 video per halaman (tanpa album) + tombol **Selanjutnya**.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

/** Jumlah video yang tampil per halaman pada bagian "Video Kegiatan". */
const GALERI_VIDEO_PER_HAL = 12;

/**
 * Tautan halaman VIDEO pada halaman galeri (`?hal=N`), selalu membawa `#videoGaleri` supaya
 * pengunjung kembali ke bagian video. Filter tahun album (`?tahun=`) tetap dibawa.
 */
function video_hal_url(int $hal): string
{
    $q = ['hal' => max(1, $hal)];
    $tahun = trim((string) ($_GET['tahun'] ?? ''));
    if ($tahun !== '' && preg_match('#^\d{4}$#', $tahun)) {
        $q['tahun'] = $tahun;
    }
    // ALAMAT RAPI: memakai jalur halaman yang sedang dibuka (mis. `galeri` atau `galeri/<album>`).
    return rute_tautan(rute_kanonik(), $q) . '#videoGaleri';
}

$albumSlug = trim((string) ($_GET['album'] ?? ''));

// ALAMAT RAPI: alamat lama `galeri.php?…` dialihkan 301 (album → `galeri/<slug>`, halaman video
// dan saringan tahun tetap pada `galeri` beserta parameternya).
if ($albumSlug !== '') {
    rute_lama_301('galeri/' . rawurlencode($albumSlug), array_diff_key($_GET, ['album' => 1]));
}
rute_lama_301('galeri', $_GET);
$album = album_by_slug($albumSlug);
$kelompokSemua = album_kelompok();

// ---- FILTER TAHUN untuk bagian Album Foto (permintaan pemilik) -------------------------------
// Nilai tahun divalidasi terhadap tahun yang BENAR-BENAR ada pada album; tahun asing diabaikan
// (bukan 403/kosong). Tanpa filter → seluruh album tampil (termasuk yang belum bertahun).
$tahunAda = [];
foreach ($kelompokSemua as $k) {
    foreach ($k['album'] as $a) {
        $th = trim((string) $a['tahun']);
        if ($th !== '') {
            $tahunAda[$th] = ($tahunAda[$th] ?? 0) + 1;
        }
    }
}
krsort($tahunAda);
$tahunFilter = trim((string) ($_GET['tahun'] ?? ''));
if ($tahunFilter !== '' && !isset($tahunAda[$tahunFilter])) {
    $tahunFilter = '';
}

// Kelompok album yang ditampilkan + album yang mewakili tahun terpilih (untuk sampul/jumlah/link).
$kelompok = [];
foreach ($kelompokSemua as $k) {
    $k['album_tampil'] = $k['album'];
    if ($tahunFilter !== '') {
        $tahunIni = array_values(array_filter($k['album'], static fn (array $a): bool => (string) $a['tahun'] === $tahunFilter));
        if (!$tahunIni) {
            continue;
        }
        $k['album_tampil'] = $tahunIni;
        $k['sampul'] = (string) $tahunIni[0]['sampul_efektif'];
        $k['jml_foto'] = array_sum(array_map(static fn (array $a): int => (int) $a['jml_foto'], $tahunIni));
    }
    $kelompok[] = $k;
}

// ---- VIDEO KEGIATAN: 12 per halaman + tombol Selanjutnya (permintaan pemilik) -----------------
$videoSemua = video_publik();
$videoTotal = count($videoSemua);
$videoHalTotal = max(1, (int) ceil($videoTotal / GALERI_VIDEO_PER_HAL));
$videoHal = min($videoHalTotal, max(1, (int) ($_GET['hal'] ?? 1)));
$video = array_slice($videoSemua, ($videoHal - 1) * GALERI_VIDEO_PER_HAL, GALERI_VIDEO_PER_HAL);

$foto = [];
$albumSemua = [];
foreach ($kelompok as $k) {
    foreach ($k['album'] as $a) {
        $albumSemua[] = $a;
    }
}
if ($album) {
    $foto = album_foto((int) $album['id']);
}

// Kelompok (kartu) yang sedang dibuka — dipakai untuk chip tahun di dalam album.
$kelompokAktif = null;
foreach ($kelompok as $k) {
    foreach ($k['album'] as $a) {
        if ((int) $a['id'] === (int) ($album['id'] ?? 0)) {
            $kelompokAktif = $k;
            break 2;
        }
    }
}

$judulHero = $album ? album_label($album) : 'Galeri';
$subHero = $album
    ? ('Album ' . $album['nama'] . ($album['tahun'] !== '' ? ' tahun ' . $album['tahun'] : '')
        . ' — ' . count($foto) . ' foto')
    : ($tahunFilter !== ''
        ? ('Album foto tahun ' . $tahunFilter . ' dan video kegiatan madrasah.')
        : 'Album foto dan video kegiatan madrasah — pilih album foto, atau tonton video kegiatannya');

situs_atas(['judul' => 'Galeri', 'aktif' => 'galeri']);
hero_halaman($judulHero, $subHero, array_values(array_filter([
    $album ? ['label' => 'Galeri', 'tautan' => 'galeri'] : null,
    ['label' => $album ? album_label($album) : 'Galeri', 'tautan' => ''],
])));
?>
<section class="isi-halaman">
    <div class="wadah">
        <?php if ($album): ?>
            <?php /* ================= ISI ALBUM (FOTO saja) ================= */ ?>
            <div class="album-nav mb24">
                <a class="tombol tombol-garis tombol-kecil" href="galeri"><?= ikon('panah-kiri', 15) ?> Semua Album Foto</a>
                <?php if ($kelompokAktif && count($kelompokAktif['tahun']) > 1): ?>
                    <span class="album-tahun-nav">
                        <span class="mut kecil">Tahun:</span>
                        <?php foreach ($kelompokAktif['tahun'] as $th): ?>
                            <?php $a3 = null; foreach ($kelompokAktif['album'] as $x) { if ((string) $x['tahun'] === $th) { $a3 = $x; break; } } ?>
                            <a class="chip-tahun<?= (string) $th === (string) $album['tahun'] ? ' aktif' : '' ?>"
                               href="<?= e(album_url((string) ($a3['slug'] ?? ''))) ?>"><?= e($th) ?></a>
                        <?php endforeach; ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($album['keterangan'] !== ''): ?>
                <div class="kartu mb24"><p class="mut mb0"><?= e($album['keterangan']) ?></p></div>
            <?php endif; ?>

            <div class="seksi-kepala">
                <div>
                    <h2>Foto Kegiatan</h2>
                    <p><?= count($foto) ?> foto pada album ini — klik foto untuk melihat ukuran penuh.</p>
                </div>
            </div>
            <?php if (!$foto): ?>
                <div class="kartu tengah"><p class="mut mb0">Belum ada foto pada album ini. Admin dapat mengunggahnya dari panel
                    &rarr; <strong>Galeri Foto &amp; Video</strong>.</p></div>
            <?php else: ?>
                <div class="galeri-grid">
                    <?php foreach ($foto as $f): ?>
                        <a class="galeri-item" href="<?= e($f['gambar']) ?>" target="_blank" rel="noopener">
                            <?= kotak_gambar($f['gambar'], 'Foto') ?>
                            <?php if ($f['keterangan'] !== ''): ?><span class="ket"><?= e($f['keterangan']) ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php /* Album foto lain agar pengunjung mudah berpindah tanpa kembali ke daftar. */ ?>
            <?php $lain = array_values(array_filter($albumSemua, static fn (array $a): bool => (int) $a['id'] !== (int) $album['id'])); ?>
            <?php if ($lain): ?>
                <div class="seksi-kepala" style="margin-top:34px"><div><h2>Album Foto Lainnya</h2></div></div>
                <div class="album-chip">
                    <?php foreach ($lain as $a): ?>
                        <a href="<?= e(album_url((string) $a['slug'])) ?>"><?= e(album_label($a)) ?>
                            <span class="mut kecil">(<?= (int) $a['jml_foto'] ?>)</span></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($video): ?>
                <p class="kecil mut" style="margin-top:26px">Mencari video? Seluruh video kegiatan ada pada
                    <a href="galeri#videoGaleri">bagian Video Kegiatan</a>.</p>
            <?php endif; ?>

        <?php else: ?>
            <?php /* ================= 1. ALBUM FOTO (ATAS) ================= */ ?>
            <div class="seksi-kepala" id="albumGaleri">
                <div>
                    <h2>Album Foto<?= $tahunFilter !== '' ? ' Tahun ' . e($tahunFilter) : '' ?></h2>
                    <p><?= count($kelompok) ?> album<?= $tahunFilter !== '' ? ' pada tahun ' . e($tahunFilter) : '' ?>
                        — klik album untuk melihat seluruh fotonya.</p>
                </div>
            </div>

            <?php /* ================= FILTER TAHUN ALBUM (permintaan pemilik) =================
                     Pengunjung dapat menyaring album per tahun. Tanpa filter = semua tahun. */ ?>
            <?php if ($tahunAda || $tahunFilter !== ''): ?>
                <div class="album-saring" id="saringTahunAlbum">
                    <span class="album-saring-label"><?= ikon('kalender', 15) ?> Tahun:</span>
                    <a class="chip-tahun<?= $tahunFilter === '' ? ' aktif' : '' ?>" href="galeri#albumGaleri">Semua tahun</a>
                    <?php foreach ($tahunAda as $th => $jml): ?>
                        <a class="chip-tahun<?= $tahunFilter === (string) $th ? ' aktif' : '' ?>"
                           href="galeri?tahun=<?= e((string) $th) ?>#albumGaleri"
                           title="<?= (int) $jml ?> album pada tahun <?= e((string) $th) ?>"><?= e((string) $th) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$kelompok): ?>
                <div class="kartu tengah"><p class="mut mb0">Belum ada album foto<?= $tahunFilter !== '' ? ' pada tahun ' . e($tahunFilter) : '' ?> yang ditampilkan.
                    <?php if ($tahunFilter !== ''): ?> <a href="galeri#albumGaleri">Lihat semua tahun</a>.<?php endif; ?></p></div>
            <?php else: ?>
                <div class="album-grid">
                    <?php foreach ($kelompok as $k): ?>
                        <?php $utama = $k['album_tampil'][0] ?? $k['album'][0]; ?>
                        <article class="album-kartu">
                            <a class="album-sampul" href="<?= e(album_url((string) $utama['slug'])) ?>">
                                <?= kotak_gambar($k['sampul'], 'Album ' . $k['nama']) ?>
                                <?php if ($k['jml_foto'] > 0): ?>
                                    <span class="album-jumlah"><?= angka($k['jml_foto']) ?> foto</span>
                                <?php endif; ?>
                            </a>
                            <div class="album-isi">
                                <h3><?= e($k['nama']) ?></h3>
                                <?php if ($k['tahun']): ?>
                                    <div class="album-tahun">
                                        <?php foreach ($k['tahun'] as $th): ?>
                                            <?php $a2 = null; foreach ($k['album'] as $x) { if ((string) $x['tahun'] === $th) { $a2 = $x; break; } } ?>
                                            <a class="chip-tahun<?= $tahunFilter === (string) $th ? ' aktif' : '' ?>" href="<?= e(album_url((string) ($a2['slug'] ?? $utama['slug']))) ?>"><?= e($th) ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <p class="album-ket"><?= e(potong((string) $utama['keterangan'], 140)) ?></p>
                                <a class="tombol tombol-garis tombol-kecil" href="<?= e(album_url((string) $utama['slug'])) ?>">
                                    <?= ikon('camera', 15) ?> Lihat Album
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php /* ================= 2. VIDEO KEGIATAN (BAWAH, TANPA ALBUM) ================= */ ?>
            <div class="seksi-kepala" id="videoGaleri" style="margin-top:38px">
                <div>
                    <h2>Video Kegiatan</h2>
                    <?php if ($video): ?>
                        <p>Menampilkan <?= angka(($videoHal - 1) * GALERI_VIDEO_PER_HAL + 1) ?>–<?= angka(($videoHal - 1) * GALERI_VIDEO_PER_HAL + count($video)) ?>
                            dari <?= angka($videoTotal) ?> video dokumentasi kegiatan (YouTube).
                            <?php if ($videoHalTotal > 1): ?>Halaman <?= (int) $videoHal ?> dari <?= (int) $videoHalTotal ?>.<?php endif; ?></p>
                    <?php else: ?>
                        <p>Video dokumentasi kegiatan yang diunggah melalui YouTube.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$video): ?>
                <div class="kartu"><p class="mut mb0">Belum ada video yang ditampilkan. Admin dapat menambahkan tautan video
                    YouTube melalui panel admin &rarr; <strong>Galeri Foto &amp; Video</strong> &rarr; bagian
                    <strong>Video YouTube</strong>.</p></div>
            <?php else: ?>
                <div class="video-grid">
                    <?php foreach ($video as $v): ?>
                        <article class="video-kotak">
                            <div class="video-bingkai">
                                <iframe src="https://www.youtube.com/embed/<?= e(youtube_id((string) $v['youtube'])) ?>" title="<?= e($v['judul']) ?>"
                                        loading="lazy" allowfullscreen allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                            </div>
                            <div class="isi">
                                <h3><?= e($v['judul']) ?></h3>
                                <?php if ($v['keterangan'] !== ''): ?><p class="mut kecil mb0"><?= e($v['keterangan']) ?></p><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php /* Tombol SELANJUTNYA untuk melihat video lainnya (permintaan pemilik).
                         Tautannya membawa `#videoGaleri` supaya pengunjung langsung kembali ke
                         bagian video setelah halaman berganti. */ ?>
                <?php if ($videoHalTotal > 1): ?>
                    <nav class="paginasi paginasi-video" id="paginasiVideo">
                        <?php if ($videoHal > 1): ?>
                            <a class="tombol tombol-garis" href="<?= e(video_hal_url($videoHal - 1)) ?>">&laquo; Sebelumnya</a>
                        <?php endif; ?>
                        <?php
                        $mulaiHal = max(1, $videoHal - 2);
                        $akhirHal = min($videoHalTotal, $mulaiHal + 4);
                        for ($i = $mulaiHal; $i <= $akhirHal; $i++):
                            ?>
                            <?php if ($i === $videoHal): ?>
                                <span class="hal aktif"><?= $i ?></span>
                            <?php else: ?>
                                <a class="hal" href="<?= e(video_hal_url($i)) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($videoHal < $videoHalTotal): ?>
                            <a class="tombol tombol-emas" href="<?= e(video_hal_url($videoHal + 1)) ?>" data-video-selanjutnya>Selanjutnya &raquo;</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php situs_bawah(); ?>
