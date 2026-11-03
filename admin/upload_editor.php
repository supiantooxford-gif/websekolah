<?php
/**
 * Unggah gambar untuk PENYUNTING ISI (tombol "Sisip Gambar" pada halaman Berita & Halaman).
 * Dijawab JSON. Berkas diteruskan ke media proxy platform dari sisi server; hanya URL yang dikembalikan.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';

header('Content-Type: application/json; charset=utf-8');

if (!sudah_masuk()) {
    http_response_code(302);
    echo json_encode(['ok' => false, 'pesan' => 'Sesi Anda sudah berakhir. Masuk kembali lalu coba lagi.']);
    exit;
}
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Metode tidak diizinkan.']);
    exit;
}
if (!csrf_sah()) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'pesan' => 'Sesi formulir kedaluwarsa (419). Muat ulang halaman lalu coba lagi.']);
    exit;
}
if (kiriman_terlalu_besar()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Ukuran gambar melebihi batas kirim server (' . ini_get('post_max_size') . ').']);
    exit;
}

$up = unggah_gambar('gambar', 'Gambar');
if (!empty($up['kosong'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Tidak ada berkas yang dipilih.']);
    exit;
}
if (!$up['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => $up['pesan']]);
    exit;
}

echo json_encode(['ok' => true, 'url' => $up['url']]);
