<?php
/**
 * PINTU DEPAN (front controller) website + BERANDA.
 *
 * Seluruh alamat publik yang rapi dilayani berkas ini: server (Caddy) menyerahkan setiap jalur
 * yang bukan berkas nyata kepada `index.php`, lalu `rute_jalankan()` menerjemahkannya
 * (mis. `/web-sekolah/halaman/tenaga-pendidik` → `halaman.php`) dan memuat berkas halaman itu.
 * Bila jalurnya kosong (akar aplikasi) — atau halaman itu diminta lewat alamat lama — beranda
 * di bawah ini yang dirender. Alamat lama `index.php` dialihkan 301 ke akar aplikasi.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// Terjemahkan alamat rapi; bila cocok, halaman bersangkutan dimuat dari sini lalu selesai.
rute_jalankan();

$s = sekolah();
$slider = all('SELECT * FROM slider WHERE aktif = 1 ORDER BY urutan, id');
$keunggulan = all('SELECT * FROM keunggulan WHERE aktif = 1 ORDER BY urutan, id');
// Slider Program Digitalisasi Madrasah — tampilkan SELURUH aplikasi (dulu dibatasi 4).
$program = all('SELECT * FROM program WHERE aktif = 1 ORDER BY urutan, id');
$berita = berita_terbaru(4);          // 4 berita terbaru: 2 kiri & 2 kanan
$guru = guru_publik(12);              // slider guru (foto + nama)
// Bagian "Galeri & Media Sosial" pada beranda menampilkan **SELURUH SAMPUL ALBUM** (permintaan
// pemilik) — bukan lagi daftar foto. Klik sampul = membuka ISI album tersebut
// (`galeri.php?album=slug`). Sampul efektif = kolom `sampul`, atau foto pertama bila sampul kosong
// (`album_publik()` pada inc/helpers.php).
$albumBeranda = album_publik();
// Slider THUMBNAIL VIDEO pada bagian "Galeri & Media Sosial" (permintaan pemilik). Diambil dari
// tabel `galeri_video` yang sama dengan bagian "Video Kegiatan" pada halaman galeri, lalu
// gambarnya memakai thumbnail YouTube. Tanpa video aktif, slidernya tidak dicetak sama sekali.
$videoBeranda = video_publik(12);
$agenda = agenda_terdekat(4);
$testimoni = all('SELECT * FROM testimoni WHERE aktif = 1 ORDER BY urutan, id');
$sosial = all('SELECT * FROM sosial WHERE aktif = 1 ORDER BY urutan, id');
$poll = poll_aktif();
$tampilHasil = ($poll && (poll_sudah_memilih((int) $poll['id']) || isset($_GET['poll']))) ? true : false;
$stat = statistik();
$gel = gelombang_aktif();

situs_atas(['aktif' => 'beranda', 'deskripsi' => sek('slogan')]);
?>

<section class="hero" data-bagian="beranda" data-slider data-jeda="6000">
    <div class="hero-lacak">
        <?php if ($slider): ?>
            <?php foreach ($slider as $i => $sl): ?>
                <div class="hero-slide<?= $i === 0 ? ' aktif' : '' ?>">
                    <?php if ($sl['gambar'] !== ''): ?>
                        <img class="hero-slide-gambar" src="<?= e($sl['gambar']) ?>" alt="<?= e($sl['judul']) ?>">
                    <?php endif; ?>
                    <span class="hero-lapisan"></span>
                    <div class="wadah">
                        <div class="hero-teks">
                            <?php if ($sl['badge'] !== ''): ?>
                                <span class="hero-badge"><?= ikon('tanda', 16) ?><?= e($sl['badge']) ?></span>
                            <?php endif; ?>
                            <h1><?= e($sl['judul']) ?><?php if ($sl['subjudul'] !== ''): ?><span class="emas"><?= e($sl['subjudul']) ?></span><?php endif; ?></h1>
                            <?php if ($sl['keterangan'] !== ''): ?><p><?= e($sl['keterangan']) ?></p><?php endif; ?>
                            <?php if ($sl['tombol_teks'] !== '' && $sl['tombol_tautan'] !== ''): ?>
                                <div class="hero-aksi">
                                    <a class="tombol tombol-emas" href="<?= e(tautan_publik((string) $sl['tombol_tautan'])) ?>"<?= ppdb_tautan_atribut((string) $sl['tombol_tautan']) ?>><?= e($sl['tombol_teks']) ?> <?= ikon('panah', 17) ?></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="hero-nav">
                <button type="button" class="hero-tombol hero-kiri" aria-label="Slide sebelumnya"><?= ikon('panah-kiri', 20) ?></button>
                <button type="button" class="hero-tombol hero-kanan" aria-label="Slide berikutnya"><?= ikon('panah', 20) ?></button>
            </div>
            <div class="hero-titik"></div>
        <?php else: ?>
            <div class="hero-slide aktif">
                <span class="hero-lapisan"></span>
                <div class="wadah">
                    <div class="hero-teks">
                        <span class="hero-badge"><?= ikon('tanda', 16) ?>Selamat Datang di Website Resmi</span>
                        <h1><?= e($s['nama']) ?></h1>
                        <p><?= e(sek('slogan', APP_SUB)) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($keunggulan): ?>
<section class="strip-keunggulan" data-bagian="keunggulan">
    <div class="wadah">
        <div class="kartu">
            <?php foreach ($keunggulan as $k): ?>
                <div class="strip-item">
                    <span class="strip-ikon"><?= ikon($k['ikon'] ?: 'star', 22) ?></span>
                    <div>
                        <strong><?= e($k['judul']) ?></strong>
                        <p><?= e($k['keterangan']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="seksi" data-bagian="berita">
    <div class="wadah">
        <div class="grid berita-ppdb">
            <div>
                <div class="seksi-kepala">
                    <div>
                        <h2><?= e(pengaturan('beranda_judul_berita', 'Berita Terbaru')) ?></h2>
                        <p><?= e(pengaturan('beranda_berita_teks')) ?></p>
                    </div>
                    <div class="seksi-kanan"><a class="kecil" href="berita">Lihat Semua <?= ikon('panah', 14) ?></a></div>
                </div>
                <div class="berita-beranda">
                    <?php if (!$berita): ?>
                        <p class="mut">Belum ada berita yang diterbitkan.</p>
                    <?php endif; ?>
                    <?php foreach ($berita as $b): ?>
                        <article class="berita-mini">
                            <a class="gbr" href="<?= e(berita_url((string) $b['slug'])) ?>"><?= kotak_gambar($b['gambar'], 'Berita') ?></a>
                            <div class="isi">
                                <div class="berita-tanggal">
                                    <?= ikon('kalender', 13) ?><?= e(tanggal_id($b['tanggal'])) ?>
                                    <?php if ($b['kategori']): ?><span class="pil pil-emas"><?= e($b['kategori']) ?></span><?php endif; ?>
                                </div>
                                <h3><a href="<?= e(berita_url((string) $b['slug'])) ?>"><?= e(potong($b['judul'], 78)) ?></a></h3>
                                <p><?= e(potong($b['ringkasan'] !== '' ? $b['ringkasan'] : $b['isi'], 96)) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <div class="kartu-ppdb">
                    <span class="badge"><?= ikon('star', 15) ?>PPDB <?= e($gel['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1))) ?></span>
                    <h3><?= e($gel['nama'] ?? 'Penerimaan') ?><span>Peserta Didik Baru</span></h3>
                    <p class="judul-kecil"><?= e($s['nama']) ?></p>
                    <p><?= e(potong(pengaturan('ppdb_isi'), 150)) ?></p>
                    <?php if ($gel && $gel['selesai'] !== ''): ?>
                        <p class="kecil" style="color:#cfe6da">Pendaftaran dibuka sampai <?= e(tanggal_id($gel['selesai'])) ?></p>
                    <?php endif; ?>
                    <a class="tombol tombol-emas" href="ppdb"<?= ppdb_tautan_atribut('ppdb') ?>><?= ppdb_dibuka() ? 'Daftar Sekarang' : 'Info Selengkapnya' ?> <?= ikon('panah', 17) ?></a>
                </div>

                <?php if ($poll): ?>
                    <div class="kartu kartu-poll" id="polling">
                        <div class="poll-kepala">
                            <span class="poll-ikon"><?= ikon('quote', 20) ?></span>
                            <h3><?= e($poll['pertanyaan']) ?></h3>
                        </div>
                        <?php if (trim((string) $poll['keterangan']) !== ''): ?>
                            <p class="poll-ket"><?= e($poll['keterangan']) ?></p>
                        <?php endif; ?>

                        <?php if (!$tampilHasil): ?>
                            <form method="post" action="polling_suara.php" class="poll-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="poll_id" value="<?= (int) $poll['id'] ?>">
                                <div class="poll-pilihan">
                                    <?php foreach ($poll['pilihan'] as $pl): ?>
                                        <label class="poll-opsi">
                                            <input type="radio" name="pilihan_id" value="<?= (int) $pl['id'] ?>" required>
                                            <span><?= e($pl['teks']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="poll-aksi">
                                    <button type="submit" class="tombol tombol-emas tombol-kecil"><?= ikon('send', 15) ?> Kirim Pendapat</button>
                                    <a class="poll-lihat" href="./?poll=hasil#polling">Lihat hasil</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="poll-hasil">
                                <?php foreach ($poll['pilihan'] as $i => $pl): ?>
                                    <div class="poll-baris<?= poll_kelas_teratas($poll, $i) ?>">
                                        <div class="poll-baris-kepala">
                                            <span><?= e($pl['teks']) ?></span>
                                            <strong><?= $pl['persen'] ?>% · <?= angka($pl['suara']) ?> suara</strong>
                                        </div>
                                        <div class="poll-track"><span style="width:<?= (float) $pl['persen'] ?>%"></span></div>
                                    </div>
                                <?php endforeach; ?>
                                <p class="poll-total">
                                    <?= angka($poll['total']) ?> pendapat masuk<?php if (poll_sudah_memilih((int) $poll['id'])): ?> · terima kasih, pendapat Anda sudah tercatat<?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($program): ?>
<section class="seksi alt" data-bagian="program" data-gulir=".gulir-program" data-gulir-auto="7000">
    <div class="wadah">
        <div class="seksi-kepala">
            <div>
                <h2><?= e(pengaturan('beranda_judul_program', 'Program Digitalisasi Madrasah')) ?></h2>
                <p><?= e(pengaturan('beranda_program_teks')) ?></p>
            </div>
            <div class="seksi-kanan gulir-nav">
                <button type="button" data-gulir-mundur aria-label="Program sebelumnya"><?= ikon('panah-kiri', 18) ?></button>
                <button type="button" data-gulir-maju aria-label="Program berikutnya"><?= ikon('panah', 18) ?></button>
            </div>
        </div>
        <div class="gulir-lacak gulir-program">
            <?php foreach ($program as $p): ?>
                <a class="kartu-program gulir-item" href="<?= e(tautan_publik((string) $p['tautan']) ?: '#') ?>">
                    <span class="ikon-bulat"><?= ikon($p['ikon'] ?: 'book', 22) ?></span>
                    <div>
                        <h3><?= e($p['judul']) ?></h3>
                        <p><?= e($p['keterangan']) ?></p>
                    </div>
                    <span class="panah"><?= ikon('panah', 18) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($guru): ?>
<section class="seksi hijau" data-bagian="guru" data-gulir=".gulir-guru" data-gulir-auto="8000">
    <div class="wadah">
        <div class="seksi-kepala">
            <div>
                <h2>Guru &amp; Tenaga Kependidikan</h2>
                <p>Tenaga pendidik yang mendampingi peserta didik setiap hari.</p>
            </div>
            <div class="seksi-kanan gulir-nav">
                <button type="button" data-gulir-mundur aria-label="Guru sebelumnya"><?= ikon('panah-kiri', 18) ?></button>
                <button type="button" data-gulir-maju aria-label="Guru berikutnya"><?= ikon('panah', 18) ?></button>
            </div>
        </div>
        <div class="gulir-lacak gulir-guru">
            <?php foreach ($guru as $g): ?>
                <div class="guru-mini gulir-item">
                    <?php if ($g['foto'] !== ''): ?>
                        <img class="guru-mini-foto" src="<?= e($g['foto']) ?>" alt="<?= e($g['nama']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="guru-mini-foto inisial"><?= e(strtoupper(substr(trim($g['nama']), 0, 1))) ?></span>
                    <?php endif; ?>
                    <strong><?= e($g['nama']) ?></strong>
                    <small><?= e($g['jabatan'] ?: 'Guru') ?><?= $g['mapel'] !== '' ? ' · ' . e($g['mapel']) : '' ?></small>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="tengah mt16"><a class="tombol tombol-emas tombol-kecil" href="<?= e(halaman_url('tenaga-pendidik')) ?>">Lihat Semua Guru &amp; Tendik <?= ikon('panah', 15) ?></a></div>
    </div>
</section>
<?php endif; ?>

<section class="seksi alt" data-bagian="agenda">
    <div class="wadah">
        <div class="grid g21">
            <div>
                <div class="seksi-kepala">
                    <div>
                        <h2><?= e(pengaturan('beranda_judul_agenda', 'Agenda Madrasah')) ?></h2>
                        <p><?= e(pengaturan('beranda_agenda_teks')) ?></p>
                    </div>
                    <div class="seksi-kanan"><a class="kecil" href="agenda">Semua Agenda <?= ikon('panah', 14) ?></a></div>
                </div>
                <div class="kartu">
                    <?php if (!$agenda): ?>
                        <p class="mut mb0">Belum ada agenda yang dijadwalkan.</p>
                    <?php endif; ?>
                    <?php foreach ($agenda as $a): ?>
                        <?php $ts = strtotime($a['tanggal'] ?: 'now'); ?>
                        <div class="agenda-baris">
                            <div class="agenda-tgl">
                                <span class="hari"><?= $ts ? date('d', $ts) : '-' ?></span>
                                <span class="bln"><?= $ts ? substr(BULAN_ID[(int) date('n', $ts)], 0, 3) : '' ?></span>
                            </div>
                            <div>
                                <h3><?= e($a['judul']) ?></h3>
                                <div class="agenda-meta">
                                    <?php if ($a['waktu'] !== ''): ?><span><?= ikon('jam', 14) ?><?= e($a['waktu']) ?></span><?php endif; ?>
                                    <?php if ($a['tempat'] !== ''): ?><span><?= ikon('lokasi', 14) ?><?= e($a['tempat']) ?></span><?php endif; ?>
                                </div>
                                <?php if ($a['keterangan'] !== ''): ?>
                                    <p class="mut kecil mb0" style="margin-top:4px"><?= e(potong($a['keterangan'], 140)) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <div class="sisi-kartu kartu-sambutan">
                    <?php if (foto_kepala() !== ''): ?>
                        <div class="kepala-foto-bingkai">
                            <img class="kepala-foto" src="<?= e(foto_kepala()) ?>" alt="<?= e(sek('kepala', kepala_label())) ?>" loading="lazy">
                        </div>
                    <?php else: ?>
                        <div class="kepala-foto-bingkai kosong">
                            <span class="kepala-foto inisial"><?= e(strtoupper(substr(trim(sek('kepala', 'K')), 0, 1))) ?></span>
                            <span class="kepala-foto-ket">Foto <?= e(kepala_label()) ?> dapat diunggah dari panel admin → Identitas Sekolah</span>
                        </div>
                    <?php endif; ?>
                    <h3>Sambutan <?= e(kepala_label()) ?></h3>
                    <?php if (sek('kepala') !== ''): ?>
                        <p class="kecil mut mb8"><strong><?= e(sek('kepala')) ?></strong><?php if (sek('nip_kepala') !== '' && sek('nip_kepala') !== '-'): ?> &middot; NIP <?= e(sek('nip_kepala')) ?><?php endif; ?></p>
                    <?php endif; ?>
                    <p style="font-size:.9rem"><?= e(pengaturan('sambutan_singkat')) ?></p>
                    <a class="tombol tombol-garis tombol-kecil" href="<?= e(halaman_url('sambutan')) ?>">Baca Selengkapnya <?= ikon('panah', 15) ?></a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="band-stat" data-bagian="statistik">
    <div class="wadah">
        <h2><?= e($s['nama']) ?> dalam Angka</h2>
        <div class="stat-grid">
            <?php
            $kotakStat = [
                ['Guru &amp; Tendik', $stat['guru'], 'users'],
                ['Siswa Aktif', $stat['siswa'], 'pengguna'],
                ['Alumni', $stat['alumni'], 'medal'],
                ['Pendaftar Terbaru', $stat['pendaftar'], 'clipboard'],
            ];
            foreach ($kotakStat as $ks): ?>
                <div class="stat-kotak">
                    <div class="stat-ikon-situs"><?= ikon($ks[2], 24) ?></div>
                    <div class="stat-angka-besar" data-hitung="<?= (int) $ks[1] ?>"><?= angka($ks[1]) ?></div>
                    <div class="stat-label"><?= $ks[0] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($testimoni): ?>
<section class="seksi" data-bagian="testimoni" data-gulir=".testi-lacak">
    <div class="wadah">
        <div class="seksi-kepala">
            <div>
                <h2><?= e(pengaturan('beranda_judul_testimoni', 'Apa kata mereka')) ?></h2>
                <p><?= e(pengaturan('beranda_testimoni_teks')) ?></p>
            </div>
            <div class="seksi-kanan testi-nav">
                <button type="button" data-gulir-mundur aria-label="Sebelumnya"><?= ikon('panah-kiri', 18) ?></button>
                <button type="button" data-gulir-maju aria-label="Berikutnya"><?= ikon('panah', 18) ?></button>
            </div>
        </div>
        <div class="testi-lacak">
            <?php foreach ($testimoni as $t): ?>
                <article class="testi-kartu">
                    <div class="quote"><?= ikon('quote', 26) ?></div>
                    <p><?= e($t['isi']) ?></p>
                    <div class="testi-orang">
                        <?php if ($t['foto'] !== ''): ?>
                            <img class="testi-foto" src="<?= e($t['foto']) ?>" alt="<?= e($t['nama']) ?>">
                        <?php else: ?>
                            <span class="testi-foto"><?= e(strtoupper(substr(trim($t['nama']), 0, 1))) ?></span>
                        <?php endif; ?>
                        <div>
                            <strong><?= e($t['nama']) ?></strong>
                            <small><?= e($t['jabatan']) ?></small>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($sosial || $fotoGaleri): ?>
<section class="seksi alt" data-bagian="galeri" id="galeri">
    <div class="wadah">
        <div class="seksi-kepala">
            <div>
                <h2><?= e(pengaturan('beranda_galeri_judul', 'Galeri & Media Sosial')) ?></h2>
                <p><?= e(pengaturan('beranda_galeri_teks')) ?></p>
            </div>
            <div class="seksi-kanan"><a class="kecil" href="galeri">Buka Galeri <?= ikon('panah', 14) ?></a></div>
        </div>
        <?php if ($albumBeranda): ?>
        <div data-gulir=".gulir-foto" data-gulir-auto="6000">
            <div class="gulir-lacak gulir-foto">
                <?php foreach ($albumBeranda as $a): ?>
                    <?php $labelAlbum = album_label($a); ?>
                    <a class="foto-slide gulir-item" href="<?= e(album_url((string) $a['slug'])) ?>"
                       title="Buka album <?= e($labelAlbum) ?>">
                        <?= kotak_gambar((string) $a['sampul_efektif'], 'Album ' . $labelAlbum) ?>
                        <?php if ((int) $a['jml_foto'] > 0): ?>
                            <span class="album-jumlah"><?= angka($a['jml_foto']) ?> foto</span>
                        <?php endif; ?>
                        <span class="ket"><?= e($labelAlbum) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="gulir-titik-nav">
                <button type="button" class="gulir-panah" data-gulir-mundur aria-label="Album sebelumnya"><?= ikon('panah-kiri', 18) ?></button>
                <button type="button" class="gulir-panah" data-gulir-maju aria-label="Album berikutnya"><?= ikon('panah', 18) ?></button>
            </div>
        </div>
        <?php else: ?>
            <div class="kotak-info">Belum ada album galeri. Admin dapat membuatnya dari panel admin → <strong>Galeri Foto &amp; Video</strong>.</div>
        <?php endif; ?>

        <?php /* ===== SLIDER THUMBNAIL VIDEO (permintaan pemilik: "tambahkan slider thumbail video
             pada yang saya beri tanda merah") — baris thumbnail video kegiatan yang bisa digeser
             (tombol panah sendiri + geser otomatis), klik = popup pemutar. ===== */ ?>
        <?php if ($videoBeranda): ?>
        <div class="video-beranda" data-bagian-video="1" data-gulir=".gulir-video" data-gulir-auto="7000">
            <div class="video-beranda-kepala">
                <strong><?= ikon('video', 18) ?> Video Kegiatan
                    <span class="mut" style="font-weight:500">(<?= angka(count($videoBeranda)) ?> terbaru)</span></strong>
                <a href="galeri#videoGaleri">Semua Video <?= ikon('panah', 14) ?></a>
            </div>
            <div class="gulir-lacak gulir-video">
                <?php foreach ($videoBeranda as $v): $yt = youtube_id((string) $v['youtube']); ?>
                    <a class="video-thumb gulir-item" href="galeri#videoGaleri"
                       data-video-beranda data-yt="<?= e($yt) ?>" data-judul="<?= e($v['judul']) ?>"
                       title="Tonton video: <?= e($v['judul']) ?>">
                        <span class="video-thumb-gambar">
                            <img src="https://i.ytimg.com/vi/<?= e($yt) ?>/hqdefault.jpg"
                                 alt="Thumbnail video <?= e($v['judul']) ?>" loading="lazy" decoding="async">
                            <span class="video-thumb-main" aria-hidden="true"><?= ikon('youtube', 20) ?></span>
                        </span>
                        <span class="ket"><?= e($v['judul']) ?></span>
                        <span class="ket-yt"><?= ikon('youtube', 14) ?> YouTube</span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="gulir-titik-nav">
                <button type="button" class="gulir-panah" data-gulir-mundur aria-label="Video sebelumnya"><?= ikon('panah-kiri', 18) ?></button>
                <button type="button" class="gulir-panah" data-gulir-maju aria-label="Video berikutnya"><?= ikon('panah', 18) ?></button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($sosial): ?>
            <div class="medsos-baris">
                <?php foreach ($sosial as $so): ?>
                    <a class="<?= e(sosial_kelas((string) $so['ikon'])) ?>" href="<?= e($so['tautan']) ?>" target="_blank" rel="noopener noreferrer"><?= ikon_sosial($so['ikon'], 19) ?><?= e($so['nama']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php /* ===== POPUP PEMUTAR VIDEO (dibuka dari slider thumbnail) =====
   `src` iframe sengaja KOSONG saat halaman dimuat dan baru diisi ketika thumbnail diklik supaya
   video tidak dimuat lebih dulu (hemat & tidak memperlambat beranda). ===== */ ?>
<?php if ($videoBeranda): ?>
<dialog id="dialogVideoBeranda" class="dialog-pesan dialog-video" aria-labelledby="videoBerandaJudul">
    <h3 id="videoBerandaJudul"><?= ikon('youtube', 20) ?> <span id="videoBerandaNama">Video Kegiatan</span></h3>
    <div class="video-isi">
        <iframe id="videoBerandaIsi" title="Pemutar video kegiatan" src="" allowfullscreen
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                loading="lazy"></iframe>
    </div>
    <p class="video-ket">Video ditayangkan dari kanal YouTube resmi madrasah.</p>
    <div class="aksi-form">
        <a class="tombol tombol-garis tombol-kecil" href="galeri#videoGaleri">Lihat semua video</a>
        <button type="button" class="tombol tombol-kecil" data-tutup-dialog>Tutup</button>
    </div>
</dialog>
<?php endif; ?>

<?php situs_bawah(); ?>
