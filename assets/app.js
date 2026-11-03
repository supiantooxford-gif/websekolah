/* ==========================================================================
   Website Sekolah — skrip halaman publik (dan sebagian panel admin).
   Seluruh pemakaian elemen selalu diperiksa lebih dulu supaya berkas ini
   aman dimuat pada halaman apa pun.
   ========================================================================== */
(function () {
  'use strict';

  function $(sel, akar) { return (akar || document).querySelector(sel); }
  function $$(sel, akar) { return Array.prototype.slice.call((akar || document).querySelectorAll(sel)); }

  /* ---------------- notifikasi mengapung ---------------- */
  window.notifikasi = function (pesan, jenis) {
    var isi = $('#notifIsi');
    if (!isi) { return; }
    var el = document.createElement('div');
    el.className = 'notif' + (jenis === 'merah' ? ' merah' : '');
    el.textContent = pesan;
    isi.appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity .3s, transform .3s';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-8px)';
      setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 320);
    }, 4200);
  };

  /* ---------------- grafik PAPAN INFORMASI BOS (Chart.js) ----------------
     Dipakai halaman "Informasi BOS" (menu Informasi → Dana Bos). Bentuk grafiknya sama dengan
     halaman depan aplikasi e-BOS: donat proporsi serapan per komponen + batang pemasukan/
     pengeluaran per bulan. Chart.js hanya dimuat pada halaman itu; bila gagal dimuat, grafik
     tidak digambar dan halaman tetap berfungsi (angka & tabelnya tetap tampil). */
  var BOS_PALET = ['#0b4a33', '#178a5f', '#c9a227', '#6d1b25', '#1d6f8b', '#2aa87a', '#a9741b', '#14556b'];

  function bosUang(v) {
    var n = Number(v) || 0;
    if (Math.abs(n) >= 1000000000) { return (n / 1000000000) + ' M'; }
    if (Math.abs(n) >= 1000000) { return (n / 1000000) + ' jt'; }
    if (Math.abs(n) >= 1000) { return (n / 1000) + ' rb'; }
    return n;
  }

  window.BOS = {
    chart: function (id, cfg) {
      var el = document.getElementById(id);
      if (!el || typeof Chart === 'undefined') { return null; }
      return new Chart(el, cfg);
    },
    /* Batang PEMASUKAN & PENGELUARAN per bulan (dua deret). */
    banding: function (id, labels, masuk, keluar) {
      return window.BOS.chart(id, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [
            { label: 'Pemasukan', data: masuk, backgroundColor: BOS_PALET[1], borderRadius: 5, maxBarThickness: 26 },
            { label: 'Pengeluaran', data: keluar, backgroundColor: BOS_PALET[3], borderRadius: 5, maxBarThickness: 26 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { labels: { font: { family: 'Inter, system-ui' }, boxWidth: 12 } },
            tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': Rp ' + (Number(c.raw) || 0).toLocaleString('id-ID'); } } }
          },
          scales: { y: { beginAtZero: true, ticks: { callback: bosUang } } }
        }
      });
    },
    /* Donat proporsi realisasi tiap komponen. */
    donut: function (id, labels, data, warna) {
      return window.BOS.chart(id, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: warna || BOS_PALET, borderWidth: 2, borderColor: '#fff' }] },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '58%',
          plugins: {
            legend: { position: 'bottom', labels: { font: { family: 'Inter, system-ui' }, boxWidth: 12 } },
            tooltip: { callbacks: { label: function (c) { return c.label + ': Rp ' + (Number(c.raw) || 0).toLocaleString('id-ID'); } } }
          }
        }
      });
    },
    palette: function () { return BOS_PALET; }
  };

  /* ---------------- grafik DASHBOARD SUPERVISI AKADEMIK (Chart.js) ----------------
     Dipakai halaman "Informasi Supervisi Akademik" (menu Informasi → Supervisi Akademik) yang
     menampilkan dashboard aplikasi Supervisi Guru. Angkanya nilai supervisi (skala 0-100) —
     BUKAN rupiah seperti BOS — sehingga penolongnya dipisah supaya tidak saling mengubah format.
     Chart.js hanya dimuat pada halaman itu; bila gagal dimuat, halaman tetap berfungsi
     (semua angka & tabelnya tetap tampil). */
  var SVP_PALET = ['#0b4a33', '#178a5f', '#2f7fb5', '#c9a227', '#6d1b25', '#1d6f8b', '#2aa87a', '#a9741b'];
  var svpFont = { family: 'Inter, Segoe UI, system-ui' };

  window.SVP = {
    chart: function (id, cfg) {
      var el = document.getElementById(id);
      if (!el || typeof Chart === 'undefined') { return null; }
      return new Chart(el, cfg);
    },
    /* Batang nilai rata-rata (per menu supervisi / per mata pelajaran). */
    bar: function (id, labels, data, label) {
      return window.SVP.chart(id, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: label || 'Nilai',
            data: data,
            backgroundColor: SVP_PALET[1],
            hoverBackgroundColor: SVP_PALET[0],
            borderRadius: 6,
            maxBarThickness: 44
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (c) {
                  var v = Number(c.raw) || 0;
                  return (label || 'Nilai') + ': ' + v.toLocaleString('id-ID', { maximumFractionDigits: 1 });
                }
              }
            }
          },
          scales: {
            y: { beginAtZero: true, max: 100, ticks: { font: svpFont } },
            x: { ticks: { font: svpFont, autoSkip: false, maxRotation: 32, minRotation: 0 } }
          }
        }
      });
    },
    /* Donat sebaran predikat. */
    donut: function (id, labels, data, warna) {
      return window.SVP.chart(id, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{ data: data, backgroundColor: warna || SVP_PALET, borderWidth: 2, borderColor: '#fff' }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '58%',
          plugins: {
            legend: { position: 'bottom', labels: { font: svpFont, boxWidth: 12 } },
            tooltip: {
              callbacks: {
                label: function (c) { return c.label + ': ' + (Number(c.raw) || 0) + ' supervisi'; }
              }
            }
          }
        }
      });
    },
    /* Garis tren nilai rata-rata per bulan. */
    line: function (id, labels, data) {
      return window.SVP.chart(id, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Rata-rata nilai',
            data: data,
            borderColor: SVP_PALET[1],
            backgroundColor: 'rgba(23,138,95,.14)',
            fill: true,
            tension: .32,
            pointRadius: 3,
            pointBackgroundColor: SVP_PALET[0]
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (c) {
                  return 'Rata-rata: ' + (Number(c.raw) || 0).toLocaleString('id-ID', { maximumFractionDigits: 1 });
                }
              }
            }
          },
          scales: {
            y: { beginAtZero: true, max: 100, ticks: { font: svpFont } },
            x: { ticks: { font: svpFont } }
          }
        }
      });
    },
    palette: function () { return SVP_PALET; }
  };

  /* ---------------- papan HASIL REAL-COUNT E-VOTING OSIM (Chart.js) ----------------
     Dipakai halaman "Informasi e-Voting OSIM" (menu Informasi → e-Voting OSIM). Angkanya berjalan
     sendiri: halaman memuat `voting_hasil.php` (JSON) setiap `data-jeda` detik lalu memperbarui
     angka, bilah, legenda, dan kedua grafik tanpa memuat ulang halaman.
     ⚠️ Penyegaran DIHENTIKAN saat tab tidak terlihat dan dilanjutkan saat tab kembali aktif
     (hemat permintaan), dan kegagalan jaringan tidak pernah menghapus angka yang sudah tampil. */
  var SVV_PALET = ['#4f46e5', '#059669', '#d97706', '#db2777', '#0891b2', '#7c3aed', '#65a30d', '#e11d48'];
  var svvTimer = null, svvDonat = null, svvBatang = null, svvJalan = false;

  function svvAngka(v) {
    var n = Number(v) || 0;
    return n.toLocaleString('id-ID');
  }
  function svvPersen(v) {
    return (Number(v) || 0).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
  }
  function svvSet(sel, teks) {
    var el = document.querySelector('[data-vt="' + sel + '"]');
    if (el) { el.textContent = teks; }
  }
  function svvBar(sel, persen) {
    var el = document.querySelector('[data-vt-bar="' + sel + '"]');
    if (el) { el.style.width = Math.min(100, Math.max(0, Number(persen) || 0)) + '%'; }
  }

  /** Gambar/mutakhirkan kedua grafik dari data terbaru. */
  function svvGrafik(paslon) {
    var label = paslon.map(function (p) { return 'Paslon ' + p.nomor; });
    var nama = paslon.map(function (p) { return p.nama; });
    var suara = paslon.map(function (p) { return Number(p.suara) || 0; });
    var persen = paslon.map(function (p) { return Number(p.persen) || 0; });
    var warna = paslon.map(function (p, i) { return p.warna || SVV_PALET[i % SVV_PALET.length]; });

    var elD = document.getElementById('vtDonat');
    if (elD && typeof Chart !== 'undefined') {
      if (!svvDonat) {
        svvDonat = new Chart(elD, {
          type: 'doughnut',
          data: { labels: label, datasets: [{ data: persen, backgroundColor: warna, borderWidth: 2, borderColor: '#fff' }] },
          options: {
            responsive: true, maintainAspectRatio: false, cutout: '58%',
            plugins: {
              legend: { display: false },
              tooltip: { callbacks: { label: function (c) {
                var arr = (c.chart && c.chart.$suara) || [];
                return c.label + ': ' + svvPersen(c.raw) + ' (' + svvAngka(arr[c.dataIndex]) + ' suara)';
              } } }
            }
          }
        });
      } else {
        svvDonat.data.labels = label;
        svvDonat.data.datasets[0].data = persen;
        svvDonat.data.datasets[0].backgroundColor = warna;
        svvDonat.update('none');
      }
    }

    var elB = document.getElementById('vtBatang');
    if (elB && typeof Chart !== 'undefined') {
      if (!svvBatang) {
        svvBatang = new Chart(elB, {
          type: 'bar',
          data: { labels: label, datasets: [{ label: 'Suara', data: suara, backgroundColor: warna, borderRadius: 6, maxBarThickness: 42 }] },
          options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: { callbacks: { label: function (c) {
                var arr = (c.chart && c.chart.$persen) || [];
                return svvAngka(c.raw) + ' suara (' + svvPersen(arr[c.dataIndex]) + ')';
              } } }
            },
            scales: {
              y: { beginAtZero: true, ticks: { precision: 0 } },
              x: { ticks: { autoSkip: false } }
            }
          }
        });
      } else {
        svvBatang.data.labels = label;
        svvBatang.data.datasets[0].data = suara;
        svvBatang.data.datasets[0].backgroundColor = warna;
        svvBatang.update('none');
      }
    }
    /* Simpan deret mentah pada grafik supaya keterangan tooltip selalu memakai angka terbaru. */
    if (svvDonat) { svvDonat.$suara = suara; svvDonat.$nama = nama; }
    if (svvBatang) { svvBatang.$persen = persen; svvBatang.$nama = nama; }
  }

  /** Terapkan satu balasan JSON ke seluruh tampilan papan. */
  function svvTerapkan(d) {
    svvSet('dpt', svvAngka(d.dpt));
    svvSet('masuk', svvAngka(d.masuk));
    svvSet('belum', svvAngka(d.belum));
    svvSet('sah', svvAngka(d.sah));
    svvSet('turnout', svvPersen(d.turnout));
    svvBar('turnout', d.turnout);
    var jam = document.querySelector('[data-vt-jam]');
    if (jam && d.jam) { jam.textContent = d.jam; }

    (d.paslon || []).forEach(function (p) {
      var a = document.querySelector('[data-vt-persen="' + p.id + '"]');
      if (a) { a.textContent = svvPersen(p.persen); }
      var b = document.querySelector('[data-vt-persen2="' + p.id + '"]');
      if (b) { b.textContent = svvPersen(p.persen); }
      var c = document.querySelector('[data-vt-suara="' + p.id + '"]');
      if (c) { c.textContent = svvAngka(p.suara); }
      var bar = document.querySelector('[data-vt-bar-persen="' + p.id + '"]');
      if (bar) { bar.style.width = Math.min(100, Math.max(0, Number(p.persen) || 0)) + '%'; }
    });

    /* Pemimpin sementara: dihitung ulang dari daftar terbaru. */
    var teratas = null;
    (d.paslon || []).forEach(function (p) {
      if (Number(d.sah) > 0 && (teratas === null || Number(p.suara) > Number(teratas.suara))) { teratas = p; }
    });
    var blok = document.querySelector('[data-vt-pemimpin]');
    if (blok && teratas) {
      var nama = blok.querySelector('[data-vt-pemimpin-nama]');
      var suara = blok.querySelector('[data-vt-pemimpin-suara]');
      if (nama) { nama.textContent = teratas.nama; }
      if (suara) { suara.textContent = svvAngka(teratas.suara); }
      var nomor = blok.querySelector('.vt-nomor');
      if (nomor) {
        nomor.textContent = teratas.nomor;
        if (teratas.warna) { nomor.style.background = teratas.warna; }
      }
    }
    if (d.paslon && d.paslon.length) { svvGrafik(d.paslon); }
  }

  /** Satu kali ambil data (JSON). */
  function svvAmbil(akar, ulang) {
    fetch(akar.getAttribute('data-api') || 'voting_hasil.php', { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) { svvTerapkan(d); }
        /* Bila panitia menutup kembali hasilnya, halaman dimuat ulang supaya keterangannya tampil. */
        else if (d && d.buka === false && ulang) { clearTimeout(svvTimer); svvTimer = setTimeout(function () { location.reload(); }, 4000); }
      })
      .catch(function () { /* jaringan bermasalah: biarkan angka terakhir tetap tampil */ });
  }

  /* ---------------- grafik JUMLAH PER TAHUN (Chart.js) ----------------
     Dipakai halaman **Pendaftar** (jumlah pendaftar tiap tahun) dan **Data Alumni** (jumlah alumni
     tiap tahun) — hanya tahun yang sudah ada datanya. Sumbu Y memakai bilangan bulat (bukan 0-100
     seperti nilai supervisi), jadi penolongnya dipisah supaya tidak saling mengubah format.
     Parameter `satuan` (opsi, bawaan 'pendaftar') menentukan kata pada keterangan tooltip. */
  var PFD_PALET = ['#0b4a33', '#15795a', '#2c8f6b', '#4aa382', '#c9a227', '#a9741b', '#6d1b25'];

  window.PFD = {
    batang: function (id, labels, data, satuan) {
      var el = document.getElementById(id);
      if (!el || typeof Chart === 'undefined') { return null; }
      var kata = satuan || 'pendaftar';
      var maks = Math.max.apply(null, data.concat([1]));
      var warna = data.map(function (v, i) {
        return v === maks ? PFD_PALET[3] : PFD_PALET[i % PFD_PALET.length];
      });
      return new Chart(el, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Jumlah ' + kata,
            data: data,
            backgroundColor: warna,
            borderRadius: 6,
            maxBarThickness: 64
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (c) {
                  return 'Tahun ' + c.label + ': ' + (Number(c.raw) || 0).toLocaleString('id-ID') + ' ' + kata;
                }
              }
            }
          },
          scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Jumlah ' + kata } },
            x: { grid: { display: false }, ticks: { autoSkip: false } }
          }
        }
      });
    }
  };

  /* ---------------- grafik INDIKATOR EDM (Chart.js) ----------------
     Dipakai halaman "Evaluasi Diri Madrasah" (menu Informasi → Evaluasi Diri Madrasah).
     Opsi grafiknya DISALIN dari aplikasi EDM (assets/app.js -> initRekap) supaya tampilannya
     sama persis: radar skala 0-100, legenda di bawah ("EDM Tahun <tahun>"), tooltip
     "Nilai: x.x"/"belum diisi", dan label sumbu berbentuk "nilai (Nama Aspek)". */
  var EDM_HIJAU = '#14764f';

  window.EDMW = {
    radar: function (id, labels, data, tahun) {
      var el = document.getElementById(id);
      if (!el || typeof Chart === 'undefined') { return null; }
      var nilai = data || [];
      var judul = 'EDM Tahun ' + (tahun || '');
      return new Chart(el, {
        type: 'radar',
        data: {
          labels: labels,
          datasets: [{
            label: judul,
            data: nilai,
            borderColor: EDM_HIJAU,
            backgroundColor: 'rgba(20,118,79,.16)',
            pointBackgroundColor: EDM_HIJAU,
            pointBorderColor: '#fff',
            pointRadius: 4,
            borderWidth: 2.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  return 'Nilai: ' + (ctx.raw === 0 ? 'belum diisi' : Number(ctx.raw).toFixed(1));
                }
              }
            }
          },
          scales: {
            r: {
              min: 0, max: 100,
              ticks: { stepSize: 25, backdropColor: 'transparent', color: '#8a9a92', font: { size: 10 } },
              grid: { color: 'rgba(11,74,51,.16)' },
              angleLines: { color: 'rgba(11,74,51,.16)' },
              pointLabels: {
                color: '#3c4d45',
                font: { size: 11, weight: '600' },
                callback: function (label, i) {
                  var v = nilai[i];
                  return (v === 0 || v === undefined ? '-' : Number(v).toFixed(1)) + ' (' + label + ')';
                }
              }
            }
          }
        }
      });
    }
  };

  window.SVV = {
    mulai: function () {
      var akar = document.querySelector('[data-voting]');
      if (!akar || svvJalan) { return; }
      svvJalan = true;
      var jeda = Math.max(2, parseInt(akar.getAttribute('data-jeda') || '5', 10)) * 1000;

      if (window.VT_DATA && window.VT_DATA.paslon && window.VT_DATA.paslon.length) {
        svvGrafik(window.VT_DATA.paslon);
      }

      function putar() {
        clearTimeout(svvTimer);
        svvTimer = setTimeout(function () {
          if (!document.hidden) { svvAmbil(akar, true); }
          putar();
        }, jeda);
      }
      putar();
      /* Saat tab kembali dilihat, langsung segarkan supaya angkanya tidak tertinggal. */
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { svvAmbil(akar, false); }
      });
    }
  };

  /* ---------------- POPUP KODE DOWNLOAD (Perangkat Guru) ----------------
     Tautan "Download" pada tabel Perangkat Guru membuka `dialog#dialogKodeUnduh`; formulirnya
     dikirim ke `perangkat_unduh.php` (halaman yang sama bila JavaScript gagal dimuat). */
  function initKodeUnduh() {
    var dialog = document.getElementById('dialogKodeUnduh');
    if (!dialog) { return; }
    var form = document.getElementById('formKodeUnduh');
    var inG = document.getElementById('pgInGuru');
    var inM = document.getElementById('pgInMapel');
    var inJ = document.getElementById('pgInJenis');
    var inK = document.getElementById('pgInKelas');
    var inTa = document.getElementById('pgInTa');
    var inSm = document.getElementById('pgInSm');
    var ket = document.getElementById('pgDialogKet');
    var kode = document.getElementById('pgKode');

    $$('[data-pg-unduh]').forEach(function (a) {
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
        var url = a.getAttribute('href') || '';
        var m = /[?&]g=(\d+)/.exec(url), m2 = /[?&]m=([^&]*)/.exec(url), m3 = /[?&]j=([^&]*)/.exec(url);
        var m4 = /[?&]k=([^&]*)/.exec(url);
        var m5 = /[?&]ta=([^&]*)/.exec(url), m6 = /[?&]sm=([^&]*)/.exec(url);
        if (!m || !m2 || !m3) { window.location.href = url; return; }
        if (inG) { inG.value = m[1]; }
        if (inM) { inM.value = decodeURIComponent(m2[1]); }
        if (inJ) { inJ.value = decodeURIComponent(m3[1]); }
        if (inK) { inK.value = m4 ? decodeURIComponent(m4[1]) : ''; }
        if (inTa) { inTa.value = m5 ? decodeURIComponent(m5[1]) : ''; }
        if (inSm) { inSm.value = m6 ? decodeURIComponent(m6[1]) : ''; }
        if (ket) {
          var guru = a.getAttribute('data-pg-guru') || '';
          var mapel = a.getAttribute('data-pg-mapel') || '';
          var kelas = a.getAttribute('data-pg-kelas') || '';
          var periode = a.getAttribute('data-pg-periode') || '';
          var jenis = a.getAttribute('data-pg-jenis') || '';
          var ada = a.getAttribute('data-pg-ada') === '1';
          ket.textContent = jenis + ' — ' + guru + (mapel !== '' ? ' · ' + mapel : '')
            + (kelas !== '' ? ' (' + kelas + ')' : '')
            + (periode !== '' ? ' · ' + periode : '')
            + (ada ? '. Masukkan kode download untuk mengunduh berkasnya.'
                   : '. Berkas ini BELUM diunggah pengelola madrasah — kode tetap diminta.');
        }
        if (kode) { kode.value = ''; }
        if (typeof dialog.showModal === 'function') { dialog.showModal(); if (kode) { kode.focus(); } }
        else { window.location.href = url; }
      });
    });
    $$('[data-pg-tutup]').forEach(function (b) {
      b.addEventListener('click', function () { if (dialog.open) { dialog.close(); } });
    });
    // Klik di luar kotak dialog menutup popup (tanpa mengganggu tombol di dalamnya).
    dialog.addEventListener('click', function (ev) {
      var r = dialog.getBoundingClientRect();
      if (ev.clientX < r.left || ev.clientX > r.right || ev.clientY < r.top || ev.clientY > r.bottom) {
        dialog.close();
      }
    });
    if (form) {
      form.addEventListener('submit', function () {
        var kirim = form.querySelector('.pg-btn-kirim');
        if (kirim) { kirim.disabled = true; kirim.textContent = 'Mengunduh…'; }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initKodeUnduh();
    /* ---------------- menu HP ---------------- */
    var tombolNav = $('#tombolNav');
    var navUtama = $('#navUtama');
    if (tombolNav && navUtama) {
      tombolNav.addEventListener('click', function () {
        var buka = navUtama.classList.toggle('buka');
        tombolNav.setAttribute('aria-expanded', buka ? 'true' : 'false');
      });
      $$('#navUtama .ada-anak > a').forEach(function (a) {
        a.addEventListener('click', function (ev) {
          if (window.innerWidth <= 1080) {
            var anak = a.parentNode.querySelector('.nav-anak');
            if (anak && !a.parentNode.classList.contains('buka-anak')) {
              a.parentNode.classList.add('buka-anak');
              ev.preventDefault();
            }
          }
        });
      });
    }

    /* ---------------- slider beranda ---------------- */
    $$('[data-slider]').forEach(function (akar) {
      var slides = $$('.hero-slide', akar);
      if (slides.length < 2) { return; }
      var titikWadah = $('.hero-titik', akar);
      var jeda = parseInt(akar.getAttribute('data-jeda') || '6000', 10);
      var idx = 0, timer = null;

      slides.forEach(function (s, i) {
        if (i === 0) { s.classList.add('aktif'); }
        if (titikWadah) {
          var b = document.createElement('button');
          b.type = 'button';
          b.setAttribute('aria-label', 'Slide ' + (i + 1));
          if (i === 0) { b.classList.add('aktif'); }
          b.addEventListener('click', function () { tampil(i); mulai(); });
          titikWadah.appendChild(b);
        }
      });

      function tampil(i) {
        idx = (i + slides.length) % slides.length;
        slides.forEach(function (s, n) { s.classList.toggle('aktif', n === idx); });
        $$('.hero-titik button', akar).forEach(function (b, n) { b.classList.toggle('aktif', n === idx); });
      }
      function mulai() {
        if (timer) { clearInterval(timer); }
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
        timer = setInterval(function () { tampil(idx + 1); }, jeda);
      }
      var kiri = $('.hero-kiri', akar), kanan = $('.hero-kanan', akar);
      if (kiri) { kiri.addEventListener('click', function () { tampil(idx - 1); mulai(); }); }
      if (kanan) { kanan.addEventListener('click', function () { tampil(idx + 1); mulai(); }); }
      akar.addEventListener('mouseenter', function () { if (timer) { clearInterval(timer); timer = null; } });
      akar.addEventListener('mouseleave', mulai);
      mulai();
    });

    /* ---------------- slider mendatar (testimoni, program, guru, foto galeri) ----------------
       Satu mekanisme untuk semua: gulir-snap (tetap bisa digeser tanpa JavaScript) + tombol panah.
       `data-gulir-auto="6000"` membuatnya bergeser sendiri (berhenti saat disorot/disentuh/fokus,
       dan tidak berjalan bila pengguna meminta gerak minimal). */
    $$('[data-gulir]').forEach(function (wadah) {
      var jalur = $(wadah.getAttribute('data-gulir'), wadah);
      if (!jalur) { return; }
      function celah() {
        var g = parseFloat(getComputedStyle(jalur).columnGap || '0');
        return isNaN(g) ? 20 : g;
      }
      function langkah() {
        var anak = jalur.firstElementChild;
        return anak ? anak.getBoundingClientRect().width + celah() : jalur.clientWidth;
      }
      function geser(arah) {
        var maks = jalur.scrollWidth - jalur.clientWidth;
        var tujuan = jalur.scrollLeft + arah * langkah();
        if (arah > 0 && tujuan > maks + 4) {
          jalur.scrollTo({ left: 0, behavior: 'smooth' });   // kembali ke awal (berputar)
        } else {
          jalur.scrollBy({ left: arah * langkah(), behavior: 'smooth' });
        }
      }
      $$('[data-gulir-mundur]', wadah).forEach(function (b) {
        b.addEventListener('click', function () {
          if (jalur.scrollLeft <= 4) {
            jalur.scrollTo({ left: jalur.scrollWidth, behavior: 'smooth' });
          } else {
            jalur.scrollBy({ left: -langkah(), behavior: 'smooth' });
          }
        });
      });
      $$('[data-gulir-maju]', wadah).forEach(function (b) {
        b.addEventListener('click', function () { geser(1); });
      });

      // Bila isinya tidak melebihi lebar wadah (mis. hanya 3 foto untuk 3 kolom), tombol panah
      // disembunyikan dan geser otomatis dimatikan — supaya tidak ada tombol yang tidak berefek.
      function bisaDigeser() { return jalur.scrollWidth > jalur.clientWidth + 12; }
      function aturNavAktif() {
        var ada = bisaDigeser();
        $$('.gulir-nav, .gulir-titik-nav', wadah).forEach(function (n) { n.hidden = !ada; });
        return ada;
      }
      aturNavAktif();
      window.addEventListener('resize', function () { aturNavAktif(); });

      var jeda = parseInt(wadah.getAttribute('data-gulir-auto') || '0', 10);
      if (jeda > 0) {
        var jam = null;
        var henti = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function mulai() {
          if (henti || jam) { return; }
          jam = setInterval(function () {
            if (document.hidden || !bisaDigeser()) { return; }
            geser(1);
          }, jeda);
        }
        function tahan() { if (jam) { clearInterval(jam); jam = null; } }
        wadah.addEventListener('mouseenter', tahan);
        wadah.addEventListener('mouseleave', mulai);
        wadah.addEventListener('focusin', tahan);
        wadah.addEventListener('focusout', mulai);
        wadah.addEventListener('touchstart', tahan, { passive: true });
        jalur.addEventListener('scroll', function () { tahan(); mulai(); }, { passive: true });
        document.addEventListener('visibilitychange', function () { if (document.hidden) { tahan(); } else { mulai(); } });
        mulai();
      }
    });

    /* ---------------- popup "pendaftaran PPDB belum dibuka" ----------------
       Tautan berpenanda `data-ppdb-tutup` (tombol Daftar Sekarang saat pendaftaran ditutup)
       menampilkan pesan, bukan membuka formulir. Tanpa JavaScript tautannya tetap membuka
       ppdb.php yang memuat pesan yang sama, jadi tidak pernah menjadi jalan buntu. */
    (function () {
      var dlg = document.getElementById('dialogPpdbTutup');
      if (!dlg) { return; }
      function tampilkan() {
        if (typeof dlg.showModal === 'function') {
          if (!dlg.open) { dlg.showModal(); }
        } else {
          dlg.setAttribute('open', 'open');
        }
      }
      document.addEventListener('click', function (ev) {
        var a = ev.target && ev.target.closest ? ev.target.closest('[data-ppdb-tutup]') : null;
        if (!a) { return; }
        ev.preventDefault();
        tampilkan();
      });
      // Daftar tombol dibaca langsung lewat querySelectorAll (bukan penolong pintasan) —
      // penolong itu pernah berubah bentuk oleh alat penyunting sehingga `.forEach` melempar
      // galat dan SELURUH skrip halaman berhenti tanpa pesan yang jelas.
      Array.prototype.slice.call(dlg.querySelectorAll('[data-tutup-dialog]')).forEach(function (b) {
        b.addEventListener('click', function () { if (dlg.close) { dlg.close(); } else { dlg.removeAttribute('open'); } });
      });
      dlg.addEventListener('click', function (ev) {
        // Klik pada latar gelap (::backdrop) menutup dialog.
        if (ev.target === dlg) { if (dlg.close) { dlg.close(); } else { dlg.removeAttribute('open'); } }
      });
    })();

    /* ---------------- POPUP pemutar video beranda ----------------
       Thumbnail pada slider video (bagian "Galeri & Media Sosial") membuka popup pemutar YouTube.
       Tanpa JavaScript tautan thumbnya tetap menuju bagian "Video Kegiatan" di halaman galeri,
       jadi tidak pernah jalan buntu. `src` iframe diisi SAAT DIKLIK lalu DIKOSONGKAN saat ditutup
       supaya video berhenti (dan tidak ada video yang dimuat lebih dulu saat beranda dibuka). */
    (function () {
      var dlg = document.getElementById('dialogVideoBeranda');
      var isi = document.getElementById('videoBerandaIsi');
      var nama = document.getElementById('videoBerandaNama');
      if (!dlg || !isi) { return; }
      function tutup() {
        if (dlg.close) { dlg.close(); } else { dlg.removeAttribute('open'); }
      }
      document.addEventListener('click', function (ev) {
        var a = ev.target && ev.target.closest ? ev.target.closest('[data-video-beranda]') : null;
        if (!a) { return; }
        var yt = a.getAttribute('data-yt') || '';
        if (!/^[A-Za-z0-9_-]{11}$/.test(yt)) { return; }   // tak sah → biarkan tautannya berjalan
        ev.preventDefault();
        isi.setAttribute('src', 'https://www.youtube.com/embed/' + yt + '?autoplay=1&rel=0');
        if (nama) { nama.textContent = a.getAttribute('data-judul') || 'Video Kegiatan'; }
        if (typeof dlg.showModal === 'function') {
          if (!dlg.open) { dlg.showModal(); }
        } else {
          dlg.setAttribute('open', 'open');
        }
      });
      Array.prototype.slice.call(dlg.querySelectorAll('[data-tutup-dialog]')).forEach(function (b) {
        b.addEventListener('click', function () { isi.setAttribute('src', ''); tutup(); });
      });
      // Klik latar gelap atau tombol Esc → hentikan video.
      dlg.addEventListener('click', function (ev) { if (ev.target === dlg) { isi.setAttribute('src', ''); tutup(); } });
      dlg.addEventListener('close', function () { isi.setAttribute('src', ''); });
    })();

    /* ---------------- Efek kartu pada perangkat SENTUH ----------------
       Kartu Data Guru (`.guru-kartu`) memakai efek sorot lewat CSS `:hover` — efek itu tidak pernah
       tampil di perangkat sentuh. Skrip ini hanya PELENGKAP: saat kartu disentuh, kelas `tersentuh`
       dipasang sebentar supaya efeknya terasa. Tanpa JavaScript kartu tetap tampil normal. */
    (function () {
      var halus = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (halus || !('ontouchstart' in window)) { return; }
      $$('.guru-kartu').forEach(function (k) {
        k.addEventListener('touchstart', function () {
          k.classList.add('tersentuh');
          window.setTimeout(function () { k.classList.remove('tersentuh'); }, 450);
        }, { passive: true });
      });
    })();

    /* ---------------- tombol cetak (data-cetak) ----------------
       Dipakai halaman Profil Lengkap & Struktur Organisasi; tanpa JavaScript pengunjung tetap dapat
       mencetak lewat menu peramban (Ctrl/Cmd+P), jadi tombol ini hanya kemudahan. */
    $$('[data-cetak]').forEach(function (b) {
      b.addEventListener('click', function () { window.print(); });
    });

    /* ---------------- skala cetak dokumen (Profil Lengkap & Struktur Organisasi) ----------------
       Bagan struktur organisasi bisa LEBIH LEBAR daripada kertas A4 mendatar. Sebelum mencetak,
       elemen bertanda `data-cetak-lebar` diperkecil memakai `transform: scale()` (BUKAN `zoom`:
       `zoom` tidak memengaruhi ukuran yang benar-benar tercetak di Chromium) sehingga seluruh bagan
       muat pada satu lembar. Dipasang pada event `beforeprint` (juga terpicu Ctrl/Cmd+P) dan
       dikembalikan pada `afterprint`. Batas bawah 0,4 supaya tulisan tidak menjadi tak terbaca. */
    var skalaAsli = [];
    function pasangSkalaCetak() {
      skalaAsli = [];
      $$('[data-cetak-lebar]').forEach(function (el) {
        var lebar = parseFloat(el.getAttribute('data-cetak-lebar')) || 0;
        if (lebar <= 0) { return; }
        skalaAsli.push([el, el.style.transform, el.style.transformOrigin, el.style.marginBottom]);
        el.style.transform = '';
        // Bagan berada di dalam wadah yang dapat digeser — ukuran ISI-nya yang menentukan skala.
        var kanvas = el.querySelector('.org-kanvas');
        var asli = Math.max(el.scrollWidth, kanvas ? kanvas.scrollWidth : 0, el.getBoundingClientRect().width);
        if (asli > lebar) {
          // Faktor 0,98 memberi sedikit sisa tepi supaya tepi kanan bagan tidak terpotong.
          var s = Math.max(0.4, Math.floor(((lebar * 0.98) / asli) * 100) / 100);
          el.style.transformOrigin = 'top left';
          el.style.transform = 'scale(' + s + ')';
          // `transform` TIDAK mengurangi tinggi tata letak, jadi sisa tinggi dikompensasi dengan
          // margin negatif — tanpa ini lembar cetak menyisakan ruang kosong & menambah halaman.
          el.style.marginBottom = '-' + Math.round(el.offsetHeight * (1 - s)) + 'px';
        }
      });
    }
    function lepasSkalaCetak() {
      skalaAsli.forEach(function (p2) {
        p2[0].style.transform = p2[1] || '';
        p2[0].style.transformOrigin = p2[2] || '';
        p2[0].style.marginBottom = p2[3] || '';
      });
      skalaAsli = [];
    }
    window.addEventListener('beforeprint', pasangSkalaCetak);
    window.addEventListener('afterprint', lepasSkalaCetak);

    /* ---------------- perbesar/perkecil BAGAN STRUKTUR ORGANISASI ----------------
       Bagan tidak untuk dicetak sehingga boleh ditampilkan lebih lebar dari A4. Tombol
       [Perkecil]/[Ukuran Layar]/[Perbesar] mengubah lebar SVG di dalam wadah yang dapat digeser;
       tanpa JavaScript bagan tetap tampil penuh (lebar wadah) dan bisa di-zoom peramban. */
    (function () {
      var wadah = document.getElementById('orgBaganGulir');
      var svg = wadah ? wadah.querySelector('.org-svg') : null;
      if (!svg) { return; }
      var info = document.getElementById('orgZoomInfo');
      var skala = 1;
      var minS = 0.75, maksS = 3, langkah = 0.25;
      function terapkan() {
        svg.style.width = Math.round(skala * 100) + '%';
        if (info) { info.textContent = Math.round(skala * 100) + '%'; }
        $$('[data-zoom]').forEach(function (b) {
          var v = b.getAttribute('data-zoom');
          if (v === '-') { b.disabled = skala <= minS + 0.001; }
          if (v === '+') { b.disabled = skala >= maksS - 0.001; }
        });
      }
      $$('[data-zoom]').forEach(function (b) {
        b.addEventListener('click', function () {
          var v = b.getAttribute('data-zoom');
          if (v === '0') { skala = 1; } else { skala = Math.min(maksS, Math.max(minS, skala + (v === '+' ? langkah : -langkah))); }
          terapkan();
        });
      });
      terapkan();
    })();

    /* ---------------- ASISTEN AI: kotak obrolan mengambang ----------------
       Tombol mengambang adalah TAUTAN ke `asisten.php` (tetap bekerja tanpa JavaScript). Bila skrip
       aktif, tautannya dibatalkan dan panel obrolan yang dibuka; pertanyaan dikirim ke
       `chat_asisten.php` (JSON) lalu balasannya ditampilkan. Riwayat TIDAK disimpan. */
    var kotakAsisten = document.getElementById('asistenKotak');
    var tombolAsisten = document.getElementById('asistenTombol');
    if (kotakAsisten && tombolAsisten) {
      var isiAsisten = document.getElementById('asistenIsi');
      var formAsisten = document.getElementById('asistenForm');
      var pesanAsisten = document.getElementById('asistenPesan');
      var saranAsisten = document.getElementById('asistenSaran');
      // Avatar balasan asisten memakai foto yang sama dengan tombol mengambang (data-ava).
      var avaAsisten = tombolAsisten.getAttribute('data-ava') || '';
      var sudahIsi = false;
      var sedangKirim = false;

      var balon = function (teks, siapa, ket) {
        var b = document.createElement('div');
        b.className = 'asisten-balon asisten-' + siapa;
        if (siapa === 'asisten') {
          var i = document.createElement('span');
          i.className = 'asisten-ikon';
          if (avaAsisten) {
            var av = document.createElement('img');
            av.src = avaAsisten;
            av.alt = '';
            av.loading = 'lazy';
            i.appendChild(av);
          } else {
            i.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
              + 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H9l-5 4z"/>'
              + '<path d="M8 9h8M8 12h5"/></svg>';
          }
          b.appendChild(i);
        }
        var t = document.createElement('div');
        t.className = 'asisten-teks';
        t.textContent = teks;          // ⚠️ selalu textContent (isi berasal dari server/pengunjung)
        b.appendChild(t);
        if (ket) {
          var k = document.createElement('span');
          k.className = 'asisten-ket';
          k.textContent = ket;
          t.appendChild(k);
        }
        isiAsisten.appendChild(b);
        isiAsisten.scrollTop = isiAsisten.scrollHeight;
        return b;
      };

      var gambarSaran = function (daftar) {
        saranAsisten.textContent = '';
        (daftar || []).forEach(function (p) {
          var b = document.createElement('button');
          b.type = 'button';
          b.textContent = p;
          b.addEventListener('click', function () {
            pesanAsisten.value = p;
            kirimTanya();
          });
          saranAsisten.appendChild(b);
        });
      };

      var bukaAsisten = function () {
        kotakAsisten.hidden = false;
        if (!sudahIsi) {
          sudahIsi = true;
          fetch('chat_asisten.php?aksi=saran', { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
              balon(d.sapaan || 'Silakan bertanya.', 'asisten');
              gambarSaran(d.saran || []);
            })
            .catch(function () {
              balon('Assalamualaikum, silakan tuliskan pertanyaan Bapak/Ibu.', 'asisten');
            });
        }
        if (pesanAsisten) { pesanAsisten.focus(); }
      };
      var tutupAsisten = function () { kotakAsisten.hidden = true; };

      tombolAsisten.addEventListener('click', function (ev) {
        ev.preventDefault();
        if (kotakAsisten.hidden) { bukaAsisten(); } else { tutupAsisten(); }
      });
      $$('[data-asisten-tutup]', kotakAsisten).forEach(function (b) {
        b.addEventListener('click', tutupAsisten);
      });
      document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && !kotakAsisten.hidden) { tutupAsisten(); }
      });

      var kirimTanya = function () {
        var teks = (pesanAsisten.value || '').trim();
        if (teks === '' || sedangKirim) { return; }
        sedangKirim = true;
        balon(teks, 'pengguna');
        pesanAsisten.value = '';
        saranAsisten.textContent = '';
        var menunggu = balon('Sedang menyiapkan jawaban…', 'asisten');
        var fd = new FormData();
        fd.append('aksi', 'tanya');
        fd.append('pesan', teks);
        var csrf = formAsisten.querySelector('input[name=csrf]');
        if (csrf) { fd.append('csrf', csrf.value); }
        fetch('chat_asisten.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
          .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, kode: r.status, data: d }; }); })
          .then(function (h) {
            menunggu.remove();
            if (h.data && h.data.jawaban) {
              balon(h.data.jawaban, 'asisten',
                h.data.sumber === 'faq' ? 'jawaban admin'
                  : (h.data.sumber === 'website' ? 'data website' : 'sistem'));
            } else {
              balon((h.data && h.data.pesan) || 'Maaf, jawaban gagal diambil. Silakan coba lagi.',
                'asisten');
            }
            if (h.kode === 419) {
              balon('Halaman perlu dimuat ulang agar sesi diperbarui.', 'asisten');
            }
            gambarSaran([]);
          })
          .catch(function () {
            menunggu.remove();
            balon('Maaf, sambungan terputus. Periksa jaringan lalu coba lagi.', 'asisten');
          })
          .then(function () { sedangKirim = false; });
      };

      formAsisten.addEventListener('submit', function (ev) {
        ev.preventDefault();
        kirimTanya();
      });
    }

    /* ---------------- konfirmasi (data-confirm) ---------------- */
    $$('form[data-konfirmasi], a[data-konfirmasi]').forEach(function (el) {
      el.addEventListener('submit', function (ev) {
        if (!window.confirm(el.getAttribute('data-konfirmasi') || 'Yakin melanjutkan?')) { ev.preventDefault(); }
      });
      if (el.tagName === 'A') {
        el.addEventListener('click', function (ev) {
          if (!window.confirm(el.getAttribute('data-konfirmasi') || 'Yakin melanjutkan?')) { ev.preventDefault(); }
        });
      }
    });

    /* ---------------- pratinjau gambar yang dipilih ----------------
       CATATAN PENTING: pratinjau HANYA mengganti isi elemen gambar (`.pratinjau-gambar`),
       BUKAN wadahnya — wadah itu juga memuat input berkasnya sendiri. Mengganti isi wadah
       akan menghapus input dari DOM sehingga berkas tidak ikut terkirim (pernah terjadi). */
    $$('input[type=file][data-pratinjau]').forEach(function (inp) {
      inp.addEventListener('change', function () {
        var kotak = document.querySelector(inp.getAttribute('data-pratinjau'));
        if (!kotak) { return; }
        var berkas = inp.files && inp.files[0];
        if (!berkas) { return; }
        if (berkas.size > 15 * 1024 * 1024) {
          if (window.notifikasi) { window.notifikasi('Ukuran gambar melebihi 15 MB.', 'merah'); }
          inp.value = '';
          return;
        }
        var baca = new FileReader();
        baca.onload = function (ev2) {
          var img = kotak.querySelector('img');
          if (!img) {
            kotak.innerHTML = '';
            img = document.createElement('img');
            img.alt = 'Pratinjau';
            kotak.appendChild(img);
          }
          img.src = ev2.target.result;
        };
        baca.readAsDataURL(berkas);
      });
    });

    /* ---------------- formulir otomatis (data-auto) ---------------- */
    $$('[data-auto]').forEach(function (el) {
      el.addEventListener('change', function () {
        var f = el.form;
        if (f) { f.submit(); }
      });
    });

    /* ---------------- PENYUNTING ISI bergaya Word ----------------
       textarea tetap ada di markup (jalan tanpa JavaScript); skrip ini menyembunyikannya,
       menampilkan kotak contenteditable, dan menyinkronkan isinya kembali ke textarea
       sebelum formulir dikirim. */
    $$('[data-editor]').forEach(function (kotak) {
      var ta = kotak.querySelector('textarea.editor-sumber');
      var area = kotak.querySelector('.editor-isi');
      var status = kotak.querySelector('[data-editor-status]');
      if (!ta || !area) { return; }

      ta.classList.add('editor-sumber-tersembunyi');
      area.hidden = false;
      area.innerHTML = ta.value || '<p><br></p>';

      function sinkron() { ta.value = area.innerHTML; }
      function pesan(t) { if (status) { status.textContent = t; } }

      area.addEventListener('input', sinkron);
      area.addEventListener('blur', sinkron);
      var form = kotak.closest('form');
      if (form) { form.addEventListener('submit', sinkron); }

      // ---- bilah tombol (tebal, miring, garis bawah, perataan, daftar, dll.) ----
      kotak.querySelectorAll('[data-perintah]').forEach(function (tombol) {
        tombol.addEventListener('mousedown', function (ev) { ev.preventDefault(); }); // jangan lepas fokus
        tombol.addEventListener('click', function (ev) {
          ev.preventDefault();
          var perintah = tombol.getAttribute('data-perintah');
          var nilai = tombol.getAttribute('data-nilai');
          area.focus();
          try {
            if (perintah === 'formatBlock') { document.execCommand(perintah, false, '<' + nilai + '>'); }
            else { document.execCommand(perintah, false, nilai || null); }
          } catch (e) {
            if (window.notifikasi) { window.notifikasi('Perintah format tidak didukung peramban ini.', 'merah'); }
          }
          sinkron();
          pesan('Format diterapkan');
          perbaruiTombolAktif();
        });
      });

      // Tandai tombol format yang sedang aktif (tebal/miring/garis bawah/perataan).
      function perbaruiTombolAktif() {
        kotak.querySelectorAll('[data-perintah]').forEach(function (tombol) {
          var p = tombol.getAttribute('data-perintah');
          if (['bold', 'italic', 'underline', 'strikeThrough', 'justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull',
               'insertUnorderedList', 'insertOrderedList'].indexOf(p) === -1) { return; }
          var aktif = false;
          try { aktif = document.queryCommandState(p); } catch (e) { aktif = false; }
          tombol.classList.toggle('aktif', !!aktif);
        });
      }
      area.addEventListener('keyup', perbaruiTombolAktif);
      area.addEventListener('mouseup', perbaruiTombolAktif);

      // Jalan pintas papan ketik (Ctrl/Cmd + B/I/U).
      area.addEventListener('keydown', function (ev) {
        if (!(ev.ctrlKey || ev.metaKey)) { return; }
        var k = ev.key.toLowerCase();
        var peta = { b: 'bold', i: 'italic', u: 'underline' };
        if (peta[k]) {
          ev.preventDefault();
          document.execCommand(peta[k], false, null);
          sinkron();
          perbaruiTombolAktif();
        }
      });

      // ---- sisipkan gambar: dipilih dari perangkat, diunggah ke penyimpanan platform ----
      var masukan = kotak.querySelector('[data-sisip-gambar]');
      var simpanPilih = null;
      if (masukan) {
        area.addEventListener('keyup', () => { simpanSeleksi(); });
        area.addEventListener('mouseup', () => { simpanSeleksi(); });
        function simpanSeleksi() {
          var s = window.getSelection();
          if (s && s.rangeCount && area.contains(s.anchorNode)) {
            simpanPilih = s.getRangeAt(0).cloneRange();
          }
        }
        area.addEventListener('focus', simpanSeleksi);

        masukan.addEventListener('change', function () {
          var berkas = masukan.files && masukan.files[0];
          if (!berkas) { return; }
          if (berkas.size > 15 * 1024 * 1024) {
            if (window.notifikasi) { window.notifikasi('Ukuran gambar melebihi 15 MB.', 'merah'); }
            masukan.value = '';
            return;
          }
          var csrf = form ? form.querySelector('input[name=csrf]') : null;
          var data = new FormData();
          data.append('gambar', berkas);
          if (csrf) { data.append('csrf', csrf.value); }
          pesan('Mengunggah gambar…');
          fetch('upload_editor.php', { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              if (!j || !j.ok) {
                pesan('Gagal mengunggah gambar');
                if (window.notifikasi) { window.notifikasi((j && j.pesan) || 'Gambar gagal diunggah.', 'merah'); }
                return;
              }
              area.focus();
              if (simpanPilih) {
                var s = window.getSelection();
                s.removeAllRanges();
                s.addRange(simpanPilih);
              }
              document.execCommand('insertHTML', false,
                '<img src="' + j.url + '" alt="" style="max-width:100%;height:auto">');
              sinkron();
              pesan('Gambar disisipkan (ukuran menyesuaikan otomatis)');
              if (window.notifikasi) { window.notifikasi('Gambar disisipkan.'); }
            })
            .catch(function () {
              pesan('Gagal mengunggah gambar');
              if (window.notifikasi) { window.notifikasi('Gambar gagal diunggah (jaringan).', 'merah'); }
            })
            .then(function () { masukan.value = ''; });
        });
      }

      pesan('Penyunting aktif — tebal, miring, perataan, dan gambar tersedia');
    });

    /* ---------------- PAPAN INFORMASI BOS: Buku Kas buka/tutup ----------------
       Tanpa JavaScript tautan `?kas=1` tetap bekerja (server mencetak daftar terbuka). */
    (function () {
      var tombol = document.getElementById('btnKas');
      var isi = document.getElementById('isiKas');
      if (!tombol || !isi) { return; }
      var petunjuk = document.getElementById('kasPetunjuk');
      tombol.addEventListener('click', function (ev) {
        ev.preventDefault();
        var buka = isi.hidden;
        isi.hidden = !buka;
        if (petunjuk) { petunjuk.hidden = buka; }
        tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
        var label = tombol.querySelector('.bos-label-toggle');
        if (label) {
          var teks = buka ? tombol.getAttribute('data-label-tutup') : tombol.getAttribute('data-label-buka');
          if (teks) { label.textContent = teks; }
        }
      });
    })();

    /* ---------------- POPUP DATA GURU (halaman Tenaga Pendidik) ----------------
       Permintaan pemilik (v1.16.0): kartu guru dapat DIKLIK dan menampilkan popup berisi
       Nama Lengkap, Jenis Kelamin, Jabatan, Telepon, dan Masa Kerja (dihitung dari TMT).
       Data seluruh guru dicetak server pada <script id="guruData" type="application/json">,
       jadi popup dapat dibuka TANPA memuat ulang halaman.
       ⚠️ Tanpa JavaScript setiap kartu tetap sebuah TAUTAN (`?guru=ID`) yang membuka halaman
       dengan popup dalam keadaan terbuka — jangan menghapus atribut href pada kartunya. */
    (function () {
      var dialog = document.getElementById('popupGuru');
      var data = document.getElementById('guruData');
      var kartu = Array.prototype.slice.call(document.querySelectorAll('[data-popup-guru]'));
      if (!dialog || !data || !kartu.length || typeof dialog.showModal !== 'function') { return; }
      var daftar = {};
      try {
        (JSON.parse(data.textContent || '{}').guru || []).forEach(function (g) { daftar[String(g.id)] = g; });
      } catch (e) { return; }
      if (!Object.keys(daftar).length) { return; }

      var isi = function (sel) { return dialog.querySelector(sel); };
      var setTeks = function (sel, teks) { var el = isi(sel); if (el) { el.textContent = teks; } };

      /* Dialog mungkin sudah dicetak server dalam keadaan `open` (jalur cadangan `?guru=ID`).
         Dialog yang sudah terbuka TIDAK bisa di-showModal() (melempar InvalidStateError), jadi
         tutup dulu — sesudah itu baru dinaikkan menjadi modal supaya tampil sebagai popup rapi. */
      var bukaModal = function () {
        try {
          if (dialog.open) { dialog.close(); }
          dialog.showModal();
        } catch (e) {
          dialog.setAttribute('open', 'open');   // cadangan: CSS tetap memusatkannya
        }
      };

      var tampilkan = function (id) {
        var g = daftar[String(id)];
        if (!g) { return false; }
        setTeks('#popupGuruJudul', g.nama);
        setTeks('#popupGuruSub', g.sub || '');
        setTeks('#popupNama', g.nama);
        setTeks('#popupJk', g.jk);
        setTeks('#popupJabatan', g.jabatan || '-');
        setTeks('#popupTelepon', g.telepon || '-');
        setTeks('#popupMasaKerja', g.masa_kerja || '-');
        setTeks('#popupMasaKet', g.tmt ? 'sejak TMT ' + g.tmt : 'TMT belum diisi pada Data Guru.');
        var foto = isi('#popupFoto');
        if (foto) {
          foto.innerHTML = '';
          if (g.foto) {
            var img = document.createElement('img');
            img.src = g.foto;
            img.alt = 'Foto ' + g.nama;
            foto.appendChild(img);
          } else {
            var span = document.createElement('span');
            span.className = 'gd-popup-inisial';
            span.textContent = (g.inisial || '?');
            foto.appendChild(span);
          }
        }
        bukaModal();
        return true;
      };

      kartu.forEach(function (el) {
        el.addEventListener('click', function (ev) {
          if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button === 1) { return; }  // biarkan buka tab baru
          if (tampilkan(el.getAttribute('data-popup-guru'))) { ev.preventDefault(); }
        });
        el.addEventListener('keydown', function (ev) {
          if (ev.key === 'Enter' || ev.key === ' ') {
            if (tampilkan(el.getAttribute('data-popup-guru'))) { ev.preventDefault(); }
          }
        });
      });

      if (dialog.open) { bukaModal(); }   // pratinjau ?guru=ID → jadikan modal rapi

      Array.prototype.slice.call(document.querySelectorAll('[data-popup-tutup]')).forEach(function (t) {
        t.addEventListener('click', function () {
          if (typeof dialog.close === 'function') { dialog.close(); } else { dialog.removeAttribute('open'); }
        });
      });
      dialog.addEventListener('click', function (ev) {
        if (ev.target === dialog) {   // klik latar gelap
          if (typeof dialog.close === 'function') { dialog.close(); } else { dialog.removeAttribute('open'); }
        }
      });
    })();

    /* ---------------- animasi angka statistik ---------------- */
    var statAngka = $$('[data-hitung]');
    if (statAngka.length && 'IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (masuk) {
        masuk.forEach(function (m) {
          if (!m.isIntersecting) { return; }
          var el = m.target;
          io.unobserve(el);
          var tujuan = parseInt(el.getAttribute('data-hitung'), 10) || 0;
          var mulai = performance.now();
          (function naik(t) {
            var p = Math.min(1, (t - mulai) / 900);
            el.textContent = Math.round(tujuan * (1 - Math.pow(1 - p, 3))).toLocaleString('id-ID');
            if (p < 1) { requestAnimationFrame(naik); }
          })(mulai);
        });
      }, { threshold: 0.4 });
      statAngka.forEach(function (el) { io.observe(el); });
    }
  });
})();
