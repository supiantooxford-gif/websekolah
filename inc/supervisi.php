<?php
/**
 * INFORMASI "SUPERVISI AKADEMIK" (menu Informasi → Supervisi Akademik) — TAMPILAN DASHBOARD
 * aplikasi Supervisi Akademik untuk **MA. Sirojuth Tholibiin Mootilango**.
 *
 * Permintaan pemilik:
 *   "Pada bagian menu informasi bagian supervisi akademik buat isinya tampilan dashboard aplikasi
 *    supervisi (Khusus MA. Sirojuth Tholibiin Mootilango), Supervisi terbaru, komentar dan hasil
 *    tinjauan dokumen tidak perlu ditampilkan. Pengunjung hanya bisa melihat tetapi tidak bisa
 *    mencetak. Tampilkan Filter Tahun Ajaran dan Semesternya."
 * Pilihan pemilik (JANGAN diubah tanpa bertanya):
 *   1. kartu **Peringkat Guru** ditampilkan APA ADANYA (nama guru + nilainya);
 *   2. **Progres Pengiriman Dokumen** TIDAK ditampilkan (halaman ini hanya hasil supervisi akademik);
 *   3. **tombol-tombol aksi** dashboard (Nilai/Daftar/Rekap/Mulai Supervisi) DIHILANGKAN;
 *   4. **Supervisi Terbaru**, **Komentar Pengawas**, dan **Hasil Tinjauan Dokumen** tidak ditampilkan;
 *   5. pengunjung **hanya melihat — TIDAK bisa mencetak** (tidak ada tombol cetak, dan saat
 *      dicetak tabel rinci/grafik sengaja disembunyikan).
 *
 * PENTING / JANGAN DIUBAH tanpa alasan kuat:
 *  1. Modul ini TIDAK memuat kode aplikasi Supervisi Guru (`supervisi-guru/inc/init.php`) —
 *     konstanta & fungsinya akan bertabrakan dengan aplikasi ini. Seluruh pembacaan ditulis ulang
 *     di sini memakai koneksi TERPISAH yang HANYA-BACA (`mode=ro`, cadangan `query_only=1`).
 *  2. Hanya tabel `sekolah`, `guru`, `instrumen`, dan `supervisi` yang dibaca. Pemilik meminta
 *     data **khusus MA. Sirojuth Tholibiin Mootilango**, jadi sekolah pemilik data ditentukan
 *     dengan urutan: nama sekolah website → konstanta `SUPERVISI_SEKOLAH` → `SUPERVISI_SEKOLAH_ID`
 *     → sekolah pertama yang punya supervisi final.
 *  3. Basis data dibuka hanya bila berkasnya ada; bila tidak, halaman menampilkan keterangan ramah
 *     (BUKAN Fatal error).
 */
declare(strict_types=1);

// =====================================================================================
// KONEKSI BACA-SAJA ke basis data aplikasi Supervisi Guru
// =====================================================================================

/** Berkas basis data aplikasi Supervisi Guru (env → pengaturan → konstanta → bawaan). */
function svp_db_file(): string
{
    static $berkas = null;
    if ($berkas !== null) {
        return $berkas;
    }
    $kandidat = [];
    $env = trim((string) getenv('WEBSK_SUPERVISI_DB'));
    if ($env !== '') {
        $kandidat[] = $env;
    }
    if (function_exists('pengaturan')) {
        $kustom = trim(pengaturan('supervisi_db', ''));
        if ($kustom !== '') {
            $kandidat[] = $kustom;
        }
    }
    if (defined('SUPERVISI_DB_FILE')) {
        $kandidat[] = (string) SUPERVISI_DB_FILE;
    }
    $kandidat[] = dirname(__DIR__) . '/supervisi-guru/data/supervisi.sqlite';

    foreach ($kandidat as $k) {
        if ($k !== '' && is_file($k) && is_readable($k)) {
            return $berkas = $k;
        }
    }
    return $berkas = '';
}

/** Koneksi BACA-SAJA (null bila tidak tersedia). */
function svp_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo instanceof PDO ? $pdo : null;
    }
    $pdo = null;
    $berkas = svp_db_file();
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
            $coba->query('SELECT COUNT(*) FROM supervisi')->fetchColumn();
            $coba->query('SELECT COUNT(*) FROM guru')->fetchColumn();
            $pdo = $coba;
            return $pdo;
        } catch (Throwable $e) {
            // coba cara berikutnya
        }
    }
    return null;
}

/** Apakah sumber data Supervisi tersedia? */
function svp_siap(): bool
{
    return svp_db() instanceof PDO;
}

function svp_all(string $sql, array $param = []): array
{
    $pdo = svp_db();
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

function svp_one(string $sql, array $param = []): ?array
{
    $pdo = svp_db();
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

function svp_nilai(string $sql, array $param = [], $bawaan = 0)
{
    $r = svp_one($sql, $param);
    if (!$r) {
        return $bawaan;
    }
    $v = reset($r);
    return $v === null ? $bawaan : $v;
}

// =====================================================================================
// SEKOLAH PEMILIK DATA (khusus MA. Sirojuth Tholibiin Mootilango)
// =====================================================================================

/** Nama untuk pencocokan (huruf kecil, hanya huruf/angka). */
function svp_nama_kunci(string $teks): string
{
    $t = strtolower(trim($teks));
    return preg_replace('/[^a-z0-9]+/', '', $t) ?? '';
}

/**
 * Sekolah/madrasah pada basis data Supervisi yang datanya ditampilkan.
 * Di-cache per permintaan. Urutannya: nama sekolah website → konstanta `SUPERVISI_SEKOLAH` →
 * konstanta `SUPERVISI_SEKOLAH_ID` → sekolah pertama yang punya supervisi final.
 */
function svp_sekolah(): ?array
{
    static $s = null;
    if ($s !== null) {
        return $s === false ? null : $s;
    }
    $s = false;
    if (!svp_siap()) {
        return null;
    }
    $semua = svp_all('SELECT * FROM sekolah ORDER BY id');
    if (!$semua) {
        return null;
    }
    $kandidat = [];
    if (function_exists('sekolah')) {
        $n = trim((string) (sekolah()['nama'] ?? ''));
        if ($n !== '') {
            $kandidat[] = $n;
        }
    }
    if (defined('SUPERVISI_SEKOLAH')) {
        $kandidat[] = (string) SUPERVISI_SEKOLAH;
    }
    foreach ($kandidat as $nama) {
        $kunci = svp_nama_kunci($nama);
        if ($kunci === '') {
            continue;
        }
        foreach ($semua as $r) {
            if (svp_nama_kunci((string) $r['nama']) === $kunci) {
                return $s = $r;
            }
        }
    }
    if (defined('SUPERVISI_SEKOLAH_ID')) {
        foreach ($semua as $r) {
            if ((int) $r['id'] === (int) SUPERVISI_SEKOLAH_ID) {
                return $s = $r;
            }
        }
    }
    $r = svp_one('SELECT k.* FROM sekolah k
        JOIN (SELECT sekolah_id, COUNT(*) n FROM supervisi WHERE status = \'final\' GROUP BY sekolah_id
              ORDER BY n DESC LIMIT 1) t ON t.sekolah_id = k.id');
    return $s = ($r ?: null);
}

// =====================================================================================
// PENOLONG NILAI & TANGGAL
// =====================================================================================

/** Angka berkoma → teks (mis. 83,4). */
function svp_r1($v): string
{
    return number_format((float) $v, 1, ',', '.');
}

/** Angka bulat → teks berkelompok. */
function svp_angka($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

function svp_tanggal_id(?string $ymd): string
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

/** Predikat dari nilai memakai ambang sekolah pada basis data Supervisi. */
function svp_predikat(float $nilai, ?array $sek): string
{
    if ($nilai <= 0) {
        return '';
    }
    $a = (float) ($sek['batas_a'] ?? 90);
    $b = (float) ($sek['batas_b'] ?? 80);
    $c = (float) ($sek['batas_c'] ?? 70);
    if ($nilai >= $a) {
        return 'A';
    }
    if ($nilai >= $b) {
        return 'B';
    }
    if ($nilai >= $c) {
        return 'C';
    }
    return 'D';
}

/** Label predikat seperti pada aplikasi Supervisi Guru. */
function svp_predikat_label(string $p): string
{
    switch ($p) {
        case 'A': return 'Amat Baik';
        case 'B': return 'Baik';
        case 'C': return 'Cukup';
        case 'D': return 'Perlu Pembinaan';
        default:  return 'Belum ada';
    }
}

// =====================================================================================
// FILTER TAHUN AJARAN & SEMESTER
// =====================================================================================

/**
 * Pisahkan teks `supervisi.periode` (mis. "2026/2027 Ganjil") menjadi tahun ajaran + semester.
 * Periode yang tidak berpola tahun ajaran diperlakukan sebagai satu kesatuan (semester kosong).
 */
function svp_periode_pisah(string $periode): array
{
    $p = trim($periode);
    if ($p === '') {
        return ['ta' => '', 'sem' => ''];
    }
    if (preg_match('#^(\d{4}\s*/\s*\d{4})\s+(.+)$#', $p, $m)) {
        return ['ta' => trim($m[1]), 'sem' => trim($m[2])];
    }
    return ['ta' => $p, 'sem' => ''];
}

/** Daftar periode yang BENAR-BENAR tercatat (urut tahun ajaran terbaru lebih dulu). */
function svp_periode_list(): array
{
    static $daftar = null;
    if ($daftar !== null) {
        return $daftar;
    }
    $sek = svp_sekolah();
    if (!$sek) {
        return $daftar = [];
    }
    $rows = svp_all('SELECT periode, COUNT(*) AS n FROM supervisi WHERE sekolah_id = ?
                     GROUP BY periode ORDER BY periode DESC', [(int) $sek['id']]);
    $keluar = [];
    foreach ($rows as $r) {
        $p = svp_periode_pisah((string) $r['periode']);
        $keluar[] = ['periode' => (string) $r['periode'], 'ta' => $p['ta'], 'sem' => $p['sem'],
                     'n' => (int) $r['n']];
    }
    return $daftar = $keluar;
}

/** Daftar tahun ajaran yang tersedia (unik). */
function svp_tahun_list(): array
{
    $ta = [];
    foreach (svp_periode_list() as $p) {
        if ($p['ta'] !== '' && !in_array($p['ta'], $ta, true)) {
            $ta[] = $p['ta'];
        }
    }
    return $ta;
}

/** Daftar semester yang tersedia (bila $ta diisi → hanya semester pada tahun ajaran itu). */
function svp_semester_list(string $ta = ''): array
{
    $sem = [];
    foreach (svp_periode_list() as $p) {
        if ($p['sem'] === '') {
            continue;
        }
        if ($ta !== '' && $p['ta'] !== $ta) {
            continue;
        }
        if (!in_array($p['sem'], $sem, true)) {
            $sem[] = $p['sem'];
        }
    }
    return $sem;
}

/**
 * Saringan Tahun Ajaran + Semester dari alamat. Nilai yang tidak dikenal DIABAIKAN (bukan 403),
 * lalu jatuh ke periode yang paling banyak datanya (bawaan).
 */
function svp_filter(): array
{
    $sek = svp_sekolah();
    $per = svp_periode_list();
    $ta = trim((string) ($_GET['ta'] ?? ''));
    $sem = trim((string) ($_GET['sem'] ?? ''));

    $sahTa = svp_tahun_list();
    if ($ta !== '' && !in_array($ta, $sahTa, true)) {
        $ta = '';
    }
    $sahSem = svp_semester_list($ta);
    if ($sem !== '' && !in_array($sem, $sahSem, true)) {
        $sem = '';
    }

    // Bawaan: periode dengan jumlah supervisi terbanyak, dicadangkan ke tahun ajaran & semester
    // yang tercatat pada identitas sekolah di aplikasi Supervisi.
    if ($ta === '' && $sem === '') {
        $pilih = null;
        foreach ($per as $p) {
            if ($pilih === null || $p['n'] > $pilih['n']) {
                $pilih = $p;
            }
        }
        if ($pilih !== null) {
            $ta = $pilih['ta'];
            $sem = in_array($pilih['sem'], svp_semester_list($ta), true) ? $pilih['sem'] : '';
        } elseif ($sek) {
            $ta = trim((string) ($sek['tahun_ajaran'] ?? ''));
            $s = trim((string) ($sek['semester'] ?? ''));
            $sem = in_array($s, svp_semester_list($ta), true) ? $s : '';
        }
    }

    return ['ta' => $ta, 'sem' => $sem];
}

/**
 * Klausa periode (tanpa parameter) untuk saringan yang aktif; `''` bila tanpa saringan.
 * Dipakai konsisten oleh seluruh query dashboard (termasuk klausa terbenam NOT EXISTS) supaya
 * "guru belum disupervisi" dihitung pada periode yang sama dengan angka lainnya.
 */
function svp_klausa_periode(array $f, string $alias = 's.'): string
{
    if ($f['ta'] !== '') {
        if ($f['sem'] !== '') {
            return $alias . 'periode = ' . svp_quote($f['ta'] . ' ' . $f['sem']);
        }
        return '(' . $alias . 'periode = ' . svp_quote($f['ta'])
            . ' OR ' . $alias . 'periode LIKE ' . svp_quote($f['ta'] . ' %') . ')';
    }
    if ($f['sem'] !== '') {
        // Semester saja (mis. seluruh semester "Ganjil" dari semua tahun ajaran).
        return '(' . $alias . 'periode = ' . svp_quote($f['sem'])
            . ' OR ' . $alias . 'periode LIKE ' . svp_quote('% ' . $f['sem']) . ')';
    }
    return '';
}

/** Klausa WHERE (tanpa kata WHERE) + parameternya untuk saringan yang aktif. */
function svp_where(array $f): array
{
    $sek = svp_sekolah();
    if (!$sek) {
        return ['1 = 0', []];
    }
    $w = ['s.sekolah_id = ?'];
    $p = [(int) $sek['id']];
    $kPer = svp_klausa_periode($f);
    if ($kPer !== '') {
        $w[] = $kPer;
    }
    return [implode(' AND ', $w), $p];
}

/** Label periode yang sedang dilihat. */
function svp_periode_label(array $f): string
{
    if ($f['ta'] === '') {
        return 'Seluruh periode';
    }
    return 'Tahun Ajaran ' . $f['ta'] . ($f['sem'] !== '' ? ' · Semester ' . $f['sem'] : ' · Semua semester');
}

// =====================================================================================
// DATA DASHBOARD
// =====================================================================================

/**
 * Seluruh angka dashboard untuk saringan yang aktif (semua query dibatasi sekolah + periode).
 * Mengikuti dashboard aplikasi Supervisi Guru, TANPA bagian yang tidak diminta pemilik
 * (Supervisi Terbaru, komentar pengawas, tinjauan dokumen, progres kirim dokumen).
 */
function svp_data(array $f): array
{
    $sek = svp_sekolah();
    $sid = $sek ? (int) $sek['id'] : 0;
    [$where, $wp] = svp_where($f);
    $kPer = svp_klausa_periode($f);
    $final = $where . ' AND s.status = \'final\'';

    $totalGuru = (int) svp_nilai('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
    $totDraft = (int) svp_nilai('SELECT COUNT(*) FROM supervisi s WHERE ' . $where . ' AND s.status = \'draft\'', $wp, 0);
    $totalSuper = (int) svp_nilai('SELECT COUNT(*) FROM supervisi s WHERE ' . $final, $wp, 0);
    $rataAll = (float) svp_nilai('SELECT AVG(s.nilai) FROM supervisi s WHERE ' . $final, $wp, 0);
    $guruBelum = (int) svp_nilai('SELECT COUNT(*) FROM guru g WHERE g.aktif = 1 AND g.sekolah_id = ?
        AND NOT EXISTS (SELECT 1 FROM supervisi s WHERE s.guru_id = g.id AND s.status = \'final\'
                        AND s.sekolah_id = g.sekolah_id'
        . ($kPer !== '' ? ' AND ' . $kPer : '')
        . ')', [$sid], 0);

    // Kartu & grafik per menu supervisi (4 menu aktif, sama seperti dashboard aplikasi Supervisi).
    $perInstrumen = svp_all('SELECT i.id, i.kode, i.nama, i.ikon, i.deskripsi,
            (SELECT COUNT(*) FROM supervisi s WHERE s.instrumen_id = i.id AND s.status = \'final\' AND ' . $where . ') AS jml,
            (SELECT AVG(s.nilai) FROM supervisi s WHERE s.instrumen_id = i.id AND s.status = \'final\' AND ' . $where . ') AS rata,
            (SELECT COUNT(DISTINCT s.guru_id) FROM supervisi s WHERE s.instrumen_id = i.id AND s.status = \'final\' AND ' . $where . ') AS jml_guru
        FROM instrumen i WHERE i.aktif = 1 ORDER BY i.urutan, i.id',
        array_merge($wp, $wp, $wp));

    $distribusi = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
    foreach (svp_all('SELECT s.predikat, COUNT(*) c FROM supervisi s WHERE ' . $final . ' GROUP BY s.predikat', $wp) as $r) {
        if (isset($distribusi[(string) $r['predikat']])) {
            $distribusi[(string) $r['predikat']] = (int) $r['c'];
        }
    }

    // Tren 8 bulan terakhir (hanya data pada periode terpilih yang masuk).
    $tren = [];
    for ($i = 7; $i >= 0; $i--) {
        $tren[date('Y-m', strtotime("-$i month"))] = [];
    }
    foreach (svp_all('SELECT substr(s.tanggal, 1, 7) b, s.nilai FROM supervisi s WHERE ' . $final, $wp) as $r) {
        if (isset($tren[(string) $r['b']])) {
            $tren[(string) $r['b']][] = (float) $r['nilai'];
        }
    }

    // Peringkat guru (nama + nilai) — permintaan pemilik: ditampilkan apa adanya.
    $perGuru = svp_all('SELECT g.id, g.nama, g.mapel, g.jabatan, AVG(s.nilai) rata, COUNT(s.id) jml
        FROM guru g JOIN supervisi s ON s.guru_id = g.id AND s.status = \'final\' AND s.sekolah_id = g.sekolah_id
        WHERE ' . $where . ' AND g.sekolah_id = ?
        GROUP BY g.id ORDER BY rata DESC LIMIT 10', array_merge($wp, [$sid]));

    $perMapel = svp_all('SELECT COALESCE(NULLIF(g.mapel, \'\'), \'Belum diisi\') mapel, AVG(s.nilai) rata, COUNT(s.id) jml
        FROM guru g JOIN supervisi s ON s.guru_id = g.id AND s.status = \'final\' AND s.sekolah_id = g.sekolah_id
        WHERE ' . $where . ' AND g.sekolah_id = ?
        GROUP BY mapel HAVING COUNT(s.id) > 0 ORDER BY rata DESC LIMIT 8', array_merge($wp, [$sid]));

    $belumList = svp_all('SELECT g.* FROM guru g WHERE g.aktif = 1 AND g.sekolah_id = ? AND NOT EXISTS
        (SELECT 1 FROM supervisi s WHERE s.guru_id = g.id AND s.status = \'final\' AND s.sekolah_id = g.sekolah_id'
        . ($kPer !== '' ? ' AND ' . $kPer : '')
        . ') ORDER BY g.nama LIMIT 8', [$sid]);

    $dom = 'A';
    $maks = 0;
    foreach ($distribusi as $k => $v) {
        if ($v > $maks) {
            $maks = $v;
            $dom = $k;
        }
    }

    return [
        'sekolah' => $sek,
        'totalGuru' => $totalGuru,
        'guruBelum' => $guruBelum,
        'totalSuper' => $totalSuper,
        'totDraft' => $totDraft,
        'rataAll' => $rataAll,
        'distribusi' => $distribusi,
        'predikatTerbanyak' => $maks > 0 ? $dom : '',
        'predikatJumlah' => $maks,
        'perInstrumen' => $perInstrumen,
        'tren' => $tren,
        'perGuru' => $perGuru,
        'perMapel' => $perMapel,
        'belumList' => $belumList,
    ];
}

/** Kutip nilai string untuk SQLite (dipakai pada klausa terbenam NOT EXISTS). */
function svp_quote(string $teks): string
{
    return "'" . str_replace("'", "''", $teks) . "'";
}

// =====================================================================================
// TAMPILAN
// =====================================================================================

/** Tautan halaman ini + saringan (RELATIF — aplikasi dilayani pada subfolder). */
function svp_tautan(array $q = []): string
{
    $kanonik = function_exists('rute_kanonik') ? rute_kanonik() : '';
    if (function_exists('rute_tautan')) {
        return rute_tautan($kanonik !== '' ? $kanonik : 'halaman/supervis-akademik', $q);
    }
    $q = array_filter($q, static fn ($v): bool => (string) $v !== '');
    $t = $kanonik !== '' ? $kanonik : 'halaman/supervis-akademik';
    return $q ? $t . '?' . http_build_query($q) : $t;
}

/** Keterangan ramah bila sumber data aplikasi Supervisi tidak dapat dibaca. */
function svp_kosong_sumber(): void
{
    echo '<section class="sv-blok">'
        . '<div class="sv-kosong">' . ikon('clipboard', 34)
        . '<h3>Data Supervisi Akademik belum dapat ditampilkan</h3>'
        . '<p>Halaman ini menampilkan <b>dashboard Supervisi Akademik</b> (hasil supervisi, peringkat
             guru, dan capaian tiap menu supervisi) yang datanya diambil otomatis dari aplikasi
             <b>Supervisi Akademik</b>. Sumber datanya belum dapat dibaca saat ini.</p>'
        . '</div></section>';
}

/** Grafik donat predikat (label + jumlah + warna). */
function svp_dist_arr(array $distribusi): array
{
    $warna = ['A' => '#15795a', 'B' => '#2f7fb5', 'C' => '#c9a227', 'D' => '#b3261e'];
    $keluar = [];
    foreach (['A', 'B', 'C', 'D'] as $p) {
        if (($distribusi[$p] ?? 0) > 0) {
            $keluar[] = ['l' => $p . ' — ' . svp_predikat_label($p), 'v' => (int) $distribusi[$p], 'c' => $warna[$p]];
        }
    }
    return $keluar;
}

/**
 * Mencetak DASHBOARD Supervisi Akademik (menu Informasi → Supervisi Akademik).
 */
function supervisi_papan(): void
{
    if (!svp_siap() || !svp_sekolah()) {
        echo '<div class="sv-papan" id="papanSupervisi">';
        svp_kosong_sumber();
        echo '</div>';
        return;
    }

    $f = svp_filter();
    $d = svp_data($f);
    $sek = $d['sekolah'];
    $distArr = svp_dist_arr($d['distribusi']);
    $tahunAda = svp_tahun_list();
    $semAda = svp_semester_list($f['ta']);
    $rataTren = array_map(static fn ($v) => $v ? round(array_sum($v) / count($v), 2) : 0, array_values($d['tren']));
    ?>
<div class="sv-papan" id="papanSupervisi">

  <section class="sv-blok">
    <div class="sv-kepala">
      <div>
        <h2><?= ikon('clipboard', 20) ?> Dashboard Supervisi Akademik</h2>
        <p><b><?= e((string) $sek['nama']) ?></b>
           <?= ((string) ($sek['jenis'] ?? '')) === 'madrasah' ? ' · Madrasah' : ' · Sekolah' ?>
           &middot; <?= e(svp_periode_label($f)) ?>. Angkanya diambil otomatis dari aplikasi
           <b>Supervisi Akademik</b>, jadi ikut terbarui setiap kali supervisor menilai.</p>
      </div>
      <div class="sv-chip">
        <span><?= e(svp_angka($d['totalSuper'])) ?> supervisi selesai</span>
        <span><?= e(svp_angka($d['totalGuru'])) ?> guru aktif</span>
      </div>
    </div>

    <?php /* ============ FILTER TAHUN AJARAN & SEMESTER (permintaan pemilik) ============ */ ?>
    <form class="sv-saring" method="get" action="<?= e(svp_tautan()) ?>" id="formSaringSupervisi">
      <span class="sv-saring-judul"><?= ikon('kalender', 16) ?> Saringan periode</span>
      <label for="svTahun">Tahun Ajaran</label>
      <select id="svTahun" name="ta" data-auto>
        <option value="">Semua tahun ajaran</option>
        <?php foreach ($tahunAda as $ta): ?>
          <option value="<?= e($ta) ?>" <?= $f['ta'] === $ta ? 'selected' : '' ?>><?= e($ta) ?></option>
        <?php endforeach; ?>
      </select>
      <label for="svSemester">Semester</label>
      <select id="svSemester" name="sem" data-auto>
        <option value="">Semua semester</option>
        <?php foreach (svp_semester_list() as $sem): ?>
          <option value="<?= e($sem) ?>" <?= $f['sem'] === $sem ? 'selected' : '' ?>
            <?= !in_array($sem, $semAda, true) ? 'disabled' : '' ?>><?= e($sem) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="tombol tombol-kecil"><?= ikon('search', 15) ?> Tampilkan</button>
      <?php if ($f['ta'] !== '' || $f['sem'] !== ''): ?>
        <a class="tombol tombol-garis tombol-kecil" href="<?= e(svp_tautan()) ?>">Reset</a>
      <?php endif; ?>
      <span class="sv-saring-periode"><?= e(svp_periode_label($f)) ?></span>
    </form>

    <?php /* ============ KARTU STATISTIK ============ */ ?>
    <div class="sv-grid sv-g4">
      <div class="sv-kartu sv-kartu-stat">
        <span class="sv-ik"><?= ikon('users', 19) ?></span>
        <div>
          <div class="sv-num"><?= e(svp_angka($d['totalGuru'])) ?></div>
          <div class="sv-lbl">Guru Aktif</div>
          <div class="sv-hint"><?= e(svp_angka($d['guruBelum'])) ?> belum disupervisi</div>
        </div>
      </div>
      <div class="sv-kartu sv-kartu-stat">
        <span class="sv-ik s"><?= ikon('check', 19) ?></span>
        <div>
          <div class="sv-num"><?= e(svp_angka($d['totalSuper'])) ?></div>
          <div class="sv-lbl">Supervisi Selesai</div>
          <div class="sv-hint"><?= e(svp_angka($d['totDraft'])) ?> masih draf</div>
        </div>
      </div>
      <div class="sv-kartu sv-kartu-stat">
        <span class="sv-ik g"><?= ikon('grafik', 19) ?></span>
        <div>
          <div class="sv-num"><?= e(svp_r1($d['rataAll'])) ?></div>
          <div class="sv-lbl">Rata-rata Nilai</div>
          <div class="sv-hint">Dari <?= e(svp_angka($d['totalSuper'])) ?> supervisi</div>
        </div>
      </div>
      <div class="sv-kartu sv-kartu-stat">
        <span class="sv-ik o"><?= ikon('star', 19) ?></span>
        <div>
          <div class="sv-num"><?= $d['predikatTerbanyak'] !== '' ? e($d['predikatTerbanyak']) : '-' ?></div>
          <div class="sv-lbl">Predikat Terbanyak</div>
          <div class="sv-hint"><?= $d['predikatTerbanyak'] !== ''
            ? e(svp_predikat_label($d['predikatTerbanyak'])) . ' · ' . e(svp_angka($d['predikatJumlah'])) . ' supervisi'
            : 'Belum ada data' ?></div>
        </div>
      </div>
    </div>

    <?php /* ============ KARTU PER MENU SUPERVISI (tanpa tombol aksi) ============ */ ?>
    <div class="sv-grid sv-g4">
      <?php foreach ($d['perInstrumen'] as $i):
          $rata = $i['rata'] !== null ? (float) $i['rata'] : 0;
          $pred = svp_predikat($rata, $sek);
          $cakupan = $d['totalGuru'] > 0 ? (int) round((int) $i['jml_guru'] / $d['totalGuru'] * 100) : 0;
          ?>
        <div class="sv-kartu sv-kartu-menu">
          <div class="sv-menu-kepala">
            <span class="sv-ik"><?= ikon((string) ($i['ikon'] ?: 'clipboard'), 17) ?></span>
            <b><?= e((string) $i['nama']) ?></b>
          </div>
          <div class="sv-menu-nilai">
            <span class="sv-num2"><?= e(svp_r1($rata)) ?></span>
            <span class="sv-pil <?= $pred !== '' ? 'sv-pil-' . e($pred) : 'sv-pil-gray' ?>"><?= $pred !== '' ? e($pred) : 'Belum ada' ?></span>
          </div>
          <div class="sv-bar"><i style="width:<?= min(100, max(0, $rata)) ?>%"></i></div>
          <div class="sv-xs"><?= e(svp_angka($i['jml'])) ?> penilaian &middot; <?= $cakupan ?>% guru terjangkau</div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php /* ============ GRAFIK ============ */ ?>
    <div class="sv-grid sv-g21">
      <div class="sv-kartu">
        <div class="sv-kartu-kepala">
          <div><h3>Rata-rata Nilai per Menu Supervisi</h3><p>Perbandingan capaian tiap jenis supervisi.</p></div>
          <span class="sv-pil sv-pil-gray">Skala 0-100</span>
        </div>
        <div class="sv-chart tinggi"><canvas id="svInstrumen"></canvas></div>
      </div>
      <div class="sv-kartu">
        <div class="sv-kartu-kepala"><div><h3>Distribusi Predikat</h3><p>Sebaran mutu hasil supervisi.</p></div></div>
        <?php if ($distArr): ?>
          <div class="sv-chart tinggi"><canvas id="svDonut"></canvas></div>
        <?php else: ?>
          <div class="sv-kosong sv-kosong-kecil"><p>Belum ada data pada periode ini.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="sv-grid sv-g21">
      <div class="sv-kartu">
        <div class="sv-kartu-kepala"><div><h3>Tren Rata-rata Nilai</h3><p>Delapan bulan terakhir.</p></div></div>
        <div class="sv-chart"><canvas id="svTren"></canvas></div>
      </div>
      <div class="sv-kartu">
        <div class="sv-kartu-kepala"><div><h3>Rata-rata per Mata Pelajaran</h3><p>Delapan mata pelajaran teratas.</p></div></div>
        <?php if ($d['perMapel']): ?>
          <div class="sv-chart"><canvas id="svMapel"></canvas></div>
        <?php else: ?>
          <div class="sv-kosong sv-kosong-kecil"><p>Belum ada data pada periode ini.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="sv-grid sv-g21">
      <?php /* Peringkat guru memuat nama guru — pemilik meminta ditampilkan apa adanya; namun
              tabelnya DILARANG ikut tercetak (pengunjung hanya boleh melihat). */ ?>
      <div class="sv-kartu sv-tanpa-cetak">
        <div class="sv-kartu-kepala">
          <div><h3>Peringkat Guru</h3><p>Berdasarkan rata-rata seluruh supervisi (nilai final).</p></div>
        </div>
        <?php if ($d['perGuru']): ?>
          <div class="sv-tabel-wrap">
            <table class="sv-tabel">
              <thead>
                <tr><th class="sv-ctr sv-no">#</th><th>Guru</th><th class="sv-ctr">Supervisi</th>
                    <th class="sv-nilai">Nilai rata-rata</th><th class="sv-ctr">Predikat</th></tr>
              </thead>
              <tbody>
              <?php foreach ($d['perGuru'] as $ix => $g): $pred = svp_predikat((float) $g['rata'], $sek); ?>
                <tr>
                  <td class="sv-ctr"><b><?= $ix + 1 ?></b></td>
                  <td><b><?= e((string) $g['nama']) ?></b>
                      <span class="sv-sub"><?= e((string) ($g['mapel'] ?: '-')) ?><?= $g['jabatan'] !== '' ? ' &middot; ' . e((string) $g['jabatan']) : '' ?></span></td>
                  <td class="sv-ctr"><?= (int) $g['jml'] ?></td>
                  <td>
                    <b><?= e(svp_r1($g['rata'])) ?></b>
                    <div class="sv-bar"><i style="width:<?= min(100, (float) $g['rata']) ?>%"></i></div>
                  </td>
                  <td class="sv-ctr"><span class="sv-pil <?= $pred !== '' ? 'sv-pil-' . e($pred) : 'sv-pil-gray' ?>"><?= $pred !== '' ? e($pred) : '-' ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="sv-kosong sv-kosong-kecil"><?= ikon('grafik', 28) ?><h3>Belum ada nilai</h3>
            <p>Belum ada supervisi final pada <?= e(svp_periode_label($f)) ?>.</p></div>
        <?php endif; ?>
      </div>

      <div class="sv-kartu sv-tanpa-cetak">
        <div class="sv-kartu-kepala">
          <div><h3>Perlu Ditindaklanjuti</h3><p>Guru yang belum pernah disupervisi pada periode ini.</p></div>
        </div>
        <?php if ($d['belumList']): ?>
          <div class="sv-daftar">
            <?php foreach ($d['belumList'] as $g): ?>
              <div class="sv-daftar-item">
                <div>
                  <b><?= e((string) $g['nama']) ?></b>
                  <div class="sv-xs"><?= e((string) ($g['mapel'] ?: '-')) ?> &middot; <?= e((string) ($g['jabatan'] ?: '-')) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($d['guruBelum'] > count($d['belumList'])): ?>
            <p class="sv-xs" style="margin-top:12px">Menampilkan <?= count($d['belumList']) ?> dari
              <?= e(svp_angka($d['guruBelum'])) ?> guru yang belum disupervisi.</p>
          <?php endif; ?>
        <?php else: ?>
          <div class="sv-kosong sv-kosong-kecil"><?= ikon('check', 28) ?><h3>Semua terjangkau</h3>
            <p>Seluruh guru aktif sudah pernah disupervisi pada periode ini.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <p class="sv-ket">Halaman ini hanya untuk <b>melihat</b> — laporan &amp; dokumen cetak supervisi
       tersedia pada aplikasi <b>Supervisi Akademik</b>.</p>
  </section>

  <script src="assets/vendor/chart.umd.min.js?v=<?= e(APP_VERSION) ?>"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (!window.SVP) { return; }
      var instrumen = <?= json_encode(array_map(static fn ($i) => (string) $i['nama'], $d['perInstrumen']), JSON_UNESCAPED_UNICODE) ?>;
      var rataInstrumen = <?= json_encode(array_map(static fn ($i) => $i['rata'] !== null ? round((float) $i['rata'], 2) : 0, $d['perInstrumen']), JSON_UNESCAPED_UNICODE) ?>;
      SVP.bar('svInstrumen', instrumen, rataInstrumen, 'Nilai rata-rata');
      var dist = <?= json_encode($distArr, JSON_UNESCAPED_UNICODE) ?>;
      if (dist.length) {
        SVP.donut('svDonut', dist.map(function (d) { return d.l; }), dist.map(function (d) { return d.v; }), dist.map(function (d) { return d.c; }));
      }
      SVP.line('svTren', <?= json_encode(array_map(static fn ($k) => date('M Y', strtotime($k . '-01')), array_keys($d['tren'])), JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($rataTren, JSON_UNESCAPED_UNICODE) ?>);
      var mapel = <?= json_encode(array_map(static fn ($m) => (string) $m['mapel'], $d['perMapel']), JSON_UNESCAPED_UNICODE) ?>;
      var rataMapel = <?= json_encode(array_map(static fn ($m) => round((float) $m['rata'], 2), $d['perMapel']), JSON_UNESCAPED_UNICODE) ?>;
      if (mapel.length) { SVP.bar('svMapel', mapel, rataMapel, 'Nilai rata-rata'); }
    });
  </script>
</div>
    <?php
}
