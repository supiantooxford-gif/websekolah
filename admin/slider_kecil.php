<?php
/**
 * Sisa halaman lama "Slider Kecil & Medsos".
 *
 * Strip "slider kecil" sudah TIDAK dipakai lagi di beranda (permintaan pemilik), sehingga
 * pengelolaannya dipindah ke halaman **Media Sosial** (`medsos.php`). Berkas ini hanya
 * mengalihkan alamat lama supaya tidak menjadi jalan buntu (mis. dari penanda buku).
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_login();   // sekaligus menghormati pembatasan peran (mis. Editor Berita)

$tabel = (string) ($_GET['tabel'] ?? $_POST['tabel'] ?? '');
if ($tabel === 'slider_kecil' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    flash('Pengelolaan “Slider Kecil” sudah tidak dipakai — stripnya tidak lagi tampil di beranda.', 'info');
}
redirect('medsos.php');
