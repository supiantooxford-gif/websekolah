<?php
/**
 * Halaman statis dinamis (isi diatur admin pada menu "Menu & Halaman").
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/profil.php';   // halaman Profil Lengkap & Bagan Struktur Organisasi
require_once __DIR__ . '/inc/bos.php';      // halaman "Informasi BOS" (data langsung dari aplikasi e-BOS)
require_once __DIR__ . '/inc/sarpras.php';  // halaman "Informasi Sarpras" (data langsung dari aplikasi e-GURU)
require_once __DIR__ . '/inc/perpustakaan.php'; // halaman "Informasi Perpustakaan" (4 laporan dari e-PERPUS)
require_once __DIR__ . '/inc/supervisi.php';    // halaman "Informasi Supervisi Akademik" (dashboard dari aplikasi Supervisi)
require_once __DIR__ . '/inc/voting.php';       // halaman "Informasi e-Voting OSIM" (papan hasil real-count dari aplikasi e-Voting)
require_once __DIR__ . '/inc/pip.php';      // halaman "Layanan PIP" (daftar penerima + Kartu PIP ber-sandi)

$slug = trim((string) ($_GET['slug'] ?? ''));

// ALAMAT RAPI: alamat lama `halaman.php?slug=…` dialihkan 301 ke `halaman/<slug>`
// (saringan kelas/tahun/pencarian/halaman/ta/kas ikut dibawa).
if ($slug !== '') {
    rute_lama_301('halaman/' . rawurlencode($slug), array_diff_key($_GET, ['slug' => 1]));
}
$m = $slug !== '' ? menu_by_slug($slug) : null;
if (!$m || in_array($m['jenis'], ['berita', 'agenda', 'galeri', 'kontak', 'ppdb', 'beranda', 'tautan'], true)) {
    http_response_code(404);
    situs_atas(['judul' => 'Halaman tidak ditemukan']);
    echo '<section class="isi-halaman"><div class="wadah"><div class="artikel tengah">'
        . '<h1>Halaman tidak ditemukan</h1><p class="mut">Halaman yang Anda cari tidak tersedia.</p>'
        . '<a class="tombol" href="./">Kembali ke Beranda</a></div></div></section>';
    situs_bawah();
    exit;
}

require_once __DIR__ . '/inc/ppdb_tarik.php';   // data pendaftar (panel lama + PPDB Online website)
require_once __DIR__ . '/inc/pendaftar.php';    // halaman menu "Pendaftar" (daftar pendaftar + filter tahun)
require_once __DIR__ . '/inc/grafik_tahun.php'; // komponen bersama kartu grafik jumlah per tahun
require_once __DIR__ . '/inc/edm.php';          // halaman "Evaluasi Diri Madrasah" (Indikator EDM dari aplikasi EDM)
require_once __DIR__ . '/inc/perangkat.php';    // halaman "Perangkat Guru" (berkas CP/ATP/RPP tiap guru)
require_once __DIR__ . '/inc/kohort.php';       // halaman "Kohort Siswa" (tabel analisis kohort + grafik)
require_once __DIR__ . '/inc/prestasi_kelas.php'; // blok "Kartu Prestasi Siswa per Kelas" (peringkat 1-3)
require_once __DIR__ . '/inc/guru.php';          // data guru: TMT, masa kerja, popup & tampilan tabel
require_once __DIR__ . '/inc/kurikulum.php';     // MENU KURIKULUM: Kaldik, Jadwal Mengajar, Dokumen Kurikulum

$induk = menu_induk_dari((int) $m['id']);
$guru = $m['jenis'] === 'guru' ? all("SELECT * FROM guru WHERE status = 'aktif' ORDER BY urutan, nama") : [];

// ---- Data Siswa & Data Alumni (menu Kesiswaan) ----
$PER_HAL = 24;
$halIni = halaman_ini();
$fKelas = (int) ($_GET['kelas'] ?? 0);
$fTahun = (int) ($_GET['tahun'] ?? 0);
$fCari = trim((string) ($_GET['cari'] ?? ''));

$kelasSiswa = $m['jenis'] === 'siswa' ? kelas_publik(true) : [];
$kelasSiswa = array_values(array_filter($kelasSiswa, static fn (array $k): bool => (int) $k['aktif'] === 1));
if ($fKelas > 0 && !one('SELECT id FROM kelas WHERE id = ?', [$fKelas])) {
    $fKelas = 0;   // kelas asing diabaikan (bukan 403)
}
$siswaDaftar = [];
$siswaTotal = 0;
$kelompokSiswa = [];
if ($m['jenis'] === 'siswa') {
    $siswaTotal = siswa_publik_jumlah($fKelas, $fCari);
    $siswaDaftar = siswa_publik($fKelas, $fCari, $PER_HAL, ($halIni - 1) * $PER_HAL);
    // Saat melihat "semua kelas", daftar dikelompokkan per kelas agar rapi.
    if ($fKelas === 0) {
        foreach ($siswaDaftar as $r) {
            $kelompokSiswa[$r['kelas'] !== null && $r['kelas'] !== '' ? $r['kelas'] : 'Belum berkelas'][] = $r;
        }
    }
}
// ---- Prestasi & Pelatihan (menu Kesiswaan/Akademik) ----
$prestasiKategori = prestasi_dari_menu((string) $m['jenis']);
$prestasiDaftar = [];
$prestasiTahunAda = [];
$fPrestasiTahun = (int) ($_GET['tahun'] ?? 0);
if ($prestasiKategori !== '') {
    $prestasiTahunAda = prestasi_tahun($prestasiKategori);
    if ($fPrestasiTahun > 0 && !in_array((string) $fPrestasiTahun, $prestasiTahunAda, true)) {
        $fPrestasiTahun = 0;
    }
    $prestasiDaftar = prestasi_list($prestasiKategori);
    if ($fPrestasiTahun > 0) {
        $prestasiDaftar = array_values(array_filter($prestasiDaftar,
            static fn (array $r): bool => (string) $r['tahun'] === (string) $fPrestasiTahun));
    }
}

$alumniDaftar = [];
$alumniTotal = 0;
$tahunAlumni = [];
if ($m['jenis'] === 'alumni') {
    // Data alumni = GABUNGAN data kelulusan (siswa lulus) + tabel `alumni` (isi manual/impor),
    // sudah dibersihkan dari data ganda oleh alumni_gabungan().
    $tahunAlumni = alumni_tahun_gabungan();
    if ($fTahun > 0 && !in_array((string) $fTahun, $tahunAlumni, true)) {
        $fTahun = 0;
    }
    $alumniSemua = alumni_gabungan($fTahun, $fCari);
    $alumniTotal = count($alumniSemua);
    $alumniDaftar = array_slice($alumniSemua, ($halIni - 1) * $PER_HAL, $PER_HAL);
}

/** Tautan penyaring halaman ini (mempertahankan saringan lain). */
function hal_filter(array $ganti): string
{
    $q = array_merge([
        'kelas' => $_GET['kelas'] ?? '', 'tahun' => $_GET['tahun'] ?? '', 'cari' => $_GET['cari'] ?? '',
        // Saringan blok "Kartu Prestasi Siswa per Kelas" ikut dipertahankan saat halaman berpindah
        // (paginasi / reset saringan prestasi) — jangan dihapus dari daftar ini.
        'pk_ta' => $_GET['pk_ta'] ?? '', 'pk_sem' => $_GET['pk_sem'] ?? '',
    ], $ganti);
    $q = array_filter($q, static fn ($v): bool => (string) $v !== '' && (string) $v !== '0');
    // ALAMAT RAPI: slug tidak lagi menjadi parameter (sudah menjadi bagian jalur, mis.
    // `halaman/data-siswa`), jadi cukup jalur halaman ini + saringan yang tersisa.
    return rute_tautan(rute_kanonik(), $q);
}

// Halaman "PROFIL LENGKAP" memakai A4 MENDATAR saat dicetak. `@page` WAJIB ditulis di halaman ini
// (bukan hanya di tema): bila hanya ada di tema, PDF hasil cetak memakai ukuran lain (pelajaran yang
// sudah tercatat pada lembar cetak data guru/siswa).
// ⚠️ BAGAN STRUKTUR ORGANISASI **TIDAK untuk dicetak** (permintaan pemilik: "bagan tidak perlu
// dicetak") — bagan ditampilkan sebesar layar dan boleh lebih lebar dari A4.
// ⚠️ Variabel ini HARUS dihitung SEBELUM `situs_atas()` — kalau tidak, kelas `dokumen` pada <body>
// selalu kosong dan seluruh gaya cetak halaman dokumen tidak berlaku.
$halamanDokumen = $m['jenis'] === 'profil_lengkap';
if ($halamanDokumen) {
    echo '<style>@page{size:A4 landscape;margin:10mm}</style>';
}
// MENU "INFORMASI BOS" — Papan Informasi Dana BOS (data langsung dari aplikasi e-BOS).
// Lembar cetaknya memakai A4 MENDATAR (sama seperti halaman depan aplikasi e-BOS) sehingga
// `@page` WAJIB ditulis di halaman ini, bukan hanya di tema.
$halamanBos = $m['jenis'] === 'bos';
if ($halamanBos) {
    echo '<style>@media print{@page{size:A4 landscape;margin:12mm}}</style>';
}
// MENU KURIKULUM → JADWAL MENGAJAR: tabel jadwal DICETAK MENDATAR (permintaan pemilik: "Buat
// Landscape"). `@page` WAJIB ditulis di halaman ini — bila hanya di dalam `@media print` pada
// tema, PDF hasil cetak memakai ukuran lain (pelajaran yang sama dengan halaman Informasi BOS).
$halamanJadwal = $m['jenis'] === 'jadwal_mengajar';
if ($halamanJadwal) {
    echo '<style>@media print{@page{size:A4 landscape;margin:10mm}}</style>';
}
// MENU KURIKULUM (semua jenisnya) — kelas `kurikulum` (dan `jadwal` untuk halaman jadwal) dipakai
// `@media print` pada tema untuk membuang kepala/kaki situs, melebarkan wadah, dan menyembunyikan
// tombol Filter Hari saat dicetak.
$kelasKurikulum = in_array($m['jenis'], ['kaldik', 'jadwal_mengajar', 'dokumen_kurikulum'], true)
    ? ($halamanJadwal ? 'kurikulum jadwal' : 'kurikulum')
    : '';

situs_atas(['judul' => $m['judul'] ?: $m['label'], 'aktif' => $induk ? $induk['slug'] : $m['slug'],
    'deskripsi' => $m['subjudul'] ?: $m['label'],
    // `dokumen` memberi gaya CETAK khusus (lihat blok @media print body.dokumen pada assets/style.css);
    // `bos` menyembunyikan kepala/kaki situs saat Papan Informasi BOS dicetak (A4 mendatar).
    'kelas_body' => $halamanDokumen ? 'dokumen' : ($halamanBos ? 'bos' : $kelasKurikulum)]);

// Halaman Informasi BOS TIDAK memakai kepala halaman (hero): susunannya sudah memuat judul
// "PAPAN INFORMASI" sendiri, persis seperti halaman depan aplikasi e-BOS.
if (!$halamanBos) {
    hero_halaman($m['judul'] ?: $m['label'], $m['subjudul'], array_values(array_filter([
        $induk && (int) $induk['id'] !== (int) $m['id'] ? ['label' => $induk['label'], 'tautan' => menu_url($induk)] : null,
        ['label' => $m['label'], 'tautan' => ''],
    ])));
}
?>
<section class="isi-halaman">
    <div class="wadah">
        <?php /* Kolom sisi (kartu penjelasan + daftar sub menu) DIHAPUS atas permintaan pemilik
                 supaya tabel/data halaman ini memakai lebar penuh dan terlihat lebih rapi.
                 Sub menu tetap tersedia pada menu turun di bilah navigasi (`.nav-anak`). */ ?>
        <?php if ($m['jenis'] === 'profil_lengkap'): ?>
            <?php
            // HALAMAN PROFIL LENGKAP — seluruh isiannya diatur dari panel (admin/profil.php).
            // Isi Halaman (bila pemilik menuliskan sesuatu di Menu & Halaman) tetap ditampilkan
            // di ATAS dokumen supaya tidak terasa "tidak berfungsi".
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') { echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>'; }
            profil_lengkap_render();
            ?>
        <?php elseif ($m['jenis'] === 'pip'): ?>
            <?php
            // HALAMAN "LAYANAN PIP" — Informasi Program Indonesia Pintar + Daftar Penerima PIP.
            // Isinya diatur admin pada panel Layanan PIP (lihat inc/pip.php & admin/pip.php).
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            pip_papan();
            ?>
        <?php elseif ($m['jenis'] === 'sarpras'): ?>
            <?php
            // HALAMAN "INFORMASI SARPRAS" — Daftar Inventaris Barang (Sarana & Prasarana) milik
            // madrasah ini. Datanya dibaca LANGSUNG dari basis data aplikasi e-GURU (menu Tugas
            // Tambahan "Sarana & Prasarana"), lihat inc/sarpras.php.
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            sarpras_papan();
            ?>
        <?php elseif ($m['jenis'] === 'perpustakaan'): ?>
            <?php
            // HALAMAN "INFORMASI PERPUSTAKAAN" — EMPAT LAPORAN (Data Buku, Pengunjung, Peminjaman,
            // Pengembalian). Datanya dibaca LANGSUNG dari basis data aplikasi e-PERPUS, lihat
            // inc/perpustakaan.php. Permintaan pemilik: empat tombol di satu halaman, laporan tampil
            // di bawah tombol, dengan saringan tanggal Dari–Sampai, tanpa tombol cetak.
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            perpustakaan_papan();
            ?>
        <?php elseif ($m['jenis'] === 'supervisi'): ?>
            <?php
            // HALAMAN "INFORMASI SUPERVISI AKADEMIK" — TAMPILAN DASHBOARD aplikasi Supervisi
            // (khusus MA. Sirojuth Tholibiin Mootilango). Datanya dibaca LANGSUNG dari basis data
            // aplikasi Supervisi Guru, lihat inc/supervisi.php. Permintaan pemilik: Supervisi
            // Terbaru, komentar pengawas, dan hasil tinjauan dokumen TIDAK ditampilkan; pengunjung
            // hanya melihat (tanpa tombol cetak) + ada saringan Tahun Ajaran & Semester.
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            supervisi_papan();
            ?>
        <?php elseif ($m['jenis'] === 'voting'): ?>
            <?php
            // HALAMAN "INFORMASI E-VOTING OSIM" — PAPAN HASIL REAL-COUNT aplikasi e-Voting
            // (khusus MA. Sirojuth Tholibiin Mootilango). Datanya dibaca LANGSUNG dari basis data
            // aplikasi e-Voting (lihat inc/voting.php) dan angkanya berjalan sendiri tiap 5 detik
            // lewat endpoint voting_hasil.php. Mengikuti setelan aplikasi: bila panitia belum
            // membuka hasil ke publik, halaman hanya menampilkan keterangan "hasil belum dibuka".
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            voting_papan();
            ?>
        <?php elseif ($m['jenis'] === 'pendaftar'): ?>
            <?php
            // HALAMAN "PENDAFTAR" (menu Siswa → Pendaftar) — daftar pendaftar PPDB dengan filter per
            // tahun (permintaan pemilik: isinya dipindahkan dari tab pada halaman Data Siswa).
            // Sumber: 2022–2026 hasil tarik panel PPDB lama (`ppdb_lama`), 2027+ PPDB Online website.
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            pendaftar_papan();
            ?>
        <?php elseif ($m['jenis'] === 'kohort'): ?>
            <?php
            // HALAMAN "KOHORT SISWA" (menu Siswa → Kohort Siswa) — tabel analisis kohort
            // (Tahun Ajaran · PMB · Kelas X/XI/XII) untuk **5 tahun ajaran terbaru** + analisis
            // grafiknya. Angkanya diisi Super Admin pada panel admin (admin/kohort.php), lihat
            // inc/kohort.php.
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            kohort_papan();
            ?>
        <?php elseif ($m['jenis'] === 'perangkat'): ?>
            <?php
            // HALAMAN "PERANGKAT GURU" (menu Kurikulum → Perangkat Guru) — tabel berkas perangkat
            // pembelajaran setiap guru (CP, ATP, RPP/Modul Ajar) dengan centang hijau/silang merah
            // dan tombol Download ber-kode (popup "masukkan kode download").
            // Permintaan pemilik: "isi dibuat seperti contoh yang saya upload … data gurunya otomatis
            // dari jumlah data guru (Kepala Madrasah tidak termasuk), dan untuk mata pelajarannya
            // setiap guru bisa lebih dari satu."
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            perangkat_papan();
            ?>
        <?php elseif ($m['jenis'] === 'kaldik'): ?>
            <?php
            // HALAMAN "KALDIK OPERASIONAL" (menu Kurikulum → Kaldik Operasional) — gambar Kalender
            // Pendidikan yang diunggah Administrator/Editor pada panel Kurikulum (lihat
            // inc/kurikulum.php & admin/kurikulum.php).
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            kurikulum_kaldik_papan();
            ?>
        <?php elseif ($m['jenis'] === 'jadwal_mengajar'): ?>
            <?php
            // HALAMAN "JADWAL MENGAJAR" (menu Kurikulum → Jadwal Mengajar) — tabel jadwal per HARI
            // (Hari · Waktu · Jam Ke · Kelas) dengan baris kegiatan yang menggabung semua kelas.
            // Permintaan pemilik: "Di tampilkan berdasarkan filter hari" + "Buat Landscape".
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            kurikulum_jadwal_papan();
            ?>
        <?php elseif ($m['jenis'] === 'dokumen_kurikulum'): ?>
            <?php
            // HALAMAN "DOKUMEN KURIKULUM" (menu Kurikulum → Dokumen Kurikulum) — kartu dokumen
            // (PDF/Word/Excel/PowerPoint/gambar) yang dapat diunduh pengunjung.
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            kurikulum_dokumen_papan();
            ?>
        <?php elseif ($m['jenis'] === 'edm'): ?>
            <?php
            // HALAMAN "EVALUASI DIRI MADRASAH" — tampilan **Indikator EDM** aplikasi EDM.
            // Permintaan pemilik: samakan isinya, TANPA tombol "Cetak Rekap" dan TANPA kartu
            // "Ringkasan Pengisian Tiap Aspek" (lihat inc/edm.php).
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            edm_papan();
            ?>
        <?php elseif ($m['jenis'] === 'bos'): ?>
            <?php
            // HALAMAN "INFORMASI BOS" — Papan Informasi penggunaan Dana BOS. Seluruh datanya
            // dibaca LANGSUNG dari basis data aplikasi e-BOS (lihat inc/bos.php), jadi pembaruan
            // di aplikasi e-BOS otomatis ikut tampil di sini.
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') {
                echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>';
            }
            bos_papan(['slug' => (string) $m['slug'], 'ta' => (int) ($_GET['ta'] ?? 0)]);
            ?>
        <?php elseif ($m['jenis'] === 'struktur_organisasi'): ?>
            <?php
            // BAGAN STRUKTUR ORGANISASI — kotak-kotaknya diatur dari panel (admin/struktur.php).
            $isiDokumen = trim(str_replace('&nbsp;', '', strip_tags((string) $m['isi'])));
            if ($isiDokumen !== '') { echo '<div class="artikel mb24" style="margin-bottom:22px">' . bersihkan_html((string) $m['isi']) . '</div>'; }
            struktur_render();
            ?>
        <?php else: ?>
        <article class="artikel">
            <?php /* v1.15.0 — KARTU PRESTASI SISWA PER KELAS: peringkat 1-3 tiap kelas dengan filter
                     tahun ajaran & semester. Diletakkan PALING ATAS isi halaman Prestasi Siswa (area
                     yang ditandai pemilik), di atas deskripsi & tabel prestasi biasa. */ ?>
            <?php if ($m['jenis'] === 'prestasi_siswa'): ?>
                <?php pk_papan(); ?>
            <?php endif; ?>
            <?= bersihkan_html((string) $m['isi']) ?>
            <?php if ($m['jenis'] === 'guru'): ?>
                <?php if (!$guru): ?>
                    <p class="mut">Data guru dan tenaga kependidikan belum diisi.</p>
                <?php else: ?>
                    <?php
                    /* ================= DATA GURU (v1.16.0) =================
                       Permintaan pemilik: (1) kartu guru DAPAT DIKLIK → popup data (nama lengkap,
                       jenis kelamin, jabatan, telepon, masa kerja dari TMT); (2) tombol TAMPILKAN
                       DALAM BENTUK TABEL → seluruh guru tampil sebagai tabel dengan isi yang sama.
                       `?tampil=tabel` untuk bentuk tabel, `?guru=ID` membuka popup (tanpa JavaScript). */
                    $guruTampil = (string) ($_GET['tampil'] ?? '') === 'tabel' ? 'tabel' : 'kartu';
                    $guruDipilih = (int) ($_GET['guru'] ?? 0);
                    $guruKanonik = rute_kanonik();
                    ?>
                    <?php /* Kontrol Kartu/Tabel + popup + tabel (area yang ditandai pemilik). */ ?>
                    <?php guru_blok_tampilan($guru, $guruKanonik, $guruTampil, $guruDipilih); ?>
                    <?php if ($guruTampil !== 'tabel'): ?>
                    <div class="guru-grid mt24" id="guruKartu">
                        <?php foreach ($guru as $g): ?>
                            <a class="guru-kartu guru-kartu-klik" href="<?= e(guru_kartu_url($guruKanonik, (int) $g['id'])) ?>"
                               data-popup-guru="<?= (int) $g['id'] ?>" role="button"
                               aria-label="Lihat data <?= e($g['nama']) ?>">
                                <?php if ($g['foto'] !== ''): ?>
                                    <img class="guru-foto" src="<?= e($g['foto']) ?>" alt="<?= e($g['nama']) ?>">
                                <?php else: ?>
                                    <span class="guru-foto"><?= e(guru_inisial((string) $g['nama'])) ?></span>
                                <?php endif; ?>
                                <h3><?= e($g['nama']) ?></h3>
                                <small><?= e($g['jabatan']) ?><?= $g['mapel'] !== '' ? ' · ' . e($g['mapel']) : '' ?></small>
                                <span class="guru-kartu-aksi"><?= ikon('mata', 13) ?> Data guru</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php /* ================= DATA SISWA ================= */ ?>
            <?php if ($m['jenis'] === 'siswa'): ?>
                <div class="filter-publik">
                    <form method="get" action="<?= e(rute_kanonik()) ?>" class="filter-baris">
                        <?php if ($fCari !== ''): ?><input type="hidden" name="cari" value="<?= e($fCari) ?>"><?php endif; ?>
                        <label for="kelas">Pilih Kelas</label>
                        <select id="kelas" name="kelas" data-auto>
                            <option value="0">Semua kelas</option>
                            <?php foreach ($kelasSiswa as $k): ?>
                                <?php $jmlKelas = jumlah("SELECT COUNT(*) FROM siswa WHERE kelas_id = ? AND status <> 'lulus'", [(int) $k['id']]); ?>
                                <option value="<?= (int) $k['id'] ?>" <?= $fKelas === (int) $k['id'] ? 'selected' : '' ?>>
                                    <?= e($k['nama']) ?> (<?= (int) $jmlKelas ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="filter-jumlah"><?= angka($siswaTotal) ?> siswa ditemukan</span>
                        <?php if ($fKelas > 0 || $fCari !== ''): ?>
                            <a class="tombol tombol-garis tombol-kecil" href="<?= e(hal_filter(['kelas' => '', 'cari' => ''])) ?>">Reset saringan</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (!$siswaDaftar): ?>
                    <div class="kartu tengah"><p class="mut mb0">Belum ada data siswa<?= $fKelas > 0 ? ' pada kelas ini' : '' ?>.</p></div>
                <?php else: ?>
                    <?php
                    // Bila penyaring kelas dipakai → satu tabel; bila "semua kelas" → dikelompokkan per kelas.
                    $grup = $fKelas > 0 ? ['' => $siswaDaftar] : $kelompokSiswa;
                    ?>
                    <?php foreach ($grup as $namaKelas => $baris): ?>
                        <?php if ($namaKelas !== ''): ?>
                            <h3 class="judul-kelompok"><?= e($namaKelas) ?> <span class="mut kecil">· <?= count($baris) ?> siswa</span></h3>
                        <?php endif; ?>
                        <div class="tabel-wrap mb16">
                            <table class="tabel tabel-publik">
                                <thead>
                                    <tr>
                                        <th style="width:64px">Foto</th>
                                        <th>Nama Siswa</th>
                                        <th style="width:110px">NISN</th>
                                        <th style="width:120px">Jenis Kelamin</th>
                                        <th style="width:110px">Kelas</th>
                                        <th style="width:110px">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($baris as $r): ?>
                                        <tr>
                                            <td>
                                                <?php if ($r['foto'] !== ''): ?>
                                                    <img class="foto-siswa" src="<?= e($r['foto']) ?>" alt="<?= e($r['nama']) ?>" loading="lazy">
                                                <?php else: ?>
                                                    <span class="foto-siswa inisial"><?= e(strtoupper(substr(trim((string) $r['nama']), 0, 1))) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?= e($r['nama']) ?></strong></td>
                                            <td class="kecil"><?= e($r['nisn'] ?: '-') ?></td>
                                            <td class="kecil"><?= $r['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></td>
                                            <td class="kecil"><?= e($r['kelas'] ?: '-') ?></td>
                                            <td><span class="<?= e(status_siswa_kelas((string) $r['status'])) ?>"><?= e(status_siswa_label((string) $r['status'])) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                    <?= paginasi($siswaTotal, $PER_HAL, $halIni) ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php /* ================= PRESTASI & PELATIHAN ================= */ ?>
            <?php if ($prestasiKategori !== ''): ?>
                <div class="filter-publik">
                    <form method="get" action="<?= e(rute_kanonik()) ?>" class="filter-baris">
                        <label for="saringTahunPrestasi">Tahun</label>
                        <select id="saringTahunPrestasi" name="tahun" data-auto>
                            <option value="0">Semua tahun</option>
                            <?php foreach ($prestasiTahunAda as $th): ?>
                                <option value="<?= (int) $th ?>" <?= $fPrestasiTahun === (int) $th ? 'selected' : '' ?>><?= e($th) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="filter-jumlah"><?= angka(count($prestasiDaftar)) ?> data ditemukan</span>
                        <?php if ($fPrestasiTahun > 0): ?>
                            <a class="tombol tombol-garis tombol-kecil" href="<?= e(hal_filter(['tahun' => ''])) ?>">Reset saringan</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (!$prestasiDaftar): ?>
                    <div class="kartu tengah"><p class="mut mb0">Belum ada data <?= e(strtolower(prestasi_label($prestasiKategori))) ?> yang ditampilkan.</p></div>
                <?php else: ?>
                    <div class="tabel-wrap">
                        <?php $kolomPrestasi = prestasi_kolom($prestasiKategori); ?>
                        <table class="tabel tabel-publik">
                            <thead>
                                <tr>
                                    <?php foreach ($kolomPrestasi as $kol): ?>
                                        <th<?= $kol['lebar'] !== null ? ' style="width:' . (int) $kol['lebar'] . 'px"' : '' ?>><?= e($kol['judul']) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prestasiDaftar as $i => $r): ?>
                                    <tr>
                                        <?php foreach ($kolomPrestasi as $kol): ?>
                                            <?php $nilaiKol = trim((string) ($r[$kol['jenis']] ?? '')); ?>
                                            <?php switch ($kol['jenis']):
                                                case 'no': ?>
                                                    <td class="kecil"><?= $i + 1 ?></td>
                                                <?php break; ?>
                                            <?php case 'foto': ?>
                                                <?php $fotoPrestasi = prestasi_foto($r); ?>
                                                <td>
                                                    <?php if ($fotoPrestasi !== ''): ?>
                                                        <img class="foto-siswa" src="<?= e($fotoPrestasi) ?>" alt="<?= e($r['nama']) ?>" loading="lazy">
                                                    <?php else: ?>
                                                        <span class="foto-siswa inisial"><?= e(strtoupper(substr(trim((string) $r['nama']), 0, 1))) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php break; ?>
                                            <?php case 'nama_keterangan': ?>
                                                <td><strong><?= e($r['nama']) ?></strong>
                                                    <?php if (trim((string) $r['keterangan']) !== ''): ?><div class="kecil mut"><?= e($r['keterangan']) ?></div><?php endif; ?>
                                                </td>
                                                <?php break; ?>
                                            <?php case 'sertifikat': ?>
                                                <td>
                                                    <?php if (trim((string) $r['sertifikat']) !== ''): ?>
                                                        <a class="tombol tombol-garis tombol-kecil btn-lihat" href="<?= e($r['sertifikat']) ?>" target="_blank" rel="noopener noreferrer"><?= ikon('mata', 14) ?> Lihat</a>
                                                    <?php else: ?>
                                                        <span class="mut kecil">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php break; ?>
                                            <?php default: ?>
                                                <td class="kecil"><?= e($nilaiKol !== '' ? $nilaiKol : '-') ?></td>
                                            <?php endswitch; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php /* ================= DATA ALUMNI ================= */ ?>
            <?php if ($m['jenis'] === 'alumni'): ?>
                <?php
                // GRAFIK ALUMNI PER TAHUN (permintaan pemilik) — di ATAS filter tahun, memakai
                // komponen bersama dengan halaman Pendaftar. Hanya tahun yang sudah ada alumninya.
                // Datanya dihitung dari gabungan data kelulusan + tabel alumni (sama dengan tabel).
                $alumniPerTahun = alumni_per_tahun();
                if ($alumniPerTahun) {
                    $thAlumni = array_map('strval', array_keys($alumniPerTahun));
                    grafik_tahun_kartu([
                        'id' => 'alumniGrafik',
                        'kanvas' => 'chAlumni',
                        'daftar' => 'alumniGrafikDaftar',
                        'judul' => 'Grafik Alumni per Tahun',
                        'keterangan' => 'Jumlah alumni pada setiap tahun keluar yang sudah memiliki data — tahun tanpa alumni tidak ditampilkan.',
                        'data' => $alumniPerTahun,
                        'satuan' => 'alumni',
                        'ikon' => 'medal',
                        'kaki' => 'Tahun ' . (int) reset($thAlumni) . '–' . (int) end($thAlumni)
                            . ' · rata-rata ' . angka((int) round(array_sum($alumniPerTahun) / max(1, count($alumniPerTahun))))
                            . ' alumni per tahun · sumber: data kelulusan & data alumni (manual/impor).',
                    ]);
                }
                ?>
                <div class="filter-publik">
                    <form method="get" action="<?= e(rute_kanonik()) ?>" class="filter-baris">
                        <?php if ($fCari !== ''): ?><input type="hidden" name="cari" value="<?= e($fCari) ?>"><?php endif; ?>
                        <label for="tahun">Tahun Keluar</label>
                        <select id="tahun" name="tahun" data-auto>
                            <option value="0">Semua tahun</option>
                            <?php foreach ($tahunAlumni as $th): ?>
                                <option value="<?= (int) $th ?>" <?= $fTahun === (int) $th ? 'selected' : '' ?>><?= e($th) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="filter-jumlah"><?= angka($alumniTotal) ?> alumni ditemukan</span>
                        <?php if ($fTahun > 0 || $fCari !== ''): ?>
                            <a class="tombol tombol-garis tombol-kecil" href="<?= e(hal_filter(['tahun' => '', 'cari' => ''])) ?>">Reset saringan</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (!$alumniDaftar): ?>
                    <div class="kartu tengah"><p class="mut mb0">Belum ada data alumni<?= $fTahun > 0 ? ' pada tahun ini' : '' ?>.</p></div>
                <?php else: ?>
                    <div class="tabel-wrap">
                        <table class="tabel tabel-publik">
                            <thead>
                                <tr>
                                    <th style="width:64px">Foto</th>
                                    <th>Nama Alumni</th>
                                    <th style="width:120px">Jenis Kelamin</th>
                                    <th style="width:110px">Tahun Masuk</th>
                                    <th style="width:110px">Tahun Keluar</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alumniDaftar as $r): ?>
                                    <tr>
                                        <td>
                                            <?php if ($r['foto'] !== ''): ?>
                                                <img class="foto-siswa" src="<?= e($r['foto']) ?>" alt="<?= e($r['nama']) ?>" loading="lazy">
                                            <?php else: ?>
                                                <span class="foto-siswa inisial"><?= e(strtoupper(substr(trim((string) $r['nama']), 0, 1))) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= e($r['nama']) ?></strong>
                                            <?php if (!empty($r['kelas'])): ?><div class="kecil mut">Kelas terakhir: <?= e((string) $r['kelas']) ?></div><?php endif; ?>
                                        </td>
                                        <td class="kecil"><?= $r['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></td>
                                        <td class="kecil"><?= e(($r['tahun_masuk'] ?? '') !== '' ? $r['tahun_masuk'] : '-') ?></td>
                                        <td class="kecil"><?= e($r['tahun_keluar'] ?: '-') ?></td>
                                        <td class="kecil"><?= e($r['keterangan'] ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= paginasi($alumniTotal, $PER_HAL, $halIni) ?>
                <?php endif; ?>
            <?php endif; ?>
        </article>
        <?php endif; ?>

        <?php if (in_array($m['jenis'], ['profil_lengkap', 'struktur_organisasi'], true)): ?>
            <?php /* Halaman dokumen: TIDAK ada tombol cetak (permintaan pemilik — bagian yang
                     ditandai merah pada halaman Profil Lengkap dihapus). Hanya tombol kembali. */ ?>
            <div class="aksi-form" style="justify-content:center;margin-top:18px">
                <a class="tombol tombol-garis" href="./">Kembali ke Beranda</a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php situs_bawah(); ?>
