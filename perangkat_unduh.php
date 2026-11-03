<?php
/**
 * UNDUH PERANGKAT GURU (dengan KODE DOWNLOAD) — halaman publik.
 *
 * Alurnya:
 *   1. Pengunjung menekan tombol **Download** pada tabel "Perangkat Guru". Dengan JavaScript, yang
 *      muncul adalah **popup "Masukkan kode download"** di halaman itu (`dialog#dialogKodeUnduh`);
 *      tanpa JavaScript, tautannya langsung ke halaman ini (formulir yang sama).
 *   2. Kode yang dimasukkan diperiksa terhadap **kode mata pelajaran** yang diatur Super Admin
 *      (`perangkat_kode`) — mata pelajaran berbeda memakai kode berbeda.
 *   3. Setelah kode BENAR, berkasnya **DIALIRKAN server ini** (`perangkat_kirim_berkas()`) sehingga
 *      URL media aslinya TIDAK pernah dikirim ke peramban — kode benar-benar melindungi berkasnya.
 *      Bila berkasnya belum diunggah (silang merah), pengunjung diberi keterangan yang jelas.
 *
 * Pengaman: batas percobaan kode per sesi (`PERANGKAT_MAKS_COBA` kali / `PERANGKAT_MENIT_COBA` menit),
 * token CSRF, dan pola POST→Redirect→GET agar kode tidak tertinggal di riwayat/refresh peramban.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/perangkat.php';

$guruId = (int) ($_GET['g'] ?? $_POST['g'] ?? 0);
$kunci = trim((string) ($_GET['m'] ?? $_POST['m'] ?? ''));
$jenis = trim((string) ($_GET['j'] ?? $_POST['j'] ?? ''));
// v27 — berkas disimpan PER KELAS, jadi kelasnya ikut dibawa (`k` = kunci kelas).
$kelasKunci = trim((string) ($_GET['k'] ?? $_POST['k'] ?? ''));
// v28 — berkas juga dipisah PER PERIODE: `ta` (tahun ajaran) + `sm` (semester).
// Penjaga yang SAMA dengan halaman publik: tautan lama tanpa periode → periode berjalan, dan
// sekolah tanpa periode sama sekali → tanpa penyaring (berkas tetap ditemukan).
$periodeUnduh = perangkat_periode_pilih(array_merge($_GET, $_POST));
$taKunci = $periodeUnduh['ta'];
$semKunci = $periodeUnduh['semester'];
if (!array_key_exists($jenis, PERANGKAT_JENIS)) {
    $jenis = 'cp';
}

/** Satu guru menurut id (null bila tidak ada). */
function perangkat_guru_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM guru WHERE id = ?', [$id]) : null;
}

/** Baris perangkat satu guru + satu mata pelajaran (null bila belum ada). */
function perangkat_baris(int $guruId, string $kunci): ?array
{
    return one('SELECT * FROM perangkat WHERE guru_id = ? AND kunci = ?', [$guruId, $kunci]) ?? null;
}

/**
 * Berkas untuk (guru × mapel × kelas) + nama kelas yang dipakai.
 *
 * Aturan pemilihan (v27):
 *  1. `k` (kunci kelas) diisi & barisnya ada → dipakai baris itu;
 *  2. `k` kosong / kelasnya tidak ada → kelas TERDAFTAR pertama yang berkasnya untuk jenis ini
 *     terisi (tautan lama tanpa `k` tetap bisa mengunduh);
 *  3. kalau tidak ada yang terisi → baris pertama (agar pesan "belum diunggah" tetap informatif).
 *
 * ⚠️ Kandidat diambil dari SELURUH baris `perangkat_kelas` milik (guru × mapel) itu, bukan hanya dari
 * daftar kelas yang sedang tersimpan — supaya berkas yang sudah diunggah tetap dapat diunduh walau
 * centang kelasnya kelak dikosongkan (jangan kehilangan berkas karena daftar kelas berubah).
 *
 * @return array{0:?array,1:string} [baris berkas, nama kelas]
 */
function perangkat_kelas_berkas(array $perangkat, string $kelasKunci, string $jenis,
                                string $ta = '', string $semester = ''): array
{
    [$andPeriode, $pPeriode] = perangkat_periode_and($ta, $semester);
    $semua = all('SELECT * FROM perangkat_kelas WHERE perangkat_id = ?' . $andPeriode . ' ORDER BY urutan, id',
        array_merge([(int) $perangkat['id']], $pPeriode));
    if (!$semua) {
        return [null, ''];
    }
    $terdaftar = perangkat_kelas_pisah((string) ($perangkat['kelas'] ?? ''));
    // Urutkan: kelas terdaftar lebih dulu (urut Data Kelas), sisanya mengikuti urutan baris.
    usort($semua, static function (array $a, array $b) use ($terdaftar): int {
        $ia = array_search((string) $a['kelas'], $terdaftar, true);
        $ib = array_search((string) $b['kelas'], $terdaftar, true);
        $ia = $ia === false ? 900 + (int) $a['urutan'] : (int) $ia;
        $ib = $ib === false ? 900 + (int) $b['urutan'] : (int) $ib;
        return $ia <=> $ib;
    });
    if ($kelasKunci !== '') {
        foreach ($semua as $r) {
            if (perangkat_kunci((string) $r['kelas']) === $kelasKunci) {
                return [$r, (string) $r['kelas']];
            }
        }
    }
    foreach ($semua as $r) {
        if (perangkat_ada($r, $jenis)) {
            return [$r, (string) $r['kelas']];
        }
    }
    return [$semua[0], (string) $semua[0]['kelas']];
}

$guru = perangkat_guru_by_id($guruId);
$namaGuru = $guru !== null ? (string) $guru['nama'] : '';

// Nama mata pelajaran ditampilkan apa adanya dari basis data (dari baris perangkat atau Data Guru).
$mapel = '';
if ($guru !== null) {
    foreach (perangkat_mapel_pisah((string) ($guru['mapel'] ?? '')) as $m) {
        if (perangkat_kunci($m) === $kunci) {
            $mapel = $m;
            break;
        }
    }
}
$baris = $guru !== null && $kunci !== '' ? perangkat_baris($guruId, $kunci) : null;
if ($mapel === '' && $baris !== null) {
    $mapel = (string) $baris['mapel'];
}
// Berkas KELAS yang akan diunduh (v27) — berkas tiap kelas boleh berbeda.
$berkasKelas = null;
$namaKelas = '';
if ($baris !== null) {
    [$berkasKelas, $namaKelas] = perangkat_kelas_berkas($baris, $kelasKunci, $jenis, $taKunci, $semKunci);
}

$labelJenis = (string) (PERANGKAT_JENIS[$jenis] ?? 'Berkas');
$galat = '';
$pesan = '';

// --- guru/mata pelajaran tidak sah → halaman ramah (bukan unduhan kosong) ----------------------
if ($guru === null || $kunci === '' || $mapel === '') {
    http_response_code(404);
    perangkat_unduh_tampil('Berkas tidak ditemukan',
        'Berkas perangkat guru yang Anda minta tidak tersedia (mungkin sudah dipindahkan atau '
        . 'mata pelajarannya belum dicatat pengelola madrasah).', '', '', '', 'err');
    exit;
}

// --- POST: periksa kode download ----------------------------------------------------------------
$streamUrl = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_sah()) {
        csrf_gagal();   // 419
    } else {
        $keadaan = perangkat_coba_keadaan();
        if ($keadaan['habis']) {
            $galat = 'Percobaan kode terlalu banyak. Coba lagi dalam ' . $keadaan['menit'] . ' menit.';
        } elseif (perangkat_kode($mapel) === '') {
            $galat = 'Kode download untuk mata pelajaran ini belum diatur pengelola madrasah. '
                . 'Silakan hubungi admin madrasah.';
        } elseif (perangkat_kode_cocok($mapel, (string) ($_POST['kode'] ?? ''))) {
            perangkat_coba_reset();
            // Pola POST→Redirect→GET: kode tidak tersimpan di riwayat peramban.
            redirect('perangkat_unduh.php?g=' . $guruId . '&m=' . rawurlencode($kunci)
                . '&j=' . rawurlencode($jenis)
                . ($kelasKunci !== '' ? '&k=' . rawurlencode($kelasKunci) : '')
                . ($taKunci !== '' ? '&ta=' . rawurlencode($taKunci) : '')
                . ($semKunci !== '' ? '&sm=' . rawurlencode($semKunci) : '')
                . '&kode=1');
        } else {
            perangkat_coba_catat();
            $sisa = perangkat_coba_keadaan();
            $galat = 'Kode download salah.'
                . ($sisa['habis']
                    ? ' Percobaan sudah habis — coba lagi dalam ' . $sisa['menit'] . ' menit.'
                    : ' Sisa percobaan: ' . $sisa['sisa'] . ' kali.');
        }
    }
}

// --- kirim berkas (hanya bila kode sudah benar pada permintaan sebelumnya) ----------------------
if (isset($_GET['kode'])) {
    $url = $berkasKelas !== null ? trim((string) ($berkasKelas[$jenis] ?? '')) : '';
    if ($url === '') {
        $pesan = 'Berkas ' . $labelJenis . ' untuk ' . ($namaKelas !== '' ? 'kelas ' . $namaKelas . ' ' : '')
            . 'mata pelajaran ini belum diunggah pengelola madrasah.';
    } else {
        $namaUnduh = perangkat_nama_unduh($jenis, $namaGuru, $mapel, $berkasKelas, $namaKelas);
        if (perangkat_kirim_berkas($url, $namaUnduh)) {
            exit;   // berkas sudah terkirim
        }
        $galat = 'Berkas gagal diambil dari penyimpanan. Silakan coba lagi sebentar lagi.';
    }
}

// Urutan argumen: judul, pesan, nama guru, mapel, galat, jenis alert, pesan info, kelas, periode.
perangkat_unduh_tampil('Unduh ' . $labelJenis, '', $namaGuru, $mapel, $galat,
    $galat !== '' ? 'err' : ($pesan !== '' ? 'info' : ''), $pesan, $namaKelas,
    perangkat_periode_label($taKunci, $semKunci));
