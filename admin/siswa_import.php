<?php
/**
 * Impor data siswa dari Excel/CSV (kompatibel dengan Ekspor Data Siswa e-GURU).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

const SISWA_KOLOM_PETA = [
    'nama'          => ['nama lengkap', 'nama siswa', 'nama'],
    'nis'           => ['nis'],
    'nisn'          => ['nisn'],
    'jenis_kelamin' => ['jenis kelamin', 'jk', 'gender', 'l p'],
    'tempat_lahir'  => ['tempat lahir'],
    'tanggal_lahir' => ['tanggal lahir', 'tgl lahir'],
    'kelas'         => ['kelas', 'rombel'],
    'tanggal_masuk' => ['tanggal daftar', 'tanggal masuk', 'tgl masuk'],
    'nama_ayah'     => ['nama ayah', 'ayah'],
    'nama_ibu'      => ['nama ibu', 'ibu'],
    'wali'          => ['nama wali', 'wali'],
    'telepon'       => ['telepon', 'no hp', 'nomor hp', 'hp', 'whatsapp', 'kontak'],
    'alamat'        => ['alamat'],
    'status'        => ['status siswa', 'status'],
];

function csv_ke_baris_siswa(string $teks): array
{
    $teks = str_replace("\r\n", "\n", str_replace("\r", "\n", $teks));
    $baris = [];
    foreach (explode("\n", $teks) as $b) {
        if (trim($b) === '') {
            continue;
        }
        $pemisah = ';';
        if (substr_count($b, ',') > substr_count($b, ';')) {
            $pemisah = ',';
        }
        if (substr_count($b, "\t") > substr_count($b, $pemisah)) {
            $pemisah = "\t";
        }
        $baris[] = array_map('trim', str_getcsv($b, $pemisah));
    }
    return $baris;
}

$laporan = null;
$galat = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $perbarui = !empty($_POST['perbarui']);
    $baris = [];
    $namaBerkas = 'tempel-teks';

    if (trim((string) ($_POST['data'] ?? '')) !== '') {
        $data = json_decode((string) $_POST['data'], true);
        if (is_array($data)) {
            $baris = $data;
        }
    } elseif (trim((string) ($_POST['csv'] ?? '')) !== '') {
        $baris = csv_ke_baris_siswa((string) $_POST['csv']);
        $namaBerkas = 'tempel-csv';
    }
    $baris = array_values(array_filter($baris, static fn ($r): bool => is_array($r) && count(array_filter($r, static fn ($c): bool => trim((string) $c) !== '')) > 0));

    if (count($baris) < 2) {
        $galat = 'Data yang dibaca kurang dari dua baris (judul kolom + data).';
    } else {
        $peta = tebak_kolom(array_map(static fn ($c): string => (string) $c, $baris[0]), SISWA_KOLOM_PETA);
        if (!isset($peta['nama'])) {
            $galat = 'Kolom “Nama” tidak ditemukan. Pastikan baris pertama berisi judul kolom '
                . '(mis. Nama, NIS, NISN, Jenis Kelamin, Kelas, Tanggal Lahir, Nama Wali, Telepon, Alamat, Status Siswa).';
        } else {
            $tambah = 0;
            $ubah = 0;
            $lewat = 0;
            $catatan = [];
            $petaKelas = [];
            foreach (all('SELECT id, nama FROM kelas') as $k) {
                $petaKelas[strtolower(trim((string) $k['nama']))] = (int) $k['id'];
            }
            foreach (array_slice($baris, 1) as $n => $r) {
                $nama = impor_nilai($r, $peta['nama'] ?? null);
                if ($nama === '') {
                    $lewat++;
                    $catatan[] = 'Baris ' . ($n + 2) . ': nama kosong, dilewati.';
                    continue;
                }
                $nis = impor_nilai($r, $peta['nis'] ?? null);
                $nisn = impor_nilai($r, $peta['nisn'] ?? null);
                $namaKelas = impor_nilai($r, $peta['kelas'] ?? null);
                $kelasId = 0;
                if ($namaKelas !== '') {
                    $kunci = strtolower(trim($namaKelas));
                    if (!isset($petaKelas[$kunci])) {
                        $namaKelasBersih = potong($namaKelas, 60, '');
                        q('INSERT INTO kelas (nama, tingkat, wali, aktif, urutan) VALUES (?, ?, ?, 1, ?)',
                            [$namaKelasBersih, $namaKelasBersih, '', urutan_baru('kelas')]);
                        $petaKelas[$kunci] = id_baru();
                        $catatan[] = 'Kelas “' . $namaKelasBersih . '” dibuat otomatis (belum ada pada Data Kelas).';
                    }
                    $kelasId = $petaKelas[$kunci];
                }
                $status = status_normal(impor_nilai($r, $peta['status'] ?? null), [
                    'lulus'   => ['lulus', 'alumni', 'tamat'],
                    'keluar'  => ['keluar', 'pindah', 'mutasi', 'drop'],
                    'nonaktif' => ['nonaktif', 'tidak aktif'],
                    'aktif'   => ['aktif', 'ya'],
                ], 'aktif');
                $data = [
                    'nis'           => potong($nis === '-' ? '' : $nis, 30, ''),
                    'nisn'          => potong($nisn === '-' ? '' : $nisn, 30, ''),
                    'nama'          => potong($nama, NAMA_MAKS, ''),
                    'jenis_kelamin' => jk_normal(impor_nilai($r, $peta['jenis_kelamin'] ?? null)),
                    'tempat_lahir'  => potong(impor_nilai($r, $peta['tempat_lahir'] ?? null), 80, ''),
                    'tanggal_lahir' => tgl_impor(impor_nilai($r, $peta['tanggal_lahir'] ?? null)),
                    'kelas_id'      => $kelasId,
                    'nama_ayah'     => potong(impor_nilai($r, $peta['nama_ayah'] ?? null), NAMA_MAKS, ''),
                    'nama_ibu'      => potong(impor_nilai($r, $peta['nama_ibu'] ?? null), NAMA_MAKS, ''),
                    'wali'          => potong(impor_nilai($r, $peta['wali'] ?? null), NAMA_MAKS, ''),
                    'telepon'       => potong(impor_nilai($r, $peta['telepon'] ?? null), 40, ''),
                    'alamat'        => potong(impor_nilai($r, $peta['alamat'] ?? null), 300, ''),
                    'status'        => $status,
                    'tahun_lulus'   => $status === 'lulus' ? date('Y') : '',
                ];
                $data['tanggal_masuk'] = tgl_impor(impor_nilai($r, $peta['tanggal_masuk'] ?? null)) ?: date('Y-m-d');

                $lama = null;
                if ($data['nisn'] !== '') {
                    $lama = one('SELECT * FROM siswa WHERE nisn = ?', [$data['nisn']]);
                }
                if (!$lama && $data['nis'] !== '') {
                    $lama = one('SELECT * FROM siswa WHERE nis = ?', [$data['nis']]);
                }
                if (!$lama) {
                    $lama = one('SELECT * FROM siswa WHERE nama = ?', [$data['nama']]);
                }
                if ($lama) {
                    if ($perbarui) {
                        // Kelas & wali yang sudah ada tidak dikosongkan bila berkas tidak memuatnya.
                        if ($data['kelas_id'] === 0) {
                            $data['kelas_id'] = (int) $lama['kelas_id'];
                        }
                        if ($data['wali'] === '') {
                            $data['wali'] = (string) $lama['wali'];
                        }
                        if ($data['nama_ayah'] === '') {
                            $data['nama_ayah'] = (string) $lama['nama_ayah'];
                        }
                        if ($data['nama_ibu'] === '') {
                            $data['nama_ibu'] = (string) $lama['nama_ibu'];
                        }
                        $set = [];
                        $val = [];
                        foreach ($data as $k => $v) {
                            $set[] = $k . ' = ?';
                            $val[] = $v;
                        }
                        $val[] = (int) $lama['id'];
                        q('UPDATE siswa SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
                        $ubah++;
                    } else {
                        $lewat++;
                    }
                } else {
                    $data['foto'] = '';
                    $data['dibuat'] = date('Y-m-d H:i:s');
                    $kol = array_keys($data);
                    q('INSERT INTO siswa (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
                    $tambah++;
                }
            }
            $laporan = ['berkas' => $namaBerkas, 'baris' => count($baris) - 1, 'tambah' => $tambah, 'ubah' => $ubah,
                'lewat' => $lewat, 'catatan' => array_slice(array_unique($catatan), 0, 30), 'kolom' => $peta];
        }
    }
}

admin_atas('Impor Data Siswa');
?>
<div class="kartu mb24">
    <div class="kartu_kepala kartu-kepala">
        <div>
            <h2>Impor Data Siswa</h2>
            <p class="mut kecil mb0">Unggah berkas Excel (.xlsx/.xls) atau CSV. Berkas dibaca di peramban Anda (tidak ada data yang dikirim ke pihak lain).</p>
        </div>
        <a class="tombol tombol-garis tombol-kecil" href="siswa.php"><?= ikon('panah-kiri', 15) ?> Daftar Siswa</a>
    </div>

    <?php if ($galat !== ''): ?><div class="alert alert-err"><?= e($galat) ?></div><?php endif; ?>

    <?php if ($laporan): ?>
        <div class="alert alert-ok">
            Impor selesai dari <strong><?= e($laporan['berkas']) ?></strong> (<?= angka($laporan['baris']) ?> baris):
            <strong><?= angka($laporan['tambah']) ?></strong> siswa baru,
            <strong><?= angka($laporan['ubah']) ?></strong> diperbarui,
            <strong><?= angka($laporan['lewat']) ?></strong> dilewati.
        </div>
        <?php if ($laporan['catatan']): ?>
            <div class="kotak-info"><strong>Catatan:</strong>
                <ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($laporan['catatan'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <div class="aksi-form"><a class="tombol tombol-kecil" href="siswa.php">Lihat Daftar Siswa</a></div>
    <?php endif; ?>

    <div class="kotak-info mb16">
        <strong>Kolom yang didukung</strong> (urutan bebas): <em>Nama</em>, <em>NIS</em>, <em>NISN</em>,
        <em>Jenis Kelamin</em>, <em>Tempat Lahir</em>, <em>Tanggal Lahir</em>, <em>Kelas</em>,
        <em>Tanggal Daftar</em>, <em>Nama Ayah</em>, <em>Nama Ibu</em>, <em>Nama Wali</em>,
        <em>Telepon</em>, <em>Alamat</em>, <em>Status Siswa</em>.
        Berkas hasil <strong>Ekspor Data Siswa e-GURU</strong> dapat langsung diimpor (kolom <em>Jenis Anggota</em> diabaikan).
        Kelas yang belum ada akan dibuat otomatis; tanggal boleh berbentuk 12/04/2011, 2011-04-12, atau 12 April 2011.
    </div>

    <form method="post" action="siswa_import.php" id="formImporSiswa">
        <?= csrf_field() ?>
        <textarea name="data" id="dataImporSiswa" hidden></textarea>
        <div class="form-2">
            <div class="form-baris">
                <label for="berkasSiswa">Pilih Berkas Excel/CSV</label>
                <input type="file" id="berkasSiswa" accept=".xlsx,.xls,.csv,.txt">
                <div class="bantuan">Baris pertama harus berisi judul kolom.</div>
            </div>
            <div class="form-baris">
                <label>Pilihan</label>
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="perbarui" value="1"> Perbarui data yang sudah ada (dicocokkan dari NISN, NIS, lalu nama)
                </label>
                <div class="bantuan">Bila tidak dicentang, siswa yang sudah ada dilewati.</div>
            </div>
        </div>

        <h3>Pratinjau</h3>
        <div class="pratinjau-impor" id="pratinjauSiswa"><p class="mut kecil" style="padding:12px">Belum ada data dibaca.</p></div>

        <details class="mt16" style="margin-top:14px">
            <summary class="kecil mut">Cara lain: tempel data CSV</summary>
            <div class="form-baris mt12">
                <label for="csvSiswa">Tempel data (pemisah koma, titik koma, atau tab)</label>
                <textarea id="csvSiswa" name="csv" style="min-height:140px" placeholder="Nama;NIS;Kelas&#10;Ahmad Fauzi;2026001;VII"></textarea>
            </div>
        </details>

        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('unggah', 17) ?> Impor Sekarang</button>
            <a class="tombol tombol-garis" href="siswa_export.php"><?= ikon('unduh', 16) ?> Unduh format/contoh</a>
        </div>
    </form>
</div>

<script src="../assets/vendor/xlsx.full.min.js?v=<?= APP_VERSION ?>"></script>
<script>
(function () {
  var input = document.getElementById('berkasSiswa');
  var keluar = document.getElementById('pratinjauSiswa');
  var data = document.getElementById('dataImporSiswa');
  if (!input) { return; }
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
        html += '</tbody></table>';
        html += '<p class="kecil mut" style="padding:8px 12px">' + (baris.length - 1) + ' baris data dibaca dari ' + nama + '.</p>';
        keluar.innerHTML = html;
      }
    });
  });
})();
</script>
<?php admin_bawah(); ?>
