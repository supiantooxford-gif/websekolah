<?php
/**
 * Impor data guru & tenaga kependidikan dari Excel/CSV.
 * Berkas dibaca DI PERAMBAN (SheetJS) lalu dikirim ke server sebagai JSON,
 * sehingga tidak perlu pustaka Excel di server. Tersedia juga cara tempel CSV.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

const GURU_KOLOM_PETA = [
    'nama'          => ['nama', 'nama lengkap', 'nama guru', 'nama dan gelar'],
    'nip'           => ['nip nuptk', 'nip', 'nuptk', 'nip nik'],
    'jenis_kelamin' => ['jenis kelamin', 'jk', 'gender', 'l p'],
    'jabatan'       => ['jabatan', 'tugas', 'posisi'],
    'mapel'         => ['mata pelajaran', 'mapel', 'bidang', 'kelas diajar'],
    'telepon'       => ['telepon', 'no hp', 'nomor hp', 'hp', 'whatsapp', 'kontak'],
    'email'         => ['email', 'surel'],
    'status'        => ['status', 'keadaan'],
];

$IKON_GURU = 'book';

/** Ubah teks CSV tempel menjadi larik baris. */
function csv_ke_baris(string $teks): array
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
        $baris = csv_ke_baris((string) $_POST['csv']);
        $namaBerkas = 'tempel-csv';
    }
    $baris = array_values(array_filter($baris, static fn ($r): bool => is_array($r) && count(array_filter($r, static fn ($c): bool => trim((string) $c) !== '')) > 0));

    if (count($baris) < 2) {
        $galat = 'Data yang dibaca kurang dari dua baris (judul kolom + data). Periksa kembali berkas/tempelan Anda.';
    } else {
        $header = array_map(static fn ($c): string => (string) $c, $baris[0]);
        $isi = array_slice($baris, 1);
        $peta = tebak_kolom($header, GURU_KOLOM_PETA);
        if (!isset($peta['nama'])) {
            $galat = 'Kolom “Nama” tidak ditemukan. Pastikan baris pertama berisi judul kolom (mis. Nama, NIP/NUPTK, Jabatan, Mata Pelajaran, Telepon, Email, Status).';
        } else {
            $tambah = 0;
            $ubah = 0;
            $lewat = 0;
            $catatan = [];
            foreach ($isi as $n => $r) {
                $nama = impor_nilai($r, $peta['nama'] ?? null);
                if ($nama === '') {
                    $lewat++;
                    $catatan[] = 'Baris ' . ($n + 2) . ': nama kosong, dilewati.';
                    continue;
                }
                $nip = impor_nilai($r, $peta['nip'] ?? null);
                $data = [
                    'nama'          => potong($nama, NAMA_MAKS, ''),
                    'nip'           => potong($nip === '-' ? '' : $nip, 40, ''),
                    'jenis_kelamin' => jk_normal(impor_nilai($r, $peta['jenis_kelamin'] ?? null)),
                    'jabatan'       => potong(impor_nilai($r, $peta['jabatan'] ?? null) ?: 'Guru', 80, ''),
                    'mapel'         => potong(impor_nilai($r, $peta['mapel'] ?? null), 160, ''),
                    'telepon'       => potong(impor_nilai($r, $peta['telepon'] ?? null), 40, ''),
                    'email'         => potong(impor_nilai($r, $peta['email'] ?? null), 120, ''),
                    'status'        => status_normal(impor_nilai($r, $peta['status'] ?? null), ['nonaktif' => ['nonaktif', 'tidak aktif', 'keluar', 'pensiun', 'mutasi'], 'aktif' => ['aktif', 'ya']], 'aktif'),
                ];
                $lama = null;
                if ($data['nip'] !== '') {
                    $lama = one('SELECT * FROM guru WHERE nip = ?', [$data['nip']]);
                }
                if (!$lama) {
                    $lama = one('SELECT * FROM guru WHERE nama = ?', [$data['nama']]);
                }
                if ($lama) {
                    if ($perbarui) {
                        q('UPDATE guru SET nama = ?, nip = ?, jenis_kelamin = ?, jabatan = ?, mapel = ?, telepon = ?, email = ?, status = ? WHERE id = ?',
                            [$data['nama'], $data['nip'], $data['jenis_kelamin'], $data['jabatan'], $data['mapel'],
                             $data['telepon'], $data['email'], $data['status'], (int) $lama['id']]);
                        $ubah++;
                    } else {
                        $lewat++;
                    }
                } else {
                    q('INSERT INTO guru (nama, nip, jenis_kelamin, jabatan, mapel, telepon, email, status, urutan, dibuat)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$data['nama'], $data['nip'], $data['jenis_kelamin'], $data['jabatan'], $data['mapel'],
                         $data['telepon'], $data['email'], $data['status'], urutan_baru('guru'), date('Y-m-d H:i:s')]);
                    $tambah++;
                }
            }
            $laporan = ['berkas' => $namaBerkas, 'baris' => count($isi), 'tambah' => $tambah, 'ubah' => $ubah,
                'lewat' => $lewat, 'catatan' => array_slice($catatan, 0, 30), 'kolom' => $peta];
        }
    }
}

admin_atas('Impor Data Guru');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Impor Data Guru &amp; Tenaga Kependidikan</h2>
            <p class="mut kecil mb0">Unggah berkas Excel (.xlsx/.xls) atau CSV. Berkas dibaca di peramban Anda, jadi tidak ada data yang bocor ke server lain.</p>
        </div>
        <a class="tombol tombol-garis tombol-kecil" href="guru.php"><?= ikon('panah-kiri', 15) ?> Daftar Guru</a>
    </div>

    <?php if ($galat !== ''): ?><div class="alert alert-err"><?= e($galat) ?></div><?php endif; ?>

    <?php if ($laporan): ?>
        <div class="alert alert-ok">
            Impor selesai dari <strong><?= e($laporan['berkas']) ?></strong>:
            <strong><?= angka($laporan['tambah']) ?></strong> data baru ditambahkan,
            <strong><?= angka($laporan['ubah']) ?></strong> diperbarui,
            <strong><?= angka($laporan['lewat']) ?></strong> dilewati (sudah ada / tidak lengkap).
        </div>
        <p class="kecil mut">Kolom yang dikenali: <?= e(implode(', ', array_keys($laporan['kolom']))) ?>.</p>
        <?php if ($laporan['catatan']): ?>
            <div class="kotak-info"><strong>Catatan:</strong>
                <ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($laporan['catatan'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <div class="aksi-form"><a class="tombol tombol-kecil" href="guru.php">Lihat Daftar Guru</a></div>
    <?php endif; ?>

    <div class="kotak-info mb16">
        <strong>Format kolom yang didukung</strong> (urutan bebas — dikenali dari judul kolomnya):
        <em>Nama</em>, <em>NIP/NUPTK</em>, <em>Jenis Kelamin</em>, <em>Jabatan</em>, <em>Mata Pelajaran</em>,
        <em>Telepon</em>, <em>Email</em>, <em>Status</em>.
        Berkas hasil <strong>Ekspor Data Guru e-GURU</strong> dapat diimpor langsung (kolom tambahan seperti
        <em>Jenis Anggota</em>, <em>Kelas Diajar</em>, dan <em>Akun</em> akan diabaikan).
    </div>

    <form method="post" action="guru_import.php" id="formImpor">
        <?= csrf_field() ?>
        <textarea name="data" id="dataImpor" hidden></textarea>
        <div class="form-2">
            <div class="form-baris">
                <label for="berkasGuru">Pilih Berkas Excel/CSV</label>
                <input type="file" id="berkasGuru" accept=".xlsx,.xls,.csv,.txt">
                <div class="bantuan">Baris pertama harus berisi judul kolom.</div>
            </div>
            <div class="form-baris">
                <label>Pilihan</label>
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="perbarui" value="1" id="perbaruiGuru"> Perbarui data yang sudah ada (dicocokkan dari NIP lalu nama)
                </label>
                <div class="bantuan">Bila tidak dicentang, data yang sudah ada akan dilewati.</div>
            </div>
        </div>

        <h3>Pratinjau</h3>
        <div class="pratinjau-impor" id="pratinjauGuru"><p class="mut kecil" style="padding:12px">Belum ada data dibaca.</p></div>

        <details class="mt16" style="margin-top:14px">
            <summary class="kecil mut">Cara lain: tempel data CSV</summary>
            <div class="form-baris mt12">
                <label for="csvGuru">Tempel data (pemisah koma, titik koma, atau tab)</label>
                <textarea id="csvGuru" name="csv" style="min-height:140px" placeholder="Nama;NIP/NUPTK;Jabatan;Mata Pelajaran&#10;Ahmad Fauzi;1987...;Guru;Matematika"></textarea>
            </div>
        </details>

        <div class="aksi-form">
            <button type="submit" class="tombol" id="tombolImpor"><?= ikon('unggah', 17) ?> Impor Sekarang</button>
            <a class="tombol tombol-garis" href="guru_export.php"><?= ikon('unduh', 16) ?> Unduh contoh format</a>
        </div>
    </form>
</div>

<script src="../assets/vendor/xlsx.full.min.js?v=<?= APP_VERSION ?>"></script>
<script>
(function () {
  var input = document.getElementById('berkasGuru');
  var keluar = document.getElementById('pratinjauGuru');
  var data = document.getElementById('dataImpor');
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
