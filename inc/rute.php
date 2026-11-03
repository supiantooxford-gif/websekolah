<?php
/**
 * ALAMAT RAPI (routing) — SATU-SATUNYA tempat jalur permintaan diterjemahkan menjadi halaman.
 *
 * Permintaan pemilik:
 *   "Apakah bisa alamat https://…/web-sekolah/index.php menjadi https://…/web-sekolah/ … Misal:
 *    https://…/web-sekolah/halaman.php?slug=tenaga-pendidik menjadi
 *    https://…/web-sekolah/halaman/tenaga-pendidik."
 * Lingkup yang dipilih pemilik: **SEMUA alamat publik dibuat rapi** (termasuk kategori berita,
 * pencarian, fasilitas) dan **alamat lama dialihkan 301** ke alamat baru (satu alamat resmi saja).
 *
 * CARA KERJA (penting — jangan diubah tanpa membaca):
 *  • Server aplikasi ini **Caddy** dan **tidak membaca `.htaccess`**. Justru tidak perlu apa-apa:
 *    setiap jalur yang BUKAN berkas nyata sudah diserahkan ke `index.php` (perilaku yang diuji
 *    langsung pada aplikasi live: `/web-sekolah/halaman/tenaga-pendidik` → 200 dijawab `index.php`).
 *    Jadi `index.php` berperan sebagai **pintu depan (front controller)**: ia memanggil
 *    `rute_jalankan()`, menerjemahkan jalurnya, lalu memuat berkas halaman yang bersangkutan.
 *  • PHP tetap menerima jalur ASLI yang diketik pengunjung pada `REQUEST_URI`
 *    (mis. `/web-sekolah/halaman/tenaga-pendidik`) sedangkan `SCRIPT_NAME`/`PHP_SELF` hanya
 *    memuat `/index.php`. Karena itu jalur dibaca dari `REQUEST_URI` lalu **prefiks aplikasi
 *    dibuang** oleh `rute_prefiks()`.
 *  • Semua tautan yang DIBUAT aplikasi tetap **relatif** (mis. `halaman/tenaga-pendidik`,
 *    `./`, `galeri/slug`), TIDAK berawalan `/`. Alasannya: aplikasi ini bisa kelak dipasang pada
 *    domain sendiri, dan prefiks `/web-sekolah` yang dihitung sendiri akan ditambahkan lagi oleh
 *    platform sehingga tautannya bertumpuk (`/web-sekolah/web-sekolah/…`). Tautan relatif benar
 *    pada kedua keadaan. Agar tautan relatif tetap benar pada alamat yang lebih dalam
 *    (mis. `/halaman/tenaga-pendidik`), halaman publik memakai **`<base>` relatif**
 *    (`rute_base()`, mis. `../`) — bukan alamat absolut.
 *  • Alamat lama (`index.php`, `halaman.php?slug=…`, `berita_detail.php?slug=…`, dst.) dialihkan
 *    **301** ke alamat rapi oleh halaman bersangkutan lewat `rute_lama_301()`; pengalihan itu
 *    TIDAK berjalan bila halaman tersebut justru dipanggil oleh router (`rute_aktif()`).
 *  • Alamat yang tidak dikenal (termasuk `…/apa.php` dan jalur ngawur) dijawab **404 sungguhan**
 *    (sebelumnya selalu 200 berisi beranda = "soft 404" yang buruk untuk mesin pencari).
 *
 * Uji: `bash websk_uji_rute.sh` (pada salinan aplikasi di /tmp).
 */
declare(strict_types=1);

/**
 * Prefiks publik aplikasi (mis. `/web-sekolah`) atau `''` bila aplikasi dilayani pada akar domain.
 *
 * Diambil dari jalur yang benar-benar diminta (bukan diasumsikan): prefiks hanya dipakai bila
 * benar-benar ADA di depan jalur. Dua sumber, berurutan:
 *   1. nama folder aplikasi (= slug publik, memang begitu cara platform ini bekerja), dan
 *   2. prefiks pada `SCRIPT_NAME` (sebagian server, mis. `php -S`, menaruhnya di situ).
 * Bila tidak ada yang cocok (mis. aplikasi dipasang pada domain sendiri), hasilnya `''` sehingga
 * jalur dipakai apa adanya.
 */
function rute_prefiks(): string
{
    static $prefiks = null;
    if ($prefiks !== null) {
        return $prefiks;
    }
    $jalur = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $prefiks = '';
    $folder = '/' . basename(dirname(__DIR__));
    if ($jalur === $folder || str_starts_with($jalur, $folder . '/')) {
        $prefiks = $folder;
    } else {
        $skrip = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $pos = strrpos($skrip, '/');
        $awal = $pos === false ? '' : substr($skrip, 0, $pos);
        if ($awal !== '' && ($jalur === $awal || str_starts_with($jalur, $awal . '/'))) {
            $prefiks = $awal;
        }
    }
    return $prefiks;
}

/**
 * Jalur permintaan TANPA prefiks aplikasi, APA ADANYA (garis miring di ujung masih ada).
 * Dipakai untuk mengetahui apakah pengunjung menulis garis miring di ujung (`/berita/`).
 */
function rute_jalur_mentah(): string
{
    static $mentah = null;
    if ($mentah !== null) {
        return $mentah;
    }
    $p = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $pre = rute_prefiks();
    if ($pre !== '' && str_starts_with($p, $pre)) {
        $p = substr($p, strlen($pre));
    }
    $mentah = $p === '' ? '/' : $p;
    return $mentah;
}

/** Jalur permintaan TANPA prefiks aplikasi & tanpa garis miring di kedua ujung. */
function rute_jalur(): string
{
    static $jalur = null;
    if ($jalur !== null) {
        return $jalur;
    }
    $jalur = trim(rute_jalur_mentah(), '/');
    return $jalur;
}

/** Segmen jalur (sudah didekode). Nilai tak berbahaya: dipakai hanya untuk `$_GET`, bukan nama berkas. */
function rute_segmen(): array
{
    $seg = [];
    foreach (explode('/', rute_jalur()) as $s) {
        if ($s === '') {
            continue;
        }
        $seg[] = str_replace("\0", '', rawurldecode($s));
    }
    return $seg;
}

/**
 * Nilai `<base href>` halaman ini — RELATIF, bukan absolut.
 *
 * Halaman pada `/halaman/tenaga-pendidik` memerlukan `../` supaya `assets/style.css`, gambar, dan
 * `fetch('chat_asisten.php')` tetap menunjuk pada AKAR aplikasi. Satu segmen (mis. `/berita`)
 * cukup `./`. Relatif dipilih supaya tetap benar pada domain sendiri (tanpa prefiks).
 */
function rute_base(): string
{
    $jalur = rute_jalur();
    if ($jalur === '') {
        return './';
    }
    $n = count(explode('/', $jalur));
    return $n <= 1 ? './' : str_repeat('../', $n - 1);
}

/** Jalur kanonik halaman yang SEDANG dibuka (mis. `halaman/tenaga-pendidik`). */
function rute_kanonik(): string
{
    $k = $GLOBALS['__rute_kanonik'] ?? null;
    return is_string($k) ? $k : rute_jalur();
}

/** Apakah halaman ini dimuat oleh router (bukan dibuka langsung lewat berkas `.php`-nya)? */
function rute_aktif(): bool
{
    return !empty($GLOBALS['__rute_aktif']);
}

/** Bangun tautan RELATIF dari jalur kanonik + parameter (mis. `halaman/profil?kelas=3`). */
function rute_tautan(string $kanonik, array $q = []): string
{
    $kanonik = trim($kanonik, '/');
    $t = $kanonik === '' ? './' : $kanonik;
    $q = array_filter($q, static fn ($v): bool => (string) $v !== '');
    return $q ? $t . '?' . http_build_query($q) : $t;
}

/** Tautan halaman dinamis (jenis menu) — mis. `halaman/tenaga-pendidik`. */
function halaman_url(string $slug, array $q = []): string
{
    return rute_tautan('halaman/' . rawurlencode($slug), $q);
}

/** Tautan detail berita — mis. `berita/judul-berita`. */
function berita_url(string $slug): string
{
    return rute_tautan('berita/' . rawurlencode($slug));
}

/** Tautan kategori berita — mis. `berita/kategori/pengumuman`. */
function berita_kategori_url(string $slug): string
{
    return rute_tautan('berita/kategori/' . rawurlencode($slug));
}

/** Tautan isi album galeri — mis. `galeri/kegiatan-pramuka`. */
function album_url(string $slug): string
{
    return rute_tautan('galeri/' . rawurlencode($slug));
}

/**
 * Alamat LENGKAP (skema + hos + jalur yang diminta) untuk keperluan berbagi (tombol WhatsApp)
 * dan `<link rel="canonical">`.
 *
 * Jalur diambil APA ADANYA dari `REQUEST_URI` — jadi prefiks aplikasi (maupun domain sendiri)
 * selalu benar tanpa dihitung sendiri. Parameter sementara (`terkirim`, `aksi`) dibuang supaya
 * yang diumumkan ke mesin pencari adalah alamat bersihnya.
 */
function rute_url_kanonik(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || !preg_match('#^[A-Za-z0-9.\-]+(:\d+)?$#', $host)) {
        return '';
    }
    $jalur = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    // ⚠️ Pada alamat rapi, jalur yang diterima PHP bisa memuat garis miring GANDA
    // (mis. `/web-sekolah//halaman/tenaga-pendidik` — prefiks + jalur yang sama-sama berawalan `/`).
    // Tata letak & rute tidak terpengaruh, tetapi alamat resmi harus rapi → digandakan di sini.
    $jalur = (string) preg_replace('#/+#', '/', $jalur);
    $tanya = [];
    parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $tanya);
    unset($tanya['terkirim'], $tanya['aksi']);
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    return ($https ? 'https://' : 'http://') . $host . $jalur . ($tanya ? '?' . http_build_query($tanya) : '');
}

/**
 * Terjemahkan segmen menjadi rencana pemuatan halaman.
 *
 * @return array{berkas:string,get:array<string,string>,kanonik:string}|null null = alamat tidak dikenal
 */
function rute_peta(array $seg): ?array
{
    $n = count($seg);
    $k = strtolower($seg[0]);
    $p = static fn (int $i): string => (string) ($seg[$i] ?? '');

    switch ($k) {
        case 'berita':
            if ($n === 1) {
                return ['berkas' => 'berita.php', 'get' => [], 'kanonik' => 'berita'];
            }
            if ($n === 2) {
                return ['berkas' => 'berita_detail.php', 'get' => ['slug' => $p(1)], 'kanonik' => 'berita/' . rawurlencode($p(1))];
            }
            if ($n === 3 && strtolower($p(1)) === 'kategori') {
                return ['berkas' => 'berita.php', 'get' => ['kategori' => $p(2)], 'kanonik' => 'berita/kategori/' . rawurlencode($p(2))];
            }
            return null;

        case 'agenda':
            return $n === 1 ? ['berkas' => 'agenda.php', 'get' => [], 'kanonik' => 'agenda'] : null;

        case 'galeri':
            if ($n === 1) {
                return ['berkas' => 'galeri.php', 'get' => [], 'kanonik' => 'galeri'];
            }
            if ($n === 2) {
                return ['berkas' => 'galeri.php', 'get' => ['album' => $p(1)], 'kanonik' => 'galeri/' . rawurlencode($p(1))];
            }
            if ($n === 3 && strtolower($p(1)) === 'album') {
                // Bentuk panjang `galeri/album/<slug>` tetap dilayani (tautan lama yang mungkin
                // sudah tersebar), alamat resminya tetap `galeri/<slug>`.
                return ['berkas' => 'galeri.php', 'get' => ['album' => $p(2)], 'kanonik' => 'galeri/album/' . rawurlencode($p(2))];
            }
            return null;

        case 'kontak':
            return $n === 1 ? ['berkas' => 'kontak.php', 'get' => [], 'kanonik' => 'kontak'] : null;

        case 'ppdb':
            if ($n === 1) {
                return ['berkas' => 'ppdb.php', 'get' => [], 'kanonik' => 'ppdb'];
            }
            return ($n === 2 && strtolower($p(1)) === 'status')
                ? ['berkas' => 'ppdb_status.php', 'get' => [], 'kanonik' => 'ppdb/status']
                : null;

        case 'fasilitas':
            return $n === 1 ? ['berkas' => 'fasilitas.php', 'get' => [], 'kanonik' => 'fasilitas'] : null;

        case 'cari':
            return $n === 1 ? ['berkas' => 'cari.php', 'get' => [], 'kanonik' => 'cari'] : null;

        case 'asisten':
            return $n === 1 ? ['berkas' => 'asisten.php', 'get' => [], 'kanonik' => 'asisten'] : null;

        case 'halaman':
            return $n === 2
                ? ['berkas' => 'halaman.php', 'get' => ['slug' => $p(1)], 'kanonik' => 'halaman/' . rawurlencode($p(1))]
                : null;
    }
    return null;
}

/** Pengalihan 301 ke jalur kanonik (tautan RELATIF — aman pada domain sendiri maupun subfolder). */
function rute_alih_301(string $kanonik, array $q = []): void
{
    $tujuan = rute_tautan($kanonik, $q);
    if (!headers_sent()) {
        http_response_code(301);
        header('Location: ' . $tujuan, true, 301);
        exit;
    }
    echo '<script>location.replace(' . json_encode($tujuan) . ')</script>';
    exit;
}

/**
 * Dipanggil oleh halaman LAMA (`index.php`, `halaman.php`, `berita_detail.php`, …): alihkan 301 ke
 * alamat rapi. Tidak melakukan apa pun bila halaman itu justru dimuat oleh router.
 */
function rute_lama_301(string $kanonik, array $q = []): void
{
    if (rute_aktif()) {
        return;
    }
    rute_alih_301($kanonik, $q);
}

/** Halaman tidak ditemukan (404 sungguhan — bukan beranda berstatus 200). */
function rute_404(): void
{
    http_response_code(404);
    try {
        situs_atas(['judul' => 'Halaman tidak ditemukan']);
        echo '<section class="isi-halaman"><div class="wadah"><div class="artikel tengah">'
            . '<h1>Halaman tidak ditemukan</h1>'
            . '<p class="mut">Alamat yang Anda buka tidak tersedia. Periksa kembali penulisannya, '
            . 'atau kembali ke beranda untuk menelusuri website ini.</p>'
            . '<a class="tombol" href="./">Kembali ke Beranda</a>'
            . '</div></div></section>';
        situs_bawah();
    } catch (Throwable $e) {
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Halaman tidak ditemukan</title></head>'
            . '<body><h1>Halaman tidak ditemukan</h1><p><a href="./">Kembali ke Beranda</a></p></body></html>';
    }
    exit;
}

/** Jalankan rute permintaan (dipanggil dari `index.php` sebagai pintu depan). */
function rute_jalankan(): void
{
    $jalur = rute_jalur();

    // `index.php` (maupun `/index`) yang diketik langsung → alamat rapi beranda.
    if (strtolower($jalur) === 'index.php' || strtolower($jalur) === 'index') {
        rute_alih_301('', $_GET);
    }

    // Satu alamat resmi saja: garis miring di ujung dibuang (mis. `/berita/` → `/berita`).
    // Akar aplikasi (`/web-sekolah/`) bernilai '/' — TIDAK dialihkan.
    $mentah = rute_jalur_mentah();
    if ($mentah !== '/' && str_ends_with($mentah, '/')) {
        rute_alih_301(trim($mentah, '/'), $_GET);
    }

    $seg = rute_segmen();
    if (!$seg) {
        return;   // beranda — index.php melanjutkan sendiri
    }

    $peta = rute_peta($seg);
    if ($peta === null) {
        rute_404();
    }

    $GLOBALS['__rute_aktif'] = true;
    $GLOBALS['__rute_kanonik'] = $peta['kanonik'];
    foreach ($peta['get'] as $ruteKunci => $ruteNilai) {
        $_GET[$ruteKunci] = $ruteNilai;
    }
    require dirname(__DIR__) . '/' . $peta['berkas'];
    exit;
}
