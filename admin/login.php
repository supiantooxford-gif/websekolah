<?php
/**
 * Masuk panel admin.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';

if (sudah_masuk()) {
    redirect('index.php');
}

$galat = '';
$username = trim((string) ($_POST['username'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_sah()) {
        $galat = 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif ($username === '' || (string) ($_POST['sandi'] ?? '') === '') {
        $galat = 'Nama pengguna dan kata sandi wajib diisi.';
    } else {
        $sisa = login_terblokir($username);
        if ($sisa > 0) {
            $galat = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil($sisa / 60) . ' menit.';
            q('INSERT INTO log_masuk (waktu, username, nama, ip, peramban, berhasil, keterangan) VALUES (?, ?, ?, ?, ?, 0, ?)', [
                date('Y-m-d H:i:s'), $username, '', ip_pengunjung(), potong((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 200, ''), 'diblokir sementara',
            ]);
        } else {
            $u = one('SELECT * FROM users WHERE username = ?', [$username]);
            $sandi = (string) ($_POST['sandi'] ?? '');
            if (!$u || !password_verify($sandi, (string) $u['sandi'])) {
                $galat = 'Nama pengguna atau kata sandi salah.';
                q('INSERT INTO log_masuk (waktu, username, nama, ip, peramban, berhasil, keterangan) VALUES (?, ?, ?, ?, ?, 0, ?)', [
                    date('Y-m-d H:i:s'), $username, (string) ($u['nama'] ?? ''), ip_pengunjung(),
                    potong((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 200, ''), 'sandi atau nama pengguna salah',
                ]);
            } elseif ((int) $u['aktif'] !== 1) {
                $galat = 'Akun ini sedang dinonaktifkan. Hubungi administrator.';
                q('INSERT INTO log_masuk (waktu, username, nama, ip, peramban, berhasil, keterangan) VALUES (?, ?, ?, ?, ?, 0, ?)', [
                    date('Y-m-d H:i:s'), $username, (string) $u['nama'], ip_pengunjung(),
                    potong((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 200, ''), 'akun dinonaktifkan',
                ]);
            } else {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int) $u['id'];
                q("UPDATE users SET terakhir = datetime('now','localtime') WHERE id = ?", [$u['id']]);
                q('INSERT INTO log_masuk (waktu, username, nama, ip, peramban, berhasil, keterangan) VALUES (?, ?, ?, ?, ?, 1, ?)', [
                    date('Y-m-d H:i:s'), $u['username'], $u['nama'], ip_pengunjung(),
                    potong((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 200, ''), '',
                ]);
                // Hitungan percobaan gagal dikosongkan setelah berhasil masuk.
                q('DELETE FROM log_masuk WHERE berhasil = 0 AND (username = ? OR ip = ?)', [$u['username'], ip_pengunjung()]);
                redirect(tautan_aman((string) ($_REQUEST['minta'] ?? 'index.php'), 'index.php'));
            }
        }
    }
}
$s = sekolah();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk Panel Admin — <?= e($s['nama']) ?></title>
<meta name="robots" content="noindex,nofollow">
<?php if (sek('logo') !== ''): ?><link rel="icon" href="<?= e(sek('logo')) ?>"><?php endif; ?>
<link rel="stylesheet" href="../assets/style.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="admin.css?v=<?= APP_VERSION ?>">
</head>
<body>
<div class="login-admin">
    <div class="login-kartu">
        <div class="login-brand">
            <?php if (sek('logo') !== ''): ?>
                <img src="<?= e(sek('logo')) ?>" alt="Logo <?= e($s['nama']) ?>">
            <?php else: ?>
                <span class="brand-lambang" style="width:54px;height:54px"><?= ikon('building', 28) ?></span>
            <?php endif; ?>
            <div>
                <h1>Panel Admin</h1>
                <div class="sub"><?= e($s['nama']) ?></div>
            </div>
        </div>

        <?php if ($galat !== ''): ?><div class="alert alert-err"><?= e($galat) ?></div><?php endif; ?>
        <?php foreach (flash_ambil() as $f): ?>
            <div class="alert <?= $f['jenis'] === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= $f['pesan'] ?></div>
        <?php endforeach; ?>

        <form method="post" action="login.php">
            <?= csrf_field() ?>
            <input type="hidden" name="minta" value="<?= e((string) ($_GET['minta'] ?? $_POST['minta'] ?? 'index.php')) ?>">
            <div class="form-baris">
                <label for="username">Nama Pengguna</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username" value="<?= e($username) ?>">
            </div>
            <div class="form-baris">
                <label for="sandi">Kata Sandi</label>
                <input type="password" id="sandi" name="sandi" required autocomplete="current-password">
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol" style="width:100%;justify-content:center"><?= ikon('kunci', 17) ?> Masuk</button>
            </div>
        </form>
        <div class="login-kaki">
            <a href="../">&larr; Kembali ke website</a>
            <div style="margin-top:6px"><?= APP_NAME ?> v<?= APP_VERSION ?></div>
        </div>
    </div>
</div>
<script src="../assets/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
