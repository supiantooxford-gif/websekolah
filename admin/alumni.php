<?php
/**
 * Data Alumni — mengisi alumni LAMA yang belum pernah masuk sebagai siswa.
 *
 * Halaman publik "Data Alumni" (menu Kesiswaan) menggabungkan DUA sumber:
 *   1) **data kelulusan** (siswa berstatus lulus) — otomatis, bertambah dari proses Kenaikan & Kelulusan;
 *   2) data pada halaman ini (tabel `alumni`) — diisi satu per satu, diimpor Excel/CSV, atau diambil
 *      dari alamat web direktori alumni.
 *
 * Bila nama + tahun keluar sama dengan data kelulusan, pada halaman publik hanya satu yang ditampilkan
 * (yang berfoto diutamakan) sehingga tidak ada alumni ganda.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

// ---------------------------------------------------------------- aksi daftar
if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan('alumni', (int) ($_GET['id'] ?? 0), $aksi);
    redirect('alumni.php');
}
if ($aksi === 'toggle') {
    q('UPDATE alumni SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('alumni.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('alumni', (int) ($_POST['id'] ?? 0));
    flash('Data alumni dihapus.', 'ok');
    redirect('alumni.php');
}
if ($aksi === 'hapus_semua') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    if (strtoupper(trim((string) ($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
        flash('Penghapusan dibatalkan — ketik HAPUS pada kotak konfirmasi untuk melanjutkan.', 'err');
        redirect('alumni.php');
    }
    $n = alumni_manual_jumlah();
    q('DELETE FROM alumni');
    flash(angka($n) . ' data alumni (tabel ini) dihapus seluruhnya. Data kelulusan tidak terpengaruh.', 'ok');
    redirect('alumni.php');
}

// ---------------------------------------------------------------- simpan satu baris
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $lama = $id > 0 ? alumni_by_id($id) : null;
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nama === '') {
        flash('Nama alumni wajib diisi.', 'err');
        redirect('alumni.php' . ($id > 0 ? '?edit=' . $id : '') . '#formAlumni');
    }
    $foto = proses_gambar('foto', (string) ($lama['foto'] ?? ''), !empty($_POST['foto_hapus']), 'Foto alumni');
    $data = [
        'nis'           => potong(trim((string) ($_POST['nis'] ?? '')), 30, ''),
        'nisn'          => potong(trim((string) ($_POST['nisn'] ?? '')), 30, ''),
        'nama'          => potong($nama, NAMA_MAKS, ''),
        'jenis_kelamin' => alumni_gender((string) ($_POST['jenis_kelamin'] ?? 'L')),
        'tahun_masuk'   => potong(preg_match('#\d{4}#', (string) ($_POST['tahun_masuk'] ?? ''), $m1) ? $m1[0] : '', 10, ''),
        'tahun_keluar'  => potong(preg_match('#\d{4}#', (string) ($_POST['tahun_keluar'] ?? ''), $m2) ? $m2[0] : '', 10, ''),
        'keterangan'    => potong((string) ($_POST['keterangan'] ?? ''), 200, ''),
        'foto'          => $foto['url'],
        'aktif'         => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        $set = [];
        $val = [];
        foreach ($data as $k => $v) {
            $set[] = $k . ' = ?';
            $val[] = $v;
        }
        $val[] = $id;
        q('UPDATE alumni SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        flash('Data alumni “' . e($data['nama']) . '” disimpan. ' . $foto['pesan'], $foto['galat'] ? 'err' : 'ok');
    } else {
        $data['sumber'] = 'manual';
        $data['urutan'] = urutan_baru('alumni');
        $data['dibuat'] = date('Y-m-d H:i:s');
        $kol = array_keys($data);
        q('INSERT INTO alumni (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        flash('Data alumni “' . e($data['nama']) . '” ditambahkan. ' . $foto['pesan'], $foto['galat'] ? 'err' : 'ok');
    }
    redirect('alumni.php');
}

// ---------------------------------------------------------------- impor (Excel/CSV ditempel atau berkas)
$laporan = null;
if ($aksi === 'impor') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    if (kiriman_terlalu_besar()) {
        flash('Ukuran kiriman melebihi batas server (' . ini_get('post_max_size') . '). Impor dengan jumlah baris lebih sedikit.', 'err');
        redirect('alumni.php#import');
    }
    $baris = [];
    if (trim((string) ($_POST['data'] ?? '')) !== '') {
        $data = json_decode((string) $_POST['data'], true);
        if (is_array($data)) {
            $baris = $data;
        }
    }
    if (!$baris && trim((string) ($_POST['csv'] ?? '')) !== '') {
        $teks = str_replace(["\r\n", "\r"], "\n", (string) $_POST['csv']);
        foreach (explode("\n", $teks) as $b) {
            if (trim($b) === '') {
                continue;
            }
            $pemisah = substr_count($b, ';') >= substr_count($b, ',') ? ';' : ',';
            if (substr_count($b, "\t") > substr_count($b, $pemisah)) {
                $pemisah = "\t";
            }
            $baris[] = array_map('trim', str_getcsv($b, $pemisah));
        }
    }
    $baris = array_values(array_filter($baris, static fn ($r): bool => is_array($r) && count(array_filter($r, static fn ($c): bool => trim((string) $c) !== '')) > 0));

    if (count($baris) < 2) {
        flash('Data yang dibaca kurang dari dua baris (judul kolom + data).', 'err');
        redirect('alumni.php#import');
    }
    $peta = tebak_kolom(array_map(static fn ($c): string => (string) $c, $baris[0]), ALUMNI_KOLOM_PETA);
    if (!isset($peta['nama'])) {
        flash('Kolom “Nama” tidak ditemukan. Baris pertama harus berisi judul kolom '
            . '(mis. NIS, Nama, Jenis Kelamin, Tahun Masuk, Tahun Keluar, Keterangan).', 'err');
        redirect('alumni.php#import');
    }
    $kumpul = [];
    foreach (array_slice($baris, 1) as $r) {
        $nama = impor_nilai($r, $peta['nama'] ?? null);
        if ($nama === '') {
            continue;
        }
        $kumpul[] = [
            'nama'          => $nama,
            'nis'           => impor_nilai($r, $peta['nis'] ?? null),
            'nisn'          => impor_nilai($r, $peta['nisn'] ?? null),
            'jenis_kelamin' => alumni_gender(impor_nilai($r, $peta['jenis_kelamin'] ?? null)),
            'tahun_masuk'   => impor_nilai($r, $peta['tahun_masuk'] ?? null),
            'tahun_keluar'  => impor_nilai($r, $peta['tahun_keluar'] ?? null),
            'keterangan'    => impor_nilai($r, $peta['keterangan'] ?? null),
            'foto'          => '',
        ];
    }
    $laporan = alumni_simpan_web($kumpul);
    if ($laporan['ok']) {
        flash(angka($laporan['tambah']) . ' alumni baru ditambahkan, ' . angka($laporan['perbarui']) . ' diperbarui.', 'ok');
    } else {
        flash($laporan['pesan'], 'err');
    }
    redirect('alumni.php');
}

// ---------------------------------------------------------------- ambil dari alamat web
$pratinjauWeb = null;
if ($aksi === 'web_pratinjau') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $url = trim((string) ($_POST['url'] ?? ALUMNI_WEB_URL_BAWAAN));
    $hasil = alumni_ambil_web($url);
    if (!$hasil['ok']) {
        flash('Gagal mengambil data: ' . $hasil['pesan'], 'err');
        redirect('alumni.php#import');
    }
    $_SESSION['alumni_web'] = ['url' => $url, 'baris' => $hasil['baris']];
    $pratinjauWeb = ['url' => $url, 'baris' => $hasil['baris'], 'pesan' => $hasil['pesan']];
    flash('Berhasil mengambil ' . angka(count($hasil['baris'])) . ' baris dari alamat tersebut. Periksa pratinjau lalu tekan “Impor Sekarang”.', 'ok');
}
if ($aksi === 'web_impor') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $simpan = $_SESSION['alumni_web']['baris'] ?? [];
    if (!$simpan) {
        flash('Belum ada data yang diambil. Tekan “Ambil Data dari Alamat” terlebih dahulu.', 'err');
        redirect('alumni.php#import');
    }
    $laporan = alumni_simpan_web($simpan);
    if ($laporan['ok']) {
        unset($_SESSION['alumni_web']);
        flash('Impor selesai: ' . angka($laporan['tambah']) . ' alumni baru, ' . angka($laporan['perbarui']) . ' diperbarui.', 'ok');
    } else {
        flash($laporan['pesan'], 'err');
    }
    redirect('alumni.php');
}
if ($aksi === 'web_batal') {
    unset($_SESSION['alumni_web']);
    flash('Pratinjau dibatalkan.', 'info');
    redirect('alumni.php#import');
}
if ($pratinjauWeb === null && !empty($_SESSION['alumni_web']['baris'])) {
    $pratinjauWeb = ['url' => (string) ($_SESSION['alumni_web']['url'] ?? ''), 'baris' => $_SESSION['alumni_web']['baris'], 'pesan' => 'Data pratinjau yang belum diimpor.'];
}

// ---------------------------------------------------------------- tampilan
$cari = trim((string) ($_GET['cari'] ?? ''));
$tahunF = (int) ($_GET['tahun'] ?? 0);
$sumberF = (string) ($_GET['sumber'] ?? '');
$w = ['1 = 1'];
$p = [];
if ($cari !== '') {
    $w[] = '(nama LIKE ? OR nis LIKE ? OR nisn LIKE ?)';
    array_push($p, '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%');
}
if ($tahunF > 0) {
    $w[] = 'CAST(tahun_keluar AS INTEGER) = ?';
    $p[] = $tahunF;
}
if ($sumberF !== '') {
    $w[] = 'sumber = ?';
    $p[] = $sumberF;
}
$perHal = 25;
$halIni = halaman_ini();
$total = jumlah('SELECT COUNT(*) FROM alumni WHERE ' . implode(' AND ', $w), $p);
$daftar = all('SELECT * FROM alumni WHERE ' . implode(' AND ', $w)
    . ' ORDER BY urutan, CAST(tahun_keluar AS INTEGER) DESC, nama LIMIT ' . $perHal . ' OFFSET ' . (($halIni - 1) * $perHal), $p);
$edit = (int) ($_GET['edit'] ?? 0) > 0 ? alumni_by_id((int) $_GET['edit']) : null;
$tahunAda = [];
foreach (all("SELECT DISTINCT tahun_keluar FROM alumni WHERE tahun_keluar <> '' ORDER BY CAST(tahun_keluar AS INTEGER) DESC") as $r) {
    $tahunAda[] = (string) $r['tahun_keluar'];
}
$alumniGabungan = alumni_gabungan();
$jumlahKelulusan = jumlah("SELECT COUNT(*) FROM siswa WHERE status = 'lulus'");
$jumlahGabungan = count($alumniGabungan);

admin_atas('Data Alumni');
?>
<div class="grid g4">
    <?php
    kartu_stat('Data Kelulusan', angka($jumlahKelulusan), 'medal', 'siswa.php?status=lulus', 'dari Kenaikan & Kelulusan');
    kartu_stat('Diisi Manual / Impor', angka(alumni_manual_jumlah()), 'clipboard', 'alumni.php', 'tabel halaman ini');
    kartu_stat('Tampil di Website', angka($jumlahGabungan), 'mata', '../' . (menu_slug_jenis('alumni') !== '' ? 'halaman/' . menu_slug_jenis('alumni') : ''), 'data gabungan (tanpa ganda)');
    kartu_stat('Tahun Keluar', angka(count(alumni_tahun_gabungan())), 'kalender', 'alumni.php', implode(' · ', array_slice(alumni_tahun_gabungan(), 0, 4)));
    ?>
</div>

<div class="alert alert-info" style="margin-top:22px">
    Halaman ini untuk <strong>alumni lama</strong> yang belum pernah masuk sebagai siswa. Alumni dari
    <strong>data kelulusan</strong> otomatis ikut tampil di website — tidak perlu dimasukkan lagi di sini.
    Bila ada nama &amp; tahun keluar yang sama, pada website hanya <strong>satu</strong> yang ditampilkan
    (data yang berfoto diutamakan) sehingga tidak ada alumni ganda.
</div>

<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2>Daftar Alumni (Diisi Manual / Impor)</h2>
            <p class="mut kecil mb0"><?= angka($total) ?> data sesuai saringan dari <?= angka(alumni_manual_jumlah()) ?> data tersimpan.</p>
        </div>
        <div class="rasta" style="gap:8px;flex-wrap:wrap">
            <a class="tombol tombol-kecil" href="alumni.php?edit=0#formAlumni"><?= ikon('plus', 15) ?> Tambah Alumni</a>
            <a class="tombol tombol-garis tombol-kecil" href="alumni.php#import"><?= ikon('unggah', 15) ?> Impor / Ambil dari Web</a>
            <?php $slugAlumni = menu_slug_jenis('alumni'); ?>
            <?php if ($slugAlumni !== ''): ?>
                <a class="tombol tombol-garis tombol-kecil" href="../halaman/<?= e($slugAlumni) ?>" target="_blank" rel="noopener"><?= ikon('mata', 15) ?> Lihat di Website</a>
            <?php endif; ?>
            <?php /* v1.16.2 — permintaan pemilik: cetak data alumni (mengikuti saringan yang aktif). */ ?>
            <a class="tombol tombol-garis tombol-kecil" id="btnCetakAlumni"
               href="cetak_alumni.php?<?= e(http_build_query(['cari' => $cari, 'tahun' => $tahunF > 0 ? $tahunF : '', 'sumber' => $sumberF])) ?>"><?= ikon('print', 15) ?> Cetak Data Alumni</a>
        </div>
    </div>

    <form class="saringan" method="get" action="alumni.php">
        <div class="medan">
            <label for="saringCari">Cari nama / NIS / NISN</label>
            <input type="search" id="saringCari" name="cari" value="<?= e($cari) ?>">
        </div>
        <div class="medan">
            <label for="saringTahun">Tahun Keluar</label>
            <select id="saringTahun" name="tahun" data-auto>
                <option value="0">Semua tahun</option>
                <?php foreach ($tahunAda as $th): ?>
                    <option value="<?= (int) $th ?>" <?= $tahunF === (int) $th ? 'selected' : '' ?>><?= e($th) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="medan">
            <label for="saringSumber">Sumber</label>
            <select id="saringSumber" name="sumber" data-auto>
                <option value="">Semua sumber</option>
                <option value="manual" <?= $sumberF === 'manual' ? 'selected' : '' ?>>Diisi manual</option>
                <option value="web" <?= $sumberF === 'web' ? 'selected' : '' ?>>Ambil dari web</option>
            </select>
        </div>
        <button type="submit" class="tombol tombol-kecil"><?= ikon('search', 15) ?> Terapkan</button>
        <a class="tombol tombol-garis tombol-kecil" href="alumni.php">Reset</a>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead>
                <tr>
                    <th style="width:62px">Urut</th>
                    <th style="width:70px">Foto</th>
                    <th>Nama Alumni</th>
                    <th style="width:100px">NIS</th>
                    <th style="width:100px">Jenis Kelamin</th>
                    <th style="width:90px">Th. Masuk</th>
                    <th style="width:90px">Th. Keluar</th>
                    <th>Keterangan</th>
                    <th style="width:110px">Sumber</th>
                    <th>Status</th>
                    <th class="aksi-sel">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$daftar): ?>
                    <tr><td colspan="11" class="mut">
                        Belum ada data di tabel ini.
                        <?php if ($jumlahGabungan > 0): ?>
                            Halaman website tetap menampilkan <?= angka($jumlahGabungan) ?> alumni dari data kelulusan + tabel ini.
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($daftar as $r): ?>
                    <tr>
                        <td class="aksi-sel"><?= tombol_urut('alumni.php', (int) $r['id']) ?></td>
                        <td>
                            <?php if ((string) $r['foto'] !== ''): ?>
                                <img class="thumb-admin kotak-bulat" src="<?= e($r['foto']) ?>" alt="<?= e($r['nama']) ?>">
                            <?php else: ?>
                                <span class="kotak-kosong kotak-bulat thumb-admin"><?= e(strtoupper(substr(trim((string) $r['nama']), 0, 1))) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= e($r['nama']) ?></strong>
                            <?php if ((string) $r['nisn'] !== ''): ?><div class="kecil mut">NISN <?= e($r['nisn']) ?></div><?php endif; ?>
                        </td>
                        <td class="kecil"><?= e($r['nis'] ?: '-') ?></td>
                        <td class="kecil"><?= e(alumni_gender_label((string) $r['jenis_kelamin'])) ?></td>
                        <td class="kecil"><?= e($r['tahun_masuk'] ?: '-') ?></td>
                        <td class="kecil"><?= e($r['tahun_keluar'] ?: '-') ?></td>
                        <td class="kecil"><?= e($r['keterangan'] ?: '-') ?></td>
                        <td class="kecil mut"><?= e(alumni_sumber_label((string) $r['sumber'])) ?></td>
                        <td><?= ((int) $r['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('alumni.php?edit=' . (int) $r['id'] . '#formAlumni', 'edit', 'Ubah') ?>
                            <?= tombol_mini('alumni.php?aksi=toggle&id=' . (int) $r['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                            <?= form_hapus('hapus', (int) $r['id'], 'Hapus data alumni “' . $r['nama'] . '”?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi($total, $perHal, $halIni) ?>

    <h3 class="mt24" id="formAlumni"><?= $edit ? 'Ubah Data Alumni' : 'Tambah Data Alumni' ?></h3>
    <form method="post" action="alumni.php" enctype="multipart/form-data" id="formAlumniBaru">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-2">
            <div class="form-baris">
                <label for="nama">Nama Lengkap <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="120" value="<?= e($edit['nama'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="jenis_kelamin">Jenis Kelamin</label>
                <select id="jenis_kelamin" name="jenis_kelamin">
                    <option value="L" <?= ($edit['jenis_kelamin'] ?? 'L') === 'P' ? '' : 'selected' ?>>Laki-laki</option>
                    <option value="P" <?= ($edit['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>
        </div>
        <div class="form-3">
            <div class="form-baris">
                <label for="nis">NIS</label>
                <input type="text" id="nis" name="nis" maxlength="30" value="<?= e($edit['nis'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="nisn">NISN</label>
                <input type="text" id="nisn" name="nisn" maxlength="30" value="<?= e($edit['nisn'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="tahun_masuk">Tahun Masuk</label>
                <input type="text" id="tahun_masuk" name="tahun_masuk" maxlength="10" placeholder="2020" value="<?= e($edit['tahun_masuk'] ?? '') ?>">
            </div>
        </div>
        <div class="form-3">
            <div class="form-baris">
                <label for="tahun_keluar">Tahun Keluar</label>
                <input type="text" id="tahun_keluar" name="tahun_keluar" maxlength="10" placeholder="2023" value="<?= e($edit['tahun_keluar'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="keterangan">Keterangan (kondisi sekarang)</label>
                <input type="text" id="keterangan" name="keterangan" maxlength="200" placeholder="mis. Kuliah di Universitas Negeri Gorontalo / Bekerja di …" value="<?= e($edit['keterangan'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label class="rasta" style="gap:9px;font-weight:500;margin-top:26px">
                    <input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan di website
                </label>
            </div>
        </div>
        <?php blok_gambar('foto', (string) ($edit['foto'] ?? ''), 'Foto Alumni (opsional)', false, 'Boleh dibiarkan kosong — keterangan “belum ada foto” akan tampil sebagai huruf awal nama.'); ?>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambahkan Alumni' ?></button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="alumni.php">Batal</a><?php endif; ?>
        </div>
    </form>

    <?php if (alumni_manual_jumlah() > 0): ?>
        <?php form_konfirmasi_ketik(
            'hapus_semua',
            'Hapus Semua Data Alumni (tabel ini)',
            'Seluruh ' . angka(alumni_manual_jumlah()) . ' data pada tabel ini akan dihapus. Data alumni dari KELULUSAN tidak terpengaruh.'
        ); ?>
    <?php endif; ?>
</div>

<div class="kartu mt24" id="import">
    <div class="kartu-kepala">
        <div>
            <h2>Impor / Ambil Data Alumni</h2>
            <p class="mut kecil mb0">Dua cara: <strong>ambil dari alamat web</strong> direktori alumni, atau <strong>impor Excel/CSV</strong>.</p>
        </div>
    </div>

    <h3>1. Ambil dari Alamat Web</h3>
    <p class="kecil mut">Server mengambil daftar alumni dari alamat tersebut (termasuk foto bila ada), lalu Anda periksa pratinjaunya sebelum diimpor.</p>
    <form method="post" action="alumni.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="web_pratinjau">
        <div class="form-2">
            <div class="form-baris">
                <label for="url">Alamat direktori alumni</label>
                <input type="url" id="url" name="url" maxlength="300" value="<?= e($pratinjauWeb['url'] ?? ALUMNI_WEB_URL_BAWAAN) ?>">
                <div class="bantuan">Alamat bawaan: <code><?= e(ALUMNI_WEB_URL_BAWAAN) ?></code></div>
            </div>
            <div class="form-baris">
                <label>&nbsp;</label>
                <button type="submit" class="tombol"><?= ikon('unggah', 16) ?> Ambil Data dari Alamat</button>
            </div>
        </div>
    </form>

    <?php if ($pratinjauWeb): ?>
        <div class="alert alert-info">
            <strong>Pratinjau:</strong> <?= angka(count($pratinjauWeb['baris'])) ?> baris dari
            <code><?= e($pratinjauWeb['url']) ?></code>.
            <?= e($pratinjauWeb['pesan']) ?>
            Baris yang namanya sudah ada (nama + tahun keluar sama) akan <strong>diperbarui</strong>, sisanya ditambahkan.
        </div>
        <div class="pratinjau-impor">
            <table class="tabel">
                <thead><tr><th>No</th><th>Nama</th><th>NIS</th><th>L/P</th><th>Th. Masuk</th><th>Th. Keluar</th><th>Foto</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($pratinjauWeb['baris'], 0, 40) as $i => $b): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e((string) $b['nama']) ?></td>
                            <td class="kecil"><?= e((string) ($b['nis'] ?? '')) ?></td>
                            <td class="kecil"><?= e(alumni_gender_label((string) ($b['jenis_kelamin'] ?? 'L'))) ?></td>
                            <td class="kecil"><?= e((string) ($b['tahun_masuk'] ?? '')) ?></td>
                            <td class="kecil"><?= e((string) ($b['tahun_keluar'] ?? '')) ?></td>
                            <td class="kecil"><?= trim((string) ($b['foto'] ?? '')) !== '' ? 'ada' : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($pratinjauWeb['baris']) > 40): ?>
            <p class="kecil mut">… dan <?= angka(count($pratinjauWeb['baris']) - 40) ?> baris lainnya (tidak ditampilkan pada pratinjau).</p>
        <?php endif; ?>
        <div class="aksi-form">
            <form method="post" action="alumni.php" data-konfirmasi="Impor <?= angka(count($pratinjauWeb['baris'])) ?> baris ke daftar alumni?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="web_impor">
                <button type="submit" class="tombol tombol-emas"><?= ikon('simpan', 17) ?> Impor Sekarang (<?= angka(count($pratinjauWeb['baris'])) ?> baris)</button>
            </form>
            <form method="post" action="alumni.php">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="web_batal">
                <button type="submit" class="tombol tombol-garis">Batalkan Pratinjau</button>
            </form>
        </div>
    <?php endif; ?>

    <h3 class="mt24">2. Impor Excel/CSV</h3>
    <p class="kecil mut">Berkas dibaca di peramban Anda. Kolom yang dikenali (urutan bebas):
        <em>NIS</em>, <em>Nama</em>, <em>NISN</em>, <em>Jenis Kelamin</em>, <em>Tahun Masuk</em>, <em>Tahun Keluar</em>, <em>Keterangan</em>.</p>
    <form method="post" action="alumni.php" id="formImporAlumni">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="impor">
        <textarea name="data" id="dataImporAlumni" hidden></textarea>
        <div class="form-2">
            <div class="form-baris">
                <label for="berkasAlumni">Pilih Berkas Excel/CSV</label>
                <input type="file" id="berkasAlumni" accept=".xlsx,.xls,.csv,.txt">
                <div class="bantuan">Baris pertama harus berisi judul kolom.</div>
            </div>
            <div class="form-baris">
                <label>&nbsp;</label>
                <button type="submit" class="tombol tombol-garis"><?= ikon('unggah', 16) ?> Impor Berkas</button>
            </div>
        </div>
        <div class="pratinjau-impor" id="pratinjauAlumni"><p class="mut kecil" style="padding:12px">Belum ada berkas dibaca.</p></div>
        <details class="mt16" style="margin-top:14px">
            <summary class="kecil mut">Cara lain: tempel data CSV</summary>
            <div class="form-baris mt12">
                <label for="csvAlumni">Tempel data (pemisah koma, titik koma, atau tab)</label>
                <textarea id="csvAlumni" name="csv" style="min-height:140px" placeholder="NIS;Nama;Jenis Kelamin;Tahun Masuk;Tahun Keluar&#10;200004;Ahmad Fais Sirojudin;Laki-laki;2020;2023"></textarea>
            </div>
            <button type="submit" class="tombol tombol-garis tombol-kecil"><?= ikon('unggah', 15) ?> Impor Tempelan</button>
        </details>
    </form>
</div>

<script src="../assets/vendor/xlsx.full.min.js?v=<?= APP_VERSION ?>"></script>
<script>
(function () {
  var input = document.getElementById('berkasAlumni');
  var keluar = document.getElementById('pratinjauAlumni');
  var data = document.getElementById('dataImporAlumni');
  if (!input || typeof window.Websk === 'undefined') { return; }
  input.addEventListener('change', function () {
    window.Websk.bacaBerkas(input, {
      sukses: function (baris, nama) {
        data.value = JSON.stringify(baris);
        var kepala = baris[0] || [];
        var html = '<table class="tabel"><thead><tr>';
        kepala.forEach(function (h) { html += '<th>' + String(h).replace(/[<>&]/g, '') + '</th>'; });
        html += '</tr></thead><tbody>';
        baris.slice(1, 16).forEach(function (r) {
          html += '<tr>';
          kepala.forEach(function (_, i) { html += '<td>' + String(r[i] === undefined ? '' : r[i]).replace(/[<>&]/g, '') + '</td>'; });
          html += '</tr>';
        });
        html += '</tbody></table><p class="kecil mut" style="padding:8px 12px">' + (baris.length - 1) + ' baris dibaca dari ' + nama + '.</p>';
        keluar.innerHTML = html;
      }
    });
  });
})();
</script>
<?php admin_bawah(); ?>
