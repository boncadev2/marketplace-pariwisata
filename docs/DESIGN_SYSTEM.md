# Design System — WisataDaerah

Status 2 Oktober 2026: seluruh 20 template halaman web memakai identitas dan komponen bersama. JavaScript tetap digunakan; tidak ada dependency UI tambahan.

## Arah visual

Inspirasi struktur marketplace perjalanan dari [Traveloka](https://www.traveloka.com/id-id) dan [Tiket](https://www.tiket.com/): navigasi produk, pencarian yang menonjol, kartu visual, alur pemesanan, serta akun/pesanan. Identitas WisataDaerah menggunakan biru, navy, oranye, latar terang, tipografi sistem, dan foto ilustrasi.

| Token | Nilai | Penggunaan |
| --- | --- | --- |
| --brand | #0870ce | Aksi utama dan tautan |
| --brand-dark | #075ba9 | Hover dan penekanan |
| --ink | #142b42 | Judul dan teks utama |
| --muted | #61758a | Teks pendukung |
| --line | #e1e9f1 | Batas kartu dan input |
| --surface | #f5f8fc | Latar halaman |
| --accent | #ef7f1a | CTA pencarian dengan teks gelap |
| --radius | 20px | Panel utama |

Token dan komponen CSS ada di frontend/src/app/design.css, diimpor globals.css. Konten desktop dibatasi 1240 px; panel 16–24 px radius; field/tombol utama minimal 44–48 px; whitespace dan ukuran heading menyesuaikan viewport. Warna sukses/peringatan/error dipertahankan sesuai makna.

## Komponen bersama

- Shell: header sticky, brand, navigasi produk, footer dan landmark main tunggal.
- SiteNavigation: menu seluler yang menutup saat berpindah route, active state produk, bottom navigation, breadcrumb, serta navigasi workspace.
- PageHeader: heading navy/biru dengan foto opsional dan deskripsi singkat.
- Card: foto, wilayah, judul, harga bila tersedia, dan CTA. Foto ilustrasi diberi label; rating dan promo tanpa data dihapus.
- EmptyState: state kosong/error dengan tindakan lanjut; loading skeleton dan fallback 404/error memakai identitas yang sama.
- AuthFrame: layout dua kolom desktop dan komposisi ringkas di ponsel.

## Rancangan per halaman

| Halaman | Rancangan dan interaksi |
| --- | --- |
| / | Hero foto, kategori produk, pencarian destinasi, inspirasi, kartu pengalaman dan akses akun |
| /destinasi | Filter wilayah/kategori, pencarian API, jumlah hasil, wishlist dan pagination |
| /destinasi/[slug] | Data destinasi terbit dari API, galeri ilustrasi, deskripsi, koordinat dan navigasi jika tersedia |
| /destinasi/demo | Pratinjau detail berlabel data demonstrasi |
| /paket | Katalog paket terbit dari API; pilihan diteruskan ke checkout |
| /paket/demo | Pratinjau itinerary dengan timeline dan informasi demonstrasi |
| /penginapan | Hero, panel tanggal/kamar/tamu, rincian harga serta reservasi simulasi |
| /kuliner | Hero, pilihan paket/waktu/peserta serta reservasi simulasi |
| /login | Form berlabel, password visibility, alur permintaan pemulihan dan link daftar |
| /daftar | Form berlabel, konfirmasi password, pendaftaran API, state berhasil/error |
| /checkout | Tahap pemesanan, produk bernama, data pemesan, quote, kupon dan ringkasan sticky |
| /pembayaran/[reference] | Instruksi sandbox, reference, penjelasan verifikasi, akses pesanan dan voucher |
| /akun | Header akun, navigasi tab, pesanan, wishlist dan profil; CTA masuk saat tanpa sesi |
| /bantuan | Header bantuan, akses cepat, tiket dan percakapan yang terhubung ke pesanan |
| /voucher | Header dan panel akses voucher/QR |
| /petugas | Workspace dan form validasi voucher/foto QR |
| /dashboard | Workspace, ringkasan, filter dan tabel transaksi; akses tetap mengikuti peran |
| /dashboard/lintas-desa | Header kolaborasi, panel konfigurasi dan alokasi mitra |
| /dashboard/persetujuan-mitra | Header persetujuan, proposal, revisi dan keputusan mitra |
| /reconciliation | Workspace, laporan, ringkasan dan alarm; akses administrator tetap berlaku |

## Aksesibilitas dan integritas informasi

Semua field memiliki label; fokus keyboard terlihat; navigasi dan CTA memakai elemen semantik. Animasi menghormati reduced motion. Header/menu dan bottom navigation tidak memerlukan plugin baru. Overflow tabel/filter tetap berada dalam kontainernya.

Tidak ada skor ulasan, diskon atau cashback rekaan pada beranda. Foto yang belum berasal dari pengelola ditandai sebagai ilustrasi. State kosong API tidak diganti dengan katalog palsu. Sandbox, izin akses, status pembayaran dan kontrol pilot tetap ditampilkan bila memengaruhi keputusan pengguna.

## Verifikasi

- Lint, production build, formatter dan diff check lulus.
- Browser diperiksa pada desktop 1440×960 dan ponsel 390×844: halaman yang dibuka tidak menunjukkan overflow horizontal; input pada halaman utama memiliki label. Halaman dynamic detail yang tidak tersedia memakai 404 khusus.
- Interaksi pencarian beranda meneruskan query ke katalog API; menu seluler serta alur tampilan pemulihan password diperiksa tanpa mengirim kredensial.
- 15 pengujian API autentikasi, detail destinasi dan wishlist lulus (67 assertion).
- Belum merupakan UAT lengkap seluruh peran/skenario transaksi. Foto masih remote/ilustrasi; aset resmi mitra dan keputusan branding bisnis dapat menggantikannya kemudian.
