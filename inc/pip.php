<?php
/**
 * LAYANAN PIP (Program Indonesia Pintar) — modul halaman publik "Informasi → Layanan PIP".
 *
 * Halaman ini memuat DUA bagian (sesuai permintaan pemilik):
 *   1. **Informasi Seputar Program Indonesia Pintar** — teks yang disunting admin + berkas
 *      **SK Penerima PIP** yang diunggah admin.
 *   2. **Daftar Penerima PIP** — tabel No, Nama, NISN, Kelas, Nominal, Kartu PIP, Keterangan.
 *      Kolom Keterangan memuat tautan **Surat Kuasa** yang diunggah admin.
 *
 * **KARTU PIP DILINDUNGI KATA SANDI.** Kata sandi ditetapkan admin (`pengaturan` kunci `pip_sandi`,
 * disimpan sebagai HASH bcrypt). Saat peserta didik/orang tua menekan tombol unduh, halaman
 * `pip_kartu.php` meminta sandi; setelah benar, berkasnya **DIALIRKAN oleh server ini**
 * (`pip_kirim_berkas()`) sehingga URL media (CDN) yang sebenarnya tidak pernah dikirim ke peramban.
 * Jadi sandi benar-benar melindungi berkasnya, bukan sekadar menyembunyikan tautan.
 */
declare(strict_types=1);

// =====================================================================================
// PENGATURAN HALAMAN (disimpan pada tabel `pengaturan`)
// =====================================================================================

/** Teks "Informasi Seputar Program Indonesia Pintar" (HTML). */
function pip_info(): string
{
    return (string) pengaturan('pip_info', (string) PIP_INFO_BAWAAN);
}

/** Judul bagian informasi (dapat disesuaikan admin). */
function pip_judul_info(): string
{
    return (string) pengaturan('pip_judul_info', 'Informasi Seputar Program Indonesia Pintar (PIP)');
}

/** Judul bagian daftar penerima (nama sekolah ditambahkan oleh pemanggil bila perlu). */
function pip_judul_daftar(): string
{
    return (string) pengaturan('pip_judul_daftar', 'Daftar Penerima PIP');
}

// =====================================================================================
// TAHUN LAYANAN PIP (v18)
//
// Permintaan pemilik: *"tambahkan tahun (karena setiap tahun SK dan penerima PIP tidak sama)"*.
// Yang MENEMPEL pada tahun: **SK Penerima PIP**, **catatan (kotak "Info Penting")**, dan
// **daftar penerima**. Yang tetap GLOBAL (sama untuk semua tahun): teks/judul informasi program,
// judul bagian daftar, dan **kata sandi Kartu PIP**.
// =====================================================================================

/** Seluruh tahun layanan PIP (urut `urutan` lalu id). */
function pip_tahun_list(bool $aktifSaja = false): array
{
    static $cache = [];
    $kunci = $aktifSaja ? '1' : '0';
    if (isset($cache[$kunci])) {
        return $cache[$kunci];
    }
    try {
        $w = $aktifSaja ? 'WHERE aktif = 1' : '';
        return $cache[$kunci] = all('SELECT * FROM pip_tahun ' . $w . ' ORDER BY urutan, id');
    } catch (Throwable $e) {
        return $cache[$kunci] = [];
    }
}

function pip_tahun_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    try {
        return one('SELECT * FROM pip_tahun WHERE id = ?', [$id]);
    } catch (Throwable $e) {
        return null;
    }
}

/** Jumlah tahun (dipakai panel). */
function pip_tahun_jumlah(): int
{
    try {
        return (int) jumlah('SELECT COUNT(*) FROM pip_tahun');
    } catch (Throwable $e) {
        return 0;
    }
}

/** Urutan berikutnya untuk tahun baru (kelipatan 10). */
function pip_tahun_urutan_baru(): int
{
    try {
        return (int) jumlah('SELECT COALESCE(MAX(urutan), 0) FROM pip_tahun') + 10;
    } catch (Throwable $e) {
        return 10;
    }
}

/** Tahun yang dipakai pengunjung: `?tahun=ID` bila sah, selain itu tahun AKTIF (lalu tahun pertama). */
function pip_tahun_kini(): ?array
{
    $minta = (int) ($_GET['tahun'] ?? 0);
    if ($minta > 0) {
        $t = pip_tahun_by_id($minta);
        if ($t !== null && (int) $t['aktif'] === 1) {
            return $t;
        }
    }
    $aktif = (int) pengaturan('pip_tahun_aktif', '0');
    $t = pip_tahun_by_id($aktif);
    if ($t !== null) {
        return $t;
    }
    $semua = pip_tahun_list(true);
    if ($semua) {
        return $semua[0];
    }
    $semua = pip_tahun_list(false);
    return $semua ? $semua[0] : null;
}

/** Tandai sebuah tahun sebagai tahun AKTIF (yang tampil lebih dulu bagi pengunjung). */
function pip_tahun_aktif_set(int $id): void
{
    if (pip_tahun_by_id($id) === null) {
        return;
    }
    pengaturan_set('pip_tahun_aktif', (string) $id);
}

/** Label tahun untuk tampilan: "2026" (kosong → "-"). */
function pip_tahun_label($t): string
{
    $row = is_array($t) ? $t : pip_tahun_by_id((int) $t);
    if (!$row) {
        return '';
    }
    $n = trim((string) $row['tahun']);
    return $n !== '' ? $n : '(tanpa tahun)';
}

/** Nama tahun yang dinormalkan (maks 24 karakter, spasi dirapatkan). */
function pip_tahun_bersih(string $teks): string
{
    $t = trim((string) preg_replace('/\s+/', ' ', $teks));
    return $t === '' ? '' : substr($t, 0, 24);
}

/** Sudah dipakai tahun lain? (dibandingkan tanpa peduli besar-kecil huruf) */
function pip_tahun_terpakai(string $tahun, int $kecualiId = 0): bool
{
    try {
        return one('SELECT id FROM pip_tahun WHERE lower(tahun) = lower(?) AND id <> ?', [$tahun, $kecualiId]) !== null;
    } catch (Throwable $e) {
        return false;
    }
}

/** Berapa penerima pada sebuah tahun (dipakai peringatan saat menghapus tahun). */
function pip_tahun_penerima_jumlah(int $tahunId): int
{
    try {
        return (int) jumlah('SELECT COUNT(*) FROM pip_penerima WHERE tahun_id = ?', [$tahunId]);
    } catch (Throwable $e) {
        return 0;
    }
}

/** URL berkas SK Penerima PIP sebuah tahun (kosong bila belum diunggah). */
function pip_sk($tahun = null): string
{
    $t = $tahun === null ? pip_tahun_kini() : (is_array($tahun) ? $tahun : pip_tahun_by_id((int) $tahun));
    return $t ? (string) $t['sk'] : '';
}

/** Nama berkas SK Penerima PIP sebuah tahun (untuk tautan/unduhan). */
function pip_sk_nama($tahun = null): string
{
    $t = $tahun === null
        ? pip_tahun_kini()
        : (is_array($tahun) ? $tahun : pip_tahun_by_id((int) $tahun));
    $n = $t ? trim((string) $t['sk_nama']) : '';
    return $n !== '' ? $n : 'SK-Penerima-PIP';
}

/** Catatan tahun terpilih (tampil sebagai kotak "Info Penting"; boleh kosong). */
function pip_catatan($tahun = null): string
{
    $t = $tahun === null
        ? pip_tahun_kini()
        : (is_array($tahun) ? $tahun : pip_tahun_by_id((int) $tahun));
    return $t ? (string) $t['catatan'] : '';
}

// =====================================================================================
// KATA SANDI KARTU PIP
// =====================================================================================

/** Apakah kata sandi Kartu PIP sudah ditetapkan admin? */
function pip_sandi_ada(): bool
{
    return trim((string) pengaturan('pip_sandi', '')) !== '';
}

/**
 * Tetapkan kata sandi Kartu PIP (hash bcrypt). Mengembalikan pesan galat, atau '' bila berhasil.
 */
function pip_sandi_set(string $sandi): string
{
    $sandi = trim($sandi);
    if ($sandi === '') {
        return 'Kata sandi tidak boleh kosong.';
    }
    if (strlen($sandi) < PIP_SANDI_MIN) {
        return 'Kata sandi minimal ' . PIP_SANDI_MIN . ' karakter.';
    }
    if (strlen($sandi) > PIP_SANDI_MAKS) {
        return 'Kata sandi maksimal ' . PIP_SANDI_MAKS . ' karakter.';
    }
    pengaturan_set('pip_sandi', password_hash($sandi, PASSWORD_DEFAULT));
    return '';
}

/** Kosongkan kata sandi (kartu tidak lagi dapat diunduh sampai sandi ditetapkan kembali). */
function pip_sandi_hapus(): void
{
    pengaturan_set('pip_sandi', '');
}

/** Periksa kata sandi yang dimasukkan memakai hash_equals (aman dari serangan waktu). */
function pip_sandi_sah(string $sandi): bool
{
    $hash = trim((string) pengaturan('pip_sandi', ''));
    if ($hash === '') {
        return false;
    }
    return password_verify(trim($sandi), $hash);
}

// ---- batas percobaan sandi (per sesi) supaya tidak bisa ditebak berulang-ulang ----------------

/**
 * Keadaan percobaan sandi pada sesi ini.
 * @return array{sisa:int,habis:bool,menit:int}
 */
function pip_coba_keadaan(): array
{
    $kini = time();
    $c = $_SESSION['pip_coba'] ?? null;
    if (!is_array($c) || ($kini - (int) ($c['sejak'] ?? 0)) > PIP_MENIT_COBA * 60) {
        $c = ['n' => 0, 'sejak' => $kini];
    }
    $n = (int) ($c['n'] ?? 0);
    $habis = $n >= PIP_MAKS_COBA;
    $menit = $habis ? max(1, (int) ceil((PIP_MENIT_COBA * 60 - ($kini - (int) $c['sejak'])) / 60)) : 0;
    return ['sisa' => max(0, PIP_MAKS_COBA - $n), 'habis' => $habis, 'menit' => $menit];
}

/** Catat satu percobaan sandi yang SALAH. */
function pip_coba_catat(): void
{
    $kini = time();
    $c = $_SESSION['pip_coba'] ?? null;
    if (!is_array($c) || ($kini - (int) ($c['sejak'] ?? 0)) > PIP_MENIT_COBA * 60) {
        $c = ['n' => 0, 'sejak' => $kini];
    }
    $c['n'] = (int) ($c['n'] ?? 0) + 1;
    $_SESSION['pip_coba'] = $c;
}

/** Kosongkan hitungan percobaan (dipanggil setelah sandi BENAR). */
function pip_coba_reset(): void
{
    unset($_SESSION['pip_coba']);
}

/** Apakah sesi ini sudah membuka sandi Kartu PIP (berlaku sampai sesi berakhir)? */
function pip_sesi_terbuka(): bool
{
    return !empty($_SESSION['pip_sandi_ok']);
}

/** Tandai sesi ini sudah membuka sandi. */
function pip_sesi_buka(): void
{
    $_SESSION['pip_sandi_ok'] = 1;
    pip_coba_reset();
}

/** Tutup kembali akses kartu pada sesi ini. */
function pip_sesi_tutup(): void
{
    unset($_SESSION['pip_sandi_ok']);
}

// =====================================================================================
// DAFTAR PENERIMA
// =====================================================================================

/**
 * Daftar penerima PIP sebuah TAHUN (di-cache per permintaan).
 *
 * @param int|null $tahunId null = tahun yang sedang dibuka pengunjung (`pip_tahun_kini()`).
 */
function pip_penerima(bool $aktifSaja = true, ?int $tahunId = null): array
{
    static $cache = [];
    if ($tahunId === null) {
        $t = pip_tahun_kini();
        $tahunId = $t ? (int) $t['id'] : 0;
    }
    $kunci = ($aktifSaja ? '1' : '0') . ':' . $tahunId;
    if (isset($cache[$kunci])) {
        return $cache[$kunci];
    }
    try {
        $w = 'WHERE tahun_id = ?' . ($aktifSaja ? ' AND aktif = 1' : '');
        return $cache[$kunci] = all('SELECT * FROM pip_penerima ' . $w . ' ORDER BY urutan, id', [$tahunId]);
    } catch (Throwable $e) {
        return $cache[$kunci] = [];
    }
}

function pip_penerima_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM pip_penerima WHERE id = ?', [$id]);
}

function pip_jumlah(bool $aktifSaja = true, ?int $tahunId = null): int
{
    if ($tahunId === null) {
        $t = pip_tahun_kini();
        $tahunId = $t ? (int) $t['id'] : 0;
    }
    try {
        return $aktifSaja
            ? (int) jumlah('SELECT COUNT(*) FROM pip_penerima WHERE aktif = 1 AND tahun_id = ?', [$tahunId])
            : (int) jumlah('SELECT COUNT(*) FROM pip_penerima WHERE tahun_id = ?', [$tahunId]);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Total nominal seluruh penerima (dianggap 0 bila kosong). */
function pip_total_nominal(array $baris): int
{
    $t = 0;
    foreach ($baris as $b) {
        $t += (int) ($b['nominal'] ?? 0);
    }
    return $t;
}

/** Urutan berikutnya pada sebuah tahun (kelipatan 10). */
function pip_urutan_baru(?int $tahunId = null): int
{
    if ($tahunId === null) {
        $t = pip_tahun_kini();
        $tahunId = $t ? (int) $t['id'] : 0;
    }
    try {
        return (int) jumlah('SELECT COALESCE(MAX(urutan), 0) FROM pip_penerima WHERE tahun_id = ?', [$tahunId]) + 10;
    } catch (Throwable $e) {
        return 10;
    }
}

/** Angka rupiah dari isian admin ("1.000.000", "1000000", "Rp 750.000" → 1000000/750000). */
function pip_uang($v): int
{
    $s = trim((string) $v);
    if ($s === '') {
        return 0;
    }
    $s = (string) preg_replace('/[^0-9]/', '', $s);
    if ($s === '') {
        return 0;
    }
    return (int) $s;
}

/** Tampilan nominal: "Rp 1.000.000", atau "-" bila kosong/0. */
function pip_rp($v): string
{
    $n = (int) $v;
    return $n > 0 ? 'Rp ' . angka($n) : '-';
}

/** Nilai sel: kosong → "-". */
function pip_sel($v): string
{
    $t = trim((string) $v);
    return $t !== '' ? $t : '-';
}

/** Ekstensi berkas dari URL atau nama berkas (huruf kecil, tanpa titik). */
function pip_ekstensi(string $url): string
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    $ext = strtolower((string) pathinfo($path !== '' ? $path : $url, PATHINFO_EXTENSION));
    return preg_match('/^[a-z0-9]{1,5}$/', $ext) === 1 ? $ext : '';
}

/** Jenis MIME untuk aliran berkas (dipakai pip_kirim_berkas()). */
function pip_mime(string $ext): string
{
    $peta = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'txt'  => 'text/plain; charset=utf-8',
    ];
    return $peta[$ext] ?? 'application/octet-stream';
}

/** Nama berkas unduhan Kartu PIP (aman untuk header HTTP). */
function pip_nama_unduh(array $row, string $kolomUrl = 'kartu', string $kolomNama = 'kartu_nama'): string
{
    $nama = trim((string) ($row[$kolomNama] ?? ''));
    $url = (string) ($row[$kolomUrl] ?? '');
    if ($nama === '') {
        $nama = basename((string) parse_url($url, PHP_URL_PATH));
    }
    $nama = (string) preg_replace('/[^A-Za-z0-9._ -]/', '', $nama);
    $nama = trim((string) preg_replace('/\s+/', ' ', $nama));
    if ($nama === '' || strpos($nama, '.') === false) {
        $ext = pip_ekstensi($url);
        $nama = $nama !== '' ? $nama : 'berkas';
        $nama .= $ext !== '' ? '.' . $ext : (string) PIP_KARTU_NAMA_BAWAAN;
    }
    return $nama;
}

/**
 * Alirkan berkas dari media (CDN) ke peramban SETELAH sandi diperiksa.
 *
 * URL aslinya TIDAK pernah dikirim ke peramban — berkasnya diambil server-side lalu diteruskan.
 * Karena itu tautan media tidak dapat disebar-luaskan untuk melewati kata sandi.
 *
 * @param bool $inline true = tampilkan di peramban (application/pdf), false = unduh (attachment)
 */
function pip_kirim_berkas(string $url, string $namaUnduh, bool $inline = false): bool
{
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return false;
    }
    // Ambil berkas server-side (curl diutamakan; ada cadangan stream).
    $isi = false;
    $kode = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'Website-Sekolah/1.0 (+PIP)',
        ]);
        $isi = curl_exec($ch);
        $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => true]]);
        $isi = @file_get_contents($url, false, $ctx);
        $kode = $isi === false ? 0 : 200;
    }
    if ($isi === false || $isi === '' || ($kode !== 0 && ($kode < 200 || $kode >= 300))) {
        return false;
    }

    $ext = pip_ekstensi($url);
    $mime = pip_mime($ext);
    // Jangan pernah menampilkan berkas yang bukan gambar/PDF secara langsung di peramban.
    if ($inline && !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        $inline = false;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($isi));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $namaUnduh . '"');
        // Berkas ber-sandi TIDAK boleh disimpan di cache bersama/umum.
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
    }
    echo $isi;
    return true;
}

/**
 * Tautan halaman "Layanan PIP" (dicari dari menu berjenis `pip` supaya tetap benar walau
 * admin mengganti slug/namanya; cadangan: slug bawaan `pip`).
 */
function pip_url(?int $tahunId = null): string
{
    static $dasar = null;
    if ($dasar === null) {
        $dasar = halaman_url('pip');
        try {
            $m = one("SELECT * FROM menu WHERE jenis = 'pip' AND aktif = 1 ORDER BY urutan, id LIMIT 1");
            if ($m) {
                $dasar = menu_url($m);
            }
        } catch (Throwable $e) {
            // biarkan memakai cadangan
        }
    }
    if ($tahunId === null || $tahunId <= 0) {
        return $dasar;
    }
    return $dasar . (strpos($dasar, '?') === false ? '?' : '&') . 'tahun=' . $tahunId;
}

/**
 * Halaman formulir kata sandi Kartu PIP (dipakai `pip_kartu.php`).
 *
 * @param string $pesan        keterangan pembuka (boleh kosong)
 * @param string $namaPenerima nama penerima yang kartunya diminta ('' = tidak ada → hanya keterangan)
 * @param string $galat        pesan galat/informasi ('' = tidak ada)
 * @param int    $idPenerima   id penerima yang sedang dibuka — WAJIB diteruskan, karena formulir
 *                             mengirim ke `pip_kartu.php` TANPA query sehingga `$_GET['id']` hilang
 *                             setelah percobaan pertama (bila diisi dari $_GET, id menjadi 0 dan
 *                             berkas yang dibuka bukan lagi kartu penerima tersebut).
 */
function pip_kartu_tampil(string $judul, string $pesan, string $namaPenerima, string $galat,
                          string $jenis = '', int $idPenerima = 0): void
{
    $terkunci = pip_coba_keadaan();
    situs_atas(['judul' => $judul . ' — Kartu PIP']);
    ?>
<section class="isi-halaman">
    <div class="wadah">
        <div class="artikel pip-sandi-kartu">
            <h1><?= e($judul) ?></h1>
            <?php if ($pesan !== ''): ?>
                <p class="mut"><?= e($pesan) ?></p>
            <?php endif; ?>

            <?php if ($galat !== ''): ?>
                <div class="alert <?= $jenis === 'err' ? 'alert-err' : 'alert-info' ?>"><?= e($galat) ?></div>
            <?php endif; ?>

            <?php if ($namaPenerima !== ''): ?>
                <p class="pip-sandi-nama">Kartu PIP untuk: <b><?= e($namaPenerima) ?></b></p>
            <?php endif; ?>

            <?php if ($namaPenerima !== '' && !$terkunci['habis']): ?>
                <form method="post" action="pip_kartu.php" class="pip-sandi-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $idPenerima ?>">
                    <div class="form-baris">
                        <label for="sandi">Kata Sandi Kartu PIP</label>
                        <input type="password" id="sandi" name="sandi" required autofocus
                               autocomplete="off" maxlength="<?= (int) PIP_SANDI_MAKS ?>"
                               placeholder="Masukkan kata sandi dari admin madrasah">
                        <div class="bantuan">Kata sandi ditetapkan admin madrasah. Bila belum menerimanya,
                            silakan menghubungi admin.</div>
                    </div>
                    <div class="aksi-form">
                        <button type="submit" class="tombol"><?= ikon('unduh', 16) ?> Buka Kartu PIP</button>
                        <a class="tombol tombol-garis" href="<?= e(pip_url()) ?>">Kembali</a>
                    </div>
                </form>
            <?php elseif ($namaPenerima !== '' && $terkunci['habis']): ?>
                <p class="pip-note"><?= ikon('kunci', 15) ?>
                    Percobaan kata sandi terlalu banyak. Silakan coba lagi dalam
                    <b><?= (int) $terkunci['menit'] ?> menit</b> atau hubungi admin madrasah.</p>
                <div class="aksi-form">
                    <a class="tombol tombol-garis" href="<?= e(pip_url()) ?>">Kembali ke halaman Layanan PIP</a>
                </div>
            <?php else: ?>
                <div class="aksi-form">
                    <a class="tombol" href="<?= e(pip_url()) ?>"><?= ikon('panah-kiri', 16) ?> Kembali ke halaman Layanan PIP</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
    <?php
    situs_bawah();
}

// =====================================================================================
// TAMPILAN HALAMAN PUBLIK
// =====================================================================================

/**
 * Mencetak halaman "Layanan PIP" (dipakai halaman.php untuk jenis menu `pip`).
 */
function pip_papan(): void
{
    $tahun = pip_tahun_kini();
    $tahunId = $tahun ? (int) $tahun['id'] : 0;
    $daftarTahun = pip_tahun_list(true);
    $penerima = pip_penerima(true, $tahunId);
    $total = pip_total_nominal($penerima);
    // HALAMAN DAFTAR (permintaan pemilik: "… dan daftar penerima PIP" ditampilkan 1-10 + tombol
    // selanjutnya). Kaki tabel (jumlah penerima & total nominal) tetap dihitung dari SELURUH baris.
    $perHal = PUBLIK_PER_HALAMAN;
    $totalPenerima = count($penerima);
    $hal = paginasi_halaman($totalPenerima, $perHal);
    $tampil = paginasi_potong($penerima, $hal, $perHal);
    $sk = pip_sk($tahun);
    $catatan = trim(pip_catatan($tahun));
    $menuDasar = pip_url();
    ?>
<div class="pip-papan" id="papanPip">

  <?php // PEMILIH TAHUN — permintaan pemilik: "tambahkan tahun (karena setiap tahun SK dan
        // penerima PIP tidak sama)". Diletakkan paling atas supaya pengunjung langsung tahu tahun
        // mana yang sedang dibuka dan dapat berpindah tahun. ?>
  <section class="pip-tahun" id="pipTahun">
    <div class="pip-tahun-kiri">
      <div class="pip-tahun-label"><?= ikon('kalender', 15) ?> Tahun Layanan PIP</div>
      <div class="pip-chip-list">
        <?php if (!$daftarTahun): ?>
          <span class="pip-chip on"><?= e(date('Y')) ?></span>
        <?php else: ?>
          <?php foreach ($daftarTahun as $t): ?>
            <a class="pip-chip <?= (int) $t['id'] === $tahunId ? 'on' : '' ?>"
               href="<?= e($menuDasar . (strpos($menuDasar, '?') === false ? '?' : '&') . 'tahun=' . (int) $t['id']) ?>"
               title="Tampilkan SK &amp; daftar penerima tahun <?= e(pip_tahun_label($t)) ?>">
              <?= e(pip_tahun_label($t)) ?>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="pip-tahun-kanan">
      <span class="pip-tahun-aktif"><?= ikon('sertifikat', 14) ?>
        Menampilkan data tahun <b><?= e(pip_tahun_label($tahun)) ?></b></span>
    </div>
  </section>

  <section class="pip-blok">
    <div class="pip-kepala">
      <div>
        <h2><?= e(pip_judul_info()) ?></h2>
        <p>Program bantuan pendidikan pemerintah bagi peserta didik dari keluarga kurang mampu.</p>
      </div>
      <?php if ($sk !== ''): ?>
        <div class="pip-aksi">
          <a class="pip-tombol pip-tombol-emas" href="<?= e($sk) ?>" target="_blank" rel="noopener noreferrer">
            <?= ikon('sertifikat', 16) ?> Lihat SK Penerima PIP<?= $tahun ? ' ' . e(pip_tahun_label($tahun)) : '' ?>
          </a>
        </div>
      <?php else: ?>
        <div class="pip-aksi">
          <span class="pip-pil pip-pil-warn"><?= ikon('info', 13) ?> SK tahun <?= e(pip_tahun_label($tahun)) ?> belum diunggah</span>
        </div>
      <?php endif; ?>
    </div>

    <?php $info = pip_info(); ?>
    <?php if (trim(str_replace('&nbsp;', '', strip_tags($info))) !== ''): ?>
      <div class="pip-isi"><?= bersihkan_html($info) ?></div>
    <?php else: ?>
      <p class="pip-mut">Keterangan Program Indonesia Pintar belum diisi admin.</p>
    <?php endif; ?>
  </section>

  <section class="pip-blok">
    <div class="pip-kepala">
      <div>
        <h2><?= e(pip_judul_daftar()) ?><?= sek('nama') !== '' ? ' ' . e((string) sek('nama')) : '' ?>
          <?php if ($tahun): ?><span class="pip-judul-tahun">Tahun <?= e(pip_tahun_label($tahun)) ?></span><?php endif; ?></h2>
        <p>Peserta didik penerima bantuan Program Indonesia Pintar pada madrasah/sekolah ini
           <?= $tahun ? 'untuk tahun ' . e(pip_tahun_label($tahun)) : '' ?>.</p>
      </div>
      <div class="pip-chip">
        <span><?= angka(count($penerima)) ?> penerima</span>
        <?php if ($total > 0): ?><span>Total <?= e(pip_rp($total)) ?></span><?php endif; ?>
      </div>
    </div>

    <?php if (!$penerima): ?>
      <div class="pip-kosong"><?= ikon('sertifikat', 34) ?>
        <h3>Belum ada data penerima PIP<?= $tahun ? ' tahun ' . e(pip_tahun_label($tahun)) : '' ?></h3>
        <p>Daftar penerima PIP untuk tahun tersebut belum diisi admin. Bila penerima tahun lain sudah
           ada, silakan pilih tahunnya pada pilihan <b>Tahun Layanan PIP</b> di atas.</p>
      </div>
    <?php else: ?>
      <div class="pip-tabel-wrap">
        <table class="pip-tabel" id="tblPip">
          <thead>
            <tr>
              <th class="pip-ctr pip-k-no">No</th>
              <th class="pip-k-nama">Nama</th>
              <th class="pip-k-nisn">NISN</th>
              <th class="pip-k-kelas">Kelas</th>
              <th class="pip-num pip-k-nominal">Nominal</th>
              <th class="pip-ctr pip-k-kartu">Kartu PIP</th>
              <th class="pip-k-ket">Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tampil as $i => $r): ?>
              <tr>
                <td class="pip-ctr"><?= (($hal - 1) * $perHal) + $i + 1 ?></td>
                <td><b><?= e((string) $r['nama']) ?></b></td>
                <td><?= e(pip_sel($r['nisn'])) ?></td>
                <td><?= e(pip_sel($r['kelas'])) ?></td>
                <td class="pip-num"><?= e(pip_rp($r['nominal'])) ?></td>
                <td class="pip-ctr">
                  <?php if (trim((string) $r['kartu']) !== ''): ?>
                    <?php if (pip_sandi_ada()): ?>
                      <a class="pip-tombol pip-tombol-kecil" href="pip_kartu.php?id=<?= (int) $r['id'] ?>"
                         title="Unduh Kartu PIP (perlu kata sandi)">
                        <?= ikon('unduh', 15) ?> Unduh
                      </a>
                    <?php else: ?>
                      <?php // Kata sandi belum ditetapkan admin → kartu belum dapat diunduh.
                            // Tulisan dibuat NETRAL karena halaman ini dibaca orang tua/peserta didik. ?>
                      <span class="pip-pil pip-pil-warn" title="Kartu PIP belum dibuka oleh admin madrasah">
                        <?= ikon('kunci', 13) ?> Belum tersedia
                      </span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="pip-mut pip-kecil">-</span>
                  <?php endif; ?>
                </td>
                <td class="pip-kecil">
                  <?php $surat = trim((string) $r['surat']); $ket = trim((string) $r['keterangan']); ?>
                  <?php if ($surat !== ''): ?>
                    <a class="pip-tautan" href="<?= e($surat) ?>" target="_blank" rel="noopener noreferrer">
                      <?= ikon('sertifikat', 14) ?> Surat Kuasa
                    </a>
                  <?php endif; ?>
                  <?php if ($ket !== ''): ?>
                    <span class="pip-ket-teks"><?= nl2br(e($ket)) ?></span>
                  <?php endif; ?>
                  <?php if ($surat === '' && $ket === ''): ?>
                    <span class="pip-mut">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="3" class="pip-ctr">Jumlah</th>
              <th class="pip-ctr"><?= angka(count($penerima)) ?> penerima</th>
              <th class="pip-num"><?= e($total > 0 ? pip_rp($total) : '-') ?></th>
              <th colspan="2" class="pip-ctr">&nbsp;</th>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php
      // Keterangan rentang baris + tombol halaman (Selanjutnya / Sebelumnya).
      $info = paginasi_info($totalPenerima, $perHal, $hal, 'penerima');
      $nav = paginasi($totalPenerima, $perHal, $hal);
      if ($info !== '' || $nav !== ''):
      ?>
        <div class="pip-halaman" id="halamanPip">
          <p class="pip-info-halaman"><?= e($info) ?></p>
          <?= $nav ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php // KOTAK "INFO PENTING" — catatan yang diisi admin pada panel Layanan PIP.
          // Permintaan pemilik: catatan dijadikan agak tebal, diletakkan dalam kotak, dan diberi
          // tulisan "Info Penting" yang TEBAL, MERAH, dan BERKEDIP (kedipan dimatikan bila pengunjung
          // memilih mode hemat gerak — tulisannya tetap merah tebal).
          if ($catatan !== ''): ?>
      <div class="pip-info-penting" id="infoPentingPip">
        <div class="pip-info-judul"><?= ikon('info', 16) ?> Info Penting</div>
        <div class="pip-info-isi"><?= nl2br(e($catatan)) ?></div>
      </div>
    <?php endif; ?>
  </section>
</div>
    <?php
}
