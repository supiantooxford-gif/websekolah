<?php
/**
 * TARIK DATA PENDAFTAR PPDB TAHUN LAMA — panel PPDB ma.stm.sch.id.
 *
 * Permintaan pemilik:
 *   "Pada menu siswa bagian pendaftar masukkan data pendaftar dari 2022 sampai 2026 yang diambil dari
 *    alamat https://ma.stm.sch.id/ppdb/panel_admin/verifikasi/thn/2022 … /2026. Untuk 2027 dan
 *    seterusnya diambil dari jumlah pendaftar ppdb online website. Data tampilkan filter per tahun.
 *    Data yang ditampilkan Nomer Pendaftaran, Nama Siswa, Asal Sekolah."
 *
 * Pilihan pemilik (JANGAN diubah tanpa bertanya):
 *   1. data 2022–2026 DITARIK dari panel lama memakai username & password yang diisi admin
 *      (**sandi TIDAK PERNAH disimpan** — hanya dipakai selama satu permintaan penarikan);
 *   2. hasil tarikan **DISIMPAN** di website (sekali tarik, dapat diperbarui kapan saja);
 *   3. tampil sebagai **tab "Pendaftar" pada menu Data Siswa** dengan **filter per tahun**.
 *
 * PENTING:
 *  • Panel lama melindungi halaman itu dengan login (tanpa kredensial hanya muncul halaman LOGIN),
 *    jadi penarikan WAJIB memakai akun panel yang sah.
 *  • Data tahun lama disimpan di tabel SENDIRI (`ppdb_lama`) — TIDAK dicampur ke `ppdb_pendaftar`
 *    (tabel itu untuk PPDB Online website ini, lengkap dengan status seleksi & pengumuman).
 *  • Penarikan ulang untuk satu tahun MENGGANTI data tahun itu saja (`ppdb_simpan_tahun()`);
 *    tahun lain tidak disentuh.
 */
declare(strict_types=1);

// =====================================================================================
// KONFIGURASI SUMBER
// =====================================================================================

/**
 * Alamat halaman verifikasi per tahun pada panel PPDB lama (format %d = tahun).
 *
 * Dapat diarahkan lewat variabel lingkungan **`WEBSK_PANEL_VERIFIKASI`** (dipakai skrip uji yang
 * menjalankan PANEL TIRUAN untuk menguji alur login + tarik secara nyata tanpa kredensial asli).
 */
function ppdb_panel_url(string $format = ''): string
{
    $pola = trim((string) getenv('WEBSK_PANEL_VERIFIKASI'));
    if ($pola === '') {
        $pola = PPDB_PANEL_VERIFIKASI;
    }
    return $format !== '' ? str_replace('%d', (string) $format, $pola) : $pola;
}

/** Tahun yang datanya DIAMBIL dari panel lama (permintaan pemilik: 2022–2026). */
function ppdb_tahun_panel(): array
{
    $mulai = (int) PPDB_PANEL_TAHUN_MULAI;
    $akhir = (int) PPDB_PANEL_TAHUN_AKHIR;
    $keluar = [];
    for ($t = $mulai; $t <= $akhir; $t++) {
        $keluar[] = $t;
    }
    return $keluar;
}

/** Tahun data PPDB Online website ini (permintaan pemilik: 2027 ke atas). */
function ppdb_tahun_website_mulai(): int
{
    return (int) PPDB_PANEL_TAHUN_AKHIR + 1;
}

// =====================================================================================
// PENARIKAN (login + unduh halaman)
// =====================================================================================

/**
 * LOGIN ke panel: mengembalikan "sesi" (berkas cookie) yang dapat dipakai untuk banyak permintaan.
 *
 * @return array{ok:bool,jar:string,pesan:string}
 */
function ppdb_panel_login(string $username, string $sandi): array
{
    $hasil = ['ok' => false, 'jar' => '', 'pesan' => ''];
    if (!function_exists('curl_init')) {
        $hasil['pesan'] = 'Perpanjangan curl tidak tersedia di server ini.';
        return $hasil;
    }
    $jar = tempnam(sys_get_temp_dir(), 'ppdb_jar_');
    if ($jar === false) {
        $hasil['pesan'] = 'Tidak dapat membuat berkas sesi sementara.';
        return $hasil;
    }
    // ⚠️ Formulir login hanya DIPROSES di alamat halaman login (POST ke halaman lain ditolak panel),
    // jadi alamat inilah yang dipakai untuk mengisi cookie sesi.
    $url = ppdb_panel_login_url();
    $opsi = ppdb_curl_opsi($jar);

    try {
        $awal = ppdb_curl_get($url, $opsi);
        if ($awal === null) {
            $hasil['pesan'] = 'Halaman panel tidak dapat dibuka.';
            @unlink($jar);
            return $hasil;
        }

        // Kirim formulir login (action kosong → POST ke alamat yang sama).
        $ch = curl_init($url);
        curl_setopt_array($ch, $opsi);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'username' => $username, 'password' => $sandi, 'btnlogin' => '1',
        ]));
        $sesudah = (string) curl_exec($ch);
        curl_close($ch);

        if ($sesudah === '' || ppdb_halaman_login($sesudah)) {
            $hasil['pesan'] = 'Login panel ditolak — periksa nama pengguna & kata sandi panel.';
            @unlink($jar);
            return $hasil;
        }
        $hasil['ok'] = true;
        $hasil['jar'] = $jar;
        return $hasil;
    } catch (Throwable $e) {
        @unlink($jar);
        $hasil['pesan'] = 'Gagal menghubungi panel: ' . $e->getMessage();
        return $hasil;
    }
}

/** Opsi curl standar (jar = berkas cookie sesi panel). */
function ppdb_curl_opsi(string $jar): array
{
    return [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => (int) PPDB_PANEL_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT      => (string) PPDB_PANEL_USERAGENT,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
}

/** GET satu alamat memakai sesi (null bila gagal). */
function ppdb_curl_get(string $url, array $opsi): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, $opsi);
    $isi = (string) curl_exec($ch);
    $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($isi === '' || $kode >= 400) {
        return null;
    }
    return $isi;
}

/**
 * Login sekali lalu unduh halaman verifikasi satu tahun (dipakai uji & pemakaian sederhana).
 *
 * @return array{ok:bool,html:string,pesan:string}
 */
function ppdb_tarik_halaman(int $tahun, string $username, string $sandi): array
{
    $sesi = ppdb_panel_login($username, $sandi);
    if (!$sesi['ok']) {
        return ['ok' => false, 'html' => '', 'pesan' => $sesi['pesan']];
    }
    try {
        $html = ppdb_curl_get(ppdb_panel_url((string) $tahun), ppdb_curl_opsi($sesi['jar']));
        if ($html === null || ppdb_halaman_login($html)) {
            return ['ok' => false, 'html' => '', 'pesan' => 'Halaman verifikasi tidak dapat dibaca.'];
        }
        return ['ok' => true, 'html' => $html, 'pesan' => ''];
    } finally {
        @unlink($sesi['jar']);
    }
}

/**
 * Alamat halaman LOGIN panel (formulirnya hanya memproses POST di alamat ini).
 * Dapat diarahkan lewat env `WEBSK_PANEL_LOGIN` (dipakai skrip uji dengan panel tiruan).
 */
function ppdb_panel_login_url(): string
{
    $env = trim((string) getenv('WEBSK_PANEL_LOGIN'));
    return $env !== '' ? $env : (string) PPDB_PANEL_LOGIN;
}

/**
 * Ambil NAMA ASAL SEKOLAH satu pendaftar dari halaman "Cetak Verifikasi" panel
 * (`panel_admin/verifikasi_cetak/<nomor>`) — halaman itulah yang memuat biodata lengkap,
 * termasuk baris "ASAL SEKOLAH : …" (halaman daftar verifikasi TIDAK memuatnya).
 *
 * @return string string kosong bila tidak ditemukan (tidak pernah menebak)
 */
function ppdb_asal_sekolah_pendaftar(string $nomor, string $jar): string
{
    $nomor = trim($nomor);
    if ($nomor === '' || $jar === '') {
        return '';
    }
    $url = rtrim(str_replace('%d', '', ppdb_panel_url('')), '/');
    // Alamat cetak = folder induk dari alamat verifikasi + "verifikasi_cetak/<nomor>".
    $url = preg_replace('#/verifikasi/thn/?$#', '', $url) ?? $url;
    $html = ppdb_curl_get($url . '/verifikasi_cetak/' . rawurlencode($nomor), ppdb_curl_opsi($jar));
    if ($html === null) {
        return '';
    }
    // Nilai diambil dari teks halaman: "ASAL SEKOLAH : <nama>" (pemisah titik dua boleh spasi).
    $teks = ppdb_sel($html);
    if (preg_match('/ASAL\s*SEKOLAH\s*:?\s*([^:]{2,160}?)(?:\s{2,}|NO\.?\s*HP|NISN|NIK|NAMA|$)/iu', $teks, $m)) {
        $asal = trim($m[1]);
        if ($asal !== '' && !preg_match('/^[-–—.]+$/u', $asal)) {
            return $asal;
        }
    }
    return '';
}

/**
 * TARIK satu tahun dengan asal sekolah (memakai sesi yang sudah login).
 *
 * @param array<int,array<string,mixed>> $daftar baris hasil `ppdb_parse_pendaftar()` (nomor + nama)
 * @return array<int,array<string,mixed>> baris dengan `asal_sekolah` terisi
 */
function ppdb_lengkapi_asal(array $daftar, string $jar, int $maks = 0): array
{
    $maks = $maks > 0 ? $maks : (int) PPDB_PANEL_MAKS_BARIS;
    $keluar = [];
    $n = 0;
    foreach ($daftar as $b) {
        if ($n >= $maks) {
            $keluar[] = $b;
            continue;
        }
        $n++;
        if (trim((string) ($b['asal_sekolah'] ?? '')) === '' && trim((string) ($b['nomor'] ?? '')) !== '') {
            $b['asal_sekolah'] = ppdb_asal_sekolah_pendaftar((string) $b['nomor'], $jar);
        }
        $keluar[] = $b;
    }
    return $keluar;
}

/** Apakah HTML yang diterima masih berupa halaman LOGIN panel (bukan daftar pendaftar)? */
function ppdb_halaman_login(string $html): bool
{
    if ($html === '') {
        return true;
    }
    $adaLogin = (bool) preg_match('/name\s*=\s*["\']?password["\']?/i', $html)
        && (bool) preg_match('/name\s*=\s*["\']?username["\']?/i', $html);
    $adaIsi = ppdb_ada_tabel($html);
    return $adaLogin && !$adaIsi;
}

/** Apakah halaman memuat tabel data (indikasi sudah masuk panel)? */
function ppdb_ada_tabel(string $html): bool
{
    return (bool) preg_match('/<table[\s>]/i', $html) && (bool) preg_match('/<tr[\s>]/i', $html);
}

// =====================================================================================
// PARSING
// =====================================================================================

/** Bersihkan sel tabel menjadi teks biasa. */
function ppdb_sel(string $html): string
{
    $t = preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $html) ?? $html;
    $t = preg_replace('#<br\s*/?>#i', ' ', $t) ?? $t;
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    return trim($t);
}

/** Nama kolom → kunci internal (dicocokkan dari judul kolom tabel panel). */
function ppdb_judul_kunci(string $judul): string
{
    $j = strtolower(trim($judul));
    $j = preg_replace('/[^a-z0-9]+/', ' ', $j) ?? $j;
    $j = trim(preg_replace('/\s+/', ' ', $j) ?? $j);
    if ($j === '') {
        return '';
    }
    // Urutan pemeriksaan penting: "asal sekolah" sebelum "sekolah", "no pendaftaran" sebelum "nama".
    if (str_contains($j, 'no') && (str_contains($j, 'pendaftar'))) {
        return 'nomor';
    }
    if (str_contains($j, 'nomor') && str_contains($j, 'pendaftar')) {
        return 'nomor';
    }
    if (str_contains($j, 'kode pendaftar') || $j === 'nomor pendaftaran') {
        return 'nomor';
    }
    // "No" / "Nomor" saja = kolom penomoran baris → kunci LEMAH (tidak boleh merebut kolom
    // "Nomor Pendaftaran"). Hanya dipakai bila tidak ada kolom nomor pendaftaran sama sekali.
    if ($j === 'nomor' || $j === 'no' || $j === 'nomor urut') {
        return 'nomor_lemah';
    }
    if (str_contains($j, 'asal')) {
        return 'asal_sekolah';
    }
    if (str_contains($j, 'sekolah') && !str_contains($j, 'nama sekolah')) {
        return 'asal_sekolah';
    }
    if (str_contains($j, 'nama')) {
        return 'nama';
    }
    if (str_contains($j, 'status') || str_contains($j, 'verifikasi')) {
        return 'status';
    }
    if (str_contains($j, 'jurusan') || str_contains($j, 'kelas')) {
        return 'jurusan';
    }
    return '';
}

/**
 * Tambahkan satu kolom ke peta kolom (menghormati kolom "lemah").
 *
 * ⚠️ JEBAKAN yang pernah menggigit: kolom "No" (penomoran baris) juga mengandung kata "nomor",
 * sehingga pada versi pertama ia MEREBUT kolom "Nomor Pendaftaran" dan yang tersimpan jadi "1".
 * Karena itu "No"/"Nomor" saja diberi kunci `nomor_lemah` dan hanya dipakai bila kolom
 * "Nomor Pendaftaran" tidak ada; sedangkan kunci kuat selalu menimpa kunci lemah.
 *
 * @return bool true bila kolom ini benar-benar dipakai sebagai judul yang dikenali
 */
function ppdb_peta_tambah(array &$peta, string $kunci, int $i): bool
{
    if ($kunci === '') {
        return false;
    }
    if ($kunci === 'nomor_lemah') {
        if (isset($peta['nomor'])) {
            return false;
        }
        $peta['nomor'] = $i;
        return true;
    }
    if ($kunci === 'nomor') {
        // Kunci kuat: menimpa pemetaan lemah sebelumnya.
        $peta['nomor'] = $i;
        return true;
    }
    if (isset($peta[$kunci])) {
        return false;
    }
    $peta[$kunci] = $i;
    return true;
}

/**
 * Parse daftar pendaftar dari HTML halaman verifikasi panel lama.
 *
 * Cara kerja: cari SEMUA tabel, tentukan tabel yang paling "berisi" (baris terbanyak), lalu petakan
 * kolom dari baris judul (`<th>` atau baris `<td>` pertama). Bila judul kolom tidak dikenali,
 * dipakai cadangan berbasis pola: sel yang tampak nomor pendaftaran / nama / sekolah.
 *
 * @return array<int,array{tahun:int,nomor:string,nama:string,asal_sekolah:string,status:string,jurusan:string,urutan:int}>
 */
function ppdb_parse_pendaftar(string $html, int $tahun = 0): array
{
    if ($html === '') {
        return [];
    }
    // Ambil seluruh tabel beserta isinya.
    if (!preg_match_all('#<table\b.*?</table>#is', $html, $mt)) {
        return [];
    }
    $terbaik = [];
    foreach ($mt[0] as $tabel) {
        $baris = ppdb_parse_tabel($tabel, $tahun);
        if (count($baris) > count($terbaik)) {
            $terbaik = $baris;
        }
    }
    return $terbaik;
}

/** Parse SATU tabel `<table>…</table>` menjadi daftar pendaftar. */
function ppdb_parse_tabel(string $tabel, int $tahun): array
{
    if (!preg_match_all('#<tr\b[^>]*>(.*?)</tr>#is', $tabel, $mr)) {
        return [];
    }
    $peta = [];
    $keluar = [];
    $urut = 0;
    foreach ($mr[1] as $tr) {
        // Sel judul (<th>) atau sel data (<td>).
        $judul = [];
        if (preg_match_all('#<th\b[^>]*>(.*?)</th>#is', $tr, $mh)) {
            $judul = array_map('ppdb_sel', $mh[1]);
        }
        if (!preg_match_all('#<td\b[^>]*>(.*?)</td>#is', $tr, $md)) {
            if ($judul) {                       // baris judul memakai <th>
                foreach ($judul as $i => $j) {
                    ppdb_peta_tambah($peta, ppdb_judul_kunci($j), $i);
                }
            }
            continue;
        }
        $sel = array_map('ppdb_sel', $md[1]);

        // Baris judul (tidak ada nomor pendaftaran yang masuk akal) → jadikan peta kolom.
        if ($judul) {
            $jmlCocok = 0;
            $petaCoba = $peta;
            foreach ($judul as $i => $j) {
                if (ppdb_peta_tambah($petaCoba, ppdb_judul_kunci($j), $i)) {
                    $jmlCocok++;
                }
            }
            if ($jmlCocok >= 2) {
                $peta = $petaCoba;
                continue;
            }
        }

        // Baris dengan <td> yang isinya hanya judul kolom (tabel tanpa <th>).
        if (!$peta) {
            $petaCoba = [];
            $jmlCocok = 0;
            foreach ($sel as $i => $nilai) {
                if (ppdb_peta_tambah($petaCoba, ppdb_judul_kunci($nilai), $i)) {
                    $jmlCocok++;
                }
            }
            if ($jmlCocok >= 2) {
                $peta = $petaCoba;
                continue;
            }
        }
        if (!$sel) {
            continue;
        }

        [$nomor, $nama, $asal, $status, $jurusan] = ppdb_baris_nilai($sel, $peta);
        if ($nomor === '' && $nama === '') {
            continue;   // bukan baris data (mis. baris tombol/aksi saja)
        }
        $urut++;
        $keluar[] = [
            'tahun' => $tahun,
            'nomor' => $nomor,
            'nama' => $nama,
            'asal_sekolah' => $asal,
            'status' => $status,
            'jurusan' => $jurusan,
            'urutan' => $urut,
        ];
    }
    return $keluar;
}

/** Ambil nilai kolom satu baris (memakai peta kolom, dicadangkan ke pola). */
function ppdb_baris_nilai(array $sel, array $peta): array
{
    $ambil = static function (string $kunci) use ($sel, $peta): string {
        $i = $peta[$kunci] ?? -1;
        return ($i >= 0 && isset($sel[$i])) ? trim((string) $sel[$i]) : '';
    };
    $nomor = $ambil('nomor');
    $nama = $ambil('nama');
    $asal = $ambil('asal_sekolah');
    $status = $ambil('status');
    $jurusan = $ambil('jurusan');

    if ($nomor === '' || $nama === '') {
        // Cadangan: cari sel yang paling menyerupai nomor pendaftaran & nama.
        foreach ($sel as $nilai) {
            $v = trim((string) $nilai);
            if ($v === '') {
                continue;
            }
            if ($nomor === '' && preg_match('/((19|20)\d{2}[-\/ ]?\d{2,}|^[A-Z]{2,6}[-\/]?\d{2,})/i', $v)) {
                $nomor = $v;
            }
        }
        if ($nama === '') {
            foreach ($sel as $nilai) {
                $v = trim((string) $nilai);
                // Nama: minimal dua kata, hanya huruf/titik/koma/spasi (bukan angka/status/tombol).
                if (strlen($v) >= 4 && preg_match('/^[A-Za-z][A-Za-z.,\'\- ]+$/', $v)
                    && preg_match('/\s/', $v) && stripos($v, 'pendaftar') === false
                    && stripos($v, 'verifikasi') === false && stripos($v, 'detail') === false) {
                    $nama = $v;
                    break;
                }
            }
        }
        if ($asal === '') {
            foreach ($sel as $nilai) {
                $v = trim((string) $nilai);
                if (stripos($v, 'SMP') === 0 || stripos($v, 'MTs') === 0 || stripos($v, 'MA ') === 0
                    || stripos($v, 'SMK') === 0 || stripos($v, 'SMA') === 0 || stripos($v, 'SD') === 0
                    || stripos($v, 'Ponpes') === 0 || stripos($v, 'Pondok') === 0) {
                    $asal = $v;
                    break;
                }
            }
        }
    }
    return [$nomor, $nama, $asal, $status, $jurusan];
}

// =====================================================================================
// PENYIMPANAN
// =====================================================================================

/**
 * Simpan (MENGGANTI) data satu tahun hasil tarikan panel.
 *
 * Data tahun lain TIDAK disentuh. Dipakai juga oleh tombol "perbarui" — jadi penarikan ulang
 * selalu menghasilkan keadaan yang sama dengan panel (tidak menumpuk baris ganda).
 *
 * @param array<int,array<string,mixed>> $baris hasil `ppdb_parse_pendaftar()`
 * @return int jumlah baris tersimpan
 */
function ppdb_simpan_tahun(int $tahun, array $baris): int
{
    $tahun = (int) $tahun;
    if ($tahun < 2000 || $tahun > 2100) {
        return 0;
    }
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->prepare('DELETE FROM ppdb_lama WHERE tahun = ?')->execute([$tahun]);
        $st = $pdo->prepare('INSERT INTO ppdb_lama (tahun, nomor, nama, asal_sekolah, sumber, urutan, ditarik, dibuat)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $kini = date('Y-m-d H:i:s');
        $n = 0;
        foreach ($baris as $b) {
            $nomor = potong(trim((string) ($b['nomor'] ?? '')), 40, '');
            $nama = potong(trim((string) ($b['nama'] ?? '')), 120, '');
            if ($nomor === '' && $nama === '') {
                continue;
            }
            if ($nomor === '') {
                $nomor = 'TANPA-NOMOR-' . ($n + 1);   // tetap dapat dibedakan bila panel tidak memuat nomor
            }
            $st->execute([
                $tahun, $nomor, $nama,
                potong(trim((string) ($b['asal_sekolah'] ?? '')), 160, ''),
                'panel',
                (int) ($b['urutan'] ?? ($n + 1)),
                $kini, $kini,
            ]);
            $n++;
        }
        $pdo->exec('COMMIT');
        return $n;
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        error_log('ppdb_simpan_tahun gagal: ' . $e->getMessage());
        return 0;
    }
}

/** Hapus seluruh data panel untuk satu tahun. */
function ppdb_hapus_tahun(int $tahun): int
{
    $st = db()->prepare('DELETE FROM ppdb_lama WHERE tahun = ?');
    $st->execute([(int) $tahun]);
    return $st->rowCount();
}

/** Ringkasan jumlah baris per tahun (dari panel). */
function ppdb_panel_ringkas(): array
{
    $keluar = [];
    foreach (all('SELECT tahun, COUNT(*) AS n, MAX(ditarik) AS terakhir FROM ppdb_lama GROUP BY tahun ORDER BY tahun DESC') as $r) {
        $keluar[(int) $r['tahun']] = ['jumlah' => (int) $r['n'], 'ditarik' => (string) ($r['terakhir'] ?? '')];
    }
    return $keluar;
}

// =====================================================================================
// GABUNGAN DATA (panel lama 2022–2026 + PPDB Online website 2027 ke atas)
// =====================================================================================

/**
 * Ungkapan SQL tahun untuk baris `ppdb_pendaftar` (PPDB Online website ini).
 *
 * Tahun diambil dari GELOMBANG (`tahun_ajaran`, mis. "2027/2028" → 2027); bila gelombang tidak
 * ada/kosong, dipakai tahun `tanggal_daftar`.
 */
function ppdb_sql_tahun_website(string $aliasP = 'p', string $aliasG = 'g'): string
{
    return 'CAST(substr(COALESCE(NULLIF(' . $aliasG . ".tahun_ajaran, ''), " . $aliasP . ".tanggal_daftar, ''), 1, 4) AS INTEGER)";
}

/** SELECT gabungan (panel + website) tanpa ORDER/LIMIT. Semua kolom bernama seragam. */
function ppdb_sql_gabungan(): string
{
    $thn = ppdb_sql_tahun_website();
    return 'SELECT l.tahun AS tahun, l.nomor AS nomor, l.nama AS nama, l.asal_sekolah AS asal_sekolah,'
        . " 'panel' AS sumber, l.urutan AS urutan, 0 AS id FROM ppdb_lama l"
        . ' UNION ALL '
        . 'SELECT ' . $thn . ' AS tahun, p.nomor AS nomor, p.nama AS nama,'
        . ' COALESCE(p.asal_sekolah, \'\') AS asal_sekolah,'
        . " 'website' AS sumber, 0 AS urutan, p.id AS id"
        . ' FROM ppdb_pendaftar p LEFT JOIN ppdb_gelombang g ON g.id = p.gelombang_id';
}

/** Daftar tahun yang punya data (panel maupun PPDB Online website), terbaru lebih dulu. */
function ppdb_tahun_ada(): array
{
    $sql = 'SELECT DISTINCT tahun FROM (' . ppdb_sql_gabungan() . ') WHERE tahun IS NOT NULL AND tahun > 1900 ORDER BY tahun DESC';
    $keluar = [];
    foreach (all($sql) as $r) {
        $keluar[] = (int) $r['tahun'];
    }
    return $keluar;
}

/** Jumlah baris pendaftar (gabungan) untuk satu tahun, atau seluruh tahun bila $tahun = 0. */
function ppdb_pendaftar_gabungan_jumlah(int $tahun = 0, string $cari = ''): int
{
    [$where, $p] = ppdb_gabungan_wheres($tahun, $cari);
    return jumlah('SELECT COUNT(*) FROM (' . ppdb_sql_gabungan() . ') ' . $where, $p);
}

/** Daftar pendaftar (gabungan), terurut nomor pendaftaran, dengan batas/paginasi. */
function ppdb_pendaftar_gabungan(int $tahun = 0, string $cari = '', int $batas = 0, int $mulai = 0): array
{
    [$where, $p] = ppdb_gabungan_wheres($tahun, $cari);
    $lim = $batas > 0 ? ' LIMIT ' . (int) $batas . ' OFFSET ' . (int) $mulai : '';
    return all('SELECT * FROM (' . ppdb_sql_gabungan() . ') ' . $where
        . ' ORDER BY nomor COLLATE NOCASE, nama COLLATE NOCASE' . $lim, $p);
}

/** Klausa WHERE gabungan (tahun + pencarian) + parameternya. */
function ppdb_gabungan_wheres(int $tahun, string $cari): array
{
    $w = [];
    $p = [];
    if ((int) $tahun > 0) {
        $w[] = 'tahun = ?';
        $p[] = (int) $tahun;
    }
    if (trim($cari) !== '') {
        $w[] = '(nama LIKE ? OR nomor LIKE ? OR asal_sekolah LIKE ?)';
        $like = '%' . trim($cari) . '%';
        array_push($p, $like, $like, $like);
    }
    return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
}

/** Label asal data (dipakai pada tabel: panel lama atau PPDB Online website ini). */
function ppdb_sumber_label(string $sumber): string
{
    return $sumber === 'panel' ? 'Panel PPDB (tahun lama)' : 'PPDB Online website';
}
