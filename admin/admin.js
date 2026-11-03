/* Panel admin Website Sekolah — menu HP + penolong impor Excel/CSV (SheetJS). */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    /* menu samping pada layar kecil */
    var tombol = document.getElementById('adminNavTombol');
    if (tombol) {
      tombol.addEventListener('click', function () {
        document.body.classList.toggle('nav-buka');
      });
    }
    /* jumlah karakter textarea */
    document.querySelectorAll('[data-hitung-karakter]').forEach(function (ta) {
      var keluar = document.querySelector(ta.getAttribute('data-hitung-karakter'));
      if (!keluar) { return; }
      var perbarui = function () { keluar.textContent = ta.value.length + ' karakter'; };
      ta.addEventListener('input', perbarui);
      perbarui();
    });
  });

  /* ------------------------------------------------------------------
     Impor Excel/CSV: berkas dibaca DI PERAMBAN memakai SheetJS lalu
     dikirim ke server sebagai JSON. Dipakai halaman impor guru & siswa.
     ------------------------------------------------------------------ */
  window.Websk = window.Websk || {};

  window.Websk.bacaBerkas = function (input, opsi) {
    var o = opsi || {};
    var berkas = input.files && input.files[0];
    if (!berkas) { return; }
    if (typeof XLSX === 'undefined') {
      window.alert('Pustaka pembaca Excel belum termuat. Muat ulang halaman lalu coba lagi, atau tempel data secara manual.');
      return;
    }
    var baca = new FileReader();
    baca.onload = function (e) {
      try {
        var buku = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
        var lembar = buku.Sheets[buku.SheetNames[0]];
        var baris = XLSX.utils.sheet_to_json(lembar, { header: 1, raw: false, defval: '' });
        baris = baris.filter(function (r) {
          return r.some(function (sel) { return String(sel).trim() !== ''; });
        });
        if (!baris.length) {
          window.alert('Berkas tidak memuat data yang dapat dibaca.');
          return;
        }
        if (typeof o.sukses === 'function') { o.sukses(baris, berkas.name); }
      } catch (err) {
        window.alert('Berkas gagal dibaca: ' + err.message);
      }
    };
    baca.readAsArrayBuffer(berkas);
  };
  /* ------------------------------------------------------------------
     AUTO-ISI WAKTU FORMULIR JADWAL KURIKULUM (v1.19.1 — permintaan pemilik)
     *"Bagian input waktu buat otomatis mengikuti hari yang sama waktunya. agar penginputan menjadi
     lebih cepat dan tidak input manual waktu satu persatu"*

     Server sudah mengisi kolom Waktu/Jam Ke dengan waktu baris TERAKHIR hari yang dipilih
     (kurikulum_jadwal_waktu_hari()). Skrip ini hanya MELENGKAPI: saat Hari diganti pada formulir,
     isian Waktu/Jam Ke ikut berganti mengikuti hari itu, saran waktu (datalist) diperbarui, dan
     keterangannya ikut menyesuaikan. Tanpa JavaScript halaman tetap bekerja (nilai awal dari server).
     ------------------------------------------------------------------ */
  (function initJadwalWaktu() {
    var form = document.querySelector('form[data-waktu-hari]');
    if (!form) { return; }

    var peta = {};
    try {
      peta = JSON.parse(form.getAttribute('data-waktu-hari') || '{}') || {};
    } catch (e) { peta = {}; }
    if (!peta || typeof peta !== 'object') { peta = {}; }

    var pilihHari = form.querySelector('#jadwal_hari');
    var isiWaktu = form.querySelector('#jadwal_waktu');
    var isiJam = form.querySelector('#jadwal_jam');
    var info = form.querySelector('#jadwalWaktuInfo');
    var saran = document.querySelector('#jadwalWaktuSaran');
    var otomatis = form.getAttribute('data-waktu-otomatis') === '1';

    var dataHari = function (hari) {
      var d = peta[hari];
      return (d && typeof d === 'object') ? d : null;
    };
    var daftarWaktu = function (hari) {
      var d = dataHari(hari);
      return (d && Array.isArray(d.daftar)) ? d.daftar : [];
    };

    function perbaruiSaran(hari) {
      if (!saran) { return; }
      while (saran.firstChild) { saran.removeChild(saran.firstChild); }
      daftarWaktu(hari).forEach(function (w) {
        var o = document.createElement('option');
        o.value = String(w);
        saran.appendChild(o);
      });
    }

    function perbaruiInfo(hari) {
      if (!info) { return; }
      if (!otomatis) {
        info.textContent = 'Ubah waktunya bila perlu — waktu baris ini tetap seperti semula bila tidak diubah.';
        return;
      }
      var d = dataHari(hari);
      var daftar = daftarWaktu(hari);
      if (d && d.waktu) {
        info.textContent = 'Terisi otomatis dari baris terakhir hari ' + hari + ' ('
          + daftar[daftar.length - 1] + '). Boleh diubah, atau pilih salah satu waktu yang sudah '
          + 'dipakai hari itu.';
      } else {
        info.textContent = 'Belum ada baris jadwal pada hari ini — isi waktunya satu kali '
          + '(mis. 07.00-07.45). Baris berikutnya pada hari yang sama akan terisi otomatis.';
      }
    }

    if (pilihHari) {
      pilihHari.addEventListener('change', function () {
        var hari = pilihHari.value;
        perbaruiSaran(hari);
        var d = dataHari(hari);
        // Hari tanpa baris jadwal → isian DIBIARKAN (jangan menghapus yang sudah diketik pengguna).
        if (otomatis && d && d.waktu && isiWaktu) {
          isiWaktu.value = String(d.waktu);
          if (isiJam) {
            isiJam.value = (parseInt(d.jam_ke, 10) > 0) ? String(parseInt(d.jam_ke, 10)) : '';
          }
        }
        perbaruiInfo(hari);
      });
    }

    perbaruiSaran(pilihHari ? pilihHari.value : '');
    perbaruiInfo(pilihHari ? pilihHari.value : '');
  })();
})();
