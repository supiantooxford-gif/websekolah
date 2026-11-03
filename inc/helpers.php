<?php
/**
 * Fungsi bantu aplikasi Website Sekolah.
 */
declare(strict_types=1);

// =====================================================================================
// KELUARAN & TEKS
// =====================================================================================

/** Escape HTML. */
function e($teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Teks multi-baris → HTML aman (baris baru jadi <br>). */
function nl($teks): string
{
    return nl2br(e($teks));
}

/**
 * Tag HTML yang diizinkan pada isi halaman/berita (hasil penyunting bergaya Word).
 * Daftar ini dipakai `bersihkan_html()` — jangan menambah tag yang bisa menjalankan skrip.
 */
const HTML_TAG_BOLEH = ['h2', 'h3', 'h4', 'p', 'div', 'span', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's',
    'sub', 'sup', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'a', 'img', 'figure', 'figcaption',
    'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'];

/** Properti CSS yang boleh disimpan pada isi (selebihnya dibuang). */
const GAYA_BOLEH = ['text-align', 'font-weight', 'font-style', 'text-decoration', 'text-decoration-line',
    'margin-left', 'margin-right', 'padding-left', 'width', 'max-width', 'height', 'max-height',
    'border', 'border-collapse', 'vertical-align', 'white-space'];

/** Saring nilai atribut `style` — hanya deklarasi aman yang dipakai. */
function saring_gaya(string $gaya): string
{
    $keluar = [];
    foreach ((array) explode(';', $gaya) as $deklarasi) {
        if (!str_contains($deklarasi, ':')) {
            continue;
        }
        [$properti, $nilai] = array_map('trim', explode(':', $deklarasi, 2));
        $properti = strtolower($properti);
        $nilai = strtolower((string) preg_replace('#\s+#', ' ', $nilai));
        if (!in_array($properti, GAYA_BOLEH, true) || $nilai === '') {
            continue;
        }
        // Catatan: pemisah pola memakai ~ karena nilai gaya boleh memuat tanda #.
        if (preg_match('~^[a-z0-9\s,.%#()\-]+$~', $nilai) !== 1) {
            continue;
        }
        if (preg_match('#url\(|expression|javascript|import|\\\\#', $nilai)) {
            continue;
        }
        if ($properti === 'text-align' && !in_array($nilai, ['left', 'right', 'center', 'justify', 'start', 'end'], true)) {
            continue;
        }
        $keluar[] = $properti . ': ' . $nilai;
    }
    return implode('; ', $keluar);
}

/** Saring atribut sebuah tag sesuai tag-nya. */
function saring_atribut(string $teksAtribut, string $tag): string
{
    if (trim($teksAtribut) === '') {
        return '';
    }
    if (!preg_match_all('#([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#', $teksAtribut, $m, PREG_SET_ORDER)) {
        return '';
    }
    $keluar = [];
    $gaya = [];
    $align = '';
    foreach ($m as $a) {
        $nama = strtolower($a[1]);
        $nilai = (string) ($a[2] !== '' ? $a[2] : ($a[3] !== '' ? $a[3] : ($a[4] ?? '')));
        switch ($nama) {
            case 'style':
                $bersih = saring_gaya($nilai);
                if ($bersih !== '') {
                    $gaya[] = $bersih;
                }
                break;
            case 'align':
                if (in_array(strtolower(trim($nilai)), ['left', 'right', 'center', 'justify'], true)) {
                    $align = strtolower(trim($nilai));
                }
                break;
            case 'href':
                if (in_array($tag, ['a'], true) && $nilai !== '' && !preg_match('#^\s*(javascript|data|vbscript):#i', $nilai)) {
                    $keluar[] = 'href="' . e($nilai) . '"';
                    if (preg_match('#^https?://#i', $nilai)) {
                        $keluar[] = 'target="_blank"';
                        $keluar[] = 'rel="noopener noreferrer"';
                    }
                }
                break;
            case 'src':
                if ($tag === 'img' && $nilai !== '' && !preg_match('#^\s*(javascript|vbscript):#i', $nilai)
                    && preg_match('~^(https?://|/|[a-zA-Z0-9_\-./?=&%#]+)~', $nilai)) {
                    $keluar[] = 'src="' . e(preg_replace('#\s#', '', $nilai)) . '"';
                }
                break;
            case 'alt':
            case 'title':
                if ($nilai !== '') {
                    $keluar[] = $nama . '="' . e(potong($nilai, 200, '')) . '"';
                }
                break;
            case 'colspan':
            case 'rowspan':
                if (in_array($tag, ['td', 'th'], true) && preg_match('#^\d+$#', trim($nilai))) {
                    $keluar[] = $nama . '="' . (int) $nilai . '"';
                }
                break;
            default:
                break;
        }
    }
    if ($align !== '') {
        $gaya[] = 'text-align: ' . $align;
    }
    if ($gaya) {
        $keluar[] = 'style="' . e(implode('; ', $gaya)) . '"';
    }
    return $keluar ? ' ' . implode(' ', $keluar) : '';
}

/**
 * Isi halaman/berita: menerima HTML SEDERHANA dari penyunting admin (h2/h3, p, ul/ol/li,
 * b/i/u, a, br, img, table) beserta perataan (style text-align) dan ukuran gambar.
 * Semua tag/atribut lain dibuang.
 */
function bersihkan_html(string $html): string
{
    $html = (string) preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|link|meta|svg|math)[^>]*>.*?</\1>#is', '', $html);
    $html = (string) preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|link|meta|svg|math)[^>]*/?>#is', '', $html);

    return (string) preg_replace_callback('#<\s*(/?)\s*([a-zA-Z0-9]+)((?:\s+[^>]*?)?)\s*(/?)>#s', static function (array $m): string {
        $tutup = $m[1] === '/';
        $tag = strtolower($m[2]);
        if (!in_array($tag, HTML_TAG_BOLEH, true)) {
            return '';
        }
        if ($tutup) {
            return '</' . $tag . '>';
        }
        if ($tag === 'br' || $tag === 'hr') {
            return '<' . $tag . '>';
        }
        return '<' . $tag . saring_atribut((string) ($m[3] ?? ''), $tag) . '>';
    }, $html);
}

/** Potong teks dengan aman (mbstring tidak selalu ada). */
function potong(string $teks, int $maks, string $ekor = '…'): string
{
    $teks = trim((string) preg_replace('/\s+/u', ' ', strip_tags($teks)));
    if (function_exists('mb_strlen')) {
        if (mb_strlen($teks, 'UTF-8') <= $maks) {
            return $teks;
        }
        return mb_substr($teks, 0, $maks, 'UTF-8') . $ekor;
    }
    if (strlen($teks) <= $maks) {
        return $teks;
    }
    return substr($teks, 0, $maks) . $ekor;
}

/**
 * Potong TEKS BIASA yang boleh berisi BANYAK BARIS tanpa membuang baris baru.
 *
 * ⚠️ `potong()` memakai `preg_replace('/\s+/')` sehingga SELURUH baris baru melebur menjadi spasi.
 * Itu membuat pengaturan "satu per baris" (Alur Pendaftaran, Persyaratan, Jadwal PPDB) rusak:
 * halaman PPDB hanya menampilkan SATU kartu langkah berisi semua langkah yang menyatu, dan daftar
 * persyaratan menjadi satu butir panjang. Gunakan fungsi ini untuk medan seperti itu.
 */
function potong_baris(string $teks, int $maks, string $ekor = ''): string
{
    $teks = str_replace(["\r\n", "\r"], "\n", $teks);
    $teks = trim((string) preg_replace('/[ \t]+/u', ' ', $teks));      // rapatkan spasi, baris TETAP
    $teks = trim((string) preg_replace('/\n{3,}/', "\n\n", $teks));    // maksimal satu baris kosong
    if (function_exists('mb_strlen')) {
        if (mb_strlen($teks, 'UTF-8') <= $maks) {
            return $teks;
        }
        return mb_substr($teks, 0, $maks, 'UTF-8') . $ekor;
    }
    return strlen($teks) <= $maks ? $teks : substr($teks, 0, $maks) . $ekor;
}

/** Potong teks HTML TANPA membuang tag.
 *
 * ⚠️ `potong()` memakai strip_tags() sehingga TIDAK BOLEH dipakai untuk isi halaman/berita —
 * memakainya membuat seluruh format (tebal, perataan, gambar) hilang saat disimpan.
 * Batasnya besar (TEKS_PANJANG_MAKS) sehingga pemotongan praktis tidak pernah terjadi.
 */
function potong_html(string $html, int $maks): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (function_exists('mb_strlen')) {
        return mb_strlen($html, 'UTF-8') > $maks ? mb_substr($html, 0, $maks, 'UTF-8') : $html;
    }
    return strlen($html) > $maks ? substr($html, 0, $maks) : $html;
}

/** Judul → slug URL. */
function slug(string $teks, string $cadangan = 'item'): string
{
    $teks = trim(strip_tags($teks));
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $teks);
        if (is_string($t) && $t !== '') {
            $teks = $t;
        }
    }
    $teks = strtolower($teks);
    $teks = (string) preg_replace('#[^a-z0-9]+#', '-', $teks);
    $teks = trim($teks, '-');
    if ($teks === '') {
        $teks = $cadangan;
    }
    return substr($teks, 0, 90);
}

/** Nomor HP/WA → format internasional (08… → 628…). */
function wa_internasional(string $nomor): string
{
    $n = (string) preg_replace('#\D+#', '', $nomor);
    if ($n === '') {
        return '';
    }
    if (str_starts_with($n, '0')) {
        $n = '62' . substr($n, 1);
    } elseif (str_starts_with($n, '8')) {
        $n = '62' . $n;
    }
    return $n;
}

/** ID video YouTube dari berbagai bentuk tautan (watch/short/embed/youtu.be) atau ID mentah. */
function youtube_id(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^[A-Za-z0-9_-]{11}$#', $url)) {
        return $url;
    }
    $pola = [
        '#youtu\.be/([A-Za-z0-9_-]{11})#i',
        '#[?&]v=([A-Za-z0-9_-]{11})#i',
        '#youtube\.com/embed/([A-Za-z0-9_-]{11})#i',
        '#youtube\.com/shorts/([A-Za-z0-9_-]{11})#i',
        '#youtube\.com/live/([A-Za-z0-9_-]{11})#i',
    ];
    foreach ($pola as $p) {
        if (preg_match($p, $url, $m)) {
            return $m[1];
        }
    }
    return '';
}

// =====================================================================================
// WAKTU
// =====================================================================================
const BULAN_ID = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const HARI_ID  = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];

function tanggal_id(?string $tgl, bool $denganHari = false): string
{
    $tgl = trim((string) $tgl);
    if ($tgl === '' || $tgl === '0000-00-00') {
        return '';
    }
    $ts = strtotime($tgl);
    if ($ts === false) {
        return $tgl;
    }
    $teks = (int) date('j', $ts) . ' ' . BULAN_ID[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    if ($denganHari) {
        $teks = (HARI_ID[date('l', $ts)] ?? '') . ', ' . $teks;
    }
    return $teks;
}

/** Tanggal + jam ringkas (format Indonesia). */
function waktu_id(?string $t): string
{
    $t = trim((string) $t);
    if ($t === '') {
        return '';
    }
    $ts = strtotime($t);
    if ($ts === false) {
        return $t;
    }
    return (int) date('j', $ts) . ' ' . BULAN_ID[(int) date('n', $ts)] . ' ' . date('Y', $ts) . ', ' . date('H:i', $ts);
}

/** "3 hari yang lalu" — untuk daftar berita/pesan. */
function waktu_relatif(?string $t): string
{
    $ts = strtotime(trim((string) $t));
    if (!$ts) {
        return '';
    }
    $selisih = time() - $ts;
    if ($selisih < 60) {
        return 'baru saja';
    }
    $satuan = [[31536000, 'tahun'], [2592000, 'bulan'], [604800, 'minggu'], [86400, 'hari'], [3600, 'jam'], [60, 'menit']];
    foreach ($satuan as [$detik, $nama]) {
        if ($selisih >= $detik) {
            return floor($selisih / $detik) . ' ' . $nama . ' lalu';
        }
    }
    return 'baru saja';
}

function jam_id(?string $t): string
{
    $ts = strtotime((string) $t);
    return $ts ? date('H:i', $ts) : '';
}

// =====================================================================================
// FLASH & CSRF
// =====================================================================================
function flash(string $pesan, string $jenis = 'ok'): void
{
    $_SESSION['flash'][] = ['pesan' => $pesan, 'jenis' => $jenis];
}

function flash_ambil(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($f) ? $f : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_sah(): bool
{
    $token = '';
    if (!empty($_POST['csrf'])) {
        $token = (string) $_POST['csrf'];
    } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    return $token !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $token);
}

/** Menjawab permintaan tanpa CSRF yang sah. */
function csrf_gagal(): void
{
    http_response_code(419);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>419</title>'
       . '<p style="font-family:system-ui;padding:24px">Sesi formulir sudah kedaluwarsa (419). '
       . 'Muat ulang halaman lalu kirim ulang.</p>';
    exit;
}

// =====================================================================================
// BASIS DATA
// =====================================================================================
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

/** Jalankan kueri berparameter. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Satu baris (atau null). */
function one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

/** Semua baris. */
function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** Satu nilai kolom. */
function nilai(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetch(PDO::FETCH_NUM);
    return $r === false ? null : $r[0];
}

/** Hitungan baris. */
function jumlah(string $sql, array $params = []): int
{
    return (int) nilai($sql, $params);
}

function id_baru(): int
{
    return (int) db()->lastInsertId();
}

// =====================================================================================
// IDENTITAS SEKOLAH & PENGATURAN
// =====================================================================================
function sekolah(): array
{
    static $s = null;
    if (is_array($s)) {
        return $s;
    }
    $s = one('SELECT * FROM sekolah WHERE id = 1');
    if (!$s) {
        $s = ['id' => 1, 'nama' => APP_NAME, 'jenis' => 'madrasah'];
    }
    return $s;
}

function sek(string $kunci, string $bawaan = ''): string
{
    $s = sekolah();
    $v = $s[$kunci] ?? null;
    return ($v === null || trim((string) $v) === '') ? $bawaan : (string) $v;
}

function sekolah_simpan(array $data): void
{
    $kolom = ['nama', 'jenjang', 'npsn', 'nsm', 'alamat', 'desa', 'kecamatan', 'kabupaten', 'provinsi',
        'telepon', 'whatsapp', 'email', 'website', 'kepala', 'nip_kepala', 'kepala_foto', 'slogan', 'jenis',
        'logo', 'maps', 'akreditasi', 'tahun_ajaran', 'jam_operasional', 'video_profil', 'diperbarui',
        // v10 — isian halaman "Profil Lengkap". ⚠️ KOLOM BARU WAJIB DITAMBAHKAN DI SINI, kalau tidak
        // nilainya DIABAIKAN diam-diam oleh sekolah_simpan() (halaman bilang "disimpan" tetapi tidak).
        'kode_pos', 'npwp', 'status_madrasah', 'waktu_belajar', 'sk_ijop', 'sk_ijop_tgl', 'tahun_berdiri',
        'akreditasi_tgl', 'penyelenggara', 'sk_menkumham', 'jarak_kecamatan', 'jarak_kabupaten', 'bangunan',
        'visi', 'misi', 'tujuan',
        // v13 — berkas dokumen (URL media proxy) untuk tombol "Lihat Ijop"/"Lihat Akreditasi".
        'berkas_ijop', 'berkas_akreditasi'];
    $set = [];
    $val = [];
    foreach ($kolom as $k) {
        if (array_key_exists($k, $data)) {
            $set[] = $k . ' = ?';
            $val[] = $data[$k];
        }
    }
    if (!$set) {
        return;
    }
    $set[] = "diperbarui = datetime('now','localtime')";
    $val[] = 1;
    q('UPDATE sekolah SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
}

/** Istilah "Sekolah"/"Madrasah" mengikuti jenis lembaga. */
function istilah(string $teks): string
{
    if ((sekolah()['jenis'] ?? 'madrasah') !== 'madrasah') {
        return $teks;
    }
    return (string) preg_replace('#\bSekolah\b#', 'Madrasah', $teks);
}

function jenis_label(): string
{
    return (sekolah()['jenis'] ?? 'madrasah') === 'madrasah' ? 'Madrasah' : 'Sekolah';
}

function kepala_label(): string
{
    return 'Kepala ' . jenis_label();
}

/** Alamat satu baris (untuk footer & kop). */
function alamat_lengkap(bool $denganWilayah = true): string
{
    $bagian = [];
    if (sek('alamat') !== '') {
        $bagian[] = sek('alamat');
    }
    if ($denganWilayah) {
        if (sek('desa') !== '') {
            $bagian[] = 'Desa/Kelurahan ' . sek('desa');
        }
        if (sek('kecamatan') !== '') {
            $bagian[] = 'Kec. ' . sek('kecamatan');
        }
        if (sek('kabupaten') !== '') {
            $bagian[] = 'Kab. ' . sek('kabupaten');
        }
        if (sek('provinsi') !== '') {
            $bagian[] = 'Prov. ' . sek('provinsi');
        }
    }
    return implode(', ', $bagian);
}

/** Pengaturan tingkat aplikasi (tabel `pengaturan`). */
function pengaturan(string $kunci, string $bawaan = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (all('SELECT kunci, nilai FROM pengaturan') as $r) {
                $cache[$r['kunci']] = (string) $r['nilai'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    $v = $cache[$kunci] ?? null;
    return ($v === null || $v === '') ? $bawaan : $v;
}

function pengaturan_set(string $kunci, string $nilai): void
{
    q('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
       ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai', [$kunci, $nilai]);
    // Segarkan cache pada permintaan yang sama.
    $ref = new ReflectionFunction('pengaturan');
    unset($ref);
}

function pengaturan_hapus(string $kunci): void
{
    q('DELETE FROM pengaturan WHERE kunci = ?', [$kunci]);
}

// =====================================================================================
// PENGGUNA & MASUK
// =====================================================================================
function current_user(): ?array
{
    $id = (int) ($_SESSION['uid'] ?? 0);
    if ($id <= 0) {
        return null;
    }
    static $u = null;
    if (is_array($u) && (int) $u['id'] === $id) {
        return $u;
    }
    $u = one('SELECT * FROM users WHERE id = ? AND aktif = 1', [$id]);
    return $u ?: null;
}

function sudah_masuk(): bool
{
    return current_user() !== null;
}

function halaman_admin_login(): string
{
    // Nama berkas halaman yang sedang dibuka (tanpa folder).
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
}

function require_login(): void
{
    $u = current_user();
    if (!$u) {
        $tujuan = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        $q = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
        redirect('login.php?minta=' . rawurlencode($tujuan . $q));
    }
    // Peran "Editor Berita" hanya boleh mengelola BERITA — berkas lain dijawab 403.
    if (!boleh_buka_berkas(halaman_admin_login())) {
        http_response_code(403);
        admin_atas('Akses ditolak');
        echo '<div class="alert alert-err">Akun ini khusus untuk <strong>mengisi berita</strong>, sehingga halaman '
            . 'tersebut tidak dapat dibuka. Silakan buka menu <strong>Berita</strong> pada panel, atau hubungi '
            . 'administrator bila Anda memerlukan akses yang lebih luas.</div>';
        admin_bawah();
        exit;
    }
    q("UPDATE users SET terakhir = datetime('now','localtime') WHERE id = ?", [$u['id']]);
}

/** Batas percobaan masuk (dari tabel log). */
function login_terblokir(string $username): int
{
    $batas = date('Y-m-d H:i:s', time() - LOGIN_MENIT_BLOKIR * 60);
    $n = jumlah("SELECT COUNT(*) FROM log_masuk WHERE berhasil = 0 AND waktu >= ? AND (username = ? OR ip = ?)",
        [$batas, $username, ip_pengunjung()]);
    if ($n < LOGIN_MAKS_GAGAL) {
        return 0;
    }
    $terakhir = one('SELECT waktu FROM log_masuk WHERE berhasil = 0 AND waktu >= ? AND (username = ? OR ip = ?) ORDER BY id DESC LIMIT 1',
        [$batas, $username, ip_pengunjung()]);
    if (!$terakhir) {
        return 0;
    }
    $sisa = (strtotime($terakhir['waktu']) + LOGIN_MENIT_BLOKIR * 60) - time();
    return max(0, $sisa);
}

function ip_pengunjung(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return $ip !== '' ? $ip : '0.0.0.0';
}

function redirect(string $tujuan): void
{
    if (!headers_sent()) {
        header('Location: ' . $tujuan);
        exit;
    }
    echo '<script>location.replace(' . json_encode($tujuan) . ')</script>';
    exit;
}

/** Alamat relatif yang aman (menolak alamat absolut/keluar folder). */
function tautan_aman(string $tujuan, string $bawaan = 'index.php'): string
{
    $tujuan = trim($tujuan);
    if ($tujuan === '' || str_contains($tujuan, '://') || str_starts_with($tujuan, '//') || str_contains($tujuan, '..')) {
        return $bawaan;
    }
    return preg_replace('#[^A-Za-z0-9_\-\.\?=&%\#]#', '', $tujuan) ?: $bawaan;
}

// =====================================================================================
// PENJAGA PERAN (panel admin)
// =====================================================================================
function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

/**
 * Daftar PERAN panel admin (kode → label pilihan pada halaman Pengguna & Akun).
 * Menambah peran baru cukup di sini + `berita_berkas_boleh()` (bila perannya dibatasi).
 */
function peran_daftar(): array
{
    return [
        'admin'  => 'Administrator (akses penuh)',
        'editor' => 'Editor (konten, PPDB & Layanan PIP)',
        'berita' => 'Editor Berita (berita + galeri foto & video)',
    ];
}

/**
 * Akun dengan peran "Editor Berita" — hanya boleh mengelola **BERITA** dan **GALERI**
 * (foto & video). Peran ini dijelaskan pada halaman Pengguna & Akun.
 */
function is_editor_berita(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'berita';
}

/**
 * Berkas panel admin yang boleh dibuka akun "Editor Berita" (peran `berita`).
 * Memuat: dasbor ringkas, berita (daftar/tulis/kategori), penyisip gambar penyunting, dan
 * **galeri foto & video** (permintaan pemilik: "tambahkan akses agar bisa menambahkan foto dan video").
 * Peran ini SENGAJA tidak diberi akses Layanan PIP — yang mengisi PIP adalah peran `editor`
 * (konten, PPDB & Layanan PIP) atau Administrator; lihat `boleh_kelola_pip()`.
 */
function berita_berkas_boleh(): array
{
    return ['index.php', 'berita.php', 'berita_edit.php', 'berita_kategori.php', 'upload_editor.php',
        'galeri.php', 'logout.php'];
}

/**
 * Boleh-tidaknya peran pengguna yang sedang masuk membuka sebuah berkas panel admin.
 * Peran lain (admin/editor) tidak dibatasi di sini — pembatasannya lewat require_admin().
 */
function boleh_buka_berkas(string $berkas): bool
{
    if (!is_editor_berita()) {
        return true;
    }
    return in_array($berkas, berita_berkas_boleh(), true);
}

/**
 * Akun dengan peran "Editor" — mengelola **konten website & PPDB**.
 * Sejak v1.7.22 pemilik juga memberi akses **Layanan PIP** kepada peran ini
 * (lihat `boleh_kelola_pip()`), sehingga pemilik madrasah dapat menugaskan pengisian
 * data penerima PIP kepada editor tanpa memberikan akses administrator penuh.
 */
function is_editor(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'editor';
}

/** Halaman Layanan PIP boleh diisi Administrator DAN peran Editor (permintaan pemilik). */
function boleh_kelola_pip(): bool
{
    return is_admin() || is_editor();
}

/** Penjaga halaman Layanan PIP (`admin/pip.php`) — pola jawaban sama dengan `require_admin()`. */
function require_kelola_pip(): void
{
    require_login();
    if (boleh_kelola_pip()) {
        return;
    }
    http_response_code(403);
    admin_atas('Akses ditolak');
    echo '<div class="alert alert-err">Halaman <strong>Layanan PIP</strong> hanya dapat dibuka oleh '
        . '<strong>Administrator</strong> atau akun <strong>Editor (konten, PPDB &amp; Layanan PIP)</strong>. '
        . 'Hubungi administrator bila Anda memerlukan akses.</div>';
    admin_bawah();
    exit;
}

/**
 * Menetapkan **kata sandi Kartu PIP** boleh dilakukan **Administrator dan peran Editor**.
 *
 * Permintaan pemilik (v1.7.23): *"Pastikan Akun Editor (Konten, PPDB & Layanan PIP) bisa mengganti
 * Kata Sandi Kartu PIP"* — ini MELONGGARKAN aturan sebelumnya yang hanya Administrator.
 * Karena itu kini sama dengan `boleh_kelola_pip()`; fungsi ini dipertahankan sebagai SATU titik
 * kebijakan agar mudah diubah kembali bila pemilik menghendaki lain.
 */
function boleh_sandi_pip(): bool
{
    return boleh_kelola_pip();
}

/**
 * Halaman **Kohort Siswa** (`admin/kohort.php`) boleh diisi **Administrator DAN peran Editor**.
 *
 * Permintaan pemilik (v1.14.0): *"Akun Editor (Yelfis Djamaludin) berikan akses untuk menginput
 * Kohort Siswa"* — data kohort diisi oleh pengelola madrasah, bukan hanya administrator, sama
 * seperti Layanan PIP. Akun peran lain (mis. "Editor Berita") tetap **403**.
 */
function boleh_kelola_kohort(): bool
{
    return is_admin() || is_editor();
}

/** Penjaga halaman Kohort Siswa (`admin/kohort.php`) — pola jawaban sama dengan `require_admin()`. */
function require_kelola_kohort(): void
{
    require_login();
    if (boleh_kelola_kohort()) {
        return;
    }
    http_response_code(403);
    admin_atas('Akses ditolak');
    echo '<div class="alert alert-err">Halaman <strong>Kohort Siswa</strong> hanya dapat dibuka oleh '
        . '<strong>Administrator</strong> atau akun <strong>Editor (konten, PPDB &amp; Layanan PIP)</strong>. '
        . 'Hubungi administrator bila Anda memerlukan akses.</div>';
    admin_bawah();
    exit;
}

/**
 * Halaman **Prestasi per Kelas** (`admin/prestasi_kelas.php`) boleh diisi **Administrator DAN
 * peran Editor** — permintaan pemilik (v1.15.0): *"Data di isi oleh super admin atau editor
 * (Yelfis Djamaludin)"*. Pola yang sama dengan Layanan PIP & Kohort Siswa; peran lain (mis.
 * "Editor Berita") tetap **403**.
 */
function boleh_kelola_prestasi_kelas(): bool
{
    return is_admin() || is_editor();
}

/** Penjaga halaman Prestasi per Kelas — pola jawaban sama dengan `require_admin()`. */
function require_kelola_prestasi_kelas(): void
{
    require_login();
    if (boleh_kelola_prestasi_kelas()) {
        return;
    }
    http_response_code(403);
    admin_atas('Akses ditolak');
    echo '<div class="alert alert-err">Halaman <strong>Prestasi per Kelas</strong> hanya dapat dibuka '
        . 'oleh <strong>Administrator</strong> atau akun <strong>Editor (konten, PPDB &amp; Layanan '
        . 'PIP)</strong>. Hubungi administrator bila Anda memerlukan akses.</div>';
    admin_bawah();
    exit;
}

/**
 * Halaman **Kurikulum** (`admin/kurikulum.php`: Kaldik Operasional, Jadwal Mengajar, dan Dokumen
 * Kurikulum) boleh diisi **Administrator DAN peran Editor**.
 *
 * Permintaan pemilik (v1.18.0): *"Pada bagian Menu Kurikulum buatkan isiannya di halaman admin dan
 * operator (yelfis)."* — "operator (yelfis)" adalah akun **peran `editor`** pada aplikasi ini
 * (Yelfis Djamaludin), sama seperti Layanan PIP, Kohort Siswa, dan Prestasi per Kelas. Akun peran
 * lain (mis. "Editor Berita") tetap **403**.
 */
function boleh_kelola_kurikulum(): bool
{
    return is_admin() || is_editor();
}

/** Penjaga halaman Kurikulum — pola jawaban sama dengan `require_admin()`. */
function require_kelola_kurikulum(): void
{
    require_login();
    if (boleh_kelola_kurikulum()) {
        return;
    }
    http_response_code(403);
    admin_atas('Akses ditolak');
    echo '<div class="alert alert-err">Halaman <strong>Kurikulum</strong> hanya dapat dibuka oleh '
        . '<strong>Administrator</strong> atau akun <strong>Editor (konten, PPDB &amp; Layanan '
        . 'PIP)</strong>. Hubungi administrator bila Anda memerlukan akses.</div>';
    admin_bawah();
    exit;
}

/** Hanya administrator yang boleh membuka halaman ini. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        admin_atas('Akses ditolak');
        echo '<div class="alert alert-err">Halaman ini hanya dapat dibuka oleh administrator. '
            . 'Hubungi administrator bila Anda memerlukan akses.</div>';
        admin_bawah();
        exit;
    }
}

function peran_label(string $role): string
{
    if ($role === 'admin') {
        return 'Administrator';
    }
    if ($role === 'berita') {
        return 'Editor Berita';
    }
    return 'Editor';
}

// =====================================================================================
// MENU
// =====================================================================================
/** Seluruh menu (dikelompokkan induk → anak). */
function menu_semua(bool $hanyaAktif = true): array
{
    $w = $hanyaAktif ? 'WHERE aktif = 1' : '';
    $baris = all("SELECT * FROM menu $w ORDER BY urutan, id");
    $induk = [];
    $anak = [];
    foreach ($baris as $b) {
        if ((int) $b['induk_id'] === 0) {
            $induk[(int) $b['id']] = $b;
            $induk[(int) $b['id']]['anak'] = [];
        } else {
            $anak[(int) $b['induk_id']][] = $b;
        }
    }
    foreach ($anak as $iid => $daftar) {
        if (isset($induk[$iid])) {
            $induk[$iid]['anak'] = $daftar;
        }
    }
    return array_values($induk);
}

function menu_by_id(int $id): ?array
{
    return one('SELECT * FROM menu WHERE id = ?', [$id]);
}

/** Menu (atau anak menu) berdasarkan slug URL. */
function menu_by_slug(string $slug): ?array
{
    return one('SELECT * FROM menu WHERE slug = ? AND aktif = 1', [$slug]);
}

/**
 * Alamat sebuah menu — SELALU dalam bentuk ALAMAT RAPI (relatif).
 *
 * Ditulis relatif (tanpa awalan `/`) supaya tetap benar baik pada alamat bawaan
 * (`…/web-sekolah/…`) maupun bila kelak dipasang pada domain sendiri.
 */
function menu_url(array $m): string
{
    switch ($m['jenis']) {
        case 'beranda':
            return './';
        case 'berita':
            return 'berita';
        case 'agenda':
            return 'agenda';
        case 'galeri':
            return 'galeri';
        case 'kontak':
            return 'kontak';
        case 'ppdb':
            return 'ppdb';
        case 'fasilitas':
            return 'fasilitas';
        case 'tautan':
            $t = tautan_publik((string) ($m['tautan'] ?? ''));
            return $t !== '' ? $t : '#';
        default:
            return 'halaman/' . rawurlencode((string) $m['slug']);
    }
}

/**
 * Ubah tautan LAMA yang diisi admin (mis. pada Slider/Keunggulan/Program maupun menu bertipe
 * "tautan") menjadi ALAMAT RAPI. Nilai yang sudah rapi, alamat luar situs (`http…`, `mailto:`),
 * jangkar (`#…`) serta nilai yang tidak dikenal dikembalikan apa adanya.
 *
 * Diperlukan karena basis data yang sudah ada menyimpan tautan gaya lama
 * (mis. `halaman.php?slug=profil`, `berita.php`) — tautan itu tetap berfungsi, tetapi sebaiknya
 * pemakaiannya diterjemahkan supaya pengunjung tidak melewati pengalihan 301.
 */
function tautan_publik(string $t): string
{
    $t = trim($t);
    if ($t === '') {
        return '';
    }
    if (preg_match('#^(https?:|mailto:|tel:|//|\#)#i', $t)) {
        return $t;   // luar situs / jangkar — biarkan
    }
    $bagian = explode('?', $t, 2);
    $berkas = strtolower(trim($bagian[0]));
    $q = [];
    if (isset($bagian[1])) {
        parse_str($bagian[1], $q);
    }
    $slug = isset($q['slug']) ? (string) $q['slug'] : '';
    unset($q['slug']);
    switch ($berkas) {
        case 'index.php':
        case 'index.php/':
            return rute_tautan('', $q);
        case 'berita.php':
            return isset($q['kategori']) && (string) $q['kategori'] !== ''
                ? berita_kategori_url((string) $q['kategori'])
                : rute_tautan('berita', $q);
        case 'berita_detail.php':
            return $slug !== '' ? berita_url($slug) : rute_tautan('berita');
        case 'agenda.php':
            return rute_tautan('agenda', $q);
        case 'galeri.php':
            return isset($q['album']) && (string) $q['album'] !== ''
                ? album_url((string) $q['album'])
                : rute_tautan('galeri', $q);
        case 'kontak.php':
            return rute_tautan('kontak', $q);
        case 'ppdb.php':
            return rute_tautan('ppdb', $q);
        case 'ppdb_status.php':
            return rute_tautan('ppdb/status', $q);
        case 'fasilitas.php':
            return rute_tautan('fasilitas', $q);
        case 'cari.php':
            return rute_tautan('cari', $q);
        case 'asisten.php':
            return rute_tautan('asisten', $q);
        case 'halaman.php':
            return $slug !== '' ? halaman_url($slug, $q) : rute_tautan('');
    }
    return $t;
}

/** Apakah menu ini tautan ke luar situs? */
function menu_luar(array $m): bool
{
    return $m['jenis'] === 'tautan' && (bool) preg_match('#^https?://#i', (string) $m['tautan']);
}

function menu_anak(int $indukId, bool $hanyaAktif = true): array
{
    $w = $hanyaAktif ? 'AND aktif = 1' : '';
    return all("SELECT * FROM menu WHERE induk_id = ? $w ORDER BY urutan, id", [$indukId]);
}

function menu_urutan_baru(int $indukId = 0): int
{
    return (int) jumlah('SELECT COALESCE(MAX(urutan), 0) FROM menu WHERE induk_id = ?', [$indukId]) + 10;
}

/** Menu induk untuk sebuah halaman (dipakai pencari & breadcrumb). */
function menu_induk_dari(int $id): ?array
{
    $m = menu_by_id($id);
    if (!$m) {
        return null;
    }
    if ((int) $m['induk_id'] === 0) {
        return $m;
    }
    return menu_by_id((int) $m['induk_id']);
}

// =====================================================================================
// UNGGAHAN (media proxy platform)
// =====================================================================================
/**
 * Kirim berkas ke media proxy platform. Hanya URL hasilnya yang boleh disimpan.
 * Token dibaca server-side dari .vibecoder-media-token (tidak pernah ke browser).
 */
function media_upload(string $bytes, string $filename): array
{
    if ($bytes === '') {
        return ['ok' => false, 'pesan' => 'Berkas kosong.'];
    }
    $tokenFile = APP_DIR . '/.vibecoder-media-token';
    if (!is_file($tokenFile) || !is_readable($tokenFile)) {
        return ['ok' => false, 'pesan' => 'Layanan penyimpanan berkas belum siap (token unggah tidak ditemukan).'];
    }
    $token = trim((string) @file_get_contents($tokenFile));
    if ($token === '') {
        return ['ok' => false, 'pesan' => 'Layanan penyimpanan berkas belum siap (token unggah kosong).'];
    }

    $endpoint = MEDIA_ENDPOINT . '?filename=' . rawurlencode($filename);
    $body = false;
    $code = 0;
    $err  = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $bytes,
            CURLOPT_HTTPHEADER     => ['X-App-Media-Token: ' . $token, 'Content-Type: application/octet-stream'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 180,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = (string) curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "X-App-Media-Token: " . $token . "\r\nContent-Type: application/octet-stream\r\n",
            'content'       => $bytes,
            'timeout'       => 180,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($endpoint, false, $ctx);
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $h, $m)) {
                    $code = (int) $m[1];
                }
            }
        }
    }

    if ($body === false || $body === '') {
        return ['ok' => false, 'pesan' => 'Gagal menghubungi layanan penyimpanan berkas. ' . $err];
    }
    $jawab = json_decode((string) $body, true);
    if (!is_array($jawab)) {
        return ['ok' => false, 'pesan' => 'Jawaban layanan penyimpanan tidak dikenali.'];
    }
    if ($code === 403 && empty($jawab['ok'])) {
        return ['ok' => false, 'pesan' => (string) ($jawab['pesan'] ?? $jawab['error'] ?? 'Penyimpanan berkas penuh — unggahan ditolak.')];
    }
    if (empty($jawab['ok']) || empty($jawab['url'])) {
        return ['ok' => false, 'pesan' => (string) ($jawab['pesan'] ?? $jawab['error'] ?? 'Unggahan ditolak layanan penyimpanan.')];
    }
    return ['ok' => true, 'url' => (string) $jawab['url']];
}

/** Periksa isi berkas gambar (bukan hanya ekstensinya). */
function gambar_isi_sah(string $path, string $ext): bool
{
    $ext = strtolower($ext);
    if ($ext === 'svg') {
        $isi = (string) @file_get_contents($path, false, null, 0, 4096);
        return stripos($isi, '<svg') !== false;
    }
    if (!function_exists('getimagesize')) {
        return true; // GD tidak tersedia → andalkan ekstensi
    }
    $info = @getimagesize($path);
    if ($info === false) {
        return false;
    }
    $boleh = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    return in_array((int) ($info[2] ?? 0), $boleh, true);
}

/**
 * Periksa isi berkas NON-gambar (mis. PDF, Word/Excel/PowerPoint) agar berkas yang hanya diganti
 * namanya ditolak.
 *
 * v1.18.0 — dokumen Office kini dikenali:
 *  • `docx`/`xlsx`/`pptx`/`zip` = arsip ZIP → diawali `PK` **dan** berukuran ≥ 512 byte (berkas
 *    2 byte berisi "PK" saja pernah lolos kalau hanya memeriksa dua huruf pertama);
 *  • `doc`/`xls`/`ppt` (format lama) = berkas OLE → diawali `D0 CF 11 E0 A1 B1 1A E1`.
 * Tanpa ini, unggahan Word/Excel/PowerPoint SELALU ditolak "isinya tidak sesuai" karena
 * `getimagesize()` tentu gagal pada dokumen — padahal menu Kurikulum & Perangkat Guru menerima
 * jenis berkas itu.
 */
function berkas_isi_sah(string $path, string $ext): bool
{
    $ext = strtolower($ext);
    if (in_array($ext, ['pdf'], true)) {
        $awal = (string) @file_get_contents($path, false, null, 0, 5);
        return strncmp($awal, '%PDF', 4) === 0;
    }
    if ($ext === 'svg') {
        return stripos((string) @file_get_contents($path, false, null, 0, 4096), '<svg') !== false;
    }
    if (in_array($ext, ['docx', 'xlsx', 'pptx', 'zip'], true)) {
        $awal = (string) @file_get_contents($path, false, null, 0, 4);
        return strncmp($awal, 'PK', 2) === 0 && (int) @filesize($path) >= 512;
    }
    if (in_array($ext, ['doc', 'xls', 'ppt'], true)) {
        $awal = (string) @file_get_contents($path, false, null, 0, 8);
        return $awal === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    }
    if (in_array($ext, ['csv', 'txt'], true)) {
        $isi = (string) @file_get_contents($path, false, null, 0, 4096);
        return $isi !== '' && strpos($isi, "\0") === false;
    }
    // Sisanya gambar → pakai pemeriksaan gambar.
    return gambar_isi_sah($path, $ext);
}

/**
 * Unggah satu berkas (gambar ATAU dokumen seperti PDF) dari $_FILES — dipakai untuk sertifikat.
 * @return array{ok:bool,url?:string,pesan?:string,kosong?:bool}
 */
function unggah_berkas(string $field, string $label = 'Berkas', array $extBoleh = SERTIFIKAT_EKSTENSI, int $maksByte = SERTIFIKAT_MAKS_BYTE): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? 4) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'pesan' => '', 'kosong' => true];
    }
    return unggah_berkas_entri($_FILES[$field], $label, $extBoleh, $maksByte);
}

/**
 * Unggah SATU berkas dari ENTRI `$_FILES` yang sudah dipilih (mis. bagian dari medan berkelompok
 * `$_FILES['berkas']['name'][$jenis][$kelas]`). Dipakai Perangkat Guru v27 yang mengunggah berkas
 * per KELAS sehingga medannya berkelompok.
 */
function unggah_berkas_entri(array $f, string $label = 'Berkas', array $extBoleh = SERTIFIKAT_EKSTENSI,
                             int $maksByte = SERTIFIKAT_MAKS_BYTE): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'pesan' => '', 'kosong' => true];
    }
    if ((int) $f['error'] !== UPLOAD_ERR_OK) {
        $pesan = (int) $f['error'] === UPLOAD_ERR_INI_SIZE || (int) $f['error'] === UPLOAD_ERR_FORM_SIZE
            ? $label . ' melebihi batas ukuran server.'
            : 'Berkas ' . $label . ' gagal diunggah (kode ' . (int) $f['error'] . ').';
        return ['ok' => false, 'pesan' => $pesan];
    }
    $namaAsli = (string) ($f['name'] ?? 'berkas');
    $ext = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));
    if (!in_array($ext, $extBoleh, true)) {
        return ['ok' => false, 'pesan' => $label . ' harus berjenis: ' . strtoupper(implode(', ', $extBoleh)) . '.'];
    }
    $ukuran = (int) ($f['size'] ?? 0);
    if ($ukuran <= 0) {
        return ['ok' => false, 'pesan' => $label . ' kosong.'];
    }
    if ($ukuran > $maksByte) {
        return ['ok' => false, 'pesan' => $label . ' terlalu besar (maks ' . ukuran_teks($maksByte) . ').'];
    }
    $tmp = (string) ($f['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'pesan' => 'Berkas ' . $label . ' tidak terbaca oleh server.'];
    }
    if (!berkas_isi_sah($tmp, $ext)) {
        return ['ok' => false, 'pesan' => $label . ' tampaknya bukan berkas ' . strtoupper($ext) . ' yang sah (isinya tidak sesuai).'];
    }
    $bytes = (string) @file_get_contents($tmp);
    if ($bytes === '') {
        return ['ok' => false, 'pesan' => 'Berkas ' . $label . ' tidak terbaca.'];
    }
    return media_upload($bytes, $namaAsli);
}

/**
 * Unggah satu berkas gambar dari $_FILES.
 * @return array{ok:bool,url?:string,pesan?:string}
 */
function unggah_gambar(string $field, string $label = 'Gambar', array $extBoleh = GAMBAR_EKSTENSI, int $maksByte = GAMBAR_MAKS_BYTE): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? 4) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'pesan' => '', 'kosong' => true];
    }
    $f = $_FILES[$field];
    if ((int) $f['error'] !== UPLOAD_ERR_OK) {
        $pesan = (int) $f['error'] === UPLOAD_ERR_INI_SIZE || (int) $f['error'] === UPLOAD_ERR_FORM_SIZE
            ? $label . ' melebihi batas ukuran server.'
            : 'Berkas ' . $label . ' gagal diunggah (kode ' . (int) $f['error'] . ').';
        return ['ok' => false, 'pesan' => $pesan];
    }
    $namaAsli = (string) ($f['name'] ?? 'gambar');
    $ext = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));
    if (!in_array($ext, $extBoleh, true)) {
        return ['ok' => false, 'pesan' => $label . ' harus berjenis: ' . strtoupper(implode(', ', $extBoleh)) . '.'];
    }
    $ukuran = (int) ($f['size'] ?? 0);
    if ($ukuran <= 0) {
        return ['ok' => false, 'pesan' => $label . ' kosong.'];
    }
    if ($ukuran > $maksByte) {
        return ['ok' => false, 'pesan' => $label . ' terlalu besar (maks ' . ukuran_teks($maksByte) . ').'];
    }
    $tmp = (string) ($f['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'pesan' => 'Berkas ' . $label . ' tidak terbaca oleh server.'];
    }
    if (!gambar_isi_sah($tmp, $ext)) {
        return ['ok' => false, 'pesan' => $label . ' tampaknya bukan berkas gambar yang sah.'];
    }
    $bytes = (string) @file_get_contents($tmp);
    if ($bytes === '') {
        return ['ok' => false, 'pesan' => 'Berkas ' . $label . ' tidak terbaca.'];
    }
    return media_upload($bytes, $namaAsli);
}

function ukuran_teks(int $byte): string
{
    if ($byte >= 1048576) {
        return round($byte / 1048576, 1) . ' MB';
    }
    if ($byte >= 1024) {
        return round($byte / 1024) . ' KB';
    }
    return $byte . ' B';
}

/** Jumlah unggahan yang gagal karena total kiriman melebihi post_max_size. */
function kiriman_terlalu_besar(): bool
{
    $cl = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    return $cl > 0 && empty($_POST) && empty($_FILES);
}

/** Kotak gambar: menampilkan foto atau kotak kosong bergaris. */
function kotak_gambar(?string $url, string $alt = '', string $kelas = ''): string
{
    if ($url) {
        return '<img src="' . e($url) . '" alt="' . e($alt) . '" class="' . e($kelas) . '" loading="lazy">';
    }
    return '<span class="kotak-kosong ' . e($kelas) . '"><span>' . e($alt !== '' ? $alt : 'Belum ada gambar') . '</span></span>';
}

// =====================================================================================
// GALERI — ALBUM BERTAHUN (v11)
// =====================================================================================
/**
 * Tahun album galeri yang sah: hanya 4 angka (1900–2999). Nilai lain dikosongkan
 * (bukan ditebak) sehingga kolom tahun selalu berisi tahun atau kosong.
 */
function album_tahun_sah(string $teks): string
{
    $t = trim($teks);
    if ($t === '') {
        return '';
    }
    $t = (string) preg_replace('#[^0-9]#', '', $t);
    return preg_match('#^(19|20|21|22|23|24|25|26|27|28|29)\d{2}$#', $t) ? $t : '';
}

/** Label album = nama + tahun (mis. "Pramuka 2026"); tanpa tahun → hanya namanya. */
function album_label(array $album): string
{
    $nama = trim((string) ($album['nama'] ?? ''));
    $tahun = trim((string) ($album['tahun'] ?? ''));
    return $tahun !== '' ? $nama . ' ' . $tahun : $nama;
}

/**
 * Album AKTIF beserta JUMLAH FOTO dan sampul efektifnya.
 *
 * ⚠️ Sejak v1.7.8 **album hanya berisi FOTO** (permintaan pemilik). Video TIDAK dikelompokkan ke
 * album lagi — semuanya tampil pada bagian "Video Kegiatan" (`video_publik()`), sehingga
 * perhitungan di sini sengaja tidak lagi menghitung video.
 *
 * Sampul efektif = kolom `sampul`, atau foto pertama album bila sampul kosong.
 *
 * @return array<int,array<string,mixed>>
 */
function album_publik(): array
{
    return all("SELECT a.*,
            (SELECT COUNT(*) FROM galeri_foto f WHERE f.album_id = a.id) AS jml_foto,
            COALESCE(NULLIF(a.sampul, ''), (SELECT f2.gambar FROM galeri_foto f2
                WHERE f2.album_id = a.id ORDER BY f2.urutan, f2.id LIMIT 1), '') AS sampul_efektif
        FROM galeri_album a WHERE a.aktif = 1
        ORDER BY a.tahun DESC, a.urutan, a.id");
}

/**
 * Seluruh VIDEO yang tampil (tanpa album): aktif dan tautan YouTube-nya sah.
 *
 * @return array<int,array<string,mixed>>
 */
function video_publik(int $batas = 0): array
{
    $sql = 'SELECT * FROM galeri_video WHERE aktif = 1 ORDER BY urutan, id' . ($batas > 0 ? ' LIMIT ' . $batas : '');
    $baris = all($sql);
    return array_values(array_filter($baris, static fn (array $v): bool => youtube_id((string) $v['youtube']) !== ''));
}

/** Jumlah video yang tampil (tanpa album). */
function video_publik_jumlah(): int
{
    return count(video_publik());
}

/**
 * Kelompokkan album per NAMA (tanpa peduli besar-kecil huruf & spasi berlebih) sehingga album
 * "Pramuka" tahun 2024, 2025, 2026 tampil sebagai SATU kartu dengan pilihan tahun.
 * Setiap kelompok diberi daftar tahun TERBARU lebih dulu; kelompok diurut menurut tahun terbaru juga.
 *
 * (Album hanya berisi FOTO sejak v1.7.8 — video tampil pada bagiannya sendiri.)
 *
 * @return array<int,array{nama:string,tahun:array<int,string>,album:array<int,array<string,mixed>>,jml_foto:int,sampul:string,urutan_min:int}>
 */
function album_kelompok(): array
{
    $kelompok = [];
    foreach (album_publik() as $a) {
        $kunci = strtolower(trim((string) preg_replace('#\s+#', ' ', (string) $a['nama'])));
        if (!isset($kelompok[$kunci])) {
            $kelompok[$kunci] = ['nama' => (string) $a['nama'], 'tahun' => [], 'album' => [],
                'jml_foto' => 0, 'sampul' => '', 'urutan_min' => (int) $a['urutan']];
        }
        $tahun = trim((string) $a['tahun']);
        if ($tahun !== '') {
            $kelompok[$kunci]['tahun'][$tahun] = $tahun;
        }
        $kelompok[$kunci]['album'][] = $a;
        $kelompok[$kunci]['jml_foto'] += (int) $a['jml_foto'];
        if ($kelompok[$kunci]['sampul'] === '') {
            $kelompok[$kunci]['sampul'] = (string) $a['sampul_efektif'];
        }
        $kelompok[$kunci]['urutan_min'] = min($kelompok[$kunci]['urutan_min'], (int) $a['urutan']);
    }
    $keluar = array_values($kelompok);
    foreach ($keluar as &$k) {
        krsort($k['tahun']);
        $k['tahun'] = array_values($k['tahun']);
    }
    unset($k);
    usort($keluar, static function (array $a, array $b): int {
        $ta = $a['tahun'] !== [] ? (int) $a['tahun'][0] : 0;
        $tb = $b['tahun'] !== [] ? (int) $b['tahun'][0] : 0;
        return $tb <=> $ta ?: ($a['urutan_min'] <=> $b['urutan_min']);
    });
    return $keluar;
}

/** Album (aktif) berdasarkan slug. */
function album_by_slug(string $slug): ?array
{
    return $slug !== '' ? one('SELECT * FROM galeri_album WHERE slug = ? AND aktif = 1', [$slug]) : null;
}

/** Foto satu album (urut). */
function album_foto(int $albumId): array
{
    return all('SELECT * FROM galeri_foto WHERE album_id = ? ORDER BY urutan, id', [$albumId]);
}

// =====================================================================================
// FOTO SISWA MASSAL (unggah sekaligus dari perangkat / ambil dari alamat web)
// =====================================================================================
/** Normalkan nama untuk pencocokan: huruf kecil, tanpa tanda baca, spasi dirapatkan. */
function nama_normal(string $teks): string
{
    $t = strtolower(trim($teks));
    $t = (string) preg_replace('#[^a-z0-9]+#', ' ', $t);
    return trim((string) preg_replace('#\s+#', ' ', $t));
}

/** Normalkan nama TANPA spasi (untuk nama berkas seperti "AhmadFaisSirojudin.jpg"). */
function nama_normal_rapat(string $teks): string
{
    return (string) str_replace(' ', '', nama_normal($teks));
}

/**
 * Membersihkan nama dari bagian nama berkas yang tidak penting
 * (mis. "foto_ahmad-fais_2020.jpg" → "ahmad fais").
 */
function nama_dari_berkas(string $namaBerkas): string
{
    $t = pathinfo($namaBerkas, PATHINFO_FILENAME);
    $t = (string) preg_replace('#[_\-.]+#', ' ', $t);
    // buang kata umum + angka
    $t = (string) preg_replace('#\b(foto|photo|img|image|gambar|pic|siswa|murid|dokumen|scan|new|copy|edit|fix|final)\b#i', ' ', $t);
    $t = (string) preg_replace('#\b\d{1,4}\b#', ' ', $t);
    return trim((string) preg_replace('#\s+#', ' ', $t));
}

/** Peta siswa untuk mencocokkan nama/NIS: [nama], [rapat], [kode]. */
function siswa_peta(): array
{
    $peta = ['nama' => [], 'rapat' => [], 'kode' => []];
    foreach (all("SELECT id, nama, nis, nisn, foto FROM siswa") as $r) {
        $k = nama_normal((string) $r['nama']);
        if ($k !== '' && !isset($peta['nama'][$k])) {
            $peta['nama'][$k] = $r;
        }
        $kr = nama_normal_rapat((string) $r['nama']);
        if ($kr !== '' && !isset($peta['rapat'][$kr])) {
            $peta['rapat'][$kr] = $r;
        }
        foreach (['nis', 'nisn'] as $kol) {
            $v = trim((string) $r[$kol]);
            if ($v !== '' && !isset($peta['kode'][$v])) {
                $peta['kode'][$v] = $r;
            }
        }
    }
    return $peta;
}

/**
 * Mencocokkan daftar kandidat foto (nama + opsional NIS/NISN + URL/gambar) dengan data siswa.
 *
 * Urutan pencocokan: (1) NIS/NISN, (2) nama persis, (3) nama tanpa spasi,
 * (4) nama yang salah satunya memuat yang lain (minimal 6 huruf).
 *
 * @param array<int,array{nama:string,foto:string,nis?:string}> $kandidat
 * @return array{cocok:array<int,array>,tidak:array<int,array>,lewat:array<int,array>}
 */
function foto_cocokkan(array $kandidat, bool $hanyaKosong = false): array
{
    $peta = siswa_peta();
    $cocok = [];
    $tidak = [];
    $lewat = [];
    $sudah = [];
    foreach ($kandidat as $k) {
        $foto = trim((string) ($k['foto'] ?? ''));
        $nama = trim((string) ($k['nama'] ?? ''));
        $kode = trim((string) ($k['nis'] ?? ''));
        $siswa = null;
        if ($kode !== '' && isset($peta['kode'][$kode])) {
            $siswa = $peta['kode'][$kode];
        }
        if (!$siswa && $nama !== '') {
            $n = nama_normal($nama);
            $nr = nama_normal_rapat($nama);
            if (isset($peta['nama'][$n])) {
                $siswa = $peta['nama'][$n];
            } elseif (isset($peta['rapat'][$nr])) {
                $siswa = $peta['rapat'][$nr];
            } else {
                // pencocokan longgar: salah satu nama memuat yang lain
                foreach ($peta['nama'] as $kunci => $row) {
                    if (strlen($kunci) >= 6 && ($n !== '' && (str_contains($kunci, $n) || str_contains($n, $kunci)))) {
                        $siswa = $row;
                        break;
                    }
                }
            }
        }
        if (!$siswa) {
            $tidak[] = ['nama' => $nama !== '' ? $nama : '(tanpa nama)', 'foto' => $foto, 'berkas' => (string) ($k['berkas'] ?? '')];
            continue;
        }
        $idSiswa = (int) $siswa['id'];
        if (isset($sudah[$idSiswa])) {
            $lewat[] = ['nama' => (string) $siswa['nama'], 'foto' => $foto, 'alasan' => 'sudah mendapat foto pada proses ini'];
            continue;
        }
        if ($hanyaKosong && trim((string) $siswa['foto']) !== '') {
            $lewat[] = ['nama' => (string) $siswa['nama'], 'foto' => $foto, 'alasan' => 'sudah punya foto'];
            continue;
        }
        $sudah[$idSiswa] = true;
        $cocok[] = [
            'siswa_id'   => $idSiswa,
            'siswa_nama' => (string) $siswa['nama'],
            'kelas'      => (string) (one('SELECT k.nama FROM kelas k JOIN siswa s ON s.kelas_id = k.id WHERE s.id = ?', [$idSiswa])['nama'] ?? ''),
            'nama_kandidat' => $nama,
            'foto'       => $foto,
        ];
    }
    return ['cocok' => $cocok, 'tidak' => $tidak, 'lewat' => $lewat];
}

/** Menyimpan foto ke data siswa. @return int jumlah baris terbarui */
function foto_simpan(array $cocok): int
{
    $n = 0;
    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach ($cocok as $c) {
            if (trim((string) ($c['foto'] ?? '')) === '') {
                continue;
            }
            q('UPDATE siswa SET foto = ? WHERE id = ?', [(string) $c['foto'], (int) $c['siswa_id']]);
            $n++;
        }
        db()->exec('COMMIT');
    } catch (Throwable $e) {
        db()->exec('ROLLBACK');
        return 0;
    }
    return $n;
}

/**
 * Unggah BANYAK foto dari perangkat sekaligus; nama berkas dipakai untuk mencocokkan siswa.
 * @return array{ok:bool,kandidat:array<int,array>,pesan:string}
 */
function unggah_foto_banyak(string $field = 'foto'): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) {
        return ['ok' => false, 'kandidat' => [], 'pesan' => 'Tidak ada berkas yang dipilih.'];
    }
    $kandidat = [];
    $lewat = [];
    $jml = count($_FILES[$field]['name']);
    for ($i = 0; $i < $jml; $i++) {
        $f = [
            'name' => (string) $_FILES[$field]['name'][$i],
            'type' => (string) ($_FILES[$field]['type'][$i] ?? ''),
            'tmp_name' => (string) $_FILES[$field]['tmp_name'][$i],
            'error' => (int) $_FILES[$field]['error'][$i],
            'size' => (int) $_FILES[$field]['size'][$i],
        ];
        if ($f['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        // daftarkan sebagai berkas satu-satu agar bisa dipakai fungsi unggah yang sudah ada
        $_FILES['__foto_massal'] = $f;
        $up = unggah_gambar('__foto_massal', 'Foto ' . $f['name']);
        unset($_FILES['__foto_massal']);
        if (!$up['ok']) {
            $lewat[] = ['nama' => $f['name'], 'alasan' => $up['pesan']];
            continue;
        }
        $kandidat[] = [
            'nama'   => nama_dari_berkas($f['name']),
            'foto'   => (string) $up['url'],
            'berkas' => $f['name'],
            'nis'    => (string) (preg_match('#(\d{6,})#', $f['name'], $m) ? $m[1] : ''),
        ];
    }
    return ['ok' => (bool) $kandidat, 'kandidat' => $kandidat, 'lewat' => $lewat,
        'pesan' => $kandidat ? '' : 'Tidak ada berkas gambar yang berhasil diunggah.'];
}

/**
 * Mengambil pasangan (nama, foto) dari ALAMAT WEB.
 *
 * Mendukung tiga pola:
 *  1. **direktori dengan filter tahun pelajaran & kelas** (mis. `/student-directory` madrasah ini):
 *     halaman memuat `<select id="academic_year_id">` & `<select id="class_group_id">` serta fungsi
 *     `search_students()` → data diambil dari endpoint `public/student_directory/search_students`
 *     untuk setiap kombinasi (dibatasi agar tidak membebani server sumber);
 *  2. **direktori alumni** (pola `total_pages` + endpoint `more_alumni`) — memakai `alumni_ambil_web()`;
 *  3. **cadangan umum**: tabel pada halaman itu dibaca langsung (kolom nama + gambar).
 *
 * @return array{ok:bool,pesan:string,kandidat:array<int,array{nama:string,foto:string,nis:string}>}
 */
function foto_ambil_web(string $url): array
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return ['ok' => false, 'pesan' => 'Alamat harus berupa tautan lengkap (http/https).', 'kandidat' => []];
    }
    $hal = web_ambil($url);
    if (!$hal['ok'] || (int) $hal['kode'] === 0) {
        return ['ok' => false, 'pesan' => $hal['pesan'] !== '' ? $hal['pesan'] : 'Alamat tidak terjangkau.', 'kandidat' => []];
    }
    if ((int) $hal['kode'] >= 400) {
        return ['ok' => false, 'pesan' => 'Alamat tersebut menjawab kode ' . (int) $hal['kode'] . '.', 'kandidat' => []];
    }
    $html = $hal['isi'];
    $kandidat = [];

    // ---- pola 1: direktori dengan filter tahun pelajaran & kelas ----
    if (str_contains($html, 'search_students') && preg_match("#_BASE_URL\s*=\s*['\"]((?:https?:)?//[^'\"]+)['\"]#", $html, $mb)) {
        $tahun = [];
        if (preg_match('#id="academic_year_id".*?</select>#is', $html, $ms, PREG_OFFSET_CAPTURE)) {
            $blok = $ms[0][0];
            if (preg_match_all('#<option[^>]*value="(\d+)"#i', $blok, $mo)) {
                $tahun = $mo[1];
            }
        }
        $kelas = [];
        if (preg_match('#id="class_group_id".*?</select>#is', $html, $mk, PREG_OFFSET_CAPTURE)) {
            $blok = $mk[0][0];
            if (preg_match_all('#<option[^>]*value="(\d+)"#i', $blok, $mo)) {
                $kelas = $mo[1];
            }
        }
        $endpoint = rtrim($mb[1], '/') . '/public/student_directory/search_students';
        $permintaan = 0;
        if ($tahun && $kelas) {
            foreach ($tahun as $th) {
                foreach ($kelas as $kl) {
                    if ($permintaan >= 30 || count($kandidat) >= ALUMNI_WEB_MAKS_BARIS) {
                        break 2;
                    }
                    $permintaan++;
                    $jawab = web_ambil($endpoint, 'POST', ['academic_year_id' => $th, 'class_group_id' => $kl]);
                    if (!$jawab['ok']) {
                        continue;
                    }
                    $data = json_decode($jawab['isi'], true);
                    foreach ((array) (is_array($data) ? ($data['rows'] ?? []) : []) as $r) {
                        $nama = trim((string) ($r['full_name'] ?? ''));
                        $foto = trim((string) ($r['photo'] ?? ''));
                        if ($nama === '' || $foto === '') {
                            continue;
                        }
                        $kandidat[alumni_kunci($nama, '')] = [
                            'nama' => $nama,
                            'foto' => $foto,
                            'nis'  => trim((string) ($r['nis'] ?? ($r['nim'] ?? ''))),
                        ];
                    }
                }
            }
        }
        if ($kandidat) {
            return ['ok' => true, 'pesan' => 'Foto diambil dari direktori peserta didik (' . count($kandidat) . ' siswa, ' . $permintaan . ' permintaan).',
                'kandidat' => array_values($kandidat)];
        }
        // bila filter tidak terbaca, tetap lanjut ke pola berikutnya
    }

    // ---- pola 2: direktori alumni ----
    $alumni = alumni_ambil_web($url);
    if ($alumni['ok'] && $alumni['baris']) {
        foreach ($alumni['baris'] as $b) {
            if (trim((string) $b['foto']) === '') {
                continue;
            }
            $kandidat[alumni_kunci((string) $b['nama'], '')] = [
                'nama' => (string) $b['nama'],
                'foto' => (string) $b['foto'],
                'nis'  => (string) $b['nis'],
            ];
        }
        if ($kandidat) {
            return ['ok' => true, 'pesan' => 'Foto diambil dari halaman tersebut (' . count($kandidat) . ' orang).',
                'kandidat' => array_values($kandidat)];
        }
    }

    // ---- pola 3: baca tabel umum pada halaman ----
    foreach (alumni_parse_tabel_html($html) as $b) {
        if (trim((string) $b['foto']) === '' || trim((string) $b['nama']) === '') {
            continue;
        }
        $kandidat[alumni_kunci((string) $b['nama'], '')] = [
            'nama' => (string) $b['nama'],
            'foto' => (string) $b['foto'],
            'nis'  => (string) $b['nis'],
        ];
    }
    if ($kandidat) {
        return ['ok' => true, 'pesan' => 'Foto dibaca dari tabel pada halaman tersebut (' . count($kandidat) . ' orang).',
            'kandidat' => array_values($kandidat)];
    }
    return ['ok' => false, 'pesan' => 'Tidak menemukan daftar nama + foto pada halaman tersebut. Pastikan alamatnya benar, atau unggah foto dari perangkat.', 'kandidat' => []];
}

// =====================================================================================
// DATA ALUMNI (kelulusan + data manual/impor)
// =====================================================================================
/** Jenis kelamin dari teks bebas ("Laki-laki"/"Perempuan"/"L"/"P"). */
function alumni_gender(string $teks): string
{
    $t = strtolower(trim($teks));
    if ($t === '') {
        return 'L';
    }
    if (str_starts_with($t, 'p') || str_contains($t, 'perempuan') || $t === 'female' || $t === 'f') {
        return 'P';
    }
    return 'L';
}

function alumni_gender_label(string $kode): string
{
    return $kode === 'P' ? 'Perempuan' : 'Laki-laki';
}

/** Data alumni yang diisi MANUAL/IMPOR (tabel `alumni`). */
function alumni_manual(bool $hanyaAktif = true): array
{
    $w = $hanyaAktif ? 'WHERE aktif = 1' : '';
    return all('SELECT * FROM alumni ' . $w . ' ORDER BY urutan, CAST(tahun_keluar AS INTEGER) DESC, nama');
}

function alumni_manual_jumlah(bool $hanyaAktif = false): int
{
    $w = $hanyaAktif ? 'WHERE aktif = 1' : '';
    return jumlah('SELECT COUNT(*) FROM alumni ' . $w);
}

function alumni_by_id(int $id): ?array
{
    return one('SELECT * FROM alumni WHERE id = ?', [$id]);
}

/** Kunci pembanding untuk menggabungkan data ganda (nama + tahun keluar). */
function alumni_kunci(string $nama, string $tahunKeluar): string
{
    $n = strtolower((string) preg_replace('#[^a-z0-9]+#i', ' ', $nama));
    return trim((string) preg_replace('#\s+#', ' ', $n)) . '|' . trim($tahunKeluar);
}

/**
 * Daftar alumni GABUNGAN: data kelulusan (siswa berstatus lulus) + tabel `alumni` (manual/impor).
 *
 * Bila ada nama & tahun keluar yang sama, hanya satu yang dipakai — yang punya FOTO diutamakan
 * (dan data kelulusan didahulukan bila keduanya sama-sama berfoto).
 *
 * @return array<int,array<string,mixed>>
 */
function alumni_gabungan(int $tahun = 0, string $cari = ''): array
{
    $hasil = [];
    $cari = trim($cari);

    // (a) dari data kelulusan
    foreach (all("SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
                  WHERE s.status = 'lulus'") as $r) {
        $tahunKeluar = trim((string) $r['tahun_lulus']);
        $nama = (string) $r['nama'];
        $hasil[alumni_kunci($nama, $tahunKeluar)] = [
            'nama'         => $nama,
            'nis'          => (string) $r['nis'],
            'nisn'         => (string) $r['nisn'],
            'jenis_kelamin' => $r['jenis_kelamin'] === 'P' ? 'P' : 'L',
            'tahun_masuk'  => tahun_dari((string) $r['tanggal_masuk']),
            'tahun_keluar' => $tahunKeluar,
            'keterangan'   => (string) $r['keterangan'],
            'foto'         => (string) $r['foto'],
            'kelas'        => (string) ($r['kelas'] ?? ''),
            'sumber'       => 'kelulusan',
            'urutan'       => 0,
        ];
    }

    // (b) dari tabel alumni (manual/impor) — tidak menimpa data kelulusan yang sudah berfoto
    foreach (alumni_manual(true) as $r) {
        $nama = (string) $r['nama'];
        $tahunKeluar = trim((string) $r['tahun_keluar']);
        $kunci = alumni_kunci($nama, $tahunKeluar);
        $baru = [
            'nama'         => $nama,
            'nis'          => (string) $r['nis'],
            'nisn'         => (string) $r['nisn'],
            'jenis_kelamin' => $r['jenis_kelamin'] === 'P' ? 'P' : 'L',
            'tahun_masuk'  => (string) $r['tahun_masuk'],
            'tahun_keluar' => $tahunKeluar,
            'keterangan'   => (string) $r['keterangan'],
            'foto'         => (string) $r['foto'],
            'kelas'        => '',
            'sumber'       => 'manual',
            'urutan'       => (int) $r['urutan'],
        ];
        if (isset($hasil[$kunci])) {
            if (trim((string) $hasil[$kunci]['foto']) === '' && $baru['foto'] !== '') {
                $hasil[$kunci] = $baru;   // data manual dipakai karena punya foto
            }
            continue;
        }
        $hasil[$kunci] = $baru;
    }

    $daftar = array_values($hasil);

    // Saringan tahun keluar + pencarian (dilakukan di PHP karena data berasal dari dua sumber)
    if ($tahun > 0) {
        $daftar = array_values(array_filter($daftar, static fn (array $r): bool => (int) $r['tahun_keluar'] === $tahun));
    }
    if ($cari !== '') {
        $daftar = array_values(array_filter($daftar, static function (array $r) use ($cari): bool {
            $t = strtolower($cari);
            return str_contains(strtolower($r['nama']), $t)
                || str_contains(strtolower((string) $r['nis']), $t)
                || str_contains(strtolower((string) $r['nisn']), $t);
        }));
    }

    usort($daftar, static function (array $a, array $b): int {
        $ta = (int) $a['tahun_keluar'];
        $tb = (int) $b['tahun_keluar'];
        if ($ta !== $tb) {
            return $tb <=> $ta;
        }
        return strcasecmp((string) $a['nama'], (string) $b['nama']);
    });
    return $daftar;
}

/**
 * Jumlah alumni yang TAMPIL di website (angka statistik "… dalam Angka").
 *
 * Memakai sumber yang SAMA dengan halaman Data Alumni — data kelulusan (siswa berstatus lulus)
 * DIGABUNG dengan tabel `alumni` (isi manual/impor), sudah dibersihkan dari data ganda oleh
 * `alumni_gabungan()`. Sebelumnya statistik hanya menghitung siswa berstatus lulus sehingga
 * angka Alumni tetap 0 walau admin sudah mengisi/mengimpor data alumni lama.
 */
function alumni_jumlah_gabungan(): int
{
    static $jumlah = null;
    if ($jumlah === null) {
        $jumlah = count(alumni_gabungan());
    }
    return $jumlah;
}

/** Tahun keluar yang tersedia pada data gabungan (untuk penyaring). */
function alumni_tahun_gabungan(): array
{
    $tahun = [];
    foreach (alumni_gabungan() as $r) {
        if ($r['tahun_keluar'] !== '') {
            $tahun[(int) $r['tahun_keluar']] = true;
        }
    }
    krsort($tahun);
    return array_map('strval', array_keys($tahun));
}

/**
 * Jumlah alumni PER TAHUN KELUAR — dipakai grafik halaman Data Alumni ("Grafik Alumni per Tahun").
 *
 * Dihitung dari data yang SAMA dengan tabel halaman (gabungan data kelulusan + tabel `alumni`,
 * sudah dibersihkan dari data ganda oleh `alumni_gabungan()`), hanya tahun yang ada isinya
 * (tahun kosong/`-` dilewati), urut tahun menaik supaya rapi pada grafik.
 *
 * @return array<int,int> tahun => jumlah alumni
 */
function alumni_per_tahun(): array
{
    $keluar = [];
    foreach (alumni_gabungan() as $r) {
        $th = (int) trim((string) $r['tahun_keluar']);
        if ($th > 1900 && $th < 2200) {
            $keluar[$th] = ($keluar[$th] ?? 0) + 1;
        }
    }
    ksort($keluar);
    return $keluar;
}

/** Label sumber data alumni (keterangan kecil pada tabel admin). */
function alumni_sumber_label(string $sumber): string
{
    return $sumber === 'kelulusan' ? 'Data kelulusan' : ($sumber === 'web' ? 'Ambil dari web' : 'Diisi manual');
}

// =====================================================================================
// PENGAMBILAN DATA ALUMNI DARI ALAMAT WEB (direktori alumni)
// =====================================================================================
/** Permintaan HTTP sederhana (GET/POST) memakai curl atau stream. */
function web_ambil(string $url, string $metode = 'GET', array $data = [], int $timeout = 25): array
{
    $body = false;
    $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opsi = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (kompatibel; pengambil data alumni)',
            CURLOPT_SSL_VERIFYPEER => false,
        ];
        if (strtoupper($metode) === 'POST') {
            $opsi[CURLOPT_POST] = true;
            $opsi[CURLOPT_POSTFIELDS] = http_build_query($data);
        }
        curl_setopt_array($ch, $opsi);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string) curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            return ['ok' => false, 'pesan' => 'Gagal menghubungi alamat tersebut: ' . $err, 'isi' => '', 'kode' => 0];
        }
    } else {
        $konteks = ['http' => [
            'method' => strtoupper($metode),
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "User-Agent: Mozilla/5.0\r\n",
        ]];
        if (strtoupper($metode) === 'POST') {
            $konteks['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $konteks['http']['content'] = http_build_query($data);
        }
        $body = @file_get_contents($url, false, stream_context_create($konteks));
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $h, $m)) {
                    $code = (int) $m[1];
                }
            }
        }
        if ($body === false) {
            return ['ok' => false, 'pesan' => 'Gagal menghubungi alamat tersebut (server menolak atau tidak terjangkau).', 'isi' => '', 'kode' => 0];
        }
    }
    return ['ok' => true, 'pesan' => '', 'isi' => (string) $body, 'kode' => $code];
}

/**
 * Mengambil daftar alumni dari alamat web direktori alumni.
 *
 * Mendukung dua cara: (1) pola direktori madrasah ini — halaman memuat variabel `total_pages` dan
 * `_BASE_URL`, lalu barisnya diambil dari endpoint `public/alumni_directory/more_alumni`
 * (`POST page_number`); (2) cara umum — tabel `<table>` pada halaman itu dibaca langsung.
 *
 * @return array{ok:bool,pesan:string,baris:array<int,array<string,string>>}
 */
function alumni_ambil_web(string $url): array
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return ['ok' => false, 'pesan' => 'Alamat harus berupa tautan lengkap (http/https).', 'baris' => []];
    }
    $hal = web_ambil($url);
    if (!$hal['ok']) {
        return ['ok' => false, 'pesan' => $hal['pesan'], 'baris' => []];
    }
    if ((int) $hal['kode'] >= 400 || (int) $hal['kode'] === 0) {
        return ['ok' => false, 'pesan' => 'Alamat tersebut menjawab kode ' . (int) $hal['kode'] . '.', 'baris' => []];
    }
    $html = $hal['isi'];
    $baris = [];

    // ---- cara 1: pola halaman direktori dengan endpoint more_alumni ----
    if (preg_match('#total_pages\s*=\s*["\'](\d+)["\']#', $html, $mt) && preg_match("#_BASE_URL\s*=\s*['\"]((?:https?:)?//[^'\"]+)['\"]#", $html, $mb)) {
        $total = min((int) $mt[1], ALUMNI_WEB_MAKS_HALAMAN);
        $endpoint = rtrim($mb[1], '/') . '/public/alumni_directory/more_alumni';
        // baris pada halaman pertama biasanya sudah tercetak di HTML → ikut dibaca sebagai cadangan
        $barisHtml = alumni_parse_tabel_html($html);
        $sudahAda = [];
        for ($p = 1; $p <= max(1, $total); $p++) {
            $jawab = web_ambil($endpoint, 'POST', ['page_number' => $p]);
            if (!$jawab['ok']) {
                continue;
            }
            $data = json_decode($jawab['isi'], true);
            $rows = is_array($data) ? ($data['rows'] ?? []) : [];
            if (!$rows && $p === 1 && $barisHtml) {
                $rows = $barisHtml;
            }
            // ⚠️ Endpoint sumber bisa mengembalikan SELURUH daftar pada halaman pertama lalu
            // mengulang sebagian baris yang sama pada halaman berikutnya. Karena itu baris yang
            // sudah pernah dibaca DILEWATI, dan bila satu halaman tidak menambah baris baru sama
            // sekali, pengambilan dihentikan (tidak membebani server sumber tanpa guna).
            $baruPadaHalaman = 0;
            foreach ((array) $rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $nama = trim((string) ($r['full_name'] ?? ($r['nama'] ?? '')));
                if ($nama === '') {
                    continue;
                }
                $tahunMasuk = preg_match('#\d{4}#', (string) ($r['start_date'] ?? ''), $m1) ? $m1[0] : '';
                $tahunKeluar = preg_match('#\d{4}#', (string) ($r['end_date'] ?? ''), $m2) ? $m2[0] : '';
                $kunciBaris = alumni_kunci($nama, $tahunKeluar);
                if (isset($sudahAda[$kunciBaris])) {
                    continue;
                }
                $sudahAda[$kunciBaris] = true;
                $baris[] = [
                    'nama'         => $nama,
                    'nis'          => trim((string) ($r['nis'] ?? ($r['nim'] ?? ''))),
                    'nisn'         => '',
                    'jenis_kelamin' => alumni_gender((string) ($r['gender'] ?? ($r['jenis_kelamin'] ?? ''))),
                    'tahun_masuk'  => $tahunMasuk,
                    'tahun_keluar' => $tahunKeluar,
                    'foto'         => trim((string) ($r['photo'] ?? ($r['foto'] ?? ''))),
                    'keterangan'   => '',
                ];
                $baruPadaHalaman++;
                if (count($baris) >= ALUMNI_WEB_MAKS_BARIS) {
                    break 2;
                }
            }
            if ($baruPadaHalaman === 0) {
                break;   // halaman berikutnya hanya mengulang → hentikan
            }
        }
        if ($baris) {
            return ['ok' => true, 'pesan' => 'Data diambil dari ' . $total . ' halaman sumber (' . count($baris) . ' alumni, duplikat pada sumber diabaikan).', 'baris' => $baris];
        }
    }

    // ---- cara 2: baca tabel pada halaman ----
    $baris = alumni_parse_tabel_html($html);
    if ($baris) {
        return ['ok' => true, 'pesan' => 'Data dibaca dari tabel pada halaman tersebut (' . count($baris) . ' baris).', 'baris' => $baris];
    }
    return ['ok' => false, 'pesan' => 'Tidak menemukan daftar alumni pada halaman tersebut. Pastikan alamatnya benar, atau gunakan impor Excel/CSV.', 'baris' => []];
}

/** Membaca baris alumni dari tabel HTML halaman direktori (cadangan cara 1 / cara umum). */
function alumni_parse_tabel_html(string $html): array
{
    $baris = [];
    if (!preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $html, $trs)) {
        return [];
    }
    foreach ($trs[1] as $tr) {
        if (stripos($tr, '<th') !== false) {
            continue;   // baris judul
        }
        if (!preg_match_all('#<td[^>]*>(.*?)</td>#is', $tr, $tds) || count($tds[1]) < 4) {
            continue;
        }
        $sel = array_map(static function (string $td): string {
            $td = preg_replace('#<img[^>]*src=["\']([^"\']+)["\'][^>]*>#i', ' [[FOTO:$1]] ', $td);
            return trim((string) preg_replace('#\s+#', ' ', strip_tags((string) $td)));
        }, $tds[1]);
        $foto = '';
        if (preg_match('#\[\[FOTO:([^\]]+)\]\]#', $sel[1] ?? '', $mf)) {
            $foto = trim($mf[1]);
        }
        // Tabel direktori: NO | PHOTO | NIS | NAMA | JENIS KELAMIN | TAHUN MASUK | TAHUN KELUAR
        $nama = trim((string) ($sel[3] ?? ''));
        if ($nama === '' || !preg_match('#[A-Za-z]{3,}#', $nama)) {
            continue;
        }
        $jk = alumni_gender((string) ($sel[4] ?? ''));
        $masuk = preg_match('#\d{4}#', (string) ($sel[5] ?? ''), $m1) ? $m1[0] : '';
        $keluar = preg_match('#\d{4}#', (string) ($sel[6] ?? ''), $m2) ? $m2[0] : '';
        $baris[] = [
            'nama'          => $nama,
            'nis'           => trim((string) ($sel[2] ?? '')),
            'nisn'          => '',
            'jenis_kelamin' => $jk,
            'tahun_masuk'   => $masuk,
            'tahun_keluar'  => $keluar,
            'foto'          => $foto,
            'keterangan'    => '',
        ];
        if (count($baris) >= ALUMNI_WEB_MAKS_BARIS) {
            break;
        }
    }
    return $baris;
}

/** Menyimpan hasil pengambilan web ke tabel `alumni` (menggantikan data yang bersumber 'web'). */
function alumni_simpan_web(array $baris): array
{
    $tambah = 0;
    $perbarui = 0;
    $lewat = 0;
    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach ($baris as $r) {
            $nama = trim((string) ($r['nama'] ?? ''));
            if ($nama === '') {
                $lewat++;
                continue;
            }
            $tahunKeluar = preg_match('#\d{4}#', (string) ($r['tahun_keluar'] ?? ''), $m) ? $m[0] : '';
            $ada = one('SELECT * FROM alumni WHERE LOWER(nama) = LOWER(?) AND tahun_keluar = ?', [$nama, $tahunKeluar]);
            $data = [
                'nis'           => substr(trim((string) ($r['nis'] ?? '')), 0, 30),
                'nisn'          => substr(trim((string) ($r['nisn'] ?? '')), 0, 30),
                'nama'          => potong($nama, NAMA_MAKS, ''),
                'jenis_kelamin' => alumni_gender((string) ($r['jenis_kelamin'] ?? '')),
                'tahun_masuk'   => preg_match('#\d{4}#', (string) ($r['tahun_masuk'] ?? ''), $m2) ? $m2[0] : '',
                'tahun_keluar'  => $tahunKeluar,
                'keterangan'    => potong((string) ($r['keterangan'] ?? ''), 200, ''),
                'foto'          => substr(trim((string) ($r['foto'] ?? '')), 0, 400),
                'sumber'        => 'web',
            ];
            if ($ada) {
                // Jangan menimpa keterangan yang sudah diisi admin
                if (trim((string) $ada['keterangan']) !== '' && $data['keterangan'] === '') {
                    unset($data['keterangan']);
                }
                if (trim((string) $ada['foto']) !== '' && $data['foto'] === '') {
                    unset($data['foto']);
                }
                $set = [];
                $val = [];
                foreach ($data as $k => $v) {
                    $set[] = $k . ' = ?';
                    $val[] = $v;
                }
                $val[] = (int) $ada['id'];
                q('UPDATE alumni SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
                $perbarui++;
            } else {
                $data['urutan'] = urutan_baru('alumni');
                $data['aktif'] = 1;
                $data['dibuat'] = date('Y-m-d H:i:s');
                $kol = array_keys($data);
                q('INSERT INTO alumni (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
                $tambah++;
            }
        }
        db()->exec('COMMIT');
    } catch (Throwable $e) {
        db()->exec('ROLLBACK');
        return ['ok' => false, 'tambah' => 0, 'perbarui' => 0, 'lewat' => 0, 'pesan' => 'Gagal menyimpan: ' . $e->getMessage()];
    }
    return ['ok' => true, 'tambah' => $tambah, 'perbarui' => $perbarui, 'lewat' => $lewat, 'pesan' => ''];
}

// =====================================================================================
// PRESTASI & PELATIHAN (halaman publik Kesiswaan/Akademik)
// =====================================================================================
/** Daftar kategori prestasi: kunci => [judul, jenis menu]. */
function prestasi_kategori(): array
{
    return [
        'siswa'     => ['judul' => 'Prestasi Siswa', 'menu' => 'prestasi_siswa'],
        'madrasah'  => ['judul' => 'Prestasi Madrasah', 'menu' => 'prestasi_madrasah'],
        'pendidik'  => ['judul' => 'Prestasi Pendidik', 'menu' => 'prestasi_pendidik'],
        'pelatihan' => ['judul' => 'Pelatihan Pendidik', 'menu' => 'pelatihan_pendidik'],
    ];
}

/** Kategori dari jenis menu (mis. 'prestasi_siswa' → 'siswa'); kosong bila bukan jenis prestasi. */
function prestasi_dari_menu(string $jenis): string
{
    foreach (prestasi_kategori() as $kunci => $k) {
        if ($k['menu'] === $jenis) {
            return $kunci;
        }
    }
    return '';
}

/** Slug menu aktif pertama dengan jenis tertentu (mis. 'prestasi_siswa'); kosong bila tidak ada. */
function menu_slug_jenis(string $jenis): string
{
    $r = one('SELECT slug FROM menu WHERE jenis = ? AND aktif = 1 ORDER BY id LIMIT 1', [$jenis]);
    return (string) ($r['slug'] ?? '');
}

function prestasi_kategori_sah(string $kategori): string
{
    return array_key_exists($kategori, prestasi_kategori()) ? $kategori : 'siswa';
}

function prestasi_label(string $kategori): string
{
    return prestasi_kategori()[prestasi_kategori_sah($kategori)]['judul'] ?? 'Prestasi';
}

function prestasi_tingkat_sah(string $tingkat): string
{
    return in_array($tingkat, TINGKAT_LIST, true) ? $tingkat : '';
}

/**
 * Definisi KOLOM tabel prestasi per kategori — SATU SUMBER untuk judul kolom DAN isi selnya.
 *
 * 🚨 Jangan menulis judul kolom dan sel secara terpisah: versi pertama melakukannya sehingga pada
 * kategori **Pelatihan** barisnya kehilangan sel Tahun & Tingkat (judul 7 kolom, isi 5 sel) —
 * akibatnya tombol “Lihat” Sertifikat bergeser ke kolom Tahun dan Tahun/Tingkat tampak kosong
 * padahal datanya ada. Karena itu judul dan isi kini dibangkitkan dari daftar yang sama.
 *
 * @return array<int,array{judul:string,lebar:?int,jenis:string}>
 */
function prestasi_kolom(string $kategori): array
{
    $kategori = prestasi_kategori_sah($kategori);
    $kolom = [['judul' => 'No', 'lebar' => 52, 'jenis' => 'no']];
    if (in_array($kategori, ['siswa', 'pendidik'], true)) {
        $kolom[] = ['judul' => 'Foto', 'lebar' => 64, 'jenis' => 'foto'];
    }
    if ($kategori === 'madrasah') {
        $kolom[] = ['judul' => 'Nama Prestasi', 'lebar' => null, 'jenis' => 'nama_keterangan'];
    } elseif ($kategori === 'pelatihan') {
        $kolom[] = ['judul' => 'Nama', 'lebar' => null, 'jenis' => 'nama'];
        $kolom[] = ['judul' => 'Jabatan', 'lebar' => 170, 'jenis' => 'jabatan'];
        $kolom[] = ['judul' => 'Jenis Pelatihan', 'lebar' => null, 'jenis' => 'jenis'];
    } else {
        $kolom[] = ['judul' => $kategori === 'siswa' ? 'Nama Siswa' : 'Nama Guru', 'lebar' => null, 'jenis' => 'nama_keterangan'];
        $kolom[] = ['judul' => 'Jenis Prestasi', 'lebar' => null, 'jenis' => 'jenis'];
    }
    // Tahun selalu ada; Tingkat hanya untuk kategori yang memang berjenjang (bukan prestasi madrasah).
    $kolom[] = ['judul' => 'Tahun', 'lebar' => $kategori === 'madrasah' ? 110 : 100, 'jenis' => 'tahun'];
    if ($kategori !== 'madrasah') {
        $kolom[] = ['judul' => 'Tingkat', 'lebar' => 120, 'jenis' => 'tingkat'];
    }
    $kolom[] = ['judul' => 'Sertifikat', 'lebar' => 110, 'jenis' => 'sertifikat'];
    return $kolom;
}

/** Judul kolom saja (dipakai uji & keterangan). */
function prestasi_judul_kolom(string $kategori): array
{
    return array_map(static fn (array $k): string => $k['judul'], prestasi_kolom($kategori));
}

/** Daftar prestasi satu kategori (untuk halaman publik). */
function prestasi_list(string $kategori, bool $hanyaAktif = true): array
{
    $w = $hanyaAktif ? ' AND aktif = 1' : '';
    return all('SELECT * FROM prestasi WHERE kategori = ?' . $w . ' ORDER BY urutan, CAST(tahun AS INTEGER) DESC, id',
        [prestasi_kategori_sah($kategori)]);
}

function prestasi_jumlah(string $kategori, bool $hanyaAktif = true): int
{
    $w = $hanyaAktif ? ' AND aktif = 1' : '';
    return jumlah('SELECT COUNT(*) FROM prestasi WHERE kategori = ?' . $w, [prestasi_kategori_sah($kategori)]);
}

function prestasi_by_id(int $id): ?array
{
    return one('SELECT * FROM prestasi WHERE id = ?', [$id]);
}

/** Foto yang ditampilkan: foto tersendiri → foto siswa → foto guru. */
function prestasi_foto(array $baris): string
{
    foreach (['foto'] as $k) {
        if (trim((string) ($baris[$k] ?? '')) !== '') {
            return (string) $baris[$k];
        }
    }
    if ((int) ($baris['siswa_id'] ?? 0) > 0) {
        $f = (string) (one('SELECT foto FROM siswa WHERE id = ?', [(int) $baris['siswa_id']])['foto'] ?? '');
        if ($f !== '') {
            return $f;
        }
    }
    if ((int) ($baris['guru_id'] ?? 0) > 0) {
        $f = (string) (one('SELECT foto FROM guru WHERE id = ?', [(int) $baris['guru_id']])['foto'] ?? '');
        if ($f !== '') {
            return $f;
        }
    }
    return '';
}

/** Tahun yang benar-benar ada datanya (untuk penyaring). */
function prestasi_tahun(string $kategori): array
{
    $baris = all("SELECT DISTINCT tahun FROM prestasi WHERE kategori = ? AND tahun <> '' ORDER BY CAST(tahun AS INTEGER) DESC",
        [prestasi_kategori_sah($kategori)]);
    return array_map(static fn (array $r): string => (string) $r['tahun'], $baris);
}

// =====================================================================================
// DATA SISWA & ALUMNI (halaman publik Kesiswaan)
// =====================================================================================
/** Daftar kelas untuk penyaring pengunjung. */
function kelas_publik(bool $termasukNonaktif = false): array
{
    $w = $termasukNonaktif ? '' : 'WHERE aktif = 1';
    return all('SELECT * FROM kelas ' . $w . ' ORDER BY urutan, nama');
}

/** Siswa yang masih terdaftar (belum lulus) — alumni ditampilkan pada halaman tersendiri. */
function siswa_publik_wheres(int $kelasId = 0, string $cari = ''): array
{
    $w = ["s.status <> 'lulus'"];
    $p = [];
    if ($kelasId > 0) {
        $w[] = 's.kelas_id = ?';
        $p[] = $kelasId;
    }
    if (trim($cari) !== '') {
        $w[] = '(s.nama LIKE ? OR s.nisn LIKE ? OR s.nis LIKE ?)';
        $like = '%' . trim($cari) . '%';
        array_push($p, $like, $like, $like);
    }
    return ['WHERE ' . implode(' AND ', $w), $p];
}

function siswa_publik_jumlah(int $kelasId = 0, string $cari = ''): int
{
    [$where, $p] = siswa_publik_wheres($kelasId, $cari);
    return jumlah('SELECT COUNT(*) FROM siswa s ' . $where, $p);
}

function siswa_publik(int $kelasId = 0, string $cari = '', int $batas = 0, int $mulai = 0): array
{
    [$where, $p] = siswa_publik_wheres($kelasId, $cari);
    $lim = $batas > 0 ? ' LIMIT ' . (int) $batas . ' OFFSET ' . (int) $mulai : '';
    return all("SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
                $where ORDER BY (s.status <> 'aktif'), k.urutan, s.nama" . $lim, $p);
}

/** Alumni (siswa berstatus lulus), dapat disaring per tahun keluar. */
function alumni_publik_wheres(int $tahun = 0, string $cari = ''): array
{
    $w = ["s.status = 'lulus'"];
    $p = [];
    if ($tahun > 0) {
        $w[] = 'CAST(s.tahun_lulus AS INTEGER) = ?';
        $p[] = $tahun;
    }
    if (trim($cari) !== '') {
        $w[] = '(s.nama LIKE ? OR s.nisn LIKE ?)';
        $like = '%' . trim($cari) . '%';
        array_push($p, $like, $like);
    }
    return ['WHERE ' . implode(' AND ', $w), $p];
}

function alumni_publik_jumlah(int $tahun = 0, string $cari = ''): int
{
    [$where, $p] = alumni_publik_wheres($tahun, $cari);
    return jumlah('SELECT COUNT(*) FROM siswa s ' . $where, $p);
}

function alumni_publik(int $tahun = 0, string $cari = '', int $batas = 0, int $mulai = 0): array
{
    [$where, $p] = alumni_publik_wheres($tahun, $cari);
    $lim = $batas > 0 ? ' LIMIT ' . (int) $batas . ' OFFSET ' . (int) $mulai : '';
    return all("SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
                $where ORDER BY CAST(s.tahun_lulus AS INTEGER) DESC, s.nama" . $lim, $p);
}

/** Daftar tahun keluar yang benar-benar ada datanya (untuk penyaring alumni). */
function alumni_tahun(): array
{
    return array_values(array_filter(array_map(
        static fn (array $r) => (string) $r['tahun_lulus'],
        all("SELECT DISTINCT tahun_lulus FROM siswa WHERE status = 'lulus' AND tahun_lulus <> '' ORDER BY CAST(tahun_lulus AS INTEGER) DESC")
    ), static fn (string $t): bool => $t !== ''));
}

/** Label status untuk pengunjung: Aktif / Tidak Aktif. */
function status_siswa_label(string $status): string
{
    return $status === 'aktif' ? 'Aktif' : 'Tidak Aktif';
}

/** Kelas status (warna pil) untuk pengunjung. */
function status_siswa_kelas(string $status): string
{
    return $status === 'aktif' ? 'pil pil-terima' : 'pil pil-baru';
}

/** Tahun dari sebuah tanggal (untuk "tahun masuk"). */
function tahun_dari(?string $tanggal): string
{
    $t = trim((string) $tanggal);
    if ($t === '' || $t === '0000-00-00') {
        return '';
    }
    $ts = strtotime($t);
    return $ts ? date('Y', $ts) : (string) substr($t, 0, 4);
}

// =====================================================================================
// POLLING PENDAPAT (beranda)
// =====================================================================================
/** Polling yang sedang aktif (pertanyaan + pilihan + jumlah suara). */
function poll_aktif(bool $hanyaAktif = true): ?array
{
    $w = $hanyaAktif ? 'WHERE aktif = 1' : '';
    $poll = one('SELECT * FROM poll ' . $w . ' ORDER BY id DESC LIMIT 1');
    if (!$poll) {
        return null;
    }
    $p = all('SELECT * FROM poll_pilihan WHERE poll_id = ?' . ($hanyaAktif ? ' AND aktif = 1' : '') . ' ORDER BY urutan, id',
        [(int) $poll['id']]);
    if (!$p) {
        return null;
    }
    $hitung = [];
    foreach (all('SELECT pilihan_id, COUNT(*) AS jml FROM poll_suara WHERE poll_id = ? GROUP BY pilihan_id', [(int) $poll['id']]) as $r) {
        $hitung[(int) $r['pilihan_id']] = (int) $r['jml'];
    }
    $total = array_sum($hitung);
    foreach ($p as $i => $r) {
        $jml = $hitung[(int) $r['id']] ?? 0;
        $p[$i]['suara'] = $jml;
        $p[$i]['persen'] = $total > 0 ? round($jml / $total * 100, 1) : 0.0;
    }
    $poll['pilihan'] = $p;
    $poll['total'] = $total;
    return $poll;
}

/** Penanda sesi untuk mencegah satu pengunjung memilih berkali-kali. */
function poll_token(int $pollId): string
{
    if (empty($_SESSION['poll_token'])) {
        $_SESSION['poll_token'] = bin2hex(random_bytes(8));
    }
    return substr(hash('sha256', $_SESSION['poll_token'] . '|' . $pollId), 0, 32);
}

function poll_sudah_memilih(int $pollId): bool
{
    if (!empty($_SESSION['poll_pilih'][$pollId])) {
        return true;
    }
    return jumlah('SELECT COUNT(*) FROM poll_suara WHERE poll_id = ? AND token = ?', [$pollId, poll_token($pollId)]) > 0;
}

/** Simpan satu suara (mengembalikan pesan galat bila tidak sah). */
function poll_suara_simpan(int $pollId, int $pilihanId): string
{
    $poll = one('SELECT * FROM poll WHERE id = ? AND aktif = 1', [$pollId]);
    if (!$poll) {
        return 'Polling tidak ditemukan atau sudah ditutup.';
    }
    $pili = one('SELECT * FROM poll_pilihan WHERE id = ? AND poll_id = ? AND aktif = 1', [$pilihanId, $pollId]);
    if (!$pili) {
        return 'Pilihan jawaban tidak dikenali.';
    }
    if (poll_sudah_memilih($pollId)) {
        return 'Anda sudah memberikan pendapat pada polling ini. Terima kasih!';
    }
    q('INSERT INTO poll_suara (poll_id, pilihan_id, waktu, ip, token) VALUES (?, ?, ?, ?, ?)', [
        $pollId, $pilihanId, date('Y-m-d H:i:s'), ip_pengunjung(), poll_token($pollId),
    ]);
    $_SESSION['poll_pilih'][$pollId] = $pilihanId;
    return '';
}

/** Kelas warna batang hasil (emas untuk teratas). */
function poll_kelas_teratas(array $poll, int $index): string
{
    $tertinggi = 0;
    foreach ($poll['pilihan'] as $p) {
        $tertinggi = max($tertinggi, (int) $p['suara']);
    }
    return ($tertinggi > 0 && (int) $poll['pilihan'][$index]['suara'] === $tertinggi) ? ' teratas' : '';
}

// =====================================================================================
// STATISTIK
// =====================================================================================
function statistik(): array
{
    $guru   = jumlah("SELECT COUNT(*) FROM guru WHERE status = 'aktif'");
    $siswa  = jumlah("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'");
    // Alumni = data kelulusan + tabel `alumni` (manual/impor), sama dengan halaman Data Alumni;
    // angka manual pada Pengaturan tetap dapat MEMPERBESAR nilai ini bila lulusan lama belum terdata.
    $alumni = alumni_jumlah_gabungan();
    $alumniManual = (int) pengaturan('alumni_manual', '0');
    if ($alumniManual > $alumni) {
        $alumni = $alumniManual;
    }
    $gel = gelombang_aktif();
    $pendaftar = $gel
        ? jumlah('SELECT COUNT(*) FROM ppdb_pendaftar WHERE gelombang_id = ?', [(int) $gel['id']])
        : jumlah('SELECT COUNT(*) FROM ppdb_pendaftar');
    return [
        'guru'      => $guru,
        'siswa'     => $siswa,
        'alumni'    => $alumni,
        'pendaftar' => $pendaftar,
    ];
}

/** Angka → teks berkelompok (1.234). */
function angka($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

// =====================================================================================
// PPDB
// =====================================================================================
function gelombang_aktif(): ?array
{
    $hari = date('Y-m-d');
    $g = one("SELECT * FROM ppdb_gelombang WHERE aktif = 1 AND (mulai IS NULL OR mulai = '' OR mulai <= ?)
              AND (selesai IS NULL OR selesai = '' OR selesai >= ?) ORDER BY urutan, id LIMIT 1", [$hari, $hari]);
    if ($g) {
        return $g;
    }
    return one('SELECT * FROM ppdb_gelombang WHERE aktif = 1 ORDER BY urutan, id LIMIT 1');
}

/**
 * Pisahkan daftar teks (satu per baris) menjadi butir — dipakai pengaturan PPDB (syarat, alur,
 * jadwal, jalur) dan asisten obrolan. (Dulu hanya ada di ppdb.php sehingga tidak dapat dipakai
 * modul lain; sekarang dipusatkan di sini.)
 *
 * @return array<int,string>
 */
function ppdb_butir(string $teks): array
{
    $baris = preg_split('#\r\n|\r|\n#', trim($teks));
    return array_values(array_filter(array_map('trim', (array) $baris), static fn (string $b): bool => $b !== ''));
}

function ppdb_dibuka(): bool
{
    return pengaturan('ppdb_buka', '0') === '1';
}

/** Pesan yang ditampilkan saat pendaftaran PPDB ditutup (halaman publik & popup). */
function ppdb_tutup_pesan(): string
{
    return 'Mohon maaf pendaftaran murid baru belum dibuka.';
}

/**
 * Atribut penanda pada TAUTAN menuju formulir PPDB saat pendaftaran ditutup.
 * Dipakai `assets/app.js` (initPpdbTutup) untuk menampilkan pesan "belum dibuka" saat
 * tombol "Daftar Sekarang" diklik; tanpa JavaScript tautannya tetap membuka `ppdb.php`
 * yang menampilkan pesan yang sama.
 */
function ppdb_tautan_atribut(string $tautan): string
{
    if (ppdb_dibuka() || trim($tautan) === '') {
        return '';
    }
    // Alamat rapi formulir PPDB adalah `ppdb` (dulu `ppdb.php`); `ppdb/status` TIDAK ikut ditandai
    // karena halaman pengecekan status harus tetap dapat dibuka walau pendaftaran sedang ditutup.
    // ⚠️ Tanda `#` di dalam pola WAJIB di-escape (`\#`) karena `#` dipakai sebagai pembatas pola —
    // tanpa itu PHP menganggap polanya berakhir lebih awal (muncul peringatan "Unknown modifier").
    return preg_match('#^ppdb(\.php)?([?\#]|$)#', trim($tautan)) ? ' data-ppdb-tutup="1"' : '';
}

function ppdb_status_label(string $s): string
{
    $peta = [
        'baru'       => 'Baru',
        'verifikasi' => 'Sedang Diverifikasi',
        'diterima'   => 'Diterima',
        'cadangan'   => 'Cadangan',
        'ditolak'    => 'Tidak Diterima',
        'batal'      => 'Dibatalkan',
    ];
    return $peta[$s] ?? 'Baru';
}

function ppdb_status_kelas(string $s): string
{
    $peta = [
        'baru'       => 'pil pil-baru',
        'verifikasi' => 'pil pil-proses',
        'diterima'   => 'pil pil-terima',
        'cadangan'   => 'pil pil-cadangan',
        'ditolak'    => 'pil pil-tolak',
        'batal'      => 'pil pil-batal',
    ];
    return $peta[$s] ?? 'pil pil-baru';
}

function ppdb_nomor_baru(int $gelombangId): string
{
    $th = date('Y');
    $urut = (int) jumlah('SELECT COUNT(*) FROM ppdb_pendaftar') + 1;
    do {
        $nomor = PPDB_PREFIX . '-' . $th . '-' . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
        $ada = jumlah('SELECT COUNT(*) FROM ppdb_pendaftar WHERE nomor = ?', [$nomor]);
        $urut++;
    } while ($ada > 0);
    return $nomor;
}

// =====================================================================================
// BERITA & AGENDA
// =====================================================================================
/** Guru & tenaga kependidikan aktif (untuk slider guru pada beranda). */
function guru_publik(int $batas = 12): array
{
    return all("SELECT * FROM guru WHERE status = 'aktif' ORDER BY urutan, nama LIMIT $batas");
}

/** Foto kepala madrasah/sekolah (kosong bila belum diunggah). */
function foto_kepala(): string
{
    return sek('kepala_foto');
}

function berita_terbaru(int $batas = 3, ?int $kategoriId = null): array
{
    if ($kategoriId) {
        return all("SELECT b.*, k.nama AS kategori FROM berita b LEFT JOIN berita_kategori k ON k.id = b.kategori_id
                    WHERE b.terbit = 1 AND b.kategori_id = ? ORDER BY b.tanggal DESC, b.id DESC LIMIT $batas", [$kategoriId]);
    }
    return all("SELECT b.*, k.nama AS kategori FROM berita b LEFT JOIN berita_kategori k ON k.id = b.kategori_id
                WHERE b.terbit = 1 ORDER BY b.tanggal DESC, b.id DESC LIMIT $batas");
}

function agenda_terdekat(int $batas = 5): array
{
    return all("SELECT * FROM agenda WHERE aktif = 1 AND (tanggal IS NULL OR tanggal = '' OR tanggal >= date('now','localtime'))
                ORDER BY tanggal, id LIMIT $batas");
}

// =====================================================================================
// HALAMAN / PAGINASI
// =====================================================================================
function halaman_ini(): int
{
    return max(1, (int) ($_GET['hal'] ?? 1));
}

/**
 * Tautan halaman (paginasi) pada ALAMAT RAPI halaman yang sedang dibuka.
 *
 * Parameter `slug` dibuang dari query karena slug sudah menjadi bagian jalur
 * (mis. `halaman/data-siswa?hal=2`).
 *
 * 🚨 **PENTING — panel admin (`/admin/…`)**: pada alamat berprefiks (aplikasi dilayani di
 * `/web-sekolah/`), `rute_kanonik()` mengembalikan jalur **`admin/alumni.php`**. Tautan relatif
 * seperti itu dibaca peramban DARI DALAM folder `/admin/` sehingga menjadi
 * `/web-sekolah/admin/admin/alumni.php` → **404** (gejala yang dilaporkan pemilik: "tombol
 * halaman 2, 3, 4 dan Selanjutnya tidak berfungsi ketika diklik"). Karena itu, bila berkas yang
 * sedang dijalankan memang berada di folder `admin`, tautannya dibangun dari **nama berkasnya saja**
 * (`alumni.php?hal=2`) — benar pada alamat berprefiks maupun pada domain sendiri.
 */
function hal_url(int $hal): string
{
    $q = $_GET;
    unset($q['slug']);
    $q['hal'] = $hal;
    $skrip = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $folder = rtrim(str_replace('\\', '/', dirname($skrip)), '/');
    $diAdmin = (bool) preg_match('#(^|/)admin$#', $folder)
        || str_starts_with(ltrim((string) rute_kanonik(), '/'), 'admin/');
    if ($diAdmin) {
        return rute_tautan(basename($skrip) !== '' ? basename($skrip) : 'index.php', $q);
    }
    return rute_tautan(rute_kanonik(), $q);
}

/**
 * Banyaknya halaman untuk sejumlah baris (minimal 1).
 */
function paginasi_jumlah(int $total, int $perHal): int
{
    return max(1, (int) ceil($total / max(1, $perHal)));
}

/**
 * Nomor halaman yang diminta, DIJEPIT ke rentang halaman yang benar-benar ada.
 *
 * Dipakai daftar publik (inventaris barang, laporan perpustakaan, daftar penerima PIP) supaya
 * `?hal=999` menampilkan halaman TERAKHIR — bukan tabel kosong yang membingungkan. Sumbernya
 * `halaman_ini()` (`$_GET['hal']`), atau nomor halaman yang diberikan langsung.
 */
function paginasi_halaman(int $total, int $perHal, ?int $hal = null): int
{
    $hal = $hal === null ? halaman_ini() : max(1, $hal);
    return min($hal, paginasi_jumlah($total, $perHal));
}

/** Potongan baris untuk satu halaman (array kosong bila halamannya di luar rentang). */
function paginasi_potong(array $baris, int $hal, int $perHal): array
{
    $perHal = max(1, $perHal);
    return array_slice($baris, (max(1, $hal) - 1) * $perHal, $perHal);
}

/**
 * Keterangan rentang baris yang sedang tampil, mis. "Menampilkan 1–10 dari 68 data".
 * Kosong bila tidak ada baris (keadaan kosong sudah dijelaskan blok tersendiri).
 */
function paginasi_info(int $total, int $perHal, int $hal, string $satuan = 'data'): string
{
    if ($total <= 0) {
        return '';
    }
    $perHal = max(1, $perHal);
    $mulai = (max(1, $hal) - 1) * $perHal + 1;
    $akhir = min($total, max(1, $hal) * $perHal);
    return 'Menampilkan ' . angka($mulai) . '–' . angka($akhir) . ' dari ' . angka($total) . ' ' . $satuan;
}

function paginasi(int $total, int $perHal, int $hal): string
{
    $jmlHal = (int) ceil($total / max(1, $perHal));
    if ($jmlHal <= 1) {
        return '';
    }
    $out = '<nav class="paginasi">';
    if ($hal > 1) {
        $out .= '<a href="' . e(hal_url($hal - 1)) . '" class="tombol tombol-garis">&laquo; Sebelumnya</a>';
    }
    $mulai = max(1, $hal - 2);
    $akhir = min($jmlHal, $mulai + 4);
    for ($i = $mulai; $i <= $akhir; $i++) {
        $out .= $i === $hal
            ? '<span class="hal aktif">' . $i . '</span>'
            : '<a class="hal" href="' . e(hal_url($i)) . '">' . $i . '</a>';
    }
    if ($hal < $jmlHal) {
        $out .= '<a href="' . e(hal_url($hal + 1)) . '" class="tombol tombol-garis">Selanjutnya &raquo;</a>';
    }
    return $out . '</nav>';
}
