<?php
/**
 * Keluar dari panel admin.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';

$u = current_user();
if ($u) {
    q('INSERT INTO log_masuk (waktu, username, nama, ip, peramban, berhasil, keterangan) VALUES (?, ?, ?, ?, ?, 1, ?)', [
        date('Y-m-d H:i:s'), $u['username'], $u['nama'], ip_pengunjung(),
        potong((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 200, ''), 'keluar',
    ]);
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
}
session_destroy();
redirect('login.php');
