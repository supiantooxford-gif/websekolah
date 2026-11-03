<?php
/**
 * PPDB — pengaturan halaman penerimaan peserta didik baru.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

// `ppdb_biaya` DIKELUARKAN dari daftar: baris "Biaya pendaftaran" pada halaman PPDB sudah dihapus
// (permintaan pemilik), jadi menyimpannya kembali hanya akan menyesatkan. Nilai lama di basis data
// dibiarkan utuh (tidak dihapus) bila suatu saat diperlukan lagi.
$KUNCI = ['ppdb_isi', 'ppdb_syarat', 'ppdb_alur', 'ppdb_jadwal', 'ppdb_kontak'];

// Medan yang WAJIB disimpan dengan `potong_baris()` (boleh banyak baris). ⚠️ `potong()` melebur
// SELURUH baris baru menjadi spasi — itu yang dulu membuat Alur/Persyaratan/Jadwal PPDB menyatu
// menjadi satu kartu panjang saat pengaturan disimpan dari panel.
$KUNCI_BARIS = ['ppdb_isi', 'ppdb_syarat', 'ppdb_alur', 'ppdb_jadwal'];

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

// Pintasan BUKA / TUTUP pendaftaran dari kartu status (tanpa perlu menggulir ke bawah).
if ($aksi === 'buka' || $aksi === 'tutup') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    pengaturan_set('ppdb_buka', $aksi === 'buka' ? '1' : '0');
    flash($aksi === 'buka'
        ? 'Pendaftaran PPDB kini DIBUKA — tombol “Daftar Sekarang” langsung membuka formulir.'
        : 'Pendaftaran PPDB kini DITUTUP — pengunjung yang menekan “Daftar Sekarang” akan melihat pesan "'
            . ppdb_tutup_pesan() . '"', 'ok');
    redirect('ppdb.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    foreach ($KUNCI as $k) {
        $nilai = (string) ($_POST[$k] ?? '');
        pengaturan_set($k, in_array($k, $KUNCI_BARIS, true) ? potong_baris($nilai, 8000) : potong($nilai, 8000, ''));
    }
    pengaturan_set('ppdb_buka', empty($_POST['ppdb_buka']) ? '0' : '1');
    pengaturan_set('ppdb_umumkan', empty($_POST['ppdb_umumkan']) ? '0' : '1');
    pengaturan_set('ppdb_pengumuman_tgl', (string) ($_POST['ppdb_pengumuman_tgl'] ?? ''));
    flash('Pengaturan PPDB disimpan — status pendaftaran: ' . (ppdb_dibuka() ? 'DIBUKA' : 'DITUTUP') . '.', 'ok');
    redirect('ppdb.php');
}

$gel = gelombang_aktif();
$stat = [
    'total'     => jumlah('SELECT COUNT(*) FROM ppdb_pendaftar'),
    'baru'      => jumlah("SELECT COUNT(*) FROM ppdb_pendaftar WHERE status = 'baru'"),
    'verifikasi' => jumlah("SELECT COUNT(*) FROM ppdb_pendaftar WHERE status = 'verifikasi'"),
    'diterima'  => jumlah("SELECT COUNT(*) FROM ppdb_pendaftar WHERE status = 'diterima'"),
    'cadangan'  => jumlah("SELECT COUNT(*) FROM ppdb_pendaftar WHERE status = 'cadangan'"),
    'ditolak'   => jumlah("SELECT COUNT(*) FROM ppdb_pendaftar WHERE status = 'ditolak'"),
];

admin_atas('Pengaturan PPDB');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2 style="margin:0 0 4px">Status Pendaftaran PPDB</h2>
            <p class="mut kecil mb0">Menutup pendaftaran menyembunyikan formulir dari pengunjung; yang menekan
                tombol “Daftar Sekarang” akan melihat pesan <em><?= e(ppdb_tutup_pesan()) ?></em></p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <span class="<?= ppdb_dibuka() ? 'pil pil-terima' : 'pil pil-tolak' ?>"><?= ppdb_dibuka() ? 'DIBUKA' : 'DITUTUP' ?></span>
            <?php if (ppdb_dibuka()): ?>
                <form method="post" action="ppdb.php" id="formTutupPpdb" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="tutup">
                    <button type="submit" class="tombol tombol-garis tombol-kecil"><?= ikon('kunci', 15) ?> Tutup Pendaftaran</button>
                </form>
            <?php else: ?>
                <form method="post" action="ppdb.php" id="formBukaPpdb" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="buka">
                    <button type="submit" class="tombol tombol-emas tombol-kecil"><?= ikon('tanda', 15) ?> Buka Pendaftaran</button>
                </form>
            <?php endif; ?>
            <a class="tombol tombol-garis tombol-kecil" target="_blank" rel="noopener" href="../ppdb"><?= ikon('mata', 15) ?> Lihat Halaman</a>
        </div>
    </div>
</div>

<div class="grid g4">
    <?php
    kartu_stat('Total Pendaftar', angka($stat['total']), 'clipboard', 'ppdb_pendaftar.php');
    kartu_stat('Belum Diverifikasi', angka($stat['baru']), 'info', 'ppdb_pendaftar.php?status=baru');
    kartu_stat('Diterima', angka($stat['diterima']), 'check', 'ppdb_pendaftar.php?status=diterima');
    kartu_stat('Cadangan', angka($stat['cadangan']), 'star', 'ppdb_pendaftar.php?status=cadangan');
    ?>
</div>

<div class="grid g21 mt24" style="margin-top:22px">
    <div class="kartu">
        <h2>Pengaturan Halaman PPDB</h2>
        <form method="post" action="ppdb.php" id="formPengaturanPpdb">
            <?= csrf_field() ?>
            <div class="form-3">
                <div class="form-baris">
                    <label>Status Pendaftaran</label>
                    <label class="rasta" style="gap:9px;font-weight:500">
                        <input type="checkbox" name="ppdb_buka" value="1" <?= ppdb_dibuka() ? 'checked' : '' ?>>
                        Buka formulir pendaftaran online
                    </label>
                    <div class="bantuan">Hilangkan centang untuk <strong>MENUTUP</strong> pendaftaran. Saat ditutup,
                        pengunjung yang menekan tombol “Daftar Sekarang” melihat pesan
                        “<?= e(ppdb_tutup_pesan()) ?>”.</div>
                </div>
                <div class="form-baris">
                    <label>Pengumuman Hasil Seleksi</label>
                    <label class="rasta" style="gap:9px;font-weight:500">
                        <input type="checkbox" name="ppdb_umumkan" value="1" <?= pengaturan('ppdb_umumkan') === '1' ? 'checked' : '' ?>>
                        Tampilkan daftar hasil seleksi pada halaman cek status
                    </label>
                    <div class="bantuan">Daftar memuat peserta berstatus Diterima &amp; Cadangan.</div>
                </div>
                <div class="form-baris">
                    <label for="ppdb_pengumuman_tgl">Tanggal Pengumuman</label>
                    <input type="date" id="ppdb_pengumuman_tgl" name="ppdb_pengumuman_tgl" value="<?= e(pengaturan('ppdb_pengumuman_tgl')) ?>">
                </div>
            </div>
            <div class="form-baris">
                <label for="ppdb_isi">Informasi / Pengantar PPDB</label>
                <textarea id="ppdb_isi" name="ppdb_isi" style="min-height:150px"><?= e(pengaturan('ppdb_isi')) ?></textarea>
                <div class="bantuan">Satu baris = satu paragraf.</div>
            </div>
            <div class="form-2">
                <div class="form-baris">
                    <label for="ppdb_syarat">Persyaratan (satu per baris, jadi nomor 1, 2, 3, …)</label>
                    <textarea id="ppdb_syarat" name="ppdb_syarat" style="min-height:170px"><?= e(pengaturan('ppdb_syarat')) ?></textarea>
                    <div class="bantuan">Tekan <strong>Enter</strong> setiap kali selesai satu persyaratan — satu baris menjadi
                        satu butir <strong>bernomor</strong> pada halaman PPDB. Bila semua ditulis dalam satu baris,
                        daftarnya akan tampil menyatu (hanya satu nomor).</div>
                </div>
                <div class="form-baris">
                    <label for="ppdb_alur">Alur Pendaftaran (satu per baris, jadi langkah bernomor)</label>
                    <textarea id="ppdb_alur" name="ppdb_alur" style="min-height:170px"><?= e(pengaturan('ppdb_alur')) ?></textarea>
                    <div class="bantuan">Tekan <strong>Enter</strong> setiap kali selesai satu langkah — satu baris menjadi
                        satu kartu langkah bernomor pada halaman PPDB.</div>
                </div>
            </div>
            <div class="form-2">
                <div class="form-baris">
                    <label for="ppdb_jadwal">Jadwal &amp; Ketentuan (satu per baris)</label>
                    <textarea id="ppdb_jadwal" name="ppdb_jadwal" style="min-height:140px"><?= e(pengaturan('ppdb_jadwal')) ?></textarea>
                </div>
                <div>
                    <div class="form-baris">
                        <label for="ppdb_kontak">Kontak Panitia PPDB</label>
                        <input type="text" id="ppdb_kontak" name="ppdb_kontak" maxlength="240" value="<?= e(pengaturan('ppdb_kontak')) ?>">
                    </div>
                    <p class="mut kecil mb0">Baris “Biaya pendaftaran” sudah <strong>tidak ditampilkan lagi</strong> pada halaman
                        PPDB (permintaan pemilik) — biaya cukup dijelaskan pada teks Informasi PPDB bila perlu.</p>
                </div>
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Pengaturan</button>
                <a class="tombol tombol-garis" target="_blank" rel="noopener" href="../ppdb"><?= ikon('mata', 16) ?> Lihat Halaman PPDB</a>
            </div>
        </form>
    </div>

    <div>
        <div class="kartu">
            <h2>Gelombang Aktif</h2>
            <?php if ($gel): ?>
                <p class="kecil mb16">
                    <strong><?= e($gel['nama']) ?></strong> — T.A. <?= e($gel['tahun_ajaran']) ?><br>
                    <?= e(tanggal_id($gel['mulai']) ?: '-') ?> s.d. <?= e(tanggal_id($gel['selesai']) ?: '-') ?>
                    <?= (int) $gel['kuota'] > 0 ? ' · Kuota ' . (int) $gel['kuota'] : '' ?>
                </p>
            <?php else: ?>
                <p class="mut kecil">Belum ada gelombang pendaftaran yang aktif.</p>
            <?php endif; ?>
            <a class="tombol tombol-kecil" href="ppdb_gelombang.php"><?= ikon('kalender', 15) ?> Kelola Gelombang</a>
        </div>
        <div class="kartu mt16">
            <h2>Rekap Pendaftar</h2>
            <div class="daftar-cek">
                <?php foreach ([
                    'Belum diverifikasi' => $stat['baru'], 'Sedang diverifikasi' => $stat['verifikasi'],
                    'Diterima' => $stat['diterima'], 'Cadangan' => $stat['cadangan'], 'Tidak diterima' => $stat['ditolak'],
                ] as $lb => $n): ?>
                    <div class="rasta" style="justify-content:space-between;font-size:.9rem;padding:5px 0;border-bottom:1px dashed var(--garis)">
                        <span><?= e($lb) ?></span><strong><?= angka($n) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <a class="tombol tombol-garis tombol-kecil mt16" style="margin-top:14px" href="ppdb_pendaftar.php"><?= ikon('clipboard', 15) ?> Buka Data Pendaftar</a>
        </div>
        <div class="kartu mt16">
            <h2>Tautan Penting</h2>
            <div class="daftar-cek">
                <div><a href="../ppdb" target="_blank" rel="noopener">Halaman PPDB publik</a></div>
                <div><a href="../ppdb/status" target="_blank" rel="noopener">Halaman cek status &amp; pengumuman</a></div>
            </div>
            <div class="kotak-info mt16" style="margin-top:14px">
                Beritahukan tautan cek status kepada pendaftar: setiap pendaftar menerima <strong>nomor pendaftaran</strong>
                yang dipakai bersama NISN/nomor HP untuk memeriksa hasil seleksi.
            </div>
        </div>
    </div>
</div>
<?php admin_bawah(); ?>
