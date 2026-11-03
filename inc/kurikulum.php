<?php
/**
 * MENU "KURIKULUM" — halaman publik tiga sub menu (v1.18.0, skema v34).
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
 * Isiannya SATU panel admin: `admin/kurikulum.php` (Administrator **dan** akun Editor/Yelfis),
 * dengan tiga tab. Halaman publiknya tiga jenis menu (`kaldik`, `jadwal_mengajar`,
 * `dokumen_kurikulum`) yang dicetak oleh `halaman.php`.
 *
 * Keputusan yang dipakai (jangan diubah tanpa bertanya):
 *  1. **Kaldik** = GAMBAR (JPG/PNG/GIF/WEBP/SVG) yang diunggah lewat media proxy; boleh lebih dari
 *     satu gambar (mis. per semester) dan diurutkan admin. Yang disimpan hanya URL CDN.
 *  2. **Jadwal** = satu baris per (hari · waktu · jam ke · kelas). Baris yang **jam ke-nya kosong**
 *     dianggap KEGIATAN (mis. "Sholat Dhuha", "Istirahat", "Sholat Dhuhur") dan dicetak sebagai satu
 *     baris yang menggabung seluruh kolom kelas — persis contoh gambar yang dikirim pemilik.
 *     Kolom kelas diambil dari menu **Data Kelas** (ditambah kelas yang muncul pada jadwal hari itu).
 *  3. **Dokumen** = berkas apa pun dari `KURIKULUM_EKSTENSI`; tampil sebagai kartu dan diunduh lewat
 *     `kurikulum_unduh.php` yang **mengalirkan berkas dari server** (URL media tidak pernah dikirim
 *     ke peramban, dan nama unduhannya rapi).
 *  4. Halaman jadwal **DICETAK MENDATAR (A4 landscape)** — `@page` ditulis pada `halaman.php`
 *     (bukan hanya di tema), pola yang sama dengan halaman Informasi BOS.
 *
 * Gaya `.kal-*`, `.jdw-*`, dan `.dok-*` ada pada `assets/style.css` — **jangan** menaruh `<style>`
 * di halaman.
 */
declare(strict_types=1);

// =====================================================================================
// KALDIK OPERASIONAL — gambar Kalender Pendidikan
// =====================================================================================

/** Daftar gambar kalender pendidikan (urut `urutan, id`). */
function kurikulum_kaldik_list(bool $aktifSaja = false): array
{
    return all('SELECT * FROM kurikulum_kaldik' . ($aktifSaja ? ' WHERE aktif = 1' : '')
        . ' ORDER BY urutan, id');
}

/** Satu gambar kalender pendidikan (null bila tidak ada). */
function kurikulum_kaldik_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM kurikulum_kaldik WHERE id = ?', [$id]) : null;
}

// =====================================================================================
// JADWAL MENGAJAR
// =====================================================================================

/** Hari yang boleh dipakai pada jadwal (urutan tampil mengikuti `KURIKULUM_HARI`). */
function kurikulum_hari_list(): array
{
    return KURIKULUM_HARI;
}

/** Apakah tulisan hari ini salah satu hari yang sah? */
function kurikulum_hari_sah(string $hari): bool
{
    return in_array(trim($hari), KURIKULUM_HARI, true);
}

/**
 * Hari yang SEDANG dilihat pengunjung: `?hari=Sabtu` (nama hari yang sah) atau, bila kosong/tidak
 * dikenal, hari ini (bila ada jadwalnya) → hari pertama yang punya jadwal → hari pertama daftar.
 */
function kurikulum_hari_pilih(): string
{
    $minta = trim((string) ($_GET['hari'] ?? ''));
    if (kurikulum_hari_sah($minta)) {
        return $minta;
    }
    $ada = kurikulum_jadwal_hari_jumlah();
    $hariIni = HARI_ID[date('l')] ?? '';
    if ($hariIni !== '' && !empty($ada[$hariIni])) {
        return $hariIni;
    }
    foreach (KURIKULUM_HARI as $h) {
        if (!empty($ada[$h])) {
            return $h;
        }
    }
    return KURIKULUM_HARI[0];
}

/** Jumlah baris jadwal per hari: `['Sabtu' => 10, …]` (hanya hari yang BERISI). */
function kurikulum_jadwal_hari_jumlah(): array
{
    $keluar = [];
    foreach (all('SELECT hari, COUNT(*) AS n FROM kurikulum_jadwal GROUP BY hari') as $r) {
        $hari = (string) ($r['hari'] ?? '');
        if (kurikulum_hari_sah($hari)) {
            $keluar[$hari] = (int) $r['n'];
        }
    }
    return $keluar;
}

/** Baris jadwal satu hari (urut `urutan, id`). */
function kurikulum_jadwal_list(string $hari = ''): array
{
    if ($hari === '') {
        return all('SELECT * FROM kurikulum_jadwal ORDER BY urutan, id');
    }
    return all('SELECT * FROM kurikulum_jadwal WHERE hari = ? ORDER BY urutan, id', [$hari]);
}

/**
 * WAKTU TERAKHIR setiap hari — dasar AUTO-ISI kolom Waktu pada formulir "Tambah Baris Jadwal".
 *
 * Permintaan pemilik (v1.19.1): *"Bagian input waktu buat otomatis mengikuti hari yang sama
 * waktunya. agar penginputan menjadi lebih cepat dan tidak input manual waktu satu persatu"*.
 *
 * Jadi saat pengelola menambah baris jadwal untuk sebuah hari, kolom **Waktu** (dan **Jam Ke**)
 * langsung terisi dengan waktu baris TERAKHIR hari itu — tidak perlu mengetik ulang waktu yang sama
 * untuk setiap baris. Bila Hari diganti pada formulir, isiannya ikut berganti (skrip
 * `initJadwalWaktu()` pada `admin/admin.js`).
 *
 * @return array<string,array{waktu:string,jam_ke:int,daftar:array<int,string>}>
 *   Kunci = nama hari (`KURIKULUM_HARI`). `waktu`/`jam_ke` = baris terakhir hari itu (urutan
 *   terbesar; baris tanpa waktu dilewati), `daftar` = semua waktu berbeda pada hari itu menurut
 *   urutan kemunculan (dipakai sebagai SARAN isian `<datalist>`).
 */
function kurikulum_jadwal_waktu_hari(): array
{
    $keluar = [];
    foreach (all('SELECT hari, waktu, jam_ke FROM kurikulum_jadwal ORDER BY hari, urutan, id') as $r) {
        $hari = (string) ($r['hari'] ?? '');
        if (!kurikulum_hari_sah($hari)) {
            continue;
        }
        $waktu = trim((string) ($r['waktu'] ?? ''));
        if ($waktu === '') {
            continue;
        }
        if (!isset($keluar[$hari])) {
            $keluar[$hari] = ['waktu' => '', 'jam_ke' => 0, 'daftar' => []];
        }
        // Terus ditimpa selama urutannya naik → nilai akhir = baris TERAKHIR hari itu.
        $keluar[$hari]['waktu'] = $waktu;
        $keluar[$hari]['jam_ke'] = max(0, (int) ($r['jam_ke'] ?? 0));
        if (!in_array($waktu, $keluar[$hari]['daftar'], true)) {
            $keluar[$hari]['daftar'][] = $waktu;
        }
    }
    return $keluar;
}

/**
 * Nama kelas untuk KOLOM tabel jadwal satu hari: seluruh kelas aktif dari menu Data Kelas (urut
 * `urutan, nama`) ditambah kelas yang muncul pada jadwal hari itu tetapi belum ada di Data Kelas
 * (mis. kelas yang sudah dihapus — datanya jangan sampai hilang dari tampilan).
 */
function kurikulum_jadwal_kelas_kolom(string $hari = ''): array
{
    $keluar = [];
    foreach (kelas_publik() as $k) {
        $nama = trim((string) ($k['nama'] ?? ''));
        if ($nama !== '' && !in_array($nama, $keluar, true)) {
            $keluar[] = $nama;
        }
    }
    $sumber = $hari === '' ? all('SELECT DISTINCT kelas FROM kurikulum_jadwal') : all(
        "SELECT DISTINCT kelas FROM kurikulum_jadwal WHERE hari = ?", [$hari]);
    foreach ($sumber as $r) {
        $nama = trim((string) ($r['kelas'] ?? ''));
        if ($nama !== '' && !in_array($nama, $keluar, true)) {
            $keluar[] = $nama;
        }
    }
    return $keluar;
}

/**
 * Susun baris jadwal satu hari menjadi SLOT (satu baris tabel = satu waktu).
 *
 * Satu slot dikenali dari (waktu, jam ke). Baris dengan jam ke kosong ATAU kelas kosong dianggap
 * KEGIATAN: labelnya diambil dari kolom `mapel` dan pada tabel dicetak menggabung semua kolom kelas
 * (mis. "Sholat Dhuha", "Istirahat", "Sholat Dhuhur").
 *
 * @return array{kelas:array<int,string>,slot:array<int,array{waktu:string,jam_ke:int,kegiatan:bool,label:string,sel:array<string,array{mapel:string,guru:string}>}>}
 */
function kurikulum_jadwal_susun(string $hari = ''): array
{
    $slot = [];
    foreach (kurikulum_jadwal_list($hari) as $b) {
        $waktu = trim((string) ($b['waktu'] ?? ''));
        $jam = (int) ($b['jam_ke'] ?? 0);
        $kelas = trim((string) ($b['kelas'] ?? ''));
        $mapel = trim((string) ($b['mapel'] ?? ''));
        $guru = trim((string) ($b['guru'] ?? ''));
        $kunci = strtolower($waktu) . '|' . $jam;
        if (!isset($slot[$kunci])) {
            $slot[$kunci] = ['waktu' => $waktu, 'jam_ke' => $jam, 'kegiatan' => false,
                'label' => '', 'sel' => []];
        }
        if ($jam <= 0 || $kelas === '') {
            $slot[$kunci]['kegiatan'] = true;
            $slot[$kunci]['jam_ke'] = 0;
            if ($mapel !== '') {
                $slot[$kunci]['label'] = $mapel;
            }
            continue;
        }
        $slot[$kunci]['sel'][$kelas] = ['mapel' => $mapel, 'guru' => $guru];
    }
    return [
        'hari'  => $hari,
        'kelas' => kurikulum_jadwal_kelas_kolom($hari),
        'slot'  => array_values($slot),
    ];
}

/** Jam ke dalam bentuk tampilan: angka, atau "" untuk baris kegiatan. */
function kurikulum_jam_teks(int $jam): string
{
    return $jam > 0 ? (string) $jam : '';
}

// =====================================================================================
// DOKUMEN KURIKULUM
// =====================================================================================

/** Daftar dokumen kurikulum (urut `urutan, id`). */
function kurikulum_dokumen_list(bool $aktifSaja = false): array
{
    return all('SELECT * FROM kurikulum_dokumen' . ($aktifSaja ? ' WHERE aktif = 1' : '')
        . ' ORDER BY urutan, id');
}

/** Satu dokumen kurikulum (null bila tidak ada). */
function kurikulum_dokumen_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM kurikulum_dokumen WHERE id = ?', [$id]) : null;
}

/** Ekstensi berkas (dari kolom `jenis`, cadangan dari nama/URL). */
function kurikulum_dokumen_ext(array $d): string
{
    $ext = strtolower(trim((string) ($d['jenis'] ?? '')));
    if ($ext !== '' && preg_match('/^[a-z0-9]{2,5}$/', $ext)) {
        return $ext;
    }
    foreach ([(string) ($d['nama_berkas'] ?? ''), (string) parse_url((string) ($d['url'] ?? ''), PHP_URL_PATH)] as $s) {
        $x = strtolower(pathinfo($s, PATHINFO_EXTENSION));
        if ($x !== '' && preg_match('/^[a-z0-9]{2,5}$/', $x)) {
            return $x;
        }
    }
    return 'pdf';
}

/** Label jenis berkas: "PDF", "Word", "Excel", "PowerPoint", "Gambar". */
function kurikulum_dokumen_jenis_teks(string $ext): string
{
    return [
        'pdf' => 'PDF',
        'doc' => 'Word', 'docx' => 'Word',
        'xls' => 'Excel', 'xlsx' => 'Excel',
        'ppt' => 'PowerPoint', 'pptx' => 'PowerPoint',
        'csv' => 'CSV', 'txt' => 'Teks', 'zip' => 'Arsip ZIP',
        'jpg' => 'Gambar', 'jpeg' => 'Gambar', 'png' => 'Gambar', 'webp' => 'Gambar',
    ][strtolower($ext)] ?? strtoupper($ext);
}

/** Nama ikon kartu dokumen menurut jenis berkasnya. */
function kurikulum_dokumen_ikon(string $ext): string
{
    $ext = strtolower($ext);
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return 'gambar';
    }
    if (in_array($ext, ['xls', 'xlsx'], true)) {
        return 'grafik';
    }
    if (in_array($ext, ['ppt', 'pptx'], true)) {
        return 'papan';
    }
    if (in_array($ext, ['csv'], true)) {
        return 'grafik';
    }
    if (in_array($ext, ['zip'], true)) {
        return 'papan';
    }
    if ($ext === 'pdf') {
        return 'sertifikat';
    }
    return 'book';
}

/** Nama berkas unduhan yang rapi: "<Judul>.<ext>". */
function kurikulum_dokumen_nama_unduh(array $d): string
{
    $ext = kurikulum_dokumen_ext($d);
    $nama = trim((string) ($d['nama_berkas'] ?? ''));
    if ($nama === '') {
        $nama = trim((string) ($d['judul'] ?? '')) !== '' ? (string) $d['judul'] : 'dokumen-kurikulum';
        $nama .= '.' . $ext;
    }
    if (strtolower((string) pathinfo($nama, PATHINFO_EXTENSION)) !== $ext) {
        $nama .= '.' . $ext;
    }
    // Nama berkas tidak boleh memuat pemisah jalur / kutip / baris baru (header HTTP).
    $nama = str_replace(['/', '\\', "\r", "\n", '"'], '-', $nama);
    return trim($nama) !== '' ? trim($nama) : ('dokumen-kurikulum.' . $ext);
}

/** Tautan unduhan dokumen (dialirkan server, bukan URL media langsung). */
function kurikulum_dokumen_tautan(array $d): string
{
    return rute_tautan('kurikulum_unduh.php', ['id' => (int) ($d['id'] ?? 0)]);
}

/**
 * Alirkan berkas dari URL media ke peramban pengunjung dengan nama unduhan yang rapi.
 * @return bool true bila berkas sudah terkirim
 */
function kurikulum_kirim_berkas(string $url, string $namaUnduh): bool
{
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return false;
    }
    $isi = false;
    $kode = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'Website-Sekolah/1.0 (+Dokumen-Kurikulum)',
        ]);
        $isi = curl_exec($ch);
        $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => true]]);
        $isi = @file_get_contents($url, false, $ctx);
        $kode = $isi === false ? 0 : 200;
    }
    if ($isi === false || $isi === '' || ($kode !== 0 && ($kode < 200 || $kode >= 300))) {
        return false;
    }

    $ext = strtolower((string) pathinfo($namaUnduh, PATHINFO_EXTENSION));
    $jenis = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    ][$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $jenis);
    header('Content-Length: ' . strlen($isi));
    header('Content-Disposition: attachment; filename="' . $namaUnduh . '"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $isi;
    return true;
}

// =====================================================================================
// HALAMAN PUBLIK
// =====================================================================================

/** HALAMAN "KALDIK OPERASIONAL" — gambar Kalender Pendidikan. */
function kurikulum_kaldik_papan(): void
{
    $daftar = kurikulum_kaldik_list(true);
    ?>
    <div class="kal-blok" id="kurikulumKaldik">
        <div class="kartu-kepala kal-kepala">
            <div>
                <h2>Kalender Pendidikan Operasional</h2>
                <p class="mut kecil mb0">Kalender pendidikan yang dipakai madrasah pada tahun ajaran berjalan.</p>
            </div>
            <?php if ($daftar): ?>
                <button type="button" class="tombol tombol-garis tombol-kecil" data-cetak>
                    <?= ikon('cetak', 16) ?> Cetak
                </button>
            <?php endif; ?>
        </div>

        <?php if (!$daftar): ?>
            <div class="kal-kosong">
                <?= ikon('kalender', 30) ?>
                <p>Kalender pendidikan belum diunggah. Silakan hubungi pengelola madrasah.</p>
            </div>
        <?php else: ?>
            <div class="kal-grid">
                <?php foreach ($daftar as $k): ?>
                    <figure class="kal-item" id="kaldik-<?= (int) $k['id'] ?>">
                        <?php $gambar = trim((string) ($k['gambar'] ?? '')); ?>
                        <?php if ($gambar !== ''): ?>
                            <a class="kal-gambar" href="<?= e($gambar) ?>" target="_blank" rel="noopener noreferrer"
                               title="Buka gambar ukuran penuh">
                                <img src="<?= e($gambar) ?>" alt="<?= e($k['judul'] !== '' ? $k['judul'] : 'Kalender Pendidikan') ?>"
                                     loading="lazy">
                            </a>
                        <?php else: ?>
                            <span class="kal-gambar kal-gambar-kosong"><?= ikon('kalender', 34) ?></span>
                        <?php endif; ?>
                        <figcaption class="kal-teks">
                            <strong><?= e($k['judul'] !== '' ? $k['judul'] : 'Kalender Pendidikan') ?></strong>
                            <?php if (trim((string) $k['keterangan']) !== ''): ?>
                                <span class="kal-ket"><?= e((string) $k['keterangan']) ?></span>
                            <?php endif; ?>
                            <?php if ($gambar !== ''): ?>
                                <a class="kal-tautan" href="<?= e($gambar) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= ikon('lihat', 15) ?> Lihat ukuran penuh
                                </a>
                            <?php endif; ?>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/** HALAMAN "JADWAL MENGAJAR" — tabel jadwal per hari (mendatar saat dicetak). */
function kurikulum_jadwal_papan(): void
{
    $hari = kurikulum_hari_pilih();
    $jumlah = kurikulum_jadwal_hari_jumlah();
    $susun = kurikulum_jadwal_susun($hari);
    $kelas = $susun['kelas'];
    $slot = $susun['slot'];
    $nKelas = count($kelas);
    $adaKelas = $nKelas > 0;
    if (!$adaKelas) {
        $kelas = ['Kelas'];
        $nKelas = 1;
    }
    $total = count($slot);
    ?>
    <div class="jdw-blok" id="kurikulumJadwal">
        <div class="jdw-filter" id="jdwFilter">
            <span class="jdw-filter-judul">Filter Hari</span>
            <div class="jdw-filter-hari">
                <?php foreach (KURIKULUM_HARI as $h): ?>
                    <?php $n = (int) ($jumlah[$h] ?? 0); ?>
                    <a class="jdw-chip<?= $h === $hari ? ' on' : '' ?><?= $n === 0 ? ' kosong' : '' ?>"
                       href="<?= e(rute_tautan(rute_kanonik(), ['hari' => $h])) ?>#tblJadwal"
                       <?= $h === $hari ? 'aria-current="true"' : '' ?>>
                        <?= e($h) ?><?php if ($n > 0): ?><span class="jdw-chip-n"><?= $n ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <button type="button" class="tombol tombol-garis tombol-kecil jdw-cetak" data-cetak>
                <?= ikon('cetak', 16) ?> Cetak
            </button>
        </div>

        <div class="jdw-kartu">
            <h2 class="jdw-judul">Jadwal Mengajar — Hari <?= e($hari) ?></h2>
            <?php if (!$slot): ?>
                <div class="jdw-kosong">
                    <?= ikon('jam', 30) ?>
                    <p>Belum ada jadwal mengajar untuk hari <strong><?= e($hari) ?></strong>.</p>
                    <p class="mut kecil mb0">Silakan pilih hari lain pada tombol Filter Hari di atas.</p>
                </div>
            <?php else: ?>
                <div class="tabel-wrap jdw-wrap">
                    <table class="tabel-jadwal" id="tblJadwal">
                        <thead>
                            <tr>
                                <th class="jdw-c-hari" rowspan="2" scope="col">Hari</th>
                                <th class="jdw-c-waktu" rowspan="2" scope="col">Waktu</th>
                                <th class="jdw-c-jam" rowspan="2" scope="col">Jam Ke</th>
                                <th class="jdw-c-kelas" colspan="<?= (int) $nKelas ?>" scope="colgroup">Kelas</th>
                            </tr>
                            <tr>
                                <?php foreach ($kelas as $nm): ?>
                                    <th class="jdw-c-kelas-nama" scope="col"><?= e($nm) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 0; foreach ($slot as $s): $i++; ?>
                                <?php if (!empty($s['kegiatan'])): ?>
                                    <tr class="jdw-baris-kegiatan">
                                        <?php if ($i === 1): ?>
                                            <th class="jdw-hari" scope="rowgroup" rowspan="<?= (int) $total ?>"><?= e($hari) ?></th>
                                        <?php endif; ?>
                                        <td class="jdw-waktu"><?= e($s['waktu']) ?></td>
                                        <td class="jdw-jam"></td>
                                        <td class="jdw-kegiatan" colspan="<?= (int) $nKelas ?>">
                                            <?= e($s['label'] !== '' ? $s['label'] : 'Kegiatan') ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <?php if ($i === 1): ?>
                                            <th class="jdw-hari" scope="rowgroup" rowspan="<?= (int) $total ?>"><?= e($hari) ?></th>
                                        <?php endif; ?>
                                        <td class="jdw-waktu"><?= e($s['waktu']) ?></td>
                                        <td class="jdw-jam"><?= e(kurikulum_jam_teks((int) $s['jam_ke'])) ?></td>
                                        <?php foreach ($kelas as $nm): ?>
                                            <?php $sel = $s['sel'][$nm] ?? null; ?>
                                            <td class="jdw-sel">
                                                <?php if ($sel !== null && ($sel['mapel'] !== '' || $sel['guru'] !== '')): ?>
                                                    <span class="jdw-mapel"><?= e($sel['mapel']) ?></span>
                                                    <?php if ($sel['guru'] !== ''): ?>
                                                        <span class="jdw-guru">(<?= e($sel['guru']) ?>)</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="mut">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="jdw-catatan">
                    <?= (int) $total ?> baris jadwal · <?= (int) $nKelas ?> kelas ·
                    ditampilkan per hari. Gunakan tombol <strong>Filter Hari</strong> untuk melihat hari lain.
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/** HALAMAN "DOKUMEN KURIKULUM" — kartu dokumen yang dapat diunduh pengunjung. */
function kurikulum_dokumen_papan(): void
{
    $daftar = kurikulum_dokumen_list(true);
    ?>
    <div class="dok-blok" id="kurikulumDokumen">
        <?php if (!$daftar): ?>
            <div class="dok-kosong">
                <?= ikon('book', 30) ?>
                <p>Belum ada dokumen kurikulum yang dapat diunduh.</p>
            </div>
        <?php else: ?>
            <div class="dok-grid">
                <?php foreach ($daftar as $d): ?>
                    <?php
                    $ext = kurikulum_dokumen_ext($d);
                    $jenis = kurikulum_dokumen_jenis_teks($ext);
                    $judul = trim((string) $d['judul']) !== '' ? (string) $d['judul'] : 'Dokumen Kurikulum';
                    $ukuran = (int) ($d['ukuran'] ?? 0);
                    ?>
                    <article class="dok-kartu dok-<?= e($ext) ?>" id="dokumen-<?= (int) $d['id'] ?>">
                        <div class="dok-ikon" aria-hidden="true">
                            <?= ikon(kurikulum_dokumen_ikon($ext), 26) ?>
                            <span class="dok-ext"><?= e(strtoupper($ext)) ?></span>
                        </div>
                        <div class="dok-isi">
                            <h3><?= e($judul) ?></h3>
                            <?php if (trim((string) $d['keterangan']) !== ''): ?>
                                <p class="dok-ket"><?= nl2br(e((string) $d['keterangan'])) ?></p>
                            <?php endif; ?>
                            <div class="dok-kaki">
                                <span class="dok-meta">
                                    <?= e($jenis) ?><?php if ($ukuran > 0): ?> &middot; <?= e(ukuran_teks($ukuran)) ?><?php endif; ?>
                                </span>
                                <?php if (trim((string) $d['url']) !== ''): ?>
                                    <a class="tombol tombol-kecil dok-unduh" href="<?= e(kurikulum_dokumen_tautan($d)) ?>">
                                        <?= ikon('unduh', 16) ?> Unduh
                                    </a>
                                <?php else: ?>
                                    <span class="dok-belum">Berkas belum diunggah</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
