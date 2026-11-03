<?php
/**
 * KOMPONEN BERSAMA: KARTU "GRAFIK JUMLAH PER TAHUN" (Chart.js).
 *
 * Dipakai dua halaman yang bentuk datanya sama (jumlah orang per tahun):
 *   • **Pendaftar** (menu Siswa → Pendaftar) → "Grafik Pendaftaran per Tahun" (satuan: pendaftar);
 *   • **Data Alumni** (menu Siswa → Data Alumni) → "Grafik Alumni per Tahun" (satuan: alumni).
 *
 * Permintaan pemilik: grafik diletakkan DI ATAS filter tahun dan **hanya tahun yang sudah ada
 * datanya** yang ditampilkan (tahun kosong tidak dicetak sebagai batang).
 *
 * Catatan penting:
 *  • Angka tiap tahun juga dicetak sebagai **daftar teks** (`<ul>`) sehingga tetap terbaca bila
 *    skrip/Chart.js gagal dimuat — jangan dihapus.
 *  • Satu halaman hanya boleh memuat SATU id kartu/kanvas (id diberikan pemanggil dan berbeda
 *    antar halaman) supaya tidak ada id kembar.
 *  • Gaya `.pf-grafik*` pada `assets/style.css` (**jangan** menaruh `<style>` di halaman).
 */
declare(strict_types=1);

/**
 * Cetak kartu grafik jumlah per tahun.
 *
 * @param array{
 *   id:string, kanvas:string, daftar:string, judul:string, keterangan:string,
 *   data:array<int,int>, satuan:string, kaki:string, ikon?:string
 * } $o
 */
function grafik_tahun_kartu(array $o): void
{
    $data = $o['data'] ?? [];
    if (!$data) {
        return;                     // belum ada data satu tahun pun → kartu tidak dicetak
    }
    $satuan = (string) ($o['satuan'] ?? 'data');
    $total = array_sum($data);
    $jmlTahun = count($data);
    $nilai = array_values($data);
    $maksNilai = max($nilai);
    $maksTahun = 0;
    foreach ($data as $th => $n) {
        if ($n >= $maksNilai) {
            $maksTahun = (int) $th; // bila seri, tahun terbaru yang dipakai
        }
    }
    $tahunList = array_map('strval', array_keys($data));
    ?>
<section class="pf-blok pf-grafik" id="<?= e((string) $o['id']) ?>">
  <div class="pf-grafik-kepala">
    <div>
      <h2><?= ikon((string) ($o['ikon'] ?? 'grafik'), 18) ?> <?= e((string) $o['judul']) ?></h2>
      <p class="sm mut mb0"><?= e((string) $o['keterangan']) ?></p>
    </div>
    <div class="pf-grafik-chip">
      <span><?= angka($jmlTahun) ?> tahun berdata</span>
      <span><?= angka($total) ?> <?= e($satuan) ?></span>
      <span>terbanyak <?= (int) $maksTahun ?> (<?= angka($maksNilai) ?>)</span>
    </div>
  </div>
  <div class="pf-chart"><canvas id="<?= e((string) $o['kanvas']) ?>"
    aria-label="<?= e((string) $o['judul']) ?>" role="img"></canvas></div>
  <?php /* Daftar angka: tetap terbaca walau grafik tidak tergambar (mis. skrip gagal dimuat). */ ?>
  <ul class="pf-grafik-daftar" id="<?= e((string) $o['daftar']) ?>">
    <?php foreach ($data as $th => $n): ?>
      <li><b><?= (int) $th ?></b><span><?= angka($n) ?> <?= e($satuan) ?></span>
        <i style="width:<?= $maksNilai > 0 ? (int) round($n / $maksNilai * 100) : 0 ?>%" data-th="<?= (int) $th ?>"></i></li>
    <?php endforeach; ?>
  </ul>
  <p class="kecil mut mt8 mb0"><?= e((string) $o['kaki']) ?></p>
</section>
<script src="assets/vendor/chart.umd.min.js?v=<?= e(APP_VERSION) ?>"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (!window.PFD) { return; }
    window.PFD.batang(<?= json_encode((string) $o['kanvas']) ?>,
      <?= json_encode($tahunList, JSON_UNESCAPED_UNICODE) ?>,
      <?= json_encode($nilai) ?>,
      <?= json_encode($satuan, JSON_UNESCAPED_UNICODE) ?>);
  });
</script>
    <?php
}
