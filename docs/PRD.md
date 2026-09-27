# PRD Marketplace Pariwisata Daerah

## Ringkasan Produk

Wisata Daerah adalah marketplace untuk membantu wisatawan menemukan dan memesan pengalaman wisata lokal, sambil memberi pengelola desa dan usaha wisata kanal katalog serta operasional yang tertib. Peluncuran pertama berfokus pada satu kabupaten pilot dengan katalog lintas kategori dan transaksi tiket serta paket wisata.

## Sasaran Rilis Pilot

1. Wisatawan dapat menemukan destinasi berdasarkan wilayah dan kategori, lalu memahami informasi kunjungan dengan jelas.
2. Wisatawan dapat memesan satu tiket atau satu paket dari satu mitra dengan harga dan kuota yang dihitung server.
3. Mitra dan admin dapat mengelola konten, ketersediaan, dan pesanan sesuai kewenangan.
4. Tim operasional dapat mengonfirmasi pembayaran, menerbitkan voucher, serta mencatat pengecualian tanpa mengorbankan jejak audit.

## Persona dan Perjalanan Utama

### Wisatawan

Mencari kegiatan yang relevan, membandingkan informasi, memesan, membayar, dan memakai voucher saat berkunjung. Perjalanan: beranda → pencarian atau kategori → detail destinasi/paket → pilih tanggal dan jumlah peserta → checkout satu produk → pembayaran → status pesanan/voucher → check-in.

### Pengelola Desa atau Pemilik Usaha

Menyediakan informasi, foto, tiket, atau paket yang akurat dan mengelola operasional tanpa melihat data mitra lain. Perjalanan: dibuat/disetujui sebagai mitra → lengkapi profil → kirim konten untuk moderasi → atur produk dan kapasitas → lihat pesanan → verifikasi kunjungan → lihat laporan yang diizinkan.

### Staf Mitra dan Petugas Check-in

Mengerjakan tugas terbatas yang diberikan pemilik mitra. Perjalanan: login → melihat daftar kunjungan hari ini atau pemindai → validasi QR/kode sekali pakai → melihat hasil tanpa akses ke keuangan atau data mitra lain.

### Admin Wilayah

Menjaga kualitas katalog dan kelancaran pilot pada wilayah tugasnya. Perjalanan: kelola wilayah/kategori → tinjau mitra dan konten → moderasi publikasi → menangani transaksi/refund sesuai izin → melihat audit dan pengecualian.

### Super Admin

Mengelola kebijakan platform, akses admin, konfigurasi, dan audit lintas wilayah. Akses ini dibatasi, dicatat, dan bukan pengganti kontrol server-side untuk data mitra.

## Kategori dan Bentuk Layanan Pilot

| Kategori katalog | Fungsi pada pilot | Status transaksi |
| --- | --- | --- |
| Alam, budaya, religi | Informasi destinasi dan dapat memiliki tiket | Gratis atau berbayar |
| Paket desa | Itinerary beberapa titik wisata | Berbayar setelah aturan keberangkatan siap |
| Kuliner | Informasi lokasi pada katalog/desa | Informasi dulu |
| Penginapan | Informasi lokasi pada katalog/desa | Informasi dulu |

Destinasi informatif tidak sama dengan produk berbayar. Produk berbayar hanya aktif setelah harga, kebijakan, kuota, dan alur pembayaran yang relevan selesai diuji.

## Kebutuhan Fungsional Inti

- Katalog publik: beranda, pencarian, filter wilayah/kategori, halaman destinasi, desa, paket, artikel dan FAQ.
- Konten: wilayah, kategori, mitra, media, destinasi, serta moderasi dan revisi publikasi.
- Transaksi: tiket dan paket, quote server, inventori, checkout satu produk/satu mitra, pesanan tamu terverifikasi, pembayaran, voucher, pembatalan, refund, dan pencatatan keuangan.
- Operasional: peran, kebijakan akses, audit, pemindai QR, notifikasi, dashboard mitra/admin, rekonsiliasi, dan observabilitas.

## Kebutuhan Nonfungsional

- Antarmuka responsif di 360, 768, dan 1440 piksel; dapat dipakai keyboard, dengan label formulir dan status loading/kosong/gagal/berhasil.
- Backend menjadi sumber kebenaran atas harga, izin, kuota, dan status pembayaran.
- Setiap akses data mitra diverifikasi melalui policy dan scope backend.
- Timestamp teknis disimpan UTC; jadwal kunjungan memakai tanggal lokal dan zona waktu destinasi.
- Catatan transaksi berbayar dan keuangan dapat ditelusuri; koreksi dilakukan dengan penyesuaian/refund, bukan sunting bebas.

## Bukan Cakupan Pilot

Tiket pesawat, integrasi inventori hotel eksternal, dompet pengguna, transfer saldo antar pengguna, kredit, rekomendasi berbasis AI, dan keranjang lintas mitra.

## Acceptance Walkthrough Fase 01

1. Destinasi gratis: wisatawan menemukan detail, membaca biaya sebagai gratis, dan menghubungi/menavigasi tanpa checkout palsu.
2. Tiket berbayar: wisatawan memilih tanggal dan jumlah, menerima quote server, membuat satu pesanan, membayar melalui provider yang disetujui, dan menerima voucher sesudah konfirmasi.
3. Paket desa: wisatawan melihat itinerary, titik kumpul, minimum peserta, serta kepastian keberangkatan sebelum ditagih.

Ketiga alur di atas bersifat definisi produk. Alur tiket dan paket belum boleh diaktifkan produksi sebelum keputusan pada `docs/DECISIONS.md` selesai.
