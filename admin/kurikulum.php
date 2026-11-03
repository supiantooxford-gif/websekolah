<?php
/**
 * KURIKULUM — panel isian Administrator **dan akun Editor (Yelfis)** — v1.18.0, skema v34.
 *
 * Permintaan pemilik:
 *   "Pada bagian Menu Kurikulum buatkan isiannya di halaman admin dan operator (yelfis). Satu menu
 *    untuk beberapa isian:
 *     - Kaldik Operasional buat inputan gambar untuk menampilkan Kalender Pendidikan
 *     - Jadwal mengajar buat inputan hari, waktu, jam ke, Mata Pelajaran + gurunya di setiap kelas.
 *       Contoh hasilnya yang ditampilkan di web seperti yang saya upload (Buat Landscape).
 *       Di tampilkan berdasarkan filter hari
 *     - Dokumen kurikulum buat inputan untuk mengupload dokumen pdf/word/excel/gambar. Hasilnya
 *       ditampilkan dalam bentuk kartu dokumen yang bisa di download oleh pengunjung website"
 *
 * SATU halaman dengan TIGA tab (Kaldik · Jadwal Mengajar · Dokumen Kurikulum). Halaman publiknya
 * tiga sub menu Kurikulum berjenis `kaldik`, `jadwal_mengajar`, `dokumen_kurikulum` (lihat
 * `inc/kurikulum.php`).
 *
 * Penjaga: **`require_kelola_kurikulum()`** — Administrator dan akun **Editor** ("operator
 * (yelfis)"); akun peran lain (mis. "Editor Berita") tetap **403**.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/kurikulum.php';

require_kelola_kurikulum();

/** Tab yang sah pada halaman ini. */
const KUR_TAB = ['kaldik' => 'Kaldik Operasional', 'jadwal' => 'Jadwal Mengajar', 'dokumen' => 'Dokumen Kurikulum'];

$tab = (string) ($_POST['tab'] ?? $_GET['tab'] ?? 'kaldik');
$tab = array_key_exists($tab, KUR_TAB) ? $tab : 'kaldik';
$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');
$hari = (string) ($_GET['hari'] ?? '');
$hari = kurikulum_hari_sah($hari) ? $hari : KURIKULUM_HARI[0];

/** Berkas yang dikirim melebihi `post_max_size` (PHP membuang seluruh $_POST/$_FILES). */
$terlaluBesar = kiriman_terlalu_besar();

/**
 * Ambil nilai medan formulir per KELAS (`mapel_kelas[<kelas>]` / `guru_kelas[<kelas>]`).
 *
 * Kunci formulir ditulis `rawurlencode(nama kelas)` supaya nama kelas yang memuat titik atau tanda
 * kurung tidak diubah PHP (PHP mengganti '.' menjadi '_' pada kunci array formulir). Karena itu
 * nilai dicari pada kunci TERKODE lebih dulu, baru kunci mentah (untuk formulir gaya lama).
 */
function kur_medan_kelas(array $sumber, string $kelas): string
{
    $kunci = rawurlencode($kelas);
    if (array_key_exists($kunci, $sumber)) {
        return (string) $sumber[$kunci];
    }
    return array_key_exists($kelas, $sumber) ? (string) $sumber[$kelas] : '';
}

// =====================================================================================
// AKSI — urutkan (naik/turun) & aktif/nonaktif memakai tautan, hapus/simpan memakai POST
// =====================================================================================
if ($aksi === 'naik' || $aksi === 'turun') {
    $id = (int) ($_GET['id'] ?? 0);
    $tabel = (string) ($_GET['tabel'] ?? 'kurikulum_kaldik');
    if ($tabel === 'kurikulum_jadwal') {
        $b = one('SELECT hari FROM kurikulum_jadwal WHERE id = ?', [$id]);
        geser_urutan('kurikulum_jadwal', $id, $aksi, $b ? ['hari' => (string) $b['hari']] : []);
        redirect('kurikulum.php?tab=jadwal&hari=' . rawurlencode((string) ($b['hari'] ?? $hari)));
    }
    if ($tabel === 'kurikulum_dokumen') {
        geser_urutan('kurikulum_dokumen', $id, $aksi);
        redirect('kurikulum.php?tab=dokumen');
    }
    geser_urutan('kurikulum_kaldik', $id, $aksi);
    redirect('kurikulum.php?tab=kaldik');
}

if ($aksi === 'toggle') {
    $id = (int) ($_GET['id'] ?? 0);
    $tabel = (string) ($_GET['tabel'] ?? 'kurikulum_kaldik');
    if (in_array($tabel, ['kurikulum_kaldik', 'kurikulum_dokumen'], true)) {
        q('UPDATE ' . $tabel . ' SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [$id]);
        flash('Status tampil data diperbarui.', 'ok');
    }
    redirect('kurikulum.php?tab=' . ($tabel === 'kurikulum_dokumen' ? 'dokumen' : 'kaldik'));
}

if ($aksi === 'kaldik_hapus' || $aksi === 'jadwal_hapus' || $aksi === 'dokumen_hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($aksi === 'kaldik_hapus') {
        hapus_baris('kurikulum_kaldik', $id);
        flash('Gambar kalender pendidikan dihapus.', 'ok');
        redirect('kurikulum.php?tab=kaldik');
    }
    if ($aksi === 'dokumen_hapus') {
        hapus_baris('kurikulum_dokumen', $id);
        flash('Dokumen kurikulum dihapus.', 'ok');
        redirect('kurikulum.php?tab=dokumen');
    }
    hapus_baris('kurikulum_jadwal', $id);
    flash('Baris jadwal dihapus.', 'ok');
    redirect('kurikulum.php?tab=jadwal&hari=' . rawurlencode((string) ($_POST['hari'] ?? $hari)));
}

// ---------------- KALDIK: simpan ----------------
if ($aksi === 'kaldik_simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $lama = $id > 0 ? kurikulum_kaldik_by_id($id) : null;
    $judul = potong((string) ($_POST['judul'] ?? ''), KURIKULUM_JUDUL_MAKS, '');
    $keterangan = potong_baris((string) ($_POST['keterangan'] ?? ''), KURIKULUM_KET_MAKS);
    $gambar = $lama !== null ? (string) ($lama['gambar'] ?? '') : '';
    if (!empty($_POST['hapus_gambar'])) {
        $gambar = '';
    }
    if ($terlaluBesar) {
        flash('Kiriman melebihi batas ukuran server. Perkecil gambar lalu ulangi (maks '
            . ukuran_teks(KURIKULUM_MAKS_BYTE) . ').', 'err');
        redirect('kurikulum.php?tab=kaldik' . ($id > 0 ? '&edit=' . $id : '') . '#formKaldik');
    }
    $up = unggah_gambar('gambar', 'Gambar kalender');
    if (!empty($up['ok'])) {
        $gambar = (string) $up['url'];
    } elseif (empty($up['kosong'])) {
        flash((string) ($up['pesan'] ?? 'Gambar gagal diunggah.'), 'err');
        redirect('kurikulum.php?tab=kaldik' . ($id > 0 ? '&edit=' . $id : '') . '#formKaldik');
    }
    if ($gambar === '') {
        flash('Gambar kalender pendidikan wajib diunggah.', 'err');
        redirect('kurikulum.php?tab=kaldik' . ($id > 0 ? '&edit=' . $id : '') . '#formKaldik');
    }
    $data = [
        'judul'      => $judul,
        'keterangan' => $keterangan,
        'gambar'     => $gambar,
        'aktif'      => empty($_POST['aktif']) ? 0 : 1,
        'diubah'     => date('Y-m-d H:i:s'),
    ];
    if ($id > 0) {
        $set = implode(', ', array_map(static fn (string $k): string => $k . ' = ?', array_keys($data)));
        q('UPDATE kurikulum_kaldik SET ' . $set . ' WHERE id = ?', array_merge(array_values($data), [$id]));
        flash('Kalender pendidikan disimpan.', 'ok');
    } else {
        $data['urutan'] = urutan_baru('kurikulum_kaldik');
        $data['dibuat'] = date('Y-m-d H:i:s');
        $kol = array_keys($data);
        q('INSERT INTO kurikulum_kaldik (' . implode(', ', $kol) . ') VALUES ('
            . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        flash('Kalender pendidikan ditambahkan.', 'ok');
    }
    redirect('kurikulum.php?tab=kaldik');
}

// ---------------- JADWAL: simpan ----------------
if ($aksi === 'jadwal_simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $hariSimpan = trim((string) ($_POST['hari'] ?? ''));
    $waktu = potong((string) ($_POST['waktu'] ?? ''), KURIKULUM_WAKTU_MAKS, '');
    $jam = trim((string) ($_POST['jam_ke'] ?? ''));
    // `kelas` bisa berupa ARRAY (mode banyak kelas) — jangan di-cast langsung: `(string) array`
    // memicu peringatan "Array to string conversion" SEBELUM header sehingga `redirect()` gagal
    // dan halaman menjawab 200 (tombol simpan seolah tidak bekerja). Nilai array ditangani di blok
    // MODE BANYAK KELAS di bawah; di sini hanya jalur satu kelas (gaya lama).
    $kelasMentah = $_POST['kelas'] ?? '';
    $kelas = is_array($kelasMentah) ? '' : potong((string) $kelasMentah, KURIKULUM_KELAS_MAKS, '');
    $mapel = potong((string) ($_POST['mapel'] ?? ''), KURIKULUM_MAPEL_MAKS, '');
    $guru = potong((string) ($_POST['guru'] ?? ''), KURIKULUM_GURU_MAKS, '');
    $jamKe = $jam === '' ? 0 : max(0, min((int) $jam, (int) KURIKULUM_JAM_MAKS));
    $kembali = 'kurikulum.php?tab=jadwal&hari=' . rawurlencode(kurikulum_hari_sah($hariSimpan) ? $hariSimpan : $hari)
        . ($id > 0 ? '&edit=' . $id : '') . '#formJadwal';

    if (!kurikulum_hari_sah($hariSimpan)) {
        flash('Hari wajib dipilih dari daftar.', 'err');
        redirect($kembali);
    }
    if ($waktu === '') {
        flash('Waktu wajib diisi, mis. 07.00-07.45.', 'err');
        redirect($kembali);
    }

    /* ---------------------------------------------------------------------------------
     * MODE BANYAK KELAS (v1.19.0 — permintaan pemilik):
     *   "Pada bagian kelas buat agar bisa memilih 3 kelas dengan centang. Begitu juga dengan
     *    mata pelajarannya bisa menginputkan 1 mata pelajaran untuk setiap kelas sekaligus.
     *    Dan untuk gurunya pun menyesuaikan. Tujuannya untuk mempercepat penginputan jadwal"
     *
     * Formulir mengirim `kelas[]` (centang, nilai = NAMA kelas) + `mapel_kelas[<kelas>]` +
     * `guru_kelas[<kelas>]`. Setiap kelas yang dicentang menjadi SATU baris jadwal (waktu & jam ke
     * sama), sehingga 3 kelas cukup diisi sekali simpan.
     *
     * Kunci array mapel/guru memakai `rawurlencode(nama kelas)` supaya nama yang memuat titik/tanda
     * kurung tidak diubah PHP (PHP mengganti '.' menjadi '_' pada kunci array formulir).
     * Bila TIDAK ADA kelas yang dicentang, formulir jatuh ke jalur lama: satu baris KEGIATAN
     * (kelas kosong) memakai medan `mapel` — dipakai untuk Sholat Dhuha/Istirahat/Sholat Dhuhur.
     * --------------------------------------------------------------------------------- */
    $pilihKelas = $_POST['kelas'] ?? null;
    if ($id === 0 && is_array($pilihKelas)) {
        $mapelKelas = is_array($_POST['mapel_kelas'] ?? null) ? (array) $_POST['mapel_kelas'] : [];
        $guruKelas = is_array($_POST['guru_kelas'] ?? null) ? (array) $_POST['guru_kelas'] : [];
        $kelasSah = kurikulum_jadwal_kelas_kolom('');
        $barisBaru = [];      // nama kelas => [mapel, guru]
        $mapelKosong = [];
        foreach ($pilihKelas as $nm) {
            $nm = trim((string) $nm);
            if ($nm === '' || !in_array($nm, $kelasSah, true) || isset($barisBaru[$nm])) {
                continue;   // kelas asing / ganda diabaikan
            }
            $mp = potong(kur_medan_kelas($mapelKelas, $nm), KURIKULUM_MAPEL_MAKS, '');
            $gr = potong(kur_medan_kelas($guruKelas, $nm), KURIKULUM_GURU_MAKS, '');
            if ($mp === '') {
                $mapelKosong[] = $nm;
                continue;
            }
            $barisBaru[$nm] = [$mp, $gr];
        }
        if ($mapelKosong) {
            flash('Mata pelajaran untuk ' . implode(', ', $mapelKosong)
                . ' belum diisi. Isi mata pelajarannya atau lepaskan centangnya.', 'err');
            redirect($kembali);
        }
        if ($barisBaru) {
            $kini = date('Y-m-d H:i:s');
            $urut = urutan_baru('kurikulum_jadwal', ['hari' => $hariSimpan]);
            $st = db()->prepare('INSERT INTO kurikulum_jadwal
                (hari, waktu, jam_ke, kelas, mapel, guru, urutan, dibuat, diubah)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            db()->exec('BEGIN IMMEDIATE');
            try {
                foreach ($barisBaru as $nm => [$mp, $gr]) {
                    $st->execute([$hariSimpan, $waktu, $jamKe, $nm, $mp, $gr, $urut, $kini, $kini]);
                    $urut += 10;
                }
                db()->exec('COMMIT');
            } catch (Throwable $e) {
                db()->exec('ROLLBACK');
                flash('Jadwal gagal disimpan: ' . $e->getMessage(), 'err');
                redirect($kembali);
            }
            flash(count($barisBaru) . ' baris jadwal ditambahkan untuk hari ' . $hariSimpan . ' ('
                . implode(', ', array_keys($barisBaru)) . ').', 'ok');
            redirect('kurikulum.php?tab=jadwal&hari=' . rawurlencode($hariSimpan));
        }
        // Tidak ada kelas sah yang dicentang → lanjut ke jalur baris kegiatan di bawah.
    }
    if ($mapel === '') {
        flash('Mata pelajaran wajib diisi' . ($kelas === '' ? ' (untuk baris kegiatan, isi nama kegiatannya).' : '.'), 'err');
        redirect($kembali);
    }
    if ($kelas === '') {
        $jamKe = 0;   // baris kegiatan: menggabung seluruh kolom kelas
    }
    $data = [
        'hari'   => $hariSimpan,
        'waktu'  => $waktu,
        'jam_ke' => $jamKe,
        'kelas'  => $kelas,
        'mapel'  => $mapel,
        'guru'   => $guru,
        'diubah' => date('Y-m-d H:i:s'),
    ];
    if ($id > 0) {
        $set = implode(', ', array_map(static fn (string $k): string => $k . ' = ?', array_keys($data)));
        q('UPDATE kurikulum_jadwal SET ' . $set . ' WHERE id = ?', array_merge(array_values($data), [$id]));
        flash('Baris jadwal disimpan.', 'ok');
    } else {
        $data['urutan'] = urutan_baru('kurikulum_jadwal', ['hari' => $hariSimpan]);
        $data['dibuat'] = date('Y-m-d H:i:s');
        $kol = array_keys($data);
        q('INSERT INTO kurikulum_jadwal (' . implode(', ', $kol) . ') VALUES ('
            . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        flash('Baris jadwal ditambahkan.', 'ok');
    }
    redirect('kurikulum.php?tab=jadwal&hari=' . rawurlencode($hariSimpan));
}

// ---------------- DOKUMEN: simpan ----------------
if ($aksi === 'dokumen_simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $lama = $id > 0 ? kurikulum_dokumen_by_id($id) : null;
    $judul = potong((string) ($_POST['judul'] ?? ''), KURIKULUM_JUDUL_MAKS, '');
    $keterangan = potong_baris((string) ($_POST['keterangan'] ?? ''), KURIKULUM_KET_MAKS);
    $kembali = 'kurikulum.php?tab=dokumen' . ($id > 0 ? '&edit=' . $id : '') . '#formDokumen';
    if ($judul === '') {
        flash('Judul dokumen wajib diisi.', 'err');
        redirect($kembali);
    }
    if ($terlaluBesar) {
        flash('Kiriman melebihi batas ukuran server. Perkecil berkas lalu ulangi (maks '
            . ukuran_teks(KURIKULUM_MAKS_BYTE) . ').', 'err');
        redirect($kembali);
    }
    $berkas = [
        'url'   => $lama !== null ? (string) ($lama['url'] ?? '') : '',
        'nama'  => $lama !== null ? (string) ($lama['nama_berkas'] ?? '') : '',
        'jenis' => $lama !== null ? (string) ($lama['jenis'] ?? '') : '',
        'ukuran' => $lama !== null ? (int) ($lama['ukuran'] ?? 0) : 0,
    ];
    $up = unggah_berkas('berkas', 'Berkas dokumen', KURIKULUM_EKSTENSI, KURIKULUM_MAKS_BYTE);
    if (!empty($up['ok'])) {
        $namaAsli = (string) ($_FILES['berkas']['name'] ?? '');
        $berkas = [
            'url'    => (string) $up['url'],
            'nama'   => $namaAsli,
            'jenis'  => strtolower((string) pathinfo($namaAsli, PATHINFO_EXTENSION)),
            'ukuran' => (int) ($_FILES['berkas']['size'] ?? 0),
        ];
    } elseif (empty($up['kosong'])) {
        flash((string) ($up['pesan'] ?? 'Berkas gagal diunggah.'), 'err');
        redirect($kembali);
    }
    if ($berkas['url'] === '') {
        flash('Berkas dokumen wajib diunggah (PDF, Word/DOCX, Excel/XLSX, PowerPoint/PPTX, CSV, TXT, ZIP, atau gambar).', 'err');
        redirect($kembali);
    }
    $data = [
        'judul'       => $judul,
        'keterangan'  => $keterangan,
        'url'         => $berkas['url'],
        'nama_berkas' => $berkas['nama'],
        'jenis'       => $berkas['jenis'],
        'ukuran'      => $berkas['ukuran'],
        'aktif'       => empty($_POST['aktif']) ? 0 : 1,
        'diubah'      => date('Y-m-d H:i:s'),
    ];
    if ($id > 0) {
        $set = implode(', ', array_map(static fn (string $k): string => $k . ' = ?', array_keys($data)));
        q('UPDATE kurikulum_dokumen SET ' . $set . ' WHERE id = ?', array_merge(array_values($data), [$id]));
        flash('Dokumen kurikulum disimpan.', 'ok');
    } else {
        $data['urutan'] = urutan_baru('kurikulum_dokumen');
        $data['dibuat'] = date('Y-m-d H:i:s');
        $kol = array_keys($data);
        q('INSERT INTO kurikulum_dokumen (' . implode(', ', $kol) . ') VALUES ('
            . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        flash('Dokumen kurikulum ditambahkan.', 'ok');
    }
    redirect('kurikulum.php?tab=dokumen');
}

// =====================================================================================
// DATA UNTUK TAMPILAN
// =====================================================================================
$editId = (int) ($_GET['edit'] ?? 0);
$editKaldik = $tab === 'kaldik' && $editId > 0 ? kurikulum_kaldik_by_id($editId) : null;
$editJadwal = $tab === 'jadwal' && $editId > 0 ? one('SELECT * FROM kurikulum_jadwal WHERE id = ?', [$editId]) : null;
$editDokumen = $tab === 'dokumen' && $editId > 0 ? kurikulum_dokumen_by_id($editId) : null;

$kaldik = kurikulum_kaldik_list();
$dokumen = kurikulum_dokumen_list();
$hariJumlah = kurikulum_jadwal_hari_jumlah();
$jadwal = kurikulum_jadwal_list($hari);
$kelasList = kurikulum_jadwal_kelas_kolom('');

/** Tautan tab (mempertahankan hari yang sedang dilihat). */
function kur_tab_tautan(string $tab, string $hari = ''): string
{
    return 'kurikulum.php?tab=' . rawurlencode($tab) . ($tab === 'jadwal' && $hari !== '' ? '&hari=' . rawurlencode($hari) : '');
}

admin_atas('Kurikulum');
?>
<div class="alert alert-info">
    <strong>Kurikulum</strong> — satu menu untuk tiga isian: <strong>Kaldik Operasional</strong>
    (gambar kalender pendidikan), <strong>Jadwal Mengajar</strong> (hari · waktu · jam ke · kelas ·
    mata pelajaran + guru), dan <strong>Dokumen Kurikulum</strong> (berkas yang dapat diunduh
    pengunjung). Isinya langsung tampil pada sub menu Kurikulum di website.
</div>

<div class="tab-prestasi" role="tablist">
    <?php foreach (KUR_TAB as $kunci => $label): ?>
        <a class="tab-item<?= $tab === $kunci ? ' aktif' : '' ?>" role="tab"
           aria-selected="<?= $tab === $kunci ? 'true' : 'false' ?>"
           href="<?= e(kur_tab_tautan($kunci, $hari)) ?>">
            <?= ikon($kunci === 'kaldik' ? 'kalender' : ($kunci === 'jadwal' ? 'jam' : 'book'), 17) ?>
            <?= e($label) ?>
            <span class="tab-jumlah"><?= $kunci === 'kaldik' ? count($kaldik) : ($kunci === 'jadwal' ? array_sum($hariJumlah) : count($dokumen)) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'kaldik'): ?>
    <?php // ============================ TAB KALDIK ============================ ?>
    <div class="grid g1">
        <div class="kartu">
            <div class="kartu-kepala">
                <div>
                    <h2>Gambar Kalender Pendidikan</h2>
                    <p class="mut kecil mb0">Unggah gambar kalender pendidikan operasional (JPG, PNG, GIF,
                        WEBP, atau SVG — maks <?= e(ukuran_teks(KURIKULUM_MAKS_BYTE)) ?>). Boleh lebih dari satu
                        gambar, mis. per semester.</p>
                </div>
                <a class="tombol tombol-kecil" target="_blank" rel="noopener"
                   href="../<?= e(halaman_url('kaldik-operasional')) ?>"><?= ikon('mata', 15) ?> Lihat Halaman</a>
            </div>
            <?php if (!$kaldik): ?>
                <p class="mut">Belum ada gambar kalender pendidikan. Gunakan formulir di samping untuk menambahkan.</p>
            <?php else: ?>
                <div class="tabel-wrap">
                    <table class="tabel">
                        <thead>
                            <tr><th style="width:52px">Urut</th><th style="width:120px">Gambar</th><th>Judul</th>
                                <th style="width:110px">Status</th><th class="aksi-sel">Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kaldik as $k): ?>
                                <tr>
                                    <td class="aksi-sel"><?= tombol_urut('kurikulum.php', (int) $k['id'], ['tab' => 'kaldik', 'tabel' => 'kurikulum_kaldik']) ?></td>
                                    <td>
                                        <?php if (trim((string) $k['gambar']) !== ''): ?>
                                            <a href="<?= e((string) $k['gambar']) ?>" target="_blank" rel="noopener">
                                                <img class="kal-admin-mini" src="<?= e((string) $k['gambar']) ?>" alt="Pratinjau kalender" loading="lazy">
                                            </a>
                                        <?php else: ?>
                                            <span class="mut kecil">Tanpa gambar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= e($k['judul'] !== '' ? $k['judul'] : '(tanpa judul)') ?></strong>
                                        <?php if (trim((string) $k['keterangan']) !== ''): ?>
                                            <div class="kecil mut"><?= e(potong((string) $k['keterangan'], 120)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int) $k['aktif'] === 1
                                        ? '<span class="pil pil-terima">Tampil</span>'
                                        : '<span class="pil pil-baru">Disembunyikan</span>' ?></td>
                                    <td class="aksi-sel">
                                        <?= tombol_mini('kurikulum.php?tab=kaldik&edit=' . (int) $k['id'] . '#formKaldik', 'edit', 'Ubah kalender') ?>
                                        <?= tombol_mini('kurikulum.php?aksi=toggle&tabel=kurikulum_kaldik&id=' . (int) $k['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                                        <?= form_hapus('kaldik_hapus', (int) $k['id'], 'Hapus gambar kalender ini?') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="kartu" id="formKaldik">
            <h2><?= $editKaldik ? 'Ubah Gambar Kalender' : 'Tambah Gambar Kalender' ?></h2>
            <form method="post" action="kurikulum.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="kaldik_simpan">
                <input type="hidden" name="tab" value="kaldik">
                <input type="hidden" name="id" value="<?= (int) ($editKaldik['id'] ?? 0) ?>">
                <div class="form-baris">
                    <label for="kaldik_judul">Judul</label>
                    <input type="text" id="kaldik_judul" name="judul" maxlength="<?= (int) KURIKULUM_JUDUL_MAKS ?>"
                           placeholder="mis. Kalender Pendidikan Semester Ganjil 2026/2027"
                           value="<?= e($editKaldik['judul'] ?? '') ?>">
                    <div class="bantuan">Boleh dikosongkan — akan tampil sebagai "Kalender Pendidikan".</div>
                </div>
                <div class="form-baris">
                    <label for="kaldik_keterangan">Keterangan</label>
                    <textarea id="kaldik_keterangan" name="keterangan" rows="3"
                              maxlength="<?= (int) KURIKULUM_KET_MAKS ?>"><?= e($editKaldik['keterangan'] ?? '') ?></textarea>
                </div>
                <div class="form-baris">
                    <label for="gambar_kaldik">Gambar Kalender <?= $editKaldik ? '' : '<span class="wajib">*</span>' ?></label>
                    <input type="file" id="gambar_kaldik" name="gambar" accept="image/*"
                           data-pratinjau="#pratinjau_kaldik">
                    <div class="bantuan">JPG, PNG, GIF, WEBP, atau SVG — maks <?= e(ukuran_teks(KURIKULUM_MAKS_BYTE)) ?>.
                        <?= $editKaldik ? 'Kosongkan bila gambar tidak diganti.' : '' ?></div>
                    <div class="pratinjau-gambar" id="pratinjau_kaldik">
                        <?php if ($editKaldik && trim((string) $editKaldik['gambar']) !== ''): ?>
                            <img src="<?= e((string) $editKaldik['gambar']) ?>" alt="Gambar sekarang" loading="lazy">
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($editKaldik && trim((string) $editKaldik['gambar']) !== ''): ?>
                    <label class="rasta" style="gap:9px;font-weight:500">
                        <input type="checkbox" name="hapus_gambar" value="1"> Hapus gambar yang sekarang
                    </label>
                <?php endif; ?>
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="aktif" value="1" <?= !$editKaldik || (int) $editKaldik['aktif'] === 1 ? 'checked' : '' ?>>
                    Tampilkan di website
                </label>
                <div class="aksi-form">
                    <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan</button>
                    <?php if ($editKaldik): ?>
                        <a class="tombol tombol-garis" href="kurikulum.php?tab=kaldik">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'jadwal'): ?>
    <?php // ============================ TAB JADWAL ============================ ?>
    <div class="kartu mb24">
        <div class="kartu-kepala">
            <div>
                <h2>Jadwal Mengajar</h2>
                <p class="mut kecil mb0">Satu baris = satu (hari · waktu · jam ke · kelas). Pada formulir
                    <strong>Tambah Baris Jadwal</strong> Anda dapat <strong>mencentang beberapa kelas sekaligus</strong>
                    (mis. 3 kelas) dan mengisi satu mata pelajaran + guru untuk tiap kelas — semuanya tersimpan
                    dalam sekali simpan. Untuk baris <strong>kegiatan</strong> (Sholat Dhuha, Istirahat,
                    Sholat Dhuhur), jangan centang kelas dan cukup isi kolom <strong>Nama Kegiatan</strong>:
                    baris itu tampil menggabung seluruh kolom kelas, seperti contoh.</p>
            </div>
            <a class="tombol tombol-kecil tombol-garis" target="_blank" rel="noopener"
               href="../<?= e(halaman_url('jadwal-mengajar')) ?>?hari=<?= e(rawurlencode($hari)) ?>">
                <?= ikon('mata', 15) ?> Lihat Halaman
            </a>
        </div>
        <div class="tab-prestasi" style="margin-bottom:0;border-bottom:0;padding-bottom:0">
            <?php foreach (KURIKULUM_HARI as $h): ?>
                <?php $n = (int) ($hariJumlah[$h] ?? 0); ?>
                <a class="tab-item<?= $h === $hari ? ' aktif' : '' ?>" href="kurikulum.php?tab=jadwal&hari=<?= e(rawurlencode($h)) ?>">
                    <?= e($h) ?><span class="tab-jumlah"><?= $n ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid g1">
        <div class="kartu">
            <div class="kartu-kepala">
                <div>
                    <h2>Baris Jadwal — Hari <?= e($hari) ?></h2>
                    <p class="mut kecil mb0">Urutkan dengan tombol panah; urutan ini yang dipakai pada tabel
                        di website.</p>
                </div>
            </div>
            <?php if (!$jadwal): ?>
                <p class="mut">Belum ada baris jadwal untuk hari <strong><?= e($hari) ?></strong>.
                    Tambahkan pada formulir di samping (atau pilih hari lain di atas).</p>
            <?php else: ?>
                <div class="tabel-wrap">
                    <table class="tabel">
                        <thead>
                            <tr><th style="width:52px">Urut</th><th style="width:130px">Waktu</th>
                                <th style="width:70px">Jam Ke</th><th style="width:120px">Kelas</th>
                                <th>Mata Pelajaran / Kegiatan</th><th style="width:170px">Guru</th>
                                <th class="aksi-sel">Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jadwal as $b): ?>
                                <?php $kegiatan = (int) $b['jam_ke'] <= 0 || trim((string) $b['kelas']) === ''; ?>
                                <tr>
                                    <td class="aksi-sel"><?= tombol_urut('kurikulum.php', (int) $b['id'],
                                            ['tab' => 'jadwal', 'hari' => $hari, 'tabel' => 'kurikulum_jadwal']) ?></td>
                                    <td><?= e((string) $b['waktu']) ?></td>
                                    <td><?= (int) $b['jam_ke'] > 0 ? (int) $b['jam_ke'] : '<span class="mut">&mdash;</span>' ?></td>
                                    <td><?= $kegiatan ? '<span class="pil pil-emas">Kegiatan</span>' : e((string) $b['kelas']) ?></td>
                                    <td><strong><?= e((string) $b['mapel']) ?></strong></td>
                                    <td><?= trim((string) $b['guru']) !== '' ? e((string) $b['guru']) : '<span class="mut">&mdash;</span>' ?></td>
                                    <td class="aksi-sel">
                                        <?= tombol_mini('kurikulum.php?tab=jadwal&hari=' . rawurlencode($hari)
                                            . '&edit=' . (int) $b['id'] . '#formJadwal', 'edit', 'Ubah baris jadwal') ?>
                                        <?= form_hapus('jadwal_hapus', (int) $b['id'], 'Hapus baris jadwal ini?', ['hari' => $hari]) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="kartu" id="formJadwal">
            <h2><?= $editJadwal ? 'Ubah Baris Jadwal' : 'Tambah Baris Jadwal' ?></h2>
            <?php
            // =====================================================================================
            // AUTO-ISI WAKTU (v1.19.1 — permintaan pemilik): *"Bagian input waktu buat otomatis
            // mengikuti hari yang sama waktunya. agar penginputan menjadi lebih cepat dan tidak
            // input manual waktu satu persatu"*.
            // Saat MENAMBAH baris, kolom Waktu & Jam Ke terisi sendiri dengan waktu baris TERAKHIR
            // hari yang dipilih (sumber: kurikulum_jadwal_waktu_hari()). Bila Hari diganti, isiannya
            // ikut berganti lewat initJadwalWaktu() di admin/admin.js — peta hari→waktu dikirim pada
            // atribut `data-waktu-hari`. Pada mode UBAH isian TIDAK diisi otomatis (nilai baris yang
            // sedang diubah harus tetap apa adanya); datalist saran tetap tersedia.
            // =====================================================================================
            $hariNilai = (string) ($editJadwal['hari'] ?? $hari);
            $waktuHari = kurikulum_jadwal_waktu_hari();
            $petaWaktu = [];
            foreach ($waktuHari as $hHari => $hData) {
                $petaWaktu[$hHari] = ['waktu' => (string) $hData['waktu'], 'jam_ke' => (int) $hData['jam_ke'],
                    'daftar' => array_values((array) $hData['daftar'])];
            }
            $waktuAwal = $editJadwal !== null
                ? (string) ($editJadwal['waktu'] ?? '')
                : (string) ($waktuHari[$hariNilai]['waktu'] ?? '');
            $jamAwal = $editJadwal !== null
                ? (int) ($editJadwal['jam_ke'] ?? 0)
                : (int) ($waktuHari[$hariNilai]['jam_ke'] ?? 0);
            $saranAwal = array_values((array) ($waktuHari[$hariNilai]['daftar'] ?? []));
            ?>
            <form method="post" action="kurikulum.php"
                  data-waktu-hari="<?= e(json_encode($petaWaktu, JSON_UNESCAPED_UNICODE)) ?>"
                  data-waktu-otomatis="<?= $editJadwal ? '0' : '1' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="jadwal_simpan">
                <input type="hidden" name="tab" value="jadwal">
                <input type="hidden" name="id" value="<?= (int) ($editJadwal['id'] ?? 0) ?>">
                <div class="form-3">
                    <div class="form-baris">
                        <label for="jadwal_hari">Hari <span class="wajib">*</span></label>
                        <select id="jadwal_hari" name="hari" required>
                            <?php foreach (KURIKULUM_HARI as $h): ?>
                                <option value="<?= e($h) ?>" <?= $hariNilai === $h ? 'selected' : '' ?>><?= e($h) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-baris">
                        <label for="jadwal_waktu">Waktu <span class="wajib">*</span></label>
                        <input type="text" id="jadwal_waktu" name="waktu" required
                               maxlength="<?= (int) KURIKULUM_WAKTU_MAKS ?>" placeholder="mis. 07.00-07.45"
                               list="jadwalWaktuSaran" autocomplete="off"
                               value="<?= e($waktuAwal) ?>">
                        <?php // Keterangan ini diperbarui skrip setiap Hari diganti. ?>
                        <div class="bantuan" id="jadwalWaktuInfo">
                            <?php if ($editJadwal): ?>
                                Ubah waktunya bila perlu — waktu baris ini tetap seperti semula bila tidak diubah.
                            <?php elseif ($saranAwal): ?>
                                <strong>Terisi otomatis</strong> dari baris terakhir hari
                                <strong><?= e($hariNilai) ?></strong> (<?= e($saranAwal[count($saranAwal) - 1]) ?>).
                                Boleh diubah, atau pilih salah satu waktu yang sudah dipakai hari itu.
                            <?php else: ?>
                                Belum ada baris jadwal pada hari ini — isi waktunya satu kali
                                (mis. 07.00-07.45). Baris berikutnya pada hari yang sama akan
                                <strong>terisi otomatis</strong>.
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-baris">
                        <label for="jadwal_jam">Jam Ke</label>
                        <input type="number" id="jadwal_jam" name="jam_ke" min="0"
                               max="<?= (int) KURIKULUM_JAM_MAKS ?>" step="1" placeholder="kosong = kegiatan"
                               value="<?= $jamAwal > 0 ? (int) $jamAwal : '' ?>">
                    </div>
                </div>
                <?php if ($editJadwal): ?>
                    <?php
                    // ---- MODE UBAH: satu baris = satu kelas (perilaku lama, jelas & aman) ----
                    $kelasNilai = (string) ($editJadwal['kelas'] ?? '');
                    ?>
                    <div class="form-3">
                        <div class="form-baris">
                            <label for="jadwal_kelas">Kelas</label>
                            <select id="jadwal_kelas" name="kelas">
                                <option value=""><?= e(KURIKULUM_LABEL_KEGIATAN) ?></option>
                                <?php foreach ($kelasList as $nm): ?>
                                    <option value="<?= e($nm) ?>" <?= $kelasNilai === $nm ? 'selected' : '' ?>><?= e($nm) ?></option>
                                <?php endforeach; ?>
                                <?php if ($kelasNilai !== '' && !in_array($kelasNilai, $kelasList, true)): ?>
                                    <option value="<?= e($kelasNilai) ?>" selected><?= e($kelasNilai) ?> (tidak ada di Data Kelas)</option>
                                <?php endif; ?>
                            </select>
                            <div class="bantuan">Daftar kelas diambil dari menu Data Kelas. Untuk mengisi beberapa
                                kelas sekaligus, gunakan formulir <strong>Tambah Baris Jadwal</strong>.</div>
                        </div>
                        <div class="form-baris">
                            <label for="jadwal_mapel">Mata Pelajaran / Nama Kegiatan <span class="wajib">*</span></label>
                            <input type="text" id="jadwal_mapel" name="mapel" required
                                   maxlength="<?= (int) KURIKULUM_MAPEL_MAKS ?>" placeholder="mis. Matematika / Sholat Dhuha"
                                   value="<?= e($editJadwal['mapel'] ?? '') ?>">
                        </div>
                        <div class="form-baris">
                            <label for="jadwal_guru">Guru</label>
                            <input type="text" id="jadwal_guru" name="guru"
                                   maxlength="<?= (int) KURIKULUM_GURU_MAKS ?>" placeholder="mis. Sulistiawan, S.Pd."
                                   list="jadwalGuruSaran" value="<?= e($editJadwal['guru'] ?? '') ?>">
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    // ---- MODE TAMBAH: CENTANG BEBERAPA KELAS SEKALIGUS (v1.19.0) ----
                    // Permintaan pemilik: "Pada bagian kelas buat agar bisa memilih 3 kelas dengan
                    // centang… 1 mata pelajaran untuk setiap kelas sekaligus… untuk mempercepat
                    // penginputan jadwal". Satu kelas dicentang = satu baris jadwal.
                    ?>
                    <div class="form-baris">
                        <label>Kelas &amp; Mata Pelajaran — boleh centang beberapa kelas sekaligus <span class="wajib">*</span></label>
                        <div class="tabel-wrap jdw-pilih-wrap">
                            <table class="tabel jdw-pilih" id="tblPilihKelas">
                                <thead>
                                    <tr>
                                        <th style="width:58px">Pilih</th>
                                        <th style="width:150px">Kelas</th>
                                        <th>Mata Pelajaran</th>
                                        <th style="width:210px">Guru</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kelasList as $i => $nm): ?>
                                        <?php $kunciKelas = rawurlencode($nm); ?>
                                        <tr>
                                            <td class="jdw-pilih-cek">
                                                <input type="checkbox" id="pilih_kelas_<?= (int) $i ?>"
                                                       name="kelas[]" value="<?= e($nm) ?>"
                                                       aria-label="Jadwalkan <?= e($nm) ?>">
                                            </td>
                                            <td><label for="pilih_kelas_<?= (int) $i ?>" class="jdw-pilih-nama"><?= e($nm) ?></label></td>
                                            <td>
                                                <input type="text" id="mapel_kelas_<?= (int) $i ?>"
                                                       name="mapel_kelas[<?= e($kunciKelas) ?>]"
                                                       maxlength="<?= (int) KURIKULUM_MAPEL_MAKS ?>"
                                                       placeholder="mis. Matematika" aria-label="Mata pelajaran <?= e($nm) ?>">
                                            </td>
                                            <td>
                                                <input type="text" id="guru_kelas_<?= (int) $i ?>"
                                                       name="guru_kelas[<?= e($kunciKelas) ?>]"
                                                       maxlength="<?= (int) KURIKULUM_GURU_MAKS ?>"
                                                       placeholder="mis. Sulistiawan, S.Pd." list="jadwalGuruSaran"
                                                       aria-label="Guru <?= e($nm) ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="bantuan">Centang kelas yang dijadwalkan pada jam ini, lalu isi mata pelajaran &amp;
                            gurunya masing-masing. <strong>Satu kelas = satu baris jadwal</strong>, jadi 3 kelas
                            cukup diisi sekali simpan.</div>
                    </div>

                    <div class="form-baris">
                        <label for="jadwal_mapel">Atau Nama Kegiatan (baris tanpa kelas)</label>
                        <input type="text" id="jadwal_mapel" name="mapel"
                               maxlength="<?= (int) KURIKULUM_MAPEL_MAKS ?>"
                               placeholder="mis. Sholat Dhuha / Istirahat / Sholat Dhuhur">
                        <div class="bantuan">Isi kolom ini <strong>hanya</strong> bila baris ini kegiatan yang
                            menggabung semua kelas (Jam Ke otomatis kosong). Bila ada kelas yang dicentang,
                            kolom ini diabaikan.</div>
                    </div>
                <?php endif; ?>
                <?php // Saran nama guru (dipakai kedua mode). Datalist tidak perlu berada di dalam form-baris. ?>
                <datalist id="jadwalGuruSaran">
                    <?php foreach (all("SELECT nama FROM guru WHERE status = 'aktif' ORDER BY nama") as $g): ?>
                        <option value="<?= e((string) $g['nama']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <?php // SARAN WAKTU hari yang sedang dipilih (v1.19.1): waktu-waktu yang sudah dipakai
                      // pada hari itu sehingga pengelola cukup memilih, tidak mengetik ulang. Isinya
                      // diperbarui skrip setiap Hari diganti (initJadwalWaktu di admin/admin.js). ?>
                <datalist id="jadwalWaktuSaran">
                    <?php foreach ($saranAwal as $w): ?>
                        <option value="<?= e((string) $w) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <div class="aksi-form">
                    <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Baris</button>
                    <?php if ($editJadwal): ?>
                        <a class="tombol tombol-garis" href="kurikulum.php?tab=jadwal&hari=<?= e(rawurlencode($hari)) ?>">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <?php // ============================ TAB DOKUMEN ============================ ?>
    <div class="grid g1">
        <div class="kartu">
            <div class="kartu-kepala">
                <div>
                    <h2>Dokumen Kurikulum</h2>
                    <p class="mut kecil mb0">PDF, Word (DOCX), Excel (XLSX), PowerPoint (PPTX), CSV, TXT, ZIP,
                        atau gambar (maks
                        <?= e(ukuran_teks(KURIKULUM_MAKS_BYTE)) ?>). Pengunjung mengunduhnya dari kartu
                        dokumen di website.</p>
                </div>
                <a class="tombol tombol-kecil tombol-garis" target="_blank" rel="noopener"
                   href="../<?= e(halaman_url('dokumen-kurikulum')) ?>"><?= ikon('mata', 15) ?> Lihat Halaman</a>
            </div>
            <?php if (!$dokumen): ?>
                <p class="mut">Belum ada dokumen kurikulum. Unggah dokumen pertama melalui formulir di samping.</p>
            <?php else: ?>
                <div class="tabel-wrap">
                    <table class="tabel">
                        <thead>
                            <tr><th style="width:52px">Urut</th><th>Dokumen</th><th style="width:110px">Jenis</th>
                                <th style="width:90px">Ukuran</th><th style="width:110px">Status</th>
                                <th class="aksi-sel">Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dokumen as $d): ?>
                                <?php $ext = kurikulum_dokumen_ext($d); ?>
                                <tr>
                                    <td class="aksi-sel"><?= tombol_urut('kurikulum.php', (int) $d['id'], ['tab' => 'dokumen', 'tabel' => 'kurikulum_dokumen']) ?></td>
                                    <td>
                                        <strong><?= e((string) $d['judul']) ?></strong>
                                        <?php if (trim((string) $d['keterangan']) !== ''): ?>
                                            <div class="kecil mut"><?= e(potong((string) $d['keterangan'], 140)) ?></div>
                                        <?php endif; ?>
                                        <?php if (trim((string) $d['nama_berkas']) !== ''): ?>
                                            <div class="kecil mut"><?= e((string) $d['nama_berkas']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="pil pil-hijau"><?= e(kurikulum_dokumen_jenis_teks($ext)) ?></span></td>
                                    <td class="kecil"><?= (int) $d['ukuran'] > 0 ? e(ukuran_teks((int) $d['ukuran'])) : '<span class="mut">&mdash;</span>' ?></td>
                                    <td><?= (int) $d['aktif'] === 1
                                        ? '<span class="pil pil-terima">Tampil</span>'
                                        : '<span class="pil pil-baru">Disembunyikan</span>' ?></td>
                                    <td class="aksi-sel">
                                        <?php if (trim((string) $d['url']) !== ''): ?>
                                            <a class="tombol-ikon" title="Unduh berkas" href="<?= e(kurikulum_dokumen_tautan($d)) ?>"><?= ikon('unduh', 15) ?></a>
                                        <?php endif; ?>
                                        <?= tombol_mini('kurikulum.php?tab=dokumen&edit=' . (int) $d['id'] . '#formDokumen', 'edit', 'Ubah dokumen') ?>
                                        <?= tombol_mini('kurikulum.php?aksi=toggle&tabel=kurikulum_dokumen&id=' . (int) $d['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                                        <?= form_hapus('dokumen_hapus', (int) $d['id'], 'Hapus dokumen ini dari daftar?') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="kartu" id="formDokumen">
            <h2><?= $editDokumen ? 'Ubah Dokumen' : 'Unggah Dokumen' ?></h2>
            <form method="post" action="kurikulum.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="dokumen_simpan">
                <input type="hidden" name="tab" value="dokumen">
                <input type="hidden" name="id" value="<?= (int) ($editDokumen['id'] ?? 0) ?>">
                <div class="form-baris">
                    <label for="dok_judul">Judul Dokumen <span class="wajib">*</span></label>
                    <input type="text" id="dok_judul" name="judul" required maxlength="<?= (int) KURIKULUM_JUDUL_MAKS ?>"
                           placeholder="mis. Kalender Pendidikan 2026/2027"
                           value="<?= e($editDokumen['judul'] ?? '') ?>">
                </div>
                <div class="form-baris">
                    <label for="dok_keterangan">Keterangan</label>
                    <textarea id="dok_keterangan" name="keterangan" rows="3"
                              maxlength="<?= (int) KURIKULUM_KET_MAKS ?>"><?= e($editDokumen['keterangan'] ?? '') ?></textarea>
                </div>
                <div class="form-baris">
                    <label for="dok_berkas">Berkas <?= $editDokumen ? '' : '<span class="wajib">*</span>' ?></label>
                    <input type="file" id="dok_berkas" name="berkas"
                           accept=".pdf,.docx,.xlsx,.pptx,.csv,.txt,.zip,.jpg,.jpeg,.png,.webp">
                    <div class="bantuan">PDF, Word (DOCX), Excel (XLSX), PowerPoint (PPTX), CSV, TXT, ZIP, JPG, PNG,
                        atau WEBP — maks
                        <?= e(ukuran_teks(KURIKULUM_MAKS_BYTE)) ?>.<?= $editDokumen ? ' Kosongkan bila berkas tidak diganti.' : '' ?></div>
                    <?php if ($editDokumen && trim((string) $editDokumen['nama_berkas']) !== ''): ?>
                        <div class="bantuan">Berkas sekarang: <strong><?= e((string) $editDokumen['nama_berkas']) ?></strong></div>
                    <?php endif; ?>
                </div>
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="aktif" value="1" <?= !$editDokumen || (int) $editDokumen['aktif'] === 1 ? 'checked' : '' ?>>
                    Tampilkan di website
                </label>
                <div class="aksi-form">
                    <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Dokumen</button>
                    <?php if ($editDokumen): ?>
                        <a class="tombol tombol-garis" href="kurikulum.php?tab=dokumen">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php admin_bawah(); ?>
