<?php
/**
 * ENDPOINT OBROLAN ASISTEN — selalu membalas JSON.
 *
 * Dipanggil kotak obrolan mengambang (`inc/layout.php`) lewat `fetch`:
 *   POST  aksi=tanya  pesan=<pertanyaan>  csrf=<token>
 *   GET   aksi=saran  (daftar pertanyaan pilihan + sapaan pembuka)
 *
 * Riwayat obrolan TIDAK disimpan di basis data (keputusan pemilik) — jawaban dihitung saat itu
 * juga dari FAQ admin + data website (`inc/asisten.php`).
 *
 * Kode balasan: 405 metode salah, 419 tanpa CSRF, 400 pesan kosong/aksi tak dikenal,
 * 429 batas pertanyaan per sesi tercapai.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? 'tanya');

if ($aksi === 'saran') {
    echo json_encode([
        'ok'      => true,
        'sapaan'  => asisten_sapaan(),
        'saran'   => asisten_saran(4),
        'sisa'    => asisten_sisa_tanya(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Metode tidak diizinkan.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($aksi !== 'tanya') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Aksi tidak dikenali.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!csrf_sah()) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'pesan' => 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.'],
        JSON_UNESCAPED_UNICODE);
    exit;
}

$pesan = trim((string) ($_POST['pesan'] ?? ''));
if (function_exists('mb_substr')) {
    $pesan = mb_substr($pesan, 0, ASISTEN_PESAN_MAKS, 'UTF-8');
} else {
    $pesan = substr($pesan, 0, ASISTEN_PESAN_MAKS);
}
if (trim(preg_replace('#\s+#', ' ', $pesan)) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Silakan tuliskan pertanyaan terlebih dahulu.'],
        JSON_UNESCAPED_UNICODE);
    exit;
}
if (asisten_sisa_tanya() <= 0) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'pesan' => asisten_pesan_batas()], JSON_UNESCAPED_UNICODE);
    exit;
}

asisten_catat_tanya();
$hasil = asisten_jawab($pesan);
echo json_encode([
    'ok'      => true,
    'jawaban' => $hasil['jawaban'],
    'kode'    => $hasil['kode'],
    'sumber'  => $hasil['sumber'],
    'sisa'    => asisten_sisa_tanya(),
], JSON_UNESCAPED_UNICODE);
