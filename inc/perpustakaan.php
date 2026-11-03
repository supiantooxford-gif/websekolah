<?php
/**
 * INFORMASI PERPUSTAKAAN (menu Informasi → Perpustakaan) — EMPAT LAPORAN:
 *   1. Laporan Data Buku
 *   2. Laporan Pengunjung
 *   3. Laporan Peminjaman
 *   4. Laporan Pengembalian
 *
 * Seluruh datanya diambil OTOMATIS (baca-saja) dari basis data aplikasi **e-PERPUS**
 * (`https://supianto.vibecoder.co.id/perpustakaan`) di server yang sama — jadi setiap
 * penambahan/perubahan transaksi di e-PERPUS langsung ikut tampil di halaman ini tanpa perlu
 * disalin ulang atau disinkronkan.
 *
 * PENTING / JANGAN DIUBAH tanpa alasan kuat:
 *  1. Modul ini TIDAK memuat (require) kode aplikasi e-PERPUS — memuat `perpustakaan/inc/init.php`
 *     akan menabrakkan konstanta & fungsi aplikasi ini dan menjalankan sesi aplikasi lain. Seluruh
 *     pembacaan ditulis ulang di sini memakai koneksi TERPISAH yang hanya boleh membaca
 *     (`PRAGMA query_only = 1`), jadi data e-PERPUS tidak mungkin berubah dari halaman ini.
 *  2. Basis data e-PERPUS dibuka hanya bila berkasnya ada; bila tidak (aplikasi dipindah/belum
 *     dipasang/berkasnya tidak terbaca) halaman menampilkan keterangan ramah — BUKAN Fatal error.
 *  3. Tabel yang dibaca hanya `sekolah`, `buku`, `anggota`, `peminjaman`, dan `pengunjung`.
 *  4. Permintaan pemilik (v1.8.1): empat TOMBOL laporan di SATU halaman, laporannya tampil di
 *     BAWAH tombol (seperti tab), dengan SARINGAN TANGGAL Dari–Sampai, dan **TANPA tombol cetak**.
 */
declare(strict_types=1);

/**
 * Batas AMAN jumlah baris yang dibaca dari basis data e-PERPUS untuk satu laporan.
 *
 * Sejak v1.8.4 laporan **dipaginasi 10 baris per halaman** dengan tombol "Selanjutnya"
 * (permintaan pemilik), jadi angka ini bukan lagi batas tampil melainkan pengaman agar satu
 * permintaan tidak menarik data tak terbatas bila koleksi perpustakaan kelak sangat besar.
 */
const PERPUS_LAPORAN_MAKS_BARIS = 5000;

// =====================================================================================
// KONEKSI BACA-SAJA ke basis data e-PERPUS
// =====================================================================================

/** Berkas basis data e-PERPUS yang dipakai (urutan: env → pengaturan → konstanta → bawaan). */
function perpus_db_file(): string
{
    static $berkas = null;
    if ($berkas !== null) {
        return $berkas;
    }
    $kandidat = [];
    // (1) dapat diarahkan lewat variabel lingkungan (dipakai skrip uji pada salinan /tmp).
    $env = trim((string) getenv('WEBSK_PERPUS_DB'));
    if ($env !== '') {
        $kandidat[] = $env;
    }
    // (2) dapat diarahkan lewat tabel `pengaturan` (kunci `perpus_db`) tanpa menyunting kode.
    if (function_exists('pengaturan')) {
        $kustom = trim(pengaturan('perpus_db', ''));
        if ($kustom !== '') {
            $kandidat[] = $kustom;
        }
    }
    // (3) konstanta di inc/config.php.
    if (defined('PERPUS_DB_FILE')) {
        $kandidat[] = (string) PERPUS_DB_FILE;
    }
    // (4) bawaan: folder aplikasi e-PERPUS di sebelah folder aplikasi ini.
    $kandidat[] = dirname(__DIR__) . '/perpustakaan/data/perpustakaan.sqlite';

    foreach ($kandidat as $k) {
        if ($k !== '' && is_file($k) && is_readable($k)) {
            return $berkas = $k;
        }
    }
    return $berkas = '';
}

/**
 * Koneksi BACA-SAJA ke basis data e-PERPUS (null bila tidak tersedia).
 *
 * Cara 1: `mode=ro` (paling aman). Cara 2: koneksi biasa + `PRAGMA query_only` (dipakai bila basis
 * data ber-jurnal WAL dan berkas `-shm` belum ada sehingga `mode=ro` tidak dapat membukanya).
 * Keduanya TIDAK PERNAH menulis data e-PERPUS.
 */
function perpus_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo instanceof PDO ? $pdo : null;
    }
    $pdo = null;
    $berkas = perpus_db_file();
    if ($berkas === '') {
        return null;
    }
    $opsi = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    foreach (['sqlite:file:' . $berkas . '?mode=ro', 'sqlite:' . $berkas] as $dsn) {
        try {
            $coba = new PDO($dsn, null, null, $opsi);
            $coba->exec('PRAGMA query_only = 1');
            $coba->exec('PRAGMA busy_timeout = 4000');
            // Uji ringan: tabel inti harus ada.
            $coba->query('SELECT COUNT(*) FROM buku')->fetchColumn();
            $coba->query('SELECT COUNT(*) FROM peminjaman')->fetchColumn();
            $coba->query('SELECT COUNT(*) FROM pengunjung')->fetchColumn();
            $pdo = $coba;
            return $pdo;
        } catch (Throwable $e) {
            // coba cara berikutnya
        }
    }
    return null;
}

/** Apakah sumber data e-PERPUS tersedia? */
function perpus_siap(): bool
{
    return perpus_db() instanceof PDO;
}

/** Seluruh baris hasil query (array kosong bila sumber data tidak tersedia / query gagal). */
function perpus_all(string $sql, array $param = []): array
{
    $pdo = perpus_db();
    if (!$pdo) {
        return [];
    }
    try {
        $st = $pdo->prepare($sql);
        $st->execute($param);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Satu baris hasil query (null bila gagal). */
function perpus_one(string $sql, array $param = []): ?array
{
    $pdo = perpus_db();
    if (!$pdo) {
        return null;
    }
    try {
        $st = $pdo->prepare($sql);
        $st->execute($param);
        $r = $st->fetch();
        return $r === false ? null : $r;
    } catch (Throwable $e) {
        return null;
    }
}

/** Satu nilai hasil query. */
function perpus_nilai(string $sql, array $param = []): string
{
    $r = perpus_one($sql, $param);
    if (!$r) {
        return '';
    }
    $v = reset($r);
    return $v === null ? '' : (string) $v;
}

// =====================================================================================
// SEKOLAH / PERPUSTAKAAN PEMILIK DATA
// =====================================================================================

/** Nama untuk pencocokan (huruf kecil, hanya huruf/angka). */
function perpus_nama_kunci(string $teks): string
{
    $t = strtolower(trim($teks));
    $t = preg_replace('/[^a-z0-9]+/', '', $t) ?? '';
    return $t;
}

/** Baris `sekolah` pada basis data e-PERPUS (identitas perpustakaan). Di-cache per permintaan. */
function perpus_sekolah(): ?array
{
    static $s = null;
    if ($s !== null) {
        return $s === false ? null : $s;
    }
    $s = false;
    if (!perpus_siap()) {
        return null;
    }
    // Aplikasi e-PERPUS melayani SATU sekolah/madrasah; baris pertama sudah cukup. Bila namanya
    // cocok dengan nama sekolah pada website ini, baris itulah yang dipakai (pencocokan NAMA).
    $semua = perpus_all('SELECT * FROM sekolah ORDER BY id');
    if (!$semua) {
        return null;
    }
    $nama = function_exists('sekolah') ? trim((string) (sekolah()['nama'] ?? '')) : '';
    if ($nama !== '') {
        $kunci = perpus_nama_kunci($nama);
        foreach ($semua as $r) {
            if ($kunci !== '' && perpus_nama_kunci((string) ($r['nama'] ?? '')) === $kunci) {
                return $s = $r;
            }
        }
    }
    if (defined('PERPUS_SEKOLAH_ID')) {
        foreach ($semua as $r) {
            if ((int) $r['id'] === (int) PERPUS_SEKOLAH_ID) {
                return $s = $r;
            }
        }
    }
    return $s = $semua[0];
}

/** Nama perpustakaan yang ditampilkan (nama perpustakaan e-PERPUS, dicadangkan ke nama sekolah). */
function perpus_nama(): string
{
    $s = perpus_sekolah();
    $namaPerpus = trim((string) ($s['nama_perpus'] ?? ''));
    if ($namaPerpus !== '') {
        return $namaPerpus;
    }
    $namaSekolah = trim((string) ($s['nama'] ?? ''));
    return $namaSekolah !== '' ? $namaSekolah : 'Perpustakaan';
}

/** Tanggal Indonesia (dipakai bila aplikasi ini belum memuat penolongnya). */
function perpus_tanggal_id(?string $ymd): string
{
    $ymd = trim((string) $ymd);
    if ($ymd === '') {
        return '-';
    }
    if (function_exists('tanggal_id')) {
        $t = tanggal_id($ymd);
        return $t !== '' ? $t : '-';
    }
    $ts = strtotime($ymd);
    return $ts === false ? $ymd : date('j/n/Y', $ts);
}

/** Nama hari Indonesia. */
function perpus_hari_id(?string $ymd): string
{
    $ymd = trim((string) $ymd);
    if ($ymd === '') {
        return '-';
    }
    $ts = strtotime($ymd);
    if ($ts === false) {
        return '-';
    }
    if (defined('HARI_ID')) {
        return HARI_ID[date('l', $ts)] ?? '-';
    }
    $h = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
          'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
    return $h[date('l', $ts)] ?? '-';
}

/** Nilai sel: kosong ditampilkan sebagai '-' (mengikuti tampilan e-PERPUS). */
function perpus_sel($v): string
{
    $t = trim((string) $v);
    return $t !== '' && $t !== '0' ? $t : ($t === '0' ? '0' : '-');
}

// =====================================================================================
// SARINGAN TANGGAL (Dari – Sampai)
// =====================================================================================

/** Tanggal `YYYY-MM-DD` yang sah, selain itu string kosong. */
function perpus_tgl_ok($v): string
{
    $v = trim((string) $v);
    if ($v === '') {
        return '';
    }
    // Terima juga tulisan `d/m/Y` atau `d-m-Y` agar tautan lama tetap bekerja.
    if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $v, $m)) {
        $v = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    if (!preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $v, $m)) {
        return '';
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $v : '';
}

/** Saringan tanggal dari query string; rentang terbalik ditukar sendiri. */
function perpus_filter(): array
{
    $dari = perpus_tgl_ok($_GET['dari'] ?? '');
    $sampai = perpus_tgl_ok($_GET['sampai'] ?? '');
    if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
        [$dari, $sampai] = [$sampai, $dari];
    }
    return ['dari' => $dari, 'sampai' => $sampai];
}

/** Label periode yang sedang dilihat (untuk keterangan kepala laporan). */
function perpus_periode_label(array $f): string
{
    if (($f['dari'] ?? '') !== '' && ($f['sampai'] ?? '') !== '') {
        return perpus_tanggal_id($f['dari']) . ' s.d. ' . perpus_tanggal_id($f['sampai']);
    }
    if (($f['dari'] ?? '') !== '') {
        return 'Sejak ' . perpus_tanggal_id($f['dari']);
    }
    if (($f['sampai'] ?? '') !== '') {
        return 'Sampai ' . perpus_tanggal_id($f['sampai']);
    }
    return 'Seluruh data yang tercatat';
}

/** Tautan halaman ini + saringan (RELATIF — wajib, aplikasi dilayani pada subfolder). */
function perpus_tautan(array $q = []): string
{
    $kanonik = function_exists('rute_kanonik') ? rute_kanonik() : '';
    if (function_exists('rute_tautan')) {
        return rute_tautan($kanonik !== '' ? $kanonik : 'halaman/perpustakaan', $q);
    }
    $q = array_filter($q, static fn ($v): bool => (string) $v !== '');
    $t = $kanonik !== '' ? $kanonik : 'halaman/perpustakaan';
    return $q ? $t . '?' . http_build_query($q) : $t;
}

// =====================================================================================
// EMPAT JENIS LAPORAN
// =====================================================================================

/** Daftar laporan yang tersedia — URUTAN tombol mengikuti permintaan pemilik. */
function perpus_laporan_jenis(): array
{
    return [
        'buku'         => ['label' => 'Laporan Data Buku', 'ikon' => 'book',  'tentang' => 'Koleksi buku perpustakaan'],
        'pengunjung'   => ['label' => 'Laporan Pengunjung', 'ikon' => 'users', 'tentang' => 'Kunjungan ke perpustakaan'],
        'peminjaman'   => ['label' => 'Laporan Peminjaman', 'ikon' => 'unduh', 'tentang' => 'Transaksi peminjaman buku'],
        'pengembalian' => ['label' => 'Laporan Pengembalian', 'ikon' => 'unggah', 'tentang' => 'Buku yang sudah dikembalikan'],
    ];
}

/** Jenis laporan dari alamat (nilai tak dikenal jatuh ke "Data Buku", bukan 403). */
function perpus_laporan_kini(): string
{
    $lap = trim((string) ($_GET['lap'] ?? ''));
    return array_key_exists($lap, perpus_laporan_jenis()) ? $lap : 'buku';
}

/** Jumlah hari keterlambatan sebuah transaksi (0 bila belum/tepat waktu). */
function perpus_hari_terlambat(array $p): int
{
    $akhir = ((string) ($p['status'] ?? '')) === 'kembali'
        ? (string) ($p['tanggal_kembali'] ?? '')
        : date('Y-m-d');
    if ($akhir === '' || $akhir === '-') {
        return 0;
    }
    $jt = strtotime((string) ($p['jatuh_tempo'] ?? ''));
    $ak = strtotime($akhir);
    if ($jt === false || $ak === false || $ak <= $jt) {
        return 0;
    }
    return (int) floor(($ak - $jt) / 86400);
}

/**
 * Data satu laporan. Struktur seragam:
 *   judul, kunci, tentang, kolom[], align[], rows[], ringkas[], kosong, catatan
 */
function perpus_laporan(string $lap, array $f): array
{
    switch ($lap) {
        case 'pengunjung':
            return perpus_lap_pengunjung($f);
        case 'peminjaman':
            return perpus_lap_peminjaman($f);
        case 'pengembalian':
            return perpus_lap_pengembalian($f);
        case 'buku':
        default:
            return perpus_lap_buku($f);
    }
}

/** 1. LAPORAN DATA BUKU — seluruh koleksi + jumlah yang sedang dipinjam/tersedia. */
function perpus_lap_buku(array $f): array
{
    $w = [];
    $p = [];
    if (($f['dari'] ?? '') !== '') {
        $w[] = 'date(b.dibuat) >= ?';
        $p[] = $f['dari'];
    }
    if (($f['sampai'] ?? '') !== '') {
        $w[] = 'date(b.dibuat) <= ?';
        $p[] = $f['sampai'];
    }
    $rows = perpus_all('SELECT b.*,
                (SELECT COUNT(*) FROM peminjaman p WHERE p.buku_id = b.id AND p.status = \'dipinjam\') AS dipinjam
            FROM buku b'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY b.judul COLLATE NOCASE LIMIT ' . PERPUS_LAPORAN_MAKS_BARIS, $p);

    $keluar = [];
    $eksemplar = 0;
    $dipinjam = 0;
    foreach ($rows as $i => $r) {
        $tot = (int) ($r['total_buku'] ?? 0);
        $dip = (int) ($r['dipinjam'] ?? 0);
        $eksemplar += $tot;
        $dipinjam += $dip;
        $keluar[] = [
            $i + 1,
            perpus_sel($r['kode'] ?? ''),
            (string) ($r['judul'] ?? ''),
            perpus_sel($r['pengarang'] ?? ''),
            perpus_sel($r['penerbit'] ?? ''),
            perpus_sel($r['tahun_terbit'] ?? ''),
            perpus_sel($r['kategori'] ?? ''),
            (string) $tot,
            (string) $dip,
            (string) max(0, $tot - $dip),
            ((int) ($r['aktif'] ?? 0) === 1 ? 'Aktif' : 'Nonaktif'),
        ];
    }

    return [
        'judul'    => 'Laporan Data Buku',
        'kunci'    => 'buku',
        'tentang'  => 'Seluruh koleksi buku perpustakaan beserta jumlah eksemplar yang dipinjam dan tersedia.',
        'kolom'    => ['No', 'Kode', 'Judul Buku', 'Pengarang', 'Penerbit', 'Tahun', 'Kategori', 'Total', 'Dipinjam', 'Tersedia', 'Status'],
        'align'    => ['c', 'l', 'l', 'l', 'l', 'c', 'c', 'c', 'c', 'c', 'c'],
        'rows'     => $keluar,
        'ringkas'  => [
            'Judul buku'      => count($rows),
            'Total eksemplar' => $eksemplar,
            'Sedang dipinjam' => $dipinjam,
            'Tersedia di rak' => max(0, $eksemplar - $dipinjam),
        ],
        'catatan'  => ((($f['dari'] ?? '') !== '' || ($f['sampai'] ?? '') !== '')
            ? 'Saringan tanggal di atas menyaring buku menurut tanggal buku dicatat di e-PERPUS.'
            : ''),
        'kosong'   => 'Belum ada data buku pada perpustakaan ini.',
    ];
}

/** 2. LAPORAN PENGUNJUNG — kunjungan per hari (buku tamu e-PERPUS). */
function perpus_lap_pengunjung(array $f): array
{
    $w = [];
    $p = [];
    if (($f['dari'] ?? '') !== '') {
        $w[] = 'g.waktu >= ?';
        $p[] = $f['dari'] . ' 00:00:00';
    }
    if (($f['sampai'] ?? '') !== '') {
        $w[] = 'g.waktu <= ?';
        $p[] = $f['sampai'] . ' 23:59:59';
    }
    $rows = perpus_all('SELECT g.* FROM pengunjung g'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY g.waktu DESC LIMIT ' . PERPUS_LAPORAN_MAKS_BARIS, $p);

    $keluar = [];
    $nama = [];
    $hari = [];
    $perKeperluan = [];
    foreach ($rows as $i => $r) {
        $tgl = substr((string) ($r['waktu'] ?? ''), 0, 10);
        $ts = strtotime((string) ($r['waktu'] ?? ''));
        $nama[strtolower(trim((string) ($r['nama'] ?? '')))] = true;
        $hari[$tgl] = true;
        $kep = trim((string) ($r['keperluan'] ?? ''));
        $kep = $kep !== '' ? $kep : 'Tidak dicatat';
        $perKeperluan[$kep] = ($perKeperluan[$kep] ?? 0) + 1;
        $keluar[] = [
            $i + 1,
            perpus_hari_id($tgl),
            perpus_tanggal_id($tgl),
            $ts !== false ? date('H:i', $ts) : '-',
            (string) ($r['nama'] ?? ''),
            strtoupper(trim((string) ($r['jenis_kelamin'] ?? ''))) === 'P' ? 'Perempuan'
                : (strtoupper(trim((string) ($r['jenis_kelamin'] ?? ''))) === 'L' ? 'Laki-laki' : '-'),
            perpus_sel($r['kelas'] ?? ''),
            $kep,
            perpus_sel($r['saran'] ?? ''),
        ];
    }
    arsort($perKeperluan);
    $terbanyak = $perKeperluan ? (string) array_key_first($perKeperluan) : '-';
    $jmlHari = count($hari);

    return [
        'judul'   => 'Laporan Pengunjung',
        'kunci'   => 'pengunjung',
        'tentang' => 'Catatan kunjungan (buku tamu) perpustakaan — siapa, kapan, dan keperluannya.',
        'kolom'   => ['No', 'Hari', 'Tanggal', 'Jam', 'Nama Pengunjung', 'Jenis Kelamin', 'Kelas', 'Keperluan', 'Saran'],
        'align'   => ['c', 'l', 'l', 'c', 'l', 'c', 'c', 'l', 'l'],
        'rows'    => $keluar,
        'ringkas' => [
            'Jumlah kunjungan'       => count($rows),
            'Pengunjung (nama unik)' => count($nama),
            'Hari tercatat'          => $jmlHari,
            'Rata-rata per hari'     => $jmlHari > 0 ? (string) round(count($rows) / $jmlHari, 1) : '0',
            'Keperluan terbanyak'    => $terbanyak,
        ],
        'catatan' => '',
        'kosong'  => 'Belum ada kunjungan yang tercatat pada saringan/periode ini.',
    ];
}

/** 3. LAPORAN PEMINJAMAN — seluruh transaksi peminjaman (masih dipinjam & sudah kembali). */
function perpus_lap_peminjaman(array $f): array
{
    $w = [];
    $p = [];
    if (($f['dari'] ?? '') !== '') {
        $w[] = 'p.tanggal_pinjam >= ?';
        $p[] = $f['dari'];
    }
    if (($f['sampai'] ?? '') !== '') {
        $w[] = 'p.tanggal_pinjam <= ?';
        $p[] = $f['sampai'];
    }
    $rows = perpus_all('SELECT p.*, a.nama AS anggota_nama, a.kelas AS anggota_kelas,
                a.kode AS anggota_kode, b.judul AS buku_judul
            FROM peminjaman p
            JOIN anggota a ON a.id = p.anggota_id
            JOIN buku b ON b.id = p.buku_id'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY p.tanggal_pinjam DESC, p.id DESC LIMIT ' . PERPUS_LAPORAN_MAKS_BARIS, $p);

    $keluar = [];
    $aktif = 0;
    $kembali = 0;
    $telat = 0;
    foreach ($rows as $i => $r) {
        $ht = perpus_hari_terlambat($r);
        if (((string) ($r['status'] ?? '')) === 'dipinjam') {
            $aktif++;
        } else {
            $kembali++;
        }
        if ($ht > 0) {
            $telat++;
        }
        $keluar[] = [
            $i + 1,
            perpus_sel($r['kode'] ?? ''),
            perpus_tanggal_id($r['tanggal_pinjam'] ?? ''),
            (string) ($r['anggota_nama'] ?? ''),
            perpus_sel($r['anggota_kelas'] ?? ''),
            (string) ($r['buku_judul'] ?? ''),
            perpus_tanggal_id($r['jatuh_tempo'] ?? ''),
            ((string) ($r['status'] ?? '')) === 'dipinjam' ? 'Masih Dipinjam' : 'Sudah Kembali',
            $ht > 0 ? $ht . ' hari' : '-',
            perpus_sel($r['petugas'] ?? ''),
        ];
    }

    return [
        'judul'   => 'Laporan Peminjaman',
        'kunci'   => 'peminjaman',
        'tentang' => 'Seluruh transaksi peminjaman buku — termasuk yang masih dipinjam dan yang sudah kembali.',
        'kolom'   => ['No', 'Kode', 'Tgl Pinjam', 'Anggota', 'Kelas', 'Judul Buku', 'Jatuh Tempo', 'Status', 'Terlambat', 'Petugas'],
        'align'   => ['c', 'l', 'c', 'l', 'c', 'l', 'c', 'c', 'c', 'l'],
        'rows'    => $keluar,
        'ringkas' => [
            'Jumlah transaksi' => count($rows),
            'Masih dipinjam'   => $aktif,
            'Sudah kembali'    => $kembali,
            'Terlambat'        => $telat,
        ],
        'catatan' => '',
        'kosong'  => 'Belum ada transaksi peminjaman pada saringan/periode ini.',
    ];
}

/** 4. LAPORAN PENGEMBALIAN — buku yang sudah dikembalikan (beserta denda & kondisinya). */
function perpus_lap_pengembalian(array $f): array
{
    $w = ["p.status = 'kembali'"];
    $p = [];
    if (($f['dari'] ?? '') !== '') {
        $w[] = 'p.tanggal_kembali >= ?';
        $p[] = $f['dari'];
    }
    if (($f['sampai'] ?? '') !== '') {
        $w[] = 'p.tanggal_kembali <= ?';
        $p[] = $f['sampai'];
    }
    $rows = perpus_all('SELECT p.*, a.nama AS anggota_nama, a.kelas AS anggota_kelas, b.judul AS buku_judul
            FROM peminjaman p
            JOIN anggota a ON a.id = p.anggota_id
            JOIN buku b ON b.id = p.buku_id
            WHERE ' . implode(' AND ', $w) . '
            ORDER BY p.tanggal_kembali DESC, p.id DESC
            LIMIT ' . PERPUS_LAPORAN_MAKS_BARIS, $p);

    $keluar = [];
    $totalDenda = 0;
    $nTelat = 0;
    foreach ($rows as $i => $r) {
        $ht = perpus_hari_terlambat($r);
        $denda = (int) ($r['denda'] ?? 0) + (int) ($r['denda_lain'] ?? 0);
        $totalDenda += $denda;
        if ($ht > 0) {
            $nTelat++;
        }
        $keluar[] = [
            $i + 1,
            perpus_sel($r['kode'] ?? ''),
            (string) ($r['anggota_nama'] ?? ''),
            perpus_sel($r['anggota_kelas'] ?? ''),
            (string) ($r['buku_judul'] ?? ''),
            perpus_tanggal_id($r['tanggal_pinjam'] ?? ''),
            perpus_tanggal_id($r['tanggal_kembali'] ?? ''),
            $ht > 0 ? $ht . ' hari' : '-',
            'Rp ' . number_format($denda, 0, ',', '.'),
            perpus_sel($r['kondisi'] ?? ''),
        ];
    }

    return [
        'judul'   => 'Laporan Pengembalian',
        'kunci'   => 'pengembalian',
        'tentang' => 'Daftar buku yang sudah dikembalikan, lengkap dengan keterlambatan, denda, dan kondisi buku.',
        'kolom'   => ['No', 'Kode', 'Anggota', 'Kelas', 'Judul Buku', 'Tgl Pinjam', 'Tgl Kembali', 'Terlambat', 'Denda', 'Kondisi'],
        'align'   => ['c', 'l', 'l', 'c', 'l', 'c', 'c', 'c', 'r', 'c'],
        'rows'    => $keluar,
        'ringkas' => [
            'Jumlah pengembalian' => count($rows),
            'Tepat waktu'         => max(0, count($rows) - $nTelat),
            'Terlambat'           => $nTelat,
            'Total denda'         => 'Rp ' . number_format($totalDenda, 0, ',', '.'),
        ],
        'catatan' => '',
        'kosong'  => 'Belum ada pengembalian buku pada saringan/periode ini.',
    ];
}

/** Ringkasan singkat isi perpustakaan (chip pada kepala halaman). */
function perpus_ringkas_pustaka(): array
{
    return [
        'judul_buku' => (int) perpus_nilai('SELECT COUNT(*) FROM buku WHERE aktif = 1'),
        'eksemplar'  => (int) perpus_nilai('SELECT COALESCE(SUM(total_buku), 0) FROM buku WHERE aktif = 1'),
        'anggota'    => (int) perpus_nilai('SELECT COUNT(*) FROM anggota WHERE aktif = 1'),
    ];
}

// =====================================================================================
// TAMPILAN: EMPAT TOMBOL LAPORAN + LAPORAN DI BAWAHNYA + SARINGAN TANGGAL
// =====================================================================================

/** Keterangan ramah bila sumber data e-PERPUS tidak dapat dibaca. */
function perpus_kosong_sumber(): void
{
    echo '<section class="per-blok">'
        . '<div class="per-kosong">' . ikon('book', 34)
        . '<h3>Data perpustakaan belum dapat ditampilkan</h3>'
        . '<p>Halaman ini menampilkan <b>Laporan Data Buku, Pengunjung, Peminjaman, dan Pengembalian</b>
             yang datanya diambil otomatis dari aplikasi <b>e-PERPUS</b>. Sumber datanya belum dapat
             dibaca saat ini (aplikasi e-PERPUS belum dipasang di server, atau berkas datanya tidak
             dapat diakses).</p>';
    if (defined('PERPUS_URL') && PERPUS_URL !== '') {
        echo '<p><a class="tombol tombol-garis tombol-kecil" href="' . e((string) PERPUS_URL) . '"
             target="_blank" rel="noopener noreferrer">' . ikon('globe', 15) . ' Buka aplikasi e-PERPUS</a></p>';
    }
    echo '</div></section>';
}

/**
 * Mencetak blok "Informasi Perpustakaan" — empat tombol laporan, saringan tanggal,
 * dan laporan yang dipilih di bawahnya (isi menu Informasi → Perpustakaan).
 */
function perpustakaan_papan(): void
{
    if (!perpus_siap()) {
        echo '<div class="per-papan" id="papanPerpustakaan">';
        perpus_kosong_sumber();
        echo '</div>';
        return;
    }

    $f = perpus_filter();
    $lap = perpus_laporan_kini();
    $jenis = perpus_laporan_jenis();
    $data = perpus_laporan($lap, $f);
    $ring = perpus_ringkas_pustaka();

    // HALAMAN DAFTAR (permintaan pemilik: "laporan data buku, laporan pengunjung, laporan
    // peminjaman, laporan pengembalian … ditampilkan 1-10 dan ditambahkan tombol selanjutnya").
    // Kotak ringkasan tetap dihitung dari SELURUH baris; hanya tabelnya yang dipotong per halaman.
    $perHal = PUBLIK_PER_HALAMAN;
    $totalBaris = count($data['rows']);
    $hal = paginasi_halaman($totalBaris, $perHal);
    $tampil = paginasi_potong($data['rows'], $hal, $perHal);
    // Nomor urut pada kolom "No" dilanjutkan dari halaman sebelumnya (kolom pertama tiap baris).
    foreach ($tampil as $i => $baris) {
        $tampil[$i][0] = (($hal - 1) * $perHal) + $i + 1;
    }

    // Saringan yang dibawa saat berpindah tombol laporan.
    $bawa = array_filter(['dari' => $f['dari'], 'sampai' => $f['sampai']], static fn ($v): bool => (string) $v !== '');
    ?>
<div class="per-papan" id="papanPerpustakaan">

  <section class="per-blok">
    <div class="per-kepala">
      <div>
        <h2><?= e(perpus_nama()) ?></h2>
        <p>Laporan perpustakaan — datanya diambil otomatis dari aplikasi <b>e-PERPUS</b>,
           jadi ikut terbarui setiap kali pengelola memperbarui data di sana.</p>
      </div>
      <div class="per-chip">
        <span><?= e(angka($ring['judul_buku'])) ?> judul buku</span>
        <span><?= e(angka($ring['eksemplar'])) ?> eksemplar</span>
        <span><?= e(angka($ring['anggota'])) ?> anggota</span>
      </div>
    </div>

    <?php /* ============ EMPAT TOMBOL LAPORAN (permintaan pemilik) ============ */ ?>
    <div class="per-tombol" role="tablist" aria-label="Pilih laporan perpustakaan">
      <?php foreach ($jenis as $kunci => $j): ?>
        <?php $aktif = ($kunci === $lap); ?>
        <a class="per-tombol-item<?= $aktif ? ' aktif' : '' ?>"
           href="<?= e(perpus_tautan(array_merge(['lap' => $kunci], $bawa))) ?>"
           role="tab"<?= $aktif ? ' aria-current="true"' : '' ?>
           id="tombolLap-<?= e($kunci) ?>">
          <span class="per-tombol-ikon"><?= ikon((string) $j['ikon'], 18) ?></span>
          <span class="per-tombol-teks">
            <b><?= e((string) $j['label']) ?></b>
            <small><?= e((string) $j['tentang']) ?></small>
          </span>
        </a>
      <?php endforeach; ?>
    </div>

    <?php /* ============ SARINGAN TANGGAL Dari–Sampai ============ */ ?>
    <form class="per-saring" method="get" action="<?= e(perpus_tautan()) ?>" id="formSaringPerpus">
      <input type="hidden" name="lap" value="<?= e($lap) ?>">
      <span class="per-saring-judul"><?= ikon('kalender', 16) ?> Saringan tanggal</span>
      <label for="perDari">Dari</label>
      <input type="date" id="perDari" name="dari" value="<?= e($f['dari']) ?>">
      <label for="perSampai">Sampai</label>
      <input type="date" id="perSampai" name="sampai" value="<?= e($f['sampai']) ?>">
      <button type="submit" class="tombol tombol-kecil"><?= ikon('search', 15) ?> Tampilkan</button>
      <?php if ($f['dari'] !== '' || $f['sampai'] !== ''): ?>
        <a class="tombol tombol-garis tombol-kecil" href="<?= e(perpus_tautan(['lap' => $lap])) ?>">Reset</a>
      <?php endif; ?>
      <span class="per-saring-periode"><?= e(perpus_periode_label($f)) ?></span>
    </form>

    <?php /* ============ LAPORAN YANG DIPILIH (tampil di bawah tombol) ============ */ ?>
    <div class="per-laporan" id="laporan-<?= e($data['kunci']) ?>">
      <div class="per-laporan-kepala">
        <div>
          <h3><?= e((string) $data['judul']) ?></h3>
          <p><?= e((string) $data['tentang']) ?></p>
        </div>
        <span class="per-laporan-periode"><?= e(perpus_periode_label($f)) ?></span>
      </div>

      <?php if ($data['ringkas']): ?>
        <div class="per-ringkas">
          <?php foreach ($data['ringkas'] as $label => $nilai): ?>
            <div class="per-ringkas-kotak">
              <span class="per-ringkas-label"><?= e((string) $label) ?></span>
              <span class="per-ringkas-nilai"><?= e(is_int($nilai) ? angka($nilai) : (string) $nilai) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!$totalBaris): ?>
        <div class="per-kosong per-kosong-kecil"><?= ikon((string) $jenis[$lap]['ikon'], 28) ?>
          <h3>Belum ada data</h3>
          <p><?= e((string) $data['kosong']) ?></p>
        </div>
      <?php else: ?>
        <div class="per-tabel-wrap">
          <table class="per-tabel">
            <thead>
              <tr>
                <?php foreach ($data['kolom'] as $ik => $kol): ?>
                  <th class="per-<?= e($data['align'][$ik] ?? 'l') ?>"><?= e((string) $kol) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tampil as $baris): ?>
                <tr>
                  <?php foreach (array_values($baris) as $i => $sel): ?>
                    <td class="per-<?= e($data['align'][$i] ?? 'l') ?>"><?= e((string) $sel) ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php
        // Keterangan rentang + tombol halaman (Selanjutnya / Sebelumnya).
        $info = paginasi_info($totalBaris, $perHal, $hal, 'baris');
        $nav = paginasi($totalBaris, $perHal, $hal);
        ?>
        <div class="per-halaman" id="halamanPerpustakaan">
          <p class="per-info-halaman"><?= e($info) ?> &middot; sumber: aplikasi e-PERPUS</p>
          <?= $nav ?>
        </div>
        <?php if (($data['catatan'] ?? '') !== ''): ?>
          <p class="per-catatan"><?= e((string) $data['catatan']) ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</div>
    <?php
}
