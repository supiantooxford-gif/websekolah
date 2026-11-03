<?php
/**
 * TARIK DATA PENDAFTAR (panel PPDB lama) — menu "Data Pendaftar (Tahun Lama)" pada panel admin.
 *
 * Permintaan pemilik: data pendaftar 2022–2026 diambil dari panel PPDB ma.stm.sch.id
 * (`/ppdb/panel_admin/verifikasi/thn/<tahun>`), disimpan di website, dan tampil pada halaman
 * Data Siswa → tab "Pendaftar" dengan filter per tahun.
 *
 * ⚠️ KEAMANAN: halaman panel itu WAJIB LOGIN. Nama pengguna & kata sandi diisi admin pada formulir
 * di halaman ini dan **HANYA dipakai selama satu permintaan penarikan** — keduanya TIDAK PERNAH
 * disimpan ke basis data, tidak dicatat pada log, dan tidak ditampilkan kembali (sandi selalu
 * diminta ulang saat memperbarui data).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/ppdb_tarik.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');
$pesan = '';
$galat = '';
$ringkasTarik = null;

/** Tahun yang dicentang pada formulir (hanya tahun panel yang sah). */
function tarik_tahun_pilihan(): array
{
    $sah = ppdb_tahun_panel();
    $pilih = $_POST['tahun'] ?? [];
    if (!is_array($pilih)) {
        $pilih = [$pilih];
    }
    $keluar = [];
    foreach ($pilih as $t) {
        $t = (int) $t;
        if (in_array($t, $sah, true)) {
            $keluar[] = $t;
        }
    }
    sort($keluar);
    return $keluar;
}

if ($aksi === 'tarik') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $tahunPilih = tarik_tahun_pilihan();
    $username = trim((string) ($_POST['username'] ?? ''));
    $sandi = (string) ($_POST['sandi'] ?? '');

    if (!$tahunPilih) {
        $galat = 'Pilih minimal satu tahun yang akan ditarik.';
    } elseif ($username === '' || $sandi === '') {
        $galat = 'Nama pengguna dan kata sandi panel wajib diisi.';
    } else {
        // Penarikan butuh waktu (halaman daftar + halaman CETAK tiap pendaftar untuk asal sekolah),
        // jadi batas waktu dijalankan dinaikkan bila server mengizinkan.
        @set_time_limit(0);
        $ringkasTarik = [];
        $berhasil = 0;
        $sesi = ppdb_panel_login($username, $sandi);
        if (!$sesi['ok']) {
            $galat = $sesi['pesan'];
            $ringkasTarik = null;
        } else {
            try {
                foreach ($tahunPilih as $thn) {
                    $html = ppdb_curl_get(ppdb_panel_url((string) $thn), ppdb_curl_opsi($sesi['jar']));
                    $baris = $html ? ppdb_parse_pendaftar($html, $thn) : [];
                    if (!$baris) {
                        $ringkasTarik[$thn] = ['ok' => false, 'jumlah' => 0, 'asal' => 0,
                            'pesan' => 'Halaman terbaca tetapi tidak ada baris pendaftar yang dikenali.'];
                        continue;
                    }
                    // Ambil NAMA ASAL SEKOLAH dari halaman cetak verifikasi tiap pendaftar.
                    $baris = ppdb_lengkapi_asal($baris, $sesi['jar']);
                    $asal = 0;
                    foreach ($baris as $b) {
                        if (trim((string) ($b['asal_sekolah'] ?? '')) !== '') {
                            $asal++;
                        }
                    }
                    $n = ppdb_simpan_tahun($thn, $baris);
                    $ringkasTarik[$thn] = ['ok' => $n > 0, 'jumlah' => $n, 'asal' => $asal, 'pesan' => ''];
                    $berhasil += ($n > 0 ? 1 : 0);
                }
            } finally {
                @unlink($sesi['jar']);
            }
        }
        // Kata sandi TIDAK pernah disimpan/ditampilkan — variabelnya dibuang di sini.
        unset($sandi, $_POST['sandi']);
        if ($ringkasTarik !== null) {
            if ($berhasil > 0) {
                $pesan = 'Data pendaftar berhasil ditarik untuk ' . $berhasil . ' tahun.';
            } else {
                $galat = 'Tidak ada data yang berhasil ditarik — lihat keterangan tiap tahun di bawah.';
            }
        }
    }
} elseif ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $thn = (int) ($_POST['tahun'] ?? 0);
    if (!in_array($thn, ppdb_tahun_panel(), true)) {
        $galat = 'Tahun itu bukan tahun data panel.';
    } else {
        $n = ppdb_hapus_tahun($thn);
        flash('Data pendaftar tahun ' . $thn . ' dihapus (' . $n . ' baris).', 'ok');
        redirect('ppdb_tarik.php');
    }
}

$ringkas = ppdb_panel_ringkas();
$tahunAda = ppdb_tahun_ada();
$perHalaman = (int) PUBLIK_PER_HALAMAN;
$totalPanel = (int) jumlah('SELECT COUNT(*) FROM ppdb_lama');
$totalWebsite = (int) jumlah('SELECT COUNT(*) FROM ppdb_pendaftar');

admin_atas('Tarik Data Pendaftar (Tahun Lama)', 'ppdb_tarik.php');
?>
<?php if ($pesan !== ''): ?><div class="alert alert-ok"><?= ikon('check', 18) ?> <?= e($pesan) ?></div><?php endif; ?>
<?php if ($galat !== ''): ?><div class="alert alert-err"><?= ikon('info', 18) ?> <?= e($galat) ?></div><?php endif; ?>

<div class="kartu">
  <div class="kartu-kepala">
    <div>
      <h2>Data Pendaftar 2022–<?= (int) PPDB_PANEL_TAHUN_AKHIR ?> (panel PPDB lama)</h2>
      <p class="mut sm mb0">
        Menarik data dari <code><?= e(str_replace('%d', '<b>' . PPDB_PANEL_TAHUN_MULAI . '–' . PPDB_PANEL_TAHUN_AKHIR . '</b>', PPDB_PANEL_VERIFIKASI)) ?></code>
        lalu menyimpannya di website. Data tahun <b><?= (int) ppdb_tahun_website_mulai() ?> dan setelahnya</b>
        diambil otomatis dari <b>PPDB Online website ini</b> (<?= angka($totalWebsite) ?> pendaftar saat ini).
      </p>
    </div>
  </div>

  <div class="alert alert-info">
    <?= ikon('kunci', 18) ?>
    <div>
      <b>Nama pengguna &amp; kata sandi panel TIDAK disimpan.</b>
      Keduanya hanya dipakai sekali untuk menarik data pada permintaan ini; bila ingin memperbarui
      data, isi kembali kredensialnya. Penarikan ulang untuk satu tahun <b>mengganti</b> data tahun
      itu saja — tahun lain tidak ikut berubah.
      <br><b>Yang diambil:</b> Nomor Pendaftaran, Nama Siswa, dan <b>Asal Sekolah</b>.
      Nomor &amp; nama dibaca dari halaman daftar verifikasi; <b>asal sekolah</b> diambil dari halaman
      <i>Cetak Verifikasi</i> tiap pendaftar (halaman daftar tidak memuatnya) — karena itu prosesnya
      butuh waktu: kira-kira <b>1 detik per pendaftar</b> (satu tahun berisi puluhan pendaftar).
      Bila server membatasi waktu proses, tariklah <b>satu atau dua tahun setiap kali</b>.
    </div>
  </div>

  <?php if ($ringkasTarik !== null): ?>
    <div class="kotak-isian mt16">
      <h3>Hasil penarikan</h3>
      <table class="tabel">
        <thead><tr><th style="width:90px">Tahun</th><th style="width:120px">Jumlah</th><th style="width:150px">Asal sekolah</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php foreach ($ringkasTarik as $thn => $r): ?>
          <tr>
            <td><b><?= (int) $thn ?></b></td>
            <td><?= $r['ok'] ? '<span class="pil pil-terima">' . angka($r['jumlah']) . ' data</span>' : '<span class="pil pil-tolak">gagal</span>' ?></td>
            <td class="sm"><?= $r['ok'] ? angka($r['asal']) . ' dari ' . angka($r['jumlah']) . ' terisi' : '&mdash;' ?></td>
            <td class="sm"><?= $r['ok']
                ? 'Tersimpan &amp; menggantikan data tahun itu.'
                : e((string) $r['pesan']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <form method="post" class="mt16" id="formTarikPengdaftar">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="tarik">
    <div class="form-baris">
      <label class="f">Tahun yang ditarik</label>
      <div class="centang-daftar">
        <?php foreach (ppdb_tahun_panel() as $thn): ?>
          <label class="centang-kotak">
            <input type="checkbox" name="tahun[]" value="<?= (int) $thn ?>" checked>
            <span><?= (int) $thn ?><?= isset($ringkas[$thn]) ? ' <b class="mut">(' . angka($ringkas[$thn]['jumlah']) . ')</b>' : '' ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="hint">Hapus centang tahun yang tidak ingin ditarik ulang.</p>
    </div>
    <div class="form-baris">
      <label class="f" for="username">Nama pengguna panel</label>
      <input type="text" id="username" name="username" autocomplete="off" required
             value="<?= e((string) ($_POST['username'] ?? '')) ?>">
    </div>
    <div class="form-baris">
      <label class="f" for="sandi">Kata sandi panel</label>
      <input type="password" id="sandi" name="sandi" autocomplete="new-password" required>
      <p class="hint">Tidak disimpan. Setelah penarikan, kolom ini otomatis kosong lagi.</p>
    </div>
    <button type="submit" class="tombol"><?= ikon('unduh', 16) ?> Tarik data sekarang</button>
  </form>
</div>

<div class="kartu mt24">
  <div class="kartu-kepala">
    <div>
      <h2>Data pendaftar tersimpan</h2>
      <p class="mut sm mb0">Total <?= angka($totalPanel) ?> data panel tersimpan
        · tampil pada halaman publik <b>Data Siswa → Pendaftar</b>.</p>
    </div>
  </div>
  <?php if (!$tahunAda): ?>
    <p class="mut">Belum ada data. Pilih tahun lalu tekan <b>Tarik data sekarang</b>.</p>
  <?php else: ?>
    <div class="tabel-wrap">
      <table class="tabel">
        <thead><tr><th style="width:90px">Tahun</th><th>Sumber data</th><th style="width:120px">Jumlah</th><th style="width:190px">Terakhir ditarik</th><th style="width:180px">Tindakan</th></tr></thead>
        <tbody>
        <?php foreach ($tahunAda as $thn): ?>
          <tr>
            <td><b><?= (int) $thn ?></b></td>
            <td class="sm"><?= $thn >= ppdb_tahun_website_mulai() ? 'PPDB Online website ini' : 'Panel PPDB lama' ?></td>
            <td><span class="pil pil-baru"><?= angka(ppdb_pendaftar_gabungan_jumlah((int) $thn)) ?> data</span></td>
            <td class="sm"><?= isset($ringkas[$thn]['ditarik']) && $ringkas[$thn]['ditarik'] !== ''
                ? e(waktu_id((string) $ringkas[$thn]['ditarik'])) : '&mdash;' ?></td>
            <td>
              <?php if (isset($ringkas[$thn])): ?>
                <form method="post" style="display:inline" data-konfirmasi="Hapus data pendaftar tahun <?= (int) $thn ?>?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="hapus">
                  <input type="hidden" name="tahun" value="<?= (int) $thn ?>">
                  <button type="submit" class="tombol tombol-garis tombol-kecil">Hapus tahun ini</button>
                </form>
              <?php endif; ?>
              <a class="tombol tombol-garis tombol-kecil" target="_blank" rel="noopener"
                 href="../halaman/<?= e((string) jumlah("SELECT slug FROM menu WHERE jenis = 'siswa' AND aktif = 1 LIMIT 1")) ?>?tab=pendaftar&amp;tahun=<?= (int) $thn ?>">Lihat di situs</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php admin_bawah(); ?>
