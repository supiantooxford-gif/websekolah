<?php
/**
 * HALAMAN "KOHORT SISWA" (menu Siswa → Kohort Siswa) — tabel analisis kohort + grafiknya.
 *
 * Permintaan pemilik:
 *   "Pada bagian Kohort Siswa isi dengan tabel seperti pada contoh, dibawah tabel tambahkan analisis
 *    grafiknya berdasarkan tabel tersebut (yang di tampilkan adalah 5 tahun terakhir. Ketika di input
 *    tahun ajaran baru otomatis tahun ajaran paling lama akan hilang dan posisi bergeser. tabel di isi
 *    oleh super admin di halaman super admin."
 *
 * Aturan yang dipakai (jangan diubah tanpa bertanya kepada pemilik):
 *   1. Tabel memuat kolom persis seperti contoh: **Tahun Ajaran · PMB (Pendaftar, Diterima) ·
 *      Kelas X / XI / XII (Jumlah, Mutasi Masuk, Mutasi Keluar, Tinggal Kelas, Naik Kelas, Drop Out)**.
 *   2. Halaman publik **hanya menampilkan 5 tahun ajaran TERBARU** (`KOHORT_TAHUN_TAMPIL`).
 *      Menambah tahun ajaran baru membuat tahun paling lama tidak lagi ditampilkan (posisi bergeser)
 *      — barisnya TIDAK dihapus dari basis data supaya angkanya tetap tersimpan sebagai arsip.
 *   3. Angka tabel diisi **Super Admin** pada panel admin (`admin/kohort.php`).
 *   4. Di bawah tabel dicetak **analisis grafik** dari angka tabel itu (3 grafik + ringkasan angka)
 *      untuk tahun-tahun yang sedang ditampilkan.
 *
 * Catatan teknis:
 *   • Semua angka disimpan pada tabel `kohort` (satu baris = satu tahun ajaran), lihat `inc/db.php`.
 *   • Chart.js dimuat lokal (`assets/vendor/chart.umd.min.js`) dan hanya pada halaman ini.
 *   • Angka grafik berasal dari tabel di atasnya, jadi bila skrip gagal dimuat seluruh angka tetap
 *     terbaca (fallback yang disengaja — jangan menghapus tabelnya).
 *   • Gaya `.kohort-*` ada pada `assets/style.css` — **jangan** menaruh `<style>` di halaman.
 */
declare(strict_types=1);

/** Tingkat kelas pada tabel kohort: kunci kolom => label tampilan. */
const KOHORT_TINGKAT = ['x' => 'Kelas X', 'xi' => 'Kelas XI', 'xii' => 'Kelas XII'];

/** Kolom angka tiap tingkat (urutan tampilan seperti contoh pemilik). */
const KOHORT_KOLOM_TINGKAT = [
    'jumlah'  => 'Jumlah',
    'masuk'   => 'Mutasi Masuk',
    'keluar'  => 'Mutasi Keluar',
    'tinggal' => 'Tinggal Kelas',
    'naik'    => 'Naik Kelas',
    'dropout' => 'Drop Out',
];

/**
 * Kolom grup **Lulus** (v1.14.0 — permintaan pemilik: *"Tabel Kohort pada tampilan ditambahkan
 * Kelulusan, seperti yang di upload"*): Jumlah Lulus, Kuliah, Bekerja, Lainnya.
 */
const KOHORT_KOLOM_LULUS = [
    'jumlah'   => 'Jumlah Lulus',
    'kuliah'   => 'Kuliah',
    'bekerja'  => 'Bekerja',
    'lainnya'  => 'Lainnya',
];

/**
 * Daftar kolom ANGKA tabel kohort (tanpa kolom Tahun Ajaran).
 *
 * @return array<int,array{kunci:string,label:string,grup:string}>
 */
function kohort_kolom(): array
{
    $kolom = [
        ['kunci' => 'pmb_pendaftar', 'label' => 'Pendaftar', 'grup' => 'pmb'],
        ['kunci' => 'pmb_diterima', 'label' => 'Diterima', 'grup' => 'pmb'],
    ];
    foreach (KOHORT_TINGKAT as $kode => $label) {
        foreach (KOHORT_KOLOM_TINGKAT as $kol => $labelKol) {
            $kolom[] = ['kunci' => $kode . '_' . $kol, 'label' => $labelKol, 'grup' => $kode];
        }
    }
    foreach (KOHORT_KOLOM_LULUS as $kol => $labelKol) {
        $kolom[] = ['kunci' => 'lulus_' . $kol, 'label' => $labelKol, 'grup' => 'lulus'];
    }
    return $kolom;
}

/** Tahun awal sebuah tahun ajaran ("2021/2022" → 2021); 0 bila tidak dapat dibaca. */
function kohort_ta_awal(string $ta): int
{
    if (preg_match('#^\s*(\d{4})#', $ta, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/** Normalkan penulisan tahun ajaran ("2021-2022" / "2021 2022" / "2021" → "2021/2022"). */
function kohort_ta_norm(string $ta): string
{
    $ta = trim($ta);
    if ($ta === '') {
        return '';
    }
    $ta = preg_replace('#\s*[/–—-]\s*#u', '/', $ta) ?? $ta;
    $ta = preg_replace('#\s+#u', '/', $ta) ?? $ta;
    if (preg_match('#^(\d{4})$#', $ta, $m)) {
        return $m[1] . '/' . ((int) $m[1] + 1);
    }
    return $ta;
}

/** Tahun ajaran sah? Harus dua tahun BERURUTAN, mis. 2025/2026. */
function kohort_ta_sah(string $ta): bool
{
    return (bool) preg_match('#^(\d{4})/(\d{4})$#', $ta, $m) && (int) $m[2] === (int) $m[1] + 1;
}

/** Label pendek tahun ajaran untuk keterangan ("2025/2026"). */
function kohort_label(string $ta): string
{
    return trim($ta) !== '' ? $ta : '-';
}

/**
 * Seluruh baris kohort, urut tahun ajaran MENAIK (seperti contoh pemilik).
 *
 * @return array<int,array<string,mixed>>
 */
function kohort_semua(): array
{
    $baris = all('SELECT * FROM kohort ORDER BY ta, id');
    usort($baris, static fn (array $a, array $b): int => [kohort_ta_awal((string) $a['ta']), (int) $a['id']]
        <=> [kohort_ta_awal((string) $b['ta']), (int) $b['id']]);
    return $baris;
}

/**
 * Baris yang DITAMPILKAN di halaman publik: `KOHORT_TAHUN_TAMPIL` tahun ajaran TERBARU, urut menaik.
 * Tahun paling lama otomatis tidak ikut tampil saat ada tahun ajaran baru (posisi bergeser).
 *
 * @return array<int,array<string,mixed>>
 */
function kohort_tampil(int $maks = 0): array
{
    $maks = $maks > 0 ? $maks : (int) KOHORT_TAHUN_TAMPIL;
    $semua = kohort_semua();
    if ($maks > 0 && count($semua) > $maks) {
        $semua = array_slice($semua, -$maks);   // yang terakhir = tahun ajaran terbaru
    }
    return array_values($semua);
}

/** Apakah baris ini termasuk yang tampil di halaman publik? */
function kohort_termasuk_tampil(int $id): bool
{
    foreach (kohort_tampil() as $r) {
        if ((int) $r['id'] === $id) {
            return true;
        }
    }
    return false;
}

/**
 * Tahun ajaran yang **TIDAK lagi masuk tabel** (di luar `KOHORT_TAHUN_TAMPIL` terbaru), urut menaik.
 *
 * Permintaan pemilik (v1.14.1): *"Tahun Ajaran yang sudah tidak masuk di dalam tabel letakkan di
 * tanda merah, agar pembaca bisa melihat rekapan pada tahun ajaran yang tidak ditampilkan di tabel.
 * Sehingga dari awal semua datanya ada dan bisa dilihat."*
 * Rekapannya dicetak pada kotak di kepala kartu (`#kohortArsip`), jadi seluruh data tetap terbaca
 * walau tabel hanya memuat 5 tahun terbaru.
 *
 * @return array<int,array<string,mixed>>
 */
function kohort_arsip(): array
{
    $tampil = array_map(static fn (array $r): int => (int) $r['id'], kohort_tampil());
    return array_values(array_filter(kohort_semua(),
        static fn (array $r): bool => !in_array((int) $r['id'], $tampil, true)));
}

/** Satu baris berdasarkan id. */
function kohort_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM kohort WHERE id = ?', [$id]) : null;
}

/** Angka satu sel (selalu bilangan bulat ≥ 0). */
function kohort_angka(array $baris, string $kunci): int
{
    return max(0, (int) ($baris[$kunci] ?? 0));
}

/**
 * Jumlahkan satu kolom pada baris-baris yang ditampilkan.
 *
 * @param array<int,array<string,mixed>> $baris
 */
function kohort_jumlah(array $baris, string $kunci): int
{
    $total = 0;
    foreach ($baris as $r) {
        $total += kohort_angka($r, $kunci);
    }
    return $total;
}

/**
 * Analisis angka dari tabel kohort (dipakai kartu ringkasan + kalimat analisis).
 *
 * @param array<int,array<string,mixed>> $baris
 * @return array<string,mixed>
 */
function kohort_analisis(array $baris): array
{
    $n = count($baris);
    $jml = static fn (string $k): int => kohort_jumlah($baris, $k);
    $pendaftar = $jml('pmb_pendaftar');
    $diterima = $jml('pmb_diterima');
    $siswa = 0;
    foreach ($baris as $r) {
        $siswa += kohort_angka($r, 'x_jumlah') + kohort_angka($r, 'xi_jumlah') + kohort_angka($r, 'xii_jumlah');
    }
    $lulus = $jml('lulus_jumlah');
    $kuliah = $jml('lulus_kuliah');
    $bekerja = $jml('lulus_bekerja');
    $lainnya = $jml('lulus_lainnya');
    $awal = $n > 0 ? (string) $baris[0]['ta'] : '';
    $akhir = $n > 0 ? (string) $baris[$n - 1]['ta'] : '';
    return [
        'jumlah_tahun'   => $n,
        'awal'           => $awal,
        'akhir'          => $akhir,
        'pendaftar'      => $pendaftar,
        'diterima'       => $diterima,
        'persen_diterima' => $pendaftar > 0 ? round($diterima / $pendaftar * 100, 1) : 0.0,
        'siswa'          => $siswa,
        'siswa_rata'     => $n > 0 ? (int) round($siswa / $n) : 0,
        'masuk'          => $jml('x_masuk') + $jml('xi_masuk') + $jml('xii_masuk'),
        'keluar'         => $jml('x_keluar') + $jml('xi_keluar') + $jml('xii_keluar'),
        'tinggal'        => $jml('x_tinggal') + $jml('xi_tinggal') + $jml('xii_tinggal'),
        'naik'           => $jml('x_naik') + $jml('xi_naik') + $jml('xii_naik'),
        'dropout'        => $jml('x_dropout') + $jml('xi_dropout') + $jml('xii_dropout'),
        // Grup LULUS (v1.14.0): jumlah lulus serta tujuan setelah lulus.
        'lulus'          => $lulus,
        'lulus_kuliah'   => $kuliah,
        'lulus_bekerja'  => $bekerja,
        'lulus_lainnya'  => $lainnya,
        'lulus_rata'     => $n > 0 ? (int) round($lulus / $n) : 0,
        'persen_kuliah'  => $lulus > 0 ? round($kuliah / $lulus * 100, 1) : 0.0,
        'persen_bekerja' => $lulus > 0 ? round($bekerja / $lulus * 100, 1) : 0.0,
        'persen_lainnya' => $lulus > 0 ? round($lainnya / $lulus * 100, 1) : 0.0,
        'ada_angka'      => $siswa + $pendaftar + $diterima + $lulus + $kuliah + $bekerja + $lainnya
            + $jml('x_masuk') + $jml('xi_masuk') + $jml('xii_masuk')
            + $jml('x_keluar') + $jml('xi_keluar') + $jml('xii_keluar')
            + $jml('x_tinggal') + $jml('xi_tinggal') + $jml('xii_tinggal')
            + $jml('x_naik') + $jml('xi_naik') + $jml('xii_naik')
            + $jml('x_dropout') + $jml('xi_dropout') + $jml('xii_dropout') > 0,
    ];
}

/**
 * Mencetak halaman publik "Kohort Siswa": judul, tabel analisis kohort, lalu analisis grafiknya.
 */
function kohort_papan(): void
{
    $baris = kohort_tampil();
    $arsip = kohort_arsip();
    $kolom = kohort_kolom();
    $analisis = kohort_analisis($baris);
    $nama = (string) (sekolah()['nama'] ?? '');

    // Tahun ajaran arsip yang dipilih lewat tombol (`?arsip=YYYY/YYYY`). Nilai di luar daftar
    // arsip DIABAIKAN (bukan 403/collapse) sehingga tidak pernah ada tabel kosong.
    $arsipPilih = '';
    $arsipBaris = null;
    $mintaArsip = trim((string) ($_GET['arsip'] ?? ''));
    if ($mintaArsip !== '') {
        foreach ($arsip as $a) {
            if ((string) $a['ta'] === $mintaArsip) {
                $arsipPilih = (string) $a['ta'];
                $arsipBaris = $a;
                break;
            }
        }
    }
    $tautanArsip = static fn (string $ta): string => rute_tautan(rute_kanonik(), ['arsip' => $ta])
        . '#kohortArsipDetail';
    ?>
<div class="kohort-kartu kartu" id="kohortTabel">
  <div class="kohort-kepala">
    <h2 class="kohort-judul">Analisis Kohort Siswa <?= e($nama) ?></h2>
    <div class="kohort-kepala-baris">
      <p class="kohort-ket">
        Rekap kohort siswa <b><?= (int) KOHORT_TAHUN_TAMPIL ?> tahun ajaran terakhir</b>
        (<?= e(kohort_label((string) $analisis['awal'])) ?>–<?= e(kohort_label((string) $analisis['akhir'])) ?>).
        Kolom PMB berisi pendaftar &amp; diterima; kolom tiap tingkat berisi jumlah siswa beserta
        mutasi masuk/keluar, tinggal kelas, naik kelas, dan drop out; kolom <b>Lulus</b> berisi jumlah
        lulusan beserta tujuan setelah lulus (kuliah, bekerja, lainnya).
        <?php if ($arsip): ?>
          Tahun ajaran yang <b>tidak lagi masuk tabel</b> tetap dapat dilihat: klik
          <b>tombol tahun ajaran</b> pada kotak <b>Rekapan Tahun Ajaran Lain</b> di bagian bawah
          halaman ini untuk menampilkan tabel rekapan beserta grafiknya.
        <?php endif; ?>
      </p>
      <?php /* v1.14.3 — kotak "Rekapan Tahun Ajaran Lain" DIPINDAH dari sini ke BAGIAN BAWAH
               (setelah kartu analisis grafik) atas permintaan pemilik, supaya keterangan di bawah
               judul "Analisis Kohort Siswa" tampil rapi selebar kartu. Jangan dikembalikan. */ ?>
    </div>
  </div>

  <?php if ($arsipBaris): ?>
    <?php
    // ================= REKAPAN TAHUN AJARAN ARSIP (muncul setelah tombol diklik) =================
    $asi = static fn (string $k): int => kohort_angka($arsipBaris, $k);
    $aSiswa = $asi('x_jumlah') + $asi('xi_jumlah') + $asi('xii_jumlah');
    $aDo = $asi('x_dropout') + $asi('xi_dropout') + $asi('xii_dropout');
    $aMasuk = $asi('x_masuk') + $asi('xi_masuk') + $asi('xii_masuk');
    $aKeluar = $asi('x_keluar') + $asi('xi_keluar') + $asi('xii_keluar');
    $aLulus = $asi('lulus_jumlah');
    $aTujuan = $asi('lulus_kuliah') + $asi('lulus_bekerja') + $asi('lulus_lainnya');
    ?>
  <section class="kohort-arsip-detail" id="kohortArsipDetail">
    <div class="kohort-arsip-detail-kepala">
      <div>
        <h3><?= ikon('grafik', 17) ?> Rekapan Kohort Tahun Ajaran
          <span class="kohort-arsip-ta"><?= e(kohort_label($arsipPilih)) ?></span></h3>
        <p class="kohort-arsip-detail-ket">Tahun ajaran ini sudah tidak ditampilkan pada tabel utama —
          angkanya tetap tersimpan dan dapat dilihat kapan saja dari tombol di atas.</p>
      </div>
      <a class="tombol tombol-garis tombol-kecil" id="tutupArsipDetail"
         href="<?= e(rute_tautan(rute_kanonik())) ?>#kohortArsip"><?= ikon('tutup', 14) ?> Tutup Rekapan</a>
    </div>

    <div class="kohort-arsip-detail-stat">
      <div class="kohort-ad-stat"><span>Pendaftar</span><strong><?= angka($asi('pmb_pendaftar')) ?></strong></div>
      <div class="kohort-ad-stat"><span>Diterima</span><strong><?= angka($asi('pmb_diterima')) ?></strong></div>
      <div class="kohort-ad-stat"><span>Jumlah Siswa</span><strong><?= angka($aSiswa) ?></strong></div>
      <div class="kohort-ad-stat kohort-ad-lulus"><span>Lulus</span><strong><?= angka($aLulus) ?></strong></div>
      <div class="kohort-ad-stat kohort-ad-merah"><span>Drop Out</span><strong><?= angka($aDo) ?></strong></div>
    </div>

    <div class="kohort-ad-tabel-grid">
      <div class="kohort-ad-blok">
        <h4>PMB &amp; Kelulusan</h4>
        <table class="tabel-ad" id="tblArsipPmb">
          <thead><tr><th scope="col">Indikator</th><th scope="col">Jumlah</th></tr></thead>
          <tbody>
            <tr><th scope="row">Pendaftar</th><td><?= angka($asi('pmb_pendaftar')) ?></td></tr>
            <tr><th scope="row">Diterima</th><td><?= angka($asi('pmb_diterima')) ?></td></tr>
            <tr><th scope="row">Jumlah Lulus</th><td><?= angka($aLulus) ?></td></tr>
            <tr><th scope="row">Kuliah</th><td><?= angka($asi('lulus_kuliah')) ?></td></tr>
            <tr><th scope="row">Bekerja</th><td><?= angka($asi('lulus_bekerja')) ?></td></tr>
            <tr><th scope="row">Lainnya</th><td><?= angka($asi('lulus_lainnya')) ?></td></tr>
          </tbody>
        </table>
      </div>
      <div class="kohort-ad-blok">
        <h4>Per Tingkat Kelas</h4>
        <table class="tabel-ad" id="tblArsipTingkat">
          <thead>
            <tr>
              <th scope="col">Indikator</th>
              <?php foreach (KOHORT_TINGKAT as $labelTingkat): ?>
                <th scope="col"><?= e($labelTingkat) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach (KOHORT_KOLOM_TINGKAT as $kol => $labelKol): ?>
              <tr>
                <th scope="row"><?= e($labelKol) ?></th>
                <?php foreach (['x', 'xi', 'xii'] as $kode): ?>
                  <td><?= angka($asi($kode . '_' . $kol)) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="kohort-ad-grafik-grid">
      <div class="kohort-ad-grafik">
        <h4>Alur Jumlah Siswa</h4>
        <div class="kohort-chart"><canvas id="chArsipAlur" role="img"
          aria-label="Grafik alur jumlah siswa tahun ajaran yang dipilih"></canvas></div>
      </div>
      <div class="kohort-ad-grafik">
        <h4>Mutasi, Naik/Tinggal Kelas &amp; Drop Out</h4>
        <div class="kohort-chart"><canvas id="chArsipHasil" role="img"
          aria-label="Grafik mutasi dan hasil kelas tahun ajaran yang dipilih"></canvas></div>
      </div>
      <div class="kohort-ad-grafik">
        <h4>Tujuan Setelah Lulus</h4>
        <div class="kohort-chart"><canvas id="chArsipLulus" role="img"
          aria-label="Grafik tujuan setelah lulus tahun ajaran yang dipilih"></canvas></div>
        <?php if ($aLulus > 0 && $aTujuan === 0): ?>
          <p class="kohort-ad-catatan">Rincian tujuan lulusan belum diisi pada panel admin.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!$baris): ?>
    <p class="kohort-kosong">Data kohort siswa belum diisi. Angka tabel ini diisi oleh administrator
      pada panel admin (menu <b>Kohort Siswa</b>).</p>
  <?php else: ?>
    <div class="tabel-wrap">
      <table class="tabel tabel-kohort" id="tblKohort">
        <thead>
          <tr>
            <th rowspan="2" class="k-ta">Tahun Ajaran</th>
            <th colspan="2" class="k-pmb">PMB</th>
            <?php foreach (KOHORT_TINGKAT as $kode => $label): ?>
              <th colspan="<?= count(KOHORT_KOLOM_TINGKAT) ?>" class="k-<?= e($kode) ?>"><?= e($label) ?></th>
            <?php endforeach; ?>
            <th colspan="<?= count(KOHORT_KOLOM_LULUS) ?>" class="k-lulus">Lulus</th>
          </tr>
          <tr>
            <th class="k-pendaftar">Pendaftar</th>
            <th class="k-diterima">Diterima</th>
            <?php foreach (KOHORT_TINGKAT as $kode => $label): ?>
              <?php foreach (KOHORT_KOLOM_TINGKAT as $kol => $labelKol): ?>
                <th class="k-<?= e($kol) ?>"><?= e($labelKol) ?></th>
              <?php endforeach; ?>
            <?php endforeach; ?>
            <?php foreach (KOHORT_KOLOM_LULUS as $kol => $labelKol): ?>
              <th class="k-lulus-<?= e($kol) ?>"><?= e($labelKol) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($baris as $r): ?>
            <tr>
              <th scope="row" class="k-ta"><?= e(kohort_label((string) $r['ta'])) ?></th>
              <?php foreach ($kolom as $kol): ?>
                <?php $n = kohort_angka($r, $kol['kunci']); ?>
                <td class="k-<?= e($kol['grup']) ?>-sel k-<?= e($kol['kunci']) ?>-sel<?= $n === 0 ? ' kohort-nol' : '' ?>">
                  <?= angka($n) ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="kohort-kaki">Sumber angka: isian administrator pada panel admin. Tabel memuat
      <?= (int) KOHORT_TAHUN_TAMPIL ?> tahun ajaran terbaru — tahun ajaran baru otomatis menggeser
      tahun paling lama keluar dari tabel<?= $arsip ? ', dan rekapan tahun-tahun lama tetap dapat dilihat pada kotak “Rekapan Tahun Ajaran Lain” di atas' : '' ?>.</p>
  <?php endif; ?>
</div>

<?php if ($baris): ?>
<div class="kohort-analisis" id="kohortAnalisis">
  <div class="kohort-stat">
    <div class="kohort-stat-kotak">
      <span class="kohort-stat-label">Total Pendaftar PMB</span>
      <strong id="kohortStatPendaftar"><?= angka((int) $analisis['pendaftar']) ?></strong>
      <span class="kohort-stat-ket"><?= (int) $analisis['jumlah_tahun'] ?> tahun ajaran</span>
    </div>
    <div class="kohort-stat-kotak">
      <span class="kohort-stat-label">Total Diterima</span>
      <strong id="kohortStatDiterima"><?= angka((int) $analisis['diterima']) ?></strong>
      <span class="kohort-stat-ket"><?= e(number_format((float) $analisis['persen_diterima'], 1, ',', '.')) ?>% dari pendaftar</span>
    </div>
    <div class="kohort-stat-kotak">
      <span class="kohort-stat-label">Rata-rata Siswa / Tahun</span>
      <strong id="kohortStatSiswa"><?= angka((int) $analisis['siswa_rata']) ?></strong>
      <span class="kohort-stat-ket">Kelas X + XI + XII</span>
    </div>
    <div class="kohort-stat-kotak kohort-stat-lulus">
      <span class="kohort-stat-label">Total Lulus</span>
      <strong id="kohortStatLulus"><?= angka((int) $analisis['lulus']) ?></strong>
      <span class="kohort-stat-ket"><?= angka((int) $analisis['lulus_rata']) ?> rata-rata / tahun</span>
    </div>
    <div class="kohort-stat-kotak kohort-stat-merah">
      <span class="kohort-stat-label">Total Drop Out</span>
      <strong id="kohortStatDropout"><?= angka((int) $analisis['dropout']) ?></strong>
      <span class="kohort-stat-ket">seluruh tingkat</span>
    </div>
  </div>

  <div class="kohort-analisis-kartu kartu">
    <h3><?= ikon('grafik', 18) ?> Analisis Grafik Kohort Siswa</h3>
    <p class="kohort-ket">
      Grafik di bawah dihitung dari angka tabel di atas (<?= (int) KOHORT_TAHUN_TAMPIL ?> tahun terakhir).
    </p>

    <div class="kohort-grafik-grid">
      <div class="kohort-grafik-kotak">
        <h4>PMB: Pendaftar &amp; Diterima</h4>
        <div class="kohort-chart"><canvas id="chKohortPmb" role="img"
          aria-label="Grafik jumlah pendaftar dan diterima tiap tahun ajaran"></canvas></div>
      </div>
      <div class="kohort-grafik-kotak">
        <h4>Jumlah Siswa per Tingkat</h4>
        <div class="kohort-chart"><canvas id="chKohortJumlah" role="img"
          aria-label="Grafik jumlah siswa kelas X, XI, dan XII tiap tahun ajaran"></canvas></div>
      </div>
      <div class="kohort-grafik-kotak">
        <h4>Naik Kelas, Tinggal Kelas &amp; Drop Out</h4>
        <div class="kohort-chart"><canvas id="chKohortHasil" role="img"
          aria-label="Grafik jumlah siswa naik kelas, tinggal kelas, dan drop out tiap tahun ajaran"></canvas></div>
      </div>
      <div class="kohort-grafik-kotak">
        <h4>Mutasi Siswa</h4>
        <div class="kohort-chart"><canvas id="chKohortMutasi" role="img"
          aria-label="Grafik jumlah mutasi masuk dan mutasi keluar tiap tahun ajaran"></canvas></div>
      </div>
      <div class="kohort-grafik-kotak kohort-grafik-lebar">
        <h4>Kelulusan: Jumlah Lulus &amp; Tujuan Setelah Lulus</h4>
        <div class="kohort-chart"><canvas id="chKohortLulus" role="img"
          aria-label="Grafik jumlah lulusan serta jumlah lulusan yang kuliah, bekerja, dan lainnya tiap tahun ajaran"></canvas></div>
      </div>
    </div>

    <div class="kohort-catatan">
      <p id="kohortAnalisisTeks">
        <?php if (!$analisis['ada_angka']): ?>
          Angka kohort untuk tahun-tahun ini belum diisi, sehingga grafik masih bernilai nol.
        <?php else: ?>
          Dalam <?= (int) $analisis['jumlah_tahun'] ?> tahun ajaran terakhir
          (<?= e(kohort_label((string) $analisis['awal'])) ?>–<?= e(kohort_label((string) $analisis['akhir'])) ?>)
          PMB menerima <b><?= angka((int) $analisis['diterima']) ?></b> dari
          <b><?= angka((int) $analisis['pendaftar']) ?></b> pendaftar
          (<?= e(number_format((float) $analisis['persen_diterima'], 1, ',', '.')) ?>%).
          Jumlah siswa rata-rata <b><?= angka((int) $analisis['siswa_rata']) ?></b> orang per tahun ajaran.
          Selama periode itu tercatat mutasi masuk <b><?= angka((int) $analisis['masuk']) ?></b>,
          mutasi keluar <b><?= angka((int) $analisis['keluar']) ?></b>,
          naik kelas <b><?= angka((int) $analisis['naik']) ?></b>,
          tinggal kelas <b><?= angka((int) $analisis['tinggal']) ?></b>, dan
          drop out <b><?= angka((int) $analisis['dropout']) ?></b>.
          Dari sisi kelulusan tercatat <b><?= angka((int) $analisis['lulus']) ?></b> siswa lulus
          (rata-rata <b><?= angka((int) $analisis['lulus_rata']) ?></b> per tahun ajaran):
          <b><?= angka((int) $analisis['lulus_kuliah']) ?></b> melanjutkan kuliah
          (<?= e(number_format((float) $analisis['persen_kuliah'], 1, ',', '.')) ?>%),
          <b><?= angka((int) $analisis['lulus_bekerja']) ?></b> bekerja
          (<?= e(number_format((float) $analisis['persen_bekerja'], 1, ',', '.')) ?>%), dan
          <b><?= angka((int) $analisis['lulus_lainnya']) ?></b> lainnya
          (<?= e(number_format((float) $analisis['persen_lainnya'], 1, ',', '.')) ?>%).
        <?php endif; ?>
      </p>
    </div>
  </div>
</div>

<?php /* =====================================================================================
         KOTAK "REKAPAN TAHUN AJARAN LAIN" — v1.14.3: DIPINDAH KE BAGIAN BAWAH (area yang
         ditandai pemilik) supaya keterangan di bawah judul "Analisis Kohort Siswa" rapi selebar
         kartu. Isinya HANYA tombol/chip tahun ajaran; tabel rekapan + grafiknya baru muncul
         setelah tombolnya diklik (kartu #kohortArsipDetail dirender server lewat `?arsip=`).
         ===================================================================================== */ ?>
<?php if ($arsip): ?>
<aside class="kohort-arsip" id="kohortArsip">
  <h3><?= ikon('kalender', 15) ?> Rekapan Tahun Ajaran Lain</h3>
  <p class="kohort-arsip-ket">Tahun ajaran berikut sudah tidak ditampilkan pada tabel —
    klik tahunnya untuk melihat tabel rekapan &amp; grafiknya.</p>
  <div class="kohort-arsip-tombol-grup" role="group" aria-label="Pilih tahun ajaran rekapan">
    <?php foreach ($arsip as $a): ?>
      <?php $on = $arsipPilih !== '' && $arsipPilih === (string) $a['ta']; ?>
      <a class="kohort-arsip-tombol<?= $on ? ' on' : '' ?>" id="arsipTa<?= (int) $a['id'] ?>"
         href="<?= e($tautanArsip((string) $a['ta'])) ?>"
         <?= $on ? 'aria-current="true"' : '' ?>>
        <?= ikon('kalender', 14) ?><span><?= e(kohort_label((string) $a['ta'])) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="kohort-arsip-jumlah"><?= angka(count($arsip)) ?> tahun ajaran tersimpan di luar tabel.</p>
</aside>
<?php endif; ?>

<?php
    $label = array_map(static fn (array $r): string => (string) $r['ta'], $baris);
    $data = [
        'pmb' => [
            'pendaftar' => array_map(static fn (array $r): int => kohort_angka($r, 'pmb_pendaftar'), $baris),
            'diterima'  => array_map(static fn (array $r): int => kohort_angka($r, 'pmb_diterima'), $baris),
        ],
        'jumlah' => [
            'x'   => array_map(static fn (array $r): int => kohort_angka($r, 'x_jumlah'), $baris),
            'xi'  => array_map(static fn (array $r): int => kohort_angka($r, 'xi_jumlah'), $baris),
            'xii' => array_map(static fn (array $r): int => kohort_angka($r, 'xii_jumlah'), $baris),
        ],
        'hasil' => [
            'naik'    => array_map(static fn (array $r): int => kohort_angka($r, 'x_naik') + kohort_angka($r, 'xi_naik') + kohort_angka($r, 'xii_naik'), $baris),
            'tinggal' => array_map(static fn (array $r): int => kohort_angka($r, 'x_tinggal') + kohort_angka($r, 'xi_tinggal') + kohort_angka($r, 'xii_tinggal'), $baris),
            'dropout' => array_map(static fn (array $r): int => kohort_angka($r, 'x_dropout') + kohort_angka($r, 'xi_dropout') + kohort_angka($r, 'xii_dropout'), $baris),
        ],
        'mutasi' => [
            'masuk'  => array_map(static fn (array $r): int => kohort_angka($r, 'x_masuk') + kohort_angka($r, 'xi_masuk') + kohort_angka($r, 'xii_masuk'), $baris),
            'keluar' => array_map(static fn (array $r): int => kohort_angka($r, 'x_keluar') + kohort_angka($r, 'xi_keluar') + kohort_angka($r, 'xii_keluar'), $baris),
        ],
        // Grup LULUS (v1.14.0) — angka langsung dari kolom tabel `kohort`.
        'lulus' => [
            'jumlah'  => array_map(static fn (array $r): int => kohort_angka($r, 'lulus_jumlah'), $baris),
            'kuliah'  => array_map(static fn (array $r): int => kohort_angka($r, 'lulus_kuliah'), $baris),
            'bekerja' => array_map(static fn (array $r): int => kohort_angka($r, 'lulus_bekerja'), $baris),
            'lainnya' => array_map(static fn (array $r): int => kohort_angka($r, 'lulus_lainnya'), $baris),
        ],
    ];
    // Data grafik REKAPAN TAHUN AJARAN ARSIP (hanya bila tombolnya diklik) — satu tahun ajaran.
    $dataArsip = null;
    if ($arsipBaris) {
        $ak = static fn (string $k): int => kohort_angka($arsipBaris, $k);
        $dataArsip = [
            'ta' => (string) $arsipBaris['ta'],
            'alur' => [
                'label' => ['Pendaftar', 'Diterima', 'Kelas X', 'Kelas XI', 'Kelas XII', 'Jumlah Lulus'],
                'data'  => [$ak('pmb_pendaftar'), $ak('pmb_diterima'),
                    $ak('x_jumlah'), $ak('xi_jumlah'), $ak('xii_jumlah'), $ak('lulus_jumlah')],
            ],
            'hasil' => [
                'label' => ['Mutasi Masuk', 'Mutasi Keluar', 'Tinggal Kelas', 'Naik Kelas', 'Drop Out'],
                'data'  => [$ak('x_masuk') + $ak('xi_masuk') + $ak('xii_masuk'),
                    $ak('x_keluar') + $ak('xi_keluar') + $ak('xii_keluar'),
                    $ak('x_tinggal') + $ak('xi_tinggal') + $ak('xii_tinggal'),
                    $ak('x_naik') + $ak('xi_naik') + $ak('xii_naik'),
                    $ak('x_dropout') + $ak('xi_dropout') + $ak('xii_dropout')],
            ],
            'tujuan' => [
                'label' => ['Kuliah', 'Bekerja', 'Lainnya'],
                'data'  => [$ak('lulus_kuliah'), $ak('lulus_bekerja'), $ak('lulus_lainnya')],
            ],
        ];
    }
    ?>
<script src="assets/vendor/chart.umd.min.js?v=<?= e(APP_VERSION) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') { return; }
  var label = <?= json_encode($label, JSON_UNESCAPED_UNICODE) ?>;
  var D = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
  var hijau = '#0b4a33', hijau2 = '#15795a', hijau3 = '#4aa382', emas = '#c9a227', emas2 = '#a9741b';
  var maroon = '#6d1b25', merah = '#b3261e', oranye = '#d97706';
  var dasar = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
      tooltip: { callbacks: { label: function (c) {
        return c.dataset.label + ': ' + (Number(c.raw) || 0).toLocaleString('id-ID');
      } } }
    },
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0 } },
      x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0 } }
    }
  };
  function buat(id, sets) {
    var el = document.getElementById(id);
    if (!el) { return; }
    new Chart(el, {
      type: 'bar',
      data: { labels: label, datasets: sets },
      options: dasar
    });
  }
  buat('chKohortPmb', [
    { label: 'Pendaftar', data: D.pmb.pendaftar, backgroundColor: hijau2, borderRadius: 4, maxBarThickness: 26 },
    { label: 'Diterima', data: D.pmb.diterima, backgroundColor: emas, borderRadius: 4, maxBarThickness: 26 }
  ]);
  buat('chKohortJumlah', [
    { label: 'Kelas X', data: D.jumlah.x, backgroundColor: hijau, borderRadius: 4, maxBarThickness: 26 },
    { label: 'Kelas XI', data: D.jumlah.xi, backgroundColor: hijau3, borderRadius: 4, maxBarThickness: 26 },
    { label: 'Kelas XII', data: D.jumlah.xii, backgroundColor: emas, borderRadius: 4, maxBarThickness: 26 }
  ]);
  buat('chKohortHasil', [
    { label: 'Naik Kelas', data: D.hasil.naik, backgroundColor: hijau2, borderRadius: 4, maxBarThickness: 26 },
    { label: 'Tinggal Kelas', data: D.hasil.tinggal, backgroundColor: oranye, borderRadius: 4, maxBarThickness: 26 },
    { label: 'Drop Out', data: D.hasil.dropout, backgroundColor: merah, borderRadius: 4, maxBarThickness: 26 }
  ]);
  buat('chKohortMutasi', [
    { label: 'Mutasi Masuk', data: D.mutasi.masuk, backgroundColor: hijau3, borderRadius: 4, maxBarThickness: 26 },
    { label: 'Mutasi Keluar', data: D.mutasi.keluar, backgroundColor: maroon, borderRadius: 4, maxBarThickness: 26 }
  ]);
  /* Kelulusan (v1.14.0): jumlah lulus + tujuan setelah lulus — satu grafik tersendiri karena
     kolomnya bertambah (grup "Lulus" pada tabel). */
  buat('chKohortLulus', [
    { label: 'Jumlah Lulus', data: D.lulus.jumlah, backgroundColor: hijau, borderRadius: 4, maxBarThickness: 24 },
    { label: 'Kuliah', data: D.lulus.kuliah, backgroundColor: '#b06ad4', borderRadius: 4, maxBarThickness: 24 },
    { label: 'Bekerja', data: D.lulus.bekerja, backgroundColor: oranye, borderRadius: 4, maxBarThickness: 24 },
    { label: 'Lainnya', data: D.lulus.lainnya, backgroundColor: emas, borderRadius: 4, maxBarThickness: 24 }
  ]);

  /* ================= GRAFIK REKAPAN TAHUN AJARAN ARSIP (v1.14.2) =================
     Hanya dibuat bila tombol tahun ajaran pada kotak "Rekapan Tahun Ajaran Lain" diklik
     (server merender kartu #kohortArsipDetail + data `A` di bawah). */
  var A = <?= json_encode($dataArsip, JSON_UNESCAPED_UNICODE) ?>;
  function batangTunggal(id, label, dataArr, warnaArr) {
    var el = document.getElementById(id);
    if (!el) { return; }
    new Chart(el, {
      type: 'bar',
      data: { labels: label, datasets: [{ label: 'Jumlah', data: dataArr, backgroundColor: warnaArr,
        borderRadius: 5, maxBarThickness: 40 }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function (c) {
            return c.label + ': ' + (Number(c.raw) || 0).toLocaleString('id-ID');
          } } }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } },
          x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 0 } }
        }
      }
    });
  }
  if (A && A.alur) {
    batangTunggal('chArsipAlur', A.alur.label, A.alur.data,
      [hijau2, emas, hijau, hijau3, emas2, '#2b8db8']);
    batangTunggal('chArsipHasil', A.hasil.label, A.hasil.data,
      [hijau3, maroon, oranye, hijau2, merah]);
    var elL = document.getElementById('chArsipLulus');
    if (elL) {
      new Chart(elL, {
        type: 'doughnut',
        data: {
          labels: A.tujuan.label,
          datasets: [{ data: A.tujuan.data, backgroundColor: ['#b06ad4', oranye, emas], borderWidth: 1 }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
            tooltip: { callbacks: { label: function (c) {
              return c.label + ': ' + (Number(c.raw) || 0).toLocaleString('id-ID');
            } } }
          }
        }
      });
    }
  }
});
</script>
<?php endif; ?>
    <?php
}
