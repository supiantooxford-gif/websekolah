<?php
/**
 * Kerangka tampilan: ikon SVG, header/nav publik, footer, kerangka panel admin.
 */
declare(strict_types=1);

/** Kumpulan ikon SVG sebaris (bukan pustaka luar, tidak butuh internet). */
function ikon(string $nama, int $ukuran = 20, string $kelas = ''): string
{
    $p = [
        'beranda'   => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.6v.2"/>',
        'book'      => '<path d="M4 4.5A2 2 0 0 1 6 2.5h13v19H6a2 2 0 0 0-2 2z"/><path d="M4 17.5h15"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.3 2.9-5.5 6.5-5.5s6.5 2.2 6.5 5.5"/><path d="M17 11a3 3 0 1 0 0-6"/><path d="M18 19.6c0-2.2-.8-3.8-2.2-4.8 3 .2 5.2 2.2 5.2 4.8"/>',
        'building'  => '<path d="M4 21V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16"/><path d="M15 21V10h3a2 2 0 0 1 2 2v9"/><path d="M8 7h3M8 11h3M8 15h3M2 21h20"/>',
        'newspaper' => '<path d="M4 5h11v16H4z"/><path d="M15 9h5v10a2 2 0 0 1-2 2h-3"/><path d="M7 9h5M7 13h5M7 17h3"/>',
        'camera'    => '<path d="M4 8h3l1.4-2h7.2L17 8h3v12H4z"/><circle cx="12" cy="13.5" r="3.5"/>',
        'video'     => '<rect x="3" y="6" width="12" height="12" rx="2"/><path d="M15 11l6-3v8l-6-3z"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5 12 12.5l8.5-6"/>',
        'telepon'   => '<path d="M7 3h3l2 5-2 1a12 12 0 0 0 5 5l1-2 5 2v3a2 2 0 0 1-2 2A17 17 0 0 1 5 5a2 2 0 0 1 2-2z"/>',
        'lokasi'    => '<path d="M12 21c4-4.6 7-8 7-11a7 7 0 1 0-14 0c0 3 3 6.4 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'jam'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3.2 2"/>',
        'kalender'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'search'    => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'tutup'     => '<path d="M6 6l12 12M18 6L6 18"/>',
        'panah'     => '<path d="M9 5l7 7-7 7"/>',
        'panah-kiri' => '<path d="M15 5l-7 7 7 7"/>',
        'bawah'     => '<path d="M6 9l6 6 6-6"/>',
        'check'     => '<path d="M4 12.5l5 5L20 6.5"/>',
        'star'      => '<path d="M12 3l2.9 5.9 6.1.9-4.5 4.4 1.1 6.2L12 17.6 6.4 20.4l1.1-6.2L3 9.8l6.1-.9z"/>',
        'trophy'    => '<path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3"/><path d="M12 14v4M9 21h6M10 18h4"/>',
        'medal'     => '<circle cx="12" cy="14" r="6"/><path d="M12 11.5l1.2 2.4 2.6.4-1.9 1.8.5 2.6-2.4-1.3-2.4 1.3.5-2.6-1.9-1.8 2.6-.4z"/><path d="M9 3h6l-2 6h-2z"/>',
        'sertifikat' => '<path d="M6 3h12v13H6z"/><path d="M9 7h6M9 10h6"/><circle cx="12" cy="18" r="3"/><path d="M10.4 20.6L9.8 23l2.2-1.2 2.2 1.2-.6-2.4"/>',
        'heart'     => '<path d="M12 20S4 14.5 4 9.6A4.6 4.6 0 0 1 12 6.6a4.6 4.6 0 0 1 8 3c0 4.9-8 10.4-8 10.4z"/>',
        'atom'      => '<circle cx="12" cy="12" r="2.2"/><path d="M12 3c4 0 8 4 8 9s-4 9-8 9-8-4-8-9 4-9 8-9z"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.4 2.6 3.6 5.6 3.6 9S14.4 18.4 12 21c-2.4-2.6-3.6-5.6-3.6-9S9.6 5.6 12 3z"/>',
        'gambar'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.8"/><path d="M21 16l-5.5-5L7 20"/>',
        'semua'     => '<rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2"/>',
        'grafik'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'minus'     => '<path d="M5 12h14"/>',
        'edit'      => '<path d="M4 20h4l10-10-4-4L4 16z"/><path d="M14 5l4 4"/>',
        'trash'     => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'naik'      => '<path d="M12 19V5"/><path d="M6 11l6-6 6 6"/>',
        'turun'     => '<path d="M12 5v14"/><path d="M6 13l6 6 6-6"/>',
        'simpan'    => '<path d="M5 4h11l3 3v13H5z"/><path d="M9 4v5h6V4M8 14h8v6H8z"/>',
        'unduh'     => '<path d="M12 4v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/>',
        'unggah'    => '<path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'kunci'     => '<circle cx="8" cy="15" r="4"/><path d="M11 12l8-8M17 6l2 2M15 8l2 2"/>',
        'keluar'    => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M15 8l4 4-4 4M19 12H9"/>',
        'pengguna'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5S20 17 20 21"/>',
        'setelan'   => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8"/>',
        'mata'      => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="2.5"/>',
        'lihat'     => '<path d="M4 5h11l5 5v9H4z"/><path d="M15 5v5h5"/><path d="M8 14h8"/>',
        'papan'     => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M12 16v4M8 20h8"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2.8h6V4"/><path d="M9 11h6M9 15h4"/>',
        'quote'     => '<path d="M9 6c-3 1.2-5 3.7-5 7v5h6v-6H7c0-2 1-3.4 3-4z"/><path d="M20 6c-3 1.2-5 3.7-5 7v5h6v-6h-3c0-2 1-3.4 3-4z"/>',
        'facebook'  => '<path d="M14.5 8.5H17V5h-2.5A4.5 4.5 0 0 0 10 9.5V12H8v3.5h2V21h3.5v-5.5H16l1-3.5h-3.5V9.5c0-.6.4-1 1-1z"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1.1"/>',
        'youtube'   => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="M11 9.5l4.5 2.5L11 14.5z"/>',
        'whatsapp'  => '<path d="M20.5 11.6a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5l1.5-4.3A8.5 8.5 0 1 1 20.5 11.6z"/><path d="M8.9 7.7h1.2l1 2.2-1.1 1.2c.6 1.3 1.7 2.4 3 3l1.2-1.1 2.2 1v1.2c0 .6-.6 1.2-1.3 1.2-3.6-.3-6.6-3.3-6.9-6.9 0-.7.6-1.3 1.2-1.3z"/>',
        'tiktok'    => '<path d="M14 4v9.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 6c.8 1.7 2.3 2.7 4 2.8"/>',
        'tanda'     => '<path d="M9 11l3 3 7-7"/><circle cx="12" cy="12" r="9"/>',
        'ttd'       => '<path d="M4 17c3-1 4.5-8 7-8s1.5 6 4 6 3-2 4-3"/><path d="M3 21h18"/>',
        'cetak'     => '<path d="M7 8V3h10v5"/><rect x="4" y="8" width="16" height="8" rx="2"/><path d="M7 16h10v5H7z"/>',
        'chat'      => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/>',
        'send'      => '<path d="M4 12l16-7-7 16-2-7z"/><path d="M11 14l9-9"/>',
        'silsilah'  => '<rect x="9" y="2.5" width="6" height="4.5" rx="1"/><rect x="2.5" y="13" width="6" height="4.5" rx="1"/><rect x="9" y="17" width="6" height="4.5" rx="1"/><rect x="15.5" y="13" width="6" height="4.5" rx="1"/><path d="M12 7v3M5.5 13v-3h13v3M12 13v4"/>',
        // Ikon penyunting isi (gaya Word) — perataan, daftar, dan penyisipan gambar.
        'rata-kiri'   => '<path d="M3 6h18M3 10h11M3 14h18M3 18h11"/>',
        'rata-tengah' => '<path d="M3 6h18M6 10h12M3 14h18M6 18h12"/>',
        'rata-kanan'  => '<path d="M3 6h18M10 10h11M3 14h18M10 18h11"/>',
        'rata-penuh'  => '<path d="M3 6h18M3 10h18M3 14h18M3 18h12"/>',
        'daftar'      => '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4.5" cy="6" r="1.4"/><circle cx="4.5" cy="12" r="1.4"/><circle cx="4.5" cy="18" r="1.4"/>',
        'daftar-angka' => '<path d="M10 6h11M10 12h11M10 18h11"/><path d="M4 4.6h1.2V8M3.6 11h2L3.6 14h2M3.6 17h2v1.3h-2V20h2"/>',
        'sisip-gambar' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.8"/><path d="M21 16l-5.5-5L7 20"/>',
        'bersih-format' => '<path d="M5 5h9l-4 14H8"/><path d="M12 5l7 14"/>',
        'undo'        => '<path d="M4 10h9a5 5 0 0 1 0 10H8"/><path d="M8 6L4 10l4 4"/>',
        'redo'        => '<path d="M20 10h-9a5 5 0 0 0 0 10h5"/><path d="M16 6l4 4-4 4"/>',
    ];
    $isi = $p[$nama] ?? $p['info'];
    $kelasAt = $kelas !== '' ? ' class="' . e($kelas) . '"' : '';
    return '<svg' . $kelasAt . ' width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $isi . '</svg>';
}

function pesan_flash(): void
{
    foreach (flash_ambil() as $f) {
        $kelas = $f['jenis'] === 'ok' ? 'alert-ok' : ($f['jenis'] === 'info' ? 'alert-info' : 'alert-err');
        echo '<div class="alert ' . $kelas . '">' . $f['pesan'] . '</div>';
    }
}

/** Ikon media sosial → SVG. */
function ikon_sosial(string $nama, int $ukuran = 18): string
{
    return ikon(sosial_jenis($nama), $ukuran);
}

/**
 * Jenis media sosial yang dikenali (nama ikon). Dipakai juga untuk memilih warna tombolnya,
 * sehingga "Facebook" selalu biru, "Instagram" bergradasi, "YouTube" merah, "WhatsApp" hijau,
 * "TikTok" hitam, dan sisanya memakai warna tema.
 */
function sosial_jenis(string $nama): string
{
    $n = strtolower(trim($nama));
    $peta = ['facebook' => 'facebook', 'fb' => 'facebook', 'instagram' => 'instagram', 'ig' => 'instagram',
        'youtube' => 'youtube', 'yt' => 'youtube', 'whatsapp' => 'whatsapp', 'wa' => 'whatsapp',
        'tiktok' => 'tiktok', 'twitter' => 'globe', 'x' => 'globe'];
    return $peta[$n] ?? 'globe';
}

/** Kelas warna tombol media sosial (mis. `sos-facebook`). */
function sosial_kelas(string $nama, string $awalan = 'sos-'): string
{
    return $awalan . sosial_jenis($nama);
}

// =====================================================================================
// HEADER & FOOTER SITUS PUBLIK
// =====================================================================================
/**
 * @param array $opt ['judul' => <title>, 'deskripsi' =>, 'aktif' => slug menu aktif]
 */
function situs_atas(array $opt = []): void
{
    $s = sekolah();
    $judul = ($opt['judul'] ?? '') !== '' ? $opt['judul'] . ' — ' . $s['nama'] : $s['nama'];
    $desk = $opt['deskripsi'] ?? (sek('slogan') . ' — ' . $s['nama']);
    $sosial = all('SELECT * FROM sosial WHERE aktif = 1 ORDER BY urutan, id');
    $telp = sek('telepon');
    $wa = sek('whatsapp');
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?></title>
<meta name="description" content="<?= e(potong($desk, 180)) ?>">
<meta name="theme-color" content="<?= TEMA_HIJAU ?>">
<?php if (sek('logo') !== ''): ?><link rel="icon" href="<?= e(sek('logo')) ?>"><?php endif; ?>
<?php
// ALAMAT RAPI: <base> RELATIF supaya seluruh tautan/aset relatif pada halaman yang lebih dalam
// (mis. /halaman/tenaga-pendidik) tetap menunjuk AKAR aplikasi. Ditambah alamat resmi halaman
// (canonical) yang diambil apa adanya dari jalur yang diminta — jadi tetap benar baik pada alamat
// bawaan (…/web-sekolah/…) maupun bila kelak dipasang pada domain sendiri.
?>
<base href="<?= e(rute_base()) ?>">
<?php $ruteKanonik = rute_url_kanonik(); if ($ruteKanonik !== ''): ?>
<link rel="canonical" href="<?= e($ruteKanonik) ?>">
<?php endif; ?>
<link rel="stylesheet" href="assets/style.css?v=<?= APP_VERSION ?>">
</head>
<body class="<?= e($opt['kelas_body'] ?? '') ?>">
<div class="notif-kerangka"><div class="notif-isi" id="notifIsi"></div></div>
<header class="situs-head">
    <div class="topbar">
        <div class="wadah topbar-dalam">
            <div class="topbar-kiri">
                <?php if ($telp !== ''): ?><span class="topbar-item"><?= ikon('telepon', 15) ?><?= e($telp) ?></span><?php endif; ?>
                <?php if (sek('email') !== ''): ?><span class="topbar-item"><?= ikon('mail', 15) ?><a href="mailto:<?= e(sek('email')) ?>"><?= e(sek('email')) ?></a></span><?php endif; ?>
                <?php if (sek('alamat') !== ''): ?><span class="topbar-item"><?= ikon('lokasi', 15) ?><?= e($s['kecamatan'] !== '' ? 'Kec. ' . $s['kecamatan'] . ', Kab. ' . $s['kabupaten'] : sek('alamat')) ?></span><?php endif; ?>
            </div>
            <div class="topbar-kanan">
                <div class="topbar-sosial">
                    <?php foreach ($sosial as $so): ?>
                        <a class="<?= e(sosial_kelas((string) $so['ikon'])) ?>" href="<?= e($so['tautan']) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($so['nama']) ?>"><?= ikon_sosial($so['ikon']) ?></a>
                    <?php endforeach; ?>
                </div>
                <form class="cari-top" action="cari" method="get" role="search">
                    <input type="search" name="q" placeholder="Cari informasi..." value="<?= e($_GET['q'] ?? '') ?>" aria-label="Cari informasi">
                    <button type="submit" aria-label="Cari"><?= ikon('search', 16) ?></button>
                </form>
            </div>
        </div>
    </div>

    <div class="head-utama">
        <div class="wadah head-dalam">
            <a class="brand" href="./">
                <?php if (sek('logo') !== ''): ?>
                    <img class="brand-logo" src="<?= e(sek('logo')) ?>" alt="Logo <?= e($s['nama']) ?>">
                <?php else: ?>
                    <span class="brand-lambang"><?= ikon('building', 30) ?></span>
                <?php endif; ?>
                <span class="brand-teks">
                    <strong><?= e($s['nama']) ?></strong>
                    <small><?= e(sek('slogan', APP_SUB)) ?></small>
                </span>
            </a>
            <button class="tombol-nav" type="button" id="tombolNav" aria-label="Buka menu" aria-expanded="false"><?= ikon('menu', 22) ?></button>
            <nav class="nav-utama" id="navUtama" aria-label="Menu utama">
                <?php foreach (menu_semua() as $m): ?>
                    <?php
                    $anak = $m['anak'] ?? [];
                    // Menu "disorot" (mis. PPDB Online) TIDAK dimasukkan ke deretan menu — tombolnya
                    // diletakkan tersendiri di ujung kanan header supaya tidak terlipat ke baris kedua.
                    if (empty($m['tampil_nav']) || !empty($m['sorot'])) {
                        continue;
                    }
                    $aktifNav = (($opt['aktif'] ?? '') === $m['slug']) ? ' aktif' : '';
                    ?>
                    <div class="nav-item<?= $anak ? ' ada-anak' : '' ?><?= $aktifNav ?>">
                        <a href="<?= e(menu_url($m)) ?>" <?= menu_luar($m) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                            <?= e($m['label']) ?><?php if ($anak): ?><?= ikon('bawah', 14) ?><?php endif; ?>
                        </a>
                        <?php if ($anak): ?>
                            <div class="nav-anak">
                                <?php foreach ($anak as $a): ?>
                                    <a href="<?= e(menu_url($a)) ?>" <?= menu_luar($a) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($a['label']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
            <?php
            // Tombol ajakan (menu ber-tanda "sorot", mis. PPDB Online) — diletakkan di ujung kanan.
            $tombolSorot = array_values(array_filter(menu_semua(), static fn (array $m): bool => !empty($m['sorot']) && !empty($m['tampil_nav'])));
            ?>
            <?php if ($tombolSorot): ?>
                <div class="head-aksi">
                    <?php foreach ($tombolSorot as $m): ?>
                        <a class="nav-tombol" href="<?= e(menu_url($m)) ?>" <?= menu_luar($m) ? 'target="_blank" rel="noopener noreferrer"' : '' ?><?= ppdb_tautan_atribut(menu_url($m)) ?>>
                            <?= ikon('pengguna', 16) ?><?= e($m['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="situs-isi">
<?php
// Pesan singkat dari aksi sebelumnya (mis. setelah mengirim pendapat polling / pesan kontak).
$flashSitus = flash_ambil();
if ($flashSitus) {
    echo '<div class="wadah flash-kerangka">';
    foreach ($flashSitus as $f) {
        $kelas = $f['jenis'] === 'ok' ? 'alert-ok' : ($f['jenis'] === 'info' ? 'alert-info' : 'alert-err');
        echo '<div class="alert ' . $kelas . '">' . $f['pesan'] . '</div>';
    }
    echo '</div>';
}
}

function situs_bawah(): void
{
    $s = sekolah();
    $sosial = all('SELECT * FROM sosial WHERE aktif = 1 ORDER BY urutan, id');
    ?>
</main>
<footer class="situs-kaki">
    <div class="wadah kaki-dalam">
        <div class="kaki-kolom kaki-brand">
            <?php if (sek('logo') !== ''): ?>
                <img class="kaki-logo" src="<?= e(sek('logo')) ?>" alt="Logo <?= e($s['nama']) ?>">
            <?php else: ?>
                <span class="brand-lambang besar"><?= ikon('building', 32) ?></span>
            <?php endif; ?>
            <div>
                <strong><?= e($s['nama']) ?></strong>
                <div class="kaki-slogan"><?= e(sek('slogan')) ?></div>
                <p class="kaki-about"><?= e(pengaturan('footer_teks')) ?></p>
            </div>
        </div>
        <?php /* Kolom "Menu" pada kaki halaman DIHAPUS atas permintaan pemilik (v1.14.0): seluruh menu
                 sudah tersedia pada bilah navigasi di atas. Jangan dicetak kembali. */ ?>
        <div class="kaki-kolom">
            <h4>Kontak Kami</h4>
            <ul class="kaki-kontak">
                <?php if (sek('alamat') !== ''): ?><li><?= ikon('lokasi', 16) ?><span><?= e(alamat_lengkap()) ?></span></li><?php endif; ?>
                <?php if (sek('telepon') !== ''): ?><li><?= ikon('telepon', 16) ?><span><?= e(sek('telepon')) ?></span></li><?php endif; ?>
                <?php if (sek('email') !== ''): ?><li><?= ikon('mail', 16) ?><span><?= e(sek('email')) ?></span></li><?php endif; ?>
                <?php if (sek('jam_operasional') !== ''): ?><li><?= ikon('jam', 16) ?><span><?= e(sek('jam_operasional')) ?></span></li><?php endif; ?>
            </ul>
        </div>
        <div class="kaki-kolom">
            <h4>Ikuti Kami</h4>
            <div class="kaki-sosial">
                <?php foreach ($sosial as $so): ?>
                    <a class="<?= e(sosial_kelas((string) $so['ikon'])) ?>" href="<?= e($so['tautan']) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($so['nama']) ?>"><?= ikon_sosial($so['ikon'], 20) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="kaki-bawah">
        <div class="wadah">
            &copy; <?= date('Y') ?> <?= e($s['nama']) ?>. Seluruh hak cipta dilindungi.
            <span class="kaki-versi"><?= APP_NAME ?> v<?= APP_VERSION ?></span>
        </div>
    </div>
</footer>
<?php if (!ppdb_dibuka()): ?>
    <?php /* Popup "pendaftaran belum dibuka" — dipakai tombol Daftar Sekarang (lihat initPpdbTutup()). */ ?>
    <dialog id="dialogPpdbTutup" class="dialog-pesan" aria-labelledby="ppdbTutupJudul">
        <h3 id="ppdbTutupJudul"><?= ikon('info', 20) ?> Pendaftaran Belum Dibuka</h3>
        <p><?= e(ppdb_tutup_pesan()) ?></p>
        <p class="kecil mut mb0">Silakan cek kembali halaman ini secara berkala, atau hubungi panitia
            <?= e(pengaturan('ppdb_kontak', 'PPDB')) ?> untuk informasi jadwal pembukaan.</p>
        <div class="aksi-form">
            <a class="tombol tombol-garis tombol-kecil" href="ppdb">Lihat Informasi PPDB</a>
            <button type="button" class="tombol tombol-kecil" data-tutup-dialog>Mengerti</button>
        </div>
    </dialog>
<?php endif; ?>
<?php
// =====================================================================================
// ASISTEN AI — KOTAK OBROLAN MENGAMBANG (menggantikan tombol "Hubungi kami via WhatsApp")
// =====================================================================================
// Tanpa JavaScript tombolnya adalah TAUTAN ke halaman `asisten.php` (formulir biasa), sehingga
// pengunjung tetap dapat bertanya; bila skrip aktif, kotak obrolan mengambang yang dipakai
// (`initAsisten()` di assets/app.js + endpoint chat_asisten.php).
?>
<div class="asisten-kotak" id="asistenKotak" hidden>
    <div class="asisten-kepala">
        <?= asisten_ava('asisten-ava') ?>
        <span class="asisten-judul">
            <strong>Asisten Website</strong>
            <small><?= e(sekolah()['nama']) ?></small>
        </span>
        <button type="button" class="asisten-tutup" data-asisten-tutup aria-label="Tutup obrolan"><?= ikon('tutup', 18) ?></button>
    </div>
    <div class="asisten-isi" id="asistenIsi" aria-live="polite"></div>
    <div class="asisten-saran" id="asistenSaran"></div>
    <form class="asisten-kirim" id="asistenForm" action="chat_asisten.php" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tanya">
        <label class="sr-only" for="asistenPesan">Tuliskan pertanyaan</label>
        <input type="text" id="asistenPesan" name="pesan" autocomplete="off"
               maxlength="<?= (int) ASISTEN_PESAN_MAKS ?>" placeholder="Tulis pertanyaan Bapak/Ibu…">
        <button type="submit" class="asisten-kirim-tombol" aria-label="Kirim pertanyaan"><?= ikon('send', 18) ?></button>
    </form>
    <div class="asisten-kaki">Jawaban disusun otomatis dari data website. Percakapan tidak disimpan.</div>
</div>
<a class="tombol-asisten" id="asistenTombol" href="asisten" data-asisten-buka
   data-ava="<?= e(asisten_foto_src()) ?>"
   title="Tanya asisten website" aria-label="Tanya asisten website">
    <img class="tombol-asisten-foto" src="<?= e(asisten_foto_src()) ?>" alt=""
         width="72" height="72" decoding="async">
    <span class="titik-on" aria-hidden="true"></span>
</a>
<script src="assets/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
<?php
}

/** Spanduk judul halaman dalam (dengan remah roti). */
function hero_halaman(string $judul, string $sub = '', array $remah = []): void
{
    ?>
<section class="hero-halaman">
    <div class="wadah">
        <h1><?= e($judul) ?></h1>
        <?php if ($sub !== ''): ?><p><?= e($sub) ?></p><?php endif; ?>
        <nav class="remah" aria-label="Remah roti">
            <a href="./">Beranda</a>
            <?php foreach ($remah as $r): ?>
                <span>/</span>
                <?php if (!empty($r['tautan'])): ?><a href="<?= e($r['tautan']) ?>"><?= e($r['label']) ?></a>
                <?php else: ?><span class="aktif"><?= e($r['label']) ?></span><?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </div>
</section>
<?php
}

// =====================================================================================
// KERANGKA PANEL ADMIN
// =====================================================================================
function admin_menu(): array
{
    // Akun "Editor Berita" hanya melihat menu Berita + Galeri (halaman lain dijawab 403 oleh
    // require_login() lewat berita_berkas_boleh()).
    if (is_editor_berita()) {
        return [
            ['judul' => 'Ringkasan', 'item' => [
                ['index.php', 'Dashboard', 'beranda'],
            ]],
            ['judul' => 'Berita', 'item' => [
                ['berita.php', 'Daftar Berita', 'newspaper'],
                ['berita_edit.php', 'Tulis Berita', 'plus'],
                ['berita_kategori.php', 'Kategori Berita', 'papan'],
            ]],
            ['judul' => 'Galeri', 'item' => [
                ['galeri.php', 'Foto & Video', 'camera'],
            ]],
        ];
    }
    return [
        ['judul' => 'Ringkasan', 'item' => [
            ['index.php', 'Dashboard', 'beranda'],
        ]],
        ['judul' => 'Konten Website', 'item' => [
            ['menu.php', 'Menu & Halaman', 'papan'],
            ['profil.php', 'Profil Lengkap (Isian)', 'book'],
            ['struktur.php', 'Struktur Organisasi (Bagan)', 'silsilah'],
            ['slider.php', 'Slider Beranda', 'gambar'],
            ['medsos.php', 'Media Sosial', 'globe'],
            ['faq.php', 'FAQ Asisten', 'chat'],
            ['polling.php', 'Polling Pendapat', 'grafik'],
            ['keunggulan.php', 'Keunggulan & Program', 'star'],
            ['berita.php', 'Berita', 'newspaper'],
            ['agenda.php', 'Agenda', 'kalender'],
            ['galeri.php', 'Galeri Foto & Video', 'camera'],
            ['fasilitas.php', 'Fasilitas', 'building'],
            ['testimoni.php', 'Apa Kata Mereka', 'quote'],
        ]],
        ['judul' => 'Kesiswaan & Kepegawaian', 'item' => [
            ['guru.php', 'Data Guru & Tendik', 'users'],
            ['perangkat.php', 'Perangkat Guru', 'unduh'],
            // v1.18.0 — MENU KURIKULUM: satu panel berisi Kaldik Operasional, Jadwal Mengajar, dan
            // Dokumen Kurikulum (Administrator + akun Editor).
            ['kurikulum.php', 'Kurikulum', 'kalender'],
            ['periode.php', 'Tahun Ajaran & Semester', 'kalender'],
            ['siswa.php', 'Data Siswa', 'pengguna'],
            ['kohort.php', 'Kohort Siswa', 'grafik'],
            ['siswa_foto.php', 'Foto Siswa (Massal)', 'camera'],
            ['pip.php', 'Layanan PIP', 'sertifikat'],
            ['kelas.php', 'Data Kelas', 'semua'],
            ['kenaikan.php', 'Kenaikan & Kelulusan', 'naik'],
            ['prestasi.php', 'Prestasi & Pelatihan', 'medal'],
            ['prestasi_kelas.php', 'Prestasi per Kelas', 'trophy'],
            ['alumni.php', 'Data Alumni', 'medal'],
        ]],
        ['judul' => 'PPDB Online', 'item' => [
            ['ppdb.php', 'Pengaturan PPDB', 'setelan'],
            ['ppdb_gelombang.php', 'Gelombang & Jadwal', 'kalender'],
            ['ppdb_pendaftar.php', 'Data Pendaftar', 'clipboard'],
            ['ppdb_tarik.php', 'Tarik Data Pendaftar (Tahun Lama)', 'unduh'],
        ]],
        ['judul' => 'Pesan & Pengaturan', 'item' => [
            ['pesan.php', 'Pesan Masuk', 'mail'],
            ['identitas.php', 'Identitas Sekolah', 'building'],
            ['pengguna.php', 'Pengguna & Akun', 'kunci'],
            ['log.php', 'Log Masuk', 'grafik'],
        ]],
    ];
}

function admin_atas(string $judul, string $berkas = ''): void
{
    $u = current_user();
    $berkas = $berkas !== '' ? $berkas : basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $pesanBaru = jumlah('SELECT COUNT(*) FROM kontak_pesan WHERE dibaca = 0');
    $pendaftarBaru = jumlah("SELECT COUNT(*) FROM ppdb_pendaftar WHERE status IN ('baru','verifikasi')");
    $s = sekolah();
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> — Panel Admin</title>
<meta name="robots" content="noindex,nofollow">
<?php if (sek('logo') !== ''): ?><link rel="icon" href="<?= e(sek('logo')) ?>"><?php endif; ?>
<link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="admin.css?v=<?= APP_VERSION ?>">
</head>
<body class="admin">
<aside class="admin-side" id="adminSide">
    <div class="admin-brand">
        <?php if (sek('logo') !== ''): ?><img class="admin-logo" src="<?= e(sek('logo')) ?>" alt="Logo">
        <?php else: ?><span class="brand-lambang"><?= ikon('building', 24) ?></span><?php endif; ?>
        <span class="admin-brand-teks"><strong><?= e($s['nama']) ?></strong><small>Panel Admin</small></span>
    </div>
    <nav class="admin-nav">
        <?php foreach (admin_menu() as $grup): ?>
            <div class="admin-grup"><?= e($grup['judul']) ?></div>
            <?php foreach ($grup['item'] as $it): ?>
                <?php
                $lencana = '';
                if ($it[0] === 'pesan.php' && $pesanBaru > 0) {
                    $lencana = '<span class="lencana">' . $pesanBaru . '</span>';
                } elseif ($it[0] === 'ppdb_pendaftar.php' && $pendaftarBaru > 0) {
                    $lencana = '<span class="lencana">' . $pendaftarBaru . '</span>';
                }
                ?>
                <a href="<?= e($it[0]) ?>" class="<?= $berkas === $it[0] ? 'aktif' : '' ?>"><?= ikon($it[2], 18) ?><span><?= e($it[1]) ?></span><?= $lencana ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="admin-nav-kaki">
        <a href="../" target="_blank"><?= ikon('mata', 18) ?><span>Lihat Website</span></a>
        <a href="logout.php"><?= ikon('keluar', 18) ?><span>Keluar</span></a>
    </div>
</aside>
<div class="admin-utama">
    <header class="admin-head">
        <button type="button" class="admin-nav-tombol" id="adminNavTombol" aria-label="Buka menu"><?= ikon('menu', 22) ?></button>
        <h1><?= e($judul) ?></h1>
        <div class="admin-head-kanan">
            <span class="admin-user"><?= ikon('pengguna', 16) ?> <?= e($u['nama'] ?? '') ?></span>
        </div>
    </header>
    <div class="admin-isi">
        <?php pesan_flash(); ?>
<?php
}

function admin_bawah(): void
{
    ?>
    </div>
    <footer class="admin-kaki">
        <?= APP_NAME ?> v<?= APP_VERSION ?> &middot; <?= e(sekolah()['nama']) ?>
    </footer>
</div>
<script src="../assets/app.js?v=<?= APP_VERSION ?>"></script>
<script src="admin.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
<?php
}

/** Kartu statistik panel admin. */
function kartu_stat(string $judul, string $angka, string $ikonNama, string $tautan = '', string $ket = ''): void
{
    $isi = '<div class="stat-ikon">' . ikon($ikonNama, 22) . '</div>'
        . '<div class="stat-teks"><div class="stat-angka">' . e($angka) . '</div><div class="stat-judul">' . e($judul) . '</div>'
        . ($ket !== '' ? '<div class="stat-ket">' . e($ket) . '</div>' : '') . '</div>';
    echo $tautan !== ''
        ? '<a class="kartu kartu-stat" href="' . e($tautan) . '">' . $isi . '</a>'
        : '<div class="kartu kartu-stat">' . $isi . '</div>';
}
