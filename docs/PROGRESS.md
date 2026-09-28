# Progress Implementasi

## Fase 23 Dashboard Pelanggan dan Klaim Pesanan Tamu

Status: sebagian terimplementasi pada 28 September 2026. Dashboard JavaScript `/akun` menampilkan pesanan yang telah ditautkan, filter status, total dan tanggal kunjungan; login pelanggan mengarah ke dashboard. API daftar/detail hanya mengambil `orders.user_id` milik sesi, tidak mengasosiasikan pesanan hanya berdasarkan alamat email. Klaim membutuhkan login, email akun terverifikasi, alamat email pesanan yang cocok, dan kode akses tamu 48 karakter. Klaim mengunci order, idempoten untuk pemiliknya, dan menolak transfer kepemilikan. Respons dibatasi ke ringkasan aman dan `Cache-Control: private, no-store`. Pesanan yang sudah diklaim dapat membuka QR voucher dari akun tanpa mengetik ulang kode tamu; akses token voucher ditolak bagi akun lain dan untuk order yang tidak lagi paid.

Registrasi telah memicu email verifikasi; sekarang tersedia endpoint kirim ulang dan tautan bertanda tangan untuk menyelesaikannya. Pada Compose lokal, email ditangkap Mailpit, bukan dikirim ke pelanggan nyata. Migrasi nullable `orders.user_id` diterapkan tanpa reset data. Verifikasi: suite penuh 77 test / 285 assertion lulus di SQLite in-memory. Tiga test concurrency MySQL dilewati di suite SQLite ini (telah lulus terisolasi 3 test / 19 assertion sebelumnya); lint frontend 0 error dengan 6 warning gambar pada perubahan beranda terpisah; build Next.js dan HTTP 200 `/akun` lulus.

Belum selesai: detail jadwal/kontak mitra, invoice/bukti sesuai kebijakan, tiket bantuan dan lampiran aman, wishlist/profil. Klaim Fase 23 belum dinyatakan selesai.

## Fase 22 Notifikasi dan Komunikasi Transaksi

Status: sedang dikerjakan — template, outbox dan pemicu transaksi utama tervalidasi lokal.

Lanjutan expiry 28 September: scheduler pelepasan hold sekarang mengubah order `pending_payment` menjadi `expired` hanya ketika semua hold item sudah expired, dan membuat event notifikasi idempoten dalam transaksi. Webhook pembayaran terlambat boleh merealokasi kuota dari `expired` bila masih tersedia; jika tidak, tetap masuk `payment_exception`. Pengirim outbox mengunci order lebih dulu dan menandai notifikasi yang sudah usang sebagai `superseded` tanpa SMTP, misalnya menunggu bayar sesudah paid atau voucher sesudah refund. Uji MySQL terisolasi untuk race expiry-vs-paid, scanner bersamaan, dan perebutan kuota terakhir: 3 test / 19 assertion lulus. Database uji MySQL tmpfs terpisah dari database aplikasi dan kontainernya dihentikan sesudah pengujian. Workflow perubahan jadwal/cancel/refund dan provider produksi masih belum tersedia.

Lanjutan outbox 28 September: tabel `notification_deliveries` menyimpan deduplication key unik, recipient/snapshot terenkripsi, status, jumlah percobaan, timestamp dan delivery log tanpa pesan error sensitif. Checkout mencatat awaiting_payment; webhook paid mencatat confirmation/voucher dalam transaksi order, tanpa SMTP atau dispatch di dalam transaksi. Poller terjadwal mengantrekan ID saja; worker memakai claim row untuk mencegah duplicate send sesudah acceptance, maksimal 3 percobaan dengan jeda 1/2 menit, terminal failed. Sending yang terputus lebih dari 5 menit menjadi uncertain dan tidak otomatis diulang. Inspeksi metadata tersedia melalui `notifications:dispatch-outbox --inspect`.

Verifikasi: migrasi tambahan MySQL berhasil tanpa reset; fixture local-only dikirim lewat worker dan terlihat di browser Mailpit, status sent dengan 1 attempt. Duplicate job berikutnya selesai tanpa pengiriman tambahan. Suite penuh SQLite in-memory: 58 test, 205 assertion lulus; 3 test concurrency MySQL skipped. Skill testing-best-practices digunakan untuk rollback, retry/provider failure, deduplication, encryption dan batas produksi. Formatter dan diff check lulus. Produksi nonaktif secara default, pengiriman lokal dipaksa ke Mailpit. SMTP acceptance bukan bukti delivered/exactly-once; retry pada kegagalan ambigu masih bisa menggandakan pesan maksimal 3 kali. Integrasi workflow expiry/jadwal/cancel/refund dan validasi provider produksi masih terbuka, sehingga Fase 22 belum dinyatakan selesai seluruhnya.

Tujuh template email tersedia: konfirmasi, menunggu bayar, kedaluwarsa, voucher, perubahan jadwal, pembatalan dan refund. Preview `/dev/notifications/{type}` menggunakan fixture tetap tanpa akses order pelanggan atau pengiriman email; hanya local/testing, private/no-store, tipe tak dikenal ditolak. Blade meng-escape data pelanggan. Preview voucher diperiksa langsung di browser dan visualnya lulus. Pengujian template, escaping dan pembatasan environment: 11 test, 56 assertion lulus. Implementasi memakai rendering Mailable sesuai dokumentasi resmi Laravel 13 (https://laravel.com/framework/docs/13.x/mail).

Belum selesai: integrasi pemicu expiry, perubahan jadwal, pembatalan dan refund saat workflow-nya tersedia, serta validasi provider produksi. Outbox, delivery log, deduplikasi, batas retry dan uji provider mati telah diimplementasikan pada lanjutan di atas. Tidak ada email pelanggan nyata yang dikirim. WhatsApp belum diaktifkan.

## Fase 21 Voucher dan Validasi Kunjungan

Status: sedang dikerjakan.

Validasi runtime/browser 28 September: backend, worker dan scheduler aktif dengan Redis; `schedule:list` menampilkan dua jadwal dan log scheduler membuktikan keduanya DONE. Login akun fixture melalui `/login` berhasil menuju `/petugas`; `/voucher` menampilkan QR untuk order demonstrasi, redeem pertama menampilkan “Kunjungan tervalidasi untuk 1 peserta”, redeem ulang ditolak. Suite lengkap SQLite: 37 test, 111 assertion, 3 test MySQL concurrency skipped (sudah diuji terisolasi pada sesi sebelumnya). Tambahan assertion konflik payload webhook: suite webhook 7 test, 33 assertion. Fungsionalitas QR/fallback online tervalidasi; pemindaian foto/kamera pada perangkat nyata masih belum diuji. Fixture hari ini sudah redeemed dan tidak di-reset oleh seeder.

Voucher diterbitkan idempoten per item setelah order paid dan inventory hold confirmed. Token acak disimpan terenkripsi, hash token dipakai untuk pencarian, dan token tidak disertakan pada serialisasi model. Redeem online mengunci order dan voucher, memeriksa membership mitra aktif, tanggal layanan (zona pilot Asia/Jakarta), status paid, dan hak masuk belum dipakai. Jumlah admission rombongan, petugas, dan waktu penggunaan tersimpan.

Verifikasi: formatter lulus; test voucher dan regresi webhook lulus (10 test, 42 assertion). Masih diperlukan: QR, layar pemindai/fallback kode, cakupan peran dan lokasi petugas, audit override, serta uji scan serentak MySQL. Fase 21 belum selesai.

Lanjutan: redeem dibatasi membership aktif dengan role owner/manager/staff. Test viewer ditolak lulus; suite voucher 4 test, 12 assertion. Uji MySQL dua scanner serentak menghasilkan tepat satu redeem (suite concurrency 3 test, 19 assertion). Layar JavaScript `/petugas` menyediakan input kode dan pembacaan QR dari foto pada browser yang mendukung BarcodeDetector; lint/build frontend lulus. Kamera/perangkat nyata dan login petugas end-to-end belum diuji. Masih diperlukan penerbitan gambar QR, pembatasan lokasi, serta audit override sebelum fase dinyatakan selesai.

Lanjutan QR/login: login Laravel kini mengautentikasi guard web dan logout mencabut sesi; form login terhubung ke API. Next.js mem-proxy API/Sanctum ke backend internal untuk cookie dan CSRF satu origin. Halaman `/voucher` membuat QR lokal memakai qrcode 1.5.4 dari token yang diterima setelah verifikasi kode akses tamu melalui header; respons private/no-store, order refund tidak mengembalikan voucher. Test autentikasi dan akses voucher: 7 test, 20 assertion lulus; lint/build frontend lulus. Backend runtime Compose belum dinyalakan sehingga alur browser penuh belum diuji. Pembatasan lokasi dan audit override masih terbuka.

Lanjutan lokasi/audit: membership memiliki assignment destinasi; staff pada produk berlokasi hanya dapat redeem pada destinasi yang ditugaskan, sedangkan owner/manager mempunyai cakupan lokasi mitra. Log check-in unik per voucher mencatat actor, admission, waktu, dan alasan override. Super admin dapat melakukan override tanggal dengan alasan wajib minimal 10 karakter; order tetap harus paid dan voucher active. Test lokasi, penolakan override staff, audit admin, dan regresi webhook: 14 test, 52 assertion lulus. Pengujian alur penuh di browser masih terbuka sebelum fase dinyatakan selesai.

Lanjutan runtime: seluruh layanan Compose dinyalakan, APP_KEY lokal dibuat dan migrasi awal dijalankan pada MySQL yang sebelumnya kosong. Fixture voucher local-only ditambahkan. Pengujian browser menemukan `artisan serve` membuang override environment Compose; startup memakai `--no-reload`. Ekstensi Redis ditambahkan ke image PHP. Pengujian Compose juga mengungkap default PHPUnit tidak mengalahkan environment Docker: fixture demo terkena reset dan dipulihkan. Base TestCase sekarang menolak semua database kecuali SQLite in-memory atau database concurrency terisolasi; runbook memakai override test eksplisit. Deduplikasi webhook dinormalisasi menurut urutan key, karena MySQL JSON dapat mengurutkan ulang key. Regresi terisolasi: 21 test, 72 assertion lulus; lint dan build frontend dalam container lulus. Build host gagal karena node_modules host belum memuat qrcode; gunakan dependency Docker/lockfile. Validasi browser belum selesai.

## Fase 20 Webhook Pembayaran

Status: sedang dikerjakan. Endpoint sandbox telah memeriksa secret nonkosong, nominal, mata uang, dan kunci event unik. Pemrosesan event serta perubahan pembayaran/order berada dalam transaksi database. Pembayaran sukses tidak diturunkan oleh event gagal terlambat.

Verifikasi: formatter lulus; `PaymentWebhookTest` lulus dengan 3 test dan 11 assertion menggunakan SQLite di memori.

Checkout kini membuat hold 15 menit. Webhook mengonfirmasi hold dalam transaksi, mencoba alokasi ulang jika hold kedaluwarsa, dan menandai `payment_exception` jika kuota tidak tersedia. Urutan locking inventori diseragamkan: bucket lalu hold.

Pemrosesan dipindahkan ke job `ProcessPaymentWebhook` dengan retry dan pemeriksaan event di dalam transaksi. Command terjadwal `payments:recover-webhooks` mengantrekan kembali event belum diproses, termasuk ketika dispatch awal gagal. Test membuktikan event tersimpan sebelum worker, retry tidak menggandakan kuota, dan recovery mengantrekan event kembali.

Uji MySQL 8.4 terisolasi berhasil: seluruh migrasi berjalan; dua proses PHP serentak berebut satu kursi menghasilkan tepat satu reservasi dan satu penolakan (1 test, 6 assertion). Nama indeks unik itinerary diperpendek agar sesuai batas MySQL. Database test memakai tmpfs dan tidak memakai volume aplikasi.

Uji race expiry-versus-paid MySQL berhasil: order paid, hold lama expired, satu alokasi confirmed, held nol. Bersama uji kuota terakhir: 2 test, 14 assertion lulus. Implementasi sandbox Fase 20 tervalidasi untuk skenario yang dicakup; integrasi provider nyata tetap memerlukan kontrak signature/merchant dari provider yang dipilih. Hanya frontend yang sedang berjalan di Compose pada pemeriksaan terakhir.

## Fase 18 Checkout dan Pesanan Tamu

Status: selesai pada 28 September 2026.

Keluaran yang dibuat:

- Endpoint checkout satu produk/satu mitra dengan tanggal layanan, kontak pelanggan, total yang dihitung server, dan snapshot order.
- Kunci idempoten wajib untuk mencegah pesanan ganda serta token akses tamu yang hanya dikembalikan saat pembuatan pertama.

Verifikasi yang dijalankan:

- `vendor/bin/pint --format agent` — lulus.
- `php artisan test tests/Feature/CheckoutTest.php` — 1 test, 6 assertion lulus memakai SQLite di memori.

## Fase 17 Keberangkatan dan Operasional Paket

Status: selesai pada 28 September 2026.

Keluaran yang dibuat:

- Departure paket dengan tanggal lokal, zona waktu, cutoff, kapasitas, status, penugasan pemandu, alasan pembatalan, dan manifest peserta.
- Layanan booking instan yang mengunci departure dan hanya menerima status `guaranteed`, sebelum cutoff, dan selama kuota tersedia.

Verifikasi yang dijalankan:

- `vendor/bin/pint --format agent` — lulus.
- `php artisan test tests/Feature/PackageDepartureTest.php` — 2 test, 2 assertion lulus memakai SQLite di memori.

## Fase 16 Paket Wisata dan Itinerary

Status: selesai pada 28 September 2026.

Keluaran yang dibuat:

- Model paket yang terhubung ke produk dengan tipe keberangkatan, durasi, titik kumpul, transportasi, pemandu, fasilitas, harga, dan batas peserta.
- Itinerary per hari/urutan dengan aktivitas berdurasi dan destinasi opsional.
- Layanan publikasi yang menolak titik kumpul atau batas peserta tidak valid, itinerary kosong, aktivitas melampaui durasi, dan aktivitas yang bertumpang tindih.

Verifikasi yang dijalankan:

- `vendor/bin/pint --format agent` — lulus.
- `php artisan test tests/Feature/TourPackageTest.php` — 2 test, 2 assertion lulus memakai SQLite di memori.

## Fase 15 Inventori Tiket dan Penahanan Kuota

Status: selesai pada 28 September 2026.

Keluaran yang dibuat:

- Bucket stok unik per produk, tanggal layanan, dan sesi dengan kapasitas, hold, konfirmasi, serta status penutupan tanggal.
- Layanan reserve, confirm, release, dan pelepasan hold kedaluwarsa yang memakai transaksi dan locking konsisten.
- Command scheduler `inventory:release-expired-holds` yang berjalan setiap menit serta kalender inventori publik.
- Factory, migrasi, endpoint kalender, kontrak OpenAPI, dan dokumentasi API.

Verifikasi yang dijalankan:

- `vendor/bin/pint --format agent` — lulus.
- `php artisan test tests/Feature/InventoryReservationTest.php tests/Feature/ProductQuoteTest.php` — 5 test, 14 assertion lulus memakai SQLite di memori.

## Fase 14 Aturan Harga Produk

Status: implementasi siap diverifikasi pada 28 September 2026.

Keluaran yang dibuat:

- Tabel `product_price_rules` untuk harga berdasarkan rentang tanggal, prioritas, dan status aktif.
- Layanan kutipan harga yang memilih aturan aktif dengan prioritas tertinggi dan memakai harga dasar sebagai fallback.
- Endpoint publik `GET /api/v1/products/{slug}/quote` dengan validasi tanggal kunjungan dan kuantitas 1–100.
- Kontrak OpenAPI, dokumentasi API, factory aturan harga, dan pengujian skenario prioritas/fallback.

Verifikasi tertunda:

- Formatter Laravel, migrasi, dan test fitur belum dapat dijalankan karena layanan eksekusi Docker pada akun saat ini menolak permintaan akibat batas kuota. Tidak ada hasil tes yang diklaim lulus sebelum layanan itu tersedia.

## Fase 03 Desain Antarmuka dan Kontrak API

Status: selesai pada 27 September 2026.

Keluaran yang dibuat:

- Design system JavaScript/CSS dan wireframe responsif untuk beranda, pencarian, detail destinasi, detail paket, checkout, dan akun.
- Fixture jelas berlabel data demonstrasi; tidak ada transaksi atau harga yang diklaim aktif.
- Kontrak OpenAPI awal dalam `openapi.yaml`, konvensi pagination/error di `docs/API.md`, dan panduan komponen di `docs/DESIGN_SYSTEM.md`.

Verifikasi yang dijalankan:

- `npm run format`, `npm run lint`, dan `npm run build` dari `frontend/` — lulus.
- Build mencakup rute `/`, `/destinasi`, `/destinasi/demo`, `/paket`, `/paket/demo`, `/penginapan`, `/kuliner`, `/checkout`, dan `/akun`.

Langkah berikutnya:

- Fase 04: model database, migrasi, factory, seeder demo, ERD, dan kamus data. Perubahan kode Laravel mengikuti instruksi `backend/AGENTS.md` setelah runtime Docker PHP 8.3 tersedia.

## Fase 02 Inisialisasi Repositori dan Lingkungan

Status: selesai dengan batasan verifikasi runtime pada 27 September 2026.

Keluaran yang dibuat:

- Monorepo Git dengan `frontend/` (Next.js 16 JavaScript), `backend/` (Laravel 13), `infra/`, dan `docs/`.
- Docker Compose dengan proxy Caddy, frontend, backend PHP 8.3, worker queue, MySQL 8.4, Redis, dan Mailpit.
- Konfigurasi environment aman: `.env.example`, `frontend/.env.example`, `.gitignore`, serta pemisahan nilai lokal dari Git.
- Pemeriksaan kualitas frontend: ESLint, Prettier, build produksi, lockfile, dan GitHub Actions CI.
- Health check Laravel bawaan pada `GET /up`, beserta kontrak awal di `docs/API.md`.
- Panduan startup, pemeriksaan, dan batas backup awal di `docs/RUNBOOK.md`.

Verifikasi yang dijalankan:

- `npm run format`, `npm run format:check`, `npm run lint`, dan `npm run build` dari `frontend/` — lulus.
- `docker compose config --quiet` — lulus validasi konfigurasi.
- `git diff --check` — lulus tanpa whitespace error.

Batasan:

- Docker Desktop tidak sedang aktif sehingga service MySQL/Redis/Mailpit, startup Laravel, koneksi database, dan tes backend belum dapat dijalankan pada sesi ini.
- PHP host adalah 8.1, sedangkan Laravel 13 membutuhkan PHP 8.3. Konfigurasi Docker sengaja menggunakan PHP 8.3; jangan menjalankan Laravel 13 dengan PHP host saat ini.
- `backend/AGENTS.md` mengharuskan Laravel Boost diinstal sebelum perubahan kode Laravel berikutnya. Langkah itu ditunda sampai Docker tersedia agar `php artisan boost:install` berjalan pada PHP yang kompatibel.

Langkah berikutnya:

- Aktifkan Docker Desktop, jalankan `docker compose up --build`, lalu selesaikan verifikasi startup dan backend test.
- Setelah itu lanjutkan Fase 03: design system, wireframe halaman, dan kontrak OpenAPI awal.

## Fase 01 Penetapan Produk dan Aturan Bisnis

Status: selesai pada 27 September 2026.

Koreksi pengguna sesudah fase: frontend yang direncanakan menggunakan JavaScript, bukan TypeScript. Keputusan dicatat pada `docs/PROJECT_CONTEXT.md` dan `docs/DECISIONS.md`; tidak ada kode yang perlu diubah karena Fase 02 belum dimulai.

Keluaran yang dibuat:

- `docs/PRD.md`: persona, perjalanan utama, kategori pilot, batasan, dan tiga acceptance walkthrough.
- `docs/BACKLOG.md`: prioritas wajib, sesudah pilot, dan opsional untuk fase 01–40.
- `docs/GLOSSARY.md`: istilah domain awal.
- `docs/DECISIONS.md`: 10 keputusan bisnis/operasional yang masih membutuhkan pemilik.
- `docs/PROJECT_CONTEXT.md`: konteks, stack rencana, asumsi, serta pembatas transaksi.

Verifikasi yang dijalankan:

- Audit workspace: direktori kosong, belum ada Git, kode, maupun implementasi fase terdahulu.
- Penelusuran definisi produk: destinasi gratis tidak memakai checkout; tiket berbayar dan paket desa ditahan dari aktivasi produksi sampai keputusan dana dan operasi selesai.

Kendala:

- Nama aplikasi final, kabupaten pilot, mitra pertama, provider pembayaran, komisi, pajak, refund, dan settlement belum diputuskan.

Langkah berikutnya:

- Inisialisasi repositori Git dan lingkungan reproducible pada Fase 02, lalu buat frontend Next.js, backend Laravel, Docker Compose, CI, endpoint health, dan panduan setup.
