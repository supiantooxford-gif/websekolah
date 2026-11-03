<?php
/**
 * LAYANAN PIP (Program Indonesia Pintar) — panel admin.
 *
 * Halaman ini mengatur SELURUH isi menu "Layanan PIP" pada website:
 *   1. **Informasi Seputar Program Indonesia Pintar** — teks (penyunting bergaya Word) + unggah
 *      berkas **SK Penerima PIP** yang dapat dibuka pengunjung.
 *   2. **Kata Sandi Kartu PIP** — satu kata sandi (disimpan sebagai HASH) yang diminta setiap kali
 *      Kartu PIP diunduh pengunjung.
 *   3. **Daftar Penerima PIP** — Nama, NISN, Kelas, Nominal, unggah **Kartu PIP**, unggah
 *      **Surat Kuasa** (tampil pada kolom Keterangan), Keterangan, urutan, aktif/nonaktif.
 *
 * Berkas diunggah lewat media proxy platform (hanya URL-nya yang disimpan di basis data).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/pip.php';
// Halaman ini boleh diisi Administrator DAN peran Editor (konten, PPDB & Layanan PIP) — permintaan
// pemilik: "Peran editor (konten & PPDB) buat juga bisa mengakses layanan PIP untuk bisa mengisi".
require_kelola_pip();
// Kata sandi Kartu PIP boleh ditetapkan Administrator MAUPUN peran Editor (permintaan pemilik
// v1.7.23). Dihitung di sini sebagai satu titik kebijakan (boleh_sandi_pip()).
$bolehSandi = boleh_sandi_pip();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

/** Nama field berkas dokumen yang dipakai halaman ini (PDF/gambar). */
const PIP_BERKAS_EKSTENSI = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

/**
 * Unggah berkas dokumen PIP (SK, Kartu PIP, Surat Kuasa).
 * @return array{url:string,nama:string,pesan:string,galat:bool}
 */
function pip_proses_berkas(string $field, string $urlLama, string $namaLama, bool $hapus, string $label): array
{
    if ($hapus) {
        return ['url' => '', 'nama' => '', 'pesan' => $label . ' dihapus.', 'galat' => false];
    }
    $up = unggah_berkas($field, $label, PIP_BERKAS_EKSTENSI, SERTIFIKAT_MAKS_BYTE);
    if (!empty($up['kosong'])) {
        return ['url' => $urlLama, 'nama' => $namaLama, 'pesan' => '', 'galat' => false];
    }
    if (!$up['ok']) {
        return ['url' => $urlLama, 'nama' => $namaLama, 'pesan' => (string) $up['pesan'], 'galat' => true];
    }
    $nama = (string) ($_FILES[$field]['name'] ?? '');
    return ['url' => (string) $up['url'], 'nama' => $nama, 'pesan' => $label . ' berhasil diunggah.', 'galat' => false];
}

// =====================================================================================
// PENOLONG URL PANEL (selalu mempertahankan tahun yang sedang dibuka)
// =====================================================================================

/** Url panel Layanan PIP dengan tahun terpilih (dan jangkar opsional, mis. "#bagianInfo"). */
function pip_admin_url(int $tahunId = 0, string $hash = ''): string
{
    $q = $tahunId > 0 ? '?tahun=' . $tahunId : '';
    return 'pip.php' . $q . $hash;
}

/** Id tahun yang sedang dibuka di panel (dari `?tahun=` atau tahun AKTIF). */
function pip_admin_tahun_id(): int
{
    $minta = (int) ($_GET['tahun'] ?? $_POST['tahun_id'] ?? 0);
    if ($minta > 0 && pip_tahun_by_id($minta) !== null) {
        return $minta;
    }
    $t = pip_tahun_kini();
    return $t ? (int) $t['id'] : 0;
}

// =====================================================================================
// AKSI
// =====================================================================================

// ---- 0) KELOLA TAHUN LAYANAN PIP (permintaan pemilik: setiap tahun SK & penerima berbeda) ----
if ($aksi === 'simpan_tahun') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['tahun_id'] ?? 0);
    if ($id > 0 && pip_tahun_by_id($id) === null) {
        flash('Tahun layanan PIP tidak ditemukan.', 'err');
        redirect(pip_admin_url());
    }
    $tahun = pip_tahun_bersih((string) ($_POST['tahun'] ?? ''));
    if ($tahun === '') {
        flash('Tahun wajib diisi (mis. 2026 atau 2025/2026).', 'err');
        redirect(pip_admin_url($id, '#bagianTahun'));
    }
    if (pip_tahun_terpakai($tahun, $id)) {
        flash('Tahun “' . e($tahun) . '” sudah ada pada daftar.', 'err');
        redirect(pip_admin_url($id, '#bagianTahun'));
    }
    $aktif = empty($_POST['tahun_aktif']) ? 0 : 1;
    $now = date('Y-m-d H:i:s');
    if ($id > 0) {
        q('UPDATE pip_tahun SET tahun = ?, aktif = ?, urutan = ?, diubah = ? WHERE id = ?',
            [$tahun,
             isset($_POST['tahun_aktif']) ? $aktif : (int) pip_tahun_by_id($id)['aktif'],
             (string) ($_POST['urutan'] ?? '') !== '' ? (int) $_POST['urutan'] : (int) pip_tahun_by_id($id)['urutan'],
             $now, $id]);
        flash('Tahun “' . e($tahun) . '” disimpan.', 'ok');
        redirect(pip_admin_url($id, '#bagianTahun'));
    }
    q('INSERT INTO pip_tahun (tahun, urutan, aktif, dibuat, diubah) VALUES (?, ?, ?, ?, ?)',
        [$tahun,
         (string) ($_POST['urutan'] ?? '') !== '' ? (int) $_POST['urutan'] : pip_tahun_urutan_baru(),
         isset($_POST['tahun_aktif']) ? $aktif : 1,
         $now, $now]);
    $baru = (int) id_baru();
    // Tahun pertama yang ditambahkan langsung dijadikan tahun aktif supaya pengunjung tidak
    // melihat halaman kosong.
    if (pip_tahun_jumlah() === 1) {
        pip_tahun_aktif_set($baru);
    }
    flash('Tahun “' . e($tahun) . '” ditambahkan. Silakan unggah SK Penerima PIP dan isi daftar penerimanya.', 'ok');
    redirect(pip_admin_url($baru, '#bagianInfo'));
}

if ($aksi === 'tahun') {   // jadikan tahun AKTIF (yang tampil lebih dulu bagi pengunjung)
    pip_tahun_aktif_set((int) ($_GET['id'] ?? 0));
    flash('Tahun ' . e(pip_tahun_label((int) ($_GET['id'] ?? 0))) . ' dijadikan tahun utama halaman.', 'ok');
    redirect(pip_admin_url((int) ($_GET['id'] ?? 0), '#bagianTahun'));
}

if ($aksi === 'hapus_tahun') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['tahun_id'] ?? 0);
    $t = pip_tahun_by_id($id);
    if (!$t) {
        flash('Tahun layanan PIP tidak ditemukan.', 'err');
        redirect(pip_admin_url());
    }
    if (pip_tahun_jumlah() <= 1) {
        flash('Tahun terakhir tidak dapat dihapus — minimal satu tahun harus tersedia.', 'err');
        redirect(pip_admin_url($id, '#bagianTahun'));
    }
    $jml = pip_tahun_penerima_jumlah($id);
    // ⚠️ PENGAMAN: tahun yang MASIH berisi penerima hanya boleh dihapus bila admin mengetik HAPUS.
    // Penghapusan tahun ikut menghapus seluruh penerimanya (satu tahun = satu himpunan data),
    // sehingga satu klik yang keliru bisa membuang banyak data.
    if ($jml > 0 && strtoupper(trim((string) ($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
        flash('Penghapusan tahun ' . e(pip_tahun_label($t)) . ' dibatalkan — tahun itu masih berisi '
            . $jml . ' penerima. Ketik HAPUS pada kotak konfirmasi bila benar-benar ingin menghapusnya.', 'err');
        redirect(pip_admin_url($id, '#bagianTahun'));
    }
    // Penerima tahun itu ikut terhapus (satu tahun = satu himpunan data).
    q('DELETE FROM pip_penerima WHERE tahun_id = ?', [$id]);
    q('DELETE FROM pip_tahun WHERE id = ?', [$id]);
    if ((int) pengaturan('pip_tahun_aktif', '0') === $id) {
        $sisa = pip_tahun_list(false);
        if ($sisa) {
            pip_tahun_aktif_set((int) $sisa[0]['id']);
        }
    }
    flash('Tahun “' . e(pip_tahun_label($t)) . '” dihapus'
        . ($jml > 0 ? ' beserta ' . $jml . ' data penerimanya' : '') . '.', 'info');
    redirect(pip_admin_url());
}

// ---- 1) Simpan informasi (judul/teks GLOBAL) + SK & catatan TAHUN terpilih ----
if ($aksi === 'simpan_info') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $tahunId = pip_admin_tahun_id();
    $tahun = pip_tahun_by_id($tahunId);
    if (!$tahun) {
        flash('Tahun layanan PIP belum dipilih.', 'err');
        redirect(pip_admin_url());
    }
    // ⚠️ Medan yang TIDAK dikirim TIDAK diubah — tanpa ini, kiriman sebagian (mis. hanya mengganti
    // SK) akan mengosongkan judul/teks yang sudah diisi admin.
    if (array_key_exists('pip_judul_info', $_POST)) {
        pengaturan_set('pip_judul_info', potong((string) $_POST['pip_judul_info'], 160, ''));
    }
    if (array_key_exists('pip_judul_daftar', $_POST)) {
        pengaturan_set('pip_judul_daftar', potong((string) $_POST['pip_judul_daftar'], 160, ''));
    }
    if (array_key_exists('pip_info', $_POST)) {
        // `bersihkan_html()` WAJIB: menghapus tag/atribut berbahaya sebelum disimpan & ditampilkan.
        pengaturan_set('pip_info', potong_html(bersihkan_html((string) $_POST['pip_info']), TEKS_PANJANG_MAKS));
    }

    // SK & catatan → kolom pada baris TAHUN (setiap tahun SK-nya berbeda).
    $hasil = pip_proses_berkas('pip_sk', (string) $tahun['sk'], (string) $tahun['sk_nama'],
        !empty($_POST['pip_sk_hapus']), 'SK Penerima PIP');
    $catatan = array_key_exists('pip_catatan', $_POST)
        ? potong_baris((string) $_POST['pip_catatan'], 1000)
        : (string) $tahun['catatan'];
    q('UPDATE pip_tahun SET sk = ?, sk_nama = ?, catatan = ?, diubah = ? WHERE id = ?',
        [$hasil['url'], $hasil['nama'], $catatan, date('Y-m-d H:i:s'), $tahunId]);
    flash('Data tahun ' . e(pip_tahun_label($tahun)) . ' disimpan. ' . $hasil['pesan'],
        $hasil['galat'] ? 'err' : 'ok');
    redirect(pip_admin_url($tahunId, '#bagianInfo'));
}

// ---- 2) Tetapkan / hapus kata sandi Kartu PIP (Administrator & Editor — v1.7.23) ----
if ($aksi === 'simpan_sandi') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    if (!empty($_POST['sandi_hapus'])) {
        pip_sandi_hapus();
        flash('Kata sandi Kartu PIP dihapus — kartu tidak dapat diunduh sampai sandi ditetapkan kembali.', 'info');
        redirect('pip.php#bagianSandi');
    }
    $sandi = (string) ($_POST['sandi'] ?? '');
    $ulang = (string) ($_POST['sandi_ulang'] ?? '');
    if ($ulang !== '' && $sandi !== $ulang) {
        flash('Kata sandi dan ulangannya tidak sama.', 'err');
        redirect('pip.php#bagianSandi');
    }
    $galat = pip_sandi_set($sandi);
    flash($galat !== '' ? $galat : 'Kata sandi Kartu PIP berhasil ditetapkan.', $galat !== '' ? 'err' : 'ok');
    redirect('pip.php#bagianSandi');
}

// ---- 3) Simpan / ubah satu penerima PIP ----
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $lama = $id > 0 ? pip_penerima_by_id($id) : null;
    $tahunId = pip_admin_tahun_id();
    if ($id > 0 && !$lama) {
        flash('Data penerima tidak ditemukan.', 'err');
        redirect(pip_admin_url($tahunId));
    }
    if ($id > 0) {
        // Tahun mengikuti data yang sedang diubah (bukan tahun yang sedang dibuka) supaya
        // memindahkan tahun tidak pernah terjadi tanpa sengaja.
        $tahunId = (int) $lama['tahun_id'];
    }
    if ($tahunId <= 0 || pip_tahun_by_id($tahunId) === null) {
        flash('Pilih tahun layanan PIP lebih dulu sebelum menyimpan penerima.', 'err');
        redirect(pip_admin_url());
    }
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nama === '') {
        flash('Nama penerima wajib diisi.', 'err');
        redirect(pip_admin_url($tahunId, '#formPenerima'));
    }

    $kartuLama = (string) ($lama['kartu'] ?? '');
    $kartuNamaLama = (string) ($lama['kartu_nama'] ?? '');
    $suratLama = (string) ($lama['surat'] ?? '');
    $suratNamaLama = (string) ($lama['surat_nama'] ?? '');
    $hKartu = pip_proses_berkas('kartu', $kartuLama, $kartuNamaLama, !empty($_POST['kartu_hapus']), 'Kartu PIP');
    $hSurat = pip_proses_berkas('surat', $suratLama, $suratNamaLama, !empty($_POST['surat_hapus']), 'Surat Kuasa');

    $nilai = [
        potong($nama, 120, ''),
        potong((string) ($_POST['nisn'] ?? ''), 30, ''),
        potong((string) ($_POST['kelas'] ?? ''), 40, ''),
        pip_uang((string) ($_POST['nominal'] ?? '')),
        $hKartu['url'],
        $hKartu['nama'],
        $hSurat['url'],
        $hSurat['nama'],
        potong_baris((string) ($_POST['keterangan'] ?? ''), 1000),
        (string) ($_POST['urutan'] ?? '') !== '' ? (int) $_POST['urutan'] : pip_urutan_baru($tahunId),
        empty($_POST['aktif']) ? 0 : 1,
        $tahunId,
    ];
    $pesanBerkas = trim($hKartu['pesan'] . ' ' . $hSurat['pesan']);
    $galatBerkas = $hKartu['galat'] || $hSurat['galat'];

    if ($id > 0) {
        q('UPDATE pip_penerima SET nama = ?, nisn = ?, kelas = ?, nominal = ?, kartu = ?, kartu_nama = ?,
                surat = ?, surat_nama = ?, keterangan = ?, urutan = ?, aktif = ?, tahun_id = ?, diubah = ?
            WHERE id = ?',
            array_merge($nilai, [date('Y-m-d H:i:s'), $id]));
        flash('Data penerima “' . e($nama) . '” disimpan. ' . $pesanBerkas, $galatBerkas ? 'err' : 'ok');
    } else {
        q('INSERT INTO pip_penerima (nama, nisn, kelas, nominal, kartu, kartu_nama, surat, surat_nama,
                keterangan, urutan, aktif, tahun_id, dibuat, diubah)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge($nilai, [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]));
        flash('Penerima “' . e($nama) . '” ditambahkan pada tahun ' . e(pip_tahun_label($tahunId)) . '. '
            . $pesanBerkas, $galatBerkas ? 'err' : 'ok');
    }
    redirect(pip_admin_url($tahunId));
}

if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $p = pip_penerima_by_id($id);
    if (!$p) {
        flash('Data penerima tidak ditemukan.', 'err');
    } elseif (hapus_baris('pip_penerima', $id)) {
        flash('Penerima “' . e((string) $p['nama']) . '” dihapus.', 'ok');
    } else {
        flash('Data penerima gagal dihapus.', 'err');
    }
    redirect(pip_admin_url());
}

if ($aksi === 'toggle') {
    q('UPDATE pip_penerima SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    flash('Status penerima diubah.', 'ok');
    redirect(pip_admin_url());
}

if ($aksi === 'geser' || $aksi === 'naik' || $aksi === 'turun') {
    $arah = (string) ($_GET['arah'] ?? ($aksi === 'geser' ? 'naik' : $aksi));
    geser_urutan('pip_penerima', (int) ($_GET['id'] ?? 0), $arah === 'turun' ? 'turun' : 'naik');
    redirect('pip.php');
}

// =====================================================================================
// TAMPILAN
// =====================================================================================
// Tahun yang sedang dibuka di panel. `?ubah_tahun=ID` membuka formulir Ubah Tahun tanpa
// mengubah tahun yang sedang diisi (perubahan tahun dibatalkan dengan menekan Batal).
$editTahun = (int) ($_GET['ubah_tahun'] ?? 0) > 0 ? pip_tahun_by_id((int) $_GET['ubah_tahun']) : null;
$tahunId = pip_admin_tahun_id();
$tahun = pip_tahun_by_id($tahunId);
$daftarTahun = pip_tahun_list(false);
$tahunAktifId = (int) pengaturan('pip_tahun_aktif', '0');
$edit = (int) ($_GET['edit'] ?? 0) > 0 ? pip_penerima_by_id((int) $_GET['edit']) : null;
$daftar = pip_penerima(false, $tahunId);
$cari = trim((string) ($_GET['cari'] ?? ''));
if ($cari !== '') {
    $daftar = array_values(array_filter($daftar, static fn (array $p): bool =>
        stripos((string) $p['nama'], $cari) !== false
        || stripos((string) $p['nisn'], $cari) !== false
        || stripos((string) $p['kelas'], $cari) !== false));
}
$jmlAktif = pip_jumlah(true, $tahunId);
$total = pip_total_nominal(pip_penerima(true, $tahunId));
$skTahun = $tahun ? (string) $tahun['sk'] : '';
$namaSkTahun = $tahun ? (string) $tahun['sk_nama'] : '';

admin_atas('Layanan PIP');
pesan_flash();
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2 style="margin:0 0 4px">Layanan PIP (Program Indonesia Pintar)</h2>
            <p class="mut kecil mb0">Halaman ini mengisi menu <strong>Layanan PIP</strong> pada website.
                <strong>SK Penerima PIP</strong> dan <strong>daftar penerima</strong> tersimpan
                <strong>per tahun</strong> (karena setiap tahun berbeda); teks informasi program dan
                kata sandi Kartu PIP berlaku untuk semua tahun.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <span class="pil pil-emas"><?= angka($jmlAktif) ?> penerima aktif</span>
            <span class="pil pil-baru"><?= angka(count($daftar)) ?> total</span>
            <?php if (pip_sandi_ada()): ?>
                <span class="pil pil-terima"><?= ikon('kunci', 13) ?> Sandi kartu sudah diatur</span>
            <?php else: ?>
                <span class="pil pil-tolak"><?= ikon('kunci', 13) ?> Sandi kartu belum diatur</span>
            <?php endif; ?>
            <a class="tombol tombol-garis tombol-kecil" target="_blank" rel="noopener" href="<?= e(pip_url()) ?>">
                <?= ikon('mata', 15) ?> Lihat halaman
            </a>
        </div>
    </div>

    <div class="alert alert-info">
        <strong>Kartu PIP dilindungi kata sandi.</strong> Peserta didik/orang tua yang menekan tombol
        <em>Unduh</em> pada kolom Kartu PIP harus memasukkan kata sandi yang Bapak/Ibu tetapkan di bawah.
        Berkasnya dialirkan server sehingga tautan aslinya tidak dapat disebar untuk melewati sandi.
        Sandi disimpan teracak — bila lupa, cukup tetapkan sandi baru.
    </div>

    <div class="info-chip">
        <div><b><?= angka($jmlAktif) ?></b><span>Penerima aktif</span></div>
        <div><b><?= $total > 0 ? e(pip_rp($total)) : '-' ?></b><span>Total nominal</span></div>
        <div><b><?= pip_sk() !== '' ? 'Ada' : 'Belum' ?></b><span>SK Penerima PIP</span></div>
        <div><b><?= pip_sandi_ada() ? 'Sudah' : 'Belum' ?></b><span>Sandi Kartu PIP</span></div>
    </div>
</div>

<!-- ============================ 0. TAHUN LAYANAN PIP ============================ -->
<?php /* Permintaan pemilik: "tambahkan tahun (karena setiap tahun SK dan penerima PIP tidak sama)".
         Kartu ini menjadi pengendali seluruh pengisian: tahun yang dipilih di sini menentukan SK
         mana yang disunting dan ke tahun mana penerima baru disimpan. */ ?>
<div class="kartu mb24" id="bagianTahun">
    <div class="kartu-kepala">
        <div>
            <h2 style="margin:0 0 4px">Tahun Layanan PIP</h2>
            <p class="mut kecil mb0">Setiap tahun memiliki <strong>SK Penerima PIP</strong> dan
                <strong>daftar penerima</strong> sendiri. Pilih tahun di bawah untuk mengisinya —
                penerima baru selalu masuk ke tahun yang sedang dibuka.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <span class="pil pil-emas"><?= ikon('kalender', 13) ?> Sedang dibuka: <?= e(pip_tahun_label($tahun)) ?></span>
            <span class="pil pil-baru"><?= angka(pip_tahun_jumlah()) ?> tahun</span>
        </div>
    </div>

    <form class="saringan" method="get" action="pip.php">
        <div class="medan">
            <label for="pilihTahunPip">Pilih Tahun</label>
            <select id="pilihTahunPip" name="tahun" data-auto>
                <?php if (!$daftarTahun): ?><option value="0">Belum ada tahun</option><?php endif; ?>
                <?php foreach ($daftarTahun as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $tahunId ? 'selected' : '' ?>>
                        <?= e(pip_tahun_label($t)) ?><?= (int) $t['id'] === $tahunAktifId ? ' (tahun utama)' : '' ?>
                        <?= (int) $t['aktif'] === 1 ? '' : ' — nonaktif' ?>
                        · <?= (int) pip_tahun_penerima_jumlah((int) $t['id']) ?> penerima
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="bantuan">Tahun <strong>utama</strong> adalah tahun yang tampil lebih dulu bagi
                pengunjung halaman Layanan PIP.</div>
        </div>
    </form>

    <div class="tabel-wrap mt16" style="margin-top:16px">
        <table class="tabel" id="tblPipTahun">
            <thead>
                <tr>
                    <th style="width:52px">No</th>
                    <th>Tahun</th>
                    <th style="width:120px">Penerima</th>
                    <th style="width:120px">SK Penerima</th>
                    <th style="width:130px">Status</th>
                    <th class="aksi-sel">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$daftarTahun): ?>
                    <tr><td colspan="6" class="mut">Belum ada tahun. Tambahkan tahun pertama pada
                        formulir di bawah (mis. <b><?= e(date('Y')) ?></b>).</td></tr>
                <?php endif; ?>
                <?php foreach ($daftarTahun as $i => $t): $tid = (int) $t['id']; ?>
                    <tr<?= $tid === $tahunId ? ' style="background:#f7fbf9"' : '' ?>>
                        <td class="kecil"><?= $i + 1 ?></td>
                        <td>
                            <strong><?= e(pip_tahun_label($t)) ?></strong>
                            <?php if ($tid === $tahunId): ?> <span class="pil pil-emas">dibuka</span><?php endif; ?>
                            <?php if ($tid === $tahunAktifId): ?> <span class="pil pil-terima">tahun utama</span><?php endif; ?>
                            <div class="kecil mut">urutan <?= (int) $t['urutan'] ?></div>
                        </td>
                        <td class="kecil"><?= angka(pip_tahun_penerima_jumlah($tid)) ?> orang</td>
                        <td class="kecil"><?php if (trim((string) $t['sk']) !== ''): ?>
                                <span class="pil pil-terima">Ada</span>
                            <?php else: ?><span class="mut">belum</span><?php endif; ?></td>
                        <td><?= ((int) $t['aktif'] === 1) ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini(pip_admin_url($tid, '#bagianTahun'), 'mata', 'Buka tahun ini') ?>
                            <?php if ($tid !== $tahunAktifId): ?>
                                <?= tombol_mini('pip.php?aksi=tahun&id=' . $tid, 'check', 'Jadikan tahun utama') ?>
                            <?php endif; ?>
                            <?= tombol_mini(pip_admin_url($tahunId) . (strpos(pip_admin_url($tahunId), '?') === false ? '?' : '&')
                                . 'ubah_tahun=' . $tid . '#formTahun', 'edit', 'Ubah tahun') ?>
                            <?php if (pip_tahun_penerima_jumlah($tid) === 0): ?>
                                <?= form_hapus('hapus_tahun', $tid, 'Hapus tahun “' . pip_tahun_label($t) . '”?', ['tahun_id' => $tid]) ?>
                            <?php else: ?>
                                <?php /* Tahun berisi data → hapus hanya lewat kartu konfirmasi (ketik HAPUS) di bawah. */ ?>
                                <a class="tombol-ikon bahaya" title="Tahun ini masih berisi data — lihat kotak konfirmasi di bawah"
                                   href="<?= e(pip_admin_url($tid, '#hapusTahun')) ?>"><?= ikon('trash', 15) ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php // Penghapusan tahun yang MASIH berisi penerima wajib dikonfirmasi dengan mengetik HAPUS.
          if ($tahun && pip_tahun_penerima_jumlah($tahunId) > 0): ?>
        <div class="divider"></div>
        <div id="hapusTahun">
            <?php form_konfirmasi_ketik(
                'hapus_tahun',
                'Hapus Tahun ' . pip_tahun_label($tahun),
                'Tahun ' . pip_tahun_label($tahun) . ' beserta ' . pip_tahun_penerima_jumlah($tahunId)
                    . ' data penerima, kartu, dan surat kuasanya akan dihapus. Data tahun lain tidak terpengaruh.',
                ['tahun_id' => $tahunId]
            ); ?>
        </div>
    <?php endif; ?>

    <div class="divider"></div>
    <h3 style="margin:0 0 10px"><?= ($editTahun ? 'Ubah' : 'Tambah') ?> Tahun Layanan PIP</h3>
    <form method="post" action="pip.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan_tahun">
        <input type="hidden" name="tahun_id" value="<?= (int) ($editTahun['id'] ?? 0) ?>">
        <div class="form-3">
            <div class="form-baris">
                <label for="tahun">Tahun <span class="wajib">*</span></label>
                <input type="text" id="tahun" name="tahun" required maxlength="24"
                       placeholder="mis. <?= e(date('Y')) ?> atau 2025/2026"
                       value="<?= e((string) ($editTahun['tahun'] ?? '')) ?>">
                <div class="bantuan">Bebas diisi (tahun kalender atau tahun ajaran), maksimal 24 karakter.</div>
            </div>
            <div class="form-baris">
                <label for="urutan_tahun">Urutan</label>
                <input type="number" id="urutan_tahun" name="urutan" min="0" max="9999"
                       value="<?= (int) ($editTahun['urutan'] ?? 0) ?>">
                <div class="bantuan">Kosongkan untuk diletakkan paling akhir.</div>
            </div>
            <div class="form-baris">
                <label>Status</label>
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="tahun_aktif" value="1"
                        <?= !isset($editTahun['aktif']) || (int) $editTahun['aktif'] === 1 ? 'checked' : '' ?>>
                    Tahun ini tampil pada halaman publik
                </label>
            </div>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> <?= ($editTahun ? 'Simpan Tahun' : 'Tambah Tahun') ?></button>
            <?php if ($editTahun): ?><a class="tombol tombol-garis" href="<?= e(pip_admin_url($tahunId, '#bagianTahun')) ?>">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<!-- ============================ 1. INFORMASI + SK ============================ -->
<div class="kartu mb24" id="bagianInfo">
    <div class="kartu-kepala">
        <div>
            <h2 style="margin:0 0 4px">Informasi Seputar Program Indonesia Pintar</h2>
            <p class="mut kecil mb0">Judul &amp; teks informasi berlaku untuk <strong>semua tahun</strong>,
                sedangkan <strong>SK Penerima PIP</strong> dan <strong>catatan</strong> menempel pada
                tahun <strong><?= e(pip_tahun_label($tahun)) ?></strong> (sesuai tahun yang dibuka di
                atas).</p>
        </div>
    </div>
    <form method="post" action="pip.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan_info">
        <input type="hidden" name="tahun_id" value="<?= (int) $tahunId ?>">
        <div class="alert alert-info">
            <?= ikon('kalender', 15) ?> Yang Anda isi di bawah untuk tahun
            <strong><?= e(pip_tahun_label($tahun)) ?></strong>: <b>SK Penerima PIP</b> dan <b>catatan</b>.
            Ingin tahun lain? Pilih tahunnya pada kartu <b>Tahun Layanan PIP</b> di atas.
        </div>
        <div class="form-2">
            <div class="form-baris">
                <label for="pip_judul_info">Judul Bagian Informasi (semua tahun)</label>
                <input type="text" id="pip_judul_info" name="pip_judul_info" maxlength="160"
                       value="<?= e(pip_judul_info()) ?>">
            </div>
            <div class="form-baris">
                <label for="pip_judul_daftar">Judul Bagian Daftar Penerima (semua tahun)</label>
                <input type="text" id="pip_judul_daftar" name="pip_judul_daftar" maxlength="160"
                       value="<?= e(pip_judul_daftar()) ?>">
                <div class="bantuan">Nama madrasah/sekolah ditambahkan otomatis di belakang judul ini.</div>
            </div>
        </div>
        <div class="form-baris">
            <label for="pipInfoEditor">Isi Informasi Seputar Program Indonesia Pintar</label>
            <?php editor_teks('pip_info', pip_info(), 'pipInfoEditor', 380,
                'Boleh memakai judul, daftar berbutir/bernomor, tabel sederhana, dan gambar.'); ?>
        </div>
        <div class="form-2">
            <div class="form-baris">
                <label for="pipSk">Berkas SK Penerima PIP tahun <?= e(pip_tahun_label($tahun)) ?></label>
                <input type="file" id="pipSk" name="pip_sk" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <div class="bantuan">PDF atau gambar, maksimal 15 MB. Tombol <b>Lihat SK Penerima PIP</b>
                    muncul pada halaman publik bila berkas tahun itu ada.</div>
                <?php if ($skTahun !== ''): ?>
                    <div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <a class="tombol tombol-garis tombol-kecil" target="_blank" rel="noopener" href="<?= e($skTahun) ?>">
                            <?= ikon('lihat', 14) ?> Lihat berkas sekarang
                        </a>
                        <span class="kecil mut"><?= e($namaSkTahun) ?></span>
                        <label class="rasta kecil" style="gap:8px">
                            <input type="checkbox" name="pip_sk_hapus" value="1"> Hapus berkas SK ini
                        </label>
                    </div>
                <?php else: ?>
                    <div class="kecil mut" style="margin-top:8px">Belum ada SK untuk tahun ini.</div>
                <?php endif; ?>
            </div>
            <div class="form-baris">
                <label for="pip_catatan">Catatan tahun <?= e(pip_tahun_label($tahun)) ?> (boleh dikosongkan)</label>
                <textarea id="pip_catatan" name="pip_catatan" style="min-height:120px"
                          placeholder="mis. Jadwal pengambilan/pencairan tahun ini."><?= e(pip_catatan($tahun)) ?></textarea>
                <div class="bantuan">Tampil pada kotak <b>Info Penting</b> di halaman publik. Satu baris = satu
                    baris (tekan Enter untuk baris baru).</div>
            </div>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Informasi &amp; SK</button>
        </div>
    </form>
</div>

<!-- ============================ 2. KATA SANDI ============================ -->
<div class="kartu mb24" id="bagianSandi">
    <div class="kartu-kepala">
        <div>
            <h2 style="margin:0 0 4px">Kata Sandi Kartu PIP</h2>
            <p class="mut kecil mb0">Satu kata sandi untuk SEMUA kartu (berlaku di semua tahun); diminta
                saat pengunjung mengunduh Kartu PIP. Disimpan teracak sehingga tidak dapat ditampilkan
                kembali.</p>
        </div>
        <span class="pil <?= pip_sandi_ada() ? 'pil-terima' : 'pil-tolak' ?>">
            <?= pip_sandi_ada() ? 'Sudah diatur' : 'Belum diatur' ?>
        </span>
    </div>
    <?php if ($bolehSandi): ?>
    <form method="post" action="pip.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan_sandi">
        <div class="form-2">
            <div class="form-baris">
                <label for="sandi">Kata Sandi Baru <span class="wajib">*</span></label>
                <input type="text" id="sandi" name="sandi" required minlength="<?= (int) PIP_SANDI_MIN ?>"
                       maxlength="<?= (int) PIP_SANDI_MAKS ?>" autocomplete="off"
                       placeholder="mis. NISN atau kata yang mudah diingat penerima">
                <div class="bantuan">Minimal <?= (int) PIP_SANDI_MIN ?> karakter. Bagikan hanya kepada
                    penerima PIP/orang tuanya.</div>
            </div>
            <div class="form-baris">
                <label for="sandi_ulang">Ulangi Kata Sandi</label>
                <input type="text" id="sandi_ulang" name="sandi_ulang" maxlength="<?= (int) PIP_SANDI_MAKS ?>"
                       autocomplete="off" placeholder="Ketik ulang untuk memastikan">
            </div>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('kunci', 17) ?> Tetapkan Kata Sandi</button>
            <span class="kecil mut">Menetapkan sandi baru menggantikan sandi lama.</span>
        </div>
        <?php if (pip_sandi_ada()): ?>
            <div class="divider"></div>
            <label class="rasta kecil" style="gap:9px">
                <input type="checkbox" name="sandi_hapus" value="1">
                Hapus kata sandi — kartu tidak dapat diunduh sampai sandi ditetapkan kembali
            </label>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>

<!-- ============================ 3. DAFTAR PENERIMA ============================ -->
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2 style="margin:0 0 4px">Daftar Penerima PIP — Tahun <?= e(pip_tahun_label($tahun)) ?></h2>
            <p class="mut kecil mb0">Kolom yang tampil pada website: No, Nama, NISN, Kelas, Nominal,
                Kartu PIP (unduh ber-sandi), dan Keterangan (memuat tautan Surat Kuasa). Penerima baru
                disimpan pada tahun <strong><?= e(pip_tahun_label($tahun)) ?></strong> — tahun aktif
                <?= e(pip_tahun_label($tahunAktifId)) ?>.</p>
        </div>
        <a class="tombol tombol-kecil" href="<?= e(pip_admin_url($tahunId, '#formPenerima')) ?>"><?= ikon('plus', 15) ?> Tambah Penerima</a>
    </div>

    <form class="saringan" method="get" action="pip.php">
        <input type="hidden" name="tahun" value="<?= (int) $tahunId ?>">
        <div class="medan">
            <label for="saringCariPip">Cari pada tahun ini</label>
            <input type="search" id="saringCariPip" name="cari" value="<?= e($cari) ?>" placeholder="nama / NISN / kelas">
        </div>
        <?php if ($cari !== ''): ?>
            <a class="tombol tombol-garis tombol-kecil" href="<?= e(pip_admin_url($tahunId)) ?>">Reset</a>
        <?php endif; ?>
    </form>

    <div class="tabel-wrap">
        <table class="tabel" id="tblPip">
            <thead>
                <tr>
                    <th style="width:52px">No</th>
                    <th>Nama</th>
                    <th style="width:120px">NISN</th>
                    <th style="width:110px">Kelas</th>
                    <th style="width:130px">Nominal</th>
                    <th style="width:130px">Kartu PIP</th>
                    <th style="width:130px">Surat Kuasa</th>
                    <th style="width:90px">Status</th>
                    <th class="aksi-sel">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$daftar): ?>
                    <tr><td colspan="9" class="mut"><?= $cari !== ''
                        ? 'Tidak ada penerima yang cocok dengan pencarian pada tahun ' . e(pip_tahun_label($tahun)) . '.'
                        : 'Belum ada penerima PIP pada tahun ' . e(pip_tahun_label($tahun)) . '. Klik “Tambah Penerima” untuk mulai mengisi — nama, NISN, kelas, nominal, lalu unggah Kartu PIP dan Surat Kuasanya.' ?></td></tr>
                <?php endif; ?>
                <?php foreach ($daftar as $i => $p): ?>
                    <tr>
                        <td class="kecil"><?= $i + 1 ?></td>
                        <td><strong><?= e((string) $p['nama']) ?></strong>
                            <div class="kecil mut"><?= (int) $p['urutan'] ?></div></td>
                        <td class="kecil"><?= e(pip_sel($p['nisn'])) ?></td>
                        <td class="kecil"><?= e(pip_sel($p['kelas'])) ?></td>
                        <td class="kecil"><?= e(pip_rp($p['nominal'])) ?></td>
                        <td class="kecil"><?php if (trim((string) $p['kartu']) !== ''): ?>
                                <span class="pil pil-terima">Ada</span>
                                <a class="kecil" target="_blank" rel="noopener" href="<?= e((string) $p['kartu']) ?>">lihat</a>
                            <?php else: ?><span class="mut">belum ada</span><?php endif; ?></td>
                        <td class="kecil"><?php if (trim((string) $p['surat']) !== ''): ?>
                                <span class="pil pil-terima">Ada</span>
                                <a class="kecil" target="_blank" rel="noopener" href="<?= e((string) $p['surat']) ?>">lihat</a>
                            <?php else: ?><span class="mut">belum ada</span><?php endif; ?></td>
                        <td><?= ((int) $p['aktif'] === 1) ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini(pip_admin_url($tahunId) . (strpos(pip_admin_url($tahunId), '?') === false ? '?' : '&')
                                . 'edit=' . (int) $p['id'] . '#formPenerima', 'edit', 'Ubah data penerima') ?>
                            <?= tombol_mini('pip.php?aksi=geser&arah=naik&id=' . (int) $p['id'] . '&tahun=' . $tahunId, 'naik', 'Naikkan urutan') ?>
                            <?= tombol_mini('pip.php?aksi=geser&arah=turun&id=' . (int) $p['id'] . '&tahun=' . $tahunId, 'turun', 'Turunkan urutan') ?>
                            <?= tombol_mini('pip.php?aksi=toggle&id=' . (int) $p['id'] . '&tahun=' . $tahunId, 'mata', 'Aktifkan/nonaktifkan') ?>
                            <?= form_hapus('hapus', (int) $p['id'], 'Hapus penerima “' . $p['nama'] . '”?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="kartu" id="formPenerima">
    <h2><?= $edit ? 'Ubah Penerima: ' . e((string) $edit['nama']) : 'Tambah Penerima PIP' ?></h2>
    <form method="post" action="pip.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <input type="hidden" name="tahun_id" value="<?= (int) ($edit ? (int) $edit['tahun_id'] : $tahunId) ?>">
        <?php if ($edit): ?>
            <div class="alert alert-info">
                <?= ikon('kalender', 15) ?> Penerima ini tercatat pada tahun
                <strong><?= e(pip_tahun_label((int) $edit['tahun_id'])) ?></strong> — tahunnya tidak
                berubah saat data disunting.
            </div>
        <?php endif; ?>
        <div class="form-2">
            <div class="form-baris">
                <label for="nama">Nama Penerima <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="120"
                       value="<?= e((string) ($edit['nama'] ?? '')) ?>">
            </div>
            <div class="form-baris">
                <label for="nisn">NISN</label>
                <input type="text" id="nisn" name="nisn" maxlength="30" value="<?= e((string) ($edit['nisn'] ?? '')) ?>">
            </div>
        </div>
        <div class="form-3">
            <div class="form-baris">
                <label for="kelas">Kelas</label>
                <input type="text" id="kelas" name="kelas" maxlength="40" placeholder="mis. X / XII IPA 1"
                       value="<?= e((string) ($edit['kelas'] ?? '')) ?>">
            </div>
            <div class="form-baris">
                <label for="nominal">Nominal (Rp)</label>
                <input type="text" id="nominal" name="nominal" inputmode="numeric" maxlength="20"
                       placeholder="mis. 1.000.000" value="<?= (int) ($edit['nominal'] ?? 0) > 0 ? angka((int) $edit['nominal']) : '' ?>">
                <div class="bantuan">Titik pemisah ribuan boleh dipakai; hanya angkanya yang disimpan.</div>
            </div>
            <div class="form-baris">
                <label for="urutan">Urutan</label>
                <input type="number" id="urutan" name="urutan" min="0" max="9999" value="<?= (int) ($edit['urutan'] ?? 0) ?>">
                <div class="bantuan">Kosongkan untuk diletakkan paling akhir.</div>
            </div>
        </div>
        <div class="form-2">
            <div class="form-baris">
                <label for="kartu">Berkas Kartu PIP</label>
                <input type="file" id="kartu" name="kartu" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <div class="bantuan">PDF atau gambar, maksimal 15 MB. Berkas inilah yang diunduh
                    peserta didik/orang tua setelah memasukkan kata sandi.</div>
                <?php if (trim((string) ($edit['kartu'] ?? '')) !== ''): ?>
                    <div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <a class="tombol tombol-garis tombol-kecil" target="_blank" rel="noopener"
                           href="<?= e((string) $edit['kartu']) ?>"><?= ikon('lihat', 14) ?> Lihat kartu</a>
                        <span class="kecil mut"><?= e(pip_sel($edit['kartu_nama'] ?? '')) ?></span>
                        <label class="rasta kecil" style="gap:8px">
                            <input type="checkbox" name="kartu_hapus" value="1"> Hapus berkas kartu
                        </label>
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-baris">
                <label for="surat">Berkas Surat Kuasa</label>
                <input type="file" id="surat" name="surat" accept=".pdf,.jpg,.jpeg,.png,.webp">
                <div class="bantuan">Tampil sebagai tautan <b>Surat Kuasa</b> pada kolom Keterangan di halaman publik.</div>
                <?php if (trim((string) ($edit['surat'] ?? '')) !== ''): ?>
                    <div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <a class="tombol tombol-garis tombol-kecil" target="_blank" rel="noopener"
                           href="<?= e((string) $edit['surat']) ?>"><?= ikon('lihat', 14) ?> Lihat surat</a>
                        <span class="kecil mut"><?= e(pip_sel($edit['surat_nama'] ?? '')) ?></span>
                        <label class="rasta kecil" style="gap:8px">
                            <input type="checkbox" name="surat_hapus" value="1"> Hapus berkas surat
                        </label>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-baris">
            <label for="keterangan">Keterangan</label>
            <textarea id="keterangan" name="keterangan" style="min-height:110px"
                      placeholder="mis. Kartu sudah dicairkan Tahap 1, atau catatan lain untuk penerima."><?= e((string) ($edit['keterangan'] ?? '')) ?></textarea>
            <div class="bantuan">Tampil pada kolom Keterangan (bersama tautan Surat Kuasa bila berkasnya diunggah).</div>
        </div>
        <div class="form-baris">
            <label>Status</label>
            <label class="rasta" style="gap:9px;font-weight:500">
                <input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>>
                Tampilkan penerima ini pada halaman Layanan PIP
            </label>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Penerima</button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="<?= e(pip_admin_url($tahunId)) ?>">Batal</a><?php endif; ?>
        </div>
    </form>
</div>
<?php admin_bawah(); ?>
