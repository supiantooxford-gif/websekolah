<?php
/**
 * PPDB — cek status pendaftaran & pengumuman hasil seleksi.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// ALAMAT RAPI: alamat lama `ppdb_status.php` dialihkan 301 ke `ppdb/status`
// (nomor pendaftaran & NISN yang dikirim ikut dibawa).
rute_lama_301('ppdb/status', $_GET);

$nomor = trim((string) ($_GET['nomor'] ?? ''));
$nisn = trim((string) ($_GET['nisn'] ?? ''));
$pendaftar = null;
$sudahCari = false;
$galat = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' || isset($_GET['nomor'])) {
    $sudahCari = true;
    if ($nomor === '' || $nisn === '') {
        $galat = 'Nomor pendaftaran dan NISN wajib diisi.';
    } else {
        $pendaftar = one('SELECT * FROM ppdb_pendaftar WHERE nomor = ? AND (nisn = ? OR telepon = ?)', [$nomor, $nisn, $nisn]);
        if (!$pendaftar) {
            $galat = 'Data pendaftar tidak ditemukan. Periksa kembali nomor pendaftaran dan NISN/nomor HP yang Anda gunakan saat mendaftar.';
        }
    }
}

$umumkan = pengaturan('ppdb_umumkan', '0') === '1';
$diterima = $umumkan
    ? all("SELECT nomor, nama, asal_sekolah, jalur FROM ppdb_pendaftar WHERE status IN ('diterima','cadangan') ORDER BY status DESC, nama")
    : [];
$gel = gelombang_aktif();

situs_atas(['judul' => 'Cek Status Pendaftaran PPDB', 'aktif' => 'ppdb']);
hero_halaman('Cek Status Pendaftaran PPDB', 'Masukkan nomor pendaftaran Anda untuk melihat hasil verifikasi dan seleksi', [['label' => 'PPDB', 'tautan' => 'ppdb'], ['label' => 'Cek Status', 'tautan' => '']]);
?>
<section class="isi-halaman">
    <div class="wadah">
        <div class="grid g21">
            <div>
                <div class="artikel">
                    <h2 style="margin-top:0">Formulir Pengecekan</h2>
                    <form method="get" action="ppdb/status">
                        <div class="form-2">
                            <div class="form-baris">
                                <label for="nomor">Nomor Pendaftaran <span class="wajib">*</span></label>
                                <input type="text" id="nomor" name="nomor" placeholder="PPDB-<?= date('Y') ?>-0001" required value="<?= e($nomor) ?>">
                            </div>
                            <div class="form-baris">
                                <label for="nisn">NISN atau Nomor HP <span class="wajib">*</span></label>
                                <input type="text" id="nisn" name="nisn" required value="<?= e($nisn) ?>">
                                <div class="bantuan">Gunakan NISN (atau nomor HP) yang diisi saat mendaftar.</div>
                            </div>
                        </div>
                        <div class="aksi-form">
                            <button type="submit" class="tombol"><?= ikon('search', 17) ?> Cek Status</button>
                            <a class="tombol tombol-garis" href="ppdb">Kembali ke Info PPDB</a>
                        </div>
                    </form>
                </div>

                <?php if ($sudahCari): ?>
                    <div class="mt24" style="margin-top:26px">
                        <?php if ($galat !== ''): ?>
                            <div class="alert alert-err"><?= e($galat) ?></div>
                        <?php elseif ($pendaftar): ?>
                            <?php $berkas = json_decode((string) $pendaftar['berkas'], true) ?: []; ?>
                            <div class="kartu-hasil">
                                <div class="rasta" style="justify-content:space-between;flex-wrap:wrap">
                                    <div>
                                        <span class="kecil mut">Nomor Pendaftaran</span>
                                        <div class="nomor"><?= e($pendaftar['nomor']) ?></div>
                                    </div>
                                    <span class="<?= e(ppdb_status_kelas((string) $pendaftar['status'])) ?>"><?= e(ppdb_status_label((string) $pendaftar['status'])) ?></span>
                                </div>
                                <table class="tabel mt16" style="margin-top:18px">
                                    <tbody>
                                        <tr><th style="width:220px">Nama Lengkap</th><td><?= e($pendaftar['nama']) ?></td></tr>
                                        <tr><th>NISN</th><td><?= e($pendaftar['nisn'] ?: '-') ?></td></tr>
                                        <tr><th>Jenis Kelamin</th><td><?= $pendaftar['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></td></tr>
                                        <tr><th>Tempat, Tanggal Lahir</th><td><?= e($pendaftar['tempat_lahir']) ?><?= $pendaftar['tempat_lahir'] !== '' && $pendaftar['tanggal_lahir'] !== '' ? ', ' : '' ?><?= e(tanggal_id($pendaftar['tanggal_lahir'])) ?></td></tr>
                                        <tr><th>Sekolah/Madrasah Asal</th><td><?= e($pendaftar['asal_sekolah'] ?: '-') ?></td></tr>
                                        <tr><th>Jalur</th><td><?= e($pendaftar['jalur'] ?: '-') ?></td></tr>
                                        <tr><th>Tanggal Daftar</th><td><?= e(waktu_id($pendaftar['tanggal_daftar'])) ?></td></tr>
                                        <?php if (trim((string) $pendaftar['nilai_seleksi']) !== ''): ?>
                                            <tr><th>Nilai Seleksi</th><td><?= e($pendaftar['nilai_seleksi']) ?></td></tr>
                                        <?php endif; ?>
                                        <?php if (trim((string) $pendaftar['catatan_panitia']) !== ''): ?>
                                            <tr><th>Keterangan Panitia</th><td><?= nl($pendaftar['catatan_panitia']) ?></td></tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th>Berkas Terunggah</th>
                                            <td>
                                                <?php if (!$berkas): ?>
                                                    <span class="mut">Belum ada berkas yang diunggah.</span>
                                                <?php else: ?>
                                                    <?php foreach ($berkas as $b): ?>
                                                        <div><a href="<?= e($b['url'] ?? '#') ?>" target="_blank" rel="noopener"><?= ikon('unduh', 14) ?> <?= e($b['nama'] ?? 'Berkas') ?></a></div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p class="kecil mut mt16" style="margin-top:16px">
                                    Status dapat berubah setelah panitia memverifikasi data dan berkas Anda.
                                    <?php if ($umumkan): ?>Hasil seleksi sudah diumumkan<?= pengaturan('ppdb_pengumuman_tgl') !== '' ? ' pada ' . e(tanggal_id(pengaturan('ppdb_pengumuman_tgl'))) : '' ?>.<?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($umumkan): ?>
                    <div class="artikel mt24" style="margin-top:26px">
                        <h2 style="margin-top:0">Pengumuman Hasil Seleksi</h2>
                        <p class="kecil mut">
                            <?php if (pengaturan('ppdb_pengumuman_tgl') !== ''): ?>
                                Diumumkan pada <?= e(tanggal_id(pengaturan('ppdb_pengumuman_tgl'), true)) ?>.
                            <?php endif; ?>
                            Daftar berikut memuat calon peserta didik yang dinyatakan <strong>diterima</strong> dan <strong>cadangan</strong>.
                            Peserta yang diterima wajib melakukan daftar ulang sesuai jadwal.
                        </p>
                        <?php if (!$diterima): ?>
                            <div class="alert alert-info">Pengumuman sudah dibuka, namun belum ada peserta yang dinyatakan diterima.</div>
                        <?php else: ?>
                            <div class="tabel-wrap">
                                <table class="tabel">
                                    <thead><tr><th>No</th><th>Nomor</th><th>Nama</th><th>Asal Sekolah/Madrasah</th><th>Jalur</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($diterima as $i => $r): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= e($r['nomor']) ?></td>
                                                <td><?= e($r['nama']) ?></td>
                                                <td><?= e($r['asal_sekolah'] ?: '-') ?></td>
                                                <td><?= e($r['jalur'] ?: '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <aside>
                <div class="sisi-kartu">
                    <h3>Ketentuan Seleksi</h3>
                    <ul class="daftar-syarat">
                        <li><?= ikon('check', 16) ?><span>Seleksi dilakukan atas dasar kelengkapan berkas dan nilai seleksi.</span></li>
                        <li><?= ikon('check', 16) ?><span>Hasil seleksi bersifat mutlak dan tidak dapat diganggu gugat.</span></li>
                        <li><?= ikon('check', 16) ?><span>Peserta yang diterima wajib melakukan daftar ulang sesuai jadwal.</span></li>
                    </ul>
                </div>
                <div class="sisi-kartu mt16">
                    <h3>Gelombang Aktif</h3>
                    <?php if ($gel): ?>
                        <p class="kecil mb0">
                            <strong><?= e($gel['nama']) ?></strong><br>
                            T.A. <?= e($gel['tahun_ajaran']) ?><br>
                            <?= e(tanggal_id($gel['mulai']) ?: '-') ?> s.d. <?= e(tanggal_id($gel['selesai']) ?: '-') ?>
                        </p>
                    <?php else: ?>
                        <p class="kecil mut mb0">Belum ada gelombang pendaftaran yang dibuka.</p>
                    <?php endif; ?>
                </div>
                <div class="sisi-kartu mt16">
                    <h3>Panitia PPDB</h3>
                    <p class="kecil"><?= e(pengaturan('ppdb_kontak')) ?></p>
                    <a class="tombol tombol-garis tombol-kecil" href="kontak">Hubungi Kami</a>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php situs_bawah(); ?>
