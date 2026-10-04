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

Layanan `scheduler` menjalankan `schedule:work` untuk expiry inventory/order, recovery webhook dan outbox setiap menit. Pastikan worker dan scheduler hidup bersama backend; periksa `docker compose logs worker scheduler` jika event tertunda.

## Rekonsiliasi Transaksi

- Scheduler menjalankan `reconciliation:dispatch` setiap lima menit dan mengantrekan payment attempt `created`/`pending` yang berumur minimal lima menit serta sudah jatuh tempo untuk diperiksa kembali.
- Pemeriksaan provider dibatasi oleh `PAYMENT_RECONCILIATION_REQUESTS_PER_MINUTE` (default 30). Job memakai backoff queue dan waktu pemeriksaan berikutnya 5/15/60/360 menit.
- Laporan harian dan alarm dijadwalkan pukul 01.30. Jalankan manual tanpa mengubah transaksi: `docker compose exec -T backend php artisan reconciliation:daily-report 2026-09-30`.
- Antarmuka administrator tersedia di `/reconciliation`. API retry dan resolve alarm membutuhkan konfirmasi password sensitif dan mencatat audit log.
- Jangan memaksa status internal `succeeded` menjadi gagal hanya karena respons provider `pending`, `unknown`, timeout, atau error sementara. Periksa discrepancy dan settlement provider terlebih dahulu.
- Adapter lokal membaca fixture status dari `payment_attempts.provider_payload`. Sebelum produksi, implementasi gateway harus mengambil status aktual dengan timeout, autentikasi, rate limit, dan retry hanya untuk operasi baca yang idempoten.

Dashboard pelanggan tersedia di `/akun`. Pesanan tamu tidak otomatis muncul hanya karena email sama: pengguna harus masuk, memverifikasi email, lalu mengisi ID pesanan dan kode akses 48 karakter dari checkout. Compose menangkap email verifikasi di Mailpit (`http://localhost:8025`); tautan mengarah ke backend pada `APP_URL`, lalu kembali ke `FRONTEND_URL` (default `http://localhost:8080`). Di lingkungan selain lokal, konfigurasi kedua URL dan mailer harus memakai domain/provider yang benar. Kode akses tidak disimpan oleh halaman klaim.

Tiket bantuan pelanggan ada di `/bantuan` setelah order diklaim. Lampiran disimpan di `backend/storage/app/private/support-attachments`, bukan pada disk publik; PDF/JPG/PNG maksimal 5 MB dapat diunduh hanya oleh pemilik tiket melalui API. Disk lokal tersebut termasuk data yang harus dicakup backup sebelum produksi. Belum ada triase/balasan petugas atau pemindaian malware; jangan menjanjikan dukungan operasional atau menerima lampiran pelanggan nyata sebelum kontrol tersebut tersedia.

Compose memasang `infra/docker/uploads.ini` ke backend HTTP (`upload_max_filesize=6M`, `post_max_size=8M`) agar validasi lampiran 5 MB benar-benar dapat dicapai. Dockerfile juga menyalin konfigurasi itu untuk rebuild image; perubahan runtime saat ini sudah aktif tanpa rebuild karena pengambilan metadata base image dari jaringan tertahan. Periksa dengan `docker compose exec -T backend php -i | rg 'upload_max_filesize|post_max_size'`.

## Demonstrasi Voucher Lokal

Jalankan `docker compose exec -T backend php artisan db:seed --class=VoucherDemoSeeder`. Seeder hanya berjalan di environment local, tidak menghapus data, dan membuat satu order demonstrasi per tanggal Asia/Jakarta.

Akun petugas fixture: `petugas.demo@example.test`, password `DemoPetugas2026!`. Order demo: `demo-checkin-YYYY-MM-DD`; kode akses tamu adalah huruf `g` sebanyak 48 karakter. Gunakan `/voucher` untuk QR dan `/petugas` untuk redeem. Ini bukan akun atau transaksi produksi; voucher hanya dapat digunakan satu kali.

## Simulasi Pilot Fase 33

Seeder pilot hanya menerima environment local/testing, membutuhkan password latihan minimal 16 karakter, dan menggunakan domain email `.test`. Hapus cache konfigurasi lokal jika pernah dibuat, lalu jalankan:

```bash
docker compose exec -T backend php artisan config:clear
docker compose exec -T -e PILOT_DEMO_PASSWORD='ganti-dengan-password-latihan-kuat' backend php artisan db:seed --class=PilotDatabaseSeeder
```

Seeder bersifat idempoten dan selalu menutup checkout. Admin demo, pemilik mitra, dan petugas memakai password environment yang sama hanya untuk simulasi lokal. Jangan gunakan password atau akun ini pada staging/production.

Super admin dapat membuka atau menutup checkout dari `/dashboard`. Setiap perubahan memerlukan konfirmasi kata sandi, alasan minimal lima karakter, dan masuk audit log. Status publik tersedia di `GET /api/v1/pilot/checkout-status`; saat ditutup, checkout mengembalikan HTTP 503 `CHECKOUT_CLOSED` sebelum membuat order, hold, atau payment attempt.

Ikuti `docs/PILOT_SUPPORT_SOP.md` untuk insiden dan `docs/PILOT_GO_LIVE_PHASE_33.md` untuk keputusan go/no-go. Payment production tetap dilarang sampai seluruh gate disetujui.

## Backup dan Restore

Volume `mysql_data` hanya untuk pengembangan dan tidak dianggap sebagai backup. Uji Fase 29 membuktikan dump lokal dapat dienkripsi AES-256-CBC/PBKDF2, didekripsi, dan cocok checksum; uji itu bukan bukti restore aplikasi. Sebelum produksi, pulihkan backup terenkripsi ke database terpisah, jalankan migrasi/read-only smoke test, catat RPO/RTO, lalu hapus data uji sesuai kebijakan.

## Keamanan dan Privasi

- Produksi wajib memakai HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, serta `CORS_ALLOWED_ORIGINS` yang hanya berisi origin resmi.
- Simpan credential staging dan produksi pada secret manager terpisah. Jangan memakai password default Compose. Catat pemilik, tanggal dibuat, tanggal rotasi, dan prosedur pencabutan setiap secret tanpa menulis nilainya pada tiket/log.
- Audit akses admin sebelum pilot dan berkala: gunakan akun individual, email terverifikasi, tanpa akun bersama. Tindakan keuangan memerlukan konfirmasi password terkini; aktifkan MFA/SSO pada lapisan identitas produksi sebelum payout nyata.
- Scheduler menjalankan `DataRetentionJob` setiap hari pukul 02.00. Default: sesi 30 hari, PII delivery notifikasi 90 hari, dan lampiran tiket tertutup 365 hari. Ubah hanya setelah persetujuan legal.
- Permintaan penghapusan dibuat melalui `POST /api/v1/account/data-deletion-request`; pemrosesan admin memakai endpoint sensitif `POST /api/v1/privacy/data-deletion-requests/{id}/process`. Order, keuangan, audit, dan membership operasional tidak dihapus otomatis; dasar serta periode retensinya harus disetujui legal.
- Lampiran dukungan belum memiliki scanner malware. Tetap private dan allowlisted, tetapi nonaktifkan penerimaan file nyata atau tambahkan scanner sebelum pilot pelanggan.
- Jalankan audit ulang: `composer audit --locked`, `npm audit --omit=dev`, test backend terisolasi, lint/build frontend, uji cross-tenant, dan smoke test CSRF/header.
- Checklist dan bukti temuan ada di `docs/SECURITY_AUDIT_PHASE_29.md`.

## Outbox Notifikasi Transaksi

- Checkout menyimpan `awaiting_payment`; webhook paid menyimpan `confirmation` dan `voucher` dalam transaksi order yang sama. Tidak ada SMTP/dispatch pada transaksi order.
- Scheduler menjalankan `notifications:dispatch-outbox` setiap menit; worker memakai claim database untuk menolak job duplikat. Recipient dan snapshot terenkripsi; queue hanya membawa ID delivery.
- Lokal selalu memakai mailer `mailpit` (host Compose `mailpit:1025`). Produksi tidak mengantrekan/mengirim sampai `TRANSACTION_NOTICES_ENABLED=true` dan provider disetujui/configured. Jangan mengaktifkannya hanya untuk preview.
- Lihat 50 delivery terakhir tanpa mengirim: `docker compose exec -T backend php artisan notifications:dispatch-outbox --inspect`. Output tidak memuat alamat email atau snapshot pelanggan.
- Kegagalan dicoba ulang setelah 1 lalu 2 menit, maksimal 3 percobaan, kemudian `failed`. Pengiriman yang masih `sending` lebih dari 5 menit ditandai `uncertain`, bukan dikirim ulang otomatis. Periksa provider sebelum tindakan manual.
- Status `sent` berarti transport menerima pesan, bukan bukti diterima pelanggan. Message-ID stabil membantu pelacakan; SMTP tidak menjamin exactly-once jika koneksi putus setelah penerimaan. Retry terbatas masih dapat menghasilkan duplikat pada kasus ambigu; sebelum produksi diperlukan kebijakan/provider idempotensi yang disetujui.
- Expiry order dari hold yang kedaluwarsa memicu template `expired`. Pengirim memeriksa status order terbaru dan melewati pesan usang tanpa SMTP. Template perubahan jadwal, pembatalan dan refund tersedia, tetapi integrasi pemicunya menunggu implementasi workflow terkait. WhatsApp nonaktif.

## Performa Katalog dan Checkout

- Cache katalog publik default 300 detik (`PUBLIC_CATALOG_CACHE_SECONDS`) dan browser cache 60 detik (`PUBLIC_BROWSER_CACHE_SECONDS`). Perubahan destinasi, produk, region, atau kategori menaikkan versi cache otomatis; tidak perlu menghapus seluruh Redis pada publish normal.
- Jangan cache quote, kalender stok, checkout, data akun, voucher, atau respons privat. Pastikan respons quote/stok/checkout tetap memuat `Cache-Control: no-store` setelah perubahan proxy/CDN.
- Smoke test cache: ambil ETag dari `GET /api/v1/destinations`, lalu kirim ulang dengan `If-None-Match`; respons yang diharapkan adalah 304 tanpa body.
- Target referensi lokal search hangat adalah error 0% dan p95 <= 200 ms pada 100 request/concurrency 20. Kalibrasi ulang target di staging; jangan menjadikannya SLA produksi tanpa baseline hosting.
- Tes race stok wajib memakai database terisolasi bernama tepat `wisata_concurrency_test`. Jalankan migrasi fresh hanya pada database itu, kemudian `php artisan test tests/Feature/InventoryConcurrencyTest.php`. Guard test menolak database aplikasi.
- Untuk load test checkout, gunakan produk dan pelanggan sintetis, payment sandbox, idempotency key unik per transaksi, serta observasi lock wait/error. Jangan mengarahkan load test ke transaksi produksi.
- Detail baseline, hasil, ukuran bundle, dan checklist mobile ada di `docs/PERFORMANCE_MOBILE_PHASE_30.md`.

## Deployment Pipeline (Staging/Production Setup)

Baseline deploy saat ini adalah satu host Linux dengan Docker Compose. Pipeline `.github/workflows/deploy.yml` menguji backend/frontend lalu mengirim commit SHA tepat ke host melalui SSH. Branch `staging` memakai GitHub Environment staging; branch `main` memakai production dan harus dilindungi required reviewer.

Pada host, isi secret environment tanpa mengkomit nilainya, set `APP_ENV=production` dan `APP_DEBUG=false`, lalu jalankan:

```bash
DEPLOY_ENVIRONMENT=production \
APP_DEBUG=false \
BACKUP_DIRECTORY=/srv/backups/wisata/$(date +%Y%m%d-%H%M%S) \
APP_SMOKE_URL=https://wisata.example.id \
./infra/scripts/deploy-compose.sh
```

Script menolak environment tidak dikenal/debug aktif, membuat backup pra-migrasi, build image, menjalankan migrasi kompatibel, cache Laravel, restart worker/scheduler, dan memeriksa `/up` serta `/api/v1/health/dependencies`. Jangan memakai password contoh Compose pada host internet. Batasi MySQL/Redis pada private network dan hanya ekspos reverse proxy HTTPS.

Rollback aplikasi dilakukan dengan checkout commit/tag release sebelumnya lalu menjalankan script yang sama. Migrasi wajib expand/contract; jangan otomatis menjalankan `migrate:rollback` pada production.

## Backups

Untuk latihan lokal, jalankan backup ke direktori absolut dengan permission terbatas:

```bash
./infra/scripts/backup-local.sh /srv/backups/wisata/20260930-120000
shasum -a 256 -c /srv/backups/wisata/20260930-120000/SHA256SUMS
./infra/scripts/restore-drill-local.sh /srv/backups/wisata/20260930-120000/database.sql
```

Restore script hanya memakai database `wisata_restore_drill`, membandingkan jumlah tabel dan migrasi, lalu menghapus target latihan. Script lokal menghasilkan dump plaintext ber-permission 600: pindahkan segera ke storage terenkripsi atau hapus aman setelah latihan.

Production harus memakai snapshot database harian, PITR/binlog, dan object storage berversi untuk media privat. Terapkan enkripsi KMS, akun backup terpisah, retensi terukur, immutable/off-site copy, serta alarm ketika backup 24 jam tidak tersedia. Restore ke environment terisolasi minimal kuartalan dan catat RPO/RTO aktual.

## Observability & Monitoring

- `/up` adalah liveness Laravel. `/api/v1/health/dependencies` adalah readiness database, cache, queue, storage, failed jobs, dan scheduler.
- Set `HEALTH_REQUIRE_SCHEDULER=true` pada staging/production. Sesuaikan threshold disk/failed jobs melalui environment.
- Setiap respons membawa `X-Request-ID`; UUID valid dari proxy diteruskan dan masuk ke log context. Nilai lain diganti UUID baru.
- Gunakan `LOG_CHANNEL=stderr` dan JSON formatter pada container production, lalu kirim stdout/stderr ke log aggregator. Redaction data sensitif tetap aktif.
- Pasang APM/error tracking Laravel dan Next.js pada provider yang dipilih. Jangan mengirim token, cookie, email, nomor rekening, atau payload webhook mentah.
- Alarm minimum: readiness gagal dua kali, HTTP 5xx >2%/5 menit, failed job >0, oldest queue >5 menit, disk tersisa <10%, payment discrepancy Critical, dan backup tidak sukses >24 jam.
- Uji alarm dengan synthetic failure di staging dan catat penerima/escalation path. Rincian bukti ada di `docs/DEPLOYMENT_PHASE_32.md`.


## Simulasi Reservasi Penginapan dan Kuliner

Jalankan migrasi tambahan tanpa reset, lalu seeder sintetis khusus local/testing:

```sh
docker compose exec -T backend php artisan migrate --no-interaction
docker compose exec -T backend php artisan db:seed --class=LodgingDemoSeeder --no-interaction
docker compose exec -T backend php artisan db:seed --class=CulinaryDemoSeeder --no-interaction
```

Buka `/penginapan` atau `/kuliner`. Quote bisa diperiksa tanpa login; reservasi/daftar/pembatalan memakai akun. Seeder tidak membuat akun atau menyalakan pembayaran. Kamar/tempat berlabel demo tidak mewakili penawaran nyata; seeding ulang tidak mereset stok terpakai.

Regresi SQLite harus mengoverride environment container agar tidak mengarah ke database aplikasi:

```sh
docker compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync backend php artisan test --compact
```

Tes MySQL pada `wisata_concurrency_test` mencakup tiket, penginapan, dan kuliner. Gunakan `APP_ENV=testing`, `DB_DATABASE=wisata_concurrency_test`, `CACHE_STORE=array`, dan `SESSION_DRIVER=array` pada container backend. Database aplikasi tidak boleh dipakai untuk test.


## Simulasi Kupon Checkout

```sh
docker compose exec -T backend php artisan db:seed --class=CouponDemoSeeder --no-interaction
```

`DEMO10` memberi diskon sandbox 10%, minimum Rp10.000, maksimum Rp25.000, kuota global 20 dan per akun 2. Gunakan akun beremail terverifikasi dan email pemesan yang sama; cek quote pada `/checkout` sebelum mengirim pesanan. Checkout pilot tetap harus dibuka melalui kontrol admin untuk simulasi sesuai runbook; seeder kupon tidak membukanya. Jangan gunakan promo untuk transaksi nyata. Seeder ulang tidak mengaktifkan kembali kupon yang dinonaktifkan atau mereset kuota terpakai.


## Midtrans Sandbox

Adapter Snap Redirect dapat dipilih melalui backend/.env:

```dotenv
PAYMENT_GATEWAY=midtrans_sandbox
MIDTRANS_SERVER_KEY=<Server Key sandbox dari dashboard Midtrans>
```

Server Key disimpan di backend saja; Client Key tidak diperlukan untuk mode redirect. Format key yang diterima adalah SB-Mid-server- atau Mid-server-. Prefix tidak menentukan environment; gunakan key dari dashboard Sandbox dan verifikasi autentikasi terhadap endpoint sandbox. Endpoint provider dipatok ke app.sandbox.midtrans.com dan api.sandbox.midtrans.com. Adapter hanya bekerja pada APP_ENV local/testing/staging dan menolak production. Default pada .env.example tetap sandbox internal; konfigurasi lokal memakai midtrans_sandbox. Pada 2 Oktober 2026 key dari dashboard Sandbox berhasil diuji dengan GET status untuk referensi probe acak: HTTP 200/status_code 404 (transaksi tidak ditemukan), tanpa membuat pembayaran.

Setelah mengisi key atau mengganti driver, muat ulang konfigurasi seluruh proses:

```sh
docker compose exec -T backend php artisan config:clear
docker compose up -d --force-recreate backend worker scheduler
```

Periksa GET /api/v1/payments/gateway-status: provider=midtrans_sandbox dan ready=true berarti konfigurasi lokal dikenali, bukan bukti autentikasi atau transaksi langsung telah diuji. Endpoint tidak membuka key. Checkout fail-closed sebelum order/hold baru ketika key belum tersedia; kontrol pilot tetap harus dibuka melalui alur admin yang sudah ada.

Pada dashboard Midtrans sandbox, atur Payment Notification URL ke URL HTTPS publik aplikasi di path /api/v1/webhooks/payments/midtrans. Localhost tidak dapat menerima notifikasi provider; perlu staging atau tunnel HTTPS yang memang disiapkan pengguna. Permintaan Snap baru mengirim `callbacks.finish` ke `FRONTEND_URL` + `/akun`, sehingga tidak mengikuti Finish URL merchant lama. Set `FRONTEND_URL` ke alamat frontend yang diakses pengunjung (default lokal `http://localhost:8080`). Atur juga Finish Redirect URL dashboard ke halaman aplikasi untuk metode yang tetap memakai pengaturan dashboard dan token lama; redirect tidak dianggap bukti pembayaran. Ikuti [panduan notifikasi resmi](https://docs.midtrans.com/docs/https-notification-webhooks) dan [integrasi Snap](https://docs.midtrans.com/docs/snap-snap-integration-guide).

UAT langsung belum dijalankan karena key/URL publik belum tersedia: gunakan produk/pelanggan sintetis dan metode simulasi Midtrans; periksa pembuatan Snap, pending, settlement/capture accept, expire/deny, webhook ulang, signature salah, nominal berbeda dan rekonsiliasi. Jangan menjalankan transfer uang nyata. Refund/payout provider serta settlement lintas mitra belum diintegrasikan.

Tidak ada retry otomatis POST Snap. order_id menggunakan UUID order sebagai reference tetap. Timeout atau 404 status sebelum pelanggan memilih metode pembayaran membuat attempt uncertain; retry hanya GET status dan tidak menciptakan Snap kedua. Bila status sudah ditemukan tetapi redirect awal hilang, URL tidak direka ulang; diperlukan penanganan operator. Pembayaran failed Midtrans tidak dibuat ulang dengan order_id yang sama. Provider lama tetap dipakai oleh rekonsiliasi setelah driver aktif berubah.

Webhook memeriksa SHA512(order_id + status_code + gross_amount + ServerKey), lalu mengambil status otoritatif melalui GET API (status/fraud di body notifikasi sendiri tidak ditandatangani dalam formula itu). settlement atau capture fraud accept diproses sebagai sukses; challenge tetap pending, refund/partial_refund/authorize tidak ditandai paid otomatis. Fee provider belum tersedia dari status API dalam adapter ini dan dicatat 0 untuk simulasi, bukan fee settlement resmi.

PHPUnit dan TestCase memaksa PAYMENT_GATEWAY=sandbox dan MIDTRANS_SERVER_KEY kosong, termasuk ketika env aplikasi sudah memilih Midtrans. Test Midtrans menggunakan Http::fake dan key dummy dari konfigurasi test; tidak menghubungi merchant nyata.

## Mobile — katalog destinasi Fase 38–39

Aplikasi Expo di `mobile/` kini mengambil katalog, pencarian, filter, dan detail dari API. Kontrak serta batas scope ada di `docs/API_MOBILE.md`. Login/pesanan/pembayaran native dan distribusi beta belum tersedia.

Dengan Node.js 20/npm dan backend Docker aktif:

```sh
cd mobile
npm ci
cp .env.example .env
npm start
```

Ubah EXPO_PUBLIC_API_URL sebelum memulai:
- Android emulator: `http://10.0.2.2:8080/api/v1`.
- iOS simulator: `http://localhost:8080/api/v1`.
- Perangkat fisik: `http://<IP-LAN-komputer>:8080/api/v1`; perangkat dan komputer harus dapat saling mengakses jaringan.
- Build rilis: URL staging/production HTTPS. Nilai contoh `https://wisata.example.test/api/v1` dalam CI hanya untuk pemeriksaan bundle, bukan layanan yang dapat diakses pengguna.

Jangan menyimpan key Midtrans atau kredensial dalam EXPO_PUBLIC. Setelah mengganti env, mulai ulang Metro dan reload aplikasi. Expo SDK 50 mengikuti kerangka awal; gunakan runtime/dev build yang sesuai SDK tersebut untuk uji perangkat. Upgrade SDK serta UAT Android/iOS belum dilakukan.

Pemeriksaan otomatis:

```sh
cd mobile
npm test
EXPO_PUBLIC_API_URL=https://wisata.example.test/api/v1 npm run export:native -- --max-workers 2
```

Ekspor menghasilkan bundle Metro/Hermes Android/iOS di dist/, bukan aplikasi APK/IPA. CI menjalankan install lockfile, test klien API, dan export kedua platform; tidak menghubungi API staging atau Midtrans.

Acceptance perangkat yang masih harus dijalankan: cari/filter dan berpindah halaman, buka detail lalu kembali dengan filter yang sama, tutup detail menggunakan tombol Android, refresh, katalog kosong, koneksi putus/retry, pencarian cepat tanpa hasil lama, serta pembacaan screen reader dan font besar.

## Akun latihan lokal admin, mitra dan pengunjung

Akun berikut diterapkan pada database lokal 2 Oktober 2026 melalui PilotDatabaseSeeder, dengan email terverifikasi:

| Peran | Email | Halaman setelah login |
| --- | --- | --- |
| Admin | admin.pilot@example.test | /dashboard |
| Mitra owner | owner.pilot@example.test | /dashboard (scope partner_owner) |
| Pengunjung | visitor.pilot@example.test | /akun |

Password latihan untuk ketiganya: `WisataDemo2026!Local`. Ini credential sintetis khusus lokal, bukan secret atau akun produksi. Seeder juga membuat petugas `staff.pilot@example.test` memakai password latihan yang sama. Reseeding selalu menutup checkout; dapat mengganti password demo lewat PILOT_DEMO_PASSWORD. Tidak mengubah akun non-demo yang sudah ada.

Login merespons redirect_to dari peran akun/keanggotaan aktif; frontend hanya menerima /dashboard atau /akun. Header mengenali sesi dan menyediakan logout. Pengunjung tidak memperoleh akses dashboard; mitra tidak memperoleh akses rekonsiliasi admin atau kontrol checkout pilot.

Pesanan checkout wisata saat login (termasuk tanpa kupon) sekarang langsung terikat ke user_id dan terlihat pada /akun. Retry dari akun lain dengan key sama ditolak; checkout tamu tetap tersedia sesuai kontrak lama dan tidak otomatis mengambil kepemilikan pesanan akun.
