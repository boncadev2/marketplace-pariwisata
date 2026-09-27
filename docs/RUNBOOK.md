# Runbook Pengembangan

## Prasyarat

- Docker Desktop aktif.
- Node.js 20.9 atau lebih baru untuk menjalankan frontend di host; proyek ini telah diverifikasi menggunakan Node.js 24.
- Untuk menjalankan Laravel di host diperlukan PHP 8.4.1 atau lebih baru. Konfigurasi Docker menggunakan PHP 8.4 agar tidak bergantung pada PHP host.

## Menjalankan Lingkungan Lokal

1. Salin konfigurasi aman: `cp .env.example .env` dan `cp frontend/.env.example frontend/.env.local`.
2. Pastikan `backend/.env` hanya berisi konfigurasi lokal dan tidak pernah dikomit.
3. Jalankan `docker compose up --build`.
4. Buka aplikasi melalui `http://localhost:8080`, frontend langsung melalui `http://localhost:3000`, backend melalui `http://localhost:8000/up`, dan Mailpit melalui `http://localhost:8025`.
5. Setelah backend hidup, jalankan migrasi dengan `docker compose exec backend php artisan migrate`.

## Pemeriksaan Dasar

- Frontend: `cd frontend && npm run lint && npm run build`
- Backend: `docker compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e SESSION_DRIVER=array backend php artisan test`. Semua override ini wajib: environment Compose dapat mengalahkan default `phpunit.xml`. Jangan jalankan suite RefreshDatabase pada database aplikasi.
- Status layanan: `docker compose ps`
- Health check: `curl -f http://localhost:8000/up`

## Konfigurasi Lingkungan

Gunakan file `.env` lokal untuk nilai yang berubah antar lingkungan. Jangan masukkan key provider, password produksi, atau data pribadi ke Git. Nilai pada `.env.example` hanyalah placeholder pengembangan.

Server pengembangan Compose memakai `artisan serve --no-reload` agar environment Docker (terutama MySQL) diteruskan ke proses HTTP. Setelah mengubah environment, recreate backend/worker. Image backend memasang ekstensi Redis untuk cache dan queue.

Layanan `scheduler` menjalankan `schedule:work` untuk expiry inventory dan recovery webhook setiap menit. Pastikan worker dan scheduler hidup bersama backend; periksa `docker compose logs worker scheduler` jika event tertunda.

## Demonstrasi Voucher Lokal

Jalankan `docker compose exec -T backend php artisan db:seed --class=VoucherDemoSeeder`. Seeder hanya berjalan di environment local, tidak menghapus data, dan membuat satu order demonstrasi per tanggal Asia/Jakarta.

Akun petugas fixture: `petugas.demo@example.test`, password `DemoPetugas2026!`. Order demo: `demo-checkin-YYYY-MM-DD`; kode akses tamu adalah huruf `g` sebanyak 48 karakter. Gunakan `/voucher` untuk QR dan `/petugas` untuk redeem. Ini bukan akun atau transaksi produksi; voucher hanya dapat digunakan satu kali.

## Backup dan Restore

Backup database, restore, dan prosedur insiden akan dirinci pada Fase 32. Sebelum fase tersebut, volume `mysql_data` hanya untuk pengembangan dan tidak dianggap sebagai backup.

## Outbox Notifikasi Transaksi

- Checkout menyimpan `awaiting_payment`; webhook paid menyimpan `confirmation` dan `voucher` dalam transaksi order yang sama. Tidak ada SMTP/dispatch pada transaksi order.
- Scheduler menjalankan `notifications:dispatch-outbox` setiap menit; worker memakai claim database untuk menolak job duplikat. Recipient dan snapshot terenkripsi; queue hanya membawa ID delivery.
- Lokal selalu memakai mailer `mailpit` (host Compose `mailpit:1025`). Produksi tidak mengantrekan/mengirim sampai `TRANSACTION_NOTICES_ENABLED=true` dan provider disetujui/configured. Jangan mengaktifkannya hanya untuk preview.
- Lihat 50 delivery terakhir tanpa mengirim: `docker compose exec -T backend php artisan notifications:dispatch-outbox --inspect`. Output tidak memuat alamat email atau snapshot pelanggan.
- Kegagalan dicoba ulang setelah 1 lalu 2 menit, maksimal 3 percobaan, kemudian `failed`. Pengiriman yang masih `sending` lebih dari 5 menit ditandai `uncertain`, bukan dikirim ulang otomatis. Periksa provider sebelum tindakan manual.
- Status `sent` berarti transport menerima pesan, bukan bukti diterima pelanggan. Message-ID stabil membantu pelacakan; SMTP tidak menjamin exactly-once jika koneksi putus setelah penerimaan. Retry terbatas masih dapat menghasilkan duplikat pada kasus ambigu; sebelum produksi diperlukan kebijakan/provider idempotensi yang disetujui.
- Template expiry, perubahan jadwal, pembatalan dan refund tersedia, tetapi integrasi pemicunya menunggu implementasi workflow terkait. WhatsApp nonaktif.
