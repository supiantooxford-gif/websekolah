<?php
/**
 * Prestasi & Pelatihan — satu halaman dengan tab per kategori:
 *   • Prestasi Siswa     (Kesiswaan)     : Foto, Nama Siswa, Jenis Prestasi, Tahun, Tingkat, Sertifikat
 *   • Prestasi Madrasah  (Akademik)      : Nama Prestasi, Tahun, Sertifikat
 *   • Prestasi Pendidik  (Akademik)      : Foto, Nama Guru, Jenis Prestasi, Tahun, Tingkat, Sertifikat
 *   • Pelatihan Pendidik (Akademik)      : Nama, Jabatan, Jenis Pelatihan, Tahun, Tingkat, Sertifikat
 *
 * Sertifikat diunggah lewat media proxy platform (PDF/JPG/PNG, maks 15 MB) dan ditampilkan
 * pada website sebagai tombol “Lihat” yang membuka berkasnya di tab baru.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$kategori = prestasi_kategori_sah((string) ($_POST['kategori'] ?? $_GET['kategori'] ?? 'siswa'));
$KAT = prestasi_kategori();
$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

/** Jenjang yang butuh tautan ke siswa/guru. */
$perluSiswa = $kategori === 'siswa';
$perluGuru = in_array($kategori, ['pendidik', 'pelatihan'], true);

// ---------------------------------------------------------------- aksi
if ($aksi === 'naik' || $aksi === 'turun') {
    geser_urutan('prestasi', (int) ($_GET['id'] ?? 0), $aksi, ['kategori' => $kategori]);
    redirect('prestasi.php?kategori=' . $kategori);
}
if ($aksi === 'toggle') {
    q('UPDATE prestasi SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('prestasi.php?kategori=' . $kategori);
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('prestasi', (int) ($_POST['id'] ?? 0));
    flash('Data ' . e(prestasi_label($kategori)) . ' dihapus.', 'ok');
    redirect('prestasi.php?kategori=' . $kategori);
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $lama = $id > 0 ? prestasi_by_id($id) : null;

    // Nama: untuk siswa/pendidik boleh diambil dari pilihan; untuk madrasah/pelatihan diketik.
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $siswaId = (int) ($_POST['siswa_id'] ?? 0);
    $guruId = (int) ($_POST['guru_id'] ?? 0);
    if ($perluSiswa && $siswaId > 0) {
        $s = one('SELECT nama FROM siswa WHERE id = ?', [$siswaId]);
        if ($s) {
            $nama = (string) $s['nama'];
        } else {
            $siswaId = 0;
        }
    }
    $jabatan = potong((string) ($_POST['jabatan'] ?? ''), NAMA_MAKS, '');
    if ($perluGuru && $guruId > 0) {
        $g = one('SELECT nama, jabatan FROM guru WHERE id = ?', [$guruId]);
        if ($g) {
            $nama = (string) $g['nama'];
            if ($jabatan === '') {
                $jabatan = (string) $g['jabatan'];
            }
        } else {
            $guruId = 0;
        }
    }

    if ($nama === '') {
        flash('Nama ' . ($kategori === 'madrasah' ? 'prestasi' : 'orang/peserta') . ' wajib diisi.', 'err');
        redirect('prestasi.php?kategori=' . $kategori . ($id > 0 ? '&edit=' . $id : '') . '#formPrestasi');
    }

    // Sertifikat: centang hapus → kosongkan; ada berkas baru → unggah; gagal → pertahankan yang lama.
    $sertifikat = (string) ($lama['sertifikat'] ?? '');
    $pesanBerkas = '';
    $galatBerkas = false;
    if (!empty($_POST['sertifikat_hapus'])) {
        $sertifikat = '';
        $pesanBerkas = 'Sertifikat dihapus.';
    } else {
        $up = unggah_berkas('sertifikat', 'Sertifikat');
        if (empty($up['kosong'])) {
            if ($up['ok']) {
                $sertifikat = (string) $up['url'];
                $pesanBerkas = 'Sertifikat berhasil diunggah.';
            } else {
                $pesanBerkas = (string) $up['pesan'];
                $galatBerkas = true;
            }
        }
    }

    $data = [
        'kategori'   => $kategori,
        'siswa_id'   => $perluSiswa ? $siswaId : 0,
        'guru_id'    => $perluGuru ? $guruId : 0,
        'nama'       => $kategori === 'madrasah' ? potong($nama, JUDUL_MAKS, '') : potong($nama, NAMA_MAKS, ''),
        'jabatan'    => $jabatan,
        'jenis'      => potong((string) ($_POST['jenis'] ?? ''), JUDUL_MAKS, ''),
        'tahun'      => potong(trim((string) ($_POST['tahun'] ?? '')), 10, ''),
        'tingkat'    => prestasi_tingkat_sah((string) ($_POST['tingkat'] ?? '')),
        'sertifikat' => $sertifikat,
        'keterangan' => potong((string) ($_POST['keterangan'] ?? ''), 300, ''),
        'aktif'      => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        $set = [];
        $val = [];
        foreach ($data as $k => $v) {
            $set[] = $k . ' = ?';
            $val[] = $v;
        }
        $val[] = $id;
        q('UPDATE prestasi SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        flash('Data ' . e(prestasi_label($kategori)) . ' “' . e($data['nama']) . '” disimpan. ' . $pesanBerkas, $galatBerkas ? 'err' : 'ok');
    } else {
        $data['urutan'] = urutan_baru('prestasi', ['kategori' => $kategori]);
        $data['dibuat'] = date('Y-m-d H:i:s');
        $kol = array_keys($data);
        q('INSERT INTO prestasi (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        flash('Data ' . e(prestasi_label($kategori)) . ' “' . e($data['nama']) . '” ditambahkan. ' . $pesanBerkas, $galatBerkas ? 'err' : 'ok');
    }
    redirect('prestasi.php?kategori=' . $kategori);
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? prestasi_by_id((int) $_GET['edit']) : null;
if ($edit && (string) $edit['kategori'] !== $kategori) {
    $edit = null;   // jangan menyunting data kategori lain dari tab ini
}
$daftar = prestasi_list($kategori, false);
$daftarSiswa = $perluSiswa ? all("SELECT id, nama, kelas_id FROM siswa WHERE status <> 'lulus' ORDER BY nama") : [];
$daftarGuru = $perluGuru ? all("SELECT id, nama, jabatan FROM guru WHERE status = 'aktif' ORDER BY nama") : [];
$jumlah = [];
foreach ($KAT as $kunci => $info) {
    $jumlah[$kunci] = prestasi_jumlah($kunci, false);
}

admin_atas('Prestasi & Pelatihan');
?>
<div class="alert alert-info">
    Data di halaman ini tampil pada website: <strong>Prestasi Siswa</strong> (menu Kesiswaan) serta
    <strong>Prestasi Madrasah</strong>, <strong>Prestasi Pendidik</strong>, dan
    <strong>Pelatihan Pendidik</strong> (menu Akademik). Sertifikat diunggah sebagai
    <strong>PDF atau gambar</strong> (maks 15 MB) dan tampil sebagai tombol <strong>“Lihat”</strong>
    yang membuka berkasnya di tab baru.
</div>

<div class="kartu">
    <div class="tab-prestasi">
        <?php foreach ($KAT as $kunci => $info): ?>
            <a class="tab-item<?= $kunci === $kategori ? ' aktif' : '' ?>" href="prestasi.php?kategori=<?= e($kunci) ?>">
                <?= e($info['judul']) ?><span class="tab-jumlah"><?= angka($jumlah[$kunci] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="kartu-kepala">
        <div>
            <h2><?= e($KAT[$kategori]['judul']) ?></h2>
            <p class="mut kecil mb0">
                <?= angka($jumlah[$kategori] ?? 0) ?> data tercatat ·
                <?php $slugPrestasi = menu_slug_jenis($KAT[$kategori]['menu']); ?>
                <?php if ($slugPrestasi !== ''): ?>
                    <a href="../halaman/<?= e($slugPrestasi) ?>" target="_blank" rel="noopener">lihat di website</a>
                <?php endif; ?>
            </p>
        </div>
        <a class="tombol tombol-kecil" href="prestasi.php?kategori=<?= e($kategori) ?>#formPrestasi"><?= ikon('plus', 15) ?> Tambah Data</a>
    </div>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead>
                <tr>
                    <th style="width:62px">Urut</th>
                    <?php if (in_array($kategori, ['siswa', 'pendidik'], true)): ?><th style="width:70px">Foto</th><?php endif; ?>
                    <th><?= $kategori === 'madrasah' ? 'Nama Prestasi' : 'Nama' ?></th>
                    <?php if ($kategori === 'pelatihan'): ?><th style="width:150px">Jabatan</th><?php endif; ?>
                    <?php if (in_array($kategori, ['siswa', 'pendidik'], true)): ?><th>Jenis Prestasi</th><?php endif; ?>
                    <?php if ($kategori === 'pelatihan'): ?><th>Jenis Pelatihan</th><?php endif; ?>
                    <th style="width:80px">Tahun</th>
                    <?php if (in_array($kategori, ['siswa', 'pendidik', 'pelatihan'], true)): ?><th style="width:100px">Tingkat</th><?php endif; ?>
                    <th style="width:110px">Sertifikat</th>
                    <th>Status</th>
                    <th class="aksi-sel">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$daftar): ?>
                    <tr><td colspan="9" class="mut">Belum ada data — tekan “Tambah Data”.</td></tr>
                <?php endif; ?>
                <?php foreach ($daftar as $r): ?>
                    <tr>
                        <td class="aksi-sel"><?= tombol_urut('prestasi.php', (int) $r['id'], ['kategori' => $kategori]) ?></td>
                        <?php if (in_array($kategori, ['siswa', 'pendidik'], true)): ?>
                            <?php $foto = prestasi_foto($r); ?>
                            <td>
                                <?php if ($foto !== ''): ?>
                                    <img class="thumb-admin kotak-bulat" src="<?= e($foto) ?>" alt="<?= e($r['nama']) ?>">
                                <?php else: ?>
                                    <span class="kotak-kosong kotak-bulat thumb-admin"><?= e(strtoupper(substr(trim((string) $r['nama']), 0, 1))) ?></span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td><strong><?= e($r['nama']) ?></strong>
                            <?php if (trim((string) $r['keterangan']) !== ''): ?><div class="kecil mut"><?= e(potong($r['keterangan'], 60)) ?></div><?php endif; ?>
                        </td>
                        <?php if ($kategori === 'pelatihan'): ?><td class="kecil"><?= e($r['jabatan'] ?: '-') ?></td><?php endif; ?>
                        <?php if (in_array($kategori, ['siswa', 'pendidik'], true)): ?><td class="kecil"><?= e($r['jenis'] ?: '-') ?></td><?php endif; ?>
                        <?php if ($kategori === 'pelatihan'): ?><td class="kecil"><?= e($r['jenis'] ?: '-') ?></td><?php endif; ?>
                        <td class="kecil"><?= e($r['tahun'] ?: '-') ?></td>
                        <?php if (in_array($kategori, ['siswa', 'pendidik', 'pelatihan'], true)): ?><td class="kecil"><?= e($r['tingkat'] ?: '-') ?></td><?php endif; ?>
                        <td>
                            <?php if (trim((string) $r['sertifikat']) !== ''): ?>
                                <a class="tombol-ikon" title="Lihat sertifikat" target="_blank" rel="noopener noreferrer" href="<?= e($r['sertifikat']) ?>"><?= ikon('mata', 15) ?></a>
                            <?php else: ?>
                                <span class="mut kecil">belum ada</span>
                            <?php endif; ?>
                        </td>
                        <td><?= ((int) $r['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('prestasi.php?kategori=' . e($kategori) . '&edit=' . (int) $r['id'] . '#formPrestasi', 'edit', 'Ubah') ?>
                            <?= tombol_mini('prestasi.php?kategori=' . e($kategori) . '&aksi=toggle&id=' . (int) $r['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                            <?= form_hapus('hapus', (int) $r['id'], 'Hapus data “' . $r['nama'] . '”?', ['kategori' => $kategori]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 class="mt24" id="formPrestasi"><?= $edit ? 'Ubah Data' : 'Tambah Data' ?> — <?= e($KAT[$kategori]['judul']) ?></h3>
    <form method="post" action="prestasi.php" enctype="multipart/form-data" id="formPrestasiForm">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="kategori" value="<?= e($kategori) ?>">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

        <div class="form-2">
            <?php if ($perluSiswa): ?>
                <div class="form-baris">
                    <label for="siswa_id">Pilih Siswa (mengisi nama &amp; foto otomatis)</label>
                    <select id="siswa_id" name="siswa_id">
                        <option value="0">— ketik manual di bawah —</option>
                        <?php foreach ($daftarSiswa as $sw): ?>
                            <option value="<?= (int) $sw['id'] ?>" <?= (int) ($edit['siswa_id'] ?? 0) === (int) $sw['id'] ? 'selected' : '' ?>><?= e($sw['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif ($perluGuru): ?>
                <div class="form-baris">
                    <label for="guru_id">Pilih Guru / Tendik (mengisi nama &amp; jabatan otomatis)</label>
                    <select id="guru_id" name="guru_id">
                        <option value="0">— ketik manual di bawah —</option>
                        <?php foreach ($daftarGuru as $gr): ?>
                            <option value="<?= (int) $gr['id'] ?>" <?= (int) ($edit['guru_id'] ?? 0) === (int) $gr['id'] ? 'selected' : '' ?>><?= e($gr['nama']) ?><?= $gr['jabatan'] !== '' ? ' — ' . e($gr['jabatan']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-baris">
                <label for="nama"><?= $kategori === 'madrasah' ? 'Nama Prestasi' : 'Nama' ?> <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required
                       maxlength="<?= $kategori === 'madrasah' ? JUDUL_MAKS : NAMA_MAKS ?>"
                       placeholder="<?= $kategori === 'madrasah' ? 'mis. Juara 1 Lomba Madrasah Berprestasi' : 'Nama lengkap' ?>"
                       value="<?= e($edit['nama'] ?? '') ?>">
                <?php if ($perluSiswa || $perluGuru): ?>
                    <div class="bantuan">Terisi otomatis bila memilih siswa/guru di atas — tetap boleh diketik manual.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($kategori === 'pelatihan'): ?>
            <div class="form-2">
                <div class="form-baris">
                    <label for="jabatan">Jabatan</label>
                    <input type="text" id="jabatan" name="jabatan" maxlength="120" placeholder="mis. Guru / Kepala Tata Usaha" value="<?= e($edit['jabatan'] ?? '') ?>">
                </div>
                <div class="form-baris">
                    <label for="jenisPelatihan">Jenis Pelatihan</label>
                    <input type="text" id="jenisPelatihan" name="jenis" maxlength="160" placeholder="mis. Pelatihan Kurikulum Merdeka" value="<?= e($edit['jenis'] ?? '') ?>">
                </div>
            </div>
        <?php elseif ($kategori !== 'madrasah'): ?>
            <div class="form-baris">
                <label for="jenisPrestasi">Jenis Prestasi</label>
                <input type="text" id="jenisPrestasi" name="jenis" maxlength="160" placeholder="mis. Juara 1 Olimpiade Sains / Tahfiz" value="<?= e($edit['jenis'] ?? '') ?>">
            </div>
        <?php endif; ?>

        <div class="form-3">
            <div class="form-baris">
                <label for="tahun">Tahun</label>
                <input type="text" id="tahun" name="tahun" maxlength="10" placeholder="<?= date('Y') ?>" value="<?= e($edit['tahun'] ?? date('Y')) ?>">
            </div>
            <?php if (in_array($kategori, ['siswa', 'pendidik', 'pelatihan'], true)): ?>
                <div class="form-baris">
                    <label for="tingkat">Tingkat</label>
                    <select id="tingkat" name="tingkat">
                        <option value="">— pilih tingkat —</option>
                        <?php foreach (TINGKAT_LIST as $t): ?>
                            <option value="<?= e($t) ?>" <?= ($edit['tingkat'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-baris">
                <label class="rasta" style="gap:9px;font-weight:500;margin-top:26px">
                    <input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan di website
                </label>
            </div>
        </div>

        <div class="form-baris">
            <label for="keterangan">Keterangan (opsional)</label>
            <input type="text" id="keterangan" name="keterangan" maxlength="300" placeholder="mis. tingkat kabupaten, diselenggarakan Dinas Pendidikan" value="<?= e($edit['keterangan'] ?? '') ?>">
        </div>

        <div class="form-baris">
            <label for="sertifikat">Sertifikat (PDF atau gambar, maks 15 MB)</label>
            <div class="gambar-pratinjau">
                <span class="pratinjau-berkas">
                    <?php if (!empty($edit['sertifikat'])): ?>
                        <?php $extSert = strtolower(pathinfo((string) parse_url((string) $edit['sertifikat'], PHP_URL_PATH), PATHINFO_EXTENSION)); ?>
                        <?php if (in_array($extSert, ['jpg', 'jpeg', 'png', 'webp'], true)): ?>
                            <img src="<?= e((string) $edit['sertifikat']) ?>" alt="Sertifikat" style="width:100%;height:100%;object-fit:cover">
                        <?php else: ?>
                            <span class="berkas-ikon"><?= ikon('doc', 26) ?><small>PDF</small></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="kosong-kecil">Belum ada</span>
                    <?php endif; ?>
                </span>
                <span class="pratinjau-medan">
                    <input type="file" id="sertifikat" name="sertifikat" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <?php if (!empty($edit['sertifikat'])): ?>
                        <label class="rasta kecil" style="margin-top:8px;gap:8px">
                            <input type="checkbox" name="sertifikat_hapus" value="1"> Hapus sertifikat ini
                        </label>
                        <a class="tombol tombol-garis tombol-kecil" style="margin-top:8px" href="<?= e((string) $edit['sertifikat']) ?>" target="_blank" rel="noopener noreferrer"><?= ikon('mata', 14) ?> Lihat berkas</a>
                    <?php endif; ?>
                    <span class="bantuan">Berkas PDF/gambar. Pada website tampil sebagai tombol “Lihat”.</span>
                </span>
            </div>
        </div>

        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambahkan Data' ?></button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="prestasi.php?kategori=<?= e($kategori) ?>">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<script>
(function () {
  // Mengisi nama & jabatan otomatis dari pilihan siswa/guru (tetap boleh disunting manual).
  var pilihSiswa = document.getElementById('siswa_id');
  var pilihGuru = document.getElementById('guru_id');
  var nama = document.getElementById('nama');
  var jabatan = document.getElementById('jabatan');
  if (pilihSiswa && nama) {
    pilihSiswa.addEventListener('change', function () {
      var opt = pilihSiswa.options[pilihSiswa.selectedIndex];
      if (pilihSiswa.value !== '0') { nama.value = opt.textContent.trim(); }
    });
  }
  if (pilihGuru && nama) {
    pilihGuru.addEventListener('change', function () {
      var opt = pilihGuru.options[pilihGuru.selectedIndex];
      if (pilihGuru.value === '0') { return; }
      var teks = opt.textContent.trim().split(' — ');
      nama.value = teks[0] || '';
      if (jabatan && teks[1]) { jabatan.value = teks[1]; }
    });
  }
})();
</script>
<?php admin_bawah(); ?>
