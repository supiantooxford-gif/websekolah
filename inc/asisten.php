<?php
/**
 * ASISTEN AI WEBSITE — mesin jawab BERBASIS ATURAN.
 *
 * Tidak memakai layanan AI luar dan tidak memerlukan kunci API: jawaban disusun dari
 *   1. **FAQ** yang diisi admin di panel (`faq` — pertanyaan/jawaban/kata kunci), dan
 *   2. **data website** (identitas sekolah, visi/misi/tujuan, kontak, guru/siswa/alumni, prestasi,
 *      fasilitas, berita, agenda, galeri album, struktur organisasi, menu halaman, dan PPDB).
 *
 * Pencocokan: teks pengunjung dinormalkan lalu diberi skor terhadap kata kunci tiap topik & FAQ.
 * Topik dengan skor tertinggi dipakai; bila tidak ada yang cocok, diberikan jawaban pengarah
 * (kontak / PPDB / cari).
 *
 * Riwayat obrolan TIDAK disimpan di basis data (sesuai keputusan pemilik). Untuk halaman
 * `asisten.php` (tanpa JavaScript) riwayat hanya disimpan sementara di sesi pengunjung.
 */
declare(strict_types=1);

/** Batas panjang satu pertanyaan pengunjung (karakter). */
const ASISTEN_PESAN_MAKS = 300;

/** Batas jumlah pertanyaan per sesi dalam jendela waktu (anti penyalahgunaan). */
const ASISTEN_MAKS_TANYA = 40;
const ASISTEN_MENIT_TANYA = 10;

/** Jumlah pesan yang diingat pada halaman asisten tanpa JavaScript (sesi saja). */
const ASISTEN_RIWAYAT_MAKS = 12;

// =====================================================================================
// FOTO / IKON ASISTEN
// =====================================================================================
// Ikon tombol obrolan mengambang, avatar kepala kotak obrolan, dan avatar pada tiap balasan
// memakai SATU sumber: foto asisten yang diunggah admin (menu **FAQ Asisten** → kartu
// "Foto Asisten", disimpan sebagai URL media proxy pada `pengaturan.asisten_foto`).
// Bila admin belum mengunggahnya, dipakai ilustrasi bawaan `assets/asisten.svg`.
// Jangan menulis sendiri URL/ikon asisten di halaman lain — pakai penolong di bawah.

/** Foto asisten (URL media proxy) hasil unggahan admin; kosong bila belum ada. */
function asisten_foto(): string
{
    return trim(pengaturan('asisten_foto', ''));
}

/** Sumber gambar avatar asisten: foto unggahan admin, atau ilustrasi bawaan. */
function asisten_foto_src(): string
{
    $foto = asisten_foto();
    return $foto !== '' ? $foto : 'assets/asisten.svg';
}

/**
 * Avatar asisten siap cetak (foto bila ada, ilustrasi bawaan bila belum).
 * $kelas = kelas CSS pembungkus (mis. `asisten-ikon`, `asisten-ava`, `tombol-asisten-foto`).
 */
function asisten_ava(string $kelas = 'asisten-ikon'): string
{
    return '<span class="' . e($kelas) . '"><img src="' . e(asisten_foto_src())
        . '" alt="" loading="lazy" decoding="async"></span>';
}

// =====================================================================================
// PENCARIAN TEKS
// =====================================================================================

/** Normalkan teks: huruf kecil, tanpa tanda baca, spasi dirapatkan. */
function asisten_normal(string $teks): string
{
    $t = strtolower(trim($teks));
    $t = (string) preg_replace('#[^a-z0-9]+#', ' ', $t);
    return trim((string) preg_replace('#\s+#', ' ', $t));
}

/** Pecah teks menjadi kata (>= 3 huruf supaya kata umum seperti "di"/"ke" tidak ikut). */
function asisten_kata(string $teks): array
{
    $keluar = [];
    foreach (explode(' ', asisten_normal($teks)) as $k) {
        if (strlen($k) >= 3) {
            $keluar[$k] = true;
        }
    }
    return array_keys($keluar);
}

/** Potongan teks aman untuk ditampilkan pada balasan obrolan. */
function asisten_potong(string $teks, int $maks = 320): string
{
    $t = trim((string) preg_replace('#\s+#', ' ', strip_tags($teks)));
    if (function_exists('mb_strlen')) {
        return mb_strlen($t, 'UTF-8') <= $maks ? $t : rtrim(mb_substr($t, 0, $maks, 'UTF-8'), ' ,.') . '…';
    }
    return strlen($t) <= $maks ? $t : rtrim(substr($t, 0, $maks), ' ,.') . '…';
}

/** Daftar nama dipisah koma + "dan" terakhir (mis. "A, B, dan C"). */
function asisten_daftar(array $nama, int $maks = 8): string
{
    $nama = array_values(array_filter(array_map('trim', $nama), static fn (string $n): bool => $n !== ''));
    if (!$nama) {
        return '';
    }
    $lebih = count($nama) - $maks;
    $nama = array_slice($nama, 0, $maks);
    if (count($nama) === 1) {
        return $nama[0];
    }
    $akhir = array_pop($nama);
    return implode(', ', $nama) . ' dan ' . $akhir . ($lebih > 0 ? ' (dan ' . $lebih . ' lainnya)' : '');
}

// =====================================================================================
// DATA PENDUKUNG JAWABAN (dibaca sekali per permintaan)
// =====================================================================================

/** Seluruh FAQ aktif (diisi admin). */
function asisten_faq(bool $hanyaAktif = true): array
{
    return all('SELECT * FROM faq' . ($hanyaAktif ? ' WHERE aktif = 1' : '') . ' ORDER BY urutan, id');
}

function asisten_faq_jumlah(bool $hanyaAktif = false): int
{
    return jumlah('SELECT COUNT(*) FROM faq' . ($hanyaAktif ? ' WHERE aktif = 1' : ''));
}

function asisten_faq_by_id(int $id): ?array
{
    return one('SELECT * FROM faq WHERE id = ?', [$id]);
}

function asisten_faq_urutan_baru(): int
{
    return ((int) jumlah('SELECT COALESCE(MAX(urutan), 0) FROM faq')) + 10;
}

/** Jumlah data yang sering ditanyakan (siswa aktif, guru, alumni, berita, album). */
function asisten_angka(): array
{
    static $angka = null;
    if ($angka !== null) {
        return $angka;
    }
    $angka = [
        'guru'    => jumlah("SELECT COUNT(*) FROM guru WHERE status = 'aktif'"),
        'siswa'   => jumlah("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'"),
        'alumni'  => alumni_jumlah_gabungan(),
        'berita'  => jumlah('SELECT COUNT(*) FROM berita WHERE terbit = 1'),
        'agenda'  => jumlah('SELECT COUNT(*) FROM agenda'),
        'album'   => jumlah('SELECT COUNT(*) FROM galeri_album WHERE aktif = 1'),
        'foto'    => jumlah('SELECT COUNT(*) FROM galeri_foto'),
        'video'   => video_publik_jumlah(),
        'prestasi' => jumlah('SELECT COUNT(*) FROM prestasi WHERE aktif = 1'),
        'fasilitas' => jumlah('SELECT COUNT(*) FROM fasilitas WHERE aktif = 1'),
        'halaman' => jumlah('SELECT COUNT(*) FROM menu WHERE aktif = 1'),
        'poll'    => jumlah('SELECT COUNT(*) FROM poll WHERE aktif = 1'),
    ];
    return $angka;
}

/** Daftar menu (label + tautan) untuk jawaban "ada menu apa saja". */
function asisten_menu(): array
{
    $keluar = [];
    foreach (menu_semua() as $m) {
        if (empty($m['tampil_nav'])) {
            continue;
        }
        $keluar[] = $m;
    }
    return $keluar;
}

/** Sekolah (satu baris) untuk jawaban identitas. */
function asisten_sekolah(): array
{
    return sekolah();
}

// =====================================================================================
// TOPIK JAWABAN OTOMATIS
// =====================================================================================

/**
 * Daftar topik jawaban otomatis.
 *
 * Setiap topik: `kunci` (kata kunci pemicu, bobot lebih besar untuk frasa), `bobot` (nilai dasar),
 * dan `jawab` (callable yang mengembalikan teks jawaban / null bila datanya belum ada).
 *
 * @return array<int,array{kode:string,kunci:array<int,string>,bobot:int,jawab:callable}>
 */
function asisten_topik(): array
{
    $s = asisten_sekolah();
    $angka = asisten_angka();
    $namaSekolah = (string) $s['nama'];

    $topik = [];
    $tambah = static function (string $kode, array $kunci, callable $jawab, int $bobot = 10) use (&$topik): void {
        $topik[] = ['kode' => $kode, 'kunci' => $kunci, 'bobot' => $bobot, 'jawab' => $jawab];
    };

    // ---------- sapaan & penutup ----------
    $tambah('sapaan', ['hai', 'halo', 'hello', 'assalamualaikum', 'salam', 'selamat pagi', 'selamat siang',
        'selamat sore', 'selamat malam', 'pagi', 'siang', 'sore', 'malam'], static function () use ($namaSekolah): string {
        return "Wa'alaikumussalam warahmatullahi wabarakatuh. Selamat datang di layanan informasi "
            . $namaSekolah . ". Saya asisten website ini — silakan bertanya tentang profil, PPDB, "
            . "data guru & siswa, prestasi, galeri, agenda, atau kontak madrasah.";
    }, 6);
    $tambah('terima kasih', ['terima kasih', 'makasih', 'thanks', 'thank you', 'syukron'], static function (): string {
        return 'Alhamdulillah, senang bisa membantu. Bila ada pertanyaan lain, silakan tanyakan kembali.';
    }, 6);
    $tambah('siapa kamu', ['siapa kamu', 'kamu siapa', 'apa ini', 'siapa ini', 'asisten apa'], static function () use ($namaSekolah): string {
        return 'Saya asisten otomatis (AI) website ' . $namaSekolah . '. Saya menjawab pertanyaan '
            . 'seputar informasi madrasah berdasarkan data yang ada di website ini — profil, PPDB, guru & siswa, '
            . 'prestasi, galeri, agenda, dan kontak.';
    }, 14);

    // ---------- identitas & profil ----------
    $tambah('identitas', ['profil', 'identitas', 'tentang', 'npsn', 'nsm', 'akreditasi', 'status madrasah',
        'tahun berdiri', 'penyelenggara', 'npwp', 'sejarah'], function (): string {
        $s = asisten_sekolah();
        $bagian = [];
        $bagian[] = 'Berikut identitas ' . $s['nama'] . ':';
        if (profil_nilai('npsn') !== '' || profil_nilai('nsm') !== '') {
            $bagian[] = '• NPSN/NSM: ' . trim(profil_nilai('npsn') . ' / ' . profil_nilai('nsm'), ' /');
        }
        if (profil_nilai('akreditasi') !== '') {
            $bagian[] = '• Akreditasi: ' . profil_nilai('akreditasi')
                . (profil_nilai('akreditasi_tgl') !== '' ? ' (' . profil_nilai('akreditasi_tgl') . ')' : '');
        }
        if (profil_nilai('tahun_berdiri') !== '') {
            $bagian[] = '• Berdiri: ' . profil_nilai('tahun_berdiri');
        }
        if (profil_nilai('status_madrasah') !== '') {
            $bagian[] = '• Status: ' . profil_nilai('status_madrasah');
        }
        if (profil_nilai('penyelenggara') !== '') {
            $bagian[] = '• Penyelenggara: ' . profil_nilai('penyelenggara');
        }
        $wilayah = implode(', ', array_filter([
            profil_nilai('desa') !== '' ? 'Desa/Kelurahan ' . profil_nilai('desa') : '',
            profil_nilai('kecamatan') !== '' ? 'Kec. ' . profil_nilai('kecamatan') : '',
            profil_nilai('kabupaten') !== '' ? 'Kab. ' . profil_nilai('kabupaten') : '',
            profil_nilai('provinsi') !== '' ? 'Prov. ' . profil_nilai('provinsi') : '',
        ]));
        if ($wilayah !== '' || profil_nilai('alamat') !== '') {
            $bagian[] = '• Alamat: ' . trim(profil_nilai('alamat') . ($wilayah !== '' ? ', ' . $wilayah : ''), ', ');
        }
        $bagian[] = 'Informasi lengkapnya ada pada halaman Profil Lengkap: ' . asisten_tautan('Profil Lengkap');
        return implode("\n", $bagian);
    }, 12);

    $tambah('visi', ['visi'], function (): ?string {
        $v = profil_nilai('visi');
        if ($v === '') {
            return null;
        }
        return 'Visi ' . asisten_sekolah()['nama'] . ': ' . $v
            . "\nSelengkapnya pada halaman Profil Lengkap: " . asisten_tautan('Profil Lengkap');
    }, 18);
    $tambah('misi', ['misi'], function (): ?string {
        $m = profil_butir((string) (asisten_sekolah()['misi'] ?? ''));
        if (!$m) {
            return null;
        }
        $baris = [];
        foreach (array_slice($m, 0, 8) as $i => $b) {
            $baris[] = ($i + 1) . '. ' . asisten_potong($b, 180);
        }
        return "Misi " . asisten_sekolah()['nama'] . ":\n" . implode("\n", $baris);
    }, 18);
    $tambah('tujuan', ['tujuan'], function (): ?string {
        $t = profil_butir((string) (asisten_sekolah()['tujuan'] ?? ''));
        if (!$t) {
            return null;
        }
        $baris = [];
        foreach (array_slice($t, 0, 8) as $i => $b) {
            $baris[] = ($i + 1) . '. ' . asisten_potong($b, 180);
        }
        return "Tujuan " . asisten_sekolah()['nama'] . ":\n" . implode("\n", $baris);
    }, 18);

    // ---------- alamat, kontak, jam ----------
    $tambah('alamat', ['alamat', 'lokasi', 'dimana', 'di mana', 'letak', 'peta', 'kecamatan', 'kabupaten',
        'provinsi', 'desa', 'kode pos', 'maps'], function (): string {
        $s = asisten_sekolah();
        $bagian = ['Alamat ' . $s['nama'] . ':'];
        if (profil_nilai('alamat') !== '') {
            $bagian[] = '• ' . profil_nilai('alamat');
        }
        if (profil_nilai('desa') !== '') {
            $bagian[] = '• Desa/Kelurahan ' . profil_nilai('desa')
                . (profil_nilai('kode_pos') !== '' ? ', Kode Pos ' . profil_nilai('kode_pos') : '');
        }
        if (profil_nilai('kecamatan') !== '') {
            $bagian[] = '• Kecamatan ' . profil_nilai('kecamatan');
        }
        if (profil_nilai('kabupaten') !== '') {
            $bagian[] = '• Kabupaten ' . profil_nilai('kabupaten');
        }
        if (profil_nilai('provinsi') !== '') {
            $bagian[] = '• Provinsi ' . profil_nilai('provinsi');
        }
        $peta = trim((string) ($s['maps'] ?? ''));
        if ($peta !== '') {
            $bagian[] = 'Peta lokasi: ' . $peta;
        }
        return implode("\n", $bagian);
    }, 14);

    $tambah('kontak', ['kontak', 'telepon', 'telpon', 'nomor', 'hp', 'whatsapp', 'wa', 'email', 'surel',
        'situs', 'website', 'laman', 'hubungi', 'media sosial', 'facebook', 'instagram', 'youtube', 'tiktok',
        // frasa (dinilai lebih kuat) supaya "alamat email"/"nomor telepon" tidak jatuh ke topik ALAMAT
        'alamat email', 'alamat surel', 'nomor telepon', 'nomor telpon', 'nomor hp', 'nomor wa',
        'nomor whatsapp', 'kontak madrasah', 'kontak sekolah'],
        function (): string {
        $s = asisten_sekolah();
        $bagian = ['Kontak ' . $s['nama'] . ':'];
        if (profil_nilai('telepon') !== '') {
            $bagian[] = '• Telepon/HP: ' . profil_nilai('telepon');
        }
        $wa = trim((string) ($s['whatsapp'] ?? ''));
        if ($wa !== '' && $wa !== profil_nilai('telepon')) {
            $bagian[] = '• WhatsApp: ' . $wa;
        }
        if (profil_nilai('email') !== '') {
            $bagian[] = '• Email: ' . profil_nilai('email');
        }
        if (profil_nilai('website') !== '') {
            $bagian[] = '• Situs: ' . profil_nilai('website');
        }
        $sosial = all('SELECT nama FROM sosial WHERE aktif = 1 ORDER BY urutan, id');
        if ($sosial) {
            $bagian[] = '• Media sosial: ' . asisten_daftar(array_column($sosial, 'nama'), 6)
                . ' (tautan di bagian "Galeri & Media Sosial" beranda)';
        }
        $bagian[] = 'Anda juga dapat mengirim pesan lewat halaman Kontak: ' . asisten_tautan('Kontak');
        return implode("\n", $bagian);
    }, 12);

    $tambah('jam', ['jam', 'buka jam', 'jam buka', 'jam operasional', 'jam layanan', 'jam kerja',
        'jam belajar', 'waktu belajar', 'kapan buka'], function (): ?string {
        $jam = profil_nilai('jam_operasional');
        if ($jam === '') {
            return null;
        }
        return 'Jam operasional ' . asisten_sekolah()['nama'] . ': ' . $jam . '.';
    }, 18);

    // ---------- jumlah data ----------
    $tambah('jumlah', ['jumlah guru', 'berapa guru', 'jumlah siswa', 'berapa siswa', 'jumlah murid',
        'jumlah alumni', 'berapa alumni', 'statistik', 'jumlah pendaftar'], function () use ($angka): string {
        return 'Data ' . asisten_sekolah()['nama'] . ' saat ini: '
            . $angka['guru'] . ' guru & tenaga kependidikan, '
            . $angka['siswa'] . ' siswa aktif, dan '
            . $angka['alumni'] . ' alumni terdata. '
            . 'Rinciannya dapat dilihat pada halaman Data Siswa/Data Alumni.';
    }, 14);

    // ---------- guru & siswa ----------
    $tambah('guru', ['guru', 'tenaga pendidik', 'tendik', 'kepala sekolah', 'kepala madrasah', 'daftar guru',
        'pengajar', 'wali kelas'], function () use ($angka): string {
        $s = asisten_sekolah();
        $bagian = ['Di ' . $s['nama'] . ' tercatat ' . $angka['guru'] . ' guru & tenaga kependidikan aktif.'];
        if (profil_nilai('kepala') !== '') {
            $bagian[] = 'Kepala madrasah: ' . profil_nilai('kepala')
                . (profil_nilai('nip_kepala') !== '' ? ' (NIP ' . profil_nilai('nip_kepala') . ')' : '');
        }
        $nama = array_column(all("SELECT nama FROM guru WHERE status = 'aktif' ORDER BY urutan, nama LIMIT 10"), 'nama');
        if ($nama) {
            $bagian[] = 'Sebagian tenaga pendidik: ' . asisten_daftar($nama, 8) . '.';
        }
        $bagian[] = 'Daftar lengkap + foto ada pada halaman Tenaga Pendidik: ' . asisten_tautan('Tenaga Pendidik');
        return implode("\n", $bagian);
    }, 12);

    $tambah('siswa', ['siswa', 'murid', 'peserta didik', 'daftar siswa', 'data siswa', 'kelas'], function () use ($angka): string {
        $kelas = array_column(all('SELECT nama FROM kelas WHERE aktif = 1 ORDER BY urutan, nama'), 'nama');
        $bagian = ['Saat ini ada ' . $angka['siswa'] . ' siswa aktif di ' . asisten_sekolah()['nama'] . '.'];
        if ($kelas) {
            $bagian[] = 'Kelas yang tersedia: ' . asisten_daftar($kelas, 10) . '.';
        }
        $bagian[] = 'Data siswa (beserta penyaring kelas) ada di halaman Data Siswa: ' . asisten_tautan('Data Siswa');
        return implode("\n", $bagian);
    }, 12);

    $tambah('alumni', ['alumni', 'lulusan', 'tamatan', 'tahun keluar'], function () use ($angka): string {
        return 'Jumlah alumni yang terdata: ' . $angka['alumni'] . ' orang. '
            . 'Daftar alumni (tahun masuk, tahun keluar, dan keterangan setelah lulus) ada di halaman '
            . 'Data Alumni: ' . asisten_tautan('Data Alumni');
    }, 12);

    // ---------- prestasi, fasilitas ----------
    $tambah('prestasi', ['prestasi', 'juara', 'lomba', 'penghargaan', 'sertifikat', 'pelatihan',
        'prestasi siswa', 'prestasi madrasah', 'prestasi pendidik', 'data prestasi', 'daftar prestasi'],
        function () use ($angka): string {
        $bagian = ['Tercatat ' . $angka['prestasi'] . ' data prestasi & pelatihan.'];
        $daftar = array_column(all('SELECT nama FROM prestasi WHERE aktif = 1 ORDER BY urutan, id LIMIT 6'), 'nama');
        if ($daftar) {
            $bagian[] = 'Sebagian di antaranya: ' . asisten_daftar($daftar, 5) . '.';
        }
        $bagian[] = 'Rinciannya (tahun, tingkat, dan sertifikat) ada pada halaman Prestasi Siswa, '
            . 'Prestasi Madrasah, Prestasi Pendidik, dan Pelatihan Pendidik.';
        return implode("\n", $bagian);
    }, 12);

    $tambah('fasilitas', ['fasilitas', 'sarana', 'prasarana', 'ruang', 'laboratorium', 'lab', 'perpustakaan'], function () use ($angka): string {
        $daftar = array_column(all('SELECT nama FROM fasilitas WHERE aktif = 1 ORDER BY urutan, id LIMIT 10'), 'nama');
        $bagian = ['Tersedia ' . $angka['fasilitas'] . ' fasilitas yang tercatat pada website.'];
        if ($daftar) {
            $bagian[] = 'Di antaranya: ' . asisten_daftar($daftar, 8) . '.';
        }
        $bagian[] = 'Selengkapnya (beserta foto) pada halaman Fasilitas: ' . asisten_tautan('Fasilitas');
        return implode("\n", $bagian);
    }, 12);

    // ---------- berita, agenda, galeri ----------
    $tambah('berita', ['berita', 'kabar', 'informasi terbaru', 'pengumuman terbaru', 'kegiatan terbaru'], function () use ($angka): string {
        $daftar = all('SELECT judul, tanggal FROM berita WHERE terbit = 1 ORDER BY tanggal DESC, id DESC LIMIT 4');
        $bagian = ['Ada ' . $angka['berita'] . ' berita yang sudah terbit.'];
        foreach ($daftar as $b) {
            $bagian[] = '• ' . asisten_potong((string) $b['judul'], 90)
                . (trim((string) $b['tanggal']) !== '' ? ' (' . tanggal_id((string) $b['tanggal']) . ')' : '');
        }
        $bagian[] = 'Selengkapnya pada halaman Berita: ' . asisten_tautan('Berita');
        return implode("\n", $bagian);
    }, 12);

    $tambah('agenda', ['agenda', 'jadwal kegiatan', 'acara', 'kegiatan mendatang'], function () use ($angka): string {
        $daftar = agenda_terdekat(4);
        $bagian = ['Tercatat ' . $angka['agenda'] . ' agenda kegiatan pada website.'];
        foreach ($daftar as $a) {
            $bagian[] = '• ' . asisten_potong((string) $a['judul'], 80)
                . (trim((string) $a['tanggal']) !== '' ? ' — ' . tanggal_id((string) $a['tanggal']) : '')
                . (trim((string) $a['tempat']) !== '' ? ' (' . asisten_potong((string) $a['tempat'], 40) . ')' : '');
        }
        $bagian[] = 'Selengkapnya pada halaman Agenda: ' . asisten_tautan('Agenda');
        return implode("\n", $bagian);
    }, 12);

    $tambah('galeri', ['galeri', 'foto', 'album', 'video', 'dokumentasi', 'youtube'], function () use ($angka): string {
        $daftar = array_map(static fn (array $a): string => album_label($a), album_publik());
        $bagian = ['Galeri berisi ' . $angka['album'] . ' album foto, ' . $angka['foto'] . ' foto, dan '
            . $angka['video'] . ' video kegiatan.'];
        if ($daftar) {
            $bagian[] = 'Album yang tersedia: ' . asisten_daftar($daftar, 8) . '.';
        }
        $bagian[] = 'Pengunjung dapat melihat album per tahun lalu membuka isinya pada halaman Galeri: '
            . asisten_tautan('Galeri');
        return implode("\n", $bagian);
    }, 12);

    // ---------- struktur organisasi ----------
    $tambah('struktur', ['struktur', 'struktur organisasi', 'bagan', 'organisasi', 'wakamad', 'ktu',
        'tata usaha', 'bendahara', 'komite', 'kepala lab', 'kepala perpus', 'guru bk', 'dewan guru'], function (): string {
        $nama = [];
        foreach (struktur_slot_tampil() as $slot) {
            $nama[] = struktur_nama_beda((string) $slot['nama'], (string) $slot['label'])
                ? $slot['label'] . ' (' . $slot['nama'] . ')' : (string) $slot['label'];
        }
        $bagian = ['Susunan struktur organisasi ' . asisten_sekolah()['nama'] . ':'];
        foreach (array_slice($nama, 0, 12) as $n) {
            $bagian[] = '• ' . asisten_potong($n, 90);
        }
        $bagian[] = 'Bagan lengkapnya ada pada halaman Struktur Organisasi: ' . asisten_tautan('Struktur Organisasi');
        return implode("\n", $bagian);
    }, 16);

    // ---------- PPDB ----------
    $tambah('ppdb', ['ppdb', 'pendaftaran', 'penerimaan murid baru', 'pmb', 'daftar sekolah', 'mendaftar',
        'cara daftar', 'pendaftar', 'murid baru', 'siswa baru'], function (): string {
        $syarat = ppdb_butir(pengaturan('ppdb_syarat'));
        $bagian = [];
        if (ppdb_dibuka()) {
            $bagian[] = 'Pendaftaran murid baru sedang DIBUKA.';
        } else {
            $bagian[] = ppdb_tutup_pesan();
        }
        $gel = gelombang_aktif();
        if ($gel) {
            $bagian[] = 'Gelombang aktif: ' . $gel['nama']
                . (trim((string) $gel['tahun_ajaran']) !== '' ? ' (T.A. ' . $gel['tahun_ajaran'] . ')' : '');
        }
        if ($syarat) {
            $bagian[] = 'Persyaratan: ' . asisten_daftar(array_map(static fn (string $x): string => asisten_potong($x, 60), $syarat), 5) . '.';
        }
        $bagian[] = 'Formulir pendaftaran online ada di halaman PPDB: ' . asisten_tautan('PPDB Online');
        $bagian[] = 'Setelah mendaftar, status seleksi dapat diperiksa pada halaman Cek Status Pendaftaran.';
        return implode("\n", $bagian);
    }, 14);

    $tambah('ppdb_status', ['cek status', 'hasil seleksi', 'pengumuman seleksi', 'status pendaftaran',
        'nomor pendaftaran', 'cara cek'], function (): string {
        return 'Hasil seleksi dapat diperiksa pada halaman Cek Status Pendaftaran dengan memasukkan '
            . 'nomor pendaftaran (format PPDB-TAHUN-NOMOR) beserta NISN atau nomor HP yang dipakai saat mendaftar.'
            . (pengaturan('ppdb_umumkan') === '1' ? ' Pengumuman hasil seleksi juga sudah ditampilkan pada halaman itu.' : '')
            . "\nPanitia PPDB: " . pengaturan('ppdb_kontak', '-')
            . "\nHalaman: " . asisten_tautan('PPDB Online');
    }, 16);
    $tambah('ppdb_alur', ['alur pendaftaran', 'prosedur pendaftaran', 'tahapan pendaftaran', 'langkah pendaftaran'], function (): ?string {
        $alur = ppdb_butir(pengaturan('ppdb_alur'));
        if (!$alur) {
            return null;
        }
        $baris = [];
        foreach ($alur as $i => $a) {
            $baris[] = ($i + 1) . '. ' . asisten_potong($a, 120);
        }
        return "Alur pendaftaran:\n" . implode("\n", $baris);
    }, 18);
    $tambah('ppdb_jadwal', ['jadwal pendaftaran', 'kapan pendaftaran', 'batas pendaftaran', 'tanggal pendaftaran',
        'jadwal ppdb'], function (): ?string {
        $jadwal = ppdb_butir(pengaturan('ppdb_jadwal'));
        $gel = gelombang_aktif();
        $bagian = [];
        if ($gel) {
            $bagian[] = 'Gelombang ' . $gel['nama'] . ': '
                . (tanggal_id((string) $gel['mulai']) ?: '-') . ' s.d. ' . (tanggal_id((string) $gel['selesai']) ?: '-');
        }
        if ($jadwal) {
            $bagian[] = 'Jadwal: ' . asisten_daftar($jadwal, 6) . '.';
        }
        if (!$bagian) {
            return null;
        }
        $bagian[] = 'Halaman: ' . asisten_tautan('PPDB Online');
        return implode("\n", $bagian);
    }, 18);

    // ---------- menu & aplikasi ----------
    $tambah('menu', ['menu', 'navigasi', 'menu apa saja', 'daftar menu', 'daftar halaman',
        'halaman apa saja', 'daftar isi', 'tampilan website'], function () use ($angka): string {
        $menu = asisten_menu();
        $bagian = ['Website ini memuat ' . $angka['halaman'] . ' menu aktif, antara lain:'];
        foreach ($menu as $m) {
            $bagian[] = '• ' . $m['label'] . ' — ' . asisten_tautan_menu($m);
        }
        $bagian[] = 'Silakan tanyakan halaman tertentu bila ingin keterangannya.';
        return implode("\n", $bagian);
    }, 10);

    $tambah('layanan_digital', ['aplikasi', 'program digital', 'layanan digital', 'smart teacher',
        'e-guru', 'supervisi', 'cbt', 'e-perpus', 'e-bos'], function (): ?string {
        $program = all('SELECT judul, keterangan, tautan FROM program WHERE aktif = 1 ORDER BY urutan, id LIMIT 12');
        if (!$program) {
            return null;
        }
        $bagian = ['Program digitalisasi ' . asisten_sekolah()['nama'] . ':'];
        foreach ($program as $p) {
            $bagian[] = '• ' . asisten_potong((string) $p['judul'], 60)
                . ($p['tautan'] !== '' ? ' — ' . asisten_potong((string) $p['keterangan'], 110) : '');
        }
        $bagian[] = 'Selengkapnya pada bagian "Program Digitalisasi" di beranda.';
        return implode("\n", $bagian);
    }, 12);

    return $topik;
}

// =====================================================================================
// TAUTAN (disisipkan ke jawaban)
// =====================================================================================

/** Tautan relatif halaman dinamis berdasarkan label menunya (mis. "Data Siswa"). */
function asisten_url_label(string $label): string
{
    $label = asisten_normal($label);
    foreach (menu_semua() as $m) {
        if (asisten_normal((string) $m['label']) === $label) {
            return menu_url($m);
        }
    }
    return './';
}

/** Teks "<nama halaman> (<tautan>)" untuk jawaban. */
function asisten_tautan(string $label): string
{
    return $label . ' (' . asisten_url_label($label) . ')';
}

/** Teks alamat halaman untuk satu baris menu (mis. "halaman.php?slug=profil"). */
function asisten_tautan_menu(array $m): string
{
    return (string) preg_replace('#^https?://#', '', menu_url($m));
}

// =====================================================================================
// MESIN JAWAB
// =====================================================================================

/**
 * Skor satu teks pemicu terhadap pertanyaan pengunjung (0 = tidak cocok).
 *
 * ⚠️ JEBAKAN yang sudah menggigit: kata kunci PENDEK tidak boleh dicocokkan sebagai POTONGAN kata.
 * Kata kunci `wa` (WhatsApp) pernah membuat pertanyaan "berapa harga tiket pesa**wa**t ke jakarta?"
 * dijawab dengan informasi KONTAK. Sekarang: frasa (>= 2 kata) atau kata panjang (>= 4 huruf) boleh
 * dicocokkan sebagai potongan; kata pendek (mis. `wa`, `hp`) hanya bila berdiri sebagai KATA utuh.
 */
function asisten_skor(string $tanya, string $kunci): int
{
    $kunci = asisten_normal($kunci);
    if ($kunci === '') {
        return 0;
    }
    $frasa = str_contains($kunci, ' ');
    if (($frasa || strlen($kunci) >= 4) && str_contains($tanya, $kunci)) {
        // Frasa (banyak kata) dinilai lebih kuat daripada satu kata.
        return 40 + substr_count($kunci, ' ') * 6;
    }
    $kataKunci = asisten_kata($kunci);
    if (!$kataKunci) {
        return 0;
    }
    $kataTanya = asisten_kata($tanya);
    $cocok = count(array_intersect($kataKunci, $kataTanya));
    // Kata kunci BANYAK kata (mis. "berapa guru") memerlukan minimal 60% katanya cocok; kalau tidak,
    // kata umum seperti "berapa" saja sudah cukup memicu topik yang salah.
    $butuh = max(1, (int) ceil(count($kataKunci) * 0.6));
    if ($cocok < $butuh) {
        return 0;
    }
    return $cocok * 8;
}

/**
 * Jawaban untuk satu pertanyaan pengunjung.
 *
 * Urutan: **FAQ (diisi admin)** lebih dulu (bila skornya menang), lalu topik otomatis dari data
 * website; bila tidak ada yang cocok → jawaban pengarah (kontak & PPDB).
 *
 * @return array{kode:string,jawaban:string,sumber:string}
 */
function asisten_jawab(string $pertanyaan): array
{
    $tanya = asisten_normal($pertanyaan);
    if ($tanya === '') {
        return ['kode' => 'kosong', 'sumber' => 'sistem',
            'jawaban' => 'Silakan tuliskan pertanyaan Bapak/Ibu — misalnya "bagaimana cara mendaftar?" '
                . 'atau "berapa jumlah siswa?".'];
    }

    // ---------- 1) FAQ yang diisi admin ----------
    $terbaik = null;
    foreach (asisten_faq(true) as $f) {
        $skor = asisten_skor($tanya, (string) $f['pertanyaan']);
        foreach (explode(',', (string) $f['kata_kunci']) as $kk) {
            $skor = max($skor, asisten_skor($tanya, $kk));
        }
        if ($skor > 0 && ($terbaik === null || $skor > $terbaik['skor'])) {
            $terbaik = ['skor' => $skor, 'kode' => 'faq' . (int) $f['id'], 'sumber' => 'faq',
                'jawaban' => (string) $f['jawaban']];
        }
    }

    // ---------- 2) Topik otomatis dari data website ----------
    foreach (asisten_topik() as $t) {
        $skor = $t['bobot'];
        $nilai = 0;
        foreach ($t['kunci'] as $kunci) {
            $nilai = max($nilai, asisten_skor($tanya, $kunci));
        }
        if ($nilai === 0) {
            continue;
        }
        $skor += $nilai;
        if ($terbaik === null || $skor > $terbaik['skor']) {
            $terbaik = ['skor' => $skor, 'kode' => $t['kode'], 'sumber' => 'website', 'topik' => $t];
        }
    }

    if ($terbaik === null) {
        return ['kode' => 'tidak_dikenal', 'sumber' => 'sistem', 'jawaban' => asisten_jawaban_pengarah($pertanyaan)];
    }
    if ($terbaik['sumber'] === 'faq') {
        return ['kode' => $terbaik['kode'], 'sumber' => 'faq', 'jawaban' => $terbaik['jawaban']];
    }
    // Topik otomatis: jawabannya disusun saat ini juga (data terbaru). Bila datanya belum ada,
    // jatuh ke jawaban pengarah supaya tidak pernah menampilkan balasan kosong.
    $jawaban = null;
    try {
        $jawaban = ($terbaik['topik']['jawab'])();
    } catch (Throwable $e) {
        error_log('asisten jawab gagal: ' . $e->getMessage());
    }
    if (!is_string($jawaban) || trim($jawaban) === '') {
        return ['kode' => 'tidak_dikenal', 'sumber' => 'sistem', 'jawaban' => asisten_jawaban_pengarah($pertanyaan)];
    }
    return ['kode' => $terbaik['kode'], 'sumber' => 'website', 'jawaban' => $jawaban];
}

/** Jawaban ketika pertanyaannya belum dikenali (selalu mengarahkan ke kontak & PPDB). */
function asisten_jawaban_pengarah(string $pertanyaan = ''): string
{
    $s = asisten_sekolah();
    $bagian = ['Maaf, saya belum menemukan jawaban untuk pertanyaan itu.'
        . ($pertanyaan !== '' ? ' ("' . asisten_potong($pertanyaan, 60) . '")' : '')];
    $bagian[] = 'Yang bisa saya bantu antara lain: profil & visi-misi, alamat, kontak, jumlah guru/siswa/alumni, '
        . 'prestasi, fasilitas, berita, agenda, galeri, struktur organisasi, dan pendaftaran murid baru (PPDB).';
    $kontak = [];
    if (profil_nilai('telepon') !== '') {
        $kontak[] = 'telepon/HP ' . profil_nilai('telepon');
    }
    if (sekolah()['email'] !== '') {
        $kontak[] = 'email ' . profil_nilai('email');
    }
    if ($kontak) {
        $bagian[] = 'Untuk pertanyaan lain silakan hubungi ' . $s['nama'] . ' melalui ' . asisten_daftar($kontak, 3) . '.';
    }
    $bagian[] = 'Bapak/Ibu juga dapat mengirim pesan lewat halaman ' . asisten_tautan('Kontak')
        . ' atau menghubungi panitia PPDB (' . pengaturan('ppdb_kontak', '-') . ').';
    return implode("\n", $bagian);
}

/** Saran pertanyaan yang ditampilkan pada antarmuka obrolan (dari FAQ admin + topik bawaan). */
function asisten_saran(int $batas = 4): array
{
    $keluar = [];
    foreach (asisten_faq(true) as $f) {
        $p = trim((string) $f['pertanyaan']);
        if ($p !== '') {
            $keluar[] = $p;
        }
        if (count($keluar) >= $batas) {
            return $keluar;
        }
    }
    foreach (['Bagaimana cara mendaftar murid baru?', 'Berapa jumlah siswa dan guru?',
        'Apa visi dan misi madrasah?', 'Di mana alamat dan nomor kontaknya?',
        'Prestasi apa saja yang diraih?', 'Bagaimana melihat galeri foto kegiatan?'] as $p) {
        if (count($keluar) >= $batas) {
            break;
        }
        if (!in_array($p, $keluar, true)) {
            $keluar[] = $p;
        }
    }
    return array_slice($keluar, 0, $batas);
}

/** Teks sapaan pembuka pada kotak obrolan. */
function asisten_sapaan(): string
{
    $s = asisten_sekolah();
    return 'Assalamualaikum, selamat datang di layanan informasi ' . $s['nama']
        . ". Saya asisten AI website ini dan siap menjawab pertanyaan Bapak/Ibu.\n"
        . 'Silakan pilih salah satu pertanyaan di bawah atau tuliskan pertanyaan Anda.';
}

// =====================================================================================
// BATAS PEMAKAIAN (per sesi — riwayat tidak disimpan di basis data)
// =====================================================================================
function asisten_sisa_tanya(): int
{
    $pakai = $_SESSION['asisten_pakai'] ?? null;
    if (!is_array($pakai) || (int) ($pakai['mulai'] ?? 0) + ASISTEN_MENIT_TANYA * 60 < time()) {
        return ASISTEN_MAKS_TANYA;
    }
    return max(0, ASISTEN_MAKS_TANYA - (int) $pakai['jumlah']);
}

function asisten_catat_tanya(): void
{
    $pakai = $_SESSION['asisten_pakai'] ?? null;
    if (!is_array($pakai) || (int) ($pakai['mulai'] ?? 0) + ASISTEN_MENIT_TANYA * 60 < time()) {
        $_SESSION['asisten_pakai'] = ['mulai' => time(), 'jumlah' => 1];
        return;
    }
    $_SESSION['asisten_pakai']['jumlah'] = (int) $pakai['jumlah'] + 1;
}

function asisten_pesan_batas(): string
{
    return 'Pertanyaan dalam sesi ini sudah cukup banyak (' . ASISTEN_MAKS_TANYA . ' pertanyaan per '
        . ASISTEN_MENIT_TANYA . ' menit). Silakan tunggu sebentar, atau hubungi langsung '
        . asisten_sekolah()['nama'] . ' melalui halaman Kontak.';
}
