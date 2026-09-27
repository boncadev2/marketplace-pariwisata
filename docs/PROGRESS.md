# Progress Implementasi

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
