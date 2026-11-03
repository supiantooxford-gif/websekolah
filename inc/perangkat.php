<?php
/**
 * PERANGKAT GURU — berkas perangkat pembelajaran (CP, ATP, RPP/Modul Ajar) tiap guru.
 *
 * Permintaan pemilik:
 *   "Pada bagian perangkat guru isi dibuat seperti contoh yang saya upload. Dan isiannya dibuat di
 *    akun super admin. Ketika ada file yang diupload maka akan centang hijau dan jika belum maka
 *    silang merah. Untuk data gurunya otomatis dari jumlah data guru (Kepala Madrasah tidak
 *    termasuk), dan untuk mata pelajarannya setiap guru bisa lebih dari satu. Pengunjung Ketika akan
 *    klik download maka akan muncul popup masukkan kode download. Kode download di seting/generate
 *    oleh super admin untuk setiap mata pelajaran berbeda."
 *
 * Keputusan yang berlaku (jangan diubah tanpa bertanya):
 *  1. **Daftar guru otomatis** dari tabel `guru` — hanya `status = 'aktif'` dan **jabatan bukan
 *     Kepala Madrasah** (`perangkat_guru_list()`). Urut seperti di Data Guru (urutan, nama).
 *  2. **Mata pelajaran tiap guru boleh lebih dari satu** — diambil dari kolom `guru.mapel` (dipisah
 *     koma, titik koma, atau garis tegak) DITAMBAH mata pelajaran yang dibuat Super Admin pada panel
 *     (`perangkat`, unique per guru+kunci). Satu baris tabel = satu (guru × mata pelajaran).
 *  3. **Kode unduh PER MATA PELAJARAN** (`perangkat_kode`) — diatur/di-generate Super Admin; mata
 *     pelajaran berbeda memakai kode berbeda. Kode tidak pernah dicetak pada halaman publik.
 *  4. Berkas disimpan sebagai **URL media proxy** (hanya URL + nama aslinya di basis data), dan saat
 *     diunduh **dialirkan server** (`perangkat_kirim_berkas()`) sehingga URL aslinya tidak pernah
 *     sampai ke peramban — kode benar-benar melindungi berkasnya.
 */
declare(strict_types=1);

// =====================================================================================
// DAFTAR GURU & MATA PELAJARAN
// =====================================================================================

/**
 * Apakah jabatan ini KEPALA MADRASAH/SEKOLAH (dikecualikan dari tabel Perangkat Guru)?
 *
 * Permintaan pemilik: "(Kepala Madrasah tidak termasuk)". Yang dikecualikan **hanya kepalanya** —
 * jabatan yang memuat "kepala" DAN lembaganya ("madrasah"/"sekolah"), sedangkan **Wakil Kepala
 * Bidang …** dan jabatan lain (mis. Kepala Tata Usaha) TETAP ikut karena mereka juga mengajar/mengisi
 * perangkat. Jangan menyaring hanya dengan kata "kepala" — pernah salah mengecualikan wakil kepala.
 */
function perangkat_jabatan_kepala(string $jabatan): bool
{
    $j = strtolower(trim(preg_replace('/\s+/u', ' ', $jabatan) ?? $jabatan));
    if ($j === '' || str_contains($j, 'wakil')) {
        return false;
    }
    return str_contains($j, 'kepala') && (str_contains($j, 'madrasah') || str_contains($j, 'sekolah'));
}

/** Daftar guru yang ditampilkan: aktif & bukan Kepala Madrasah (urut seperti Data Guru). */
function perangkat_guru_list(): array
{
    $keluar = [];
    foreach (all("SELECT * FROM guru WHERE status = 'aktif' ORDER BY urutan, nama") as $g) {
        if (perangkat_jabatan_kepala((string) ($g['jabatan'] ?? ''))) {
            continue;
        }
        $keluar[] = $g;
    }
    return $keluar;
}

/** Pecah daftar mata pelajaran (boleh dipisah koma, titik koma, atau garis tegak). */
function perangkat_mapel_pisah(string $mapel): array
{
    $bagian = preg_split('/[,;|]+/u', $mapel) ?: [];
    $keluar = [];
    foreach ($bagian as $b) {
        $b = trim(preg_replace('/\s+/u', ' ', $b) ?? $b);
        if ($b !== '') {
            $keluar[] = $b;
        }
    }
    return $keluar;
}

/** Kunci pembanding mata pelajaran (huruf kecil, hanya huruf/angka) — "IPS" = "ips". */
function perangkat_kunci(string $mapel): string
{
    $t = strtolower(trim($mapel));
    $t = preg_replace('/[^a-z0-9]+/u', '', $t) ?? '';
    return $t;
}

// =====================================================================================
// KELAS (satu mata pelajaran boleh dipakai di BEBERAPA kelas)
// =====================================================================================
// Permintaan pemilik: *"Halaman tampilan perangkat guru tambahkan kelas, karena dalam satu mata
// pelajaran bisa saja ada di tiga kelas. … pada bagian isian di super admin disesuaikan lagi,
// setiap mata pelajaran kelasnya bisa diatur."*
// Daftar kelas diambil dari menu **Data Kelas** (tabel `kelas`); kelas yang belum terdaftar di sana
// boleh diketik manual pada panel. Disimpan sebagai teks dipisah koma pada `perangkat.kelas`.

/** Daftar kelas dari menu Data Kelas (urut seperti di panel Data Kelas) — di-cache per permintaan. */
function perangkat_kelas_daftar(bool $termasukNonaktif = false): array
{
    static $cache = [];
    $k = $termasukNonaktif ? '1' : '0';
    if (!isset($cache[$k])) {
        $w = $termasukNonaktif ? '' : ' WHERE aktif = 1';
        $cache[$k] = all('SELECT * FROM kelas' . $w . ' ORDER BY urutan, nama');
    }
    return $cache[$k];
}

/**
 * Peta urutan kelas: kunci nama kelas => nomor urut. Dipakai untuk mengurutkan baris tabel publik
 * (kelas X, XI, XII, … mengikuti urutan pada Data Kelas). Kelas yang belum terdaftar di Data Kelas
 * diletakkan SETELAH kelas terdaftar (tidak pernah hilang).
 */
function perangkat_kelas_urut(): array
{
    static $peta = null;
    if ($peta !== null) {
        return $peta;
    }
    $peta = [];
    $i = 0;
    foreach (perangkat_kelas_daftar(true) as $k) {
        $kunci = perangkat_kunci((string) $k['nama']);
        if ($kunci !== '' && !isset($peta[$kunci])) {
            $peta[$kunci] = $i;
        }
        $i++;
    }
    return $peta;
}

/** Potong teks (aman terhadap mbstring yang tidak selalu tersedia). */
function perangkat_potong(string $teks, int $maks): string
{
    if (function_exists('mb_substr')) {
        return (string) mb_substr($teks, 0, $maks);
    }
    return substr($teks, 0, $maks);
}

/**
 * Kunci kelas untuk NAMA MEDAN formulir (`berkas[<jenis>][<kunci>]`, `nama_kelas[<kunci>]`).
 * Kelas kosong ("mata pelajaran belum diberi kelas") memakai penanda `tanpakelas` — bukan string
 * kosong, karena PHP mengubah nama medan `berkas[cp][]` menjadi indeks angka (`[0]`) sehingga
 * berkasnya tidak akan pernah cocok.
 */
function perangkat_kelas_medan_kunci(string $kelas): string
{
    $k = perangkat_kunci($kelas);
    return $k !== '' ? $k : 'tanpakelas';
}

/** Pecah daftar kelas (boleh dipisah koma, titik koma, atau garis tegak) — unik, urut, terbatas. */
function perangkat_kelas_pisah(string $kelas): array
{
    $bagian = preg_split('/[,;|]+/u', $kelas) ?: [];
    $keluar = [];
    $sudah = [];
    foreach ($bagian as $b) {
        $b = trim(preg_replace('/\s+/u', ' ', $b) ?? $b);
        if ($b === '') {
            continue;
        }
        $b = perangkat_potong($b, PERANGKAT_KELAS_MAKS);
        $kunci = perangkat_kunci($b);
        if ($kunci === '' || isset($sudah[$kunci])) {
            continue;
        }
        $sudah[$kunci] = true;
        $keluar[$kunci] = $b;
        if (count($keluar) >= PERANGKAT_KELAS_BANYAK) {
            break;
        }
    }
    return array_values($keluar);
}

/**
 * Susun teks daftar kelas dari pilihan pengguna (`kelas[]` + isian manual `kelas_lain`).
 * Urutan hasilnya mengikuti urutan kelas pada Data Kelas sehingga tampilan tabel publik rapi.
 */
function perangkat_kelas_siap(array $daftar): string
{
    $peta = [];
    foreach ($daftar as $n) {
        foreach (perangkat_kelas_pisah((string) $n) as $nama) {
            $kunci = perangkat_kunci($nama);
            if ($kunci !== '' && !isset($peta[$kunci])) {
                $peta[$kunci] = $nama;
            }
        }
    }
    $urut = perangkat_kelas_urut();
    uksort($peta, static function (string $a, string $b) use ($urut): int {
        $ia = $urut[$a] ?? 900;
        $ib = $urut[$b] ?? 900;
        return $ia === $ib ? 0 : ($ia < $ib ? -1 : 1);
    });
    if (count($peta) > PERANGKAT_KELAS_BANYAK) {
        $peta = array_slice($peta, 0, PERANGKAT_KELAS_BANYAK, true);
    }
    return implode(', ', array_values($peta));
}

/** Seluruh baris `perangkat` (berkas) di-cache per permintaan: guru_id => [kunci => baris]. */
function perangkat_peta(): array
{
    static $peta = null;
    if ($peta !== null) {
        return $peta;
    }
    $peta = [];
    foreach (all('SELECT * FROM perangkat ORDER BY urutan, mapel') as $r) {
        $peta[(int) $r['guru_id']][(string) $r['kunci']] = $r;
    }
    return $peta;
}

// =====================================================================================
// PERIODE (TAHUN AJARAN + SEMESTER) — v28
// =====================================================================================
// Permintaan pemilik: *"…diganti dengan filter Tahun Ajaran dan Semester. Misal Tahun Ajaran
// 2026/2027 Semester Ganjil, karena perangkat pembelajaran itu per semester."* Daftar periode
// diatur Super Admin pada menu **"Tahun Ajaran & Semester"** (`admin/periode.php`, tabel `periode`).
// Baris bertanda `bawaan` = periode yang sedang berjalan (dibuka lebih dulu pada halaman publik).

/** Rapikan tulisan tahun ajaran: "2026-2027" / "2026 / 2027" → "2026/2027". */
function perangkat_ta_norm(string $ta): string
{
    $ta = trim(preg_replace('/\s+/u', '', $ta) ?? $ta);
    $ta = str_replace(['-', '–', '—'], '/', $ta);
    if (preg_match('#^(\d{4})/(\d{4})$#', $ta, $m) && (int) $m[2] === (int) $m[1] + 1) {
        return $m[1] . '/' . $m[2];
    }
    return perangkat_potong($ta, PERANGKAT_TA_MAKS);
}

/** Rapikan tulisan semester (mis. "ganjil" → "Ganjil"). */
function perangkat_semester_norm(string $sem): string
{
    $sem = trim(preg_replace('/\s+/u', ' ', $sem) ?? $sem);
    foreach ((array) PERANGKAT_SEMESTER as $baku) {
        if (strcasecmp($sem, (string) $baku) === 0) {
            return (string) $baku;
        }
    }
    return perangkat_potong($sem, PERANGKAT_SEMESTER_MAKS);
}

/**
 * Apakah tulisan tahun ajaran sah? Yang diterima HANYA dua tahun BERURUTAN (mis. 2026/2027) —
 * "2027/2030" ditolak karena bukan tahun ajaran.
 */
function perangkat_ta_sah(string $ta): bool
{
    if (!preg_match('#^(\d{4})/(\d{4})$#', perangkat_ta_norm($ta), $m)) {
        return false;
    }
    return (int) $m[2] === (int) $m[1] + 1;
}

/** Kunci cache/penyaring periode ('' = tanpa penyaring). */
function perangkat_periode_kunci(string $ta, string $semester): string
{
    return $ta . '|' . $semester;
}

/**
 * Klausa penyaring periode TANPA kata kunci WHERE, mis. `' AND ta = ? AND semester = ?'` — dipakai
 * pada kueri yang SUDAH punya WHERE (pernah salah memakai `perangkat_periode_where()` di situ
 * sehingga menghasilkan "… WHERE … WHERE …" dan PDO mengeluh syntax error).
 *
 * `$ta`/`$semester` KOSONG = tanpa penyaring (semua periode).
 *
 * @return array{0:string,1:array}
 */
function perangkat_periode_and(string $ta, string $semester): array
{
    $w = [];
    $p = [];
    if (trim($ta) !== '') {
        $w[] = 'ta = ?';
        $p[] = $ta;
    }
    if (trim($semester) !== '') {
        $w[] = 'semester = ?';
        $p[] = $semester;
    }
    return [$w ? (' AND ' . implode(' AND ', $w)) : '', $p];
}

/**
 * Klausa WHERE penyaring periode untuk kueri tabel `perangkat_kelas` yang BELUM punya WHERE.
 * `$ta`/`$semester` KOSONG = TANPA penyaring (semua periode) — dipakai bila sekolah belum punya
 * periode sama sekali sehingga seluruh berkas tetap tampil.
 *
 * @return array{0:string,1:array}
 */
function perangkat_periode_where(string $ta, string $semester): array
{
    [$and, $p] = perangkat_periode_and($ta, $semester);
    // ' AND ta = ?' → ' WHERE ta = ?' (spasi setelah WHERE WAJIB — pernah hilang sehingga muncul
    // "WHEREta" dan PDO mengeluh syntax error).
    return [$and === '' ? '' : (' WHERE ' . substr($and, 5)), $p];
}

/** Seluruh periode (urut), boleh hanya yang aktif. */
function perangkat_periode_list(bool $aktifSaja = true): array
{
    $w = $aktifSaja ? ' WHERE aktif = 1' : '';
    return all('SELECT * FROM periode' . $w . ' ORDER BY urutan, ta, semester, id');
}

/** Tahun ajaran yang ada pada daftar periode (urut seperti daftar periode). */
function perangkat_periode_ta_list(bool $aktifSaja = true): array
{
    $keluar = [];
    foreach (perangkat_periode_list($aktifSaja) as $r) {
        $ta = (string) $r['ta'];
        if ($ta !== '' && !in_array($ta, $keluar, true)) {
            $keluar[] = $ta;
        }
    }
    return $keluar;
}

/** Daftar semester pada satu tahun ajaran ('' = semua tahun ajaran). */
function perangkat_periode_semester_list(string $ta = '', bool $aktifSaja = true): array
{
    $keluar = [];
    foreach (perangkat_periode_list($aktifSaja) as $r) {
        if ($ta !== '' && (string) $r['ta'] !== $ta) {
            continue;
        }
        $sem = (string) $r['semester'];
        if ($sem !== '' && !in_array($sem, $keluar, true)) {
            $keluar[] = $sem;
        }
    }
    return $keluar;
}

/** Baris periode menurut (ta, semester); null bila tidak ada di daftar. */
function perangkat_periode_cari(string $ta, string $semester): ?array
{
    foreach (perangkat_periode_list(false) as $r) {
        if ((string) $r['ta'] === $ta && (string) $r['semester'] === $semester) {
            return $r;
        }
    }
    return null;
}

/**
 * Periode BAWAAN: baris bertanda `bawaan`, kalau tidak ada → periode aktif pertama, kalau tidak ada
 * juga → tahun ajaran sekolah + semester pertama ('' bila sekolah belum mengaturnya).
 *
 * @return array{ta:string,semester:string}
 */
function perangkat_periode_bawaan(): array
{
    static $hasil = null;
    if ($hasil !== null) {
        return $hasil;
    }
    foreach (perangkat_periode_list() as $r) {
        if ((int) $r['bawaan'] === 1) {
            return $hasil = ['ta' => (string) $r['ta'], 'semester' => (string) $r['semester']];
        }
    }
    $ada = perangkat_periode_list();
    if ($ada) {
        return $hasil = ['ta' => (string) $ada[0]['ta'], 'semester' => (string) $ada[0]['semester']];
    }
    return $hasil = ['ta' => perangkat_ta_norm(sek('tahun_ajaran')), 'semester' => (string) (PERANGKAT_SEMESTER[0] ?? 'Ganjil')];
}

/**
 * Periode yang sedang dilihat menurut permintaan (`?ta=` & `?sem=`), dengan pengaman: nilai yang
 * tidak ada pada daftar periode DIABAIKAN (kembali ke periode bawaan) — bukan error.
 *
 * @param array $sumber biasanya $_GET
 * @return array{ta:string,semester:string}
 */
function perangkat_periode_pilih(array $sumber = []): array
{
    // Sekolah yang BELUM mengatur periode sama sekali → TANPA penyaring, supaya berkas yang sudah
    // ada tetap tampil (halaman menampilkan keterangan "periode belum diatur").
    if (!perangkat_periode_list()) {
        return ['ta' => '', 'semester' => ''];
    }
    $bawaan = perangkat_periode_bawaan();
    $ta = perangkat_ta_norm((string) ($sumber['ta'] ?? ''));
    // ⚠️ DUA penulisan yang sah: `sem` (formulir filter halaman publik & panel) dan `sm` (parameter
    // ringkas pada tautan unduhan). Karena itu KEDUANYA dibaca — pernah hanya `sem` yang dibaca
    // sehingga unduhan selalu jatuh ke periode berjalan dan berkas semester lain tidak ditemukan.
    $sem = perangkat_semester_norm((string) ($sumber['sem'] ?? $sumber['sm'] ?? ''));
    if ($ta === '' && $sem === '') {
        return $bawaan;   // belum memilih → periode bawaan
    }
    // "Semua" eksplisit (ta=&sem= kosong dua-duanya) TIDAK dipakai di halaman publik, tetapi
    // nilai yang tidak dikenal tetap dijaga di bawah ini.
    if ($ta !== '' && !in_array($ta, perangkat_periode_ta_list(false), true)) {
        return $bawaan;
    }
    if ($sem !== '' && !in_array($sem, perangkat_periode_semester_list($ta, false), true)) {
        return $bawaan;
    }
    if ($ta === '' && $sem === '') {
        return $bawaan;
    }
    return ['ta' => $ta, 'semester' => $sem];
}

/** Label periode yang enak dibaca, mis. "2026/2027 · Semester Ganjil". */
function perangkat_periode_label(string $ta, string $semester): string
{
    $bagian = [];
    if ($ta !== '') {
        $bagian[] = 'T.A. ' . $ta;
    }
    if ($semester !== '') {
        $bagian[] = 'Semester ' . $semester;
    }
    return $bagian ? implode(' · ', $bagian) : 'semua periode';
}

/** Tautan halaman Perangkat Guru dengan periode tertentu (untuk filter & pengalihan). */
function perangkat_url_periode(string $ta, string $semester): string
{
    $q = [];
    if ($ta !== '') {
        $q['ta'] = $ta;
    }
    if ($semester !== '') {
        $q['sem'] = $semester;
    }
    return rute_tautan('halaman/' . rawurlencode((string) (nilai("SELECT slug FROM menu WHERE jenis = 'perangkat' AND aktif = 1 LIMIT 1", []) ?: 'perangkat-guru')), $q);
}

/**
 * Penolong query untuk FORMULIR/PENGALIHAN di halaman admin (mis. `perangkat.php?ta=…&sem=…`).
 * Berbeda dengan `perangkat_url_periode()` yang menuju halaman PUBLIK.
 */
function perangkat_periode_query(string $ta, string $semester): string
{
    $q = [];
    if ($ta !== '') {
        $q['ta'] = $ta;
    }
    if ($semester !== '') {
        $q['sem'] = $semester;
    }
    return $q ? ('?' . http_build_query($q)) : '';
}

// =====================================================================================
// BERKAS PER KELAS **DAN PER SEMESTER** (`perangkat_kelas`) — v27/v28
// =====================================================================================
// Permintaan pemilik: *"Pada bagian upload perangkat tambahkan fitur file yang akan di upload masuk
// dimana. Misal Upload Modul ajar Fikih untuk kelas 10, 11 kelas 12."* (v27)
//   → berkas CP/ATP/RPP/Modul Ajar disimpan **per kelas**; baris `perangkat` (guru × mata pelajaran)
//     tetap menjadi identitasnya dan **kode unduh tetap per MATA PELAJARAN**.
// Pembaruan (v28): *"…diganti dengan filter Tahun Ajaran dan Semester. Misal Tahun Ajaran 2026/2027
// Semester Ganjil, karena perangkat pembelajaran itu per semester."*
//   → berkasnya juga dipisah **per PERIODE** (`ta` + `semester`); daftar guru/kelas/mata pelajaran
//     tetap SATU (pilihan pemilik). Kelas kosong ('') = mata pelajaran yang belum diberi kelas.
//
// ⚠️ `$ta`/`$semester` KOSONG berarti "tanpa penyaring periode" (semua periode) — itulah perilaku
// aman bila sekolah belum punya periode sama sekali.

/** Seluruh berkas di-cache per permintaan: periode => perangkat_id => [kunci kelas => baris]. */
function perangkat_kelas_peta(string $ta = '', string $semester = ''): array
{
    static $peta = [];
    $kunciCache = perangkat_periode_kunci($ta, $semester);
    if (isset($peta[$kunciCache])) {
        return $peta[$kunciCache];
    }
    $peta[$kunciCache] = [];
    [$w, $p] = perangkat_periode_where($ta, $semester);
    foreach (all('SELECT * FROM perangkat_kelas' . $w . ' ORDER BY urutan, id', $p) as $r) {
        $pid = (int) $r['perangkat_id'];
        $kls = (string) $r['kelas'];
        // Tanpa penyaring periode (sekolah belum mengatur periode) satu (perangkat × kelas) bisa punya
        // beberapa baris dari periode berbeda. Yang dipakai adalah baris yang BENAR-BENAR berberkas
        // supaya berkas yang pernah diunggah tetap terlihat (bukan asal baris terakhir).
        $lama = $peta[$kunciCache][$pid][$kls] ?? null;
        if ($lama !== null && perangkat_jumlah_berkas($lama) >= perangkat_jumlah_berkas($r)) {
            continue;
        }
        $peta[$kunciCache][$pid][$kls] = $r;
    }
    return $peta[$kunciCache];
}

/** Satu baris berkas kelas (null bila belum ada) — menurut id perangkat + nama kelas + periode. */
function perangkat_kelas_baris(int $perangkatId, string $kelas, string $ta = '', string $semester = ''): ?array
{
    return perangkat_kelas_peta($ta, $semester)[$perangkatId][$kelas] ?? null;
}

/** Baris berkas untuk satu (guru × mata pelajaran × kelas × periode) — dipakai halaman unduh. */
function perangkat_kelas_cari(int $guruId, string $kunci, string $kelas, string $ta = '', string $semester = ''): ?array
{
    [$and, $p] = perangkat_periode_and($ta, $semester);
    return one('SELECT * FROM perangkat_kelas WHERE guru_id = ? AND kunci = ? AND kelas = ?' . $and,
        array_merge([$guruId, $kunci, $kelas], $p)) ?? null;
}

/**
 * Daftar baris berkas sebuah (guru × mata pelajaran) menurut daftar kelasnya PADA satu periode.
 * Selalu mengembalikan minimal satu entri (kelas kosong bila mata pelajaran belum diberi kelas).
 *
 * @return array<int,array{kelas:string,berkas:?array}>
 */
function perangkat_kelas_baris_list(array $perangkat, string $ta = '', string $semester = ''): array
{
    $kelas = perangkat_kelas_pisah((string) ($perangkat['kelas'] ?? ''));
    if (!$kelas) {
        $kelas = [''];
    }
    $keluar = [];
    foreach ($kelas as $k) {
        $keluar[] = ['kelas' => $k, 'berkas' => perangkat_kelas_baris((int) $perangkat['id'], $k, $ta, $semester)];
    }
    return $keluar;
}

/**
 * Simpan (buat bila belum ada) baris berkas satu (perangkat × kelas × PERIODE) — kembalikan idnya.
 * Id dicari berdasarkan periode juga, sehingga baris semester lain tidak ikut tersentuh.
 */
function perangkat_kelas_id(int $perangkatId, int $guruId, string $kunci, string $kelas,
                            string $ta = '', string $semester = '', int $urutan = 0): int
{
    $ada = one('SELECT * FROM perangkat_kelas WHERE perangkat_id = ? AND kelas = ? AND ta = ? AND semester = ?',
        [$perangkatId, $kelas, $ta, $semester]);
    $kini = date('Y-m-d H:i:s');
    if ($ada) {
        q('UPDATE perangkat_kelas SET guru_id = ?, kunci = ?, urutan = ?, diubah = ? WHERE id = ?',
            [$guruId, $kunci, $urutan, $kini, (int) $ada['id']]);
        return (int) $ada['id'];
    }
    q('INSERT INTO perangkat_kelas (perangkat_id, guru_id, kunci, kelas, ta, semester, urutan, dibuat, diubah)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$perangkatId, $guruId, $kunci, $kelas, $ta, $semester, $urutan, $kini, $kini]);
    return (int) db()->lastInsertId();
}

/** Jumlah kelas yang sudah punya minimal satu berkas pada satu (guru × mata pelajaran × periode). */
function perangkat_kelas_terisi(array $perangkat, string $ta = '', string $semester = ''): int
{
    $n = 0;
    foreach (perangkat_kelas_baris_list($perangkat, $ta, $semester) as $b) {
        if (perangkat_jumlah_berkas($b['berkas']) > 0) {
            $n++;
        }
    }
    return $n;
}

/** Kode unduh per mata pelajaran (kunci => baris) di-cache per permintaan. */
function perangkat_kode_peta(): array
{
    static $peta = null;
    if ($peta !== null) {
        return $peta;
    }
    $peta = [];
    foreach (all('SELECT * FROM perangkat_kode ORDER BY urutan, mapel') as $r) {
        $peta[(string) $r['kunci']] = $r;
    }
    return $peta;
}

/** Kode unduh satu mata pelajaran ('' bila belum diatur). */
function perangkat_kode(string $mapel): string
{
    $k = perangkat_kunci($mapel);
    $peta = perangkat_kode_peta();
    return $k !== '' && isset($peta[$k]) ? trim((string) $peta[$k]['kode']) : '';
}

/**
 * Nama mata pelajaran yang dipakai pada daftar **Kode Download**.
 *
 * Permintaan pemilik: *"Dan mata pelajarannya menyesuaikan dengan yang diinput oleh super admin"* —
 * jadi daftar ini bersumber dari baris `perangkat` yang DIINPUT Super Admin pada panel (urut sesuai
 * urutan barisnya), BUKAN dari kolom `mapel` pada Data Guru. Mata pelajaran dari Data Guru yang belum
 * diinput Super Admin memang belum punya berkas, jadi tidak perlu diberi kode.
 *
 * Baris kode lama yang mata pelajarannya sudah tidak ada lagi di tabel perangkat tetap DICETAK
 * (ditandai `tidak_ada` = true) supaya kode yang mungkin masih dipakai pengunjung tidak hilang
 * diam-diam — tapi tidak dihitung pada tombol "Generate semua".
 *
 * @return array<string,array{nama:string,tidak_ada:bool}>
 */
function perangkat_mapel_semua(): array
{
    $keluar = [];
    foreach (perangkat_peta() as $baris) {
        foreach ($baris as $k => $r) {
            if ($k === '' || isset($keluar[$k])) {
                continue;
            }
            $keluar[$k] = ['nama' => (string) $r['mapel'], 'tidak_ada' => false];
        }
    }
    foreach (perangkat_kode_peta() as $k => $r) {
        if ($k === '' || isset($keluar[$k])) {
            continue;
        }
        $keluar[$k] = ['nama' => (string) $r['mapel'], 'tidak_ada' => true];
    }
    uasort($keluar, static fn (array $a, array $b): int => $a['tidak_ada'] === $b['tidak_ada']
        ? strcasecmp($a['nama'], $b['nama'])
        : ($a['tidak_ada'] ? 1 : -1));
    return $keluar;   // kunci => ['nama' => …, 'tidak_ada' => bool]
}

/**
 * Data lengkap untuk tabel publik: satu kelompok per GURU, tiap kelompok berisi baris mata pelajaran.
 *
 * Setiap baris membawa **daftar kelas** mata pelajaran itu beserta **berkas per kelas**
 * (`kelas_baris` = daftar `['kelas' => nama, 'berkas' => baris perangkat_kelas|null]`). Tabel publik
 * memecah satu baris (guru × mapel) menjadi satu baris (kelas × mapel) lewat `perangkat_tabel_tampil()`.
 *
 * @return array<int,array{guru:array,baris:array<int,array{mapel:string,kunci:string,kelas:string,daftar_kelas:array,kelas_baris:array,berkas:?array}>}>
 */
function perangkat_tabel(string $ta = '', string $semester = ''): array
{
    $peta = perangkat_peta();
    $keluar = [];
    foreach (perangkat_guru_list() as $g) {
        $gid = (int) $g['id'];
        $baris = [];
        $sudah = [];
        // (1) mata pelajaran dari baris `perangkat` lebih dulu (urutannya diatur Super Admin),
        //     lalu (2) mata pelajaran dari Data Guru yang belum punya baris.
        foreach ($peta[$gid] ?? [] as $k => $r) {
            $kelas = (string) ($r['kelas'] ?? '');
            $baris[] = [
                'mapel' => (string) $r['mapel'], 'kunci' => $k, 'berkas' => $r,
                'perangkat_id' => (int) $r['id'],
                'kelas' => $kelas, 'daftar_kelas' => perangkat_kelas_pisah($kelas),
                'kelas_baris' => perangkat_kelas_baris_list($r, $ta, $semester),
            ];
            $sudah[$k] = true;
        }
        foreach (perangkat_mapel_pisah((string) ($g['mapel'] ?? '')) as $m) {
            $k = perangkat_kunci($m);
            if ($k === '' || isset($sudah[$k])) {
                continue;
            }
            $baris[] = ['mapel' => $m, 'kunci' => $k, 'berkas' => null, 'perangkat_id' => 0,
                'kelas' => '', 'daftar_kelas' => [], 'kelas_baris' => [['kelas' => '', 'berkas' => null]]];
            $sudah[$k] = true;
        }
        if (!$baris) {
            // Guru tanpa mata pelajaran tetap tampil satu baris (kolom mapel kosong).
            $baris[] = ['mapel' => '', 'kunci' => '', 'berkas' => null, 'perangkat_id' => 0,
                'kelas' => '', 'daftar_kelas' => [], 'kelas_baris' => [['kelas' => '', 'berkas' => null]]];
        }
        $keluar[] = ['guru' => $g, 'baris' => $baris];
    }
    return $keluar;
}

/**
 * Baris siap-cetak untuk tabel PUBLIK: satu baris per (KELAS × MATA PELAJARAN).
 * Mata pelajaran tanpa kelas tetap tampil satu baris (kolom kelas kosong) supaya tidak hilang,
 * dan urutannya mengikuti urutan kelas pada Data Kelas (X, XI, XII, …).
 * Berkasnya adalah berkas **kelas itu sendiri** (satu mapel boleh beda berkas tiap kelas).
 *
 * @return array<int,array{guru:array,baris:array<int,array{kelas:string,mapel:string,kunci:string,berkas:?array}>}>
 */
function perangkat_tabel_tampil(string $ta = '', string $semester = ''): array
{
    $urutKelas = perangkat_kelas_urut();
    $keluar = [];
    foreach (perangkat_tabel($ta, $semester) as $kel) {
        $baris = [];
        $i = 0;
        foreach ($kel['baris'] as $b) {
            $kelasBaris = $b['kelas_baris'] !== [] ? $b['kelas_baris'] : [['kelas' => '', 'berkas' => null]];
            foreach ($kelasBaris as $kb) {
                $namaKelas = (string) $kb['kelas'];
                $kunciKelas = perangkat_kunci($namaKelas);
                $baris[] = [
                    'kelas'       => $namaKelas,
                    'kelas_urut'  => $namaKelas === '' ? 9999 : ($urutKelas[$kunciKelas] ?? 900),
                    'urut'        => $i,
                    'mapel'       => $b['mapel'],
                    'kunci'       => $b['kunci'],
                    'berkas'      => $kb['berkas'],
                ];
                $i++;
            }
        }
        usort($baris, static fn (array $x, array $y): int
            => [$x['kelas_urut'], $x['urut']] <=> [$y['kelas_urut'], $y['urut']]);
        $keluar[] = ['guru' => $kel['guru'], 'baris' => $baris];
    }
    return $keluar;
}

/** Apakah baris perangkat ini punya berkas untuk jenis tertentu (cp/atp/rpp)? */
function perangkat_ada(?array $berkas, string $jenis): bool
{
    if ($berkas === null) {
        return false;
    }
    return trim((string) ($berkas[$jenis] ?? '')) !== '';
}

/** Jumlah berkas terunggah pada satu baris (0-3). */
function perangkat_jumlah_berkas(?array $berkas): int
{
    $n = 0;
    foreach (array_keys(PERANGKAT_JENIS) as $j) {
        if (perangkat_ada($berkas, $j)) {
            $n++;
        }
    }
    return $n;
}

/** Ringkasan seluruh tabel (jumlah guru, baris mata pelajaran, kelas, baris tampil, berkas). */
function perangkat_ringkas(string $ta = '', string $semester = ''): array
{
    $guru = 0;
    $baris = 0;
    $berkas = 0;
    $lengkap = 0;
    $tampil = 0;
    $kelas = [];
    foreach (perangkat_tabel($ta, $semester) as $kel) {
        $guru++;
        foreach ($kel['baris'] as $b) {
            $baris++;
            // Berkas dihitung per KELAS pada PERIODE itu (lengkap bila CP+ATP+RPP-nya ada).
            foreach ($b['kelas_baris'] as $kb) {
                $n = perangkat_jumlah_berkas($kb['berkas']);
                $berkas += $n;
                if ($n === count(PERANGKAT_JENIS)) {
                    $lengkap++;
                }
            }
            foreach ($b['daftar_kelas'] as $nk) {
                $kelas[perangkat_kunci($nk)] = true;
            }
        }
    }
    foreach (perangkat_tabel_tampil($ta, $semester) as $kel) {
        $tampil += count($kel['baris']);
    }
    return ['guru' => $guru, 'baris' => $baris, 'berkas' => $berkas, 'lengkap' => $lengkap,
            'jenis' => count(PERANGKAT_JENIS), 'tampil' => $tampil, 'kelas' => count($kelas)];
}

// =====================================================================================
// KODE UNDUH (per mata pelajaran) — diatur/di-generate Super Admin
// =====================================================================================

/** Buat kode unduh acak yang mudah dibaca (tanpa huruf/angka mirip: 0/O, 1/I/L). */
function perangkat_kode_generate(int $panjang = 0): string
{
    $panjang = $panjang > 0 ? $panjang : (int) PERANGKAT_KODE_PANJANG;
    $abjad = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $maks = strlen($abjad) - 1;
    $kode = '';
    for ($i = 0; $i < $panjang; $i++) {
        $kode .= $abjad[random_int(0, $maks)];
    }
    return $kode;
}

/**
 * Buat kode BARU yang belum dipakai kode lain (dan tidak sama dengan kode mana pun pada `$hindari`).
 *
 * Dipakai oleh tombol **Generate Sekaligus** — permintaan pemilik: *"tambah fitur generate sekaligus
 * tapi hasil generate berbeda"* — sehingga setiap mata pelajaran menerima kode yang BERBEDA satu
 * dengan lainnya (bukan satu kode yang dipakai bersama). Bila abjad habis (praktis mustahil untuk
 * daftar mapel yang wajar), panjang kodenya ditambah satu karakter.
 */
function perangkat_kode_unik(array $hindari = []): string
{
    $ada = [];
    foreach ($hindari as $k) {
        $ada[strtoupper((string) $k)] = true;
    }
    foreach (perangkat_kode_peta() as $r) {
        $ada[strtoupper(trim((string) $r['kode']))] = true;
    }
    $panjang = (int) PERANGKAT_KODE_PANJANG;
    for ($coba = 0; $coba < 200; $coba++) {
        $kode = perangkat_kode_generate($panjang);
        if (!isset($ada[$kode])) {
            return $kode;
        }
        if ($coba > 0 && $coba % 50 === 0) {
            $panjang++;   // ruang kode penuh → perpanjang agar tetap unik
        }
    }
    return perangkat_kode_generate($panjang + 2);
}

/**
 * Simpan kode untuk BANYAK mata pelajaran SEKALIGUS; hasilnya berbeda satu dengan lainnya.
 *
 * @param array<string,string> $daftar kunci mapel => nama mapel (yang diberi kode baru)
 * @return array<string,string> kunci => kode baru
 */
function perangkat_kode_generate_sekaligus(array $daftar): array
{
    $hasil = [];
    foreach ($daftar as $kunci => $nama) {
        if ((string) $kunci === '') {
            continue;
        }
        $kode = perangkat_kode_unik($hasil);
        perangkat_kode_simpan((string) $nama, $kode);
        $hasil[(string) $kunci] = $kode;
    }
    return $hasil;
}

/** Simpan kode unduh satu mata pelajaran (menambah barisnya bila belum ada). */
function perangkat_kode_simpan(string $mapel, string $kode): bool
{
    $mapel = trim(preg_replace('/\s+/u', ' ', $mapel) ?? $mapel);
    $k = perangkat_kunci($mapel);
    $kode = strtoupper(trim($kode));
    if ($k === '' || $kode === '') {
        return false;
    }
    $kini = date('Y-m-d H:i:s');
    $ada = one('SELECT * FROM perangkat_kode WHERE kunci = ?', [$k]);
    if ($ada) {
        q('UPDATE perangkat_kode SET mapel = ?, kode = ?, diubah = ? WHERE id = ?',
            [$mapel, $kode, $kini, (int) $ada['id']]);
        return true;
    }
    q('INSERT INTO perangkat_kode (mapel, kunci, kode, urutan, dibuat, diubah) VALUES (?, ?, ?, ?, ?, ?)',
        [$mapel, $k, $kode, (int) jumlah('SELECT COUNT(*) FROM perangkat_kode') * 10 + 10, $kini, $kini]);
    return true;
}

/** Hapus kode unduh satu mata pelajaran. */
function perangkat_kode_hapus(string $kunci): void
{
    q('DELETE FROM perangkat_kode WHERE kunci = ?', [$kunci]);
}

/** Cocokkan kode yang dimasukkan pengunjung dengan kode mata pelajaran ini. */
function perangkat_kode_cocok(string $mapel, string $masukan): bool
{
    $kode = perangkat_kode($mapel);
    if ($kode === '') {
        return false;
    }
    $bersih = strtoupper(preg_replace('/[\s\-]+/u', '', trim($masukan)) ?? '');
    return $bersih !== '' && hash_equals(strtoupper($kode), $bersih);
}

// --- batas percobaan kode per sesi (anti tebak), pola sama dengan Kartu PIP ----------------------
/** Keadaan percobaan kode pada sesi ini: percobaan ke-, sisa, dan apakah sudah habis. */
function perangkat_coba_keadaan(): array
{
    $kini = time();
    $data = $_SESSION['perangkat_coba'] ?? ['mulai' => 0, 'n' => 0];
    $batas = (int) PERANGKAT_MENIT_COBA * 60;
    if ((int) $data['mulai'] === 0 || ($kini - (int) $data['mulai']) > $batas) {
        $data = ['mulai' => $kini, 'n' => 0];
        $_SESSION['perangkat_coba'] = $data;
    }
    $sisa = max(0, (int) PERANGKAT_MAKS_COBA - (int) $data['n']);
    return [
        'n' => (int) $data['n'],
        'sisa' => $sisa,
        'habis' => $sisa <= 0,
        'menit' => (int) PERANGKAT_MENIT_COBA,
    ];
}

/** Catat satu percobaan kode yang salah. */
function perangkat_coba_catat(): void
{
    $data = $_SESSION['perangkat_coba'] ?? ['mulai' => time(), 'n' => 0];
    $data['n'] = (int) $data['n'] + 1;
    $_SESSION['perangkat_coba'] = $data;
}

/** Kosongkan hitungan percobaan (setelah kode benar). */
function perangkat_coba_reset(): void
{
    unset($_SESSION['perangkat_coba']);
}

// =====================================================================================
// BERKAS: aliran unduhan (URL media tidak pernah sampai ke peramban)
// =====================================================================================

/** Ekstensi berkas dari URL/nama berkas. */
function perangkat_ekstensi(string $url, string $namaAsli = ''): string
{
    foreach ([$namaAsli, (string) parse_url($url, PHP_URL_PATH)] as $sumber) {
        $ext = strtolower(pathinfo((string) $sumber, PATHINFO_EXTENSION));
        if ($ext !== '' && preg_match('/^[a-z0-9]{2,5}$/', $ext)) {
            return $ext;
        }
    }
    return 'pdf';
}

/**
 * Kirim (alirkan) berkas dari URL media ke peramban pengunjung, memakai nama berkas yang rapi.
 * @return bool true bila berkas sudah terkirim
 */
function perangkat_kirim_berkas(string $url, string $namaUnduh): bool
{
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return false;
    }
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
            CURLOPT_USERAGENT      => 'Website-Sekolah/1.0 (+Perangkat-Guru)',
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

    $ext = perangkat_ekstensi($url, $namaUnduh);
    $jenis = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    ][$ext] ?? 'application/octet-stream';

    $namaUnduh = trim($namaUnduh) !== '' ? trim($namaUnduh) : ('perangkat-guru.' . $ext);
    if (strtolower(pathinfo($namaUnduh, PATHINFO_EXTENSION)) !== $ext) {
        $namaUnduh .= '.' . $ext;
    }
    $namaUnduh = preg_replace('/[\r\n"]+/', '', $namaUnduh) ?? $namaUnduh;

    header('Content-Type: ' . $jenis);
    header('Content-Length: ' . strlen($isi));
    header('Content-Disposition: attachment; filename="' . $namaUnduh . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $isi;
    return true;
}

/** Nama berkas unduhan yang rapi: "CP - <Nama Guru> - <Mapel> - <Kelas>.pdf". */
function perangkat_nama_unduh(string $jenis, string $namaGuru, string $mapel, ?array $berkas, string $kelas = ''): string
{
    $label = PERANGKAT_JENIS[$jenis] ?? strtoupper($jenis);
    $label = preg_replace('/\s*\(.*?\)\s*/', '', $label) ?? $label;   // buang "(CP)" dsb.
    $nama = trim($label . ' - ' . $namaGuru . ($mapel !== '' ? ' - ' . $mapel : '')
        . ($kelas !== '' ? ' - ' . $kelas : ''));
    if ($berkas !== null && trim((string) ($berkas[$jenis . '_nama'] ?? '')) !== '') {
        return (string) $berkas[$jenis . '_nama'];
    }
    return $nama;
}

// =====================================================================================
// TAMPILAN PUBLIK
// =====================================================================================

/** Ikon centang hijau (berkas sudah diunggah) — seperti contoh pemilik. */
function perangkat_ikon_ada(int $ukuran = 26): string
{
    return '<span class="pg-ikon pg-ikon-ok" aria-label="Berkas sudah tersedia" title="Berkas sudah tersedia">'
        . '<svg viewBox="0 0 24 24" width="' . $ukuran . '" height="' . $ukuran . '" aria-hidden="true">'
        . '<circle cx="12" cy="12" r="11" fill="#3aa63f"/>'
        . '<path d="M6.8 12.4l3.2 3.2 7-7.4" fill="none" stroke="#fff" stroke-width="2.6" '
        . 'stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
}

/** Ikon silang merah (berkas belum diunggah). */
function perangkat_ikon_belum(int $ukuran = 26): string
{
    return '<span class="pg-ikon pg-ikon-no" aria-label="Berkas belum diunggah" title="Berkas belum diunggah">'
        . '<svg viewBox="0 0 24 24" width="' . $ukuran . '" height="' . $ukuran . '" aria-hidden="true">'
        . '<circle cx="12" cy="12" r="11" fill="#e02b20"/>'
        . '<path d="M8.2 8.2l7.6 7.6M15.8 8.2l-7.6 7.6" fill="none" stroke="#fff" stroke-width="2.6" '
        . 'stroke-linecap="round"/></svg></span>';
}

/** Tautan/tombol unduh satu sel (target halaman kode unduh). */
/**
 * Tautan/tombol unduh satu sel (target halaman kode unduh).
 * `$kelasKunci` = KUNCI kelas (huruf kecil/angka) — berkasnya berbeda tiap kelas, jadi kelasnya ikut
 * dibawa pada alamat unduhan (`k`). Kode unduh tetap milik MATA PELAJARAN.
 */
function perangkat_tautan_unduh(int $guruId, string $kunci, string $jenis, string $kelasKunci = '',
                                 string $ta = '', string $semester = ''): string
{
    $q = ['g' => $guruId, 'm' => $kunci, 'j' => $jenis];
    if (trim($kelasKunci) !== '') {
        $q['k'] = trim($kelasKunci);
    }
    if (trim($ta) !== '') {
        $q['ta'] = trim($ta);
    }
    if (trim($semester) !== '') {
        $q['sm'] = trim($semester);
    }
    return rute_tautan('perangkat_unduh.php', $q);
}

/**
 * Mencetak tabel Perangkat Guru (menu Kurikulum → Perangkat Guru).
 */
function perangkat_papan(): void
{
    // PERIODE (Tahun Ajaran + Semester) — permintaan pemilik: perangkat pembelajaran itu per semester,
    // jadi bagian chip ringkasan DIGANTI filter periode. Pilihan bawaan = periode `bawaan`.
    $periode = perangkat_periode_pilih($_GET);
    $ta = $periode['ta'];
    $semester = $periode['semester'];
    $tabel = perangkat_tabel_tampil($ta, $semester);
    $taAda = perangkat_periode_ta_list();
    $semAda = perangkat_periode_semester_list($ta);
    ?>
<div class="pg-papan" id="papanPerangkat">

  <section class="pg-blok">
    <div class="pg-kepala">
      <div>
        <h2>Perangkat Guru</h2>
        <p>Berkas perangkat pembelajaran setiap guru: <b>Capaian Pembelajaran (CP)</b>,
           <b>Alur Tujuan Pembelajaran (ATP)</b>, dan <b>RPP/Modul Ajar</b>. Tanda
           <span class="pg-ikon-ket"><?= perangkat_ikon_ada(16) ?></span> berarti berkasnya sudah tersedia,
           <span class="pg-ikon-ket"><?= perangkat_ikon_belum(16) ?></span> berarti belum diunggah.</p>
      </div>
    </div>

    <?php /* ===== FILTER TAHUN AJARAN & SEMESTER (menggantikan chip ringkasan) ===== */ ?>
    <div class="pg-filter" id="filterPerangkat">
      <?php if ($taAda): ?>
        <form method="get" action="" class="pg-filter-form">
          <label class="pg-filter-medan" for="pgTa">
            <span>Tahun Ajaran</span>
            <select id="pgTa" name="ta">
              <?php foreach ($taAda as $t): ?>
                <option value="<?= e($t) ?>"<?= $t === $ta ? ' selected' : '' ?>>Tahun Ajaran <?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="pg-filter-medan" for="pgSem">
            <span>Semester</span>
            <select id="pgSem" name="sem">
              <?php foreach ($semAda as $sm): ?>
                <option value="<?= e($sm) ?>"<?= $sm === $semester ? ' selected' : '' ?>>Semester <?= e($sm) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <button type="submit" class="pg-btn pg-btn-filter"><?= ikon('grafik', 16) ?> Tampilkan</button>
        </form>
        <p class="pg-filter-ket">
          Menampilkan <b><?= e(perangkat_periode_label($ta, $semester)) ?></b>.
          Perangkat pembelajaran disusun per semester — ganti tahun ajaran/semester untuk melihat
          berkas semester lain.
        </p>
      <?php else: ?>
        <p class="pg-filter-ket">Periode (Tahun Ajaran &amp; Semester) belum diatur pengelola madrasah.</p>
      <?php endif; ?>
    </div>

    <?php if (!$tabel): ?>
      <div class="pg-kosong">
        <?= ikon('users', 34) ?>
        <h3>Belum ada data guru</h3>
        <p>Daftar guru diambil otomatis dari menu Data Guru (kepala madrasah tidak termasuk).</p>
      </div>
    <?php else: ?>
      <div class="pg-tabel-wrap">
        <table class="pg-tabel" id="tabelPerangkat">
          <thead>
            <tr>
              <th class="pg-c-no">No</th>
              <th class="pg-c-nama">Nama Guru</th>
              <th class="pg-c-kelas">Kelas</th>
              <th class="pg-c-mapel">Mata Pelajaran</th>
              <?php foreach (PERANGKAT_JENIS as $jenis => $label): ?>
                <th class="pg-c-berkas"><?= e($label) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
          <?php $no = 0; foreach ($tabel as $kel): $g = $kel['guru']; $no++; $jml = count($kel['baris']); ?>
            <?php foreach ($kel['baris'] as $i => $b): $kelasBaris = $i === 0 ? ' pg-baris-awal' : ' pg-baris-lanjutan'; ?>
              <tr class="pg-baris<?= $kelasBaris ?><?= $i === $jml - 1 ? ' pg-baris-akhir' : '' ?>">
                <?php if ($i === 0): ?>
                  <td class="pg-c-no" rowspan="<?= $jml ?>"><?= $no ?></td>
                  <td class="pg-c-nama" rowspan="<?= $jml ?>">
                    <b><?= e((string) $g['nama']) ?></b>
                    <?php if (trim((string) ($g['nip'] ?? '')) !== '' && trim((string) $g['nip']) !== '-'): ?>
                      <i class="pg-sub">NIP/NUPTK <?= e((string) $g['nip']) ?></i>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td class="pg-c-kelas">
                  <?php if ($b['kelas'] !== ''): ?>
                    <?= e($b['kelas']) ?>
                  <?php else: ?>
                    <span class="pg-mut">—</span>
                  <?php endif; ?>
                </td>
                <td class="pg-c-mapel">
                  <?php if ($b['mapel'] !== ''): ?>
                    <?= e($b['mapel']) ?>
                  <?php else: ?>
                    <span class="pg-mut">(belum ada mata pelajaran)</span>
                  <?php endif; ?>
                </td>
                <?php foreach (array_keys(PERANGKAT_JENIS) as $jenis): ?>
                  <?php $ada = perangkat_ada($b['berkas'], $jenis); ?>
                  <td class="pg-c-berkas pg-sel">
                    <span class="pg-ikon-baris"><?= $ada ? perangkat_ikon_ada() : perangkat_ikon_belum() ?></span>
                    <?php if ($b['kunci'] === ''): ?>
                      <span class="pg-btn pg-btn-mati" aria-disabled="true"><?= ikon('unduh', 16) ?> Download</span>
                    <?php else: ?>
                      <a class="pg-btn pg-btn-unduh" href="<?= e(perangkat_tautan_unduh((int) $g['id'], (string) $b['kunci'], $jenis, perangkat_kunci((string) $b['kelas']), $ta, $semester)) ?>"
                         data-pg-unduh="1"
                         data-pg-guru="<?= e((string) $g['nama']) ?>"
                         data-pg-mapel="<?= e((string) $b['mapel']) ?>"
                         data-pg-kelas="<?= e((string) $b['kelas']) ?>"
                         data-pg-periode="<?= e(perangkat_periode_label($ta, $semester)) ?>"
                         data-pg-jenis="<?= e((string) (PERANGKAT_JENIS[$jenis] ?? $jenis)) ?>"
                         data-pg-ada="<?= $ada ? '1' : '0' ?>">
                        <?= ikon('unduh', 16) ?> Download</a>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <p class="pg-ket">
        Klik <b>Download</b> lalu masukkan <b>kode download</b> yang diberikan pengelola madrasah untuk
        mata pelajaran tersebut. Kode setiap mata pelajaran berbeda, sedangkan berkasnya terpisah
        <b>per semester</b> — berkas semester lain dapat dibuka lewat pilihan Tahun Ajaran &amp; Semester
        di atas.
      </p>
    <?php endif; ?>
  </section>
</div>

<?php /* ============ POPUP KODE DOWNLOAD ============ */ ?>
<dialog id="dialogKodeUnduh" class="pg-dialog" aria-labelledby="pgDialogJudul">
  <form method="post" action="perangkat_unduh.php" id="formKodeUnduh">
    <?= csrf_field() ?>
    <input type="hidden" name="g" value="" id="pgInGuru">
    <input type="hidden" name="m" value="" id="pgInMapel">
    <input type="hidden" name="j" value="" id="pgInJenis">
    <?php /* Kelas baris yang diklik — berkas perangkat disimpan per KELAS (v27). */ ?>
    <input type="hidden" name="k" value="" id="pgInKelas">
    <?php /* Periode baris yang diklik (tahun ajaran + semester) — berkas terpisah per SEMESTER (v28). */ ?>
    <input type="hidden" name="ta" value="" id="pgInTa">
    <input type="hidden" name="sm" value="" id="pgInSm">
    <h3 id="pgDialogJudul"><?= ikon('kunci', 18) ?> Masukkan kode download</h3>
    <p class="pg-dialog-ket" id="pgDialogKet">Berkas perangkat guru ini dilindungi kode download.</p>
    <label class="pg-dialog-label" for="pgKode">Kode download</label>
    <input type="text" id="pgKode" name="kode" autocomplete="off" maxlength="40" required
           placeholder="Mis. A7K2M9QP">
    <div class="pg-dialog-aksi">
      <button type="button" class="pg-btn pg-btn-batal" data-pg-tutup>Tutup</button>
      <button type="submit" class="pg-btn pg-btn-kirim"><?= ikon('unduh', 16) ?> Unduh berkas</button>
    </div>
  </form>
</dialog>
    <?php
}

/**
 * Tampilkan halaman permintaan KODE DOWNLOAD (dipakai `perangkat_unduh.php`).
 *
 * Halaman ini adalah bentuk "tanpa JavaScript" dari popup — formulirnya sama dengan yang ada pada
 * `dialog#dialogKodeUnduh` di halaman Perangkat Guru, sehingga alurnya tetap bekerja bila skrip
 * gagal dimuat. Setelah kode benar, halaman mengalirkan berkasnya (URL media tidak pernah terkirim).
 */
function perangkat_unduh_tampil(string $judul, string $pesan, string $namaGuru, string $mapel,
                                string $galat = '', string $jenisAlert = '', string $pesanInfo = '',
                                string $kelas = '', string $periode = ''): void
{
    $terkunci = perangkat_coba_keadaan();
    $guruId = (int) ($_GET['g'] ?? $_POST['g'] ?? 0);
    $kunci = trim((string) ($_GET['m'] ?? $_POST['m'] ?? ''));
    $j = trim((string) ($_GET['j'] ?? $_POST['j'] ?? ''));
    $k = trim((string) ($_GET['k'] ?? $_POST['k'] ?? ''));
    $ta = perangkat_ta_norm((string) ($_GET['ta'] ?? $_POST['ta'] ?? ''));
    $sm = perangkat_semester_norm((string) ($_GET['sm'] ?? $_POST['sm'] ?? ''));
    $labelJenis = (string) (PERANGKAT_JENIS[$j] ?? 'Berkas');
    if ($kelas === '' && $k !== '') {
        $kelas = $k;   // tampilkan kunci kelas bila nama kelasnya tidak diketahui
    }
    situs_atas(['judul' => $judul . ' — Perangkat Guru']);
    ?>
<section class="isi-halaman">
    <div class="wadah">
        <div class="artikel pg-unduh-kartu">
            <h1><?= e($judul) ?></h1>
            <?php if ($pesan !== ''): ?><p class="pg-mut"><?= e($pesan) ?></p><?php endif; ?>

            <?php if ($galat !== ''): ?>
                <div class="alert <?= $jenisAlert === 'err' ? 'alert-err' : 'alert-info' ?>"><?= e($galat) ?></div>
            <?php endif; ?>
            <?php if ($pesanInfo !== ''): ?>
                <div class="alert alert-info"><?= e($pesanInfo) ?></div>
            <?php endif; ?>

            <?php if ($namaGuru !== ''): ?>
                <p class="pg-unduh-identitas">
                    <b><?= e($namaGuru) ?></b><?= $mapel !== '' ? ' &middot; ' . e($mapel) : '' ?>
                    <?= $kelas !== '' ? ' &middot; Kelas ' . e($kelas) : '' ?>
                    <?= $periode !== '' ? ' &middot; ' . e($periode) : '' ?>
                    &middot; <?= e($labelJenis) ?>
                </p>
            <?php endif; ?>

            <?php if ($guruId > 0 && $kunci !== '' && $namaGuru !== '' && !$terkunci['habis']): ?>
                <form method="post" action="perangkat_unduh.php" class="pg-unduh-form" id="formKodeUnduhHal">
                    <?= csrf_field() ?>
                    <input type="hidden" name="g" value="<?= $guruId ?>">
                    <input type="hidden" name="m" value="<?= e($kunci) ?>">
                    <input type="hidden" name="j" value="<?= e($j) ?>">
                    <input type="hidden" name="k" value="<?= e($k) ?>">
                    <input type="hidden" name="ta" value="<?= e($ta) ?>">
                    <input type="hidden" name="sm" value="<?= e($sm) ?>">
                    <div class="form-baris">
                        <label for="kode">Kode download</label>
                        <input type="text" id="kode" name="kode" required autofocus autocomplete="off"
                               maxlength="40" placeholder="Kode untuk mata pelajaran ini">
                        <div class="bantuan">Kode download ditetapkan pengelola madrasah dan
                            <b>berbeda untuk setiap mata pelajaran</b>. Bila belum menerimanya,
                            silakan menghubungi admin madrasah.</div>
                    </div>
                    <div class="aksi-form">
                        <button type="submit" class="tombol"><?= ikon('unduh', 16) ?> Unduh berkas</button>
                        <a class="tombol tombol-garis" href="<?= e(perangkat_url()) ?>">Kembali</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="aksi-form">
                    <a class="tombol tombol-garis" href="<?= e(perangkat_url()) ?>">
                        <?= ikon('panah-kiri', 16) ?> Kembali ke halaman Perangkat Guru</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
    <?php
    situs_bawah();
}

/** Tautan halaman menu Perangkat Guru (RELATIF) — slug dibaca dari basis data bila ada. */
function perangkat_url(): string
{
    static $slug = null;
    if ($slug === null) {
        $slug = (string) (nilai("SELECT slug FROM menu WHERE jenis = 'perangkat' AND aktif = 1 LIMIT 1", []) ?: 'perangkat-guru');
    }
    return rute_tautan('halaman/' . rawurlencode($slug));
}
