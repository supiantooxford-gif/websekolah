<?php
/**
 * Pesan masuk dari formulir kontak website.
 */
declare(strict_types=1);
require_once __DIR__ . '/../inc/init.php';
require_once __DIR__ . '/../inc/admin_helpers.php';
require_login();

$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'baca') {
    q('UPDATE kontak_pesan SET dibaca = CASE dibaca WHEN 1 THEN 0 ELSE 1 END WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
    redirect('pesan.php');
}
if ($aksi === 'baca_semua') {
    q('UPDATE kontak_pesan SET dibaca = 1 WHERE dibaca = 0');
    flash('Semua pesan ditandai sudah dibaca.', 'ok');
    redirect('pesan.php');
}
if ($aksi === 'hapus') {
    if (!csrf_sah()) {
        csrf_gagal();
    }
    hapus_baris('kontak_pesan', (int) ($_POST['id'] ?? 0));
    flash('Pesan dihapus.', 'ok');
    redirect('pesan.php');
}

$filter = (string) ($_GET['saring'] ?? '');
$w = $filter === 'belum' ? 'WHERE dibaca = 0' : ($filter === 'sudah' ? 'WHERE dibaca = 1' : '');
$cari = trim((string) ($_GET['cari'] ?? ''));
$p = [];
if ($cari !== '') {
    $w = $w === '' ? 'WHERE' : $w . ' AND';
    $w .= ' (nama LIKE ? OR pesan LIKE ? OR subjek LIKE ? OR email LIKE ? OR telepon LIKE ?)';
    array_push($p, '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%');
}
$perHal = 15;
$hal = halaman_ini();
$total = jumlah("SELECT COUNT(*) FROM kontak_pesan $w", $p);
$daftar = all("SELECT * FROM kontak_pesan $w ORDER BY dibaca, id DESC LIMIT $perHal OFFSET " . (($hal - 1) * $perHal), $p);
$baru = jumlah('SELECT COUNT(*) FROM kontak_pesan WHERE dibaca = 0');

admin_atas('Pesan Masuk');
?>
<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2>Pesan dari Pengunjung Website</h2>
            <p class="mut kecil mb0"><?= angka($total) ?> pesan · <?= angka($baru) ?> belum dibaca. Pesan dikirim melalui formulir pada halaman Kontak.</p>
        </div>
        <?php if ($baru > 0): ?>
            <a class="tombol tombol-garis tombol-kecil" href="pesan.php?aksi=baca_semua"><?= ikon('check', 15) ?> Tandai semua sudah dibaca</a>
        <?php endif; ?>
    </div>

    <form class="saringan" method="get" action="pesan.php">
        <div class="medan">
            <label for="cari">Cari nama / isi pesan</label>
            <input type="search" id="cari" name="cari" value="<?= e($cari) ?>">
        </div>
        <div class="medan">
            <label for="saring">Status</label>
            <select id="saring" name="saring" data-auto>
                <option value="">Semua pesan</option>
                <option value="belum" <?= $filter === 'belum' ? 'selected' : '' ?>>Belum dibaca</option>
                <option value="sudah" <?= $filter === 'sudah' ? 'selected' : '' ?>>Sudah dibaca</option>
            </select>
        </div>
        <button type="submit" class="tombol tombol-kecil"><?= ikon('search', 15) ?> Terapkan</button>
        <a class="tombol tombol-garis tombol-kecil" href="pesan.php">Reset</a>
    </form>

    <?php if (!$daftar): ?>
        <p class="mut">Belum ada pesan.</p>
    <?php endif; ?>
    <?php foreach ($daftar as $m): ?>
        <div class="agenda-baris" style="align-items:flex-start;<?= (int) $m['dibaca'] === 0 ? 'background:#fdfbf3;border-radius:10px;padding-left:12px;padding-right:12px' : '' ?>">
            <div style="flex:1;min-width:0">
                <div class="rasta" style="justify-content:space-between;gap:10px;flex-wrap:wrap">
                    <strong style="font-size:.98rem">
                        <?php if ((int) $m['dibaca'] === 0): ?><span class="pil pil-tolak">baru</span> <?php endif; ?>
                        <?= e($m['nama']) ?>
                    </strong>
                    <span class="kecil mut"><?= e(waktu_id($m['waktu'])) ?> · <?= e($m['ip']) ?></span>
                </div>
                <div class="kecil mut mb8">
                    <?= e($m['subjek'] ?: '(tanpa subjek)') ?>
                    <?php if ($m['email'] !== ''): ?> · <?= e($m['email']) ?><?php endif; ?>
                    <?php if ($m['telepon'] !== ''): ?> · <?= e($m['telepon']) ?><?php endif; ?>
                </div>
                <p style="margin:0;white-space:pre-line"><?= e($m['pesan']) ?></p>
                <div class="mt8" style="display:flex;gap:8px;flex-wrap:wrap">
                    <?php if ($m['telepon'] !== ''): ?>
                        <a class="tombol tombol-kecil" target="_blank" rel="noopener" href="https://wa.me/<?= e(wa_internasional((string) $m['telepon'])) ?>?text=<?= rawurlencode('Assalamualaikum ' . $m['nama'] . ', terima kasih atas pesan Anda melalui website kami.') ?>"><?= ikon('whatsapp', 14) ?> Balas via WhatsApp</a>
                    <?php endif; ?>
                    <?php if ($m['email'] !== ''): ?>
                        <a class="tombol tombol-garis tombol-kecil" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Balasan: ' . ($m['subjek'] ?: 'Pesan melalui website')) ?>"><?= ikon('mail', 14) ?> Balas via Email</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="aksi-sel">
                <?= tombol_mini('pesan.php?aksi=baca&id=' . (int) $m['id'], 'mata', (int) $m['dibaca'] === 1 ? 'Tandai belum dibaca' : 'Tandai sudah dibaca') ?>
                <?= form_hapus('hapus', (int) $m['id'], 'Hapus pesan dari ' . $m['nama'] . '?') ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?= paginasi($total, $perHal, $hal) ?>
</div>
<?php admin_bawah(); ?>
