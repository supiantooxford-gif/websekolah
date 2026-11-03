<?php
/**
 * UNDUH DOKUMEN KURIKULUM (halaman publik) — v1.18.0.
 *
 * Permintaan pemilik: *"Dokumen kurikulum buat inputan untuk mengupload dokumen pdf/word/excel/
 * gambar. Hasilnya ditampilkan dalam bentuk kartu dokumen yang bisa di download oleh pengunjung
 * website"*.
 *
 * Alurnya: kartu dokumen pada halaman "Dokumen Kurikulum" menautkan berkas ke halaman ini, lalu
 * berkasnya **DIALIRKAN server ini** (`kurikulum_kirim_berkas()`) dengan nama unduhan yang rapi.
 * Dengan begitu URL media aslinya tidak pernah sampai ke peramban pengunjung, dan nama berkas yang
 * tersimpan di perangkat pengunjung tetap sesuai judul dokumennya.
 *
 * Dokumen yang dinonaktifkan admin (atau sudah dihapus) dijawab 404 — bukan berkas kosong.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kurikulum.php';

$id = (int) ($_GET['id'] ?? 0);
$d = kurikulum_dokumen_by_id($id);

if (!$d || (int) ($d['aktif'] ?? 0) !== 1 || trim((string) ($d['url'] ?? '')) === '') {
    http_response_code(404);
    situs_atas(['judul' => 'Dokumen tidak ditemukan']);
    echo '<section class="isi-halaman"><div class="wadah"><div class="artikel tengah">'
        . '<h1>Dokumen tidak ditemukan</h1>'
        . '<p class="mut">Dokumen yang Anda minta tidak tersedia atau sudah tidak dibagikan lagi. '
        . 'Silakan kembali ke halaman Dokumen Kurikulum untuk melihat daftar dokumen yang ada.</p>'
        . '<a class="tombol" href="./">Kembali ke Beranda</a>'
        . '</div></div></section>';
    situs_bawah();
    exit;
}

if (!kurikulum_kirim_berkas((string) $d['url'], kurikulum_dokumen_nama_unduh($d))) {
    http_response_code(404);
    situs_atas(['judul' => 'Berkas tidak dapat diunduh']);
    echo '<section class="isi-halaman"><div class="wadah"><div class="artikel tengah">'
        . '<h1>Berkas tidak dapat diunduh</h1>'
        . '<p class="mut">Berkas dokumen ini sedang tidak dapat diambil dari penyimpanan. '
        . 'Silakan coba lagi beberapa saat lagi atau hubungi pengelola madrasah.</p>'
        . '<a class="tombol" href="./">Kembali ke Beranda</a>'
        . '</div></div></section>';
    situs_bawah();
    exit;
}
exit;
