<?php
/**
 * PERANGKAT GURU — PANEL SUPER ADMIN (website).
 *
 * Permintaan pemilik: *"Dan isiannya dibuat di akun super admin … Kode download di seting/generate
 * oleh super admin untuk setiap mata pelajaran berbeda."*
 * Pembaruan (v1.9.0): *"pada bagian isian di super admin disesuaikan lagi, setiap mata pelajaran
 * kelasnya bisa diatur. Dan tampilan di super admin dibuat tabel juga agar mudah mengisinya"*.
 *
 * Halaman ini mengatur:
 *   1. **Berkas Perangkat tiap Guru × Mata Pelajaran × KELAS** dalam bentuk **TABEL** —
 *      No, Nama Guru, Kelas, Mata Pelajaran, CP, ATP, RPP/Modul Ajar, Tindakan. Tiap baris punya
 *      kotak centang kelas (dari menu **Data Kelas**) + isian "kelas lain" (kelas yang belum
 *      terdaftar di Data Kelas), tiga isian berkas, tombol Simpan, dan tombol Hapus baris.
 *   2. **Kode Download per Mata Pelajaran** — satu mata pelajaran satu kode (berbeda antar mata
 *      pelajaran), dapat diketik sendiri atau di-*generate*.
 *
 * ⚠️ HTML tidak mengizinkan `<form>` menjadi anak `<tr>`/berisi baris tabel, karena itu SETIAP BARIS
 * memakai formulirnya sendiri yang diletakkan di dalam sel pertama, dan seluruh isian baris itu
 * menunjuk formulirnya lewat atribut **`form="…"`** (pola yang sama dipakai aplikasi e-GURU).
 *
 * Berkas diunggah lewat media proxy platform → yang disimpan ke basis data hanya URL-nya.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/perangkat.php';
require_login();

/** Hanya peran Administrator (Super Admin website) yang mengisi perangkat guru. */
if (!is_admin()) {
    http_response_code(403);
    admin_atas('Tidak berwenang');
    echo '<div class="alert alert-err">' . ikon('info', 18)
        . ' Hanya akun Administrator (Super Admin) yang dapat mengisi Perangkat Guru.</div>';
    admin_bawah();
    exit;
}

/** Buang spasi berlebih pada satu sel kode. */
function perangkat_kode_input(string $kode): string
{
    $kode = strtoupper(trim($kode));
    return preg_replace('/\s+/', '', $kode) ?? '';
}

/** Baris perangkat satu guru + kunci mapel (null bila belum ada) — dipakai blok aksi. */
function perangkat_baris_ambil(int $guruId, string $kunci): ?array
{
    return one('SELECT * FROM perangkat WHERE guru_id = ? AND kunci = ?', [$guruId, $kunci]) ?? null;
}

/** Buat/lengkapi baris perangkat (guru × mapel) dan kembalikan idnya. */
function perangkat_baris_siap(int $guruId, string $mapel, string $kunci): int
{
    $ada = perangkat_baris_ambil($guruId, $kunci);
    $kini = date('Y-m-d H:i:s');
    if ($ada) {
        return (int) $ada['id'];
    }
    q('INSERT INTO perangkat (guru_id, mapel, kunci, kelas, urutan, dibuat, diubah) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$guruId, $mapel, $kunci, '', (int) jumlah('SELECT COUNT(*) FROM perangkat WHERE guru_id = ?', [$guruId]) * 10 + 10, $kini, $kini]);
    return (int) db()->lastInsertId();
}

/** Guru menurut id (null bila tidak ada). */
function perangkat_guru_ambil(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM guru WHERE id = ?', [$id]) : null;
}

/**
 * Kotak centang KELAS + isian kelas manual untuk satu baris tabel.
 *
 * @param array  $terpilih kelas yang sedang tersimpan pada baris itu
 * @param string $idAwal   awalan id agar tidak ada id kembar antar baris
 * @param string $form     id formulir baris ('' bila isian berada di dalam <form> sungguhan)
 */
function perangkat_kelas_isian(array $terpilih, string $idAwal, string $form = ''): string
{
    $daftar = perangkat_kelas_daftar(true);      // kelas nonaktif ikut ditampilkan agar tetap terlihat
    $peta = [];
    foreach ($daftar as $k) {
        $peta[perangkat_kunci((string) $k['nama'])] = (string) $k['nama'];
    }
    $dipilih = [];
    foreach ($terpilih as $nama) {
        $dipilih[perangkat_kunci((string) $nama)] = true;
    }
    $f = $form !== '' ? ' form="' . e($form) . '"' : '';

    $html = '<div class="pg-kelas-isian">';
    if (!$daftar) {
        $html .= '<p class="pg-sub mb0">Belum ada kelas pada menu <b>Data Kelas</b> — tuliskan
                  kelasnya pada isian di bawah.</p>';
    } else {
        $html .= '<div class="pg-kelas-centang">';
        $n = 0;
        foreach ($daftar as $k) {
            $nama = (string) $k['nama'];
            $id = $idAwal . '_k' . $n;
            $html .= '<label class="pg-kelas-label" for="' . e($id) . '">'
                . '<input type="checkbox" id="' . e($id) . '" name="kelas[]" value="' . e($nama) . '"'
                . (isset($dipilih[perangkat_kunci($nama)]) ? ' checked' : '') . $f . '>'
                . '<span>' . e($nama) . '</span>'
                . ((int) $k['aktif'] === 1 ? '' : ' <i class="pg-sub">(nonaktif)</i>')
                . '</label>';
            $n++;
        }
        $html .= '</div>';
    }
    $lain = [];
    foreach ($terpilih as $nama) {
        if (!isset($peta[perangkat_kunci((string) $nama)])) {
            $lain[] = (string) $nama;
        }
    }
    $html .= '<input type="text" class="pg-kelas-lain" name="kelas_lain" maxlength="200"' . $f
        . ' value="' . e(implode(', ', $lain)) . '" placeholder="Kelas lain (pisah koma)">';
    $html .= '</div>';
    return $html;
}

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');
$pesan = '';
$galat = '';

// PERIODE (Tahun Ajaran + Semester) yang sedang disunting — v28. Berkas diunggah/dihapus pada
// periode ini saja, dan nilainya dijaga terhadap daftar periode (nilai asing → periode berjalan).
$periodeEdit = perangkat_periode_pilih(array_merge($_GET, $_POST));
$taEdit = $periodeEdit['ta'];
$semEdit = $periodeEdit['semester'];

// =====================================================================================
// AKSI
// =====================================================================================
if ($aksi === 'simpan_kode') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $mapel = trim((string) ($_POST['mapel'] ?? ''));
    $kunci = perangkat_kunci($mapel);
    $buat = perangkat_kode_input((string) ($_POST['kode'] ?? ''));
    if ($kunci === '') {
        $galat = 'Nama mata pelajaran tidak sah.';
    } elseif ($buat === '') {
        // Dikosongkan → kode untuk mata pelajaran ini dihapus (unduhan tertutup).
        perangkat_kode_hapus($kunci);
        flash('Kode download mata pelajaran ' . $mapel . ' dikosongkan (unduhan mata pelajaran itu ditutup).', 'ok');
        redirect('perangkat.php' . perangkat_periode_query($taEdit, $semEdit));
    } else {
        perangkat_kode_simpan($mapel, $buat);
        flash('Kode download mata pelajaran ' . $mapel . ' disimpan: ' . $buat, 'ok');
        redirect('perangkat.php' . perangkat_periode_query($taEdit, $semEdit));
    }
} elseif ($aksi === 'generate_kode') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $mapel = trim((string) ($_POST['mapel'] ?? ''));
    if (perangkat_kunci($mapel) === '') {
        $galat = 'Nama mata pelajaran tidak sah.';
    } else {
        $kode = perangkat_kode_generate();
        perangkat_kode_simpan($mapel, $kode);
        flash('Kode download baru untuk ' . $mapel . ': ' . $kode, 'ok');
        redirect('perangkat.php' . perangkat_periode_query($taEdit, $semEdit));
    }
} elseif ($aksi === 'generate_sekaligus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    // Generate kode untuk BANYAK mata pelajaran sekaligus — hasilnya BERBEDA satu dengan lainnya.
    // Pilihan `hanya_kosong` = hanya mata pelajaran yang belum punya kode.
    $hanyaKosong = !empty($_POST['hanya_kosong']);
    $daftar = [];
    foreach (perangkat_mapel_semua() as $k => $r) {
        if ($r['tidak_ada']) {
            continue;   // mapel yang sudah tidak ada di tabel perangkat tidak ikut
        }
        if ($hanyaKosong && trim(perangkat_kode($r['nama'])) !== '') {
            continue;
        }
        $daftar[(string) $k] = $r['nama'];
    }
    if (!$daftar) {
        flash($hanyaKosong
            ? 'Semua mata pelajaran sudah punya kode download — tidak ada yang perlu di-generate.'
            : 'Belum ada mata pelajaran yang bisa diberi kode.', 'err');
        redirect('perangkat.php' . perangkat_periode_query($taEdit, $semEdit));
    }
    $hasil = perangkat_kode_generate_sekaligus($daftar);
    flash(angka(count($hasil)) . ' kode download baru dibuat sekaligus (berbeda tiap mata pelajaran): '
        . implode(', ', array_map(static fn (string $k, string $v): string => $k . ' = ' . $v,
            array_keys($hasil), array_values($hasil))), 'ok');
    redirect('perangkat.php' . perangkat_periode_query($taEdit, $semEdit));
} elseif ($aksi === 'simpan_berkas') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $guruId = (int) ($_POST['guru_id'] ?? 0);
    $guru = perangkat_guru_ambil($guruId);
    $mapelBaru = trim((string) ($_POST['mapel_baru'] ?? ''));
    $mapel = trim((string) ($_POST['mapel'] ?? ''));
    if ($guru === null) {
        $galat = 'Data guru tidak ditemukan.';
    } else {
        if ($mapelBaru !== '') {
            $mapel = $mapelBaru;   // mata pelajaran baru yang diketik Super Admin
        }
        $kunci = perangkat_kunci($mapel);
        if ($kunci === '') {
            $galat = 'Mata pelajaran wajib diisi.';
        } else {
            $id = perangkat_baris_siap($guruId, $mapel, $kunci);
            $lama = one('SELECT * FROM perangkat WHERE id = ?', [$id]) ?? [];
            // KELAS baris ini: gabungan kotak centang + kelas yang diketik manual (urut Data Kelas).
            // ⚠️ Bila kiriman SAMA SEKALI tidak memuat medan kelas (mis. unggahan berkas dari skrip
            // atau klien lama), kelas yang sedang tersimpan DIBIARKAN — jangan dikosongkan tanpa
            // sengaja. Formulir pada halaman ini selalu mengirim `kelas_lain` (walaupun kosong),
            // sehingga mengosongkan centang tetap benar-benar mengosongkan kelas.
            $adaMedanKelas = array_key_exists('kelas', $_POST) || array_key_exists('kelas_lain', $_POST);
            $kelas = $adaMedanKelas
                ? perangkat_kelas_siap(array_merge(
                    (array) ($_POST['kelas'] ?? []),
                    [(string) ($_POST['kelas_lain'] ?? '')]
                ))
                : (string) ($lama['kelas'] ?? '');
            $simpan = ['mapel' => $mapel, 'kelas' => $kelas];
            q('UPDATE perangkat SET mapel = ?, kelas = ?, diubah = ? WHERE id = ?',
                [$simpan['mapel'], $simpan['kelas'], date('Y-m-d H:i:s'), $id]);

            // ---- BERKAS PER KELAS (v27) -------------------------------------------------------
            // Kiriman formulir: berkas[<jenis>][<kelasKunci>] (unggahan) & hapus[<jenis>][<kelasKunci>]
            // (centang hapus), dengan nama kelas dipetakan lewat nama_kelas[<kelasKunci>].
            // Bila kiriman hanya memuat medan berkas LAMA (cp/atp/rpp langsung), berkas itu
            // diterapkan pada kelas PERTAMA mata pelajaran (kompatibilitas skrip/klien lama).
            $kelasSusun = perangkat_kelas_pisah($kelas);      // '' bila belum ada kelas
            $namaKelas = [];
            foreach ((array) ($_POST['nama_kelas'] ?? []) as $ka => $na) {
                $ka = (string) $ka;
                $na = trim((string) $na);
                if ($ka !== '' && $na !== '') {
                    $namaKelas[$ka] = $na;
                }
            }
            // Kunci kelas resmi menurut daftar kelas yang BARU disimpan (bukan dari peramban).
            // Kelas kosong ("belum diberi kelas") memakai penanda 'tanpakelas' — sama dengan nama
            // medan pada formulir; baris itu hanya dibuat bila mata pelajaran belum punya kelas.
            $kunciKelas = [];
            if (!$kelasSusun) {
                $kunciKelas[perangkat_kelas_medan_kunci('')] = '';
            }
            foreach ($kelasSusun as $nk) {
                $kunciKelas[perangkat_kelas_medan_kunci($nk)] = $nk;
            }
            $adaGalat = '';
            $jmlUnggah = 0;
            $urutan = 0;
            foreach ($kunciKelas as $kk => $namaKel) {
                // Baris berkas PER PERIODE: semester lain tidak tersentuh oleh simpan ini.
                $barisId = perangkat_kelas_id($id, $guruId, $kunci, $namaKel, $taEdit, $semEdit, $urutan);
                $lamaKelas = one('SELECT * FROM perangkat_kelas WHERE id = ?', [$barisId]) ?? [];
                $ubahKelas = [];
                foreach (array_keys(PERANGKAT_JENIS) as $jenis) {
                    $label = PERANGKAT_JENIS[$jenis];
                    $ubahKelas[$jenis] = (string) ($lamaKelas[$jenis] ?? '');
                    $ubahKelas[$jenis . '_nama'] = (string) ($lamaKelas[$jenis . '_nama'] ?? '');
                    // (a) centang hapus → kosongkan berkas KELAS itu.
                    if (!empty($_POST['hapus'][$jenis][$kk])) {
                        $ubahKelas[$jenis] = '';
                        $ubahKelas[$jenis . '_nama'] = '';
                        continue;
                    }
                    // (b) berkas baru untuk kelas itu (nama medan: berkas[<jenis>][<kelasKunci>]).
                    $medan = 'berkas';
                    if (empty($_FILES['berkas']['name'][$jenis][$kk])) {
                        // Kompatibilitas: medan lama `cp`/`atp`/`rpp` (tanpa kurung) → kelas pertama.
                        if ($kk === array_key_first($kunciKelas) && !empty($_FILES[$jenis]['name'])) {
                            $medan = $jenis;
                        } else {
                            continue;
                        }
                    }
                    $up = $medan === 'berkas'
                        ? unggah_berkas_kelas($jenis, $kk, $label)
                        : unggah_berkas($jenis, $label, PERANGKAT_EKSTENSI, PERANGKAT_MAKS_BYTE);
                    if (!empty($up['ok'])) {
                        $ubahKelas[$jenis] = (string) $up['url'];
                        $ubahKelas[$jenis . '_nama'] = nama_berkas_asli($jenis, $guru, $mapel, $label, $namaKel);
                        $jmlUnggah++;
                    } elseif (empty($up['kosong']) && $adaGalat === '') {
                        $adaGalat = (string) ($up['pesan'] ?? ('Berkas ' . $label . ' gagal diunggah.'));
                    }
                }
                q('UPDATE perangkat_kelas SET cp = ?, cp_nama = ?, atp = ?, atp_nama = ?, rpp = ?, rpp_nama = ?,
                   kelas = ?, urutan = ?, diubah = ? WHERE id = ?',
                    [$ubahKelas['cp'], $ubahKelas['cp_nama'], $ubahKelas['atp'], $ubahKelas['atp_nama'],
                     $ubahKelas['rpp'], $ubahKelas['rpp_nama'], $namaKel, $urutan,
                     date('Y-m-d H:i:s'), $barisId]);
                $urutan += 10;
            }
            if ($adaGalat !== '') {
                $galat = $adaGalat;
            } else {
                $kelasTeks = $kelas !== '' ? ' · kelas ' . $kelas : ' · kelas belum diatur';
                $unggahTeks = $jmlUnggah > 0 ? ', ' . $jmlUnggah . ' berkas diunggah' : '';
                flash('Perangkat ' . $guru['nama'] . ' — ' . $mapel
                    . ' (' . perangkat_periode_label($taEdit, $semEdit) . ')' . $kelasTeks . $unggahTeks . ' disimpan.', 'ok');
                redirect('perangkat.php' . perangkat_periode_query($taEdit, $semEdit) . '#g' . $guruId);
            }
        }
    }
} elseif ($aksi === 'hapus_baris') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $row = one('SELECT * FROM perangkat WHERE id = ?', [$id]);
    if ($row) {
        q('DELETE FROM perangkat WHERE id = ?', [$id]);
        flash('Baris perangkat ' . $row['mapel'] . ' dihapus.', 'ok');
    } else {
        flash('Baris perangkat tidak ditemukan.', 'err');
    }
    redirect('perangkat.php' . perangkat_periode_query($taEdit, $semEdit));
}

/** Nama berkas unduhan yang rapi: "<Jenis> - <Nama Guru> - <Mapel> - <Kelas>.<ext asli>". */
function nama_berkas_asli(string $jenis, array $guru, string $mapel, string $label, string $kelas = ''): string
{
    $ext = '';
    if (!empty($_FILES[$jenis]['name'])) {
        $ext = strtolower(pathinfo((string) $_FILES[$jenis]['name'], PATHINFO_EXTENSION));
    } elseif (isset($_FILES['berkas']['name']) && is_array($_FILES['berkas']['name'])) {
        // Medan berkelompok: berkas[<jenis>][<kelasKunci>] (v27).
        $nama = $_FILES['berkas']['name'][$jenis] ?? '';
        if (is_array($nama)) {
            $nama = (string) reset($nama);
        }
        $ext = strtolower(pathinfo((string) $nama, PATHINFO_EXTENSION));
    }
    $label = preg_replace('/\s*\(.*?\)\s*/', '', $label) ?? $label;
    $nama = trim($label . ' - ' . (string) $guru['nama'] . ($mapel !== '' ? ' - ' . $mapel : '')
        . ($kelas !== '' ? ' - ' . $kelas : ''));
    $nama = preg_replace('/[\r\n"\/\\\\]+/', ' ', $nama) ?? $nama;
    return $ext !== '' ? ($nama . '.' . $ext) : $nama;
}

/**
 * Unggah berkas kelas dari medan berkelompok `$_FILES['berkas'][…][<jenis>][<kelasKunci>]`.
 * PHP menyusun medan berkelompok SECARA BERKOLOM (`['name'][jenis][kelas]`), jadi entri untuk satu
 * (jenis × kelas) harus dirakit ulang lebih dulu.
 */
function unggah_berkas_kelas(string $jenis, string $kelasKunci, string $label): array
{
    $sumber = $_FILES['berkas'] ?? [];
    if (!is_array($sumber) || !isset($sumber['name'][$jenis][$kelasKunci])) {
        return ['ok' => false, 'pesan' => '', 'kosong' => true];
    }
    $entri = [];
    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $k) {
        $entri[$k] = $sumber[$k][$jenis][$kelasKunci] ?? '';
    }
    return unggah_berkas_entri($entri, $label, PERANGKAT_EKSTENSI, PERANGKAT_MAKS_BYTE);
}

// =====================================================================================
// TAMPILAN
// =====================================================================================
$mapelSemua = perangkat_mapel_semua();          // kunci => ['nama' => …, 'tidak_ada' => bool]
$kodePeta = perangkat_kode_peta();              // kunci => baris
$tabel = perangkat_tabel($taEdit, $semEdit);
$ringkas = perangkat_ringkas($taEdit, $semEdit);
$periodeTaAda = perangkat_periode_ta_list();
$periodeSemAda = perangkat_periode_semester_list($taEdit);

admin_atas('Perangkat Guru', 'perangkat.php');
?>
<?php if ($pesan !== ''): ?><div class="alert alert-ok"><?= ikon('check', 18) ?> <?= e($pesan) ?></div><?php endif; ?>
<?php if ($galat !== ''): ?><div class="alert alert-err"><?= ikon('info', 18) ?> <?= e($galat) ?></div><?php endif; ?>

<div class="kartu">
  <div class="kartu-kepala">
    <div>
      <h2>Perangkat Guru</h2>
      <p class="mut sm mb0">
        Satu baris = satu <b>mata pelajaran pada satu guru</b>. Bagian <b>Kelas</b> diatur dengan kotak
        centang (daftar dari menu <b>Data Kelas</b>) — satu mata pelajaran boleh dipakai di beberapa
        kelas sekaligus, mis. Fikih di kelas X, XI, dan XII. Kelas yang belum terdaftar di Data Kelas
        dapat diketik pada isian <b>kelas lain</b>. Untuk setiap kelas tersedia isian berkasnya
        sendiri (<b>CP</b>, <b>ATP</b>, dan <b>RPP/Modul Ajar</b>), jadi Modul Ajar Fikih kelas X, XI,
        dan XII boleh berbeda — dan karena perangkat pembelajaran disusun <b>per semester</b>, berkasnya
        juga terpisah tiap Tahun Ajaran &amp; Semester (pilih periodenya di bawah). Tekan <b>Simpan</b>
        setelah memilih berkas. Daftar guru diambil otomatis dari Data Guru —
        <b>kepala madrasah tidak termasuk</b>. Halaman publiknya:
        <a href="<?= e(perangkat_url()) ?>" target="_blank" rel="noopener"><?= e('halaman/' . (string) (nilai("SELECT slug FROM menu WHERE jenis = 'perangkat' LIMIT 1", []) ?: 'perangkat-guru')) ?></a>.
      </p>
    </div>
    <div class="flex" style="gap:8px">
      <span class="pil pil-baru"><?= angka($ringkas['guru']) ?> guru</span>
      <span class="pil pil-baru"><?= angka($ringkas['baris']) ?> mata pelajaran</span>
      <span class="pil pil-baru"><?= angka($ringkas['kelas']) ?> kelas</span>
      <span class="pil pil-terima"><?= angka($ringkas['berkas']) ?> berkas</span>
    </div>
  </div>

  <?php /* ===== PERIODE: berkas perangkat diunggah PER SEMESTER (v28) ===== */ ?>
  <div class="pg-periode" id="periodeKerja">
    <?php if ($periodeTaAda): ?>
      <form method="get" action="perangkat.php" class="pg-periode-form">
        <strong class="pg-periode-judul"><?= ikon('kalender', 16) ?> Periode yang sedang diisi:</strong>
        <label class="pg-periode-medan" for="taEdit">
          <span>Tahun Ajaran</span>
          <select id="taEdit" name="ta">
            <?php foreach ($periodeTaAda as $t): ?>
              <option value="<?= e($t) ?>"<?= $t === $taEdit ? ' selected' : '' ?>>Tahun Ajaran <?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="pg-periode-medan" for="semEdit">
          <span>Semester</span>
          <select id="semEdit" name="sem">
            <?php foreach ($periodeSemAda as $sm): ?>
              <option value="<?= e($sm) ?>"<?= $sm === $semEdit ? ' selected' : '' ?>>Semester <?= e($sm) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="tombol tombol-kecil"><?= ikon('mata', 15) ?> Tampilkan</button>
        <p class="pg-periode-ket">
          Seluruh unggahan &amp; penghapusan berkas pada halaman ini berlaku untuk
          <b><?= e(perangkat_periode_label($taEdit, $semEdit)) ?></b> — berkas semester lain tidak
          tersentuh. Daftar periode diatur pada menu
          <a href="periode.php">Tahun Ajaran &amp; Semester</a>.
        </p>
      </form>
    <?php else: ?>
      <p class="pg-periode-ket">
        <?= ikon('info', 16) ?>
        <b>Periode (Tahun Ajaran &amp; Semester) belum diatur.</b> Tambahkan pada menu
        <a href="periode.php">Tahun Ajaran &amp; Semester</a> supaya berkas perangkat dapat dipisah
        per semester. Untuk sementara, seluruh berkas dianggap satu periode.
      </p>
    <?php endif; ?>
  </div>

  <div class="alert alert-info">
    <?= ikon('info', 18) ?>
    <div>
      Berkas diunggah lewat layanan penyimpanan platform; yang disimpan ke basis data hanya
      <b>URL berkasnya</b>. Pada halaman publik, berkas yang sudah diunggah ditandai
      <b>centang hijau</b> dan yang belum <b>silang merah</b>. Pengunjung harus memasukkan
      <b>kode download mata pelajaran</b> (daftar di bagian bawah) sebelum berkasnya diunduh.
      Setiap <b>kelas diunggah berkasnya sendiri</b> (mis. Modul Ajar Fikih kelas X berbeda dengan
      kelas XI) dan tiap <b>periode/semester</b> punya berkasnya sendiri, sedangkan
      <b>kode download tetap satu per mata pelajaran</b> (berlaku untuk semua semester).
    </div>
  </div>
</div>

<div class="kartu mt24">
  <div class="kartu-kepala">
    <div>
      <h2>Berkas &amp; Kelas per Mata Pelajaran</h2>
      <p class="mut sm mb0">Centang kelasnya, pilih berkas untuk tiap kelas, lalu tekan <b>Simpan</b>.
        Berkas yang diunggah hanya dipakai oleh kelas di sebelahnya — jadi satu mata pelajaran bisa
        punya Modul Ajar berbeda untuk kelas X, XI, dan XII, <b>dan berbeda pula tiap semester</b>
        (<?= e(perangkat_periode_label($taEdit, $semEdit)) ?>). Baris baru ditambahkan pada bagian bawah
        kelompok tiap guru.</p>
    </div>
  </div>

  <?php if (!$tabel): ?>
    <p class="mut">Belum ada data guru. Tambahkan guru pada menu <b>Data Guru</b> terlebih dahulu.</p>
  <?php else: ?>
    <div class="tabel-wrap">
      <table class="pg-admin-tabel" id="tblPerangkat">
        <thead>
          <tr>
            <th class="pg-c-no">No</th>
            <th>Nama Guru</th>
            <th>Kelas</th>
            <th>Mata Pelajaran</th>
            <?php foreach (PERANGKAT_JENIS as $label): ?>
              <th class="pg-th-berkas"><?= e($label) ?></th>
            <?php endforeach; ?>
            <th class="pg-th-aksi">Tindakan</th>
          </tr>
        </thead>
        <tbody>
        <?php $no = 0; $noForm = 0; ?>
        <?php foreach ($tabel as $kel): $g = $kel['guru']; $gid = (int) $g['id']; $jml = count($kel['baris']); $no++; ?>
          <?php foreach ($kel['baris'] as $i => $b):
            $kunci = (string) $b['kunci'];
            $row = $b['berkas'];
            $noForm++;
            $fid = 'pgF' . $noForm;                 // formulir SIMPAN baris ini
            $hid = 'pgH' . $noForm;                 // formulir HAPUS baris ini
            $kode = $kunci !== '' ? (string) ($kodePeta[$kunci]['kode'] ?? '') : '';
          ?>
            <tr class="pg-baris-admin<?= $i === 0 ? ' pg-baris-awal' : '' ?>"<?= $i === 0 ? ' id="g' . $gid . '"' : '' ?>>
              <?php if ($i === 0): ?>
                <td class="pg-c-no" rowspan="<?= $jml ?>"><?= $no ?></td>
                <td class="pg-c-nama" rowspan="<?= $jml ?>">
                  <b><?= e((string) $g['nama']) ?></b>
                  <?php if (trim((string) ($g['jabatan'] ?? '')) !== ''): ?>
                    <i class="pg-sub"><?= e((string) $g['jabatan']) ?></i>
                  <?php endif; ?>
                  <?php if (trim((string) ($g['nip'] ?? '')) !== '' && trim((string) $g['nip']) !== '-'): ?>
                    <i class="pg-sub">NIP/NUPTK <?= e((string) $g['nip']) ?></i>
                  <?php endif; ?>
                </td>
              <?php endif; ?>

              <td class="pg-td-kelas">
                <?php /* Formulir baris ini berada DI DALAM sel (HTML tidak mengizinkan <form> di <tr>). */ ?>
                <form id="<?= e($fid) ?>" method="post" enctype="multipart/form-data" class="pg-form-baris">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="simpan_berkas">
                  <input type="hidden" name="guru_id" value="<?= $gid ?>">
                  <input type="hidden" name="mapel" value="<?= e((string) $b['mapel']) ?>">
                </form>
                <?= perangkat_kelas_isian($b['daftar_kelas'], $fid, $fid) ?>
                <?php /* Peta kunci kelas => nama kelas untuk pembacaan berkas di server. */ ?>
                <?php foreach ($b['kelas_baris'] as $kb): $kk = perangkat_kelas_medan_kunci((string) $kb['kelas']); ?>
                  <input type="hidden" name="nama_kelas[<?= e($kk) ?>]" value="<?= e((string) $kb['kelas']) ?>" form="<?= e($fid) ?>">
                <?php endforeach; ?>
              </td>

              <td class="pg-td-mapel">
                <?php if ($b['mapel'] !== ''): ?>
                  <b><?= e((string) $b['mapel']) ?></b>
                <?php else: ?>
                  <span class="pg-mut">(belum ada mata pelajaran)</span>
                <?php endif; ?>
                <?php if ($kode !== ''): ?>
                  <i class="pg-sub">kode: <?= e($kode) ?></i>
                <?php elseif ($kunci !== ''): ?>
                  <i class="pg-sub">kode belum diatur</i>
                <?php endif; ?>
                <?php
                  // Ringkasan kelas baris ini (jangan memanggil perangkat_kelas_terisi() dengan baris
                  // kosong — baris mata pelajaran dari Data Guru belum punya baris perangkat).
                  $terisi = 0;
                  foreach ($b['kelas_baris'] as $kbRingkas) {
                      if (perangkat_jumlah_berkas($kbRingkas['berkas']) > 0) {
                          $terisi++;
                      }
                  }
                ?>
                <i class="pg-sub"><?= angka(count($b['kelas_baris'])) ?> kelas · <?= angka($terisi) ?> kelas berberkas</i>
              </td>

              <?php foreach (PERANGKAT_JENIS as $jenis => $label): ?>
                <td class="pg-td-berkas">
                  <?php foreach ($b['kelas_baris'] as $kb):
                    $kk = perangkat_kelas_medan_kunci((string) $kb['kelas']);
                    $berkas = $kb['berkas'];   // berkas pada PERIODE yang sedang dipilih
                    $ada = perangkat_ada($berkas, $jenis);
                  ?>
                    <div class="pg-admin-sel">
                      <div class="pg-admin-sel-kepala">
                        <?= $ada ? perangkat_ikon_ada(20) : perangkat_ikon_belum(20) ?>
                        <b><?= $label ?></b>
                        <span class="pg-kelas-tanda"><?= $kb['kelas'] !== '' ? e((string) $kb['kelas']) : 'tanpa kelas' ?></span>
                      <?php if (perangkat_ada($kb['berkas'], $jenis)): ?>
                        <span class="pg-periode-tanda"><?= e($semEdit !== '' ? $semEdit : 'periode') ?></span>
                      <?php endif; ?>
                      </div>
                      <?php if ($ada): ?>
                        <p class="pg-sub mb0"><?= e((string) ($berkas[$jenis . '_nama'] ?? 'berkas tersimpan')) ?></p>
                        <label class="pg-admin-centang">
                          <input type="checkbox" name="hapus[<?= e($jenis) ?>][<?= e($kk) ?>]" value="1" form="<?= e($fid) ?>"> hapus
                        </label>
                      <?php else: ?>
                        <p class="pg-sub mb0">Belum diunggah untuk kelas ini.</p>
                      <?php endif; ?>
                      <?php if ($kunci !== ''): ?>
                        <input type="file" name="berkas[<?= e($jenis) ?>][<?= e($kk) ?>]" form="<?= e($fid) ?>"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp">
                      <?php else: ?>
                        <p class="pg-sub mb0">Simpan mata pelajarannya lebih dahulu.</p>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </td>
              <?php endforeach; ?>

              <td class="pg-td-aksi">
                <div class="pg-aksi">
                  <button type="submit" class="tombol tombol-kecil" form="<?= e($fid) ?>">
                    <?= ikon('simpan', 15) ?> Simpan</button>
                  <?php if ($row): ?>
                    <form id="<?= e($hid) ?>" method="post" class="pg-form-baris"
                          data-konfirmasi="Hapus baris perangkat <?= e((string) $b['mapel']) ?>?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus_baris">
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    </form>
                    <button type="submit" class="tombol tombol-garis tombol-kecil" form="<?= e($hid) ?>">
                      <?= ikon('trash', 15) ?> Hapus</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php /* ---- baris TAMBAH mata pelajaran baru untuk guru ini ---- */ ?>
          <tr class="pg-baris-tambah">
            <td colspan="<?= 5 + count(PERANGKAT_JENIS) ?>">
              <form method="post" enctype="multipart/form-data" class="pg-tambah-mapel">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="simpan_berkas">
                <input type="hidden" name="guru_id" value="<?= $gid ?>">
                <span class="pg-tambah-label"><?= ikon('plus', 15) ?> Tambah mata pelajaran
                  untuk <?= e((string) $g['nama']) ?>:</span>
                <input type="text" name="mapel_baru" maxlength="80" placeholder="Mis. Bahasa Arab">
                <?= perangkat_kelas_isian([], 'pgT' . $gid, '') ?>
                <button type="submit" class="tombol tombol-garis tombol-kecil">Tambah</button>
                <p class="pg-sub mb0">Menambah baris baru (kelas + mata pelajaran). Berkasnya diunggah
                  setelah barisnya tersimpan.</p>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="kartu mt24">
  <div class="kartu-kepala">
    <div>
      <h2>Kode Download per Mata Pelajaran</h2>
      <p class="mut sm mb0">Satu mata pelajaran satu kode — <b>berbeda untuk setiap mata pelajaran</b>
        (seluruh kelas mata pelajaran itu memakai kode yang sama). Daftar ini mengikuti mata pelajaran
        yang <b>diinput pada tabel di atas</b>. Kosongkan lalu simpan bila ingin menutup unduhan mata
        pelajaran itu.</p>
    </div>
    <div class="pg-kode-aksi">
      <form method="post" id="formGenerateSemuaSemua">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="generate_sekaligus">
        <button type="submit" class="tombol tombol-kecil"
                data-konfirmasi="Buat kode BARU untuk SEMUA mata pelajaran sekaligus? Kode lama tidak berlaku lagi.">
          <?= ikon('setelan', 15) ?> Generate Sekaligus (semua)</button>
      </form>
      <form method="post" id="formGenerateSemuaKosong">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="generate_sekaligus">
        <input type="hidden" name="hanya_kosong" value="1">
        <button type="submit" class="tombol tombol-garis tombol-kecil">
          <?= ikon('plus', 15) ?> Isi yang belum ada kode</button>
      </form>
    </div>
  </div>
  <?php if (!$mapelSemua): ?>
    <p class="mut">Belum ada mata pelajaran. Tambahkan mata pelajaran pada tabel di atas — daftar kode
       ini mengikuti mata pelajaran yang Anda input di sana.</p>
  <?php else: ?>
    <div class="tabel-wrap">
      <table class="tabel" id="tblKodeMapel">
        <thead><tr>
          <th style="width:60px">No</th><th>Mata Pelajaran</th><th style="width:230px">Kode Download</th>
          <th style="width:250px">Tindakan</th>
        </tr></thead>
        <tbody>
        <?php $no = 0; foreach ($mapelSemua as $kunci => $r): $no++; $nama = $r['nama']; $kode = (string) ($kodePeta[$kunci]['kode'] ?? ''); ?>
          <tr<?= $r['tidak_ada'] ? ' class="pg-baris-luar"' : '' ?>>
            <td><?= $no ?></td>
            <td>
              <b><?= e($nama) ?></b>
              <?php if ($r['tidak_ada']): ?>
                <i class="pg-sub">tidak ada lagi pada tabel perangkat</i>
              <?php endif; ?>
            </td>
            <td>
              <form method="post" style="display:flex;gap:6px;align-items:center" id="formKode<?= $no ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="simpan_kode">
                <input type="hidden" name="mapel" value="<?= e($nama) ?>">
                <input type="text" name="kode" value="<?= e($kode) ?>" maxlength="40"
                       placeholder="(belum diatur)" style="width:130px" class="inp-kode">
                <button type="submit" class="tombol tombol-kecil">Simpan</button>
              </form>
            </td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="generate_kode">
                <input type="hidden" name="mapel" value="<?= e($nama) ?>">
                <button type="submit" class="tombol tombol-garis tombol-kecil"><?= ikon('setelan', 15) ?> Generate kode baru</button>
              </form>
              <?php if ($kode !== ''): ?>
                <button type="button" class="tombol tombol-garis tombol-kecil" data-salin="#kodeTeks<?= $no ?>">Salin</button>
                <span id="kodeTeks<?= $no ?>" class="sr-salin-teks" style="display:none"><?= e($kode) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php admin_bawah(); ?>
