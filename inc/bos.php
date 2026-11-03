<?php
/**
 * INFORMASI BOS — Papan Informasi penggunaan Dana BOS pada website sekolah.
 *
 * Data diambil LANGSUNG (baca-saja) dari basis data aplikasi e-BOS yang ada di server yang sama
 * (`<workspace>/e-bos/data/ebos.sqlite`), sehingga setiap perubahan yang dilakukan pengelola di
 * aplikasi e-BOS (komponen, pagu, transaksi/pemasukan-pengeluaran, identitas lembaga) OTOMATIS
 * ikut tampil di halaman ini tanpa perlu disalin ulang.
 *
 * PENTING / JANGAN DIUBAH tanpa alasan kuat:
 *  1. Modul ini TIDAK memuat (require) kode aplikasi e-BOS — memuat `e-bos/inc/init.php` akan
 *     menabrakkan konstanta & fungsi aplikasi ini dan menjalankan sesi aplikasi lain. Seluruh
 *     pembacaan ditulis ulang di sini memakai koneksi TERPISAH yang hanya boleh membaca
 *     (`PRAGMA query_only = 1`), jadi data e-BOS tidak mungkin berubah dari halaman ini.
 *  2. Basis data e-BOS dibuka hanya bila berkasnya ada; bila tidak (aplikasi e-BOS dipindah,
 *     belum dipasang, atau berkasnya tidak dapat dibaca) halaman menampilkan keterangan ramah —
 *     BUKAN halaman kosong/ Fatal error.
 *  3. Nama & format tampilan mengikuti halaman depan e-BOS (`e-bos/index.php`) supaya isi menu
 *     "Informasi BOS" pada website sama persis dengan Papan Informasi di aplikasi e-BOS.
 */
declare(strict_types=1);

// =====================================================================================
// KONEKSI BACA-SAJA ke basis data e-BOS
// =====================================================================================

/** Berkas basis data e-BOS yang dipakai (urutan: pengaturan panel → konstanta → bawaan). */
function bos_db_file(): string
{
    static $berkas = null;
    if ($berkas !== null) {
        return $berkas;
    }
    $kandidat = [];
    // (1) dapat diarahkan lewat variabel lingkungan (dipakai skrip uji pada salinan /tmp).
    $env = trim((string) getenv('WEBSK_EBOS_DB'));
    if ($env !== '') {
        $kandidat[] = $env;
    }
    // (2) dapat diarahkan admin lewat tabel `pengaturan` (kunci `bos_db`) tanpa menyunting kode.
    if (function_exists('pengaturan')) {
        $kustom = trim(pengaturan('bos_db', ''));
        if ($kustom !== '') {
            $kandidat[] = $kustom;
        }
    }
    // (3) konstanta di inc/config.php.
    if (defined('EBOS_DB_FILE')) {
        $kandidat[] = (string) EBOS_DB_FILE;
    }
    // (4) bawaan: folder aplikasi e-BOS di sebelah folder aplikasi ini.
    $kandidat[] = dirname(__DIR__) . '/e-bos/data/ebos.sqlite';

    foreach ($kandidat as $k) {
        if ($k !== '' && is_file($k) && is_readable($k)) {
            return $berkas = $k;
        }
    }
    return $berkas = '';
}

/** Koneksi BACA-SAJA ke basis data e-BOS (null bila tidak tersedia). */
function bos_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo instanceof PDO ? $pdo : null;
    }
    $pdo = null;
    $berkas = bos_db_file();
    if ($berkas === '') {
        return null;
    }
    $opsi = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    // Cara 1: mode=ro (URI) — cara paling aman. Cara 2: koneksi biasa + PRAGMA query_only,
    // dipakai bila basis data ber-jurnal WAL dan berkas -shm belum ada (mode=ro tidak bisa
    // membuatnya). Keduanya TIDAK PERNAH menulis data e-BOS.
    foreach ([
        'sqlite:file:' . $berkas . '?mode=ro',
        'sqlite:' . $berkas,
    ] as $i => $dsn) {
        try {
            $coba = new PDO($dsn, null, null, $opsi);
            $coba->exec('PRAGMA query_only = 1');
            $coba->exec('PRAGMA busy_timeout = 4000');
            // Uji ringan: tabel inti harus ada.
            $coba->query('SELECT COUNT(*) FROM tahun_anggaran')->fetchColumn();
            $pdo = $coba;
            return $pdo;
        } catch (Throwable $e) {
            if ($i === 0 && defined('EBOS_DEBUG') && EBOS_DEBUG) {
                error_log('bos_db (mode=ro) gagal: ' . $e->getMessage());
            }
        }
    }
    return null;
}

/** Apakah sumber data e-BOS tersedia? */
function bos_siap(): bool
{
    return bos_db() instanceof PDO;
}

/** Satu baris hasil query (null bila sumber data tidak tersedia / query gagal). */
function bos_one(string $sql, array $param = []): ?array
{
    $pdo = bos_db();
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

/** Seluruh baris hasil query (array kosong bila sumber data tidak tersedia / query gagal). */
function bos_all(string $sql, array $param = []): array
{
    $pdo = bos_db();
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

/** Satu nilai hasil query (null bila kosong / gagal). */
function bos_nilai(string $sql, array $param = [])
{
    $r = bos_one($sql, $param);
    if ($r === null) {
        return null;
    }
    $v = reset($r);
    return $v === false ? null : $v;
}

// =====================================================================================
// PENOLONG TAMPILAN (meniru e-BOS: format angka & label yang sama)
// =====================================================================================

/** Angka bulat dengan pemisah ribuan: "1.250.000". */
function bos_r0($v): string
{
    return number_format((float) $v, 0, ',', '.');
}

/** Rupiah: "Rp 1.250.000" (persis seperti e-BOS). */
function bos_rp($v): string
{
    return 'Rp ' . bos_r0($v);
}

/** Persentase satu desimal dengan koma: "12,5%". */
function bos_persen(float $pembilang, float $penyebut): string
{
    if ($penyebut <= 0) {
        return '0,0%';
    }
    return number_format($pembilang / $penyebut * 100, 1, ',', '.') . '%';
}

/** Pagu (rencana anggaran) sudah diisi? */
function bos_pagu_ada($pagu): bool
{
    return ((int) $pagu) > 0;
}

/** Label sisa anggaran: "-" bila pagu belum diisi (jangan menampilkan angka minus). */
function bos_sisa_label($pagu, $real): string
{
    return bos_pagu_ada($pagu) ? bos_rp((int) $pagu - (int) $real) : '-';
}

/** Label serapan: "-" bila pagu belum diisi. */
function bos_serapan_label($pagu, $real): string
{
    return bos_pagu_ada($pagu) ? bos_persen((float) $real, (float) $pagu) : '-';
}

/** Nilai identitas yang dianggap TERISI ("-"/"–"/"." tidak dicetak). */
function bos_nilai_ada(string $v): string
{
    $v = trim($v);
    return in_array($v, ['', '-', '–', '—', '.', '_'], true) ? '' : $v;
}

/** Nama bulan Indonesia (1-12). */
function bos_bulan(int $n): string
{
    $b = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus',
        'September', 'Oktober', 'November', 'Desember'];
    return $b[$n] ?? '-';
}

// =====================================================================================
// IDENTITAS LEMBAGA (dari basis data e-BOS → supaya persis sama dengan aplikasi e-BOS)
// =====================================================================================
function bos_lembaga(): array
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $s = bos_one('SELECT * FROM sekolah ORDER BY id LIMIT 1') ?? [];
    return $s;
}

function bos_setting(string $kunci, string $bawaan = ''): string
{
    $s = bos_lembaga();
    $v = $s[$kunci] ?? null;
    return $v === null ? $bawaan : (string) $v;
}

function bos_nama_lembaga(): string
{
    $n = trim(bos_setting('nama'));
    return $n !== '' ? $n : '';
}

function bos_is_madrasah(): bool
{
    return bos_setting('jenis', 'umum') === 'madrasah';
}

/** "Kepala Sekolah" / "Kepala Madrasah" mengikuti jenis lembaga pada data e-BOS. */
function bos_jabatan_kepala(): string
{
    return bos_is_madrasah() ? 'Kepala Madrasah' : 'Kepala Sekolah';
}

/** Nama Bendahara BOS: pengaturan e-BOS, atau nama akun bendahara yang aktif. */
function bos_bendahara(): string
{
    $n = bos_nilai_ada(bos_setting('bendahara'));
    if ($n !== '') {
        return $n;
    }
    $u = bos_one("SELECT nama FROM users WHERE role = 'bendahara' AND aktif = 1 ORDER BY id LIMIT 1");
    return $u ? (trim((string) $u['nama']) !== '' ? trim((string) $u['nama']) : '-') : '-';
}

// =====================================================================================
// TAHUN ANGGARAN
// =====================================================================================
function bos_ta_list(): array
{
    return bos_all('SELECT * FROM tahun_anggaran ORDER BY nama DESC, id DESC');
}

function bos_ta_by_id(?int $id): ?array
{
    if ($id === null || $id <= 0) {
        return null;
    }
    return bos_one('SELECT * FROM tahun_anggaran WHERE id = ?', [$id]);
}

function bos_ta_aktif_id(): int
{
    $id = (int) bos_nilai('SELECT ta_aktif_id FROM sekolah WHERE id = (SELECT MIN(id) FROM sekolah)', [], 0);
    if ($id > 0 && bos_ta_by_id($id) !== null) {
        return $id;
    }
    return (int) bos_nilai('SELECT id FROM tahun_anggaran ORDER BY nama DESC, id DESC LIMIT 1', [], 0);
}

/** Label tahun anggaran: "2026" atau "2026 (1 Januari 2026 - 31 Desember 2026)". */
function bos_ta_label($ta, bool $denganPeriode = false): string
{
    $row = is_array($ta) ? $ta : bos_ta_by_id((int) $ta);
    if (!$row) {
        return 'Belum ada tahun anggaran';
    }
    $nama = trim((string) $row['nama']) !== '' ? trim((string) $row['nama']) : '-';
    if (!$denganPeriode) {
        return $nama;
    }
    $mulai = bos_nilai_ada((string) ($row['tanggal_mulai'] ?? ''));
    $selesai = bos_nilai_ada((string) ($row['tanggal_selesai'] ?? ''));
    if ($mulai === '' && $selesai === '') {
        return $nama;
    }
    return $nama . ' (' . ($mulai !== '' ? tanggal_id($mulai) : '?') . ' - '
        . ($selesai !== '' ? tanggal_id($selesai) : '?') . ')';
}

// =====================================================================================
// KOMPONEN, PAGU, REALISASI, TRANSAKSI (sama dengan perhitungan e-BOS)
// =====================================================================================
function bos_komponen_list(int $taId, bool $aktifSaja = true): array
{
    if ($taId <= 0) {
        return [];
    }
    $sql = 'SELECT * FROM komponen WHERE ta_id = ?';
    if ($aktifSaja) {
        $sql .= ' AND aktif = 1';
    }
    return bos_all($sql . ' ORDER BY urutan, nama COLLATE NOCASE', [$taId]);
}

function bos_pagu_total(int $taId): array
{
    $row = bos_one('SELECT COALESCE(SUM(pagu_tahap1),0) AS t1, COALESCE(SUM(pagu_tahap2),0) AS t2,
                           COUNT(*) AS n
                    FROM komponen WHERE ta_id = ? AND aktif = 1', [$taId]) ?? [];
    $t1 = (int) ($row['t1'] ?? 0);
    $t2 = (int) ($row['t2'] ?? 0);
    return ['tahap1' => $t1, 'tahap2' => $t2, 'total' => $t1 + $t2, 'komponen' => (int) ($row['n'] ?? 0)];
}

/** Rekap pagu & realisasi per komponen; pengeluaran tanpa komponen dikembalikan pada kunci 'tanpa'. */
function bos_rekap_komponen(int $taId, bool $aktifSaja = false): array
{
    $sql = "SELECT k.id, k.kode, k.nama, k.pagu_tahap1, k.pagu_tahap2, k.keterangan, k.urutan, k.aktif,
            COALESCE(SUM(CASE WHEN t.jenis = 'pengeluaran' AND t.tahap = 1 THEN t.jumlah END),0) AS real1,
            COALESCE(SUM(CASE WHEN t.jenis = 'pengeluaran' AND t.tahap = 2 THEN t.jumlah END),0) AS real2,
            COALESCE(SUM(CASE WHEN t.jenis = 'pengeluaran' THEN t.jumlah END),0) AS real_total
        FROM komponen k
        LEFT JOIN transaksi t ON t.komponen_id = k.id
        WHERE k.ta_id = ?" . ($aktifSaja ? ' AND k.aktif = 1' : '') . '
        GROUP BY k.id ORDER BY k.urutan, k.nama COLLATE NOCASE';
    $rows = bos_all($sql, [$taId]);
    $tanpa = bos_one("SELECT COALESCE(SUM(CASE WHEN jenis = 'pengeluaran' AND tahap = 1 THEN jumlah END),0) AS real1,
                             COALESCE(SUM(CASE WHEN jenis = 'pengeluaran' AND tahap = 2 THEN jumlah END),0) AS real2,
                             COUNT(*) AS n
                      FROM transaksi WHERE ta_id = ? AND jenis = 'pengeluaran' AND komponen_id IS NULL",
        [$taId]) ?? [];
    return [
        'komponen' => $rows,
        'tanpa'    => [
            'real1' => (int) ($tanpa['real1'] ?? 0),
            'real2' => (int) ($tanpa['real2'] ?? 0),
            'baris' => (int) ($tanpa['n'] ?? 0),
        ],
    ];
}

function bos_transaksi_list(int $taId): array
{
    return bos_all('SELECT t.*, k.nama AS komponen_nama, k.kode AS komponen_kode, ta.nama AS ta_nama
                    FROM transaksi t
                    LEFT JOIN komponen k ON k.id = t.komponen_id
                    JOIN tahun_anggaran ta ON ta.id = t.ta_id
                    WHERE t.ta_id = ? ORDER BY t.tanggal ASC, t.id ASC', [$taId]);
}

/** Total pemasukan/pengeluaran/saldo sebuah tahun anggaran. */
function bos_ringkas(int $taId): array
{
    $row = bos_one("SELECT
            COALESCE(SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah END),0) AS pemasukan,
            COALESCE(SUM(CASE WHEN jenis = 'pengeluaran' THEN jumlah END),0) AS pengeluaran,
            COUNT(*) AS baris
        FROM transaksi WHERE ta_id = ?", [$taId]) ?? [];
    $masuk = (int) ($row['pemasukan'] ?? 0);
    $keluar = (int) ($row['pengeluaran'] ?? 0);
    return ['pemasukan' => $masuk, 'pengeluaran' => $keluar, 'saldo' => $masuk - $keluar,
        'baris' => (int) ($row['baris'] ?? 0)];
}

/** Seri 12 bulan (pemasukan & pengeluaran) untuk grafik. */
function bos_bulan_seri(int $taId): array
{
    $seri = [];
    for ($b = 1; $b <= 12; $b++) {
        $seri[$b] = ['pemasukan' => 0, 'pengeluaran' => 0];
    }
    $rows = bos_all("SELECT CAST(strftime('%m', tanggal) AS INTEGER) AS bulan, jenis,
                            COALESCE(SUM(jumlah),0) AS jumlah
                     FROM transaksi WHERE ta_id = ? GROUP BY bulan, jenis", [$taId]);
    foreach ($rows as $r) {
        $b = (int) $r['bulan'];
        if ($b >= 1 && $b <= 12) {
            $seri[$b][(string) $r['jenis'] === 'pemasukan' ? 'pemasukan' : 'pengeluaran'] = (int) $r['jumlah'];
        }
    }
    return $seri;
}

function bos_tahap_label($n): string
{
    $n = (int) $n;
    if ($n === 0) {
        return 'Tanpa tahap';
    }
    $peta = [1 => 'Tahap 1', 2 => 'Tahap 2'];
    return $peta[$n] ?? ('Tahap ' . $n);
}

// =====================================================================================
// TAMPILAN: PAPAN INFORMASI PENGGUNAAN DANA BOS
// =====================================================================================

/** Tautan halaman ini dengan saringan tambahan — ALAMAT RAPI (mis. `halaman/dana-bos?ta=2`). */
function bos_tautan(string $slug, array $q = []): string
{
    $q = array_filter($q, static fn ($v): bool => (string) $v !== '');
    return rute_tautan('halaman/' . rawurlencode($slug), $q);
}

/**
 * Mencetak PAPAN INFORMASI PENGGUNAAN DANA BOS (isi menu "Informasi BOS").
 *
 * @param array $opt ['slug' => <slug menu halaman ini>, 'ta' => <id tahun anggaran>]
 */
function bos_papan(array $opt = []): void
{
    $slug = (string) ($opt['slug'] ?? 'dana-bos');
    $taId = (int) ($opt['ta'] ?? 0);

    if (!bos_siap()) {
        // Basis data e-BOS tidak dapat dibaca — keterangan ramah, bukan halaman kosong.
        echo '<div class="bos-papan"><section class="bos-kartu">'
            . '<div class="bos-kosong">' . ikon('papan', 34)
            . '<h3>Data Dana BOS belum dapat ditampilkan</h3>'
            . '<p>Halaman ini menampilkan Papan Informasi Dana BOS langsung dari aplikasi <b>e-BOS</b>. '
            . 'Sumber datanya belum dapat dibaca saat ini (aplikasi e-BOS belum dipasang di server, '
            . 'atau berkas datanya tidak dapat diakses).</p>';
        if (defined('EBOS_URL') && EBOS_URL !== '') {
            echo '<p><a class="tombol tombol-garis tombol-kecil" href="' . e((string) EBOS_URL) . '" '
                . 'target="_blank" rel="noopener noreferrer">' . ikon('globe', 15) . ' Buka aplikasi e-BOS</a></p>';
        }
        echo '</div></section></div>';
        return;
    }

    if ($taId <= 0 || bos_ta_by_id($taId) === null) {
        $taId = bos_ta_aktif_id();
    }
    $ta = bos_ta_by_id($taId);

    // ---- perhitungan (urutan & rumus mengikuti e-bos/index.php) ----
    $komponen = bos_komponen_list($taId, true);
    $rekap = bos_rekap_komponen($taId, true);
    $realById = [];
    foreach ($rekap['komponen'] as $k) {
        $realById[(int) $k['id']] = [
            't1' => (int) $k['real1'],
            't2' => (int) $k['real2'],
            'total' => (int) $k['real_total'],
        ];
    }
    $trx = bos_transaksi_list($taId);
    $ring = bos_ringkas($taId);
    $pagu = bos_pagu_total($taId);

    $t1Pagu = $pagu['tahap1'];
    $t2Pagu = $pagu['tahap2'];
    $realT1 = 0;
    $realT2 = 0;
    foreach ($realById as $r) {
        $realT1 += $r['t1'];
        $realT2 += $r['t2'];
    }
    // Pengeluaran yang belum ditautkan ke komponen dihitung terpisah (baris bertanda "*").
    $realTanpa = (int) $rekap['tanpa']['real1'] + (int) $rekap['tanpa']['real2'];
    $realSemua = $realT1 + $realT2 + $realTanpa;
    $nMasuk = 0;
    $nKeluar = 0;
    foreach ($trx as $t) {
        if ((string) $t['jenis'] === 'pemasukan') {
            $nMasuk++;
        } else {
            $nKeluar++;
        }
    }
    $grafikNama = [];
    $grafikNilai = [];
    foreach ($rekap['komponen'] as $k) {
        if ((int) $k['real_total'] > 0) {
            $grafikNama[] = (string) $k['nama'];
            $grafikNilai[] = (int) $k['real_total'];
        }
    }
    $adaTanpaKomponen = ((int) $rekap['tanpa']['real1'] + (int) $rekap['tanpa']['real2']) > 0
        || (int) $rekap['tanpa']['baris'] > 0;

    $seriBulan = bos_bulan_seri($taId);
    $labelBulan = [];
    $masukBulan = [];
    $keluarBulan = [];
    for ($b = 1; $b <= 12; $b++) {
        $labelBulan[] = substr(bos_bulan($b), 0, 3);
        $masukBulan[] = (int) $seriBulan[$b]['pemasukan'];
        $keluarBulan[] = (int) $seriBulan[$b]['pengeluaran'];
    }
    $adaGrafikBulan = (array_sum($masukBulan) + array_sum($keluarBulan)) > 0;

    // Buku Kas disembunyikan sampai pengunjung menekan "Buka Kas dan Transaksi"
    // (tautan ?kas=1 tetap bekerja tanpa JavaScript).
    $kasTerbuka = !empty($_GET['kas']) && (string) $_GET['kas'] !== '0';

    $catatan = trim(bos_setting('catatan_papan'));
    ?>
<div class="bos-papan" id="papanBos">

  <div class="bos-aksi">
    <div class="bos-tahun">
      <div class="bos-judul-kecil"><?= ikon('kalender', 15) ?> Tahun Anggaran</div>
      <div class="bos-chip-list">
        <?php foreach (bos_ta_list() as $t): ?>
          <a class="bos-chip <?= (int) $t['id'] === $taId ? 'on' : '' ?>"
             href="<?= e(bos_tautan($slug, ['ta' => (int) $t['id']])) ?>">
            <?= e(bos_ta_label($t)) ?><?= (int) $t['id'] === bos_ta_aktif_id() ? ' (aktif)' : '' ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <h1 class="bos-judul">
      PAPAN INFORMASI
      <span class="bos-judul-sub">Penggunaan Dana BOS<?= bos_nama_lembaga() !== '' ? ' · ' . e(bos_nama_lembaga()) : '' ?></span>
    </h1>

    <div class="bos-tombol">
      <button class="tombol tombol-emas tombol-kecil" type="button" data-cetak><?= ikon('cetak', 16) ?> Cetak Papan Informasi</button>
    </div>
  </div>

  <?php if ($catatan !== ''): ?>
    <p class="bos-ket"><?= nl2br(e($catatan)) ?></p>
  <?php endif; ?>

  <section class="bos-kartu">
    <div class="bos-kepala">
      <div>
        <h2>Data Lembaga</h2>
        <p>Identitas <?= bos_is_madrasah() ? 'madrasah' : 'sekolah' ?> pengelola Dana BOS dan periode anggaran yang ditampilkan.</p>
      </div>
      <span class="bos-pil bos-pil-emas"><?= ikon('kalender', 13) ?> Tahun Anggaran <?= e(bos_ta_label($ta)) ?></span>
    </div>

    <div class="bos-info">
      <div><b>Nama <?= bos_is_madrasah() ? 'Madrasah' : 'Sekolah' ?></b><span><?= e(bos_nama_lembaga() !== '' ? bos_nama_lembaga() : '-') ?></span></div>
      <div><b>NPSN</b><span><?= e(bos_nilai_ada(bos_setting('npsn')) !== '' ? bos_setting('npsn') : '-') ?></span></div>
      <div><b>Alamat</b><span><?= e(bos_nilai_ada(bos_setting('alamat')) !== '' ? bos_setting('alamat') : '-') ?></span></div>
      <div><b><?= e(bos_jabatan_kepala()) ?></b><span><?= e(bos_nilai_ada(bos_setting('kepala_sekolah')) !== '' ? bos_setting('kepala_sekolah') : '-') ?></span></div>
      <div><b>Bendahara BOS</b><span><?= e(bos_bendahara()) ?></span></div>
      <div><b>Telepon / HP</b><span><?= e(bos_nilai_ada(bos_setting('telepon')) !== '' ? bos_setting('telepon') : '-') ?></span></div>
      <div><b>Email</b><span><?= e(bos_nilai_ada(bos_setting('email')) !== '' ? bos_setting('email') : '-') ?></span></div>
      <div><b>Situs / Laman</b><span><?= e(bos_nilai_ada(bos_setting('website')) !== '' ? bos_setting('website') : '-') ?></span></div>
      <div><b>Periode Anggaran</b><span><?= e(bos_ta_label($ta, true)) ?></span></div>
      <div><b>Dana Diterima</b><span><?= e(bos_rp($ring['pemasukan'])) ?></span></div>
    </div>

    <div class="bos-divider"></div>

    <div class="bos-stat">
      <div class="bos-kotak">
        <div class="lbl">Jumlah Anggaran Komponen</div>
        <div class="nilai"><?= e(bos_rp($pagu['total'])) ?></div>
        <div class="sub"><?= bos_pagu_ada($pagu['total'])
              ? 'Tahap 1 ' . e(bos_rp($t1Pagu)) . ', Tahap 2 ' . e(bos_rp($t2Pagu))
              : 'Komponen belum diisi pada menu Komponen BOS' ?></div>
      </div>
      <div class="bos-kotak masuk">
        <div class="lbl">Dana Diterima</div>
        <div class="nilai"><?= e(bos_rp($ring['pemasukan'])) ?></div>
        <div class="sub"><?= bos_r0($nMasuk) ?> transaksi pemasukan</div>
      </div>
      <div class="bos-kotak keluar">
        <div class="lbl">Realisasi Pengeluaran</div>
        <div class="nilai"><?= e(bos_rp($ring['pengeluaran'])) ?></div>
        <div class="sub"><?= bos_pagu_ada($pagu['total'])
              ? e(bos_persen((float) $ring['pengeluaran'], (float) $pagu['total'])) . ' dari pagu'
              : bos_r0($nKeluar) . ' transaksi pengeluaran' ?></div>
      </div>
      <div class="bos-kotak sisa">
        <div class="lbl">Sisa Dana</div>
        <div class="nilai"><?= e(bos_rp($ring['saldo'])) ?></div>
        <div class="sub">Dana diterima dikurangi pengeluaran</div>
      </div>
    </div>
  </section>

  <section class="bos-kartu">
    <div class="bos-kepala">
      <div>
        <h2>Komponen Penggunaan Dana BOS</h2>
      </div>
      <span class="bos-pil bos-pil-info"><?= bos_r0(count($komponen)) ?> komponen</span>
    </div>
    <?php if (!$komponen): ?>
      <div class="bos-kosong"><?= ikon('papan', 34) ?><h3>Belum ada komponen</h3>
        <p>Komponen penggunaan dana BOS untuk tahun anggaran ini belum diisi pengelola.</p></div>
    <?php else: ?>
      <div class="bos-tabel-wrap">
        <table class="bos-tbl" id="tblKomponen">
          <thead>
            <tr>
              <th class="bos-ctr" rowspan="2" style="width:52px">No</th>
              <th rowspan="2">Komponen Penggunaan Dana BOS</th>
              <th class="bos-num" rowspan="2">Jumlah</th>
              <th class="bos-grp" colspan="2">Tahap</th>
              <th rowspan="2">Keterangan</th>
            </tr>
            <tr class="bos-sub-baris">
              <th class="bos-num">Tahap 1</th>
              <th class="bos-num">Tahap 2</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($komponen as $i => $k):
                $p1 = (int) $k['pagu_tahap1'];
                $p2 = (int) $k['pagu_tahap2']; ?>
              <tr>
                <td class="bos-ctr"><?= $i + 1 ?></td>
                <td><b><?= e((string) $k['nama']) ?></b></td>
                <td class="bos-money"><b><?= e(bos_rp($p1 + $p2)) ?></b></td>
                <td class="bos-money"><?= e(bos_rp($p1)) ?></td>
                <td class="bos-money"><?= e(bos_rp($p2)) ?></td>
                <td class="bos-kecil bos-mut"><?= trim((string) ($k['keterangan'] ?? '')) !== '' ? e((string) $k['keterangan']) : '-' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th class="bos-ctr">&nbsp;</th>
              <th>JUMLAH</th>
              <th class="bos-num"><?= e(bos_rp($pagu['total'])) ?></th>
              <th class="bos-num"><?= e(bos_rp($t1Pagu)) ?></th>
              <th class="bos-num"><?= e(bos_rp($t2Pagu)) ?></th>
              <th>&nbsp;</th>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <div class="bos-grid2">
    <section class="bos-kartu">
      <div class="bos-kepala">
        <div>
          <h2>Realisasi dan Sisa Anggaran</h2>
          <p>Perbandingan jumlah komponen yang diisi dengan pengeluaran yang sudah dicatat.</p>
        </div>
        <span class="bos-pil <?= $realSemua > 0 ? 'bos-pil-ok' : 'bos-pil-gray' ?>">
          <?= bos_pagu_ada($pagu['total']) ? e(bos_serapan_label($pagu['total'], $realSemua)) . ' terserap' : 'Angka komponen belum diisi' ?>
        </span>
      </div>
      <?php if (!$komponen): ?>
        <p class="bos-kecil bos-mut mb0">Belum ada komponen untuk dihitung.</p>
      <?php else: ?>
        <div class="bos-tabel-wrap">
          <table class="bos-tbl">
            <thead>
              <tr>
                <th class="bos-ctr" style="width:44px">No</th>
                <th>Komponen</th>
                <th class="bos-num">Jumlah Komponen</th>
                <th class="bos-num">Realisasi</th>
                <th class="bos-num">Sisa</th>
                <th class="bos-ctr" style="width:110px">Serapan</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($komponen as $i => $k):
                  $id = (int) $k['id'];
                  $p = (int) $k['pagu_tahap1'] + (int) $k['pagu_tahap2'];
                  $r = (int) ($realById[$id]['total'] ?? 0);
                  $lebih = $r > $p;
                  $lbr = $p > 0 ? min(100, (int) round($r / $p * 100)) : ($r > 0 ? 100 : 0); ?>
                <tr>
                  <td class="bos-ctr"><?= $i + 1 ?></td>
                  <td><?= e((string) $k['nama']) ?></td>
                  <td class="bos-money"><?= e(bos_rp($p)) ?></td>
                  <td class="bos-money"><?= e(bos_rp($r)) ?></td>
                  <td class="bos-money"><?= e(bos_sisa_label($p, $r)) ?></td>
                  <td>
                    <?php if (bos_pagu_ada($p)): ?>
                      <span class="bos-bar"><i class="<?= $lebih ? 'lebih' : '' ?>" style="width:<?= $lbr ?>%"></i></span>
                      <span class="bos-xs bos-mut"><?= e(bos_persen((float) $r, (float) $p)) ?></span>
                    <?php else: ?>
                      <span class="bos-xs bos-mut">belum diisi</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($adaTanpaKomponen): ?>
                <tr>
                  <td class="bos-ctr">*</td>
                  <td>Belum ditautkan ke komponen</td>
                  <td class="bos-money">-</td>
                  <td class="bos-money"><?= e(bos_rp($realTanpa)) ?></td>
                  <td class="bos-money">-</td>
                  <td><span class="bos-xs bos-mut"><?= bos_r0($rekap['tanpa']['baris']) ?> transaksi</span></td>
                </tr>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <th class="bos-ctr">&nbsp;</th>
                <th>JUMLAH</th>
                <th class="bos-num"><?= e(bos_rp($pagu['total'])) ?></th>
                <th class="bos-num"><?= e(bos_rp($realSemua)) ?></th>
                <th class="bos-num"><?= e(bos_sisa_label($pagu['total'], $realSemua)) ?></th>
                <th class="bos-num"><?= e(bos_serapan_label($pagu['total'], $realSemua)) ?></th>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <div class="bos-kolom">
      <section class="bos-kartu">
        <div class="bos-kepala">
          <div>
            <h2>Grafik Serapan Dana</h2>
            <p>Proporsi realisasi pengeluaran tiap komponen.</p>
          </div>
        </div>
        <?php if (!$grafikNilai): ?>
          <div class="bos-kosong"><?= ikon('grafik', 34) ?><h3>Belum ada realisasi</h3>
            <p>Grafik muncul setelah pengeluaran dicatat pada tahun anggaran ini.</p></div>
        <?php else: ?>
          <div class="bos-chart bos-chart-kompak"><canvas id="grafikSerapan"></canvas></div>
        <?php endif; ?>
      </section>

      <section class="bos-kartu">
        <div class="bos-kepala">
          <div>
            <h2>Pemasukan dan Pengeluaran per Bulan</h2>
            <p>Dana yang diterima dan pengeluaran pada tiap bulan tahun anggaran <?= e(bos_ta_label($ta)) ?>.</p>
          </div>
          <span class="bos-pil <?= $adaGrafikBulan ? 'bos-pil-ok' : 'bos-pil-gray' ?>">
            <?= $adaGrafikBulan ? e(bos_rp(array_sum($masukBulan))) . ' masuk' : 'Belum ada transaksi' ?>
          </span>
        </div>
        <?php if (!$adaGrafikBulan): ?>
          <div class="bos-kosong"><?= ikon('grafik', 34) ?><h3>Belum ada transaksi</h3>
            <p>Grafik muncul setelah pemasukan atau pengeluaran dicatat pada tahun anggaran ini.</p></div>
        <?php else: ?>
          <div class="bos-chart bos-chart-bulanan"><canvas id="grafikBulanDepan"></canvas></div>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <section class="bos-kartu" id="kas">
    <div class="bos-kepala">
      <div>
        <h2>Buku Kas, Daftar Transaksi</h2>
        <p>Seluruh pemasukan dan pengeluaran tahun anggaran <?= e(bos_ta_label($ta)) ?>, urut tanggal.</p>
      </div>
      <div class="bos-flex">
        <span class="bos-pil bos-pil-info"><?= bos_r0(count($trx)) ?> transaksi</span>
        <?php if ($trx): ?>
          <?php // Buka/tutup tanpa memuat ulang halaman; tanpa JavaScript tautan ini tetap bekerja (?kas=1). ?>
          <a class="tombol tombol-kecil" id="btnKas" data-bos-toggle="isiKas"
             href="<?= e(bos_tautan($slug, ['ta' => $taId, 'kas' => 1]) . '#kas') ?>"
             aria-expanded="<?= $kasTerbuka ? 'true' : 'false' ?>"
             aria-controls="isiKas"
             data-label-buka="Buka Kas dan Transaksi" data-label-tutup="Tutup Kas dan Transaksi">
            <?= ikon('papan', 16) ?> <span class="bos-label-toggle"><?= $kasTerbuka ? 'Tutup Kas dan Transaksi' : 'Buka Kas dan Transaksi' ?></span>
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!$trx): ?>
      <div class="bos-kosong"><?= ikon('papan', 34) ?><h3>Belum ada transaksi</h3>
        <p>Pemasukan dan pengeluaran dana BOS belum dicatat pada tahun anggaran ini.</p></div>
    <?php else: ?>
      <p class="bos-xs bos-mut mb0" id="kasPetunjuk" <?= $kasTerbuka ? 'hidden' : '' ?>>
        Daftar pemasukan dan pengeluaran disembunyikan agar halaman tetap ringkas.
        Klik <b>Buka Kas dan Transaksi</b> untuk menampilkannya.
      </p>
      <div id="isiKas" <?= $kasTerbuka ? '' : 'hidden' ?>>
        <div class="bos-tabel-wrap">
          <table class="bos-tbl">
            <thead>
              <tr>
                <th class="bos-ctr" style="width:52px">No</th>
                <th style="width:130px">Tanggal</th>
                <th>Uraian</th>
                <th>Komponen</th>
                <th class="bos-ctr" style="width:96px">Tahap</th>
                <th class="bos-num">Pemasukan</th>
                <th class="bos-num">Pengeluaran</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($trx as $i => $t): $masuk = (string) $t['jenis'] === 'pemasukan'; ?>
                <tr>
                  <td class="bos-ctr"><?= $i + 1 ?></td>
                  <td class="bos-nowrap"><?= e(tanggal_id((string) $t['tanggal'], true)) ?></td>
                  <td>
                    <?= e((string) $t['uraian']) ?>
                    <?php if ((string) ($t['bukti'] ?? '') !== ''): ?><span class="bos-sub">Bukti: <?= e((string) $t['bukti']) ?></span><?php endif; ?>
                  </td>
                  <td class="bos-kecil"><?= $t['komponen_nama'] !== null ? e((string) $t['komponen_nama']) : '<span class="bos-mut">-</span>' ?></td>
                  <td class="bos-ctr"><span class="bos-pil <?= (int) $t['tahap'] === 2 ? 'bos-pil-t2' : ((int) $t['tahap'] === 1 ? 'bos-pil-t1' : 'bos-pil-gray') ?>"><?= e(bos_tahap_label((int) $t['tahap'])) ?></span></td>
                  <td class="bos-money"><?= $masuk ? e(bos_rp((int) $t['jumlah'])) : '-' ?></td>
                  <td class="bos-money"><?= $masuk ? '-' : e(bos_rp((int) $t['jumlah'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th class="bos-ctr">&nbsp;</th>
                <th colspan="4">JUMLAH</th>
                <th class="bos-num"><?= e(bos_rp($ring['pemasukan'])) ?></th>
                <th class="bos-num"><?= e(bos_rp($ring['pengeluaran'])) ?></th>
              </tr>
            </tfoot>
          </table>
        </div>
        <p class="bos-xs bos-mut mt8">Saldo dana: <b><?= e(bos_rp($ring['saldo'])) ?></b> (dana diterima dikurangi pengeluaran).</p>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php if ($grafikNilai || $adaGrafikBulan): ?>
  <script src="assets/vendor/chart.umd.min.js?v=<?= e(APP_VERSION) ?>"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    if (!window.BOS) { return; }
    <?php if ($grafikNilai): ?>
    window.BOS.donut('grafikSerapan', <?= json_encode($grafikNama, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($grafikNilai) ?>);
    <?php endif; ?>
    <?php if ($adaGrafikBulan): ?>
    window.BOS.banding('grafikBulanDepan', <?= json_encode($labelBulan) ?>, <?= json_encode($masukBulan) ?>, <?= json_encode($keluarBulan) ?>);
    <?php endif; ?>
  });
  </script>
<?php endif; ?>
    <?php
}
