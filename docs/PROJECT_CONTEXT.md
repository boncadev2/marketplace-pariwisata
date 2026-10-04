# Konteks Proyek

## Tujuan

Membangun marketplace yang menghubungkan wisatawan dengan destinasi, pengelola wisata, paket desa, penginapan, dan usaha kuliner di satu kabupaten pilot.

## Ruang Lingkup Rilis Pilot

Rilis awal mencakup katalog destinasi, informasi wilayah dan desa, tiket, paket wisata, serta transaksi satu produk dari satu mitra dalam satu checkout. Penginapan, reservasi kuliner, keranjang lintas mitra, dan aplikasi mobile bukan bagian dari transaksi inti awal.

## Stack yang Direncanakan

- Frontend: Next.js 16 dengan JavaScript
- Backend API: Laravel 13 pada prefiks `/api/v1`
- Database: MySQL atau relasional yang setara
- Queue dan cache: Redis
- Autentikasi web: Sanctum dengan sesi cookie

Stack diinisialisasi pada Fase 02. Runtime Docker menggunakan Node.js 24 dan PHP 8.4; lockfile frontend dan backend disimpan dalam repositori.

JavaScript dipilih secara eksplisit untuk frontend. Fase 02 tidak boleh menambahkan konfigurasi atau file TypeScript kecuali pengguna mengubah keputusan ini kembali.

## Asumsi Aktif

- Nama kerja aplikasi: Wisata Daerah.
- Wilayah pilot, nama merek, dan mitra pertama belum ditentukan.
- Semua data awal nantinya adalah data demonstrasi yang diberi label jelas.
- Admin dapat membuat produk atas nama mitra; pendaftaran mandiri dikendalikan feature flag.
- Produk gratis tetap memiliki halaman informasi dan tidak memakai tombol pembayaran palsu.
- Nilai uang IDR nantinya disimpan sebagai integer, bukan floating point.

## Batasan Keselamatan Transaksi

- Jangan menyimpan atau menahan dana bebas tanpa kontrak dan skema provider yang disetujui.
- Harga, kuota, kebijakan, dan status pembayaran dihitung serta divalidasi di server.
- Tidak ada secret di Git maupun fixture data produksi untuk pengujian.
