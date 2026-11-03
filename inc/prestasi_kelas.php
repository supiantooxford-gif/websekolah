<?php
/**
 * PRESTASI SISWA PER KELAS — peringkat 1–3 tiap kelas, per TAHUN AJARAN & SEMESTER.
 *
 * Permintaan pemilik: *"Bagian yang saya tandai warna merah buat kartu prestasi siswa per kelas.
 * Isinya peringkat 1-3 di setiap kelas. Yang ditampilkan adalah peringkat 1-3 dikelas, filter setiap
 * tahun ajaran dan semesternya. Peringkat berisi Foto, Kelas, Nilai Rata-rata. Data di isi oleh super
 * admin atau editor (Yelfis Djamaludin)"*
 *
 * Aturan yang dipakai (jangan diubah tanpa bertanya kepada pemilik):
 *   1. Satu baris = satu PERINGKAT pada satu KELAS di satu PERIODE (tahun ajaran + semester).
 *      Peringkat 1–3 per kelas bersifat unik (`UNIQUE (ta, semester, kelas_id, peringkat)`).
 *   2. Yang ditampilkan pada setiap peringkat: **foto**, **kelas**, dan **nilai rata-rata**
 *      (ditambah nama siswa supaya jelas siapa yang dimaksud).
 *   3. Halaman publik punya **filter tahun ajaran & semester**; pilihan awalnya periode berjalan
 *      (`periode.bawaan`), dan bila periode itu belum berisi data dipakai periode terbaru yang ada.
 *   4. Data diisi **Super Admin (Administrator) atau akun Editor** — lihat
 *      `boleh_kelola_prestasi_kelas()` pada `inc/helpers.php`; halaman panelnya
 *      `admin/prestasi_kelas.php`.
 *
 * Catatan teknis:
 *   • Blok ini dicetak pada halaman **Prestasi Siswa** (`halaman.php`) di ATAS deskripsi & tabel
 *     prestasi — tepat pada area yang ditandai pemilik.
 *   • Foto disimpan sebagai **URL media proxy** (bukan berkas di server).
 *   • Gaya `.pk-*` ada pada `assets/style.css` — **jangan** menaruh `<style>` di halaman.
 */
declare(strict_types=1);

/** Banyaknya peringkat yang dicatat/ditampilkan per kelas (peringkat 1–3). */
const PK_PERINGKAT_MAKS = 3;
const PK_TA_MAKS = 20;
const PK_SEMESTER_MAKS = 24;

/** Pilihan semester baku (semester lain tetap boleh diketik, mis. "Antara"). */
const PK_SEMESTER_PILIHAN = ['Ganjil', 'Genap'];

/** Nilai rata-rata: batas atas yang masuk akal (skala 0–100). */
const PK_NILAI_MAKS = 100.0;

/** Normalkan penulisan tahun ajaran ("2026-2027"/"2026" → "2026/2027"). */
function pk_ta_norm(string $ta): string
{
    $ta = trim($ta);
    if ($ta === '') {
        return '';
    }
    $ta = preg_replace('#\s*[/–—-]\s*#u', '/', $ta) ?? $ta;
    $ta = preg_replace('#\s+#u', '/', $ta) ?? $ta;
    if (preg_match('#^(\d{4})$#', $ta, $m)) {
        return $m[1] . '/' . ((int) $m[1] + 1);
    }
    return $ta;
}

/** Tahun ajaran sah? Dua tahun BERURUTAN, mis. 2026/2027. */
function pk_ta_sah(string $ta): bool
{
    return (bool) preg_match('#^(\d{4})/(\d{4})$#', $ta, $m) && (int) $m[2] === (int) $m[1] + 1;
}

/** Normalkan tulisan semester ("ganjil" → "Ganjil"; kata lain dirapikan huruf depannya). */
function pk_semester_norm(string $semester): string
{
    $semester = trim(preg_replace('#\s+#u', ' ', $semester) ?? $semester);
    if ($semester === '') {
        return '';
    }
    foreach (PK_SEMESTER_PILIHAN as $s) {
        if (strcasecmp($semester, $s) === 0) {
            return $s;
        }
    }
    return ucfirst($semester);
}

/** Semester sah? Minimal 2 karakter dan tidak lebih dari batas. */
function pk_semester_sah(string $semester): bool
{
    $n = function_exists('mb_strlen') ? mb_strlen($semester) : strlen($semester);
    return $semester !== '' && $n >= 2 && $n <= PK_SEMESTER_MAKS;
}

/** Label periode untuk ditampilkan ("2026/2027 · Ganjil"). */
function pk_label_periode(string $ta, string $semester): string
{
    return trim($ta) . ' · ' . trim($semester);
}

/** Nilai rata-rata → teks ("92,5" → "92,50"). */
function pk_nilai_teks($nilai): string
{
    return number_format((float) $nilai, 2, ',', '.');
}

/**
 * Daftar periode yang SUDAH berisi data (tahun ajaran terbaru lebih dulu).
 *
 * @return array<int,array{ta:string,semester:string,jumlah:int}>
 */
function pk_periode_list(): array
{
    $keluar = [];
    foreach (all('SELECT ta, semester, COUNT(*) AS n FROM prestasi_kelas
                  GROUP BY ta, semester ORDER BY ta DESC, semester') as $r) {
        $keluar[] = [
            'ta' => (string) $r['ta'],
            'semester' => (string) $r['semester'],
            'jumlah' => (int) $r['n'],
        ];
    }
    return $keluar;
}

/** Daftar tahun ajaran yang punya data (terbaru lebih dulu). */
function pk_ta_list(): array
{
    $ta = [];
    foreach (pk_periode_list() as $p) {
        if (!in_array($p['ta'], $ta, true)) {
            $ta[] = $p['ta'];
        }
    }
    return $ta;
}

/** Daftar semester yang punya data. */
function pk_semester_list(): array
{
    $sem = [];
    foreach (pk_periode_list() as $p) {
        if (!in_array($p['semester'], $sem, true)) {
            $sem[] = $p['semester'];
        }
    }
    return $sem;
}

/**
 * Periode BAWAAN halaman publik: periode berjalan (`periode.bawaan`) bila periode itu sudah berisi
 * data; kalau tidak, periode TERBARU yang ada datanya. Tanpa data sama sekali → ['', ''].
 *
 * @return array{0:string,1:string}
 */
function pk_periode_bawaan(): array
{
    $ada = pk_periode_list();
    if (!$ada) {
        return ['', ''];
    }
    $bawaan = null;
    try {
        $bawaan = one('SELECT ta, semester FROM periode WHERE bawaan = 1 ORDER BY urutan, id LIMIT 1');
    } catch (Throwable $e) {
        $bawaan = null;   // tabel periode belum ada (instalasi sangat baru) — pakai periode terbaru
    }
    if ($bawaan) {
        foreach ($ada as $p) {
            if ($p['ta'] === (string) $bawaan['ta'] && $p['semester'] === (string) $bawaan['semester']) {
                return [(string) $bawaan['ta'], (string) $bawaan['semester']];
            }
        }
    }
    return [$ada[0]['ta'], $ada[0]['semester']];
}

/** Apakah sebuah periode sudah terdaftar pada daftar periode berdata? */
function pk_periode_ada(string $ta, string $semester): bool
{
    foreach (pk_periode_list() as $p) {
        if ($p['ta'] === $ta && $p['semester'] === $semester) {
            return true;
        }
    }
    return false;
}

/**
 * Baris peringkat sebuah periode, urut kelas (urutan kelas → nama) lalu peringkat.
 *
 * @return array<int,array<string,mixed>>
 */
function pk_list(string $ta, string $semester, bool $hanyaAktif = true): array
{
    if ($ta === '' || $semester === '') {
        return [];
    }
    $w = $hanyaAktif ? ' AND p.aktif = 1' : '';
    $baris = all('SELECT p.*, k.urutan AS kelas_urutan, k.nama AS kelas_nama
                  FROM prestasi_kelas p
                  LEFT JOIN kelas k ON k.id = p.kelas_id
                  WHERE p.ta = ? AND p.semester = ?' . $w . '
                  ORDER BY COALESCE(k.urutan, 999), COALESCE(k.nama, p.kelas), p.peringkat, p.id',
        [$ta, $semester]);
    return $baris;
}

/**
 * Baris peringkat dikelompokkan per kelas (siap dicetak sebagai kartu per kelas).
 *
 * @return array<int,array{kelas:string,baris:array<int,array<string,mixed>>}>
 */
function pk_grup(string $ta, string $semester, bool $hanyaAktif = true): array
{
    $grup = [];
    foreach (pk_list($ta, $semester, $hanyaAktif) as $r) {
        $nama = trim((string) ($r['kelas_nama'] ?? '')) !== '' ? (string) $r['kelas_nama'] : (string) $r['kelas'];
        if ($nama === '') {
            $nama = 'Tanpa kelas';
        }
        $grup[$nama]['kelas'] = $nama;
        $grup[$nama]['baris'][] = $r;
    }
    return array_values($grup);
}

/** Satu baris berdasarkan id. */
function pk_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM prestasi_kelas WHERE id = ?', [$id]) : null;
}

/** Baris yang sudah memakai satu peringkat pada satu kelas & periode (untuk cek bentrok). */
function pk_bentrok(string $ta, string $semester, int $kelasId, int $peringkat, int $kecualiId = 0): ?array
{
    return one('SELECT * FROM prestasi_kelas
                WHERE ta = ? AND semester = ? AND kelas_id = ? AND peringkat = ? AND id <> ?',
        [$ta, $semester, $kelasId, $peringkat, $kecualiId]);
}

/** Foto yang ditampilkan: foto baris ini → foto siswa tertaut (bila ada). */
function pk_foto(array $baris): string
{
    $foto = trim((string) ($baris['foto'] ?? ''));
    if ($foto !== '') {
        return $foto;
    }
    $sid = (int) ($baris['siswa_id'] ?? 0);
    if ($sid > 0) {
        $s = one('SELECT foto FROM siswa WHERE id = ?', [$sid]);
        if ($s && trim((string) $s['foto']) !== '') {
            return (string) $s['foto'];
        }
    }
    return '';
}

/** Inisial nama untuk pengganti foto. */
function pk_inisial(string $nama): string
{
    $nama = trim($nama);
    return $nama === '' ? '?' : strtoupper(substr($nama, 0, 1));
}

/** Kelas yang boleh dipilih pada panel (kelas aktif lebih dulu). */
function pk_kelas_list(): array
{
    return all('SELECT id, nama, tingkat, aktif FROM kelas ORDER BY aktif DESC, urutan, nama');
}

/**
 * Mencetak BLOK "Prestasi Siswa per Kelas" pada halaman publik Prestasi Siswa.
 *
 * Tanpa data sama sekali, blok TIDAK dicetak (halaman tetap seperti semula).
 */
function pk_papan(): void
{
    $periode = pk_periode_list();
    if (!$periode) {
        return;
    }
    $daftarTa = pk_ta_list();
    $daftarSem = pk_semester_list();
    [$bawaanTa, $bawaanSem] = pk_periode_bawaan();

    // Saringan: nilai asing DIABAIKAN (kembali ke periode bawaan) — bukan halaman kosong/403.
    $ta = pk_ta_norm((string) ($_GET['pk_ta'] ?? ''));
    $semester = pk_semester_norm((string) ($_GET['pk_sem'] ?? ''));
    if ($ta === '' || !in_array($ta, $daftarTa, true)) {
        $ta = $bawaanTa;
    }
    if ($semester === '' || !in_array($semester, $daftarSem, true)) {
        $semester = $bawaanSem;
    }

    $grup = pk_grup($ta, $semester);
    $jumlahSiswa = 0;
    foreach ($grup as $g) {
        $jumlahSiswa += count($g['baris']);
    }
    ?>
<section class="pk-blok" id="prestasiKelas">
  <div class="pk-kepala">
    <div class="pk-kepala-teks">
      <h2><?= ikon('trophy', 20) ?> Kartu Prestasi Siswa per Kelas</h2>
      <p class="pk-sub">Peringkat 1–3 setiap kelas pada tahun ajaran &amp; semester yang dipilih,
        lengkap dengan foto dan nilai rata-rata.</p>
    </div>
    <span class="pk-chip-periode"><?= ikon('kalender', 14) ?><?= e(pk_label_periode($ta, $semester)) ?></span>
  </div>

  <form class="pk-filter" method="get" action="<?= e(rute_kanonik()) ?>">
    <label for="pkTa">Tahun Ajaran</label>
    <select id="pkTa" name="pk_ta" data-auto>
      <?php foreach ($daftarTa as $t): ?>
        <option value="<?= e($t) ?>" <?= $t === $ta ? 'selected' : '' ?>><?= e($t) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="pkSemester">Semester</label>
    <select id="pkSemester" name="pk_sem" data-auto>
      <?php foreach ($daftarSem as $s): ?>
        <option value="<?= e($s) ?>" <?= $s === $semester ? 'selected' : '' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="filter-jumlah"><?= angka(count($grup)) ?> kelas · <?= angka($jumlahSiswa) ?> peringkat</span>
  </form>

  <?php if (!$grup): ?>
    <p class="pk-kosong">Belum ada data peringkat untuk periode
      <b><?= e(pk_label_periode($ta, $semester)) ?></b>. Pilih tahun ajaran/semester lain, atau
      isi datanya pada panel admin (menu <b>Prestasi per Kelas</b>).</p>
  <?php else: ?>
    <div class="pk-grid">
      <?php foreach ($grup as $g): ?>
        <article class="pk-kartu">
          <header class="pk-kartu-kepala">
            <h3><?= e($g['kelas']) ?></h3>
            <span class="pk-kartu-jumlah"><?= count($g['baris']) ?> peringkat</span>
          </header>
          <ol class="pk-daftar">
            <?php foreach ($g['baris'] as $r): ?>
              <?php
              $peringkat = max(1, min(PK_PERINGKAT_MAKS, (int) $r['peringkat']));
              $foto = pk_foto($r);
              $nama = trim((string) $r['nama']);
              ?>
              <li class="pk-baris pk-rank-<?= $peringkat ?>">
                <span class="pk-medali" aria-label="Peringkat <?= $peringkat ?>"><?= $peringkat ?></span>
                <span class="pk-foto">
                  <?php if ($foto !== ''): ?>
                    <img src="<?= e($foto) ?>" alt="Foto <?= e($nama) ?>" loading="lazy">
                  <?php else: ?>
                    <span class="pk-inisial"><?= e(pk_inisial($nama)) ?></span>
                  <?php endif; ?>
                </span>
                <span class="pk-teks">
                  <strong><?= e($nama !== '' ? $nama : 'Tanpa nama') ?></strong>
                  <small><?= e($g['kelas']) ?> · Rata-rata <b><?= e(pk_nilai_teks($r['nilai'])) ?></b></small>
                </span>
                <span class="pk-nilai"><?= e(pk_nilai_teks($r['nilai'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="pk-kaki">Peringkat 1–3 tiap kelas untuk
      <b><?= e(pk_label_periode($ta, $semester)) ?></b> — nilai rata-rata seluruh mata pelajaran.</p>
    <?php
    // Tautan cepat ke periode lain yang SUDAH berisi data — supaya pembaca tahu periode mana saja
    // yang tersedia (tanpa harus mencoba satu per satu pada filter).
    $periodeLain = array_values(array_filter(pk_periode_list(), static function (array $p) use ($ta, $semester): bool {
        return !($p['ta'] === $ta && $p['semester'] === $semester);
    }));
    ?>
    <?php if ($periodeLain): ?>
      <div class="pk-periode-lain">
        <span class="pk-periode-label"><?= ikon('kalender', 13) ?> Periode lain:</span>
        <?php foreach ($periodeLain as $pl): ?>
          <a class="pk-periode-chip" href="<?= e(rute_tautan(rute_kanonik(), ['pk_ta' => $pl['ta'], 'pk_sem' => $pl['semester']])) ?>#prestasiKelas">
            <?= e(pk_label_periode($pl['ta'], $pl['semester'])) ?>
            <span class="pk-periode-jumlah"><?= angka($pl['jumlah']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
    <?php
}
