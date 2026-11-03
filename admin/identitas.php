<?php
/**
 * Identitas sekolah/madrasah + pengaturan tampilan beranda.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_admin();

$KUNCI_TEKS = ['footer_teks', 'sambutan_singkat', 'beranda_judul_program', 'beranda_program_teks',
    'beranda_judul_berita', 'beranda_berita_teks', 'beranda_judul_agenda', 'beranda_agenda_teks',
    'beranda_judul_testimoni', 'beranda_testimoni_teks', 'beranda_galeri_judul', 'beranda_galeri_teks'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $aksi = (string) ($_POST['aksi'] ?? 'identitas');

    if ($aksi === 'beranda') {
        foreach ($KUNCI_TEKS as $k) {
            pengaturan_set($k, potong((string) ($_POST[$k] ?? ''), 2000, ''));
        }
        pengaturan_set('alumni_manual', (string) max(0, (int) ($_POST['alumni_manual'] ?? 0)));
        flash('Pengaturan beranda disimpan.', 'ok');
        redirect('identitas.php?tab=beranda');
    }

    $lama = sekolah();
    $logo = proses_gambar('logo', (string) $lama['logo'], !empty($_POST['logo_hapus']), 'Logo');
    $kepalaFoto = proses_gambar('kepala_foto', (string) ($lama['kepala_foto'] ?? ''), !empty($_POST['kepala_foto_hapus']), 'Foto kepala');
    $data = [];
    foreach (['nama', 'jenjang', 'npsn', 'nsm', 'alamat', 'desa', 'kecamatan', 'kabupaten', 'provinsi',
        'telepon', 'whatsapp', 'email', 'website', 'kepala', 'nip_kepala', 'slogan', 'maps',
        'akreditasi', 'tahun_ajaran', 'jam_operasional', 'video_profil'] as $k) {
        $data[$k] = potong(trim((string) ($_POST[$k] ?? '')), $k === 'maps' || $k === 'video_profil' ? 400 : 200, '');
    }
    $data['jenis'] = (string) ($_POST['jenis'] ?? 'madrasah') === 'umum' ? 'umum' : 'madrasah';
    $data['logo'] = $logo['url'];
    $data['kepala_foto'] = $kepalaFoto['url'];
    sekolah_simpan($data);
    flash('Identitas sekolah disimpan. ' . trim($logo['pesan'] . ' ' . $kepalaFoto['pesan']),
        ($logo['galat'] || $kepalaFoto['galat']) ? 'err' : 'ok');
    redirect('identitas.php');
}

$tab = (string) ($_GET['tab'] ?? 'identitas');
$s = sekolah();
$stat = statistik();

admin_atas('Identitas Sekolah');
?>
<div class="kartu mb24">
    <div class="rasta" style="gap:10px;flex-wrap:wrap">
        <a class="tombol <?= $tab === 'identitas' ? '' : 'tombol-garis' ?> tombol-kecil" href="identitas.php">Identitas &amp; Kontak</a>
        <a class="tombol <?= $tab === 'beranda' ? '' : 'tombol-garis' ?> tombol-kecil" href="identitas.php?tab=beranda">Teks Beranda &amp; Statistik</a>
    </div>
</div>

<?php if ($tab === 'beranda'): ?>
    <div class="grid g21">
        <div class="kartu">
            <h2>Judul &amp; Keterangan Bagian Beranda</h2>
            <p class="mut kecil">Mengubah tulisan ini akan langsung mengubah tampilan halaman depan.</p>
            <form method="post" action="identitas.php">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="beranda">
                <div class="form-3">
                    <div class="form-baris">
                        <label for="beranda_judul_program">Judul bagian Program</label>
                        <input type="text" id="beranda_judul_program" name="beranda_judul_program" maxlength="160" value="<?= e(pengaturan('beranda_judul_program', 'Program Digitalisasi Madrasah')) ?>">
                    </div>
                    <div class="form-baris">
                        <label for="beranda_judul_berita">Judul bagian Berita</label>
                        <input type="text" id="beranda_judul_berita" name="beranda_judul_berita" maxlength="160" value="<?= e(pengaturan('beranda_judul_berita', 'Berita Terbaru')) ?>">
                    </div>
                    <div class="form-baris">
                        <label for="beranda_judul_agenda">Judul bagian Agenda</label>
                        <input type="text" id="beranda_judul_agenda" name="beranda_judul_agenda" maxlength="160" value="<?= e(pengaturan('beranda_judul_agenda', 'Agenda Madrasah')) ?>">
                    </div>
                </div>
                <div class="form-3">
                    <div class="form-baris">
                        <label for="beranda_program_teks">Keterangan bagian Program</label>
                        <textarea id="beranda_program_teks" name="beranda_program_teks" maxlength="2000" style="min-height:70px"><?= e(pengaturan('beranda_program_teks')) ?></textarea>
                    </div>
                    <div class="form-baris">
                        <label for="beranda_berita_teks">Keterangan bagian Berita</label>
                        <textarea id="beranda_berita_teks" name="beranda_berita_teks" maxlength="2000" style="min-height:70px"><?= e(pengaturan('beranda_berita_teks')) ?></textarea>
                    </div>
                    <div class="form-baris">
                        <label for="beranda_agenda_teks">Keterangan bagian Agenda</label>
                        <textarea id="beranda_agenda_teks" name="beranda_agenda_teks" maxlength="2000" style="min-height:70px"><?= e(pengaturan('beranda_agenda_teks')) ?></textarea>
                    </div>
                </div>
                <div class="form-3">
                    <div class="form-baris">
                        <label for="beranda_judul_testimoni">Judul bagian Testimoni</label>
                        <input type="text" id="beranda_judul_testimoni" name="beranda_judul_testimoni" maxlength="160" value="<?= e(pengaturan('beranda_judul_testimoni', 'Apa kata mereka')) ?>">
                    </div>
                    <div class="form-baris">
                        <label for="beranda_judul_galeri">Judul bagian Galeri/Medos</label>
                        <input type="text" id="beranda_judul_galeri" name="beranda_judul_galeri" maxlength="160" value="<?= e(pengaturan('beranda_galeri_judul', 'Galeri & Media Sosial')) ?>">
                    </div>
                    <div class="form-baris">
                        <label for="beranda_testimoni_teks">Keterangan bagian Testimoni</label>
                        <textarea id="beranda_testimoni_teks" name="beranda_testimoni_teks" maxlength="2000" style="min-height:70px"><?= e(pengaturan('beranda_testimoni_teks')) ?></textarea>
                    </div>
                </div>
                <div class="form-2">
                    <div class="form-baris">
                        <label for="beranda_galeri_teks">Keterangan bagian Galeri/Medos</label>
                        <textarea id="beranda_galeri_teks" name="beranda_galeri_teks" maxlength="2000" style="min-height:80px"><?= e(pengaturan('beranda_galeri_teks')) ?></textarea>
                    </div>
                    <div class="form-baris">
                        <label for="sambutan_singkat">Kutipan Sambutan pada Beranda</label>
                        <textarea id="sambutan_singkat" name="sambutan_singkat" maxlength="2000" style="min-height:80px"><?= e(pengaturan('sambutan_singkat')) ?></textarea>
                        <div class="bantuan">Tampil pada kartu “Sambutan Kepala” di beranda.</div>
                    </div>
                </div>
                <div class="form-baris">
                    <label for="footer_teks">Teks Kaki Halaman (footer)</label>
                    <textarea id="footer_teks" name="footer_teks" maxlength="2000" style="min-height:70px"><?= e(pengaturan('footer_teks')) ?></textarea>
                </div>
                <div class="aksi-form"><button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Pengaturan Beranda</button></div>
            </form>
        </div>
        <div>
            <div class="kartu">
                <h2>Angka Statistik</h2>
                <p class="mut kecil">Tiga angka dihitung otomatis dari data aplikasi. Angka <strong>Alumni</strong> dihitung dari data kelulusan <em>ditambah</em> Data Alumni yang diisi/diimpor pada menu <strong>Data Alumni</strong>; isian manual di bawah hanya dipakai bila angkanya lebih besar (mis. lulusan lama yang belum terdata).</p>
                <div class="daftar-cek">
                    <div class="rasta" style="justify-content:space-between"><span>Guru &amp; Tendik (aktif)</span><strong><?= angka($stat['guru']) ?></strong></div>
                    <div class="rasta" style="justify-content:space-between"><span>Siswa Aktif</span><strong><?= angka($stat['siswa']) ?></strong></div>
                    <div class="rasta" style="justify-content:space-between"><span>Alumni terdata</span><strong><?= angka($stat['alumni']) ?></strong></div>
                    <div class="rasta" style="justify-content:space-between"><span>Pendaftar PPDB</span><strong><?= angka($stat['pendaftar']) ?></strong></div>
                </div>
                <form method="post" action="identitas.php" class="mt16" style="margin-top:14px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="beranda">
                    <?php foreach ($KUNCI_TEKS as $k): ?>
                        <input type="hidden" name="<?= e($k) ?>" value="<?= e(pengaturan($k)) ?>">
                    <?php endforeach; ?>
                    <div class="form-baris">
                        <label for="alumni_manual">Jumlah Alumni (manual)</label>
                        <input type="number" id="alumni_manual" name="alumni_manual" min="0" value="<?= (int) pengaturan('alumni_manual', '0') ?>">
                        <div class="bantuan">Kosongkan (0) untuk memakai jumlah otomatis: data kelulusan + Data Alumni yang terisi.</div>
                    </div>
                    <div class="aksi-form"><button type="submit" class="tombol tombol-kecil"><?= ikon('simpan', 15) ?> Simpan Angka Alumni</button></div>
                </form>
            </div>
        </div>
    </div>
<?php else: ?>
    <form method="post" action="identitas.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="grid g21">
            <div class="kartu">
                <h2>Identitas Lembaga</h2>
                <div class="form-2">
                    <div class="form-baris">
                        <label for="nama">Nama <?= e(jenis_label()) ?> <span class="wajib">*</span></label>
                        <input type="text" id="nama" name="nama" required maxlength="200" value="<?= e($s['nama']) ?>">
                    </div>
                    <div class="form-baris">
                        <label for="jenis">Jenis Lembaga</label>
                        <select id="jenis" name="jenis">
                            <option value="madrasah" <?= ($s['jenis'] ?? '') === 'madrasah' ? 'selected' : '' ?>>Madrasah (MI/MTs/MA/MAK)</option>
                            <option value="umum" <?= ($s['jenis'] ?? '') === 'umum' ? 'selected' : '' ?>>Sekolah Umum (SD/SMP/SMA/SMK)</option>
                        </select>
                        <div class="bantuan">Menentukan istilah yang dipakai pada website (Sekolah/Madrasah).</div>
                    </div>
                </div>
                <div class="form-3">
                    <div class="form-baris">
                        <label for="jenjang">Jenjang</label>
                        <input type="text" id="jenjang" name="jenjang" maxlength="30" placeholder="MA / SMP / MI" value="<?= e($s['jenjang'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="npsn">NPSN</label>
                        <input type="text" id="npsn" name="npsn" maxlength="30" value="<?= e($s['npsn'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="nsm">NSM (khusus madrasah)</label>
                        <input type="text" id="nsm" name="nsm" maxlength="30" value="<?= e($s['nsm'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-2">
                    <div class="form-baris">
                        <label for="alamat">Alamat (Jalan, Dusun)</label>
                        <input type="text" id="alamat" name="alamat" maxlength="200" value="<?= e($s['alamat'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="desa">Desa / Kelurahan</label>
                        <input type="text" id="desa" name="desa" maxlength="120" value="<?= e($s['desa'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-3">
                    <div class="form-baris">
                        <label for="kecamatan">Kecamatan</label>
                        <input type="text" id="kecamatan" name="kecamatan" maxlength="120" value="<?= e($s['kecamatan'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="kabupaten">Kabupaten / Kota</label>
                        <input type="text" id="kabupaten" name="kabupaten" maxlength="120" value="<?= e($s['kabupaten'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="provinsi">Provinsi</label>
                        <input type="text" id="provinsi" name="provinsi" maxlength="120" value="<?= e($s['provinsi'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-3">
                    <div class="form-baris">
                        <label for="telepon">Telepon</label>
                        <input type="text" id="telepon" name="telepon" maxlength="60" value="<?= e($s['telepon'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="whatsapp">Nomor WhatsApp</label>
                        <input type="text" id="whatsapp" name="whatsapp" maxlength="40" value="<?= e($s['whatsapp'] ?? '') ?>">
                        <div class="bantuan">Dipakai tombol WhatsApp mengapung &amp; halaman kontak.</div>
                    </div>
                    <div class="form-baris">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="120" value="<?= e($s['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-3">
                    <div class="form-baris">
                        <label for="website">Situs / Laman</label>
                        <input type="text" id="website" name="website" maxlength="200" value="<?= e($s['website'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="akreditasi">Akreditasi</label>
                        <input type="text" id="akreditasi" name="akreditasi" maxlength="60" value="<?= e($s['akreditasi'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="tahun_ajaran">Tahun Ajaran Aktif</label>
                        <input type="text" id="tahun_ajaran" name="tahun_ajaran" maxlength="20" value="<?= e($s['tahun_ajaran'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-3">
                    <div class="form-baris">
                        <label for="kepala">Nama <?= e(kepala_label()) ?></label>
                        <input type="text" id="kepala" name="kepala" maxlength="160" value="<?= e($s['kepala'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="nip_kepala">NIP <?= e(kepala_label()) ?></label>
                        <input type="text" id="nip_kepala" name="nip_kepala" maxlength="40" value="<?= e($s['nip_kepala'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="jam_operasional">Jam Layanan</label>
                        <input type="text" id="jam_operasional" name="jam_operasional" maxlength="160" placeholder="Senin - Sabtu, 07.00 - 14.00 WITA" value="<?= e($s['jam_operasional'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-2">
                    <div class="form-baris">
                        <label for="slogan">Slogan / Motto</label>
                        <input type="text" id="slogan" name="slogan" maxlength="200" value="<?= e($s['slogan'] ?? '') ?>">
                    </div>
                    <div class="form-baris">
                        <label for="video_profil">Tautan Video Profil (YouTube, opsional)</label>
                        <input type="text" id="video_profil" name="video_profil" maxlength="400" value="<?= e($s['video_profil'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-baris">
                    <label for="maps">Peta Lokasi</label>
                    <input type="text" id="maps" name="maps" maxlength="400" placeholder="Tempelkan alamat embed Google Maps (https://www.google.com/maps/embed?...) atau tulis alamat lokasi" value="<?= e($s['maps'] ?? '') ?>">
                    <div class="bantuan">Alamat <code>.../maps/embed?...</code> akan ditampilkan sebagai peta pada halaman kontak. Alamat biasa hanya ditampilkan sebagai teks.</div>
                </div>
                <div class="aksi-form">
                    <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Identitas</button>
                    <a class="tombol tombol-garis" href="../" target="_blank" rel="noopener"><?= ikon('mata', 16) ?> Lihat Website</a>
                </div>
            </div>
            <div class="kartu">
                <h2>Logo Lembaga</h2>
                <p class="mut kecil">Logo dipakai pada bilah navigasi, footer, dan favicon situs.</p>
                <?php blok_gambar('logo', (string) ($s['logo'] ?? ''), 'Logo', true, 'PNG/SVG berlatar transparan, disarankan berbentuk persegi.'); ?>
                <div class="kotak-info mt16" style="margin-top:14px">
                    Simpan formulir untuk menerapkan logo baru.
                </div>
            </div>
            <div class="kartu mt24">
                <h2>Foto <?= e(kepala_label()) ?></h2>
                <p class="mut kecil">Foto ini ditampilkan di atas kartu <strong>Sambutan <?= e(kepala_label()) ?></strong> pada halaman depan. Disarankan foto berdiri (potret) dengan perbandingan 4:5.</p>
                <?php blok_gambar('kepala_foto', (string) ($s['kepala_foto'] ?? ''), 'Foto ' . kepala_label(), true, 'JPG/PNG/WEBP, maksimal 15 MB.'); ?>
                <?php if (foto_kepala() === ''): ?>
                    <div class="kotak-info mt16" style="margin-top:14px">Belum ada foto — halaman depan memakai bulatan inisial nama kepala sebagai gantinya.</div>
                <?php endif; ?>
            </div>
        </div>
    </form>
<?php endif; ?>
<?php admin_bawah(); ?>
