<?php
/**
 * INFORMASI "EVALUASI DIRI MADRASAH" (menu Informasi → Evaluasi Diri Madrasah) — tampilan
 * **Indikator EDM** dari aplikasi EDM.
 *
 * Permintaan pemilik: *"Pada bagian Evaluasi Diri Madrasah isi dengan tampilan Indikator EDM pada
 * aplikasi EDM, hanya saja hilangkan Cetak Rekap dan Ringkasan Pengisian Tiap Aspek selebihnya buat
 * sama."*
 * Jadi halaman ini memuat: kartu **Indikator EDM** (pemilih tahun + nilai SKPM + predikat + jumlah
 * butir terisi), **tab Charts / Tabel**, grafik **radar** tiap aspek, tabel **No, Aspek, Nilai SKPM,
 * Terbilang** dengan baris kaki **SKPM** — **TANPA** tombol *Cetak Rekap* dan **TANPA** kartu
 * *Ringkasan Pengisian Tiap Aspek*.
 *
 * PENTING / JANGAN DIUBAH tanpa alasan kuat:
 *  1. Modul ini **TIDAK memuat** kode aplikasi EDM (`edm/inc/init.php`) — konstanta & fungsinya akan
 *     bertabrakan dengan aplikasi ini. Seluruh pembacaan ditulis ulang di sini memakai koneksi
 *     TERPISAH yang HANYA-BACA (`mode=ro`, cadangan `query_only=1`).
 *  2. Yang dibaca **hanya tabel `evaluasi`** (tahun, butir, tingkat) + kunci `tahun` pada
 *     `app_pengaturan`. Nama/isi butir, jawaban, dan berkas bukti tidak diambil (tidak ditampilkan
 *     pada halaman ini).
 *  3. **Rumus nilai mengikuti aplikasi EDM** (lihat `edm/inc/hitung.php`): Tingkat 1-4 bernilai
 *     25/50/75/100, nilai aspek = rata-rata butir yang SUDAH diisi, SKPM = rata-rata kelima aspek,
 *     predikat Sangat Baik ≥ 80, Baik ≥ 60, Cukup ≥ 40, Kurang < 40. Bila rumus itu diubah di
 *     aplikasi EDM, ubah juga di sini supaya angkanya sama.
 *  4. Jumlah butir tiap aspek diambil dari konstanta `EDM_BUTIR_JUMLAH` **dan** dari kode butir yang
 *     benar-benar ada di basis data (diambil yang TERBESAR), sehingga bila pemilik menambah butir di
 *     aplikasi EDM, penyebut "x dari N butir" ikut menyesuaikan tanpa menyunting berkas ini.
 */
declare(strict_types=1);

/**
 * Jumlah butir instrumen EDM per aspek (mengikuti instrumen "Data Indikator 2026" pada aplikasi EDM:
 * A1-A7, B1-B4, C1-C7, D1-D5, E1-E3 = 26 butir). Dipakai sebagai penyebut "terisi dari N butir";
 * kode butir yang ada di basis data tetap diperhitungkan bila lebih banyak (lihat edm_aspek_data()).
 */
const EDM_BUTIR_JUMLAH = ['A' => 7, 'B' => 4, 'C' => 7, 'D' => 5, 'E' => 3];

/** Nilai tiap Tingkat (1-4) — sama dengan aplikasi EDM. */
const EDM_TINGKAT_NILAI = [4 => 100.0, 3 => 75.0, 2 => 50.0, 1 => 25.0];

/** Ambang predikat SKPM (batas bawah) — sama dengan aplikasi EDM. */
const EDM_PREDIKAT_AMBANG = ['Sangat Baik' => 80.0, 'Baik' => 60.0, 'Cukup' => 40.0, 'Kurang' => 0.0];

// =====================================================================================
// KONEKSI BACA-SAJA ke basis data aplikasi EDM
// =====================================================================================

/** Berkas basis data EDM (env → pengaturan → konstanta → bawaan). */
function edm_db_file(): string
{
    static $berkas = null;
    if ($berkas !== null) {
        return $berkas;
    }
    $kandidat = [];
    $env = trim((string) getenv('WEBSK_EDM_DB'));
    if ($env !== '') {
        $kandidat[] = $env;
    }
    if (function_exists('pengaturan')) {
        $kustom = trim(pengaturan('edm_db', ''));
        if ($kustom !== '') {
            $kandidat[] = $kustom;
        }
    }
    if (defined('EDM_DB_FILE')) {
        $kandidat[] = (string) EDM_DB_FILE;
    }
    $kandidat[] = dirname(__DIR__) . '/edm/data/edm.sqlite';

    foreach ($kandidat as $k) {
        if ($k !== '' && is_file($k) && is_readable($k)) {
            return $berkas = $k;
        }
    }
    return $berkas = '';
}

/** Koneksi BACA-SAJA (null bila tidak tersedia). */
function edm_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo instanceof PDO ? $pdo : null;
    }
    $pdo = null;
    $berkas = edm_db_file();
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
            $coba->query('SELECT COUNT(*) FROM evaluasi')->fetchColumn();
            $pdo = $coba;
            return $pdo;
        } catch (Throwable $e) {
            // coba cara berikutnya
        }
    }
    return null;
}

/** Apakah sumber data EDM tersedia? */
function edm_siap(): bool
{
    return edm_db() instanceof PDO;
}

function edm_all(string $sql, array $param = []): array
{
    $pdo = edm_db();
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

function edm_nilai(string $sql, array $param = [], $bawaan = null)
{
    $pdo = edm_db();
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

// =====================================================================================
// ASPEK & PERHITUNGAN NILAI (mengikuti aplikasi EDM)
// =====================================================================================

/** Daftar aspek EDM: kode => nomor, judul, judul singkat (untuk grafik). */
function edm_aspek(): array
{
    return [
        'A' => ['nomor' => 1, 'judul' => 'Budaya kedisiplinan bagi warga madrasah',
                'singkat' => 'Kedisiplinan'],
        'B' => ['nomor' => 2, 'judul' => 'Budaya melakukan pengembangan diri bagi guru dan tenaga kependidikan',
                'singkat' => 'Pengembangan Diri'],
        'C' => ['nomor' => 3, 'judul' => 'Budaya guru melakukan penyiapan, pelaksanaan dan penilaian atas proses pembelajaran',
                'singkat' => 'Proses Pembelajaran'],
        'D' => ['nomor' => 4, 'judul' => 'Budaya madrasah menyediakan sarana belajar untuk guru dan siswa',
                'singkat' => 'Sarana dan Prasarana'],
        'E' => ['nomor' => 5, 'judul' => 'Budaya madrasah melakukan pengelolaan anggaran yang transparan dan berorientasi pada peningkatan mutu',
                'singkat' => 'Pembiayaan'],
    ];
}

/** Tahun EDM yang sedang berjalan menurut aplikasi EDM (kunci `tahun` pada `app_pengaturan`). */
function edm_tahun_aktif(): int
{
    $t = (int) edm_nilai('SELECT nilai FROM app_pengaturan WHERE kunci = ?', ['tahun'], 0);
    return $t >= 2000 && $t <= 2100 ? $t : (int) date('Y');
}

/** Daftar tahun EDM yang dapat dipilih (tahun yang punya data + tahun berjalan). */
function edm_tahun_daftar(): array
{
    $ada = [];
    foreach (edm_all('SELECT DISTINCT tahun FROM evaluasi ORDER BY tahun DESC') as $r) {
        $t = (int) $r['tahun'];
        if ($t >= 2000 && $t <= 2100) {
            $ada[] = $t;
        }
    }
    $aktif = edm_tahun_aktif();
    if (!in_array($aktif, $ada, true)) {
        $ada[] = $aktif;
    }
    rsort($ada);
    return array_values(array_unique($ada));
}

/** Peta baris evaluasi satu tahun: butir => baris (tingkat, dst.). */
function edm_peta(int $tahun): array
{
    $keluar = [];
    foreach (edm_all('SELECT butir, aspek, tingkat FROM evaluasi WHERE tahun = ?', [$tahun]) as $r) {
        $keluar[(string) $r['butir']] = $r;
    }
    return $keluar;
}

/** Nilai satu Tingkat (1-4) → 25/50/75/100; null bila belum diisi. */
function edm_nilai_tingkat($tingkat): ?float
{
    $t = (int) $tingkat;
    return EDM_TINGKAT_NILAI[$t] ?? null;
}

/** Predikat dari nilai SKPM (mengikuti ambang aplikasi EDM). */
function edm_predikat($nilai): string
{
    if ($nilai === null || $nilai === '' ) {
        return '-';
    }
    $n = (float) $nilai;
    foreach (EDM_PREDIKAT_AMBANG as $label => $batas) {
        if ($n >= $batas) {
            return $label;
        }
    }
    return 'Kurang';
}

/** Kelas pil predikat (warna; sepadan dengan aplikasi EDM). */
function edm_predikat_kelas($nilai): string
{
    switch (edm_predikat($nilai)) {
        case 'Sangat Baik':
            return 'edm-pil-ok';
        case 'Baik':
            return 'edm-pil-info';
        case 'Cukup':
            return 'edm-pil-warn';
        case 'Kurang':
            return 'edm-pil-err';
        default:
            return 'edm-pil-mut';
    }
}

/** Angka nilai dengan satu desimal (dipakai "-" bila kosong) — sama dengan `n1()` aplikasi EDM. */
function edm_n1($n): string
{
    if ($n === null || $n === '') {
        return '-';
    }
    return number_format((float) $n, 1, '.', ',');
}

/** Jumlah butir tiap aspek: dari konstanta DAN kode butir yang ada di basis data (ambil terbesar). */
function edm_jumlah_butir(array $peta): array
{
    $keluar = EDM_BUTIR_JUMLAH;
    foreach (array_keys(edm_aspek()) as $kode) {
        if (!isset($keluar[$kode])) {
            $keluar[$kode] = 0;
        }
    }
    foreach ($peta as $butir => $row) {
        $butir = strtoupper(trim((string) $butir));
        if (!preg_match('/^([A-E])(\d+)$/', $butir, $m)) {
            continue;
        }
        $aspek = $m[1];
        if (isset($keluar[$aspek])) {
            $keluar[$aspek] = max($keluar[$aspek], (int) $m[2]);
        }
    }
    return $keluar;
}

/**
 * Nilai satu aspek pada satu tahun (rumus aplikasi EDM).
 * @return array{aspek:string,nilai:?float,terisi:int,jumlah:int,tingkat:array<string,int>}
 */
function edm_nilai_aspek(string $aspek, array $peta, array $jumlahButir): array
{
    $aspek = strtoupper($aspek);
    $jumlah = (int) ($jumlahButir[$aspek] ?? 0);
    $jumlahNilai = 0.0;
    $terisi = 0;
    $tingkat = [];
    for ($i = 1; $i <= $jumlah; $i++) {
        $butir = $aspek . $i;
        $row = $peta[$butir] ?? null;
        $t = ($row && $row['tingkat'] !== null && $row['tingkat'] !== '') ? (int) $row['tingkat'] : 0;
        $tingkat[$butir] = $t;
        $nilai = edm_nilai_tingkat($t);
        if ($nilai !== null) {
            $jumlahNilai += $nilai;
            $terisi++;
        }
    }
    return [
        'aspek' => $aspek,
        'nilai' => $terisi > 0 ? $jumlahNilai / $terisi : null,
        'terisi' => $terisi,
        'jumlah' => $jumlah,
        'tingkat' => $tingkat,
    ];
}

/**
 * Rekap seluruh aspek + nilai SKPM satu tahun (rumus & keluaran mengikuti aplikasi EDM).
 * @return array{tahun:int,aspek:array<string,array>,nilai:?float,predikat:string,diisi:int,total:int,lengkap:bool}
 */
function edm_skpm(int $tahun, ?array $peta = null): array
{
    $peta = $peta ?? edm_peta($tahun);
    $jumlahButir = edm_jumlah_butir($peta);
    $aspek = [];
    $jumlah = 0.0;
    $jumlahAspek = 0;
    $diisi = 0;
    $total = 0;
    foreach (array_keys(edm_aspek()) as $kode) {
        $a = edm_nilai_aspek($kode, $peta, $jumlahButir);
        $aspek[$kode] = $a;
        $diisi += (int) $a['terisi'];
        $total += (int) $a['jumlah'];
        if ($a['nilai'] !== null) {
            $jumlah += (float) $a['nilai'];
            $jumlahAspek++;
        }
    }
    $nilai = $jumlahAspek > 0 ? $jumlah / $jumlahAspek : null;
    return [
        'tahun' => $tahun,
        'aspek' => $aspek,
        'nilai' => $nilai,
        'predikat' => edm_predikat($nilai),
        'diisi' => $diisi,
        'total' => $total,
        'lengkap' => $total > 0 && $diisi >= $total,
    ];
}

// =====================================================================================
// TAMPILAN
// =====================================================================================

/** Keterangan ramah bila sumber data aplikasi EDM tidak dapat dibaca. */
function edm_kosong_sumber(): void
{
    echo '<section class="edm-blok">'
        . '<div class="edm-kosong">' . ikon('clipboard', 34)
        . '<h3>Data Evaluasi Diri Madrasah belum dapat ditampilkan</h3>'
        . '<p>Halaman ini menampilkan <b>Indikator EDM</b> (nilai SKPM tiap aspek) yang datanya diambil
             otomatis dari aplikasi <b>EDM</b>. Sumber datanya belum dapat dibaca saat ini (aplikasi EDM
             belum dipasang di server, atau berkas datanya tidak dapat diakses).</p>'
        . '</div></section>';
}

/**
 * Mencetak halaman "Indikator EDM" (menu Informasi → Evaluasi Diri Madrasah).
 *
 * ⚠️ Bagian yang SENGAJA TIDAK ADA (permintaan pemilik): tombol **Cetak Rekap** dan kartu
 * **Ringkasan Pengisian Tiap Aspek**. Jangan ditambahkan kembali tanpa permintaan.
 */
function edm_papan(): void
{
    if (!edm_siap()) {
        echo '<div class="edm-papan" id="papanEdm">';
        edm_kosong_sumber();
        echo '</div>';
        return;
    }

    $tahun = (int) ($_GET['tahun'] ?? 0);
    if ($tahun < 2000 || $tahun > 2100 || !in_array($tahun, edm_tahun_daftar(), true)) {
        $tahun = edm_tahun_aktif();
    }
    $tampilan = (string) ($_GET['tampilan'] ?? 'tabel');
    if (!in_array($tampilan, ['tabel', 'grafik'], true)) {
        $tampilan = 'tabel';
    }

    $peta = edm_peta($tahun);
    $skpm = edm_skpm($tahun, $peta);
    $aspek = edm_aspek();

    // Data grafik radar: label singkat + nilai tiap aspek.
    $label = [];
    $nilaiGrafik = [];
    foreach ($aspek as $kode => $a) {
        $label[] = $a['singkat'];
        $v = $skpm['aspek'][$kode]['nilai'];
        $nilaiGrafik[] = $v === null ? 0 : round((float) $v, 1);
    }
    $dataJs = [
        'tahun' => $tahun,
        'label' => $label,
        'nilai' => $nilaiGrafik,
        'skpm' => $skpm['nilai'] === null ? 0 : round((float) $skpm['nilai'], 1),
    ];
    ?>
<div class="edm-papan" id="papanEdm">

  <?php /* ============ KARTU INDIKATOR EDM ============ */ ?>
  <section class="edm-blok">
    <div class="edm-indikator">
      <div class="edm-indikator-kiri">
        <h2 class="edm-mb0">Indikator EDM</h2>
        <p class="edm-mut">Perbandingan Hasil EDM madrasah tahunan</p>
        <form method="get" action="<?= e(rute_kanonik()) ?>" class="filter-baris" id="formEdmTahun">
          <input type="hidden" name="tampilan" value="<?= e($tampilan) ?>">
          <label for="edmTahun">Tahun EDM</label>
          <select id="edmTahun" name="tahun" data-auto>
            <?php foreach (edm_tahun_daftar() as $t): ?>
              <option value="<?= (int) $t ?>" <?= $t === $tahun ? 'selected' : '' ?>>EDM Tahun <?= (int) $t ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button class="tombol tombol-kecil" type="submit">Tampilkan</button></noscript>
        </form>
        <p class="edm-mut edm-kecil edm-mb0">
          <?= angka($skpm['diisi']) ?> dari <?= angka($skpm['total']) ?> butir sudah diisi<?php
          if (!$skpm['lengkap']): ?> &mdash; nilai masih sementara<?php endif; ?>.
        </p>
      </div>
      <div class="edm-indikator-nilai">
        <b class="edm-angka"><?= e(edm_n1($skpm['nilai'])) ?></b>
        <span class="edm-mut edm-kecil">Nilai SKPM</span>
        <span class="edm-pil edm-pil-besar <?= e(edm_predikat_kelas($skpm['nilai'])) ?>"><?= e(strtoupper($skpm['predikat'])) ?></span>
      </div>
    </div>
  </section>

  <?php /* ============ TAB CHARTS / TABEL (TANPA tombol Cetak Rekap) ============ */ ?>
  <section class="edm-blok">
    <div class="edm-tab-baris">
      <a class="edm-tab<?= $tampilan === 'grafik' ? ' on' : '' ?>"
         href="<?= e(rute_tautan(rute_kanonik(), ['tahun' => $tahun, 'tampilan' => 'grafik'])) ?>">Charts</a>
      <a class="edm-tab<?= $tampilan === 'tabel' ? ' on' : '' ?>"
         href="<?= e(rute_tautan(rute_kanonik(), ['tahun' => $tahun, 'tampilan' => 'tabel'])) ?>">Tabel</a>
    </div>

    <?php if ($tampilan === 'grafik'): ?>
      <div class="edm-chart"><canvas id="chEdmIndikator" aria-label="Grafik Indikator EDM" role="img"></canvas></div>
      <p class="edm-mut edm-kecil edm-tengah edm-mb0"><?= ikon('grafik', 15) ?> EDM Tahun <?= e((string) $tahun) ?></p>
      <div class="edm-tabel-wrap mt16">
        <table class="edm-tabel">
          <thead><tr><th>Aspek</th><th class="edm-c-nilai">Nilai</th></tr></thead>
          <tbody>
          <?php foreach ($aspek as $kode => $a): ?>
            <tr>
              <td><?= e($kode) ?>. <?= e($a['judul']) ?></td>
              <td class="edm-c-nilai"><b><?= e(edm_n1($skpm['aspek'][$kode]['nilai'])) ?></b></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="edm-geser">
        <table class="edm-tabel edm-tabel-indikator" id="tblIndikator">
          <thead>
          <tr>
            <th class="edm-c-no">No</th>
            <th>Aspek</th>
            <th class="edm-c-nilai">Nilai SKPM</th>
            <th class="edm-c-terbilang">Terbilang</th>
          </tr>
          </thead>
          <tbody>
          <?php foreach ($aspek as $kode => $a):
              $n = $skpm['aspek'][$kode]['nilai'];
              $terisi = (int) $skpm['aspek'][$kode]['terisi'];
              $jumlah = (int) $skpm['aspek'][$kode]['jumlah'];
              ?>
            <tr>
              <td class="edm-c-no"><?= (int) $a['nomor'] ?></td>
              <td>
                <b><?= e($a['judul']) ?></b>
                <i class="edm-mut edm-kecil edm-sub"><?= angka($terisi) ?> dari <?= angka($jumlah) ?> butir terisi</i>
              </td>
              <td class="edm-c-nilai"><?= e(edm_n1($n)) ?></td>
              <td class="edm-c-terbilang">
                <span class="edm-pil edm-pil-besar <?= e(edm_predikat_kelas($n)) ?>"><?= e(strtoupper(edm_predikat($n))) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
          <tr>
            <td class="edm-c-no"></td>
            <td><b>(SKPM) Skor Kinerja Pencapaian Mutu</b></td>
            <td class="edm-c-nilai"><b class="edm-angka-kaki"><?= e(edm_n1($skpm['nilai'])) ?></b></td>
            <td class="edm-c-terbilang">
              <span class="edm-pil edm-pil-besar <?= e(edm_predikat_kelas($skpm['nilai'])) ?>"><?= e(strtoupper($skpm['predikat'])) ?></span>
            </td>
          </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<script id="dataEdm" type="application/json"><?= json_encode($dataJs,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/vendor/chart.umd.min.js?v=<?= e(APP_VERSION) ?>"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (!window.EDMW) { return; }
    window.EDMW.radar('chEdmIndikator', <?= json_encode($label, JSON_UNESCAPED_UNICODE) ?>,
      <?= json_encode($nilaiGrafik) ?>, <?= e((string) $tahun) ?>);
  });
</script>
    <?php
}
