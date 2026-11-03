<?php
/**
 * UNDUH KARTU PIP (dengan KATA SANDI) — halaman publik.
 *
 * Alurnya:
 *   1. Peserta didik/orang tua menekan tombol "Unduh" pada kolom Kartu PIP di halaman Layanan PIP.
 *   2. Halaman ini menampilkan formulir **kata sandi** (satu sandi untuk semua kartu, ditetapkan
 *      admin pada panel Layanan PIP).
 *   3. Setelah sandi BENAR, berkasnya **DIALIRKAN server ini** (`pip_kirim_berkas()`) — URL media
 *      yang sebenarnya TIDAK pernah dikirim ke peramban, sehingga sandi benar-benar melindungi
 *      berkasnya (bukan sekadar menyembunyikan tautan).
 *
 * Pengaman: batas percobaan sandi per sesi (`PIP_MAKS_COBA` kali / `PIP_MENIT_COBA` menit), token
 * CSRF, dan pola POST→Redirect→GET agar sandi tidak tertinggal di riwayat/refresh.
 *
 * Seluruh penolongnya ada pada `inc/pip.php` — halaman ini sengaja tipis.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/pip.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$row = pip_penerima_by_id($id);
$kartu = $row !== null ? trim((string) $row['kartu']) : '';
$nama = $row !== null ? trim((string) $row['nama']) : '';

// --- berkas tidak ada / tidak aktif → halaman ramah (bukan unduhan kosong) -------------------
if ($row === null || $kartu === '' || (int) $row['aktif'] !== 1) {
    http_response_code(404);
    pip_kartu_tampil('Kartu PIP tidak ditemukan',
        'Kartu PIP yang Anda minta tidak tersedia (mungkin sudah dipindahkan atau dinonaktifkan admin).',
        '', '', 'err', $id);
    exit;
}

$galat = '';

// --- POST: periksa kata sandi -----------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_sah()) {
        csrf_gagal();   // 419 — sama seperti halaman lain pada aplikasi ini
    } else {
        $keadaan = pip_coba_keadaan();
        if ($keadaan['habis']) {
            $galat = 'Percobaan kata sandi terlalu banyak. Coba lagi dalam ' . $keadaan['menit'] . ' menit.';
        } elseif (!pip_sandi_ada()) {
            $galat = 'Admin belum menetapkan kata sandi Kartu PIP. Silakan hubungi admin madrasah.';
        } elseif (pip_sandi_sah((string) ($_POST['sandi'] ?? ''))) {
            pip_sesi_buka();
            // Pola POST→Redirect→GET: sandi tidak tersimpan di riwayat peramban.
            redirect('pip_kartu.php?id=' . $id . '&unduh=1');
        } else {
            pip_coba_catat();
            $sisa = pip_coba_keadaan();
            $galat = 'Kata sandi salah.'
                . ($sisa['habis']
                    ? ' Percobaan sudah habis — coba lagi dalam ' . $sisa['menit'] . ' menit.'
                    : ' Sisa percobaan: ' . $sisa['sisa'] . ' kali.');
        }
    }
}

// --- kirim berkas: hanya bila sesi sudah membuka sandi ----------------------------------------
if (isset($_GET['unduh']) || isset($_GET['lihat'])) {
    if (!pip_sesi_terbuka()) {
        // Sesi belum dibuka (mis. tautan dibuka di peramban/perangkat lain) → minta sandi lagi.
        $galat = 'Silakan masukkan kata sandi untuk mengunduh Kartu PIP ini.';
    } elseif (!pip_sandi_ada()) {
        http_response_code(403);
        pip_kartu_tampil('Kata sandi belum diatur',
            'Admin belum menetapkan kata sandi Kartu PIP, sehingga kartu belum dapat diunduh.',
            $nama, '', 'err', $id);
        exit;
    } else {
        $namaUnduh = pip_nama_unduh($row);
        if (pip_kirim_berkas($kartu, $namaUnduh, isset($_GET['lihat']))) {
            exit;   // berkas sudah terkirim
        }
        $galat = 'Berkas Kartu PIP gagal diambil dari penyimpanan. Silakan coba lagi sebentar lagi.';
    }
}

pip_kartu_tampil('Unduh Kartu PIP', '', $nama, $galat, $galat !== '' ? 'err' : '', $id);
