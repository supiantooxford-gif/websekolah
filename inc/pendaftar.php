<?php
/**
 * HALAMAN "PENDAFTAR" (menu Siswa → Pendaftar) — daftar pendaftar PPDB dengan FILTER PER TAHUN.
 *
 * Permintaan pemilik (pembaruan):
 *   "Pendaftar yang berdampingan dengan data siswa isinya pindahkan ke menu pendaftar seperti yang
 *    ada pada gambar yang saya tandai warna merah. Dan tambahkan tahun 2027 sesuai saran."
 * Jadi isi yang dahulu menjadi TAB "Pendaftar" di halaman **Data Siswa** kini berdiri sendiri
 * sebagai halaman **menu Pendaftar** (sub menu "Siswa"), dan daftar tahunnya **selalu memuat tahun
 * PPDB Online yang sedang dibuka (mis. 2027)** walau belum ada pendaftar.
 *
 * Sumber data (lihat `inc/ppdb_tarik.php`):
 *   • 2022–2026 → hasil TARIK dari panel PPDB lama (tabel `ppdb_lama`; ditarik lewat halaman admin);
 *   • 2027 ke atas → PPDB Online website ini (tabel `ppdb_pendaftar`, tanpa penarikan).
 * Kolom yang ditampilkan sesuai permintaan pemilik: **Nomor Pendaftaran, Nama Siswa, Asal Sekolah**
 * (+ No dan Tahun sebagai penunjuk), dipaginasi `PUBLIK_PER_HALAMAN` baris per halaman.
 */
declare(strict_types=1);

require_once __DIR__ . '/ppdb_tarik.php';
require_once __DIR__ . '/grafik_tahun.php';   // komponen bersama kartu grafik jumlah per tahun

/**
 * Daftar tahun untuk pemilih tahun: tahun yang PUNYA DATA + tahun gelombang PPDB Online yang sudah
 * dibuka (agar **tahun berjalan**, mis. 2027, tetap terlihat walau belum ada pendaftar).
 */
function ppdb_tahun_pilihan(): array
{
    $tahun = ppdb_tahun_ada();
    foreach (all('SELECT DISTINCT tahun_ajaran FROM ppdb_gelombang') as $r) {
        $th = (int) substr(trim((string) ($r['tahun_ajaran'] ?? '')), 0, 4);
        if ($th > 1900 && $th < 2200) {
            $tahun[] = $th;
        }
    }
    // Tahun berjalan (bila pendaftaran tahun ini sudah pernah dibuka/diisi sebelumnya).
    $tahun = array_values(array_unique($tahun));
    rsort($tahun);
    return $tahun;
}

/** Tahun PPDB Online yang sedang aktif (dari gelombang yang aktif) — untuk keterangan halaman. */
function ppdb_tahun_aktif(): int
{
    $th = (int) substr(trim((string) nilai('SELECT tahun_ajaran FROM ppdb_gelombang WHERE aktif = 1
                                          ORDER BY urutan, id LIMIT 1', [])), 0, 4);
    return $th > 1900 ? $th : 0;
}

/**
 * Jumlah pendaftar PER TAHUN — hanya tahun yang SUDAH ADA pendaftarnya (permintaan pemilik:
 * "yang ditampilkan adalah tahun yang sudah ada pendaftarnya"), urut tahun menaik.
 *
 * @return array<int,int> tahun => jumlah pendaftar
 */
function ppdb_pendaftar_per_tahun(): array
{
    $keluar = [];
    foreach (all('SELECT tahun, COUNT(*) AS n FROM (' . ppdb_sql_gabungan() . ')
                  WHERE tahun > 1900 GROUP BY tahun ORDER BY tahun') as $r) {
        $n = (int) $r['n'];
        if ($n > 0) {
            $keluar[(int) $r['tahun']] = $n;
        }
    }
    return $keluar;
}

/**
 * Mencetak halaman "Pendaftar" (menu Siswa → Pendaftar).
 */
function pendaftar_papan(): void
{
    $tahunAda = ppdb_tahun_pilihan();
    $fTahun = (int) ($_GET['tahun'] ?? 0);
    if ($fTahun > 0 && !in_array($fTahun, $tahunAda, true)) {
        $fTahun = 0;   // tahun asing diabaikan (bukan 403)
    }
    $cari = trim((string) ($_GET['cari'] ?? ''));
    $total = ppdb_pendaftar_gabungan_jumlah($fTahun, $cari);
    $perHal = (int) PUBLIK_PER_HALAMAN;
    $hal = paginasi_halaman($total, $perHal);
    $baris = ppdb_pendaftar_gabungan($fTahun, $cari, $perHal, ($hal - 1) * $perHal);
    $tahunAktif = ppdb_tahun_aktif();
    $tahunPanel = ppdb_tahun_panel();
    // Grafik pendaftaran per tahun (hanya tahun yang sudah ada pendaftarnya) — komponen bersama
    // dengan halaman Data Alumni (lihat inc/grafik_tahun.php).
    $perTahun = ppdb_pendaftar_per_tahun();
    $labelTahun = array_map('strval', array_keys($perTahun));
    if ($perTahun) {
        grafik_tahun_kartu([
            'id' => 'pendaftarGrafik',
            'kanvas' => 'chPendaftar',
            'daftar' => 'pendaftarGrafikDaftar',
            'judul' => 'Grafik Pendaftaran per Tahun',
            'keterangan' => 'Jumlah pendaftar pada setiap tahun yang sudah memiliki pendaftar — tahun tanpa pendaftar tidak ditampilkan.',
            'data' => $perTahun,
            'satuan' => 'pendaftar',
            'kaki' => 'Tahun ' . (int) reset($labelTahun) . '–' . (int) end($labelTahun)
                . ' · rata-rata ' . angka((int) round(array_sum($perTahun) / max(1, count($perTahun))))
                . ' pendaftar per tahun · sumber: panel PPDB lama & PPDB Online website ini.',
        ]);
    }
    ?>
<section class="pf-blok">
  <div class="filter-publik">
    <form method="get" action="<?= e(rute_kanonik()) ?>" class="filter-baris" id="formSaringPendaftar">
      <?php if ($cari !== ''): ?><input type="hidden" name="cari" value="<?= e($cari) ?>"><?php endif; ?>
      <label for="tahunDaftar">Pilih Tahun</label>
      <select id="tahunDaftar" name="tahun" data-auto>
        <option value="0">Semua tahun</option>
        <?php foreach ($tahunAda as $th): ?>
          <option value="<?= (int) $th ?>" <?= $fTahun === (int) $th ? 'selected' : '' ?>>
            <?= (int) $th ?> (<?= angka(ppdb_pendaftar_gabungan_jumlah((int) $th)) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <span class="filter-jumlah"><?= angka($total) ?> pendaftar ditemukan<?= $fTahun > 0 ? ' pada tahun ' . (int) $fTahun : '' ?></span>
      <?php if ($fTahun > 0 || $cari !== ''): ?>
        <a class="tombol tombol-garis tombol-kecil" href="<?= e(rute_tautan(rute_kanonik(), [])) ?>">Reset saringan</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$baris): ?>
    <div class="kartu tengah" id="pendaftarKosong">
      <?= ikon('clipboard', 34) ?>
      <p class="mut mb0">Belum ada data pendaftar<?= $fTahun > 0 ? ' pada tahun ' . (int) $fTahun : '' ?>.</p>
      <p class="mut kecil mb0">
        Data tahun <?= (int) (reset($tahunPanel)) ?>–<?= (int) PPDB_PANEL_TAHUN_AKHIR ?> ditambahkan pengelola
        dari panel PPDB lama; mulai <?= (int) ppdb_tahun_website_mulai() ?> terisi otomatis dari
        PPDB Online website ini<?= $tahunAktif > 0 ? ' (pendaftaran ' . (int) $tahunAktif . ' sudah dibuka)' : '' ?>.
      </p>
    </div>
  <?php else: ?>
    <div class="tabel-wrap">
      <table class="tabel tabel-publik" id="tabelPendaftar">
        <thead>
          <tr>
            <th style="width:64px">No</th>
            <th style="width:200px">Nomor Pendaftaran</th>
            <th>Nama Siswa</th>
            <th style="width:34%">Asal Sekolah</th>
            <th style="width:110px">Tahun</th>
          </tr>
        </thead>
        <tbody>
          <?php $no = ($hal - 1) * $perHal; ?>
          <?php foreach ($baris as $r): $no++; ?>
            <tr>
              <td class="kecil"><?= $no ?></td>
              <td><b><?= e((string) $r['nomor']) ?></b></td>
              <td><?= e((string) $r['nama']) ?></td>
              <td class="kecil"><?= e((string) ($r['asal_sekolah'] !== '' ? $r['asal_sekolah'] : '-')) ?></td>
              <td class="kecil"><?= (int) $r['tahun'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= paginasi($total, $perHal, $hal) ?>
    <p class="kecil mut mt8" id="infoPendaftar"><?= e(paginasi_info($total, $perHal, $hal, 'pendaftar')) ?>
      &middot; tahun <?= $fTahun > 0 ? (int) $fTahun : (int) min($tahunAda) . '–' . (int) max($tahunAda) ?>.
      Tahun <?= (int) (reset($tahunPanel)) ?>–<?= (int) PPDB_PANEL_TAHUN_AKHIR ?> berasal dari panel PPDB lama,
      mulai <?= (int) ppdb_tahun_website_mulai() ?> dari PPDB Online website ini<?= $tahunAktif > 0 ? ' (pendaftaran ' . (int) $tahunAktif . ' sudah dibuka)' : '' ?>.</p>
  <?php endif; ?>
</section>
    <?php
}
