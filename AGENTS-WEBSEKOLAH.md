# Website Sekolah (web-sekolah/) — v1.19.1, skema v35

Aplikasi **website sekolah/madrasah** (satu lembaga) dengan panel admin: halaman depan
memuat slider besar berketerangan, keunggulan, program unggulan, berita terbaru, agenda,
kartu PPDB, statistik (Guru & Tendik / Siswa Aktif / Alumni / Pendaftar), testimoni
"apa kata mereka", serta slider kecil berisi gambar + tautan media sosial.
Live: **https://supianto.vibecoder.co.id/web-sekolah** (publish: slug `web-sekolah`, tipe `php`).

## Stack & keputusan
- **PHP 8.2 + SQLite (PDO)**, tanpa framework/composer/build step. Titik masuk
  `inc/init.php` (config → helpers → db → layout); panel admin di `admin/`.
- **ALAMAT RAPI (pretty URL), v1.8.0** — lihat bagian **“ALAMAT RAPI”** di bawah: jalur publik tidak
  lagi memuat `.php` (mis. `/web-sekolah/halaman/tenaga-pendidik`) dan alamat lama dialihkan 301.
- Semua tautan **relatif** (aplikasi dilayani di subfolder `/web-sekolah/`) — sudah diuji lewat
  `php -S` + router tiruan pada path subfolder: semua halaman 200, aset 200, 0 tautan absolut.
  Halaman publik memakai **`<base>` relatif** supaya alamat yang lebih dalam tetap memuat asetnya.
- **Satu sekolah**; hampir semua identitas & isi dapat diubah admin:
  `inc/db.php` menanam isi awal sekali saja (penanda `pengaturan.isi_awal_v1`).
- Tema mengikuti referensi pemilik: **hijau tua (#0b4a33/#0d5c3f) + emas (#c9a227/#f2c94c)**.
- Unggahan gambar (slider, galeri, berita, fasilitas, foto guru/siswa, berkas PPDB) lewat
  **media proxy platform dari sisi server** (`media_upload()`); yang disimpan ke basis data
  hanya **URL CDN**. Token dibaca dari `.vibecoder-media-token` (tidak pernah ke browser).

## Peta berkas
- **`inc/rute.php`** — ALAMAT RAPI (v1.8.0): menerjemahkan jalur permintaan menjadi halaman,
  menyediakan tautan (menu_url/hal_url), `<base>`, canonical, dan pengalihan 301 alamat lama.
- Halaman publik: `index.php` (**pintu depan / front controller**, sekaligus beranda),
  `halaman.php?slug=` (halaman dinamis + sub menu),
  `berita.php`, `berita_detail.php`, `agenda.php`, `galeri.php`, `fasilitas.php`, `kontak.php`,
  `cari.php`, `ppdb.php` (info + formulir), `ppdb_status.php` (cek status + pengumuman),
  **`pip_kartu.php`** (unduh Kartu PIP dengan kata sandi — lihat v1.7.20).
- Panel admin (`admin/`): `login.php`/`logout.php`, `index.php` (dashboard), `menu.php`
  (menu & isi halaman), `slider.php`, `slider_kecil.php` (slider kecil + media sosial),
  `keunggulan.php` (keunggulan + program), `berita.php`/`berita_edit.php`/`berita_kategori.php`,
  `agenda.php`, `galeri.php` (album + foto multi-unggah + video YouTube), `fasilitas.php`,
  `testimoni.php`, `guru.php`/`guru_import.php`/`guru_export.php`,
  **`profil.php`** (isian halaman Profil Lengkap), **`struktur.php`** (kelola bagan Struktur Organisasi),
  `siswa.php`/`siswa_import.php`/`siswa_export.php`, **`cetak_guru.php`**, **`cetak_siswa.php`**
  (lembar cetak A4 mendatar), `kelas.php`, `kenaikan.php`
  (naik kelas & kelulusan + pembatalan), `ppdb.php`, `ppdb_gelombang.php`, `ppdb_pendaftar.php`,
  **`upload_editor.php`** (unggah gambar untuk penyunting isi),
  `pesan.php`, `identitas.php` (identitas + teks beranda + angka alumni), **`pip.php`**
  (Layanan PIP: **Tahun Layanan PIP** + informasi + SK Penerima per tahun, kata sandi Kartu PIP,
  daftar penerima per tahun — penjaganya **`require_kelola_pip()`** = Administrator **atau peran
  `editor`**, dan **kata sandi kartu boleh diubah kedua peran**; lihat v1.7.23), `pengguna.php`,
  `log.php`.
- `inc/`: `config.php`, `helpers.php`, `db.php`, `layout.php` (ikon SVG + header/nav/footer publik +
  kerangka panel), `admin_helpers.php` (geser urutan, hapus baris, blok unggah gambar, penolong impor),
  **`profil.php`** (halaman Profil Lengkap + bagan Struktur Organisasi; di-`require` `halaman.php`),
  **`bos.php`** (halaman **Informasi BOS** — Papan Informasi Dana BOS yang dibaca LANGSUNG dari basis
  data aplikasi e-BOS; di-`require` `halaman.php`, lihat bagian v1.7.18),
  **`sarpras.php`** (halaman **Informasi Sarpras** — Daftar Inventaris Barang yang dibaca LANGSUNG
  dari basis data aplikasi e-GURU/asisten.guru; di-`require` `halaman.php`, lihat bagian v1.7.19),
  **`perpustakaan.php`** (halaman **Informasi Perpustakaan** — EMPAT LAPORAN: Data Buku, Pengunjung,
  Peminjaman, Pengembalian; dibaca LANGSUNG dari basis data aplikasi e-PERPUS; di-`require`
  `halaman.php`, lihat bagian v1.8.1),
  **`supervisi.php`** (halaman **Informasi Supervisi Akademik** — DASHBOARD aplikasi Supervisi Khusus
  MA. Sirojuth Tholibiin Mootilango dengan saringan Tahun Ajaran & Semester; dibaca LANGSUNG dari
  basis data aplikasi Supervisi Guru; di-`require` `halaman.php`, lihat bagian v1.8.2),
  **`perangkat.php`** (halaman **Perangkat Guru** — berkas CP/ATP/RPP tiap guru **per KELAS & per
  PERIODE (Tahun Ajaran + Semester)** (`perangkat_kelas`) + kode unduh per mata pelajaran; di-`require`
  `halaman.php`, `perangkat_unduh.php`, dan `admin/perangkat.php`, lihat v1.11.0/v1.10.0/v1.9.0/v1.8.11),
  **`admin/periode.php`** (menu Super Admin **"Tahun Ajaran & Semester"** — daftar periode yang dipakai
  Perangkat Guru; lihat v1.11.0),
  **`edm.php`** (halaman **Evaluasi Diri Madrasah** — tampilan *Indikator EDM* yang datanya dibaca
  LANGSUNG dari aplikasi EDM; di-`require` `halaman.php`, lihat bagian v1.8.9),
  **`ppdb_tarik.php`** (modul **DATA PENDAFTAR TAHUN LAMA**: login + tarik + parser + simpan dari
  panel PPDB ma.stm.sch.id; di-`require` `halaman.php` & `admin/ppdb_tarik.php`, lihat bagian v1.8.5),
  **`voting.php`** (halaman **Informasi e-Voting OSIM** — PAPAN HASIL REAL-COUNT aplikasi e-Voting
  khusus MA. Sirojuth Tholibiin Mootilango, angka berjalan tiap 5 detik; dibaca LANGSUNG dari basis
  data aplikasi e-Voting; di-`require` `halaman.php`, lihat bagian v1.8.3),
  **`pip.php`** (halaman **Layanan PIP** — informasi PIP + SK Penerima, daftar penerima, dan
  Kartu PIP ber-sandi; di-`require` `halaman.php` & `pip_kartu.php`, lihat bagian v1.7.20),
  **`kohort.php`** (halaman **Kohort Siswa** — tabel analisis kohort 5 tahun terakhir (termasuk grup
  kolom **Lulus**) + analisis grafiknya; di-`require` `halaman.php` & `admin/kohort.php`, lihat
  bagian v1.13.0/v1.14.0),
  **`prestasi_kelas.php`** (blok **Kartu Prestasi Siswa per Kelas** — peringkat 1–3 tiap kelas per
  tahun ajaran & semester; di-`require` `halaman.php` & `admin/prestasi_kelas.php`, lihat v1.15.0),
  **`guru.php`** (data guru: **TMT**, **masa kerja**, keterangan Satmikal/Non Satmikal, **popup data
  guru**, dan **tampilan tabel**; di-`require` `halaman.php` & `admin/guru.php`, lihat v1.16.0),
  **`kurikulum.php`** (MENU KURIKULUM: panel publik **Kaldik Operasional** (gambar kalender),
  **Jadwal Mengajar** (tabel per hari — mendatar saat dicetak), dan **Dokumen Kurikulum** (kartu
  dokumen + unduhan yang dialirkan server); di-`require` `halaman.php`, `kurikulum_unduh.php`, dan
  `admin/kurikulum.php` — lihat v1.18.0).
- Panel tambahan: `admin/cetak_alumni.php` (lembar cetak Data Alumni A4 mendatar, mengikuti saringan
  halaman Data Alumni — lihat v1.16.2), **`admin/kurikulum.php`** (MENU KURIKULUM: tiga tab isian —
  Kaldik Operasional, Jadwal Mengajar, Dokumen Kurikulum; penjaganya
  **`require_kelola_kurikulum()`** = Administrator **atau peran `editor`** "operator (yelfis)" —
  lihat v1.18.0), dan **`kurikulum_unduh.php`** (endpoint unduhan dokumen kurikulum).

## Basis data (SELALU periksa `const SCHEMA_VERSION` di `inc/config.php` — saat ini **v35**)
Tabel: `sekolah` (satu baris), `pengaturan` (kunci/nilai), `users`, `log_masuk`, `menu`, `slider`,
`slider_kecil`, `sosial`, `keunggulan`, `program`, `berita_kategori`, `berita`, `agenda`,
`galeri_album`, `galeri_foto`, `galeri_video`, `fasilitas`, `testimoni`, `kelas`, `guru`, `siswa`,
`kenaikan`, `ppdb_gelombang`, `ppdb_pendaftar`, `kontak_pesan`, `poll*`, `prestasi`, `alumni`,
**`struktur`** (kotak bagan Struktur Organisasi, v10), **`kohort`** (tabel analisis kohort
siswa — satu baris per tahun ajaran, v29), **`prestasi_kelas`** (peringkat 1–3 tiap kelas per tahun
ajaran & semester, v32), **`kurikulum_kaldik`** / **`kurikulum_jadwal`** / **`kurikulum_dokumen`**
(tiga isian MENU KURIKULUM — gambar kalender, jadwal mengajar per hari/kelas, dan berkas dokumen
kurikulum; v34). `guru` punya kolom **`tmt`** (tanggal mulai tugas) & **`keterangan`**
(Satmikal/Non Satmikal) — v33.
`galeri_album` punya kolom **`tahun`** (v11) — lihat bagian v1.7.7 (album bertahun);
`struktur` punya kolom **`kode`** (slot peta tetap) dan **`foto`** (pas foto) — lihat v1.7.9;
`sekolah` punya kolom **`berkas_ijop`** & **`berkas_akreditasi`** (URL dokumen pada media proxy) — v1.7.13.
- `SCHEMA_TABEL_WAJIB` + `SCHEMA_KOLOM_WAJIB` + `skema_terkini()` → **penanda skema yang lebih baru
  daripada isi basis data pulih sendiri** (jangan dilepas; setiap kolom/tabel baru wajib didaftarkan).
- Seluruh migrasi satu `BEGIN IMMEDIATE … COMMIT`; `PRAGMA journal_mode=WAL`, `busy_timeout=5000`.
- `menu.induk_id` = 0 untuk menu utama, > 0 untuk sub menu. `menu.jenis` menentukan tampilan:
  `beranda`, `halaman`, `guru` (isi + kartu daftar guru), **`siswa`** (isi + tabel Data Siswa),
  **`alumni`** (isi + tabel Data Alumni), **`prestasi_siswa`**, **`prestasi_madrasah`**,
  **`prestasi_pendidik`**, **`pelatihan_pendidik`** (isi + tabel prestasi/pelatihan),
  `berita`, `agenda`, `galeri`, `fasilitas`, `kontak`, `ppdb`, `tautan`
  (sejak v1.8.1: **`perpustakaan`** — empat laporan dari e-PERPUS; sejak v1.8.2: **`supervisi`** —
  dashboard aplikasi Supervisi dengan saringan tahun ajaran & semester; sejak v1.8.3: **`voting`** —
  papan hasil Real-Count aplikasi e-Voting; lihat bagian terkait).
  Sejak **v1.8.9** menu "Evaluasi Diri Madrasah" memakai jenis **`edm`** (tampilan Indikator EDM).
  Sejak **v1.8.6** pendaftar PPDB punya **halaman menu tersendiri**: jenis **`pendaftar`**
  (sub menu "Siswa" → **Pendaftar**) dengan filter per tahun — lihat bagian v1.8.6. Isinya dahulu
  berupa tab pada halaman Data Siswa (v1.8.5); tab itu **sudah dihapus**.
  Sejak **v1.13.0** menu **Kohort Siswa** (sub menu "Siswa") memakai jenis **`kohort`** — tabel
  analisis kohort 5 tahun terakhir + analisis grafiknya (lihat bagian v1.13.0).
  Sejak **v1.18.0** tiga sub menu **Kurikulum** memakai jenis **`kaldik`** (gambar Kalender
  Pendidikan), **`jadwal_mengajar`** (tabel jadwal per hari, mendatar saat dicetak), dan
  **`dokumen_kurikulum`** (kartu dokumen yang dapat diunduh pengunjung) — lihat bagian v1.18.0.
  `menu.isi` = HTML sederhana (disaring `bersihkan_html()`: hanya
  h2-h4/p/br/strong/em/u/ul/ol/li/blockquote/a/img/hr/table...). `menu.sorot` = 1 → tombol PPDB di navbar.

## PPDB
- `pengaturan`: `ppdb_buka` (formulir dibuka/ditutup), `ppdb_umumkan` + `ppdb_pengumuman_tgl`
  (menampilkan daftar Diterima/Cadangan di halaman cek status), teks `ppdb_isi/syarat/alur/jadwal/biaya/kontak`.
- Pendaftar menerima **nomor `PPDB-YYYY-NNNN`**; cek status memakai nomor + NISN **atau** nomor HP.
- Pengaman: captcha penjumlahan (nilai disimpan di sesi), maks **5 kiriman/sesi**, CSRF,
  unggah berkas (PDF/JPG/PNG, maks 15 MB) lewat media proxy, `kiriman_terlalu_besar()` untuk kasus
  `post_max_size` terlampaui (PHP membuang seluruh $_POST/$_FILES tanpa pesan).
- Panel admin: tetapkan status (Baru/Verifikasi/Diterima/Cadangan/Tidak Diterima/Dibatalkan),
  nilai seleksi, keterangan (tampil ke pendaftar), ekspor CSV, tautan WhatsApp cepat ke pendaftar.

## Impor/Ekspor (kompatibel e-GURU)
- `tebak_kolom()` dua tahap (persis → awalan, satu kolom tidak boleh dipakai dua medan) +
  `tgl_impor()` menerima ISO, `d/m/Y`, `d.m.Y`, angka serial Excel, dan "12 April 2011".
- Guru: kolom Nama, NIP/NUPTK, Jenis Kelamin, Jabatan, Mata Pelajaran, Telepon, Email, Status.
- Siswa: Nama, NIS, NISN, Jenis Kelamin, Tempat Lahir, Tanggal Lahir, Kelas, Tanggal Daftar,
  Nama Ayah, Nama Ibu, Nama Wali, Telepon, Alamat, Status Siswa. **Kelas yang belum ada dibuat otomatis**.
- Ekspor memakai susunan kolom **e-GURU** (`Nama, Jenis Anggota, …`) agar berkas dapat dipakai
  ulang di aplikasi lain; Excel dibuat di peramban (SheetJS di `assets/vendor/xlsx.full.min.js`),
  CSV dilayani server (`?unduh=csv`).
- Berkas dibaca DI PERAMBAN lalu dikirim sebagai JSON (`payload`), jadi tidak perlu ZipArchive.

## Jebakan yang sudah menggigit (jangan diulang)
> Tambahan v1.7.20 (Layanan PIP): (a) **`id` pada formulir tanpa query hilang setelah kiriman
> pertama** — teruskan nilai yang sudah diresolusi ke fungsi tampil, jangan membaca `$_GET` lagi;
> (b) **jangan memberi `id` pembungkus kartu yang sama dengan `id` isian formulir** (pakai awalan
> `bagian…`); (c) **`curl -F` menganggap nilai berawalan `<` sebagai nama berkas** → pakai
> `--form-string` untuk mengirim HTML; (d) medan yang tidak dikirim **tidak boleh diubah**
> (`array_key_exists`) agar kiriman sebagian tidak mengosongkan isian lain.
1. **`.hero-slide .wadah{width:100%}` WAJIB ada.** `.wadah` sebagai anak flex (`display:flex`)
   menyusut ke lebar isinya dan ikut terpusat, sehingga judul beranda tidak rata dengan tepi konten
   (terukur x=482 alih-alih 150).
2. **Pratinjau gambar tidak boleh mengganti wadah yang memuat input berkasnya.** Versi pertama
   menulis ulang `innerHTML` wadah → input `<input type=file>` ikut terhapus dari DOM → berkas
   TIDAK terkirim dan `UPLOAD_ERR_NO_FILE` (gejala: "berhasil disimpan" tapi gambar lama/tiada).
   Sekarang pratinjau hanya mengganti isi `<span class="pratinjau-gambar" id="pratinjau_<field>">`.
3. **Nama berkas/fungsi JS $$ mudah berubah menjadi $ oleh alat penyunting** — $ mengembalikan
   satu elemen (bisa null) sehingga `.forEach` melempar galat dan **seluruh** skrip halaman berhenti
   (`[data-auto]`, dialog konfirmasi, tombol menu HP tidak jalan). Selalu periksa ulang `assets/app.js`
   setelah menyunting.
   🚨 **Terjadi LAGI (v1.7.4) dan kali ini lebih parah**: menuliskan penolong dua-dolar di dalam teks
   yang disunting membuat POTONGAN ISI BERKAS TERSISIP ULANG di titik itu (berkas `assets/app.js`
   membengkak 390 → 566 baris, header berkas muncul di tengah, `node --check` gagal; berkas
   dokumentasi ini juga sempat rusak). **ATURAN: jangan pernah menulis penolong dua-dolar pada teks
   yang dikirim ke alat penyunting** — tulis $ tunggal saja, atau ganti dengan
   `Array.prototype.slice.call(el.querySelectorAll(…))`, atau sunting berkasnya lewat skrip Python
   (heredoc berkutip) yang tidak menafsirkan tanda dolar. Setelah menyunting `assets/app.js` SELALU
   jalankan `node --check assets/app.js` dan hitung penanda bagiannya
   (`grep -c "animasi angka statistik"` harus 1).
4. **Isian `slug` yang dikirim KOSONG** tidak boleh jatuh ke nilai bawaan `halaman`/`berita` —
   pakai label/judul. (`$_POST['slug'] ?? …` tidak menangkap string kosong.)
5. **Jangan memakai `style="grid-template-columns:…"` sebaris** untuk tata letak utama: gaya sebaris
   mengalahkan media query → luapan mendatar di HP. Pakai kelas (mis. `.grid-beranda`, dengan
   penyesuaian pada ≤1080px dan ≤760px).
6. **Penanda variabel yang hilang `$`** (mis. `if (kiriman >= …)`) membuat halaman FATAL 200 tanpa
   isi. Periksa dengan `php -d extension=tokenizer websk_scan_konstanta.php` (memindai T_STRING
   huruf kecil yang bukan konstanta/fungsi).
7. **Banyak "id formulir" sebenarnya ada pada pembungkus `<div>`**, bukan pada `<form>`
   (mis. `#formSlider`, `#formGuru`, `#formVideo`). Pada uji, targetkan `#id button[type=submit]`
   atau `form:has(input[name="…"]) button[type=submit]` — jangan berasumsi `form#id`.
   ⚠️ **Sejak v1.7.4 ada LEBIH DARI SATU `<form>` ber-`action="ppdb.php"`** pada pengaturan PPDB
   (kartu status “Tutup/Buka Pendaftaran” + formulir pengaturan). Selektor lama
   `form[action="ppdb.php"] button[type=submit]` menjadi ambigu → gunakan id-nya:
   `#formPengaturanPpdb`, `#formTutupPpdb`, `#formBukaPpdb`.
8. `galeri_video` sengaja **kosong** pada isi awal (tidak ada video contoh yang rusak); halaman
   Galeri menampilkan petunjuk agar admin menambahkan tautan YouTube sendiri.
9. **`potong()` MELEBUR SELURUH BARIS BARU menjadi spasi** (`preg_replace('/\s+/')`). Medan
   pengaturan yang berbentuk daftar "satu per baris" (Alur Pendaftaran, Persyaratan, Jadwal PPDB)
   WAJIB disimpan dengan **`potong_baris()`** — memakai `potong()` membuat halaman PPDB hanya
   menampilkan SATU kartu langkah raksasa dan satu butir persyaratan panjang (pernah terjadi pada
   data pemilik).
## ALAMAT RAPI (pretty URL) — v1.8.0, tanpa perubahan skema
Permintaan pemilik: `…/web-sekolah/index.php` → `…/web-sekolah/`, dan
`…/web-sekolah/halaman.php?slug=tenaga-pendidik` → `…/web-sekolah/halaman/tenaga-pendidik`.
Pilihan pemilik (jangan diubah tanpa bertanya): **(1) SEMUA alamat publik dibuat rapi** — termasuk
kategori berita, pencarian, dan fasilitas; **(2) alamat lama dialihkan 301** (satu alamat resmi saja).

### Peta alamat
| Alamat rapi (resmi) | Halaman | Alamat lama → 301 |
|---|---|---|
| `/` | beranda (`index.php`) | `index.php`, `index.php?…` |
| `/berita` | daftar berita | `berita.php` |
| `/berita/kategori/<slug>` | berita per kategori | `berita.php?kategori=<slug>` |
| `/berita/<slug>` | detail berita | `berita_detail.php?slug=<slug>` |
| `/agenda` · `/galeri` · `/galeri/<album>` · `/kontak` · `/ppdb` · `/ppdb/status` · `/fasilitas` · `/cari` · `/asisten` | halaman yang bersangkutan | `<berkas>.php` (+ parameternya) |
| `/halaman/<slug>` | halaman dinamis (profil, guru, siswa, BOS, PIP, …) | `halaman.php?slug=<slug>` |

Endpoint tetap seperti semula: `chat_asisten.php`, `polling_suara.php`, `pip_kartu.php` (bukan halaman).
Saringan & paginasi tetap pada query: `halaman/data-siswa?kelas=3&hal=2`, `galeri?tahun=2026`,
`berita?q=…&hal=2`, `cari?q=…`, `asisten?tanya=…`. Bentuk panjang `galeri/album/<slug>` tetap dilayani
sebagai cadangan tautan lama.

### Cara kerjanya (jangan diubah tanpa membaca `inc/rute.php`)
1. **Server Caddy tidak membaca `.htaccess` — dan tidak diperlukan aturan apa pun.** Setiap jalur yang
   BUKAN berkas nyata sudah diserahkan ke `index.php` (diuji langsung pada alamat live).
   `index.php` = **pintu depan**: memanggil `rute_jalankan()`, lalu memuat berkas halaman tujuan.
2. Jalur dibaca dari **`REQUEST_URI`** (PHP tetap menerima jalur asli yang diketik pengunjung,
   sedangkan `SCRIPT_NAME`/`PHP_SELF` hanya `/index.php`), lalu **prefiks aplikasi dibuang** oleh
   `rute_prefiks()`. ⚠️ Pada alamat rapi, jalur yang diterima PHP bisa memuat **garis miring ganda**
   (`/web-sekolah//halaman/x`) — rute tidak terpengaruh (segmen kosong dilewati), canonical dinormalkan.
3. Tautan yang DIBUAT aplikasi **selalu relatif** (`halaman/profil`, `./`, `galeri/<slug>`) dan setiap
   halaman memakai **`<base href>` relatif** (`rute_base()`: `./` untuk satu segmen, `../` untuk lebih
   dalam). **JANGAN** menghitung prefiks absolut sendiri: bila kelak aplikasi dipasang pada domain
   sendiri, prefiks yang sudah tertulis akan ditambahkan lagi oleh platform dan tautannya bertumpuk.
   `<base>` diizinkan CSP `base-uri 'self'` (relatif = origin yang sama).
4. **Pengalihan 301** dilakukan halaman lama lewat `rute_lama_301()` (tidak berjalan bila halaman itu
   dimuat oleh router). Tautan gaya lama yang TERSIMPAN di basis data (Slider/Keunggulan/Program/menu
   bertipe tautan) diterjemahkan saat dicetak oleh **`tautan_publik()`**.
5. Alamat tak dikenal → **404 sungguhan** (sebelumnya selalu 200 berisi beranda = “soft 404”),
   garis miring di ujung dinormalkan (`/berita/` → `/berita`), dan tiap halaman mencetak
   **`<link rel="canonical">`** dari jalur yang diminta (benar pada alamat bawaan maupun domain sendiri).
6. Panel admin **tidak berubah** alamatnya (`/web-sekolah/admin/…`), hanya tautannya ke halaman publik
   yang ikut disesuaikan (mis. `admin/berita.php` → `../berita/<slug>`).

### Jebakan yang sudah menggigit
- 🚨 **Regex ber-pembatas `#` yang memuat `#` di dalam kelas karakter** (`[\?#]`) membuat PHP
  mengeluarkan peringatan **“Unknown modifier”**; peringatan itu tercetak DI DALAM atribut `href`
  sehingga tombol PPDB melebar dan halaman **meluber 352px di HP** walau statusnya 200. Tulis `[?\#]`.
  Karena itu `websk_uji_rute.sh` memeriksa **isi halaman** bebas teks peringatan PHP, bukan hanya log.
- 🚨 Menghapus/mengganti penanda `<base>` membuat halaman dalam (mis. `/halaman/tenaga-pendidik`)
  kehilangan CSS/JS **tanpa galat apa pun** — gejalanya halaman tampak polos.
- 🚨 `hal_url()`/`hal_filter()`/`bos_tautan()`/`video_hal_url()` memakai **jalur halaman yang sedang
  dibuka** (`rute_kanonik()`) dan **membuang parameter `slug`** (slug sudah bagian jalur). Jangan
  mengembalikannya menulis `halaman.php?slug=…`.
- 🚨 Berkas yang menerima POST **tidak boleh dialihkan 301 saat POST** — browser mengubah 301 POST
  menjadi GET sehingga isian pengguna hilang. Pengalihan `asisten.php` karena itu diletakkan
  **sesudah** blok POST, dan formulir publik memakai `action` alamat rapi (`ppdb`, `kontak`, `cari`,
  `asisten`).
- ⚠️ Uji lama yang memakai alamat gaya lama sudah disesuaikan (skrip `.sh` memakai alamat rapi;
  `websk_admin_ui.js`/`websk_galeri_ui.js`/`websk_asisten_ui.js`/`websk_ukur.js` menyesuaikan
  selektor `form[action=…]`, `a[href^="../berita/"]`, `galeri/<slug>`).
- ⚠️ `websk_cek_live.sh` kini **berbasis data** untuk slug/jenis menu, album (hanya yang AKTIF), tahun
  album terbaru, berkas Ijop, dan jawaban asisten (memakai jar cookie) — pemilik sudah sering
  mengganti nama/slug menu sehingga patokan lama (mis. `data-siswa`, `prestasi`) menjadi gagal palsu.

## Uji
- `bash websk_uji.sh <skrip.js>` — menjalankan server PHP pada port 8899 lalu skrip Node;
  `bash websk_uji.sh --curl "halaman/sejarah" …` untuk memeriksa status halaman + log galat PHP.
  ⚠️ Skrip ini (juga `websk_cek_live.sh`, `websk_uji_pip/bos/sarpras.sh`) menjalankan **router tiruan
  `websk_router.php`** (disalin jadi `uji_router.php`) supaya `php -S` meniru perilaku Caddy
  (berkas nyata dilayani apa adanya, jalur lain → `index.php`) — tanpa itu semua alamat rapi 404 lokal.
- `bash websk_uji_video_teratas_ui.sh` (+ `websk_uji_video_teratas_ui.js`) — **19 pemeriksaan**
  VIDEO YOUTUBE BARU PALING ATAS (v1.17.1): tambah 3 video berturut-turut → urut terbaru paling atas
  di panel, galeri publik (paling atas) & slider beranda (paling kiri), ▲▼ tetap bekerja, sunting video
  lama tidak memindahkannya, hapus video teratas → berikutnya jadi teratas, tanpa luapan HP 390px.
- `bash websk_uji_asisten_foto_ui.sh` (+ `websk_uji_asisten_foto_ui.js`) — **35 pemeriksaan**
  FOTO/IKON ASISTEN (v1.17.0): tombol mengambang 72px (64px di HP) benar-benar bergambar & termuat,
  avatar kepala/balasan memakai gambar yang sama, unggah foto sungguhan lewat panel (FAQ Asisten →
  Foto Asisten) → URL media proxy dipakai di seluruh halaman publik, hapus → ilustrasi bawaan,
  tanpa luapan & tanpa galat konsol. Salinan di `/tmp` (basis data asli tidak disentuh).
- `bash websk_uji_rute.sh` — **108 pemeriksaan ALAMAT RAPI** pada salinan yang dilayani di
  `…/web-sekolah/` (meniru alamat live): setiap alamat rapi 200 & berisi halaman yang benar, setiap
  alamat lama 301 ke alamat rapi, garis miring di ujung dinormalkan, jalur tak dikenal **404**,
  aset terjangkau, `<base>`/canonical benar, tautan gaya lama hilang dari beranda, panel admin tetap
  200, dan **isi halaman bebas dari teks peringatan PHP**.
- `bash websk_uji_rute_ui.sh` (+ `websk_rute_ui.js`) — **30 pemeriksaan Playwright** pada alamat
  berprefiks: gaya tema benar-benar berlaku di halaman dalam, aset & skrip termuat (0 permintaan
  gagal, 0 galat konsol), tautan menu berpindah ke alamat rapi, pencarian & PPDB memakai alamat rapi,
  pengalihan `index.php` bekerja, jalur tak dikenal 404, dan 390px tanpa luapan.
- `bash websk_uji.sh websk_admin_ui.js` — **473 pemeriksaan** Playwright: login (sandi salah ditolak,
  batas percobaan), 27 halaman admin (status 200, tanpa luapan, tanpa galat PHP), CRUD slider + unggah
  gambar nyata ke media proxy, berita, agenda, fasilitas, testimoni, kategori, keunggulan, menu/halaman
  baru, kelas, guru, siswa, impor guru & siswa (tempel CSV), kenaikan **dan pembatalannya**, album +
  **unggah 2 foto sekaligus**, video YouTube (tautan sah/tidak sah), PPDB (seleksi, pengaturan,
  formulir publik + berkas, cek status, captcha salah ditolak), pesan kontak, identitas + teks beranda,
  akun **editor** (403 pada Pengguna/Identitas, 200 pada konten), pengalihan tanpa masuk, dan tampilan HP.
  Tambahan v1.1.0: ukuran tombol PPDB, **penyunting isi** (tebal/miring/garis bawah/perataan tersimpan
  pada medan & bertahan di halaman publik), **sisip gambar** dari penyunting sampai tampil di publik
  dengan lebar menyesuaikan, **lembar cetak guru & siswa** (data, saringan, tanda tangan, toolbar
  tersembunyi saat mencetak), tombol **Tambah Keunggulan/Program** pada tiap kartu, serta
  **hapus semua** guru & siswa (kata konfirmasi salah ditolak, siswa + riwayat kenaikan dibersihkan,
  kartu hilang saat data kosong).
  Tambahan v1.2.0: **beranda baru** (4 berita 2 kiri & 2 kanan sejajar, urutan bagian dikunci
  `data-bagian`, Program Unggulan di bawah berita sebagai slider, slider guru ukuran sedang,
  foto kepala di atas judul sambutan, tanpa Tautan Cepat, slider foto galeri + tombol panahnya),
  dan panah slider benar-benar menggeser isi.
- `bash websk_uji.sh websk_profil_ui.js` — **89 pemeriksaan** halaman Profil Lengkap & Bagan
  Struktur Organisasi peta tetap (lihat bagian v1.7.6, v1.7.9–v1.7.14 di bawah).
- `bash websk_uji.sh websk_galeri_ui.js` — **89 pemeriksaan** galeri dua bagian (Album Foto +
  Video Kegiatan), sampul album pada beranda, album bertahun, filter tahun, video 12/halaman, dan
  hak akses Editor Berita (lihat bagian v1.7.7, v1.7.8, v1.7.15 & v1.7.16).
- `bash websk_uji.sh websk_ukur.js` — pengukuran nyata: beranda (tombol PPDB ≤44px, 4 butir strip
  sebaris, kotak statistik sebaris, lebar kolom), gambar isi berita tidak melebihi artikel,
  **lembar cetak** (toolbar hilang saat mencetak, tabel muat area A4 mendatar, **PDF 842×595 pt =
  A4 mendatar**, 1 halaman untuk data guru), **beranda baru** (berita 2 kiri & 2 kanan sejajar &
  sama lebar, kartu program ≥280px, slide foto ≥280px, foto guru 90–170px, foto kepala ≥150px,
  urutan bagian benar), **geser otomatis slider tanpa interaksi**, serta beranda & panel admin pada
  **390px tanpa luapan**. Skrip ini mengunggah 6 foto ke galeri lebih dulu agar track slider foto
  benar-benar bisa digeser.
- `bash websk_uji.sh websk_shot.js` (+ `HAL=`/`LEBAR=`/`NAMA=`) — tangkapan layar & cek luapan cepat.
- `bash websk_uji_bos.sh` — **72 pemeriksaan** halaman **Informasi BOS** (sumber data e-BOS,
  kemampuan memutakhirkan diri, baca-saja, migrasi v15) — lihat bagian v1.7.18.
- `bash websk_uji_bos_ui.sh` (+ `websk_uji_bos_ui.js`, **36**) — pemeriksaan terukur (Playwright)
  halaman Informasi BOS: tata letak, grafik, tombol Buku Kas, dan hasil cetak A4 mendatar.
- `bash websk_uji_pendaftar.sh` — **127 pemeriksaan DATA PENDAFTAR**: skema v22 (`ppdb_lama`),
  parser tabel panel (3 varian HTML: `<th>`, tanpa `<th>`, judul "No Pendaftaran"), simpan/ganti per
  tahun, penggabungan panel (2022–2026) dengan PPDB Online website (2027+), halaman admin, dan
  **alur login + tarik NYATA lewat HTTP memakai panel tiruan** (`websk_panel_tiruan.php`) — lihat v1.8.5.
- `bash websk_uji_efek.sh` (+ `websk_uji_efek_ui.js`, **46**) — pemeriksaan TERUKUR **EFEK kartu
  Data Guru** + **SLIDER THUMBNAIL VIDEO beranda**: kartu punya transisi & garis aksen emas, saat
  disorot kartu benar-benar TERANGKAT (posisi atas bergeser), bayangan & garis tepi berubah, foto
  membesar, aksen muncul, lalu kembali normal saat kursor keluar; grid 4 kolom & jarak ≥16px;
  **mode hemat gerak** (browser terpisah, `reducedMotion:'reduce'`) mematikan transisi & angkatan;
  slider video: thumbnail memakai `i.ytimg.com/vi/<id>/hqdefault.jpg` (gambar benar-benar termuat
  setelah digulir — `loading="lazy"`), bersap 16:9, ada tombol main & judul, **berada DI ANTARA
  slider album dan tombol media sosial**, tombol panah menggeser & mengembalikan jalur, klik
  thumbnail membuka **popup pemutar** berisi `youtube.com/embed/<id>` yang benar + judulnya, popup
  muat di layar & bersap 16:9, tombol Tutup menutup popup **dan mengosongkan `src` iframe**;
  iframe pemutar **KOSONG saat halaman dibuka** (tidak memuat video lebih dulu); HP 390px tanpa
  luapan; tanpa galat konsol & tanpa permintaan gagal (aset pihak ketiga YouTube dikecualikan) —
  lihat v1.12.0.
- `bash websk_uji_periode.sh` — **68 pemeriksaan TAHUN AJARAN & SEMESTER + PERANGKAT PER SEMESTER**
  (skema v28): migrasi v27→v28 pada salinan yang SENGAJA diturunkan ke bentuk v27 (tabel perangkat_kelas
  tanpa kolom periode + tabel `periode` dihapus + berkas lama dikembalikan ke `perangkat.rpp`) — kolom &
  kunci unik baru, periode awal dari tahun ajaran sekolah, berkas lama disalin (tidak hilang);
  halaman publik: **filter Tahun Ajaran & Semester** tampil, kalimat lama & chip ringkasan **sudah
  dihapus**, pilihan periode lain kosong (berkas terpisah), nilai periode asing diabaikan, periode
  berjalan dibuka lebih dulu; **menu baru `admin/periode.php`**: daftar + tambah satu tahun ajaran
  dengan beberapa semester sekaligus, normalisasi `2025-2026` → `2025/2026`, penolakan tahun ajaran
  tidak berurutan, pindah periode berjalan, aktif/nonaktif, ubah tanpa menggandakan; **panel Perangkat
  Guru**: pemilih periode, unggah ke periode Genap tidak menyentuh Ganjil, satu baris berkas tiap kelas
  pada periode itu, unduhan benar-benar mengambil berkas periode itu (isi berkas dicek), tautan membawa
  `ta`+`sm`; keadaan **sekolah tanpa periode** (berkas tetap tampil + keterangan); pengaman (302 tanpa
  login, 419 tanpa CSRF, 403 untuk akun non-Administrator); kerapian — lihat v1.11.0.
- `bash websk_uji_perangkat.sh` — **132 pemeriksaan PERANGKAT GURU**: skema v27 (`perangkat` +
  `perangkat_kode` + kolom `kelas` + tabel **`perangkat_kelas`**), migrasi jenis menu, kolom tabel
  persis seperti contoh pemilik (**termasuk KELAS**), **daftar guru otomatis** (aktif, kepala madrasah
  TIDAK termasuk, wakil kepala & nonaktif tetap benar), **mata pelajaran lebih dari satu per guru**,
  **satu mata pelajaran boleh di BEBERAPA kelas** (satu baris per kelas; urut mengikuti Data Kelas;
  mapel tanpa kelas tetap tampil dengan tanda —), centang hijau/silang merah + tombol Download,
  **BERKAS BERBEDA TIAP KELAS** (unggah tiga berkas RPP untuk tiga kelas → tiga baris kelas menyimpan
  berkasnya sendiri, tautan unduh membawa `k`, dan isi berkas yang terunduh berbeda per kelas),
  kode unduh **berbeda tiap mata pelajaran** + generate 8 karakter, **GENERATE SEKALIGUS** (semua kode
  berubah & seluruhnya unik) + tombol **"Isi yang belum ada kode"** (hanya mengisi yang kosong),
  **daftar mata pelajaran pada tabel kode mengikuti input Super Admin** (mapel dari Data Guru yang
  belum diinput tidak muncul), halaman kode unduh (kode salah → pesan & sisa percobaan; kode benar →
  berkas dialirkan; berkas belum ada → pesan jelas), panel Super Admin berbentuk **TABEL**
  (unggah per kelas/generate/tambah/hapus/atur kelas — menyimpan kelas tidak menghapus berkas),
  CSRF 419, editor 403, kerapian, **filter periode (v28) tampil & kalimat lama + chip ringkasan
  dihapus**, dan **migrasi v25→v26 + pemulihan diri** (kolom hilang → dibuat ulang tanpa kehilangan
  baris/berkas) — lihat v1.11.0/v1.10.0/v1.9.0/v1.8.11.
- `bash websk_uji_perangkat_ui.sh` (+ `websk_uji_perangkat_ui.js`, **84**) — pemeriksaan terukur:
  susunan 7 kolom (Kelas di urutan ke-3) & lebar kolom, kerapian tabel (kepala hijau muda, garis,
  rata tengah, sel berkas sama tinggi, tombol satu baris), **popup kode download benar-benar terbuka**
  saat Download diklik (judul, isian kode fokus, membawa guru/mapel/**kelas**/jenis, ada CSRF), tutup
  popup, kode salah → pesan, **kode benar → berkas benar-benar terunduh lewat peramban**, panel Super
  Admin berbentuk tabel (kolom, kotak centang kelas, isian berkas ber-`form`), **mengatur DUA kelas
  satu mata pelajaran lewat antarmuka lalu tampil sebagai baris tersendiri di halaman publik**, dan
  HP 390px (halaman publik & panel admin).
- `bash websk_banding_edm_ui.sh` (+ `websk_banding_edm_ui.js`, **131**) — **UJI BANDING TERUKUR**:
  menjalankan SALINAN website + SALINAN aplikasi EDM berdampingan, mengukur properti gaya yang
  benar-benar dihitung peramban (warna, garis, radius, padding, ukuran/tumpukan huruf, perataan,
  bayangan) pada kartu, kotak nilai SKPM, pil predikat, tab, kepala/sel/kaki tabel — lalu
  membandingkan **opsi grafik radar** (jenis, label, nilai, legenda "EDM Tahun …", warna garis/isian,
  skala 0-100, langkah tick 25, label sumbu "nilai (Aspek)", tooltip) — semuanya harus SAMA, sekaligus
  memastikan "Cetak Rekap" & "Ringkasan Pengisian Tiap Aspek" benar-benar tidak ada di website.
  Uji ini yang dipakai membuktikan permintaan pemilik "buat sama persis dengan aplikasi EDM".
- `bash websk_uji_edm.sh` — **85 pemeriksaan EVALUASI DIRI MADRASAH (Indikator EDM)**: skema v24 +
  migrasi jenis menu, kartu Indikator EDM (tahun + nilai SKPM + predikat + "x dari 26 butir sudah
  diisi … nilai masih sementara"), tab Charts/Tabel, kolom No/Aspek/Nilai SKPM/Terbilang + baris kaki
  SKPM, grafik radar (kanvas + data JSON), **dua bagian yang DIMINTA HILANG (Cetak Rekap & Ringkasan
  Pengisian Tiap Aspek)**, pemilih tahun, baca-saja, dan **UJI SILANG angka dengan kode aplikasi EDM
  sendiri** pada basis data yang sama (harus identik) — lihat v1.8.9.
- `bash websk_uji_edm_ui.sh` (+ `websk_uji_edm_ui.js`, **52**) — pemeriksaan terukur: kartu & tabel
  sesuai aplikasi EDM, **grafik radar benar-benar tergambar** (tipe radar, skala 0-100, label judul
  singkat aspek), pindah tab Charts/Tabel dari antarmuka, teks "Cetak Rekap"/"Ringkasan Pengisian
  Tiap Aspek" benar-benar TIDAK ada, saringan tahun, HP 390px tanpa luapan, tanpa galat konsol.
- `bash websk_uji_grafik_alumni.sh` — **49 pemeriksaan GRAFIK ALUMNI PER TAHUN** (halaman menu
  Siswa → Data Alumni): komponen bersama, fungsi `alumni_per_tahun()`, kartu & kanvas grafik, posisi
  DI ATAS filter tahun, label & nilai = data (tahun yang ada alumninya saja), alumnus nonaktif tidak
  dihitung, tahun tanpa alumni/alumni tanpa tahun tidak masuk grafik, grafik tak berubah saat
  difilter, dan halaman Pendaftar tidak terpengaruh refactor — lihat v1.8.8.
- `bash websk_uji_grafik_alumni_ui.sh` (+ `websk_uji_grafik_alumni_ui.js`, **30**) — pemeriksaan
  terukur: grafik alumni **benar-benar tergambar** (batang, tooltip & sumbu Y menyebut "alumni"),
  posisi di atas filter, label = tahun berdata (dibandingkan basis data), total = basis data, grafik
  tak berubah saat difilter, HP 390px tanpa luapan, tanpa galat konsol.
- `bash websk_uji_pendaftar_ui.sh` (+ `websk_uji_pendaftar_ui.js`, **65**) — pemeriksaan terukur:
  halaman menu Pendaftar (tanpa tab), tab yang sudah hilang dari halaman Data Siswa, filter tahun dari
  antarmuka, tahun 2027 muncul & tahun 2028 (belum ada pendaftar) memberi keterangan ramah, kolom &
  urutan baris, paginasi, halaman admin penarik, HP 390px.
- `bash websk_uji_sarpras.sh` — **66 pemeriksaan** halaman **Informasi Sarpras** (sumber data e-GURU,
  isolasi antar sekolah, kemampuan memutakhirkan diri, baca-saja, migrasi v16) — lihat bagian v1.7.19.
- `bash websk_uji_sarpras_ui.sh` (+ `websk_uji_sarpras_ui.js`, **42**) — pemeriksaan terukur
  halaman Informasi Sarpras: sembilan kolom, pil kondisi, perbandingan baris demi baris dengan basis
  data e-GURU, dan tampilan HP.
- `bash websk_uji_pip.sh` — **105 pemeriksaan** halaman **Layanan PIP** (menu dibuat otomatis,
  unggah SK/kartu/surat, kata sandi Kartu PIP, dan unduhan ber-sandi) — lihat bagian v1.7.20.
- `bash websk_uji_pip_ui.sh` (+ `websk_uji_pip_ui.js`, **47**) — alur lengkap lewat peramban:
  mengisi panel, mengunggah berkas, menetapkan sandi, lalu mengunduh Kartu PIP dengan sandi.
- `bash websk_uji_perpus.sh` — **98 pemeriksaan** halaman **Informasi Perpustakaan** (empat tombol
  laporan, laporan tampil di bawah tombol, saringan tanggal, kemampuan memutakhirkan diri saat
  e-PERPUS diubah, baca-saja, migrasi v19) — lihat bagian v1.8.1.
- `bash websk_uji_perpus_ui.sh` (+ `websk_uji_perpus_ui.js`, **72**) — pemeriksaan terukur
  (Playwright): urutan & jarak keempat tombol, laporan benar-benar tampil DI BAWAH tombol, tiap
  tombol ditekan menampilkan laporan yang benar beserta kolomnya, saringan tanggal dari antarmuka,
  tanpa tombol cetak, serta 1440px & 390px tanpa luapan.
- `bash websk_uji_supervisi.sh` — **93 pemeriksaan** halaman **Informasi Supervisi Akademik**
  (dashboard aplikasi Supervisi khusus MA. Sirojuth Tholibiin Mootilango, angka dibandingkan dengan
  basis data Supervisi, saringan Tahun Ajaran & Semester, bagian yang TIDAK ditampilkan, tidak bisa
  mencetak, baca-saja, migrasi v20) — lihat bagian v1.8.2.
- `bash websk_uji_supervisi_ui.sh` (+ `websk_uji_supervisi_ui.js`, **44**) — pemeriksaan terukur
  (Playwright): empat kartu statistik & kartu menu sebaris, **keempat grafik benar-benar tergambar**
  (`Chart.getChart`), saringan TA/semester mengubah angka lewat antarmuka, tombol cetak 0 & saat
  media cetak tabel rinci (nama guru) + grafik disembunyikan, HP 390px, tanpa luapan/galat konsol.
- `bash websk_uji_voting.sh` — **83 pemeriksaan** halaman **Informasi e-Voting OSIM** (papan hasil
  Real-Count: setelan aplikasi dihormati, angka sama dengan basis data e-Voting, endpoint JSON,
  kerahasiaan pemilih, baca-saja, migrasi v21) — lihat bagian v1.8.3.
- `bash websk_uji_voting_ui.sh` (+ `websk_uji_voting_ui.js`, **47**) — pemeriksaan terukur
  (Playwright): keadaan "hasil belum dibuka", dua grafik tergambar, angka **berubah SENDIRI tanpa
  memuat ulang** setelah suara baru masuk di basis data, penyegaran berhenti saat tab disembunyikan
  lalu segar saat tab kembali, HP 390px, tanpa luapan/galat konsol.
- Data uji selalu dibuat di `web-sekolah/data/sekolah.sqlite` (basis data aplikasi ini sendiri);
  untuk mengembalikan ke isi awal: hapus berkas itu + `data/.schema_ok` → ter-seed ulang saat
  permintaan pertama.
  ⚠️ **`websk_uji.sh` SELALU menguji pada SALINAN di `/tmp/websk_app`** (tanpa `data/`), karena basis
  data aplikasi yang sudah dipublikasikan bersifat READ-ONLY dari Bash dan uji tidak boleh menyentuh
  data asli pemilik.


## v1.19.0 — INPUT JADWAL DIPERCEPAT: CENTANG BEBERAPA KELAS SEKALIGUS (tanpa perubahan skema)

Permintaan pemilik:
> *"Pada bagian kelas buat agar bisa memilih 3 kelas dengan centang. Begitu juga dengan mata
> pelajarannya bisa menginputkan 1 mata pelajaran untuk setiap kelas sekaligus. Dan untuk gurunya
> pun menyesuaikan. Tujuannya untuk mempercepat penginputan jadwal."*

### A. Formulir "Tambah Baris Jadwal" (`admin/kurikulum.php`)
- Pilihan kelas tunggal **diganti TABEL PEMILIH** `#tblPilihKelas` berkolom
  **Pilih (centang) · Kelas · Mata Pelajaran · Guru** — satu baris per kelas dari **Data Kelas**.
  Baris yang dicentang diberi latar hijau muda (`:has()` hanya pemanis; tanpa JavaScript tetap jalan).
- Nama medan: `kelas[]` (nilai = NAMA kelas), `mapel_kelas[<kelas>]`, `guru_kelas[<kelas>]`, dengan
  kunci **`rawurlencode(nama kelas)`** supaya nama kelas yang memuat titik/tanda kurung tidak diubah
  PHP (PHP mengganti `.` menjadi `_` pada kunci array formulir). Penolong **`kur_medan_kelas()`**
  membaca kunci terkode lebih dulu, lalu kunci mentah (kompatibel formulir lama).
- **Satu kelas dicentang = satu baris jadwal** (waktu & jam ke sama), semuanya disimpan dalam SATU
  transaksi `BEGIN IMMEDIATE` dengan `urutan` berurutan; pesan sukses menyebut jumlah baris + nama
  kelasnya.
- **Baris kegiatan** tetap ada: kolom **"Atau Nama Kegiatan (baris tanpa kelas)"** (nama medan `mapel`,
  gaya lama). Bila ada kelas dicentang, kolom ini DIABAIKAN; bila tidak ada yang dicentang, baris
  kegiatan dibuat dengan `kelas = ''` dan `jam_ke = 0` (menggabung seluruh kolom kelas di website).
- **Kelas dicentang tetapi mata pelajarannya kosong → DITOLAK** dengan pesan yang menyebut kelas mana
  yang belum diisi (tidak ada baris yang tersimpan) — supaya tidak ada baris jadwal "kosong" yang
  lolos ke website.
- **Mode UBAH (`?edit=ID`) tetap SATU kelas** (pemilih `select#jadwal_kelas` + `mapel` + `guru`):
  mengubah satu baris tidak boleh diam-diam menambah baris lain. Untuk mengisi banyak kelas,
  gunakan formulir Tambah.
- Halaman publik **tidak berubah**: beberapa baris dengan (waktu, jam ke) yang sama otomatis
  digabung menjadi SATU baris slot oleh `kurikulum_jadwal_susun()`.

### B. 🚨 JEBAKAN yang sudah menggigit di fitur ini
`$kelas = potong((string) ($_POST['kelas'] ?? ''), …)` — pada mode centang, `$_POST['kelas']` adalah
**ARRAY**, dan `(string) array` memicu peringatan **"Array to string conversion"** yang keluar
**SEBELUM header** sehingga `redirect()` gagal: tombol Simpan menjawab **HTTP 200** dan datanya tidak
tersimpan (gejala: "tombol simpan tidak bereaksi"). Sekarang nilainya diperiksa dulu
(`is_array($kelasMentah) ? '' : …`). **Aturan umum**: medan yang bisa berupa array jangan pernah
di-cast langsung ke string.

### C. Kelas utilitas `.g1` (perbaikan luapan 7px)
`class="grid g12"` yang dipakai versi pertama panel ini **tidak pernah ada** di tema; grid tanpa
`grid-template-columns` memakai kolom `auto`, yang lebarnya = **min-content** isinya, sehingga tabel
pemilih kelas yang lebar membuat **dua kartu meluber 7px di HP 390px** (terukur). Sekarang ada
**`.g1{grid-template-columns:minmax(0,1fr)}`** pada `assets/style.css` dan panel memakai
`class="grid g1"`. **Aturan**: setiap wadah kartu berisi tabel lebar WAJIB memakai kolom
`minmax(0,1fr)` (bukan `auto`/`1fr` polos) supaya `.tabel-wrap` (overflow-x) benar-benar bisa menyusut.

### D. Uji
- `bash websk_uji_kurikulum.sh` (**176**) — bagian baru: formulir memakai `kelas[]`/`mapel_kelas[]`/
  `guru_kelas[]`, **3 kelas dicentang → 3 baris** dengan mapel & guru masing-masing pada jam ke yang
  sama, ketiganya tampil pada SATU baris slot di halaman publik, kelas dicentang tanpa mapel
  **ditolak**, dan dua kelas dengan mapel sama → dua baris.
- `bash websk_uji_kurikulum_ui.sh` (+ `.js`, **62**) — Playwright: tabel pemilih berkolom
  Pilih · Kelas · Mata Pelajaran · Guru, baris tercentang berubah warna, **centang 3 kelas lewat
  antarmuka → 3 baris tersimpan** (pesan "3 baris jadwal ditambahkan"), tampil di website pada satu
  baris slot, lalu ketiganya dihapus lewat tombol hapus (dialog konfirmasi diterima).

## v1.18.0 — MENU KURIKULUM: KALDIK, JADWAL MENGAJAR (LANDSCAPE), & DOKUMEN KURIKULUM — skema v34/v35

Permintaan pemilik:
> *"Pada bagian Menu Kurikulum buatkan isiannya di halaman admin dan operator (yelfis). Satu menu
> untuk beberapa isian: **Kaldik Operasional** buat inputan gambar untuk menampilkan Kalender
> Pendidikan; **Jadwal mengajar** buat inputan hari, waktu, jam ke, Mata Pelajaran + gurunya di
> setiap kelas. Contoh hasilnya yang ditampilkan di web seperti yang saya upload (**Buat
> Landscape**). Di tampilkan berdasarkan **filter hari**; **Dokumen kurikulum** buat inputan untuk
> mengupload dokumen pdf/word/excel/gambar. Hasilnya ditampilkan dalam bentuk **kartu dokumen yang
> bisa di download oleh pengunjung website**."*

Pilihan yang dipakai (jangan diubah tanpa bertanya):
1. **Satu panel admin** (`admin/kurikulum.php`) berisi TIGA TAB — bukan tiga halaman terpisah.
   Penjaganya `require_kelola_kurikulum()` = **Administrator DAN peran `editor`** ("operator
   (yelfis)"), pola yang sama dengan Layanan PIP / Kohort / Prestasi per Kelas. Peran
   **`berita` (Editor Berita) → 403**, tamu → 302.
2. **Kaldik** = GAMBAR yang diunggah (boleh lebih dari satu, mis. per semester; bisa
   diurutkan/disembunyikan/dihapus). Yang disimpan hanya **URL media proxy**.
3. **Jadwal** = satu baris per **(hari · waktu · jam ke · kelas)**; **jam ke kosong ATAU kelas
   kosong = baris KEGIATAN** (Sholat Dhuha / Istirahat / Sholat Dhuhur) yang pada tabel publik
   dicetak sebagai SATU baris menggabung seluruh kolom kelas (persis contoh gambar pemilik).
   Kolom kelas diambil dari **Data Kelas** (+ kelas yang muncul pada jadwal hari itu).
   Halaman publik disaring **per hari** lewat tombol "Filter Hari" (tautan biasa, bekerja tanpa
   JavaScript).
4. **Dokumen** = PDF/DOCX/XLSX/PPTX/CSV/TXT/ZIP/JPG/PNG/WEBP (mengikuti batas media proxy);
   pengunjung mengunduh lewat **`kurikulum_unduh.php`** yang **mengalirkan berkas dari server**
   (URL media tidak pernah sampai ke peramban, nama unduhan = nama berkas asli).

### A. Skema v34 (+ v35) — tiga tabel baru
- `kurikulum_kaldik` (judul, keterangan, gambar, urutan, aktif, dibuat, diubah),
  `kurikulum_jadwal` (hari, waktu, jam_ke, kelas, mapel, guru, urutan, dibuat, diubah),
  `kurikulum_dokumen` (judul, keterangan, url, nama_berkas, jenis, ukuran, urutan, aktif, dibuat,
  diubah) + 3 indeks (`idx_kurikulum_kaldik`, `idx_kurikulum_jadwal`, `idx_kurikulum_dokumen`).
  Ketiganya didaftarkan di **`SCHEMA_TABEL_WAJIB`** & **`SCHEMA_KOLOM_WAJIB`**; hanya
  `CREATE TABLE`/`CREATE INDEX` — **tidak ada tabel lama yang dibangun ulang**, dan tabelnya
  **KOSONG** setelah migrasi (tidak ada contoh yang dipaksakan).
- **`db_menu_kurikulum()`** (idempoten): membuat ketiga sub menu bila belum ada (induk = menu yang
  memuat halaman Perangkat Guru → induk menu "Kurikulum" → induk dari menu Kurikulum bila Kurikulum
  sendiri sub menu, agar tidak menjadi menu tingkat ketiga) lalu **menyesuaikan `jenis`** baris yang
  masih `halaman` menjadi `kaldik` / `jadwal_mengajar` / `dokumen_kurikulum`. Dijaga penanda
  `kurikulum_menu_v1` supaya nama/urutan/penghapusan oleh admin tidak dibatalkan migrasi.
- 🚨 **SKEMA v35 = MEMASTIKAN JENIS MENU DITERAPKAN (kejadian NYATA, sama seperti v30 Kohort).**
  Aplikasi ini LIVE dan pemilik sedang memakainya: sebuah permintaan sempat menjalankan migrasi
  **di sela penyuntingan `inc/db.php`**, sehingga tabel kurikulum + penanda `34` sudah terpasang
  sementara `db_menu_kurikulum()` belum ada di kode ⇒ penanda dianggap cocok, migrasi tidak pernah
  jalan lagi, dan ketiga sub menu tetap berjenis `halaman` (halaman publiknya kosong walau tabel
  sudah ada). Perbaikannya: **naikkan `SCHEMA_VERSION` satu langkah (35)** sehingga seluruh migrasi
  (idempoten) berjalan sekali lagi. **PELAJARAN: jangan menaikkan SCHEMA_VERSION sebelum SELURUH
  perubahan `inc/db.php` selesai ditulis.** Kasus ini diuji khusus oleh
  `cek_live_kurikulum_websekolah.sh` bagian 1 (keadaan rusak disimulasikan pada SALINAN data asli).

### B. Halaman publik (`inc/kurikulum.php`, dipanggil `halaman.php`)
- **`kurikulum_kaldik_papan()`** — kartu tiap gambar (klik = ukuran penuh) + tombol Cetak; kalender
  tanpa gambar tetap tampil sebagai kotak pengganti (tidak pernah kotak rusak); keadaan kosong ramah.
- **`kurikulum_jadwal_papan()`** — kotak **`#jdwFilter` "Filter Hari"** (7 hari, hari terpilih
  tersorot, hari tanpa data ditandai putus-putus + keterangan) di ATAS tabel
  **`#tblJadwal`** berkolom **Hari · Waktu · Jam Ke · Kelas** (nama kelas sebagai sub-kolom), kolom
  Hari digabung `rowspan`, mapel tebal + **nama guru dalam tanda kurung**. Hari bawaan = hari ini
  bila ada jadwalnya → hari pertama yang berisi → Senin. `?hari=Ngawur` **diabaikan** (200, bukan
  403). Baris kegiatan memakai latar biru muda seperti contoh pemilik.
- **`kurikulum_dokumen_papan()`** — kartu dokumen (`.dok-kartu`) berisi ikon berwarna menurut jenis,
  judul, keterangan, jenis + ukuran, dan tombol **Unduh**.
- **CETAK MENDATAR**: `halaman.php` mencetak
  `<style>@media print{@page{size:A4 landscape;margin:10mm}}</style>` untuk jenis `jadwal_mengajar`
  (pola yang sama dengan Informasi BOS) dan memberi kelas `kurikulum`/`jadwal` pada `<body>`; aturan
  `@media print` di tema membuang kepala/kaki situs, melebarkan `.wadah`, menyembunyikan tombol
  Filter Hari, dan merapatkan tabel sehingga **muat area cetak A4 mendatar** (diukur Playwright:
  tabel ≤ 1047px, tanpa perlu digeser). Kaldik & Dokumen tetap A4 tegak.

### C. Panel admin (`admin/kurikulum.php`)
- Tiga tab (`.tab-prestasi`/`.tab-item`): **Kaldik Operasional · Jadwal Mengajar · Dokumen
  Kurikulum**, masing-masing dengan kartu daftar + formulir tambah/ubah.
- **Kaldik**: judul, keterangan, berkas gambar (pratinjau `data-pratinjau`), centang hapus gambar,
  tampil/sembunyi, ▲▼, hapus.
- **Jadwal**: pemilih hari (chip) untuk melihat/mengelola baris hari itu; formulir **Hari · Waktu ·
  Jam Ke · Kelas (dengan pilihan "Kegiatan (menggabung semua kelas)") · Mata Pelajaran/Nama
  Kegiatan · Guru** (guru punya `datalist` dari Data Guru). Validasi: hari harus sah, waktu wajib,
  mapel/nama kegiatan wajib; kelas kosong ⇒ `jam_ke` dipaksa **0** (baris kegiatan).
- **Dokumen**: judul wajib, berkas wajib (kecuali saat mengubah), keterangan, tampil/sembunyi,
  ▲▼, hapus, tautan unduh.
- Semua aksi tulis memakai **POST + CSRF** (419 bila tanpa token); urutkan/sembunyikan memakai
  tautan seperti panel lain (pola `keunggulan.php`).
- 🚨 **`berkas_isi_sah()` di `inc/helpers.php` DIPERLUAS (v1.18.0)**: sebelumnya hanya mengenali
  PDF/gambar, sehingga **DOCX/XLSX/PPTX SELALU ditolak** ("isinya tidak sesuai") padahal menu ini
  (dan Perangkat Guru) menerima jenis itu. Sekarang: `docx/xlsx/pptx/zip` = diawali `PK` **dan**
  ≥ 512 byte (berkas 2 byte berisi "PK" tetap DITOLAK), `doc/xls/ppt` = magic OLE, `csv/txt` = tanpa
  byte NUL, sisanya gambar (`getimagesize`).

### D. Uji
- `bash websk_uji_kurikulum.sh` (**160**) — skema v34 (3 tabel + 3 indeks + 11/10/8 kolom), jenis
  ketiga sub menu, halaman Kaldik (hanya yang aktif, tanpa gambar tetap rapi), **Jadwal** (7 chip
  hari, kolom Hari/Waktu/Jam Ke/Kelas, `colspan` baris kegiatan = jumlah kelas, guru dalam kurung,
  `@page A4 landscape`, filter hari benar-benar menyaring, hari tanpa data & `?hari=Ngawur`),
  **Dokumen** (kartu, URL media tidak bocor) + **unduhan sungguhan** (Content-Disposition & isi
  `%PDF`), panel admin untuk Administrator **dan akun Editor** (Editor Berita 403, tamu 302),
  CRUD jadwal (termasuk kegiatan), **unggahan nyata** gambar kalender & dokumen PDF **dan DOCX**
  (serta penolakan `.exe`, PDF palsu, dan DOCX 2 byte), CSRF 419, escape XSS, kerapian, dan
  migrasi idempoten.
- `bash websk_uji_kurikulum_ui.sh` (+ `websk_uji_kurikulum_ui.js`, **56**) — Playwright: kotak
  Filter Hari DI ATAS tabel, 7 tombol hari & hari tersorot, tabel dua baris kepala, sel kegiatan
  benar-benar selebar tabel, klik chip memuat hari lain, **emulasi media cetak** (Filter Hari hilang,
  tabel ≤ 1047px), kartu dokumen + **unduhan dengan nama berkas rapi**, tanpa luapan 1440/390,
  tabel bisa digeser di HP, dan akun **Editor** menambah/menghapus baris jadwal lewat antarmuka yang
  langsung tampil di website.
- `bash cek_live_kurikulum_websekolah.sh` (**34**, SALINAN basis data asli) — jalur NYATA (penanda 34
  + jenis menu belum diperbaiki) → v35 memperbaikinya, sidik baris seluruh tabel lama identik,
  isi menu selain `jenis` tidak berubah, tabel baru kosong, jalur v33 → v35, integritas & FK bersih,
  idempoten.

## v1.17.1 — VIDEO YOUTUBE BARU OTOMATIS PALING ATAS (tanpa perubahan skema)

Permintaan pemilik: *"Video atau link youtube yang di simpan paling terbaru letakkan otomatis
paling atas (yang saya tandai warna merah)"* — kotak merahnya menunjuk baris teratas tabel
**Video YouTube** pada panel Galeri Foto & Video.

- **Penolong baru `urutan_teratas()`** (`inc/admin_helpers.php`) = **`MIN(urutan) − 10`** (tabel kosong
  → 10). Berbeda dari `urutan_baru()` yang menaruh baris baru di **paling bawah** (`MAX + 10`) —
  jangan tertukar: `urutan_baru()` tetap dipakai tabel lain (agenda, fasilitas, foto album, dst).
- `admin/galeri.php` aksi **`video_simpan`** untuk video **BARU** memakai `urutan_teratas('galeri_video')`,
  jadi video yang paling baru disimpan langsung berada di **baris pertama** tabel panel.
- **Halaman publik tidak diubah sama sekali**: `video_publik()` (`inc/helpers.php`) dan daftar panel
  sama-sama membaca `ORDER BY urutan, id`, sehingga video terbaru otomatis menjadi **paling atas**
  pada bagian **"Video Kegiatan"** halaman galeri dan **paling kiri** pada slider video beranda
  (12 video terbaru).
- **Tombol ▲▼ tetap bekerja** (hanya menukar nilai `urutan`); karena nilai `urutan` **tidak pernah
  dicetak** sebagai angka pada tabel, nilai negatif tidak terlihat pengguna. **Mengubah** video yang
  sudah ada (`?edit=`) **tidak** mengubah urutannya.
- Pesan flash berbunyi **"Video ditambahkan dan diletakkan paling atas."**, dan keterangan pada kartu
  Video YouTube menjelaskan perilaku ini + saran memakai ▲▼ bila ingin urutan lain.
- Uji: **`bash websk_uji_video_teratas_ui.sh`** (19 pemeriksaan Playwright: tiga video baru →
  terbaru di baris pertama, ikut teratas di galeri publik & paling kiri di slider beranda, ▲▼ masih
  bekerja, penyuntingan video lama tidak memindahkannya, video keempat langsung teratas, hapus video
  teratas → berikutnya jadi teratas, tanpa luapan HP 390px). Suite lama tetap lulus:
  `websk_admin_ui.js` (485), `websk_uji_asisten_foto_ui.sh` (35), `websk_uji_rute.sh` (108).

## v1.17.0 — IKON TOMBOL ASISTEN MEMAKAI FOTO (tanpa perubahan skema)

Permintaan pemilik: *"Bisakah ikon chat asisten diganti? Jika bisa ganti dengan gambar orang yang
saya kirim sebagai ikon asisten web, buat agak sedikit besar atau di sesuaikan agar rapi"*.

⚠️ **Lampiran gambar pada obrolan TIDAK tersimpan di workspace** (tidak ada jalur berkasnya), jadi
fotonya tidak bisa saya pasang langsung. Yang dikerjakan: **jalur unggah foto asisten** + ukuran/kerapian
ikon, ditambah **ilustrasi bawaan** supaya ikonnya sudah berbentuk orang sebelum fotonya diunggah.
Untuk memasang foto yang diinginkan: **panel admin → FAQ Asisten → kartu "Foto Asisten (Ikon Obrolan)"**
→ unggah foto → Simpan (langsung berlaku di seluruh halaman publik).

- **Satu sumber foto** (`inc/asisten.php`): `asisten_foto()` (URL media proxy dari
  `pengaturan.asisten_foto`), `asisten_foto_src()` (foto itu atau **`assets/asisten.svg`** bila belum
  ada), dan **`asisten_ava($kelas)`** (markup avatar siap cetak). **JANGAN** menulis ikon/URL asisten
  sendiri di halaman lain — dulu ikon balon ditulis tangan di `assets/app.js` dan `asisten.php`.
- **Dipakai serentak** untuk: ikon **tombol obrolan mengambang** (`inc/layout.php`, ada `data-ava`
  untuk skrip), **avatar kepala kotak obrolan**, **avatar setiap balasan** (skrip `initAsisten()`
  membaca `data-ava`; halaman `asisten.php` memakai `asisten_ava()`).
- **Unggah** di `admin/faq.php` (aksi POST **`foto_asisten`**, `enctype=multipart/form-data`):
  pratinjau **bulat 104px** (`.ava-asisten`), `proses_gambar()` + `media_upload()` (JPG/PNG/GIF/WEBP/SVG,
  maks 15 MB), centang hapus untuk kembali ke ilustrasi bawaan. Yang disimpan **hanya URL CDN**.
- **Ukuran & kerapian** (`assets/style.css`): tombol 56px → **72px** (HP ≤520px → **64px**), foto bulat
  penuh `object-fit:cover; object-position:center 15%` (wajah tidak terpotong pada foto tegak), cincin
  emas 2.5px, titik "online" hijau, efek terangkat + `:focus-visible`; kotak obrolan digeser
  (`bottom:102px`, HP `92px`, `max-height:calc(100vh - 140px)`) agar tidak menutupi tombol.
  Avatar kepala 40px, avatar balasan 30px — semuanya `overflow:hidden` + gambar `cover`.
- **Ilustrasi bawaan** `assets/asisten.svg` (petugas ber-headset, gaya datar, latar transparan) —
  hanya dipakai bila admin belum mengunggah foto; jangan menghapusnya.
- Uji: **`bash websk_uji_asisten_foto_ui.sh`** (35 pemeriksaan Playwright: tombol 72/64px bergambar
  yang benar-benar termuat, titik online, jarak kotak–tombol ≥6px, avatar kepala/balasan memakai gambar
  yang sama, unggah foto SUNGGUHAN → URL `/app-media/` dipakai di tombol & avatar, hapus → kembali ke
  ilustrasi bawaan, tanpa luapan 1440/390, tanpa galat konsol). Suite lama tetap lulus:
  `bash websk_uji.sh websk_asisten_ui.js` (45), `bash websk_uji.sh websk_admin_ui.js` (485),
  `bash websk_uji.sh websk_ukur.js`.

## v1.16.2 — PAGINASI PANEL ADMIN DIPERBAIKI + CETAK DATA ALUMNI (tanpa perubahan skema)

Dua permintaan pemilik: *"Yang saya tandai warna merah itu tidak berfungsi ketika di klik"*
(tombol halaman 2/3/4 & "Selanjutnya" pada panel **Data Alumni**) dan *"tambahkan fitur untuk cetak
data alumni pada akun admin"*.

### A. 🚨 AKAR MASALAH paginasi panel admin (penting — jangan dikembalikan)
- `paginasi()` (`inc/helpers.php`) membuat tautannya lewat **`hal_url()`**, yang memakai
  **`rute_kanonik()`**. Pada **ALAMAT BERPREFIKS** (`/web-sekolah/…`) `rute_kanonik()` mengembalikan
  **`admin/alumni.php`** (prefiks aplikasi sudah dibuang). Tautan **relatif** itu dibaca peramban
  **dari dalam folder `/admin/`** sehingga menjadi **`/web-sekolah/admin/admin/alumni.php`** →
  **404**. Gejalanya: tombol halaman tidak bereaksi (halaman 404), dan di layar tampak "tidak
  berfungsi".
- **Perbaikannya**: `hal_url()` kini mendeteksi halaman panel (`…/admin` pada `SCRIPT_NAME` ATAU
  jalur kanoniknya berawalan `admin/`) lalu membangun tautan dari **nama berkasnya saja**
  (`alumni.php?hal=2`). Berlaku untuk SEMUA halaman panel yang memakai `paginasi()`: **alumni,
  guru, siswa, berita, pesan, log, pendaftar PPDB**. Paginasi halaman **PUBLIK** tidak berubah (tetap
  memakai alamat rapi relatif, mis. `halaman/data-alumni-2?hal=2`).
- ⚠️ **Cara mengujinya WAJIB memakai subfolder.** Bila aplikasi diuji pada akar domain
  (`http://127.0.0.1:PORT/…`), `rute_prefiks()` mengambil prefiks dari `SCRIPT_NAME` (`/admin`) sehingga
  bug ini **tidak pernah muncul** dan uji akan lulus palsu. Salinan harus diletakkan di
  `…/web-sekolah/` dan server dijalankan **dari AKAR DOKUMEN** (folder induk), bukan dari folder
  aplikasi — kalau tidak, `return false` router mencari berkas di tempat yang salah (halaman panel
  dijawab 404 oleh `php -S`).

### B. Lembar cetak baru `admin/cetak_alumni.php` (A4 MENDATAR)
- Tombol **"Cetak Data Alumni"** (`#btnCetakAlumni`) pada kepala kartu halaman Data Alumni
  membawa **saringan yang sedang aktif** (`cari`, `tahun`, `sumber`) — sama seperti tombol Cetak
  Data Guru/Siswa. Yang tercetak = yang tampil di layar, **tanpa batas halaman**.
- Kolom: **No, Nama Alumni, NIS, NISN, Jenis Kelamin, Tahun Masuk, Tahun Keluar, Keterangan,
  Sumber**; baris **JUMLAH** (L/P + jumlah tahun keluar + keterangan jumlah dari data kelulusan);
  tanda tangan **kepala sekolah/madrasah di KANAN** dengan baris "kecamatan, tanggal" di atasnya.
- `?auto=1` mencetak otomatis. `@page{size:A4 landscape;margin:10mm}` ditulis **di halaman ini**
  (bukan hanya tema) supaya PDF benar-benar A4 mendatar (terukur **842×595 pt**). Lembar dibungkus
  `.lembar-gulir` agar **tidak meluber mendatar di HP** (terukur 0px pada 390px).
- **Hak akses**: `require_login()` + pemeriksaan `boleh_buka_berkas('alumni.php')` — Administrator &
  akun **Editor** boleh (200), peran **`berita` 403**, tamu **302**.

### C. Uji
- `bash websk_uji_alumni_cetak.sh` (**68**) — dijalankan pada salinan yang **dilayani sebagai
  subfolder `/web-sekolah/`** (meniru alamat live): tautan paginasi `alumni.php?hal=2` (bukan
  `admin/alumni.php?hal=2`), `?hal=2` → 200 dengan 25 baris & isi berbeda, `?hal=3` tanpa tombol
  "Selanjutnya", paginasi guru/siswa tidak pernah absolut, paginasi publik tetap rapi; tombol cetak &
  lembar cetak (kolom, seluruh 61 alumni, baris JUMLAH, L/P, `@page` A4 mendatar, `?auto=1`, tanpa
  tautan absolut & tanpa `&middot;`), saringan tahun/kata kunci/sumber dihormati, dan hak akses
  (tamu 302, Editor 200, Editor Berita 403).
- `bash websk_uji_alumni_cetak_ui.sh` (+ `websk_uji_alumni_cetak_ui.js`, **48**) — Playwright: klik
  angka **2** → **200** & alamat `?hal=2` (tanpa `/admin/admin/`), isi halaman berbeda,
  "Selanjutnya" → halaman 3 (10 baris sisa, tombol hilang di halaman terakhir), paginasi guru/siswa
  juga bekerja; tombol Cetak Data Alumni → lembar berisi 60 alumni + kolom lengkap + L/P + tanda
  tangan; saringan `tahun=2025` → 30 baris; **PDF 842×595 pt (A4 mendatar)**, bilah tombol
  tersembunyi saat dicetak, tanpa luapan 1440/390, tanpa galat konsol.


## v1.16.0 — TMT + KETERANGAN GURU, POPUP DATA GURU, & TAMPILAN TABEL — skema v33

Tiga permintaan pemilik sekaligus:
1. *"Yang saya tandai warna merah tambahkan inputan untuk: TMT Guru (format tanggal-bulan-tahun),
   Keterangan (Satmikal atau Non Satmikal)"*
2. *"Pada halaman data guru, buat kartu guru bisa diklik, dan Ketika diklik akan menampilkan Popup
   Data: Nama Lengkap, Jenis Kelamin, Jabatan, Telepon, Masa Kerja (dihitung dari tanggal TMT hingga
   aktif sekarang. Misal Masa kerja 5 tahun, 3 bulan, 4 hari."*
3. *"Pada halaman data guru yang saya beri tanda biru tambahkan fitur tampilkan dalam bentuk tabel.
   Ketika tombol tabel yang diklik maka data guru tampil dalam bentuk tabel (semua guru) dan isinya
   sama dengan popup data guru."*

### A. Skema v33 — kolom `guru.tmt` & `guru.keterangan`
- Dua kolom baru lewat `db_add_column` **dan** ikut ditulis pada `CREATE TABLE` + didaftarkan di
  `SCHEMA_KOLOM_WAJIB` (`'guru' => ['tmt', 'keterangan']`). Hanya MENAMBAH kolom; guru lama bernilai
  kosong (tidak ada nilai yang ditebak) sehingga masa kerja tampil "-" sampai TMT-nya diisi.
- **Keterangan = pilihan TETAP** `GURU_KETERANGAN = ['Satmikal', 'Non Satmikal']`
  (`inc/guru.php`); penulisan lain dinormalkan (huruf besar/kecil) dan **nilai asing dikosongkan**
  (`guru_keterangan_sah()`), bukan ditebak.
- `guru_tmt_sah()` menerima `Y-m-d` (dari `<input type="date">`) dan juga tulisan Indonesia
  ("1 Januari 2020") lewat `tgl_impor()`; tanggal tidak sah (mis. 30 Feb) **tidak disimpan**.

### B. Modul `inc/guru.php` (fungsi `guru_*`)
- **`guru_masa_kerja($tmt)`** → `tahun/bulan/hari` + **`teks`** ("5 tahun, 3 bulan, 4 hari") dihitung
  dari TMT sampai HARI INI (satu sumber: popup, tabel, dan panel tidak pernah berbeda). Tanpa TMT
  atau TMT di masa depan → `'-'`.
- `guru_tmt_teks()` ("15 Januari 2020"), `guru_jk_teks()` ("Laki-laki"/"Perempuan"),
  `guru_inisial()`, `guru_foto_url()`, `guru_kartu_url()`.
- **`guru_blok_tampilan($daftar, $kanonik, $tampil, $guruId)`** mencetak: kontrol **Kartu / Tabel**
  (`#guruKontrol`, `?tampil=tabel`, bekerja tanpa JavaScript), **tabel semua guru** (mode tabel),
  `<script id="guruData" type="application/json">` (data popup), dan `<dialog id="popupGuru">`.

### C. Halaman publik Tenaga Pendidik (`halaman.php`, jenis menu `guru`)
- **Setiap kartu guru kini `<a class="guru-kartu guru-kartu-klik" data-popup-guru="ID">`** — dapat
  diklik; klik membuka **popup data** berisi **Nama Lengkap, Jenis Kelamin, Jabatan, Telepon, Masa
  Kerja** (+ "sejak TMT …"). **Tanpa JavaScript** kartu tetap tautan `?guru=ID` dan server mencetak
  dialog dalam keadaan **terbuka** (jadi selalu ada jalan tanpa skrip).
- Tombol **Tabel** (`#btnTampilTabel`) menampilkan **seluruh guru** dalam tabel berkolom **No, Foto,
  Nama Lengkap, Jenis Kelamin, Jabatan, Telepon, TMT, Masa Kerja, Keterangan** — isinya sama dengan
  popup (nama/jk/jabatan/telepon/masa kerja) + TMT & keterangan. Tombol **Kartu** mengembalikan.
  Nilai `?tampil=` asing → kembali ke mode kartu.
- Gaya `.gd-*`/`a.guru-kartu-klik`/`dialog.gd-popup`/`table.gd-tabel` di `assets/style.css`.

### D. Panel `admin/guru.php`
- Form Tambah/Ubah memuat **TMT** (`<input type="date" id="tmt">`, keterangan di bawahnya menampilkan
  masa kerja saat ini) dan **Keterangan** (`<select id="keterangan">`, dua pilihan).
- Daftar panel menambah kolom **TMT**, **Masa Kerja**, dan **Keterangan**.

### E. 🚨 JEBAKAN YANG MENGGIGIT LAGI (penting)
- Di `assets/app.js`, blok popup awalnya memakai penolong global **dua-dolar**; setelah disunting,
  penolong itu **berubah menjadi satu-dolar** sehingga `kartu.length` undefined → blok **berhenti
  diam-diam** (popup hanya terbuka karena server merender `?guru=ID`, dan halaman TETAP dimuat ulang
  saat kartu diklik). Sekarang blok itu memakai **`Array.prototype.slice.call(document.querySelectorAll(...))`**
  — **jangan** menuliskan penolong dua-dolar pada teks yang dikirim ke alat penyunting.
  Setelah menyunting `assets/app.js` SELALU jalankan `node --check assets/app.js` **dan** uji klik
  kartu (uji UI memeriksa URL tidak berubah saat popup dibuka).

### F. Uji
- `bash websk_uji_guru_popup.sh` (**94**) — skema v33 (2 kolom), hitungan masa kerja dibandingkan
  dengan hitungan mandiri PHP, format "N tahun, N bulan, N hari", TMT kosong → "-", TMT kemarin →
  "1 hari", normalisasi keterangan, isian TMT (`type=date`) & dua pilihan keterangan pada panel,
  simpan TMT/keterangan, nilai keterangan asing & tanggal 30 Feb ditolak, CSRF 419, kartu dapat
  diklik + `data-popup-guru` + tetap tautan, popup `?guru=ID` terbuka tanpa JS berisi 5 data yang
  diminta, `?guru=` asing aman, tabel `?tampil=tabel` memuat SEMUA guru dengan kolom & isi yang sama
  dengan popup, kerapian + integritas.
- `bash websk_uji_guru_popup_ui.sh` (+ `websk_uji_guru_popup_ui.js`, **60**) — Playwright: tombol
  Kartu/Tabel, **klik kartu membuka popup TANPA memuat ulang halaman** (`url` tetap, `:modal` true),
  isi popup (nama/jk/jabatan/telepon/masa kerja) dan **masa kerja dihitung ulang di peramban**,
  tutup lewat tombol & Esc, guru tanpa TMT → "-", tabel memuat semua guru dengan isi yang sama,
  isian TMT ber-`type=date` + dua pilihan keterangan **disimpan lewat antarmuka** dan langsung
  tercermin di popup publik, tanpa luapan 1440/390.
- ⚠️ Catatan uji: slug halaman jenis `guru` **berbeda antar basis data** (`tenaga-pendidik` pada
  instalasi baru, `data-guru-2` pada data pemilik) — skrip uji **mencari slug dari basis data**,
  jangan menuliskannya mati.


## v1.15.0 — KARTU PRESTASI SISWA PER KELAS (peringkat 1–3) — skema v32

Permintaan pemilik: *"Bagian yang saya tandai warna merah buat kartu prestasi siswa per kelas. Isinya
peringkat 1-3 di setiap kelas. Yang ditampilkan adalah peringkat 1-3 dikelas, filter setiap tahun
ajaran dan semesternya. Peringkat berisi Foto, Kelas, Nilai Rata-rata. Data di isi oleh super admin
atau editor (Yelfis Djamaludin)"*

Pilihan pemilik (jangan diubah tanpa bertanya): **(1) peringkat 1–3 per kelas**; **(2) filter tahun
ajaran & semester**; **(3) tiap peringkat memuat FOTO, KELAS, NILAI RATA-RATA** (ditambah nama siswa
agar jelas siapa orangnya); **(4) pengisian oleh Administrator ATAU akun Editor.**

### A. Skema v32 — tabel `prestasi_kelas`
- Satu baris = satu PERINGKAT pada satu kelas di satu periode:
  `ta`, `semester`, `kelas_id`, `kelas` (snapshot nama), `peringkat` (1–3), `siswa_id`, `nama`,
  `foto` (URL media proxy), `nilai` (REAL = rata-rata), `urutan`, `aktif`, `dibuat`, `diubah`.
- **`UNIQUE (ta, semester, kelas_id, peringkat)`** → peringkat 1, 2, 3 tidak bisa dobel per kelas.
- Hanya `CREATE TABLE`/`CREATE INDEX` (`idx_prestasi_kelas_periode`) + didaftarkan di
  `SCHEMA_TABEL_WAJIB` & `SCHEMA_KOLOM_WAJIB` — **tidak ada tabel lama yang dibangun ulang**.

### B. Modul `inc/prestasi_kelas.php` (fungsi `pk_*`)
`PK_PERINGKAT_MAKS` (3), `PK_NILAI_MAKS` (100), `PK_SEMESTER_PILIHAN`;
`pk_ta_norm()`/`pk_ta_sah()`/`pk_semester_norm()`/`pk_semester_sah()`, `pk_nilai_teks()` (92,50),
`pk_periode_list()`/`pk_ta_list()`/`pk_semester_list()`, **`pk_periode_bawaan()`** (periode berjalan
`periode.bawaan` bila berisi data → kalau tidak, periode terbaru), `pk_list()`, `pk_grup()`
(dikelompokkan per kelas), `pk_by_id()`, `pk_bentrok()` (cek peringkat dobel), `pk_foto()` (foto baris
→ foto siswa tertaut), `pk_inisial()`, `pk_kelas_list()`, dan **`pk_papan()`** (blok publik).

### C. Halaman publik (`halaman.php`)
- `require_once inc/prestasi_kelas.php`; blok dicetak pada cabang `<article class="artikel">` **TEPAT
  SETELAH pembuka artikel dan DI ATAS `menu.isi` (deskripsi) + tabel prestasi** — hanya untuk menu
  berjenis **`prestasi_siswa`** (`<?php if ($m['jenis'] === 'prestasi_siswa') { pk_papan(); } ?>`).
  Inilah area yang ditandai pemilik (kotak kosong di atas deskripsi).
- Blok `#prestasiKelas`: judul **"Kartu Prestasi Siswa per Kelas"**, chip periode, **filter
  Tahun Ajaran (`pk_ta`) & Semester (`pk_sem`)** dengan `data-auto` (otomatis menerapkan pilihan),
  lalu **satu kartu per kelas** (`.pk-kartu`) berisi peringkat 1–3 (`.pk-baris` + `.pk-medali`):
  foto (`.pk-foto`, foto asli atau inisial), nama, **"Kelas X · Rata-rata 92,50"**, dan angka nilai.
- Nilai asing pada filter **diabaikan** (kembali ke periode bawaan, tetap 200 — bukan halaman kosong).
- **Tanpa data sama sekali → blok tidak dicetak** (halaman tetap seperti semula). Periode yang ada
  tetapi belum berisi peringkat → keadaan kosong yang ramah.
- `hal_filter()` di `halaman.php` kini juga mempertahankan `pk_ta`/`pk_sem` (agar saringan tidak
  hilang saat paginasi/reset saringan prestasi).

### D. Panel `admin/prestasi_kelas.php` (Administrator **dan** Editor)
- Penjaga baru **`boleh_kelola_prestasi_kelas()` / `require_kelola_prestasi_kelas()`**
  (`inc/helpers.php`) = `is_admin() || is_editor()` — pola sama dengan Layanan PIP & Kohort. Peran
  `berita` (Editor Berita) tetap **403**. Menu sidebar **"Prestasi per Kelas"** (grup *Kesiswaan &
  Kepegawaian*, setelah Prestasi & Pelatihan; ikon `trophy`).
- Isian: Tahun Ajaran (wajib dua tahun berurutan; `2026-2027` dinormalkan), Semester, **Pilih dari
  Data Siswa** (nama & kelas otomatis mengikuti; anti salah ketik) atau nama manual + kelas,
  **Peringkat 1–3**, **Nilai Rata-rata** (koma diterima: `94,5` → 94.5), **Foto** (unggah → media
  proxy; bila kosong dipakai foto siswa), dan centang "tampilkan di website".
- Daftar: kolom Peringkat (pil berwarna), Foto, Nama, Kelas, Tahun Ajaran, Semester, Nilai Rata-rata,
  Status (Tampil/Disembunyikan), Aksi (Ubah / tampil-sembunyikan / Hapus) + saringan tahun ajaran &
  semester. Peringkat dobel ditolak dengan pesan jelas.
- Gaya: `.pk-blok`, `.pk-grid`, `.pk-kartu`, `.pk-medali` (emas/perak/perunggu), `.pk-foto`,
  `.pk-nilai`, `.pk-pil-peringkat`, `.pk-mini` pada `assets/style.css` (**jangan** `<style>`).

### E. Uji
- `bash websk_uji_prestasi_kelas.sh` (**97**) — skema v32 (14 kolom + indeks + UNIQUE), blok **tepat
  di atas deskripsi & tabel prestasi**, 3 kartu kelas × 3 peringkat, foto/inisial, kelas + nilai
  rata-rata berformat Indonesia, filter tahun ajaran & semester (termasuk nilai asing diabaikan),
  angka = basis data, hanya baris `aktif = 1` yang tampil, akses panel (Administrator 200, Editor
  **200**, Editor Berita **403**, tamu 302), simpan (autofill dari Data Siswa, koma nilai, normalisasi
  tahun ajaran), peringkat kembar ditolak, ubah/toggle/hapus, CSRF 419, tanpa data → blok tidak
  dicetak, kerapian + integritas.
- `bash websk_uji_prestasi_kelas_ui.sh` (+ `websk_uji_prestasi_kelas_ui.js`, **48**) — Playwright:
  blok di ATAS deskripsi (terukur), kartu & peringkat 1-2-3 + tinggi kotak foto, nilai berformat,
  filter Semester benar-benar menyaring (auto-submit) lalu kembali ke Ganjil, HP 390 satu kolom tanpa
  luapan, **Editor dapat menyimpan lewat antarmuka** (data langsung tampil di website) sementara
  **Editor Berita 403**, tombol sembunyikan menghilangkan kartu, tanpa galat konsol.
- ⚠️ **Catatan uji**: (a) instalasi BARU memakai slug menu **`prestasi`** (bukan `prestasi-siswa`) dan
  nama kelas **VII/VIII/IX** — skrip uji mencari slug & kelas dari basis data, jangan menuliskannya
  mati; (b) halaman `admin/login.php` **mengalihkan** pengguna yang masih masuk, jadi uji harus
  `logout.php` dulu sebelum berganti peran; (c) satu galat konsol 403 pada uji akses memang
  diharapkan (disaring).


## v1.14.3 — KOTAK "REKAPAN TAHUN AJARAN LAIN" DIPINDAH KE BAGIAN BAWAH (tanpa perubahan skema)

Permintaan pemilik: *"Yang saya tandai warna merah posisinya pindahkan ke yang saya tandai warna biru,
agar tulisan dibawah judul Analisis Kohort Siswa MA. STM terlihat rapi."*

- Kotak **`#kohortArsip`** tidak lagi berada **di samping keterangan** pada kepala kartu; kini berada
  **DI BAGIAN BAWAH halaman** — setelah kartu **`#kohortAnalisis`** (kartu analisis grafik) — dan
  **selebar kartu** di atasnya (`width:100%`, `margin-top:18px`).
- Keterangan di bawah judul ("Rekap kohort siswa 5 tahun ajaran terakhir …") kini memakai **lebar
  penuh kartu** (`.kohort-kepala-baris{display:block}`, `.kohort-ket{max-width:100%}`) sehingga
  tampil rapi tanpa kotak di sebelahnya. Kalimatnya diubah menjadi "… pada kotak **Rekapan Tahun
  Ajaran Lain** di bagian bawah halaman ini".
- Perilakunya TIDAK berubah: isi kotak tetap **hanya tombol tahun ajaran**, tabel rekapan + 3 grafik
  tetap muncul lewat `?arsip=<tahun>` (kartu `#kohortArsipDetail`).
- `?arsip=` yang dipilih tetap aman karena kartu rekapan berada di ATAS tabel utama, sedangkan kotak
  tombol di bawah — jadi pembaca melihat rekapan lalu dapat mengganti tahun pada kotak di bawahnya.
- Uji: `bash websk_uji_kohort.sh` (**165** — kotak berada **setelah** `#kohortAnalisis` dan kepala
  kartu tidak lagi memuatnya) dan `bash websk_uji_kohort_ui.sh` (**103** — terukur: kotak di bawah
  kartu analisis, **tidak** di samping keterangan, lebarnya = lebar kartu (±4 px), keterangan selebar
  kartu, tetap tanpa tabel di dalam kotak, tombol membuka rekapan, tanpa luapan 1440/390).


## v1.14.2 — KOTAK REKAPAN KOHORT: TOMBOL TAHUN AJARAN → TABEL + GRAFIK (tanpa perubahan skema)

Permintaan pemilik: *"Yang saya tandai warna merah jangan langsung ditampilkan langsung dalam bentuk
tabel. Tapi hanya tombol tahun ajaran. dan jika diklik akan muncul tabel rekapan + grafiknya."*

- Isi kotak **`#kohortArsip`** (kepala kartu kohort) kini **HANYA tombol/chip tahun ajaran**
  (`.kohort-arsip-tombol`, satu per tahun arsip) — **tidak ada tabel apa pun di dalam kotak**
  (ada ujinya). Kotak juga menyebut "... tahun ajaran tersimpan di luar tabel".
- Tombol = **tautan** `?arsip=<tahun>#kohortArsipDetail` (bekerja **tanpa JavaScript** dan tanpa
  memuat ulang aset lain). Tombol tahun yang sedang dibuka diberi kelas `.on` + `aria-current="true"`.
- Saat tombol diklik, server merender kartu **`#kohortArsipDetail`** **DI ATAS tabel utama**:
  5 kartu ringkas (Pendaftar, Diterima, Jumlah Siswa, Lulus, Drop Out) + **dua tabel rekapan**
  (`#tblArsipPmb` = PMB & Kelulusan, `#tblArsipTingkat` = per tingkat X/XI/XII) + **tiga grafik**:
  `chArsipAlur` (batang alur siswa), `chArsipHasil` (batang mutasi/naik/tinggal/drop out), dan
  `chArsipLulus` (**donat** tujuan setelah lulus). Ada tombol **Tutup Rekapan** (`#tutupArsipDetail`)
  yang kembali ke halaman tanpa `?arsip=`.
- **`?arsip=` yang tidak dikenal DIABAIKAN** — halaman tetap 200 tanpa kartu rekapan (tidak pernah
  403 atau tabel kosong).
- Data grafik dikirim sebagai `A` pada blok skrip yang sama (hanya bila ada tahun terpilih); fungsi
  kecil `batangTunggal()` menggambar dua grafik batang, donat digambar langsung.
- Gaya: `.kohort-arsip-tombol-grup`, `.kohort-arsip-tombol(.on)`, `.kohort-arsip-jumlah`,
  `.kohort-arsip-detail*`, `.kohort-ad-*`, `table.tabel-ad` di `assets/style.css`
  (aturan `.kohort-arsip-gulir`/`table.tabel-arsip` lama dibiarkan, tidak dipakai lagi).
- Uji: `bash websk_uji_kohort.sh` (**164** — isi kotak hanya tombol, tidak ada `<table>` di dalam
  aside, tombol membawa `?arsip=` + jangkar, tanpa `?arsip=` kartu rekapan & grafik TIDAK ada,
  `?arsip=2019/2020` memunculkan dua tabel + tiga grafik dengan angka = basis data, tombol aktif
  tersorot, `?arsip=` ngawur diabaikan, tahun yang tergeser menjadi **tombol** baru, dan kotak tidak
  dicetak saat hanya 5 tahun) dan `bash websk_uji_kohort_ui.sh` (**103** — Playwright: klik tombol
  benar-benar membuka rekapan, grafik tergambar (`Chart.getChart`), label/deret grafik = angka tahun
  yang diklik, tombol Tutup menyembunyikan lagi, tombol tahun tetap tersedia, tanpa luapan 1440/390).
- ⚠️ **Catatan uji**: data uji UI hanya memuat 6 tahun ajaran → arsip = **1 tombol**; sesudah tahun
  baru ditambahkan menjadi **2 tombol** (dipakai untuk menguji tahun yang baru tergeser). Jangan
  menuliskan angka chip dari data uji CLI (7 tahun) di skrip UI.


## v1.14.1 — KOTAK "REKAPAN TAHUN AJARAN LAIN" PADA KARTU KOHORT (tanpa perubahan skema)

Permintaan pemilik: *"Tahun Ajaran yang sudah tidak masuk di dalam tabel letakkan di tanda merah,
agar pembaca bisa melihat rekapan pada tahun ajaran yang tidak ditampilkan di tabel. Sehingga dari
awal semua datanya ada dan bisa dilihat."*

- Fungsi baru **`kohort_arsip()`** (`inc/kohort.php`) = seluruh baris kohort yang **tidak** termasuk
  `kohort_tampil()` (di luar `KOHORT_TAHUN_TAMPIL` terbaru), urut tahun menaik.
- Dicetak sebagai kotak **`#kohortArsip`** — judul **"Rekapan Tahun Ajaran Lain"** + keterangan
  "Tidak ditampilkan di tabel — tetap tersimpan." + tabel **`#tblKohortArsip`** berkolom
  **Tahun · Pendaftar · Diterima · Siswa (X+XI+XII) · Lulus · Drop Out** (angka dari baris yang sama
  dengan tabel utama, hanya kolom ringkasannya).
- **Posisi** (sesuai area yang ditandai merah pemilik): pada kepala kartu, **di SAMPING KANAN**
  keterangan dan **DI ATAS tabel** — dibungkus `.kohort-kepala-baris` (flex; keterangan
  `flex:1 1 420px`, kotak `flex:0 1 380px`). Di HP (< ≈820px) kotak **turun ke bawah** keterangan.
- Kotak **hanya dicetak bila memang ada** tahun di luar tabel; kalau seluruh tahun sudah tampil
  (≤ 5 baris), kotaknya tidak muncul sama sekali (ada ujinya). Kalimat kaki tabel ikut menyebut
  kotak rekapan bila ada.
- Gaya: `.kohort-arsip`, `.kohort-arsip-ket`, `.kohort-arsip-gulir`, `table.tabel-arsip` di
  `assets/style.css` (bisa digeser mendatar di HP) — **jangan** `<style>` di halaman.
- Uji: `bash websk_uji_kohort.sh` (**142** — kotak & judulnya, `kohort_arsip()` = 2 tahun tertua,
  angka rekapan = basis data, posisi di atas tabel, tahun yang tergeser **pindah** ke kotak rekapan
  setelah tahun baru diisi, dan kotak **tidak dicetak** saat hanya 5 tahun) dan
  `bash websk_uji_kohort_ui.sh` (**85** — posisi kanan keterangan & sejajar di 1440px, turun di
  390px, lebar wajar, isi tetap terbaca, tanpa luapan).
- ⚠️ **Jebakan uji**: pemeriksaan "tahun lama TIDAK tampil" pada uji lama dulu memeriksa SELURUH
  halaman, sehingga gagal setelah tahun lama justru muncul di kotak rekapan (perilaku yang BENAR).
  Sekarang uji memeriksa **badan tabel** saja (penolong `tahun_tabel()`), bukan seluruh HTML.


## v1.14.0 — KOHORT: KOLOM KELULUSAN + AKSES AKUN EDITOR + KAKI HALAMAN TANPA "MENU" (skema v31)

Tiga permintaan pemilik sekaligus:
1. *"Akun Editor (Yelfis Djamaludin) berikan akses untuk menginput Kohort Siswa"*
2. *"Tabel Kohort pada tampilan ditambahkan Kelulusan, seperti yang di upload. Untuk grafiknya
   disesuaikan Kembali karena tambah kolomnya."*
3. *"Menu yang dibagian footer yang saya tandai warna biru dihapus saja"*

### A. Akses akun Editor (`boleh_kelola_kohort()`)
- Penjaga `admin/kohort.php` diganti dari `require_admin()` menjadi
  **`require_kelola_kohort()`** = **Administrator ATAU peran `editor`** (pola sama dengan Layanan
  PIP; keduanya di `inc/helpers.php`). Akun `yelfis` (peran `editor`) kini bisa mengisi kohort.
- **Peran `berita` (Editor Berita) tetap 403** — `berita_berkas_boleh()` tidak diubah.
- Menu panel "Kohort Siswa" sudah tampil untuk peran editor (sidebar hanya dibatasi untuk
  `berita`), jadi tidak ada perubahan menu.

### B. Grup kolom "Lulus" (skema v31)
- Empat kolom angka baru pada tabel `kohort`: **`lulus_jumlah`, `lulus_kuliah`, `lulus_bekerja`,
  `lulus_lainnya`** — ditambahkan lewat `db_add_column` **dan** ikut ditulis pada `CREATE TABLE`
  + didaftarkan di `SCHEMA_KOLOM_WAJIB`. Hanya MENAMBAH kolom: baris lama bernilai 0 (tidak ada
  angka yang ditebak), tidak ada tabel yang dibangun ulang.
- Konstanta **`KOHORT_KOLOM_LULUS`** (di `inc/kohort.php`) menggerakkan seluruh tampilan: kepala
  tabel (grup **Lulus**, biru), isian panel, dan grafik. Menambah/mengubah kolom kelulusan cukup
  di situ — **jangan** menulis daftar kolom di halaman.
- Warna kepala kolom mengikuti contoh pemilik: **Lulus biru (#3ea9d6)**, Jumlah Lulus hijau muda,
  Kuliah ungu muda, Bekerja oranye muda, Lainnya kuning. `min-width` tabel naik ke **1330px**
  (25 kolom) dan tetap bisa digeser mendatar di HP.

### C. Grafik & analisis disesuaikan
- **Grafik kelima `chKohortLulus`** (baris penuh, `.kohort-grafik-lebar`): Jumlah Lulus, Kuliah,
  Bekerja, Lainnya — memakai angka `D.lulus.*` yang dibangun dari kolom baru.
- Kartu ringkasan menjadi **lima** (`Total Pendaftar`, `Total Diterima`, `Rata-rata Siswa/Tahun`,
  **`Total Lulus`**, `Total Drop Out`); gridnya `repeat(auto-fit,minmax(196px,1fr))` supaya lima
  kartu tetap **sebaris** di desktop dan menumpuk di HP.
- `kohort_analisis()` menambah kunci **`lulus`, `lulus_kuliah`, `lulus_bekerja`, `lulus_lainnya`,
  `lulus_rata`, `persen_kuliah`, `persen_bekerja`, `persen_lainnya`** dan kalimat analisis menyebut
  jumlah lulus + persentase tujuan setelah lulus.
- Panel admin: tabel daftar menambah 4 kolom (Lulus/Kuliah/Bekerja/Lainnya, kini 13 kolom) dan
  formulir menambah kelompok isian **"Lulus"** (total 24 isian angka).

### D. Kaki halaman tanpa kolom "Menu"
- `situs_bawah()` (`inc/layout.php`) **tidak lagi mencetak** kolom "Menu" (daftar 6 menu utama) —
  permintaan pemilik (ditandai biru pada tangkapan layar). Kaki halaman kini **tiga kolom**:
  identitas · **Kontak Kami** · **Ikuti Kami**; `.kaki-dalam` menjadi
  `grid-template-columns:1.5fr 1.2fr 1fr`.
- Variabel `$menuKaki` dihapus; aturan CSS `.kaki-menu*` **sengaja dibiarkan** (tidak dipakai) agar
  mudah dikembalikan bila pemilik berubah pikiran. Jangan mencetak kolomnya kembali tanpa permintaan.

### E. Uji
- `bash websk_uji_kohort.sh` (**124**) — 28 kolom tabel kohort, grup Lulus (4 kepala kolom berwarna,
  4 isian panel, 24 isian angka), grafik kelulusan + lima kartu ringkasan, angka Total Lulus dari
  basis data, **akun Editor diizinkan (200) dan benar-benar menyimpan** (termasuk kolom Lulus),
  **akun Editor Berita tetap 403**, serta seluruh uji lama (pergeseran 5 tahun, validasi, CSRF).
- `bash websk_uji_kohort_ui.sh` (+ `websk_uji_kohort_ui.js`, **74**) — Playwright: 24 sel angka per
  baris & warna kepala grup Lulus, **5 grafik benar-benar tergambar** dengan deret kelulusan = angka
  tabel, 5 kartu ringkasan, **kaki halaman tiga kolom tanpa "Menu"**, akun Editor masuk panel &
  menyimpan tahun ajaran baru, tanpa luapan 1440/390.
- ⚠️ **Jebakan uji yang menggigit**: variabel shell **`LULUS` sudah dipakai sebagai penghitung uji
  yang lulus** (`LULUS=$((LULUS+1))`) — menamai variabel SQL `LULUS` menimpanya sehingga skrip
  melaporkan "420 lulus" palsu. Pakai nama lain (`JML_LULUS`).
- `bash websk_uji_bos_ui.js` diperbaiki kecil: pemeriksaan "pindah tahun anggaran" masih menuntut
  `slug=dana-bos` padahal sejak v1.8.0 alamatnya rapi (`/halaman/dana-bos?ta=1`) — kini cukup
  memeriksa `dana-bos` pada URL (gagal palsu, bukan regresi).


## v1.13.0 — MENU "SISWA → KOHORT SISWA": TABEL ANALISIS KOHORT + GRAFIKNYA (skema v29/v30)

Permintaan pemilik: *"Pada bagian Kohort Siswa isi dengan tabel seperti pada contoh, dibawah tabel
tambahkan analisis grafiknya berdasarkan tabel tersebut (yang di tampilkan adalah 5 tahun terakhir.
Ketika di input tahun ajaran baru otomatis tahun ajaran paling lama akan hilang dan posisi bergeser.
tabel di isi oleh super admin di halaman super admin."*

Pilihan pemilik (jangan diubah tanpa bertanya): **(1) kolom tabel PERSIS seperti contoh** —
Tahun Ajaran · PMB (Pendaftar, Diterima) · Kelas X/XI/XII (Jumlah, Mutasi Masuk, Mutasi Keluar,
Tinggal Kelas, Naik Kelas, Drop Out); **(2) halaman publik hanya menampilkan 5 tahun ajaran
TERBARU**, tahun paling lama otomatis keluar dari tabel saat tahun baru diisi (posisi bergeser);
**(3) isian di panel Super Admin** (`require_admin()`).

### A. Skema v29 (tabel) + v30 (penyelesaian jenis menu) — tabel `kohort`
- Satu baris = satu tahun ajaran: `ta` (`UNIQUE`), **20 kolom angka** (`pmb_pendaftar`,
  `pmb_diterima`, `x_jumlah` … `xii_dropout`), `dibuat`, `diubah` + indeks `idx_kohort_ta`.
  Hanya `CREATE TABLE`/`CREATE INDEX` — **tidak ada tabel lama yang dibangun ulang**.
- Tabel didaftarkan pada **`SCHEMA_TABEL_WAJIB`** dan seluruh kolomnya pada **`SCHEMA_KOLOM_WAJIB`**
  (pola "penanda skema bisa lebih baru daripada isi basis data" tetap berlaku).
- ⚠️ **Skema v30** hanya mengulang seluruh migrasi (idempoten) supaya `db_menu_kohort()` benar-benar
  diterapkan: pada basis data LIVE penanda `29` sempat tertulis lebih dulu (permintaan pengguna
  menjalankan migrasi di tengah penyuntingan `inc/db.php`) sehingga baris menu Kohort Siswa masih
  berjenis `halaman` dan halaman publiknya kosong. **Pelajaran: jangan menaikkan `SCHEMA_VERSION`
  sebelum seluruh perubahan `inc/db.php` selesai ditulis** — aplikasi ini live, jadi migrasi bisa
  berjalan kapan saja.
- **Baris lama TIDAK dihapus** saat tahun ajaran baru masuk — hanya tidak ditampilkan (arsip),
  sehingga angka historis tetap tersedia bila tahun yang lebih baru dihapus lagi.

### B. Jenis menu + halaman publik (`inc/kohort.php`)
- Migrasi **`db_menu_kohort()`** (idempoten): membuat sub menu **Kohort Siswa** (`slug`
  `kohort-siswa`, jenis `kohort`) bila belum ada — diletakkan pada induk yang memuat halaman Data
  Siswa (jenis `siswa`), cadangan "Akademik"/"Kesiswaan"/"Informasi", terakhir sebagai menu utama;
  pembuatannya dijaga penanda `kohort_menu_v1`. Setelah itu baris menu yang MASIH berjenis `halaman`
  diubah menjadi `kohort` (slug `kohort-siswa`/`kohort`/`kohort_siswa` dikenali).
- `halaman.php` menambah cabang **`jenis === 'kohort'` → `kohort_papan()`**.
- **`kohort_tampil()`** = `KOHORT_TAHUN_TAMPIL` (= **5**, di `inc/config.php`) tahun ajaran terbaru,
  urut menaik; `kohort_semua()` = seluruh baris tersimpan (untuk panel admin).
- Tabel memakai kepala dua baris **berwarna seperti contoh pemilik**: Tahun Ajaran hijau, PMB oranye,
  Kelas X/XI/XII merah muda, dan sub-kolom Jumlah/Masuk/Keluar/Tinggal/Naik/Drop Out berwarna
  masing-masing. Tabel dibungkus `.tabel-wrap` (geser mendatar) + kolom Tahun Ajaran **sticky**.
- Di bawah tabel dicetak **analisis grafik** (`#kohortAnalisis`): 4 kotak ringkasan
  (Total Pendaftar, Total Diterima + persen, Rata-rata Siswa/Tahun, Total Drop Out) + **4 grafik
  Chart.js** (`chKohortPmb`, `chKohortJumlah`, `chKohortHasil`, `chKohortMutasi`) + kalimat analisis
  yang angkanya dihitung dari baris yang SEDANG ditampilkan (bukan seluruh arsip).
- Angka tabel adalah sumber grafik — jadi bila skrip gagal dimuat, semua angka tetap terbaca.
- Gaya `.kohort-*`/`table.tabel-kohort` di `assets/style.css` (**jangan** `<style>` di halaman).

### C. Panel Super Admin (`admin/kohort.php`)
- Menu sidebar **"Kohort Siswa"** (grup *Kesiswaan & Kepegawaian*, setelah Data Siswa).
  Penjaga **`require_admin()`** — akun peran `editor`/`berita` **403** (ada ujinya).
- Isian: Tahun Ajaran + 2 angka PMB + 6 angka tiap tingkat (X, XI, XII); dikelompok dengan judul
  PMB/Kelas X/Kelas XI/Kelas XII. Validasi: tahun ajaran wajib **dua tahun berurutan**
  (`2025/2026`; tulisan `2025-2026`/`2025 2026` dinormalkan, `2025` menjadi `2025/2026`), tidak boleh
  kembar, angka dijepit `0…KOHORT_NILAI_MAKS` (100.000).
- Daftar menampilkan SELURUH tahun tersimpan dengan pil **"Tampil di website"** / **"Arsip (tidak
  tampil)"** + tombol Ubah & Hapus. Pesan simpan menyebutkan tahun mana yang kini menjadi arsip.
- `admin/menu.php` mengenali jenis **`kohort`** dan `jenis_menu_label()` menjelaskannya.

### D. Uji
- `bash websk_uji_kohort.sh` (**94**) — skema v29 + menu + `kohort_tampil()` (5 tahun terbaru saja),
  tabel & seluruh kolom contoh, 4 grafik + Chart.js lokal, angka ringkasan = basis data,
  panel Super Admin (20 isian, tambah/ubah/hapus, tahun kembar & format tahun, CSRF 419),
  akses **hanya Administrator** (Editor 403), pergeseran tahun tanpa menghapus data, kerapian.
- `bash websk_uji_kohort_ui.sh` (+ `websk_uji_kohort_ui.js`, **54**) — Playwright: warna kepala kolom,
  grafik benar-benar tergambar & labelnya 5 tahun terakhir, kartu ringkasan, tanpa luapan 1440/390,
  tabel bisa digeser di HP, menambah tahun lewat panel → tabel & grafik bergeser dan tahun paling
  lama hilang.
- ⚠️ Catatan uji: pesan flash panel juga tercetak pada halaman publik bila diuji memakai sesi admin
  yang sama (perilaku lama `situs_atas()`), sehingga uji membaca halaman publik **dua kali** —
  permintaan pertama menghabiskan flash, pemeriksaan dilakukan pada permintaan berikutnya.


## v1.12.0 — EFEK KARTU DATA GURU + SLIDER THUMBNAIL VIDEO BERANDA (tanpa perubahan skema)

Permintaan pemilik: *"Tambahkan efek pada halaman data guru. Dan tambahkan slider thumbail video
pada yang saya beri tanda merah"*.

* **EFEK pada kartu halaman Data Guru** (menu jenis `guru`, `.guru-grid` → `.guru-kartu` pada
  `halaman.php`), seluruhnya di `assets/style.css` (**jangan** menaruh `<style>` di halaman):
  * kartu terangkat (`translateY(-6px)`) + bayangan lebih dalam + tepi keemasan saat disorot;
  * **garis aksen emas** di atas kartu (`::after`, `scaleX(0)` → `1`);
  * **foto membesar** sedikit dengan **cincin emas** (`box-shadow` 4px) & terangkat;
  * nama guru berubah hijau tua;
  * terasa **"ditekan"** saat diklik (`:active`).
  🚨 **Aturan `:active` WAJIB ditulis SETELAH blok `@media (hover:hover)`** (pola yang sudah dipakai
  `.kartu-program`/`.medsos-baris` di aplikasi ini); menaruhnya sebelum blok itu membuat efek sorot
  kalah pada perangkat berpenunjuk.
  **Perangkat sentuh**: `:hover` tidak pernah aktif, jadi `assets/app.js` menambahkan kelas
  **`.tersentuh`** sebentar saat kartu disentuh (`ontouchstart`) — skrip ini **hanya pelengkap**
  (tanpa JavaScript kartu tetap tampil normal) dan **tidak dijalankan** saat
  `prefers-reduced-motion: reduce`. Blok `@media (prefers-reduced-motion:reduce)` mematikan seluruh
  transisi & angkatan.
* **SLIDER THUMBNAIL VIDEO** pada bagian **"Galeri & Media Sosial"** beranda (`index.php`), di
  **baris yang ditandai pemilik** — yaitu **di antara slider album** (yang tombol panahnya sudah ada
  di situ) **dan deretan tombol media sosial**:
  * `$videoBeranda = video_publik(12)` (tabel `galeri_video` yang sama dengan bagian "Video
    Kegiatan" halaman galeri) → hanya video yang `youtube_id()`-nya sah; tanpa video, blok **tidak
    dicetak sama sekali**;
  * markup: `.video-beranda[data-gulir=".gulir-video"][data-gulir-auto="7000"]` → `.gulir-lacak.gulir-video`
    berisi `a.video-thumb` (thumbnail `https://i.ytimg.com/vi/<id>/hqdefault.jpg` + tombol main +
    judul + label YouTube) + **`.gulir-titik-nav` sendiri** (panah ‹ › terpisah dari panah album —
    mesin slider `[data-gulir]` di `assets/app.js` sudah mengurus geser/geser-otomatis/penyembunyian
    panah bila isinya tidak melebihi wadah);
  * **klik thumbnail → POPUP pemutar** `dialog#dialogVideoBeranda` (pola `dialog.dialog-pesan`):
    `src` iframe diisi SAAT DIKLIK (`youtube.com/embed/<id>?autoplay=1&rel=0`) lalu **dikosongkan
    saat ditutup/Esc/klik latar** supaya video berhenti — jadi beranda **tidak memuat video apa pun**
    lebih dulu. Tanpa JavaScript setiap thumbnail adalah tautan biasa ke `galeri#videoGaleri`
    (bagian "Video Kegiatan"), jadi tidak pernah jalan buntu.
  * ⚠️ Thumbnail YouTube adalah **gambar pihak ketiga** (di halaman galeri aplikasi ini juga sudah
    menanam iframe YouTube, jadi tidak menambah jenis ketergantungan baru). Uji UI menyaring
    `youtube.com`/`ytimg.com`/`gstatic.com` dari daftar "permintaan gagal" karena itu aset pemutar,
    bukan berkas aplikasi.
  * Gaya `.video-beranda*`, `.video-thumb*`, `dialog.dialog-video` ada di `assets/style.css`;
    `@media print` menyembunyikan slider & dialog.

### Uji
`bash websk_uji_efek.sh` (+ `websk_uji_efek_ui.js`, **46**).


## v1.11.0 — TAHUN AJARAN & SEMESTER: FILTER PUBLIK + MENU BARU SUPER ADMIN (skema v28)

Permintaan pemilik: *"Yang saya tandai warna merah di hapus. Kemudian diganti dengan filter Tahun
Ajaran dan Semester. Misal Tahun Ajaran 2026/2027 Semester Ganjil, karena perangkat pembelajaran itu
per semester. Untuk inputan dihalaman super adminnya di sesuaikan saja"* — dengan pilihan pemilik:
**(1) daftar periode diatur lewat MENU BARU Super Admin**, dan **(2) hanya BERKASNYA yang dipisah per
semester** (daftar guru/kelas/mata pelajaran tetap satu).

* **Tabel baru `periode`** (skema v28): satu baris = satu semester pada satu tahun ajaran
  (`ta`, `semester`, `urutan`, `aktif`, **`bawaan`** = periode yang sedang berjalan), `UNIQUE (ta, semester)`
  + indeks; didaftarkan di `SCHEMA_TABEL_WAJIB`/`SCHEMA_KOLOM_WAJIB`.
  Isi awal dibuat **sekali** (`db_periode_awal()`) dari **tahun ajaran sekolah** (`sekolah.tahun_ajaran`),
  mis. "2026/2027" → semester Ganjil (ditandai `bawaan`) & Genap; tahun ajaran yang tidak berbentuk dua
  tahun berurutan jatuh ke tahun berjalan.
* **Kolom baru `perangkat_kelas.ta` & `perangkat_kelas.semester`** (lewat `db_add_column` **dan** ikut
  ditulis pada `CREATE TABLE` + `SCHEMA_KOLOM_WAJIB`). Tabel `perangkat_kelas` **dibangun ulang** oleh
  `db_perangkat_semester()` HANYA bila kunci uniknya masih `(perangkat_id, kelas)` → menjadi
  **`(perangkat_id, kelas, ta, semester)`**; baris lama disalin apa adanya, lalu baris ber-`ta`/`semester`
  kosong diberi **periode berjalan** supaya berkas yang sudah diunggah tidak hilang.
  🚨 **JEBAKAN yang sudah menggigit**: indeks `idx_perangkat_kelas_periode` SEMPAT diletakkan pada daftar
  indeks `db_tabel()` — padahal daftar itu dijalankan SEBELUM kolom `ta` ditambahkan, sehingga basis data
  lama gagal dengan **"no such column: ta"** dan SELURUH migrasi ROLLBACK (basis data tetap v27 tanpa
  tabel `periode`). Indeks itu sekarang dibuat di dalam `db_perangkat_semester()` (setelah kolomnya ada).
* **HALAMAN PUBLIK**: bagian yang ditandai pemilik **DIHAPUS** (kalimat "Satu mata pelajaran dapat
  diajarkan pada beberapa kelas …" + deretan **chip ringkasan**) dan **diganti filter
  "Tahun Ajaran" + "Semester"** (`#filterPerangkat`, formulir GET `?ta=…&sem=…`) dengan keterangan
  periode yang sedang dilihat. Pilihan bawaan = periode `bawaan`; **nilai periode asing diabaikan**
  (kembali ke periode berjalan, bukan 404). Bila sekolah **belum punya periode** sama sekali, penyaring
  dilewati (semua berkas tetap tampil) + keterangan "periode belum diatur".
  Tautan unduh membawa periode (`ta` + `sm`) dan tombolnya membawa label periode (`data-pg-periode`)
  yang ikut dicetak pada popup kode.
* **MENU BARU `admin/periode.php` ("Tahun Ajaran & Semester", khusus Administrator)**: daftar periode
  (urut ▲▼, Aktif/Nonaktif, penanda **Periode Berjalan**, jumlah baris berkasnya), formulir **Tambah
  Tahun Ajaran** (satu tahun ajaran + beberapa semester sekaligus: centang Ganjil/Genap + isian
  «semester lain»), formulir **satu periode** (tambah/ubah), tombol **jadikan periode berjalan**,
  dan **hapus** (POST + CSRF + konfirmasi, menghapus baris berkas periode itu).
  Penulisan tahun ajaran dinormalkan (`2025-2026` → `2025/2026`) dan **hanya dua tahun BERURUTAN yang
  diterima** (`perangkat_ta_sah()`); semester divalidasi dari daftar `PERANGKAT_SEMESTER` atau ditulis
  sendiri (mis. "Antara"). Toggle & urutan memakai GET seperti halaman lain di aplikasi ini.
* **PANEL PERANGKAT GURU**: **pemilih periode** di atas tabel (`#periodeKerja` → `?ta=…&sem=…`).
  Seluruh unggah/penghapusan berkas berlaku pada periode itu (`perangkat_kelas_id(..., $ta, $sem)`),
  pengalihan membawa periode (`perangkat_periode_query()`), dan tiap blok berkas menandai semesternya.
  Bila ada uji/skrip lama yang TIDAK mengirim `ta`/`sem`, berkas masuk ke **periode berjalan**
  (kompatibel).
* **`perangkat_unduh.php`**: periode dipilih lewat **`perangkat_periode_pilih()`** yang sama dengan
  halaman publik. 🚨 **JEBAKAN**: `perangkat_periode_pilih()` awalnya hanya membaca `$_GET['sem']`,
  sedangkan tautan unduh memakai parameter ringkas **`sm`** — akibatnya unduhan selalu jatuh ke periode
  berjalan dan berkas semester lain tidak pernah ditemukan. Sekarang **keduanya dibaca** (`sem` lalu
  `sm`); parameter `sm` dipertahankan pada tautan & pengalihan.
  🚨 **JEBAKAN KEDUA**: urutan argumen `perangkat_unduh_tampil()` pernah tertukar (parameternya kini
  dinamai tegas **`$jenisAlert`/`$pesanInfo`**) sehingga halaman unduh mencetak **"Kelas cp"** alih-alih
  nama kelas — kelas & periode kini benar.
* Gaya `.pg-filter*`, `.pg-periode*`, `.pg-periode-tanda` di `assets/style.css` (**jangan** menaruh
  `<style>` di halaman). Konstanta `PERANGKAT_SEMESTER`, `PERANGKAT_TA_MAKS`, `PERANGKAT_SEMESTER_MAKS`
  di `inc/config.php`.

### Uji
`bash websk_uji_periode.sh` (**68**, termasuk simulasi keadaan v27 lewat `websk_periode_v27.php` +
penolong `websk_periode_penolong.php`) + `bash websk_uji_perangkat.sh` (**132**) +
`bash websk_uji_perangkat_ui.sh` (**84**) + `bash websk_cek_live.sh` (**194**, periode & berkas per
semester pada salinan data asli).


## v1.10.0 — BERKAS PERANGKAT GURU PER KELAS + KODE DOWNLOAD GENERATE SEKALIGUS (skema v27)

Permintaan pemilik: *"Pada bagian upload perangkat tambahkan fitur file yang akan di upload masuk
dimana. Misal Upload Modul ajar Fikih untuk kelas 10, 11 kelas 12. Karena fitur yang ada sekarang satu
file upload untuk semua kelas setiap mata pelajaran. Kemudian pada Kode Download per Mata Pelajaran
tambahkan fitur generate sekaligus tapi hasil generate berbeda. Dan mata pelajarannya menyesuaikan
dengan yang diinput oleh super admin."*

* **Skema v27 — tabel `perangkat_kelas`** (hanya MENAMBAH TABEL; tabel lama tidak disentuh):
  satu baris per **(perangkat × kelas)** menyimpan `cp/cp_nama`, `atp/atp_nama`, `rpp/rpp_nama` +
  `guru_id`/`kunci` (denormalisasi agar tabel publik cukup satu kueri), `UNIQUE (perangkat_id, kelas)`,
  `ON DELETE CASCADE` dari `perangkat`; di-`SCHEMA_TABEL_WAJIB` + `SCHEMA_KOLOM_WAJIB` + 2 indeks.
  Kelas kosong (`''`) = mata pelajaran yang belum diberi kelas.
* **Migrasi v27 `db_perangkat_pindah_kelas()`** (dijalankan di `ensure_schema()`): berkas lama pada
  kolom `perangkat.cp/atp/rpp` **DISALIN** ke baris per kelas (satu baris per kelas mata pelajaran itu,
  atau satu baris kelas kosong bila belum ada kelas) sehingga **berkas milik pemilik tidak hilang**;
  kolom lama **dibiarkan sebagai arsip**. Idempoten (hanya untuk baris yang belum punya baris per kelas)
  dan sekaligus membersihkan baris kelas kosong yang menganggur.
* **Halaman publik**: tiap baris (kelas × mapel) memakai **berkas kelasnya sendiri**; tautan unduh
  membawa kelas (`k=<kunci kelas>`) dan keterangan popup menyebut kelasnya.
* **`perangkat_unduh.php`**: `k` (kunci kelas) memilih berkas kelas itu; tanpa `k` (tautan lama) dipakai
  kelas terdaftar pertama yang berberkas, dan berkas tetap dapat diunduh walau centang kelasnya kelak
  dikosongkan (kandidat diambil dari SELURUH baris `perangkat_kelas` milik mapel itu). Nama unduhan
  menyertakan kelas: `CP - <Guru> - <Mapel> - <Kelas>.pdf`.
* **Panel Super Admin**: tiap sel berkas kini berisi **satu blok per kelas** (ikon centang/silang, nama
  berkas, centang hapus, isian berkas) dengan penanda nama kelas. Nama medan **berkelompok**:
  `berkas[<jenis>][<kelasKunci>]`, `hapus[<jenis>][<kelasKunci>]`, dan `nama_kelas[<kelasKunci>]`
  (kelas kosong memakai penanda **`tanpakelas`** — bukan string kosong, karena PHP mengubah
  `berkas[cp][]` menjadi indeks angka sehingga berkasnya tidak akan pernah cocok). Isian berkas LAMA
  (`cp`/`atp`/`rpp` tanpa kurung) masih diterima dan diterapkan pada kelas pertama (kompatibilitas).
  Unggahan berkelompok dibaca lewat penolong baru **`unggah_berkas_entri()`** (`inc/helpers.php`;
  `unggah_berkas()` kini memakainya) dan **`unggah_berkas_kelas()`** (`admin/perangkat.php`).
* **Kode Download per mata pelajaran**:
  * daftar mata pelajaran **mengikuti yang diinput Super Admin** pada tabel perangkat
    (`perangkat_mapel_semua()` tidak lagi mengambil dari `mapel` Data Guru). Baris kode lama untuk
    mapel yang sudah tidak ada tetap ditampilkan dengan penanda "tidak ada lagi pada tabel perangkat"
    (agar kode yang mungkin masih dipakai pengunjung tidak hilang) tetapi tidak ikut di-generate ulang;
  * **Generate Sekaligus** (`aksi=generate_sekaligus`): satu klik membuat kode **BARU untuk semua mata
    pelajaran** dan **semuanya BERBEDA** (`perangkat_kode_unik()` menghindari kode lain, lalu
    `perangkat_kode_generate_sekaligus()`); tombol keduanya **"Isi yang belum ada kode"**
    (`hanya_kosong=1`) yang hanya mengisi yang masih kosong — kode yang sudah dibagikan tidak berubah.
    Tombol "semua" memakai `data-confirm` karena kode lama tidak berlaku lagi.
* Gaya `.pg-kelas-tanda`, `.pg-kode-aksi`, `.pg-baris-luar` ditambahkan di `assets/style.css`
  (**jangan** menaruh `<style>` di halaman).

### Uji
`bash websk_uji_perangkat.sh` (**128**) + `bash websk_uji_perangkat_ui.sh` (**82**) +
`bash websk_cek_live.sh` (**173**, termasuk pemeriksaan migrasi v27 pada salinan data asli:
berkas lama tersalin ke baris per kelas, halaman publik tetap menampilkan centang hijau, migrasi
idempoten) + penolong **`websk_cek_pg_kelas.php`** (memeriksa baris `perangkat_kelas` pada salinan).


## v1.9.0 — KELAS pada PERANGKAT GURU (skema v26)

Permintaan pemilik: *"Halaman tampilan perangkat guru tambahkan kelas, karena dalam satu mata
pelajaran bisa saja ada di tiga kelas. Kemudian tampilan tabelnya dibuat rapi seperti yang diupload.
Kemudian pada bagian isian di super admin disesuaikan lagi, setiap mata pelajaran kelasnya bisa
diatur. Dan tampilan di super admin dibuat tabel juga agar mudah mengisinya"*.

* **Skema v26 — kolom `perangkat.kelas`** (hanya MENAMBAH kolom lewat `db_add_column` **dan** ikut
  ditulis pada `CREATE TABLE` + didaftarkan di **`SCHEMA_KOLOM_WAJIB`**). Isinya **daftar kelas**
  sebuah mata pelajaran, dipisah koma (mis. `Kelas X, Kelas XI, Kelas XII`). Baris lama bernilai
  kosong dan tetap tampil seperti semula (satu baris tanpa kelas) — **tidak ada nilai yang ditebak**.
* **Halaman publik — kolom KELAS**: tabel kini **No, Nama Guru, KELAS, Mata Pelajaran, Capaian
  Pembelajaran (CP), Alur Tujuan Pembelajaran (ATP), RPP/Modul Ajar** dengan **satu baris per
  (kelas × mata pelajaran)**; nama guru tetap digabung satu sel (`rowspan`). Urutan baris mengikuti
  **urutan kelas pada menu Data Kelas** (mis. X, XI, XII) lalu urutan barisnya. Mata pelajaran yang
  belum diberi kelas tetap tampil satu baris dengan tanda **—** (tidak hilang).
  Helper: **`perangkat_tabel_tampil()`** (baris siap-cetak) di samping `perangkat_tabel()`
  (baris per guru × mapel); `perangkat_kelas_daftar()`, `perangkat_kelas_urut()`,
  `perangkat_kelas_pisah()`, **`perangkat_kelas_siap()`** (menyusun teks kelas dari kiriman
  pengguna: unik, urut Data Kelas, kelas luar di akhir, dibatasi `PERANGKAT_KELAS_BANYAK`).
  Chip ringkasan menambah **jumlah kelas** & **jumlah baris kelas**.
* **Panel Super Admin — berbentuk TABEL** (`#tblPerangkat`): kolom **No, Nama Guru, Kelas, Mata
  Pelajaran, CP, ATP, RPP/Modul Ajar, Tindakan**. Tiap baris memuat:
  * **kotak centang kelas** (daftar dari menu **Data Kelas**, kelas nonaktif ditandai "(nonaktif)")
    + isian **"Kelas lain (pisah koma)"** untuk kelas yang belum terdaftar di Data Kelas;
  * tiga sel berkas (ikon centang/silang, nama berkas, isian berkas, centang hapus);
  * tombol **Simpan** dan **Hapus**;
  * satu **baris tambah mata pelajaran** per guru (nama mapel + kelas sekaligus).
  🚨 **HTML tidak mengizinkan `<form>` menjadi anak `<tr>`**, jadi setiap baris memakai formulirnya
  sendiri yang diletakkan **di dalam sel** (`form.pg-form-baris`) dan seluruh isian baris itu
  menunjuk formulirnya lewat atribut **`form="pgF…"`** (pola yang sama dipakai e-GURU). Jangan
  mengubahnya menjadi `<form>` pembungkus baris — markup seperti itu tidak sah dan isian akan
  kehilangan pemiliknya.
  🚨 **Kelas tidak dikosongkan tanpa sengaja**: bila kiriman `save` **tidak memuat** medan kelas
  (`kelas[]`/`kelas_lain`) sama sekali (mis. unggahan berkas dari skrip), kelas yang tersimpan
  DIBIARKAN; formulir halaman selalu mengirim `kelas_lain` (walau kosong) sehingga mengosongkan
  centang tetap benar-benar mengosongkan kelas. Menyimpan kelas **tidak** menghapus berkas.
* **Kode download TETAP per MATA PELAJARAN** (seluruh kelas mapel itu memakai kode yang sama) —
  berkas CP/ATP/RPP juga milik mapel, jadi semua kelas memakai berkas yang sama. Keterangan popup
  kode kini menyebut **kelas** baris yang diklik (`data-pg-kelas`).
* Gaya **`.pg-admin-tabel`**, `.pg-kelas-isian/.pg-kelas-centang/.pg-kelas-label/.pg-kelas-lain`,
  `.pg-tambah-mapel`, `.pg-aksi` di `assets/style.css` — **jangan** menaruh `<style>` di halaman.

### Uji
`bash websk_uji_perangkat.sh` (**99**) + `bash websk_uji_perangkat_ui.sh` (**63**) +
`bash websk_cek_live.sh` (**194**, migrasi v25→v28 pada salinan data asli — termasuk periode & berkas per semester).


## v1.8.11 — MENU "PERANGKAT GURU" (skema v25)

Permintaan pemilik: *"Pada bagian perangkat guru isi dibuat seperti contoh yang saya upload. Dan
isiannya dibuat di akun super admin. Ketika ada file yang diupload maka akan centang hijau dan jika
belum maka silang merah. Untuk data gurunya otomatis dari dari jumlah data guru (Kepala Madrasah tidak
termasuk), dan untuk mata pelajarannya setiap guru bisa lebih dari satu. Pengunjung Ketika akan klik
download maka akan muncul popup masukkan kode download. Kode download di seting/generate oleh super
admin untuk setiap mata pelajaran berbeda."*

* **Halaman publik** (menu Kurikulum → Perangkat Guru, jenis menu baru **`perangkat`**, migrasi
  **v25** `db_menu_perangkat()`): tabel **No, Nama Guru, Mata Pelajaran, Capaian Pembelajaran (CP),
  Alur Tujuan Pembelajaran (ATP), RPP/Modul Ajar** — satu baris per (guru × mata pelajaran), nama guru
  digabung satu sel (`rowspan`) dan dinomori per guru. Tiap sel berisi **centang hijau** (berkas ada)
  atau **silang merah** (belum) + tombol **Download**. Modul **`inc/perangkat.php`**.
  ⚠️ **Sejak v1.9.0** susunannya menjadi **No, Nama Guru, KELAS, Mata Pelajaran, CP, ATP, RPP/Modul
  Ajar** dengan **satu baris per (kelas × mata pelajaran)** — lihat bagian v1.9.0 di bawah.
* **Data guru OTOMATIS** dari tabel `guru`: hanya `status = 'aktif'` dan jabatan **Kepala
  Madrasah/Sekolah** dikecualikan (`perangkat_jabatan_kepala()` — jabatan yang memuat "kepala" **dan**
  "madrasah/sekolah" saja, sehingga **Wakil Kepala Bidang …** tetap ikut; jangan menyaring hanya dengan
  kata "kepala", pernah salah mengecualikan wakil kepala). **Mata pelajaran boleh lebih dari satu**:
  diambil dari kolom `guru.mapel` (pemisah koma/titik koma/garis tegak) DITAMBAH mata pelajaran yang
  ditambahkan Super Admin pada panel.
* **Tabel `perangkat`** (satu baris per guru × mata pelajaran: `cp/cp_nama`, `atp/atp_nama`,
  `rpp/rpp_nama` — hanya URL media proxy + nama berkas asli) dan **`perangkat_kode`**
  (`mapel`, `kunci` unik, `kode`) = **kode unduh PER MATA PELAJARAN**, diatur/di-generate Super Admin
  (8 karakter, abjad tanpa 0/O/1/I/L).
* **POPUP kode download**: tautan Download membawa `data-pg-unduh` + data guru/mapel/jenis; JS
  `initKodeUnduh()` (`assets/app.js`) membuka `dialog#dialogKodeUnduh` dan mengisi isian tersembunyi,
  lalu formulirnya POST ke **`perangkat_unduh.php`** — halaman yang sama bila JavaScript gagal dimuat
  (formulirnya punya id **`formKodeUnduhHal`**).
  ⚠️ **Uji WAJIB memakai `#formKodeUnduhHal button[type=submit]`**, bukan `button[type=submit]` —
  tombol submit pertama pada halaman itu adalah **tombol PENCARIAN di header** (uji pernah salah
  sasaran sehingga POST-nya menuju `/cari?q=`).
* **Kode diperiksa di server** (batas `PERANGKAT_MAKS_COBA` 5× / `PERANGKAT_MENIT_COBA` 10 menit per
  sesi, pola sama dengan Kartu PIP) dan berkasnya **DIALIRKAN server** (`perangkat_kirim_berkas()`) —
  URL media tidak pernah sampai ke peramban, jadi kode benar-benar melindungi berkas. Slang kode benar
  → pola POST→Redirect→GET (`?kode=1`). Berkas yang belum diunggah → keterangan jelas (bukan unduhan
  kosong). Pemintaan tanpa CSRF → **419**.
* **Panel Super Admin `admin/perangkat.php`** (`require_login()` + **is_admin()**, peran `editor`
  **403**): kartu **Kode Download per Mata Pelajaran** (isi kode + tombol **Generate kode baru** +
  Salin) dan kartu per guru untuk **mengunggah/menghapus CP, ATP, RPP** (PDF/Word/Excel/PowerPoint/
  gambar, maks 15 MB) serta menambah/ menghapus baris mata pelajaran. Menu sidebar admin **Perangkat
  Guru** pada blok "Kesiswaan & Kepegawaian".
  ⚠️ **Sejak v1.9.0** isiannya berbentuk **SATU TABEL** (No, Nama Guru, Kelas, Mata Pelajaran, CP,
  ATP, RPP/Modul Ajar, Tindakan) — lihat bagian v1.9.0 di bawah.
* Gaya **`.pg-*`** meniru contoh pemilik (kepala tabel hijau muda bergaris tipis, teks tengah, ikon
  centang/silang SVG bulat, tombol unduh hijau membulat) — **jangan** menaruh `<style>` di halaman.
* ⚠️ **Server uji `php -S` hanya satu thread**: berkas uji TIDAK boleh diarahkan ke berkas di server
  uji itu sendiri (aplikasi menggantung saat mengambil berkasnya). Uji UI mengunggah berkas nyata
  lewat panel (media proxy) lebih dulu supaya unduhannya benar-benar teruji.
* ⚠️ Formulir kode yang BENAR memicu **unduhan** (bukan navigasi) → uji UI harus menunggu kejadian
  `download`, bukan `waitForNavigation`; permintaan `perangkat_unduh.php` juga dikecualikan dari
  daftar "permintaan gagal" (peramban membatalkannya karena berubah menjadi unduhan).

### Uji
`bash websk_uji_perangkat.sh` (**68**) + `bash websk_uji_perangkat_ui.sh` (**42**) +
`bash websk_cek_live.sh` (**160**) — angka uji Perangkat Guru diperbarui pada v1.9.0 menjadi 99 & 63.


## v1.8.10 — TAMPILAN "EVALUASI DIRI MADRASAH" DISAMAKAN PERSIS DENGAN APLIKASI EDM

Permintaan pemilik: *"Hasil tidak rapi, buat sama persis dengan yang ada di aplikasi edm."*

* **Seluruh gaya** halaman disalin dari **`edm/assets/style.css`** (kertas krem `#fffdf8`, kartu
  **tanpa garis gradien atas** seperti kartu halaman lain website ini, padding **18px**/radius **12px**,
  kotak nilai SKPM `2px solid #14764f` radius 14px padding 14px 26px, angka **Georgia 2.4rem**, pil
  predikat kecil (padding 2px 9px, font .76rem) + pil besar (.88rem), **tab bergaris bawah**
  (padding 9px 20px, garis bawah 3px) alih-alih tab berkotak, tabel padding **9px 10px** dengan
  kepala hijau tua + kaki emas muda, lebar kolom **52/130/170px**, wadah grafik **400px maksimal
  640px**) — hanya nama kelasnya diberi awalan **`.edm-`** agar tidak bertabrakan dengan gaya
  halaman lain.
* **Susunan markup** mengikuti `edm/rekap.php` (termasuk kelas bantu `.edm-mut/.edm-kecil/.edm-mb0`),
  dan **opsi grafik radar** disalin dari `edm/assets/app.js` (legenda bawah "**EDM Tahun <tahun>**",
  tooltip "Nilai: x.x"/"belum diisi", label sumbu berbentuk "**nilai (Nama Aspek)**", skala 0-100
  langkah 25, warna garis `#14764f`).
* **Dua bagian tetap TIDAK dicetak** (permintaan pemilik sebelumnya, jangan ditambahkan kembali):
  tombol **Cetak Rekap** dan kartu **Ringkasan Pengisian Tiap Aspek** — ada ujinya.
* ⚠️ Perhatikan perbedaan token warna: hijau tema website (`--hijau #15795a`) ≠ hijau aplikasi EDM
  (`#14764f`), sehingga garis kotak nilai memakai **nilai literal aplikasi EDM**, bukan `var(--hijau)`.
  Ukuran huruf dasar kartu juga dipatok **15px** (aplikasi EDM memakai `body{font-size:15px}`).
* 🚨 **JEBAKAN UJI BANDING**: (1) aplikasi EDM menyimpan sandi pada kolom **`users.sandi`**
  (bukan `password_hash`) — sandi pada SALINAN disetel di kolom itu; (2) Playwright dibatasi
  **1 tab per instance**, jadi pengukuran kedua halaman dilakukan **BERURUTAN** pada satu halaman
  (bukan dua tab sekaligus); (3) halaman EDM wajib **login lewat peramban** dulu sebelum diukur.

### Uji
`bash websk_banding_edm_ui.sh` (**131**, bukti "sama persis") + `bash websk_uji_edm.sh` (**85**) +
`bash websk_uji_edm_ui.sh` (**52**) + `bash websk_cek_live.sh` (**160**).


## v1.8.9 — MENU "EVALUASI DIRI MADRASAH" = TAMPILAN INDIKATOR EDM (skema v24)

Permintaan pemilik: *"Pada bagian Evaluasi Diri Madrasah isi dengan tampilan Indikator EDM pada
aplikasi EDM, hanya saja hilangkan Cetak Rekap dan Ringkasan Pengisian Tiap Aspek selebihnya buat
sama."*

* Menu **Informasi → Evaluasi Diri Madrasah** (jenis menu baru **`edm`**, migrasi **v24**
  `db_menu_edm()`) kini berisi tampilan **Indikator EDM**:
  kartu **Indikator EDM** (pemilih tahun + nilai SKPM besar + pil predikat + "x dari N butir sudah
  diisi — nilai masih sementara"), **tab Charts / Tabel**, grafik **radar** nilai tiap aspek, dan
  tabel **No, Aspek, Nilai SKPM, Terbilang** + baris kaki **SKPM**.
* **YANG SENGAJA TIDAK ADA** (permintaan pemilik, jangan ditambahkan kembali tanpa bertanya):
  tombol **Cetak Rekap** (dan seluruh tautan `cetak_rekap.php`) dan kartu **Ringkasan Pengisian Tiap
  Aspek** (kolom Koordinator/Butir selesai/Berkas bukti/Terakhir diubah).
* Modul **`inc/edm.php`** membuka basis data aplikasi **EDM** HANYA-BACA dengan koneksi TERPISAH
  (`EDM_DB_FILE` = `../edm/data/edm.sqlite`; env `WEBSK_EDM_DB` untuk uji, pengaturan `edm_db`),
  dan **hanya membaca tabel `evaluasi`** + kunci `tahun` pada `app_pengaturan`. **JANGAN** memuat
  `edm/inc/init.php` (konstanta & fungsinya bertabrakan).
* **Rumus nilai disalin dari aplikasi EDM** (lihat `edm/inc/hitung.php`): Tingkat 1-4 → 25/50/75/100,
  nilai aspek = rata-rata butir yang SUDAH diisi, SKPM = rata-rata aspek yang punya nilai, predikat
  Sangat Baik ≥ 80 / Baik ≥ 60 / Cukup ≥ 40 / Kurang. Bila rumus itu diubah di aplikasi EDM, ubah
  juga di sini — ada **uji silang** yang menjalankan kode aplikasi EDM pada basis data yang sama dan
  membandingkan hasilnya dengan modul ini (harus identik).
* **Jumlah butir per aspek** = `EDM_BUTIR_JUMLAH` (A7+B4+C7+D5+E3 = 26, dari instrumen "Data Indikator
  2026") DAN kode butir yang benar-benar ada di basis data — diambil yang TERBESAR, sehingga kalau
  pemilik menambah butir di aplikasi EDM, penyebut "dari N butir" ikut menyesuaikan tanpa menyunting
  berkas ini.
* Penolong grafik baru **`window.EDMW.radar()`** (`assets/app.js`) dan gaya blok `.edm-*`
  (`assets/style.css`) — **jangan** menaruh `<style>` di halaman. Tab Charts/Tabel memakai parameter
  alamat `?tampilan=` (bekerja tanpa JavaScript), tahun `?tahun=`.
* 🚨 **JEBAKAN UJI**: kutip tunggal bersarang di dalam `php -r '…'` pada skrip bash **memutus
  kutipan** (mis. `WHERE kunci = ''tahun''` → parse error "unexpected identifier"); tulislah SQL
  memakai kutip ganda di dalamnya atau pakai berkas PHP terpisah. Berlaku juga untuk `\$` yang
  tertulis apa adanya di dalam heredoc.

### Uji
`bash websk_uji_edm.sh` (**85**, termasuk uji silang dengan kode aplikasi EDM) +
`bash websk_uji_edm_ui.sh` (**52**) + `bash websk_cek_live.sh` (**160**).


## v1.8.8 — GRAFIK ALUMNI PER TAHUN + KOMPONEN GRAFIK "JUMLAH PER TAHUN" BERSAMA

Permintaan pemilik: *"Yang saya kasih tanda merah buatkan/tambahkan grafik alumni setiap tahunnya"* —
area bertanda merah ada **di atas filter tahun** pada halaman **Data Alumni** (menu Siswa → Data
Alumni), sama seperti grafik pendaftaran pada halaman Pendaftar.

* Kartu **`#alumniGrafik`** ("Grafik Alumni per Tahun") dicetak **DI ATAS filter tahun**: chip
  ringkasan (tahun berdata, jumlah alumni, tahun terbanyak), **diagram batang** (`#chAlumni`),
  **daftar angka per tahun sebagai teks** (`#alumniGrafikDaftar`, tetap terbaca tanpa JS), dan kaki
  kartu (rentang tahun, rata-rata per tahun, sumber: data kelulusan & data alumni).
* Angka dihitung **`alumni_per_tahun()`** (`inc/helpers.php`) dari **data yang SAMA dengan tabel
  halaman** (`alumni_gabungan()` = data kelulusan + tabel `alumni`, sudah bebas data ganda):
  hanya tahun keluar yang valid (> 1900), urut menaik, **hanya tahun yang ada alumninya**, alumnus
  **nonaktif tidak dihitung**, dan baris tanpa tahun tidak membuat batang "tahun 0".
* **KOMPONEN BERSAMA BARU `inc/grafik_tahun.php` → `grafik_tahun_kartu($o)`** — dipakai halaman
  **Pendaftar** dan **Data Alumni** supaya markup, chip, daftar angka, dan pemanggilan grafiknya
  identik (id kartu/kanvas/daftar diberikan pemanggil karena berbeda antar halaman). Refactor ini
  tidak mengubah keluaran halaman Pendaftar (diuji: id, kanvas, dan satuan tetap sama).
* Penolong grafik **`window.PFD.batang(id, labels, data, satuan)`** kini menerima **satuan**
  (bawaan `'pendaftar'`) sehingga tooltip, label dataset, dan judul sumbu Y berbunyi "alumni" pada
  halaman alumni. Grafik **tidak berubah saat tahun difilter** (menampilkan seluruh tahun berdata).
* 🚨 **JEBAKAN UJI (dua-duanya sudah menggigit)**: (1) **penanda heredoc bertabrakan** — mengganti
  penanda `<<'PY'` pada satu fungsi sementara penutupnya masih `PY` membuat sisa berkas uji ikut
  tertelan (gejala: `SLUG: unbound variable` dan banyak pemeriksaan gagal palsu); pakai penanda
  berbeda per fungsi. (2) **`group_concat(...) ... GROUP BY`** pada uji mengembalikan satu baris PER
  tahun sehingga hanya baris pertama terbaca (tahun jadi "2019,2019,2019") — pakai
  `json_group_array()` seperti pada uji UI grafik alumni.

### Uji
`bash websk_uji_grafik_alumni.sh` (**49**) + `bash websk_uji_grafik_alumni_ui.sh` (**30**) +
`bash websk_uji_pendaftar.sh` (**127**, memastikan halaman Pendaftar tidak berubah) +
`bash websk_cek_live.sh` (**160**).


## v1.8.7 — GRAFIK PENDAFTARAN PER TAHUN pada halaman "Pendaftar"

Permintaan pemilik: *"Yang saya kasih tanda merah buatkan/tambahkan grafik pendaftaran setiap tahunnya
(yang ditampilkan adalah tahun yang sudah ada pendaftarnya)"* — area bertanda merah ada **di atas
filter tahun** pada halaman **Pendaftar** (menu Siswa → Pendaftar).

* Kartu **`#pendaftarGrafik`** dicetak **DI ATAS filter tahun**: judul "Grafik Pendaftaran per Tahun",
  chip ringkasan (jumlah tahun berdata, jumlah pendaftar, tahun terbanyak), **diagram batang
  Chart.js** (`#chPendaftar`), **daftar angka per tahun sebagai teks**
  (`#pendaftarGrafikDaftar` — tetap terbaca walau skrip gagal dimuat), dan baris keterangan
  (rentang tahun, rata-rata per tahun, sumber data).
* **Hanya tahun yang SUDAH ADA pendaftarnya** yang masuk grafik: `ppdb_pendaftar_per_tahun()`
  (`inc/pendaftar.php`) mengelompokkan gabungan panel + PPDB Online dan membuang tahun berjumlah 0 —
  jadi tahun gelombang yang belum ada pendaftar (mis. 2027) **tidak** muncul sebagai batang, walau
  tetap tersedia pada pemilih tahun.
* Grafik **selalu menampilkan seluruh tahun berdata** (tidak ikut berubah saat tahun difilter) —
  ada ujinya. Saat **belum ada pendaftar sama sekali**, kartu grafik tidak dicetak (halaman tetap
  menampilkan keadaan ramah).
* Penolong grafik baru **`window.PFD.batang()`** (`assets/app.js`): sumbu Y **bilangan bulat**
  (`ticks.precision: 0`) dengan judul sumbu, batang tahun terbanyak diberi warna berbeda — sengaja
  TERPISAH dari `window.BOS` (rupiah) & `window.SVP` ( nilai 0–100) supaya formatnya tidak saling
  mengubah. Gaya di blok `.pf-grafik*` `assets/style.css` (**jangan** menaruh `<style>` di halaman).
* 🚨 **JEBAKAN UJI**: PHP di dalam `php -r '…'` pada skrip bash **tidak boleh memakai kutip bersarang**
  (`$x[] = "\"$th\"";` menjadi `""$th""` → PHP fatal "unexpected variable"). Pakai
  `json_encode(array_map("strval", array_keys($p)))` supaya keluarannya sama dengan yang dicetak
  halaman (`["2022","2024"]`), dan jangan memanggil penolong PHP (mis. `angka()`) dari shell —
  `angka: command not found`.

### Uji
`bash websk_uji_pendaftar.sh` (**127**, termasuk bagian 4b grafik) +
`bash websk_uji_pendaftar_ui.sh` (**65**, grafik benar-benar tergambar, tahun tanpa pendaftar tidak
masuk grafik, grafik tak berubah saat difilter) + `bash websk_cek_live.sh` (**160**).


## v1.8.6 — "PENDAFTAR" JADI MENU TERSENDIRI + TAHUN 2027 SELALU TAMPIL (skema v23)

Permintaan pemilik: *"Pendaftar yang berdampingan dengan data siswa isinya pindahkan ke menu pendaftar
seperti yang ada pada gambar yang saya tandai warna merah. Dan tambahkan tahun 2027 sesuai saran."*

* **Isi pendaftar dipindahkan** dari TAB pada halaman Data Siswa ke **halaman menu "Pendaftar"**
  (sub menu "Siswa"; jenis menu baru **`pendaftar`**). Tab di halaman Data Siswa **DIHAPUS** beserta
  gaya `.tab-publik`-nya, dan halaman Data Siswa kembali seperti semula.
* Halaman baru dirender **`inc/pendaftar.php` → `pendaftar_papan()`** (di-`require` `halaman.php`):
  filter tahun + tabel **No, Nomor Pendaftaran, Nama Siswa, Asal Sekolah, Tahun** + paginasi
  `PUBLIK_PER_HALAMAN` dengan keterangan rentang (`#infoPendaftar`), serta keadaan ramah
  (`#pendaftarKosong`) bila satu tahun belum punya pendaftar. Gaya di blok `.pf-blok`
  `assets/style.css` — **jangan** menaruh `<style>` di halaman.
* **Tahun 2027 (dan tahun gelombang lain) selalu tersedia** walau belum ada pendaftar:
  `ppdb_tahun_pilihan()` menggabungkan tahun yang punya data (panel & website) dengan tahun
  `ppdb_gelombang.tahun_ajaran` — tahun PPDB Online yang sudah dibuka muncul dengan jumlah `(0)`.
  `ppdb_tahun_aktif()` menyebut tahun yang gelombangnya aktif pada keterangan halaman.
* **Migrasi v23** (`db_menu_pendaftar()`): menu slug `pendaftar` (juga `data-pendaftar`/
  `pendaftar-ppdb`) berjenis `halaman` → `pendaftar`. Hanya baris berjenis `halaman` yang disentuh.
* 🚨 **JEBAKAN**: `val()` TIDAK ADA di aplikasi ini — penolong pengambil nilai tunggal bernama
  **`nilai()`** (sempat salah dipakai sehingga halaman fatal "Call to undefined function val()").
* 🚨 **JEBAKAN UJI**: bila menu `pendaftar` disiapkan SETELAH penanda skema naik ke versi terbaru,
  migrasi v23 tidak berjalan lagi (penanda dianggap cocok) sehingga menunya tetap `halaman` dan
  halaman tampil kosong tanpa galat. Skrip uji karena itu **menulis ulang penanda ke `22`** setelah
  menyiapkan menu, supaya jalur migrasinya benar-benar diuji.

### Uji
`bash websk_uji_pendaftar.sh` (**110**) + `bash websk_uji_pendaftar_ui.sh` (**51**) +
`bash websk_cek_live.sh` (**160**).


## v1.8.5 — DATA PENDAFTAR: 2022–2026 DARI PANEL PPDB LAMA + 2027+ DARI PPDB ONLINE (skema v22)

Permintaan pemilik: *“Pada menu siswa bagian pendaftar masukkan data pendaftar dari 2022 sampai 2026
yang diambil dari alamat https://ma.stm.sch.id/ppdb/panel_admin/verifikasi/thn/2022 … /2026. Untuk
2027 dan seterusnya diambil dari jumlah pendaftar ppdb online website. Data tampilkan filter per
tahun. Data yang ditampilkan Nomer Pendaftaran, Nama Siswa, Asal Sekolah.”*

**Pilihan pemilik (JANGAN diubah tanpa bertanya)**: (1) data 2022–2026 ditarik memakai kredensial
panel yang diisi admin; (2) hasil tarikan **disimpan** di website (bisa diperbarui kapan saja);
(3) tampil sebagai **tab "Pendaftar"** pada menu Data Siswa **dengan filter per tahun**.

### A. Tampilan: tab "Pendaftar" pada menu Data Siswa
* `halaman.php` (jenis menu `siswa`) kini mencetak **bilah tab** (`.tab-publik`): **"Data Siswa"**
  dan **"Pendaftar"** (`?tab=pendaftar`). Pilihan tab disimpan di `$tabSiswa`; `hal_filter()`
  mempertahankan `tab` sehingga berpindah halaman/filter tidak melepas tab.
* Tabel tab Pendaftar: **No, Nomor Pendaftaran, Nama Siswa, Asal Sekolah, Tahun** +
  **pemilih tahun** (`#tahunDaftar`, pilihan "Semua tahun" + tiap tahun yang ada datanya beserta
  jumlahnya), keterangan jumlah, dan paginasi `PUBLIK_PER_HALAMAN` (10) dengan keterangan rentang
  (`#infoPendaftar`). Tahun asing diabaikan (bukan 403).
* Gaya `.tab-publik`/`.tab-item` ada di `assets/style.css` (**jangan** menaruh `<style>` di halaman).

### B. Sumber data 2022–2026: panel PPDB lama (wajib login)
* Modul **`inc/ppdb_tarik.php`**: `ppdb_panel_url()` (konstanta `PPDB_PANEL_VERIFIKASI`, dapat
  diarahkan lewat env `WEBSK_PANEL_VERIFIKASI` untuk uji), `ppdb_tahun_panel()` (2022–2026 dari
  `PPDB_PANEL_TAHUN_MULAI`/`PPDB_PANEL_TAHUN_AKHIR`), **`ppdb_panel_login()`** (login sekali →
  mengembalikan berkas cookie "sesi"; env `WEBSK_PANEL_LOGIN` untuk uji), `ppdb_curl_opsi()`,
  `ppdb_curl_get()`, **`ppdb_tarik_halaman()`** (login + unduh halaman daftar satu tahun),
  **`ppdb_halaman_login()`** (halaman masih berupa formulir login = kredensial/sesi gagal),
  **`ppdb_parse_pendaftar()`** + `ppdb_parse_tabel()` + `ppdb_baris_nilai()` (parser tabel),
  **`ppdb_asal_sekolah_pendaftar()`** + **`ppdb_lengkapi_asal()`** (ASAL SEKOLAH per pendaftar),
  **`ppdb_simpan_tahun()`** (GANTI data satu tahun), `ppdb_hapus_tahun()`, `ppdb_panel_ringkas()`.
* **ASAL SEKOLAH tidak ada di halaman daftar** (`verifikasi/thn/<tahun>` hanya memuat No. Pendaftaran,
  NISN, NIK, Nama Lengkap, Status). Nilainya diambil dari halaman **Cetak Verifikasi**
  (`panel_admin/verifikasi_cetak/<nomor>`, baris "ASAL SEKOLAH : …") — satu permintaan per pendaftar,
  dibatasi `PPDB_PANEL_MAKS_BARIS` (600) dan `PPDB_PANEL_TIMEOUT` (30 detik). Halaman admin karena itu
  memanggil `@set_time_limit(0)` dan menganjurkan menarik 1–2 tahun setiap kali bila server membatasi
  waktu proses. Bila halaman cetak tidak terbaca → `asal_sekolah` dibiarkan KOSONG (tidak menebak).
* Halaman admin **`admin/ppdb_tarik.php`** (menu **PPDB Online → Tarik Data Pendaftar (Tahun Lama)**):
  centang tahun + nama pengguna + kata sandi panel → **Tarik data sekarang**; menampilkan hasil per
  tahun, daftar tahun tersimpan, tombol hapus per tahun, dan tautan "Lihat di situs".
  **Sandi TIDAK PERNAH disimpan** (hanya dipakai satu permintaan; kolomnya dikosongkan lagi) —
  ada uji yang memastikan tidak ada tabel penyimpan kredensial & sandi tidak muncul kembali.
* 🚨 **JEBAKAN yang sudah menggigit di parser**: kolom **"No"** (penomoran baris) juga memuat kata
  "nomor" sehingga pada versi pertama ia MEREBUT kolom **"Nomor Pendaftaran"** — yang tersimpan jadi
  `1`, bukan `2022-0001`. Perbaikannya: kunci `nomor_lemah` untuk "No"/"Nomor" saja + penolong
  **`ppdb_peta_tambah()`** (kunci kuat selalu menimpa yang lemah). Varian tabel tanpa `<th>` (judul di
  baris `<td>` pertama) juga didukung, dan ada cadangan berbasis pola bila judul kolom tak dikenali.
* 🚨 **Formulir login panel hanya diproses di alamat `panel_admin/log_in`** (POST ke halaman lain
  dijawab halaman login lagi). Versi pertama modul ini mem-POST kredensial ke halaman verifikasi
  (karena form-nya ber-`action=""`) sehingga login SELALU ditolak walau kredensial benar. Konstanta
  **`PPDB_PANEL_LOGIN`** + `ppdb_panel_login_url()` menyimpan alamat itu (dapat diubah lewat env
  `WEBSK_PANEL_LOGIN`).
* 🚨 **`session_start()` PHP gagal di sandbox** (folder `/var/lib/php/sessions` tidak ada) sehingga
  panel tiruan uji tidak pernah menyimpan sesi login — gejalanya "login ditolak" walau kredensial
  benar. `websk_panel_tiruan.php` mengarahkan `session_save_path()` ke `tmp_panel_sesi/` di sampingnya
  (folder itu dibersihkan uji). Aplikasi sendiri **tidak memakai session untuk penarikan** (cookie
  disimpan di berkas sementara lewat curl), jadi tidak terpengaruh.
* 🚨 Penarikan **tidak dapat dijalankan dari shell sandbox** untuk basis data SITUS (berkas basis data
  aplikasi yang sudah dipublikasikan bersifat **read-only** dari Bash: `attempt to write a readonly
  database`). Karena itu penarikan WAJIB dijalankan oleh situsnya sendiri (halaman admin di atas) —
  penarikan lewat HTTP diuji memakai **panel tiruan** (`websk_panel_tiruan.php`, env
  `WEBSK_PANEL_VERIFIKASI`) sehingga alur login + tarik + simpan benar-benar terbukti.
* Ekstensi **curl** dipakai modul ini (tersedia di server live; pada pemanggilan CLI uji perlu
  `-d extension=curl`).

### C. Sumber 2027 ke atas: PPDB Online website ini
Tidak perlu penarikan apa pun — dibaca dari tabel **`ppdb_pendaftar`** (formulir PPDB Online),
dengan tahun diambil dari **gelombang** (`tahun_ajaran`, mis. "2027/2028" → 2027) dan cadangan
`tanggal_daftar` (`ppdb_sql_tahun_website()`). Kedua sumber digabung satu SELECT
(`ppdb_sql_gabungan()`) sehingga tab Pendaftar, jumlah per tahun, dan filter tahunnya seragam.
Data 2027+ **tidak** disalin ke `ppdb_lama` (tabel itu khusus hasil tarikan panel).

### D. Skema v22
Tabel baru **`ppdb_lama`** (`tahun, nomor, nama, asal_sekolah, sumber, urutan, ditarik, dibuat`) +
indeks `idx_ppdb_lama_tahun`, didaftarkan di `SCHEMA_TABEL_WAJIB` & `SCHEMA_KOLOM_WAJIB`. Hanya
`CREATE TABLE`/`CREATE INDEX` — **tidak ada tabel lama yang dibangun ulang**; tabel baru tetap KOSONG
(tidak ada data yang ditebak).

### E. Uji
`bash websk_uji_pendaftar.sh` (**105**, termasuk alur login+tarik nyata lewat panel tiruan dan
pengambilan **asal sekolah** dari halaman cetak) +
`bash websk_uji_pendaftar_ui.sh` (**52**, Playwright) + `bash websk_cek_live.sh` (**160**).

### ⚠️ Catatan operasional
Data 2022–2026 baru muncul setelah admin menjalankan penarikan dari halaman admin (butuh kredensial
panel). Selama belum ditarik, tab Pendaftar menampilkan keadaan kosong dengan penjelasan singkat.

**Data pemilik sudah ditarik** (157 pendaftar: 2022 = 36, 2023 = 43, 2024 = 37, 2025 = 27, 2026 = 14 —
seluruhnya lengkap dengan asal sekolah) dan tersimpan di tabel `ppdb_lama` basis data LIVE. Karena
basis data live **tidak dapat ditulis dari shell sandbox** (read-only), pengisian awal dilakukan lewat
skrip impor sementara di dalam aplikasi yang **langsung dihapus** setelah selesai (dilindungi token
acak; token & berkas datanya juga dibersihkan) — cara itu hanya dipakai sekali dan tidak perlu
diulang: perbaruan berikutnya cukup lewat halaman admin.


## v1.8.4 — PAGINASI DAFTAR PUBLIK (skema tetap v21, tanpa migrasi)

Daftar publik yang panjang tidak lagi dicetak sekaligus: sejak v1.8.4 halaman **Perpustakaan
(empat laporan)**, **Sarpras (daftar inventaris barang)**, dan **Layanan PIP (daftar penerima)**
dipaginasi **`PUBLIK_PER_HALAMAN` = 10 baris per halaman** (konstanta di `inc/config.php`).

* Penolong BERSAMA di **`inc/helpers.php`** (dipakai ketiga halaman supaya perilakunya seragam):
  **`paginasi_jumlah()`**, **`paginasi_halaman()`** (nomor halaman dijepit ke rentang yang ada —
  `?hal=999` menampilkan halaman TERAKHIR, bukan tabel kosong), **`paginasi_potong()`**,
  **`paginasi_info()`** (“Menampilkan 1–10 dari 68 barang”), dan **`paginasi()`** (tautan
  Sebelumnya/Selanjutnya + nomor halaman memakai `hal_url()` sehingga tetap pada ALAMAT RAPI).
* Keterangan rentang + tombol halaman dicetak di wadah ber-id **`#halamanPerpustakaan`**,
  **`#halamanSarpras`**, dan wadah PIP — dipakai uji untuk mengenali keadaan halaman.
* 🚨 **Konsekuensi untuk uji**: pemeriksaan yang menghitung baris tabel WAJIB memakai
  `min(jumlah data, PUBLIK_PER_HALAMAN)` dan memeriksa **keterangan paginasi** untuk jumlah
  SELURUH baris. Uji yang disesuaikan: `websk_uji_perpus.sh` (**98**, + bagian 3b), 
  `websk_uji_perpus_ui.sh` (**72**, + klik “Selanjutnya” & `?hal=999`), `websk_uji_sarpras.sh`
  (**66**), `websk_uji_sarpras_ui.sh` (**42**), dan `websk_cek_live.sh` (**161**).
  Skrip pembungkus uji UI kini mengirim `PER_HAL` (dibaca dari `PUBLIK_PER_HALAMAN`) ke skrip Node.
* 🚨 **Data uji harus tampil di HALAMAN 1**: baris yang disisipkan uji Sarpras dulu memakai
  `urutan = 998` (halaman terakhir pada daftar 68 barang) sehingga pemeriksaan “barang baru
  langsung tampil” gagal palsu — sekarang memakai `urutan = 0`. Untuk Perpustakaan (urut judul)
  judul uji diawali angka (`000 BUKU BARU UJI …`) supaya berada di halaman pertama.


## v1.8.3 — MENU “INFORMASI → E-VOTING OSIM”: PAPAN HASIL REAL-COUNT (skema v21)

Permintaan pemilik: *“Pada bagian e-Voting OSIM isi dengan Hasil Real-Count pada aplikasi e-Voting
(Khusus MA. Sirojuth Tholibiin Mootilango).”*

**Pilihan pemilik (JANGAN diubah tanpa bertanya)**: (1) **MENGIKUTI SETELAN APLIKASI** — bila panitia
belum membuka hasil ke publik, halaman hanya menampilkan keterangan “hasil belum dibuka” (tanpa satu
angka pun); (2) angkanya **BERJALAN SENDIRI setiap 5 detik** lewat endpoint `voting_hasil.php`.

### A. Halaman & sumber data
* **`inc/voting.php`** (modul baru) di-`require` `halaman.php`; jenis menu baru **`voting`** dipakai
  lewat `voting_papan()`.
* Basis data aplikasi **e-Voting OSIM** dibuka **HANYA-BACA** (`mode=ro`, cadangan `query_only=1`)
  dengan koneksi TERPISAH — **JANGAN** memuat `e-voting-osis/inc/init.php`. Berkasnya diarahkan lewat
  env **`WEBSK_VOTING_DB`** (dipakai skrip uji) → pengaturan kunci **`voting_db`** → konstanta
  **`VOTING_DB_FILE`**; nama lembaga diperiksa terhadap **`VOTING_LEMBAGA`**
  (“MA. Sirojuth Tholibiin Mootilango”) supaya data lembaga lain tidak pernah ditampilkan.
* **Kerahasiaan pemilih dijaga**: yang dibaca hanya tabel `pengaturan`, `paslon`, `pemilih`
  (**hanya JUMLAHNYA** — nama/NISN/token pemilih tidak pernah ditampilkan), dan `suara` (satu baris
  per surat suara, tanpa identitas pemilih). Ada uji yang menolak bocornya token & nama pemilih.
* Isi papan (meniru halaman hasil aplikasi e-Voting): kepala (nama pemilihan + lembaga + periode +
  tanggal + pil **LIVE** + partisipasi + jam), **4 kartu statistik** (Pemilih Terdaftar, Suara Masuk,
  Belum Memilih, Tingkat Partisipasi + bilah), **grafik donat** persentase perolehan + legenda,
  kartu **Perolehan Suara Terbanyak**, **grafik batang** jumlah suara per paslon, dan **rincian tiap
  paslon** (nomor, foto, nama ketua & wakil, kelas, bilah, persen, jumlah suara). Aturan paslon
  mengikuti aplikasi: **paslon nonaktif tanpa suara tidak ditampilkan**, sedangkan paslon nonaktif
  yang pernah mendapat suara tetap tampil (diberi pil “nonaktif”).
* **Penyegaran otomatis**: `voting_hasil.php` (`voting_json()`) membalas JSON `ok/buka/dpt/masuk/
  belum/turnout/sah/jam/paslon`; skrip **`window.SVV`** (`assets/app.js`) mengambilnya tiap
  `data-jeda` (= `VOTING_POLLING`, 5 detik) dan memperbarui angka, bilah, legenda, pemimpin
  sementara, **dan kedua grafik** tanpa memuat ulang halaman. Penyegaran **berhenti saat tab
  disembunyikan** (hemat permintaan) dan **langsung menyegarkan** saat tab kembali terlihat;
  kegagalan jaringan tidak pernah menghapus angka yang sudah tampil.
* Gaya **`.vt-*`** ada di `assets/style.css` blok “INFORMASI E-VOTING OSIM” — **jangan** menaruh
  `<style>` di halaman. Grafik memakai **Chart.js 4.4.1** yang sudah di-vendor.
* Sumber data tidak terbaca → **keterangan ramah** (bukan Fatal error); endpoint menjawab **503 JSON**.

### B. Migrasi v21 (`db_menu_voting()` di `inc/db.php`)
Mengubah sub menu **e-Voting OSIM** (slug `e-voting-osim`, juga `e-voting-osis`/`voting-osim`/
`voting`) dari jenis `halaman` menjadi `voting`; **hanya** baris yang masih `halaman` yang disentuh.
Jenis “Informasi e-Voting OSIM” juga dapat dipilih admin pada panel **Menu & Halaman**.

### C. Uji
`bash websk_uji_voting.sh` (**83**) + `bash websk_uji_voting_ui.sh` (**47**) +
`bash websk_cek_live.sh` (**158**, pada SALINAN data asli — pada keadaan pemilik saat ini
`tampilkan_hasil = 0`, jadi halaman menampilkan keterangan “hasil belum dibuka” sesuai pilihan pemilik).

### 🚨 Catatan uji yang penting
* Uji UI **tidak boleh membuka tab kedua** pada satu instance Chromium (Playwright membatasi 1 tab):
  pengujian “tab disembunyikan” dilakukan pada HALAMAN YANG SAMA dengan menimpa `document.hidden`
  lewat `Object.defineProperty` lalu mengirim `visibilitychange`.
* Tabel `suara` pada aplikasi e-Voting punya kolom **`dibuat` NOT NULL** — data suara uji WAJIB
  mengisinya, kalau tidak `INSERT` gagal dan pengujian berikutnya ikut gagal palsu.
* Lebar bilah ditulis dengan **titik** desimal (`width:0.0%`) karena itu atribut `style` CSS,
  sedangkan angka yang TAMPIL memakai koma Indonesia — pembandingan uji harus dipisah.


## v1.8.2 — MENU “INFORMASI → SUPERVISI AKADEMIK”: DASHBOARD APLIKASI SUPERVISI (skema v20)

Permintaan pemilik: *“Pada bagian menu informasi bagian supervisi akademik buat isinya tampilan
dashboard aplikasi supervisi (Khusus MA. Sirojuth Tholibiin Mootilango), Supervisi terbaru, komentar
dan hasil tinjauan dokumen tidak perlu ditampilkan. Pengunjung hanya bisa melihat tetapi tidak bisa
mencetak. Tampilkan Filter Tahun Ajaran dan Semesternya.”*

**Pilihan pemilik (JANGAN diubah tanpa bertanya)**: (1) kartu **Peringkat Guru** ditampilkan **apa
adanya** (nama guru + nilainya); (2) kartu **Progres Pengiriman Dokumen** **tidak** ditampilkan;
(3) **tombol aksi** dashboard (Nilai/Daftar/Rekap/Mulai Supervisi) **dihilangkan**; (4) **Supervisi
Terbaru**, **Komentar Pengawas**, dan **Hasil Tinjauan Dokumen** tidak ditampilkan; (5) pengunjung
hanya melihat — **tidak bisa mencetak**.

### A. Halaman & sumber data
* **`inc/supervisi.php`** (modul baru) di-`require` `halaman.php`; jenis menu baru **`supervisi`**
  dipakai lewat `supervisi_papan()`.
* Basis data aplikasi **Supervisi Guru** dibuka **HANYA-BACA** (`mode=ro`, cadangan `query_only=1`)
  dengan koneksi TERPISAH — **JANGAN** memuat `supervisi-guru/inc/init.php` (konstanta & fungsinya
  bertabrakan dengan aplikasi ini). Berkasnya diarahkan lewat env **`WEBSK_SUPERVISI_DB`** (dipakai
  skrip uji) → pengaturan kunci **`supervisi_db`** → konstanta **`SUPERVISI_DB_FILE`**.
* **Sekolah pemilik data** ditentukan `svp_sekolah()` berurutan: nama sekolah website → konstanta
  **`SUPERVISI_SEKOLAH`** (= “MA. Sirojuth Tholibiin Mootilango”, itulah “khusus” yang diminta
  pemilik) → `SUPERVISI_SEKOLAH_ID` → sekolah pertama yang punya supervisi final. Yang dibaca hanya
  tabel `sekolah`, `guru`, `instrumen`, dan `supervisi`.
* Isi dashboard (meniru dashboard aplikasi Supervisi, **tanpa** bagian yang tidak diminta):
  empat kartu statistik (Guru Aktif + belum disupervisi, Supervisi Selesai + masih draf, Rata-rata
  Nilai, Predikat Terbanyak), kartu per menu supervisi (nilai + pil predikat + bilah capaian +
  “N penilaian · X% guru terjangkau”, **tanpa tombol**), empat grafik (batang per menu, donat
  sebaran predikat, garis tren 8 bulan, batang per mata pelajaran), tabel **Peringkat Guru**
  (nama + nilai, 10 teratas) dan kartu **Perlu Ditindaklanjuti** (guru belum disupervisi).
* **Saringan Tahun Ajaran + Semester** dibaca dari kolom `supervisi.periode` (mis. “2026/2027
  Ganjil” → TA `2026/2027`, semester `Ganjil`). Aturannya terpusat di **`svp_klausa_periode()`**
  (dipakai seluruh query — termasuk klausa terbenam “guru belum disupervisi” — supaya angkanya
  konsisten): TA saja → `periode = TA OR periode LIKE 'TA %'`; TA+semester → `periode = 'TA Sem'`;
  **semester saja** → `periode = Sem OR periode LIKE '% Sem'`. Nilai tak dikenal **diabaikan**
  (bukan 403) dan jatuh ke periode dengan data terbanyak.
* Gaya **`.sv-*`** ada di `assets/style.css` blok “INFORMASI SUPERVISI AKADEMIK” — **jangan** menaruh
  `<style>` di halaman. Grafik memakai **Chart.js 4.4.1** yang sudah di-vendor + penolong baru
  **`window.SVP`** (`bar`/`donut`/`line`) di `assets/app.js` — sengaja TERPISAH dari `window.BOS`
  (format BOS memakai rupiah, di sini skala nilai 0–100).
* Sumber data tidak terbaca → **keterangan ramah** (bukan Fatal error), halaman lain tetap normal.

### B. “HANYA MELIHAT, TIDAK BISA MENCETAK”
* Tidak ada tombol cetak/`window.print()` dan tidak ada tautan ke lembar cetak aplikasi Supervisi.
* `@media print` pada tema: **tabel rinci (`.sv-tanpa-cetak` — peringkat guru & perlu ditindaklanjuti),
  grafik, saringan, chip, dan keterangan disembunyikan**, lalu muncul kalimat
  *“Ringkasan ini hanya untuk dilihat — dokumen laporan supervisi cetak tersedia pada aplikasi
  Supervisi Akademik.”* Jadi laporan berisi nama/nilai per guru tidak dapat dihasilkan dari website.
* Diuji terukur: saat `emulateMedia({media:'print'})` kelas itu benar-benar `display:none`.

### C. Migrasi v20 (`db_menu_supervisi()` di `inc/db.php`)
Mengubah sub menu **Supervisi Akademik** (slug `supervis-akademik`, juga `supervisi-akademik`/
`supervisi`) dari jenis `halaman` menjadi `supervisi`; **hanya** baris yang masih `halaman` yang
disentuh (pengaturan admin tidak pernah tertimpa). Dipanggil di `ensure_schema()` SESUDAH
`db_isi_awal()`. Jenis “Informasi Supervisi Akademik” juga dapat dipilih admin pada panel
**Menu & Halaman**.

### D. Uji
`bash websk_uji_supervisi.sh` (**93**) + `bash websk_uji_supervisi_ui.sh` (**44**) +
`bash websk_cek_live.sh` (**148**, pada SALINAN data asli: menu bermigrasi, angka dashboard =
28 supervisi final & 7 guru aktif pada data pemilik, tanpa Supervisi Terbaru/tinjauan/tombol cetak).

### 🚨 Catatan uji yang penting
Data pemilik hanya punya SATU periode, sehingga saringan tidak akan benar-benar teruji tanpa data
tambahan: kedua skrip uji menambahkan **periode lain** (`2025/2026 Genap` & `2025/2026 Ganjil`) pada
SALINAN basis data Supervisi. Dari antarmuka, **mengganti Tahun Ajaran TIDAK mengosongkan semester**
(select semester ikut terkirim) — jadi uji harus menyetel semester lebih dulu (mis. “Semua
semester”) sebelum mengharapkan angka seluruh tahun ajaran.


## v1.8.1 — MENU “INFORMASI → PERPUSTAKAAN”: EMPAT TOMBOL LAPORAN DARI e-PERPUS (skema v19)

Permintaan pemilik: *“Pada bagian menu informasi bagian perpustakaan buat isinya beberapa Tombol
Laporan: Laporan Data Buku, Laporan Pengunjung, Laporan Peminjaman, Laporan Pengembalian. Isi dari
setiap laporan datanya diambil otomatis dari e-Perpus. Jadi akan terisi otomatis Ketika e-Perpus
melakukan update data. Dan Ketika tombol tersebut di klik akan muncul laporannya.”*

**Pilihan pemilik (JANGAN diubah tanpa bertanya)**: (1) **empat tombol di SATU halaman**; (2) laporan
yang dipilih **tampil di BAWAH tombol** (seperti tab); (3) **ada saringan tanggal Dari–Sampai**;
(4) **TANPA tombol cetak**.

### A. Halaman & sumber data
* **`inc/perpustakaan.php`** (modul baru) di-`require` `halaman.php`; jenis menu baru
  **`perpustakaan`** dipakai lewat `perpustakaan_papan()`.
* Basis data aplikasi **e-PERPUS** dibuka **HANYA-BACA** dengan koneksi TERPISAH
  (`mode=ro`, cadangan `query_only=1`) — **JANGAN** memuat `perpustakaan/inc/init.php` (konstanta &
  fungsi kedua aplikasi akan bertabrakan). Berkasnya dapat diarahkan lewat variabel lingkungan
  **`WEBSK_PERPUS_DB`** (dipakai skrip uji), tabel `pengaturan` kunci **`perpus_db`**, lalu konstanta
  **`PERPUS_DB_FILE`** (`../perpustakaan/data/perpustakaan.sqlite`); `PERPUS_SEKOLAH_ID` hanya
  cadangan. Yang dibaca **hanya** tabel `sekolah`, `buku`, `anggota`, `peminjaman`, `pengunjung`.
* Tombol = tautan `?lap=buku|pengunjung|peminjaman|pengembalian` (saringan tanggal ikut dibawa);
  nilai `lap` yang tidak dikenal **jatuh ke Data Buku** (bukan 403). Tautan dibangun
  `perpus_tautan()` memakai **`rute_kanonik()`** (jalur yang sedang dibuka) sehingga tetap benar
  walau admin mengganti slug menunya.
* Empat laporan (kolom mengikuti e-PERPUS): Data Buku (11 kolom, termasuk Dipinjam/Tersedia),
  Pengunjung (9 kolom), Peminjaman (10 kolom), Pengembalian (10 kolom + denda & kondisi).
  Ditutup kotak ringkasan + keterangan jumlah baris dan periode.
* **Saringan tanggal**: `dari`/`sampai` divalidasi `perpus_tgl_ok()` (menerima `Y-m-d` maupun
  `d/m/Y`), **rentang terbalik ditukar sendiri**, tanggal tidak sah **diabaikan** (bukan halaman
  rusak). Untuk Data Buku saringan menyaring **tanggal buku dicatat** (`buku.dibuat`) — keterangan
  itu ikut dicetak agar tidak menyesatkan. Sejak **v1.8.4** hasilnya **dipaginasi `PUBLIK_PER_HALAMAN`
  (10) baris per halaman** dengan keterangan rentang + tombol Sebelumnya/Selanjutnya (lihat bagian
  v1.8.4) — batas `PERPUS_LAPORAN_MAKS_BARIS` kini hanya pagar atas (5000) di SQL.
* Gaya **`.per-*`** ada di `assets/style.css` blok “INFORMASI PERPUSTAKAAN” (empat tombol grid 4
  kolom → 2 kolom ≤1080px → 1 kolom ≤760px; tabel lebar dapat digeser mendatar) —
  **jangan** menaruh `<style>` di halaman.
* Bila sumber data tidak terbaca → **keterangan ramah** (bukan Fatal error), halaman lain tetap normal.

### B. Migrasi v19 (`db_menu_perpustakaan()` di `inc/db.php`)
Mengubah sub menu **Perpustakaan** (slug `perpustakaan`/`perpus`) dari jenis `halaman` menjadi
`perpustakaan`; **hanya** baris yang masih `halaman` yang disentuh (pengaturan admin tidak pernah
tertimpa). Dipanggil di `ensure_schema()` SESUDAH `db_isi_awal()` (urutan penting: isi awal membuat
baris menunya). Jenis “Informasi Perpustakaan” juga dapat dipilih sendiri admin pada panel
**Menu & Halaman** (`admin/menu.php` + `jenis_menu_label()`).

### C. 🚨 Jebakan yang menggigit saat mengerjakan ini
* **Kutip tunggal yang ditutup SETELAH tanda kutip dua** (`'id="perSampai'"` — perhatikan posisinya!)
  membuat bash membaca `"` sebagai awal kutipan baru dan seluruh skrip uji gagal `unexpected EOF`.
  Tulis `'id="perSampai"'` (tanda `"` DI DALAM kutip tunggal). Gejalanya menyesatkan: pesan galat
  menunjuk baris LAIN (baris perintah sesudahnya).
* Judul buku dari data nyata memuat **tanda apostrof** (`Al-Qur'an`) sehingga `e()` mengubahnya jadi
  `&#039;` — pemeriksaan teks pada skrip uji wajib **mendekode entitas HTML** dulu (`teks_ada()`).

### D. Uji
`bash websk_uji_perpus.sh` (**83**) + `bash websk_uji_perpus_ui.sh` (**53**) +
`bash websk_cek_live.sh` (**136**, pada SALINAN data asli — sub menu Perpustakaan bermigrasi menjadi
jenis `perpustakaan`, tepat 4 tombol, tanpa tombol cetak, dan laporan benar-benar membaca basis data
e-PERPUS: 50 judul buku pada data pemilik).


## v1.1.0 — PEMBARUAN (permintaan pemilik)

### 1. Penyunting isi bergaya Word (`editor_teks()` di `inc/admin_helpers.php` + `initEditor()`)
Dipakai pada **Isi Berita** (`berita_edit.php`), **Isi Halaman** (`menu.php`), dan **Teks Pengantar
Fasilitas** (`fasilitas.php`).
- Bilah tombol: **Tebal, Miring, Garis bawah, Coret** · **Rata kiri/tengah/kanan/kiri-kanan** ·
  daftar berbutir & bernomor · judul bagian (H3) · bersihkan format · batalkan/ulangi ·
  **Sisip Gambar**. Jalan pintas Ctrl/Cmd+B/I/U tersedia.
- **Tetap bekerja tanpa JavaScript**: `<textarea>` tetap ada di markup (HTML mentah bisa diketik);
  skrip menyembunyikannya (`.editor-sumber-tersembunyi`) dan mengaktifkan kotak `contenteditable`,
  lalu menyinkronkan isinya kembali sebelum formulir dikirim. Bilah tombol hanya *pelengkap*.
- Tombol "Sisip Gambar" mengunggah ke **`admin/upload_editor.php`** (JSON) → gambar disisipkan
  sebagai `<img style="max-width:100%;height:auto">`; posisi kursor dipulihkan dari seleksi yang
  disimpan sebelum dialog berkas dibuka. CSS juga menjamin `img{max-width:100%}` pada halaman publik,
  jadi gambar selalu menyesuaikan lebar secara otomatis.
- **`bersihkan_html()` diperluas**: menyimpan atribut `style` (dengan daftar properti aman:
  text-align, font-weight/style, text-decoration, width/max-width/height, margin/padding, border,
  vertical-align) dan menerjemahkan `align="…"` menjadi `style="text-align:…"`, tetap
  membuang skrip/iframe/atribut berbahaya. Daftar tag: `HTML_TAG_BOLEH`.
- 🚨 **JEBAKAN BESAR yang diperbaiki di sini**: `potong()` memakai `strip_tags()` sehingga
  **menghapus SELURUH format** saat isi disimpan (inilah sebabnya format apa pun hilang sebelumnya).
  Untuk isi HTML gunakan **`potong_html()`** (fungsi baru) — jangan `potong()`.
  Sudah diperbaiki pada `berita_edit.php`, `menu.php`, dan `fasilitas.php`.

### 2. Cetak Data Guru & Data Siswa
- **`admin/cetak_guru.php`** dan **`admin/cetak_siswa.php`**: lembar **A4 MENDATAR**
  (`@page{size:A4 landscape}` ditulis di `<style>` halaman — kalau hanya di tema, PDF jadi A4 tegak),
  kop sekolah (logo/nama/alamat/kontak/NPSN-NSM), judul, baris meta (jumlah data L/P, saringan,
  tanggal cetak), tabel, baris JUMLAH, dan tanda tangan **kepala** di kanan.
- Tombol **Cetak Data Guru / Cetak Data Siswa** pada kepala kartu daftar membawa **saringan yang
  sedang aktif** (cari/status/jabatan untuk guru; cari/kelas/status untuk siswa).
- Terverifikasi terukur: PDF **842×595 pt (A4 mendatar)**, **1 halaman untuk ±13 baris data guru**,
  tabel tidak meluber, dan bila dipandang di HP lembar dibungkus `.lembar-gulir` (bergulir mendatar)
  sehingga halaman panel tidak ikut meluber.
- ⚠️ Saat menguji dengan Playwright: **jangan mengembalikan media ke `screen` sebelum `page.pdf()`**
  (PDF akan memakai gaya layar → toolbar ikut tercetak dan lembar pecah 2 halaman), dan
  **kembalikan `emulateMedia({media:null})`** sebelum mengukur tampilan layar.

### 3. Hapus semua data guru / siswa
- Tombol **Hapus Semua Data Guru** (`guru.php`) dan **Hapus Semua Data Siswa** (`siswa.php`) memakai
  `form_konfirmasi_ketik()`: **wajib mengetik HAPUS** (kata salah → ditolak dengan pesan) + dialog
  konfirmasi peramban.
- Menghapus seluruh siswa **sekaligus membersihkan riwayat `kenaikan`** dalam satu transaksi
  (`BEGIN IMMEDIATE`), karena riwayat itu menunjuk siswa yang sudah tidak ada.
- Kartu bahaya hanya tampil bila masih ada data.

### 4. Perbaikan kartu Keunggulan & Program Unggulan
Gejala yang dilaporkan pemilik *"bagian Keunggulan (Strip di Bawah Slider) tidak bisa menambahkan"*
sebabnya: **tidak ada tombol Tambah** pada kartu itu — formulir tambah hanya ada di bawah halaman
dengan satu pemilih jenis, sehingga tidak jelas/tidak ditemukan. Sekarang **setiap kartu punya
tombol “Tambah Keunggulan” / “Tambah Program” dan formulirnya sendiri** (id `#formKeunggulan` /
`#formProgram` diletakkan pada `<form>`, bukan pada judul), dengan id medan terpisah
(`judul_k`/`judul_p`, …) supaya tidak ada id kembar.
Strip beranda juga dirapikan untuk jumlah selain 4: pemisah antar butir hanya pada baris pertama
(`.strip-item:nth-child(4n+1){border-left:0}`) sehingga butir ke-5 dan seterusnya rata di kolom pertama.

### 5. Tombol PPDB pada menu diperkecil
`.nav-tombol` (dan `.nav-sorot>a`) kini `padding:11px 18px;font-size:.9rem;line-height:1.15`
(sebelumnya 12px 20px / 15.2px) → tinggi **41 px** (sebelumnya 51 px), ikon 15px, tetap sejajar dengan
deretan menu (49 px) dan nyaman diklik.


## v1.2.0 — PERBAIKAN TAMPILAN BERANDA (permintaan pemilik) + skema v2

Susunan beranda SEKARANG (urutannya dikunci uji pengukuran `websk_ukur.js`):

| # | Bagian | Penanda uji |
|---|--------|-------------|
| 1 | Slider besar (hero) | `data-bagian="beranda"` |
| 2 | Strip keunggulan | `data-bagian="keunggulan"` |
| 3 | **Berita Terbaru — 4 berita, susunan 2 kiri & 2 kanan** (+ kartu PPDB di kanannya) | `data-bagian="berita"` |
| 4 | **Program Unggulan sebagai SLIDER** | `data-bagian="program"` |
| 5 | **Slider GURU (foto + nama, ukuran sedang)** | `data-bagian="guru"` |
| 6 | Agenda + Sambutan kepala (dengan **foto kepala** di atasnya) | `data-bagian="agenda"` |
| 7 | Statistik (Guru & Tendik/Siswa/Alumni/Pendaftar) | `data-bagian="statistik"` |
| 8 | Apa kata mereka (testimoni) | `data-bagian="testimoni"` |
| 9 | **Galeri & Media Sosial — slider FOTO yang diunggah** + tombol media sosial besar | `data-bagian="galeri"` |

Rincian perubahan:
- **Berita 4 kartu** memakai kelas baru `.berita-beranda` (grid 2 kolom) + `.berita-mini`
  (kartu vertikal: gambar 150px, tanggal + pil kategori, judul, ringkasan). `berita_terbaru(4)`.
- **Program Unggulan** dipindah KE BAWAH berita dan menjadi slider: `.gulir-lacak` + `.gulir-item`
  (3 kartu per layar; 2 di ≤980px, 85% di ≤620px), panah di kepala bagian, geser otomatis 7 detik.
- **Slider guru**: `.gulir-guru` dengan kartu `.guru-mini` (foto bulat **118px** — ukuran sedang,
  96px di HP), nama, jabatan · mata pelajaran; data dari `guru_publik()` (guru berstatus aktif,
  maks 12) + tombol menuju halaman Tenaga Pendidik.
- **Foto kepala madrasah**: kolom baru **`sekolah.kepala_foto`** (skema v2) + unggahan pada
  panel admin → **Identitas Sekolah** → kartu "Foto Kepala Madrasah" (`blok_gambar('kepala_foto')`).
  Di beranda tampil **DI ATAS judul "Sambutan Kepala Madrasah"** dengan bingkai 4:5 maks 230px;
  bila belum diunggah, dipakai bulatan inisial + keterangan cara mengunggahnya.
- **Blok "Tautan Cepat" DIHAPUS** dari beranda (beserta variabel `$anakCepat`).
- **Galeri & Media Sosial** menampilkan **slider foto galeri** (`.gulir-foto` + `.foto-slide` dari
  tabel `galeri_foto`, 3 per layar, geser otomatis 6 detik, klik menuju albumnya). Slider kecil
  (gambar + tautan medsos) tetap ditampilkan di bawahnya, dan tombol media sosial tetap ada.

### Perbaikan perilaku slider (dipakai semua slider)
- `data-gulir-auto="7000"` → bergeser sendiri; **berhenti saat disorot/disentuh/fokus/nonaktif**,
  dan **tidak berjalan** bila pengguna memilih "kurangi gerak" (`prefers-reduced-motion`).
- Panah **maju** berputar kembali ke awal setelah slide terakhir; panah **mundur** dari awal
  melompat ke akhir.
- **Bila isi slider tidak melebihi wadahnya (mis. hanya 3 foto untuk 3 kolom), tombol panah
  disembunyikan** dan geser otomatis dimatikan — supaya tidak ada tombol yang tidak berefek.
  ⚠️ Uji yang menekan panah harus memeriksa dulu apakah tombolnya tampil (`nav.hidden`).
- Jarak antar kartu dibaca dari `column-gap` (bukan angka 20px yang dipatok) sehingga geseran
  selalu pas dengan lebar kartu + celahnya.

### Jejak penanda `<form>` yang sering salah (dipakai uji)
Banyak kartu admin dulu menaruh `id="formX"` pada **judul `<h3>`**, bukan pada `<form>`, sehingga
tautan `#formX`/selektor uji gagal. Sekarang id itu ada pada **`<form>`** yang benar:
`galeri.php` (`#formAlbum`, `#formVideo`), `slider_kecil.php` (`#formKecil`, `#formSosial`),
`keunggulan.php` (`#formKeunggulan`, `#formProgram`).


## v1.3.0 — PENYESUAIAN BERANDA lanjutan (permintaan pemilik) + skema v3

Berdasarkan 5 tanda pada tangkapan layar pemilik:

1. **Bidang merah** (ruang hijau KOSONG di bawah kartu PPDB) → **diganti POLLING PENDAPAT**:
   kartu `#polling` berisi pertanyaan **“Bagaimana pendapat anda tentang web ini?”** + pilihan jawaban,
   tombol **Kirim Pendapat**, tautan **Lihat hasil**, dan setelah memilih langsung tampil
   **persentase + jumlah suara** (batang `.poll-track`, jawaban terbanyak berwarna emas).
   - Skema v3: tabel **`poll`** (pertanyaan/keterangan/aktif), **`poll_pilihan`** (teks/urutan/aktif),
     **`poll_suara`** (pilihan_id/waktu/ip/token) + 3 indeks; isi awal disemai sekali dengan penanda
     **`poll_awal_v1`** (4 pilihan) sehingga tetap berjalan pada basis data yang sudah terisi.
   - Suara dikirim ke **`polling_suara.php`** (POST + CSRF) lalu dialihkan kembali ke `#polling`.
     **Satu pengunjung = satu suara** (token sesi `poll_token()` + kolom `token`; suara ganda ditolak
     dengan pesan ramah). Halaman `?poll=hasil` menampilkan hasil tanpa memilih.
   - Pengelolaan: menu admin baru **Polling Pendapat** (`admin/polling.php`) — pertanyaan, keterangan,
     aktif/nonaktif, pilihan jawaban (tambah/ubah/urutkan/aktifkan/hapus), rekap hasil + persentase,
     dan **Hapus Semua Suara** (wajib ketik HAPUS) untuk memulai perhitungan dari nol.
   - 🚨 **Akibat penting**: `.kartu-ppdb` TIDAK lagi memakai `height:100%`. Sebelumnya kartu itu
     diregangkan setinggi kolom sehingga menyisakan ruang hijau kosong (bidang yang ditandai pemilik)
     dan kartu polling MELUBER keluar dari bagiannya (menumpuk bagian program). Sekarang
     `.berita-ppdb{align-items:start}` dan kartu setinggi isinya.
2. **Bidang biru** (bagian Guru & Tendik) → **diberi latar HIJAU**: kelas `.seksi.hijau`
   (gradasi hijau tua), judul putih, panah putih/gold, kartu guru tetap putih, dan tombol
   “Lihat Semua Guru & Tendik” menjadi **emas** agar kontras.
3. **Bidang kuning** (strip “slider kecil”) → **DIHAPUS dari beranda**; CSS `.strip-kecil*` ikut
   dibuang. Pengelolaan **Slider Kecil** dihapus dari panel admin: menu **“Slider Kecil & Medsos”**
   menjadi **“Media Sosial”** (`admin/medsos.php`, khusus mengelola media sosial);
   `admin/slider_kecil.php` kini hanya **pengalih** ke `medsos.php` (agar alamat lama tidak jadi
   jalan buntu). Tabel `slider_kecil` **dibiarkan utuh** di basis data (tidak dihapus).
4. **Bidang ungu** (tombol media sosial) → **ikon diperbesar** (19 → 24px; 20px di HP), tombol lebih
   tinggi (≥44px), dan ditata **grid** (`minmax(170px,1fr)`) sehingga **tepi kiri-kanannya SEJAJAR
   dengan slider galeri di atasnya** (terukur sama: x=150, lebar 1140 pada 1440px).
5. Perbaikan yang ikut terbawa:
   - **Luapan 10px di HP 390px** (semua halaman) berasal dari **kotak pencarian di bilah atas** yang
     berbagi baris dengan ikon media sosial; pada ≤640px pencarian kini menempati **baris sendiri**
     selebar wadah (`.cari-top{flex:1 1 100%}` + `input{width:100%;min-width:0}`) → 0px luapan.
   - **Pesan flash kini juga tampil pada halaman publik** (`situs_atas()` mencetak `.flash-kerangka`).
     Sebelumnya hanya panel admin yang mencetak flash, sehingga pesan “Terima kasih! Pendapat Anda
     sudah kami catat.” tidak terlihat oleh pengunjung.

### Uji
- `websk_uji.sh websk_admin_ui.js` → **253 pemeriksaan** (ditambah: halaman Polling & Media Sosial,
  pilihan nonaktif hilang dari beranda, memilih dari beranda + cegah suara ganda, bagian guru hijau,
  strip kecil tidak ada, ikon media sosial ≥22px & sejajar dengan galeri).
- `websk_uji.sh websk_ukur.js` → tambahan pengukuran: latar hijau bagian guru, tinggi kartu PPDB
  < 700px (ruang kosong hilang), kartu polling tepat di bawah PPDB & tidak menumpuk bagian lain,
  tombol media sosial ≥44px dan sejajar kiri-kanan dengan galeri.


## v1.3.1 — PERAPIAN TATA LETAK (permintaan pemilik, tanpa perubahan skema)

1. **Tombol PPDB Online dipindah keluar dari deretan menu.** Sebelumnya menu ber-tanda `sorot`
   dirender SEBAGAI ITEM di dalam `.nav-utama`; karena 8 menu + tombol tidak muat sebaris, tombolnya
   terlipat ke baris KEDUA dan menyisakan ruang kosong di kanannya (yang ditandai pemilik).
   Sekarang:
   - `situs_atas()` **melewati** menu `sorot` saat merender `.nav-utama`, lalu merendernya
     tersendiri di `.head-aksi` (setelah `</nav>`) → tombol berdiri sendiri di **ujung kanan header**;
   - CSS: `.nav-utama{margin-left:auto}` + `.head-aksi{flex:0 0 auto}` dan
     `.head-dalam{align-items:center}` → tombol **sejajar tengah** dengan baris menu
     (terukur: pusat 85px = 85px pada 1440px);
   - ukuran disesuaikan: `padding:12px 18px`, ikon 16px → tinggi 43px (setara baris menu);
   - agar 8 menu **selalu satu baris**, blok brand + huruf menu dirapatkan:
     `.brand{max-width:272px}` (238px di ≤1340px), `.nav-item>a{padding:12px 10px;font-size:.92rem}`,
     `.nav-utama{gap:0}`. Diuji satu baris pada **1200–1600px** (`websk_ukur.js` memeriksa
     `barisMenu === 1` dan `navH <= 60`).
   - Pada **≤1080px** (mode lipat) tombol TETAP tampil di baris atas di samping tombol menu:
     `.head-aksi{order:2;margin-left:auto}` + `.tombol-nav{order:3}` (brand `order:1`, menu `order:4`).
   - ⚠️ Aturan lama `.nav-sorot>a{…}` sudah tidak dipakai dan ikut dibuang; jangan dihidupkan lagi.
     Saat menyunting blok `.nav-tombol` hati-hati: pernah tertinggal
     `".nav-tombol{display:inline-flex;.nav-tombol{display:inline-flex;…"` yang membuat SELURUH CSS
     setelahnya tidak terpakai (header jadi bertumpuk di semua ukuran layar).
2. **Kartu Program Unggulan berwarna BERBEDA-BEDA** (6 warna berputar lewat
   `.gulir-program .gulir-item:nth-child(6n+N)`): **hijau** `#eaf6f0`/ikon `#15795a`,
   **emas** `#fdf6e3`/ikon `#c9a227` (ikon gelap `#3a2c00` agar kontras), **biru** `#eaf1fb`/`#2563a8`,
   **ungu** `#f2ecfb`/`#6d4bb5`, **merah** `#fdecea`/`#b3261e`, **teal** `#e7f5f6`/`#0f7d86`.
   Lingkaran ikon dan warna panah ikut berbeda; judul/keterangan tetap gelap agar terbaca.

### Uji
- `websk_admin_ui.js` → **262 pemeriksaan**: deretan menu satu baris, tombol PPDB terpisah di kanan
  sejajar tengah dengan menu, ukuran tombol wajar, tidak ada sisa `.nav-sorot`, dan **setiap kartu
  program berbeda warna** (latar maupun lingkaran ikonnya).
- `websk_ukur.js` → pengukuran header (`barisMenu`, `navH`, keselarasan pusat tombol vs menu, jarak
  tombol ke tepi kanan) + warna kartu program.


## v1.3.2 — TOMBOL MEDIA SOSIAL BERWARNA KHAS (permintaan pemilik, tanpa perubahan skema)

Deretan tombol media sosial pada bagian “Galeri & Media Sosial” kini **berwarna berbeda sesuai
warna khas platformnya**, bukan putih seragam:
**Facebook** biru `#2d88ff → #1462c9`, **Instagram** gradasi `#f58529 → #dd2a7b → #8134af`,
**YouTube** merah `#ff2b16 → #c31407`, **WhatsApp** hijau `#25d366 → #0f7a52`, **TikTok** hitam
`#333941 → #0b0e12`; media sosial lain (`globe`) memakai gradasi tema hijau. Tulisan & ikon di
dalamnya putih, dengan sorotan lembut + bayangan saat disorot.

Cara kerjanya:
- helper baru **`sosial_jenis($nama)`** (facebook/fb, instagram/ig, youtube/yt, whatsapp/wa, tiktok,
  sisanya `globe`) dan **`sosial_kelas($nama, 'sos-')`** di `inc/layout.php`; `ikon_sosial()` kini
  memakai `sosial_jenis()` sehingga jenis ikon dan warna selalu sejalan;
- kelas itu dipasang pada **tiga tempat**: tombol besar di beranda (`.medsos-baris a.sos-*`),
  ikon **bilah atas** (`.topbar-sosial a.sos-*`, versi terang untuk latar hijau tua), dan ikon
  **kaki halaman** (`.kaki-sosial a.sos-*`, juga versi terang);
- warna dipilih sedikit lebih gelap pada ujung gradasi supaya tulisan putih tetap terbaca;
- menambah media sosial baru cukup lewat menu **Media Sosial** — warnanya otomatis mengikuti pilihan
  ikon (tanpa menyentuh kode), dan pilihan yang belum punya warna khusus memakai warna tema.

### Uji
- `websk_admin_ui.js` → **274 pemeriksaan** (ditambah: setiap tombol berkelas `sos-*`, latar
  bergradasi & **berbeda satu sama lain**, tulisan/ikon putih, warna Facebook/Instagram/YouTube/
  WhatsApp diperiksa terhadap nilai RGB warna platformnya, serta kelas warna pada bilah atas & kaki).
- `websk_ukur.js` → pengukuran: warna tombol berbeda-beda, tinggi ≥44px, kelas `sos-*` terpasang di
  ketiga tempat.


## v1.4.0 — KESISWAAN: DATA SISWA & DATA ALUMNI (permintaan pemilik) + skema v4

Dua **jenis menu baru** yang dirender `halaman.php` (bukan halaman berkas terpisah):
`jenis = 'siswa'` → **Data Siswa**, `jenis = 'alumni'` → **Data Alumni**.

### Data Siswa (`halaman.php?slug=data-siswa`)
Kolom: **Foto · Nama Siswa · NISN · Jenis Kelamin · Kelas · Status**.
- **Penyaring kelas** untuk pengunjung (`?kelas=ID`, pilihan berbentuk daftar kelas + jumlah siswanya,
  `data-auto` sehingga langsung tersaring). Id kelas asing **diabaikan** (kembali ke semua kelas).
- Bila penyaring kelas dipakai → satu tabel; bila **"Semua kelas"** → daftar **dikelompokkan per kelas**
  (`.judul-kelompok` dengan jumlah siswa).
- Status ditulis **Aktif / Tidak Aktif** (`status_siswa_label()`), siswa berstatus **lulus TIDAK ikut**
  (mereka tampil di Data Alumni); siswa urut "aktif dulu" lalu nama.
- Paginasi 24 baris (`$PER_HAL`), foto memakai `siswa.foto` (inisial bila kosong).

### Data Alumni (`halaman.php?slug=data-alumni`)
Kolom: **Foto · Nama Alumni · Jenis Kelamin · Tahun Masuk · Tahun Keluar · Keterangan** (+ kelas terakhir
sebagai keterangan kecil di bawah nama).
- Hanya siswa berstatus `lulus`; **Tahun Masuk** = tahun dari `tanggal_masuk`, **Tahun Keluar** =
  `tahun_lulus`, **Keterangan** = kolom baru **`siswa.keterangan`** (kondisi setelah lulus: kuliah/bekerja).
- **Penyaring tahun keluar** (`?tahun=YYYY`) dari tahun yang benar-benar ada datanya; tahun asing diabaikan.

### Basis data & panel admin
- **Skema v4**: kolom `siswa.keterangan` lewat `db_add_column` **dan** ditulis pada `CREATE TABLE`,
  serta didaftarkan di **`SCHEMA_KOLOM_WAJIB`** (`'siswa' => ['kelas_id','status','tahun_lulus','keterangan']`).
- Penyemaian dua menu baru dijalankan **sekali** lewat **`db_menu_kesiswaan()`** dengan penanda
  **`menu_kesiswaan_v2`**, dan bersifat **upsert**: bila menu dengan slug itu sudah ada (pemilik sudah
  membuatnya sendiri sebagai halaman biasa) → **jenisnya ditingkatkan** ke `siswa`/`alumni`
  (judul/subjudul yang sudah diisi pemilik TIDAK ditimpa); bila belum ada → dibuat pada menu induk
  **Kesiswaan** di urutan terakhir. ⚠️ Penanda harus tetap ada; jangan menambahkan pemeriksaan
  "kalau sudah ada, lewati" saja — menu lama berjenis `halaman` harus ikut diperbaiki.
- `admin/siswa.php`: medan **Keterangan Alumni** + label **Tahun Keluar / Lulus**; kolom daftar admin
  menampilkan tahun lulus & keterangan; kepala kartu menautkan ke halaman publiknya.
- `admin/kenaikan.php`: proses "Luluskan" menyediakan **Tahun Keluar** dan **Keterangan Setelah Lulus**
  (diterapkan ke semua siswa yang dicentang; keterangan hanya menimpa bila diisi — `CASE WHEN ? <> ''`).
- `admin/menu.php`: jenis **"Daftar Data Siswa"** & **"Daftar Data Alumni"** dapat dipilih admin
  (`jenis_menu_label()` di `inc/admin_helpers.php`).
- Helper baru (`inc/helpers.php`): `kelas_publik()`, `siswa_publik_wheres/jumlah/publik()`,
  `alumni_publik_wheres/jumlah/publik()`, `alumni_tahun()`, `status_siswa_label()`,
  `status_siswa_kelas()`, `tahun_dari()`; penolong `hal_filter()` (menjaga saringan saat menekan Reset).
- Gaya: `.filter-publik/.filter-baris/.filter-jumlah`, `.judul-kelompok`, `table.tabel-publik`,
  `.foto-siswa` (44×56px, inisial 3:4) di `assets/style.css`.

### 🚨 Jebakan yang ketemu saat mengerjakan ini
1. **Id kembar antara penyaring dan formulir** pada halaman admin (`#status` dipakai penyaring sekaligus
   medan formulir di `siswa.php`, `guru.php`, `ppdb_pendaftar.php`, `berita.php`) → HTML tidak sah dan
   `selectOption('#status')`/klik tombol bisa mengenai kontrol yang salah (uji jadi menggantung karena
   `data-auto` mengirim formulir saringan). **Semua id penyaring kini berawalan `saring`**
   (`#saringCari`, `#saringStatus`, `#saringKelas`, `#saringKategori`, `#saringJabatan`, `#saringGelombang`).
   Periksa dengan skrip kecil `preg_match_all('/\bid="…"/')` pada semua berkas (sekarang bersih).
2. **Menu Data Siswa/Data Alumni sudah dibuat pemilik** sebagai halaman biasa (jenis `halaman`, isi
   `<p><br></p>`), sehingga penyemaian "kalau sudah ada, lewati" TIDAK cukup — lihat butir upsert di atas.
3. Basis data aplikasi yang sudah dipublikasikan **read-only dari Bash** → seluruh pemeriksaan migrasi
   dilakukan pada **salinan** (`bash websk_cek_live.sh`).

### Uji
- `websk_admin_ui.js` → **298 pemeriksaan** (kolom & urutan kedua halaman, penyaring kelas benar-benar
  menyaring satu kelas, penyaring tahun keluar menyaring satu tahun, pengelompokan per kelas, Status hanya
  Aktif/Tidak Aktif, medan Keterangan pada form Data Siswa + tampil di daftar admin & halaman publik,
  pilihan jenis menu baru di Menu & Halaman, medan Tahun Keluar/Keterangan pada proses kelulusan).
- `websk_ukur.js` → tata letak terukur kedua halaman (6 kolom, tabel tidak melebihi artikel, foto 44×56px,
  tanpa luapan di 1440px & 390px).
- **`websk_cek_live.sh`** → **39 pemeriksaan** MIGRASI pada **salinan data asli** pemilik (akumulatif sejak skema v4):
  penanda skema naik ke 4, kolom `siswa.keterangan` dibuat, jenis kedua menu diperbaiki menjadi
  `siswa`/`alumni`, menu tidak dibuat ganda, **sidik jumlah baris SELURUH tabel identik** sebelum/sesudah,
  migrasi idempoten, integritas & FK bersih. Data asli tidak pernah disentuh.


## v1.5.0 — HALAMAN PRESTASI & PELATIHAN (permintaan pemilik) + skema v5

Empat halaman baru yang isinya diatur admin, semuanya memakai **satu tabel `prestasi`** dengan kolom
**`kategori`** (`siswa` / `madrasah` / `pendidik` / `pelatihan`) sehingga tidak ada empat modul kembar:

| Halaman | Menu | Kolom yang tampil | Jenis menu |
|---|---|---|---|
| **Prestasi Siswa** | Kesiswaan | No, **Foto Siswa**, Nama Siswa, Jenis Prestasi, Tahun, **Tingkat**, **Sertifikat** | `prestasi_siswa` |
| **Prestasi Madrasah** | Akademik | No, Nama Prestasi, Tahun, **Sertifikat** | `prestasi_madrasah` |
| **Prestasi Pendidik** | Akademik | No, **Foto**, Nama Guru, Jenis Prestasi, Tahun, **Tingkat**, **Sertifikat** | `prestasi_pendidik` |
| **Pelatihan Pendidik** | Akademik | No, Nama, Jabatan, Jenis Pelatihan, Tahun, **Tingkat**, **Sertifikat** | `pelatihan_pendidik` |

- **Tingkat** memakai pilihan tetap `TINGKAT_LIST` = **Kabupaten / Provinsi / Nasional**
  (`prestasi_tingkat_sah()` mengosongkan nilai di luar daftar).
- **Sertifikat**: berkas **PDF atau gambar** (maks 15 MB) diunggah lewat media proxy lewat
  **`unggah_berkas()`** (fungsi baru) yang **memeriksa isi berkas** — PDF wajib diawali `%PDF`,
  gambar diperiksa `getimagesize()`, SVG wajib memuat `<svg` — sehingga berkas yang hanya diganti
  namanya ditolak. Pada website tampil sebagai tombol **“Lihat”** (`a.btn-lihat`) yang membuka
  berkasnya di tab baru; pada panel admin ada tombol mata yang sama.
- **Halaman publik** dirender `halaman.php` (jenis menu di atas) dengan **penyaring Tahun**
  (`?tahun=YYYY`, hanya tahun yang benar-benar ada datanya, tahun asing diabaikan), penomoran **No**
  berurutan, dan keadaan kosong yang ramah. Nama/huruf awal tampil sebagai foto cadangan bila tidak
  ada foto.
- **Halaman admin `admin/prestasi.php`** — **4 tab kategori** (dengan lencana jumlah), CRUD
  (tambah/ubah/urutkan/aktif-nonaktif/hapus), pemilih **siswa** (kategori siswa) atau **guru**
  (kategori pendidik & pelatihan) yang **mengisi nama otomatis** (JavaScript), unggah/ganti/hapus
  sertifikat + tombol **Lihat berkas**, dan tautan “lihat di website” yang dicari dari **slug menu
  yang sebenarnya** (`menu_slug_jenis()`), bukan dugaan slug.
- **Penyemaian menu** `db_menu_prestasi()` dengan penanda **`menu_prestasi_v1`**, bersifat **upsert**:
  menu dicari lewat beberapa **calon slug** lalu lewat **LABEL** (tanpa peduli besar-kecil huruf),
  sehingga menu yang sudah dibuat/dinamai pemilik sendiri **tidak digandakan** — jenisnya saja yang
  ditingkatkan (judul/subjudul pemilik tidak ditimpa). Menu baru ditempatkan pada induk
  **Akademik** / **Kesiswaan** di urutan terakhir. Hasil pada data pemilik: menu “Prestasi Siswa”
  yang sudah ada (slug `prestasi`) otomatis ditingkatkan, tidak muncul menu ganda.
- Skema v5: tabel `prestasi` + 2 indeks, didaftarkan di `SCHEMA_TABEL_WAJIB` &
  `SCHEMA_KOLOM_WAJIB`; `admin_helpers` memasukkan `prestasi` ke whitelist (dipakai geser urutan).
- Gaya: `.tab-prestasi/.tab-item/.tab-jumlah`, `.pratinjau-berkas`, `a.btn-lihat` di `assets/style.css`.
- ⚠️ **Id medan** pada formulir prestasi dibedakan antar cabang (`#jenisPelatihan` vs `#jenisPrestasi`)
  agar tidak ada id kembar statis; uji memakai pemilih berbasis **`input[name=jenis]`**.

### Uji
- `websk_admin_ui.js` → **340 pemeriksaan** (4 tab; CRUD keempat kategori; unggah **PDF nyata** dan
  memastikan URL `.pdf` tersimpan; kolom & urutan halaman publik diperiksa persis per kategori;
  nama tampil; tombol “Lihat” mengarah ke berkas; berkas `.txt` ditolak; sembunyikan/tampilkan data
  berpengaruh ke website; penyaring tahun; jenis menu baru tersedia di Menu & Halaman).
- `websk_ukur.js` → pengukuran keempat halaman (jumlah kolom 7/4/7/7, tabel tidak melebihi artikel,
  tombol “Lihat” ≥26px, tanpa luapan 1440px & 390px) setelah membuat satu data per kategori.
- `websk_cek_live.sh` → **36 pemeriksaan migrasi pada SALINAN data asli** (penanda naik ke 5, tabel
  `prestasi` + kolomnya, keempat menu berjenis benar dan **tidak digandakan**, menu berada di bawah
  induk yang tepat dengan membandingkan `induk_id`, sidik baris seluruh tabel identik, idempoten,
  integritas & FK bersih).


## v1.5.1 — PERBAIKAN TABEL PRESTASI (laporan pemilik: “tahun, tingkat, sertifikat”)

Gejala yang dilaporkan (dengan tangkapan layar halaman **Pelatihan Pendidik**): kolom **Tahun** dan
**Tingkat** tampak kosong, dan tombol **“Lihat”** sertifikat muncul di bawah kolom **Tahun**.

Penyebabnya BUKAN data: pada basis data pemilik baris itu lengkap
(`tahun='2026'`, `tingkat='Nasional'`, `sertifikat` ada). Masalahnya di `halaman.php`: **judul kolom
dan isi sel ditulis terpisah**, dan untuk kategori **pelatihan** isi selnya hanya memuat 5 sel
(No, Nama, Jabatan, Jenis, Sertifikat) padahal judulnya 7 kolom (… Tahun, Tingkat, Sertifikat) —
dua sel Tahun & Tingkat hilang sehingga seluruh sel bergeser satu-dua kolom.

Perbaikan (bukan tambal per kategori):
- Helper baru **`prestasi_kolom($kategori)`** (`inc/helpers.php`) mengembalikan daftar kolom
  (judul, lebar, dan **jenis isi**: `no`/`foto`/`nama_keterangan`/`jabatan`/`jenis`/`tahun`/`tingkat`/`sertifikat`).
  **Judul dan isi sel dibangkitkan dari daftar YANG SAMA**, jadi jumlah sel tidak mungkin lagi berbeda
  dengan jumlah judul; menambah kolom cukup di satu tempat.
- `halaman.php` merender `<thead>` dan `<tbody>` dengan `foreach ($kolomPrestasi …)` + `switch` jenis isi.
- Ditambah **`prestasi_judul_kolom()`** untuk keterangan/uji.
- Hasil pada data pemilik (diperiksa langsung pada SALINAN yang berisi data asli):
  **Pelatihan Pendidik** → `No=1 · Nama=Supianto, S.Pd.I · Jabatan=Kepala Madrasah · Jenis Pelatihan=Diklat ·
  Tahun=2026 · Tingkat=Nasional · Sertifikat=Lihat` (7 sel = 7 judul, kolom Sertifikat sejajar);
  **Prestasi Siswa** (6 baris) dan **Prestasi Madrasah** (1 baris) juga 7 dan 4 sel sesuai judulnya.
  Kategori **Prestasi Pendidik** memang belum berisi data (halaman menampilkan keterangan kosong).

### Uji yang ditambahkan agar kelas bug ini tidak terulang
- `websk_admin_ui.js` → **359 pemeriksaan**: untuk SETIAP kategori, **jumlah sel baris pertama harus
  sama dengan jumlah judul kolom**, nilai kolom **Tahun** harus `2026` dan **Tingkat** harus sesuai
  pilihan, kolom **Sertifikat** harus berisi tombol “Lihat”; pemeriksaan serupa juga dilakukan pada
  tabel panel admin.
- `websk_ukur.js` → `selBaris === kolom` dan **posisi kolom “Sertifikat” sejajar** (kiri & lebar sama)
  dengan selnya, untuk keempat kategori.


## v1.6.0 — DATA ALUMNI DAPAT DIISI MANUAL / IMPOR / DIAMBIL DARI WEB (skema v6)

Kebutuhan pemilik: *“Tambahkan data inputan untuk data alumni. Ini untuk mengisi alumni lama yang belum
terinput di website. Selebihnya nanti akan diambil dari data kelulusan. Data alumni bisa diambil dari
alamat https://ma.stm.sch.id/alumni-directory.”*

### Dua sumber data alumni
Halaman publik **Data Alumni** (menu Kesiswaan) kini menggabungkan:
1. **data kelulusan** — siswa berstatus `lulus` (otomatis; bertambah sendiri dari proses
   Kenaikan & Kelulusan), dan
2. **tabel `alumni`** (skema v6) — alumni LAMA yang belum pernah masuk sebagai siswa.

`alumni_gabungan()` menyatukan keduanya di PHP (bukan SQL UNION) supaya pembersihan data ganda mudah:
kunci pembanding = **nama (dinormalkan) + tahun keluar**; bila sama, hanya satu yang dipakai dan
**yang memiliki foto diutamakan** (data kelulusan didahulukan bila keduanya berfoto). Penyaring tahun
memakai `alumni_tahun_gabungan()`, dan daftar diurut tahun keluar terbaru lalu nama.

### Halaman admin `admin/alumni.php` (menu **Data Alumni**)
- Empat kartu ringkasan: **Data Kelulusan**, **Diisi Manual/Impor**, **Tampil di Website** (gabungan),
  dan **Tahun Keluar**.
- **CRUD satu baris**: Nama, NIS, NISN, Jenis Kelamin, Tahun Masuk, Tahun Keluar, Keterangan (kondisi
  sekarang: kuliah/bekerja), foto (opsional, lewat media proxy), aktif/nonaktif, ▲▼ urutan, hapus,
  saringan (cari/tahun/sumber), paginasi 25, dan **Hapus Semua** (wajib ketik HAPUS — hanya tabel ini,
  data kelulusan tidak tersentuh).
- **Impor Excel/CSV** (berkas dibaca di peramban lewat SheetJS, atau tempel CSV) memakai
  `tebak_kolom()` dengan peta **`ALUMNI_KOLOM_PETA`**.
- **Ambil dari Alamat Web** — server mengambil daftar alumni langsung dari halaman direktori
  (alamat bawaan `ALUMNI_WEB_URL_BAWAAN`), menampilkan **pratinjau** (40 baris pertama + jumlah total),
  lalu tombol **Impor Sekarang**. Dua cara pembacaan (`alumni_ambil_web()`):
  1. **pola halaman direktori** (dipakai madrasah pemilik): HTML memuat `total_pages` dan `_BASE_URL`,
     baris diambil dari endpoint `public/alumni_directory/more_alumni` (`POST page_number`);
  2. **cadangan umum**: tabel `<table>` pada halaman itu dibaca langsung
     (`alumni_parse_tabel_html()`, mendukung foto dari `<img src>`).
- 🚨 **JEBAKAN yang ketemu saat mengerjakan ini**: endpoint sumber mengembalikan **SELURUH daftar pada
  halaman pertama (66 baris)**, lalu halaman 2–4 hanya **MENGULANG** sebagian baris yang sama
  (20+20+6). Tanpa penjagaan, 112 baris terbaca dan 46 di antaranya “diperbarui” sia-sia. Karena itu
  baris yang sudah pernah dibaca **dilewati** (`alumni_kunci()`) dan pengambilan **dihentikan** begitu
  satu halaman tidak menambah baris baru. Hasil pada data pemilik: **66 alumni unik**,
  pesan “Impor selesai: 66 alumni baru, 0 diperbarui”.
- `alumni_simpan_web()` dipakai bersama oleh **impor file** dan **impor web**, berjalan dalam satu
  transaksi `BEGIN IMMEDIATE`, **tidak menimpa** keterangan/foto yang sudah diisi admin dengan nilai
  kosong, dan menandai baris hasil ambil-web dengan `sumber = 'web'` (manual = `'manual'`).
- Foto alumni dari sumber web disimpan sebagai **URL langsung** ke alamat sumber (tidak diunggah ulang);
  foto tetap boleh diganti lewat form (media proxy).
- Bila nama + tahun keluar sama dengan data kelulusan, data tersebut tidak perlu dimasukkan lagi —
  halaman publik menampilkannya satu kali saja.

### Uji
- `websk_admin_ui.js` → **384 pemeriksaan**: form Tambah Alumni, bagian impor, alamat web bawaan,
  alumni manual tersimpan + keterangan tampil di website (disaring tahun), **ambil dari web → pratinjau
  → impor** dengan jumlah tersimpan = jumlah pratinjau, jumlah di website bertambah, **tidak ada alumni
  ganda**, tahun keluar alumni manual ikut pada penyaring, alumni nonaktif hilang dari website,
  dan validasi server “Nama alumni wajib diisi”.
- `websk_ukur.js` → setelah impor web: 6 kolom = 6 sel, foto eksternal **benar-benar termuat**
  (18/18 pada uji), tabel tidak melebihi area artikel di desktop, dan di HP tabel tetap **bisa digeser**
  tanpa luapan halaman.
- `websk_cek_live.sh` → **38 pemeriksaan** pada salinan data asli (penanda skema ke 6, tabel `alumni`
  + kolomnya, sidik baris seluruh tabel identik, idempoten, integritas & FK bersih). Isi tabel alumni
  **data-driven** karena pemilik dapat mengisi/mengimpor kapan saja.


## v1.7.0 — FOTO SISWA MASSAL + PROGRAM DIGITALISASI MADRASAH (skema v7)

### A. Foto Siswa Massal (`admin/siswa_foto.php`, menu **Foto Siswa (Massal)**)
Untuk mengisi **49 foto siswa sekaligus** tanpa satu per satu. Dua cara:

1. **Unggah dari perangkat** — satu input berkas `multiple`; **nama berkas** dipakai mencocokkan siswa
   (`Ahmad Fais Sirojudin.jpg` → nama, `260205.jpg` → NIS). Nama berkas dibersihkan dulu
   (`nama_dari_berkas()`: buang `foto/photo/img/scan/new/copy` & angka tahun).
2. **Ambil dari alamat web** — server mengambil pasangan (nama + foto) dari halaman direktori lalu
   **mencocokkan otomatis berdasarkan NAMA**. Hasil pencocokan ditampilkan **sebelum disimpan**
   (pratinjau: foto, nama siswa, kelas, nama pada sumber) + daftar “tidak cocok” & “dilewati”.

- **Urutan pencocokan** (`foto_cocokkan()`): **NIS/NISN** → nama persis (`nama_normal()`) → nama tanpa
  spasi (`nama_normal_rapat()`) → salah satu nama memuat yang lain (min 6 huruf). Satu siswa hanya
  menerima **satu** foto per proses, dan opsi **“hanya isi siswa yang belum punya foto”**
  (bawaan: dicentang) melindungi foto lama.
- **Pengambil data web** (`foto_ambil_web()`) mendukung tiga pola:
  1. **direktori dengan filter tahun pelajaran & kelas** (pola `/student-directory` madrasah pemilik):
     opsi dibaca dari `<select id="academic_year_id">` & `<select id="class_group_id">` pada halaman,
     lalu data diambil dari endpoint `public/student_directory/search_students` untuk setiap kombinasi
     (dibatasi **30 permintaan**) — pada data pemilik menghasilkan **174 nama+foto**;
  2. **direktori alumni** (`total_pages` + `more_alumni`) — memakai `alumni_ambil_web()`;
  3. **cadangan umum**: tabel pada halaman dibaca (`alumni_parse_tabel_html()`).
- Laporan hasil (jumlah tersimpan, dilewati, tidak cocok) disimpan di sesi lalu ditampilkan sekali.
- Foto dari web disimpan sebagai **URL langsung** ke sumbernya; foto hasil unggahan lewat media proxy.
- Pintasan: tombol **Foto Massal** pada kepala kartu Data Siswa + menu sidebar.

### B. Program Unggulan → **Program Digitalisasi Madrasah** (skema v7)
Bagian program pada beranda kini menampilkan **daftar APLIKASI** (bukan program unggulan):
**e-GURU, Supervisi Akademik, CBT, e-PKKM, e-BOS, e-Keuangan, e-PERPUS, Smart Teacher, Portal Digital**
beserta tautan masing-masing. Satu sumber data: konstanta **`PROGRAM_DIGITAL_BAWAAN`** (config)
dipakai oleh **penyemaian instalasi baru** (`db_pdo_program()`) **dan** migrasi basis data lama
(`db_program_digital()`, penanda **`program_digital_v1`**).
- Migrasi **aman terhadap suntingan pemilik**: judul bagian hanya diganti bila masih `'Program Unggulan'`,
  dan daftar bawaan hanya diganti bila isinya masih persis empat program bawaan; bila sudah disunting,
  aplikasi yang belum ada hanya **ditambahkan** di akhir (tidak ada data yang hilang).
- Beranda: kueri program **tidak lagi dibatasi `LIMIT 4`** (dulu “maksimal 4 program teratas”) agar
  seluruh aplikasi tampil pada slider.
- 🚨 **Palet warna kartu program diperluas dari 6 → 9 warna** (`nth-child(9n+N)`) karena jumlah kartu
  kini 9; dengan siklus 6 warna akan ada kartu berwarna sama. Uji menuntut jumlah warna berbeda
  ≥ min(jumlah kartu, 9).
- Label panel admin ikut disesuaikan (`KEU_TABEL`, judul kartu, keterangan) dan default medan
  “Judul bagian Program” pada Identitas Sekolah.

### Uji
- `websk_admin_ui.js` → **416 pemeriksaan** (foto: 2 foto tersimpan dari 3 berkas — satu lewat NAMA,
  satu lewat NIS, satu dilaporkan tidak cocok; foto tampil di Data Siswa; ambil dari web → pratinjau →
  simpan dengan **nama siswa disamakan lebih dulu ke nama nyata dari sumber** supaya pengujian
  pencocokan tetap pasti; foto pratinjau benar-benar termuat; berkas tak cocok tidak mengubah data).
  Program: judul beranda, minimal 9 aplikasi beserta tautan & ikonnya, dan label panel admin.
- `websk_ukur.js` → bagian aplikasi terukur: 9 kartu, **9 warna berbeda**, lebar kartu 280–460px,
  slider bisa digeser, tanpa luapan di 1440px & 390px.
- `websk_cek_live.sh` → 38 pemeriksaan migrasi pada salinan data asli (penanda skema ke 7).


## v1.7.1 — FOTO SLIDER BERANDA DIBUAT JELAS (laporan pemilik)

Laporan: *“Bagian yang saya kasih tanda merah agar foto/gambar lebih jelas”* (area foto pada slider
besar beranda). Penyebabnya dua hal yang saling menumpuk:
`.hero-slide-gambar{opacity:.55}` (foto tampil pudar) **dan** `.hero-lapisan` yang gelap merata
(alpa .94 → .25 dari kiri ke kanan) sehingga bagian foto yang ditandai pun ikut gelap.

Perbaikannya:
1. **Opacity foto .55 → .96** — foto hampir apa adanya.
2. **`.hero-lapisan` dibuat BERGANTUNG SISI**:
   `linear-gradient(90deg, .95 0%, .80 34%, .45 48%, .04 60%, .02 100%)` — tetap gelap di kiri
   (tempat tulisan) tetapi **hampir transparan mulai ~60%** sehingga foto terlihat jelas.
3. **Bayangan tulisan dua lapis** (`0 1px 3px` + `0 3px 16px`) supaya tulisan tetap terbaca walau
   di belakangnya ada foto yang sangat terang; hiasan latar `.hero-lacak::before` juga ditipiskan
   (kabut hijau `.5 → .28`) agar tidak lagi menghijaukan foto.
4. **≤760px lapisan dibuat MERATA (vertikal)** karena tulisan memakai lebar penuh di HP —
   `.hero-lapisan{linear-gradient(180deg,.86,.64,.88)}` + foto `opacity:.95`. Di HP **keterbacaan
   diutamakan** (tulisan memakai lebar penuh), jadi lapisan lebih tebal: pada data pemilik
   kontras tulisan 3,19 dengan kecerahan foto 0,40 (foto tetap terlihat).

### Pengukuran nyata (bukan perkiraan)
Alat baru **`websk_ukur_hero.py`** (Python + PIL) membandingkan **potongan gambar yang benar-benar
tampil** (`object-fit: cover` dihitung ulang) dengan area hero pada tangkapan layar, lalu menghitung:
`rasio` kecerahan (foto vs aslinya), `kontras_hero`, dan **`kontras_teks`** (piksel tulisan terang vs
latar di area tulisan). Hasil pada data pemilik:

| Keadaan | Sebelum | Sesudah |
|---|---|---|
| Kecerahan foto (rasio thd aslinya) | **0,43** | **0,94** |
| Kontras rincian foto | 0,067 | 0,221 (asli 0,254) |
| Kontras tulisan | — | 3,96 |

Kasus **terburuk** (foto sengaja sangat terang) juga diuji: kontras tulisan **3,29** dengan kecerahan
foto **0,94** — jadi foto jelas TANPA mengorbankan keterbacaan tulisan. Pada data pemilik di HP:
kecerahan foto 0,40 dengan kontras tulisan 3,19.

### Uji otomatis yang ditambahkan
`websk_ukur.js` kini memasang **gambar contoh berstruktur** (`websk_ukur_gambar.py`) pada **slide
pertama** (lewat halaman edit slide — bukan menambah slide baru di akhir), lalu mengukur di 1440px &
390px dan memastikan: `opacity ≥ 0,9`, **rasio ≥ 0,85 (desktop) / ≥ 0,45 (HP)**,
**kontras_teks ≥ 3,0**, bagian teks tetap gelap (`cerah_kiri ≤ 0,45`), dan tanpa luapan mendatar.
Batas kecerahan foto di HP adalah **≥ 0,35** (bukan 0,85 seperti desktop) karena lapisan gelap di
layar sempit memang harus lebih kuat demi keterbacaan tulisan — angka ini disengaja, bukan kelonggaran.
⚠️ Formulir slider memakai `required` pada judul sehingga mengirim hanya berkas gambar **tidak
menghasilkan POST sama sekali** (validasi HTML5 menahan) — uji harus mengisi judul lebih dulu.
`websk_cek_live.sh` → **39 pemeriksaan** (penanda skema ke 7, penanda `program_digital_v1`, dan
judul bagian beranda = Program Digitalisasi Madrasah).


## v1.7.23 — TAHUN pada LAYANAN PIP + EDITOR BOLEH GANTI KATA SANDI KARTU (skema v18)

Permintaan pemilik: *"Pastikan Akun Editor (Konten, PPDB & Layanan PIP) bisa mengganti Kata Sandi
Kartu PIP. Yang saya tandai warna merah pada gambar tambahkan tahun (karena setiap tahun SK dan
penerima PIP tidak sama). Dan untuk panel pengisian layanan PIPnya disesuaikan kembali."*

### A. Editor boleh mengganti kata sandi Kartu PIP
`boleh_sandi_pip()` kini **sama dengan `boleh_kelola_pip()`** (Administrator ATAU peran `editor`).
Aturan v1.7.22 (hanya Administrator) **dibatalkan permintaan ini**; kartu "Kata Sandi Kartu PIP"
kembali menampilkan formulir untuk kedua peran, dan blok 403 + keterangan "hanya Administrator"
dihapus. Fungsi `boleh_sandi_pip()` tetap ada sebagai **satu titik kebijakan** bila pemilik ingin
membatasinya lagi.

### B. Tahun Layanan PIP (skema v18)
* **Tabel baru `pip_tahun`** (`tahun`, `sk`, `sk_nama`, `catatan`, `urutan`, `aktif`, `dibuat`,
  `diubah`) + **kolom `pip_penerima.tahun_id`** + indeks `idx_pip_tahun_urutan`.
* **Yang menempel pada tahun**: **SK Penerima PIP**, **catatan (kotak "Info Penting")**, dan
  **daftar penerima**. **Yang tetap GLOBAL**: judul/teks informasi program, judul bagian daftar, dan
  **kata sandi Kartu PIP** (berlaku semua tahun).
* **Migrasi `db_pip_tahun()`** (idempoten): membuat tahun pertama (label = tahun berjalan) dan
  **memindahkan seluruh SK, catatan, serta penerima yang sudah ada** ke tahun itu — tidak ada data
  yang hilang. Tahun aktif disimpan pada pengaturan `pip_tahun_aktif`.
  ⚠️ **Penolong migrasi memakai `db_migrasi_nilai()` (LOKAL di `inc/db.php`)** — jangan memakai
  `db_val()` (itu milik aplikasi lain di workspace ini; pernah fatal "Call to undefined function").
* **Halaman publik**: **pemilih tahun** `#pipTahun` (baris tersendiri di ATAS kartu informasi — di
  area yang ditandai pemilik) berisi chip tiap tahun (`?tahun=ID`) + keterangan "Menampilkan data
  tahun …". Judul daftar memuat tahun (`.pip-judul-tahun`), dan keadaan kosong menjelaskan bahwa
  tahun lain mungkin sudah berisi. `?tahun=` asing/tahun nonaktif → jatuh ke **tahun utama**;
  tanpa parameter → tahun utama (`pip_tahun_aktif`).
* **Panel**: kartu **"Tahun Layanan PIP"** (`#bagianTahun`, paling atas) = pemilih tahun
  (`#pilihTahunPip`, `data-auto`), tabel `#tblPipTahun` (penerima & status SK tiap tahun, tombol
  Buka/Ubah/Jadikan tahun utama/Hapus), formulir tambah–ubah tahun (`#tahun`, `#urutan_tahun`).
  Kartu **Informasi** menyimpan judul/teks GLOBAL + **SK & catatan tahun yang sedang dibuka**.
  Kartu **Daftar Penerima** hanya menampilkan tahun itu; penerima baru masuk ke tahun tersebut.
  Seluruh tautan/aksi panel membawa `?tahun=` (penolong `pip_admin_url()`/`pip_admin_tahun_id()`),
  dan menyunting penerima **tidak pernah** memindahkan tahunnya (`tahun_id` diambil dari baris lama).
* **Pengaman penghapusan tahun**: tahun yang **masih berisi penerima** hanya dapat dihapus lewat
  **kartu konfirmasi ketik `HAPUS`** (`#hapusTahun`, penolong `form_konfirmasi_ketik`) dan server
  menolak penghapusan tanpa medan `konfirmasi=HAPUS`; tahun kosong tetap boleh dihapus satu klik.
  Tahun terakhir tidak dapat dihapus, dan penghapusan tahun memindahkan tahun utama secara otomatis.

### 🚨 Jebakan yang menggigit di sini
1. **`pengaturan($kunci, $bawaan)` mensyaratkan `$bawaan` bertipe string** — menulis
   `pengaturan('pip_tahun_aktif', 0)` membuat halaman **fatal** ("must be of type string, int
   given"). Pakai `'0'` lalu `(int)`.
2. **`db_val()` tidak ada di aplikasi ini** (lihat catatan penolong migrasi di atas).
3. **Menyalin penolong dari aplikasi lain di workspace** (e-BOS/e-GURU) sering tidak cocok —
   periksa dulu dengan `grep -n "^function nama" inc/helpers.php inc/db.php`.

### Uji
* `bash websk_uji_pip.sh` → **187 lulus** — termasuk bagian **12** khusus tahun: tabel & kolom baru,
  penerima lama terpindah, tahun tambah/kembar/kosong, SK & catatan per tahun **tidak** saling
  menimpa, penerima masuk tahun yang dibuka, pemisahan data di halaman publik, tahun asing jatuh ke
  tahun utama, jadikan tahun utama, ubah nama tahun, hapus tahun (+ pengaman HAPUS), tahun terakhir
  tidak dapat dihapus; bagian **11** kini memastikan **editor boleh mengganti & menghapus kata sandi**.
* `bash websk_uji_pip_ui.sh` (+ `websk_uji_pip_ui.js`, **79 lulus**) — menambah tahun 2028 lewat
  panel, mengunggah SK untuk tahun itu, menyimpan penerima ke tahun tersebut, menandai **tahun
  utama**, memeriksa pemilih tahun di halaman publik (chip aktif, `?tahun=`, keterangan tahun),
  berpindah tahun lewat chip (data & SK & kotak Info Penting **tidak tercampur**), lalu mengunduh
  Kartu PIP ber-sandi.
* `websk_admin_ui.js` (**485**) menyesuaikan: peran editor kini melihat formulir sandi + kartu tahun.
* Uji lain tidak berubah: profil 89, galeri 89, ukuran OK, BOS 72+36, Sarpras 58+40,
  `websk_cek_live.sh` 110/17 (sama seperti sebelumnya).

### ⚠️ Keadaan data live saat pembaruan ini
Pemilik sudah memakai fitur tahun di live: **dua tahun** (2025 & 2026) masing-masing dengan SK-nya
sendiri, tahun utama = **2026**. Namun tabel `pip_penerima` pada basis data live **kosong** saat
diperiksa (pernah berisi sampai 7 baris menurut `sqlite_sequence`) — penghapusan hanya mungkin lewat
aksi eksplisit (tombol hapus penerima, atau hapus tahun). Migrasi v17→v18 **tidak** menghapus
penerima (diuji pada salinan: seluruh baris tetap dan terpindah ke tahun pertama), dan sejak
pembaruan ini penghapusan tahun yang masih berisi data wajib konfirmasi ketik HAPUS.

## v1.7.22 — PERAN "EDITOR" BOLEH MENGISI LAYANAN PIP

Permintaan pemilik: *"Peran editor (konten & PPDB) buat juga bisa mengakses layanan PIP untuk bisa
mengisi"*.

### Yang berubah
* Penjaga `admin/pip.php` diganti dari `require_admin()` menjadi **`require_kelola_pip()`** —
  boleh dibuka **Administrator ATAU peran `editor`** (labelnya kini
  **"Editor (konten, PPDB & Layanan PIP)"**). Editor dapat **mengisi SELURUH isi halaman**:
  teks informasi (penyunting), berkas **SK Penerima PIP**, catatan (kotak "Info Penting"),
  daftar penerima (tambah/ubah/▲▼/aktif-nonaktif/hapus), serta unggah **Kartu PIP** & **Surat Kuasa**.
  Menu *Layanan PIP* sudah tampil pada sidebar peran `editor` (penuh sejak awal), jadi tidak ada
  perubahan menu yang diperlukan.
* 🚨 **KATA SANDI KARTU PIP TETAP WEWENANG ADMINISTRATOR** (`boleh_sandi_pip()` = `is_admin()`).
  Dasarnya permintaan pemilik sebelumnya: *"Kartu PIP ketika akan di download minta password
  (Password ditentukan oleh Admin)"*. Karena itu, untuk peran Editor:
  * kartu **"Kata Sandi Kartu PIP"** tidak mencetak formulirnya, melainkan satu keterangan yang
    menjelaskan bahwa sandi ditetapkan Administrator dan isi halaman lain tetap dapat diisi;
  * aksi POST `simpan_sandi` dijawab **403 yang diperiksa SEBELUM CSRF** (peran Editor tidak pernah
    melihat formulirnya, jadi jawabannya harus 403 — bukan 419; pola yang sama dengan halaman
    aktivasi di aplikasi lain).
  **Tanyakan pemilik** bila ingin kata sandi juga boleh diubah oleh Editor.
* Peran **"Editor Berita"** (`berita`) **SENGAJA TIDAK diberi akses PIP** — dihentikan lebih dulu
  oleh `boleh_buka_berkas()` di `require_login()` sehingga jawabannya 403 dengan pesan bahwa akun itu
  khusus berita. Bila pemilik ingin akun itu juga boleh mengisi PIP, tambahkan
  `'pip.php'` pada `berita_berkas_boleh()` **dan** longgarkan `boleh_kelola_pip()` — tanyakan dulu.
* Teks penjelasan peran pada `admin/pengguna.php` (kepala kartu "Daftar Akun Panel" + bantuan pada
  pemilih Peran) ikut menyebut Layanan PIP agar tidak menyesatkan.

### Uji
* Bagian **11** `bash websk_uji_pip.sh` (kini **143 lulus**): akun peran `editor` & `berita` dibuat
  lewat panel, lalu dipastikan — editor **200** pada `admin/pip.php`, melihat daftar & formulir
  penerima, **tidak** melihat formulir/isian sandi, mendapat keterangan soal sandi, **berhasil
  menyimpan** judul informasi + catatan + penerima baru (langsung tampil di halaman publik),
  **403** saat menetapkan sandi (hash tidak berubah), tetap **403** pada halaman Pengguna/Identitas/
  Log/FAQ; sedangkan peran `berita` tetap **403** dan tidak dapat menambah data; Administrator
  tetap dapat menetapkan sandi.
* `websk_admin_ui.js` (kini **485 lulus**) menambah pemeriksaan peran Editor di dalam peramban:
  halaman PIP 200, daftar & formulir penerima terlihat, formulir sandi **tidak** ada, ada keterangan
  "ditetapkan oleh …", menu PIP tampil di sidebar, dan editor benar-benar dapat menyimpan perubahan.
* Uji lain tidak berubah: `websk_uji_pip_ui.sh` (55), `websk_profil_ui.js` (89),
  `websk_galeri_ui.js` (89), `websk_ukur.js` (SEMUA UKURAN OK), `websk_uji_bos.sh` (72),
  `websk_uji_sarpras.sh` (58) + UI (40), dan `websk_cek_live.sh` (110/17 — sama seperti sebelumnya).
* **Akun live**: basis data pemilik sudah punya akun peran `editor` bernama **`yelfis`**
  (`Yelfis Djamaludin, S.Pd.I`) dan peran `berita` bernama **`ayulinda`** — jadi `yelfis` langsung
  dapat mengisi Layanan PIP setelah pembaruan ini, tanpa perlu membuat akun baru.

## v1.7.21 / v1.7.20 — MENU "LAYANAN PIP" + KARTU PIP BER-SANDI (skema v17)

Permintaan pemilik: *"Pada bagian informasi layanan PIP di isi dengan daftar berikut. Pengisiannya
lewat akun admin (Buatkan Menu PIP): Informasi Seputar Program Indonesia Pintar (tambahkan SK
Penerima PIP yang diupload oleh admin); Daftar Penerima PIP MA. Sirojuth Tholibiin Mootilango
(Tabel: No, Nama, NISN, Kelas, Nominal, Kartu PIP (di upload admin), Keterangan (Keterangan isi
dengan surat kuasa yang diupload admin). Kartu PIP Ketika akan di download minta password (Password
ditentukan oleh Admin)."*

### A. Menu & data
* **Menu "Layanan PIP" DIBUAT otomatis** (slug `pip`, jenis halaman baru `pip`) oleh
  `db_menu_pip()`: diletakkan sebagai sub menu **"Informasi"** bila menu itu ada (keadaan basis data
  pemilik), cadangan **"Kesiswaan"**, lalu menu utama bila keduanya tidak ada. Pembuatannya dijaga
  penanda **`pip_menu_v1`** sehingga tidak pernah dibuat dua kali dan **perubahan nama/urutan/
  penghapusan oleh admin tidak dibatalkan** migrasi berikutnya (ada ujinya).
* **Tabel baru `pip_penerima`**: `nama`, `nisn`, `kelas`, `nominal` (INTEGER rupiah), `kartu` +
  `kartu_nama`, `surat` + `surat_nama` (URL media proxy), `keterangan`, `urutan`, `aktif`,
  `dibuat`, `diubah` + indeks `idx_pip_urutan`; didaftarkan pada `SCHEMA_TABEL_WAJIB` &
  `SCHEMA_KOLOM_WAJIB`.
* Teks "Informasi Seputar Program Indonesia Pintar" disemai **sekali** (`pip_info_seed`) dari
  konstanta `PIP_INFO_BAWAAN`; sesudah itu suntingan admin tidak pernah ditimpa. Kunci pengaturan
  lain: `pip_info`, `pip_judul_info`, `pip_judul_daftar`, `pip_sk`, `pip_sk_nama`, `pip_catatan`,
  `pip_sandi`.

### B. Panel admin `admin/pip.php` (menu sidebar "Layanan PIP")
Tiga kartu: (1) judul + teks penyunting bergaya Word + unggah/hapus **SK Penerima PIP** + catatan;
(2) **Kata Sandi Kartu PIP** (tetapkan/ganti/hapus); (3) **Daftar Penerima PIP** (tambah/ubah/
▲▼/aktif-nonaktif/hapus + cari) dengan unggah **Kartu PIP** & **Surat Kuasa** per penerima.

### C. Halaman publik
Bagian **Informasi Seputar PIP** (+ tombol **Lihat SK Penerima PIP**) dan bagian **Daftar Penerima
PIP <nama sekolah>** bertabel **No, Nama, NISN, Kelas, Nominal, Kartu PIP, Keterangan** + baris
JUMLAH (jumlah penerima & total nominal). Kolom Keterangan memuat tautan **Surat Kuasa** dan/atau
teks keterangan.
* Di bawah tabel, catatan yang diisi admin (`pip_catatan`) tampil sebagai **kotak "Info Penting"**
  (`#infoPentingPip`, kelas `.pip-info-penting`): judul **"Info Penting"** TEBAL + MERAH +
  **BERKEDIP** (`@keyframes pipKedip`, 1,1 detik, `steps(1,end)`) dan isi catatan agak tebal
  (`font-weight:600`). Kedipan dimatikan pada `prefers-reduced-motion: reduce` (tulisan tetap merah
  tebal), sesuai kebiasaan aplikasi ini.
* **DUA BARIS KETERANGAN DIHAPUS atas permintaan pemilik** (ditandai kotak merah pada tangkapan
  layar) — **jangan dikembalikan**:
  1. *"Surat Keputusan penerima PIP dapat dibuka pada tombol Lihat SK Penerima PIP di atas
     (berkas: …)."* (informasi berkas SK), dan
  2. *"Kartu PIP diunduh dengan kata sandi dari admin madrasah. Surat kuasa yang perlu dibawa saat
     pencairan tersedia pada kolom Keterangan."*
  Akibatnya **tidak ada lagi blok `.pip-note`** pada halaman publik Layanan PIP (sisanya hanya di
  halaman ber-sandi `pip_kartu.php`). Ada uji yang menolak keduanya muncul kembali.

### D. Kartu PIP ber-sandi — berkas DIALIRKAN server
* Tombol unduh menuju **`pip_kartu.php?id=…`** yang meminta kata sandi (batas
  **`PIP_MAKS_COBA`=5 percobaan / `PIP_MENIT_COBA`=10 menit per sesi**; pesan menyebut sisa
  percobaan; setelah habis, **sandi BENAR pun ditolak** sampai rentangnya lewat).
* Setelah sandi benar (`password_verify` atas hash bcrypt) → **302** ke `?unduh=1`, lalu berkas
  diambil server-side (`pip_kirim_berkas()`) dan **dikirim dengan `Content-Disposition: attachment`
  + `Cache-Control: private, no-store`**. **URL media (CDN) TIDAK pernah sampai ke peramban** —
  jadi sandi benar-benar melindungi berkas, bukan sekadar menyembunyikan tautan: membuka
  `?unduh=1` pada sesi baru (tanpa sandi) hanya menampilkan formulir sandi (ada ujinya).
* Sandi disimpan sebagai **hash** (`pengaturan` kunci `pip_sandi`) sehingga **tidak dapat ditampilkan
  kembali**; admin cukup menetapkan sandi baru bila lupa. Bila sandi belum ditetapkan, halaman
  publik menandai kartu "Belum tersedia" (tulisan netral — halaman ini dibaca orang tua) dan
  unduhan tidak mungkin.

### E. Perapian v1.7.21 (permintaan pemilik dari tangkapan layar)
Lihat butir "kotak Info Penting" & "DUA baris keterangan dihapus" pada bagian C di atas.

### 🚨 Jebakan yang ditemukan oleh uji pada fitur ini (jangan diulang)
1. **`id` penerima HILANG setelah percobaan sandi pertama.** Formulir mengirim ke `pip_kartu.php`
   **tanpa query**, sehingga medan tersembunyi yang diisi dari `$_GET['id']` menjadi **0** pada
   percobaan berikutnya — sandi benar pun mengarah ke kartu yang salah (404). Sekarang
   `pip_kartu_tampil()` **menerima `$idPenerima`** dan `pip_kartu.php` meneruskan id yang sudah
   diresolusi (`$_POST['id'] ?? $_GET['id']`).
2. **`id` kembar pada panel**: pembungkus kartu sempat memakai `id="sandi"`/`id="info"` yang SAMA
   dengan isian formulir (`#sandi`) → peramban/uji salah sasaran. Pembungkus kini
   **`#bagianSandi`** / **`#bagianInfo`** (isi konfirmasi tetap `#sandi`).
3. **Curl `-F` menafsirkan nilai berawalan `<` sebagai NAMA BERKAS** (sama seperti `@`). Untuk
   mengirim HTML lewat `curl` di skrip uji WAJIB memakai **`--form-string`**, kalau tidak seluruh
   kiriman gagal diam-diam.
4. **Medan yang tidak dikirim tidak diubah** (`array_key_exists`) — tanpa itu, kiriman sebagian
   (mis. hanya mengganti SK) mengosongkan judul/teks yang sudah diisi admin.

### Uji
- `bash websk_uji_pip.sh` → **113 lulus** (migrasi v17 & pembuatan menu + idempoten, unggah SK
  nyata ke media proxy, penolakan PDF palsu, penyimpanan penerima & nominal, sandi tersimpan sebagai
  hash & penolakan sandi pendek/ulangan beda, tabel publik, **kotak "Info Penting"** + pemastian dua
  baris keterangan yang DIHAPUS tidak muncul lagi, **unduhan ber-sandi lengkap** termasuk blokir
  5 percobaan & perbandingan byte berkas dengan media, CSRF 419, 404 untuk id asing/penerima
  nonaktif/tanpa kartu, hapus & urutkan penerima, hak akses, kerapian).
  ⚠️ Pengambilan berkas dari CDN **sesekali gagal sesaat** (server menjawab halaman dengan pesan
  "gagal diambil dari penyimpanan") — uji mengulang unduhan sampai 3 kali sebelum menilai gagal.
- `bash websk_uji_pip_ui.sh` (+ `websk_uji_pip_ui.js`, **55 lulus**): mengisi panel lewat antarmuka
  (unggah SK + Kartu + Surat Kuasa + catatan), menetapkan sandi, memeriksa tabel publik (7 kolom),
  **mengukur kotak "Info Penting"** (judul merah `rgb(179,38,30)`, font-weight 800, `animation-name:
  pipKedip`, isi font-weight 600, garis kotak merah) dan memastikan baris lama sudah tidak ada,
  **mengunduh Kartu PIP dengan sandi** dan memastikan isinya benar-benar PDF + tajuk `attachment`,
  memastikan sesi lain tanpa sandi tidak mendapat berkas, serta tampilan HP.
- Uji lama tetap lulus: `websk_admin_ui.js` (478), `websk_profil_ui.js` (89), `websk_galeri_ui.js`
  (89), `websk_ukur.js` (SEMUA UKURAN OK), `websk_uji_bos.sh` (72), `websk_uji_bos_ui.sh` (36),
  `websk_uji_sarpras.sh` (58), `websk_uji_sarpras_ui.sh` (40), dan `websk_cek_live.sh`
  (**110 lulus / 17 gagal — SAMA PERSIS dengan sebelum perubahan**; kegagalan itu sudah ada
  sebelumnya karena pemilik mengganti nama/slug menu dan mengisi data).

### 🚨 DATA LIVE SUDAH DIISI PEMILIK (jangan direset)
Pemilik **langsung mengisi** halaman ini di live tak lama setelah fiturnya aktif. Keadaan terakhir
yang terlihat: **2 penerima** — *Ilham Maulana* (NISN 1234567890, kelas X, Rp 1.800.000) dan
*Kaylanisa Azzahra* (NISN 0987654321, kelas XII, Rp 900.000) — keduanya **sudah punya berkas
Kartu PIP DAN Surat Kuasa**; `pip_sandi` **sudah ditetapkan** (tersimpan sebagai hash bcrypt,
sehingga tombol **Unduh** tampil di halaman publik); `pip_sk` = berkas
"Screenshot 2026-09-19 083558.png"; dan `pip_catatan` berisi imbauan kepada wali murid yang
**tampil sebagai kotak "Info Penting"** (berkedip).
⚠️ **JANGAN** menghapus/menimpa data itu (termasuk **jangan** mereset `pip_sandi`), dan **jangan
menguji pada folder `web-sekolah/`** — semua uji memakai salinan `/tmp`.


## v1.7.19 — MENU "INFORMASI → SARPRAS" DIISI DAFTAR INVENTARIS BARANG (skema v16)

Permintaan pemilik: *"Pada bagian Informasi Sarpras isi dengan tampilan yang di upload. Datanya
bersumber dari https://asisten.guru/tugas.php?kode=sarpras yang khusus dari MA. Sirojuth Tholibiin
Mootilango. Sehingga ketika data tersebut di update otomatis akan tampil juga di informasi sarpras
website madrasah."*

### A. Sumber data: e-GURU (asisten.guru) dibaca langsung, hanya-baca
* **`asisten.guru` ADALAH aplikasi `e-guru/` di workspace ini** (sudah dicocokkan: versi
  `assets/style.css?v=1.64.1` sama dengan `APP_VERSION` lokal, seluruh isi halaman depannya
  —`landing_kontak_email`, `landing_label`, `tentang_nama_1/2`, …— cocok dengan nilai pada
  `e-guru/data/eguru.sqlite`, dan favicon-nya menunjuk berkas media milik app `e-guru`).
  Karena itu mencukupi membaca **basis data e-GURU** — tidak perlu (dan tidak bisa) mengambil
  `tugas.php?kode=sarpras` lewat HTTP karena halaman itu **wajib login** (terbukti: dialihkan ke
  `login.php`) dan menyerahkan kredensial pemilik kepada agen bukan pilihan yang aman.
* **`inc/sarpras.php`** (modul baru) membuka basis data itu **HANYA-BACA** lewat
  `EGURU_DB_FILE` = `../e-guru/data/eguru.sqlite` (`mode=ro`, cadangan `query_only=1`);
  dapat diarahkan ulang lewat variabel lingkungan `WEBSK_EGURU_DB` (dipakai skrip uji) atau kunci
  pengaturan **`sarpras_db`**. **JANGAN** menulis apa pun ke basis data e-GURU, dan **JANGAN**
  `require` kode e-GURU (konstanta & fungsi bertabrakan: `e()`, `tanggal_id()`, `is_madrasah()`,
  `nama_sekolah()` ada di kedua aplikasi) — seluruh pembacaan ditulis ulang dengan awalan `sarpras_`.
* **Sekolah pemilik data dicocokkan dari NAMA** sekolah website ini dengan daftar sekolah pada
  e-GURU (`sarpras_sekolah()`; nama dinormalkan lewat `sarpras_nama_kunci()`), cadangan kedua
  konstanta `SARPRAS_SEKOLAH_ID`, cadangan ketiga sekolah yang paling banyak data barangnya.
  Jadi **hanya 68 baris milik MA. Sirojuth Tholibiin Mootilango** yang tampil — data 10 sekolah
  lain pada basis data e-GURU **tidak pernah** ikut (ada ujinya).
* Modul ini **hanya** membaca tabel `sarpras_barang` + nama sekolah. Basis data e-GURU memuat data
  seluruh sekolah pemakai beserta akun penggunanya — **jangan** menambah query yang lebih luas.

### B. Tampilan (sama seperti tangkapan layar pemilik)
Satu kartu berisi: kepala **"Daftar Inventaris Barang"** + keterangan (sarana & prasarana milik
<nama sekolah dari e-GURU> · Madrasah/Sekolah) + chip **"N jenis barang"** dan **"N unit"**, lalu
tabel **No, Nama Barang, Spesifikasi, Lokasi, Kondisi, Jumlah, Sumber Dana, Jenis, Keterangan**
(kepala hijau tua–emas, baris selang-seling), **pil kondisi** (Baik hijau, Rusak Ringan kuning,
Rusak Berat merah), **baris Jumlah** di kaki tabel (total unit + jumlah jenis barang), dan satu baris
ringkasan kondisi. Kolom yang kosong tetap ditulis `-` (sama seperti e-GURU).

### C. Migrasi v16 (`db_menu_sarpras()` di `inc/db.php`)
`UPDATE menu SET jenis = 'sarpras' WHERE slug = ? AND jenis = 'halaman'` untuk slug
`sarana-prasarana` **dan** `sarpras`. Hanya baris yang masih `halaman` yang disentuh.
Tanpa tabel/kolom baru → `SCHEMA_TABEL_WAJIB`/`SCHEMA_KOLOM_WAJIB` tidak berubah.

### 🚨 JEBAKAN BESAR yang ditemukan & diperbaiki di sini (berlaku juga untuk v15/BOS)
**Urutan di dalam `ensure_schema()` salah**: dahulu penyesuaian jenis menu (`db_menu_bos`,
`db_menu_sarpras`) dijalankan **SEBELUM `db_isi_awal()`**, padahal `db_isi_awal()` yang **membuat
baris menu bawaan** — sehingga pada **instalasi BARU** menu `sarana-prasarana` tetap berjenis
`halaman` (halaman kosong) walau penanda skema sudah versi terbaru. Sekarang urutannya:
`db_tabel` → `db_kolom_tambahan` → **`db_isi_awal`** → `db_menu_bos` → `db_menu_sarpras`
(keduanya idempoten). **Bila menambah penyesuaian jenis menu lagi, letakkan SESUDAH `db_isi_awal()`.**

### D. Gaya
Blok **"INFORMASI SARPRAS"** pada `assets/style.css` (kelas `.sar-*`: `.sar-blok`, `.sar-kepala`,
`.sar-chip`, `.sar-tabel`, `.sar-pil-*`, `.sar-ket`, `.sar-kosong`) — **jangan** menaruh `<style>`
di halaman. Tabel lebar bisa digeser sendiri di dalam `.sar-tabel-wrap` pada layar sempit.

### Uji
- `bash websk_uji_sarpras.sh` → **58 lulus**: migrasi v15→v16 (jenis menu jadi `sarpras`, idempoten,
  menu lain tidak tersentuh), sembilan kolom, baris = jumlah baris e-GURU, kaki tabel = total unit
  e-GURU, pil per baris, **isolasi** (barang sekolah lain disuntikkan ke salinan basis data e-GURU →
  tidak tampil), **kemampuan memutakhirkan diri** (tambah/ubah/hapus barang di salinan basis data
  e-GURU langsung tercermin), **sidik berkas e-GURU tidak berubah** (baca-saja), pilihan jenis pada
  panel (diperiksa juga dari halaman panel yang benar-benar dirender setelah login),
  halaman Informasi BOS tetap bekerja, dan keterangan ramah saat sumber data hilang.
- `bash websk_uji_sarpras_ui.sh` (Playwright, **40 lulus**): pilihan jenis "Informasi Sarpras" pada
  panel + terpilih pada formulir ubah menu, sembilan kolom, jumlah baris, pil kondisi berwarna,
  kepala tabel ber-gradasi hijau, baris Jumlah, chip ringkasan, **perbandingan baris demi baris
  dengan basis data e-GURU** (nama/spesifikasi/lokasi/kondisi/jumlah/sumber dana), tabel tidak
  melampaui wadahnya pada 1440px, tampilan HP 390px tanpa luapan, dan tanpa galat konsol.
- Uji lama tetap lulus: `websk_admin_ui.js` (**478**), `websk_profil_ui.js` (89),
  `websk_galeri_ui.js` (89), `websk_ukur.js` (SEMUA UKURAN OK), `websk_uji_bos.sh` (72),
  `websk_uji_bos_ui.sh` (36), dan `websk_cek_live.sh` (**110 lulus / 17 gagal — SAMA PERSIS
  sebelum & sesudah perubahan**; kegagalan itu sudah ada sebelumnya karena pemilik mengganti
  nama/slug menu dan mengisi data).
- Terverifikasi LIVE: `…/web-sekolah/halaman.php?slug=sarana-prasarana` → 200, 68 baris, pil "Baik"
  68×, tanpa luapan, tanpa galat konsol, dan sidik berkas basis data e-GURU **tidak berubah**
  setelah halaman dibuka berkali-kali.


## v1.7.18 — MENU "INFORMASI → DANA BOS" DIISI PAPAN INFORMASI DANA BOS (skema v15)

Permintaan pemilik: *"Pada bagian informasi Bos, di isi dengan tampilan yang ada di
https://supianto.vibecoder.co.id/e-bos/. Buat sama persis. Sumber datanya tarik data dari link
tersebut, sehingga ketika link e-bos melakukan update otomatis Informasi bos yang ada di website juga
akan terupdate."*

### A. Jenis halaman baru `bos` + sumber data baca-saja
* **`inc/bos.php`** (modul baru) membaca **LANGSUNG & HANYA-BACA** basis data aplikasi e-BOS di
  server yang sama: `EBOS_DB_FILE` (`inc/config.php`) = `dirname(APP_DIR) . '/e-bos/data/ebos.sqlite'`,
  dapat diarahkan ulang lewat variabel lingkungan `WEBSK_EBOS_DB` (dipakai skrip uji) atau kunci
  pengaturan **`bos_db`** (tanpa menyunting kode). Tidak ada penyalinan data, tidak ada cron, tidak
  ada API — jadi **setiap pembaruan di aplikasi e-BOS langsung tampil** di halaman website.
  Urutan pembukaan koneksi: `sqlite:file:<berkas>?mode=ro` lebih dulu, cadangan `sqlite:<berkas>` +
  `PRAGMA query_only = 1` (dipakai bila basis data ber-jurnal WAL dan berkas `-shm` belum ada).
  → **JANGAN menulis apa pun ke basis data e-BOS** dari aplikasi ini.
* **JANGAN `require`/`include` kode aplikasi e-BOS** (mis. `e-bos/inc/init.php`): konstanta & nama
  fungsinya bertabrakan dengan aplikasi ini (keduanya punya `e()`, `tanggal_id()`, `is_madrasah()`,
  `nama_sekolah()`) dan modul itu memulai sesi aplikasi lain. Seluruh pembacaan ditulis ulang di
  `inc/bos.php` dengan awalan **`bos_`**.
* `halaman.php` menangani `jenis === 'bos'`: **tanpa** kepala halaman (hero) — halaman ini sudah
  memuat judul **PAPAN INFORMASI** sendiri, persis seperti halaman depan e-BOS — dan `<body>` diberi
  kelas **`bos`** supaya saat dicetak kepala/kaki situs disembunyikan. `@page{size:A4 landscape}`
  WAJIB ditulis di halaman ini (bukan hanya di tema) agar PDF benar-benar A4 mendatar.
* **Jenis "Informasi BOS" juga dapat dipilih admin** pada panel Menu & Halaman
  (`$JENIS` di `admin/menu.php` + `jenis_menu_label()` di `inc/admin_helpers.php`), sehingga halaman
  lain pun bisa dijadikan Papan Informasi BOS.

### B. Isi halaman (sama persis dengan `e-bos/index.php`)
Baris Tahun Anggaran (chip, `halaman.php?slug=…&ta=ID`) + judul **PAPAN INFORMASI** + tombol Cetak →
kartu **Data Lembaga** (nama, NPSN, alamat, kepala, bendahara, telepon, email, situs, periode
anggaran, dana diterima) → **empat kotak ringkasan** (Jumlah Anggaran Komponen, Dana Diterima,
Realisasi Pengeluaran, Sisa Dana) → tabel **Komponen Penggunaan Dana BOS** (kepala bertingkat:
Jumlah + Tahap 1/Tahap 2) → tabel **Realisasi dan Sisa Anggaran** (bilah serapan, baris `*` untuk
pengeluaran yang belum ditautkan ke komponen) → **grafik donat** serapan per komponen → **grafik
batang** pemasukan & pengeluaran per bulan → **Buku Kas, Daftar Transaksi** (tombol buka/tutup,
`?kas=1` tetap bekerja tanpa JavaScript).
Rumusnya **diambil dari kode e-BOS** (pagu per tahap, realisasi per tahap, ringkasan
pemasukan/pengeluaran/saldo, seri 12 bulan) dan formatnya identik (`Rp 1.250.000`, persen koma,
tahun anggaran "2026 (1 Januari 2026 - 31 Desember 2026)").

### C. Migrasi v15 (`db_menu_bos()` di `inc/db.php`)
Satu baris: `UPDATE menu SET jenis = 'bos' WHERE slug = 'dana-bos' AND jenis = 'halaman'`.
Hanya bila jenisnya **masih `halaman`**, sehingga jenis yang dipilih admin tidak pernah tertimpa.
Tanpa tabel/kolom baru → `SCHEMA_TABEL_WAJIB`/`SCHEMA_KOLOM_WAJIB` tidak berubah.

### D. Aset
**Chart.js 4.4.1** disalin dari e-BOS ke **`assets/vendor/chart.umd.min.js`** (tanpa CDN; hanya
dimuat halaman BOS) + penolong **`window.BOS`** (`donut`/`banding`/`palette`) dan penangan tombol
Buku Kas di `assets/app.js`. Seluruh gaya ada di blok **“INFORMASI BOS”** `assets/style.css`
(kelas `.bos-*`, ukuran/warna mengikuti e-BOS) — **jangan** menaruh `<style>` di halaman (satu-satunya
`<style>` yang boleh ada adalah aturan `@page` cetak).

### 🚨 Jebakan yang wajib diingat
1. **Kegagalan sumber data tidak boleh menjadi Fatal error.** `bos_db()` → `null` bila berkas tidak
   ada/tidak terbaca, dan `bos_all()`/`bos_one()` menelan galat query; halaman menampilkan kartu
   keterangan ramah + tautan ke aplikasi e-BOS. Diuji dengan `WEBSK_EBOS_DB` diarahkan ke berkas
   yang tidak ada (halaman tetap 200, halaman lain normal).
2. **Jangan menyalin nomor/judul tahun anggaran dari `$_GET` tanpa validasi** — `?ta=` asing harus
   jatuh ke tahun anggaran aktif (bukan halaman kosong).
3. **Tautan chip tahun WAJIB membawa `slug`** (`halaman.php?slug=…&ta=…`); tanpa itu halaman
   kehilangan menunya.
4. Modul ini **tidak** boleh membuka basis data e-BOS dengan mode tulis bila tidak perlu; `mode=ro`
   terbukti tidak mengubah berkas (`md5` sama setelah halaman dibuka berkali-kali — diuji).

### Uji
- `bash websk_uji_bos.sh` → **72 lulus**: migrasi v14→v15 (jenis menu jadi `bos`, idempoten, menu
  lain tidak tersentuh), kelengkapan isi Papan Informasi, angka yang benar-benar diambil dari e-BOS,
  **kemampuan memutakhirkan diri** (transaksi/tahun anggaran/komponen baru yang disuntikkan ke salinan
  basis data e-BOS langsung muncul di halaman tanpa langkah apa pun), sidik berkas e-BOS **tidak
  berubah** oleh halaman ini, `?kas=1`, penolakan slug asing, **pembandingan seluruh nominal dengan
  halaman depan aplikasi e-BOS yang dijalankan sungguhan** (28 nilai harus sama), dan keterangan
  ramah saat sumber data hilang.
- `bash websk_uji_bos_ui.sh` (Playwright, **36 lulus**): pilihan jenis “Informasi BOS” pada panel
  Menu & Halaman, isi & tata letak halaman (5+ kartu, 4 kotak ringkasan, tabel komponen terisi,
  chip tahun, 2 kolom pada layar lebar), **Chart.js & `window.BOS` termuat, kanvas grafik benar-benar
  terisi**, tombol Buka/Tutup Kas, pindah tahun anggaran lewat chip, **hasil cetak: kepala/kaki situs
  disembunyikan, `@page` A4 mendatar, PDF 842×595 pt**, tampilan 390px tanpa luapan, dan tanpa galat
  konsol.
- Uji lama tetap lulus: `websk_admin_ui.js` (**478**), `websk_profil_ui.js` (89),
  `websk_galeri_ui.js` (89), `websk_ukur.js` (SEMUA UKURAN OK), dan `websk_cek_live.sh`
  (**110 lulus / 17 gagal — SAMA PERSIS sebelum & sesudah perubahan**; 17 kegagalan itu sudah ada
  sebelumnya karena pemilik mengganti nama/slug menu dan mengisi data, bukan akibat perubahan ini).


## v1.7.16 — BERANDA: BAGIAN GALERI MENAMPILKAN SELURUH SAMPUL ALBUM (permintaan pemilik)

Permintaan: *"Pada bagian galeri yang ditampilkan adalah semua sampul album dan jika diklik akan
mengarah ke isi album foto tersebut"* (bagian “Galeri & Media Sosial” pada beranda).

* `index.php` kini memakai **`album_publik()`** (bukan lagi `galeri_foto` + `LIMIT 12`): **satu slide
  per album** berisi **sampul album** (kolom `sampul`, atau **foto pertama** bila sampul kosong —
  `sampul_efektif`), keterangan **nama album + tahun** (`album_label()`), dan **badge jumlah foto**.
* **Klik sampul → membuka ISI album** tersebut (`galeri.php?album=slug`) — bukan lagi membuka satu
  foto. Album yang belum punya foto/sampul tetap tampil memakai kotak pengganti (`kotak_gambar()`).
* Slider-nya tidak berubah: tetap `.gulir-foto` + `data-gulir` (geser otomatis 6 detik, panah, dan
  bisa digeser tanpa JavaScript); hanya isinya yang berubah dari foto → sampul album.
  CSS tambahan: badge jumlah foto dipindah ke **kanan-atas** slide (`.foto-slide .album-jumlah`) dan
  kotak pengganti dibuat selebar slide (`.foto-slide .kotak-kosong`).
* Bagian kosong berbunyi “Belum ada album galeri. Admin dapat membuatnya dari panel admin → Galeri
  Foto & Video.” (dulu menyebut “belum ada foto”).

### Uji
- `bash websk_uji.sh websk_galeri_ui.js` → **89 lulus** (+7): bagian Galeri ada di beranda, jumlah
  sampul = jumlah **album (rekod)** pada halaman galeri, **setiap** slide menaut ke
  `galeri.php?album=…`, keterangan memuat **tahun**, ada badge jumlah foto, klik sampul pertama
  benar-benar membuka **isi album** (tanpa kartu daftar album + ada tombol “Semua Album Foto”),
  tanpa luapan.
- `bash websk_uji.sh websk_ukur.js` → **SEMUA UKURAN OK** — persiapan ujinya diubah: kini membuat
  **6 album** (masing-masing 1 foto) supaya slider beranda tetap punya 6 slide yang bisa digeser
  (dulu 6 foto dalam satu album, yang kini hanya menjadi 1 slide).
- `bash websk_uji.sh websk_admin_ui.js` **478**, `websk_profil_ui.js` **89**,
  `bash websk_cek_live.sh` → **116 lulus** (jumlah sampul beranda = jumlah album aktif pada data asli).
  ⚠️ Pemeriksaan “gambar sampul termuat” di `websk_admin_ui.js` kini **menggulir bagian galeri dulu**
  dan menunggu `naturalWidth > 0` (15 detik): gambar memakai `loading="lazy"` dan letaknya jauh di
  bawah halaman, jadi tanpa itu uji bisa gagal palsu (sempat terjadi satu kali).

## v1.7.15 — GALERI: FILTER TAHUN ALBUM + VIDEO 12 PER HALAMAN (permintaan pemilik)

Permintaan: *"Pada bagian Galeri Album foto buat filter tahun. Agar pengunjung bisa melihat album foto
berdasarkan tahun. Dan untuk video kegiatan tampilkan 12 thumbail video, tambahkan fitur selanjutnya
untuk melihat video yang lain."*

### A. Filter TAHUN pada bagian Album Foto
* Baris chip baru **`.album-saring`** (`#saringTahunAlbum`) di atas grid album: label “Tahun:” +
  chip **“Semua tahun”** + satu chip per tahun yang **benar-benar ada** pada album, urut **terbaru
  lebih dulu**, dengan tooltip jumlah album tahun itu.
* Penyaringan dilakukan di `galeri.php` (bukan mengubah `album_kelompok()`): setiap kelompok album
  dicek, yang tidak punya album pada tahun terpilih dibuang, sedangkan yang punya memakai
  **`album_tampil`** — album tahun itu — sehingga **sampul, jumlah foto, dan tombol “Lihat Album”
  mengikuti tahun terpilih** (chip tahun di dalam kartu ditandai `aktif`).
* **Tahun asing diabaikan** (`?tahun=1999` → kembali ke semua tahun, bukan halaman kosong atau 403),
  dan tanpa filter seluruh album tampil (termasuk album yang belum bertahun).
* Judul bagian ikut berubah: “Album Foto Tahun 2026”; subjudul hero ikut menyebut tahunnya.
  Tautan chip membawa `#albumGaleri` supaya pengunjung tetap di bagian album.

### B. Video Kegiatan 12 per halaman + tombol Selanjutnya
* Konstanta **`GALERI_VIDEO_PER_HAL = 12`**; video diambil `video_publik()` (hanya yang aktif &
  tautannya sah), lalu dipotong **`array_slice()`** 12 per halaman (grid 4 kolom = 3 baris).
* Keterangan bagian video: **“Menampilkan 1–12 dari N video … Halaman 1 dari 2.”**
* Navigasi **`.paginasi paginasi-video`** (`#paginasiVideo`): tombol **Selanjutnya** (kelas
  `tombol-emas`, atribut `data-video-selanjutnya`), **Sebelumnya**, dan nomor halaman.
  Tautannya dibuat **`video_hal_url()`** → `galeri.php?hal=N#videoGaleri` (filter `?tahun=` tetap
  dibawa) sehingga pengunjung dikembalikan ke bagian video, bukan ke atas halaman.
* Nomor halaman yang melebihi jumlah **dijepit** ke halaman terakhir (tidak pernah menampilkan
  bagian kosong).

### Uji
- `bash websk_uji.sh websk_galeri_ui.js` → **82 lulus** (+23): chip saringan tahun (Semua tahun aktif
  bawaan, tahun urut terbaru dulu), klik chip menyaring album + menandai chip aktif + judul menyebut
  tahun + tombol “Lihat Album” mengarah ke album tahun itu, tahun asing diabaikan, serta video:
  tepat **12 kotak** pada halaman pertama, tombol Selanjutnya ada & menuju `?hal=2#videoGaleri`,
  keterangan “Menampilkan 1–12”, halaman kedua berisi sisa video + tombol Sebelumnya (tanpa
  Selanjutnya), Album Foto tetap tampil, dan `?hal=99` dijepit ke halaman terakhir.
  ⚠️ Uji menambah **13 video** lewat panel agar paginasi benar-benar teruji (basis data uji selalu
  disemai baru, tanpa video milik pemilik).
  ⚠️ **Console-error dari iframe YouTube**: dengan banyak iframe, Chromium mencatat
  `Permissions policy violation: compute-pressure is not allowed in this document` (berasal dari
  pemutar YouTube pihak ketiga, bukan kode aplikasi). Pesan itu **disaring** pada pemeriksaan
  “tanpa galat konsol” di `websk_galeri_ui.js` & `websk_admin_ui.js` — jangan dianggap regresi.
- `bash websk_uji.sh websk_admin_ui.js` **476**, `websk_profil_ui.js` **89**, `websk_ukur.js`
  **SEMUA UKURAN OK**, `bash websk_cek_live.sh` → **112 lulus** (saringan tahun pada data asli,
  filter `?tahun=` benar-benar menyaring, tahun asing diabaikan, video 12/halaman + halaman kedua).

## v1.7.14 — BLOK MERAH DI DALAM KOTAK BAGAN DIHAPUS (permintaan pemilik)

Permintaan: *"Hilangkan warna merah yang ada di dalam kolom bagan."*

* Penanda **`aksen` dihapus dari seluruh slot** `struktur_peta()` (`inc/profil.php`) — dulu 10 kotak
  (Kepala Madrasah, Komite, KTU, Guru BK, dan 6 kotak kelas) memakai blok merah di tepi kiri kotak.
* `struktur_render()` tidak lagi menggambar `.org-aksen` / `.org-aksen-pemisah`, dan gaya CSS
  keduanya dibuang dari `assets/style.css`.
* Panel `admin/struktur.php` tidak lagi menampilkan pil **“blok merah”** dan baris baru selalu
  ditulis dengan `aksen = 0` (kolom basis data dibiarkan ada supaya tidak ada tabel yang dibangun
  ulang; nilainya kini tidak pernah dipakai).
* **Efek samping menguntungkan**: karena tidak ada lagi blok 22px di kiri kotak,
  `$xTeks`/`$lebarFoto` tidak lagi memakai `$lebarAksen` sehingga **pas foto dan teks memakai
  seluruh lebar kotak** (teks jadi lebih lega, tidak ada nama yang meluber).
* ⚠️ Skrip analisis piksel `ws_baca_foto_bagan.py` ikut disesuaikan: area pindai pas foto dimulai
  dari **5%** lebar kotak (sebelumnya 14% karena harus melewati blok merah), dan penanda
  “FOTO TERLIHAT” kini berbasis **porsi piksel kuning** (latar kotak) — bukan jumlah warna unik,
  karena gradient kotak yang tertutup pun menghasilkan banyak warna (pernah membuat uji gagal palsu).

### Uji
- `bash websk_uji.sh websk_profil_ui.js` → **89 lulus**: `org.aksen === 0` (tidak ada blok merah
  sama sekali) dan panel tidak lagi menandai “blok merah”.
- `bash websk_uji.sh websk_admin_ui.js` **476**, `websk_galeri_ui.js` **59**, `websk_ukur.js`
  **SEMUA UKURAN OK**, `bash websk_cek_live.sh` **101 lulus**.
- Diperiksa pada SITUS LIVE: `.org-aksen` = **0**, 21 kotak, 24 garis, 13 jabatan, **12 pas foto tetap
  tampil**, tidak ada nama yang meluber, tanpa luapan & tanpa galat konsol.

## v1.7.13 — DOKUMEN IJOP & AKREDITASI + tombol cetak profil dihapus (skema v13)

Permintaan: *"Yang ditandai warna merah dihapus. Yang ditandai warna biru tambahkan fitur Lihat Ijop
dan Lihat Akreditasi. Dokumen berupa ijop dan akreditas berupa gambar/pdf di upload dari isian
Profil lengkap."* (merah = tombol **Cetak / Simpan PDF**; biru = ruang kosong di bawah tabel identitas.)

1. **Tombol “Cetak / Simpan PDF” DIHAPUS** dari halaman Profil Lengkap — tinggal tombol
   “Kembali ke Beranda”. (Halaman bagan struktur organisasi sudah tidak punya tombol cetak sejak
   v1.7.11.) Kelas `.pl-aksi` pada aturan cetak dibiarkan sebagai jaring pengaman bila nanti tombol
   itu dipakai lagi.
2. **Tombol “Lihat Ijop” & “Lihat Akreditasi”** tampil pada blok **`.pl-dokumen`** (`#profilDokumen`)
   **tepat di bawah tabel Identitas** (kolom kiri — ruang yang ditandai biru pemilik). Keduanya
   `<a>` ke berkas dokumen dengan `target="_blank"` + `rel="noopener noreferrer"`; tombol hanya
   dicetak bila berkasnya sudah ada, sehingga **tidak pernah ada tombol mati**.
3. **Unggahan dari panel Profil Lengkap** (`admin/profil.php`): formulir menjadi
   `enctype="multipart/form-data"` dan memuat dua isian berkas **Dokumen IJOP** (`#berkas_ijop`) &
   **Dokumen Akreditasi** (`#berkas_akreditasi`) dengan pratinjau tautan “Lihat berkas”, unggah/ganti,
   dan centang **Hapus berkas ini** (`<field>` + `_hapus`). Validasi memakai **`unggah_berkas()`**
   (gambar JPG/PNG/WEBP atau PDF, maks 15 MB, **isi berkas diperiksa** — PDF wajib diawali `%PDF`,
   gambar lewat `getimagesize`), lalu diteruskan `media_upload()` — yang disimpan ke basis data
   **hanya URL CDN**. Bagian yang tidak dikirim (formulir lama) tidak mengubah berkas.
4. **Skema v13**: kolom `sekolah.berkas_ijop` & `sekolah.berkas_akreditasi` (`db_add_column` +
   `SCHEMA_KOLOM_WAJIB` + whitelist `sekolah_simpan()`). Hanya MENAMBAH kolom — kolom lain tidak
   disentuh dan sekolah yang belum mengunggah tetap tidak menampilkan tombol apa pun.
5. Ikon baru pada `ikon()` (`inc/layout.php`): **`lihat`** (berkas) & **`sertifikat`**.

### Uji
- `bash websk_uji.sh websk_profil_ui.js` → **89 lulus** (+14): halaman Profil **tanpa** tombol cetak,
  blok dokumen **tidak muncul** sebelum ada berkas, panel memuat kedua medan berkas & form multipart,
  unggah **PDF + PNG sungguhan** berhasil, berkas **`.txt` ditolak**, kedua tombol muncul **di bawah
  tabel** dengan `target=_blank` + `rel` aman dan tautan berakhiran `.pdf`, tombol Ijop **hilang lagi**
  setelah berkasnya dihapus dari panel.
- `bash websk_uji.sh websk_admin_ui.js` **476**, `websk_galeri_ui.js` **59**, `websk_ukur.js`
  **SEMUA UKURAN OK**, `bash websk_cek_live.sh` → **101 lulus** (kolom berkas ada, tombol cetak 0,
  blok dokumen & tombol “Lihat Ijop” tercetak pada data asli).
- Diperiksa pada SITUS LIVE (v1.7.13): tombol cetak **0**, blok dokumen di bawah tabel, dan **kedua
  berkas PDF pemilik dijawab 200 `application/pdf`**.

## v1.7.12 — ISIAN NILAI AKREDITASI + KERAPIAN TABEL & TANDA TANGAN DI PINGGIR (permintaan pemilik)

Dua permintaan: *"Pada inputan profil lengkap tambahkan input nilai akreditas"* dan
*"Pada tampilan profil lengkap madrasah, rapikan lagi untuk bagian nomor, garis dan posisi tanda
tangan dipinggir"* (tanda merah = nomor 10.–13. terbelah & garis kolom bergeser; tanda biru = letak
tanda tangan).

### A. Isian NILAI Akreditasi
Panel **Profil Lengkap (Isian)** (`admin/profil.php`) kini memuat sepasang medan berdampingan:
**“Nilai / Peringkat Akreditasi”** (`#akreditasi`, dengan saran A/B/C/Unggul/Baik Sekali/Baik, boleh
juga angka) dan **“Tanggal Akreditasi”** (`#akreditasi_tgl`). `akreditasi` ikut masuk daftar `$KUNCI`
halaman itu (kolom `sekolah.akreditasi` sudah ada di whitelist `sekolah_simpan()`), dan keduanya
tampil pada baris **Akreditasi** di tabel Identitas halaman profil.

### B. Tabel identitas dirapikan (nomor & garis)
* Lebar kolom dipatok di **satu tempat** lewat `<colgroup>` (`.pl-c-no` 46px, `.pl-c-label` 30%,
  `.pl-c-nilai` 34%, `.pl-c-nilai2` 30%) dan **setiap baris memakai 4 sel** (nomor · label · nilai ·
  nilai kedua) — sel kedua dibiarkan kosong bila tidak dipakai. Sebelumnya jumlah sel berbeda antar
  baris (3 vs 4) sehingga kolom “Kode Pos/Tanggal/Provinsi” **bergeser-geser** (garis tidak lurus).
  ⚠️ Total lebar kolom sengaja **< 100%** supaya peramban tidak mengecilkan seluruh kolom
  (total lebih dari lebar tabel membuat kolom label menyempit → label seperti “Bangunan Madrasah”
  terpotong).
* Kolom nomor dilebarkan (32 → **46px**) + `white-space:nowrap` sehingga nomor dua angka
  (**10., 11., 12., 13.**) tidak lagi terbelah menjadi dua baris seperti pada tangkapan layar pemilik.

### C. Tanda tangan di pinggir kanan
Blok tanda tangan dipindah **keluar dari kolom kiri** ke **kaki lembar** (`.pl-kaki` setelah
`.pl-grid`) dan diratakan **ke pinggir KANAN dokumen** (`.pl-kaki{display:flex;justify-content:flex-end;
padding-right:6px}`, `.pl-ttd{text-align:right;min-width:280px}`). Terukur: tepi kanan tanda tangan
**27 px** dari tepi lembar (= padding lembar + 6px) dan posisinya **di bawah** kedua kolom isi.

### Uji
- `bash websk_uji.sh websk_profil_ui.js` → **75 lulus** (+9): setiap baris 4 sel, kolom **label** &
  kolom **nilai kedua** punya **satu nilai x** di seluruh baris (garis lurus), **nomor tidak terbelah**
  diukur pada RANGE teksnya (bukan tinggi sel — tinggi sel mengikuti baris dan pernah menyesatkan),
  lebar kolom nomor ≥40px, tanda tangan **≤32px dari pinggir kanan** + di bawah isi + `text-align:right`,
  medan `#akreditasi` & `#akreditasi_tgl` ada (17 medan pada formulir), serta nilai & tanggal
  akreditasi yang disimpan benar-benar tampil pada baris Akreditasi.
- `bash websk_uji.sh websk_admin_ui.js` **476**, `websk_galeri_ui.js` **59**, `websk_ukur.js`
  **SEMUA UKURAN OK**, `bash websk_cek_live.sh` → **95 lulus** (colgroup, 2 sel nilai per baris,
  `pl-kaki`, kolom akreditasi).

## v1.7.11 — PAS FOTO BAGAN MUNCUL + BAGAN TIDAK UNTUK DICETAK (permintaan pemilik)

Laporan: *"Pas foto tidak muncul di bagan. Dan bagan tidak perlu dicetak jadi buat struktur organisasi
berukuran lebih dari A4 tidak masalah karena hanya untuk dilihat."*

### A. Penyebab pas foto tidak muncul (dan perbaikannya)
`<clipPath id="orgFotoBulat">` memakai **`clipPathUnits` bawaan = `userSpaceOnUse`** dengan
`<rect x="0" y="0" width="100" height="100">`. Karena `<image>` berada di koordinat kotaknya sendiri
(mis. `x=727 y=165`), clip itu **memotong HABIS** pas foto → kotak terlihat kuning kembali.
Perbaikan: **`clipPathUnits="objectBoundingBox"`** dengan `rect x=0 y=0 width=1 height=1 rx=0.08`
(clip dihitung relatif kotak gambar).
**Dibuktikan dengan analisis piksel** pada potret kotak Kepala Madrasah (skrip `ws_baca_foto_bagan.py`):
* versi LAMA → **1102 dari 1102 piksel kuning** (foto tidak terlihat),
* versi BARU → **958 warna unik, hanya 6 piksel kuning** (foto terlihat).
⚠️ Uji Playwright memakai pas foto uji **GAMBAR SOLID** (dibuat PIL) — PNG tembus pandang membuat
kotak tetap kuning sehingga uji "foto terlihat" gagal palsu; uji juga menunggu foto benar-benar
termuat (`new Image()` + `onload`) sebelum kotaknya dipotret.

### B. Bagan hanya untuk dilihat (tidak dicetak, boleh lebih lebar dari A4)
* `halaman.php`: `@page A4 landscape` dan tombol **Cetak / Simpan PDF** kini **hanya** untuk halaman
  **Profil Lengkap**; halaman bagan hanya punya tombol “Kembali ke Beranda”. `$halamanDokumen`
  (kelas `dokumen` pada `<body>`) juga hanya untuk Profil Lengkap.
* `struktur_render()`: penanda penyusutan cetak **`data-cetak-lebar` dilepas** dan bagan dibungkus
  **`.org-lebar`** — melebar melewati lebar konten (full-bleed, maks **1900px**) sehingga pada layar
  1600px SVG-nya **±1526px** (jauh lebih lebar dari area A4 ±1032px).
* **Tombol Perkecil / Ukuran Layar / Perbesar** (`#orgAksi` + `#orgZoomInfo`, skrip `initZoomBagan`
  pada `assets/app.js`) mengubah lebar SVG 75%–300%; wadah `.org-bagan-gulir` `overflow-x:auto`
  sehingga bagan lebar tetap dapat digeser, sementara `.org-svg{min-width:1100px}` menjaga keterbacaan
  di HP. Tanpa JavaScript bagan tetap tampil penuh dan bisa di-zoom peramban.
* Aturan cetak khusus bagan dihapus dari `assets/style.css` (blok `@media print body.dokumen`
  sekarang murni untuk lembar Profil).

### Uji
- `bash websk_uji.sh websk_profil_ui.js` → **66 lulus** (+5): halaman bagan **tanpa tombol cetak** &
  tanpa `data-cetak-lebar`, lebar bagan **> 1032px**, 3 tombol zoom + benar-benar memperbesar,
  wadah bergulir, `clipPathUnits="objectBoundingBox"`, dan **analisis piksel “FOTO TERLIHAT”**.
- `bash websk_uji.sh websk_admin_ui.js` **476**, `websk_galeri_ui.js` **59**, `websk_ukur.js`
  **SEMUA UKURAN OK**, `bash websk_cek_live.sh` → **91 lulus** (clipPath objectBoundingBox,
  kotak berfoto pada data asli, tanpa tombol cetak di halaman bagan).

## v1.7.10 — NAMA JABATAN DI ATAS NAMA + GARIS BAGAN YANG HILANG (permintaan pemilik)

Dua laporan atas bagan hasil v1.7.9:
1. *"Pada struktur organisasi nama jabatan diatas nama jangan dihilangkan. Misal supianto diatasnya
   ada tulisan kecil Kepala Madrasah dan seterusnya."*
2. *"ada garis yang hilang sudah saya berit tanda panah."*

### A. Jabatan selalu tercetak di atas nama
`struktur_render()` kini mencetak **dua baris** pada kotak yang isian namanya BERBEDA dari jabatan:
* baris 1 = **jabatan** (`.org-jabatan`, huruf kecil ~11,5 px, di atas) — mis. “KEPALA MADRASAH”;
* baris 2 = **nama** (`.org-nama`, tebal ~15 px) — mis. “SUPIANTO, S.Pd.I”.
Kotak yang isinya memang jabatan itu sendiri (mis. `KTU`, `Dewan Guru`, `Siswa`, `Kelas X MIPA 2`)
tetap **satu baris** — dibandingkan oleh **`struktur_nama_beda()`** (tanpa peduli huruf besar/kecil &
tanda baca) supaya tidak ada tulisan kembar. Kedua baris memakai penghitung ukuran yang sama
(`$ukur()` → auto-kecil + `textLength`) sehingga **tidak pernah meluber** keluar kotak.

### B. Garis yang hilang
Penyambung tegak dari **Wali Kelas** berhenti di `y = 630` sehingga stub baris kelas ke-2 (`y = 700`)
dan ke-3 (`y = 770`) **menggantung** — inilah garis yang ditandai pemilik. Sekarang:
* `M 180 580 V 770` (satu garis menembus ketiga baris kelas);
* batang datar Wakamad dirapikan menjadi `M 180 383 H 1444` (dulu mulai di 178, 2 px sebelum stub
  kirinya) sehingga **tidak ada ujung garis yang menggantung**.

### Uji
- `bash websk_uji.sh websk_profil_ui.js` → **61 lulus** (+5): uji **kemampuan garis** — seluruh 24
  segmen diurai dari atribut `d`, setiap ujung harus menyentuh segmen lain atau tepi kotak
  (**0 ujung menggantung**; uji ini akan langsung menangkap garis yang hilang), pengecekan khusus
  garis penyambung `x = 180` sampai `y = 770`, serta pengecekan jabatan: setelah nama diisi,
  kotak berisi **dua baris**, teks jabatan muncul, dan **y jabatan lebih kecil dari y nama**.
- `bash websk_uji.sh websk_admin_ui.js` **476**, `websk_galeri_ui.js` **59**, `websk_ukur.js`
  **SEMUA UKURAN OK**, `bash websk_cek_live.sh` **85 lulus**.

## v1.7.9 — BAGAN STRUKTUR ORGANISASI: PETA TETAP + isian NAMA & PAS FOTO (skema v12)

Permintaan: *"Pada tampilan struktur organisasi, susunan dan tampilan dibuat permanen seperti contoh
yang sudah dikirim. Dan admin tinggal input saja nama-namanya + pas fotonya, tidak perlu lagi
menggeser bagan."*

1. **Letak bagan bersifat PERMANEN** — ditentukan **KODE slot** pada **`struktur_peta()`**
   (`inc/profil.php`), bukan lagi kolom basis data:
   kanvas **1600 × 853** (nisbah A4 mendatar), 21 kotak pada koordinat tetap (Kepala Madrasah,
   Komite, KTU, Tata Usaha, Bendahara, 6 kotak Wakamad/Kepala Lab/Kepala Perpus, Wali Kelas,
   Dewan Guru, Guru BK, 6 kotak kelas, Siswa) + **24 garis penghubung** tetap (garis tebal; **Komite
   bergaris putus-putus**), kotak ber-**blok merah** seperti contoh, dan kop judul + logo madrasah.
2. **Bagan digambar sebagai SVG** (`.org-svg`) sehingga tajam di segala ukuran & saat dicetak;
   nama kotak memakai `text-anchor:middle` dan **dipastikan tidak meluber**: PHP memperkirakan lebar
   teks, lalu memakai `textLength="…" lengthAdjust="spacingAndGlyphs"` (dan menurunkan ukuran huruf
   bila nama jauh lebih panjang, batas bawah 10,5) — tanpa JavaScript, tetap benar saat dicetak.
3. **Panel `admin/struktur.php` hanya berisi isian NAMA + PAS FOTO** (satu formulir untuk seluruh
   kotak, satu tombol Simpan): tiap kotak punya kartu berisi label tetap, pratinjau pas foto 3:4,
   isian Nama, unggah foto, centang hapus foto, dan centang “tampilkan kotak”, plus tombol
   **“Kembalikan Nama Bawaan”**. **DIHAPUS**: pemilih atasan, urutan, jenis garis, tombol ▲▼,
   hapus kotak, “Tanamkan Contoh Bagan”, dan “Kosongkan Semua” — **letak tidak bisa lagi digeser**.
   Slot yang medannya tidak dikirim (formulir lama) dilewati agar tidak mengosongkan isian.
4. **Skema v12**: kolom `struktur.kode` + `struktur.foto` (`db_add_column` + `SCHEMA_KOLOM_WAJIB`).
   Migrasi `db_struktur_peta()` memasang `kode` pada baris lama dengan **mencocokkan NAMANYA**
   (mis. “Kepala Madrasah”) dan menambah baris untuk slot yang belum ada — **tidak menghapus apa pun**
   (baris tanpa slot dibiarkan, tidak dirender). Kolom lama (`induk_id`/`urutan`/`garis`/`aksen`)
   tetap ada tetapi **tidak lagi menentukan letak**; diuji pada salinan data asli dengan mengubah
   kolom-kolom itu (bagan tetap 21 kotak pada posisi yang sama).

### Uji
- `bash websk_uji.sh websk_profil_ui.js` → **56 lulus**: kanvas tetap `0 0 1600 853`, **21 kotak
  berurutan persis seperti dokumen contoh**, ≥20 garis + tepat 1 garis putus-putus (Komite), tebal
  garis ≥4, ≥5 kotak ber-blok merah, **letak kotak kunci sama dengan peta bawaan**, tidak ada nama
  yang keluar dari kotaknya, panel berisi 21 kartu dengan isian Nama + unggah Foto sementara
  **atasan/urutan/garis/geser/hapus sudah tidak ada**, mengisi nama + mengunggah pas foto lalu
  memastikan keduanya tampil di bagan publik, tombol “Kembalikan Nama Bawaan”, cetak (tombol
  disembunyikan, bagan ≤ area A4 mendatar, **PDF 842×595 pt**), serta tanpa luapan di HP 390px.
- `bash websk_uji.sh websk_admin_ui.js` **476**, `websk_galeri_ui.js` **59**, `websk_ukur.js`
  **SEMUA UKURAN OK**, dan `bash websk_cek_live.sh` → **85 lulus** (migrasi v12 pada salinan data
  asli: kolom dibuat, 21 kotak berkode, bagan 21 kotak + garis, letak tidak berubah walau
  `induk_id`/`urutan` diubah, nama dari basis data tampil).
- Diperiksa pada SITUS LIVE (v1.7.9): bagan 21 kotak, 24 garis (1 putus-putus), 10 blok merah,
  **nama sudah diisi pemilik** (mis. “Sunarso”, “Yelfis Djamaludin, S.Pd.I”), tanpa luapan & tanpa
  galat konsol.

## v1.7.8 — GALERI DUA BAGIAN: ALBUM FOTO (atas) + VIDEO KEGIATAN (bawah) (permintaan pemilik)

Permintaan: *"Pada menu galeri yang ditampilkan 2 jenis. Album foto dan video. Album Foto dibagian
atas dan dibawah video (Video tanpa album) langsung tampil semua. Jadi yang di dalam album hanya
file foto saja"*.

1. **Halaman `galeri.php` kini memuat DUA bagian**:
   * **Album Foto** (ATAS, `#albumGaleri`) — kartu album (kelompok per nama + chip tahun) dengan
     hitungan **FOTO saja**; tombol “Lihat Album”.
   * **Video Kegiatan** (BAWAH, `#videoGaleri`) — **SELURUH video langsung tampil** tanpa album
     (`video_publik()`), memakai grid 4 kolom yang sama seperti sebelumnya.
   Urutan ini diukur di uji Playwright (`yAlbum < yVideo`) **dan** pada salinan data asli
   (`websk_cek_live.sh` membandingkan posisi byte kedua penanda bagian).
2. **Isi album (`?album=slug`) hanya FOTO** — blok video di halaman album **dihapus**, keterangan
   album tidak lagi menyebut video, tombol kembali berbunyi **“Semua Album Foto”**, dan bagian
   “Album Foto Lainnya” tetap ada. Bila ada video, muncul satu baris penunjuk ke
   **bagian Video Kegiatan** (`galeri.php#videoGaleri`).
3. **Album tidak lagi menghitung video**: `album_publik()` hanya menghitung foto dan
   `album_kelompok()` tidak lagi mengembalikan `jml_video`; helper `album_video()` **dihapus** dan
   diganti **`video_publik()`/`video_publik_jumlah()`** (aktif + tautan YouTube sah).
4. **Panel `admin/galeri.php`**: pemilih **Album pada formulir video DIHAPUS** (video selalu
   `album_id = 0` — kolom basis data dibiarkan agar tidak ada tabel yang dibangun ulang), kolom
   **Album** pada tabel video dihapus, kolom tabel album menjadi **Jumlah Foto** (bukan
   “Foto / Video”), dan tombol **“Isi Tahun dari Tanggal Unggah”** kini menghitung dari tanggal
   unggah **foto** (album hanya berisi foto).

### Uji
- `bash websk_uji.sh websk_galeri_ui.js` → **59 lulus** (ditambah: dua bagian & urutannya, kartu
  album hanya menyebut jumlah FOTO, **isi album nol video**, formulir video tanpa pemilih album,
  tabel video tanpa kolom Album — termasuk saat dijalankan oleh akun Editor Berita).
- `bash websk_uji.sh websk_admin_ui.js` → **476 lulus**, `websk_profil_ui.js` **52 lulus**,
  `websk_ukur.js` **SEMUA UKURAN OK**, `websk_cek_live.sh` → **77 lulus**.
- Diperiksa langsung pada SITUS LIVE (v1.7.8): 4 album di ATAS, 20 video di bawah, isi album
  berisi foto saja, tanpa luapan, tanpa galat konsol.

## v1.7.7 — EDITOR BERITA BOLEH MENGELOLA GALERI + ALBUM BERTAHUN + GALERI PER ALBUM (skema v11)

Tiga permintaan pemilik:
1. *"Pada akun editor berita tambahkan akses agar bisa menambahkan foto dan video"*
2. *"Pada setiap Album Galeri Foto tambahkan tahun agar diketahui foto tersebut pada tahun berapa.
   Misal Album Pramuka di dalamnya ada Pramuka 2024, 2025, 2026 dan seterusnya."*
3. *"Foto kegiatan yang ditampilkan ke pengunjung adalah per album dan mereka bisa melihat isi
   album tersebut"*

### A. Editor Berita juga mengelola Galeri
- **`galeri.php` ditambahkan pada `berita_berkas_boleh()`** (`inc/helpers.php`) — daftar putih berkas
  yang boleh dibuka peran `berita`: dasbor, berita (daftar/tulis/kategori), `upload_editor.php`,
  dan **`galeri.php`**. Berkas lain tetap **403** (diperiksa di `require_login()`).
- Sidebar peran ini mendapat blok **“Galeri → Foto & Video”**, dan dasbornya diberi pintasan
  Galeri + tautan “Lihat Galeri di Website”.
- Label peran menjadi **“Editor Berita (berita + galeri foto & video)”** (`peran_daftar()`).
- Diuji: Editor Berita **200** pada `galeri.php` (dan benar-benar dapat menambah album, mengunggah
  foto, serta menambah video YouTube) sementara `slider.php`, `medsos.php`, `identitas.php`,
  `pengguna.php`, `menu.php` tetap **403**.

### B. Album galeri punya TAHUN (skema v11)
- Kolom baru **`galeri_album.tahun`** (`db_add_column` + `SCHEMA_KOLOM_WAJIB`) — hanya MENAMBAH kolom;
  basis data lama TIDAK dibangun ulang dan **tahun tidak ditebak** (dibiarkan kosong).
- Helper `album_tahun_sah()`: hanya 4 angka yang diterima (nilai seperti `20xx` → kosong), dan
  **tahun WAJIB** saat menyimpan album (ditolak server dengan pesan “Tahun album wajib diisi”).
- Di panel (`admin/galeri.php`): medan **Tahun** (wajib, dengan saran tahun) + datalist saran nama
  album, **kolom Tahun** & **Foto/Video** pada tabel, **saringan tahun** (termasuk pilihan
  “Belum diberi tahun”) dan saringan cari nama album, chip `bulan` label album (`album_label()` →
  “Pramuka 2026”), peringatan “N album belum diberi tahun”, serta tombol
  **“Isi Tahun dari Tanggal Unggah”** (mengisi tahun album kosong dari tanggal unggah foto/video
  pertamanya — album tanpa berkas dilewati, jadi tidak ada nilai yang ditebak).
- **Album yang sama untuk tahun berbeda** = buat album baru dengan nama yang SAMA dan tahun berbeda.
  Di website keduanya tampil sebagai **SATU kartu** dengan pilihan tahun (lihat C).

### C. Halaman Galeri publik: DAFTAR ALBUM lalu ISI ALBUM
`galeri.php` ditulis ulang:
- **Tanpa `?album=`** → **daftar kartu album** `.album-grid/.album-kartu`: sampul efektif
  (sampul album, atau foto pertama bila sampul kosong — `album_publik()`), jumlah foto/video,
  nama album, **chip tahun**, keterangan, dan tombol “Lihat Album”.
  Kartu **dikelompokkan per NAMA album** (`album_kelompok()`): album “Pramuka” 2024/2025/2026 → satu
  kartu dengan chip **2026 · 2025 · 2024** (terbaru lebih dulu). Album tanpa tahun tetap tampil.
- **Dengan `?album=slug`** → **isi album**: tombol “Semua Album”, chip tahun album itu (tahun aktif
  ditandai), keterangan, **video album itu** lalu **foto album itu** (`album_foto()`/`album_video()`),
  serta **“Album Lainnya”** untuk berpindah tanpa kembali ke daftar.
  Foto & video **tidak lagi ditampilkan semuanya sekaligus** — hanya milik album yang dibuka.
- CSS baru `.album-*`/`.chip-tahun` (4 kolom → 3 → 2 → 1 pada ≤1100/≤820/≤520px).
- **Tautan foto pada slider galeri BERANDA diperbaiki**: dulu menuju `galeri.php?album=<id>`
  sedangkan halaman galeri memakai **slug** sehingga tautannya sia-sia. Kini query beranda ikut
  mengambil `a.slug`/`a.tahun` dan tautannya memakai **slug**; label foto memakai nama album + tahun
  (`album_label()`) bila keterangan fotonya kosong.

### Uji
- `bash websk_uji.sh websk_galeri_ui.js` → **51 pemeriksaan** BARU: kolom/medan/saringan tahun &
  tombol “Isi Tahun dari Tanggal Unggah”, menolak tahun tidak sah/kosong (POST langsung, karena
  isian `number` menahan teks dari antarmuka), tiga album “Pramuka” (2024/2025/2026) menjadi **satu
  kartu** dengan chip tahun berurutan terbaru-lebih-dulu, chip membuka album tahun itu dan tahun
  aktif ditandai, album terisi menampilkan 2 foto + 1 video, album kosong menampilkan pesan ramah
  (tidak memakai isi album lain), tombol “Semua Album”, hak akses Editor Berita (200 di Galeri,
  403 di 4 halaman lain, dapat menambah album/foto/video), serta tanpa luapan di HP 390px.
- `bash websk_uji.sh websk_admin_ui.js` → **476 lulus** (uji lama disesuaikan: album uji kini wajib
  bertahun, video uji diletakkan di album itu, dan halaman video publik diukur di dalam albumnya).
- `bash websk_uji.sh websk_ukur.js` → **SEMUA UKURAN OK**.
- `bash websk_cek_live.sh` → **71 lulus** (migrasi v11 pada SALINAN data asli: kolom `tahun` dibuat,
  album & data lain tidak berubah, halaman galeri publik memuat kartu album + chip tahun).

### 🚨 Catatan operasional
Pemilik **langsung memakai fitur ini di situs live** (5 album sudah diberi tahun 2026 dan satu album
baru “Kegiatan OSIM” ditambahkan tak lama setelah rilis) — jangan “merapikan” isi `galeri_album`/
`galeri_foto` dari skrip apa pun; seluruh uji memakai salinan di `/tmp`.

## v1.7.6 — HALAMAN “PROFIL LENGKAP” & “STRUKTUR ORGANISASI” (skema v10, permintaan pemilik)

Permintaan: *"Pada isian Profil Lengkap dan Struktur Organisasi yang ditampilkan seperti contoh.
Dan data isiannya lewat admin"* (dua dokumen contoh dari pemilik: lembar **PROFIL MA. STM** dan
bagan **STRUKTUR ORGANISASI**).

Kedua halaman memakai **jenis menu baru** sehingga dirender `halaman.php` (bukan teks bebas lagi):
`profil_lengkap` dan `struktur_organisasi`. Keduanya dipasang pada menu yang SUDAH ADA dan masih
kosong (dibuat pemilik sendiri): **Profil Lengkap** (slug `visi-misi`) dan **Struktur Organisasi**
(slug `struktur`) — jadi tidak ada menu ganda.

### A. Halaman “Profil Lengkap” (`inc/profil.php` → `profil_lengkap_render()`)
Susunannya mengikuti contoh: kop hijau-emas berjudul **“PROFIL <nama madrasah>”**, dua kolom —
kiri = **LOGO MADRASAH** + tabel **IDENTITAS MADRASAH** + tanda tangan
**“<Kecamatan>, <bulan tahun>”** di atas nama kepala; kanan = **VISI** (huruf besar),
**MISI** & **TUJUAN** sebagai daftar bernomor.
- Tabel identitas mengikuti penomoran dokumen contoh pemilik: **13 baris pokok bernomor 1–13**
  (Nama Madrasah, NPSN/NSM, **Alamat**, NPWP, Status Madrasah, Waktu Belajar, No. SK IJOP,
  Tahun Berdiri, Akreditasi, Penyelenggara, SK Menkumham, **Lokasi Madrasah**, Bangunan Madrasah)
  dengan **6 baris rincian TANPA nomor** di bawah “Alamat” (Desa, Kecamatan + Kode Pos,
  Kabupaten + Provinsi, Telepon, Email, Website — latarnya dibedakan tipis, `.pl-sub-baris`)
  dan rincian **Jarak ke Kecamatan / Jarak ke Kabupaten** pada baris Lokasi Madrasah.
  Jadi jumlah total baris 19, tetapi nomornya berhenti di 13 seperti contoh.
- Isiannya = kolom tabel `sekolah` (16 kolom baru) dan diubah dari panel **Profil Lengkap (Isian)**
  (`admin/profil.php`). Nama/alamat/NPSN/NSM/telepon/email/situs/akreditasi/nama kepala TETAP diambil
  dari **Identitas Sekolah** (tidak diduplikasi).
- Baris identitas yang seluruh nilainya kosong **tidak dicetak** (tidak ada baris “-”).
- VISI/MISI/TUJUAN memakai **`potong_baris()`** — satu baris = satu butir (jangan `potong()`).

### B. Halaman “Struktur Organisasi” (`inc/profil.php` → `struktur_render()`)
**Bagan kotak berjenjang** dengan garis penghubung (pola klasik `ul/li` + pseudo-element, sehingga
garis selalu benar tanpa mengukur lebar kotak). Setiap kotak punya: nama, **atasan (induk)**,
urutan, jenis garis (**penuh / putus-putus** — dipakai Komite seperti pada contoh), dan penanda
**blok merah** (`.so-aksen`) persis seperti kotak pada contoh.
- Tabel baru **`struktur`** (`nama`, `induk_id`, `urutan`, `garis`, `aksen`, `aktif`, `dibuat`).
- Panel **Struktur Organisasi (Bagan)** (`admin/struktur.php`): tabel kotak (dengan lekukan sesuai
  tingkat), tambah/ubah/▲▼/sembunyikan/hapus, tombol **Tanamkan Contoh Bagan**, dan **Kosongkan
  Semua** (wajib ketik HAPUS). Bagan **melingkar ditolak** (`struktur_turunan()`), dan menghapus
  sebuah kotak **memindahkan anak-anaknya ke atasan kotak itu** (bagan tidak terputus).
- Isi contoh (21 kotak sesuai contoh pemilik) ditanam **sekali** oleh migrasi.

### C. Cetak
Keduanya punya tombol **Cetak / Simpan PDF** dan gaya cetak **A4 MENDATAR** (`@page` ditulis di
`<style>` halaman — pelajaran yang sama dengan lembar cetak data guru/siswa).
- Kelas **`dokumen`** pada `<body>` (dari `situs_atas()`) menandai kedua halaman ini; SELURUH aturan
  cetak khusus di `assets/style.css` di-scope `body.dokumen ...` — **jangan dijadikan global**
  (aturan global ikut berlaku saat lembar lain dicetak/diukur).
- Bagan bisa LEBIH LEBAR daripada kertas → `initSkalaCetak()` (`assets/app.js`) memperkecil elemen
  ber-`data-cetak-lebar` memakai **`transform: scale()`** (BUKAN `zoom` — `zoom` tidak memengaruhi
  ukuran yang benar-benar tercetak) pada event `beforeprint`, plus kompensasi `margin-bottom`
  negatif (karena `transform` tidak mengurangi tinggi tata letak) dan dikembalikan pada `afterprint`.

### D. 🚨 JEBAKAN yang menggigit saat mengerjakan ini (semua sudah diperbaiki)
1. **`SELECT nilai FROM sekolah`** (tertulis pada seed) → “no such column: nilai”; migrasi
   ter-ROLLBACK sehingga penanda skema TIDAK naik padahal sebagian perubahan tampak terjadi.
   Untuk baris sekolah pakai `SELECT * FROM sekolah`.
2. **`sekolah_simpan()` punya daftar kolom sendiri** — kolom baru (kode_pos … tujuan) WAJIB
   ditambahkan di situ; kalau tidak, panel bilang “disimpan” tetapi nilainya diabaikan diam-diam.
3. **`hapus_baris()`/`geser_urutan()` memakai whitelist `TABEL_HAPUS_BOLEH`** — tabel `struktur`
   harus didaftarkan, kalau tidak tombol Hapus berpesan “dihapus” padahal tidak menghapus apa pun
   (sekarang kegagalan juga dilaporkan jujur).
4. **`$halamanDokumen` harus dihitung SEBELUM `situs_atas()`** (dipakai untuk kelas `<body>`),
   kalau tidak kelas `dokumen` selalu kosong dan seluruh gaya cetak tidak berlaku (pernah terjadi).
5. **Bagan yang lebih lebar dari wadah** tidak boleh dipusatkan dengan
   `flex + justify-content:center` di dalam wadah bergulir: sisi KIRI menjadi tidak terjangkau.
   Sekarang akar bagan memakai `inline-block` + `text-align:center` (mulai dari kiri, meluber kanan).
6. **`websk_ukur.js` sempat mengukur tata letak dalam MODE CETAK** (media tidak dikembalikan setelah
   mengukur lembar cetak). Sekarang media & lebar layar dikembalikan sebelum bagian berikutnya —
   tanpa itu, uji gagal palsu ketika ada aturan cetak baru.

### Uji
- `bash websk_uji.sh websk_profil_ui.js` → **52 pemeriksaan** BARU (Playwright): 19 baris identitas
  (**13 baris pokok bernomor urut 1–13** + 6 baris rincian tanpa nomor: Desa, Kecamatan, Kabupaten,
  Telepon, Email, Website) + label sesuai contoh + baris dua kolom (Kode Pos/Tanggal/Jarak),
  dua kolom sejajar,
  VISI + MISI/TUJUAN bernomor, tanda tangan, bagan ≥ 8 kotak dengan kotak teratas “Kepala Madrasah”,
  Komite bergaris putus-putus, blok merah, **garis penghubung benar-benar ada**, kotak tidak keluar
  ke kiri, kanvas bergulir, skala cetak otomatis + **PDF A4 mendatar (842×595)** dan bagan tidak
  terpotong, panel isian & bagan (16 medan, kolom tabel lengkap), menyimpan misi satu per baris lalu
  tampil di website, tambah & hapus kotak bagan dari panel, serta tanpa luapan di HP 390px.
- `bash websk_uji.sh websk_admin_ui.js` → **473 lulus, 0 gagal** (tanpa regresi).
- `bash websk_uji.sh websk_ukur.js` → **SEMUA UKURAN OK**.
- `bash websk_cek_live.sh` → **63 lulus** (migrasi v10 pada SALINAN data asli: kolom & tabel dibuat,
  jenis kedua menu dipulihkan, bagan 21 kotak, VISI/MISI terisi, dan **suntingan pemilik tidak
  ditimpa** saat migrasi diulang).

## v1.7.5 — HALAMAN PPDB: BARIS BIAYA DIHAPUS + PERSYARATAN BERNOMOR (permintaan pemilik)

Permintaan: *"Yang saya tandai warna merah dihapus, yang warna biru dibuat penomoran biar rapi
(1,2 dan seterusnya)"* (merah = baris **“Biaya pendaftaran: Gratis”**, biru = daftar **Persyaratan**).

1. **Baris “Biaya pendaftaran: …” DIHAPUS** dari `ppdb.php` (di bawah Jadwal & Ketentuan).
   Medan **“Biaya Pendaftaran” pada panel juga dihapus** (`admin/ppdb.php`) dan `ppdb_biaya`
   dikeluarkan dari daftar `$KUNCI` — menyisakan medan yang tidak dipakai hanya akan menyesatkan.
   ⚠️ **Nilai lama `pengaturan.ppdb_biaya` di basis data DIBIARKAN utuh** (tidak dihapus) bila suatu
   saat diperlukan lagi; biaya kini cukup dijelaskan pada teks “Informasi PPDB”.
2. **Daftar Persyaratan BERNOMOR 1, 2, 3, …** — markup memakai `<ol class="daftar-syarat bernomor">`
   dengan **nomor yang DICETAK PHP** pada `<span class="nomor">` (BUKAN CSS counter) supaya pasti
   tampil dan mudah diperiksa uji; gaya lingkaran emasnya `.daftar-syarat.bernomor .nomor` di
   `assets/style.css`. Tanda centang pada Persyaratan dihapus (digantikan nomor), sedangkan
   **Jadwal & Ketentuan TIDAK ikut dinomori** (tetap bertanda ikon kalender) karena tidak diminta.
   Satu baris pengaturan = satu nomor.
3. **Migrasi skema v9 — `db_ppdb_syarat_bernomor()`** (penanda `ppdb_syarat_nomor_v1`):
   nilai `ppdb_syarat` yang **masih satu baris** (data pemilik berisi 5 persyaratan dipisah koma)
   dipisah menjadi satu per baris agar nomornya benar-benar 1–5, bukan satu nomor panjang.
   Pengamannya sengaja berlapis: **hanya sekali jalan** (bila pemilik sengaja menggabungkannya
   kembali, migrasi tidak memisahkannya lagi), hanya bila **tidak ada baris baru**, memuat **≥ 2 koma**,
   **tidak ada potongan kosong**, dan tidak ada potongan > 160 karakter (penanda “bukan daftar”).
   Diuji pada SALINAN data asli: 5 baris hasil pemisahan + penanda tersimpan.

### Uji
- `bash websk_uji.sh websk_admin_ui.js` → **473 lulus, 0 gagal** (+7 pemeriksaan baru): daftar itu
  `<ol>` bernomor, **nomor tiap butir = 1..n dan benar-benar terlihat di layar**, tanda centang
  hilang, **baris “Biaya pendaftaran:” tidak muncul lagi** (dicocokkan dengan titik dua supaya tidak
  salah menangkap kalimat “tanpa dipungut biaya pendaftaran” pada teks Informasi PPDB), dan Jadwal &
  Ketentuan tetap bertanda ikon.
- `bash websk_uji.sh websk_ukur.js` → **SEMUA UKURAN OK**.
- `bash websk_cek_live.sh` → **46 lulus** (migrasi v9 pada salinan data asli; sidik baris seluruh
  tabel identik).
- Diperiksa langsung pada SITUS LIVE (1440px & 390px): nomor `1..5` tampil, tanpa luapan, tanpa galat
  konsol, `Biaya pendaftaran:` = tidak ada.

## v1.7.4 — PERAN EDITOR BERITA, TOMBOL SLIDER, BUKA/TUTUP PPDB, KARTU LANGKAH (permintaan pemilik)

Empat permintaan sekaligus:
1. *"Pada pengguna dan akun tambahkan peran yang khusus hanya mengisi berita."*
2. *"Pada slider beranda Ketika klik Daftar sekarang harusnya kehalaman pendaftaran bukan ke fasilitas."*
3. *"Pada pengaturan PPDB tambahkan fitur untuk menutup dan membuka pendaftaran. Ketika yang di
   aktifkan ditutup maka Ketika pengunjung mengklik Daftar sekarang yang muncul 'Mohon maaf
   pendaftaran murid baru belum dibuka'."*
4. *"Pada gambar yang saya beri warna merah agar dirapikan tulisannya sampai kesamping agar terlihat
   rapi"* (kartu langkah PPDB — semuanya menumpuk di SATU kartu sempit).

### A. Peran baru **Editor Berita** (kode peran `berita`)
- Daftar peran terpusat di **`peran_daftar()`** (`inc/helpers.php`) → pilihan pada
  `admin/pengguna.php` dibangkitkan dari situ (`admin`/`editor`/`berita`; nilai tak dikenal jatuh ke
  `admin`, jadi tidak mungkin naik peran lewat POST).
- **Daftar putih berkas**: `berita_berkas_boleh()` = `index.php`, `berita.php`, `berita_edit.php`,
  `berita_kategori.php`, `upload_editor.php` (untuk menyisipkan gambar ke berita), `logout.php`.
- **Penegakan di SATU titik**: `require_login()` memanggil `boleh_buka_berkas()` → berkas di luar
  daftar putih dijawab **403** (halaman pesan, bukan halaman kosong). Peran lain tidak dibatasi;
  pembatasan admin/editor tetap lewat `require_admin()`.
- Sidebar (`admin_menu()`) untuk peran ini **hanya** memuat Dashboard + Berita (Daftar/Tulis/Kategori),
  dan `admin/index.php` mencetak **dasbor ringkas** (jumlah berita, draf, terbit + Berita Terakhir +
  pintasan) supaya tidak ada kartu PPDB/galeri yang menyesatkan.
- `peran_label('berita')` = **“Editor Berita”**. Pengaturan akun bawaan (tidak boleh menonaktifkan/
  menghapus administrator terakhir) tidak berubah.

### B. Tombol “Daftar Sekarang” pada slider beranda (BUG NYATA)
Tautannya sejak awal sudah `ppdb.php`, tetapi **slide yang tidak aktif tetap menumpuk di atas slide
aktif** (`position:absolute` + `opacity:0`) sehingga tombol slide TERAKHIR (“Lihat Fasilitas” →
`fasilitas.php`) selalu yang menerima klik. Dibuktikan `document.elementFromPoint()` pada titik
tombol: elemen teratas = `fasilitas.php` untuk KETIGA slide.
Perbaikan (CSS, `assets/style.css`):
`.hero-slide{…pointer-events:none}` + `.hero-slide.aktif{opacity:1;position:relative;pointer-events:auto}`.
Uji: setiap tombol slide diperiksa dengan `elementFromPoint` (elemen teratas harus tombol itu
sendiri) dan diklik sungguhan → URL berakhir di `ppdb.php`.

### C. PPDB BUKA / TUTUP pendaftaran
- **Panel** (`admin/ppdb.php`): kartu **“Status Pendaftaran PPDB”** di paling atas berisi pil
  DIBUKA/DITUTUP + tombol pintasan **Tutup Pendaftaran** / **Buka Pendaftaran**
  (`aksi=buka|tutup` + CSRF, id form `#formTutupPpdb` / `#formBukaPpdb`) + tautan lihat halaman.
  Checkbox lama (`name=ppdb_buka`) TETAP ada (dipakai uji & formulir), dengan keterangan yang
  menjelaskan bahwa menghilangkan centang = MENUTUP.
- **Pesan pengunjung**: helper **`ppdb_tutup_pesan()`** = *“Mohon maaf pendaftaran murid baru belum
  dibuka.”* — dipakai di tiga tempat: (1) alert pada formulir di `ppdb.php` saat ditutup,
  (2) pesan galat bila ada yang mencoba POST saat ditutup, (3) **`<dialog id="dialogPpdbTutup">`**
  yang dicetak `situs_bawah()` HANYA saat pendaftaran ditutup.
- **`ppdb_tautan_atribut($tautan)`** menambahkan `data-ppdb-tutup="1"` pada tautan `ppdb.php` saat
  ditutup — dipakai tombol slide beranda, kartu PPDB beranda, dan tombol PPDB di bilah navigasi.
  **`initPpdbTutup()`** (`assets/app.js`) menampilkan dialog itu (bukan berpindah halaman);
  tombol “Mengerti”, klik latar gelap, dan Esc menutupnya. **Tanpa JavaScript** tautannya tetap
  membuka `ppdb.php` yang memuat pesan yang sama — tidak pernah jadi jalan buntu.

### D. Kartu langkah PPDB dirapikan (akar masalah: `potong()`)
- **Penyebab**: `admin/ppdb.php` menyimpan medan “satu per baris” memakai **`potong()`**, yang
  memakai `preg_replace('/\s+/')` sehingga SELURUH baris baru melebur menjadi spasi. Akibatnya
  Alur Pendaftaran (6 langkah) tersimpan sebagai SATU baris → halaman hanya memunculkan satu kartu
  raksasa di kolom pertama (persis tanda merah pada tangkapan layar), dan Persyaratan menjadi satu
  butir panjang.
- **Perbaikan**: helper baru **`potong_baris()`** (merapikan spasi/tab & baris kosong ganda, TAPI
  baris tetap) dipakai untuk `ppdb_isi`, `ppdb_syarat`, `ppdb_alur`, `ppdb_jadwal`.
- **Migrasi skema v8** — `db_perbaiki_baris_ppdb()` (penanda `ppdb_baris_v1`): mengembalikan baris
  teks bawaan yang sudah melebur, dan **HANYA bila isinya masih sama persis dengan teks bawaan**
  (dibandingkan tanpa peduli spasi) sehingga suntingan pemilik tidak pernah ditimpa. Diuji pada
  SALINAN basis data asli: Alur Pendaftaran kembali menjadi 6 baris, sedangkan nilai yang sudah
  disunting pemilik dibiarkan apa adanya.
- **Tata letak kartu langkah** (`assets/style.css`): nomor + judul dalam satu baris
  (`.langkah{display:flex;gap:14px}` + `.langkah .isi`), `line-height` dirapikan, petunjuk
  “Ikuti langkah di atas …” hanya dicetak **sekali** di bawah grid (`.langkah-ket`), bukan diulang
  pada setiap kartu.
  ⚠️ Persyaratan milik pemilik saat ini memang masih ditulis dalam SATU baris (teksnya berbeda dari
  bawaan sehingga sengaja tidak diubah) — agar tampil sebagai butir terpisah, sunting di panel dan
  tekan Enter tiap selesai satu persyaratan (keterangan ini sudah ditambahkan pada formulir).

### Uji
- `bash websk_uji.sh websk_admin_ui.js` → **466 lulus, 0 gagal** (+40 pemeriksaan baru): peran
  Editor Berita (pilihan peran, sidebar hanya Berita, 200 pada berita/kategori, 403 pada 6 halaman
  lain, menulis berita sungguhan sampai tampil di website), klik tombol slide sungguhan menuju
  `ppdb.php`, tutup/buka pendaftaran lewat pintasan + popup “belum dibuka” (dan tombol Mengerti),
  halaman PPDB saat ditutup, serta baris Alur Pendaftaran tetap utuh setelah disimpan dari panel.
- `bash websk_uji.sh websk_ukur.js` → **SEMUA UKURAN OK**.
- `bash websk_cek_live.sh` → **43 lulus** (migrasi skema v8 pada salinan data asli: sidik baris
  seluruh tabel identik, penanda versi dibaca dari `inc/config.php`, perbaikan baris PPDB berjalan
  dan tidak menimpa suntingan pemilik).

## v1.7.3 — HALAMAN DINAMIS LEBAR PENUH + VIDEO YOUTUBE 4 KOTAK SEBARIS (permintaan pemilik)

Permintaan: *"Yang ditandai warna merah dihapus agar tabel lebih luas dan rapi terlihat. Dan pada
bagian tumbail youtube yang tampil agar diperkecil dan bisa menjad 4 kesamping"* (tanda merah pada
tangkapan layar = **kolom sisi** halaman Data Alumni, Prestasi Siswa, Data Siswa, dan Guru & Tenaga
Kependidikan).

1. **KOLOM SISI halaman dinamis DIHAPUS** (`halaman.php`). Yang dibuang: kartu penjelasan induk
   (mis. “Kesiswaan — Pembinaan peserta didik dan prestasi”) dan kartu **“Menu Kesiswaan”**
   (daftar sub menu di sisi kanan), beserta pembungkus `.grid.g21` dan variabel `$anak`/`$saudara`
   yang hanya dipakai di sana. Isi halaman kini memakai **LEBAR PENUH**.
   * Jarak dari tepi konten ke tepi konten = `150px` dan lebar artikel **1140px**; tabel
     `.tabel-publik` yang sebelumnya **625px** kini **1076px** (ukuran terukur `websk_ukur.js`).
     Sisa 60px = padding `.artikel` (30px kiri+kanan) dan 4px dari garis `.tabel-wrap`.
   * **Tidak ada menu yang hilang**: sub menu tetap ada pada menu turun bilah navigasi
     (`.nav-anak`, mis. Kesiswaan → Data Siswa / Prestasi Siswa / Data Alumni) dan pada bilah
     navigasi HP.
   * Kelas `.sisi-kartu`/`.sisi-menu` **TETAP ADA di `assets/style.css`** karena masih dipakai
     `berita_detail.php`, `ppdb.php`, `ppdb_status.php`, dan `index.php` (kartu Sambutan) —
     jangan dihapus.
2. **Video YouTube pada halaman Galeri diperkecil & 4 kotak sebaris** (`assets/style.css`):
   `.video-grid` `repeat(2)` → **`repeat(4)`** dengan `gap:16px`, tulisan di dalam kotak diperkecil
   (`.video-kotak .isi{padding:10px 12px}`, `h3{font-size:.86rem}`, `p{font-size:.76rem}`), dan
   jumlah kolom menyesuaikan layar: **3 kolom ≤1000px, 2 kolom ≤700px, 1 kolom ≤420px**
   (sebelumnya hanya 2 kolom → 1 kolom ≤820px). Terukur pada 1440px: tiap kotak **273px**
   (dulu ~512px). Data video di basis data pemilik: **11 video** sehingga menjadi baris 4–4–3.

### Uji
- `bash websk_uji.sh websk_admin_ui.js` → **425 lulus, 0 gagal**. Pemeriksaan BARU di dalamnya:
  * halaman Data Siswa tidak lagi memuat kolom sisi (`kolomSisi === 0`) dan tabelnya memakai
    lebar penuh (artikel ≥ 1000px, tabel ≥ lebar isi artikel − 8px);
  * sub menu Kesiswaan kini diperiksa dari **menu turun navigasi** (`.nav-anak`), bukan lagi dari
    `.sisi-menu` yang sudah dihapus;
  * halaman Galeri: **video 4 kotak sebaris**, kotak ≤300px, tersusun sebaris saat videonya >1,
    dan pada HP 390px menjadi 1–2 kolom — keduanya tanpa luapan mendatar.
- `bash websk_uji.sh websk_ukur.js` → **SEMUA UKURAN OK**; angka tabel kelima halaman (Data Siswa,
  Data Alumni, 4 halaman prestasi) naik dari 625px → **1076px** dan pemeriksaan
  “tabel tidak melebihi area artikel” tetap lulus.
- `bash websk_cek_live.sh` → **39 lulus** (tidak terpengaruh; hanya tampilan yang berubah).


## v1.7.2 — KAKI HALAMAN + ANGKA ALUMNI (permintaan pemilik)

Permintaan: *"Yang diberi tanda merah dihapus. Kemudian pastikan jumlah alumni di MA. STM dalam
Angka terisi, karena sekarang masih 0 padahal sudah alumni yang dimasukkan"* (dua tanda merah pada
kaki halaman: tombol **Masuk Admin** dan kata **Website** pada baris versi).

1. **Tombol “Masuk Admin” pada kaki halaman DIHAPUS** (`inc/layout.php`, kolom “Ikuti Kami”).
   Panel admin tetap dapat dibuka langsung di `…/web-sekolah/admin/`; tautan itu memang hanya
   jalan pintas, bukan pengaman. Jangan dikembalikan tanpa permintaan.
2. **Kata “Website” GANDA dihapus.** Baris kaki halaman dahulu `Website <?= APP_NAME ?> v…`
   sedangkan `APP_NAME` = **“Website Sekolah”** → tertulis **“Website Website Sekolah v1.7.1”**.
   Sekarang `<?= APP_NAME ?> v<?= APP_VERSION ?>` → **“Website Sekolah v1.7.2”**.
3. **ANGKA ALUMNI pada bagian “<nama lembaga> dalam Angka”** (`index.php`, `data-bagian="statistik"`)
   selalu 0 walaupun admin sudah mengisi/mengimpor **Data Alumni**.
   Sebabnya: `statistik()` hanya menghitung `siswa WHERE status = 'lulus'` (0 baris) sedangkan
   isian “Jumlah Alumni (manual)” masih 0; 66 baris pada tabel **`alumni`** (hasil ambil dari web)
   tidak pernah dihitung.
   Perbaikan: helper baru **`alumni_jumlah_gabungan()`** (`inc/helpers.php`, ada di dekat fungsi
   alumni lain, hasilnya di-cache per permintaan) = **`count(alumni_gabungan())`** — memakai sumber
   yang SAMA dengan halaman publik Data Alumni (data kelulusan + tabel `alumni`, sudah dibersihkan
   dari data ganda). `statistik()` memakainya, dan isian manual `alumni_manual` tetap berlaku
   **hanya bila angkanya lebih besar** (mis. lulusan lama yang belum terdata) sehingga perilaku lama
   tidak rusak. Terverifikasi pada **salinan data asli**: kotak Alumni = **66** dan halaman
   Data Alumni juga “66 alumni ditemukan” (sebelumnya 0).
   Teks bantuan pada `admin/identitas.php` (tab Teks Beranda & Statistik) ikut disesuaikan agar
   menjelaskan bahwa angka Alumni dihitung dari kelulusan **+** Data Alumni.
   ⚠️ Jangan menghitung alumni langsung dari `siswa` saja di tempat lain — pakai
   `alumni_jumlah_gabungan()` supaya angka di statistik, dasbor admin, dan halaman Data Alumni
   selalu sama.

### Uji
- `bash websk_uji.sh websk_admin_ui.js` → **416 lulus, 0 gagal** (tidak ada yang bergantung pada
  tombol Masuk Admin / baris versi lama).
- `bash websk_uji.sh websk_ukur.js` → **SEMUA UKURAN OK**.
- `bash websk_cek_live.sh` → **39 lulus** (migrasi pada salinan data asli; tabel `alumni` = 66 baris,
  semuanya bersumber `web`).

---

## v1.19.1 — AUTO-ISI WAKTU PADA FORMULIR JADWAL (tanpa perubahan skema)

Permintaan pemilik (tangkapan layar kotak "Waktu" pada formulir *Tambah Baris Jadwal*):
> *"Bagian input waktu buat otomatis mengikuti hari yang sama waktunya. agar penginputan menjadi lebih
> cepat dan tidak input manual waktu satu persatu."*

### A. Perilaku yang berlaku sekarang
- **Saat MENAMBAH baris** (`admin/kurikulum.php?tab=jadwal&hari=Sabtu`), kolom **Waktu** dan
  **Jam Ke** langsung **terisi sendiri** dengan nilai **baris TERAKHIR hari itu** (baris dengan
  `urutan` terbesar). Jadi menambah beberapa baris jadwal pada hari yang sama tidak perlu mengetik
  waktu berulang-ulang.
- **Hari diganti pada formulir** → isian Waktu/Jam Ke **ikut berganti** mengikuti hari yang dipilih
  (skrip `initJadwalWaktu()` pada `admin/admin.js`). Bila hari itu **belum punya baris jadwal**,
  isian **dibiarkan apa adanya** (tidak menghapus yang sudah diketik) dan keterangannya berbunyi
  *"Belum ada baris jadwal pada hari ini — isi waktunya satu kali…"*.
- Kolom Waktu memakai **`<datalist id="jadwalWaktuSaran">`** berisi **seluruh waktu berbeda** yang
  sudah dipakai pada hari itu (urut kemunculan) → pengelola bisa **memilih** waktu yang sudah ada
  tanpa mengetik. Isinya diperbarui setiap Hari diganti.
- **Mode UBAH (`?edit=ID`) TIDAK diisi otomatis** (`data-waktu-otomatis="0"`): isian memakai waktu
  baris yang sedang diubah dan tidak berubah saat Hari diganti — mengubah satu baris tidak boleh
  diam-diam mengganti waktunya. Datalist saran tetap tersedia.
- Tanpa JavaScript **halaman tetap bekerja**: nilai awal Waktu/Jam Ke + isi `<datalist>` sudah dicetak
  server; skrip hanya melengkapi saat Hari diganti.

### B. Bagian kode
- **`kurikulum_jadwal_waktu_hari()`** (`inc/kurikulum.php`) → peta
  `hari => ['waktu' => terakhir, 'jam_ke' => ..., 'daftar' => [semua waktu berbeda]]`.
  Satu query `ORDER BY hari, urutan, id` ditimpa terus sehingga nilai akhir = baris terakhir; baris
  tanpa waktu dilewati. **Jangan** menghitung ini di halaman — helper ini satu-satunya sumbernya.
- **`admin/kurikulum.php`** (kartu `#formJadwal`): formulir membawa
  `data-waktu-hari="<JSON peta>"` + `data-waktu-otomatis="1|0"`, isian
  `#jadwal_waktu` (dengan `list="jadwalWaktuSaran"`), `#jadwal_jam`, keterangan `#jadwalWaktuInfo`,
  dan `<datalist id="jadwalWaktuSaran">` tepat sesudah `<datalist id="jadwalGuruSaran">`.
- **`admin/admin.js`** → `initJadwalWaktu()` (IIFE, vanilla, **tanpa** penolong `$$`): membarui
  isian, datalist, dan keterangan saat `#jadwal_hari` berubah; dipanggil juga sekali saat halaman
  dimuat.
  ⚠️ JSON pada atribut ditulis dengan `e(json_encode(…))` sehingga tanda kutipnya ter-escape dan
  `getAttribute()` mengembalikan JSON utuh.

### C. Uji
- `bash websk_uji_kurikulum.sh` (**200**) — bagian **6b**: hari tanpa baris → kolom kosong & tanpa
  saran; setelah dua baris disimpan (07.00-07.45 lalu 08.30-09.15) formulir hari itu terisi
  **08.30-09.15 / jam 2**, peta `data-waktu-hari` benar, datalist memuat **kedua** waktu, hari lain
  (Selasa) tetap kosong, mode UBAH memakai waktu barisnya sendiri & tidak diisi otomatis, dan baris
  **kegiatan** (jam ke kosong) menjadi acuan terakhir tanpa memaksa angka.
  ⚠️ Catatan fixtur: hari **Selasa** adalah satu-satunya hari yang masih kosong pada titik itu
  (Sabtu & Senin dari seed, Jumat/Rabu/Kamis/Minggu dari pengujian sebelumnya) — memakai hari lain
  membuat uji "hari tanpa baris" gagal palsu.
- `bash websk_uji_kurikulum_ui.sh` (+ `.js`, **77**) — 15 pemeriksaan Playwright baru: nilai awal
  otomatis hari Sabtu (13.30-14.00, jam kosong karena baris terakhir kegiatan), ganti Hari ke Senin
  → isian & saran ikut berganti (07.00-07.45 / jam 1), ganti ke Selasa → isian dibiarkan + keterangan,
  dan mode UBAH tidak ikut berubah saat Hari diganti.
  ⚠️ Tautan "Ubah" di panel bersifat **relatif** (`kurikulum.php?…`) sehingga uji harus membukanya
  dari `/admin/…`, bukan akar situs.
