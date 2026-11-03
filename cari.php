<?php
/**
 * Pencarian isi website.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// ALAMAT RAPI: alamat lama `cari.php?q=…` dialihkan 301 ke `cari?q=…`.
rute_lama_301('cari', $_GET);

$c = trim((string) ($_GET['q'] ?? ''));
$hasil = ['berita' => [], 'halaman' => [], 'agenda' => [], 'fasilitas' => [], 'guru' => []];
$total = 0;
if ($c !== '') {
    $like = '%' . $c . '%';
    $hasil['berita'] = all("SELECT * FROM berita WHERE terbit = 1 AND (judul LIKE ? OR ringkasan LIKE ? OR isi LIKE ?) ORDER BY tanggal DESC LIMIT 10", [$like, $like, $like]);
    $hasil['halaman'] = all("SELECT * FROM menu WHERE aktif = 1 AND jenis IN ('halaman','guru','fasilitas') AND (label LIKE ? OR judul LIKE ? OR isi LIKE ?) ORDER BY urutan LIMIT 10", [$like, $like, $like]);
    $hasil['agenda'] = all("SELECT * FROM agenda WHERE aktif = 1 AND (judul LIKE ? OR keterangan LIKE ? OR tempat LIKE ?) ORDER BY tanggal DESC LIMIT 10", [$like, $like, $like]);
    $hasil['fasilitas'] = all("SELECT * FROM fasilitas WHERE aktif = 1 AND (nama LIKE ? OR keterangan LIKE ?) LIMIT 10", [$like, $like]);
    $hasil['guru'] = all("SELECT * FROM guru WHERE status = 'aktif' AND (nama LIKE ? OR mapel LIKE ? OR jabatan LIKE ?) ORDER BY nama LIMIT 10", [$like, $like, $like]);
    $total = array_sum(array_map('count', $hasil));
}

situs_atas(['judul' => $c !== '' ? 'Pencarian: ' . $c : 'Pencarian', 'aktif' => '']);
hero_halaman('Pencarian', $c !== '' ? 'Hasil pencarian untuk “' . $c . '”' : 'Cari informasi pada website ini', [['label' => 'Pencarian', 'tautan' => '']]);
?>
<section class="isi-halaman">
    <div class="wadah">
        <div class="artikel mb24" style="margin-bottom:24px">
            <form method="get" action="cari" class="rasta" style="gap:10px;flex-wrap:wrap">
                <input type="search" name="q" value="<?= e($c) ?>" placeholder="Kata kunci pencarian..." style="flex:1;min-width:220px;padding:11px 13px;border:1.5px solid var(--garis);border-radius:10px;font:inherit" aria-label="Kata kunci">
                <button type="submit" class="tombol"><?= ikon('search', 17) ?> Cari</button>
            </form>
        </div>

        <?php if ($c === ''): ?>
            <div class="kartu tengah"><p class="mut mb0">Masukkan kata kunci untuk mencari berita, halaman, agenda, fasilitas, atau data guru.</p></div>
        <?php elseif ($total === 0): ?>
            <div class="kartu tengah"><p class="mut mb0">Tidak ada hasil untuk “<?= e($c) ?>”. Coba kata kunci lain.</p></div>
        <?php else: ?>
            <p class="mut"><?= angka($total) ?> hasil ditemukan.</p>
            <?php foreach ([
                'berita' => 'Berita', 'halaman' => 'Halaman', 'agenda' => 'Agenda', 'fasilitas' => 'Fasilitas', 'guru' => 'Guru & Tendik',
            ] as $kunci => $judul): ?>
                <?php if (!$hasil[$kunci]) { continue; } ?>
                <div class="artikel mb24" style="margin-bottom:22px">
                    <h2 style="margin-top:0"><?= e($judul) ?></h2>
                    <?php foreach ($hasil[$kunci] as $r): ?>
                        <?php
                        switch ($kunci) {
                            case 'berita':
                                $tautan = berita_url((string) $r['slug']);
                                $nama = $r['judul'];
                                $ket = tanggal_id($r['tanggal']) . ' — ' . potong($r['ringkasan'] !== '' ? $r['ringkasan'] : $r['isi'], 120);
                                break;
                            case 'halaman':
                                $tautan = menu_url($r);
                                $nama = $r['label'];
                                $ket = $r['subjudul'] !== '' ? $r['subjudul'] : potong($r['isi'], 120);
                                break;
                            case 'agenda':
                                $tautan = 'agenda';
                                $nama = $r['judul'];
                                $ket = tanggal_id($r['tanggal']) . ($r['tempat'] !== '' ? ' · ' . $r['tempat'] : '');
                                break;
                            case 'fasilitas':
                                $tautan = 'fasilitas';
                                $nama = $r['nama'];
                                $ket = potong($r['keterangan'], 120);
                                break;
                            default:
                                $tautan = halaman_url('tenaga-pendidik');
                                $nama = $r['nama'];
                                $ket = $r['jabatan'] . ($r['mapel'] !== '' ? ' · ' . $r['mapel'] : '');
                        }
                        ?>
                        <div class="agenda-baris">
                            <div>
                                <h3 style="margin:0 0 3px"><a href="<?= e($tautan) ?>"><?= e($nama) ?></a></h3>
                                <p class="mut kecil mb0"><?= e($ket) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
<?php situs_bawah(); ?>
