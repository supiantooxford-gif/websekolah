<?php
/**
 * PRESTASI SISWA PER KELAS — panel pengisian (Administrator **dan** akun Editor).
 *
 * Permintaan pemilik: *"Bagian yang saya tandai warna merah buat kartu prestasi siswa per kelas.
 * Isinya peringkat 1-3 di setiap kelas. Yang ditampilkan adalah peringkat 1-3 dikelas, filter setiap
 * tahun ajaran dan semesternya. Peringkat berisi Foto, Kelas, Nilai Rata-rata. Data di isi oleh super
 * admin atau editor (Yelfis Djamaludin)"*
 *
 * Halaman ini mengisi tabel `prestasi_kelas` (lihat `inc/prestasi_kelas.php`):
 *   • Tahun Ajaran + Semester (periode)  • Kelas  • Peringkat 1–3
 *   • Nama siswa (dipilih dari Data Siswa, atau diketik manual)  • Foto (unggah → media proxy)
 *   • Nilai rata-rata
 * Setiap peringkat per kelas unik pada satu periode; baris yang sudah ada tinggal disunting.
 *
 * Penjaga: `require_kelola_prestasi_kelas()` = Administrator atau peran `editor`.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_once __DIR__ . '/../inc/prestasi_kelas.php';

require_kelola_prestasi_kelas();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

/** Nilai rata-rata dari kiriman formulir (koma diterima, dijepit 0…PK_NILAI_MAKS). */
function pk_input_nilai(string $kunci): float
{
    $v = trim((string) ($_POST[$kunci] ?? ''));
    $v = str_replace([' ', '.'], '', $v);   // "92.50" / "92,50" / "9 2" → angka
    $v = str_replace(',', '.', $v);
    if (!is_numeric($v)) {
        return 0.0;
    }
    return max(0.0, min((float) $v, PK_NILAI_MAKS));
}

/** Tanggal & jam sekarang (zona waktu aplikasi). */
function pk_kini(): string
{
    return date('Y-m-d H:i:s');
}

// =====================================================================================
// AKSI
// =====================================================================================
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $lama = pk_by_id($id);
    $ta = pk_ta_norm((string) ($_POST['ta'] ?? ''));
    $semester = pk_semester_norm((string) ($_POST['semester'] ?? ''));
    $peringkat = (int) ($_POST['peringkat'] ?? 0);
    $siswaId = (int) ($_POST['siswa_id'] ?? 0);
    $kelasId = (int) ($_POST['kelas_id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $kembali = 'prestasi_kelas.php' . ($id > 0 ? '?edit=' . $id : '') . '#formPk';

    $galat = '';
    if (!pk_ta_sah($ta)) {
        $galat = 'Tahun ajaran wajib berbentuk dua tahun berurutan, mis. 2026/2027.';
    } elseif (!pk_semester_sah($semester)) {
        $galat = 'Semester wajib diisi (mis. Ganjil atau Genap).';
    } elseif ($peringkat < 1 || $peringkat > PK_PERINGKAT_MAKS) {
        $galat = 'Peringkat harus 1, 2, atau 3.';
    }

    // Siswa terpilih → nama & kelas mengikuti Data Siswa (anti salah ketik).
    if ($galat === '' && $siswaId > 0) {
        $s = one('SELECT id, nama, kelas_id FROM siswa WHERE id = ?', [$siswaId]);
        if (!$s) {
            $galat = 'Siswa yang dipilih tidak ditemukan.';
        } else {
            $nama = (string) $s['nama'];
            if ((int) $s['kelas_id'] > 0) {
                $kelasId = (int) $s['kelas_id'];
            }
        }
    }
    if ($galat === '' && $nama === '') {
        $galat = 'Nama siswa wajib diisi (pilih dari Data Siswa, atau ketik manual).';
    }
    if ($galat === '') {
        $kelas = $kelasId > 0 ? one('SELECT id, nama FROM kelas WHERE id = ?', [$kelasId]) : null;
        if (!$kelas) {
            $galat = 'Kelas wajib dipilih, atau pilih siswa yang sudah punya kelas.';
        }
    }
    if ($galat === '' && ($bentrok = pk_bentrok($ta, $semester, $kelasId, $peringkat, $id))) {
        $galat = 'Peringkat ' . $peringkat . ' ' . e((string) $kelas['nama']) . ' untuk periode '
            . e(pk_label_periode($ta, $semester)) . ' sudah diisi (' . e((string) $bentrok['nama'])
            . '). Sunting baris itu, atau pilih peringkat lain.';
    }
    if ($galat !== '') {
        flash($galat, 'err');
        redirect($kembali);
    }

    // Foto: centang hapus → kosongkan; ada berkas baru → unggah; gagal → pertahankan yang lama.
    $foto = (string) ($lama['foto'] ?? '');
    $pesanFoto = '';
    $galatFoto = false;
    if (!empty($_POST['foto_hapus'])) {
        $foto = '';
        $pesanFoto = ' Foto dihapus.';
    } else {
        $up = unggah_gambar('foto', 'Foto siswa');
        if (empty($up['kosong'])) {
            if ($up['ok']) {
                $foto = (string) $up['url'];
                $pesanFoto = ' Foto berhasil diunggah.';
            } else {
                $pesanFoto = ' ' . (string) $up['pesan'];
                $galatFoto = true;
            }
        }
    }

    $data = [
        'ta'        => $ta,
        'semester'  => $semester,
        'kelas_id'  => $kelasId,
        'kelas'     => (string) $kelas['nama'],
        'peringkat' => $peringkat,
        'siswa_id'  => $siswaId,
        'nama'      => potong($nama, NAMA_MAKS, ''),
        'foto'      => $foto,
        'nilai'     => pk_input_nilai('nilai'),
        'aktif'     => empty($_POST['aktif']) ? 0 : 1,
        'diubah'    => pk_kini(),
    ];
    if ($id > 0) {
        $set = [];
        $val = [];
        foreach ($data as $k => $v) {
            $set[] = $k . ' = ?';
            $val[] = $v;
        }
        $val[] = $id;
        q('UPDATE prestasi_kelas SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        flash('Peringkat ' . $peringkat . ' ' . e((string) $kelas['nama']) . ' — “'
            . e($data['nama']) . '” disimpan.' . $pesanFoto, $galatFoto ? 'err' : 'ok');
    } else {
        $data['urutan'] = urutan_baru('prestasi_kelas');
        $data['dibuat'] = pk_kini();
        $kol = array_keys($data);
        q('INSERT INTO prestasi_kelas (' . implode(', ', $kol) . ') VALUES ('
            . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        flash('Peringkat ' . $peringkat . ' ' . e((string) $kelas['nama']) . ' — “'
            . e($data['nama']) . '” ditambahkan.' . $pesanFoto, $galatFoto ? 'err' : 'ok');
    }
    redirect('prestasi_kelas.php?ta=' . rawurlencode($ta) . '&sem=' . rawurlencode($semester));
}

if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $row = pk_by_id($id);
    if (!$row) {
        flash('Baris prestasi tidak ditemukan.', 'err');
        redirect('prestasi_kelas.php');
    }
    q('DELETE FROM prestasi_kelas WHERE id = ?', [$id]);
    flash('Peringkat ' . (int) $row['peringkat'] . ' ' . e((string) $row['kelas']) . ' — “'
        . e((string) $row['nama']) . '” dihapus.', 'ok');
    redirect('prestasi_kelas.php?ta=' . rawurlencode((string) $row['ta'])
        . '&sem=' . rawurlencode((string) $row['semester']));
}

if ($aksi === 'toggle') {
    q('UPDATE prestasi_kelas SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?',
        [(int) ($_GET['id'] ?? 0)]);
    redirect('prestasi_kelas.php?ta=' . rawurlencode((string) ($_GET['ta'] ?? ''))
        . '&sem=' . rawurlencode((string) ($_GET['sem'] ?? '')));
}

// =====================================================================================
// TAMPILAN
// =====================================================================================
$edit = pk_by_id((int) ($_GET['edit'] ?? 0));

// Saringan daftar: periode (tahun ajaran + semester) yang punya data.
$fTa = pk_ta_norm((string) ($_GET['ta'] ?? ''));
$fSem = pk_semester_norm((string) ($_GET['sem'] ?? ''));
$daftarTa = pk_ta_list();
$daftarSem = pk_semester_list();
if ($fTa !== '' && !in_array($fTa, $daftarTa, true)) {
    $fTa = '';
}
if ($fSem !== '' && !in_array($fSem, $daftarSem, true)) {
    $fSem = '';
}

$semua = all('SELECT p.*, k.urutan AS kelas_urutan FROM prestasi_kelas p
              LEFT JOIN kelas k ON k.id = p.kelas_id
              ORDER BY p.ta DESC, p.semester, COALESCE(k.urutan, 999), p.peringkat, p.id');
$daftar = array_values(array_filter($semua, static function (array $r) use ($fTa, $fSem): bool {
    if ($fTa !== '' && (string) $r['ta'] !== $fTa) {
        return false;
    }
    if ($fSem !== '' && (string) $r['semester'] !== $fSem) {
        return false;
    }
    return true;
}));

$kelasList = pk_kelas_list();
$siswaList = all("SELECT s.id, s.nama, s.kelas_id, k.nama AS kelas FROM siswa s
                  LEFT JOIN kelas k ON k.id = s.kelas_id
                  WHERE s.status <> 'lulus' ORDER BY k.urutan, s.nama");
$periodeList = [];
try {
    $periodeList = all('SELECT ta, semester FROM periode ORDER BY urutan, id');
} catch (Throwable $e) {
    $periodeList = [];
}

// Nilai awal formulir.
$isi = [
    'id' => (int) ($edit['id'] ?? 0),
    'ta' => (string) ($edit['ta'] ?? (($periodeList[0]['ta'] ?? '') ?: '')),
    'semester' => (string) ($edit['semester'] ?? (($periodeList[0]['semester'] ?? '') ?: 'Ganjil')),
    'kelas_id' => (int) ($edit['kelas_id'] ?? 0),
    'peringkat' => (int) ($edit['peringkat'] ?? 1),
    'siswa_id' => (int) ($edit['siswa_id'] ?? 0),
    'nama' => (string) ($edit['nama'] ?? ''),
    'foto' => (string) ($edit['foto'] ?? ''),
    'nilai' => $edit ? (float) $edit['nilai'] : 0.0,
    'aktif' => $edit ? (int) $edit['aktif'] : 1,
];

admin_atas('Prestasi per Kelas', 'prestasi_kelas.php');
?>
<div class="alert alert-info">
    <?= ikon('info', 18) ?>
    <div>
        Data di halaman ini tampil pada halaman website <strong>Prestasi Siswa</strong> sebagai
        <strong>Kartu Prestasi Siswa per Kelas</strong> — peringkat <strong>1–3 setiap kelas</strong>
        pada tahun ajaran &amp; semester yang dipilih pengunjung. Setiap peringkat memuat
        <strong>foto, kelas, dan nilai rata-rata</strong>. Satu peringkat hanya boleh diisi satu kali
        untuk satu kelas pada satu periode.
        <strong>Administrator dan akun Editor</strong> sama-sama dapat mengisi halaman ini.
    </div>
</div>

<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2>Daftar Peringkat per Kelas</h2>
            <p class="mut kecil mb0">
                <?= angka(count($daftar)) ?> baris ditampilkan ·
                <?php $slugPk = menu_slug_jenis('prestasi_siswa'); ?>
                <?php if ($slugPk !== ''): ?>
                    <a href="../halaman/<?= e($slugPk) ?>" target="_blank" rel="noopener">lihat di website</a>
                <?php endif; ?>
            </p>
        </div>
        <a class="tombol tombol-kecil" href="prestasi_kelas.php#formPk"><?= ikon('plus', 15) ?> Tambah Peringkat</a>
    </div>

    <form method="get" action="prestasi_kelas.php" class="filter-baris mb16" id="filterPk">
        <label for="fta">Tahun Ajaran</label>
        <select id="fta" name="ta" data-auto>
            <option value="">Semua tahun</option>
            <?php foreach ($daftarTa as $t): ?>
                <option value="<?= e($t) ?>" <?= $t === $fTa ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="fsem">Semester</label>
        <select id="fsem" name="sem" data-auto>
            <option value="">Semua semester</option>
            <?php foreach ($daftarSem as $s): ?>
                <option value="<?= e($s) ?>" <?= $s === $fSem ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($fTa !== '' || $fSem !== ''): ?>
            <a class="tombol tombol-garis tombol-kecil" href="prestasi_kelas.php">Reset saringan</a>
        <?php endif; ?>
    </form>

    <div class="tabel-wrap">
        <table class="tabel" id="tblPk">
            <thead>
                <tr>
                    <th style="width:64px">Peringkat</th>
                    <th style="width:70px">Foto</th>
                    <th>Nama Siswa</th>
                    <th style="width:130px">Kelas</th>
                    <th style="width:110px">Tahun Ajaran</th>
                    <th style="width:100px">Semester</th>
                    <th style="width:120px">Nilai Rata-rata</th>
                    <th style="width:90px">Status</th>
                    <th class="aksi-sel" style="width:120px">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$daftar): ?>
                <tr><td colspan="9" class="mut">Belum ada data peringkat. Isi formulir di bawah —
                    halaman website baru menampilkan kartunya setelah ada data.</td></tr>
            <?php endif; ?>
            <?php foreach ($daftar as $r): ?>
                <?php $sedangDiubah = (int) $r['id'] === $isi['id']; ?>
                <tr<?= $sedangDiubah ? ' class="pk-baris-ubah"' : '' ?>>
                    <td><span class="pk-pil-peringkat pk-rank-<?= (int) $r['peringkat'] ?>"><?= (int) $r['peringkat'] ?></span></td>
                    <td>
                        <?php $f = pk_foto($r); ?>
                        <?php if ($f !== ''): ?>
                            <img class="pk-mini" src="<?= e($f) ?>" alt="Foto <?= e((string) $r['nama']) ?>">
                        <?php else: ?>
                            <span class="pk-mini pk-mini-kosong"><?= e(pk_inisial((string) $r['nama'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= e((string) $r['nama']) ?></strong>
                        <?php if ($sedangDiubah): ?><span class="pil pil-baru">sedang diubah</span><?php endif; ?>
                    </td>
                    <td><?= e((string) $r['kelas']) ?></td>
                    <td><?= e((string) $r['ta']) ?></td>
                    <td><?= e((string) $r['semester']) ?></td>
                    <td><b><?= e(pk_nilai_teks($r['nilai'])) ?></b></td>
                    <td>
                        <?php if ((int) $r['aktif'] === 1): ?>
                            <span class="pil pil-terima">Tampil</span>
                        <?php else: ?>
                            <span class="pil pil-baru">Disembunyikan</span>
                        <?php endif; ?>
                    </td>
                    <td class="aksi-sel">
                        <?= tombol_mini('prestasi_kelas.php?edit=' . (int) $r['id'] . '#formPk', 'edit', 'Ubah') ?>
                        <?= tombol_mini('prestasi_kelas.php?aksi=toggle&id=' . (int) $r['id']
                            . '&ta=' . rawurlencode((string) $r['ta']) . '&sem=' . rawurlencode((string) $r['semester']),
                            'mata', 'Tampilkan/sembunyikan') ?>
                        <?= form_hapus('hapus', (int) $r['id'],
                            'Hapus peringkat ' . (int) $r['peringkat'] . ' ' . (string) $r['kelas']
                            . ' — ' . (string) $r['nama'] . '?') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="kartu mt24" id="formPk">
    <h2><?= $isi['id'] > 0 ? 'Ubah Peringkat' : 'Tambah Peringkat' ?></h2>
    <form method="post" action="prestasi_kelas.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) $isi['id'] ?>">
        <div class="grid g2">
            <div class="form-baris">
                <label for="ta">Tahun Ajaran <span class="wajib">*</span></label>
                <input type="text" id="ta" name="ta" required maxlength="<?= (int) PK_TA_MAKS ?>"
                       list="saranTaPk" placeholder="2026/2027" value="<?= e($isi['ta']) ?>">
                <datalist id="saranTaPk">
                    <?php foreach ($periodeList as $p): ?>
                        <option value="<?= e((string) $p['ta']) ?>"></option>
                    <?php endforeach; ?>
                    <?php foreach ($daftarTa as $t): ?>
                        <option value="<?= e($t) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <div class="bantuan">Dua tahun berurutan, mis. <b>2026/2027</b>.</div>
            </div>
            <div class="form-baris">
                <label for="semester">Semester <span class="wajib">*</span></label>
                <input type="text" id="semester" name="semester" required maxlength="<?= (int) PK_SEMESTER_MAKS ?>"
                       list="saranSemPk" placeholder="Ganjil" value="<?= e($isi['semester']) ?>">
                <datalist id="saranSemPk">
                    <?php foreach (PK_SEMESTER_PILIHAN as $s): ?><option value="<?= e($s) ?>"></option><?php endforeach; ?>
                    <?php foreach ($daftarSem as $s): ?><option value="<?= e($s) ?>"></option><?php endforeach; ?>
                </datalist>
            </div>
        </div>
        <div class="grid g2">
            <div class="form-baris">
                <label for="siswa_id">Pilih dari Data Siswa</label>
                <select id="siswa_id" name="siswa_id">
                    <option value="0">— ketik manual di bawah —</option>
                    <?php foreach ($siswaList as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $isi['siswa_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e((string) $s['nama']) ?><?= $s['kelas'] !== null && $s['kelas'] !== '' ? ' (' . e((string) $s['kelas']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="bantuan">Bila siswa dipilih, <b>nama &amp; kelas</b> otomatis mengikuti Data Siswa.</div>
            </div>
            <div class="form-baris">
                <label for="kelas_id">Kelas <span class="wajib">*</span></label>
                <select id="kelas_id" name="kelas_id">
                    <option value="0">— pilih kelas —</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= (int) $k['id'] ?>" <?= $isi['kelas_id'] === (int) $k['id'] ? 'selected' : '' ?>>
                            <?= e((string) $k['nama']) ?><?= (int) $k['aktif'] === 1 ? '' : ' (nonaktif)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid g2">
            <div class="form-baris">
                <label for="nama">Nama Siswa (manual) <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" maxlength="<?= (int) NAMA_MAKS ?>"
                       placeholder="Nama lengkap siswa" value="<?= e($isi['nama']) ?>">
            </div>
            <div class="form-baris">
                <label for="peringkat">Peringkat <span class="wajib">*</span></label>
                <select id="peringkat" name="peringkat">
                    <?php for ($i = 1; $i <= PK_PERINGKAT_MAKS; $i++): ?>
                        <option value="<?= $i ?>" <?= $isi['peringkat'] === $i ? 'selected' : '' ?>>Peringkat <?= $i ?></option>
                    <?php endfor; ?>
                </select>
                <div class="bantuan">Setiap kelas hanya boleh punya satu peringkat 1, satu peringkat 2,
                    dan satu peringkat 3 pada periode yang sama.</div>
            </div>
        </div>
        <div class="grid g2">
            <div class="form-baris">
                <label for="nilai">Nilai Rata-rata <span class="wajib">*</span></label>
                <input type="text" id="nilai" name="nilai" inputmode="decimal" maxlength="8"
                       placeholder="92,50" value="<?= $isi['id'] > 0 ? e(pk_nilai_teks($isi['nilai'])) : '' ?>">
                <div class="bantuan">Skala 0–100, boleh memakai koma atau titik (mis. <b>92,50</b>).</div>
            </div>
            <div class="form-baris">
                <label for="foto">Foto Siswa</label>
                <input type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png,.gif,.webp,.svg"
                       data-pratinjau="#pratinjauPk">
                <div class="bantuan">JPG/PNG/WEBP, maks <?= e(ukuran_teks(GAMBAR_MAKS_BYTE)) ?>.
                    Bila dikosongkan, dipakai foto siswa dari Data Siswa (bila ada).</div>
                <div class="gambar-pratinjau" style="margin-top:10px">
                    <span class="pratinjau-gambar" id="pratinjauPk">
                        <?php if ($isi['foto'] !== ''): ?>
                            <img src="<?= e($isi['foto']) ?>" alt="Pratinjau foto">
                        <?php else: ?>
                            <span class="kosong-kecil">Belum ada foto</span>
                        <?php endif; ?>
                    </span>
                    <?php if ($isi['foto'] !== ''): ?>
                        <label class="rasta kecil" style="margin-top:8px;gap:8px">
                            <input type="checkbox" name="foto_hapus" value="1"> Hapus foto ini
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="form-baris">
            <label class="rasta" style="gap:9px">
                <input type="checkbox" name="aktif" value="1" <?= $isi['aktif'] === 1 ? 'checked' : '' ?>>
                Tampilkan peringkat ini di website
            </label>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?>
                <?= $isi['id'] > 0 ? 'Simpan Perubahan' : 'Tambah Peringkat' ?></button>
            <?php if ($isi['id'] > 0): ?>
                <a class="tombol tombol-garis" href="prestasi_kelas.php">Batal Ubah</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php
admin_bawah();
