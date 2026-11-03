<?php
/**
 * Skema basis data & isi awal aplikasi Website Sekolah.
 *
 * ATURAN PENTING (pelajaran dari aplikasi lain di workspace ini):
 *  1. Seluruh migrasi dijalankan DALAM SATU transaksi BEGIN IMMEDIATE (menghindari puluhan fsync).
 *  2. Penanda `data/.schema_ok` bisa LEBIH BARU daripada isi basis data (tulisan terakhir hilang),
 *     sehingga migrasi tidak pernah jalan lagi. Karena itu `skema_terkini()` memeriksa
 *     SCHEMA_TABEL_WAJIB & SCHEMA_KOLOM_WAJIB lebih dulu.
 *  3. Setiap kolom/tabel baru WAJIB didaftarkan di SCHEMA_KOLOM_WAJIB / SCHEMA_TABEL_WAJIB.
 *  4. Seeding isi contoh hanya dijalankan SEKALI (dijaga penanda pada tabel `pengaturan`),
 *     supaya suntingan admin tidak pernah ditimpa.
 */
declare(strict_types=1);

const SCHEMA_TABEL_WAJIB = [
    'sekolah', 'pengaturan', 'users', 'log_masuk', 'menu', 'slider', 'slider_kecil', 'sosial',
    'keunggulan', 'program', 'berita_kategori', 'berita', 'agenda', 'galeri_album', 'galeri_foto',
    'galeri_video', 'fasilitas', 'testimoni', 'guru', 'siswa', 'kelas', 'kenaikan',
    'ppdb_gelombang', 'ppdb_pendaftar', 'kontak_pesan',
    'ppdb_lama',      // v22 — data pendaftar tahun LAMA (ditarik dari panel PPDB ma.stm.sch.id)
    'perangkat',      // v25 — berkas Perangkat Guru (CP/ATP/RPP) per guru per mata pelajaran
    'perangkat_kode', // v25 — kode unduh per MATA PELAJARAN (diatur Super Admin)
    'perangkat_kelas', // v27 — berkas per KELAS (satu mapel bisa beda berkas tiap kelas)
    'periode',         // v28 — Tahun Ajaran + Semester (perangkat pembelajaran per semester)
    'kohort',          // v29 — tabel analisis kohort siswa (satu baris = satu tahun ajaran)
    'prestasi_kelas',  // v32 — peringkat 1-3 tiap kelas per tahun ajaran & semester
    // v34 — MENU KURIKULUM: kalender pendidikan, jadwal mengajar, dan dokumen kurikulum.
    'kurikulum_kaldik', 'kurikulum_jadwal', 'kurikulum_dokumen',
    'poll', 'poll_pilihan', 'poll_suara', 'prestasi', 'alumni', 'struktur', 'faq',
    'pip_penerima',   // v17 — daftar penerima PIP (kartu PIP & surat kuasa diunggah admin)
    'pip_tahun',      // v18 — tahun layanan PIP (SK & daftar penerima berbeda tiap tahun)
];

const SCHEMA_KOLOM_WAJIB = [
    'sekolah'       => ['nama', 'jenis', 'logo', 'whatsapp', 'video_profil', 'kepala_foto',
                        // v10 — isian halaman "Profil Lengkap"
                        'kode_pos', 'npwp', 'status_madrasah', 'waktu_belajar', 'sk_ijop', 'sk_ijop_tgl',
                        'tahun_berdiri', 'akreditasi_tgl', 'penyelenggara', 'sk_menkumham',
                        'jarak_kecamatan', 'jarak_kabupaten', 'bangunan', 'visi', 'misi', 'tujuan',
                        // v13 — berkas dokumen IJOP & Akreditasi (URL media proxy)
                        'berkas_ijop', 'berkas_akreditasi'],
    'struktur'      => ['nama', 'induk_id', 'urutan', 'garis', 'aksen', 'aktif', 'kode', 'foto'],
    'faq'           => ['pertanyaan', 'jawaban', 'kata_kunci', 'urutan', 'aktif'],
    'galeri_album'  => ['nama', 'slug', 'sampul', 'aktif', 'urutan', 'tahun'],
    'menu'          => ['jenis', 'isi', 'sorot', 'slug'],
    'siswa'         => ['kelas_id', 'status', 'tahun_lulus', 'keterangan'],
    // v33 — TMT (tanggal mulai tugas) & Keterangan guru (Satmikal/Non Satmikal).
    'guru'          => ['tmt', 'keterangan'],
    'ppdb_pendaftar' => ['berkas', 'status', 'nilai_seleksi'],
    // v22 — data pendaftar tahun lama (hasil TARIK dari panel PPDB lama).
    'ppdb_lama'     => ['tahun', 'nomor', 'nama', 'asal_sekolah', 'sumber', 'urutan'],
    // v25 — berkas Perangkat Guru per guru per mata pelajaran (URL media proxy) + kode unduhnya.
    // v26 — kolom `kelas` = daftar kelas sebuah mata pelajaran (mis. "Kelas X, Kelas XI") —
    //       satu mata pelajaran boleh dipakai di beberapa kelas. Hanya MENAMBAH kolom.
    'perangkat'      => ['guru_id', 'mapel', 'kunci', 'kelas', 'cp', 'cp_nama', 'atp', 'atp_nama', 'rpp', 'rpp_nama', 'urutan'],
    'perangkat_kode' => ['mapel', 'kunci', 'kode', 'urutan'],
    // v27 — BERKAS PER KELAS: satu baris per (perangkat × kelas) sehingga berkas tiap kelas boleh
    // berbeda (mis. Modul Ajar Fikih kelas X, XI, XII masing-masing berkasnya sendiri).
    // v28 — `ta`/`semester`: berkas dipisah PER SEMESTER (daftar guru/kelas/mapel tetap satu).
    'perangkat_kelas' => ['perangkat_id', 'guru_id', 'kunci', 'kelas', 'ta', 'semester', 'urutan',
                          'cp', 'cp_nama', 'atp', 'atp_nama', 'rpp', 'rpp_nama'],
    // v28 — periode (Tahun Ajaran + Semester) yang diatur Super Admin.
    'periode'         => ['ta', 'semester', 'urutan', 'bawaan', 'aktif'],
    // v29 — kohort siswa: selain tahun ajaran, 20 kolom angka (PMB + 3 tingkat × 6 kolom).
    // v31 — ditambah 4 kolom KELULUSAN (grup "Lulus": jumlah lulus, kuliah, bekerja, lainnya).
    // v32 — prestasi per kelas: peringkat 1-3 tiap kelas per (tahun ajaran, semester).
    'prestasi_kelas'  => ['ta', 'semester', 'kelas_id', 'kelas', 'peringkat', 'siswa_id', 'nama',
                          'foto', 'nilai', 'aktif'],
    'kohort'          => ['ta', 'pmb_pendaftar', 'pmb_diterima',
                          'x_jumlah', 'x_masuk', 'x_keluar', 'x_tinggal', 'x_naik', 'x_dropout',
                          'xi_jumlah', 'xi_masuk', 'xi_keluar', 'xi_tinggal', 'xi_naik', 'xi_dropout',
                          'xii_jumlah', 'xii_masuk', 'xii_keluar', 'xii_tinggal', 'xii_naik', 'xii_dropout',
                          'lulus_jumlah', 'lulus_kuliah', 'lulus_bekerja', 'lulus_lainnya'],
    'galeri_video'  => ['youtube'],
    'poll'          => ['pertanyaan', 'aktif'],
    'poll_pilihan'  => ['teks', 'urutan', 'aktif'],
    'poll_suara'    => ['pilihan_id', 'token'],
    'prestasi'      => ['kategori', 'nama', 'tingkat', 'sertifikat', 'urutan', 'aktif'],
    'alumni'        => ['nama', 'tahun_keluar', 'keterangan', 'sumber', 'urutan', 'aktif'],
    // v17 — daftar penerima PIP (Kartu PIP & surat kuasa disimpan sebagai URL media proxy).
    // v18 — `tahun_id` mengaitkan setiap penerima dengan tahun layanan PIP-nya.
    'pip_penerima'  => ['nama', 'nisn', 'kelas', 'nominal', 'kartu', 'kartu_nama', 'surat',
                        'surat_nama', 'keterangan', 'urutan', 'aktif', 'tahun_id'],
    // v18 — tahun layanan PIP (SK Penerima & catatan juga menempel pada tahun).
    'pip_tahun'     => ['tahun', 'sk', 'sk_nama', 'catatan', 'urutan', 'aktif'],
    // v34 — MENU KURIKULUM (kalender pendidikan, jadwal mengajar, dokumen kurikulum).
    'kurikulum_kaldik'   => ['judul', 'keterangan', 'gambar', 'urutan', 'aktif'],
    'kurikulum_jadwal'   => ['hari', 'waktu', 'jam_ke', 'kelas', 'mapel', 'guru', 'urutan'],
    'kurikulum_dokumen'  => ['judul', 'keterangan', 'url', 'nama_berkas', 'jenis', 'ukuran',
                             'urutan', 'aktif'],
];

/** Penanda skema cocok & seluruh tabel/kolom wajib benar-benar ada? */
function skema_terkini(): bool
{
    if (!is_file(SCHEMA_FILE) || trim((string) @file_get_contents(SCHEMA_FILE)) !== SCHEMA_VERSION) {
        return false;
    }
    try {
        $pdo = db();
        foreach (SCHEMA_TABEL_WAJIB as $t) {
            $ada = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = " . $pdo->quote($t))->fetchColumn();
            if (!$ada) {
                return false;
            }
        }
        foreach (SCHEMA_KOLOM_WAJIB as $t => $kolom) {
            $dimiliki = [];
            foreach ($pdo->query('PRAGMA table_info(' . $t . ')') as $r) {
                $dimiliki[] = strtolower((string) $r['name']);
            }
            foreach ((array) $kolom as $k) {
                if (!in_array(strtolower((string) $k), $dimiliki, true)) {
                    return false;
                }
            }
        }
    } catch (Throwable $e) {
        return false;
    }
    return true;
}

function db_add_column(PDO $pdo, string $tabel, string $kolom, string $definisi): void
{
    $dimiliki = [];
    foreach ($pdo->query('PRAGMA table_info(' . $tabel . ')') as $r) {
        $dimiliki[] = strtolower((string) $r['name']);
    }
    if (!in_array(strtolower($kolom), $dimiliki, true)) {
        $pdo->exec('ALTER TABLE ' . $tabel . ' ADD COLUMN ' . $kolom . ' ' . $definisi);
    }
}

function ensure_schema(): void
{
    if (skema_terkini()) {
        return;
    }
    $pdo = db();
    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        db_tabel($pdo);
        db_kolom_tambahan($pdo);
        // ⚠️ URUTAN PENTING: isi awal (yang juga MEMBUAT baris menu bawaan) dijalankan LEBIH DULU,
        // baru penyesuaian jenis menu (bos/sarpras). Bila dibalik, instalasi BARU tetap memakai
        // jenis `halaman` (menu bawaan dibuat setelah penyesuaian) sehingga halaman Informasi BOS /
        // Sarpras tidak pernah tampil sampai migrasi berikutnya. Kedua penyesuaian itu idempoten.
        db_isi_awal($pdo);
        db_menu_bos($pdo);
        db_menu_sarpras($pdo);
        db_menu_perpustakaan($pdo);
        db_menu_supervisi($pdo);
        db_menu_voting($pdo);
        db_menu_pendaftar($pdo);
        db_menu_edm($pdo);
        db_menu_perangkat($pdo);
        db_perangkat_pindah_kelas($pdo);
        // v28 — periode (Tahun Ajaran + Semester) diisi lebih dulu, baru berkas perangkat diberi
        // periode bawaan supaya berkas yang sudah ada tetap tampil di halaman publik.
        db_periode_awal($pdo);
        db_perangkat_semester($pdo);
        db_menu_pip($pdo);
        db_pip_tahun($pdo);
        db_menu_kohort($pdo);
        db_menu_kurikulum($pdo);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        error_log('ensure_schema gagal: ' . $e->getMessage());
        throw $e;
    }
    $pdo->exec('PRAGMA foreign_keys = ON');
    if (!is_dir(dirname(SCHEMA_FILE))) {
        @mkdir(dirname(SCHEMA_FILE), 0777, true);
    }
    @file_put_contents(SCHEMA_FILE, SCHEMA_VERSION);
}

function db_tabel(PDO $pdo): void
{
    $sql = [];

    $sql[] = "CREATE TABLE IF NOT EXISTS sekolah (
        id INTEGER PRIMARY KEY,
        nama TEXT NOT NULL DEFAULT '',
        jenjang TEXT DEFAULT '',
        npsn TEXT DEFAULT '',
        nsm TEXT DEFAULT '',
        alamat TEXT DEFAULT '',
        desa TEXT DEFAULT '',
        kecamatan TEXT DEFAULT '',
        kabupaten TEXT DEFAULT '',
        provinsi TEXT DEFAULT '',
        telepon TEXT DEFAULT '',
        whatsapp TEXT DEFAULT '',
        email TEXT DEFAULT '',
        website TEXT DEFAULT '',
        kepala TEXT DEFAULT '',
        nip_kepala TEXT DEFAULT '',
        slogan TEXT DEFAULT '',
        kepala_foto TEXT DEFAULT '',
        jenis TEXT NOT NULL DEFAULT 'madrasah',
        logo TEXT DEFAULT '',
        maps TEXT DEFAULT '',
        akreditasi TEXT DEFAULT '',
        tahun_ajaran TEXT DEFAULT '',
        jam_operasional TEXT DEFAULT '',
        video_profil TEXT DEFAULT '',
        diperbarui TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS pengaturan (
        kunci TEXT PRIMARY KEY,
        nilai TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        username TEXT NOT NULL UNIQUE,
        sandi TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'admin',
        email TEXT DEFAULT '',
        telepon TEXT DEFAULT '',
        foto TEXT DEFAULT '',
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT DEFAULT '',
        terakhir TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS log_masuk (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        waktu TEXT DEFAULT '',
        username TEXT DEFAULT '',
        nama TEXT DEFAULT '',
        ip TEXT DEFAULT '',
        peramban TEXT DEFAULT '',
        berhasil INTEGER NOT NULL DEFAULT 0,
        keterangan TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS menu (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        induk_id INTEGER NOT NULL DEFAULT 0,
        label TEXT NOT NULL,
        slug TEXT DEFAULT '',
        jenis TEXT NOT NULL DEFAULT 'halaman',
        tautan TEXT DEFAULT '',
        judul TEXT DEFAULT '',
        subjudul TEXT DEFAULT '',
        isi TEXT DEFAULT '',
        ikon TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        tampil_nav INTEGER NOT NULL DEFAULT 1,
        sorot INTEGER NOT NULL DEFAULT 0,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS slider (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        badge TEXT DEFAULT '',
        judul TEXT NOT NULL DEFAULT '',
        subjudul TEXT DEFAULT '',
        keterangan TEXT DEFAULT '',
        gambar TEXT DEFAULT '',
        tombol_teks TEXT DEFAULT '',
        tombol_tautan TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS slider_kecil (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        keterangan TEXT DEFAULT '',
        gambar TEXT DEFAULT '',
        tautan TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS sosial (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        ikon TEXT DEFAULT 'globe',
        tautan TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS keunggulan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        judul TEXT NOT NULL,
        keterangan TEXT DEFAULT '',
        ikon TEXT DEFAULT 'star',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS program (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        judul TEXT NOT NULL,
        keterangan TEXT DEFAULT '',
        ikon TEXT DEFAULT 'book',
        tautan TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS berita_kategori (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        slug TEXT NOT NULL,
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS berita (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        judul TEXT NOT NULL,
        slug TEXT NOT NULL,
        kategori_id INTEGER DEFAULT 0,
        ringkasan TEXT DEFAULT '',
        isi TEXT DEFAULT '',
        gambar TEXT DEFAULT '',
        penulis TEXT DEFAULT '',
        tanggal TEXT DEFAULT '',
        terbit INTEGER NOT NULL DEFAULT 1,
        dilihat INTEGER NOT NULL DEFAULT 0,
        dibuat TEXT DEFAULT '',
        diperbarui TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS agenda (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        judul TEXT NOT NULL,
        tanggal TEXT DEFAULT '',
        waktu TEXT DEFAULT '',
        tempat TEXT DEFAULT '',
        keterangan TEXT DEFAULT '',
        gambar TEXT DEFAULT '',
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS galeri_album (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        slug TEXT NOT NULL,
        keterangan TEXT DEFAULT '',
        sampul TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS galeri_foto (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        album_id INTEGER NOT NULL DEFAULT 0,
        gambar TEXT NOT NULL,
        keterangan TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        dibuat TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS galeri_video (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        album_id INTEGER NOT NULL DEFAULT 0,
        judul TEXT NOT NULL,
        youtube TEXT NOT NULL,
        keterangan TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS fasilitas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        keterangan TEXT DEFAULT '',
        gambar TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS testimoni (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        jabatan TEXT DEFAULT '',
        isi TEXT DEFAULT '',
        foto TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS kelas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        tingkat TEXT DEFAULT '',
        wali TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS guru (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        nip TEXT DEFAULT '',
        jenis_kelamin TEXT DEFAULT 'L',
        jabatan TEXT DEFAULT 'Guru',
        mapel TEXT DEFAULT '',
        email TEXT DEFAULT '',
        telepon TEXT DEFAULT '',
        foto TEXT DEFAULT '',
        status TEXT NOT NULL DEFAULT 'aktif',
        tmt TEXT DEFAULT '',
        keterangan TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        dibuat TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS siswa (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nis TEXT DEFAULT '',
        nisn TEXT DEFAULT '',
        nama TEXT NOT NULL,
        jenis_kelamin TEXT DEFAULT 'L',
        tempat_lahir TEXT DEFAULT '',
        tanggal_lahir TEXT DEFAULT '',
        kelas_id INTEGER DEFAULT 0,
        nama_ayah TEXT DEFAULT '',
        nama_ibu TEXT DEFAULT '',
        wali TEXT DEFAULT '',
        telepon TEXT DEFAULT '',
        alamat TEXT DEFAULT '',
        tanggal_masuk TEXT DEFAULT '',
        tahun_lulus TEXT DEFAULT '',
        keterangan TEXT DEFAULT '',
        status TEXT NOT NULL DEFAULT 'aktif',
        foto TEXT DEFAULT '',
        dibuat TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS kenaikan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        siswa_id INTEGER NOT NULL,
        kelas_asal TEXT DEFAULT '',
        kelas_tujuan TEXT DEFAULT '',
        jenis TEXT NOT NULL DEFAULT 'naik',
        tahun_ajaran TEXT DEFAULT '',
        catatan TEXT DEFAULT '',
        oleh TEXT DEFAULT '',
        waktu TEXT DEFAULT '',
        dibatalkan INTEGER NOT NULL DEFAULT 0
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS ppdb_gelombang (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        tahun_ajaran TEXT DEFAULT '',
        mulai TEXT DEFAULT '',
        selesai TEXT DEFAULT '',
        kuota INTEGER NOT NULL DEFAULT 0,
        jalur TEXT DEFAULT '',
        keterangan TEXT DEFAULT '',
        aktif INTEGER NOT NULL DEFAULT 1,
        urutan INTEGER NOT NULL DEFAULT 10
    )";

    /* v25 — PERANGKAT GURU: berkas perangkat pembelajaran (CP, ATP, RPP/Modul Ajar) milik setiap
       guru untuk setiap MATA PELAJARAN yang diampu, beserta kode unduhnya.
       • `perangkat` — satu baris per (guru × mata pelajaran); menyimpan HANYA URL CDN hasil media
         proxy + nama berkas aslinya (berkasnya tidak disimpan di server ini).
         Sejak v26 satu baris juga menyimpan **daftar KELAS** mata pelajaran itu (`kelas`, dipisah
         koma) karena satu mata pelajaran bisa diajarkan di beberapa kelas — pada tabel publik
         baris (guru × mapel) dipecah menjadi baris (kelas × mapel).
       • `perangkat_kode` — KODE UNDUH per MATA PELAJARAN (diatur/di-generate Super Admin), dipakai
         popup "masukkan kode download" pada halaman publik. Satu mata pelajaran = satu kode,
         sehingga mata pelajaran berbeda memakai kode berbeda pula. */
    $sql[] = "CREATE TABLE IF NOT EXISTS perangkat (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        guru_id INTEGER NOT NULL REFERENCES guru(id) ON DELETE CASCADE,
        mapel TEXT NOT NULL DEFAULT '',
        kunci TEXT NOT NULL DEFAULT '',
        kelas TEXT NOT NULL DEFAULT '',
        cp TEXT DEFAULT '', cp_nama TEXT DEFAULT '',
        atp TEXT DEFAULT '', atp_nama TEXT DEFAULT '',
        rpp TEXT DEFAULT '', rpp_nama TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 0,
        dibuat TEXT,
        diubah TEXT,
        UNIQUE (guru_id, kunci)
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS perangkat_kode (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        mapel TEXT NOT NULL DEFAULT '',
        kunci TEXT NOT NULL UNIQUE,
        kode TEXT NOT NULL DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 0,
        dibuat TEXT,
        diubah TEXT
    )";

    /* v27 — BERKAS PER KELAS (`perangkat_kelas`): satu baris per (perangkat × kelas).
       Permintaan pemilik: *"Pada bagian upload perangkat tambahkan fitur file yang akan di upload
       masuk dimana. Misal Upload Modul ajar Fikih untuk kelas 10, 11 kelas 12. Karena fitur yang ada
       sekarang satu file upload untuk semua kelas setiap mata pelajaran."*
       Jadi berkas CP/ATP/RPP/Modul Ajar boleh BERBEDA tiap kelas: baris (guru × mata pelajaran) pada
       tabel `perangkat` tetap menjadi identitasnya (kode unduh per mata pelajaran), sedangkan
       berkasnya disimpan di sini per kelas. Kelas kosong ('') = mata pelajaran tanpa kelas.
       Kolom `guru_id` & `kunci` sengaja ikut disimpan (denormalisasi) supaya tabel publik cukup
       memakai satu kueri. */
    // v28 — `ta`/`semester`: satu (perangkat × kelas) boleh punya berkas BERBEDA tiap semester.
    $sql[] = "CREATE TABLE IF NOT EXISTS perangkat_kelas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        perangkat_id INTEGER NOT NULL REFERENCES perangkat(id) ON DELETE CASCADE,
        guru_id INTEGER NOT NULL DEFAULT 0,
        kunci TEXT NOT NULL DEFAULT '',
        kelas TEXT NOT NULL DEFAULT '',
        ta TEXT NOT NULL DEFAULT '',
        semester TEXT NOT NULL DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 0,
        cp TEXT DEFAULT '', cp_nama TEXT DEFAULT '',
        atp TEXT DEFAULT '', atp_nama TEXT DEFAULT '',
        rpp TEXT DEFAULT '', rpp_nama TEXT DEFAULT '',
        dibuat TEXT,
        diubah TEXT,
        UNIQUE (perangkat_id, kelas, ta, semester)
    )";

    /* v28 — PERIODE (Tahun Ajaran + Semester) yang diatur Super Admin pada menu
       "Tahun Ajaran & Semester". Satu baris = satu semester pada satu tahun ajaran, mis.
       "2026/2027 · Ganjil". Kolom `bawaan` (1) menandai periode yang sedang berjalan — halaman
       publik membukanya lebih dulu dan panel Super Admin memakainya sebagai pilihan awal. */
    $sql[] = "CREATE TABLE IF NOT EXISTS periode (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ta TEXT NOT NULL DEFAULT '',
        semester TEXT NOT NULL DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 0,
        bawaan INTEGER NOT NULL DEFAULT 0,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT,
        diubah TEXT,
        UNIQUE (ta, semester)
    )";

    /* v29 — KOHORT SISWA (menu Siswa → Kohort Siswa). Satu baris = satu tahun ajaran.
       Angka diisi Super Admin pada panel admin (`admin/kohort.php`): PMB (pendaftar, diterima)
       dan tiap tingkat kelas X/XI/XII (jumlah, mutasi masuk, mutasi keluar, tinggal kelas,
       naik kelas, drop out). Halaman publik hanya menampilkan 5 tahun ajaran terbaru, tetapi
       baris lama TIDAK dihapus — tetap tersimpan sebagai arsip. */
    $sql[] = "CREATE TABLE IF NOT EXISTS kohort (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ta TEXT NOT NULL DEFAULT '',
        pmb_pendaftar INTEGER NOT NULL DEFAULT 0,
        pmb_diterima INTEGER NOT NULL DEFAULT 0,
        x_jumlah INTEGER NOT NULL DEFAULT 0,
        x_masuk INTEGER NOT NULL DEFAULT 0,
        x_keluar INTEGER NOT NULL DEFAULT 0,
        x_tinggal INTEGER NOT NULL DEFAULT 0,
        x_naik INTEGER NOT NULL DEFAULT 0,
        x_dropout INTEGER NOT NULL DEFAULT 0,
        xi_jumlah INTEGER NOT NULL DEFAULT 0,
        xi_masuk INTEGER NOT NULL DEFAULT 0,
        xi_keluar INTEGER NOT NULL DEFAULT 0,
        xi_tinggal INTEGER NOT NULL DEFAULT 0,
        xi_naik INTEGER NOT NULL DEFAULT 0,
        xi_dropout INTEGER NOT NULL DEFAULT 0,
        xii_jumlah INTEGER NOT NULL DEFAULT 0,
        xii_masuk INTEGER NOT NULL DEFAULT 0,
        xii_keluar INTEGER NOT NULL DEFAULT 0,
        xii_tinggal INTEGER NOT NULL DEFAULT 0,
        xii_naik INTEGER NOT NULL DEFAULT 0,
        xii_dropout INTEGER NOT NULL DEFAULT 0,
        lulus_jumlah INTEGER NOT NULL DEFAULT 0,
        lulus_kuliah INTEGER NOT NULL DEFAULT 0,
        lulus_bekerja INTEGER NOT NULL DEFAULT 0,
        lulus_lainnya INTEGER NOT NULL DEFAULT 0,
        dibuat TEXT,
        diubah TEXT,
        UNIQUE (ta)
    )";

    /* v32 — PRESTASI SISWA PER KELAS (peringkat 1-3 tiap kelas per tahun ajaran & semester).
       Dipakai blok "Kartu Prestasi Siswa per Kelas" pada halaman Prestasi Siswa; datanya diisi
       Super Admin atau akun Editor (admin/prestasi_kelas.php). Satu baris = satu PERINGKAT pada
       satu kelas di satu periode, sehingga peringkat 1-3 per kelas bersifat unik. */
    $sql[] = "CREATE TABLE IF NOT EXISTS prestasi_kelas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ta TEXT NOT NULL DEFAULT '',
        semester TEXT NOT NULL DEFAULT '',
        kelas_id INTEGER NOT NULL DEFAULT 0,
        kelas TEXT NOT NULL DEFAULT '',
        peringkat INTEGER NOT NULL DEFAULT 1,
        siswa_id INTEGER NOT NULL DEFAULT 0,
        nama TEXT NOT NULL DEFAULT '',
        foto TEXT DEFAULT '',
        nilai REAL NOT NULL DEFAULT 0,
        urutan INTEGER NOT NULL DEFAULT 0,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT,
        diubah TEXT,
        UNIQUE (ta, semester, kelas_id, peringkat)
    )";

    /* v34 — MENU "KURIKULUM" (tiga isian pada satu panel admin; lihat admin/kurikulum.php).
       (1) `kurikulum_kaldik` — gambar KALENDER PENDIDIKAN OPERASIONAL. Yang disimpan hanya URL
           media proxy; boleh lebih dari satu gambar (mis. per semester) dan diurutkan admin.
       (2) `kurikulum_jadwal` — JADWAL MENGAJAR per baris: hari, waktu, jam ke, kelas, mata
           pelajaran + guru. `jam_ke = 0` DAN `kelas = ''` menandai baris KEGIATAN (Sholat Dhuha,
           Istirahat, Sholat Dhuhur, …) yang pada halaman publik dicetak sebagai satu baris
           menggabung seluruh kolom kelas — persis contoh yang dikirim pemilik.
       (3) `kurikulum_dokumen` — DOKUMEN KURIKULUM (PDF/Word/Excel/PowerPoint/gambar) yang tampil
           sebagai kartu dan dapat diunduh pengunjung. Yang disimpan hanya URL media proxy + nama
           asli berkasnya (dipakai sebagai nama unduhan).
       Ketiga tabel KOSONG setelah migrasi — tidak ada contoh yang dipaksakan. */
    $sql[] = "CREATE TABLE IF NOT EXISTS kurikulum_kaldik (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        judul TEXT NOT NULL DEFAULT '',
        keterangan TEXT NOT NULL DEFAULT '',
        gambar TEXT NOT NULL DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 0,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT,
        diubah TEXT
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS kurikulum_jadwal (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        hari TEXT NOT NULL DEFAULT '',
        waktu TEXT NOT NULL DEFAULT '',
        jam_ke INTEGER NOT NULL DEFAULT 0,
        kelas TEXT NOT NULL DEFAULT '',
        mapel TEXT NOT NULL DEFAULT '',
        guru TEXT NOT NULL DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 0,
        dibuat TEXT,
        diubah TEXT
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS kurikulum_dokumen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        judul TEXT NOT NULL DEFAULT '',
        keterangan TEXT NOT NULL DEFAULT '',
        url TEXT NOT NULL DEFAULT '',
        nama_berkas TEXT NOT NULL DEFAULT '',
        jenis TEXT NOT NULL DEFAULT '',
        ukuran INTEGER NOT NULL DEFAULT 0,
        urutan INTEGER NOT NULL DEFAULT 0,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT,
        diubah TEXT
    )";

    /* v22 — DATA PENDAFTAR TAHUN LAMA (hasil TARIK dari panel PPDB lama ma.stm.sch.id).
       Yang disimpan hanya empat kolom yang diminta pemilik: Nomor Pendaftaran, Nama Siswa,
       Asal Sekolah (+ tahun). Data tahun lalu TIDAK masuk ke `ppdb_pendaftar` supaya status
       seleksi & pengumuman tahun berjalan tidak tercampur. Satu baris = satu pendaftar. */
    $sql[] = "CREATE TABLE IF NOT EXISTS ppdb_lama (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tahun INTEGER NOT NULL,
        nomor TEXT NOT NULL DEFAULT '',
        nama TEXT NOT NULL DEFAULT '',
        asal_sekolah TEXT NOT NULL DEFAULT '',
        sumber TEXT NOT NULL DEFAULT 'panel',
        urutan INTEGER NOT NULL DEFAULT 0,
        ditarik TEXT DEFAULT '',
        dibuat TEXT
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS ppdb_pendaftar (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nomor TEXT NOT NULL UNIQUE,
        gelombang_id INTEGER DEFAULT 0,
        nama TEXT NOT NULL,
        nisn TEXT DEFAULT '',
        jenis_kelamin TEXT DEFAULT 'L',
        tempat_lahir TEXT DEFAULT '',
        tanggal_lahir TEXT DEFAULT '',
        asal_sekolah TEXT DEFAULT '',
        nama_ayah TEXT DEFAULT '',
        nama_ibu TEXT DEFAULT '',
        wali TEXT DEFAULT '',
        telepon TEXT DEFAULT '',
        email TEXT DEFAULT '',
        alamat TEXT DEFAULT '',
        jalur TEXT DEFAULT '',
        catatan_pendaftar TEXT DEFAULT '',
        berkas TEXT DEFAULT '{}',
        status TEXT NOT NULL DEFAULT 'baru',
        nilai_seleksi TEXT DEFAULT '',
        catatan_panitia TEXT DEFAULT '',
        tanggal_daftar TEXT DEFAULT '',
        diubah TEXT DEFAULT '',
        ip TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS alumni (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nis TEXT DEFAULT '',
        nisn TEXT DEFAULT '',
        nama TEXT NOT NULL,
        jenis_kelamin TEXT DEFAULT 'L',
        tahun_masuk TEXT DEFAULT '',
        tahun_keluar TEXT DEFAULT '',
        keterangan TEXT DEFAULT '',
        foto TEXT DEFAULT '',
        sumber TEXT NOT NULL DEFAULT 'manual',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS prestasi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kategori TEXT NOT NULL DEFAULT 'siswa',
        siswa_id INTEGER NOT NULL DEFAULT 0,
        guru_id INTEGER NOT NULL DEFAULT 0,
        nama TEXT NOT NULL DEFAULT '',
        jabatan TEXT DEFAULT '',
        jenis TEXT DEFAULT '',
        tahun TEXT DEFAULT '',
        tingkat TEXT DEFAULT '',
        sertifikat TEXT DEFAULT '',
        keterangan TEXT DEFAULT '',
        foto TEXT DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS poll (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pertanyaan TEXT NOT NULL DEFAULT '',
        keterangan TEXT DEFAULT '',
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS poll_pilihan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        poll_id INTEGER NOT NULL DEFAULT 0,
        teks TEXT NOT NULL,
        urutan INTEGER NOT NULL DEFAULT 10,
        aktif INTEGER NOT NULL DEFAULT 1
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS poll_suara (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        poll_id INTEGER NOT NULL DEFAULT 0,
        pilihan_id INTEGER NOT NULL DEFAULT 0,
        waktu TEXT DEFAULT '',
        ip TEXT DEFAULT '',
        token TEXT DEFAULT ''
    )";

    $sql[] = "CREATE TABLE IF NOT EXISTS kontak_pesan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        email TEXT DEFAULT '',
        telepon TEXT DEFAULT '',
        subjek TEXT DEFAULT '',
        pesan TEXT DEFAULT '',
        dibaca INTEGER NOT NULL DEFAULT 0,
        waktu TEXT DEFAULT '',
        ip TEXT DEFAULT ''
    )";

    // v10 — BAGAN STRUKTUR ORGANISASI (kotak-kotak yang dapat diatur admin pada panel).
    $sql[] = "CREATE TABLE IF NOT EXISTS struktur (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL DEFAULT '',
        induk_id INTEGER NOT NULL DEFAULT 0,
        urutan INTEGER NOT NULL DEFAULT 0,
        garis TEXT NOT NULL DEFAULT 'penuh',
        aksen INTEGER NOT NULL DEFAULT 0,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT DEFAULT ''
    )";

    // v14 — FAQ ASISTEN (pertanyaan & jawaban yang diisi admin; jawaban lain disusun otomatis dari
    // data website oleh inc/asisten.php). Tabel BARU, tabel lama tidak disentuh.
    $sql[] = "CREATE TABLE IF NOT EXISTS faq (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pertanyaan TEXT NOT NULL DEFAULT '',
        jawaban TEXT NOT NULL DEFAULT '',
        kata_kunci TEXT NOT NULL DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 0,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT DEFAULT ''
    )";

    // v17 — LAYANAN PIP: daftar penerima Program Indonesia Pintar.
    // `kartu` & `surat` menyimpan URL berkas pada media proxy (bukan berkas di server aplikasi).
    $sql[] = "CREATE TABLE IF NOT EXISTS pip_penerima (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL DEFAULT '',
        nisn TEXT NOT NULL DEFAULT '',
        kelas TEXT NOT NULL DEFAULT '',
        nominal INTEGER NOT NULL DEFAULT 0,
        kartu TEXT NOT NULL DEFAULT '',
        kartu_nama TEXT NOT NULL DEFAULT '',
        surat TEXT NOT NULL DEFAULT '',
        surat_nama TEXT NOT NULL DEFAULT '',
        keterangan TEXT NOT NULL DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 0,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT DEFAULT '',
        diubah TEXT DEFAULT ''
    )";

    // v18 — TAHUN LAYANAN PIP: SK Penerima PIP & daftar penerima BERBEDA setiap tahun
    // (permintaan pemilik). Satu baris = satu tahun; `sk`/`sk_nama`/`catatan` menempel pada tahun itu.
    $sql[] = "CREATE TABLE IF NOT EXISTS pip_tahun (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tahun TEXT NOT NULL DEFAULT '',
        sk TEXT NOT NULL DEFAULT '',
        sk_nama TEXT NOT NULL DEFAULT '',
        catatan TEXT NOT NULL DEFAULT '',
        urutan INTEGER NOT NULL DEFAULT 0,
        aktif INTEGER NOT NULL DEFAULT 1,
        dibuat TEXT DEFAULT '',
        diubah TEXT DEFAULT ''
    )";

    foreach ($sql as $s) {
        $pdo->exec($s);
    }

    $idx = [
        'CREATE INDEX IF NOT EXISTS idx_menu_induk ON menu (induk_id, urutan)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_menu_slug ON menu (slug)',
        'CREATE INDEX IF NOT EXISTS idx_berita_tanggal ON berita (terbit, tanggal)',
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_berita_slug ON berita (slug)',
        'CREATE INDEX IF NOT EXISTS idx_galeri_album ON galeri_foto (album_id, urutan)',
        'CREATE INDEX IF NOT EXISTS idx_siswa_status ON siswa (status, kelas_id)',
        'CREATE INDEX IF NOT EXISTS idx_guru_status ON guru (status, urutan)',
        'CREATE INDEX IF NOT EXISTS idx_ppdb_status ON ppdb_pendaftar (gelombang_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_ppdb_lama_tahun ON ppdb_lama (tahun, urutan, nomor)',
        'CREATE INDEX IF NOT EXISTS idx_perangkat_guru ON perangkat (guru_id, urutan, mapel)',
        'CREATE INDEX IF NOT EXISTS idx_perangkat_kunci ON perangkat_kode (kunci)',
        // v27 — berkas per kelas: dipakai saat memuat tabel publik & menyimpan panel.
        'CREATE INDEX IF NOT EXISTS idx_perangkat_kelas ON perangkat_kelas (perangkat_id, urutan, kelas)',
        'CREATE INDEX IF NOT EXISTS idx_perangkat_kelas_kunci ON perangkat_kelas (guru_id, kunci, kelas)',
        // ⚠️ Indeks `idx_perangkat_kelas_periode` TIDAK ada di sini: kolom `ta` baru ditambahkan
        // db_perangkat_semester(), sedangkan daftar indeks ini dijalankan LEBIH DULU — memaksanya di
        // sini membuat migrasi basis data lama gagal ("no such column: ta") dan ROLLBACK.
        'CREATE INDEX IF NOT EXISTS idx_periode_urutan ON periode (urutan, ta, semester)',
        // v29 — kohort siswa: urut tahun ajaran (halaman publik mengambil 5 tahun terbaru).
        'CREATE INDEX IF NOT EXISTS idx_kohort_ta ON kohort (ta)',
        // v32 — prestasi per kelas: pencarian per PERIODE (ta + semester) lalu kelas & peringkat.
        'CREATE INDEX IF NOT EXISTS idx_prestasi_kelas_periode ON prestasi_kelas (ta, semester, kelas_id, peringkat)',
        // v34 — menu Kurikulum: kaldik & dokumen diurutkan saat ditampilkan, jadwal disaring per HARI.
        'CREATE INDEX IF NOT EXISTS idx_kurikulum_kaldik ON kurikulum_kaldik (aktif, urutan, id)',
        'CREATE INDEX IF NOT EXISTS idx_kurikulum_jadwal ON kurikulum_jadwal (hari, urutan, id)',
        'CREATE INDEX IF NOT EXISTS idx_kurikulum_dokumen ON kurikulum_dokumen (aktif, urutan, id)',
        'CREATE INDEX IF NOT EXISTS idx_pesan_dibaca ON kontak_pesan (dibaca, waktu)',
        'CREATE INDEX IF NOT EXISTS idx_log_waktu ON log_masuk (waktu, berhasil)',
        'CREATE INDEX IF NOT EXISTS idx_poll_pilihan ON poll_pilihan (poll_id, urutan)',
        'CREATE INDEX IF NOT EXISTS idx_poll_suara ON poll_suara (poll_id, pilihan_id)',
        'CREATE INDEX IF NOT EXISTS idx_poll_token ON poll_suara (poll_id, token)',
        'CREATE INDEX IF NOT EXISTS idx_prestasi_kategori ON prestasi (kategori, aktif, urutan)',
        'CREATE INDEX IF NOT EXISTS idx_prestasi_tahun ON prestasi (kategori, tahun)',
        'CREATE INDEX IF NOT EXISTS idx_alumni_aktif ON alumni (aktif, urutan)',
        'CREATE INDEX IF NOT EXISTS idx_alumni_tahun ON alumni (tahun_keluar)',
        'CREATE INDEX IF NOT EXISTS idx_struktur_induk ON struktur (induk_id, urutan)',
        'CREATE INDEX IF NOT EXISTS idx_faq_aktif ON faq (aktif, urutan)',
        'CREATE INDEX IF NOT EXISTS idx_pip_urutan ON pip_penerima (aktif, urutan, id)',
        'CREATE INDEX IF NOT EXISTS idx_pip_tahun_urutan ON pip_tahun (urutan, id)',
    ];
    foreach ($idx as $s) {
        $pdo->exec($s);
    }
}

/** Kolom tambahan (dijalankan setiap migrasi — idempoten) — daftarkan juga di SCHEMA_KOLOM_WAJIB. */
function db_kolom_tambahan(PDO $pdo): void
{
    db_add_column($pdo, 'sekolah', 'video_profil', "TEXT DEFAULT ''");
    // v2: foto kepala madrasah/sekolah (dipakai kartu Sambutan pada beranda).
    db_add_column($pdo, 'sekolah', 'kepala_foto', "TEXT DEFAULT ''");
    // v4: keterangan kondisi siswa/alumni setelah lulus (mis. "Kuliah di …", "Bekerja di …").
    db_add_column($pdo, 'siswa', 'keterangan', "TEXT DEFAULT ''");
    db_add_column($pdo, 'sekolah', 'whatsapp', "TEXT DEFAULT ''");
    db_add_column($pdo, 'menu', 'sorot', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column($pdo, 'galeri_video', 'youtube', "TEXT DEFAULT ''");

    // v13 — DOKUMEN IJOP & AKREDITASI pada halaman Profil Lengkap (gambar/PDF lewat media proxy).
    // Hanya MENAMBAH kolom; sekolah lama bernilai kosong → tombol "Lihat" tidak dicetak.
    db_add_column($pdo, 'sekolah', 'berkas_ijop', "TEXT DEFAULT ''");
    db_add_column($pdo, 'sekolah', 'berkas_akreditasi', "TEXT DEFAULT ''");

    // v12 — BAGAN STRUKTUR ORGANISASI menjadi PETA TETAP: setiap kotak punya `kode` slot
    // (posisinya ditentukan kode) dan `foto` (pas foto). Hanya MENAMBAH kolom; kolom lama
    // (`induk_id`/`urutan`/`garis`/`aksen`) dibiarkan ada tetapi tidak lagi dipakai untuk
    // mengatur letak — lihat db_struktur_peta().
    db_add_column($pdo, 'struktur', 'kode', "TEXT DEFAULT ''");
    db_add_column($pdo, 'struktur', 'foto', "TEXT DEFAULT ''");

    // v11 — TAHUN pada ALBUM GALERI (mis. "Pramuka 2024", "Pramuka 2025"). Hanya MENAMBAH kolom;
    // album lama bernilai kosong (tidak ada nilai yang ditebak) dan tetap tampil tanpa tahun.
    db_add_column($pdo, 'galeri_album', 'tahun', "TEXT DEFAULT ''");

    // v26 — KELAS pada PERANGKAT GURU: satu mata pelajaran boleh diajarkan di beberapa kelas
    // (mis. Fikih di kelas X, XI, dan XII). Hanya MENAMBAH kolom; baris perangkat lama bernilai
    // kosong sehingga tampil sebagai satu baris tanpa kelas (perilaku sebelum pembaruan).
    db_add_column($pdo, 'perangkat', 'kelas', "TEXT NOT NULL DEFAULT ''");

    // v28 — PERIODE (tahun ajaran + semester) pada berkas perangkat. Hanya MENAMBAH kolom; baris
    // lama diberi periode berjalan oleh db_perangkat_semester() sehingga berkasnya tetap tampil.
    db_add_column($pdo, 'perangkat_kelas', 'ta', "TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo, 'perangkat_kelas', 'semester', "TEXT NOT NULL DEFAULT ''");

    // v33 — TMT (tanggal mulai tugas, `Y-m-d`) & Keterangan (Satmikal / Non Satmikal) pada guru.
    // Hanya MENAMBAH kolom; guru lama bernilai kosong (tidak ada nilai yang ditebak) sehingga masa
    // kerja tampil "-" sampai TMT-nya diisi.
    db_add_column($pdo, 'guru', 'tmt', "TEXT DEFAULT ''");
    db_add_column($pdo, 'guru', 'keterangan', "TEXT DEFAULT ''");

    // v31 — KELULUSAN pada tabel kohort (grup kolom "Lulus": jumlah lulus, kuliah, bekerja,
    // lainnya). Hanya MENAMBAH kolom; baris kohort yang sudah ada bernilai 0 sehingga tabel lama
    // tetap tampil apa adanya (tidak ada angka yang ditebak).
    db_add_column($pdo, 'kohort', 'lulus_jumlah', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column($pdo, 'kohort', 'lulus_kuliah', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column($pdo, 'kohort', 'lulus_bekerja', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column($pdo, 'kohort', 'lulus_lainnya', 'INTEGER NOT NULL DEFAULT 0');

    // v10 — ISIAN "PROFIL LENGKAP" (halaman Profil Lengkap) + BAGAN STRUKTUR ORGANISASI.
    // Hanya MENAMBAH kolom (tanpa membangun ulang tabel) sehingga data sekolah yang sudah ada aman;
    // kolom baru bernilai kosong.
    foreach ([
        'kode_pos'        => "TEXT DEFAULT ''",
        'npwp'            => "TEXT DEFAULT ''",
        'status_madrasah' => "TEXT DEFAULT ''",
        'waktu_belajar'   => "TEXT DEFAULT ''",
        'sk_ijop'         => "TEXT DEFAULT ''",
        'sk_ijop_tgl'     => "TEXT DEFAULT ''",
        'tahun_berdiri'   => "TEXT DEFAULT ''",
        'akreditasi_tgl'  => "TEXT DEFAULT ''",
        'penyelenggara'   => "TEXT DEFAULT ''",
        'sk_menkumham'    => "TEXT DEFAULT ''",
        'jarak_kecamatan' => "TEXT DEFAULT ''",
        'jarak_kabupaten' => "TEXT DEFAULT ''",
        'bangunan'        => "TEXT DEFAULT ''",
        'visi'            => "TEXT DEFAULT ''",
        'misi'            => "TEXT DEFAULT ''",
        'tujuan'          => "TEXT DEFAULT ''",
    ] as $kolom => $def) {
        db_add_column($pdo, 'sekolah', $kolom, $def);
    }
}

// =====================================================================================
// PENYESUAIAN DATA (idempoten, dijalankan setiap migrasi)
// =====================================================================================

/**
 * v15 — MENU "INFORMASI BOS" memakai jenis halaman BARU `bos`.
 *
 * Sub menu "Dana Bos" (di bawah menu "Informasi") yang dibuat admin pada panel hanya berjenis
 * `halaman` (isinya kosong). Sejak v15 jenisnya diubah menjadi `bos` supaya halaman itu menampilkan
 * Papan Informasi Dana BOS yang datanya dibaca langsung dari aplikasi e-BOS (lihat inc/bos.php).
 *
 * Catatan: pernyataan ini hanya mengubah baris yang MASIH berjenis `halaman`, sehingga pengaturan
 * admin tidak pernah ditimpa (bila admin memilih jenis lain, migrasi berikutnya tidak mengubahnya).
 */
function db_menu_bos(PDO $pdo): void
{
    $pdo->prepare("UPDATE menu SET jenis = 'bos' WHERE slug = ? AND jenis = 'halaman'")
        ->execute(['dana-bos']);
}

/**
 * v16 — MENU "INFORMASI SARPRAS" memakai jenis halaman BARU `sarpras`.
 *
 * Sub menu "Sarpras" (di bawah menu "Informasi") dibuat admin hanya berjenis `halaman` (isinya
 * kosong). Sejak v16 jenisnya diubah menjadi `sarpras` supaya halaman itu menampilkan **Daftar
 * Inventaris Barang** yang datanya dibaca langsung dari aplikasi e-GURU (lihat inc/sarpras.php).
 *
 * Catatan: hanya baris yang MASIH berjenis `halaman` yang diubah, sehingga pengaturan admin tidak
 * pernah ditimpa. Kedua slug (`sarana-prasarana` dan `sarpras`) dikenali agar tetap bekerja bila
 * admin kelak menyingkat alamat menunya.
 */
function db_menu_sarpras(PDO $pdo): void
{
    $st = $pdo->prepare("UPDATE menu SET jenis = 'sarpras' WHERE slug = ? AND jenis = 'halaman'");
    foreach (['sarana-prasarana', 'sarpras'] as $slug) {
        $st->execute([$slug]);
    }
}

/**
 * v25 — MENU "PERANGKAT GURU" memakai jenis halaman BARU `perangkat`.
 *
 * Permintaan pemilik: menu Perangkat Guru diisi tabel seperti contoh yang dikirim (No, Nama Guru,
 * Mata Pelajaran, Capaian Pembelajaran (CP), Alur Tujuan Pembelajaran (ATP), RPP/Modul Ajar) dengan
 * centang hijau bila berkasnya sudah diunggah dan silang merah bila belum, serta tombol Download
 * yang meminta KODE UNDUH per mata pelajaran — lihat `inc/perangkat.php` + `admin/perangkat.php`.
 *
 * Catatan: hanya baris menu yang MASIH berjenis `halaman` yang diubah (pengaturan admin tidak
 * pernah tertimpa).
 */
function db_menu_perangkat(PDO $pdo): void
{
    $st = $pdo->prepare("UPDATE menu SET jenis = 'perangkat' WHERE slug = ? AND jenis = 'halaman'");
    foreach (['perangkat-guru', 'perangkat', 'perangkat_guru'] as $slug) {
        $st->execute([$slug]);
    }
}

/**
 * v29 — MENU "KOHORT SISWA" (sub menu Siswa) memakai jenis halaman BARU `kohort`.
 *
 * Permintaan pemilik: halaman Kohort Siswa diisi **tabel analisis kohort** (Tahun Ajaran · PMB ·
 * Kelas X/XI/XII) beserta **analisis grafiknya** untuk 5 tahun ajaran terakhir — lihat
 * `inc/kohort.php` (halaman publik) & `admin/kohort.php` (isian Super Admin).
 *
 * Menu **dibuat** bila belum ada (instalasi baru) dan diletakkan sebagai sub menu dari menu induk
 * yang memuat halaman Data Siswa (jenis `siswa`); bila tidak ditemukan, dipakai menu "Akademik"/
 * "Kesiswaan"/"Informasi", dan sebagai menu utama bila semuanya tidak ada. Pembuatan menu dijaga
 * penanda `kohort_menu_v1` supaya tidak pernah dibuat dua kali dan perubahan nama/urutan/
 * penghapusan oleh admin tidak dibatalkan migrasi berikutnya.
 *
 * Penyesuaian jenis hanya mengubah baris yang MASIH berjenis `halaman`, sehingga pengaturan admin
 * tidak pernah tertimpa. Beberapa penulisan slug dikenali agar tetap bekerja bila alamat menunya
 * kelak disingkat.
 */
function db_menu_kohort(PDO $pdo): void
{
    if (!db_penanda('kohort_menu_v1')) {
        $slugAda = ['kohort-siswa', 'kohort', 'kohort_siswa'];
        $ada = 0;
        foreach ($slugAda as $slug) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM menu WHERE slug = ?');
            $st->execute([$slug]);
            if ((int) $st->fetchColumn() > 0) {
                $ada = 1;
                break;
            }
        }
        if ($ada === 0) {
            // Induk = menu yang memuat halaman Data Siswa (jenis `siswa`), supaya Kohort Siswa
            // berada di kelompok yang sama dengan Data Siswa pada navigasi.
            $st = $pdo->query("SELECT induk_id FROM menu WHERE jenis = 'siswa' AND induk_id > 0
                               ORDER BY urutan, id LIMIT 1");
            $induk = (int) $st->fetchColumn();
            if ($induk <= 0) {
                foreach (['akademik', 'kesiswaan', 'informasi'] as $slug) {
                    $s2 = $pdo->prepare('SELECT id FROM menu WHERE slug = ? LIMIT 1');
                    $s2->execute([$slug]);
                    $id = (int) $s2->fetchColumn();
                    if ($id > 0) {
                        $induk = $id;
                        break;
                    }
                }
            }
            $s3 = $pdo->prepare('SELECT COALESCE(MAX(urutan), 0) + 10 FROM menu WHERE induk_id = ?');
            $s3->execute([$induk]);
            $urut = (int) $s3->fetchColumn();
            $pdo->prepare('INSERT INTO menu (induk_id, label, slug, jenis, tautan, judul, subjudul, isi, ikon,
                            urutan, tampil_nav, sorot, aktif)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 1)')
                ->execute([$induk, 'Kohort Siswa', 'kohort-siswa', 'kohort', '', 'Kohort Siswa',
                    'Analisis kohort siswa 5 tahun terakhir', '', '', $urut]);
        }
        $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)')
            ->execute(['kohort_menu_v1', date('Y-m-d H:i:s')]);
    }

    // Bila menunya sudah ada tetapi masih berjenis halaman biasa → disesuaikan (idempoten).
    $st = $pdo->prepare("UPDATE menu SET jenis = 'kohort' WHERE slug = ? AND jenis = 'halaman'");
    foreach (['kohort-siswa', 'kohort', 'kohort_siswa'] as $slug) {
        $st->execute([$slug]);
    }
}

/**
 * v34 — TIGA SUB MENU "KURIKULUM" memakai jenis halaman BARU, yaitu `kaldik`,
 * `jadwal_mengajar`, dan `dokumen_kurikulum` (halaman publiknya ada di `inc/kurikulum.php`,
 * isiannya di `admin/kurikulum.php`).
 *
 * Permintaan pemilik: *"Pada bagian Menu Kurikulum buatkan isiannya di halaman admin dan operator
 * (yelfis). Satu menu untuk beberapa isian: Kaldik Operasional …, Jadwal mengajar …, Dokumen
 * kurikulum …"*.
 *
 * Menu **dibuat** bila belum ada (instalasi baru) sebagai sub menu dari menu induk **Kurikulum**
 * (atau induk yang memuat halaman Perangkat Guru / jenis `perangkat`), lalu jenisnya disesuaikan.
 * Pembuatan menu dijaga penanda `kurikulum_menu_v1` supaya tidak pernah dibuat dua kali dan
 * perubahan nama/urutan/penghapusan oleh admin tidak dibatalkan migrasi berikutnya.
 *
 * Penyesuaian jenis hanya mengubah baris yang MASIH berjenis `halaman`, sehingga pengaturan admin
 * tidak pernah tertimpa. Beberapa penulisan slug dikenali agar tetap bekerja bila alamat menunya
 * kelak disingkat.
 */
function db_menu_kurikulum(PDO $pdo): void
{
    // slug utama, label, jenis, judul halaman, anak judul, daftar slug yang dikenali
    $daftar = [
        ['kaldik-operasional', 'Kaldik Operasional', 'kaldik', 'Kalender Pendidikan',
         'Kalender Pendidikan Operasional madrasah',
         ['kaldik-operasional', 'kaldik', 'kaldik_operasional', 'kalender-pendidikan']],
        ['jadwal-mengajar', 'Jadwal Mengajar', 'jadwal_mengajar', 'Jadwal Mengajar',
         'Jadwal pelajaran per hari dan per kelas',
         ['jadwal-mengajar', 'jadwal', 'jadwal_mengajar', 'jadwal-pelajaran']],
        ['dokumen-kurikulum', 'Dokumen Kurikulum', 'dokumen_kurikulum', 'Dokumen Kurikulum',
         'Dokumen kurikulum yang dapat diunduh',
         ['dokumen-kurikulum', 'dokumen_kurikulum', 'dokumen']],
    ];

    if (!db_penanda('kurikulum_menu_v1')) {
        // Induk menu: menu induk yang memuat halaman Perangkat Guru (jenis `perangkat`), lalu menu
        // "Kurikulum" itu sendiri. Bila menu Kurikulum ternyata SUB MENU (instalasi baru: Kurikulum
        // berada di bawah "Akademik"), ketiga menu ini dibuat sebagai SAUDARA Kurikulum supaya tidak
        // menjadi menu tingkat ketiga yang tidak dirender navigasi.
        $induk = 0;
        $st = $pdo->query("SELECT induk_id FROM menu WHERE jenis = 'perangkat' AND induk_id > 0
                           ORDER BY urutan, id LIMIT 1");
        $induk = (int) $st->fetchColumn();
        if ($induk <= 0) {
            $st = $pdo->prepare('SELECT id, induk_id FROM menu WHERE slug = ? LIMIT 1');
            $st->execute(['kurikulum']);
            $kur = $st->fetch(PDO::FETCH_ASSOC);
            if ($kur) {
                $induk = (int) $kur['induk_id'] > 0 ? (int) $kur['induk_id'] : (int) $kur['id'];
            }
        }
        if ($induk <= 0) {
            $st = $pdo->query("SELECT induk_id FROM menu WHERE lower(label) = 'kurikulum' LIMIT 1");
            $induk = (int) $st->fetchColumn();
        }

        foreach ($daftar as [$slugUtama, $label, $jenis, $judul, $subjudul, $slugDikenal]) {
            $ada = 0;
            foreach ($slugDikenal as $slug) {
                $s = $pdo->prepare('SELECT COUNT(*) FROM menu WHERE slug = ?');
                $s->execute([$slug]);
                if ((int) $s->fetchColumn() > 0) {
                    $ada = 1;
                    break;
                }
            }
            if ($ada === 1) {
                continue;   // sudah dibuat admin → jangan digandakan
            }
            $s3 = $pdo->prepare('SELECT COALESCE(MAX(urutan), 0) + 10 FROM menu WHERE induk_id = ?');
            $s3->execute([$induk]);
            $urut = (int) $s3->fetchColumn();
            $pdo->prepare('INSERT INTO menu (induk_id, label, slug, jenis, tautan, judul, subjudul, isi, ikon,
                            urutan, tampil_nav, sorot, aktif)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 1)')
                ->execute([$induk, $label, $slugUtama, $jenis, '', $judul, $subjudul, '', '', $urut]);
        }
        $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)')
            ->execute(['kurikulum_menu_v1', date('Y-m-d H:i:s')]);
    }

    // Sesuaikan jenis menunya (idempoten; hanya baris yang masih berjenis `halaman`).
    foreach ($daftar as [$slugUtama, $label, $jenis, $judul, $subjudul, $slugDikenal]) {
        $st = $pdo->prepare("UPDATE menu SET jenis = ? WHERE slug = ? AND jenis = 'halaman'");
        foreach ($slugDikenal as $slug) {
            $st->execute([$jenis, $slug]);
        }
    }
}

/**
 * v27 — PINDAHKAN BERKAS LAMA KE TABEL PER KELAS (`perangkat_kelas`).
 *
 * Sebelum v27 berkas CP/ATP/RPP disimpan pada baris (guru × mata pelajaran) sehingga SEMUA kelas
 * memakai berkas yang sama. Sejak v27 berkas disimpan per kelas. Migrasi ini menyalin berkas lama ke
 * tiap kelas mata pelajaran itu supaya **tidak ada berkas yang hilang** dari halaman publik; kelas
 * yang belum diatur pada baris lama → satu baris dengan kelas kosong ('').
 *
 * Idempoten: hanya dikerjakan untuk baris `perangkat` yang belum punya satu pun baris
 * `perangkat_kelas` — jadi mengubah/menghapus berkas sesudahnya tidak pernah ditimpa ulang.
 * Kolom berkas pada `perangkat` SENGAJA dibiarkan (tidak dihapus) sebagai arsip.
 *
 * ⚠️ Fungsi ini berjalan di dalam `inc/db.php` yang bisa dijalankan tanpa modul lain, jadi pemecah
 * daftar kelas ditulis lokal (jangan memanggil `perangkat_kelas_pisah()` dari inc/perangkat.php).
 */
function db_perangkat_pindah_kelas(PDO $pdo): void
{
    $adaTabel = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'perangkat_kelas'")->fetchColumn();
    if (!$adaTabel) {
        return;
    }
    $kini = date('Y-m-d H:i:s');
    $baris = $pdo->query("SELECT * FROM perangkat WHERE IFNULL(cp,'') <> '' OR IFNULL(atp,'') <> '' OR IFNULL(rpp,'') <> ''")->fetchAll(PDO::FETCH_ASSOC);
    $cek = $pdo->prepare('SELECT COUNT(*) FROM perangkat_kelas WHERE perangkat_id = ?');
    // v28 — periode bawaan ikut diisi bila kolomnya sudah ada (kolomnya ditambahkan oleh
    // db_perangkat_semester(); urutan pemanggilan bisa berbeda pada basis data yang bermasalah).
    $adaPeriode = false;
    foreach ($pdo->query('PRAGMA table_info(perangkat_kelas)') as $c) {
        if (strtolower((string) $c['name']) === 'ta') {
            $adaPeriode = true;
        }
    }
    [$taBawaan, $semBawaan] = $adaPeriode ? db_periode_bawaan($pdo) : ['', ''];
    $masuk = $adaPeriode
        ? $pdo->prepare('INSERT OR IGNORE INTO perangkat_kelas
            (perangkat_id, guru_id, kunci, kelas, ta, semester, urutan, cp, cp_nama, atp, atp_nama, rpp, rpp_nama, dibuat, diubah)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        : $pdo->prepare('INSERT OR IGNORE INTO perangkat_kelas
            (perangkat_id, guru_id, kunci, kelas, urutan, cp, cp_nama, atp, atp_nama, rpp, rpp_nama, dibuat, diubah)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $n = 0;
    foreach ($baris as $r) {
        $cek->execute([(int) $r['id']]);
        if ((int) $cek->fetchColumn() > 0) {
            continue;   // sudah pernah dipindahkan (atau berkasnya sudah diatur per kelas)
        }
        $kelas = [];
        foreach (preg_split('/[,;|]+/u', (string) ($r['kelas'] ?? '')) ?: [] as $k) {
            $k = trim(preg_replace('/\s+/u', ' ', (string) $k) ?? (string) $k);
            if ($k !== '' && !in_array($k, $kelas, true)) {
                $kelas[] = $k;
            }
        }
        if (!$kelas) {
            $kelas = [''];   // mata pelajaran tanpa kelas
        }
        $i = 0;
        foreach ($kelas as $nama) {
            $umum = [(int) $r['id'], (int) $r['guru_id'], (string) $r['kunci'], $nama];
            if ($adaPeriode) {
                $umum[] = $taBawaan;
                $umum[] = $semBawaan;
            }
            $umum[] = $i * 10;
            $masuk->execute(array_merge($umum, [
                (string) ($r['cp'] ?? ''), (string) ($r['cp_nama'] ?? ''),
                (string) ($r['atp'] ?? ''), (string) ($r['atp_nama'] ?? ''),
                (string) ($r['rpp'] ?? ''), (string) ($r['rpp_nama'] ?? ''), $kini, $kini]));
            $n++;
            $i++;
        }
    }
    // Bersihkan baris "tanpa kelas" yang KOSONG pada mata pelajaran yang SUDAH punya kelas —
    // sisa percobaan penyimpanan pada versi pertama fitur ini (tidak berisi apa pun, jadi aman).
    $pdo->exec("DELETE FROM perangkat_kelas
                WHERE kelas = '' AND LENGTH(IFNULL(cp,'')) = 0 AND LENGTH(IFNULL(atp,'')) = 0
                  AND LENGTH(IFNULL(rpp,'')) = 0
                  AND perangkat_id IN (SELECT id FROM perangkat WHERE TRIM(IFNULL(kelas,'')) <> '')");
    if ($n > 0) {
        error_log('v27 perangkat_kelas: ' . $n . ' baris berkas per kelas dibuat dari data lama.');
    }
}

/**
 * v24 — MENU "EVALUASI DIRI MADRASAH" memakai jenis halaman BARU `edm`.
 *
 * Permintaan pemilik: isi halaman itu disamakan dengan tampilan **Indikator EDM** aplikasi EDM
 * (kartu Indikator EDM + tab Charts/Tabel), hanya TANPA "Cetak Rekap" dan "Ringkasan Pengisian Tiap
 * Aspek" — lihat `inc/edm.php`. Migrasi ini hanya mengubah baris menu yang MASIH berjenis `halaman`
 * (pengaturan admin tidak pernah tertimpa).
 */
function db_menu_edm(PDO $pdo): void
{
    $st = $pdo->prepare("UPDATE menu SET jenis = 'edm' WHERE slug = ? AND jenis = 'halaman'");
    foreach (['evaluasi-diri-madrasah', 'edm', 'evaluasi-diri'] as $slug) {
        $st->execute([$slug]);
    }
}

/**
 * v23 — MENU "PENDAFTAR" (sub menu Siswa) memakai jenis halaman BARU `pendaftar`.
 *
 * Permintaan pemilik: isi yang dahulu menjadi TAB di halaman **Data Siswa** dipindahkan ke menu
 * **Pendaftar** (sub menu "Siswa") yang sebelumnya masih halaman kosong. Migrasi ini hanya mengubah
 * baris menu itu bila jenisnya masih `halaman` (pengaturan admin tidak pernah tertimpa).
 */
function db_menu_pendaftar(PDO $pdo): void
{
    $st = $pdo->prepare("UPDATE menu SET jenis = 'pendaftar' WHERE slug = ? AND jenis = 'halaman'");
    foreach (['pendaftar', 'data-pendaftar', 'pendaftar-ppdb'] as $slug) {
        $st->execute([$slug]);
    }
}

/**
 * v21 — MENU "INFORMASI E-VOTING OSIM" memakai jenis halaman BARU `voting`.
 *
 * Sub menu "e-Voting OSIM" (di bawah menu "Informasi") dibuat admin hanya berjenis `halaman`
 * (isinya kosong). Sejak v21 jenisnya diubah menjadi `voting` supaya halaman itu menampilkan
 * **Papan Hasil Real-Count** aplikasi e-Voting OSIM (angkanya berjalan sendiri setiap 5 detik)
 * yang datanya dibaca langsung dari aplikasi e-Voting — lihat inc/voting.php.
 *
 * Catatan: hanya baris yang MASIH berjenis `halaman` yang diubah, sehingga pengaturan admin tidak
 * pernah tertimpa (bila admin memilih jenis lain, migrasi berikutnya tidak mengubahnya).
 */
function db_menu_voting(PDO $pdo): void
{
    $st = $pdo->prepare("UPDATE menu SET jenis = 'voting' WHERE slug = ? AND jenis = 'halaman'");
    foreach (['e-voting-osim', 'e-voting-osis', 'voting-osim', 'voting'] as $slug) {
        $st->execute([$slug]);
    }
}

/**
 * v20 — MENU "INFORMASI SUPERVISI AKADEMIK" memakai jenis halaman BARU `supervisi`.
 *
 * Sub menu "Supervisi Akademik" (di bawah menu "Informasi") dibuat admin hanya berjenis `halaman`
 * (isinya kosong). Sejak v20 jenisnya diubah menjadi `supervisi` supaya halaman itu menampilkan
 * **dashboard aplikasi Supervisi Akademik** (hasil supervisi, capaian tiap menu supervisi,
 * peringkat guru) yang datanya dibaca langsung dari aplikasi Supervisi Guru — lihat
 * inc/supervisi.php.
 *
 * Catatan: hanya baris yang MASIH berjenis `halaman` yang diubah, sehingga pengaturan admin tidak
 * pernah tertimpa (bila admin memilih jenis lain, migrasi berikutnya tidak mengubahnya).
 */
function db_menu_supervisi(PDO $pdo): void
{
    $st = $pdo->prepare("UPDATE menu SET jenis = 'supervisi' WHERE slug = ? AND jenis = 'halaman'");
    foreach (['supervis-akademik', 'supervisi-akademik', 'supervisi'] as $slug) {
        $st->execute([$slug]);
    }
}

/**
 * v19 — MENU "INFORMASI PERPUSTAKAAN" memakai jenis halaman BARU `perpustakaan`.
 *
 * Sub menu "Perpustakaan" (di bawah menu "Informasi"/"Fasilitas") dibuat admin hanya berjenis
 * `halaman` (isinya teks kosong/penjelasan singkat). Sejak v19 jenisnya diubah menjadi
 * `perpustakaan` supaya halaman itu menampilkan **empat Laporan Perpustakaan** (Data Buku,
 * Pengunjung, Peminjaman, Pengembalian) yang datanya dibaca langsung dari aplikasi e-PERPUS —
 * lihat inc/perpustakaan.php.
 *
 * Catatan: hanya baris yang MASIH berjenis `halaman` yang diubah, sehingga pengaturan admin tidak
 * pernah tertimpa (bila admin memilih jenis lain, migrasi berikutnya tidak mengubahnya).
 */
function db_menu_perpustakaan(PDO $pdo): void
{
    $st = $pdo->prepare("UPDATE menu SET jenis = 'perpustakaan' WHERE slug = ? AND jenis = 'halaman'");
    foreach (['perpustakaan', 'perpus'] as $slug) {
        $st->execute([$slug]);
    }
}

/**
 * v17 — MENU "LAYANAN PIP" (jenis halaman `pip`) + teks bawaan bagian informasinya.
 *
 * Berbeda dengan v15/v16 (yang hanya MENGUBAH jenis menu yang sudah dibuat admin), di sini menu
 * PIP **dibuat** karena memang diminta pemilik ("Buatkan Menu PIP") dan belum ada. Menu diletakkan
 * sebagai sub menu "Informasi" bila menu itu ada (seperti pada basis data pemilik), di bawah
 * "Kesiswaan" bila tidak ada, dan sebagai menu utama bila keduanya tidak ada.
 *
 * ⚠️ Pembuatan menu dijaga penanda `pip_menu_v1` supaya tidak pernah dibuat dua kali dan supaya
 * perubahan nama/urutan/penghapusan oleh admin TIDAK dibatalkan migrasi berikutnya.
 */
function db_menu_pip(PDO $pdo): void
{
    if (!db_penanda('pip_menu_v1')) {
        $adaMenu = (int) $pdo->query("SELECT COUNT(*) FROM menu WHERE slug = 'pip'")->fetchColumn() > 0;
        if (!$adaMenu) {
            $induk = 0;
            foreach (['informasi', 'kesiswaan'] as $slug) {
                $st = $pdo->prepare('SELECT id FROM menu WHERE slug = ? LIMIT 1');
                $st->execute([$slug]);
                $id = (int) $st->fetchColumn();
                if ($id > 0) {
                    $induk = $id;
                    break;
                }
            }
            $st = $pdo->prepare('SELECT COALESCE(MAX(urutan), 0) + 10 FROM menu WHERE induk_id = ?');
            $st->execute([$induk]);
            $urut = (int) $st->fetchColumn();
            $pdo->prepare('INSERT INTO menu (induk_id, label, slug, jenis, tautan, judul, subjudul, isi, ikon,
                            urutan, tampil_nav, sorot, aktif)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 1)')
                ->execute([$induk, 'Layanan PIP', 'pip', 'pip', '', 'Layanan PIP',
                    'Informasi Program Indonesia Pintar (PIP)', '', '', $urut]);
        }
        // Penanda ditulis SESUDAH menu dibuat supaya kegagalan di tengah tidak meninggalkan
        // keadaan "penanda ada tetapi menu tidak ada".
        $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)')
            ->execute(['pip_menu_v1', date('Y-m-d H:i:s')]);
    }

    // Bila menunya sudah ada tetapi masih berjenis halaman biasa → disesuaikan (idempoten).
    $pdo->prepare("UPDATE menu SET jenis = 'pip' WHERE slug = ? AND jenis = 'halaman'")->execute(['pip']);

    // Teks bawaan "Informasi Seputar Program Indonesia Pintar" — diisi SEKALI, sesudah itu
    // suntingan admin tidak pernah ditimpa (menghapus isinya juga tetap kosong).
    if (!db_penanda('pip_info_seed')) {
        if (trim((string) $pdo->query("SELECT nilai FROM pengaturan WHERE kunci = 'pip_info'")->fetchColumn()) === '') {
            $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)
                           ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai')
                ->execute(['pip_info', PIP_INFO_BAWAAN]);
        }
        $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)')
            ->execute(['pip_info_seed', date('Y-m-d H:i:s')]);
    }
}

/** Satu nilai dari query (penolong LOKAL migrasi; tidak memakai koneksi halaman). */
function db_migrasi_nilai(PDO $pdo, string $sql, array $param = [], $bawaan = null)
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($param);
        $v = $st->fetchColumn();
        return $v === false ? $bawaan : $v;
    } catch (Throwable $e) {
        return $bawaan;
    }
}

/**
 * v18 — TAHUN pada LAYANAN PIP.
 *
 * Permintaan pemilik: *"tambahkan tahun (karena setiap tahun SK dan penerima PIP tidak sama)"*.
 * Karena itu **SK Penerima PIP** dan **daftar penerima** kini menempel pada sebuah TAHUN, dan
 * halaman publik memakai pemilih tahun.
 *
 * Migrasi ini **idempoten**: baris tahun pertama hanya dibuat bila tabelnya masih kosong, dan
 * pemindahan `tahun_id` penerima hanya menyentuh baris yang bernilai 0/NULL. Seluruh isi yang sudah
 * ada (SK, catatan, daftar penerima) DIPINDAHKAN ke tahun itu — tidak ada data yang hilang.
 */
function db_pip_tahun(PDO $pdo): void
{
    db_add_column($pdo, 'pip_penerima', 'tahun_id', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column($pdo, 'pip_tahun', 'catatan', "TEXT NOT NULL DEFAULT ''");

    $jumlah = (int) $pdo->query('SELECT COUNT(*) FROM pip_tahun')->fetchColumn();
    if ($jumlah === 0) {
        // Tahun pertama diambil dari data yang sudah ada supaya halaman tidak mendadak kosong.
        $sk = (string) db_migrasi_nilai($pdo, "SELECT nilai FROM pengaturan WHERE kunci = 'pip_sk'", [], '');
        $skNama = (string) db_migrasi_nilai($pdo, "SELECT nilai FROM pengaturan WHERE kunci = 'pip_sk_nama'", [], '');
        $catatan = (string) db_migrasi_nilai($pdo, "SELECT nilai FROM pengaturan WHERE kunci = 'pip_catatan'", [], '');
        $adaIsi = ($sk !== '' || $catatan !== '' || (int) $pdo->query('SELECT COUNT(*) FROM pip_penerima')->fetchColumn() > 0);
        $pdo->prepare('INSERT INTO pip_tahun (tahun, sk, sk_nama, catatan, urutan, aktif, dibuat, diubah)
                       VALUES (?, ?, ?, ?, 10, 1, ?, ?)')
            ->execute([date('Y'), $sk, $skNama, $catatan, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $idBaru = (int) $pdo->lastInsertId();
        // Seluruh penerima yang belum punya tahun dipindahkan ke tahun pertama itu.
        $pdo->prepare('UPDATE pip_penerima SET tahun_id = ? WHERE tahun_id IS NULL OR tahun_id = 0')
            ->execute([$idBaru]);
        // Simpan sebagai tahun aktif hanya bila sudah ada isinya (instalasi baru tidak mengunci apa pun).
        if ($adaIsi) {
            $pdo->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES ('pip_tahun_aktif', ?)
                           ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai")
                ->execute([(string) $idBaru]);
        }
    }

    // Tahun aktif harus selalu menunjuk baris tahun yang ada (kalau tidak, diambil yang pertama).
    $aktif = (int) db_migrasi_nilai($pdo, "SELECT nilai FROM pengaturan WHERE kunci = 'pip_tahun_aktif'", [], 0);
    if ($aktif <= 0 || db_migrasi_nilai($pdo, 'SELECT id FROM pip_tahun WHERE id = ?', [$aktif]) === null) {
        $pertama = (int) db_migrasi_nilai($pdo, 'SELECT id FROM pip_tahun ORDER BY urutan, id LIMIT 1', [], 0);
        if ($pertama > 0) {
            $pdo->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES ('pip_tahun_aktif', ?)
                           ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai")
                ->execute([(string) $pertama]);
        }
    }
}

// =====================================================================================
// ISI AWAL (dijalankan sekali)
// =====================================================================================
function db_penanda(string $kunci): bool
{
    return jumlah('SELECT COUNT(*) FROM pengaturan WHERE kunci = ?', [$kunci]) > 0;
}

function db_isi_awal(PDO $pdo): void
{
    // --- identitas sekolah (hanya bila tabel masih kosong) -------------------------------
    if ((int) $pdo->query('SELECT COUNT(*) FROM sekolah')->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO sekolah
            (id, nama, jenjang, npsn, nsm, alamat, desa, kecamatan, kabupaten, provinsi, telepon, whatsapp,
             email, website, kepala, nip_kepala, slogan, jenis, akreditasi, tahun_ajaran, jam_operasional, diperbarui)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now','localtime'))")
            ->execute([
                'MA. Sirojuth Tholibiin Mootilango', 'MA', '70027902', '131275010019',
                'Jl. Dusun Gandaria', 'Sidomukti', 'Mootilango', 'Gorontalo', 'Gorontalo',
                '(0435) 123456', '085394726089', 'ma.sirojuththolibiinmootilngo@gmail.com', 'www.ma.stm.sch.id',
                'Supianto, S.Pd.I', '-', 'Berakhlak, Berilmu dan Berprestasi', 'madrasah',
                'A', date('Y') . '/' . (date('Y') + 1), 'Senin - Sabtu, 07.00 - 14.00 WITA',
            ]);
    }

    // --- akun admin ----------------------------------------------------------------------
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO users (nama, username, sandi, role, email, aktif, dibuat) VALUES (?, ?, ?, ?, ?, 1, datetime(\'now\',\'localtime\'))')
            ->execute(['Administrator', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin', '']);
    }

    // --- pengaturan umum ------------------------------------------------------------------
    $set = [
        'ppdb_buka'          => '1',
        'ppdb_umumkan'       => '0',
        'ppdb_isi'           => "Penerimaan Peserta Didik Baru (PPDB) tahun ini dibuka untuk seluruh calon peserta didik baru.\nPendaftaran dilakukan secara online melalui formulir di halaman ini, tanpa dipungut biaya pendaftaran.",
        'ppdb_syarat'        => "Fotokopi Ijazah/SKL (dilegalisasi)\nFotokopi Akta Kelahiran\nFotokopi Kartu Keluarga\nPas foto berwarna 3x4 sebanyak 2 lembar\nFotokopi rapor semester terakhir",
        'ppdb_alur'          => "Isi formulir pendaftaran online\nUnggah berkas persyaratan\nPanitia memverifikasi data & berkas\nSeleksi administrasi dan nilai\nPengumuman hasil seleksi\nDaftar ulang bagi yang diterima",
        'ppdb_biaya'         => 'Gratis (tidak dipungut biaya)',
        'ppdb_kontak'        => 'Panitia PPDB — 0853 9472 6089',
        'ppdb_jadwal'        => "Pendaftaran gelombang 1\nVerifikasi berkas\nPengumuman hasil seleksi\nDaftar ulang",
        'ppdb_pengumuman_tgl' => '',
        'alumni_manual'      => '0',
        'beranda_judul_program'   => 'Program Digitalisasi Madrasah',
        'beranda_judul_berita'    => 'Berita Terbaru',
        'beranda_judul_agenda'    => 'Agenda Madrasah',
        'beranda_judul_testimoni' => 'Apa kata mereka',
        'beranda_program_teks'    => 'Berbagai aplikasi digital yang kami gunakan untuk mempercepat dan merapikan administrasi madrasah — dari administrasi guru, supervisi akademik, hingga asesmen siswa.',
        'beranda_berita_teks'     => 'Kabar terbaru seputar kegiatan dan prestasi madrasah.',
        'beranda_agenda_teks'     => 'Kegiatan yang akan datang — mari ikut berpartisipasi.',
        'beranda_testimoni_teks'  => 'Kesan dan pesan dari peserta didik, orang tua, dan alumni.',
        'beranda_galeri_judul'    => 'Galeri & Media Sosial',
        'beranda_galeri_teks'     => 'Jelajahi dokumentasi kegiatan madrasah dan ikuti kanal media sosial kami.',
        'footer_teks'        => 'Website resmi madrasah sebagai media informasi dan komunikasi bagi peserta didik, orang tua, dan masyarakat.',
        'sambutan_singkat'   => 'Assalamu\'alaikum warahmatullahi wabarakatuh. Selamat datang di website resmi kami — media informasi dan komunikasi madrasah.',
    ];
    $st = $pdo->prepare('INSERT OR IGNORE INTO pengaturan (kunci, nilai) VALUES (?, ?)');
    foreach ($set as $k => $v) {
        $st->execute([$k, $v]);
    }

    // --- menu & isi halaman (sekali saja) --------------------------------------------------
    if (!db_penanda('isi_awal_v1')) {
        db_menu_awal($pdo);
        db_pdo_program($pdo);
        db_pdo_berita($pdo);
        db_pdo_agenda($pdo);
        db_pdo_galeri($pdo);
        db_pdo_fasilitas($pdo);
        db_pdo_testimoni($pdo);
        db_pdo_sosial($pdo);
        db_pdo_guru($pdo);
        db_pdo_siswa($pdo);
        db_pdo_ppdb($pdo);
        $st->execute(['isi_awal_v1', '1']);
    }

    // Program Digitalisasi Madrasah (skema v7) — menggantikan isi bawaan "Program Unggulan".
    if (!db_penanda('program_digital_v1')) {
        db_program_digital($pdo);
        $st->execute(['program_digital_v1', '1']);
    }

    // Sub menu Prestasi Siswa / Prestasi Madrasah / Prestasi Pendidik / Pelatihan Pendidik (skema v5).
    if (!db_penanda('menu_prestasi_v1')) {
        db_menu_prestasi($pdo);
        $st->execute(['menu_prestasi_v1', '1']);
    }

    // Sub menu Kesiswaan: Data Siswa & Data Alumni (skema v4).
    // Penanda v2 dipakai agar fungsi ini berjalan SEKALI LAGI pada basis data yang sudah terisi
    // (pemilik sudah membuat menu "Data Siswa"/"Data Alumni" sendiri sebagai halaman biasa —
    //  menunya harus ditingkatkan ke jenis siswa/alumni, bukan ditambahkan ulang).
    if (!db_penanda('menu_kesiswaan_v2')) {
        db_menu_kesiswaan($pdo);
        $st->execute(['menu_kesiswaan_v2', '1']);
    }

    // Polling pendapat awal (skema v3) — penanda sendiri supaya juga dijalankan pada basis data
    // yang sudah terisi (isi awal di atas tidak akan berjalan lagi karena penandanya sudah ada).
    if (!db_penanda('poll_awal_v1')) {
        db_pdo_poll($pdo);
        $st->execute(['poll_awal_v1', '1']);
    }

    // Baris teks PPDB yang MELEBUR (skema v8) — lihat db_perbaiki_baris_ppdb().
    if (!db_penanda('ppdb_baris_v1')) {
        db_perbaiki_baris_ppdb($pdo);
        $st->execute(['ppdb_baris_v1', '1']);
    }

    // PERSYARATAN PPDB yang masih ditulis dalam SATU baris dipisah menjadi satu per baris
    // (skema v9) supaya tampil bernomor 1, 2, 3, … — lihat db_ppdb_syarat_bernomor().
    if (!db_penanda('ppdb_syarat_nomor_v1')) {
        db_ppdb_syarat_bernomor($pdo);
        $st->execute(['ppdb_syarat_nomor_v1', '1']);
    }

    // PROFIL LENGKAP + BAGAN STRUKTUR ORGANISASI (skema v10) — lihat db_profil_struktur().
    if (!db_penanda('profil_struktur_v1')) {
        db_profil_struktur($pdo);
        $st->execute(['profil_struktur_v1', '1']);
    }

    // PETA TETAP bagan struktur organisasi (skema v12) — lihat db_struktur_peta().
    if (!db_penanda('struktur_peta_v1')) {
        db_struktur_peta($pdo);
        $st->execute(['struktur_peta_v1', '1']);
    }
}

/**
 * v12 — PETA TETAP bagan struktur organisasi.
 *
 * Letak, ukuran, dan garis penghubung bagan ditentukan KODE (lihat struktur_peta() pada
 * inc/profil.php) sehingga admin hanya mengisi NAMA + PAS FOTO. Fungsi ini:
 *   1. memasang `kode` pada baris lama dengan mencocokkan NAMANYA (mis. "Kepala Madrasah");
 *   2. menambah baris untuk slot yang belum ada (nama = label bawaan slot);
 *   3. TIDAK menghapus baris apa pun — baris yang tidak punya slot dibiarkan (tidak dirender).
 */
function db_struktur_peta(PDO $pdo): void
{
    require_once __DIR__ . '/profil.php';
    $peta = struktur_peta();
    $kunci = static fn (string $t): string => trim((string) preg_replace('#[^a-z0-9]+#', ' ', strtolower($t)));

    $baris = $pdo->query('SELECT id, nama, kode FROM struktur ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $pakai = [];   // id baris yang sudah dipakai slot
    $pakaiKode = [];
    foreach ($baris as $b) {
        if (trim((string) $b['kode']) !== '') {
            $pakaiKode[(string) $b['kode']] = (int) $b['id'];
        }
    }
    $pasangKode = $pdo->prepare('UPDATE struktur SET kode = ? WHERE id = ?');
    // Kursip: nilai kolom `foto` dikosongkan (string kosong) — memakai kutip tunggal di dalam
    // string PHP berkutip tunggal akan memutus string, jadi SQL ini ditulis dengan kutip ganda.
    $tambah = $pdo->prepare("INSERT INTO struktur (nama, induk_id, urutan, garis, aksen, aktif, kode, foto, dibuat)
                             VALUES (?, 0, ?, ?, ?, 1, ?, '', ?)");
    $waktu = date('Y-m-d H:i:s');
    $urut = 0;
    foreach ($peta['slot'] as $slot) {
        $urut += 10;
        $kode = (string) $slot['kode'];
        if (isset($pakaiKode[$kode])) {
            $pakai[$pakaiKode[$kode]] = true;
            continue;   // slot sudah punya baris
        }
        // cari baris LAMA yang namanya cocok dengan label slot (belum dipakai slot lain)
        $cocok = 0;
        foreach ($baris as $b) {
            $id = (int) $b['id'];
            if (isset($pakai[$id]) || trim((string) $b['kode']) !== '') {
                continue;
            }
            if ($kunci((string) $b['nama']) === $kunci((string) $slot['label'])) {
                $cocok = $id;
                break;
            }
        }
        if ($cocok > 0) {
            $pasangKode->execute([$kode, $cocok]);
            $pakai[$cocok] = true;
        } else {
            $tambah->execute([(string) $slot['label'], $urut, 'penuh', $slot['aksen'] ? 1 : 0, $kode, $waktu]);
        }
    }
}

/**
 * v10 — SIAPKAN HALAMAN "PROFIL LENGKAP" & "STRUKTUR ORGANISASI" (dijalankan SEKALI).
 *
 *  1. Menaikkan JENIS dua menu yang sudah ada (dibuat pemilik sendiri, isinya masih kosong)
 *     menjadi `profil_lengkap` dan `struktur_organisasi` supaya dirender sebagai halaman isian.
 *  2. Mengisi CONTOH isian identitas/visi/misi/tujuan HANYA bila kolomnya masih kosong — sehingga
 *     apa pun yang sudah diisi/diubah pemilik TIDAK pernah ditimpa.
 *  3. Menanam contoh kotak bagan struktur organisasi HANYA bila tabel `struktur` masih kosong.
 *
 * Isi contoh diambil dari dokumen "PROFIL MA. SIROJUTH THOLIBIIN MOOTILANGO" & bagan
 * "STRUKTUR ORGANISASI" yang dikirim pemilik; semuanya dapat diubah/dihapus dari panel admin.
 */
function db_profil_struktur(PDO $pdo): void
{
    // (1) jenis menu
    $pasang = [
        ['visi-misi', 'profil_lengkap'],
        ['struktur', 'struktur_organisasi'],
    ];
    $cari = $pdo->prepare("SELECT id FROM menu WHERE slug = ? AND jenis = 'halaman'");
    $ubah = $pdo->prepare('UPDATE menu SET jenis = ? WHERE id = ?');
    foreach ($pasang as [$slug, $jenis]) {
        $cari->execute([$slug]);
        foreach ($cari->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ubah->execute([$jenis, (int) $id]);
        }
    }

    // (2) isian identitas/visi/misi/tujuan — hanya bila masih KOSONG
    $contoh = [
        'kode_pos'        => '96157',
        'npwp'            => '65.013.344.0-822.000',
        'status_madrasah' => 'Swasta',
        'waktu_belajar'   => 'Pagi',
        'sk_ijop'         => '88',
        'sk_ijop_tgl'     => '21 Maret 2022',
        'tahun_berdiri'   => '10 Juli 2020',
        'akreditasi_tgl'  => '',
        'penyelenggara'   => 'Yayasan Sirojuth Tholibiin Sidomukti',
        'sk_menkumham'    => 'AHU-0000678.AH.01.04.TAHUN 2017',
        'jarak_kecamatan' => '5 KM',
        'jarak_kabupaten' => '50 KM',
        'bangunan'        => 'Milik Sendiri',
        'visi'            => 'TERWUJUDNYA PESERTA DIDIK YANG BERAKHLAK, CERDAS, TERAMPIL, MANDIRI DAN BERWAWASAN GLOBAL',
        'misi'            => "Menanamkan keimanan dan ketakwaan melalui pengamalan ajaran agama.
"
            . "Mengoptimalkan proses belajar dan bimbingan.
"
            . "Mengembangkan bidang ilmu pengetahuan dan teknologi berdasarkan minat, bakat dan potensi peserta didik.
"
            . "Membina kemandirian peserta didik melalui kegiatan pembiasaan, kewirausahaan dan pengembangan diri yang terencana dan bersinambungan.
"
            . "Menjalin kerjasama yang harmonis antar warga madrasah dan lembaga yang terkait.",
        'tujuan'          => "Mengembangkan budaya madrasah yang religius melalui pengamalan ajaran agama.
"
            . "Melaksanakan pendekatan pembelajaran aktif pada semua mata pelajaran.
"
            . "Mengembangkan berbagai kegiatan dalam proses belajar di kelas berbasis Pendidikan karakter bangsa.
"
            . "Menjalin Kerjasama dengan lembaga lain dalam merealisasikan program madrasah.
"
            . "Memanfaatkan dan memelihara fasilitas pendukung proses pembelajaran berbasis TIK.",
    ];
    $ambil = $pdo->prepare('SELECT * FROM sekolah WHERE id = 1');   // seluruh kolom (bukan `nilai`)
    $ambil->execute();
    $baris = $ambil->fetch(PDO::FETCH_ASSOC) ?: [];
    $set = [];
    $val = [];
    foreach ($contoh as $k => $v) {
        if (trim((string) ($baris[$k] ?? '')) === '') {
            $set[] = $k . ' = ?';
            $val[] = $v;
        }
    }
    if ($set) {
        $val[] = 1;
        $pdo->prepare('UPDATE sekolah SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($val);
    }

    // (3) contoh kotak bagan struktur organisasi — hanya bila tabelnya masih kosong.
    $ada = (int) $pdo->query('SELECT COUNT(*) FROM struktur')->fetchColumn();
    if ($ada > 0) {
        return;
    }
    $waktu = date('Y-m-d H:i:s');
    $tambah = $pdo->prepare('INSERT INTO struktur (nama, induk_id, urutan, garis, aksen, aktif, dibuat) VALUES (?, ?, ?, ?, ?, 1, ?)');
    $idDari = [];
    $simpan = static function (string $nama, string $indukNama, int $urutan, string $garis, int $aksen) use ($tambah, $waktu, &$idDari, $pdo): int {
        $induk = $indukNama !== '' ? (int) ($idDari[$indukNama] ?? 0) : 0;
        $tambah->execute([$nama, $induk, $urutan, $garis, $aksen, $waktu]);
        $id = (int) $pdo->lastInsertId();
        $idDari[$nama] = $id;
        return $id;
    };
    $simpan('Kepala Madrasah', '', 10, 'penuh', 1);
    $simpan('Komite', 'Kepala Madrasah', 10, 'putus', 1);
    $simpan('KTU', 'Kepala Madrasah', 20, 'penuh', 1);
    $simpan('Tata Usaha', 'KTU', 10, 'penuh', 0);
    $simpan('Bendahara', 'KTU', 20, 'penuh', 0);
    $simpan('Kepala Lab', 'Kepala Madrasah', 30, 'penuh', 0);
    $simpan('Wakamad Kurikulum', 'Kepala Madrasah', 40, 'penuh', 0);
    $simpan('Wakamad Kesiswaan', 'Kepala Madrasah', 50, 'penuh', 0);
    $simpan('Wakamad Sarpras', 'Kepala Madrasah', 60, 'penuh', 0);
    $simpan('Wakamad Humas', 'Kepala Madrasah', 70, 'penuh', 0);
    $simpan('Kepala Perpus', 'Kepala Madrasah', 80, 'penuh', 0);
    $simpan('Wali Kelas', 'Kepala Madrasah', 90, 'penuh', 1);
    $simpan('Kelas X MIPA 1', 'Wali Kelas', 10, 'penuh', 0);
    $simpan('Kelas X MIPA 2', 'Wali Kelas', 20, 'penuh', 0);
    $simpan('Kelas XI MIPA 1', 'Wali Kelas', 30, 'penuh', 0);
    $simpan('Kelas XI MIPA 2', 'Wali Kelas', 40, 'penuh', 0);
    $simpan('Kelas XII MIPA 1', 'Wali Kelas', 50, 'penuh', 0);
    $simpan('Kelas XII MIPA 2', 'Wali Kelas', 60, 'penuh', 0);
    $simpan('Dewan Guru', 'Kepala Madrasah', 100, 'penuh', 0);
    $simpan('Guru BK', 'Kepala Madrasah', 110, 'penuh', 1);
    $simpan('Siswa', 'Dewan Guru', 10, 'penuh', 0);
}

/**
 * Pisahkan daftar PERSYARATAN PPDB yang masih berada pada SATU baris menjadi satu persyaratan
 * per baris (skema v9) — supaya halaman PPDB menampilkannya sebagai daftar bernomor 1, 2, 3, …
 *
 * Aturan pengaman (penting — data pemilik):
 *  • hanya dijalankan SEKALI (penanda `ppdb_syarat_nomor_v1`), jadi bila pemilik sengaja
 *    menggabungkannya kembali menjadi satu baris, migrasi berikutnya TIDAK memisahkannya lagi;
 *  • hanya menyentuh nilai yang BELUM punya baris baru (satu baris) dan memuat ≥ 2 koma;
 *  • tidak menyentuh nilai yang punya segmen kosong (mis. "a,, b") karena berarti pemisahnya
 *    bukan daftar;
 *  • spasi di tiap potongan dirapikan; potongan yang lebih dari 160 karakter dianggap satu kalimat
 *    (bukan daftar) sehingga nilai dibiarkan apa adanya.
 */
function db_ppdb_syarat_bernomor(PDO $pdo): void
{
    $ambil = $pdo->prepare('SELECT nilai FROM pengaturan WHERE kunci = ?');
    $tulis = $pdo->prepare('UPDATE pengaturan SET nilai = ? WHERE kunci = ?');
    $ambil->execute(['ppdb_syarat']);
    $nilai = $ambil->fetchColumn();
    if ($nilai === false || $nilai === null) {
        return;
    }
    $nilai = trim((string) $nilai);
    if ($nilai === '' || str_contains($nilai, "\n") || substr_count($nilai, ',') < 2) {
        return;
    }
    $potongan = array_map('trim', explode(',', $nilai));
    foreach ($potongan as $p) {
        // `mb_strlen` tidak selalu tersedia di lingkungan CLI (inc/helpers.php juga membungkusnya).
        $panjang = function_exists('mb_strlen') ? mb_strlen($p, 'UTF-8') : strlen($p);
        if ($p === '' || $panjang > 160) {
            return;   // bukan daftar — biarkan apa adanya
        }
    }
    $tulis->execute([implode("\n", $potongan), 'ppdb_syarat']);
}

/**
 * Perbaiki nilai PPDB yang BARIS-BARISNYA SUDAH MELEBUR menjadi satu baris (skema v8).
 *
 * Penyebabnya: panel `admin/ppdb.php` dahulu menyimpan medan "satu per baris" memakai `potong()`,
 * yang melebur seluruh baris baru menjadi spasi. Akibatnya halaman PPDB hanya menampilkan SATU
 * kartu langkah raksasa ("Isi formulir… Unggah berkas… Panitia memverifikasi…") dan daftar
 * Persyaratan menjadi satu butir panjang.
 *
 * Aman terhadap suntingan pemilik: nilai HANYA diganti bila isinya — setelah seluruh spasi
 * diseragamkan — masih **sama persis** dengan teks bawaan (jadi hanya barisnya yang hilang,
 * bukan kata-katanya). Bila pemilik sudah menyunting kalimatnya, nilainya dibiarkan apa adanya.
 */
function db_perbaiki_baris_ppdb(PDO $pdo): void
{
    $bawaan = [
        'ppdb_isi'    => "Penerimaan Peserta Didik Baru (PPDB) tahun ini dibuka untuk seluruh calon peserta didik baru.\nPendaftaran dilakukan secara online melalui formulir di halaman ini, tanpa dipungut biaya pendaftaran.",
        'ppdb_syarat' => "Fotokopi Ijazah/SKL (dilegalisasi)\nFotokopi Akta Kelahiran\nFotokopi Kartu Keluarga\nPas foto berwarna 3x4 sebanyak 2 lembar\nFotokopi rapor semester terakhir",
        'ppdb_alur'   => "Isi formulir pendaftaran online\nUnggah berkas persyaratan\nPanitia memverifikasi data & berkas\nSeleksi administrasi dan nilai\nPengumuman hasil seleksi\nDaftar ulang bagi yang diterima",
        'ppdb_jadwal' => "Pendaftaran gelombang 1\nVerifikasi berkas\nPengumuman hasil seleksi\nDaftar ulang",
    ];
    $rapat = static fn (string $t): string => trim((string) preg_replace('/\s+/u', ' ', $t));
    $ambil = $pdo->prepare('SELECT nilai FROM pengaturan WHERE kunci = ?');
    $tulis = $pdo->prepare('UPDATE pengaturan SET nilai = ? WHERE kunci = ?');
    foreach ($bawaan as $kunci => $teks) {
        $ambil->execute([$kunci]);
        $nilai = $ambil->fetchColumn();
        if ($nilai === false || $nilai === null) {
            continue;   // kunci belum ada (basis data sangat lama) — jangan dibuat-buat
        }
        $nilai = (string) $nilai;
        if (str_contains($nilai, "\n")) {
            continue;   // barisnya masih utuh
        }
        if ($rapat($nilai) !== $rapat($teks)) {
            continue;   // sudah disunting pemilik — JANGAN ditimpa
        }
        $tulis->execute([$teks, $kunci]);
    }
}

function db_masukkan(PDO $pdo, string $tabel, array $baris): void
{
    if (!$baris) {
        return;
    }
    $kolom = array_keys($baris[0]);
    $sql = 'INSERT INTO ' . $tabel . ' (' . implode(', ', $kolom) . ') VALUES (' . implode(', ', array_fill(0, count($kolom), '?')) . ')';
    $st = $pdo->prepare($sql);
    foreach ($baris as $b) {
        $st->execute(array_values($b));
    }
}

function db_menu_awal(PDO $pdo): void
{
    /** @var array<string,array{induk:array,anak:array}> $pohon */
    $pohon = [
        [
            'label' => 'Beranda', 'slug' => 'beranda', 'jenis' => 'beranda',
            'judul' => 'Beranda', 'subjudul' => '', 'isi' => '',
            'anak'  => [],
        ],
        [
            'label' => 'Profil', 'slug' => 'profil', 'jenis' => 'halaman',
            'judul' => 'Profil ' . 'Madrasah', 'subjudul' => 'Mengenal lebih dekat lembaga pendidikan kami',
            'isi'   => "<p>MA. Sirojuth Tholibiin Mootilango berdiri sebagai lembaga pendidikan Islam yang berkomitmen mencetak generasi Qur'ani, berilmu, berakhlak mulia, dan berprestasi.</p><p>Melalui halaman Profil ini kami menyajikan gambaran umum lembaga: sejarah, visi dan misi, struktur kepengurusan, serta sambutan pimpinan.</p>",
            'anak'  => [
                ['label' => 'Sejarah', 'slug' => 'sejarah', 'jenis' => 'halaman', 'judul' => 'Sejarah Madrasah',
                 'isi' => "<p>Madrasah ini tumbuh dari sebuah pengajian masyarakat yang diselenggarakan di Dusun Gandaria, Desa Sidomukti, Kecamatan Mootilango. Semangat masyarakat yang tinggi terhadap pendidikan agama dan umum mendorong berdirinya lembaga pendidikan formal.</p><h3>Perkembangan</h3><p>Dari tahun ke tahun jumlah peserta didik terus bertambah sehingga madrasah membangun ruang kelas baru, laboratorium, perpustakaan, dan sarana olahraga.</p><h3>Masa Kini</h3><p>Kini madrasah hadir sebagai lembaga pendidikan yang memadukan ilmu agama dan ilmu pengetahuan umum dengan akhlak sebagai fondasinya.</p>"],
                ['label' => 'Visi & Misi', 'slug' => 'visi-misi', 'jenis' => 'halaman', 'judul' => 'Visi & Misi',
                 'isi' => "<h3>Visi</h3><p>Terwujudnya generasi muslim yang Qur'ani, berilmu, berakhlak mulia, mandiri, dan berprestasi.</p><h3>Misi</h3><ul><li>Menyelenggarakan pembelajaran yang memadukan ilmu agama dan ilmu pengetahuan umum.</li><li>Membina hafalan dan pemahaman Al-Qur'an sebagai dasar akhlak.</li><li>Mengembangkan kompetensi guru dan tenaga kependidikan secara berkelanjutan.</li><li>Menyediakan lingkungan belajar yang nyaman, aman, dan menyenangkan.</li><li>Mendorong prestasi akademik maupun non-akademik peserta didik.</li></ul>"],
                ['label' => 'Struktur Organisasi', 'slug' => 'struktur', 'jenis' => 'halaman', 'judul' => 'Struktur Organisasi',
                 'isi' => "<p>Pengelolaan madrasah dilaksanakan secara bersama oleh pimpinan, guru, dan tenaga kependidikan dengan pembagian tugas yang jelas.</p><ul><li>Kepala Madrasah — memimpin dan mengendalikan penyelenggaraan pendidikan.</li><li>Wakil Kepala Bidang Kurikulum — mengembangkan kurikulum dan pembelajaran.</li><li>Wakil Kepala Bidang Kesiswaan — membina peserta didik dan kegiatan ekstrakurikuler.</li><li>Kepala Tata Usaha — mengelola administrasi dan sarana prasarana.</li><li>Guru dan Tenaga Kependidikan — melaksanakan pembelajaran dan pendampingan.</li></ul>"],
                ['label' => 'Sambutan Kepala', 'slug' => 'sambutan', 'jenis' => 'halaman', 'judul' => 'Sambutan ' . 'Kepala',
                 'isi' => "<p>Assalamu'alaikum warahmatullahi wabarakatuh.</p><p>Puji syukur kami panjatkan kepada Allah SWT atas segala rahmat-Nya. Selamat datang di website resmi madrasah kami.</p><p>Website ini kami hadirkan sebagai media informasi, komunikasi, dan publikasi kegiatan madrasah. Semoga bermanfaat bagi peserta didik, orang tua, dan masyarakat luas.</p><p>Wassalamu'alaikum warahmatullahi wabarakatuh.</p>"],
            ],
        ],
        [
            'label' => 'Akademik', 'slug' => 'akademik', 'jenis' => 'halaman',
            'judul' => 'Akademik', 'subjudul' => 'Kurikulum, tenaga pendidik, dan layanan pembelajaran',
            'isi'   => "<p>Pembelajaran di madrasah kami memadukan Kurikulum Merdeka dengan penguatan pendidikan agama dan karakter.</p>",
            'anak'  => [
                ['label' => 'Kurikulum', 'slug' => 'kurikulum', 'jenis' => 'halaman', 'judul' => 'Kurikulum',
                 'isi' => "<p>Madrasah menerapkan Kurikulum Merdeka dengan penguatan mata pelajaran Pendidikan Agama Islam dan Bahasa Arab.</p><h3>Mata Pelajaran Umum</h3><ul><li>Pendidikan Agama Islam (Al-Qur'an Hadis, Fikih, Akidah Akhlak, Sejarah Kebudayaan Islam)</li><li>Bahasa Arab, Bahasa Indonesia, Bahasa Inggris</li><li>Matematika, Ilmu Pengetahuan Alam, Ilmu Pengetahuan Sosial</li><li>PPKn, Seni Budaya, Pendidikan Jasmani</li></ul><h3>Penguatan Karakter</h3><p>Pembiasaan ibadah harian, tahfiz, dan pembinaan akhlak menjadi bagian tak terpisahkan dari proses pembelajaran.</p>"],
                ['label' => 'Tenaga Pendidik', 'slug' => 'tenaga-pendidik', 'jenis' => 'guru', 'judul' => 'Guru & Tenaga Kependidikan',
                 'isi' => "<p>Guru dan tenaga kependidikan kami berkompeten di bidangnya serta terus mengembangkan diri melalui pelatihan dan musyawarah guru mata pelajaran.</p><p>Data lengkap pendidik dan tenaga kependidikan tersedia pada menu Galeri/Data Guru di panel admin.</p>"],
                ['label' => 'Ekstrakurikuler', 'slug' => 'ekstrakurikuler', 'jenis' => 'halaman', 'judul' => 'Ekstrakurikuler',
                 'isi' => "<p>Untuk mengembangkan bakat dan minat peserta didik, madrasah menyediakan beragam kegiatan ekstrakurikuler:</p><ul><li>Tahfiz dan Tilawah Al-Qur'an</li><li>Pramuka</li><li>Palang Merah Remaja (PMR)</li><li>Olimpiade Sains dan Matematika</li><li>Futsal, voli, dan seni bela diri</li><li>Seni kaligrafi dan hadrah</li></ul>"],
                ['label' => 'Kalender Akademik', 'slug' => 'kalender-akademik', 'jenis' => 'halaman', 'judul' => 'Kalender Akademik',
                 'isi' => "<p>Kalender akademik memuat jadwal kegiatan pembelajaran, penilaian, dan kegiatan kesiswaan sepanjang tahun ajaran.</p><ul><li>Semester Ganjil: Juli - Desember</li><li>Semester Genap: Januari - Juni</li><li>Asesmen Sumatif Tengah Semester dan Akhir Semester sesuai jadwal madrasah</li><li>Libur semester mengikuti kalender pendidikan madrasah dan Kementerian Agama</li></ul>"],
            ],
        ],
        [
            'label' => 'Kesiswaan', 'slug' => 'kesiswaan', 'jenis' => 'halaman',
            'judul' => 'Kesiswaan', 'subjudul' => 'Pembinaan peserta didik dan prestasi',
            'isi'   => "<p>Pembinaan kesiswaan diarahkan pada pembentukan karakter, kepemimpinan, dan pengembangan bakat peserta didik.</p>",
            'anak'  => [
                ['label' => 'OSIM', 'slug' => 'osim', 'jenis' => 'halaman', 'judul' => 'Organisasi Siswa Intra Madrasah',
                 'isi' => "<p>Organisasi Siswa Intra Madrasah (OSIM) menjadi wadah pembinaan kepemimpinan, kedisiplinan, dan keorganisasian peserta didik.</p><h3>Program OSIM</h3><ul><li>Peringatan hari besar Islam dan nasional</li><li>Bakti sosial dan kerja bakti</li><li>Lomba kebersihan dan kelas berprestasi</li><li>Pengembangan minat dan bakat</li></ul>"],
                ['label' => 'Prestasi Siswa', 'slug' => 'prestasi', 'jenis' => 'halaman', 'judul' => 'Prestasi Siswa',
                 'isi' => "<p>Berbagai prestasi telah diraih peserta didik, baik di tingkat kabupaten, provinsi, maupun nasional — mulai dari Olimpiade Sains Madrasah, lomba tahfiz, hingga kompetisi olahraga dan seni.</p><p>Prestasi terbaru dapat diikuti pada halaman berita.</p>"],
                ['label' => 'Kegiatan Siswa', 'slug' => 'kegiatan-siswa', 'jenis' => 'halaman', 'judul' => 'Kegiatan Siswa',
                 'isi' => "<p>Kegiatan peserta didik didokumentasikan secara berkala pada halaman galeri, termasuk kegiatan pembelajaran, ekstrakurikuler, dan kegiatan madrasah lainnya.</p>"],
            ],
        ],
        [
            'label' => 'Fasilitas', 'slug' => 'fasilitas', 'jenis' => 'fasilitas',
            'judul' => 'Fasilitas', 'subjudul' => 'Sarana dan prasarana pendukung pembelajaran',
            'isi'   => "<p>Madrasah menyediakan sarana dan prasarana untuk menunjang kenyamanan dan keberhasilan pembelajaran peserta didik.</p>",
            'anak'  => [
                ['label' => 'Sarana & Prasarana', 'slug' => 'sarana-prasarana', 'jenis' => 'halaman', 'judul' => 'Sarana & Prasarana',
                 'isi' => "<p>Selain daftar fasilitas pada halaman utama Fasilitas, madrasah terus melengkapi sarana pembelajaran seperti media pembelajaran, komputer, dan alat peraga.</p>"],
                ['label' => 'Perpustakaan', 'slug' => 'perpustakaan', 'jenis' => 'halaman', 'judul' => 'Perpustakaan',
                 'isi' => "<p>Perpustakaan madrasah menyediakan koleksi buku pelajaran, buku umum, dan buku agama yang dapat dibaca serta dipinjam oleh peserta didik.</p>"],
                ['label' => 'Laboratorium', 'slug' => 'laboratorium', 'jenis' => 'halaman', 'judul' => 'Laboratorium',
                 'isi' => "<p>Laboratorium IPA dan laboratorium komputer digunakan untuk pembelajaran praktik sehingga peserta didik lebih memahami materi secara nyata.</p>"],
            ],
        ],
        ['label' => 'Berita', 'slug' => 'berita', 'jenis' => 'berita', 'judul' => 'Berita', 'subjudul' => 'Kabar terbaru madrasah', 'isi' => '', 'anak' => []],
        ['label' => 'Galeri', 'slug' => 'galeri', 'jenis' => 'galeri', 'judul' => 'Galeri', 'subjudul' => 'Dokumentasi foto & video kegiatan', 'isi' => '', 'anak' => []],
        ['label' => 'Kontak', 'slug' => 'kontak', 'jenis' => 'kontak', 'judul' => 'Kontak', 'subjudul' => 'Hubungi kami', 'isi' => '', 'anak' => []],
        ['label' => 'PPDB Online', 'slug' => 'ppdb', 'jenis' => 'ppdb', 'judul' => 'Penerimaan Peserta Didik Baru', 'subjudul' => 'Bergabunglah bersama kami', 'isi' => '', 'sorot' => 1, 'anak' => []],
    ];

    foreach ($pohon as $i => $m) {
        $pdo->prepare('INSERT INTO menu (induk_id, label, slug, jenis, tautan, judul, subjudul, isi, ikon, urutan, tampil_nav, sorot, aktif)
                       VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 1)')
            ->execute([
                $m['label'], $m['slug'], $m['jenis'], $m['tautan'] ?? '', $m['judul'] ?? $m['label'],
                $m['subjudul'] ?? '', $m['isi'] ?? '', $m['ikon'] ?? '', ($i + 1) * 10, $m['sorot'] ?? 0,
            ]);
        $indukId = (int) $pdo->lastInsertId();
        foreach (($m['anak'] ?? []) as $j => $a) {
            $pdo->prepare('INSERT INTO menu (induk_id, label, slug, jenis, tautan, judul, subjudul, isi, ikon, urutan, tampil_nav, sorot, aktif)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 1)')
                ->execute([
                    $indukId, $a['label'], $a['slug'], $a['jenis'], $a['tautan'] ?? '',
                    $a['judul'] ?? $a['label'], $a['subjudul'] ?? '', $a['isi'] ?? '', $a['ikon'] ?? '', ($j + 1) * 10,
                ]);
        }
    }
}

function db_pdo_program(PDO $pdo): void
{
    // Slider besar beranda
    db_masukkan($pdo, 'slider', [
        ['badge' => 'Selamat Datang di Website Resmi', 'judul' => 'MA. Sirojuth Tholibiin', 'subjudul' => 'Mootilango',
         'keterangan' => "Mencetak Generasi Qur'ani, Berilmu, Berakhlak Mulia dan Berprestasi",
         'gambar' => '', 'tombol_teks' => 'Kenali Lebih Dekat', 'tombol_tautan' => 'halaman.php?slug=profil', 'urutan' => 10, 'aktif' => 1],
        ['badge' => 'Penerimaan Peserta Didik Baru', 'judul' => 'Bergabung Bersama', 'subjudul' => 'Kami',
         'keterangan' => 'Pendaftaran PPDB dibuka secara online — mudah, cepat, dan tanpa biaya pendaftaran.',
         'gambar' => '', 'tombol_teks' => 'Daftar Sekarang', 'tombol_tautan' => 'ppdb.php', 'urutan' => 20, 'aktif' => 1],
        ['badge' => 'Fasilitas & Lingkungan Belajar', 'judul' => 'Belajar Nyaman', 'subjudul' => 'Berprestasi Gemilang',
         'keterangan' => 'Ruang kelas nyaman, laboratorium, perpustakaan, dan sarana olahraga untuk menunjang pembelajaran.',
         'gambar' => '', 'tombol_teks' => 'Lihat Fasilitas', 'tombol_tautan' => 'fasilitas.php', 'urutan' => 30, 'aktif' => 1],
    ]);

    // Strip keunggulan di bawah slider
    db_masukkan($pdo, 'keunggulan', [
        ['judul' => 'Kurikulum Terpadu', 'keterangan' => 'Mengintegrasikan ilmu agama dan ilmu pengetahuan umum', 'ikon' => 'book', 'urutan' => 10, 'aktif' => 1],
        ['judul' => 'Tenaga Pendidik Profesional', 'keterangan' => 'Guru berpengalaman dan berkompeten di bidangnya', 'ikon' => 'users', 'urutan' => 20, 'aktif' => 1],
        ['judul' => 'Fasilitas Lengkap', 'keterangan' => 'Ruang kelas nyaman, laboratorium, perpustakaan, dan sarana olahraga', 'ikon' => 'building', 'urutan' => 30, 'aktif' => 1],
        ['judul' => 'Prestasi Siswa', 'keterangan' => 'Berbagai prestasi di tingkat kabupaten, provinsi dan nasional', 'ikon' => 'medal', 'urutan' => 40, 'aktif' => 1],
    ]);

    // Program Digitalisasi Madrasah — daftar aplikasi (satu sumber: PROGRAM_DIGITAL_BAWAAN)
    $program = [];
    $i = 10;
    foreach (PROGRAM_DIGITAL_BAWAAN as $ap) {
        $program[] = ['judul' => $ap[0], 'keterangan' => $ap[1], 'ikon' => $ap[2], 'tautan' => $ap[3], 'urutan' => $i, 'aktif' => 1];
        $i += 10;
    }
    db_masukkan($pdo, 'program', $program);
}

function db_pdo_berita(PDO $pdo): void
{
    db_masukkan($pdo, 'berita_kategori', [
        ['nama' => 'Kegiatan', 'slug' => 'kegiatan', 'urutan' => 10, 'aktif' => 1],
        ['nama' => 'Prestasi', 'slug' => 'prestasi', 'urutan' => 20, 'aktif' => 1],
        ['nama' => 'Pengumuman', 'slug' => 'pengumuman', 'urutan' => 30, 'aktif' => 1],
    ]);
    $kat = [];
    foreach ($pdo->query('SELECT id, slug FROM berita_kategori') as $r) {
        $kat[$r['slug']] = (int) $r['id'];
    }
    $hari = static fn (int $n): string => date('Y-m-d', strtotime('-' . $n . ' day'));
    db_masukkan($pdo, 'berita', [
        ['judul' => 'Kegiatan MPLS Siswa Baru Tahun Ajaran ' . date('Y') . '/' . (date('Y') + 1),
         'slug' => 'kegiatan-mpls-siswa-baru', 'kategori_id' => $kat['kegiatan'] ?? 0,
         'ringkasan' => 'Masa Pengenalan Lingkungan Madrasah (MPLS) diikuti seluruh peserta didik baru dengan rangkaian kegiatan pengenalan budaya madrasah.',
         'isi' => "<p>Kegiatan Masa Pengenalan Lingkungan Madrasah (MPLS) berlangsung selama tiga hari dan diikuti oleh seluruh peserta didik baru.</p><p>Rangkaian kegiatan meliputi pengenalan lingkungan madrasah, tata tertib, program pembelajaran, serta pembinaan karakter dan kedisiplinan.</p><p>Kepala madrasah berpesan agar peserta didik baru segera beradaptasi dan memanfaatkan waktu belajar dengan sebaik-baiknya.</p>",
         'penulis' => 'Humas Madrasah', 'tanggal' => $hari(2), 'terbit' => 1, 'dilihat' => 24, 'dibuat' => date('Y-m-d H:i:s')],
        ['judul' => 'Prestasi Siswa pada Ajang Olimpiade Madrasah',
         'slug' => 'prestasi-olimpiade-madrasah', 'kategori_id' => $kat['prestasi'] ?? 0,
         'ringkasan' => 'Peserta didik berhasil meraih juara pada Olimpiade Sains Madrasah tingkat kabupaten.',
         'isi' => "<p>Alhamdulillah, peserta didik madrasah kembali menorehkan prestasi pada ajang Olimpiade Sains Madrasah tingkat kabupaten.</p><p>Prestasi ini merupakan hasil pembinaan rutin guru pembimbing dan kerja keras peserta didik.</p><p>Madrasah mengucapkan terima kasih atas dukungan orang tua dan seluruh pihak.</p>",
         'penulis' => 'Humas Madrasah', 'tanggal' => $hari(10), 'terbit' => 1, 'dilihat' => 41, 'dibuat' => date('Y-m-d H:i:s')],
        ['judul' => 'Upacara HUT RI ke-80 di Madrasah',
         'slug' => 'upacara-hut-ri', 'kategori_id' => $kat['kegiatan'] ?? 0,
         'ringkasan' => 'Seluruh peserta didik, guru, dan tenaga kependidikan mengikuti upacara peringatan HUT Kemerdekaan RI.',
         'isi' => "<p>Peringatan HUT Kemerdekaan Republik Indonesia berlangsung khidmat di lapangan madrasah.</p><p>Rangkaian kegiatan dilanjutkan dengan lomba antar kelas yang diikuti peserta didik dengan penuh semangat.</p>",
         'penulis' => 'Humas Madrasah', 'tanggal' => $hari(20), 'terbit' => 1, 'dilihat' => 33, 'dibuat' => date('Y-m-d H:i:s')],
        ['judul' => 'Pengumuman: Jadwal Asesmen Sumatif Akhir Semester',
         'slug' => 'jadwal-asesmen-sumatif-akhir-semester', 'kategori_id' => $kat['pengumuman'] ?? 0,
         'ringkasan' => 'Jadwal asesmen sumatif akhir semester telah ditetapkan dan dapat diunduh pada menu akademik.',
         'isi' => "<p>Diberitahukan kepada seluruh peserta didik bahwa jadwal asesmen sumatif akhir semester telah ditetapkan.</p><p>Peserta didik diharapkan mempersiapkan diri dan mengikuti seluruh rangkaian asesmen dengan tertib.</p>",
         'penulis' => 'Kurikulum', 'tanggal' => $hari(30), 'terbit' => 1, 'dilihat' => 57, 'dibuat' => date('Y-m-d H:i:s')],
    ]);
}

function db_pdo_agenda(PDO $pdo): void
{
    db_masukkan($pdo, 'agenda', [
        ['judul' => 'Rapat Orang Tua Peserta Didik', 'tanggal' => date('Y-m-d', strtotime('+3 day')), 'waktu' => '08.00 - 11.00 WITA', 'tempat' => 'Aula Madrasah', 'keterangan' => 'Pertemuan dengan orang tua/wali membahas program pembelajaran dan pembinaan peserta didik.', 'aktif' => 1],
        ['judul' => 'Peringatan Isra Mikraj', 'tanggal' => date('Y-m-d', strtotime('+8 day')), 'waktu' => '07.30 WITA', 'tempat' => 'Masjid Madrasah', 'keterangan' => 'Ceramah agama bersama seluruh peserta didik dan guru.', 'aktif' => 1],
        ['judul' => 'Kegiatan Pramuka Perkemahan Sabtu Minggu', 'tanggal' => date('Y-m-d', strtotime('+15 day')), 'waktu' => 'Sabtu - Minggu', 'tempat' => 'Bumi Perkemahan Mootilango', 'keterangan' => 'Perkemahan rutin pembinaan kemandirian peserta didik.', 'aktif' => 1],
        ['judul' => 'Sosialisasi PPDB Tahun Ajaran Baru', 'tanggal' => date('Y-m-d', strtotime('+22 day')), 'waktu' => '09.00 WITA', 'tempat' => 'Aula Madrasah', 'keterangan' => 'Sosialisasi alur, syarat, dan jadwal penerimaan peserta didik baru.', 'aktif' => 1],
    ]);
}

function db_pdo_galeri(PDO $pdo): void
{
    db_masukkan($pdo, 'galeri_album', [
        ['nama' => 'Kegiatan Pembelajaran', 'slug' => 'kegiatan-pembelajaran', 'keterangan' => 'Dokumentasi kegiatan belajar mengajar di kelas.', 'urutan' => 10, 'aktif' => 1],
        ['nama' => 'Kegiatan Madrasah', 'slug' => 'kegiatan-madrasah', 'keterangan' => 'Dokumentasi kegiatan resmi madrasah.', 'urutan' => 20, 'aktif' => 1],
        ['nama' => 'Ekstrakurikuler', 'slug' => 'ekstrakurikuler', 'keterangan' => 'Dokumentasi kegiatan ekstrakurikuler peserta didik.', 'urutan' => 30, 'aktif' => 1],
    ]);
    $album = [];
    foreach ($pdo->query('SELECT id, slug FROM galeri_album') as $r) {
        $album[$r['slug']] = (int) $r['id'];
    }
    // Video galeri sengaja dibiarkan KOSONG: admin menambahkan tautan YouTube sendiri
    // (lihat halaman publik Galeri yang menampilkan petunjuk bila belum ada video).
}

function db_pdo_fasilitas(PDO $pdo): void
{
    db_masukkan($pdo, 'fasilitas', [
        ['nama' => 'Ruang Kelas Nyaman', 'keterangan' => 'Ruang kelas dengan penerangan dan sirkulasi udara yang baik.', 'urutan' => 10, 'aktif' => 1],
        ['nama' => 'Laboratorium IPA', 'keterangan' => 'Praktikum ilmu pengetahuan alam dengan alat peraga memadai.', 'urutan' => 20, 'aktif' => 1],
        ['nama' => 'Laboratorium Komputer', 'keterangan' => 'Pembelajaran komputer dan literasi digital peserta didik.', 'urutan' => 30, 'aktif' => 1],
        ['nama' => 'Perpustakaan', 'keterangan' => 'Koleksi buku pelajaran, buku umum, dan buku keagamaan.', 'urutan' => 40, 'aktif' => 1],
        ['nama' => 'Masjid / Musala', 'keterangan' => 'Pusat kegiatan ibadah dan pembinaan keagamaan.', 'urutan' => 50, 'aktif' => 1],
        ['nama' => 'Lapangan Olahraga', 'keterangan' => 'Sarana olahraga untuk kegiatan jasmani dan ekstrakurikuler.', 'urutan' => 60, 'aktif' => 1],
    ]);
}

function db_pdo_testimoni(PDO $pdo): void
{
    db_masukkan($pdo, 'testimoni', [
        ['nama' => 'Ahmad Fauzi', 'jabatan' => 'Alumni', 'isi' => 'Pembinaan di madrasah ini sangat berpengaruh bagi saya. Ilmu agama dan umum diberikan seimbang.', 'urutan' => 10, 'aktif' => 1],
        ['nama' => 'Nurhaliza', 'jabatan' => 'Peserta Didik', 'isi' => 'Guru-gurunya sabar dan menyenangkan. Saya senang belajar dan mengikuti kegiatan ekstrakurikuler.', 'urutan' => 20, 'aktif' => 1],
        ['nama' => 'Bapak Sudirman', 'jabatan' => 'Orang Tua Peserta Didik', 'isi' => 'Lingkungan madrasah aman dan nyaman. Komunikasi dengan orang tua juga sangat baik.', 'urutan' => 30, 'aktif' => 1],
        ['nama' => 'Rahmawati', 'jabatan' => 'Alumni', 'isi' => 'Terima kasih untuk pembinaan akhlak yang menjadi bekal saya sampai sekarang.', 'urutan' => 40, 'aktif' => 1],
    ]);
}

function db_pdo_sosial(PDO $pdo): void
{
    db_masukkan($pdo, 'sosial', [
        ['nama' => 'Facebook', 'ikon' => 'facebook', 'tautan' => 'https://facebook.com/', 'urutan' => 10, 'aktif' => 1],
        ['nama' => 'Instagram', 'ikon' => 'instagram', 'tautan' => 'https://instagram.com/', 'urutan' => 20, 'aktif' => 1],
        ['nama' => 'YouTube', 'ikon' => 'youtube', 'tautan' => 'https://youtube.com/', 'urutan' => 30, 'aktif' => 1],
        ['nama' => 'WhatsApp', 'ikon' => 'whatsapp', 'tautan' => 'https://wa.me/6285394726089', 'urutan' => 40, 'aktif' => 1],
    ]);

    // Slider kecil beranda (gambar + tautan media sosial/konten) — belum berisi gambar,
    // admin mengunggah sendiri; placeholder tampil bergradasi agar tetap rapi.
    db_masukkan($pdo, 'slider_kecil', [
        ['keterangan' => 'Kegiatan pembelajaran di kelas', 'gambar' => '', 'tautan' => 'galeri.php', 'urutan' => 10, 'aktif' => 1],
        ['keterangan' => 'Kegiatan ekstrakurikuler', 'gambar' => '', 'tautan' => 'halaman.php?slug=ekstrakurikuler', 'urutan' => 20, 'aktif' => 1],
        ['keterangan' => 'Prestasi peserta didik', 'gambar' => '', 'tautan' => 'berita.php', 'urutan' => 30, 'aktif' => 1],
        ['keterangan' => 'Ikuti kanal media sosial kami', 'gambar' => '', 'tautan' => 'kontak.php', 'urutan' => 40, 'aktif' => 1],
    ]);
}

/**
 * Menambahkan sub menu "Data Siswa" & "Data Alumni" pada menu Kesiswaan (sekali saja).
 * Dilewati bila menunya sudah ada sehingga aman dijalankan berulang / pada basis data terisi.
 */
/**
 * Penyemaian menu Prestasi Siswa / Prestasi Madrasah / Prestasi Pendidik / Pelatihan Pendidik.
 *
 * Bersifat UPSERT:
 *  - menu dicari lewat beberapa calon slug lalu lewat LABEL (tanpa peduli besar-kecil huruf),
 *    sehingga menu yang sudah dibuat pemilik sendiri tidak digandakan;
 *  - menu yang sudah ada namun masih berjenis 'halaman' ditingkatkan jenisnya ke jenis prestasi
 *    (judul/subjudul yang sudah diisi pemilik TIDAK ditimpa);
 *  - bila belum ada, menu dibuat pada menu induk yang sesuai (Akademik / Kesiswaan).
 */
/**
 * Mengganti bagian beranda "Program Unggulan" menjadi **Program Digitalisasi Madrasah**
 * (daftar aplikasi yang dipakai madrasah) — dijalankan SEKALI.
 *
 * Aman terhadap suntingan pemilik: judul bagian hanya diganti bila masih memakai judul bawaan,
 * dan daftar program bawaan hanya diganti bila isinya masih persis empat program bawaan
 * (Tahfidz/Sains/Pembinaan Akhlak/Ekstrakurikuler). Bila pemilik sudah menyuntingnya, aplikasi
 * yang belum ada hanya DITAMBAHKAN di akhir sehingga tidak ada data yang hilang.
 */
function db_program_digital(PDO $pdo): void
{
    $judulLama = 'Program Unggulan';
    $judulBaru = 'Program Digitalisasi Madrasah';
    $teksLama = 'Beberapa program unggulan yang menjadi ciri khas layanan pendidikan kami.';
    $teksBaru = 'Berbagai aplikasi digital yang kami gunakan untuk mempercepat dan merapikan administrasi madrasah — dari administrasi guru, supervisi akademik, hingga asesmen siswa.';

    $set = $pdo->prepare('UPDATE pengaturan SET nilai = ? WHERE kunci = ? AND nilai = ?');
    $set->execute([$judulBaru, 'beranda_judul_program', $judulLama]);
    $set->execute([$teksBaru, 'beranda_program_teks', $teksLama]);

    $apps = PROGRAM_DIGITAL_BAWAAN;

    $bawaan = ["Tahfidz Al-Qur'an", 'Sains & Teknologi', 'Pembinaan Akhlak', 'Ekstrakurikuler'];
    $adaSekarang = array_column($pdo->query('SELECT judul FROM program')->fetchAll(PDO::FETCH_ASSOC), 'judul');
    $masihBawaan = count($adaSekarang) === count($bawaan) && !array_diff($adaSekarang, $bawaan);

    if ($masihBawaan) {
        // masih isi bawaan → ganti seluruhnya dengan daftar aplikasi
        $pdo->exec('DELETE FROM program');
        $urutan = 0;
    } else {
        $urutan = (int) $pdo->query('SELECT COALESCE(MAX(urutan), 0) FROM program')->fetchColumn();
    }
    $ins = $pdo->prepare('INSERT INTO program (judul, keterangan, ikon, tautan, urutan, aktif) VALUES (?, ?, ?, ?, ?, 1)');
    foreach ($apps as $a) {
        $urutan += 10;
        if (in_array($a[0], $adaSekarang, true)) {
            continue;   // aplikasi dengan judul sama sudah ada (suntingan pemilik) → jangan digandakan
        }
        $ins->execute([$a[0], $a[1], $a[2], $a[3], $urutan]);
    }
}

function db_menu_prestasi(PDO $pdo): void
{
    $daftar = [
        ['label' => 'Prestasi Siswa', 'jenis' => 'prestasi_siswa', 'induk' => 'kesiswaan',
         'slugs' => ['prestasi-siswa', 'prestasi'],
         'subjudul' => 'Prestasi yang diraih peserta didik pada berbagai tingkat perlombaan.'],
        ['label' => 'Prestasi Madrasah', 'jenis' => 'prestasi_madrasah', 'induk' => 'akademik',
         'slugs' => ['prestasi-madrasah'],
         'subjudul' => 'Prestasi dan penghargaan yang diraih madrasah.'],
        ['label' => 'Prestasi Pendidik', 'jenis' => 'prestasi_pendidik', 'induk' => 'akademik',
         'slugs' => ['prestasi-pendidik', 'prestasi-guru'],
         'subjudul' => 'Prestasi yang diraih guru dan tenaga kependidikan.'],
        ['label' => 'Pelatihan Pendidik', 'jenis' => 'pelatihan_pendidik', 'induk' => 'akademik',
         'slugs' => ['pelatihan-pendidik', 'pelatihan-guru'],
         'subjudul' => 'Data pelatihan yang diikuti guru dan tenaga kependidikan.'],
    ];
    $cariSlug = $pdo->prepare('SELECT * FROM menu WHERE slug = ? LIMIT 1');
    $cariLabel = $pdo->prepare('SELECT * FROM menu WHERE LOWER(label) = LOWER(?) LIMIT 1');
    $perbarui = $pdo->prepare('UPDATE menu SET jenis = ?, judul = ?, subjudul = ? WHERE id = ?');
    $tambah = $pdo->prepare('INSERT INTO menu (induk_id, label, slug, jenis, tautan, judul, subjudul, isi, ikon, urutan, tampil_nav, sorot, aktif)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 1)');

    foreach ($daftar as $d) {
        $ada = null;
        foreach ($d['slugs'] as $slug) {
            $cariSlug->execute([$slug]);
            $ada = $cariSlug->fetch(PDO::FETCH_ASSOC);
            if ($ada) {
                break;
            }
        }
        if (!$ada) {
            $cariLabel->execute([$d['label']]);
            $ada = $cariLabel->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if ($ada) {
            $judul = trim((string) $ada['judul']) !== '' ? (string) $ada['judul'] : $d['label'];
            $sub = trim((string) $ada['subjudul']) !== '' ? (string) $ada['subjudul'] : $d['subjudul'];
            if ((string) $ada['jenis'] !== $d['jenis'] || $judul !== (string) $ada['judul'] || $sub !== (string) $ada['subjudul']) {
                $perbarui->execute([$d['jenis'], $judul, $sub, (int) $ada['id']]);
            }
            continue;
        }
        $induk = $pdo->prepare('SELECT id FROM menu WHERE slug = ? LIMIT 1');
        $induk->execute([$d['induk']]);
        $indukId = (int) ($induk->fetchColumn() ?: 0);
        $urutan = 0;
        if ($indukId > 0) {
            $q = $pdo->query('SELECT COALESCE(MAX(urutan), 0) FROM menu WHERE induk_id = ' . $indukId);
            $urutan = (int) $q->fetchColumn();
        }
        $tambah->execute([$indukId, $d['label'], $d['slugs'][0], $d['jenis'], '', $d['label'], $d['subjudul'], '', '', $urutan + 10]);
    }
}

function db_menu_kesiswaan(PDO $pdo): void
{
    $induk = $pdo->query("SELECT id FROM menu WHERE slug = 'kesiswaan' LIMIT 1")->fetchColumn();
    if (!$induk) {
        return;
    }
    $indukId = (int) $induk;
    $urutan = (int) $pdo->query('SELECT COALESCE(MAX(urutan), 0) FROM menu WHERE induk_id = ' . $indukId)->fetchColumn();
    $baru = [
        ['Data Siswa', 'data-siswa', 'siswa', 'Daftar peserta didik yang masih aktif belajar beserta kelasnya.'],
        ['Data Alumni', 'data-alumni', 'alumni', 'Peserta didik yang telah lulus beserta tahun masuk, tahun keluar, dan kegiatannya sekarang.'],
    ];
    $st = $pdo->prepare('INSERT INTO menu (induk_id, label, slug, jenis, tautan, judul, subjudul, isi, ikon, urutan, tampil_nav, sorot, aktif)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 1)');
    foreach ($baru as $b) {
        [$label, $slug, $jenis, $subjudul] = $b;
        $ada = $pdo->query('SELECT id, jenis, judul, subjudul FROM menu WHERE slug = ' . $pdo->quote($slug))->fetch(PDO::FETCH_ASSOC);
        if ($ada) {
            // Menu sudah dibuat pemilik sebagai halaman biasa → tingkatkan ke jenis yang benar.
            // Judul/subjudul yang sudah diisi pemilik TIDAK ditimpa.
            $judulBaru = trim((string) $ada['judul']) !== '' ? (string) $ada['judul'] : $label;
            $subBaru = trim((string) $ada['subjudul']) !== '' ? (string) $ada['subjudul'] : $subjudul;
            $pdo->prepare('UPDATE menu SET jenis = ?, judul = ?, subjudul = ? WHERE id = ?')
                ->execute([$jenis, $judulBaru, $subBaru, (int) $ada['id']]);
            continue;
        }
        $urutan += 10;
        $st->execute([$indukId, $label, $slug, $jenis, '', $label, $subjudul, '', '', $urutan]);
    }
}

function db_pdo_poll(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM poll')->fetchColumn() > 0) {
        return;   // jangan menimpa polling yang sudah dibuat admin
    }
    $pdo->prepare('INSERT INTO poll (pertanyaan, keterangan, aktif, dibuat) VALUES (?, ?, 1, ?)')
        ->execute(['Bagaimana pendapat anda tentang web ini?', 'Pilih salah satu jawaban — pendapat Anda membantu kami memperbaiki tampilan dan isi website.', date('Y-m-d H:i:s')]);
    $pollId = (int) $pdo->lastInsertId();
    $pilihan = [
        'Sangat baik & menarik',
        'Baik',
        'Cukup baik',
        'Perlu banyak perbaikan',
    ];
    $i = 10;
    $st = $pdo->prepare('INSERT INTO poll_pilihan (poll_id, teks, urutan, aktif) VALUES (?, ?, ?, 1)');
    foreach ($pilihan as $t) {
        $st->execute([$pollId, $t, $i]);
        $i += 10;
    }
}

function db_pdo_guru(PDO $pdo): void
{
    db_masukkan($pdo, 'kelas', [
        ['nama' => 'VII', 'tingkat' => 'VII', 'wali' => 'Nining, S.Pd', 'urutan' => 10, 'aktif' => 1],
        ['nama' => 'VIII', 'tingkat' => 'VIII', 'wali' => 'Astia A. Hanunu, S.Pd', 'urutan' => 20, 'aktif' => 1],
        ['nama' => 'IX', 'tingkat' => 'IX', 'wali' => 'Rusli Ibrahim, S.Ag', 'urutan' => 30, 'aktif' => 1],
    ]);

    $guru = [
        ['Supianto, S.Pd.I', 'L', 'Kepala Madrasah', 'Bahasa Arab'],
        ['Rusli Ibrahim, S.Ag', 'L', 'Wakil Kepala Bidang Kurikulum', 'Al-Qur\'an Hadis'],
        ['Nining, S.Pd', 'P', 'Wakil Kepala Bidang Kesiswaan', 'Matematika'],
        ['Astia A. Hanunu, S.Pd', 'P', 'Guru', 'Bahasa Indonesia'],
        ['Herianto Dalanggo, M.Pd.I', 'L', 'Guru', 'Fikih'],
        ['Fatmawati, S.Pd', 'P', 'Guru', 'Ilmu Pengetahuan Alam'],
        ['Abdul Rahman, S.Ag', 'L', 'Guru', 'Akidah Akhlak'],
        ['Sitti Aminah, S.Pd', 'P', 'Guru', 'Bahasa Inggris'],
        ['Muhammad Yusuf, S.Pd', 'L', 'Guru', 'Ilmu Pengetahuan Sosial'],
        ['Yuniarti, S.Pd', 'P', 'Guru', 'Sejarah Kebudayaan Islam'],
        ['Hasanudin, S.Pd', 'L', 'Guru', 'Pendidikan Jasmani'],
        ['Marlina, A.Md', 'P', 'Kepala Tata Usaha', 'Administrasi'],
        ['Ramli, S.Kom', 'L', 'Tenaga Kependidikan', 'Teknologi Informasi'],
    ];
    $i = 10;
    foreach ($guru as $g) {
        db_masukkan($pdo, 'guru', [[
            'nama' => $g[0], 'nip' => '', 'jenis_kelamin' => $g[1], 'jabatan' => $g[2], 'mapel' => $g[3],
            'email' => '', 'telepon' => '', 'foto' => '', 'status' => 'aktif', 'urutan' => $i, 'dibuat' => date('Y-m-d H:i:s'),
        ]]);
        $i += 10;
    }
}

function db_pdo_siswa(PDO $pdo): void
{
    $kelas = [];
    foreach ($pdo->query('SELECT id, nama FROM kelas') as $r) {
        $kelas[$r['nama']] = (int) $r['id'];
    }
    $namaL = ['Ahmad', 'Muhammad', 'Abdul', 'Hasan', 'Umar', 'Rizki', 'Fajar', 'Ilham', 'Naufal', 'Rifki', 'Yusran', 'Zulkifli', 'Ardi', 'Bayu', 'Candra', 'Dedi', 'Eko', 'Farhan', 'Gunawan', 'Hendra'];
    $namaP = ['Siti', 'Nur', 'Aisyah', 'Fatimah', 'Hafsa', 'Khadijah', 'Maryam', 'Zainab', 'Rahma', 'Salma', 'Wulan', 'Yuni', 'Ayu', 'Bella', 'Citra', 'Dewi', 'Elsa', 'Fitri', 'Gita', 'Hana'];
    $ayah = ['Sudirman', 'Hasanuddin', 'Rahmat', 'Yusuf', 'Ilham', 'Anwar', 'Basri', 'Karim', 'Latif', 'Samad'];
    $ibu  = ['Halimah', 'Aminah', 'Saripa', 'Nurlaila', 'Rahmawati', 'Suryani', 'Murni', 'Hadijah', 'Rosna', 'Fitriani'];
    $kelasId = array_values($kelas);
    $baris = [];
    $no = 1;
    // Siswa aktif: 12 per kelas
    foreach ($kelasId as $ki => $kid) {
        for ($n = 0; $n < 12; $n++) {
            $laki = ($n % 2 === 0);
            $depan = $laki ? $namaL[($ki * 12 + $n) % count($namaL)] : $namaP[($ki * 12 + $n) % count($namaP)];
            $nama = $depan . ' ' . ($laki ? $namaL[($n + 3) % count($namaL)] : $namaP[($n + 5) % count($namaP)]);
            $baris[] = [
                'nis' => (string) (2026000 + $no), 'nisn' => (string) (7000000000 + $no),
                'nama' => $nama, 'jenis_kelamin' => $laki ? 'L' : 'P', 'tempat_lahir' => 'Mootilango',
                'tanggal_lahir' => sprintf('%04d-%02d-%02d', 2013 - $ki, (($n % 12) + 1), (($n % 27) + 1)),
                'kelas_id' => $kid, 'nama_ayah' => $ayah[$n % count($ayah)], 'nama_ibu' => $ibu[$n % count($ibu)],
                'wali' => '', 'telepon' => '0852' . (10000000 + $no), 'alamat' => 'Dusun Gandaria, Desa Sidomukti',
                'tanggal_masuk' => date('Y') . '-07-15', 'tahun_lulus' => '', 'status' => 'aktif', 'foto' => '',
                'dibuat' => date('Y-m-d H:i:s'),
            ];
            $no++;
        }
    }
    $th = (int) date('Y');
    for ($n = 0; $n < 24; $n++) {
        $laki = ($n % 2 === 0);
        $depan = $laki ? $namaL[$n % count($namaL)] : $namaP[$n % count($namaP)];
        $baris[] = [
            'nis' => (string) (2023000 + $n), 'nisn' => (string) (7100000000 + $n),
            'nama' => $depan . ' ' . ($laki ? $namaL[($n + 7) % count($namaL)] : $namaP[($n + 9) % count($namaP)]),
            'jenis_kelamin' => $laki ? 'L' : 'P', 'tempat_lahir' => 'Gorontalo',
            'tanggal_lahir' => sprintf('%04d-%02d-%02d', 2007 + ($n % 2), (($n % 12) + 1), (($n % 27) + 1)),
            'kelas_id' => $kelasId[2] ?? 0, 'nama_ayah' => $ayah[($n + 2) % count($ayah)], 'nama_ibu' => $ibu[($n + 4) % count($ibu)],
            'wali' => '', 'telepon' => '0853' . (20000000 + $n), 'alamat' => 'Desa Sidomukti',
            'tanggal_masuk' => ($th - 3) . '-07-15', 'tahun_lulus' => (string) ($th - (1 + ($n % 3))),
            'status' => 'lulus', 'foto' => '', 'dibuat' => date('Y-m-d H:i:s'),
        ];
    }
    db_masukkan($pdo, 'siswa', $baris);
}

function db_pdo_ppdb(PDO $pdo): void
{
    $ta = date('Y') . '/' . (date('Y') + 1);
    db_masukkan($pdo, 'ppdb_gelombang', [
        ['nama' => 'Gelombang 1', 'tahun_ajaran' => $ta, 'mulai' => date('Y-m-01', strtotime('-1 month')),
         'selesai' => date('Y-m-d', strtotime('+45 day')), 'kuota' => 60, 'jalur' => 'Reguler',
         'keterangan' => 'Pendaftaran gelombang pertama dengan kuota terbatas.', 'aktif' => 1, 'urutan' => 10],
        ['nama' => 'Gelombang 2', 'tahun_ajaran' => $ta, 'mulai' => date('Y-m-d', strtotime('+46 day')),
         'selesai' => date('Y-m-d', strtotime('+90 day')), 'kuota' => 40, 'jalur' => 'Reguler',
         'keterangan' => 'Dibuka apabila kuota gelombang pertama belum terpenuhi.', 'aktif' => 0, 'urutan' => 20],
    ]);
    $gel = (int) $pdo->query('SELECT id FROM ppdb_gelombang ORDER BY urutan LIMIT 1')->fetchColumn();

    $namaL = ['Ahmad Rifai', 'Muhammad Iqbal', 'Abdul Azis', 'Hasan Basri', 'Fajar Nugraha', 'Ilham Saputra'];
    $namaP = ['Siti Nurhaliza', 'Aisyah Putri', 'Fatimah Zahra', 'Rahma Wati', 'Salma Kamila', 'Zainab Azzahra'];
    $status = ['baru', 'verifikasi', 'diterima', 'diterima', 'cadangan', 'baru'];
    $asal = ['MTs. Sirojuth Tholibiin Sidomukti', 'SMP Negeri 1 Mootilango', 'SD Negeri Sidomukti', 'MI Alkhairaat', 'SMP Negeri 2 Mootilango', 'SD Negeri Gandaria'];
    $baris = [];
    for ($n = 0; $n < 6; $n++) {
        $baris[] = [
            'nomor' => PPDB_PREFIX . '-' . date('Y') . '-' . str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT),
            'gelombang_id' => $gel, 'nama' => $n % 2 === 0 ? $namaL[$n] : $namaP[$n],
            'nisn' => (string) (6000000000 + $n), 'jenis_kelamin' => $n % 2 === 0 ? 'L' : 'P',
            'tempat_lahir' => 'Mootilango', 'tanggal_lahir' => sprintf('2010-%02d-%02d', ($n % 12) + 1, ($n % 27) + 1),
            'asal_sekolah' => $asal[$n], 'nama_ayah' => 'Sudirman ' . $n, 'nama_ibu' => 'Halimah ' . $n,
            'wali' => '', 'telepon' => '0852' . (30000000 + $n), 'email' => '',
            'alamat' => 'Desa Sidomukti, Kecamatan Mootilango', 'jalur' => 'Reguler', 'catatan_pendaftar' => '',
            'berkas' => '{}', 'status' => $status[$n], 'nilai_seleksi' => (string) (85 - $n),
            'catatan_panitia' => '', 'tanggal_daftar' => date('Y-m-d H:i:s', strtotime('-' . ($n + 1) . ' day')),
            'diubah' => '', 'ip' => '127.0.0.1',
        ];
    }
    db_masukkan($pdo, 'ppdb_pendaftar', $baris);
}

// =====================================================================================
// v28 — PERIODE (TAHUN AJARAN + SEMESTER) & BERKAS PERANGKAT PER SEMESTER
// =====================================================================================

/**
 * Periode BAWAAN menurut data yang ada: baris `periode` yang bertanda `bawaan`, kalau tidak ada →
 * tahun ajaran sekolah (pengaturan) + semester pertama, kalau itu juga kosong → Ganjil.
 *
 * @return array{0:string,1:string} [tahun ajaran, semester]
 */
function db_periode_bawaan(PDO $pdo): array
{
    return db_periode_ta_sem(
        "SELECT ta, semester FROM periode WHERE bawaan = 1 ORDER BY urutan, id LIMIT 1",
        "SELECT ta, semester FROM periode WHERE aktif = 1 ORDER BY urutan, id LIMIT 1",
        $pdo
    );
}

/** Penolong `db_periode_bawaan()`: coba dua kueri berurutan, lalu jatuh ke tahun ajaran sekolah. */
function db_periode_ta_sem(string $sqlBawaan, string $sqlAktif, PDO $pdo): array
{
    try {
        foreach ([$sqlBawaan, $sqlAktif] as $sql) {
            $r = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                return [(string) $r['ta'], (string) $r['semester']];
            }
        }
        $ta = (string) $pdo->query("SELECT tahun_ajaran FROM sekolah ORDER BY id LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {
        $ta = '';
    }
    $ta = trim($ta);
    return [$ta, (string) (PERANGKAT_SEMESTER[0] ?? 'Ganjil')];
}

/**
 * v28 — ISI AWAL tabel `periode` (dijalankan SEKALI, hanya bila tabelnya masih kosong).
 * Periode dibuat dari **tahun ajaran sekolah** (mis. "2026/2027") dengan dua semester
 * (Ganjil & Genap); semester pertama ditandai `bawaan` sehingga halaman publik langsung menampilkan
 * berkas yang berlaku. Bila tahun ajaran sekolah kosong, dipakai tahun berjalan.
 */
function db_periode_awal(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM periode')->fetchColumn() > 0) {
        return;   // sudah pernah diisi (atau sudah disunting Super Admin) — jangan ditimpa
    }
    try {
        $ta = trim((string) $pdo->query("SELECT tahun_ajaran FROM sekolah ORDER BY id LIMIT 1")->fetchColumn());
    } catch (Throwable $e) {
        $ta = '';
    }
    if ($ta === '') {
        $tahun = (int) date('Y');
        $ta = $tahun . '/' . ($tahun + 1);
    }
    // Hanya tahun ajaran dua tahun BERURUTAN yang dipakai (kalau tidak, pakai tahun berjalan).
    if (!preg_match('#^(\d{4})\s*/\s*(\d{4})$#', $ta, $m) || (int) $m[2] !== (int) $m[1] + 1) {
        $ta = (string) date('Y') . '/' . (string) ((int) date('Y') + 1);
    }
    $kini = date('Y-m-d H:i:s');
    $st = $pdo->prepare('INSERT OR IGNORE INTO periode (ta, semester, urutan, bawaan, aktif, dibuat, diubah)
                         VALUES (?, ?, ?, ?, 1, ?, ?)');
    $i = 0;
    foreach ((array) PERANGKAT_SEMESTER as $sem) {
        $st->execute([$ta, (string) $sem, $i * 10, $i === 0 ? 1 : 0, $kini, $kini]);
        $i++;
    }
    error_log('v28 periode: periode awal dibuat untuk T.A. ' . $ta . '.');
}

/**
 * v28 — BERKAS PERANGKAT MENJADI PER SEMESTER.
 *
 * Langkahnya (idempoten):
 *  1. Kolom `ta`/`semester` ditambahkan bila belum ada (basis data yang penandanya mendahului isi).
 *  2. Tabel `perangkat_kelas` **DIBANGUN ULANG** hanya bila kunci uniknya masih
 *     `(perangkat_id, kelas)` — kunci barunya `(perangkat_id, kelas, ta, semester)` sehingga satu
 *     kelas boleh punya berkas terpisah tiap semester. Seluruh baris disalin apa adanya.
 *     (Rebuild memakai `CREATE TABLE … AS` pola aman: tabel baru → salin → DROP → RENAME, sehingga
 *     definisi FOREIGN KEY tabel lain tidak ikut ditulis ulang — lihat catatan di aplikasi Supervisi.)
 *  3. Baris `perangkat_kelas` yang `ta`/`semester`-nya masih KOSONG diberi **periode bawaan** supaya
 *     berkas yang sudah ada tetap tampil (data lama tidak hilang).
 */
function db_perangkat_semester(PDO $pdo): void
{
    $dimiliki = [];
    foreach ($pdo->query('PRAGMA table_info(perangkat_kelas)') as $r) {
        $dimiliki[] = strtolower((string) $r['name']);
    }
    if (!$dimiliki) {
        return;   // tabelnya belum ada (akan dibuat oleh db_tabel pada migrasi berikutnya)
    }
    db_add_column($pdo, 'perangkat_kelas', 'ta', "TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo, 'perangkat_kelas', 'semester', "TEXT NOT NULL DEFAULT ''");

    // (2) Kunci unik lama → bangun ulang tabelnya.
    $sqlTabel = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'perangkat_kelas'")->fetchColumn();
    if ($sqlTabel !== '' && stripos($sqlTabel, 'UNIQUE (perangkat_id, kelas, ta, semester)') === false) {
        $pdo->exec('CREATE TABLE perangkat_kelas_v28 (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            perangkat_id INTEGER NOT NULL REFERENCES perangkat(id) ON DELETE CASCADE,
            guru_id INTEGER NOT NULL DEFAULT 0,
            kunci TEXT NOT NULL DEFAULT \'\',
            kelas TEXT NOT NULL DEFAULT \'\',
            ta TEXT NOT NULL DEFAULT \'\',
            semester TEXT NOT NULL DEFAULT \'\',
            urutan INTEGER NOT NULL DEFAULT 0,
            cp TEXT DEFAULT \'\', cp_nama TEXT DEFAULT \'\',
            atp TEXT DEFAULT \'\', atp_nama TEXT DEFAULT \'\',
            rpp TEXT DEFAULT \'\', rpp_nama TEXT DEFAULT \'\',
            dibuat TEXT,
            diubah TEXT,
            UNIQUE (perangkat_id, kelas, ta, semester)
        )');
        $pdo->exec('INSERT INTO perangkat_kelas_v28
            (id, perangkat_id, guru_id, kunci, kelas, ta, semester, urutan,
             cp, cp_nama, atp, atp_nama, rpp, rpp_nama, dibuat, diubah)
            SELECT id, perangkat_id, guru_id, kunci, kelas, ta, semester, urutan,
                   cp, cp_nama, atp, atp_nama, rpp, rpp_nama, dibuat, diubah
            FROM perangkat_kelas');
        $pdo->exec('DROP TABLE perangkat_kelas');
        $pdo->exec('ALTER TABLE perangkat_kelas_v28 RENAME TO perangkat_kelas');
        foreach ([
            'CREATE INDEX IF NOT EXISTS idx_perangkat_kelas ON perangkat_kelas (perangkat_id, urutan, kelas)',
            'CREATE INDEX IF NOT EXISTS idx_perangkat_kelas_kunci ON perangkat_kelas (guru_id, kunci, kelas)',
            'CREATE INDEX IF NOT EXISTS idx_perangkat_kelas_periode ON perangkat_kelas (ta, semester, perangkat_id)',
        ] as $idx) {
            $pdo->exec($idx);
        }
        error_log('v28 perangkat_kelas: tabel dibangun ulang agar berkas terpisah per semester.');
    }

    // Indeks pencarian per PERIODE — dibuat di sini karena butuh kolom `ta`/`semester` yang baru
    // saja ditambahkan di atas (lihat catatan pada daftar indeks di db_tabel).
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_perangkat_kelas_periode ON perangkat_kelas (ta, semester, perangkat_id)');

    // (3) Baris tanpa periode → periode bawaan (data lama tetap tampil).
    [$ta, $sem] = db_periode_bawaan($pdo);
    if ($ta !== '' || $sem !== '') {
        $st = $pdo->prepare("UPDATE perangkat_kelas SET ta = ?, semester = ? WHERE TRIM(IFNULL(ta,'')) = '' AND TRIM(IFNULL(semester,'')) = ''");
        $st->execute([$ta, $sem]);
    }
}
