<?php
/**
 * Titik masuk tunggal semua halaman (publik & admin).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Kepala keamanan tambahan (Caddy sudah mengirim X-Frame-Options/nosniff). Yang dikunci di sini:
// halaman tidak dapat dibingkai situs lain, objek plugin dilarang, <base> tidak bisa disisipkan,
// dan formulir hanya boleh dikirim ke aplikasi ini sendiri.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header("Content-Security-Policy: frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

require_once __DIR__ . '/helpers.php';
// Modul ALAMAT RAPI (routing) — dipakai `index.php` sebagai pintu depan, dan oleh setiap halaman
// publik untuk membangun tautan (menu_url/hal_url) serta mengalihkan alamat lama.
require_once __DIR__ . '/rute.php';
require_once __DIR__ . '/db.php';
// Modul PROFIL (dipakai halaman Profil Lengkap/bagan) & ASISTEN (mesin jawab obrolan). Keduanya
// hanya berisi fungsi, jadi aman dimuat di semua halaman.
require_once __DIR__ . '/profil.php';
require_once __DIR__ . '/asisten.php';

try {
    ensure_schema();
} catch (Throwable $ex) {
    error_log('Skema basis data gagal disiapkan: ' . $ex->getMessage());
}

require_once __DIR__ . '/layout.php';
