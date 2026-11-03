<?php
/**
 * DATA GURU — TMT, MASA KERJA, POPUP DATA, dan TAMPILAN TABEL (halaman Tenaga Pendidik).
 *
 * Permintaan pemilik (v1.16.0):
 *   1. *"Yang saya tandai warna merah tambahkan inputan untuk: TMT Guru (format tanggal-bulan-tahun),
 *      Keterangan (Satmikal atau Non Satmikal)"*
 *   2. *"Pada halaman data guru, buat kartu guru bisa diklik, dan Ketika diklik akan menampilkan
 *      Popup Data: Nama Lengkap, Jenis Kelamin, Jabatan, Telepon, Masa Kerja (dihitung dari tanggal
 *      TMT hingga aktif sekarang. Misal Masa kerja 5 tahun, 3 bulan, 4 hari."*
 *   3. *"Pada halaman data guru yang saya beri tanda biru tambahkan fitur tampilkan dalam bentuk
 *      tabel. Ketika tombol tabel yang diklik maka data guru tampil dalam bentuk tabel (semua guru)
 *      dan isinya sama dengan popup data guru."*
 *
 * Aturan yang dipakai (jangan diubah tanpa bertanya kepada pemilik):
 *   • **TMT** disimpan sebagai tanggal `Y-m-d` pada kolom `guru.tmt` (input memakai
 *     `<input type="date">` — format tanggal-bulan-tahun pada peramban).
 *   • **Keterangan** = `Satmikal` atau `Non Satmikal` (kolom `guru.keterangan`, pilihan tetap;
 *     nilai asing dikosongkan, bukan ditebak).
 *   • **Masa kerja** dihitung dari TMT sampai HARI INI (tahun, bulan, hari) — satu fungsi
 *     `guru_masa_kerja()` supaya popup dan tabel tidak pernah berbeda.
 *   • Tanpa TMT, masa kerja ditampilkan "-" (tidak menebak dari tanggal dibuat).
 *
 * Gaya `.gd-*` ada pada `assets/style.css` — **jangan** menaruh `<style>` di halaman.
 */
declare(strict_types=1);

/** Pilihan Keterangan guru (permintaan pemilik — pilihan TETAP). */
const GURU_KETERANGAN = ['Satmikal', 'Non Satmikal'];

/** Keterangan sah? Nilai asing dikosongkan (tidak menebak). */
function guru_keterangan_sah(string $keterangan): string
{
    foreach (GURU_KETERANGAN as $k) {
        if (strcasecmp(trim($keterangan), $k) === 0) {
            return $k;
        }
    }
    return '';
}

/** Tanggal sah (Y-m-d) yang benar-benar ada di kalender? */
function guru_tgl_ok(string $tgl): bool
{
    if (!preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', trim($tgl), $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/**
 * TMT tersimpan sebagai `Y-m-d`; nilai kosong/tidak sah → ''.
 *
 * Menerima juga tulisan Indonesia (mis. "1 Januari 2020") lewat `tgl_impor()` apabila berkas
 * penolong panel admin tersedia — sehingga data hasil impor/ketik manual tetap tersimpan benar.
 */
function guru_tmt_sah(string $tmt): string
{
    $tmt = trim($tmt);
    if ($tmt === '') {
        return '';
    }
    if (guru_tgl_ok($tmt)) {
        return $tmt;
    }
    if (function_exists('tgl_impor')) {
        $iso = tgl_impor($tmt);
        if ($iso !== '' && guru_tgl_ok($iso)) {
            return $iso;
        }
    }
    return '';
}

/** TMT untuk ditampilkan ("1 Januari 2020"); kosong bila belum diisi. */
function guru_tmt_teks(?string $tmt): string
{
    $tmt = trim((string) $tmt);
    if ($tmt === '' || !preg_match('#^\d{4}-\d{2}-\d{2}$#', $tmt)) {
        return '';
    }
    return function_exists('tanggal_id') ? tanggal_id($tmt) : $tmt;
}

/**
 * Masa kerja dihitung dari TMT sampai hari ini.
 *
 * @return array{tahun:int,bulan:int,hari:int,teks:string,ada:bool,hari_total:int}
 *         `teks` = "5 tahun, 3 bulan, 4 hari" (permintaan pemilik); tanpa TMT → '-'.
 */
function guru_masa_kerja(?string $tmt): array
{
    $kosong = ['tahun' => 0, 'bulan' => 0, 'hari' => 0, 'teks' => '-', 'ada' => false, 'hari_total' => 0];
    $tmt = trim((string) $tmt);
    if ($tmt === '' || !preg_match('#^\d{4}-\d{2}-\d{2}$#', $tmt)) {
        return $kosong;
    }
    try {
        $mulai = new DateTimeImmutable($tmt . ' 00:00:00');
        $kini = new DateTimeImmutable(date('Y-m-d') . ' 00:00:00');
    } catch (Throwable $e) {
        return $kosong;
    }
    if ($mulai > $kini) {
        // TMT di masa depan: belum ada masa kerja (jangan menampilkan angka negatif).
        return $kosong;
    }
    $selisih = $mulai->diff($kini);
    $tahun = (int) $selisih->y;
    $bulan = (int) $selisih->m;
    $hari = (int) $selisih->d;
    $bagian = [];
    if ($tahun > 0) {
        $bagian[] = $tahun . ' tahun';
    }
    if ($bulan > 0) {
        $bagian[] = $bulan . ' bulan';
    }
    if ($hari > 0 || !$bagian) {
        $bagian[] = $hari . ' hari';
    }
    return [
        'tahun' => $tahun,
        'bulan' => $bulan,
        'hari' => $hari,
        'teks' => implode(', ', $bagian),
        'ada' => true,
        'hari_total' => (int) $mulai->diff($kini)->days,
    ];
}

/** Foto guru untuk kartu/popup/tabel (kosong bila belum ada). */
function guru_foto_url(array $g): string
{
    return trim((string) ($g['foto'] ?? ''));
}

/** Inisial nama guru (pengganti foto). */
function guru_inisial(string $nama): string
{
    $nama = trim($nama);
    return $nama === '' ? '?' : strtoupper(substr($nama, 0, 1));
}

/** Jenis kelamin dalam kata ("Laki-laki"/"Perempuan"). */
function guru_jk_teks(?string $jk): string
{
    return strtoupper(trim((string) $jk)) === 'P' ? 'Perempuan' : 'Laki-laki';
}

/** Seluruh guru aktif (halaman publik Tenaga Pendidik). */
function guru_publik_tabel(): array
{
    return all("SELECT * FROM guru WHERE status = 'aktif' ORDER BY urutan, nama");
}

/**
 * Mencetak kontrol "Kartu / Tabel" + POPUP data guru + tabel semua guru.
 *
 * Dicetak pada halaman Tenaga Pendidik (`halaman.php`, jenis menu `guru`). Tombol memakai
 * `?tampil=tabel` (bekerja tanpa JavaScript), dan kartu guru dapat diklik untuk membuka popup
 * (`<dialog>`; tanpa JavaScript setiap kartu adalah tautan ke `?guru=ID` yang membuka halaman
 * dengan popup dalam keadaan terbuka).
 *
 * @param array<int,array<string,mixed>> $daftar guru yang ditampilkan
 * @param string $kanonik alamat halaman (untuk tautan)
 * @param string $tampil 'kartu' | 'tabel'
 * @param int $guruId guru yang popup-nya diminta lewat `?guru=ID`
 */
function guru_blok_tampilan(array $daftar, string $kanonik, string $tampil, int $guruId): void
{
    $modeTabel = $tampil === 'tabel';
    $dipilih = null;
    foreach ($daftar as $g) {
        if ((int) $g['id'] === $guruId) {
            $dipilih = $g;
            break;
        }
    }
    $tautan = static fn (array $q): string => rute_tautan($kanonik, $q);
    ?>
<div class="gd-kontrol" id="guruKontrol">
  <div class="gd-kontrol-teks">
    <strong><?= angka(count($daftar)) ?> guru &amp; tenaga kependidikan</strong>
    <span class="gd-kontrol-ket">Klik kartu guru untuk melihat data lengkapnya.</span>
  </div>
  <div class="gd-tombol-grup" role="group" aria-label="Bentuk tampilan data guru">
    <a class="gd-tombol<?= $modeTabel ? '' : ' on' ?>" id="btnTampilKartu"
       href="<?= e($tautan([])) ?>#guruKontrol" <?= $modeTabel ? '' : 'aria-current="true"' ?>>
      <?= ikon('papan', 15) ?><span>Kartu</span>
    </a>
    <a class="gd-tombol<?= $modeTabel ? ' on' : '' ?>" id="btnTampilTabel"
       href="<?= e($tautan(['tampil' => 'tabel'])) ?>#guruKontrol" <?= $modeTabel ? 'aria-current="true"' : '' ?>>
      <?= ikon('daftar', 15) ?><span>Tabel</span>
    </a>
  </div>
</div>

<?php if ($modeTabel): ?>
  <?php /* ================= TAMPILAN TABEL (semua guru) =================
           Isinya SAMA dengan popup data guru: nama lengkap, jenis kelamin, jabatan, telepon, dan
           masa kerja (dari TMT sampai hari ini). Kolom tambahan: NIP/NUPTK, mata pelajaran,
           keterangan (Satmikal/Non Satmikal), dan TMT. */ ?>
  <div class="gd-tabel-wrap" id="guruTabel">
    <table class="tabel gd-tabel" id="tblGuru">
      <thead>
        <tr>
          <th style="width:52px">No</th>
          <th style="width:64px">Foto</th>
          <th>Nama Lengkap</th>
          <th style="width:110px">Jenis Kelamin</th>
          <th style="width:150px">Jabatan</th>
          <th style="width:140px">Telepon</th>
          <th style="width:120px">TMT</th>
          <th style="width:170px">Masa Kerja</th>
          <th style="width:130px">Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$daftar): ?>
          <tr><td colspan="9" class="mut">Belum ada data guru.</td></tr>
        <?php endif; ?>
        <?php foreach ($daftar as $i => $g): ?>
          <?php $mk = guru_masa_kerja($g['tmt'] ?? ''); ?>
          <tr>
            <td class="kecil"><?= $i + 1 ?></td>
            <td>
              <?php if (guru_foto_url($g) !== ''): ?>
                <img class="gd-mini" src="<?= e(guru_foto_url($g)) ?>" alt="Foto <?= e((string) $g['nama']) ?>" loading="lazy">
              <?php else: ?>
                <span class="gd-mini gd-mini-inisial"><?= e(guru_inisial((string) $g['nama'])) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= e((string) $g['nama']) ?></strong>
              <?php if (trim((string) ($g['nip'] ?? '')) !== ''): ?>
                <div class="kecil mut">NIP: <?= e((string) $g['nip']) ?></div>
              <?php endif; ?>
              <?php if (trim((string) ($g['mapel'] ?? '')) !== ''): ?>
                <div class="kecil mut"><?= e((string) $g['mapel']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= e(guru_jk_teks($g['jenis_kelamin'] ?? 'L')) ?></td>
            <td><?= e((string) ($g['jabatan'] ?? '')) ?></td>
            <td><?= trim((string) ($g['telepon'] ?? '')) !== '' ? e((string) $g['telepon']) : '-' ?></td>
            <td><?= guru_tmt_teks($g['tmt'] ?? '') !== '' ? e(guru_tmt_teks($g['tmt'] ?? '')) : '-' ?></td>
            <td><b><?= e($mk['teks']) ?></b></td>
            <td>
              <?php if (trim((string) ($g['keterangan'] ?? '')) !== ''): ?>
                <span class="gd-pil"><?= e((string) $g['keterangan']) ?></span>
              <?php else: ?>
                <span class="mut kecil">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php /* ================= POPUP DATA GURU =================
         Satu dialog dipakai untuk SEMUA guru. Skrip `initGuruPopup()` (assets/app.js) mengisi dialog
         dari data JSON di bawah sehingga kartu dapat diklik tanpa memuat ulang halaman; tanpa
         JavaScript setiap kartu adalah tautan `?guru=ID` dan dialog dicetak dalam keadaan terbuka. */ ?>
<?php
$dataPopup = [];
foreach ($daftar as $g) {
    $mk = guru_masa_kerja($g['tmt'] ?? '');
    $dataPopup[] = [
        'id' => (int) $g['id'],
        'nama' => (string) $g['nama'],
        'jk' => guru_jk_teks($g['jenis_kelamin'] ?? 'L'),
        'jabatan' => (string) ($g['jabatan'] ?? ''),
        'telepon' => trim((string) ($g['telepon'] ?? '')),
        'masa_kerja' => $mk['teks'],
        'tmt' => guru_tmt_teks($g['tmt'] ?? ''),
        'foto' => guru_foto_url($g),
        'inisial' => guru_inisial((string) $g['nama']),
        'sub' => trim((string) ($g['jabatan'] ?? '') . (trim((string) ($g['mapel'] ?? '')) !== ''
            ? ' · ' . (string) $g['mapel'] : '')),
    ];
}
?>
<script type="application/json" id="guruData"><?= json_encode(['guru' => $dataPopup],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<dialog class="gd-popup" id="popupGuru" aria-labelledby="popupGuruJudul"<?= $dipilih ? ' open' : '' ?>>
  <div class="gd-popup-kepala">
    <span class="gd-popup-foto" id="popupFoto">
      <?php if ($dipilih): ?>
        <?php if (guru_foto_url($dipilih) !== ''): ?>
          <img src="<?= e(guru_foto_url($dipilih)) ?>" alt="Foto <?= e((string) $dipilih['nama']) ?>">
        <?php else: ?>
          <span class="gd-popup-inisial"><?= e(guru_inisial((string) $dipilih['nama'])) ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </span>
    <div class="gd-popup-judul">
      <h3 id="popupGuruJudul"><?= $dipilih ? e((string) $dipilih['nama']) : 'Data Guru' ?></h3>
      <p id="popupGuruSub"><?php
        if ($dipilih) {
            $sub = trim((string) ($dipilih['jabatan'] ?? ''));
            if (trim((string) ($dipilih['mapel'] ?? '')) !== '') {
                $sub .= ($sub !== '' ? ' · ' : '') . (string) $dipilih['mapel'];
            }
            echo e($sub);
        } else {
            echo 'Klik kartu guru untuk melihat datanya.';
        } ?></p>
    </div>
    <button type="button" class="gd-popup-tutup" data-popup-tutup aria-label="Tutup"><?= ikon('tutup', 18) ?></button>
  </div>
  <?php $mkAwal = $dipilih ? guru_masa_kerja($dipilih['tmt'] ?? '') : guru_masa_kerja(''); ?>
  <dl class="gd-popup-data">
    <div><dt>Nama Lengkap</dt><dd id="popupNama"><?= $dipilih ? e((string) $dipilih['nama']) : '-' ?></dd></div>
    <div><dt>Jenis Kelamin</dt><dd id="popupJk"><?= $dipilih ? e(guru_jk_teks($dipilih['jenis_kelamin'] ?? 'L')) : '-' ?></dd></div>
    <div><dt>Jabatan</dt><dd id="popupJabatan"><?= $dipilih ? e((string) ($dipilih['jabatan'] ?? '')) : '-' ?></dd></div>
    <div><dt>Telepon</dt><dd id="popupTelepon"><?php
      $tel = $dipilih ? trim((string) ($dipilih['telepon'] ?? '')) : '';
      echo $tel !== '' ? e($tel) : '-'; ?></dd></div>
    <div class="gd-lebar"><dt>Masa Kerja</dt>
      <dd><b id="popupMasaKerja"><?= e($mkAwal['teks']) ?></b>
        <span class="gd-popup-ket" id="popupMasaKet"><?php
          if ($dipilih) {
              echo $mkAwal['ada'] ? 'sejak TMT ' . e(guru_tmt_teks($dipilih['tmt'] ?? ''))
                  : 'TMT belum diisi pada Data Guru.';
          } ?></span>
      </dd>
    </div>
  </dl>
  <form method="dialog" class="gd-popup-aksi">
    <button type="submit" class="tombol tombol-kecil">Tutup</button>
  </form>
</dialog>
    <?php
}

/**
 * Tautan kartu guru: membuka popup lewat `?guru=ID` (bekerja tanpa JavaScript) dan atribut
 * `data-popup-guru` dipakai skrip untuk membuka popup tanpa memuat ulang halaman.
 */
function guru_kartu_url(string $kanonik, int $id, string $tampil = 'kartu'): string
{
    $q = ['guru' => $id];
    if ($tampil === 'tabel') {
        $q['tampil'] = 'tabel';
    }
    return rute_tautan($kanonik, $q) . '#guruKartu';
}
