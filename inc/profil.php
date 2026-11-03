<?php
/**
 * HALAMAN "PROFIL LENGKAP" & "STRUKTUR ORGANISASI" (skema v10).
 *
 * Keduanya adalah JENIS MENU yang dirender `halaman.php`:
 *   • `profil_lengkap`        → dokumen profil madrasah (kop, logo, identitas, visi/misi/tujuan, ttd)
 *   • `struktur_organisasi`   → bagan kotak berjenjang berikut garis penghubung
 *
 * Seluruh ISI diambil dari basis data dan diubah lewat panel admin:
 *   • `admin/profil.php`   → identitas tambahan + visi/misi/tujuan (kolom tabel `sekolah`)
 *   • `admin/struktur.php` → kotak-kotak bagan (tabel `struktur`)
 */
declare(strict_types=1);

// =====================================================================================
// PROFIL LENGKAP
// =====================================================================================

/** Nilai satu kolom identitas profil (kosong/`-` dianggap belum diisi). */
function profil_nilai(string $kunci): string
{
    $v = trim((string) (sekolah()[$kunci] ?? ''));
    if (in_array($v, ['', '-', '–', '—', '.', '_'], true)) {
        return '';
    }
    return $v;
}

/**
 * Baris tabel IDENTITAS MADRASAH pada halaman Profil Lengkap.
 *
 * Susunannya mengikuti dokumen contoh pemilik: baris bernomor 1..13, dengan beberapa baris
 * **rincian tanpa nomor** (Desa, Kecamatan+Kode Pos, Kabupaten+Provinsi, Telepon, Email, Website)
 * sebagai bagian dari baris **Alamat**, serta Jarak ke Kecamatan/Kabupaten sebagai rincian
 * baris **Lokasi Madrasah**. Baris yang seluruh nilainya kosong tidak dicetak.
 *
 * @return array<int,array{no:int,label:string,sub:bool,sel:array<int,array{label:string,nilai:string,kelas:string}>}>
 */
function profil_identitas(): array
{
    $wilayah = trim(implode(' ', array_filter([
        profil_nilai('desa'), profil_nilai('kecamatan'), profil_nilai('kabupaten'), profil_nilai('provinsi'),
    ])));
    $alamat = profil_nilai('alamat') !== '' ? profil_nilai('alamat') : $wilayah;

    $nomor = 0;
    $hasil = [];
    $tambah = static function (string $label, array $sel, bool $sub = false) use (&$nomor, &$hasil): void {
        $ada = false;
        foreach ($sel as $s) {
            if (trim((string) $s['nilai']) !== '') {
                $ada = true;
                break;
            }
        }
        if (!$ada) {
            return;   // baris tanpa isi tidak ditampilkan
        }
        if (!$sub) {
            $nomor++;
        }
        $hasil[] = [
            'no'    => $sub ? 0 : $nomor,
            'label' => $label,
            'sub'   => $sub,
            'sel'   => $sel,
        ];
    };

    $tambah('Nama Madrasah', [['label' => '', 'nilai' => profil_nilai('nama'), 'kelas' => '']]);
    $tambah('NPSN / NSM', [
        ['label' => '', 'nilai' => profil_nilai('npsn'), 'kelas' => ''],
        ['label' => '', 'nilai' => profil_nilai('nsm'), 'kelas' => ''],
    ]);
    // Nomor 3 = blok ALAMAT (rinciannya mengikuti sebagai baris tanpa nomor).
    $tambah('Alamat', [['label' => '', 'nilai' => $alamat, 'kelas' => '']]);
    $tambah('Desa', [['label' => '', 'nilai' => profil_nilai('desa'), 'kelas' => '']], true);
    $tambah('Kecamatan', [
        ['label' => '', 'nilai' => profil_nilai('kecamatan'), 'kelas' => ''],
        ['label' => 'Kode Pos', 'nilai' => profil_nilai('kode_pos'), 'kelas' => 'sempit'],
    ], true);
    $tambah('Kabupaten', [
        ['label' => '', 'nilai' => profil_nilai('kabupaten'), 'kelas' => ''],
        ['label' => 'Provinsi', 'nilai' => profil_nilai('provinsi'), 'kelas' => 'sempit'],
    ], true);
    $tambah('Telepon', [['label' => '', 'nilai' => profil_nilai('telepon'), 'kelas' => '']], true);
    $tambah('Email', [['label' => '', 'nilai' => profil_nilai('email'), 'kelas' => '']], true);
    $tambah('Website', [['label' => '', 'nilai' => profil_nilai('website'), 'kelas' => '']], true);

    $tambah('NPWP', [['label' => '', 'nilai' => profil_nilai('npwp'), 'kelas' => '']]);
    $tambah('Status Madrasah', [['label' => '', 'nilai' => profil_nilai('status_madrasah'), 'kelas' => '']]);
    $tambah('Waktu Belajar', [['label' => '', 'nilai' => profil_nilai('waktu_belajar'), 'kelas' => '']]);
    $tambah('No. SK IJOP', [
        ['label' => '', 'nilai' => profil_nilai('sk_ijop'), 'kelas' => ''],
        ['label' => 'Tanggal', 'nilai' => profil_nilai('sk_ijop_tgl'), 'kelas' => 'sempit'],
    ]);
    $tambah('Tahun Berdiri', [['label' => '', 'nilai' => profil_nilai('tahun_berdiri'), 'kelas' => '']]);
    $tambah('Akreditasi', [
        ['label' => '', 'nilai' => profil_nilai('akreditasi'), 'kelas' => ''],
        ['label' => 'Tanggal', 'nilai' => profil_nilai('akreditasi_tgl'), 'kelas' => 'sempit'],
    ]);
    $tambah('Penyelenggara', [['label' => '', 'nilai' => profil_nilai('penyelenggara'), 'kelas' => '']]);
    $tambah('SK Menkumham', [['label' => '', 'nilai' => profil_nilai('sk_menkumham'), 'kelas' => '']]);
    $tambah('Lokasi Madrasah', [
        ['label' => 'Jarak ke Kecamatan', 'nilai' => profil_nilai('jarak_kecamatan'), 'kelas' => ''],
        ['label' => 'Jarak ke Kabupaten', 'nilai' => profil_nilai('jarak_kabupaten'), 'kelas' => ''],
    ]);
    $tambah('Bangunan Madrasah', [['label' => '', 'nilai' => profil_nilai('bangunan'), 'kelas' => '']]);

    return $hasil;
}

/** Butir teks multi-baris (Misi/Tujuan) — satu baris = satu butir, dibersihkan dari penomoran. */
function profil_butir(string $teks): array
{
    $keluar = [];
    foreach (preg_split('#\r\n|\r|\n#', trim($teks)) ?: [] as $b) {
        $b = trim((string) preg_replace('#^\s*\d+[\.\)]\s*#', '', (string) $b));   // buang "1." bila ada
        if ($b !== '') {
            $keluar[] = $b;
        }
    }
    return $keluar;
}

/** "Juli 2022" — bulan + tahun (dipakai baris tempat/tanggal pada tanda tangan profil). */
function profil_bulan_tahun(?string $tgl = null): string
{
    $ts = $tgl !== null && trim($tgl) !== '' ? strtotime($tgl) : time();
    if ($ts === false) {
        $ts = time();
    }
    return BULAN_ID[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * URL DOKUMEN profil (IJOP / Akreditasi) — kosong bila belum diunggah.
 * Dipakai halaman Profil Lengkap untuk tombol "Lihat Ijop"/"Lihat Akreditasi".
 */
function profil_dokumen(string $kunci): string
{
    $kunci = in_array($kunci, ['berkas_ijop', 'berkas_akreditasi'], true) ? $kunci : '';
    return $kunci === '' ? '' : trim((string) (sekolah()[$kunci] ?? ''));
}

/** Halaman PROFIL LENGKAP (dipanggil `halaman.php`). */
function profil_lengkap_render(): void
{
    $s = sekolah();
    $identitas = profil_identitas();
    $visi = profil_nilai('visi');
    $misi = profil_butir((string) ($s['misi'] ?? ''));
    $tujuan = profil_butir((string) ($s['tujuan'] ?? ''));
    ?>
    <?php /* data-cetak-lebar = lebar area cetak A4 MENDATAR (±1032px @96dpi) dipakai `initSkalaCetak()`. */ ?>
    <div class="profil-lengkap" id="profilLengkap" data-cetak-lebar="1032">
        <div class="pl-kop">
            <h1>PROFIL <?= e($s['nama']) ?></h1>
        </div>

        <div class="pl-grid">
            <div class="pl-kiri">
                <h2 class="pl-judul">LOGO MADRASAH</h2>
                <div class="pl-logo">
                    <?php if (sek('logo') !== ''): ?>
                        <img src="<?= e(sek('logo')) ?>" alt="Logo <?= e($s['nama']) ?>">
                    <?php else: ?>
                        <span class="pl-logo-kosong"><?= ikon('building', 40) ?><small>Logo belum diunggah</small></span>
                    <?php endif; ?>
                </div>

                <h2 class="pl-judul">IDENTITAS MADRASAH</h2>
                <?php /* Lebar kolom DIPATOK lewat <colgroup> dan SETIAP baris selalu memakai 4 sel
                         (nomor · label · nilai · nilai kedua). Dengan begitu garis pemisah kolom
                         LURUS dari baris pertama sampai terakhir — sebelumnya baris berisi 3 sel
                         membuat kolom "Kode Pos/Tanggal/Provinsi" bergeser-geser (ditandai pemilik). */ ?>
                <table class="pl-tabel">
                    <colgroup>
                        <col class="pl-c-no">
                        <col class="pl-c-label">
                        <col class="pl-c-nilai">
                        <col class="pl-c-nilai2">
                    </colgroup>
                    <tbody>
                        <?php foreach ($identitas as $baris): ?>
                            <?php
                            $sel1 = $baris['sel'][0] ?? null;
                            $sel2 = $baris['sel'][1] ?? null;
                            ?>
                            <tr<?= $baris['sub'] ? ' class="pl-sub-baris"' : '' ?>>
                                <td class="pl-no"><?= $baris['no'] > 0 ? (int) $baris['no'] . '.' : '' ?></td>
                                <td class="pl-label"><?= e($baris['label']) ?></td>
                                <td class="pl-nilai">
                                    <?php if ($sel1 !== null): ?>
                                        <?php if ($sel1['label'] !== ''): ?><span class="pl-anak-label"><?= e($sel1['label']) ?></span><?php endif; ?>
                                        <?= e($sel1['nilai']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="pl-nilai pl-nilai2">
                                    <?php if ($sel2 !== null): ?>
                                        <?php if ($sel2['label'] !== ''): ?><span class="pl-anak-label"><?= e($sel2['label']) ?></span><?php endif; ?>
                                        <?= e($sel2['nilai']) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                // Tombol DOKUMEN (IJOP & Akreditasi) — hanya dicetak bila berkasnya sudah diunggah
                // dari panel Profil Lengkap. Membuka berkas (gambar/PDF) di tab baru.
                $dokIjo = profil_dokumen('berkas_ijop');
                $dokAkr = profil_dokumen('berkas_akreditasi');
                ?>
                <?php if ($dokIjo !== '' || $dokAkr !== ''): ?>
                    <div class="pl-dokumen" id="profilDokumen">
                        <?php if ($dokIjo !== ''): ?>
                            <a class="tombol tombol-garis" href="<?= e($dokIjo) ?>" target="_blank" rel="noopener noreferrer">
                                <?= ikon('lihat', 16) ?> Lihat Ijop
                            </a>
                        <?php endif; ?>
                        <?php if ($dokAkr !== ''): ?>
                            <a class="tombol tombol-garis" href="<?= e($dokAkr) ?>" target="_blank" rel="noopener noreferrer">
                                <?= ikon('sertifikat', 16) ?> Lihat Akreditasi
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

            <div class="pl-kanan">
                <h2 class="pl-judul">VISI, MISI, DAN TUJUAN MADRASAH</h2>

                <h3 class="pl-sub">VISI</h3>
                <div class="pl-visi">
                    <?= $visi !== '' ? e($visi) : '<span class="mut">Visi belum diisi.</span>' ?>
                </div>

                <h3 class="pl-sub">MISI</h3>
                <?php if (!$misi): ?>
                    <p class="mut">Misi belum diisi.</p>
                <?php else: ?>
                    <ol class="pl-daftar">
                        <?php foreach ($misi as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <h3 class="pl-sub">TUJUAN</h3>
                <?php if (!$tujuan): ?>
                    <p class="mut">Tujuan belum diisi.</p>
                <?php else: ?>
                    <ol class="pl-daftar">
                        <?php foreach ($tujuan as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>

        <?php /* Tanda tangan diletakkan di KAKI lembar dan diratakan KE PINGGIR KANAN dokumen
                 (permintaan pemilik: "posisi tanda tangan dipinggir"). */ ?>
        <div class="pl-kaki">
            <div class="pl-ttd">
                <?php /* Baris "tempat, bulan tahun" — tempat dari Kecamatan pada Identitas Sekolah. */ ?>
                <div class="pl-ttd-tempat"><?= e(profil_nilai('kecamatan') !== '' ? profil_nilai('kecamatan') : '') ?><?= profil_nilai('kecamatan') !== '' ? ', ' : '' ?><?= e(profil_bulan_tahun()) ?></div>
                <div class="pl-ttd-jabatan">Kepala Madrasah,</div>
                <div class="pl-ttd-ruang"></div>
                <div class="pl-ttd-nama"><?= e(profil_nilai('kepala') !== '' ? profil_nilai('kepala') : '-') ?></div>
            </div>
        </div>
    </div>
    <?php
}

// =====================================================================================
// STRUKTUR ORGANISASI (BAGAN) — PETA TETAP
// =====================================================================================
// Seluruh letak kotak, ukuran, dan garis penghubung ditentukan KODE di `struktur_peta()`
// (koordinat dalam ruang 1600 × 853, mengikuti dokumen contoh pemilik). Admin HANYA mengisi
// NAMA + PAS FOTO setiap kotak — tidak ada lagi pengaturan letak/urutan yang bisa salah geser.

/** PETA TETAP bagan struktur organisasi. */
function struktur_peta(): array
{
    return [
        'kanvas' => ['lebar' => 1600, 'tinggi' => 853],
        // Garis penghubung TETAP (path SVG). `putus` = garis putus-putus (Komite).
        'garis' => [
            ['d' => 'M 798 212 V 535'],                 // batang tengah dari Kepala Madrasah ke Dewan Guru
            ['d' => 'M 180 383 H 1444'],                // batang datar baris Wakamad (mulai di stub kiri)
            ['d' => 'M 180 383 V 425'],                 // stub ke Kepala Lab
            ['d' => 'M 416 383 V 425'],                 // stub ke Wakamad Kurikulum
            ['d' => 'M 652 383 V 425'],                 // stub ke Wakamad Kesiswaan
            ['d' => 'M 955 383 V 425'],                 // stub ke Wakamad Sarpras
            ['d' => 'M 1202 383 V 425'],                // stub ke Wakamad Humas
            ['d' => 'M 1444 383 V 425'],                // stub ke Kepala Perpus
            ['d' => 'M 180 383 V 535'],                 // turun ke Wali Kelas
            ['d' => 'M 1444 383 V 528'],                // turun ke Guru BK
            ['d' => 'M 798 232 H 1103'],                // dari batang tengah ke KTU
            ['d' => 'M 470 232 H 798', 'gaya' => 'putus'],   // Komite (garis putus-putus)
            ['d' => 'M 1202 257 V 288'],                // KTU turun ke batang anak
            ['d' => 'M 1038 288 H 1346'],               // batang anak KTU
            ['d' => 'M 1038 288 V 310'],                // stub ke Tata Usaha
            ['d' => 'M 1346 288 V 310'],                // stub ke Bendahara
            // ⚠️ WAJIB sampai y = 770: garis ini yang dulu HILANG sehingga stub baris kelas ke-2
            // dan ke-3 menggantung (ditandai pemilik dengan tanda panah pada tangkapan layar).
            ['d' => 'M 180 580 V 770'],                 // Wali Kelas → seluruh baris kelas
            ['d' => 'M 180 630 H 218'],                 // stub ke kolom kelas 1
            ['d' => 'M 180 700 H 218'],                 // stub baris kelas 2
            ['d' => 'M 180 770 H 218'],                 // stub baris kelas 3
            ['d' => 'M 421 630 H 440'],                 // Kelas X MIPA 1 → 2
            ['d' => 'M 421 700 H 440'],                 // Kelas XI MIPA 1 → 2
            ['d' => 'M 421 770 H 440'],                 // Kelas XII MIPA 1 → 2
            ['d' => 'M 798 580 V 785'],                 // Dewan Guru → Siswa
        ],
        // Kotak (slot) — letak & ukuran tetap. Blok merah di dalam kotak SUDAH DIHAPUS
        // (permintaan pemilik: "Hilangkan warna merah yang ada di dalam kolom bagan"),
        // jadi tidak ada lagi penanda `aksen` pada slot mana pun.
        'slot' => [
            ['kode' => 'kepala_madrasah',   'label' => 'Kepala Madrasah',    'x' => 700,  'y' => 160, 'w' => 197, 'h' => 52],
            ['kode' => 'komite',            'label' => 'Komite',             'x' => 273,  'y' => 208, 'w' => 197, 'h' => 48],
            ['kode' => 'ktu',               'label' => 'KTU',                'x' => 1103, 'y' => 208, 'w' => 197, 'h' => 48],
            ['kode' => 'tata_usaha',        'label' => 'Tata Usaha',         'x' => 958,  'y' => 310, 'w' => 161, 'h' => 45],
            ['kode' => 'bendahara',         'label' => 'Bendahara',          'x' => 1245, 'y' => 310, 'w' => 203, 'h' => 45],
            ['kode' => 'kepala_lab',        'label' => 'Kepala Lab',         'x' => 78,   'y' => 425, 'w' => 203, 'h' => 45],
            ['kode' => 'wakamad_kurikulum', 'label' => 'Wakamad Kurikulum',  'x' => 312,  'y' => 425, 'w' => 218, 'h' => 45],
            ['kode' => 'wakamad_kesiswaan', 'label' => 'Wakamad Kesiswaan',  'x' => 548,  'y' => 425, 'w' => 218, 'h' => 45],
            ['kode' => 'wakamad_sarpras',   'label' => 'Wakamad Sarpras',    'x' => 855,  'y' => 425, 'w' => 200, 'h' => 45],
            ['kode' => 'wakamad_humas',     'label' => 'Wakamad Humas',      'x' => 1105, 'y' => 425, 'w' => 195, 'h' => 45],
            ['kode' => 'kepala_perpus',     'label' => 'Kepala Perpus',      'x' => 1345, 'y' => 425, 'w' => 198, 'h' => 45],
            ['kode' => 'wali_kelas',        'label' => 'Wali Kelas',         'x' => 78,   'y' => 535, 'w' => 203, 'h' => 45],
            ['kode' => 'dewan_guru',        'label' => 'Dewan Guru',         'x' => 700,  'y' => 535, 'w' => 197, 'h' => 45],
            ['kode' => 'guru_bk',           'label' => 'Guru BK',            'x' => 1335, 'y' => 528, 'w' => 205, 'h' => 47],
            ['kode' => 'kelas_x_1',         'label' => 'Kelas X MIPA 1',     'x' => 218,  'y' => 608, 'w' => 203, 'h' => 45],
            ['kode' => 'kelas_x_2',         'label' => 'Kelas X MIPA 2',     'x' => 440,  'y' => 608, 'w' => 200, 'h' => 45],
            ['kode' => 'kelas_xi_1',        'label' => 'Kelas XI MIPA 1',    'x' => 218,  'y' => 678, 'w' => 203, 'h' => 45],
            ['kode' => 'kelas_xi_2',        'label' => 'Kelas XI MIPA 2',    'x' => 440,  'y' => 678, 'w' => 200, 'h' => 45],
            ['kode' => 'kelas_xii_1',       'label' => 'Kelas XII MIPA 1',   'x' => 218,  'y' => 748, 'w' => 203, 'h' => 45],
            ['kode' => 'kelas_xii_2',       'label' => 'Kelas XII MIPA 2',   'x' => 440,  'y' => 748, 'w' => 200, 'h' => 45],
            ['kode' => 'siswa',             'label' => 'Siswa',              'x' => 700,  'y' => 785, 'w' => 197, 'h' => 45],
        ],
    ];
}

/** Daftar slot bagan (urut sebagaimana ditampilkan pada panel). */
function struktur_slot(): array
{
    return struktur_peta()['slot'];
}

/** Baris `struktur` menurut kode slot (satu baris per slot). */
function struktur_baris_kode(string $kode): ?array
{
    if ($kode === '') {
        return null;
    }
    return one('SELECT * FROM struktur WHERE kode = ? LIMIT 1', [$kode]);
}

/**
 * NAMA yang ditampilkan pada sebuah kotak: isian admin bila ada, selain itu label bawaan slot.
 * Nilai kosong/tanda hubung dianggap "belum diisi" sehingga label bawaan tetap terpakai.
 */
function struktur_nama_slot(array $slot): string
{
    $baris = struktur_baris_kode((string) $slot['kode']);
    $nama = $baris ? trim((string) $baris['nama']) : '';
    if ($nama === '' || in_array($nama, ['-', '–', '—', '.', '_'], true)) {
        return (string) $slot['label'];
    }
    return $nama;
}

/**
 * Apakah isian nama BERBEDA dari jabatan bawaan kotak?
 * Bila sama (mis. kotak "KTU" diisi "KTU"), bagan hanya menampilkan SATU baris (jabatan saja)
 * sehingga tidak ada tulisan kembar.
 */
function struktur_nama_beda(string $nama, string $jabatan): bool
{
    $rapi = static fn (string $t): string => trim((string) preg_replace('#[^a-z0-9]+#', ' ', strtolower($t)));
    $a = $rapi($nama);
    $b = $rapi($jabatan);
    return $a !== '' && $a !== $b;
}

/** URL pas foto sebuah slot (kosong bila belum diunggah). */
function struktur_foto_slot(array $slot): string
{
    $baris = struktur_baris_kode((string) $slot['kode']);
    return $baris ? trim((string) ($baris['foto'] ?? '')) : '';
}

/** Slot yang TAMPIL (baris wajib punya `aktif = 1`; slot tanpa baris tetap tampil). */
function struktur_slot_tampil(): array
{
    $keluar = [];
    foreach (struktur_slot() as $slot) {
        $baris = struktur_baris_kode((string) $slot['kode']);
        if ($baris && (int) $baris['aktif'] !== 1) {
            continue;
        }
        $slot['nama'] = struktur_nama_slot($slot);
        $slot['foto'] = struktur_foto_slot($slot);
        $keluar[] = $slot;
    }
    return $keluar;
}

/** Berapa slot yang sudah dibéri nama (bukan label bawaan) & foto — untuk ringkasan panel. */
function struktur_ringkasan(): array
{
    $nama = 0;
    $foto = 0;
    $kosong = [];
    foreach (struktur_slot() as $slot) {
        $baris = struktur_baris_kode((string) $slot['kode']);
        $n = $baris ? trim((string) $baris['nama']) : '';
        if ($n !== '' && !in_array($n, ['-', '–', '—', '.', '_'], true)) {
            $nama++;
        } else {
            $kosong[] = (string) $slot['label'];
        }
        if ($baris && trim((string) ($baris['foto'] ?? '')) !== '') {
            $foto++;
        }
    }
    return ['nama' => $nama, 'foto' => $foto, 'total' => count(struktur_slot()), 'kosong' => $kosong];
}

/** Peta kode → baris (untuk panel, sekali query). */
function struktur_semua_kode(): array
{
    $keluar = [];
    foreach (all('SELECT * FROM struktur ORDER BY id') as $b) {
        $kode = trim((string) ($b['kode'] ?? ''));
        if ($kode !== '' && !isset($keluar[$kode])) {
            $keluar[$kode] = $b;
        }
    }
    return $keluar;
}

/**
 * HALAMAN STRUKTUR ORGANISASI — bagan SVG berlayout TETAP (admin hanya mengisi nama + foto).
 */
function struktur_render(): void
{
    $s = sekolah();
    $peta = struktur_peta();
    $lebar = (int) $peta['kanvas']['lebar'];
    $tinggi = (int) $peta['kanvas']['tinggi'];
    $slot = struktur_slot_tampil();
    $adaFoto = false;
    foreach ($slot as $sl) {
        if ($sl['foto'] !== '') {
            $adaFoto = true;
            break;
        }
    }
    ?>
    <?php /* Bagan tidak untuk dicetak — ditampilkan sebesar layar (boleh lebih lebar dari A4,
             permintaan pemilik). `.org-lebar` melebarkan lembar melewati lebar konten biasa. */ ?>
    <div class="org-lebar">
    <div class="org-sheet" id="strukturOrganisasi">
        <div class="org-kop">
            <?php if (sek('logo') !== ''): ?>
                <img class="org-logo" src="<?= e(sek('logo')) ?>" alt="Logo <?= e($s['nama']) ?>">
            <?php endif; ?>
            <div class="org-judul">
                <h2>STRUKTUR ORGANISASI</h2>
                <p><?= e($s['nama']) ?></p>
            </div>
        </div>

        <?php /* Bagan hanya untuk DILIHAT (tidak dicetak) → disediakan tombol perbesar/perkecil. */ ?>
        <div class="org-aksi" id="orgAksi">
            <button type="button" class="tombol tombol-garis tombol-kecil" data-zoom="-"><?= ikon('minus', 15) ?> Perkecil</button>
            <button type="button" class="tombol tombol-garis tombol-kecil" data-zoom="0">Ukuran Layar</button>
            <button type="button" class="tombol tombol-kecil" data-zoom="+"><?= ikon('plus', 15) ?> Perbesar</button>
            <span class="mut kecil" id="orgZoomInfo">100%</span>
        </div>
        <div class="org-bagan-gulir" id="orgBaganGulir">
        <svg class="org-svg" viewBox="0 0 <?= $lebar ?> <?= $tinggi ?>" role="img"
             aria-label="Bagan struktur organisasi <?= e($s['nama']) ?>" preserveAspectRatio="xMidYMid meet">
            <defs>
                <linearGradient id="orgKotak" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#fff2a8"/>
                    <stop offset="1" stop-color="#ffd633"/>
                </linearGradient>
                <?php /* ⚠️ JEBAKAN: clipPath bawaan memakai `userSpaceOnUse`, sehingga rect 0,0 100x100
                         memotong HABIS pas foto (fotonya berada di koordinat kotaknya sendiri, bukan di
                         0,0) — inilah sebab "pas foto tidak muncul". `objectBoundingBox` membuat clip
                         dihitung relatif terhadap kotak gambar. */ ?>
                <clipPath id="orgFotoBulat" clipPathUnits="objectBoundingBox">
                    <rect x="0" y="0" width="1" height="1" rx="0.08"/>
                </clipPath>
            </defs>

            <?php /* Garis penghubung (tetap) — digambar lebih dulu agar kotak menutupinya. */ ?>
            <g class="org-garis">
                <?php foreach ($peta['garis'] as $g): ?>
                    <path d="<?= e((string) $g['d']) ?>" class="<?= (($g['gaya'] ?? '') === 'putus') ? 'org-putus' : '' ?>"/>
                <?php endforeach; ?>
            </g>

            <?php foreach ($slot as $sl): ?>
                <?php
                $x = (float) $sl['x'];
                $y = (float) $sl['y'];
                $w = (float) $sl['w'];
                $h = (float) $sl['h'];
                $foto = (string) $sl['foto'];
                $px = 5.0;                    // jarak dalam kotak
                // Blok merah di dalam kotak SUDAH DIHAPUS (permintaan pemilik) sehingga tidak ada
                // lagi ruang tambahan di kiri — foto & teks memakai seluruh lebar kotak.
                $lebarFoto = $foto !== '' ? ($h - 2 * $px) * 0.75 : 0.0;   // pas foto 3:4
                $xTeks = $x + $px + ($lebarFoto > 0 ? $lebarFoto + 6 : 0);
                $lebarTeks = $w - ($xTeks - $x) - $px;
                $tengahTeks = $xTeks + $lebarTeks / 2;
                $tengahY = $y + $h / 2;
                $jabatan = (string) $sl['label'];   // ⚠️ $sl (baris slot), bukan array $slot
                // Nama jabatan SELALU tampil: bila isian namanya berbeda dari jabatan, kotak
                // memakai DUA baris (jabatan kecil di atas + nama di bawah); bila sama/kosong,
                // cukup satu baris jabatan (mis. kotak "KTU", "Dewan Guru", "Siswa").
                $duaBaris = struktur_nama_beda((string) $sl['nama'], $jabatan);
                /** Hitung ukuran huruf + `textLength` agar teks TIDAK meluber keluar kotak. */
                $ukur = static function (string $teks, float $lebar, float $fontMaks, float $fontMin): array {
                    $panjang = function_exists('mb_strlen') ? mb_strlen($teks, 'UTF-8') : strlen($teks);
                    $font = $fontMaks;
                    $ttl = '';
                    // Teks kotak dibuat HURUF BESAR oleh CSS → perkiraan lebar memakai faktor besar.
                    $perkiraan = 0.78 * $font * max(1, $panjang);
                    if ($lebar > 12 && $perkiraan > $lebar) {
                        if ($perkiraan > $lebar * 1.35) {
                            $font = max($fontMin, floor($font * ($lebar * 1.35 / $perkiraan) * 10) / 10);
                            $perkiraan = 0.78 * $font * max(1, $panjang);
                        }
                        $ttl = ' textLength="' . round($lebar, 1) . '" lengthAdjust="spacingAndGlyphs"';
                    }
                    return [$font, $ttl];
                };
                ?>
                <g class="org-kotak">
                    <rect class="org-latar" x="<?= $x ?>" y="<?= $y ?>" width="<?= $w ?>" height="<?= $h ?>" rx="3"/>
                    <?php if ($foto !== ''): ?>
                        <image class="org-foto" href="<?= e($foto) ?>" x="<?= $x + $px ?>" y="<?= $y + $px ?>"
                               width="<?= $lebarFoto ?>" height="<?= $h - 2 * $px ?>" preserveAspectRatio="xMidYMid slice"
                               clip-path="url(#orgFotoBulat)"/>
                    <?php endif; ?>
                    <?php if ($duaBaris): ?>
                        <?php [$fontJab, $ttlJab] = $ukur($jabatan, $lebarTeks, 11.5, 8.0); ?>
                        <?php [$fontNama, $ttlNama] = $ukur((string) $sl['nama'], $lebarTeks, 15.0, 9.0); ?>
                        <text class="org-jabatan" x="<?= $tengahTeks ?>" y="<?= $y + $h * 0.36 ?>" font-size="<?= $fontJab ?>"<?= $ttlJab ?>><?= e($jabatan) ?></text>
                        <text class="org-nama" x="<?= $tengahTeks ?>" y="<?= $y + $h * 0.70 ?>" font-size="<?= $fontNama ?>"<?= $ttlNama ?>><?= e((string) $sl['nama']) ?></text>
                    <?php else: ?>
                        <?php [$fontJab, $ttlJab] = $ukur($jabatan, $lebarTeks, 17.0, 10.5); ?>
                        <text class="org-nama" x="<?= $tengahTeks ?>" y="<?= $tengahY ?>" font-size="<?= $fontJab ?>"<?= $ttlJab ?>><?= e($jabatan) ?></text>
                    <?php endif; ?>
                </g>
            <?php endforeach; ?>
        </svg>
        </div>

        <?php if (!$adaFoto): ?>
            <p class="kecil mut tengah org-catatan">Pas foto tiap kotak belum diunggah. Admin dapat menambahkannya dari panel
                &rarr; <strong>Struktur Organisasi (Bagan)</strong> — bagan hanya perlu diisi nama &amp; foto.</p>
        <?php endif; ?>
    </div>
    </div>
    <?php
}
