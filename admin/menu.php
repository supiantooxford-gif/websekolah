<?php
/**
 * Menu & Halaman — mengatur menu utama, sub menu, dan isi setiap halaman.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$JENIS = ['beranda', 'halaman', 'profil_lengkap', 'struktur_organisasi', 'bos', 'sarpras', 'perpustakaan', 'supervisi', 'voting', 'pendaftar', 'edm', 'perangkat', 'kohort', 'pip', 'guru', 'siswa', 'alumni',
    // v34 — MENU KURIKULUM: Kaldik Operasional, Jadwal Mengajar, Dokumen Kurikulum.
    'kaldik', 'jadwal_mengajar', 'dokumen_kurikulum',
    'berita', 'agenda', 'galeri', 'fasilitas', 'kontak', 'ppdb', 'tautan',
    'prestasi_siswa', 'prestasi_madrasah', 'prestasi_pendidik', 'pelatihan_pendidik'];

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');
$pesan = '';
$galat = '';

if ($aksi !== '' && in_array($aksi, ['simpan', 'hapus', 'naik', 'turun', 'toggle'], true)) {
    if ($aksi === 'naik' || $aksi === 'turun') {
        $id = (int) ($_GET['id'] ?? 0);
        $m = menu_by_id($id);
        if ($m) {
            geser_urutan('menu', $id, $aksi, ['induk_id' => (int) $m['induk_id']]);
        }
        redirect('menu.php' . ((int) ($m['induk_id'] ?? 0) > 0 ? '?induk=' . (int) $m['induk_id'] : ''));
    }
    if ($aksi === 'toggle') {
        $id = (int) ($_GET['id'] ?? 0);
        q('UPDATE menu SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [$id]);
        redirect('menu.php');
    }
    if (!csrf_sah()) {
        csrf_gagal();
    }
    if ($aksi === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        $m = menu_by_id($id);
        if (!$m) {
            flash('Menu tidak ditemukan.', 'err');
        } elseif ($m['jenis'] === 'beranda') {
            flash('Menu Beranda tidak dapat dihapus.', 'err');
        } else {
            // Anak menu ikut terhapus agar tidak ada menu yatim.
            q('DELETE FROM menu WHERE induk_id = ?', [$id]);
            hapus_baris('menu', $id);
            flash('Menu “' . e($m['label']) . '” dihapus.', 'ok');
        }
        redirect('menu.php');
    }

    // ---------------- simpan (tambah / ubah) ----------------
    $id = (int) ($_POST['id'] ?? 0);
    $indukId = (int) ($_POST['induk_id'] ?? 0);
    $label = trim((string) ($_POST['label'] ?? ''));
    $slugIsi = trim((string) ($_POST['slug'] ?? ''));
    $slug = slug($slugIsi !== '' ? $slugIsi : ($label !== '' ? $label : 'halaman'), 'halaman');
    $jenis = in_array((string) ($_POST['jenis'] ?? ''), $JENIS, true) ? (string) $_POST['jenis'] : 'halaman';
    $isi = potong_html(bersihkan_html((string) ($_POST['isi'] ?? '')), TEKS_PANJANG_MAKS);

    if ($label === '') {
        $galat = 'Label menu wajib diisi.';
    }
    if ($galat === '') {
        $bentrok = one('SELECT id FROM menu WHERE slug = ? AND id <> ?', [$slug, $id]);
        $n = 2;
        while ($bentrok) {
            $slug = slug($label, 'halaman') . '-' . $n;
            $bentrok = one('SELECT id FROM menu WHERE slug = ? AND id <> ?', [$slug, $id]);
            $n++;
        }
        $data = [
            'induk_id'   => $indukId,
            'label'      => potong($label, JUDUL_MAKS, ''),
            'slug'       => $slug,
            'jenis'      => $jenis,
            'tautan'     => potong((string) ($_POST['tautan'] ?? ''), URL_MAKS, ''),
            'judul'      => potong((string) ($_POST['judul'] ?? ''), JUDUL_MAKS, ''),
            'subjudul'   => potong((string) ($_POST['subjudul'] ?? ''), 240, ''),
            'isi'        => $isi,
            'tampil_nav' => empty($_POST['tampil_nav']) ? 0 : 1,
            'sorot'      => empty($_POST['sorot']) ? 0 : 1,
            'aktif'      => empty($_POST['aktif']) ? 0 : 1,
        ];
        if ($id > 0) {
            $set = [];
            $val = [];
            foreach ($data as $k => $v) {
                $set[] = $k . ' = ?';
                $val[] = $v;
            }
            $val[] = $id;
            q('UPDATE menu SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
            flash('Menu “' . e($data['label']) . '” disimpan.', 'ok');
        } else {
            $data['urutan'] = menu_urutan_baru($indukId);
            $kol = array_keys($data);
            q('INSERT INTO menu (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
            $id = id_baru();
            flash('Menu “' . e($data['label']) . '” ditambahkan.', 'ok');
        }
        redirect('menu.php?edit=' . $id);
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId > 0 ? menu_by_id($editId) : null;
$indukTerpilih = (int) ($_GET['induk'] ?? ($edit['induk_id'] ?? 0));

$semua = all('SELECT * FROM menu ORDER BY induk_id, urutan, id');
$indukList = array_values(array_filter($semua, static fn (array $m): bool => (int) $m['induk_id'] === 0));

admin_atas('Menu & Halaman');
?>
<?php if ($galat !== ''): ?><div class="alert alert-err"><?= e($galat) ?></div><?php endif; ?>

<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Struktur Menu Website</h2>
            <p class="mut kecil mb0">Menu induk dan sub menu dapat diubah namanya, ditambah, dinonaktifkan, diurutkan, bahkan dihapus. Jenis menu menentukan tampilan halamannya.</p>
        </div>
        <a class="tombol tombol-kecil" href="menu.php?edit=0#formMenu"><?= ikon('plus', 15) ?> Tambah Menu</a>
    </div>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead>
                <tr><th style="width:52px">Urut</th><th>Label</th><th>Jenis</th><th>Alamat</th><th>Tampil di Navbar</th><th>Status</th><th class="aksi-sel">Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($indukList as $m): ?>
                    <tr>
                        <td class="aksi-sel"><?= tombol_urut('menu.php', (int) $m['id'], ['induk' => 0]) ?></td>
                        <td>
                            <strong><?= e($m['label']) ?></strong>
                            <?php if ((int) $m['sorot'] === 1): ?> <span class="pil pil-emas">tombol</span><?php endif; ?>
                            <div class="kecil mut">/<?= e($m['slug']) ?></div>
                        </td>
                        <td class="kecil"><?= e(jenis_menu_label((string) $m['jenis'])) ?></td>
                        <td class="kecil mut"><?= e(menu_url($m)) ?></td>
                        <td><?= ((int) $m['tampil_nav'] === 1) ? 'Ya' : '<span class="mut">Tidak</span>' ?></td>
                        <td><?= ((int) $m['aktif'] === 1) ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('menu.php?edit=' . (int) $m['id'], 'edit', 'Ubah menu & isi halaman') ?>
                            <?= tombol_mini('menu.php?aksi=toggle&id=' . (int) $m['id'], 'mata', 'Aktifkan/nonaktifkan') ?>
                            <?= form_hapus('hapus', (int) $m['id'], 'Hapus menu “' . $m['label'] . '” beserta sub menunya?') ?>
                        </td>
                    </tr>
                    <?php foreach (menu_anak((int) $m['id'], false) as $a): ?>
                        <tr style="background:#fbfdfc">
                            <td class="aksi-sel" style="padding-left:18px"><?= tombol_urut('menu.php', (int) $a['id'], ['induk' => (int) $m['id']]) ?></td>
                            <td style="padding-left:26px">
                                <span class="mut">&rdsh;</span> <?= e($a['label']) ?>
                                <div class="kecil mut">/<?= e($a['slug']) ?></div>
                            </td>
                            <td class="kecil"><?= e(jenis_menu_label((string) $a['jenis'])) ?></td>
                            <td class="kecil mut"><?= e(menu_url($a)) ?></td>
                            <td><?= ((int) $a['tampil_nav'] === 1) ? 'Ya' : '<span class="mut">Tidak</span>' ?></td>
                            <td><?= ((int) $a['aktif'] === 1) ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                            <td class="aksi-sel">
                                <?= tombol_mini('menu.php?edit=' . (int) $a['id'], 'edit', 'Ubah sub menu') ?>
                                <?= tombol_mini('menu.php?aksi=toggle&id=' . (int) $a['id'], 'mata', 'Aktifkan/nonaktifkan') ?>
                                <?= form_hapus('hapus', (int) $a['id'], 'Hapus sub menu “' . $a['label'] . '”?') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="kartu" id="formMenu">
    <h2><?= $edit ? 'Ubah Menu: ' . e($edit['label']) : 'Tambah Menu Baru' ?></h2>
    <form method="post" action="menu.php">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="form-3">
            <div class="form-baris">
                <label for="label">Label Menu <span class="wajib">*</span></label>
                <input type="text" id="label" name="label" required maxlength="160" value="<?= e($edit['label'] ?? '') ?>">
                <div class="bantuan">Tulisan yang tampil pada menu navigasi.</div>
            </div>
            <div class="form-baris">
                <label for="induk_id">Posisi</label>
                <select id="induk_id" name="induk_id">
                    <option value="0">Menu utama</option>
                    <?php foreach ($indukList as $m): ?>
                        <?php if ((int) ($edit['id'] ?? 0) === (int) $m['id']) { continue; } ?>
                        <option value="<?= (int) $m['id'] ?>" <?= $indukTerpilih === (int) $m['id'] ? 'selected' : '' ?>>Sub menu dari: <?= e($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-baris">
                <label for="jenis">Jenis Halaman</label>
                <select id="jenis" name="jenis">
                    <?php foreach ($JENIS as $j): ?>
                        <option value="<?= e($j) ?>" <?= ($edit['jenis'] ?? 'halaman') === $j ? 'selected' : '' ?>><?= e(jenis_menu_label($j)) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="bantuan">Jenis “Berita”, “Agenda”, “Galeri”, “Fasilitas”, “Kontak”, dan “PPDB” menampilkan daftar/halaman khusus yang isinya diatur pada menu lain di panel ini.</div>
            </div>
        </div>
        <div class="form-3">
            <div class="form-baris">
                <label for="slug">Slug / Alamat</label>
                <input type="text" id="slug" name="slug" maxlength="90" value="<?= e($edit['slug'] ?? '') ?>">
                <div class="bantuan">Dibuat otomatis dari label bila dikosongkan. Digunakan pada alamat halaman.</div>
            </div>
            <div class="form-baris">
                <label for="tautan">Tautan Luar (untuk jenis “Tautan/alamat luar”)</label>
                <input type="url" id="tautan" name="tautan" maxlength="300" placeholder="https://..." value="<?= e($edit['tautan'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label>Status</label>
                <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($edit['aktif']) || (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Aktif (ditampilkan)</label>
                <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="tampil_nav" value="1" <?= !isset($edit['tampil_nav']) || (int) $edit['tampil_nav'] === 1 ? 'checked' : '' ?>> Tampil di navbar</label>
                <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="sorot" value="1" <?= (int) ($edit['sorot'] ?? 0) === 1 ? 'checked' : '' ?>> Tampilkan sebagai tombol (disorot)</label>
            </div>
        </div>
        <div class="form-baris">
            <label for="judul">Judul Halaman</label>
            <input type="text" id="judul" name="judul" maxlength="160" value="<?= e($edit['judul'] ?? '') ?>">
            <div class="bantuan">Judul yang tampil pada spanduk halaman (bila kosong memakai label menu).</div>
        </div>
        <div class="form-baris">
            <label for="subjudul">Anak Judul / Keterangan Singkat</label>
            <input type="text" id="subjudul" name="subjudul" maxlength="240" value="<?= e($edit['subjudul'] ?? '') ?>">
        </div>
        <div class="form-baris">
            <label for="isi">Isi Halaman</label>
            <?php editor_teks('isi', (string) ($edit['isi'] ?? ''), 'isi', 320, 'Gambar yang disisipkan menyesuaikan lebar halaman secara otomatis.'); ?>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Menu</button>
            <?php if ($edit): ?>
                <a class="tombol tombol-garis" href="menu.php">Batal &amp; Tambah Baru</a>
                <a class="tombol tombol-garis" target="_blank" rel="noopener" href="../<?= e(menu_url($edit)) ?>"><?= ikon('mata', 16) ?> Lihat Halaman</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php admin_bawah(); ?>
