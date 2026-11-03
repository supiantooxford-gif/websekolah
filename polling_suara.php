<?php
/**
 * Menerima suara POLLING PENDAPAT dari beranda, lalu kembali ke halaman asal.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    redirect('./');
}
if (!csrf_sah()) {
    csrf_gagal();
}
$pollId = (int) ($_POST['poll_id'] ?? 0);
$pilihanId = (int) ($_POST['pilihan_id'] ?? 0);
$galat = poll_suara_simpan($pollId, $pilihanId);

if ($galat !== '') {
    flash($galat, 'err');
} else {
    flash('Terima kasih! Pendapat Anda sudah kami catat.', 'ok');
}
redirect('./?poll=hasil#polling');
