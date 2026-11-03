<?php
/**
 * INFORMASI "E-VOTING OSIM" (menu Informasi → e-Voting OSIM) — **PAPAN HASIL REAL-COUNT**
 * aplikasi e-Voting OSIM untuk **MA. Sirojuth Tholibiin Mootilango**.
 *
 * Permintaan pemilik:
 *   "Pada bagian e-Voting OSIM isi dengan Hasil Real-Count pada aplikasi e-Voting
 *    (Khusus MA. Sirojuth Tholibiin Mootilango)."
 * Pilihan pemilik (JANGAN diubah tanpa bertanya):
 *   1. **Mengikuti setelan aplikasi e-Voting**: bila panitia BELUM membuka hasil ke publik
 *      (`tampilkan_hasil` ≠ 1), halaman hanya menampilkan keterangan "hasil belum dibuka"
 *      (tanpa angka apa pun) — persis aturan aplikasi sumbernya.
 *   2. Angkanya **berjalan sendiri setiap 5 detik** (`HASIL_POLLING` aplikasi) lewat endpoint
 *      `voting_hasil.php` tanpa memuat ulang halaman.
 *
 * PENTING / JANGAN DIUBAH tanpa alasan kuat:
 *  1. Modul ini TIDAK memuat kode aplikasi e-Voting (`e-voting-osis/inc/init.php`) — konstanta &
 *     fungsinya akan bertabrakan dengan aplikasi ini. Seluruh pembacaan ditulis ulang di sini
 *     memakai koneksi TERPISAH yang HANYA-BACA (`mode=ro`, cadangan `query_only=1`).
 *  2. Yang dibaca hanya tabel `pengaturan`, `paslon`, `pemilih`, dan `suara`. **Tabel `pemilih`
 *     hanya dihitung JUMLAHNYA** (DPT) — data pemilih (nama/NISN/token) tidak pernah ditampilkan
 *     dan **tidak pernah menyentuh tabel `suara` per pemilih** (satu surat suara = satu baris tanpa
 *     identitas pemilih, jadi kerahasiaan pilihan tetap terjaga).
 *  3. Basis data dibuka hanya bila berkasnya ada; bila tidak, halaman menampilkan keterangan ramah
 *     (BUKAN Fatal error).
 */
declare(strict_types=1);

/** Palet warna kartu paslon (mengikuti aplikasi e-Voting supaya tampilannya sama). */
const VOTING_PALET = ['#4f46e5', '#059669', '#d97706', '#db2777', '#0891b2', '#7c3aed', '#65a30d', '#e11d48'];

// =====================================================================================
// KONEKSI BACA-SAJA ke basis data aplikasi e-Voting OSIM
// =====================================================================================

/** Berkas basis data e-Voting (env → pengaturan → konstanta → bawaan). */
function voting_db_file(): string
{
    static $berkas = null;
    if ($berkas !== null) {
        return $berkas;
    }
    $kandidat = [];
    $env = trim((string) getenv('WEBSK_VOTING_DB'));
    if ($env !== '') {
        $kandidat[] = $env;
    }
    if (function_exists('pengaturan')) {
        $kustom = trim(pengaturan('voting_db', ''));
        if ($kustom !== '') {
            $kandidat[] = $kustom;
        }
    }
    if (defined('VOTING_DB_FILE')) {
        $kandidat[] = (string) VOTING_DB_FILE;
    }
    $kandidat[] = dirname(__DIR__) . '/e-voting-osis/data/voting.sqlite';

    foreach ($kandidat as $k) {
        if ($k !== '' && is_file($k) && is_readable($k)) {
            return $berkas = $k;
        }
    }
    return $berkas = '';
}

/** Koneksi BACA-SAJA (null bila tidak tersedia). */
function voting_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo instanceof PDO ? $pdo : null;
    }
    $pdo = null;
    $berkas = voting_db_file();
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
            $coba->query('SELECT COUNT(*) FROM paslon')->fetchColumn();
            $coba->query('SELECT COUNT(*) FROM pengaturan')->fetchColumn();
            $pdo = $coba;
            return $pdo;
        } catch (Throwable $e) {
            // coba cara berikutnya
        }
    }
    return null;
}

/** Apakah sumber data e-Voting tersedia? */
function voting_siap(): bool
{
    return voting_db() instanceof PDO;
}

function voting_all(string $sql, array $param = []): array
{
    $pdo = voting_db();
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

function voting_nilai(string $sql, array $param = [], $bawaan = 0)
{
    $pdo = voting_db();
    if (!$pdo) {
        return $bawaan;
    }
    try {
        $st = $pdo->prepare($sql);
        $st->execute($param);
        $v = $st->fetchColumn();
        return $v === false || $v === null ? $bawaan : $v;
    } catch (Throwable $e) {
        return $bawaan;
    }
}

/** Nilai satu kunci `pengaturan` aplikasi e-Voting. */
function voting_setting(string $kunci, string $bawaan = ''): string
{
    $v = voting_nilai('SELECT nilai FROM pengaturan WHERE kunci = ?', [$kunci], null);
    $v = $v === null ? '' : (string) $v;
    return $v !== '' ? $v : $bawaan;
}

/** Apakah lembaga pemilik data memang sekolah/madrasah ini? */
function voting_lembaga_cocok(): bool
{
    if (!defined('VOTING_LEMBAGA') || (string) VOTING_LEMBAGA === '') {
        return true;
    }
    $kunci = static function (string $t): string {
        $t = strtolower(trim($t));
        return preg_replace('/[^a-z0-9]+/', '', $t) ?? '';
    };
    return $kunci(voting_setting('nama_lembaga')) === $kunci((string) VOTING_LEMBAGA);
}

// =====================================================================================
// ANGKA HASIL (mengikuti hasil_lengkap() aplikasi e-Voting)
// =====================================================================================

/** Angka bulat berkelompok. */
function voting_angka($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

/** Persentase satu desimal + tanda %. */
function voting_persen($v): string
{
    return number_format((float) $v, 1, ',', '.') . '%';
}

/** Foto paslon (hanya URL yang disimpan aplikasi sumber). */
function voting_foto(array $p): string
{
    $f = trim((string) ($p['foto'] ?? ''));
    return $f !== '' ? $f : '';
}

/**
 * Seluruh angka papan Real-Count.
 * Struktur: buka (hasil dibuka panitia?), pemilihan/lembaga/periode/tanggal, dpt, masuk, belum,
 * turnout, sah, paslon[], teratas, diperbarui, zona.
 */
function voting_hasil(): array
{
    $buka = voting_setting('tampilkan_hasil', '0') === '1';
    $out = [
        'buka'      => $buka,
        'pemilihan' => voting_setting('nama_pemilihan', 'Pemilihan Ketua & Wakil Ketua'),
        'lembaga'   => voting_setting('nama_lembaga', ''),
        'periode'   => voting_setting('periode', ''),
        'tanggal'   => voting_setting('tanggal_pemilihan', ''),
        'jenis'     => voting_setting('jenis_lembaga', ''),
        'dpt'       => 0, 'masuk' => 0, 'belum' => 0, 'turnout' => 0.0, 'sah' => 0,
        'paslon'    => [], 'teratas' => null,
        'diperbarui' => date('H:i:s'),
        'zona'      => 'WITA',
        'polling'   => 5,
    ];
    if (!voting_siap()) {
        return $out;
    }

    $peta = [];
    foreach (voting_all('SELECT paslon_id, COUNT(*) AS n FROM suara GROUP BY paslon_id') as $r) {
        $peta[(int) $r['paslon_id']] = (int) $r['n'];
    }
    $sah = (int) voting_nilai('SELECT COUNT(*) FROM suara', [], 0);
    $dpt = (int) voting_nilai('SELECT COUNT(*) FROM pemilih', [], 0);
    $masuk = min($sah, $dpt > 0 ? max($sah, 0) : $sah);   // satu pemilih = satu surat suara
    $belum = max(0, $dpt - $masuk);

    $paslon = [];
    $i = 0;
    foreach (voting_all('SELECT * FROM paslon ORDER BY nomor, id') as $p) {
        $jml = $peta[(int) $p['id']] ?? 0;
        // Mengikuti aplikasi: paslon nonaktif tanpa suara tidak ditampilkan di papan hasil.
        if ((int) $p['aktif'] !== 1 && $jml === 0) {
            continue;
        }
        $paslon[] = [
            'id'     => (int) $p['id'],
            'nomor'  => (int) $p['nomor'],
            'ketua'  => (string) $p['ketua'],
            'wakil'  => (string) $p['wakil'],
            'kelas_ketua' => (string) ($p['kelas_ketua'] ?? ''),
            'kelas_wakil' => (string) ($p['kelas_wakil'] ?? ''),
            'aktif'  => (int) $p['aktif'],
            'foto'   => voting_foto($p),
            'suara'  => $jml,
            'persen' => $sah > 0 ? round(($jml / $sah) * 100, 1) : 0.0,
            'warna'  => VOTING_PALET[$i % count(VOTING_PALET)],
        ];
        $i++;
    }

    $teratas = null;
    foreach ($paslon as $p) {
        if ($sah > 0 && ($teratas === null || $p['suara'] > $teratas['suara'])) {
            $teratas = $p;
        }
    }

    $out['dpt'] = $dpt;
    $out['masuk'] = $masuk;
    $out['belum'] = $belum;
    $out['turnout'] = $dpt > 0 ? round(($masuk / $dpt) * 100, 1) : 0.0;
    $out['sah'] = $sah;
    $out['paslon'] = $paslon;
    $out['teratas'] = $teratas;
    $out['diperbarui'] = date('H:i:s');
    if (defined('VOTING_POLLING')) {
        $out['polling'] = (int) VOTING_POLLING;
    }
    if (defined('VOTING_ZONA')) {
        $out['zona'] = (string) VOTING_ZONA;
    }
    return $out;
}

/** Data ringkas untuk endpoint JSON (dipakai penyegaran otomatis di peramban). */
function voting_json(array $h): array
{
    return [
        'ok'       => true,
        'buka'     => $h['buka'],
        'dpt'      => $h['dpt'],
        'masuk'    => $h['masuk'],
        'belum'    => $h['belum'],
        'turnout'  => $h['turnout'],
        'sah'      => $h['sah'],
        'jam'      => $h['diperbarui'],
        'paslon'   => array_map(static fn (array $p): array => [
            'id' => $p['id'], 'nomor' => $p['nomor'],
            'nama' => $p['ketua'] . ' & ' . $p['wakil'],
            'suara' => $p['suara'], 'persen' => $p['persen'], 'warna' => $p['warna'],
        ], $h['paslon']),
    ];
}

// =====================================================================================
// TAMPILAN — PAPAN HASIL REAL-COUNT
// =====================================================================================

/** Keterangan ramah bila sumber data e-Voting tidak dapat dibaca. */
function voting_kosong_sumber(): void
{
    echo '<section class="vt-blok">'
        . '<div class="vt-kosong">' . ikon('papan', 34)
        . '<h3>Hasil e-Voting belum dapat ditampilkan</h3>'
        . '<p>Halaman ini menampilkan <b>papan hasil Real-Count</b> aplikasi <b>e-Voting OSIM</b>.
             Sumber datanya belum dapat dibaca saat ini (aplikasi e-Voting belum dipasang di server,
             atau berkas datanya tidak dapat diakses).</p>'
        . '</div></section>';
}

/** Keterangan ketika panitia belum membuka hasil ke publik (mengikuti setelan aplikasi). */
function voting_belum_dibuka(array $h): void
{
    ?>
<section class="vt-blok">
  <div class="vt-kosong" id="votingBelumDibuka">
    <span class="vt-ikon-besar"><?= ikon('kunci', 30) ?></span>
    <h3>Hasil pemilihan belum dibuka</h3>
    <p>Papan hasil <b><?= e($h['pemilihan']) ?></b> belum dibuka oleh panitia
       <?= $h['lembaga'] !== '' ? 'di ' . e($h['lembaga']) : '' ?>. Sesuai aturan aplikasi
       e-Voting, angka perolehan suara baru dapat dilihat setelah panitia membuka papan hasil.</p>
    <?php if ($h['periode'] !== '' || $h['tanggal'] !== ''): ?>
      <p class="vt-ket-kecil"><?= $h['periode'] !== '' ? 'Periode ' . e($h['periode']) : '' ?>
        <?= $h['periode'] !== '' && $h['tanggal'] !== '' ? ' &middot; ' : '' ?>
        <?= $h['tanggal'] !== '' ? 'Pemungutan suara ' . e($h['tanggal']) : '' ?></p>
    <?php endif; ?>
    <p class="vt-ket-kecil">Silakan kembali lagi setelah panitia membuka papan hasil.</p>
  </div>
</section>
    <?php
}

/**
 * Mencetak PAPAN HASIL REAL-COUNT e-Voting (menu Informasi → e-Voting OSIM).
 */
function voting_papan(): void
{
    if (!voting_siap()) {
        echo '<div class="vt-papan" id="papanVoting">';
        voting_kosong_sumber();
        echo '</div>';
        return;
    }
    if (!voting_lembaga_cocok()) {
        echo '<div class="vt-papan" id="papanVoting">'
            . '<section class="vt-blok"><div class="vt-kosong">' . ikon('papan', 34)
            . '<h3>Data e-Voting bukan untuk sekolah/madrasah ini</h3>'
            . '<p>Basis data aplikasi e-Voting yang terbaca memuat pemilihan milik
                 <b>' . e(voting_setting('nama_lembaga', '(tidak diketahui)')) . '</b>, sedangkan halaman ini
                 khusus untuk <b>' . e((string) VOTING_LEMBAGA) . '</b>. Tanyakan kepada pengelola aplikasi.</p>'
            . '</div></section></div>';
        return;
    }

    $h = voting_hasil();

    if (!$h['buka']) {
        echo '<div class="vt-papan" id="papanVoting">';
        voting_belum_dibuka($h);
        echo '</div>';
        return;
    }

    $paslon = $h['paslon'];
    $tinggiBatang = min(300, max(132, 26 + (count($paslon) * 42)));
    ?>
<div class="vt-papan" id="papanVoting"
     data-voting data-api="voting_hasil.php" data-jeda="<?= (int) $h['polling'] ?>">

  <?php /* ============ KEPALA PAPAN ============ */ ?>
  <section class="vt-blok vt-kepala">
    <div>
      <span class="vt-pil-hidup"><i></i> <span data-vt-status>LIVE</span></span>
      <h2><?= e($h['pemilihan']) ?></h2>
      <p><?= e($h['lembaga']) ?><?= $h['periode'] !== '' ? ' &middot; ' . e($h['periode']) : '' ?><?= $h['tanggal'] !== '' ? ' &middot; ' . e($h['tanggal']) : '' ?></p>
    </div>
    <div class="vt-kepala-kanan">
      <span class="vt-lbl">Partisipasi</span>
      <span class="vt-besar" data-vt="turnout"><?= e(voting_persen($h['turnout'])) ?></span>
      <span class="vt-lbl">Diperbarui <span data-vt-jam><?= e($h['diperbarui']) ?></span> <?= e($h['zona']) ?></span>
    </div>
  </section>

  <?php /* ============ KARTU STATISTIK ============ */ ?>
  <section class="vt-grid vt-g4" aria-label="Statistik pemilihan">
    <div class="vt-kartu vt-kartu-stat">
      <span class="vt-ik"><?= ikon('users', 19) ?></span>
      <div>
        <div class="vt-angka" data-vt="dpt"><?= e(voting_angka($h['dpt'])) ?></div>
        <div class="vt-sub">Pemilih Terdaftar</div>
      </div>
    </div>
    <div class="vt-kartu vt-kartu-stat">
      <span class="vt-ik s"><?= ikon('tanda', 19) ?></span>
      <div>
        <div class="vt-angka" data-vt="masuk"><?= e(voting_angka($h['masuk'])) ?></div>
        <div class="vt-sub">Suara Masuk</div>
      </div>
    </div>
    <div class="vt-kartu vt-kartu-stat">
      <span class="vt-ik o"><?= ikon('jam', 19) ?></span>
      <div>
        <div class="vt-angka" data-vt="belum"><?= e(voting_angka($h['belum'])) ?></div>
        <div class="vt-sub">Belum Memilih</div>
      </div>
    </div>
    <div class="vt-kartu vt-kartu-stat vt-kartu-stat-utama">
      <span class="vt-ik g"><?= ikon('trophy', 19) ?></span>
      <div class="vt-kartu-stat-tubuh">
        <div class="vt-angka" data-vt="turnout"><?= e(voting_persen($h['turnout'])) ?></div>
        <div class="vt-sub">Tingkat Partisipasi</div>
        <div class="vt-bar vt-bar-kecil"><i data-vt-bar="turnout" style="width:<?= e(number_format(min(100, (float) $h['turnout']), 1, '.', '')) ?>%"></i></div>
      </div>
    </div>
  </section>

  <?php /* ============ GRAFIK DONAT + PEMIMPIN SEMENTARA + BATANG ============ */ ?>
  <section class="vt-grid vt-g21 vt-atas">
    <div class="vt-kartu">
      <div class="vt-kartu-kepala">
        <div><h3>Persentase Perolehan Suara</h3><p>Dihitung dari total suara sah.</p></div>
      </div>
      <?php if ($paslon): ?>
        <div class="vt-chart vt-chart-donat"><canvas id="vtDonat"></canvas></div>
        <ul class="vt-legenda">
          <?php foreach ($paslon as $p): ?>
            <li>
              <i style="background:<?= e($p['warna']) ?>"></i>
              <span class="vt-legenda-nama">Paslon <?= e((string) $p['nomor']) ?></span>
              <b data-vt-persen="<?= (int) $p['id'] ?>"><?= e(voting_persen($p['persen'])) ?></b>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="vt-kosong vt-kosong-kecil"><p>Belum ada pasangan calon yang terdaftar.</p></div>
      <?php endif; ?>
    </div>

    <div class="vt-tumpuk">
      <div class="vt-kartu" id="vtPemimpin">
        <div class="vt-kartu-kepala"><div><h3>Perolehan Suara Terbanyak</h3><p>Perolehan tertinggi sementara.</p></div></div>
        <?php if ($h['teratas'] !== null): $t = $h['teratas']; ?>
          <div class="vt-pemimpin" data-vt-pemimpin>
            <span class="vt-nomor" style="background:<?= e($t['warna']) ?>"><?= e((string) $t['nomor']) ?></span>
            <div class="vt-pemimpin-teks">
              <span class="vt-lbl">Perolehan tertinggi sementara</span>
              <b data-vt-pemimpin-nama><?= e($t['ketua'] . ' & ' . $t['wakil']) ?></b>
            </div>
            <div class="vt-pemimpin-kanan">
              <span class="vt-besar" data-vt-pemimpin-suara><?= e(voting_angka($t['suara'])) ?></span>
              <span class="vt-sub">suara</span>
            </div>
          </div>
        <?php else: ?>
          <div class="vt-kosong vt-kosong-kecil" data-vt-kosong-pemimpin>
            <p>Belum ada surat suara yang masuk.</p>
          </div>
        <?php endif; ?>
      </div>

      <div class="vt-kartu">
        <div class="vt-kartu-kepala">
          <div><h3>Jumlah Suara per Pasangan Calon</h3><p>Perbandingan perolehan suara.</p></div>
        </div>
        <div class="vt-chart" style="height:<?= (int) $tinggiBatang ?>px"><canvas id="vtBatang"></canvas></div>
      </div>
    </div>
  </section>

  <?php /* ============ RINCIAN TIAP PASLON ============ */ ?>
  <section class="vt-blok">
    <div class="vt-kartu-kepala">
      <div><h3>Rincian Perolehan Suara</h3><p>Total suara sah: <b data-vt="sah"><?= e(voting_angka($h['sah'])) ?></b>.</p></div>
    </div>
    <div class="vt-rincian">
      <?php if (!$paslon): ?>
        <div class="vt-kosong vt-kosong-kecil"><p>Belum ada data pasangan calon.</p></div>
      <?php endif; ?>
      <?php foreach ($paslon as $p): ?>
        <article class="vt-baris" data-vt-baris="<?= (int) $p['id'] ?>">
          <span class="vt-nomor" style="background:<?= e($p['warna']) ?>"><?= e((string) $p['nomor']) ?></span>
          <?php if ($p['foto'] !== ''): ?>
            <img class="vt-foto" src="<?= e($p['foto']) ?>" alt="Foto paslon <?= e((string) $p['nomor']) ?>" loading="lazy">
          <?php endif; ?>
          <div class="vt-baris-teks">
            <b><?= e($p['ketua']) ?> &amp; <?= e($p['wakil']) ?>
              <?php if ($p['aktif'] !== 1): ?><span class="vt-pil vt-pil-gray">nonaktif</span><?php endif; ?>
            </b>
            <?php if ($p['kelas_ketua'] !== '' || $p['kelas_wakil'] !== ''): ?>
              <span class="vt-sub"><?= e($p['kelas_ketua'] !== '' ? $p['kelas_ketua'] : '-') ?> &middot; <?= e($p['kelas_wakil'] !== '' ? $p['kelas_wakil'] : '-') ?></span>
            <?php endif; ?>
            <div class="vt-bar"><i data-vt-bar-persen="<?= (int) $p['id'] ?>" style="width:<?= e(number_format((float) $p['persen'], 1, '.', '')) ?>%;background:<?= e($p['warna']) ?>"></i></div>
          </div>
          <div class="vt-baris-kanan">
            <span class="vt-besar" data-vt-persen2="<?= (int) $p['id'] ?>"><?= e(voting_persen($p['persen'])) ?></span>
            <span class="vt-sub"><b data-vt-suara="<?= (int) $p['id'] ?>"><?= e(voting_angka($p['suara'])) ?></b> suara</span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="vt-ket">Papan ini menyegarkan dirinya sendiri setiap <?= (int) $h['polling'] ?> detik tanpa
       memuat ulang halaman. Datanya dibaca langsung dari aplikasi <b>e-Voting OSIM</b>.</p>
  </section>
</div>

<script src="assets/vendor/chart.umd.min.js?v=<?= e(APP_VERSION) ?>"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    window.VT_DATA = <?= json_encode(voting_json($h), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (window.SVV) { window.SVV.mulai(); }
  });
</script>
    <?php
}
