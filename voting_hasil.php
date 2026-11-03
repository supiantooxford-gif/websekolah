<?php
/**
 * voting_hasil.php — ENDPOINT JSON papan hasil Real-Count e-Voting untuk WEBSITE.
 *
 * Dipakai halaman "Informasi e-Voting OSIM" (menu Informasi → e-Voting OSIM) untuk menyegarkan
 * angka setiap beberapa detik tanpa memuat ulang halaman (lihat inc/voting.php + `window.SVV` pada
 * assets/app.js).
 *
 * Cara kerjanya SAMA dengan `api_hasil.php` aplikasi e-Voting:
 *   • selalu membalas JSON (`Content-Type: application/json`, `Cache-Control: no-store`);
 *   • **menghormati setelan aplikasi** — bila panitia belum membuka hasil ke publik, balasannya
 *     `ok: false` + `buka: false` dan TIDAK memuat satu angka pun (halaman menampilkan keterangan
 *     "hasil belum dibuka");
 *   • hanya MEMBACA basis data aplikasi e-Voting (`mode=ro`), tidak pernah menulis apa pun.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/voting.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (!voting_siap()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'buka' => false,
        'pesan' => 'Sumber data aplikasi e-Voting belum dapat dibaca.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!voting_lembaga_cocok()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'buka' => false,
        'pesan' => 'Basis data e-Voting yang terbaca bukan milik lembaga ini.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$h = voting_hasil();

if (!$h['buka']) {
    echo json_encode(['ok' => false, 'buka' => false,
        'pesan' => 'Hasil pemilihan belum dibuka oleh panitia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(voting_json($h), JSON_UNESCAPED_UNICODE);
