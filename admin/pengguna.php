<?php
/**
 * Pengguna & akun panel admin.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_admin();

$sandiSekali = null;

function user_by_id_admin(int $id): ?array
{
    return one('SELECT * FROM users WHERE id = ?', [$id]);
}

function sandi_acak_admin(): string
{
    $abjad = 'abcdefghjkmnpqrstuvwxyz23456789';
    $keluar = '';
    for ($i = 0; $i < 8; $i++) {
        $keluar .= $abjad[random_int(0, strlen($abjad) - 1)];
    }
    return $keluar;
}

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'toggle') {
    $id = (int) ($_GET['id'] ?? 0);
    $u = user_by_id_admin($id);
    if ($u && (int) $u['id'] === (int) (current_user()['id'] ?? 0)) {
        flash('Anda tidak dapat menonaktifkan akun Anda sendiri.', 'err');
    } elseif ($u && $u['role'] === 'admin' && (int) $u['aktif'] === 1
        && jumlah("SELECT COUNT(*) FROM users WHERE role = 'admin' AND aktif = 1") <= 1) {
        flash('Tidak dapat menonaktifkan administrator terakhir.', 'err');
    } else {
        q('UPDATE users SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [$id]);
        flash('Status akun diubah.', 'ok');
    }
    redirect('pengguna.php');
}
if ($aksi === 'reset') {
    $id = (int) ($_GET['id'] ?? 0);
    $u = user_by_id_admin($id);
    if (!$u) {
        flash('Akun tidak ditemukan.', 'err');
    } else {
        $sandi = sandi_acak_admin();
        q('UPDATE users SET sandi = ? WHERE id = ?', [password_hash($sandi, PASSWORD_DEFAULT), $id]);
        $_SESSION['sandi_sekali'] = ['nama' => $u['nama'], 'username' => $u['username'], 'sandi' => $sandi];
        flash('Kata sandi baru dibuat — salin sekarang, tidak akan ditampilkan lagi.', 'info');
    }
    redirect('pengguna.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $u = user_by_id_admin($id);
    if (!$u) {
        flash('Akun tidak ditemukan.', 'err');
    } elseif ((int) $id === (int) (current_user()['id'] ?? 0)) {
        flash('Anda tidak dapat menghapus akun Anda sendiri.', 'err');
    } elseif ($u['role'] === 'admin' && jumlah("SELECT COUNT(*) FROM users WHERE role = 'admin'") <= 1) {
        flash('Tidak dapat menghapus administrator terakhir.', 'err');
    } else {
        hapus_baris('users', $id);
        flash('Akun ' . e($u['nama']) . ' dihapus.', 'ok');
    }
    redirect('pengguna.php');
}
if ($aksi === 'simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $username = strtolower(trim((string) ($_POST['username'] ?? '')));
    $sandi = (string) ($_POST['sandi'] ?? '');
    $role = (string) ($_POST['role'] ?? 'admin');
    if (!array_key_exists($role, peran_daftar())) {
        $role = 'admin';
    }

    $galat = '';
    if ($nama === '' || $username === '') {
        $galat = 'Nama dan nama pengguna wajib diisi.';
    } elseif (!preg_match('#^[a-z0-9._-]{3,40}$#', $username)) {
        $galat = 'Nama pengguna hanya boleh huruf kecil, angka, titik, garis bawah, atau tanda hubung (3–40 karakter).';
    } elseif (one('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id])) {
        $galat = 'Nama pengguna tersebut sudah dipakai akun lain.';
    } elseif ($id === 0 && strlen($sandi) < 6) {
        $galat = 'Kata sandi minimal 6 karakter.';
    } elseif ($id > 0 && $sandi !== '' && strlen($sandi) < 6) {
        $galat = 'Kata sandi baru minimal 6 karakter.';
    }
    if ($galat !== '') {
        flash($galat, 'err');
        redirect('pengguna.php' . ($id > 0 ? '?edit=' . $id : '') . '#formPengguna');
    }

    $fotoLama = $id > 0 ? (string) (user_by_id_admin($id)['foto'] ?? '') : '';
    $g = proses_gambar('foto', $fotoLama, !empty($_POST['foto_hapus']), 'Foto');

    if ($id > 0) {
        $set = ['nama = ?', 'username = ?', 'role = ?', 'email = ?', 'telepon = ?', 'foto = ?', 'aktif = ?'];
        $val = [potong($nama, NAMA_MAKS, ''), $username, $role, potong((string) ($_POST['email'] ?? ''), 120, ''),
            potong((string) ($_POST['telepon'] ?? ''), 40, ''), $g['url'], empty($_POST['aktif']) ? 0 : 1];
        if ($sandi !== '') {
            $set[] = 'sandi = ?';
            $val[] = password_hash($sandi, PASSWORD_DEFAULT);
        }
        $val[] = $id;
        q('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        flash('Akun disimpan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    } else {
        q('INSERT INTO users (nama, username, sandi, role, email, telepon, foto, aktif, dibuat)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            potong($nama, NAMA_MAKS, ''), $username, password_hash($sandi, PASSWORD_DEFAULT), $role,
            potong((string) ($_POST['email'] ?? ''), 120, ''), potong((string) ($_POST['telepon'] ?? ''), 40, ''),
            $g['url'], empty($_POST['aktif']) ? 0 : 1, date('Y-m-d H:i:s'),
        ]);
        flash('Akun baru ditambahkan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    }
    redirect('pengguna.php');
}

if (!empty($_SESSION['sandi_sekali'])) {
    $sandiSekali = $_SESSION['sandi_sekali'];
    unset($_SESSION['sandi_sekali']);
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? user_by_id_admin((int) $_GET['edit']) : null;
$daftar = all('SELECT * FROM users ORDER BY (role <> \'admin\'), nama');

admin_atas('Pengguna & Akun');
?>
<?php if ($sandiSekali): ?>
    <div class="alert alert-info">
        Kata sandi baru untuk <strong><?= e($sandiSekali['nama']) ?></strong> (nama pengguna <strong><?= e($sandiSekali['username']) ?></strong>):
        <span style="font-size:1.15rem;font-weight:800;letter-spacing:.05em"><?= e($sandiSekali['sandi']) ?></span>
        <div class="kecil">Catat dan berikan kepada pemilik akun — kata sandi ini tidak akan ditampilkan lagi.</div>
    </div>
<?php endif; ?>

<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Daftar Akun Panel</h2>
            <p class="mut kecil mb0">Administrator dapat mengelola seluruh isi website; Editor dapat mengelola konten,
                PPDB, &amp; <strong>Layanan PIP</strong> (tidak dapat membuka Pengguna, Identitas, dan Log);
                <strong>Editor Berita</strong> hanya dapat mengisi berita.</p>
        </div>
        <a class="tombol tombol-kecil" href="pengguna.php?edit=0#formPengguna"><?= ikon('plus', 15) ?> Tambah Akun</a>
    </div>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th style="width:60px">Foto</th><th>Nama</th><th>Nama Pengguna</th><th>Peran</th><th>Kontak</th><th>Masuk Terakhir</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($daftar as $u): ?>
                    <tr>
                        <td><?php if ((string) $u['foto'] !== ''): ?><img class="thumb-admin kotak-bulat" src="<?= e($u['foto']) ?>" alt="Foto <?= e($u['nama']) ?>"><?php else: ?><span class="kotak-kosong kotak-bulat thumb-admin"><?= e(strtoupper(substr((string) $u['nama'], 0, 1))) ?></span><?php endif; ?></td>
                        <td><strong><?= e($u['nama']) ?></strong><?php if ((int) $u['id'] === (int) (current_user()['id'] ?? 0)): ?> <span class="pil pil-emas">Anda</span><?php endif; ?></td>
                        <td class="kecil"><?= e($u['username']) ?></td>
                        <td class="kecil"><?= e(peran_label((string) $u['role'])) ?></td>
                        <td class="kecil"><?= e($u['email'] ?: '-') ?><?= $u['telepon'] !== '' ? '<div class="mut">' . e($u['telepon']) . '</div>' : '' ?></td>
                        <td class="kecil"><?= e(waktu_id($u['terakhir']) ?: 'belum pernah') ?></td>
                        <td><?= ((int) $u['aktif'] === 1) ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('pengguna.php?edit=' . (int) $u['id'] . '#formPengguna', 'edit', 'Ubah akun') ?>
                            <?= tombol_mini('pengguna.php?aksi=reset&id=' . (int) $u['id'], 'kunci', 'Buat kata sandi baru') ?>
                            <?= tombol_mini('pengguna.php?aksi=toggle&id=' . (int) $u['id'], 'mata', 'Aktifkan/nonaktifkan') ?>
                            <?= form_hapus('hapus', (int) $u['id'], 'Hapus akun ' . $u['nama'] . '?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="kartu" id="formPengguna">
    <h2><?= $edit ? 'Ubah Akun: ' . e($edit['nama']) : 'Tambah Akun Baru' ?></h2>
    <form method="post" action="pengguna.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-2">
            <div class="form-baris">
                <label for="nama">Nama Lengkap <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="120" value="<?= e($edit['nama'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="username">Nama Pengguna <span class="wajib">*</span></label>
                <input type="text" id="username" name="username" required maxlength="40" value="<?= e($edit['username'] ?? '') ?>">
            </div>
        </div>
        <div class="form-2">
            <div class="form-baris">
                <label for="sandi"><?= $edit ? 'Kata Sandi Baru (biarkan kosong bila tidak diubah)' : 'Kata Sandi' ?> <?= $edit ? '' : '<span class="wajib">*</span>' ?></label>
                <input type="password" id="sandi" name="sandi" <?= $edit ? '' : 'required' ?> autocomplete="new-password">
                <div class="bantuan">Minimal 6 karakter. Sandi disimpan terenkripsi dan tidak dapat ditampilkan kembali.</div>
            </div>
            <div class="form-baris">
                <label for="role">Peran</label>
                <select id="role" name="role">
                    <?php foreach (peran_daftar() as $kode => $label): ?>
                        <option value="<?= e($kode) ?>" <?= ($edit['role'] ?? 'admin') === $kode ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="bantuan"><strong>Editor Berita</strong> hanya dapat membuka menu Berita
                    (daftar berita, tulis/ubah berita, dan kategori berita) — halaman lain ditolak.
                    <strong>Editor</strong> mengelola konten, PPDB, dan <strong>Layanan PIP</strong>.</div>
            </div>
        </div>
        <div class="form-2">
            <div class="form-baris">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="120" value="<?= e($edit['email'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="telepon">Telepon / WhatsApp</label>
                <input type="tel" id="telepon" name="telepon" maxlength="40" value="<?= e($edit['telepon'] ?? '') ?>">
            </div>
        </div>
        <?php blok_gambar('foto', (string) ($edit['foto'] ?? ''), 'Foto Profil (opsional)'); ?>
        <div class="form-baris">
            <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Akun aktif (dapat masuk)</label>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Akun</button>
            <?php if ($edit): ?><a class="tombol tombol-garis" href="pengguna.php">Batal</a><?php endif; ?>
        </div>
    </form>
</div>
<?php admin_bawah(); ?>
