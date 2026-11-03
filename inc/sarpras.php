<?php
/**
 * INFORMASI SARPRAS — Daftar Inventaris Barang (Sarana & Prasarana) pada website sekolah.
 *
 * Data diambil LANGSUNG (baca-saja) dari basis data aplikasi e-GURU yang melayani menu Tugas
 * Tambahan "Sarana & Prasarana" (`tugas.php?kode=sarpras`) — aplikasi yang sama dengan yang
 * dipakai pengelola di https://asisten.guru. Karena itu setiap penambahan/perubahan barang yang
 * dilakukan di e-GURU OTOMATIS ikut tampil di halaman ini tanpa perlu disalin ulang.
 *
 * PENTING / JANGAN DIUBAH tanpa alasan kuat:
 *  1. Modul ini TIDAK memuat (require) kode aplikasi e-GURU — memuat `e-guru/inc/init.php` akan
 *     menabrakkan konstanta & fungsi aplikasi ini dan menjalankan sesi aplikasi lain. Seluruh
 *     pembacaan ditulis ulang di sini memakai koneksi TERPISAH yang hanya boleh membaca
 *     (`PRAGMA query_only = 1`), jadi data e-GURU tidak mungkin berubah dari halaman ini.
 *  2. Basis data e-GURU dibuka hanya bila berkasnya ada; bila tidak (aplikasi dipindah/belum
 *     dipasang/berkasnya tidak terbaca) halaman menampilkan keterangan ramah — BUKAN Fatal error.
 *  3. Hanya tabel `sarpras_barang` + nama sekolah yang dibaca. **JANGAN** membaca kolom lain
 *     (mis. tabel `users`) — basis data e-GURU memuat data seluruh sekolah/madrasah pemakai.
 *  4. Tampilan mengikuti halaman e-GURU (menu Sarana & Prasarana → Laporan → "Daftar Barang"):
 *     kolom No, Nama Barang, Spesifikasi, Lokasi, Kondisi, Jumlah, Sumber Dana, Jenis, Keterangan.
 */
declare(strict_types=1);

// =====================================================================================
// KONEKSI BACA-SAJA ke basis data e-GURU
// =====================================================================================

/** Berkas basis data e-GURU yang dipakai (urutan: env → pengaturan → konstanta → bawaan). */
function sarpras_db_file(): string
{
    static $berkas = null;
    if ($berkas !== null) {
        return $berkas;
    }
    $kandidat = [];
    // (1) dapat diarahkan lewat variabel lingkungan (dipakai skrip uji pada salinan /tmp).
    $env = trim((string) getenv('WEBSK_EGURU_DB'));
    if ($env !== '') {
        $kandidat[] = $env;
    }
    // (2) dapat diarahkan lewat tabel `pengaturan` (kunci `sarpras_db`) tanpa menyunting kode.
    if (function_exists('pengaturan')) {
        $kustom = trim(pengaturan('sarpras_db', ''));
        if ($kustom !== '') {
            $kandidat[] = $kustom;
        }
    }
    // (3) konstanta di inc/config.php.
    if (defined('EGURU_DB_FILE')) {
        $kandidat[] = (string) EGURU_DB_FILE;
    }
    // (4) bawaan: folder aplikasi e-GURU di sebelah folder aplikasi ini.
    $kandidat[] = dirname(__DIR__) . '/e-guru/data/eguru.sqlite';

    foreach ($kandidat as $k) {
        if ($k !== '' && is_file($k) && is_readable($k)) {
            return $berkas = $k;
        }
    }
    return $berkas = '';
}

/** Koneksi BACA-SAJA ke basis data e-GURU (null bila tidak tersedia). */
function sarpras_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo instanceof PDO ? $pdo : null;
    }
    $pdo = null;
    $berkas = sarpras_db_file();
    if ($berkas === '') {
        return null;
    }
    $opsi = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    // Cara 1: mode=ro (paling aman). Cara 2: koneksi biasa + PRAGMA query_only (dipakai bila basis
    // data ber-jurnal WAL dan berkas -shm belum ada sehingga mode=ro tidak dapat membukanya).
    // Keduanya TIDAK PERNAH menulis data e-GURU.
    foreach (['sqlite:file:' . $berkas . '?mode=ro', 'sqlite:' . $berkas] as $dsn) {
        try {
            $coba = new PDO($dsn, null, null, $opsi);
            $coba->exec('PRAGMA query_only = 1');
            $coba->exec('PRAGMA busy_timeout = 4000');
            // Uji ringan: tabel inti harus ada.
            $coba->query('SELECT COUNT(*) FROM sarpras_barang')->fetchColumn();
            $pdo = $coba;
            return $pdo;
        } catch (Throwable $e) {
            // coba cara berikutnya
        }
    }
    return null;
}

/** Apakah sumber data e-GURU tersedia? */
function sarpras_siap(): bool
{
    return sarpras_db() instanceof PDO;
}

/** Seluruh baris hasil query (array kosong bila sumber data tidak tersedia / query gagal). */
function sarpras_all(string $sql, array $param = []): array
{
    $pdo = sarpras_db();
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
function sarpras_one(string $sql, array $param = []): ?array
{
    $pdo = sarpras_db();
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

// =====================================================================================
// SEKOLAH/MADRASAH PEMILIK DATA
// =====================================================================================

/** Nama untuk pencocokan (huruf kecil, hanya huruf/angka). */
function sarpras_nama_kunci(string $teks): string
{
    $t = strtolower(trim($teks));
    $t = preg_replace('/[^a-z0-9]+/', '', $t) ?? '';
    return $t;
}

/**
 * Sekolah/madrasah pada basis data e-GURU yang datanya ditampilkan.
 *
 * Dicocokkan dengan NAMA sekolah website ini (jadi pemilik tidak perlu menyetel apa pun), lalu
 * dicadangkan ke konstanta `SARPRAS_SEKOLAH_ID`, dan terakhir ke sekolah mana pun yang punya data
 * barang. Hasilnya di-cache per permintaan.
 */
function sarpras_sekolah(): ?array
{
    static $s = null;
    if ($s !== null) {
        return $s === false ? null : $s;
    }
    $s = false;
    if (!sarpras_siap()) {
        return null;
    }

    // (1) cocokkan nama sekolah website dengan daftar sekolah di e-GURU.
    $nama = function_exists('sekolah') ? trim((string) (sekolah()['nama'] ?? '')) : '';
    if ($nama !== '') {
        $kunci = sarpras_nama_kunci($nama);
        foreach (sarpras_all('SELECT id, nama, jenis FROM sekolah ORDER BY id') as $r) {
            if ($kunci !== '' && sarpras_nama_kunci((string) $r['nama']) === $kunci) {
                return $s = $r;
            }
        }
    }

    // (2) konstanta di inc/config.php.
    if (defined('SARPRAS_SEKOLAH_ID')) {
        $r = sarpras_one('SELECT id, nama, jenis FROM sekolah WHERE id = ?', [(int) SARPRAS_SEKOLAH_ID]);
        if ($r) {
            return $s = $r;
        }
    }

    // (3) sekolah yang benar-benar punya data barang (paling banyak barisnya).
    $r = sarpras_one('SELECT k.id, k.nama, k.jenis FROM sekolah k
                      JOIN (SELECT sekolah_id, COUNT(*) AS n FROM sarpras_barang GROUP BY sekolah_id
                            ORDER BY n DESC LIMIT 1) t ON t.sekolah_id = k.id');
    if ($r) {
        return $s = $r;
    }
    return null;
}

/** Daftar barang milik sekolah tersebut (urut seperti di e-GURU: urutan lalu id). */
function sarpras_barang(): array
{
    static $baris = null;
    if ($baris !== null) {
        return $baris;
    }
    $sek = sarpras_sekolah();
    if (!$sek) {
        return $baris = [];
    }
    return $baris = sarpras_all('SELECT * FROM sarpras_barang WHERE sekolah_id = ? ORDER BY urutan, id',
        [(int) $sek['id']]);
}

/** Label kondisi yang menandai barang rusak ringan/berat (mengikuti konstanta e-GURU). */
function sarpras_kondisi_rusak(string $kondisi): bool
{
    $k = strtolower(trim($kondisi));
    return in_array($k, ['rusak ringan', 'rusak berat'], true);
}

/** Kelas pil kondisi (warna mengikuti e-GURU: Baik hijau, Rusak Ringan kuning, Rusak Berat merah). */
function sarpras_kondisi_kelas(string $kondisi): string
{
    $k = strtolower(trim($kondisi));
    if ($k === 'rusak berat') {
        return 'sar-pil sar-pil-err';
    }
    if ($k === 'rusak ringan') {
        return 'sar-pil sar-pil-warn';
    }
    if ($k === 'baik') {
        return 'sar-pil sar-pil-ok';
    }
    return 'sar-pil sar-pil-gray';
}

/** Nilai sel: kosong/'-' ditampilkan sebagai '-' (persis seperti e-GURU). */
function sarpras_sel($v): string
{
    $t = trim((string) $v);
    return $t !== '' ? $t : '-';
}

/** Ringkasan angka daftar barang: jenis (baris), unit, per kondisi. */
function sarpras_ringkas(array $baris): array
{
    $unit = 0;
    $kondisi = [];
    foreach ($baris as $b) {
        $unit += (int) $b['jumlah'];
        $k = trim((string) $b['kondisi']);
        $k = $k !== '' ? $k : 'Tanpa keterangan';
        $kondisi[$k] = ($kondisi[$k] ?? 0) + 1;
    }
    return ['jenis' => count($baris), 'unit' => $unit, 'kondisi' => $kondisi];
}

/** Rekap jumlah barang per kolom tertentu (jenis/lokasi/sumber_dana) — seperti rekap di e-GURU. */
function sarpras_rekap(array $baris, string $kolom): array
{
    $izin = ['jenis', 'lokasi', 'sumber_dana', 'kondisi'];
    if (!in_array($kolom, $izin, true)) {
        return [];
    }
    $keluar = [];
    foreach ($baris as $b) {
        $n = trim((string) ($b[$kolom] ?? ''));
        $n = $n !== '' ? $n : '(tidak diisi)';
        if (!isset($keluar[$n])) {
            $keluar[$n] = ['barang' => 0, 'unit' => 0];
        }
        $keluar[$n]['barang']++;
        $keluar[$n]['unit'] += (int) $b['jumlah'];
    }
    uasort($keluar, static fn (array $a, array $b): int => $b['unit'] <=> $a['unit']);
    return $keluar;
}

// =====================================================================================
// TAMPILAN: DAFTAR INVENTARIS BARANG
// =====================================================================================

/**
 * Mencetak blok "Informasi Sarpras" (Daftar Inventaris Barang) — isi menu Informasi → Sarpras.
 */
function sarpras_papan(): void
{
    if (!sarpras_siap()) {
        echo '<section class="sar-blok">'
            . '<div class="sar-kosong">' . ikon('papan', 34)
            . '<h3>Data Sarana &amp; Prasarana belum dapat ditampilkan</h3>'
            . '<p>Halaman ini menampilkan Daftar Inventaris Barang dari aplikasi <b>e-GURU</b> menu
                 Tugas Tambahan &ldquo;Sarana &amp; Prasarana&rdquo;. Sumber datanya belum dapat dibaca
                 saat ini (aplikasi e-GURU belum dipasang di server, atau berkas datanya tidak dapat
                 diakses).</p>';
        if (defined('EGURU_URL') && EGURU_URL !== '') {
            echo '<p><a class="tombol tombol-garis tombol-kecil" href="' . e((string) EGURU_URL) . '"
                 target="_blank" rel="noopener noreferrer">' . ikon('globe', 15) . ' Buka aplikasi e-GURU</a></p>';
        }
        echo '</div></section>';
        return;
    }

    $sek = sarpras_sekolah();
    $baris = sarpras_barang();
    $ring = sarpras_ringkas($baris);
    // HALAMAN DAFTAR (permintaan pemilik: "Data inventaris barang ditampilkan 1-10 dan ditambahkan
    // tombol selanjutnya"). Ringkasan/kaki tabel tetap dihitung dari SELURUH baris.
    $perHal = PUBLIK_PER_HALAMAN;
    $total = count($baris);
    $hal = paginasi_halaman($total, $perHal);
    $tampil = paginasi_potong($baris, $hal, $perHal);
    ?>
<div class="sar-papan" id="papanSarpras">

  <?php if (!$baris): ?>
    <section class="sar-blok">
      <div class="sar-kosong"><?= ikon('papan', 34) ?>
        <h3>Belum ada data barang</h3>
        <p>Daftar inventaris barang<?= $sek ? ' untuk ' . e((string) $sek['nama']) : '' ?> belum diisi
           pada aplikasi e-GURU (menu Tugas Tambahan &ldquo;Sarana &amp; Prasarana&rdquo;).</p>
      </div>
    </section>
  <?php else: ?>

    <section class="sar-blok">
      <div class="sar-kepala">
        <div>
          <h2>Daftar Inventaris Barang</h2>
          <p>Sarana &amp; prasarana <?= $sek ? 'milik ' . e((string) $sek['nama']) : '' ?>
             <?= $sek ? '&middot; ' . e((string) ($sek['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah')) : '' ?></p>
        </div>
        <div class="sar-chip">
          <span><?= e((string) $ring['jenis']) ?> jenis barang</span>
          <span><?= e(number_format($ring['unit'], 0, ',', '.')) ?> unit</span>
        </div>
      </div>

      <div class="sar-tabel-wrap">
        <table class="sar-tabel">
          <thead>
            <tr>
              <th class="sar-ctr sar-k-no">No</th>
              <th class="sar-k-nama">Nama Barang</th>
              <th class="sar-k-spek">Spesifikasi</th>
              <th class="sar-k-lokasi">Lokasi</th>
              <th class="sar-ctr sar-k-kondisi">Kondisi</th>
              <th class="sar-ctr sar-k-jumlah">Jumlah</th>
              <th class="sar-k-sumber">Sumber Dana</th>
              <th class="sar-k-jenis">Jenis</th>
              <th class="sar-k-ket">Keterangan</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($tampil as $i => $b): ?>
            <tr>
              <td class="sar-ctr"><?= (($hal - 1) * $perHal) + $i + 1 ?></td>
              <td><b><?= e((string) $b['nama']) ?></b></td>
              <td class="sar-kecil"><?= e(sarpras_sel($b['spesifikasi'])) ?></td>
              <td><?= e(sarpras_sel($b['lokasi'])) ?></td>
              <td class="sar-ctr">
                <?php $k = trim((string) $b['kondisi']); ?>
                <span class="<?= e(sarpras_kondisi_kelas($k)) ?>"><?= e($k !== '' ? $k : '-') ?></span>
              </td>
              <td class="sar-ctr"><?= (int) $b['jumlah'] ?></td>
              <td><?= e(sarpras_sel($b['sumber_dana'])) ?></td>
              <td><?= e(sarpras_sel($b['jenis'])) ?></td>
              <td class="sar-kecil"><?= e(sarpras_sel($b['keterangan'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="5" class="sar-ctr">Jumlah</th>
              <th class="sar-ctr"><?= e(number_format($ring['unit'], 0, ',', '.')) ?></th>
              <th colspan="3" class="sar-ctr"><?= e((string) $ring['jenis']) ?> jenis barang</th>
            </tr>
          </tfoot>
        </table>
      </div>

      <?php
      // Keterangan rentang baris + tombol halaman (Selanjutnya / Sebelumnya).
      $info = paginasi_info($total, $perHal, $hal, 'barang');
      $nav = paginasi($total, $perHal, $hal);
      if ($info !== '' || $nav !== ''):
      ?>
        <div class="sar-halaman" id="halamanSarpras">
          <p class="sar-info-halaman"><?= e($info) ?></p>
          <?= $nav ?>
        </div>
      <?php endif; ?>

      <?php if ($ring['kondisi']): ?>
        <p class="sar-ket">
          Kondisi barang:
          <?php $pcs = []; foreach ($ring['kondisi'] as $k => $n) { $pcs[] = e((string) $k) . ' ' . $n . ' jenis'; } ?>
          <?= implode(' &middot; ', $pcs) ?>
        </p>
      <?php endif; ?>
    </section>

  <?php endif; ?>
</div>
    <?php
}
