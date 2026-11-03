<?php
/**
 * Penolong khusus panel admin: urutan naik/turun, hapus baris generik, dan blok unggah gambar.
 */
declare(strict_types=1);

/** Tabel yang boleh dihapus lewat penolong generik (whitelist). */
const TABEL_HAPUS_BOLEH = [
    'menu', 'slider', 'slider_kecil', 'sosial', 'keunggulan', 'program', 'berita', 'berita_kategori',
    'agenda', 'galeri_album', 'galeri_foto', 'galeri_video', 'fasilitas', 'testimoni', 'kelas',
    'guru', 'siswa', 'ppdb_gelombang', 'ppdb_pendaftar', 'kontak_pesan', 'kenaikan', 'log_masuk',
    'poll', 'poll_pilihan', 'prestasi', 'alumni',
    'struktur',   // v10 — kotak bagan Struktur Organisasi (hapus & geser urutan)
    'faq',        // v14 — FAQ asisten obrolan (hapus & geser urutan)
    'pip_penerima', // v17 — daftar penerima PIP (hapus & geser urutan)
    // v34 — MENU KURIKULUM (gambar kalender, baris jadwal, dan dokumen kurikulum).
    'kurikulum_kaldik', 'kurikulum_jadwal', 'kurikulum_dokumen',
];

/** Hapus satu baris (anti salah tabel: nama tabel diperiksa whitelist). */
function hapus_baris(string $tabel, int $id): bool
{
    if (!in_array($tabel, TABEL_HAPUS_BOLEH, true) || $id <= 0) {
        return false;
    }
    return q('DELETE FROM ' . $tabel . ' WHERE id = ?', [$id])->rowCount() > 0;
}

/**
 * Tukar posisi urutan dengan baris tetangga.
 * @param string $tabel  nama tabel (whitelist TABEL_HAPUS_BOLEH)
 * @param array  $saring pasangan kolom => nilai untuk membatasi kelompok (mis. album_id / induk_id)
 */
function geser_urutan(string $tabel, int $id, string $arah, array $saring = []): bool
{
    if (!in_array($tabel, TABEL_HAPUS_BOLEH, true) || $id <= 0) {
        return false;
    }
    $w = '';
    $p = [];
    foreach ($saring as $k => $v) {
        if (preg_match('#^[a-z_]+$#', (string) $k)) {
            $w .= ' AND ' . $k . ' = ?';
            $p[] = $v;
        }
    }
    $ini = one('SELECT id, urutan FROM ' . $tabel . ' WHERE id = ?' . $w, array_merge([$id], $p));
    if (!$ini) {
        return false;
    }
    if ($arah === 'naik') {
        $tetangga = one('SELECT id, urutan FROM ' . $tabel . ' WHERE urutan < ? AND id <> ?' . $w
            . ' ORDER BY urutan DESC, id DESC LIMIT 1', array_merge([$ini['urutan'], $id], $p));
    } else {
        $tetangga = one('SELECT id, urutan FROM ' . $tabel . ' WHERE urutan > ? AND id <> ?' . $w
            . ' ORDER BY urutan ASC, id ASC LIMIT 1', array_merge([$ini['urutan'], $id], $p));
    }
    if (!$tetangga) {
        return false;
    }
    q('UPDATE ' . $tabel . ' SET urutan = ? WHERE id = ?', [$tetangga['urutan'], $ini['id']]);
    q('UPDATE ' . $tabel . ' SET urutan = ? WHERE id = ?', [$ini['urutan'], $tetangga['id']]);
    return true;
}

/** Urutan berikutnya (kelipatan 10). */
function urutan_baru(string $tabel, array $saring = []): int
{
    $w = '';
    $p = [];
    foreach ($saring as $k => $v) {
        if (preg_match('#^[a-z_]+$#', (string) $k)) {
            $w .= ' AND ' . $k . ' = ?';
            $p[] = $v;
        }
    }
    return (int) jumlah('SELECT COALESCE(MAX(urutan), 0) FROM ' . $tabel . ' WHERE 1 = 1' . $w, $p) + 10;
}

/**
 * Urutan untuk baris BARU yang harus tampil PALING ATAS (v1.17.1 — permintaan pemilik:
 * *"Video atau link youtube yang di simpan paling terbaru letakkan otomatis paling atas"*).
 *
 * Nilainya MIN(urutan) − 10 sehingga baris baru selalu di atas yang sudah ada, sedangkan tombol
 * ▲▼ tetap bekerja seperti biasa (keduanya hanya menukar nilai `urutan`). Tabel kosong → 10.
 */
function urutan_teratas(string $tabel, array $saring = []): int
{
    $w = '';
    $p = [];
    foreach ($saring as $k => $v) {
        if (preg_match('#^[a-z_]+$#', (string) $k)) {
            $w .= ' AND ' . $k . ' = ?';
            $p[] = $v;
        }
    }
    $min = one('SELECT MIN(urutan) AS m FROM ' . $tabel . ' WHERE 1 = 1' . $w, $p);
    if (!$min || $min['m'] === null) {
        return 10;
    }
    return (int) $min['m'] - 10;
}

/**
 * Proses unggahan satu medan gambar pada formulir.
 * Aturan: centang hapus → kosongkan; ada berkas baru → unggah (gagal = pertahankan yang lama).
 *
 * @return array{url:string,pesan:string,galat:bool}
 */
function proses_gambar(string $field, string $lama, bool $hapus, string $label = 'Gambar'): array
{
    if ($hapus) {
        return ['url' => '', 'pesan' => $label . ' dihapus.', 'galat' => false];
    }
    $up = unggah_gambar($field, $label);
    if (!empty($up['kosong'])) {
        return ['url' => $lama, 'pesan' => '', 'galat' => false];
    }
    if (!$up['ok']) {
        return ['url' => $lama, 'pesan' => $up['pesan'], 'galat' => true];
    }
    return ['url' => $up['url'], 'pesan' => $label . ' berhasil diunggah.', 'galat' => false];
}

/** Blok unggah gambar (input file + pratinjau + centang hapus). */
function blok_gambar(string $field, string $url, string $label = 'Gambar', bool $besar = false, string $ket = ''): void
{
    ?>
    <div class="form-baris">
        <label for="<?= e($field) ?>"><?= e($label) ?></label>
        <div class="gambar-pratinjau<?= $besar ? ' besar' : '' ?>">
            <span class="pratinjau-gambar" id="pratinjau_<?= e($field) ?>">
                <?php if ($url !== ''): ?>
                    <img src="<?= e($url) ?>" alt="Pratinjau <?= e($label) ?>">
                <?php else: ?>
                    <span class="kosong-kecil">Belum ada gambar</span>
                <?php endif; ?>
            </span>
            <div class="pratinjau-medan">
                <input type="file" id="<?= e($field) ?>" name="<?= e($field) ?>" accept=".jpg,.jpeg,.png,.gif,.webp,.svg"
                       data-pratinjau="#pratinjau_<?= e($field) ?>">
                <?php if ($url !== ''): ?>
                    <label class="rasta kecil" style="margin-top:8px;gap:8px">
                        <input type="checkbox" name="<?= e($field) ?>_hapus" value="1"> Hapus gambar ini
                    </label>
                <?php endif; ?>
                <div class="bantuan"><?= $ket !== '' ? e($ket) : 'JPG/PNG/GIF/WEBP/SVG, maksimal 15 MB.' ?></div>
            </div>
        </div>
    </div>
    <?php
}

/** Tautan pengurutan naik/turun untuk sebuah baris tabel. */
function tombol_urut(string $berkas, int $id, array $tambahan = []): string
{
    $q1 = array_merge(['aksi' => 'naik', 'id' => $id], $tambahan);
    $q2 = array_merge(['aksi' => 'turun', 'id' => $id], $tambahan);
    return '<a class="tombol-ikon" title="Naikkan" href="' . e($berkas . '?' . http_build_query($q1)) . '">' . ikon('naik', 15) . '</a>'
        . '<a class="tombol-ikon" title="Turunkan" href="' . e($berkas . '?' . http_build_query($q2)) . '">' . ikon('turun', 15) . '</a>';
}

// =====================================================================================
// PENYUNTING ISI bergaya Word (tebal/miring/garis bawah, perataan, daftar, sisip gambar)
// =====================================================================================
/**
 * Menampilkan penyunting isi.
 *
 * Elemen `<textarea>` tetap ada di markup sehingga isi MASIH DAPAT disunting (HTML mentah)
 * walau JavaScript gagal dimuat; skrip `initEditor()` di assets/app.js menyembunyikannya dan
 * mengaktifkan kotak `contenteditable` beserta bilah tombolnya.
 *
 * @param string $nama    nama medan (name="…") yang dikirim ke server
 * @param string $nilai   isi HTML saat ini
 * @param string $idUnik  id unik untuk textarea (wajib berbeda antar penyunting di satu halaman)
 * @param int    $tinggi  tinggi kotak suntingan (px)
 * @param string $catatan keterangan kecil di bawah kotak
 */
function editor_teks(string $nama, string $nilai, string $idUnik, int $tinggi = 360, string $catatan = ''): void
{
    $tombol = [
        ['bold', 'Tebal (Ctrl+B)', '<b>B</b>', ''],
        ['italic', 'Miring (Ctrl+I)', '<i>I</i>', ''],
        ['underline', 'Garis bawah (Ctrl+U)', '<u>U</u>', ''],
        ['strikeThrough', 'Coret', '<s>S</s>', ''],
        ['---', '', '', ''],
        ['justifyLeft', 'Rata kiri', ikon('rata-kiri', 16), ''],
        ['justifyCenter', 'Rata tengah', ikon('rata-tengah', 16), ''],
        ['justifyRight', 'Rata kanan', ikon('rata-kanan', 16), ''],
        ['justifyFull', 'Rata kiri-kanan', ikon('rata-penuh', 16), ''],
        ['---', '', '', ''],
        ['insertUnorderedList', 'Daftar berbutir', ikon('daftar', 16), ''],
        ['insertOrderedList', 'Daftar bernomor', ikon('daftar-angka', 16), ''],
        ['formatBlock', 'Judul bagian', '<span class="editor-teks-kecil">H3</span>', 'h3'],
        ['---', '', '', ''],
        ['removeFormat', 'Bersihkan format', ikon('bersih-format', 16), ''],
        ['undo', 'Batalkan', ikon('undo', 16), ''],
        ['redo', 'Ulangi', ikon('redo', 16), ''],
    ];
    ?>
    <div class="editor" data-editor>
        <div class="editor-bilah" role="toolbar" aria-label="Format tulisan">
            <?php foreach ($tombol as $t): ?>
                <?php if ($t[0] === '---'): ?>
                    <span class="editor-pemisah" aria-hidden="true"></span>
                <?php else: ?>
                    <button type="button" class="editor-tombol" data-perintah="<?= e($t[0]) ?>"
                            <?= $t[3] !== '' ? 'data-nilai="' . e($t[3]) . '"' : '' ?>
                            title="<?= e($t[1]) ?>" aria-label="<?= e($t[1]) ?>"><?= $t[2] ?></button>
                <?php endif; ?>
            <?php endforeach; ?>
            <span class="editor-pemisah" aria-hidden="true"></span>
            <label class="editor-tombol editor-tombol-lebar" title="Sisipkan gambar (ukuran menyesuaikan otomatis)">
                <?= ikon('sisip-gambar', 16) ?> <span>Sisip Gambar</span>
                <input type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" data-sisip-gambar hidden>
            </label>
        </div>
        <textarea class="editor-sumber" id="<?= e($idUnik) ?>" name="<?= e($nama) ?>" rows="12"
                  style="min-height:<?= (int) $tinggi ?>px"><?= e($nilai) ?></textarea>
        <div class="editor-isi" contenteditable="true" hidden style="min-height:<?= (int) $tinggi ?>px"
             aria-label="Isi tulisan"></div>
        <div class="editor-kaki">
            <span class="editor-status" data-editor-status>Mode penyunting aktif</span>
            <?php if ($catatan !== ''): ?><span class="mut"><?= $catatan ?></span><?php endif; ?>
        </div>
    </div>
    <?php
}

/** Format gambar agar tidak melebihi lebar halaman (dipakai penyunting & tombol cepat). */
const GAYA_GAMBAR_ISI = 'max-width:100%;height:auto';

/**
 * Formulir konfirmasi tindakan berbahaya: wajib mengetik kata tertentu.
 *
 * @param string $aksi   nilai medan `aksi`
 * @param string $judul  tulisan pada tombol
 * @param string $pesan  keterangan/peringatan
 * @param array  $tambahan medan tambahan (nama => nilai)
 */
function form_konfirmasi_ketik(string $aksi, string $judul, string $pesan, array $tambahan = []): void
{
    ?>
    <div class="kotak-bahaya">
        <h3><?= e($judul) ?></h3>
        <p class="kecil mb8"><?= e($pesan) ?></p>
        <form method="post" data-konfirmasi="<?= e($judul) ?> — lanjutkan?">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="<?= e($aksi) ?>">
            <?php foreach ($tambahan as $k => $v): ?>
                <input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>">
            <?php endforeach; ?>
            <div class="rasta" style="gap:10px;flex-wrap:wrap">
                <input type="text" name="konfirmasi" placeholder="ketik HAPUS" required style="max-width:180px">
                <button type="submit" class="tombol tombol-bahaya"><?= ikon('trash', 16) ?> <?= e($judul) ?></button>
            </div>
        </form>
    </div>
    <?php
}

/** Tombol tautan dengan ikon (dipakai pada tabel admin). */
function tombol_mini(string $tautan, string $ikonNama, string $judul, string $kelas = ''): string
{
    return '<a class="tombol-ikon ' . e($kelas) . '" title="' . e($judul) . '" href="' . e($tautan) . '">' . ikon($ikonNama, 15) . '</a>';
}

/** Form hapus ringkas (POST + CSRF + konfirmasi). */
function form_hapus(string $aksi, int $id, string $pesan, array $tambahan = []): string
{
    $inp = csrf_field() . '<input type="hidden" name="aksi" value="' . e($aksi) . '"><input type="hidden" name="id" value="' . $id . '">';
    foreach ($tambahan as $k => $v) {
        $inp .= '<input type="hidden" name="' . e((string) $k) . '" value="' . e((string) $v) . '">';
    }
    return '<form method="post" style="display:inline" data-konfirmasi="' . e($pesan) . '">' . $inp
        . '<button type="submit" class="tombol-ikon bahaya" title="Hapus">' . ikon('trash', 15) . '</button></form>';
}

/** Label jenis menu (dipakai pada halaman Menu & Halaman). */
function jenis_menu_label(string $jenis): string
{
    $peta = [
        'beranda'   => 'Beranda (halaman depan)',
        'halaman'   => 'Halaman isi (teks)',
        'profil_lengkap'       => 'Profil Lengkap (identitas + visi, misi, tujuan — isian dari panel)',
        'struktur_organisasi'  => 'Struktur Organisasi (bagan kotak berjenjang)',
        'bos'       => 'Informasi BOS (Papan Informasi Dana BOS — data langsung dari aplikasi e-BOS)',
        'sarpras'   => 'Informasi Sarpras (Daftar Inventaris Barang — data langsung dari aplikasi e-GURU)',
        'perpustakaan' => 'Informasi Perpustakaan (Laporan Data Buku, Pengunjung, Peminjaman, Pengembalian — data langsung dari aplikasi e-PERPUS)',
        'supervisi' => 'Informasi Supervisi Akademik (dashboard hasil supervisi — data langsung dari aplikasi Supervisi)',
        'voting'    => 'Informasi e-Voting OSIM (papan hasil Real-Count — data langsung dari aplikasi e-Voting)',
        'pendaftar' => 'Daftar Pendaftar PPDB (tahun lama dari panel PPDB + PPDB Online website ini)',
        'edm'       => 'Evaluasi Diri Madrasah (Indikator EDM — data langsung dari aplikasi EDM)',
        'perangkat' => 'Perangkat Guru (berkas CP/ATP/RPP tiap guru — diunggah Super Admin)',
        // v34 — MENU KURIKULUM (tiga isian pada satu panel admin).
        'kaldik'            => 'Kaldik Operasional (gambar Kalender Pendidikan)',
        'jadwal_mengajar'   => 'Jadwal Mengajar (hari · waktu · jam ke · kelas · mapel + guru)',
        'dokumen_kurikulum' => 'Dokumen Kurikulum (kartu dokumen yang dapat diunduh pengunjung)',
        'kohort'    => 'Kohort Siswa (tabel analisis kohort + grafik — diisi Super Admin)',
        'pip'       => 'Layanan PIP (Informasi Program Indonesia Pintar + daftar penerima & Kartu PIP)',
        'guru'      => 'Halaman isi + daftar guru',
        'siswa'     => 'Daftar Data Siswa (dengan penyaring kelas)',
        'alumni'    => 'Daftar Data Alumni (tahun masuk/keluar + keterangan)',
        'prestasi_siswa'     => 'Daftar Prestasi Siswa',
        'prestasi_madrasah'  => 'Daftar Prestasi Madrasah',
        'prestasi_pendidik'  => 'Daftar Prestasi Pendidik',
        'pelatihan_pendidik' => 'Daftar Pelatihan Pendidik',
        'berita'    => 'Daftar berita',
        'agenda'    => 'Daftar agenda',
        'galeri'    => 'Galeri foto & video',
        'fasilitas' => 'Daftar fasilitas',
        'kontak'    => 'Kontak & formulir pesan',
        'ppdb'      => 'PPDB Online',
        'tautan'    => 'Tautan/alamat luar',
    ];
    return $peta[$jenis] ?? 'Halaman isi (teks)';
}

// =====================================================================================
// PENOLONG IMPOR (Excel/CSV dibaca di peramban, dikirim sebagai JSON)
// =====================================================================================

/** Peta kolom untuk impor data alumni (dipakai `tebak_kolom`). */
const ALUMNI_KOLOM_PETA = [
    'nama'          => ['nama', 'nama lengkap', 'nama alumni', 'nama siswa'],
    'nis'           => ['nis'],
    'nisn'          => ['nisn'],
    'jenis_kelamin' => ['jenis kelamin', 'jk', 'gender', 'l p'],
    'tahun_masuk'   => ['tahun masuk', 'angkatan', 'masuk'],
    'tahun_keluar'  => ['tahun keluar', 'tahun lulus', 'lulus', 'keluar'],
    'keterangan'    => ['keterangan', 'kondisi', 'kegiatan', 'status'],
];

/**
 * Tebak posisi kolom berdasarkan nama judul pada baris pertama.
 * Dua tahap: cocok PERSIS lebih dulu, baru cocok AWALAN — dan satu kolom tidak boleh
 * dipakai dua medan (kalau tidak, "Nama Ayah" bisa dirampas medan "Nama").
 *
 * @param array<int,string>            $header
 * @param array<string,array<int,string>> $peta  medan => daftar kata kunci
 * @return array<string,int>             medan => indeks kolom
 */
function tebak_kolom(array $header, array $peta): array
{
    $hasil = [];
    $dipakai = [];
    $bersih = static function (string $s): string {
        $s = strtolower(trim($s));
        $s = (string) preg_replace('#[^a-z0-9]+#', ' ', $s);
        return trim((string) preg_replace('#\s+#', ' ', $s));
    };
    $judul = [];
    foreach ($header as $i => $h) {
        $judul[$i] = $bersih((string) $h);
    }
    // tahap 1: persis
    foreach ($peta as $medan => $kata) {
        foreach ($kata as $k) {
            $k = $bersih($k);
            foreach ($judul as $i => $h) {
                if (!isset($dipakai[$i]) && $h === $k) {
                    $hasil[$medan] = $i;
                    $dipakai[$i] = true;
                    break 2;
                }
            }
        }
    }
    // tahap 2: awalan
    foreach ($peta as $medan => $kata) {
        if (isset($hasil[$medan])) {
            continue;
        }
        foreach ($kata as $k) {
            $k = $bersih($k);
            foreach ($judul as $i => $h) {
                if (!isset($dipakai[$i]) && $h !== '' && str_starts_with($h, $k)) {
                    $hasil[$medan] = $i;
                    $dipakai[$i] = true;
                    break 2;
                }
            }
        }
    }
    return $hasil;
}

function impor_nilai(array $baris, ?int $i): string
{
    if ($i === null || !isset($baris[$i])) {
        return '';
    }
    return trim((string) $baris[$i]);
}

/** "L"/"P" dari berbagai tulisan (Laki-laki, Perempuan, L, P, Lk, Pr). */
function jk_normal(string $teks): string
{
    $t = strtolower(trim($teks));
    if ($t === '') {
        return 'L';
    }
    if (str_starts_with($t, 'p') || str_contains($t, 'perempuan') || $t === 'pr' || $t === 'f') {
        return 'P';
    }
    return 'L';
}

/** Status guru/siswa dari teks bebas. */
function status_normal(string $teks, array $peta, string $bawaan): string
{
    $t = strtolower(trim($teks));
    foreach ($peta as $kunci => $kata) {
        foreach ($kata as $k) {
            if ($t !== '' && str_contains($t, $k)) {
                return $kunci;
            }
        }
    }
    return $bawaan;
}

/** Tanggal dari berbagai bentuk (ISO, d/m/Y, angka serial Excel, "12 April 2011"). */
function tgl_impor(string $teks): string
{
    $t = trim($teks);
    if ($t === '') {
        return '';
    }
    if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $t)) {
        return $t;
    }
    if (preg_match('#^\d{4}[/.](\d{1,2})[/.](\d{1,2})$#', $t, $m)) {
        return sprintf('%04d-%02d-%02d', (int) substr($t, 0, 4), (int) $m[2], (int) $m[1]);
    }
    if (preg_match('#^\d{8}$#', $t)) {
        return substr($t, 0, 4) . '-' . substr($t, 4, 2) . '-' . substr($t, 6, 2);
    }
    if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $t, $m)) {
        // kebiasaan Indonesia: hari lebih dulu
        $h = (int) $m[1];
        $b = (int) $m[2];
        if ($b > 12 && $h <= 12) {
            [$h, $b] = [$b, $h];
        }
        if (checkdate($b, $h, (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], $b, $h);
        }
    }
    if (preg_match('#^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$#', $t, $m)) {
        $bulan = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'agu' => 8, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'des' => 12, 'dec' => 12];
        $kunci = strtolower(substr($m[2], 0, 3));
        if (isset($bulan[$kunci]) && checkdate($bulan[$kunci], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], $bulan[$kunci], (int) $m[1]);
        }
    }
    if (is_numeric($t) && (float) $t > 20000 && (float) $t < 60000) {
        // angka serial Excel (1 = 1900-01-01)
        return date('Y-m-d', (int) ((float) $t - 25569) * 86400);
    }
    $ts = strtotime($t);
    return $ts ? date('Y-m-d', $ts) : '';
}

