<?php
/**
 * Berita — tulis/ubah berita.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$lama = $id > 0 ? one('SELECT * FROM berita WHERE id = ?', [$id]) : null;
if ($id > 0 && !$lama) {
    flash('Berita tidak ditemukan.', 'err');
    redirect('berita.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $judul = trim((string) ($_POST['judul'] ?? ''));
    if ($judul === '') {
        flash('Judul berita wajib diisi.', 'err');
        redirect('berita_edit.php' . ($id > 0 ? '?id=' . $id : ''));
    }
    $slugIsi = trim((string) ($_POST['slug'] ?? ''));
    $slugTeks = slug($slugIsi !== '' ? $slugIsi : $judul, 'berita');
    $n = 2;
    while (one('SELECT id FROM berita WHERE slug = ? AND id <> ?', [$slugTeks, $id])) {
        $slugTeks = slug($judul, 'berita') . '-' . $n;
        $n++;
    }
    $g = proses_gambar('gambar', (string) ($lama['gambar'] ?? ''), !empty($_POST['gambar_hapus']), 'Gambar berita');

    $data = [
        'judul'       => potong($judul, JUDUL_MAKS, ''),
        'slug'        => $slugTeks,
        'kategori_id' => (int) ($_POST['kategori_id'] ?? 0),
        'ringkasan'   => potong((string) ($_POST['ringkasan'] ?? ''), RINGKASAN_MAKS, ''),
        'isi'         => potong_html(bersihkan_html((string) ($_POST['isi'] ?? '')), TEKS_PANJANG_MAKS),
        'gambar'      => $g['url'],
        'penulis'     => potong((string) ($_POST['penulis'] ?? ''), NAMA_MAKS, ''),
        'tanggal'     => (string) ($_POST['tanggal'] ?? '') !== '' ? (string) $_POST['tanggal'] : date('Y-m-d'),
        'terbit'      => empty($_POST['terbit']) ? 0 : 1,
    ];
    if ($id > 0) {
        $set = [];
        $val = [];
        foreach ($data as $k => $v) {
            $set[] = $k . ' = ?';
            $val[] = $v;
        }
        $set[] = "diperbarui = datetime('now','localtime')";
        $val[] = $id;
        q('UPDATE berita SET ' . implode(', ', $set) . ' WHERE id = ?', $val);
        flash('Berita disimpan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    } else {
        $data['dilihat'] = 0;
        $data['dibuat'] = date('Y-m-d H:i:s');
        $kol = array_keys($data);
        q('INSERT INTO berita (' . implode(', ', $kol) . ') VALUES (' . implode(', ', array_fill(0, count($kol), '?')) . ')', array_values($data));
        $id = id_baru();
        flash('Berita baru ditambahkan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    }
    redirect('berita_edit.php?id=' . $id);
}

$kategori = all('SELECT * FROM berita_kategori WHERE aktif = 1 ORDER BY urutan, id');

admin_atas($lama ? 'Ubah Berita' : 'Tulis Berita');
?>
<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2><?= $lama ? 'Ubah Berita' : 'Tulis Berita Baru' ?></h2>
            <p class="mut kecil mb0">Berita yang berstatus “Terbit” langsung tampil pada halaman berita dan beranda.</p>
        </div>
        <div class="rasta" style="gap:10px">
            <a class="tombol tombol-garis tombol-kecil" href="berita.php"><?= ikon('panah-kiri', 15) ?> Daftar Berita</a>
            <a class="tombol tombol-garis tombol-kecil" href="berita_kategori.php">Kategori</a>
        </div>
    </div>
    <form method="post" action="berita_edit.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-3">
            <div class="form-baris">
                <label for="tanggal">Tanggal</label>
                <input type="date" id="tanggal" name="tanggal" value="<?= e($lama['tanggal'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-baris">
                <label for="kategori_id">Kategori</label>
                <select id="kategori_id" name="kategori_id">
                    <option value="0">Tanpa kategori</option>
                    <?php foreach ($kategori as $k): ?>
                        <option value="<?= (int) $k['id'] ?>" <?= (int) ($lama['kategori_id'] ?? 0) === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-baris">
                <label for="penulis">Penulis</label>
                <input type="text" id="penulis" name="penulis" maxlength="120" value="<?= e($lama['penulis'] ?? '') ?>">
            </div>
        </div>
        <div class="form-baris">
            <label for="judul">Judul Berita <span class="wajib">*</span></label>
            <input type="text" id="judul" name="judul" required maxlength="160" value="<?= e($lama['judul'] ?? '') ?>">
        </div>
        <div class="form-2">
            <div class="form-baris">
                <label for="slug">Slug / Alamat Berita</label>
                <input type="text" id="slug" name="slug" maxlength="90" value="<?= e($lama['slug'] ?? '') ?>">
                <div class="bantuan">Dibuat otomatis dari judul bila dikosongkan.</div>
            </div>
            <div class="form-baris">
                <label>Status Terbit</label>
                <label class="rasta" style="gap:9px;font-weight:500">
                    <input type="checkbox" name="terbit" value="1" <?= !isset($lama['terbit']) || (int) $lama['terbit'] === 1 ? 'checked' : '' ?>> Terbitkan berita ini
                </label>
            </div>
        </div>
        <div class="form-baris">
            <label for="ringkasan">Ringkasan</label>
            <textarea id="ringkasan" name="ringkasan" maxlength="400" style="min-height:80px"><?= e($lama['ringkasan'] ?? '') ?></textarea>
            <div class="bantuan">Tampil pada daftar berita dan beranda (maksimal 400 karakter).</div>
        </div>
        <?php blok_gambar('gambar', (string) ($lama['gambar'] ?? ''), 'Gambar Utama Berita', true, 'Disarankan 1200 x 700 px, maksimal 15 MB.'); ?>
        <div class="form-baris">
            <label for="isi">Isi Berita</label>
            <?php editor_teks('isi', (string) ($lama['isi'] ?? ''), 'isi', 380, 'Gambar yang disisipkan menyesuaikan lebar halaman secara otomatis.'); ?>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Berita</button>
            <?php if ($lama): ?>
                <a class="tombol tombol-garis" target="_blank" rel="noopener" href="../berita/<?= e($lama['slug']) ?>"><?= ikon('mata', 16) ?> Lihat di Website</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php admin_bawah(); ?>
