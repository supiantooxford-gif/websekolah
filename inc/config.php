<?php
/**
 * Konfigurasi dasar WEBSITE SEKOLAH/MADRASAH.
 *
 * Aplikasi SATU sekolah/madrasah: identitas sekolah, seluruh isi halaman, menu, slider,
 * berita, agenda, galeri, data guru/siswa, dan PPDB diisi dari akun admin.
 *
 * Unggahan gambar (slider, galeri, berita, logo, foto siswa/guru, berkas PPDB) TIDAK disimpan di
 * disk server ini, melainkan diteruskan ke media proxy platform dari sisi SERVER; yang disimpan
 * ke basis data hanya URL hasilnya. Token unggah dibaca dari berkas .vibecoder-media-token.
 */
declare(strict_types=1);

define('APP_NAME', 'Website Sekolah');
define('APP_SUB', 'Website Resmi Sekolah/Madrasah');
// 1.13.0 = MENU "SISWA → KOHORT SISWA" DIISI TABEL ANALISIS KOHORT + GRAFIKNYA (skema v29) —
//   permintaan pemilik: "Pada bagian Kohort Siswa isi dengan tabel seperti pada contoh, dibawah tabel
//   tambahkan analisis grafiknya berdasarkan tabel tersebut (yang di tampilkan adalah 5 tahun
//   terakhir. Ketika di input tahun ajaran baru otomatis tahun ajaran paling lama akan hilang dan
//   posisi bergeser. tabel di isi oleh super admin di halaman super admin."
//   Pilihan pemilik (JANGAN diubah tanpa bertanya): (1) kolom tabel PERSIS seperti contoh —
//   Tahun Ajaran · PMB (Pendaftar, Diterima) · Kelas X/XI/XII (Jumlah, Mutasi Masuk, Mutasi Keluar,
//   Tinggal Kelas, Naik Kelas, Drop Out); (2) yang ditampilkan **5 tahun ajaran TERBARU**
//   (`KOHORT_TAHUN_TAMPIL`), tahun paling lama otomatis keluar dari tabel saat ada tahun baru
//   (barisnya TIDAK dihapus, hanya tidak dicetak); (3) di bawah tabel ada **analisis grafik**
//   (PMB, jumlah siswa per tingkat, naik/tinggal kelas & drop out, mutasi) + ringkasan angkanya.
//   (a) TABEL BARU **`kohort`** (satu baris = satu tahun ajaran, 20 kolom angka) + indeks; hanya
//       `CREATE TABLE`/`CREATE INDEX` — tidak ada tabel lama yang dibangun ulang.
//   (b) JENIS HALAMAN BARU **`kohort`** (`halaman.php` → `kohort_papan()` di `inc/kohort.php`);
//       migrasi `db_menu_kohort()` mengubah sub menu **Kohort Siswa** (jenis `halaman`) menjadi
//       `kohort` — hanya baris yang masih `halaman` yang disentuh.
//   (c) PANEL SUPER ADMIN **`admin/kohort.php`** (`require_admin()`): isian tahun ajaran + 20 angka,
//       daftar seluruh tahun yang tersimpan (ditandai mana yang tampil di website), ubah & hapus.
//   Uji: `bash websk_uji_kohort.sh` + `bash websk_uji_kohort_ui.sh`.
// 1.17.1 = VIDEO YOUTUBE BARU OTOMATIS PALING ATAS (tanpa perubahan skema).
//   Permintaan pemilik: *"Video atau link youtube yang di simpan paling terbaru letakkan otomatis
//   paling atas"*.
//   (a) Penolong baru **`urutan_teratas()`** (`inc/admin_helpers.php`) = `MIN(urutan) − 10`
//       (tabel kosong → 10). Berbeda dari `urutan_baru()` yang menaruh baris baru di PALING BAWAH.
//   (b) `admin/galeri.php` aksi `video_simpan` (video BARU) memakai `urutan_teratas('galeri_video')`
//       → video paling baru langsung berada paling atas, baik pada tabel panel maupun pada bagian
//       **"Video Kegiatan"** halaman galeri dan slider video beranda (keduanya membaca
//       `ORDER BY urutan, id` lewat `video_publik()`), tanpa mengubah halaman publik sama sekali.
//   (c) Tombol ▲▼ tetap bekerja seperti biasa (hanya menukar nilai `urutan`), dan nilai `urutan`
//       tidak pernah dicetak sebagai angka sehingga nilai negatif tidak terlihat pengguna.
//       Mengubah video yang SUDAH ADA tidak mengubah urutannya.
// 1.17.0 = IKON ASISTEN MEMAKAI FOTO (tanpa perubahan skema).
//   Permintaan pemilik: *"Bisakah ikon chat asisten diganti? Jika bisa ganti dengan gambar orang
//   yang saya kirim sebagai ikon asisten web, buat agak sedikit besar atau di sesuaikan agar rapi"*.
//   (a) FOTO ASISTEN DAPAT DIUNGGAH admin pada menu **FAQ Asisten** → kartu baru **"Foto Asisten
//       (Ikon Obrolan)"** (`admin/faq.php`, aksi POST `foto_asisten`): pratinjau BULAT 104px, unggah
//       JPG/PNG/GIF/WEBP/SVG (maks 15 MB, lewat media proxy) dan centang hapus. URL-nya disimpan pada
//       `pengaturan.asisten_foto`; BELUM diunggah = ilustrasi bawaan `assets/asisten.svg`.
//   (b) Foto itu dipakai SERENTAK sebagai: ikon **tombol obrolan mengambang**, avatar **kepala kotak
//       obrolan**, dan avatar **setiap balasan asisten** (kotak mengambang maupun halaman `asisten`).
//       Satu sumber: `asisten_foto()`, `asisten_foto_src()`, `asisten_ava()` (`inc/asisten.php`) —
//       jangan menulis ikon/URL asisten sendiri di halaman lain. Skrip mengambil alamat yang sama
//       dari `data-ava` pada tombol (`initAsisten()` di `assets/app.js`).
//   (c) UKURAN & KERAPIAN: tombol mengambang 56px → **72px** (64px di HP ≤520px) dengan cincin emas,
//       foto bulat penuh (`object-fit:cover; object-position:center 15%` supaya wajah tidak terpotong),
//       titik "online" hijau, efek terangkat saat disorot, dan posisi kotak obrolan disesuaikan
//       (`bottom:102px`, HP `92px`) agar tidak menutupi tombol.
//   (d) Ilustrasi bawaan baru **`assets/asisten.svg`** (petugas ber-headset, gaya datar) — dipakai
//       selama admin belum mengunggah foto, jadi ikon tidak kembali menjadi balon obrolan polos.
// 1.16.2 = PAGINASI PANEL ADMIN DIPERBAIKI + CETAK DATA ALUMNI (tanpa perubahan skema).
//   Permintaan pemilik: (1) *"Yang saya tandai warna merah itu tidak berfungsi ketika di klik"*
//   (tombol halaman 2/3/4 & "Selanjutnya" pada panel Data Alumni); (2) *"tambahkan fitur untuk cetak
//   data alumni pada akun admin"*.
//   (a) 🚨 AKAR MASALAH paginasi: `hal_url()` memakai `rute_kanonik()`, yang pada ALAMAT BERPREFIKS
//       (`/web-sekolah/…`) menghasilkan **`admin/alumni.php`**. Tautan RELATIF itu dibaca peramban
//       dari DALAM folder `/admin/` → `/web-sekolah/admin/admin/alumni.php` → **404**. Karena itu
//       `hal_url()` kini mendeteksi halaman panel (`…/admin`) dan membangun tautan dari **nama
//       berkasnya saja** (`alumni.php?hal=2`). Perbaikan ini berlaku untuk SEMUA halaman panel yang
//       memakai `paginasi()` (alumni, guru, siswa, berita, pesan, log, pendaftar PPDB), sedangkan
//       paginasi halaman PUBLIK (alamat rapi) tidak berubah.
//   (b) Lembar cetak baru **`admin/cetak_alumni.php`** (A4 MENDATAR): No, Nama Alumni, NIS, NISN,
//       Jenis Kelamin, Tahun Masuk, Tahun Keluar, Keterangan, Sumber + baris JUMLAH (L/P) + tanda
//       tangan kepala di kanan; **mengikuti saringan** yang aktif (cari/tahun/sumber), `?auto=1`
//       mencetak otomatis, dan dibungkus wadah bergulir agar tidak meluber di HP. Tombol
//       **"Cetak Data Alumni"** ditambahkan pada kepala kartu halaman Data Alumni.
//       Hak akses: Administrator & akun Editor (sama dengan halaman Data Alumni); peran `berita` 403.
//   Uji: `bash websk_uji_alumni_cetak.sh` (68) + `bash websk_uji_alumni_cetak_ui.sh` (48).
// 1.16.1 = MEMPERBAIKI TAMPILAN POPUP DATA GURU. Laporan pemilik: *"saat diklik kartu guru tampilan
//   seperti yang saya upload (tidak rapi)"* — pada tangkapan layarnya dialog tampil sebagai KOTAK DI
//   DALAM artikel (menumpuk di bawah kartu-kartu guru, fotonya besar) karena dialog yang dicetak
//   server dalam keadaan `open` (jalur cadangan `?guru=ID` / pratinjau tanpa JavaScript) TIDAK berada
//   di "top layer" sehingga ikut alur halaman.
//   (a) CSS: `dialog.gd-popup[open]` kini dipaksa **position:fixed, terpusat (inset:0;margin:auto),
//       max-height:calc(100vh-40px), overflow:auto, z-index:1200**, dan untuk dialog NON-modal
//       ditambah latar gelap + kunci gulir lewat `html:has(dialog.gd-popup[open]:not(:modal))`.
//       Ukuran foto popup juga dikunci (60×74px, `max-width/max-height`), tidak bisa membesar.
//   (b) JS: dialog yang sudah ber-`open` **ditutup lalu di-`showModal()`** (`bukaModal()`) — baik saat
//       halaman dibuka dengan `?guru=ID` maupun saat kartu diklik — jadi selalu menjadi modal rapi.
//   (c) Versi aplikasi dinaikkan agar CSS/JS lama di cache peramban tidak dipakai lagi.
//   Uji: `bash websk_uji_guru_popup.sh` (94) + `bash websk_uji_guru_popup_ui.sh` (66).
// 1.16.0 = DATA GURU: TMT + KETERANGAN (Satmikal/Non Satmikal), KARTU GURU DIKLIK → POPUP DATA,
//   dan TOMBOL "TABEL" untuk menampilkan SEMUA guru dalam bentuk tabel (skema v33).
//   Permintaan pemilik: (1) *"Yang saya tandai warna merah tambahkan inputan untuk: TMT Guru (format
//   tanggal-bulan-tahun), Keterangan (Satmikal atau Non Satmikal)"*; (2) *"Pada halaman data guru, buat
//   kartu guru bisa diklik, dan Ketika diklik akan menampilkan Popup Data: Nama Lengkap, Jenis
//   Kelamin, Jabatan, Telepon, Masa Kerja (dihitung dari tanggal TMT hingga aktif sekarang…)"*;
//   (3) *"…tambahkan fitur tampilkan dalam bentuk tabel … isinya sama dengan popup data guru."*
//   (a) Kolom `guru.tmt` + `guru.keterangan` (db_add_column) — pilihan Keterangan TETAP.
//   (b) Modul **`inc/guru.php`**: `guru_masa_kerja()` (tahun/bulan/hari dari TMT), `guru_tmt_teks()`,
//       `guru_keterangan_sah()`, `guru_keterangan()`, dan **`guru_blok_tampilan()`** (kontrol
//       Kartu/Tabel + dialog popup + tabel semua guru).
//   (c) Halaman publik Tenaga Pendidik: kartu guru dapat DIKLIK (dialog popup; tanpa JavaScript
//       membuka `?guru=ID`), dan tombol **Tabel** menampilkan seluruh guru dalam tabel berisi data
//       yang sama (nama, jenis kelamin, jabatan, telepon, masa kerja) + TMT & keterangan.
//   (d) Panel `admin/guru.php`: isian **TMT** (`<input type="date">`) & **Keterangan** pada form
//       Tambah/Ubah; kolom TMT, Masa Kerja, dan Keterangan ikut ditampilkan pada daftar panel.
//   Uji: `bash websk_uji_guru_popup.sh` + `bash websk_uji_guru_popup_ui.sh`.
// 1.15.0 = KARTU PRESTASI SISWA PER KELAS (skema v32) — permintaan pemilik: *"Bagian yang saya tandai
//   warna merah buat kartu prestasi siswa per kelas. Isinya peringkat 1-3 di setiap kelas. Yang
//   ditampilkan adalah peringkat 1-3 dikelas, filter setiap tahun ajaran dan semesternya. Peringkat
//   berisi Foto, Kelas, Nilai Rata-rata. Data di isi oleh super admin atau editor (Yelfis
//   Djamaludin)"*.
//   (a) TABEL BARU **`prestasi_kelas`** — satu baris = satu peringkat pada satu kelas di satu periode
//       (`UNIQUE (ta, semester, kelas_id, peringkat)`), kolom `nilai` = rata-rata, `foto` = URL media
//       proxy. Hanya `CREATE TABLE`/`CREATE INDEX`.
//   (b) HALAMAN PUBLIK: blok **`#prestasiKelas`** dicetak pada halaman **Prestasi Siswa** TEPAT DI
//       ATAS deskripsi/tabel prestasi (area yang ditandai pemilik) dengan **filter Tahun Ajaran &
//       Semester** (`?pk_ta=&pk_sem=`, otomatis memakai periode berjalan bila ada datanya) dan kartu
//       per kelas berisi peringkat 1-3 (foto, nama, kelas, nilai rata-rata).
//   (c) PANEL: **`admin/prestasi_kelas.php`** — boleh dibuka **Administrator DAN akun Editor**
//       (`require_kelola_prestasi_kelas()`), menunya di grup *Kesiswaan & Kepegawaian*.
//   Modul bersama: `inc/prestasi_kelas.php` (fungsi `pk_*`); gaya `.pk-*` di `assets/style.css`.
//   Uji: `bash websk_uji_prestasi_kelas.sh` + `bash websk_uji_prestasi_kelas_ui.sh`.
// 1.14.3 = KOHORT SISWA: KOTAK "REKAPAN TAHUN AJARAN LAIN" DIPINDAH KE BAGIAN BAWAH (tanpa
//   perubahan skema) — permintaan pemilik: *"Yang saya tandai warna merah posisinya pindahkan ke yang
//   saya tandai warna biru, agar tulisan dibawah judul Analisis Kohort Siswa MA. STM terlihat rapi"*.
//   Kotak itu tidak lagi berada di samping keterangan pada kepala kartu, melainkan **di bagian bawah
//   halaman** (setelah kartu analisis grafik) dan **selebar kartu**. Keterangan di bawah judul kini
//   memakai lebar penuh kartu (`max-width:100%`) sehingga tampak rapi. Isi & perilakunya tidak
//   berubah: tetap hanya tombol tahun ajaran, dan tabel rekapan + grafiknya muncul lewat `?arsip=`.
//   Uji: `bash websk_uji_kohort.sh` (165) + `bash websk_uji_kohort_ui.sh` (103).
// 1.14.2 = KOHORT SISWA: KOTAK REKAPAN BERISI **TOMBOL TAHUN AJARAN** (tanpa perubahan skema) —
//   permintaan pemilik: *"Yang saya tandai warna merah jangan langsung ditampilkan langsung dalam
//   bentuk tabel. Tapi hanya tombol tahun ajaran. dan jika diklik akan muncul tabel rekapan +
//   grafiknya"*.
//   (a) Isi kotak `#kohortArsip` kini HANYA **tombol/chip tahun ajaran** (`.kohort-arsip-tombol`),
//       satu tombol per tahun arsip — tidak ada tabel di dalam kotak.
//   (b) Tombol adalah TAUTAN `?arsip=<tahun>#kohortArsipDetail` (bekerja tanpa JavaScript). Saat
//       dipilih, server merender kartu **`#kohortArsipDetail`** di atas tabel utama berisi
//       4 kartu ringkas + **dua tabel rekapan** (`#tblArsipPmb`, `#tblArsipTingkat`) + **tiga grafik**
//       (`chArsipAlur`, `chArsipHasil`, `chArsipLulus` — batang & donat). Tombol tahun aktif
//       tersorot (`aria-current`), tersedia tombol **Tutup Rekapan**.
//   (c) `?arsip=` yang tidak dikenal DIABAIKAN (200, tanpa kartu rekapan) — tidak pernah halaman rusak.
//   Uji: `bash websk_uji_kohort.sh` (164) + `bash websk_uji_kohort_ui.sh` (103).
// 1.14.1 = KOHORT SISWA: REKAPAN TAHUN AJARAN YANG TIDAK MASUK TABEL (tanpa perubahan skema) —
//   permintaan pemilik: *"Tahun Ajaran yang sudah tidak masuk di dalam tabel letakkan di tanda merah,
//   agar pembaca bisa melihat rekapan pada tahun ajaran yang tidak ditampilkan di tabel. Sehingga
//   dari awal semua datanya ada dan bisa dilihat."*
//   Kotak **`#kohortArsip`** ("Rekapan Tahun Ajaran Lain") dicetak pada kepala kartu — di SAMPING
//   KANAN keterangan (persis area yang ditandai merah pemilik), di ATAS tabel — berisi tabel
//   **Tahun · Pendaftar · Diterima · Siswa · Lulus · Drop Out** untuk setiap tahun ajaran yang sudah
//   di luar `KOHORT_TAHUN_TAMPIL` terbaru. Datanya dari fungsi baru **`kohort_arsip()`**
//   (`inc/kohort.php`) — jadi seluruh tahun ajaran tetap dapat dilihat walau tabel hanya memuat 5
//   tahun terbaru, dan kotak itu otomatis tidak dicetak bila seluruh tahun sudah masuk tabel.
//   Uji: `bash websk_uji_kohort.sh` (142) + `bash websk_uji_kohort_ui.sh` (85).
// 1.14.0 = KOHORT SISWA: KOLOM KELULUSAN + AKSES AKUN EDITOR + KAKI HALAMAN TANPA "MENU" (skema v31)
//   Permintaan pemilik: (1) *"Akun Editor (Yelfis Djamaludin) berikan akses untuk menginput Kohort
//   Siswa"*; (2) *"Tabel Kohort pada tampilan ditambahkan Kelulusan, seperti yang di upload. Untuk
//   grafiknya disesuaikan Kembali karena tambah kolomnya."*; (3) *"Menu yang dibagian footer yang
//   saya tandai warna biru dihapus saja"*.
//   (a) **GRUP KOLOM "LULUS"** pada tabel kohort: Jumlah Lulus, Kuliah, Bekerja, Lainnya (4 kolom
//       angka baru di tabel `kohort`, ditambahkan lewat `db_add_column` → baris lama bernilai 0,
//       tidak ada angka yang ditebak). Kepala kolomnya berwarna seperti contoh pemilik
//       (Lulus biru, Jumlah Lulus hijau, Kuliah ungu muda, Bekerja oranye muda, Lainnya kuning).
//   (b) **GRAFIK DISESUAIKAN**: ditambah grafik kelima **`chKohortLulus`** (Jumlah Lulus + Kuliah +
//       Bekerja + Lainnya), kartu ringkasan menjadi **lima** (termasuk **Total Lulus**), dan kalimat
//       analisis menyebut angka kelulusan beserta persentase kuliah/bekerja/lainnya.
//   (c) **AKSES AKUN EDITOR**: penjaga `admin/kohort.php` diganti dari `require_admin()` menjadi
//       **`require_kelola_kohort()`** = Administrator **atau peran `editor`** (pola yang sama dengan
//       Layanan PIP). Peran `berita` (Editor Berita) tetap **403**.
//   (d) **KAKI HALAMAN**: kolom **"Menu"** (daftar 6 menu utama) DIHAPUS dari `situs_bawah()`
//       (`inc/layout.php`) — kaki halaman kini tiga kolom (identitas, Kontak Kami, Ikuti Kami).
//       Aturan CSS `.kaki-menu*` sengaja dibiarkan (tidak dipakai) agar mudah dikembalikan.
//   Uji: `bash websk_uji_kohort.sh` (124) + `bash websk_uji_kohort_ui.sh` (74).
// 1.8.4 = DAFTAR PUBLIK DIBERI HALAMAN (10 baris) + TOMBOL "SELANJUTNYA" (tanpa perubahan skema) —
//   permintaan pemilik: "Data inventaris barang ditampilkan 1-10 dan ditambahkan tombol selanjutnya.
//   Begitu juga laporan data buku, laporan pengunjung, laporan peminjaman, laporan pengembalian dan
//   daftar penerima PIP."
//   (a) SATU konstanta **`PUBLIK_PER_HALAMAN`** (inc/config.php) + penolong bersama pada
//       inc/helpers.php: **`paginasi_jumlah()`**, **`paginasi_halaman()`** (nomor halaman dijepit,
//       jadi `?hal=999` menampilkan halaman terakhir — bukan tabel kosong), **`paginasi_potong()`**,
//       dan **`paginasi_info()`** ("Menampilkan 1–10 dari 68 data"). Tautan halaman memakai
//       `paginasi()` + `hal_url()` yang sudah ada (saringan aktif ikut terbawa, karena `hal_url()`
//       mempertahankan query string halaman).
//   (b) Daftar yang dipaginasi: **Daftar Inventaris Barang** (inc/sarpras.php), **empat Laporan
//       Perpustakaan** (inc/perpustakaan.php), dan **Daftar Penerima PIP** (inc/pip.php).
//       Angka ringkasan/kaki tabel (jumlah unit, total suara/sah, total nominal PIP) tetap dihitung
//       dari SELURUH baris — hanya baris yang DITAMPILKAN yang dipotong per halaman.
//   Uji: `bash websk_uji_paginasi.sh` (baru) + penyesuaian `websk_uji_sarpras.sh`,
//   `websk_uji_perpus.sh`, `websk_uji_pip.sh`, serta uji UI-nya.
// 1.8.3 = MENU "INFORMASI → E-VOTING OSIM" DIISI PAPAN HASIL REAL-COUNT (skema v21) —
//   permintaan pemilik: "Pada bagian e-Voting OSIM isi dengan Hasil Real-Count pada aplikasi
//   e-Voting (Khusus MA. Sirojuth Tholibiin Mootilango)."
//   Pilihan pemilik (JANGAN diubah tanpa bertanya): (1) **mengikuti setelan aplikasi e-Voting** —
//   bila panitia belum membuka hasil ke publik, halaman hanya menampilkan keterangan "hasil belum
//   dibuka" (tanpa angka); (2) angkanya **berjalan sendiri setiap 5 detik** (`VOTING_POLLING`).
//   (a) JENIS HALAMAN BARU `voting` (`halaman.php` → `voting_papan()` di `inc/voting.php`).
//   (b) SUMBER DATA dibaca LANGSUNG & HANYA-BACA dari basis data aplikasi **e-Voting OSIM**
//       (`VOTING_DB_FILE` = `../e-voting-osis/data/voting.sqlite`, `mode=ro`, cadangan
//       `query_only=1`). Yang dibaca hanya tabel `pengaturan`, `paslon`, `pemilih` (hanya JUMLAH),
//       dan `suara` — data pemilih/token tidak pernah ditampilkan sehingga kerahasiaan pilihan
//       tetap terjaga.
//   (c) PENYEGARAN OTOMATIS memakai endpoint baru **`voting_hasil.php`** (JSON, di akar aplikasi)
//       + skrip **`window.SVV`** (`assets/app.js`): angka, bilah, legenda, dan kedua grafik
//       diperbarui tanpa memuat ulang halaman.
//   (d) MIGRASI v21 (`db_menu_voting()`) mengubah sub menu **e-Voting OSIM** (jenis `halaman`)
//       menjadi `voting`; hanya baris yang masih `halaman` yang disentuh.
//   (e) Aset: **Chart.js 4.4.1** (sudah tersedia) + gaya blok "INFORMASI E-VOTING OSIM"
//       `assets/style.css` (kelas `.vt-*`).
//   Uji: `bash websk_uji_voting.sh` + `bash websk_uji_voting_ui.sh`.
// 1.8.2 = MENU "INFORMASI → SUPERVISI AKADEMIK" DIISI DASHBOARD APLIKASI SUPERVISI (skema v20) —
//   permintaan pemilik: "Pada bagian menu informasi bagian supervisi akademik buat isinya tampilan
//   dashboard aplikasi supervisi (Khusus MA. Sirojuth Tholibiin Mootilango), Supervisi terbaru,
//   komentar dan hasil tinjauan dokumen tidak perlu ditampilkan. Pengunjung hanya bisa melihat
//   tetapi tidak bisa mencetak. Tampilkan Filter Tahun Ajaran dan Semesternya."
//   Pilihan pemilik (JANGAN diubah tanpa bertanya): (1) kartu **Peringkat Guru** ditampilkan APA
//   ADANYA (nama guru + nilai); (2) **Progres Pengiriman Dokumen** tidak ditampilkan; (3) tombol
//   aksi dashboard (Nilai/Daftar/Rekap/Mulai Supervisi) DIHILANGKAN; (4) Supervisi Terbaru,
//   Komentar Pengawas, dan Hasil Tinjauan Dokumen tidak ditampilkan; (5) pengunjung hanya melihat —
//   TIDAK bisa mencetak.
//   (a) JENIS HALAMAN BARU `supervisi` (`halaman.php` → `supervisi_papan()` di `inc/supervisi.php`).
//   (b) SUMBER DATA dibaca LANGSUNG & HANYA-BACA dari basis data aplikasi **Supervisi Guru**
//       (`SUPERVISI_DB_FILE` = `../supervisi-guru/data/supervisi.sqlite`, `mode=ro`, cadangan
//       `query_only=1`). Sekolah pemilik data = `SUPERVISI_SEKOLAH` (MA. Sirojuth Tholibiin
//       Mootilango) → `SUPERVISI_SEKOLAH_ID`. Tabel yang dibaca: `sekolah`, `guru`, `instrumen`,
//       `supervisi`.
//   (c) FILTER **Tahun Ajaran + Semester** dibaca dari kolom `supervisi.periode`
//       (mis. "2026/2027 Ganjil" → tahun ajaran "2026/2027", semester "Ganjil"); nilai yang tidak
//       dikenal diabaikan (bukan 403).
//   (d) MIGRASI v20 (`db_menu_supervisi()`) mengubah sub menu **Supervisi Akademik** (jenis
//       `halaman`) menjadi `supervisi`; hanya baris yang masih `halaman` yang disentuh.
//   (e) Aset: **Chart.js 4.4.1** (sudah tersedia di `assets/vendor/`) + penolong grafik
//       **`window.SVP`** (`assets/app.js`); gaya di blok "INFORMASI SUPERVISI AKADEMIK"
//       `assets/style.css` (kelas `.sv-*`).
//   Uji: `bash websk_uji_supervisi.sh` + `bash websk_uji_supervisi_ui.sh`.
// 1.8.1 = MENU "INFORMASI → PERPUSTAKAAN" DIISI EMPAT LAPORAN (skema v19) — permintaan pemilik:
//   "Pada bagian menu informasi bagian perpustakaan buat isinya beberapa Tombol Laporan: Laporan
//   Data Buku, Laporan Pengunjung, Laporan Peminjaman, Laporan Pengembalian. Isi dari setiap
//   laporan datanya diambil otomatis dari e-Perpus. Jadi akan terisi otomatis Ketika e-Perpus
//   melakukan update data. Dan Ketika tombol tersebut di klik akan muncul laporannya."
//   Pilihan pemilik (JANGAN diubah tanpa bertanya): **empat tombol di SATU halaman**, laporan yang
//   dipilih **tampil di bawah tombol** (seperti tab), **ada saringan tanggal Dari–Sampai**, dan
//   **TANPA tombol cetak**.
//   (a) JENIS HALAMAN BARU `perpustakaan` (`halaman.php` → `perpustakaan_papan()` di
//       `inc/perpustakaan.php`).
//   (b) SUMBER DATA dibaca **LANGSUNG & HANYA-BACA** dari basis data aplikasi **e-PERPUS**
//       (`PERPUS_DB_FILE` = `../perpustakaan/data/perpustakaan.sqlite`, `mode=ro`, cadangan
//       `query_only=1`) — aplikasi perpustakaan yang dipakai pengelola madrasah. Setiap
//       penambahan/pembaruan buku, kunjungan, peminjaman, dan pengembalian di e-PERPUS langsung
//       tampil di website tanpa disalin ulang/cron/API. Modul `inc/perpustakaan.php` TIDAK memuat
//       kode aplikasi e-PERPUS dan hanya membaca tabel `sekolah`, `buku`, `anggota`, `peminjaman`,
//       dan `pengunjung`. Bila berkas datanya tidak terbaca → keterangan ramah (bukan Fatal error).
//   (c) MIGRASI v19 (`db_menu_perpustakaan()`) mengubah sub menu **Perpustakaan** (slug
//       `perpustakaan`) dari jenis `halaman` menjadi `perpustakaan`; hanya satu baris `menu` yang
//       disentuh dan hanya bila jenisnya masih `halaman`, sehingga pengaturan admin tidak tertimpa.
//       Jenis "Informasi Perpustakaan" juga dapat dipilih sendiri admin pada panel Menu & Halaman.
//   (d) Aset: seluruh gaya ada di blok "INFORMASI PERPUSTAKAAN" `assets/style.css` (kelas `.per-*`).
//   Uji: `bash websk_uji_perpus.sh` + `bash websk_uji_perpus_ui.sh`.
// 1.8.0 = ALAMAT RAPI (pretty URL) — permintaan pemilik (skema tetap v18, tanpa migrasi).
//   Permintaan pemilik: "Apakah bisa alamat https://…/web-sekolah/index.php menjadi
//   https://…/web-sekolah/ … Misal: https://…/web-sekolah/halaman.php?slug=tenaga-pendidik menjadi
//   https://…/web-sekolah/halaman/tenaga-pendidik." Pilihan pemilik: **SEMUA alamat publik dibuat
//   rapi** (termasuk kategori berita, pencarian, fasilitas) dan **alamat lama dialihkan 301** ke
//   alamat baru (satu alamat resmi saja).
//   (a) Modul baru **`inc/rute.php`** = SATU-SATUNYA tempat jalur permintaan diterjemahkan menjadi
//       halaman; `index.php` menjadi **pintu depan (front controller)**. Server Caddy sudah
//       menyerahkan setiap jalur yang bukan berkas nyata kepada `index.php`, jadi TIDAK diperlukan
//       aturan server/`.htaccess` (dan Caddy memang tidak membacanya).
//       Alamat yang berlaku: `/` (beranda), `/berita`, `/berita/kategori/<slug>`, `/berita/<slug>`,
//       `/agenda`, `/galeri`, `/galeri/<album>`, `/kontak`, `/ppdb`, `/ppdb/status`, `/fasilitas`,
//       `/halaman/<slug>`, `/cari`, `/asisten`. Parameter saringan/paginasi tetap pada query
//       (mis. `halaman/data-siswa?kelas=3&hal=2`).
//   (b) Seluruh tautan yang DIBUAT aplikasi tetap **RELATIF** (mis. `halaman/profil`, `./`,
//       `galeri/<slug>`) — TIDAK berawalan `/` — supaya tetap benar baik pada alamat bawaan
//       (`…/web-sekolah/…`) maupun bila kelak dipasang pada domain sendiri. Halaman publik memakai
//       **`<base>` relatif** (`rute_base()`) sehingga aset & skrip tetap terjangkau pada alamat yang
//       lebih dalam; sekaligus dicetak **`<link rel="canonical">`** dari jalur yang diminta.
//   (c) Alamat lama (`index.php`, `halaman.php?slug=…`, `berita_detail.php?slug=…`,
//       `galeri.php?album=…`, `ppdb_status.php`, `asisten.php`, `kontak.php`, `agenda.php`,
//       `fasilitas.php`, `cari.php`, `berita.php?kategori=…`) dialihkan **301** ke alamat rapi oleh
//       halaman bersangkutan (`rute_lama_301()`); tautan gaya lama yang diisi admin pada basis data
//       (Slider/Keunggulan/Program/menu bertipe tautan) diterjemahkan saat dicetak lewat
//       **`tautan_publik()`**.
//   (d) Alamat tak dikenal kini dijawab **404 sungguhan** (sebelumnya selalu 200 berisi beranda =
//       "soft 404"), dan garis miring di ujung dinormalkan (`/berita/` → `/berita`).
//   (e) Panel admin TIDAK berubah alamatnya (`/web-sekolah/admin/…`), hanya tautannya ke halaman
//       publik yang ikut disesuaikan.
//   Uji: `bash websk_uji_rute.sh` (108) + `bash websk_uji_rute_ui.sh` (30).
// 1.7.23 = TAHUN pada LAYANAN PIP + EDITOR BOLEH MENGGANTI KATA SANDI KARTU (skema v18).
//   Permintaan pemilik: "Pastikan Akun Editor (Konten, PPDB & Layanan PIP) bisa mengganti Kata Sandi
//   Kartu PIP. Yang saya tandai warna merah pada gambar tambahkan tahun (karena setiap tahun SK dan
//   penerima PIP tidak sama). Dan untuk panel pengisian layanan PIPnya disesuaikan kembali."
//   (a) **EDITOR boleh mengganti kata sandi Kartu PIP** — `boleh_sandi_pip()` kini sama dengan
//       `boleh_kelola_pip()` (Administrator ATAU Editor). Aturan v1.7.22 yang membatasinya hanya
//       Administrator DIBATALKAN oleh permintaan ini; kartu "Kata Sandi Kartu PIP" kini menampilkan
//       formulirnya untuk kedua peran.
//   (b) **TAHUN LAYANAN PIP** (skema v18): halaman publik memuat **pemilih tahun** tepat di bawah
//       kepala halaman (#pipTahun); **SK Penerima PIP**, **catatan (kotak "Info Penting")**, dan
//       **daftar penerima** kini tersimpan PER TAHUN (`pip_tahun`, `pip_penerima.tahun_id`).
//       Judul/teks informasi program, judul bagian daftar, dan kata sandi Kartu PIP tetap GLOBAL.
//   (c) **PANEL disesuaikan**: kartu **"Tahun Layanan PIP"** (paling atas) untuk memilih/menambah/
//       mengubah/menghapus tahun serta menetapkan **tahun utama** (yang tampil lebih dulu bagi
//       pengunjung); kartu Informasi kini mengunggah SK & menulis catatan untuk **tahun yang sedang
//       dibuka**; kartu Daftar Penerima hanya menampilkan penerima tahun itu dan menyimpan penerima
//       baru ke tahun tersebut. Seluruh tautan/aksi panel membawa `?tahun=` sehingga tidak pernah
//       berpindah tahun sendiri.
//   Uji: `bash websk_uji_pip.sh` + `bash websk_uji_pip_ui.sh`.
// 1.7.22 = PERAN "EDITOR" BOLEH MENGISI LAYANAN PIP (permintaan pemilik):
//   "Peran editor (konten & PPDB) buat juga bisa mengakses layanan PIP untuk bisa mengisi."
//   (a) Penjaga halaman `admin/pip.php` diganti dari `require_admin()` menjadi
//       **`require_kelola_pip()`** (= Administrator ATAU peran `editor`) sehingga akun Editor
//       (konten, PPDB & Layanan PIP) dapat MENGISI SELURUH isi halaman: teks informasi, SK Penerima
//       PIP, catatan, daftar penerima (tambah/ubah/urut/aktif-nonaktif/hapus), serta unggah Kartu PIP
//       dan Surat Kuasa. Menu *Layanan PIP* sudah tampil pada sidebar peran `editor`.
//   (b) **KATA SANDI KARTU PIP TETAP WEWENANG ADMINISTRATOR** (`boleh_sandi_pip()` = `is_admin()`):
//       permintaan pemilik sebelumnya berbunyi "Password ditentukan oleh Admin", jadi peran Editor
//       tidak melihat formulirnya (kartunya diganti keterangan yang menjelaskan) dan aksi
//       `simpan_sandi` dari non-Administrator dijawab **403 yang diperiksa SEBELUM CSRF**.
//       **Tanyakan pemilik** bila ingin kata sandi juga boleh diubah oleh Editor.
//   (c) Peran **"Editor Berita"** (`berita`) SENGAJA TIDAK diberi akses PIP (tetap 403) — akun itu
//       khusus berita + galeri. Label peran pada Pengguna & Akun menjadi
//       "Editor (konten, PPDB & Layanan PIP)" dan teks penjelasannya ikut disesuaikan.
// 1.7.21 = PERAPIAN HALAMAN "LAYANAN PIP" (permintaan pemilik, dari tangkapan layar):
//   (a) DUA baris keterangan DIHAPUS (ditandai kotak merah oleh pemilik) — **jangan dikembalikan**:
//       keterangan berkas SK Penerima PIP, dan keterangan "Kartu PIP diunduh dengan kata sandi …
//       Surat kuasa … pada kolom Keterangan". Karena itu halaman publik Layanan PIP tidak lagi
//       memuat blok `.pip-note`.
//   (b) Catatan yang diisi admin (`pip_catatan`) kini tampil sebagai **KOTAK "INFO PENTING"**
//       (`#infoPentingPip`, kelas `.pip-info-penting`): judul **"Info Penting"** TEBAL + MERAH +
//       **BERKEDIP** (`@keyframes pipKedip`, 1,1 detik) dan isi catatan dibuat agak tebal
//       (`font-weight:600`) — sesuai permintaan "dibuat agak tebal dan diletakkan di dalam kotak
//       dengan tambahan tulisan info penting (tulisan info penting buat tebal merah dan berkedip)".
//       Kedipan dimatikan pada `prefers-reduced-motion: reduce` (tulisan tetap merah tebal).
// 1.7.20 = MENU "LAYANAN PIP" (Program Indonesia Pintar) — skema v17. Permintaan pemilik:
//   "Pada bagian informasi layanan PIP di isi dengan daftar berikut. Pengisiannya lewat akun admin
//   (Buatkan Menu PIP): Informasi Seputar Program Indonesia Pintar (tambahkan SK Penerima PIP yang
//   diupload oleh admin); Daftar Penerima PIP MA. Sirojuth Tholibiin Mootilango (Tabel: No, Nama,
//   NISN, Kelas, Nominal, Kartu PIP (di upload admin), Keterangan (Keterangan isi dengan surat kuasa
//   yang diupload admin). Kartu PIP Ketika akan di download minta password (Password ditentukan oleh
//   Admin)."
//   (a) MENU BARU **"Layanan PIP"** (slug `pip`, jenis halaman `pip`) dibuat otomatis sebagai sub menu
//       "Informasi" (cadangan: "Kesiswaan", lalu menu utama) — lihat `db_menu_pip()`.
//   (b) PANEL ADMIN BARU **`admin/pip.php`** (menu sidebar "Layanan PIP"): menyunting teks
//       "Informasi Seputar Program Indonesia Pintar" (penyunting bergaya Word), mengunggah berkas
//       **SK Penerima PIP**, menetapkan **kata sandi Kartu PIP**, serta mengelola **Daftar Penerima**
//       (Nama, NISN, Kelas, Nominal, unggah Kartu PIP, unggah Surat Kuasa, Keterangan, urutan,
//       aktif/nonaktif, hapus) — semuanya lewat **satu** halaman.
//   (c) HALAMAN PUBLIK berisi: bagian **Informasi Seputar Program Indonesia Pintar** (+ tombol
//       "Lihat SK Penerima PIP" bila berkasnya sudah diunggah) dan **Daftar Penerima PIP <nama
//       sekolah>** bertabel **No, Nama, NISN, Kelas, Nominal, Kartu PIP, Keterangan** + baris JUMLAH
//       (jumlah penerima & total nominal). Kolom Keterangan memuat tautan **Surat Kuasa** yang
//       diunggah admin (boleh disertai teks keterangan).
//   (d) **KARTU PIP BER-SANDI**: tombol unduh menuju `pip_kartu.php?id=…` yang meminta kata sandi
//       (batas percobaan `PIP_MAKS_COBA`/`PIP_MENIT_COBA` per sesi). Setelah sandi benar, berkasnya
//       **DIALIRKAN server** (`pip_kirim_berkas()`) sehingga URL media aslinya tidak pernah sampai ke
//       peramban — jadi sandi benar-benar melindungi berkas, bukan hanya menyembunyikan tautan.
//       Sandi disimpan sebagai **hash bcrypt** (tidak dapat ditampilkan kembali; admin cukup
//       menetapkan sandi baru bila lupa).
//   Uji: `bash websk_uji_pip.sh` + `bash websk_uji_pip_ui.sh` (+ `websk_uji_pip_ui.js`).
// 1.7.19 = MENU "INFORMASI → SARPRAS" DIISI DAFTAR INVENTARIS BARANG (skema v16) — permintaan
//   pemilik: "Pada bagian Informasi Sarpras isi dengan tampilan yang di upload. Datanya bersumber
//   dari https://asisten.guru/tugas.php?kode=sarpras yang khusus dari MA. Sirojuth Tholibiin
//   Mootilango. Sehingga ketika data tersebut diupdate otomatis akan tampil juga di informasi
//   sarpras website madrasah."
//   (a) JENIS HALAMAN BARU `sarpras` (`halaman.php` → `sarpras_papan()` di `inc/sarpras.php`)
//       menampilkan **Daftar Inventaris Barang** persis seperti tampilan di e-GURU (menu Tugas
//       Tambahan Sarana & Prasarana → Laporan): kolom **No, Nama Barang, Spesifikasi, Lokasi,
//       Kondisi, Jumlah, Sumber Dana, Jenis, Keterangan** + baris **Jumlah** di kaki tabel, dengan
//       pil warna kondisi (Baik / Rusak Ringan / Rusak Berat) seperti tangkapan layar pemilik.
//   (b) SUMBER DATA dibaca **LANGSUNG & HANYA-BACA** dari basis data aplikasi **e-GURU**
//       (`EGURU_DB_FILE` = `../e-guru/data/eguru.sqlite`, `mode=ro`, cadangan `query_only=1`).
//       e-GURU itulah aplikasi yang dilayani di https://asisten.guru (versi & isi halaman depannya
//       sudah dicocokkan), sehingga **setiap pembaruan barang di sana langsung tampil di website**
//       — tanpa salin data, tanpa cron, tanpa API, tanpa perlu kredensial.
//       Sekolah/madrasah pemilik data dicocokkan dari **nama sekolah website ini** dengan daftar
//       sekolah pada e-GURU (cadangan: konstanta `SARPRAS_SEKOLAH_ID`), jadi tidak ada data sekolah
//       lain yang ikut tampil. Bila berkas datanya tidak terbaca, halaman menampilkan keterangan
//       ramah (bukan Fatal error) dan halaman lain tetap normal.
//   (c) MIGRASI v16 (`db_menu_sarpras()`) mengubah sub menu **Sarpras** (slug `sarana-prasarana`)
//       dari jenis `halaman` (isinya kosong) menjadi `sarpras`; hanya satu baris `menu` yang
//       disentuh dan hanya bila jenisnya masih `halaman`. Jenis "Informasi Sarpras" juga dapat
//       dipilih sendiri admin pada panel **Menu & Halaman**.
//   Uji: `bash websk_uji_sarpras.sh` + `bash websk_uji_sarpras_ui.sh` — termasuk bukti bahwa
//   menambah/mengubah barang di salinan basis data e-GURU langsung tampil di website dan bahwa
//   berkas basis data e-GURU TIDAK berubah oleh halaman ini (baca-saja).
// 1.7.18 = MENU "INFORMASI → DANA BOS" DIISI PAPAN INFORMASI DANA BOS (skema v15) — permintaan
//   pemilik: "Pada bagian informasi Bos diisi dengan tampilan yang ada di aplikasi e-BOS. Buat sama
//   persis. Sumber datanya tarik data dari link tersebut, sehingga ketika aplikasi e-BOS melakukan
//   update otomatis Informasi BOS yang ada di website juga akan terupdate."
//   (a) JENIS HALAMAN BARU `bos` (`halaman.php`) menampilkan **Papan Informasi Penggunaan Dana BOS**
//       persis seperti halaman depan aplikasi e-BOS: baris Tahun Anggaran (chip) + judul besar PAPAN
//       INFORMASI + tombol Cetak, kartu **Data Lembaga**, empat kotak ringkasan (jumlah anggaran/
//       dana diterima/realisasi/sisa), tabel **Komponen Penggunaan Dana BOS** (No, komponen, jumlah,
//       Tahap 1 & 2, keterangan + baris JUMLAH), tabel **Realisasi dan Sisa Anggaran** (dengan bilah
//       serapan), **grafik donat** serapan per komponen, **grafik batang** pemasukan & pengeluaran
//       per bulan, serta **Buku Kas, Daftar Transaksi** yang dapat dibuka/ditutup.
//   (b) SUMBER DATA dibaca **LANGSUNG & HANYA-BACA** dari basis data aplikasi e-BOS di server yang
//       sama (`inc/bos.php` → `EBOS_DB_FILE`), dibuka dengan `mode=ro` (cadangan: `query_only=1`).
//       Jadi setiap pembaruan di aplikasi e-BOS (pengeluaran/pemasukan baru, komponen, pagu, tahun
//       anggaran, identitas lembaga) LANGSUNG tampil di website tanpa disalin ulang & tanpa cron.
//       Modul ini TIDAK memuat kode aplikasi e-BOS; bila berkas datanya tidak dapat dibaca, halaman
//       menampilkan keterangan ramah (bukan Fatal error) dan halaman lain tetap normal.
//   (c) MIGRASI v15 (`db_menu_bos()`) mengubah sub menu **Dana Bos** (slug `dana-bos`) dari jenis
//       `halaman` (isinya kosong) menjadi `bos`; hanya satu baris `menu` yang disentuh dan hanya bila
//       jenisnya masih `halaman`, sehingga pengaturan admin lain tidak pernah tertimpa. Jenis
//       "Informasi BOS" juga dapat dipilih sendiri admin pada panel **Menu & Halaman**.
//   (d) Aset: **Chart.js 4.4.1** disalin ke `assets/vendor/chart.umd.min.js` (tanpa CDN) + penolong
//       `window.BOS` (`assets/app.js`); seluruh gaya halaman ada di blok "INFORMASI BOS"
//       `assets/style.css` (kelas `.bos-*`), dan lembar cetaknya A4 MENDATAR dengan `@page`
//       ditulis di halaman ini (agar PDF benar-benar A4 landscape).
//   Uji: `bash websk_uji_bos.sh` (72) dan `bash websk_uji_bos_ui.sh` (36) — termasuk pembandingan
//   seluruh nominal dengan halaman depan aplikasi e-BOS yang dijalankan sungguhan (harus sama persis)
//   dan bukti bahwa berkas basis data e-BOS TIDAK berubah oleh halaman ini.
// 1.7.17 = TOMBOL "HUBUNGI KAMI VIA WHATSAPP" DIGANTI OBROLAN ASISTEN AI (skema v14):
//   (a) tombol mengambang WhatsApp diganti **tombol + kotak obrolan asisten** (kanan bawah, tema
//       aplikasi). Tanpa JavaScript tombolnya adalah TAUTAN ke halaman `asisten.php` (formulir
//       biasa), jadi pengunjung tetap dapat bertanya.
//   (b) jawaban ASISTEN disusun **berbasis aturan** dari (1) **FAQ** yang diisi admin pada menu
//       **FAQ Asisten** (`admin/faq.php`, tabel `faq`) dan (2) **data website** (profil, visi/misi/
//       tujuan, alamat, kontak, jam operasional, jumlah guru/siswa/alumni, daftar guru & kelas,
//       prestasi, fasilitas, berita, agenda, galeri album, struktur organisasi, menu, program
//       digital, serta PPDB: syarat/alur/jadwal/status/cek seleksi). Tidak memakai layanan AI luar
//       dan tidak butuh kunci API.
//   (c) **riwayat obrolan TIDAK disimpan** di basis data (keputusan pemilik). Halaman `asisten.php`
//       hanya menyimpan percakapan sementara di sesi pengunjung (maks 12 pesan).
//   (d) batas ASISTEN_MAKS_TANYA=40 pertanyaan / ASISTEN_MENIT_TANYA=10 menit per sesi + panjang
//       pertanyaan maks 300 karakter; endpoint `chat_asisten.php` (JSON, CSRF).
//   (e) tautan WhatsApp lain (halaman Kontak, "Hubungi Panitia" PPDB, balas pesan di panel)
//       DIBIARKAN seperti semula sesuai pilihan pemilik.
// 1.7.16 = BAGIAN "GALERI & MEDIA SOSIAL" PADA BERANDA menampilkan **SELURUH SAMPUL ALBUM**
//   (permintaan pemilik) — bukan lagi daftar foto: satu slide per album (sampul album, atau foto
//   pertama bila kolom sampul kosong), keterangannya **nama album + tahun**, badge jumlah foto, dan
//   **klik sampul membuka ISI album tersebut** (`galeri.php?album=slug`). Slide tetap memakai
//   mekanisme slider yang sama (`.gulir-foto`), dan jumlah slide = jumlah album aktif.
// 1.7.15 = GALERI: FILTER TAHUN ALBUM + VIDEO 12 PER HALAMAN (permintaan pemilik):
//   (a) bagian **Album Foto** mendapat **saringan TAHUN** (`?tahun=YYYY`, chip "Semua tahun" +
//       tahun yang benar-benar ada urut terbaru lebih dulu, dengan jumlah album pada tooltip).
//       Saat disaring: judul bagian menyebut tahunnya, hanya album (kelompok) tahun itu yang tampil,
//       chip tahun di dalam kartu ditandai aktif, dan tombol "Lihat Album" membuka album TAHUN
//       terpilih. Tahun asing diabaikan (kembali ke semua tahun, bukan halaman kosong).
//   (b) bagian **Video Kegiatan** kini menampilkan **12 video per halaman** (grid 4 kolom = 3 baris)
//       dengan keterangan "Menampilkan 1–12 dari N video" dan tombol **Selanjutnya**/Sebelumnya
//       (`?hal=N`, tautan membawa `#videoGaleri` supaya kembali ke bagian video; nomor halaman yang
//       melebihi jumlah dijepit ke halaman terakhir).
// 1.7.14 = BLOK MERAH DI DALAM KOTAK BAGAN DIHAPUS (permintaan pemilik: "Hilangkan warna merah yang
//   ada di dalam kolom bagan"). Peta tetap `struktur_peta()` tidak lagi memakai penanda `aksen`,
//   `struktur_render()` tidak menggambar `.org-aksen`/`.org-aksen-pemisah`, gaya CSS-nya dibuang,
//   dan panel tidak lagi menandai "blok merah". Efek sampingnya bagus: foto & teks kotak memakai
//   seluruh lebar kotak (tidak lagi menyisakan 22px di kiri).
// 1.7.13 = DOKUMEN IJOP & AKREDITASI (permintaan pemilik):
//   (a) tombol **Cetak / Simpan PDF** pada halaman Profil Lengkap DIHAPUS (bagian yang ditandai merah);
//   (b) di bawah tabel Identitas ditambahkan tombol **Lihat Ijop** & **Lihat Akreditasi** (bagian biru)
//       yang membuka berkas dokumen (gambar/PDF) pada tab baru. Berkasnya diunggah dari panel
//       **Profil Lengkap (Isian)** → kolom baru `sekolah.berkas_ijop` & `sekolah.berkas_akreditasi`
//       (skema v13) yang menyimpan URL media proxy; tombol TIDAK muncul bila berkasnya belum ada.
// 1.7.12 = DUA PERMINTAAN PEMILIK pada halaman PROFIL LENGKAP:
//   (a) **isian NILAI AKREDITASI** ditambahkan pada panel Profil Lengkap (`admin/profil.php`) —
//       medan "Nilai / Peringkat Akreditasi" (kolom `sekolah.akreditasi`, boleh A/B/C atau angka)
//       berdampingan dengan "Tanggal Akreditasi" (`akreditasi_tgl`); keduanya tampil pada baris
//       **Akreditasi** di tabel Identitas halaman profil.
//   (b) **kerapian tabel & posisi tanda tangan**: lebar kolom dipatok lewat `<colgroup>` dan SETIAP
//       baris memakai **4 sel** (nomor · label · nilai · nilai kedua) sehingga garis pemisah kolom
//       LURUS dari baris pertama sampai terakhir; kolom nomor dilebarkan (46px) + `white-space:nowrap`
//       sehingga nomor dua angka (10.–13.) tidak lagi terbelah; **tanda tangan dipindah ke kaki
//       lembar dan diratakan ke pinggir KANAN dokumen** (`.pl-kaki` + `.pl-ttd`).
// 1.7.11 = PAS FOTO BAGAN MUNCUL + BAGAN TIDAK UNTUK DICETAK (permintaan pemilik):
//   (a) **PAS FOTO TIDAK MUNCUL** — penyebabnya `clipPath` bawaan memakai `userSpaceOnUse`
//       (`<rect x=0 y=0 width=100 height=100>`), padahal gambar berada di koordinat kotaknya sendiri
//       (mis. x=727, y=165), sehingga foto dipotong HABIS dan kotak kembali terlihat kuning.
//       Sekarang `clipPathUnits="objectBoundingBox"` (clip relatif kotak gambar). Dibuktikan dengan
//       analisis piksel: sebelum = 1102/1102 piksel kuning, sesudah = warna beragam (foto terlihat).
//   (b) **BAGAN TIDAK PERLU DICETAK** — "buat struktur organisasi berukuran lebih dari A4 tidak
//       masalah karena hanya untuk dilihat": tombol "Cetak / Simpan PDF" & `@page A4` dihapus dari
//       halaman bagan, penanda penyusutan cetak (`data-cetak-lebar`) dilepas, lembar bagan dibuat
//       melebar melewati lebar konten (`.org-lebar`, maks 1900px) dengan wadah bergulir + tombol
//       **Perkecil / Ukuran Layar / Perbesar** (`initZoomBagan`). Tombol cetak tetap ada pada
//       halaman **Profil Lengkap** (dokumen itu memang untuk dicetak).
// 1.7.10 = DUA PERBAIKAN BAGAN STRUKTUR ORGANISASI (permintaan pemilik):
//   (a) **NAMA JABATAN kembali tampil di ATAS nama orang** ("supianto diatasnya ada tulisan kecil
//       Kepala Madrasah"). Setiap kotak kini menampilkan dua baris bila isian namanya berbeda dari
//       jabatan: jabatan (huruf kecil, `.org-jabatan`) di atas + nama (tebal) di bawah. Bila kotak
//       memang berisi jabatan saja (mis. "KTU", "Dewan Guru", "Siswa", "Kelas X MIPA 2") hanya SATU
//       baris ditampilkan supaya tidak ada tulisan kembar.
//   (b) **GARIS YANG HILANG diperbaiki**: penyambung tegak dari Wali Kelas sampai baris kelas ke-3
//       (`M 180 580 V 770`) — sebelumnya berhenti di y=630 sehingga stub baris kelas ke-2 & ke-3
//       menggantung (ditandai pemilik dengan tanda panah). Ujung batang Wakamad juga dirapikan
//       (mulai tepat di stub kiri x=180) sehingga TIDAK ada ujung garis yang menggantung.
// 1.7.9 = BAGAN STRUKTUR ORGANISASI MENJADI PETA TETAP (permintaan pemilik): "susunan dan tampilan
//   dibuat permanen seperti contoh yang sudah dikirim. Dan admin tinggal input saja nama-namanya +
//   pas fotonya, tidak perlu lagi menggeser bagan":
//   (a) letak/ukuran kotak + garis penghubung ditentukan **KODE slot** pada `struktur_peta()`
//       (inc/profil.php) — kanvas 1600×853 (A4 mendatar), Komite bergaris putus-putus, kotak ber-blok
//       merah seperti contoh; bagan digambar sebagai **SVG** sehingga tajam & tetap rapi saat dicetak;
//   (b) tiap kotak punya **Nama** + **Pas Foto** (kolom baru `struktur.kode` & `struktur.foto`,
//       skema v12) — panel `admin/struktur.php` kini HANYA berisi isian nama + unggah foto;
//       pengaturan atasan/urutan/jenis garis/geser/hapus **DIHAPUS** (letak tidak bisa bergeser);
//   (c) nama panjang otomatis dipaskan ke kotaknya (auto-kecil + `textLength`) sehingga tidak meluber.
// 1.7.8 = GALERI DUA BAGIAN (permintaan pemilik): "Album Foto dibagian atas dan dibawah video
//   (Video tanpa album) langsung tampil semua. Jadi yang di dalam album hanya file foto saja":
//   (a) halaman `galeri.php` kini memuat **Album Foto** (kartu per album + pilihan tahun) di ATAS
//       dan **Video Kegiatan** (SELURUH video, tanpa album) di BAWAH;
//   (b) isi album (`?album=slug`) hanya menampilkan **FOTO** — tidak ada video di dalam album;
//   (c) panel: pemilih Album pada formulir video DIHAPUS (`galeri_video.album_id` selalu 0) dan
//       tabel album hanya menghitung FOTO (`video_publik()` menjadi satu-satunya sumber video).
// 1.7.7 = TIGA PERMINTAAN PEMILIK:
//   (a) AKUN EDITOR BERITA kini juga boleh mengelola **GALERI (foto & video)** — `galeri.php`
//       ditambahkan pada `berita_berkas_boleh()`, menunya muncul di sidebar, dan dasbornya memuat
//       pintasan galeri. Label peran menjadi "Editor Berita (berita + galeri foto & video)".
//   (b) ALBUM GALERI PUNYA TAHUN (skema v11, kolom `galeri_album.tahun`) — di panel ada medan
//       Tahun, kolom Tahun, saringan tahun, dan tombol **Isi Tahun dari Tanggal Unggah** untuk album
//       lama (tidak menebak: hanya bila ada berkas dengan tanggal unggah).
//   (c) HALAMAN GALERI PUBLIK kini menampilkan **DAFTAR ALBUM** lebih dulu (per nama album +
//       pilihan TAHUN, mis. "Pramuka 2024/2025/2026" sebagai satu kartu) dan pengunjung membuka
//       isi album lewat `?album=slug` — foto & video ditampilkan PER ALBUM, bukan semua sekaligus.
// 1.7.6 = HALAMAN "PROFIL LENGKAP" & "STRUKTUR ORGANISASI" (permintaan pemilik: "Pada isian Profil
//   Lengkap dan Struktur Organisasi yang ditampilkan seperti contoh. Dan data isiannya lewat admin"):
//   (a) menu **Profil Lengkap** (jenis menu `profil_lengkap`) menampilkan dokumen profil seperti
//       contoh — kop judul, LOGO MADRASAH, tabel IDENTITAS MADRASAH (13 baris), VISI/MISI/TUJUAN
//       bernomor, dan tanda tangan kepala; SELURUH isiannya diubah dari panel
//       **Profil Lengkap (Isian)** (`admin/profil.php`).
//   (b) menu **Struktur Organisasi** (jenis menu `struktur_organisasi`) menampilkan BAGAN kotak
//       berjenjang berikut garis penghubung (termasuk garis putus-putus untuk Komite); kotak-kotaknya
//       diatur dari panel **Struktur Organisasi (Bagan)** (`admin/struktur.php`) — tiap kotak punya
//       atasan/induk, urutan, jenis garis, dan penanda blok merah.
//   Keduanya dapat dicetak (tombol Cetak + gaya cetak A4).
// 1.7.5 = DUA PERMINTAAN PEMILIK pada halaman PPDB:
//   (a) baris "Biaya pendaftaran: …" (§ yang ditandai MERAH) DIHAPUS dari halaman PPDB; medan
//       "Biaya Pendaftaran" pada panel juga dihapus karena tidak ditampilkan lagi (nilai lama di
//       basis data dibiarkan utuh);
//   (b) daftar PERSYARATAN (yang ditandai BIRU) kini BERNOMOR 1, 2, 3, … — daftar memakai `<ol>`
//       + CSS counter (`.daftar-syarat.bernomor`), dan migrasi skema v9
//       (`db_ppdb_syarat_bernomor()`) memisahkan persyaratan yang masih ditulis dalam satu baris
//       (dipisah pada tanda koma) supaya nomornya benar-benar berurutan. Aman: hanya sekali jalan
//       (penanda `ppdb_syarat_nomor_v1`) dan tidak menyentuh nilai yang bukan daftar.
// 1.7.4 = EMPAT PERMINTAAN PEMILIK:
//   (a) PERAN BARU "Editor Berita" pada halaman Pengguna & Akun — akun khusus yang HANYA boleh
//       membuka menu Berita (daftar berita, tulis/ubah berita, kategori berita; + dasbor ringkas).
//       Berkas panel lain dijawab 403 lewat `boleh_buka_berkas()` pada `require_login()`
//       (`berita_berkas_boleh()` = daftar putihnya). Peran: `admin`/`editor`/`berita`.
//   (b) TOMBOL "Daftar Sekarang" pada SLIDER BERANDA selalu membuka halaman FASILITAS — bug nyata:
//       slide yang tidak aktif tetap menumpuk di atas slide aktif (position:absolute + opacity:0)
//       sehingga tombol slide TERAKHIR ("Lihat Fasilitas" → fasilitas.php) yang menerima klik.
//       Perbaikan: `.hero-slide{pointer-events:none}` + `.hero-slide.aktif{pointer-events:auto}`.
//   (c) PPDB BUKA/TUTUP: kartu "Status Pendaftaran PPDB" di panel dengan tombol pintasan
//       **Tutup Pendaftaran / Buka Pendaftaran**; saat DITUTUP, pengunjung yang menekan
//       "Daftar Sekarang" (slider, kartu PPDB beranda, tombol PPDB di bilah atas) melihat pesan
//       **"Mohon maaf pendaftaran murid baru belum dibuka."** — lewat `<dialog id="dialogPpdbTutup">`
//       + `initPpdbTutup()` (tanpa JavaScript tetap muncul di halaman ppdb.php).
//   (d) RAPIKAN KARTU LANGKAH PPDB: akarnya `potong()` yang MELEBUR baris baru sehingga Alur,
//       Persyaratan, dan Jadwal PPDB tersimpan sebagai SATU baris panjang (satu kartu raksasa).
//       Panel kini menyimpan medan "satu per baris" memakai `potong_baris()` (baris dipertahankan)
//       dan migrasi skema v8 (`db_perbaiki_baris_ppdb()`) mengembalikan baris teks bawaan yang
//       melebur — HANYA bila isinya masih sama persis dengan teks bawaan (suntingan pemilik aman).
// 1.7.3 = PERAPIAN HALAMAN (permintaan pemilik):
//   (a) KOLOM SISI pada halaman dinamis (`halaman.php` — Data Siswa, Data Alumni, Prestasi Siswa,
//       Prestasi Madrasah/Pendidik, Pelatihan Pendidik, Tenaga Pendidik, dan halaman isi lain)
//       DIHAPUS beserta markup petunjuk `$anak`/`$saudara`-nya. Isi tabel/data kini memakai LEBAR
//       PENUH sehingga lebih luas dan rapi (permintaan: "yang ditandai warna merah dihapus agar
//       tabel lebih luas dan rapi terlihat"). Sub menu tetap tersedia pada menu turun bilah
//       navigasi (`.nav-anak`), jadi tidak ada menu yang hilang.
//   (b) Video YouTube pada halaman Galeri dibuat LEBIH KECIL dan **4 kotak sebaris**
//       (`.video-grid` 2 → 4 kolom; 3 kolom ≤1000px, 2 kolom ≤700px, 1 kolom ≤420px) dengan
//       jarak 16px dan tulisan di dalamnya diperkecil agar tetap seimbang.
// 1.7.2 = PERBAIKAN (laporan pemilik):
//   (a) tombol "Masuk Admin" pada kaki halaman DIHAPUS (pemilik menandainya dengan kotak merah;
//       alamat panel tetap dapat dibuka langsung di /web-sekolah/admin/);
//   (b) tulisan pada kaki halaman berbunyi "Website Website Sekolah v…" (APP_NAME sudah memuat kata
//       "Website") → kata "Website" ganda dihapus, sekarang "Website Sekolah v…";
//   (c) ANGKA ALUMNI pada bagian "… dalam Angka" selalu 0 walau admin sudah mengisi/mengimpor
//       Data Alumni — sebabnya statistik hanya menghitung siswa berstatus LULUS. Sekarang memakai
//       sumber yang sama dengan halaman Data Alumni (`alumni_jumlah_gabungan()` = data kelulusan +
//       tabel `alumni`, sudah dibersihkan dari data ganda); isian manual "Jumlah Alumni" tetap
//       dipakai hanya bila angkanya lebih besar.
// 1.7.1 = FOTO SLIDER BERANDA DIBUAT JELAS (laporan pemilik: bagian foto slider terlalu pudar).
//   Sebelumnya `.hero-slide-gambar{opacity:.55}` + lapisan gelap yang kuat membuat foto hanya
//   tampil ~43% kecerahan aslinya (terukur). Sekarang:
//     • opacity foto .96 (hampir apa adanya);
//     • `.hero-lapisan` dibuat BERGANTUNG SISI — tetap gelap di KIRI (tempat tulisan) tetapi
//       hampir transparan mulai ~60% (tempat foto terlihat);
//     • tulisan diberi bayangan dua lapis agar tetap terbaca walau fotonya sangat terang
//       (kasus terburuk diukur: kontras teks 3,29 dengan foto terang, kecerahan foto 0,94);
//     • pada layar ≤760px lapisan dibuat MERATA (vertikal) karena tulisan memakai lebar penuh,
//       sehingga foto tetap terlihat (rasio ~0,52) dan tulisan tetap terbaca.
//   Hasil terukur pada data pemilik: kecerahan foto 0,43 → 0,94 dan kontras tulisan tetap ≥ 3.
// 1.7.0 = (a) FOTO SISWA MASSAL (tanpa perubahan skema) — halaman admin `siswa_foto.php`:
//         unggah BANYAK foto sekaligus dari perangkat (nama berkas / NIS dipakai mencocokkan siswa)
//         dan **ambil foto sekaligus dari alamat web** direktori peserta didik (nama+foto dicocokkan
//         otomatis berdasarkan nama); hasil pencocokan ditampilkan lebih dulu sebelum disimpan.
//         (b) PROGRAM UNGGULAN → **PROGRAM DIGITALISASI MADRASAH** (skema v7, migrasi `program_digital_v1`):
//         bagian program pada beranda menampilkan daftar APLIKASI (e-GURU, Supervisi Akademik, CBT,
//         e-PKKM, e-BOS, e-Keuangan, e-PERPUS, Smart Teacher, Portal Digital) dengan tautan ke
//         masing-masing aplikasi.
// 1.6.0 = DATA ALUMNI DAPAT DIISI MANUAL/IMPOR (permintaan pemilik) + skema v6:
//   Halaman publik **Data Alumni** kini menggabungkan DUA sumber:
//   (a) data kelulusan (siswa berstatus lulus — otomatis, akan terus bertambah dari proses kelulusan), dan
//   (b) **tabel `alumni`** khusus alumni LAMA yang belum pernah masuk sebagai siswa, yang diisi admin
//       lewat menu **Data Alumni** (input satu per satu, impor Excel/CSV, atau **ambil dari alamat web**
//       direktori alumni madrasah — halaman `alumni.php` di panel admin).
//   Bila nama + tahun keluar sama, data ganda digabung (yang berfoto dipakai).
// 1.5.1 = PERBAIKAN TABEL PRESTASI (laporan pemilik: “tahun, tingkat, sertifikat”):
//   judul kolom dan isi sel dahulu ditulis TERPISAH, sehingga kategori **Pelatihan Pendidik**
//   barisnya kehilangan 2 sel (Tahun & Tingkat) — akibatnya tombol “Lihat” Sertifikat bergeser ke
//   kolom Tahun dan Tahun/Tingkat tampak kosong padahal datanya tersimpan lengkap.
//   Sekarang judul DAN isi dibangkitkan dari SATU daftar: `prestasi_kolom($kategori)` (helpers),
//   sehingga jumlah sel selalu sama dengan jumlah judul. Ditambah uji yang membandingkan jumlah sel
//   dengan jumlah judul serta memastikan kolom Tahun/Tingkat/Sertifikat berisi nilai yang benar.
// 1.5.0 = HALAMAN PRESTASI & PELATIHAN (permintaan pemilik) + skema v5:
//   empat halaman baru yang isinya diatur admin (satu tabel `prestasi` dengan kolom `kategori`):
//   (a) **Prestasi Siswa** (menu Kesiswaan) — Foto Siswa, Nama Siswa, Jenis Prestasi, Tahun,
//       Tingkat (Kabupaten/Provinsi/Nasional), Sertifikat (berkas diunggah + tombol Lihat);
//   (b) **Prestasi Madrasah** (menu Akademik) — No, Nama Prestasi, Tahun, Sertifikat;
//   (c) **Prestasi Pendidik** (menu Akademik) — Foto, Nama Guru, Jenis Prestasi, Tahun, Tingkat,
//       Sertifikat;
//   (d) **Pelatihan Pendidik** (menu Akademik) — Nama, Jabatan, Jenis Pelatihan, Tahun, Tingkat,
//       Sertifikat.
//   Sertifikat diunggah lewat media proxy (PDF/JPG/PNG, maks 15 MB) dan ditampilkan sebagai
//   tombol "Lihat" yang membuka berkasnya di tab baru. Pengelolaan: satu halaman admin
//   `prestasi.php` dengan tab per kategori.
// 1.4.0 = HALAMAN KESISWAAN: DATA SISWA & DATA ALUMNI (permintaan pemilik) + skema v4:
//   dua sub menu baru pada menu **Kesiswaan** yang tampil di website:
//   (a) **Data Siswa** — foto siswa, nama, NISN, jenis kelamin, kelas, dan status (Aktif/Tidak Aktif),
//       lengkap dengan **penyaring kelas** untuk pengunjung + pengelompokan per kelas;
//   (b) **Data Alumni** — foto, nama, jenis kelamin, **tahun masuk**, **tahun keluar**, dan
//       **keterangan** kondisi setelah lulus (mis. "Kuliah di …" / "Bekerja di …"), juga dapat disaring
//       per tahun keluar. Kolom baru `siswa.keterangan` (skema v4) diisi admin pada Data Siswa.
// 1.3.2 = TOMBOL MEDIA SOSIAL BERWARNA KHAS (permintaan pemilik, tanpa perubahan skema):
//   setiap tombol memakai warna platform-nya — Facebook biru (#1877F2), Instagram gradasi
//   (#F58529 → #DD2A7B → #8134AF), YouTube merah (#FF0000), WhatsApp hijau (#25D366 → #128C7E),
//   TikTok hitam; media sosial lain memakai warna tema. Warna ditulis sedikit lebih gelap pada
//   ujung gradasinya agar tulisan & ikon putih tetap terbaca. Kelas warna ditentukan dari nama ikon
//   pada tabel `sosial` (helper `sosial_kelas()`/`sosial_jenis()` di inc/layout.php), dan dipakai
//   juga oleh ikon di bilah atas serta kaki halaman (versi warna terang untuk latar gelap).
// 1.3.1 = PERAPIAN TATA LETAK (permintaan pemilik, tanpa perubahan skema):
//   (a) TOMBOL PPDB ONLINE dipindah keluar dari deretan menu — sekarang berdiri sendiri di
//       ujung KANAN header dan sejajar (tengah) dengan deretan menu. Sebelumnya tombol itu ikut
//       terlipat ke baris kedua (karena menu + tombol tidak muat sebaris) sehingga tampak
//       menggantung dengan ruang kosong di kanannya. Ukuran tombol disesuaikan (tinggi setara
//       baris menu) dan blok nama lembaga + huruf menu dirapatkan supaya 8 menu SELALU satu baris
//       (diuji pada 1200-1600px). Pada layar ≤1080px tombol tetap tampil di baris atas di samping
//       tombol menu.
//   (b) KARTU PROGRAM UNGGULAN diberi WARNA BERBEDA-BEDA (6 warna berputar: hijau, emas, biru,
//       ungu, merah, teal) — latar kartu, lingkaran ikon, dan warna panahnya ikut berbeda.
// 1.3.0 = PENYESUAIAN BERANDA lanjutan (permintaan pemilik) + skema v3:
//   (a) ruang kosong di bawah kartu PPDB kini diisi **POLLING PENDAPAT** — pertanyaan
//       "Bagaimana pendapat anda tentang web ini?" beserta pilihan jawaban, hasil suara (persentase),
//       dan pencegah suara ganda per sesi; pertanyaan & pilihan diatur admin di menu **Polling Pendapat**
//       (tabel `poll`, `poll_pilihan`, `poll_suara`);
//   (b) bagian **Guru & Tenaga Kependidikan** diberi latar **HIJAU** (band) dengan tulisan putih;
//   (c) **strip "slider kecil" di beranda DIHAPUS** (beserta pengelolaannya; tabel `slider_kecil`
//       dibiarkan utuh di basis data, hanya tidak dipakai lagi) — menu admin menjadi **Media Sosial**;
//   (d) tombol media sosial **diperbesar & disejajarkan** dengan galeri di atasnya.
// 1.2.0 = PERBAIKAN TAMPILAN BERANDA (permintaan pemilik) + skema v2:
//   (a) **Berita Terbaru 4 berita** dalam susunan 2 kiri & 2 kanan (kartu sendiri), kartu PPDB tetap
//       di sebelah kanannya;
//   (b) **Program Unggulan dipindah KE BAWAH berita** dan ditampilkan sebagai **slider**;
//   (c) di bawah program unggulan ada **slider GURU (foto + nama) ukuran sedang**;
//   (d) **foto kepala madrasah** di atas kartu Sambutan — diunggah lewat akun admin
//       (Identitas Sekolah) → kolom baru `sekolah.kepala_foto` (skema v2);
//   (e) blok **Tautan Cepat** di bawah sambutan kepala DIHAPUS;
//   (f) bagian **Galeri & Media Sosial** menampilkan **slider foto** dari foto galeri yang diunggah.
// 1.1.0 = (a) DATA GURU: tombol CETAK (cetak_guru.php, A4 mendatar) + HAPUS SEMUA (konfirmasi
//         ketik HAPUS); (b) DATA SISWA: tombol CETAK (cetak_siswa.php) + HAPUS SEMUA (sekaligus
//         membersihkan riwayat kenaikan/kelulusan); (c) PENYUNTING ISI bergaya Word pada **Isi Berita**
//         dan **Isi Halaman**: Tebal/Miring/Garis bawah/Coret, perataan kiri-tengah-kanan-justify,
//         daftar berbutir & bernomor, judul bagian (H3), bersihkan format, serta **Sisip Gambar**
//         (diunggah ke media proxy lalu disisipkan; ukuran menyesuaikan lebar otomatis) —
//         `bersihkan_html()` diperluas agar menyimpan perataan (text-align) & ukuran gambar;
//         (d) TAMPILAN: kartu Keunggulan & Program Unggulan masing-masing punya tombol
//         Tambah + formulirnya sendiri (sebelumnya tidak ada tombol Tambah sehingga terasa
//         'tidak bisa menambahkan'); (e) tombol PPDB pada menu navigasi diperkecil.
define('APP_VERSION', '1.19.1');
define('APP_AUTHOR', 'Supianto');
define('APP_AUTHOR_JABATAN', 'Pengembang Aplikasi');

// ------------------------------------------------------------------
// Lokasi berkas & sesi. Tautan aplikasi selalu RELATIF karena aplikasi
// dilayani pada subfolder (https://<domain>/web-sekolah).
// ------------------------------------------------------------------
define('APP_DIR', dirname(__DIR__));
define('DB_FILE', APP_DIR . '/data/sekolah.sqlite');
define('SES_DIR', APP_DIR . '/data/sessions');
define('SCHEMA_FILE', APP_DIR . '/data/.schema_ok');
define('MEDIA_ENDPOINT', 'http://127.0.0.1:4310/api/app-media/upload');

// ------------------------------------------------------------------
// SUMBER DATA "INFORMASI BOS" (menu Informasi → Dana Bos).
// Halaman itu menampilkan Papan Informasi Dana BOS PERSIS seperti halaman depan aplikasi e-BOS,
// dengan data yang dibaca LANGSUNG (baca-saja) dari basis data aplikasi e-BOS di server yang sama —
// jadi setiap pembaruan di aplikasi e-BOS otomatis ikut tampil di website, tanpa disalin ulang.
// Dapat diarahkan ulang lewat tabel `pengaturan` (kunci `bos_db`, diisi alamat berkas .sqlite).
// PENTING: dibaca read-only; modul `inc/bos.php` TIDAK memuat kode aplikasi e-BOS.
define('EBOS_DB_FILE', dirname(APP_DIR) . '/e-bos/data/ebos.sqlite');
define('EBOS_URL', 'https://supianto.vibecoder.co.id/e-bos');

// ------------------------------------------------------------------
// SUMBER DATA "INFORMASI SARPRAS" (menu Informasi → Sarpras).
// Halaman itu menampilkan **Daftar Inventaris Barang** (Sarana & Prasarana) milik madrasah ini,
// dengan data yang dibaca LANGSUNG (baca-saja) dari basis data aplikasi e-GURU — aplikasi yang
// sama dengan yang dipakai pengelola pada menu Tugas Tambahan "Sarana & Prasarana"
// (`tugas.php?kode=sarpras`). Jadi setiap penambahan/perubahan barang di e-GURU otomatis ikut
// tampil di website, tanpa disalin ulang.
// Dapat diarahkan ulang lewat tabel `pengaturan` (kunci `sarpras_db`, berisi alamat berkas .sqlite).
// PENTING: dibaca read-only; modul `inc/sarpras.php` TIDAK memuat kode aplikasi e-GURU dan hanya
// membaca tabel `sarpras_barang` + nama sekolah.
define('EGURU_DB_FILE', dirname(APP_DIR) . '/e-guru/data/eguru.sqlite');
define('EGURU_URL', 'https://asisten.guru');
// Cadangan bila nama sekolah website tidak sama dengan nama sekolah pada basis data e-GURU
// (biasanya tidak perlu — pencocokan memakai NAMA otomatis).
define('SARPRAS_SEKOLAH_ID', 1);

// ------------------------------------------------------------------
// SUMBER DATA "INFORMASI PERPUSTAKAAN" (menu Informasi → Perpustakaan).
// Halaman itu menampilkan EMPAT LAPORAN (Data Buku, Pengunjung, Peminjaman, Pengembalian) yang
// datanya dibaca LANGSUNG (baca-saja) dari basis data aplikasi **e-PERPUS** di server yang sama —
// jadi setiap pembaruan di e-PERPUS otomatis ikut tampil di website, tanpa disalin ulang.
// Dapat diarahkan ulang lewat tabel `pengaturan` (kunci `perpus_db`, berisi alamat berkas .sqlite).
// PENTING: dibaca read-only; modul `inc/perpustakaan.php` TIDAK memuat kode aplikasi e-PERPUS.
define('PERPUS_DB_FILE', dirname(APP_DIR) . '/perpustakaan/data/perpustakaan.sqlite');
define('PERPUS_URL', 'https://supianto.vibecoder.co.id/perpustakaan');
// Cadangan bila nama sekolah website tidak sama dengan nama sekolah pada basis data e-PERPUS
// (biasanya tidak perlu — aplikasi e-PERPUS melayani satu sekolah dan datanya dipakai apa adanya).
define('PERPUS_SEKOLAH_ID', 1);

// ------------------------------------------------------------------
// SUMBER DATA "INFORMASI SUPERVISI AKADEMIK" (menu Informasi → Supervisi Akademik).
// Halaman itu menampilkan **dashboard aplikasi Supervisi Akademik** (hasil supervisi, capaian tiap
// menu supervisi, peringkat guru, perlu ditindaklanjuti) dengan data yang dibaca LANGSUNG
// (baca-saja) dari basis data aplikasi Supervisi Guru di server yang sama — jadi setiap penilaian
// yang disimpan supervisor otomatis ikut tampil di website.
// Permintaan pemilik: **khusus MA. Sirojuth Tholibiin Mootilango** (nama sekolahnya ditulis di
// `SUPERVISI_SEKOLAH`; `SUPERVISI_SEKOLAH_ID` = cadangan bila nama di basis data berbeda).
// Dapat diarahkan ulang lewat tabel `pengaturan` (kunci `supervisi_db`, berisi alamat berkas .sqlite).
// PENTING: dibaca read-only; modul `inc/supervisi.php` TIDAK memuat kode aplikasi Supervisi Guru.
define('SUPERVISI_DB_FILE', dirname(APP_DIR) . '/supervisi-guru/data/supervisi.sqlite');
define('SUPERVISI_SEKOLAH', 'MA. Sirojuth Tholibiin Mootilango');
define('SUPERVISI_SEKOLAH_ID', 1);
define('SUPERVISI_URL', 'https://supianto.vibecoder.co.id/supervisi-guru');

// ------------------------------------------------------------------
// SUMBER DATA "INFORMASI E-VOTING OSIM" (menu Informasi → e-Voting OSIM).
// Halaman itu menampilkan **Papan Hasil Real-Count** aplikasi e-Voting OSIM (angka berjalan setiap
// beberapa detik) dengan data yang dibaca LANGSUNG (baca-saja) dari basis data aplikasi e-Voting di
// server yang sama. Permintaan pemilik: **khusus MA. Sirojuth Tholibiin Mootilango** (nama lembaga
// ditulis di `VOTING_LEMBAGA`; bila basis data memuat pemilihan lembaga lain, halaman memberi
// keterangan, bukan menampilkan data lembaga lain).
// **MENGIKUTI SETELAN APLIKASI**: bila panitia belum membuka hasil ke publik
// (`tampilkan_hasil` ≠ 1) halaman hanya menampilkan keterangan "hasil belum dibuka".
// Dapat diarahkan ulang lewat tabel `pengaturan` (kunci `voting_db`, berisi alamat berkas .sqlite).
// PENTING: dibaca read-only; modul `inc/voting.php` TIDAK memuat kode aplikasi e-Voting dan HANYA
// MENGHITUNG jumlah baris `pemilih` (data pemilih/token tidak pernah ditampilkan).
define('VOTING_DB_FILE', dirname(APP_DIR) . '/e-voting-osis/data/voting.sqlite');
define('VOTING_LEMBAGA', 'MA. Sirojuth Tholibiin Mootilango');
define('VOTING_POLLING', 5);          // jeda penyegaran angka (detik) — sama dengan aplikasi e-Voting
define('VOTING_ZONA', 'WITA');        // zona waktu aplikasi e-Voting (Asia/Makassar)

// ------------------------------------------------------------------
// SUMBER DATA "EVALUASI DIRI MADRASAH" (menu Informasi → Evaluasi Diri Madrasah).
// Halaman itu menampilkan **Indikator EDM** (nilai SKPM tiap aspek A-E) dengan data yang dibaca
// LANGSUNG (baca-saja) dari basis data aplikasi **EDM** di server yang sama — jadi setiap butir yang
// diisi koordinator langsung ikut tampil di website. Rumus nilainya mengikuti aplikasi EDM
// (Tingkat 1-4 → 25/50/75/100; SKPM = rata-rata aspek). Dapat diarahkan ulang lewat tabel
// `pengaturan` (kunci `edm_db`, berisi alamat berkas .sqlite).
// PENTING: dibaca read-only; modul `inc/edm.php` TIDAK memuat kode aplikasi EDM dan hanya membaca
// tabel `evaluasi` + kunci `tahun` pada `app_pengaturan`.
define('EDM_DB_FILE', dirname(APP_DIR) . '/edm/data/edm.sqlite');

// ------------------------------------------------------------------
// LAYANAN PIP (Program Indonesia Pintar) — menu "Layanan PIP" pada website.
// Isi halaman diisi admin dari panel `admin/pip.php`:
//   • teks "Informasi Seputar Program Indonesia Pintar" + berkas **SK Penerima PIP**;
//   • **Daftar Penerima PIP** (Nama, NISN, Kelas, Nominal, Kartu PIP, Keterangan/surat kuasa);
//   • **satu kata sandi** (ditentukan admin) yang diminta setiap kali Kartu PIP diunduh.
// Kata sandi disimpan sebagai HASH (bcrypt) seperti sandi akun lain — admin tidak dapat
// menampilkan kembali sandi lama; bila lupa, cukup tetapkan sandi baru.
define('PIP_SANDI_MIN', 4);
define('PIP_SANDI_MAKS', 60);
// Batas percobaan sandi Kartu PIP (per sesi) supaya tidak bisa ditebak berulang-ulang.
define('PIP_MAKS_COBA', 5);
define('PIP_MENIT_COBA', 10);
// Nama berkas unduhan Kartu PIP bila nama aslinya tidak diketahui.
define('PIP_KARTU_NAMA_BAWAAN', 'Kartu-PIP.pdf');
// Teks bawaan "Informasi Seputar Program Indonesia Pintar" (dapat diubah admin; disemai sekali).
define('PIP_INFO_BAWAAN', '<p><strong>Program Indonesia Pintar (PIP)</strong> adalah bantuan tunai '
    . 'pendidikan dari pemerintah bagi peserta didik dari keluarga kurang mampu agar dapat terus '
    . 'melanjutkan pendidikan sampai jenjang menengah. Bantuan diberikan kepada peserta didik '
    . 'pemegang <strong>Kartu Indonesia Pintar (KIP)</strong> atau yang ditetapkan sebagai penerima '
    . 'melalui data sekolah/madrasah.</p>'
    . '<h3>Tujuan Program</h3><ul>'
    . '<li>Meningkatkan akses dan kesempatan belajar peserta didik dari keluarga kurang mampu.</li>'
    . '<li>Mencegah peserta didik putus sekolah serta menarik kembali peserta didik yang sudah berhenti.</li>'
    . '<li>Memenuhi kebutuhan biaya pendidikan peserta didik, baik langsung maupun tidak langsung.</li>'
    . '</ul>'
    . '<h3>Manfaat yang Diterima Peserta Didik</h3><ul>'
    . '<li>Pembelian buku dan alat tulis serta perlengkapan belajar lainnya.</li>'
    . '<li>Biaya transportasi ke sekolah/madrasah bagi peserta didik yang bertempat tinggal jauh.</li>'
    . '<li>Biaya ujian, kegiatan praktik, dan kebutuhan pembelajaran lain yang menunjang.</li>'
    . '</ul>'
    . '<h3>Cara Penggunaan Kartu PIP di Madrasah Ini</h3><ol>'
    . '<li>Peserta didik atau orang tua mengambil <strong>Kartu PIP</strong> pada kolom Kartu PIP di '
    . 'tabel penerima di bawah (perlu kata sandi dari admin).</li>'
    . '<li>Cetak kartu tersebut, lalu bawa/isi <strong>surat kuasa</strong> yang tersedia pada kolom '
    . 'Keterangan untuk keperluan pencairan dana.</li>'
    . '<li>Jika ada data yang tidak sesuai atau kartu belum tersedia, hubungi admin madrasah.</li>'
    . '</ol>');

// Versi skema basis data. Setiap perubahan tabel/kolom WAJIB menaikkan angka ini DAN mengisi
// SCHEMA_KOLOM_WAJIB / SCHEMA_TABEL_WAJIB pada inc/db.php (lihat catatan di berkas itu).
// 1.12.0 = (a) EFEK pada kartu halaman **Data Guru** (permintaan pemilik: "Tambahkan efek pada
//   halaman data guru"): kartu terangkat saat disorot + garis aksen emas + foto membesar dengan
//   cincin emas + nama berubah hijau tua, terasa "ditekan" saat diklik, ada efek sentuhan untuk
//   perangkat sentuh (`initKartuSentuh()` di assets/app.js menambah kelas `.tersentuh`), dan mode
//   hemat gerak dihormati (`prefers-reduced-motion`).
//   (b) SLIDER THUMBNAIL VIDEO pada bagian "Galeri & Media Sosial" beranda (permintaan pemilik:
//   "tambahkan slider thumbail video pada yang saya beri tanda merah"): baris thumbnail video
//   YouTube (gambar `i.ytimg.com`) yang bisa digeser & bergeser sendiri, tombol panah ‹ › sendiri,
//   dan klik thumbnail membuka **popup pemutar** (`dialog#dialogVideoBeranda` — `src` iframe diisi
//   saat diklik lalu dikosongkan saat ditutup). Tanpa JavaScript thumbnail menuju bagian
//   "Video Kegiatan" halaman galeri. Tanpa kolom/tabel baru (memakai `galeri_video` + `youtube_id()`).
// 1.11.0 = PERANGKAT PEMBELAJARAN PER SEMESTER (skema v28) — permintaan pemilik: *"Yang saya tandai
//   warna merah di hapus. Kemudian diganti dengan filter Tahun Ajaran dan Semester. Misal Tahun
//   Ajaran 2026/2027 Semester Ganjil, karena perangkat pembelajaran itu per semester. Untuk inputan
//   di halaman super adminnya di sesuaikan saja"*.
//   (a) TABEL BARU `periode` (Tahun Ajaran + Semester; urutan, aktif, `bawaan` = periode yang sedang
//       berjalan) + menu Super Admin baru **"Tahun Ajaran & Semester"** (`admin/periode.php`).
//   (b) KOLOM BARU `perangkat_kelas.ta` & `perangkat_kelas.semester` — **berkasnya yang dipisah per
//       semester**, sedangkan daftar guru/kelas/mata pelajaran tetap SATU (pilihan pemilik).
//       Tabel `perangkat_kelas` DIBANGUN ULANG hanya untuk mengubah kunci unik menjadi
//       `(perangkat_id, kelas, ta, semester)`; baris lama disalin & diberi periode bawaan.
//   (c) HALAMAN PUBLIK: bagian yang diminta dihapus (kalimat "Satu mata pelajaran dapat diajarkan…"
//       + deretan chip ringkasan) DIGANTI **filter Tahun Ajaran & Semester**; pilihan bawaan =
//       periode `bawaan`. Tautan unduh membawa periode (`ta` + `sm`).
//   (d) PANEL SUPER ADMIN: pemilih periode di atas tabel; seluruh unggah/hapus berkas berlaku pada
//       periode yang sedang dipilih, dan setiap sel berkas menandai periode yang ditampilkan.
// 1.9.0 = KELAS pada PERANGKAT GURU (skema v26) — permintaan pemilik: "Halaman tampilan perangkat
//   guru tambahkan kelas, karena dalam satu mata pelajaran bisa saja ada di tiga kelas. Kemudian
//   tampilan tabelnya dibuat rapi seperti yang diupload. Kemudian pada bagian isian di super admin
//   disesuaikan lagi, setiap mata pelajaran kelasnya bisa diatur. Dan tampilan di super admin dibuat
//   tabel juga agar mudah mengisinya."
//   (a) KOLOM BARU `perangkat.kelas` (hanya MENAMBAH kolom) berisi daftar kelas sebuah mata
//       pelajaran (dipisah koma, mis. "Kelas X, Kelas XI, Kelas XII"); baris lama bernilai kosong
//       dan tetap tampil seperti semula (satu baris tanpa kelas) — tidak ada nilai yang ditebak.
//   (b) HALAMAN PUBLIK memakai kolom **Kelas** di antara Nama Guru dan Mata Pelajaran:
//       **No, Nama Guru, Kelas, Mata Pelajaran, Capaian Pembelajaran (CP), Alur Tujuan Pembelajaran
//       (ATP), RPP/Modul Ajar** — satu baris per (kelas × mata pelajaran) dan nama guru tetap
//       digabung satu sel (`rowspan`). Urutan baris mengikuti urutan kelas pada menu Data Kelas.
//   (c) PANEL SUPER ADMIN berbentuk **TABEL** (mudah diisi): No, Nama Guru, Kelas, Mata Pelajaran,
//       CP, ATP, RPP/Modul Ajar, Tindakan — tiap baris punya kotak centang kelas (dari Data Kelas)
//       + isian "kelas lain" (kelas yang belum terdaftar), tiga isian berkas, tombol Simpan, dan
//       tombol Hapus baris. Formulir tiap baris memakai atribut `form="…"` (HTML tidak mengizinkan
//       formulir bersarang di dalam baris tabel).
// 1.8.11 = MENU "PERANGKAT GURU" (skema v25) — permintaan pemilik: "Pada bagian perangkat guru isi
//   dibuat seperti contoh yang saya upload. Dan isiannya dibuat di akun super admin. Ketika ada file
//   yang diupload maka akan centang hijau dan jika belum maka silang merah. Untuk data gurunya
//   otomatis dari jumlah data guru (Kepala Madrasah tidak termasuk), dan untuk mata pelajarannya
//   setiap guru bisa lebih dari satu. Pengunjung Ketika akan klik download maka akan muncul popup
//   masukkan kode download. Kode download di seting/generate oleh super admin untuk setiap mata
//   pelajaran berbeda."
//   (a) JENIS HALAMAN BARU `perangkat` (migrasi v25 `db_menu_perangkat()`; sub menu "Perangkat Guru"
//       yang tadinya halaman kosong otomatis berubah jenisnya).
//   (b) TABEL BARU: `perangkat` (satu baris per guru × mata pelajaran; menyimpan URL media proxy +
//       nama berkas untuk CP, ATP, dan RPP/Modul Ajar) dan `perangkat_kode` (KODE UNDUH per MATA
//       PELAJARAN, diatur/di-generate Super Admin — tiap mata pelajaran berbeda).
//   (c) DAFTAR GURU OTOMATIS dari tabel `guru` (status aktif, **jabatan bukan Kepala Madrasah**);
//       MATA PELAJARAN tiap guru diambil dari kolom `mapel` (boleh lebih dari satu, dipisah koma)
//       ditambah mata pelajaran tambahan yang dibuat Super Admin pada panel.
//   (d) Halaman publik menampilkan tabel **No, Nama Guru, Mata Pelajaran, Capaian Pembelajaran (CP),
//       Alur Tujuan Pembelajaran (ATP), RPP/Modul Ajar**; tiap sel berisi **centang hijau** (berkas
//       sudah diunggah) atau **silang merah** (belum) + tombol **Download**.
//   (e) KODE UNDUH: menekan Download memunculkan **popup "Masukkan kode download"** (dialog; tanpa
//       JavaScript dialihkan ke halaman `perangkat_unduh.php` yang memuat formulir yang sama).
//       Kode diperiksa di server (batas percobaan per sesi) dan berkasnya **DIALIRKAN server** —
//       URL media tidak pernah dikirim ke peramban, jadi kode benar-benar melindungi berkas.
//   (f) PANEL SUPER ADMIN `admin/perangkat.php`: mengunggah/menghapus berkas tiap guru × mata
//       pelajaran serta menyetel/meng-generate kode unduh tiap mata pelajaran.
//   Uji: `bash websk_uji_perangkat.sh` + `bash websk_uji_perangkat_ui.sh`.
// 1.8.10 = TAMPILAN halaman "Evaluasi Diri Madrasah" DISAMAKAN PERSIS dengan aplikasi EDM
//   (permintaan pemilik: "Hasil tidak rapi, buat sama persis dengan yang ada di aplikasi edm"):
//   (a) seluruh GAYA halaman disalin dari `edm/assets/style.css` (kertas krem #fffdf8, kartu tanpa
//       garis gradien atas, padding 18px/radius 12px, pil predikat kecil, tab bergaris bawah, tabel
//       padding 9px/10px + kepala hijau tua + kaki emas muda, kolom 52/130/170px, grafik 400px
//       maksimal 640px) — hanya nama kelasnya diberi awalan `.edm-`;
//   (b) susunan markup mengikuti `edm/rekap.php` (termasuk kelas bantu `.edm-mut/.edm-kecil/.edm-mb0`);
//   (c) opsi grafik radar disalin dari `edm/assets/app.js` (legenda bawah "EDM Tahun <tahun>",
//       tooltip "Nilai: x.x"/"belum diisi", label sumbu "nilai (Nama Aspek)", skala 0-100 langkah 25);
//   (d) DUA bagian tetap TIDAK dicetak: "Cetak Rekap" & "Ringkasan Pengisian Tiap Aspek".
//   Dibuktikan uji TERUKUR `websk_banding_edm_ui.sh` yang mengukur properti gaya + opsi grafik pada
//   halaman website dan halaman aplikasi EDM sekaligus (131 pemeriksaan, harus semuanya sama).
// 1.8.9 = MENU "EVALUASI DIRI MADRASAH" (skema v24) diisi dengan tampilan **Indikator EDM** dari
//   aplikasi EDM — permintaan pemilik: "isi dengan tampilan Indikator EDM pada aplikasi EDM, hanya
//   saja hilangkan Cetak Rekap dan Ringkasan Pengisian Tiap Aspek selebihnya buat sama."
//   (a) JENIS HALAMAN BARU `edm` (migrasi v24 `db_menu_edm()`; menu `evaluasi-diri-madrasah` yang
//       tadinya halaman kosong otomatis berubah jenisnya).
//   (b) SUMBER DATA dibaca LANGSUNG & HANYA-BACA dari basis data aplikasi **EDM**
//       (`EDM_DB_FILE` = `../edm/data/edm.sqlite`, `mode=ro`); hanya tabel `evaluasi` + kunci `tahun`
//       pada `app_pengaturan` yang dibaca. Rumus nilai disalin dari aplikasi EDM
//       (Tingkat 1-4 → 25/50/75/100, nilai aspek = rata-rata butir terisi, SKPM = rata-rata aspek,
//       predikat Sangat Baik ≥ 80 / Baik ≥ 60 / Cukup ≥ 40 / Kurang).
//   (c) DISAMAKAN kecuali dua bagian yang DIMINTA HILANG: **Cetak Rekap** dan **Ringkasan Pengisian
//       Tiap Aspek**. Selebihnya sama: kartu Indikator EDM (pemilih tahun + nilai SKPM + predikat +
//       "x dari N butir sudah diisi … nilai masih sementara"), tab **Charts/Tabel**, grafik **radar**
//       tiap aspek, dan tabel No/Aspek/Nilai SKPM/Terbilang + baris kaki SKPM.
//   (d) Aset: **Chart.js 4.4.1** (sudah tersedia) + penolong **`window.EDMW.radar()`**
//       (`assets/app.js`); gaya blok "EVALUASI DIRI MADRASAH" `assets/style.css` (kelas `.edm-*`).
//   Uji: `bash websk_uji_edm.sh` + `bash websk_uji_edm_ui.sh`.
// 1.8.8 = GRAFIK ALUMNI PER TAHUN pada halaman Data Alumni (permintaan pemilik: "grafik alumni
//   setiap tahunnya") + **KOMPONEN BERSAMA** kartu grafik "jumlah per tahun":
//   `inc/grafik_tahun.php` (`grafik_tahun_kartu()`) dipakai halaman **Pendaftar** (v1.8.7) dan
//   **Data Alumni**, dengan penolong JS `window.PFD.batang(id, labels, data, satuan)` (satuan
//   menentukan kata pada tooltip/sumbu). Angka alumni dari **`alumni_per_tahun()`**
//   (`inc/helpers.php`) — dihitung dari `alumni_gabungan()` (data kelulusan + tabel alumni, bebas
//   data ganda), hanya tahun yang ada alumninya. Grafik diletakkan DI ATAS filter tahun, hanya tahun
//   berdata, angkanya juga dicetak sebagai daftar teks, dan tidak berubah saat tahun difilter.
// 1.8.7 = GRAFIK PENDAFTARAN PER TAHUN pada halaman "Pendaftar" (menu Siswa → Pendaftar,
//   permintaan pemilik): grafik batang JUMLAH PENDAFTAR tiap tahun — **hanya tahun yang sudah ada
//   pendaftarnya** (tahun tanpa pendaftar seperti 2027 tidak ditampilkan) — diletakkan DI ATAS filter
//   tahun, memakai Chart.js yang sudah di-vendor + penolong baru **`window.PFD.batang()`**
//   (`assets/app.js`; sumbu Y bilangan bulat, terpisah dari `BOS`/`SVP` yang berformat lain) dan gaya
//   `.pf-grafik*` (`assets/style.css`). Daftar angka per tahun juga dicetak sebagai teks sehingga
//   tetap terbaca walau skrip gagal dimuat. Fungsi: **`ppdb_pendaftar_per_tahun()`** (`inc/pendaftar.php`).
// 1.8.5 = DATA PENDAFTAR (skema v22) — permintaan pemilik: "Pada menu siswa bagian pendaftar
//   masukkan data pendaftar dari 2022 sampai 2026 yang diambil dari alamat
//   https://ma.stm.sch.id/ppdb/panel_admin/verifikasi/thn/2022 … /2026. Untuk 2027 dan seterusnya
//   diambil dari jumlah pendaftar ppdb online website. Data tampilkan filter per tahun. Data yang
//   ditampilkan Nomer Pendaftaran, Nama Siswa, Asal Sekolah."
//   (a) HALAMAN MENU **"Pendaftar"** (sub menu Siswa; jenis halaman `pendaftar`, v23) + **filter per
//       tahun**; kolom: No, Nomor Pendaftaran, Nama Siswa, Asal Sekolah, Tahun (dipaginasi
//       10/halaman). Mula-mula isinya berupa TAB pada halaman Data Siswa, lalu DIPINDAH ke menu
//       Pendaftar atas permintaan pemilik ("pindahkan ke menu pendaftar"), dan daftar tahunnya
//       selalu memuat tahun PPDB Online yang sudah dibuka (mis. 2027 walau belum ada pendaftar).
//   (b) SUMBER 2022–2026 = **panel PPDB lama** (ma.stm.sch.id) yang WAJIB LOGIN; halaman admin
//       **`admin/ppdb_tarik.php`** ("Tarik Data Pendaftar (Tahun Lama)") menariknya memakai
//       nama pengguna & kata sandi yang diisi admin — **sandi TIDAK PERNAH disimpan** — lalu
//       menyimpannya di tabel **`ppdb_lama`** (skema v22). Penarikan ulang satu tahun MENGGANTI
//       data tahun itu saja. Logika di **`inc/ppdb_tarik.php`** (login curl, parser tabel,
//       penyimpanan) — parser diuji memakai contoh HTML panel (fixture) karena kredensial panel
//       tidak diikutkan pada uji.
//   (c) SUMBER 2027 ke atas = **PPDB Online website ini** (`ppdb_pendaftar`), tahunnya diambil dari
//       gelombang (`tahun_ajaran`, mis. "2027/2028" → 2027; cadangan `tanggal_daftar`), sehingga
//       tidak perlu penarikan apa pun — terisi sendiri saat ada pendaftar.
// v23 = MENU "PENDAFTAR" (sub menu Siswa) memakai JENIS HALAMAN BARU `pendaftar`: isi yang dahulu
//       menjadi TAB pada halaman Data Siswa dipindahkan ke menu Pendaftar (permintaan pemilik),
//       dan daftar tahunnya selalu memuat tahun PPDB Online yang sudah dibuka (mis. 2027).
//       Tanpa tabel/kolom baru — hanya penyesuaian satu baris `menu`; lihat db_menu_pendaftar().
// v22 = DATA PENDAFTAR TAHUN LAMA (`ppdb_lama`): tabel BARU berisi hasil TARIK data pendaftar
//       dari panel PPDB lama (ma.stm.sch.id, tahun 2022-2026) — Nomor Pendaftaran, Nama Siswa,
//       Asal Sekolah + tahun. Dibaca halaman publik "Data Siswa → Pendaftar" bersama data PPDB
//       Online website ini (2027 ke atas). Hanya CREATE TABLE/CREATE INDEX — tidak ada tabel lama
//       yang dibangun ulang; lihat inc/ppdb_tarik.php.
// v21 = INFORMASI E-VOTING OSIM: sub menu "e-Voting OSIM" memakai JENIS HALAMAN BARU `voting`
//       (Papan Hasil Real-Count aplikasi e-Voting — khusus MA. Sirojuth Tholibiin Mootilango,
//       angka berjalan tiap 5 detik, hanya tampil bila panitia membuka hasil). Tanpa tabel/kolom
//       baru — hanya penyesuaian satu baris `menu`; lihat db_menu_voting() pada inc/db.php.
// v20 = INFORMASI SUPERVISI AKADEMIK: sub menu "Supervisi Akademik" memakai JENIS HALAMAN BARU
//       `supervisi` (dashboard aplikasi Supervisi — khusus MA. Sirojuth Tholibiin Mootilango,
//       dengan saringan Tahun Ajaran & Semester). Tanpa tabel/kolom baru — hanya penyesuaian satu
//       baris `menu` (jenis `halaman` → `supervisi`); lihat db_menu_supervisi() pada inc/db.php.
// v19 = INFORMASI PERPUSTAKAAN: sub menu "Perpustakaan" memakai JENIS HALAMAN BARU `perpustakaan`
//       (empat tombol Laporan: Data Buku, Pengunjung, Peminjaman, Pengembalian — datanya dibaca
//       langsung dari aplikasi e-PERPUS). Tanpa tabel/kolom baru — hanya penyesuaian satu baris
//       `menu` (jenis `halaman` → `perpustakaan`) yang dijalankan sekali;
//       lihat db_menu_perpustakaan() pada inc/db.php dan inc/perpustakaan.php.
// v18 = TAHUN LAYANAN PIP: tabel BARU `pip_tahun` (satu baris = satu tahun; kolom `sk`, `sk_nama`,
//       dan `catatan` menempel pada tahun itu) + kolom `pip_penerima.tahun_id`. Seluruh SK, catatan,
//       dan penerima yang SUDAH ADA dipindahkan ke tahun pertama secara otomatis — tidak ada data
//       yang hilang. Lihat db_pip_tahun() pada inc/db.php.
// v17 = LAYANAN PIP: tabel BARU `pip_penerima` (daftar penerima Program Indonesia Pintar) + menu
//       "Layanan PIP" (jenis halaman `pip`) yang DIBUAT otomatis karena memang diminta pemilik
//       ("Buatkan Menu PIP") — lihat db_menu_pip() pada inc/db.php dan `inc/pip.php`.
//       Kartu PIP diunduh dengan KATA SANDI (disimpan sebagai hash) dan berkasnya DIALIRKAN server
//       (URL aslinya tidak pernah dikirim ke peramban).
// v16 = INFORMASI SARPRAS: sub menu "Sarpras" (slug `sarana-prasarana`) memakai JENIS HALAMAN BARU
//       `sarpras` (Daftar Inventaris Barang dari aplikasi e-GURU). Tanpa tabel/kolom baru — hanya
//       penyesuaian satu baris `menu` (jenis `halaman` → `sarpras`) yang dijalankan sekali;
//       lihat db_menu_sarpras() pada inc/db.php.
// v15 = INFORMASI BOS: sub menu "Dana Bos" memakai JENIS HALAMAN BARU `bos` (tampilan Papan
//       Informasi e-BOS). Tanpa tabel/kolom baru — hanya penyesuaian satu baris `menu`
//       (jenis `halaman` → `bos`) yang dijalankan sekali; lihat db_menu_bos() pada inc/db.php.
// v8 = perbaikan BARIS TEKS PPDB yang melebur (tanpa tabel/kolom baru; lihat
//      db_perbaiki_baris_ppdb() pada inc/db.php dan penanda `ppdb_baris_v1`).
// v14 = FAQ ASISTEN: tabel BARU `faq` (pertanyaan/jawaban/kata_kunci/urutan/aktif) untuk pertanyaan
//       yang belum terjawab otomatis oleh asisten obrolan. Tabel lama tidak disentuh.
// v13 = DOKUMEN IJOP & AKREDITASI: kolom `sekolah.berkas_ijop` & `sekolah.berkas_akreditasi`
//       (URL berkas pada media proxy — gambar/PDF diunggah dari panel Profil Lengkap). Hanya
//       MENAMBAH kolom; tombol "Lihat" hanya muncul bila berkasnya sudah diunggah.
// v12 = BAGAN STRUKTUR ORGANISASI PETA TETAP: kolom `struktur.kode` (slot tetap) & `struktur.foto`
//       (pas foto). Hanya MENAMBAH kolom — baris lama tidak dibangun ulang, dan baris lama dicocokkan
//       ke slot berdasarkan NAMA-nya (lihat db_struktur_peta() pada inc/db.php).
// v11 = TAHUN pada ALBUM GALERI (kolom `galeri_album.tahun`). Hanya MENAMBAH kolom — album lama
//       bernilai kosong (tidak ada nilai yang ditebak) dan tetap tampil tanpa keterangan tahun;
//       panel menyediakan tombol "Isi Tahun dari Tanggal Unggah" + peringatan untuk melengkapinya.
// v10 = PROFIL LENGKAP (kolom isian identitas tambahan pada `sekolah` + visi/misi/tujuan) dan
//       BAGAN STRUKTUR ORGANISASI (tabel `struktur`) — hanya MENAMBAH kolom/tabel baru, tidak ada
//       tabel yang dibangun ulang; lihat db_profil_struktur() pada inc/db.php.
// v9 = PERSYARATAN PPDB yang masih satu baris dipisah menjadi satu per baris agar tampil
//      bernomor 1, 2, 3, … (tanpa tabel/kolom baru; lihat db_ppdb_syarat_bernomor() dan
//      penanda `ppdb_syarat_nomor_v1`).
// v30 = MEMASTIKAN JENIS MENU KOHORT DITERAPKAN. Basis data LIVE sempat menuliskan penanda `29`
//       sebelum penyesuaian jenis menu ada di kode, sehingga menu Kohort Siswa tetap berjenis
//       `halaman`. v30 menjalankan seluruh migrasi (idempoten) sekali lagi.
//       ⚠️ PELAJARAN: JANGAN menaikkan SCHEMA_VERSION sebelum seluruh perubahan `inc/db.php` selesai
//       ditulis — aplikasi ini LIVE, jadi migrasi bisa berjalan kapan saja.
// v33 = TMT & KETERANGAN GURU: kolom `guru.tmt` (tanggal mulai tugas, `Y-m-d`) + `guru.keterangan`
//       (Satmikal / Non Satmikal) lewat db_add_column — tanpa membangun ulang tabel; guru lama
//       bernilai kosong (tidak ada nilai yang ditebak).
// v32 = PRESTASI SISWA PER KELAS: tabel BARU `prestasi_kelas` (peringkat 1-3 tiap kelas per tahun
//       ajaran & semester) + indeks — hanya CREATE TABLE/CREATE INDEX, tidak ada tabel lama yang
//       dibangun ulang. Dipakai blok "Kartu Prestasi Siswa per Kelas" pada halaman Prestasi Siswa
//       (diisi Super Admin atau akun Editor lewat admin/prestasi_kelas.php).
// v31 = KELULUSAN PADA KOHORT SISWA. Tabel `kohort` menambah EMPAT kolom angka pada grup "Lulus"
//       (Jumlah Lulus, Kuliah, Bekerja, Lainnya) — permintaan pemilik: "Tabel Kohort pada tampilan
//       ditambahkan Kelulusan, seperti yang di upload. Untuk grafiknya disesuaikan kembali karena
//       tambah kolomnya." Hanya MENAMBAH kolom (db_add_column) — tidak ada tabel yang dibangun ulang;
//       baris kohort yang sudah ada bernilai 0 sehingga tabel lama tidak berubah.
//       Versi ini juga membawa: akses INPUT KOHORT untuk akun Editor
//       (`boleh_kelola_kohort()`/`require_kelola_kohort()`) dan penghapusan kolom "Menu" pada kaki
//       halaman publik.
// v34 = MENU "KURIKULUM": tiga tabel BARU — `kurikulum_kaldik` (gambar Kalender Pendidikan),
//       `kurikulum_jadwal` (jadwal mengajar per hari/waktu/jam ke/kelas/mapel+guru) dan
//       `kurikulum_dokumen` (dokumen kurikulum yang dapat diunduh pengunjung) + 3 indeks. Hanya
//       CREATE TABLE/CREATE INDEX — tidak ada tabel lama yang dibangun ulang, dan tabel barunya
//       KOSONG (tidak ada contoh yang dipaksakan). Sekaligus menyesuaikan jenis tiga sub menu
//       Kurikulum yang sudah ada menjadi `kaldik`/`jadwal_mengajar`/`dokumen_kurikulum`
//       (lihat db_menu_kurikulum()). Permintaan pemilik: "Pada bagian Menu Kurikulum buatkan
//       isiannya di halaman admin dan operator (yelfis). Satu menu untuk beberapa isian …".
// v35 = MEMASTIKAN JENIS MENU KURIKULUM DITERAPKAN. Sama seperti kejadian v30 (Kohort Siswa):
//       aplikasi ini LIVE, sehingga sebuah permintaan pengguna sempat menjalankan migrasi SELA
//       antara `inc/db.php` sedang disunting — tabel kurikulum & penanda `34` sudah terpasang,
//       tetapi `db_menu_kurikulum()` belum ada di kode saat itu. Karena penanda sudah cocok,
//       migrasi tidak pernah jalan lagi dan ketiga sub menu Kurikulum tetap berjenis `halaman`
//       (halaman publiknya kosong). v35 menjalankan seluruh migrasi (idempoten) sekali lagi.
//       ⚠️ PELAJARAN (diulang): jangan menaikkan SCHEMA_VERSION sebelum SELURUH perubahan
//       `inc/db.php` selesai ditulis — dan bila hal ini terjadi lagi, naikkan versi satu langkah.
// v1.19.0 (TANPA perubahan skema) = penginputan jadwal DIPERCEPAT: formulir "Tambah Baris Jadwal"
//       memakai CENTANG kelas (boleh beberapa sekaligus, mis. 3 kelas) dengan isian mata pelajaran
//       & guru PER KELAS; satu kelas = satu baris jadwal, semua tersimpan dalam sekali simpan.
// v1.19.1 (TANPA perubahan skema) = AUTO-ISI WAKTU pada formulir "Tambah Baris Jadwal" (permintaan
//       pemilik: *"Bagian input waktu buat otomatis mengikuti hari yang sama waktunya. agar
//       penginputan menjadi lebih cepat dan tidak input manual waktu satu persatu"*): kolom Waktu
//       (dan Jam Ke) terisi sendiri dengan waktu baris TERAKHIR hari yang dipilih, berganti saat
//       Hari diganti (initJadwalWaktu di admin/admin.js memakai peta data-waktu-hari), dan kolom
//       Waktu menyediakan SARAN (<datalist>) seluruh waktu yang sudah dipakai hari itu.
//       Helper: kurikulum_jadwal_waktu_hari() (inc/kurikulum.php).
const SCHEMA_VERSION = '35';

// ------------------------------------------------------------------
// ------------------------------------------------------------------
// DAFTAR/DAFTAR-TABEL PUBLIK: banyaknya baris per halaman.
// Permintaan pemilik: "Data inventaris barang ditampilkan 1-10 dan ditambahkan tombol selanjutnya.
// Begitu juga laporan data buku, laporan pengunjung, laporan peminjaman, laporan pengembalian dan
// daftar penerima PIP." → SEMUA daftar itu memakai angka yang SAMA dan tombol "Selanjutnya"
// (helper `paginasi()` + `paginasi_info()` pada inc/helpers.php). Ubah di sini bila jumlahnya
// ingin berbeda — JANGAN menulis angka 10 langsung di halaman/modul.
const PUBLIK_PER_HALAMAN = 10;

// Batas unggahan
// ------------------------------------------------------------------
const GAMBAR_EKSTENSI = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
const GAMBAR_MAKS_BYTE = 15728640;          // 15 MB (batas media proxy untuk gambar)
const BERKAS_EKSTENSI = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
const BERKAS_MAKS_BYTE = 15728640;          // 15 MB untuk berkas pendaftaran PPDB

// ------------------------------------------------------------------
// PERANGKAT GURU (menu Kurikulum → Perangkat Guru) — berkas perangkat pembelajaran tiap guru.
// Jenis berkas yang disimpan (kolom pada tabel publik) + batas & pengaman kode unduh.
const PERANGKAT_JENIS = [
    'cp'  => 'Capaian Pembelajaran (CP)',
    'atp' => 'Alur Tujuan Pembelajaran (ATP)',
    'rpp' => 'RPP/Modul Ajar',
];
const PERANGKAT_EKSTENSI = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp'];
const PERANGKAT_MAKS_BYTE = 15728640;       // 15 MB per berkas (batas media proxy untuk dokumen)
const PERANGKAT_KODE_PANJANG = 8;           // panjang kode unduh yang di-generate
// KELAS pada tabel publik: satu mata pelajaran boleh dipakai di BEBERAPA kelas (mis. Fikih di
// kelas X, XI, dan XII). Daftar kelas diambil dari menu **Data Kelas**; kelas yang belum ada di
// sana boleh diketik manual. `PERANGKAT_KELAS_MAKS` = panjang maksimal SATU nama kelas, dan
// `PERANGKAT_KELAS_BANYAK` = jumlah maksimal kelas dalam satu baris mata pelajaran.
const PERANGKAT_KELAS_MAKS = 40;
const PERANGKAT_KELAS_BANYAK = 20;
// v1.11.0 — PERIODE (TAHUN AJARAN + SEMESTER): perangkat pembelajaran disusun PER SEMESTER,
// jadi berkas tiap kelas punya versi semester masing-masing. Daftar periode diatur Super Admin
// pada menu "Tahun Ajaran & Semester" (tabel `periode`). Semester bawaan boleh dipilih dari daftar
// ini atau ditulis sendiri (mis. "Antara").
const PERANGKAT_SEMESTER = ['Ganjil', 'Genap'];
const PERANGKAT_TA_MAKS = 20;               // panjang maksimal tulisan tahun ajaran
const PERANGKAT_SEMESTER_MAKS = 24;         // panjang maksimal tulisan semester
// Batas percobaan kode unduh per sesi (anti tebak), seperti Kartu PIP.
const PERANGKAT_MAKS_COBA = 5;
const PERANGKAT_MENIT_COBA = 10;

// ------------------------------------------------------------------
// KOHORT SISWA (menu Siswa → Kohort Siswa) — tabel analisis kohort + grafiknya.
// `KOHORT_TAHUN_TAMPIL` = banyaknya tahun ajaran TERBARU yang ditampilkan pada halaman publik.
// Menambah tahun ajaran baru membuat tahun paling lama tidak lagi tampil (posisi bergeser) —
// barisnya tetap tersimpan di basis data, hanya tidak dicetak. Ubah di sini bila pemilik ingin
// jumlah tahun yang berbeda; jangan menulis angka 5 langsung di halaman/modul.
const KOHORT_TAHUN_TAMPIL = 5;
const KOHORT_NILAI_MAKS = 100000;           // batas atas satu sel angka (anti salah ketik)
const KOHORT_TA_MAKS = 20;                  // panjang maksimal tulisan tahun ajaran

// ------------------------------------------------------------------
// MENU KURIKULUM (v1.18.0) — satu panel admin berisi TIGA isian:
//   1. Kaldik Operasional  → gambar Kalender Pendidikan (tabel `kurikulum_kaldik`)
//   2. Jadwal Mengajar     → hari, waktu, jam ke, kelas, mapel + guru (tabel `kurikulum_jadwal`)
//   3. Dokumen Kurikulum   → unggahan dokumen yang dapat diunduh pengunjung (`kurikulum_dokumen`)
//
// `KURIKULUM_HARI` = daftar hari yang boleh dipilih pada formulir jadwal (urutan tampil pada
// halaman publik juga mengikuti urutan ini). Baris jadwal yang JAM KE-nya kosong dianggap
// KEGIATAN (mis. "Sholat Dhuha", "Istirahat", "Sholat Dhuhur") dan dicetak sebagai satu baris
// yang menggabung seluruh kolom kelas — persis seperti contoh yang dikirim pemilik.
const KURIKULUM_HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
// Jenis berkas dokumen kurikulum — mengikuti batas MEDIA PROXY platform: dokumen (pdf/docx/xlsx/
// pptx/csv/txt/zip) + gambar (jpg/jpeg/png/webp). Format lama `.doc`/`.xls`/`.ppt` TIDAK
// dicantumkan karena penyimpanan platform menolaknya; pemilik memakai Word/Excel versi baru.
const KURIKULUM_EKSTENSI = ['pdf', 'docx', 'xlsx', 'pptx', 'csv', 'txt', 'zip', 'jpg', 'jpeg', 'png', 'webp'];
const KURIKULUM_MAKS_BYTE = 15728640;       // 15 MB per berkas (batas media proxy)
const KURIKULUM_JUDUL_MAKS = 160;
const KURIKULUM_KET_MAKS = 600;
const KURIKULUM_WAKTU_MAKS = 40;            // mis. "07.00-07.45"
const KURIKULUM_JAM_MAKS = 20;              // batas atas "jam ke-" (anti salah ketik)
const KURIKULUM_MAPEL_MAKS = 100;
const KURIKULUM_GURU_MAKS = 120;
const KURIKULUM_KELAS_MAKS = 40;
const KURIKULUM_LABEL_KEGIATAN = 'Kegiatan (menggabung semua kelas)';

// Sertifikat prestasi/pelatihan (dokumen PDF atau gambar) + daftar tingkat perlombaan.
const SERTIFIKAT_EKSTENSI = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
const SERTIFIKAT_MAKS_BYTE = 15728640;      // 15 MB
const TINGKAT_LIST = ['Kabupaten', 'Provinsi', 'Nasional'];

// Daftar APLIKASI untuk bagian beranda "Program Digitalisasi Madrasah" (satu sumber, dipakai
// penyemaian instalasi baru maupun migrasi basis data yang sudah berisi bagian "Program Unggulan").
const PROGRAM_DIGITAL_BAWAAN = [
    ['Aplikasi e-GURU', 'Administrasi guru digital: data guru & siswa, daftar hadir, penilaian, jurnal harian, dan tugas tambahan.', 'book', 'https://supianto.vibecoder.co.id/e-guru'],
    ['Aplikasi Supervisi Akademik', 'Supervisi pembelajaran, instrumen penilaian, jadwal supervisi, dan laporan hasil supervisi.', 'clipboard', 'https://supianto.vibecoder.co.id/supervisi-guru'],
    ['Aplikasi CBT', 'Ujian berbasis komputer: bank soal, token asesmen, koreksi otomatis, dan analisis hasil.', 'papan', 'https://supianto.vibecoder.co.id/cbt'],
    ['Aplikasi e-PKKM', 'Penilaian kinerja kepala madrasah beserta rekap dan laporannya.', 'medal', 'https://supianto.vibecoder.co.id/e-pkkm'],
    ['Aplikasi e-BOS', 'Pengelolaan dana BOS: komponen, realisasi, dan papan informasi transaksi.', 'grafik', 'https://supianto.vibecoder.co.id/e-bos'],
    ['Aplikasi e-Keuangan', 'Pencatatan pemasukan, pengeluaran, hutang-piutang, dan saldo kas madrasah.', 'star', 'https://supianto.vibecoder.co.id/keuangan'],
    ['Aplikasi e-PERPUS', 'Perpustakaan digital: data anggota & buku, peminjaman, pengembalian, dan kunjungan.', 'doc', 'https://supianto.vibecoder.co.id/perpustakaan'],
    ['Smart Teacher', 'Ruang aplikasi pembelajaran guru: capaian pembelajaran, pekan efektif, dan media interaktif.', 'mata', 'https://supianto.vibecoder.co.id/smart-teacher'],
    ['Portal Digital Layanan', 'Kumpulan layanan digital madrasah dalam satu halaman portal.', 'globe', 'https://supianto.vibecoder.co.id/portal-digital'],
];

// Pengambilan data alumni dari alamat web (direktori alumni) — jumlah halaman & baris dibatasi
// agar tidak membebani server sumber.
const ALUMNI_WEB_MAKS_HALAMAN = 40;
const ALUMNI_WEB_MAKS_BARIS = 2000;
const ALUMNI_WEB_URL_BAWAAN = 'https://ma.stm.sch.id/alumni-directory';

// ------------------------------------------------------------------
// Ukuran / batas teks
// ------------------------------------------------------------------
const JUDUL_MAKS = 160;
const RINGKASAN_MAKS = 400;
const TEKS_PANJANG_MAKS = 60000;            // isi halaman / berita (HTML sederhana)
const URL_MAKS = 300;
const NAMA_MAKS = 120;
const PESAN_MAKS = 3000;

// PPDB
const PPDB_PREFIX = 'PPDB';
// ------------------------------------------------------------------
// TARIK DATA PENDAFTAR TAHUN LAMA dari panel PPDB ma.stm.sch.id (lihat inc/ppdb_tarik.php).
// Permintaan pemilik: data 2022–2026 diambil dari panel lama; 2027 ke atas dari PPDB Online website.
// Halaman panel itu WAJIB LOGIN → nama pengguna & kata sandi panel diisi admin pada halaman
// "Tarik Data Pendaftar" dan **TIDAK PERNAH disimpan** ke basis data.
// Bila alamat panel berubah, cukup ubah konstanta ini (format %d = tahun).
define('PPDB_PANEL_VERIFIKASI', 'https://ma.stm.sch.id/ppdb/panel_admin/verifikasi/thn/%d');
// Halaman login panel (formulir login hanya diproses di alamat ini).
define('PPDB_PANEL_LOGIN', 'https://ma.stm.sch.id/ppdb/panel_admin/log_in');
define('PPDB_PANEL_TAHUN_MULAI', 2022);
define('PPDB_PANEL_TAHUN_AKHIR', 2026);
// Batas waktu & jumlah baris saat menarik data panel (asal sekolah diambil per pendaftar, jadi
// satu tahun bisa berarti puluhan permintaan — lihat inc/ppdb_tarik.php).
define('PPDB_PANEL_TIMEOUT', 30);
define('PPDB_PANEL_MAKS_BARIS', 600);
define('PPDB_PANEL_USERAGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36');
const PPDB_MAKS_BERKAS = 4;                 // jumlah berkas yang boleh diunggah pendaftar
const PPDB_BERKAS_LABEL = [
    'kartu_keluarga' => 'Kartu Keluarga',
    'akta'           => 'Akta Kelahiran',
    'ijazah'         => 'Ijazah / SKL',
    'foto'           => 'Pas Foto 3x4',
];
const PPDB_MAKS_SESI = 5;                    // maksimal kiriman formulir per sesi (anti spam)

// Batas percobaan masuk admin
const LOGIN_MAKS_GAGAL = 5;
const LOGIN_MENIT_BLOKIR = 10;

// Kelas bawaan (dipakai saat isi awal & pemilih kenaikan kelas)
const KELAS_BAWAAN = ['VII', 'VIII', 'IX'];

// Tema situs (dipakai pada <meta name="theme-color"> & CSS)
const TEMA_HIJAU = '#0b4a33';
const TEMA_EMAS = '#c9a227';

// ------------------------------------------------------------------
// Sesi: diberi pengaman (anti session fixation, HttpOnly, SameSite).
// ------------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!is_dir(SES_DIR)) {
        @mkdir(SES_DIR, 0777, true);
    }
    if (is_dir(SES_DIR) && is_writable(SES_DIR)) {
        ini_set('session.save_path', SES_DIR);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_name('wskolah');
    session_start();
}

date_default_timezone_set('Asia/Makassar'); // WITA (sesuai lokasi sekolah pada data awal)
setlocale(LC_ALL, 'id_ID');
