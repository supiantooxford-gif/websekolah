<?php
/**
 * Galeri: album, foto (unggah sekaligus), dan video YouTube.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

/** Unggah satu berkas dari larik $_FILES['foto'] multi. */
function unggah_satu(array $f, string $label): array
{
    $_FILES['__satu'] = $f;
    $hasil = unggah_gambar('__satu', $label);
    unset($_FILES['__satu']);
    return $hasil;
}

if ($aksi === 'album_simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $tahun = album_tahun_sah((string) ($_POST['tahun'] ?? ''));
    if ($nama === '') {
        flash('Nama album wajib diisi.', 'err');
        redirect('galeri.php');
    }
    if ($tahun === '') {
        // Tahun WAJIB: permintaan pemilik — setiap album harus diketahui tahunnya. Bila berkas di
        // album itu sudah ada tanggal unggahnya, tombol "Isi Tahun dari Tanggal Unggah" dapat dipakai.
        flash('Tahun album wajib diisi (4 angka, mis. ' . date('Y') . '). Bila tahunnya belum diketahui, '
            . 'pakai tombol “Isi Tahun dari Tanggal Unggah” pada daftar album.', 'err');
        redirect('galeri.php' . ($id > 0 ? '?edit=' . $id : '') . '#formAlbum');
    }
    $s = slug($nama, 'album');
    $n = 2;
    while (one('SELECT id FROM galeri_album WHERE slug = ? AND id <> ?', [$s, $id])) {
        $s = slug($nama, 'album') . '-' . $n;
        $n++;
    }
    $lama = $id > 0 ? one('SELECT * FROM galeri_album WHERE id = ?', [$id]) : null;
    $g = proses_gambar('sampul', (string) ($lama['sampul'] ?? ''), !empty($_POST['sampul_hapus']), 'Sampul album');
    $data = [
        'nama'       => potong($nama, JUDUL_MAKS, ''),
        'slug'       => $s,
        'keterangan' => potong((string) ($_POST['keterangan'] ?? ''), 1000, ''),
        'sampul'     => $g['url'],
        'tahun'      => $tahun,
        'aktif'      => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        q('UPDATE galeri_album SET nama = ?, slug = ?, keterangan = ?, sampul = ?, tahun = ?, aktif = ? WHERE id = ?',
            [$data['nama'], $data['slug'], $data['keterangan'], $data['sampul'], $data['tahun'], $data['aktif'], $id]);
        flash('Album disimpan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    } else {
        $data['urutan'] = urutan_baru('galeri_album');
        q('INSERT INTO galeri_album (nama, slug, keterangan, sampul, tahun, aktif, urutan) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$data['nama'], $data['slug'], $data['keterangan'], $data['sampul'], $data['tahun'], $data['aktif'], $data['urutan']]);
        flash('Album ditambahkan. ' . $g['pesan'], $g['galat'] ? 'err' : 'ok');
    }
    redirect('galeri.php');
}
if ($aksi === 'album_hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    q('DELETE FROM galeri_foto WHERE album_id = ?', [$id]);
    q('DELETE FROM galeri_video WHERE album_id = ?', [$id]);
    hapus_baris('galeri_album', $id);
    flash('Album beserta foto & video di dalamnya dihapus.', 'ok');
    redirect('galeri.php');
}
if ($aksi === 'album_tahun_otomatis') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    // Isi tahun album yang MASIH KOSONG dari TAHUN tanggal unggah FOTONYA (album hanya berisi foto).
    // Album tanpa foto dilewati (tidak ada dasar tahun) — tidak ada nilai yang ditebak.
    $kandidat = all("SELECT a.id, a.nama, MIN(f.dibuat) AS dibuat FROM galeri_album a
                     JOIN galeri_foto f ON f.album_id = a.id
                     WHERE a.tahun IS NULL OR TRIM(a.tahun) = '' GROUP BY a.id, a.nama");
    $diisi = 0;
    $dilewati = [];
    foreach ($kandidat as $k) {
        $th = substr(trim((string) $k['dibuat']), 0, 4);
        if (preg_match('#^\d{4}$#', $th)) {
            q('UPDATE galeri_album SET tahun = ? WHERE id = ?', [$th, (int) $k['id']]);
            $diisi++;
        } else {
            $dilewati[] = (string) $k['nama'];
        }
    }
    $kosong = jumlah("SELECT COUNT(*) FROM galeri_album WHERE tahun IS NULL OR TRIM(tahun) = ''");
    flash('Tahun diisi dari tanggal unggah berkas untuk ' . $diisi . ' album.'
        . ($dilewati ? ' Belum ada tanggal unggah: ' . implode(', ', $dilewati) . '.' : '')
        . ($kosong > 0 ? ' Masih ada ' . $kosong . ' album tanpa tahun — isi manual pada kolom Tahun.' : ''),
        $diisi > 0 ? 'ok' : 'info');
    redirect('galeri.php');
}
if ($aksi === 'album_toggle') {
    q('UPDATE galeri_album SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('galeri.php');
}
if ($aksi === 'foto_unggah') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    if (kiriman_terlalu_besar()) {
        flash('Total ukuran berkas melebihi batas server (' . ini_get('post_max_size') . '). Unggah foto dalam jumlah lebih sedikit.', 'err');
        redirect('galeri.php?album=' . (int) ($_POST['album_id'] ?? 0));
    }
    $albumId = (int) ($_POST['album_id'] ?? 0);
    if ($albumId <= 0 || !one('SELECT id FROM galeri_album WHERE id = ?', [$albumId])) {
        flash('Pilih album tujuan terlebih dahulu.', 'err');
        redirect('galeri.php');
    }
    $ket = potong((string) ($_POST['keterangan'] ?? ''), 200, '');
    $berhasil = 0;
    $galat = [];
    if (!empty($_FILES['foto']['name']) && is_array($_FILES['foto']['name'])) {
        $jml = count($_FILES['foto']['name']);
        for ($i = 0; $i < $jml; $i++) {
            $f = [
                'name' => (string) $_FILES['foto']['name'][$i],
                'type' => (string) ($_FILES['foto']['type'][$i] ?? ''),
                'tmp_name' => (string) $_FILES['foto']['tmp_name'][$i],
                'error' => (int) $_FILES['foto']['error'][$i],
                'size' => (int) $_FILES['foto']['size'][$i],
            ];
            if ((int) $f['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $up = unggah_satu($f, 'Foto ' . ($i + 1));
            if (!$up['ok']) {
                $galat[] = $f['name'] . ': ' . $up['pesan'];
                continue;
            }
            q('INSERT INTO galeri_foto (album_id, gambar, keterangan, urutan, dibuat) VALUES (?, ?, ?, ?, ?)',
                [$albumId, $up['url'], $ket, urutan_baru('galeri_foto', ['album_id' => $albumId]), date('Y-m-d H:i:s')]);
            $berhasil++;
        }
    }
    if ($berhasil > 0) {
        flash($berhasil . ' foto berhasil diunggah.', 'ok');
    }
    foreach ($galat as $g) {
        flash($g, 'err');
    }
    if ($berhasil === 0 && !$galat) {
        flash('Tidak ada berkas yang dipilih.', 'err');
    }
    redirect('galeri.php?album=' . $albumId);
}
if ($aksi === 'foto_hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('galeri_foto', (int) ($_POST['id'] ?? 0));
    flash('Foto dihapus.', 'ok');
    redirect('galeri.php?album=' . (int) ($_POST['album_id'] ?? 0));
}
if ($aksi === 'video_simpan') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    $id = (int) ($_POST['id'] ?? 0);
    $judul = trim((string) ($_POST['judul'] ?? ''));
    $yt = trim((string) ($_POST['youtube'] ?? ''));
    if ($judul === '' || youtube_id($yt) === '') {
        flash('Judul wajib diisi dan tautan YouTube harus sah (mis. https://youtu.be/XXXXXXXXXXX).', 'err');
        redirect('galeri.php?tab=video' . ($id > 0 ? '&edit=' . $id : ''));
    }
    $data = [
        'judul'      => potong($judul, JUDUL_MAKS, ''),
        'youtube'    => youtube_id($yt),
        'keterangan' => potong((string) ($_POST['keterangan'] ?? ''), 1000, ''),
        // Video TIDAK dikelompokkan ke album (permintaan pemilik, v1.7.8): semuanya tampil pada
        // bagian "Video Kegiatan" di halaman galeri. Kolom `album_id` tetap ada di basis data
        // (dibiarkan 0) supaya tidak ada tabel yang perlu dibangun ulang.
        'album_id'   => 0,
        'aktif'      => empty($_POST['aktif']) ? 0 : 1,
    ];
    if ($id > 0) {
        q('UPDATE galeri_video SET judul = ?, youtube = ?, keterangan = ?, album_id = ?, aktif = ? WHERE id = ?',
            [$data['judul'], $data['youtube'], $data['keterangan'], $data['album_id'], $data['aktif'], $id]);
        flash('Video disimpan.', 'ok');
    } else {
        q('INSERT INTO galeri_video (judul, youtube, keterangan, album_id, aktif, urutan, dibuat) VALUES (?, ?, ?, ?, ?, ?, ?)',
            // v1.17.1 — permintaan pemilik: video/tautan YouTube yang BARU disimpan otomatis
            // berada PALING ATAS daftar (juga paling atas pada bagian "Video Kegiatan" di website).
            [$data['judul'], $data['youtube'], $data['keterangan'], $data['album_id'], $data['aktif'],
                urutan_teratas('galeri_video'), date('Y-m-d H:i:s')]);
        flash('Video ditambahkan dan diletakkan paling atas.', 'ok');
    }
    redirect('galeri.php?tab=video');
}
if ($aksi === 'video_hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('galeri_video', (int) ($_POST['id'] ?? 0));
    flash('Video dihapus.', 'ok');
    redirect('galeri.php?tab=video');
}
if ($aksi === 'video_toggle') {
    q('UPDATE galeri_video SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('galeri.php?tab=video');
}
if ($aksi === 'video_naik' || $aksi === 'video_turun') {
    geser_urutan('galeri_video', (int) ($_GET['id'] ?? 0), $aksi === 'video_naik' ? 'naik' : 'turun');
    redirect('galeri.php?tab=video');
}

$albumId = (int) ($_GET['album'] ?? 0);
$tab = (string) ($_GET['tab'] ?? '');
$editAlbum = (int) ($_GET['edit'] ?? 0) > 0 && $tab === '' ? one('SELECT * FROM galeri_album WHERE id = ?', [(int) $_GET['edit']]) : null;
$editVideo = (int) ($_GET['edit'] ?? 0) > 0 && $tab === 'video' ? one('SELECT * FROM galeri_video WHERE id = ?', [(int) $_GET['edit']]) : null;
$tahunSaring = (int) ($_GET['tahun'] ?? 0);
$cariAlbum = trim((string) ($_GET['cari'] ?? ''));
$wAlbum = [];
$pAlbum = [];
if ($tahunSaring === -1) {
    $wAlbum[] = "(a.tahun IS NULL OR TRIM(a.tahun) = '')";
} elseif ($tahunSaring > 0) {
    $wAlbum[] = 'a.tahun = ?';
    $pAlbum[] = (string) $tahunSaring;
}
if ($cariAlbum !== '') {
    $wAlbum[] = '(a.nama LIKE ? OR a.keterangan LIKE ?)';
    $pAlbum[] = '%' . $cariAlbum . '%';
    $pAlbum[] = '%' . $cariAlbum . '%';
}
// Dua daftar: $album (SEMUA album — dipakai pemilih album & form video, supaya pilihan tidak
// hilang saat saringan aktif) dan $albumTampil (hasil saringan — hanya untuk tabel).
// Album hanya berisi FOTO (v1.7.8) — hitungan video per album tidak dipakai lagi.
$albumSemua = all('SELECT a.*, (SELECT COUNT(*) FROM galeri_foto f WHERE f.album_id = a.id) AS jml
      FROM galeri_album a ORDER BY a.tahun DESC, a.urutan, a.id');
$albumTampil = ($wAlbum || $cariAlbum !== '') ? all('SELECT a.*, (SELECT COUNT(*) FROM galeri_foto f WHERE f.album_id = a.id) AS jml
      FROM galeri_album a' . ($wAlbum ? ' WHERE ' . implode(' AND ', $wAlbum) : '') . ' ORDER BY a.tahun DESC, a.urutan, a.id', $pAlbum)
    : $albumSemua;
$tahunAlbumSemua = array_map('strval', array_column(
    all("SELECT DISTINCT tahun FROM galeri_album WHERE tahun IS NOT NULL AND TRIM(tahun) <> '' ORDER BY tahun DESC"), 'tahun'));
$albumTanpaTahun = jumlah("SELECT COUNT(*) FROM galeri_album WHERE tahun IS NULL OR TRIM(tahun) = ''");
$foto = $albumId > 0 ? all('SELECT * FROM galeri_foto WHERE album_id = ? ORDER BY urutan, id', [$albumId]) : [];
$video = all('SELECT * FROM galeri_video ORDER BY urutan, id');
$albumAktif = $albumId > 0 ? one('SELECT * FROM galeri_album WHERE id = ?', [$albumId]) : null;

admin_atas('Galeri Foto & Video');
?>
<div class="kartu mb24">
    <div class="kartu-kepala">
        <div>
            <h2>Album Galeri</h2>
            <p class="mut kecil mb0"><strong>Album berisi FOTO saja</strong> dan diberi <strong>tahun</strong> agar
                pengunjung tahu foto itu dari tahun berapa. Video dikelola pada bagian <em>Video YouTube</em> di bawah
                dan tampil pada bagian “Video Kegiatan” halaman galeri (tanpa album).</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php if ($albumTanpaTahun > 0): ?>
                <form method="post" action="galeri.php" data-konfirmasi="Isi tahun album yang masih kosong dari TANGGAL UNGGAH berkas di dalamnya?">
                    <?= csrf_field() ?><input type="hidden" name="aksi" value="album_tahun_otomatis">
                    <button type="submit" class="tombol tombol-garis tombol-kecil"><?= ikon('kalender', 15) ?> Isi Tahun dari Tanggal Unggah (<?= (int) $albumTanpaTahun ?>)</button>
                </form>
            <?php endif; ?>
            <a class="tombol tombol-kecil" href="galeri.php?edit=0#formAlbum"><?= ikon('plus', 15) ?> Tambah Album</a>
        </div>
    </div>
    <?php if ($albumTanpaTahun > 0): ?>
        <div class="alert alert-info">
            <strong><?= (int) $albumTanpaTahun ?> album belum diberi tahun.</strong>
            Buka <em>Ubah</em> pada album itu lalu isi <strong>Tahun</strong> (mis. 2026), atau pakai tombol
            “Isi Tahun dari Tanggal Unggah”. Album tanpa tahun tetap tampil di website, hanya belum ada keterangan tahunnya.
        </div>
    <?php endif; ?>
    <form class="saringan" method="get" action="galeri.php">
        <div class="medan">
            <label for="saringTahunAlbum">Saring Tahun</label>
            <select id="saringTahunAlbum" name="tahun" data-auto>
                <option value="0">Semua tahun</option>
                <?php foreach ($tahunAlbumSemua as $th): ?>
                    <option value="<?= (int) $th ?>" <?= $tahunSaring === (int) $th ? 'selected' : '' ?>><?= e($th) ?></option>
                <?php endforeach; ?>
                <?php if ($albumTanpaTahun > 0): ?>
                    <option value="-1" <?= $tahunSaring === -1 ? 'selected' : '' ?>>Belum diberi tahun</option>
                <?php endif; ?>
            </select>
        </div>
        <div class="medan">
            <label for="saringCariAlbum">Cari Album</label>
            <input type="search" id="saringCariAlbum" name="cari" value="<?= e($cariAlbum) ?>" placeholder="nama album / keterangan">
        </div>
        <?php if ($tahunSaring !== 0 || $cariAlbum !== ''): ?>
            <a class="tombol tombol-garis tombol-kecil" href="galeri.php">Reset</a>
        <?php endif; ?>
    </form>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th style="width:100px">Sampul</th><th>Nama Album</th><th style="width:90px">Tahun</th><th style="width:110px">Jumlah Foto</th><th style="width:110px">Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$albumTampil): ?><tr><td colspan="6" class="mut">Tidak ada album yang cocok dengan saringan.</td></tr><?php endif; ?>
                <?php foreach ($albumTampil as $a): ?>
                    <tr>
                        <td><?= kotak_gambar($a['sampul'], 'Album', 'thumb-admin') ?></td>
                        <td>
                            <strong><?= e($a['nama']) ?></strong>
                            <span class="pil pil-emas"><?= e(album_label($a)) ?></span>
                            <div class="kecil mut"><?= e(potong($a['keterangan'], 80)) ?></div>
                        </td>
                        <td class="kecil"><?= (string) ($a['tahun'] ?? '') !== '' ? e((string) $a['tahun']) : '<span class="pil pil-baru">belum diisi</span>' ?></td>
                        <td class="kecil"><?= angka($a['jml']) ?> foto</td>
                        <td><?= ((int) $a['aktif'] === 1) ? '<span class="pil pil-terima">Aktif</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <a class="tombol tombol-garis tombol-kecil" href="galeri.php?album=<?= (int) $a['id'] ?>#kelolaFoto">Kelola Foto</a>
                            <?= tombol_mini('galeri.php?edit=' . (int) $a['id'] . '#formAlbum', 'edit', 'Ubah album') ?>
                            <?= tombol_mini('galeri.php?aksi=album_toggle&id=' . (int) $a['id'], 'mata', 'Aktifkan/nonaktifkan') ?>
                            <?= form_hapus('album_hapus', (int) $a['id'], 'Hapus album “' . $a['nama'] . '” beserta seluruh foto & video di dalamnya?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <h3 class="mt24"><?= $editAlbum ? 'Ubah Album' : 'Tambah Album' ?></h3>
    <form method="post" action="galeri.php" enctype="multipart/form-data" id="formAlbum">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="album_simpan">
        <input type="hidden" name="id" value="<?= (int) ($editAlbum['id'] ?? 0) ?>">
        <div class="form-3">
            <div class="form-baris">
                <label for="nama">Nama Album <span class="wajib">*</span></label>
                <input type="text" id="nama" name="nama" required maxlength="160" list="saranAlbum" value="<?= e($editAlbum['nama'] ?? '') ?>">
                <div class="bantuan">Tulis nama kegiatannya saja, mis. <em>Pramuka</em> atau <em>Kegiatan Pramuka</em>.</div>
                <datalist id="saranAlbum">
                    <?php foreach ($albumSemua as $a): ?><option value="<?= e((string) $a['nama']) ?>"></option><?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-baris">
                <label for="tahun">Tahun <span class="wajib">*</span></label>
                <input type="number" id="tahun" name="tahun" required min="1900" max="2999" step="1" list="saranTahun" value="<?= e((string) ($editAlbum['tahun'] ?? '')) ?>" placeholder="mis. <?= date('Y') ?>">
                <div class="bantuan">Tahun foto/video album ini. Nama album pada website otomatis ditambah tahunnya.</div>
                <datalist id="saranTahun">
                    <?php foreach (range((int) date('Y') + 1, (int) date('Y') - 6) as $th): ?>
                        <option value="<?= (int) $th ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-baris">
                <label for="keterangan">Keterangan</label>
                <input type="text" id="keterangan" name="keterangan" maxlength="1000" value="<?= e($editAlbum['keterangan'] ?? '') ?>">
            </div>
        </div>
        <p class="mut kecil">Ingin album yang sama untuk tahun berbeda (mis. <strong>Pramuka 2024, 2025, 2026</strong>)?
            Buat album baru dengan <strong>nama yang sama</strong> dan <strong>tahun berbeda</strong> — di website
            album itu tampil sebagai satu kartu “Pramuka” dengan pilihan tahun.</p>
        <?php blok_gambar('sampul', (string) ($editAlbum['sampul'] ?? ''), 'Sampul Album'); ?>
        <div class="form-baris">
            <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($editAlbum['aktif']) || (int) $editAlbum['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan album</label>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Album</button>
            <?php if ($editAlbum): ?><a class="tombol tombol-garis" href="galeri.php">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="kartu mb24" id="kelolaFoto">
    <div class="kartu-kepala">
        <div>
            <h2>Kelola Foto<?= $albumAktif ? ': ' . e($albumAktif['nama']) : '' ?></h2>
            <p class="mut kecil mb0">Pilih album lalu unggah beberapa foto sekaligus (JPG/PNG/GIF/WEBP/SVG, maksimal 15 MB per foto).</p>
        </div>
    </div>
    <form class="saringan" method="get" action="galeri.php">
        <div class="medan">
            <label for="album">Pilih Album</label>
            <select id="album" name="album" data-auto>
                <option value="0">— pilih album —</option>
                <?php foreach ($albumSemua as $a): ?>
                    <option value="<?= (int) $a['id'] ?>" <?= $albumId === (int) $a['id'] ? 'selected' : '' ?>><?= e(album_label($a)) ?> (<?= (int) $a['jml'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($albumId > 0): ?>
        <form method="post" action="galeri.php" enctype="multipart/form-data" class="mb24">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="foto_unggah">
            <input type="hidden" name="album_id" value="<?= (int) $albumId ?>">
            <div class="form-2">
                <div class="form-baris">
                    <label for="foto">Pilih Foto (boleh beberapa sekaligus)</label>
                    <input type="file" id="foto" name="foto[]" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" multiple required>
                </div>
                <div class="form-baris">
                    <label for="ket_foto">Keterangan (dipakai untuk semua foto yang diunggah)</label>
                    <input type="text" id="ket_foto" name="keterangan" maxlength="200">
                </div>
            </div>
            <div class="aksi-form">
                <button type="submit" class="tombol"><?= ikon('unggah', 17) ?> Unggah Foto</button>
            </div>
        </form>

        <?php if (!$foto): ?>
            <p class="mut">Belum ada foto pada album ini.</p>
        <?php else: ?>
            <div class="kelola-foto">
                <?php foreach ($foto as $f): ?>
                    <div class="kelola-foto-item">
                        <?= kotak_gambar($f['gambar'], 'Foto') ?>
                        <div class="isi">
                            <div><?= e($f['keterangan'] ?: '(tanpa keterangan)') ?></div>
                            <div class="aksi">
                                <?= form_hapus('foto_hapus', (int) $f['id'], 'Hapus foto ini?', ['album_id' => $albumId]) ?>
                                <a class="tombol-ikon" target="_blank" rel="noopener" href="<?= e($f['gambar']) ?>" title="Lihat ukuran penuh"><?= ikon('mata', 15) ?></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="mut mb0">Pilih salah satu album di atas untuk mengelola fotonya.</p>
    <?php endif; ?>
</div>

<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2>Video YouTube</h2>
            <p class="mut kecil mb0">Tempelkan tautan video YouTube (boleh tautan biasa, youtu.be, atau shorts) — video
                akan tampil sebagai embed pada bagian <strong>“Video Kegiatan”</strong> di halaman galeri.
                Video <strong>tidak</strong> dimasukkan ke album. <strong>Video yang baru disimpan
                otomatis diletakkan paling atas</strong>; gunakan tombol ▲▼ bila ingin menaruhnya di
                urutan lain.</p>
        </div>
    </div>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th style="width:48px">Urut</th><th>Judul</th><th>Video</th><th>Status</th><th class="aksi-sel">Aksi</th></tr></thead>
            <tbody>
                <?php if (!$video): ?><tr><td colspan="5" class="mut">Belum ada video.</td></tr><?php endif; ?>
                <?php foreach ($video as $v): ?>
                    <tr>
                        <td class="aksi-sel">
                            <?= tombol_mini('galeri.php?aksi=video_naik&id=' . (int) $v['id'], 'naik', 'Naikkan') ?>
                            <?= tombol_mini('galeri.php?aksi=video_turun&id=' . (int) $v['id'], 'turun', 'Turunkan') ?>
                        </td>
                        <td><strong><?= e($v['judul']) ?></strong><div class="kecil mut"><?= e(potong($v['keterangan'], 70)) ?></div></td>
                        <td class="kecil">
                            <a href="https://youtu.be/<?= e(youtube_id((string) $v['youtube'])) ?>" target="_blank" rel="noopener noreferrer"><?= e(youtube_id((string) $v['youtube']) ?: 'tidak sah') ?></a>
                        </td>
                        <td><?= ((int) $v['aktif'] === 1) ? '<span class="pil pil-terima">Tampil</span>' : '<span class="pil pil-baru">Nonaktif</span>' ?></td>
                        <td class="aksi-sel">
                            <?= tombol_mini('galeri.php?tab=video&edit=' . (int) $v['id'] . '#formVideo', 'edit', 'Ubah video') ?>
                            <?= tombol_mini('galeri.php?aksi=video_toggle&id=' . (int) $v['id'], 'mata', 'Tampilkan/sembunyikan') ?>
                            <?= form_hapus('video_hapus', (int) $v['id'], 'Hapus video ini?') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <h3 class="mt24"><?= $editVideo ? 'Ubah Video' : 'Tambah Video' ?></h3>
    <form method="post" action="galeri.php" id="formVideo">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="video_simpan">
        <input type="hidden" name="id" value="<?= (int) ($editVideo['id'] ?? 0) ?>">
        <div class="form-2">
            <div class="form-baris">
                <label for="vjudul">Judul Video <span class="wajib">*</span></label>
                <input type="text" id="vjudul" name="judul" required maxlength="160" value="<?= e($editVideo['judul'] ?? '') ?>">
            </div>
            <div class="form-baris">
                <label for="vyoutube">Tautan YouTube <span class="wajib">*</span></label>
                <input type="text" id="vyoutube" name="youtube" required maxlength="300" placeholder="https://www.youtube.com/watch?v=XXXXXXXXXXX" value="<?= e($editVideo['youtube'] ?? '') ?>">
            </div>
        </div>
        <div class="form-baris">
            <label for="vketerangan">Keterangan</label>
            <input type="text" id="vketerangan" name="keterangan" maxlength="1000" value="<?= e($editVideo['keterangan'] ?? '') ?>">
            <div class="bantuan">Video tampil pada bagian <strong>“Video Kegiatan”</strong> halaman galeri —
                urutannya diatur dengan tombol ▲▼ pada daftar di atas.</div>
        </div>
        <div class="form-baris">
            <label class="rasta" style="gap:9px;font-weight:500"><input type="checkbox" name="aktif" value="1" <?= !isset($editVideo['aktif']) || (int) $editVideo['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan video</label>
        </div>
        <div class="aksi-form">
            <button type="submit" class="tombol"><?= ikon('simpan', 17) ?> Simpan Video</button>
            <?php if ($editVideo): ?><a class="tombol tombol-garis" href="galeri.php?tab=video">Batal</a><?php endif; ?>
        </div>
    </form>
</div>
<?php admin_bawah(); ?>
