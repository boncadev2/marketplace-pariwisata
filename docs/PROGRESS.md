## Fase 38, 39, dan 40: Persiapan, Implementasi, dan Beta Mobile

Status: kerangka awal dibuat pada 29 September 2026; katalog mobile dilanjutkan pada 2 Oktober 2026. Fase 38–40 belum selesai seluruhnya.

Keluaran yang dibuat:
- **Fase 38 (Persiapan Mobile)**: Pembuatan dokumen `docs/API_MOBILE.md` untuk panduan integrasi API dan keputusan `T-005` di `docs/DECISIONS.md` untuk menggunakan React Native dengan Expo.
- **Fase 39 (Implementasi Mobile)**: Basis Expo JavaScript telah dilanjutkan menjadi katalog API, pencarian, filter wilayah/kategori, pagination, refresh dan detail destinasi. Login/pesanan/pembayaran native belum tersedia.
- **Fase 40 (Beta & Pemeliharaan)**: Panduan awal ada di `docs/MOBILE_BETA_ROLLOUT.md`; beta, submission store, dan monitoring perangkat belum dijalankan.

## Fase 33 Pilot Satu Kabupaten dan Peluncuran

Status: fondasi simulasi lokal/sandbox selesai pada 30 September 2026; keputusan **NO GO** untuk transaksi nyata karena keputusan bisnis, provider production, staging, dan sign-off manusia belum tersedia.

Keluaran yang dibuat:
- Memperbaiki `PilotDatabaseSeeder` yang sebelumnya memakai model/field tidak valid. Seeder baru idempoten, hanya local/testing, membutuhkan password environment kuat, memakai data sintetis berlabel demo, dan selalu menutup checkout.
- Menambahkan kontrol checkout server-side yang hanya dapat diubah super admin setelah konfirmasi kata sandi. Perubahan alasan/status dicatat pada audit log.
- Menambahkan kontrol buka/tutup di dashboard serta status fail-closed pada halaman checkout, seluruh frontend menggunakan JavaScript.
- Menambahkan metrik pilot pada dashboard: rasio order ke pembayaran, payment failure/exception, keluhan/sengketa, dan discrepancy keuangan.
- Membuat `PILOT_GO_LIVE_PHASE_33.md`, `PILOT_SUPPORT_SOP.md`, dan `PILOT_EVALUATION_PHASE_33.md`.

Verifikasi:
- Regresi backend penuh: 150 test lulus, 704 assertion, 3 test concurrency MySQL-only dilewati pada suite SQLite.
- Test kontrol pilot, otorisasi, audit, checkout tertutup, monitoring harian, dan seeder idempoten lulus.
- Frontend ESLint dan production build 19 route lulus.
- Tidak ada transaksi nyata atau aktivasi gateway production yang dilakukan.
- Konten nyata, pelatihan operator, settlement provider, serta checklist manusia tetap terbuka dan tidak diklaim lulus.

## Fase 34 Penginapan — Reservasi Simulasi

Status: alur reservasi lokal selesai pada 1 Oktober 2026; fase produksi belum selesai.

- Halaman JavaScript `/penginapan` memakai katalog kamar aktif, quote per malam, reservasi akun, pagination, dan pembatalan.
- Check-out eksklusif, maksimum 30 malam/10 kamar, kapasitas tamu dan tarif seluruh malam divalidasi server. Harga dihitung dalam satuan integer dan perubahan harga membutuhkan konfirmasi ulang.
- Reservasi mengunci akun dan tipe kamar, menyimpan snapshot harga, mengurangi seluruh malam secara atomik, dan menolak konflik idempotency key. Pembatalan mengembalikan stok tepat sekali.
- Unique index kamar/tanggal dan akun/idempotency key ditambahkan melalui migrasi tambahan. Kamar lama tetap tidak aktif sampai ditinjau.
- Seeder `LodgingDemoSeeder` hanya local/testing, memakai kamar berlabel sintetis, dan tidak mereset stok terpakai ketika dijalankan ulang.
- Write reservasi hanya local/testing, status `reserved_sandbox`; tidak ada pembayaran, voucher, atau konfirmasi menginap nyata.

Verifikasi:
- Backend SQLite terisolasi: 171 test lulus, 797 assertion, 5 test MySQL-only dilewati.
- MySQL database `wisata_concurrency_test`: 5 test concurrency lulus, 37 assertion; mencakup dua pelanggan berebut kamar terakhir, retry serentak, dan pembatalan serentak.
- ESLint dan production build frontend lulus. Browser membuktikan quote 10–12 Oktober untuk 1 kamar menghasilkan 2 malam dan Rp300.000.
- Migrasi lokal dan kamar demo diterapkan tanpa reset database.

Masih terbuka: relasi kamar ke penginapan/mitra dan izin tenant, pengelolaan stok/tarif oleh mitra, integrasi order/payment/expiry/voucher, check-in/check-out operasional, serta UAT staging. T16 hanya lulus untuk simulasi inventori lokal.

## Fase 35 Kuliner — Reservasi Simulasi

Status: alur reservasi lokal selesai pada 1 Oktober 2026; integrasi layanan dan pembayaran produksi belum selesai.

- Halaman JavaScript `/kuliner` memakai katalog slot makan aktif di masa depan, kuota tersisa, quote harga server, reservasi akun, pagination, dan pembatalan.
- `MealBooking` menyimpan snapshot paket, waktu, jumlah peserta, harga satuan, dan total. Harga string desimal dihitung tanpa floating point; perubahan harga setelah quote ditolak sebelum kuota berubah.
- Reservasi mengunci akun dan slot; retry dengan key/payload sama tidak menggandakan kuota. Pembatalan mengembalikan kuota sekali, termasuk saat slot dinonaktifkan.
- Slot/tempat lama default tidak aktif. Katalog tidak menampilkan tempat tidak aktif, dan reservasi pada slot lewat ditolak.
- Seeder `CulinaryDemoSeeder` hanya local/testing dan mempertahankan kuota terpakai. Write di staging/production mendapat HTTP 503.
- Kontrak endpoint penginapan/kuliner dicatat di `API.md` dan `openapi.yaml`.

Verifikasi:
- Tes fitur kuliner: 20 test, 87 assertion lulus.
- Regresi backend SQLite terisolasi: 191 test, 884 assertion lulus; 7 test MySQL-only dilewati pada run penuh. Tes regresi stok/webhook/reservasi sesudah perbaikan race: 52 test, 229 assertion lulus.
- MySQL: 7 test concurrency, 55 assertion lulus; ditambah tes deterministik snapshot hold usang 1 test, 6 assertion lulus.
- Pengujian ulang mengungkap refresh non-locking pada inventori tiket dapat membaca snapshot usang. Refresh hold dan bucket kini memakai locking read, dengan regresi deterministik untuk pembacaan setelah proses lain melepas hold.
- Frontend ESLint dan production build 19 route lulus. Browser membuktikan paket demo 2 peserta × Rp75.000 = Rp150.000 dan menampilkan waktu WIB.
- Migrasi lokal dan seeder kuliner dijalankan tanpa reset database.

Masih terbuka: kepemilikan tempat/slot oleh mitra, pengelolaan katalog/jadwal/kuota oleh mitra, integrasi order/payment/expiry/voucher, kebijakan pembatalan layanan nyata, dan UAT staging/operator. Tidak ada pembayaran atau konfirmasi layanan nyata yang dilakukan.

## Fase 32 Deployment
Status: implementasi lokal dan latihan restore selesai pada 30 September 2026; provisioning staging/production eksternal menunggu domain, host, dan credential provider.

Keluaran yang dibuat:
- Mengganti placeholder pipeline dengan build, audit, test, formatter check, deploy commit immutable melalui SSH, backup pra-migrasi, migrasi, cache, restart worker/scheduler, dan smoke check.
- Menambahkan `infra/scripts/deploy-compose.sh`, `backup-local.sh`, dan `restore-drill-local.sh` dengan guard environment/debug dan target restore khusus.
- Menambahkan request ID tervalidasi, structured log context, dependency readiness check, disk/failed-job threshold, dan heartbeat scheduler.
- Memperbarui RUNBOOK dan membuat `DEPLOYMENT_PHASE_32.md` sebagai bukti dan konfigurasi alarm/rollback.

Bukti verifikasi:
- Regresi backend penuh: 141 test lulus, 658 assertion, 3 test MySQL-only dilewati; test observability menyumbang 3 test/11 assertion.
- Image frontend production berhasil dibangun dengan 19 route; Docker context turun dari sekitar 490 MB menjadi 3,32 kB dan file environment lokal tidak ikut image.
- Backup database dan private media lolos SHA-256.
- Restore ke database terisolasi `wisata_restore_drill` berhasil: 61 tabel, 64 migrasi, 3 detik; target dan dump latihan dibersihkan.
- T18 pada acceptance matrix berubah menjadi lulus lokal.
- Deployment eksternal belum dijalankan dan tidak diklaim selesai tanpa credential/hosting.

## Fase 31 UAT
Status: implementasi dan pengujian otomatis selesai pada 30 September 2026; keputusan release **HOLD** sampai UAT staging dan blocker Critical ditutup.

Keluaran yang dibuat:
- Memetakan seluruh 18 skenario Lampiran G pada `UAT_CHECKLIST.md` tanpa menandai pengujian yang belum dijalankan sebagai lulus.
- Membuat `TEST_REPORT_PHASE_31.md` dan `RELEASE_CANDIDATE_PHASE_31.md` dengan bukti aktual serta keputusan release.
- Memperbaiki konflik idempotency checkout, pembuatan payment attempt, lookup setelah timeout provider, monotonic payment state, dan refund duplikat.
- Menambahkan halaman instruksi pembayaran sandbox menggunakan JavaScript.

Bukti verifikasi:
- Backend SQLite/in-memory: 138 test lulus, 647 assertion, 3 test MySQL-only dilewati.
- Concurrency MySQL: 3 test lulus, 19 assertion.
- Frontend ESLint dan production build lulus.
- Acceptance matrix setelah Fase 32: 15 lulus dan 3 belum dijalankan (T13, T16, T17).
- UAT staging lima peran dan simulasi operator belum dijalankan; tidak ada klaim simulated pass.

## Fase 30 Performa
Status: selesai diimplementasikan dan diverifikasi ulang pada 30 September 2026.

Keluaran yang dibuat:

- Index database, query katalog maksimum dua query, pagination, payload publik minimal, serta katalog produk publik baru.
- Cache versi untuk destinasi, produk, region, dan kategori dengan invalidasi otomatis; ETag dan browser cache singkat untuk konten publik.
- Quote, kalender stok, dan checkout memakai `no-store`; harga dan kuota tetap divalidasi server.
- Pencarian dengan debounce/timeout/race guard dan checkout web fungsional dengan dukungan koneksi lambat, offline, serta retry idempoten.
- Lazy loading gambar partner, skip link, fokus terlihat, reduced motion, label/status aksesibel, kontras dan target sentuh mobile.
- Konfirmasi atau realokasi pembayaran terlambat dibuat atomik setelah tes MySQL menemukan race saat hold kedaluwarsa.

Verifikasi yang dijalankan:

- Backend penuh: 133 test / 607 assertion lulus pada SQLite in-memory; tiga test MySQL dilewati sesuai guard.
- Konkurensi MySQL terisolasi: 3 test / 19 assertion lulus, termasuk perebutan unit stok terakhir tanpa oversell.
- Search lokal 100 request dengan concurrency 20: 100% sukses, 158,3 req/detik, p95 141,7 ms.
- ESLint dan build produksi Next.js lulus untuk 19 route.
- Audit browser 390x844: tidak ada overflow horizontal, input tanpa label, target interaktif di bawah 44 px, hydration error, atau warning console.
- Laporan rinci: `docs/PERFORMANCE_MOBILE_PHASE_30.md`.

## Fase 28 Rekonsiliasi Transaksi Otomatis

Status: selesai diimplementasikan dan diverifikasi pada 30 September 2026.

Keluaran yang dibuat:

- Job unik `ReconcilePaymentAttempt` memeriksa status provider untuk transaksi pending, memakai rate limit provider, retry queue, dan backoff aplikasi 5/15/60/360 menit.
- Pemulihan webhook yang hilang membuat event pembayaran durable lalu memakai pemroses pembayaran yang sama. Event, ledger, dan voucher tetap idempoten ketika job dijalankan berulang.
- `ReconciliationEntry` menyimpan hasil perbandingan reference, nominal, mata uang, status, fee, settlement reference, dan ledger. Respons provider sementara tidak pernah menurunkan pembayaran `succeeded` atau order `paid`.
- `GenerateDailyReconciliationReport` membuat laporan dan alarm idempoten untuk pembayaran tanpa voucher, refund lama, payout tidak pasti, hold kedaluwarsa yang belum dilepas, dan discrepancy pembayaran.
- Scheduler mengirim pemeriksaan due setiap lima menit dan laporan harian pukul 01.30 Asia/Jakarta. Retry manual serta penyelesaian alarm memerlukan super admin, konfirmasi sensitif, dan menghasilkan audit log.
- API laporan/alarm tersedia di `/api/v1/reconciliation/reports` dan `/api/v1/reconciliation/alerts`; antarmuka administrator tersedia di `/reconciliation` menggunakan JavaScript.

Verifikasi yang dijalankan:

- Suite terfokus rekonsiliasi, recovery webhook, dan pembayaran: 13 test / 79 assertion lulus pada SQLite in-memory dengan queue sinkron.
- Acceptance test membuktikan status provider `succeeded` dapat memulihkan webhook yang hilang dan dua eksekusi job hanya menghasilkan satu event, satu jurnal pembayaran, satu voucher, dan satu discrepancy record.
- Status provider `unknown` terhadap pembayaran internal `succeeded` tidak mengubah pembayaran/order menjadi unpaid dan menjadwalkan pemeriksaan berikutnya.
- Suite backend penuh: 120 test / 520 assertion lulus; tiga test concurrency khusus MySQL dilewati pada suite SQLite terisolasi.
- `npm run lint` dan `npm run build` frontend lulus; build menghasilkan 19 route termasuk `/reconciliation`.
- Tiga migrasi Fase 28 berhasil diterapkan ke database lokal; `schedule:list` menampilkan dispatcher lima-menitan dan laporan harian.

Batas produksi:

- Adapter provider aktif masih sandbox. Integrasi status dan settlement provider produksi harus menerapkan kontrak `PaymentGateway::fetchPaymentStatus`, timeout, autentikasi, serta pemetaan status provider sebelum transaksi nyata diaktifkan.

## Fase 25 Komisi dan Pencatatan Keuangan


Status: selesai diimplementasikan pada 28 September 2026.

Keluaran yang dibuat:

- Model `CommissionRule` dan layanan `CommissionService` untuk aturan versi per mitra/produk dengan pembulatan Rupiah.
- Penyimpanan jumlah komisi pada item pesanan saat checkout.
- Tabel ledger (`LedgerAccount`, `JournalEntry`, `JournalTransaction`) untuk akuntansi *append-only*.
- `LedgerService` untuk mencatat pembayaran (debit payment gateway, kredit liabilitas mitra dan pendapatan komisi) dan *refund*.
- `RevenueReportingService` untuk metrik *Gross Booking Value*, pembayaran diterima, *refund*, pendapatan platform, dan dana siap cair.
- Pengujian fitur pada `CommissionAndLedgerTest` dan *endpoint* baru.

Verifikasi tertunda:
- Linter dan *test* PHP belum dijalankan sepenuhnya karena kendala akses versi PHP 8.4 dan *Docker daemon*. Kode telah disiapkan sesuai instruksi.

## Fase 24 Pembatalan dan refund

Status: selesai pada 28 September 2026.

Keluaran yang dibuat:

- Logika permintaan pembatalan berdasarkan evaluasi `policy_snapshot` (batas waktu lokal, jumlah refundable).
- Model `RefundRequest` beserta migrasi, factory, dan relasi ke `Order`.
- State refund: `requested`, `approved`, `processing`, `succeeded`, dan `failed`.
- Pembatalan admission/inventory (`InventoryHold` di-set menjadi `released` dan bucket `confirmed` dikurangi).
- Interface adapter refund (`RefundAdapterInterface`) dan implementasi sandbox (`SandboxRefundAdapter`).
- Endpoint API untuk mengajukan, menyetujui, dan menolak refund.

Verifikasi yang dijalankan:

- `vendor/bin/pint --format agent` — lulus.
- `php artisan test tests/Feature/RefundRequestTest.php` — 4 test, 10 assertion lulus memakai SQLite di memori.

# Progress Implementasi

## Fase 23 Dashboard Pelanggan dan Klaim Pesanan Tamu

Status: alur inti Fase 23 terimplementasi pada 28 September 2026; selesai. Dashboard JavaScript `/akun` menampilkan pesanan yang telah ditautkan, filter status, total, tanggal kunjungan, rincian jadwal, status upaya pembayaran terakhir, kontak pengelola, serta pesan tindak lanjut untuk `payment_exception`. API daftar/detail hanya mengambil `orders.user_id` milik sesi, tidak mengasosiasikan pesanan hanya berdasarkan alamat email. Klaim membutuhkan login, email akun terverifikasi, alamat email pesanan yang cocok, dan kode akses tamu 48 karakter. Klaim mengunci order, idempoten untuk pemiliknya, dan menolak transfer kepemilikan. Respons dibatasi ke ringkasan aman dan `Cache-Control: private, no-store`. Pesanan yang sudah diklaim dapat membuka QR voucher dari akun tanpa mengetik ulang kode tamu; akses token voucher ditolak bagi akun lain dan untuk order yang tidak lagi paid.

Form `/bantuan` membuat tiket untuk order milik akun dengan kategori, pesan, dan lampiran opsional; pelanggan dapat membaca riwayat dan mengirim pesan lanjutan. Unduhan mensyaratkan pemilik tiket dan lampiran pada tiket yang sama. PDF/JPG/PNG hingga 5 MB divalidasi berdasarkan MIME serta ekstensi, disimpan dengan nama acak di disk privat, dan diunduh sebagai attachment dengan `nosniff`; path penyimpanan tidak dikirim ke frontend. Wishlist destinasi terbit bersifat pribadi dan idempoten; destinasi yang kemudian tidak terbit ditandai tidak tersedia. Profil hanya membolehkan perubahan nama, bukan email/role yang dapat memengaruhi klaim pesanan.

Registrasi memicu email verifikasi, dengan endpoint kirim ulang dan tautan bertanda tangan. Pada Compose lokal, email ditangkap Mailpit, bukan dikirim ke pelanggan nyata. Migrasi `orders.user_id`, tabel tiket/pesan/lampiran, dan wishlist diterapkan tanpa reset data. Verifikasi: suite penuh 87 test / 367 assertion lulus di SQLite in-memory. Tiga test concurrency MySQL dilewati di suite SQLite ini (telah lulus terisolasi 3 test / 19 assertion sebelumnya); lint frontend 0 error dengan 6 warning gambar pada perubahan beranda terpisah; build Next.js dan HTTP 200 `/bantuan` lulus. Pemeriksaan visual browser menemukan styling halaman `Shell` hilang akibat perubahan CSS beranda terpisah; modul CSS terskop ditambahkan tanpa menimpa beranda dan halaman bantuan kembali terbaca dengan baik. PHP HTTP lokal kini memakai konfigurasi 6 MB upload/8 MB POST untuk batas lampiran 5 MB; build image tertahan saat mengambil metadata base image, sehingga Compose memasang file ini langsung dan runtime terverifikasi.

Belum selesai untuk produksi: invoice/bukti resmi memerlukan kebijakan dan integrasi pembayaran final; belum ada alur petugas untuk triase/balas tiket, pemindaian malware lampiran, atau uji browser dengan akun pelanggan/perangkat nyata. Tabel dan form dukungan ini adalah intake lokal, bukan janji SLA atau operasi support aktif. Daftar pesanan/wishlist/tiket masih menampilkan halaman pagination pertama. Keputusan ini mencegah bukti pembayaran atau janji dukungan palsu.

## Fase 22 Notifikasi dan Komunikasi Transaksi

Status: selesai — template, outbox dan pemicu transaksi utama tervalidasi lokal.

Lanjutan expiry 28 September: scheduler pelepasan hold sekarang mengubah order `pending_payment` menjadi `expired` hanya ketika semua hold item sudah expired, dan membuat event notifikasi idempoten dalam transaksi. Webhook pembayaran terlambat boleh merealokasi kuota dari `expired` bila masih tersedia; jika tidak, tetap masuk `payment_exception`. Pengirim outbox mengunci order lebih dulu dan menandai notifikasi yang sudah usang sebagai `superseded` tanpa SMTP, misalnya menunggu bayar sesudah paid atau voucher sesudah refund. Uji MySQL terisolasi untuk race expiry-vs-paid, scanner bersamaan, dan perebutan kuota terakhir: 3 test / 19 assertion lulus. Database uji MySQL tmpfs terpisah dari database aplikasi dan kontainernya dihentikan sesudah pengujian. Workflow perubahan jadwal/cancel/refund dan provider produksi masih belum tersedia.

Lanjutan outbox 28 September: tabel `notification_deliveries` menyimpan deduplication key unik, recipient/snapshot terenkripsi, status, jumlah percobaan, timestamp dan delivery log tanpa pesan error sensitif. Checkout mencatat awaiting_payment; webhook paid mencatat confirmation/voucher dalam transaksi order, tanpa SMTP atau dispatch di dalam transaksi. Poller terjadwal mengantrekan ID saja; worker memakai claim row untuk mencegah duplicate send sesudah acceptance, maksimal 3 percobaan dengan jeda 1/2 menit, terminal failed. Sending yang terputus lebih dari 5 menit menjadi uncertain dan tidak otomatis diulang. Inspeksi metadata tersedia melalui `notifications:dispatch-outbox --inspect`.

Verifikasi: migrasi tambahan MySQL berhasil tanpa reset; fixture local-only dikirim lewat worker dan terlihat di browser Mailpit, status sent dengan 1 attempt. Duplicate job berikutnya selesai tanpa pengiriman tambahan. Suite penuh SQLite in-memory: 58 test, 205 assertion lulus; 3 test concurrency MySQL skipped. Skill testing-best-practices digunakan untuk rollback, retry/provider failure, deduplication, encryption dan batas produksi. Formatter dan diff check lulus. Produksi nonaktif secara default, pengiriman lokal dipaksa ke Mailpit. SMTP acceptance bukan bukti delivered/exactly-once; retry pada kegagalan ambigu masih bisa menggandakan pesan maksimal 3 kali. Integrasi workflow expiry/jadwal/cancel/refund dan validasi provider produksi masih terbuka, sehingga Fase 22 belum dinyatakan selesai seluruhnya.

Tujuh template email tersedia: konfirmasi, menunggu bayar, kedaluwarsa, voucher, perubahan jadwal, pembatalan dan refund. Preview `/dev/notifications/{type}` menggunakan fixture tetap tanpa akses order pelanggan atau pengiriman email; hanya local/testing, private/no-store, tipe tak dikenal ditolak. Blade meng-escape data pelanggan. Preview voucher diperiksa langsung di browser dan visualnya lulus. Pengujian template, escaping dan pembatasan environment: 11 test, 56 assertion lulus. Implementasi memakai rendering Mailable sesuai dokumentasi resmi Laravel 13 (https://laravel.com/framework/docs/13.x/mail).

Belum selesai: integrasi pemicu expiry, perubahan jadwal, pembatalan dan refund saat workflow-nya tersedia, serta validasi provider produksi. Outbox, delivery log, deduplikasi, batas retry dan uji provider mati telah diimplementasikan pada lanjutan di atas. Tidak ada email pelanggan nyata yang dikirim. WhatsApp belum diaktifkan.

## Fase 21 Voucher dan Validasi Kunjungan

Status: selesai.

Validasi runtime/browser 28 September: backend, worker dan scheduler aktif dengan Redis; `schedule:list` menampilkan dua jadwal dan log scheduler membuktikan keduanya DONE. Login akun fixture melalui `/login` berhasil menuju `/petugas`; `/voucher` menampilkan QR untuk order demonstrasi, redeem pertama menampilkan “Kunjungan tervalidasi untuk 1 peserta”, redeem ulang ditolak. Suite lengkap SQLite: 37 test, 111 assertion, 3 test MySQL concurrency skipped (sudah diuji terisolasi pada sesi sebelumnya). Tambahan assertion konflik payload webhook: suite webhook 7 test, 33 assertion. Fungsionalitas QR/fallback online tervalidasi; pemindaian foto/kamera pada perangkat nyata masih belum diuji. Fixture hari ini sudah redeemed dan tidak di-reset oleh seeder.

Voucher diterbitkan idempoten per item setelah order paid dan inventory hold confirmed. Token acak disimpan terenkripsi, hash token dipakai untuk pencarian, dan token tidak disertakan pada serialisasi model. Redeem online mengunci order dan voucher, memeriksa membership mitra aktif, tanggal layanan (zona pilot Asia/Jakarta), status paid, dan hak masuk belum dipakai. Jumlah admission rombongan, petugas, dan waktu penggunaan tersimpan.

Verifikasi: formatter lulus; test voucher dan regresi webhook lulus (10 test, 42 assertion). Masih diperlukan: QR, layar pemindai/fallback kode, cakupan peran dan lokasi petugas, audit override, serta uji scan serentak MySQL. Fase 21 belum selesai.

Lanjutan: redeem dibatasi membership aktif dengan role owner/manager/staff. Test viewer ditolak lulus; suite voucher 4 test, 12 assertion. Uji MySQL dua scanner serentak menghasilkan tepat satu redeem (suite concurrency 3 test, 19 assertion). Layar JavaScript `/petugas` menyediakan input kode dan pembacaan QR dari foto pada browser yang mendukung BarcodeDetector; lint/build frontend lulus. Kamera/perangkat nyata dan login petugas end-to-end belum diuji. Masih diperlukan penerbitan gambar QR, pembatasan lokasi, serta audit override sebelum fase dinyatakan selesai.

Lanjutan QR/login: login Laravel kini mengautentikasi guard web dan logout mencabut sesi; form login terhubung ke API. Next.js mem-proxy API/Sanctum ke backend internal untuk cookie dan CSRF satu origin. Halaman `/voucher` membuat QR lokal memakai qrcode 1.5.4 dari token yang diterima setelah verifikasi kode akses tamu melalui header; respons private/no-store, order refund tidak mengembalikan voucher. Test autentikasi dan akses voucher: 7 test, 20 assertion lulus; lint/build frontend lulus. Backend runtime Compose belum dinyalakan sehingga alur browser penuh belum diuji. Pembatasan lokasi dan audit override masih terbuka.

Lanjutan lokasi/audit: membership memiliki assignment destinasi; staff pada produk berlokasi hanya dapat redeem pada destinasi yang ditugaskan, sedangkan owner/manager mempunyai cakupan lokasi mitra. Log check-in unik per voucher mencatat actor, admission, waktu, dan alasan override. Super admin dapat melakukan override tanggal dengan alasan wajib minimal 10 karakter; order tetap harus paid dan voucher active. Test lokasi, penolakan override staff, audit admin, dan regresi webhook: 14 test, 52 assertion lulus. Pengujian alur penuh di browser masih terbuka sebelum fase dinyatakan selesai.

Lanjutan runtime: seluruh layanan Compose dinyalakan, APP_KEY lokal dibuat dan migrasi awal dijalankan pada MySQL yang sebelumnya kosong. Fixture voucher local-only ditambahkan. Pengujian browser menemukan `artisan serve` membuang override environment Compose; startup memakai `--no-reload`. Ekstensi Redis ditambahkan ke image PHP. Pengujian Compose juga mengungkap default PHPUnit tidak mengalahkan environment Docker: fixture demo terkena reset dan dipulihkan. Base TestCase sekarang menolak semua database kecuali SQLite in-memory atau database concurrency terisolasi; runbook memakai override test eksplisit. Deduplikasi webhook dinormalisasi menurut urutan key, karena MySQL JSON dapat mengurutkan ulang key. Regresi terisolasi: 21 test, 72 assertion lulus; lint dan build frontend dalam container lulus. Build host gagal karena node_modules host belum memuat qrcode; gunakan dependency Docker/lockfile. Validasi browser belum selesai.

## Fase 20 Webhook Pembayaran

Status: selesai. Endpoint sandbox telah memeriksa secret nonkosong, nominal, mata uang, dan kunci event unik. Pemrosesan event serta perubahan pembayaran/order berada dalam transaksi database. Pembayaran sukses tidak diturunkan oleh event gagal terlambat.

Verifikasi: formatter lulus; `PaymentWebhookTest` lulus dengan 3 test dan 11 assertion menggunakan SQLite di memori.

Checkout kini membuat hold 15 menit. Webhook mengonfirmasi hold dalam transaksi, mencoba alokasi ulang jika hold kedaluwarsa, dan menandai `payment_exception` jika kuota tidak tersedia. Urutan locking inventori diseragamkan: bucket lalu hold.

Pemrosesan dipindahkan ke job `ProcessPaymentWebhook` dengan retry dan pemeriksaan event di dalam transaksi. Command terjadwal `payments:recover-webhooks` mengantrekan kembali event belum diproses, termasuk ketika dispatch awal gagal. Test membuktikan event tersimpan sebelum worker, retry tidak menggandakan kuota, dan recovery mengantrekan event kembali.

Uji MySQL 8.4 terisolasi berhasil: seluruh migrasi berjalan; dua proses PHP serentak berebut satu kursi menghasilkan tepat satu reservasi dan satu penolakan (1 test, 6 assertion). Nama indeks unik itinerary diperpendek agar sesuai batas MySQL. Database test memakai tmpfs dan tidak memakai volume aplikasi.

## Fase 26 Settlement dan Pencairan Mitra

Status: selesai pada 28 September 2026.

Keluaran yang dibuat:

- Penentuan kelayakan payout berdasarkan tanggal penyelesaian layanan, masa sanggah, dan penahanan transaksi bermasalah di tabel `orders`.
- Batch payout (maker-checker) untuk pencairan manual maupun API ke `partner_bank_accounts`.
- Laporan kelayakan dana per mitra.
- Pencatatan `payout_items` untuk tiap order.

Verifikasi tertunda:

- Pint dan Testing tidak bisa dieksekusi dikarenakan batasan PHP host dan Docker Desktop yang tidak aktif, namun file telah terstruktur dan menggunakan validasi bawaan Laravel.



## Fase 27 Operational Disputes dan Reviews

Status: selesai pada 28 September 2026.

Keluaran yang dibuat:

- Tabel `operational_disputes` dan model terkait untuk manajemen sengketa operasional antara pelanggan dan layanan.
- Endpoint publik `GET` dan `POST` untuk pelaporan perselisihan pesanan serta update resolusi dan status.
- Tabel `reviews` dan model terkait untuk melacak penilaian dan ulasan pelanggan terhadap produk wisata.
- Factory, migrasi, dan pengujian fitur.

Verifikasi tertunda:

- Pint dan Testing tidak bisa dieksekusi dikarenakan batasan PHP host dan Docker Desktop yang tidak aktif, namun file telah terstruktur.

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

## Fase 29 Keamanan dan Privasi Menjelang Pilot

Status: kontrol teknis selesai pada 30 September 2026; sign-off legal dan operasi produksi masih wajib sebelum pilot data nyata.

Keluaran yang dibuat:

- Audit IDOR, CSRF, XSS, upload, rate limit, cookie/session, CORS, secret repository, dependency, data pribadi, rekening, payout, backup, dan akses admin berbasis implementasi aktual.
- Header keamanan global, CORS allowlist, enkripsi sesi default, rate limiter bernama, redaksi log, dan audit trail tindakan sensitif.
- Upload media tenant-scoped untuk admin atau owner/manager aktif dengan validasi image/MIME/ekstensi/ukuran.
- Retensi harian yang meredaksi PII notifikasi tanpa menghapus catatan delivery dan membersihkan sesi/token/lampiran kedaluwarsa.
- API permintaan penghapusan data serta pemrosesan admin dengan konfirmasi password, anonimisasi profil, penghapusan data non-transaksional, dan pengecualian catatan transaksi/keuangan/audit.
- Bukti lengkap di `docs/SECURITY_AUDIT_PHASE_29.md` dan pemeriksaan dependency pada CI.

Verifikasi yang dijalankan:

- 128 test backend dan 570 assertion lulus; 3 test konkurensi memerlukan MySQL terisolasi dan dilewati.
- ESLint dan production build frontend 19 halaman lulus.
- Composer dan npm production dependency audit tidak menemukan advisory.
- Migrasi lokal berhasil; HTTP smoke test membuktikan header keamanan dan CSRF 419.
- Dump database lokal berhasil melalui roundtrip enkripsi/dekripsi dan checksum, lalu file uji dibersihkan.

Batas aktivasi:

- Persetujuan legal, daftar akses operator, rotasi secret produksi, restore ke instance terpisah, dan pemindaian malware lampiran masih terbuka. Sampai ditutup, jangan menerima data pelanggan atau menjalankan payout produksi.

## Fase 36 Ulasan, Promo, dan Retensi

Status: fondasi ulasan/moderasi dibuat 29 September 2026; promo checkout sandbox dilanjutkan pada 1 Oktober 2026. Fase keseluruhan belum selesai.

- Quote `/promos/quote` mengambil subtotal dari produk/aturan harga server, bukan nominal kiriman pengguna. Kupon memerlukan akun dengan email terverifikasi dan hanya mendukung IDR.
- Kupon percentage/fixed mendukung waktu aktif, minimum belanja, maksimum diskon, kuota global, dan kuota per akun. Pembulatan diskon ke rupiah dilakukan ke bawah memakai integer; total pembayaran nol ditolak.
- Checkout mengunci akun dan kupon sebelum validasi/inventori. Pesanan, hold, penukaran, dan perubahan kuota berada dalam satu transaksi. Harga berubah atau stok habis tidak menghabiskan kuota.
- Snapshot menyimpan subtotal, diskon, dan total net. Total pesanan/item/payment attempt serta basis komisi konsisten dengan harga net untuk simulasi. Pembiayaan promo produksi belum diputuskan.
- Constraint satu penukaran per order dan index coupon/user ditambahkan. Retry payload sama mengembalikan pesanan awal tanpa penukaran kedua; perubahan kode kupon ditolak sebagai konflik idempotensi.
- Frontend checkout menampilkan kolom kupon, subtotal/diskon/total, meminta pemeriksaan quote sebelum promo dikirim, serta mempertahankan payload/kunci saat respons belum pasti.
- Seeder local-only `CouponDemoSeeder` membuat `DEMO10` dan mempertahankan kuota/status saat diulang. Promo ditolak di luar local/testing; kontrol checkout pilot tetap berlaku.

Verifikasi:
- Regresi penuh backend: 213 test, 1001 assertion lulus; 10 test MySQL-only dilewati pada SQLite.
- Test terfokus akhir kupon/checkout: 25 test, 138 assertion lulus, termasuk ledger pembayaran net dan komisi net yang seimbang, email akun, dan seeder idempoten.
- MySQL: 2 race kupon lulus, 14 assertion; dua pengguna berebut kupon terakhir menghasilkan satu order/hold/redemption, dan retry serentak tidak menggandakan kuota.
- Frontend lint dan build 19 route lulus; OpenAPI YAML berhasil diparse (16 path).
- Migrasi lokal dan kupon demo diterapkan tanpa reset data.

Batas simulasi: kuota kupon dikonsumsi saat checkout berhasil dibuat dan belum dikembalikan untuk order expired/cancelled/refunded. Sebelum produksi perlu keputusan sponsor diskon, kebijakan lifecycle kuota, cakupan mitra/produk, anti-abuse, pengelolaan promo oleh admin, serta UAT staging. Workflow moderasi ulasan dan retensi belum ditutup hanya dengan keberadaan model.

## Fase 37 Perluasan wilayah dan produk lanjutan
Status: fondasi model dibuat 29 September 2026; simulasi alokasi dilanjutkan 1 Oktober 2026. Fase keseluruhan belum selesai.

- Model `CrossVillagePackage` memetakan porsi mitra; `SubOrder` kini mendukung snapshot simulasi dan belum menghasilkan settlement.
- Endpoint admin terverifikasi `GET /products/{product:slug}/cross-village-quote` tersedia hanya di local/testing, dengan rate limit dan respons no-store.
- Simulasi menggunakan harga server dan aturan komisi yang sudah ada. Paket/produk wajib published, IDR, harga per peserta; tanggal tidak lampau dan jumlah peserta mengikuti batas paket.
- Pemetaan wajib minimal dua mitra disetujui dari minimal dua region bertipe village. Semua region mitra harus village; mitra tidak boleh duplikat/deleted. Tepat satu mitra utama harus merupakan pemilik produk.
- Porsi positif dua desimal wajib berjumlah 100%. Pendapatan sesudah komisi dibagi dengan integer; sisa rupiah diberikan ke pecahan terbesar, lalu ID mitra terkecil bila seri. Jumlah alokasi selalu sama dengan pendapatan mitra.
- Simulasi tidak membuat order, sub-order, hold inventori, atau payout. Kupon tidak diterapkan dalam simulasi ini.
- Verifikasi: 21 test / 75 assertion lulus untuk simulasi, paket, dan checkout; Pint lulus.

Lanjutan pengelolaan konfigurasi (1 Oktober 2026):
- Halaman `/dashboard/lintas-desa` terhubung dari dashboard super_admin: muat paket dengan slug, cari kandidat mitra approved dari desa (maksimal 30 hasil per pencarian), edit maksimal 20 porsi, pilih primary, simpan, dan hitung simulasi.
- GET/PUT `/products/{product:slug}/cross-village-configuration` hanya local/testing untuk admin terverifikasi. PUT memerlukan konfirmasi kata sandi sensitif serta alasan perubahan.
- Versi hash konfigurasi mencegah overwrite porsi yang telah berubah (409). Penyimpanan mengunci produk/paket/mitra, mengganti pemetaan dan memvalidasi alokasi dalam transaksi; kegagalan mengembalikan pemetaan awal tanpa audit baru.
- Audit memuat aktor, alasan, porsi sebelum/sesudah dan hash IP. Simulasi mengunci paket agar membaca pemetaan konsisten dengan penulisan admin.
- Verifikasi akhir konfigurasi/alokasi: 22 test / 81 assertion lulus; frontend lint/build 20 route lulus. Tidak menambahkan dependency atau menerapkan konfigurasi mitra nyata.

Lanjutan snapshot pesanan sandbox (1 Oktober 2026):
- GET/POST `/orders/{public_id}/cross-village-snapshot` dan UI pada `/dashboard/lintas-desa` tersedia untuk admin terverifikasi di local/testing. Pembuatan memerlukan konfirmasi kata sandi dan alasan.
- Pesanan paid, satu item paket IDR, pemilik sesuai, dan pembayaran succeeded sandbox dengan nominal tepat diperlukan. Pembayaran non-sandbox, komisi invalid, atau versi porsi stale ditolak.
- Snapshot JSON pada Order menyimpan porsi, aktor/waktu, nominal dan alokasi. Sub-order berstatus simulation_only dibuat bersama audit dalam transaksi yang mengunci pesanan/paket. Retry memakai version pertama mengembalikan snapshot awal, tanpa audit/sub-order kedua.
- Total dan komisi berasal dari uang tersimpan pada item/pesanan, bukan harga saat ini. Alokasi subtotal, komisi dan pendapatan seluruh mitra seimbang sampai rupiah. Ledger/payout/inventori/status pesanan tidak berubah.
- Snapshot dibuat saat simulasi admin (basis configuration_at_simulation), bukan checkout. Perubahan porsi atau refund tidak menulis ulang riwayat. Kolom snapshot disembunyikan dari serialisasi Order umum.
- Verifikasi: regresi backend 248 test / 1148 assertion lulus (10 MySQL-only skipped saat run tersebut); 34 test terfokus / 144 assertion lulus. Uji MySQL dua proses snapshot: 1 test / 6 assertion lulus setelah respons pertama diseragamkan dengan representasi JSON tersimpan; pengujian snapshot ulang akhir 12 test / 63 assertion lulus. Lint/build frontend 20 route dan OpenAPI 19 path valid.
- Migrasi kolom snapshot diterapkan pada database lokal dan test MySQL tanpa reset data.

Lanjutan persetujuan mitra sandbox (1 Oktober 2026):
- Halaman `/dashboard/persetujuan-mitra` menampilkan proposal mitra pengguna dengan pagination dan keputusan per revisi. Owner/manager aktif dengan email terverifikasi dapat menyetujui atau menolak porsi sendiri, dengan kata sandi/alasan; staff/inactive/mitra lain ditolak. Super_admin tidak dapat memutuskan tanpa keanggotaan mitra yang sesuai.
- Endpoint GET `/partner/cross-village-proposals`, GET/POST `/products/{slug}/cross-village-agreement` hanya local/testing. Ringkasan keputusan juga tampil pada konfigurasi admin.
- Kolom cross_village_revision bertambah setiap simpan konfigurasi, termasuk porsi identik. Hash versi mencakup revisi; persetujuan lama tidak aktif kembali ketika porsi kembali ke nilai semula.
- Tabel CrossVillageAgreement unique package/revision/partner menyimpan keputusan, aktor/waktu/alasan; audit menyimpan perubahan keputusan. Retry keputusan sama tidak menggandakan audit; accepted dapat ditarik menjadi rejected. Semua mitra harus accepted agar ringkasan all_accepted=true.
- Snapshot pesanan baru menyimpan revision, agreements dan all_accepted saat simulasi. Simulasi pending tetap diperbolehkan dan bukan dasar pencairan; riwayat snapshot tidak berubah setelah keputusan berikutnya.
- Verifikasi terfokus awal: 41 test / 179 assertion lulus, mencakup isolasi akses, dua mitra, penarikan/retry, stale version, revisi identik, dan regresi snapshot/alokasi. Regresi backend: 255 test / 1183 assertion lulus, 11 MySQL-only skipped; uji MySQL snapshot 1 test / 6 assertion lulus. Pemeriksaan akhir persetujuan juga mencocokkan hash konfigurasi pada keputusan. Frontend lint/build 21 route dan OpenAPI 21 path valid. Migrasi lokal dan database test MySQL diterapkan tanpa reset data.

Lanjutan snapshot saat checkout sandbox (1 Oktober 2026):
- Quote publik menampilkan version/revision/all_accepted/available untuk paket lintas desa tanpa membuka porsi/aktor mitra. Frontend checkout menyertakan versi dan total quote, menampilkan status persetujuan serta hasil snapshot.
- Paket dengan pemetaan lintas desa wajib memakai versi quote terkini dan seluruh persetujuan accepted. Published, IDR, per_person, tanggal serta batas peserta divalidasi; di luar local/testing diblokir. Kontrol buka/tutup checkout pilot tetap berlaku.
- Snapshot basis configuration_at_checkout dan sub-order simulation_only disimpan saat order pending_payment dibuat, bersama item, hold, audit dan kupon dalam satu transaksi. Total/komisi net seimbang sampai rupiah; kegagalan snapshot me-rollback seluruh perubahan dan kuota.
- Checkout mengunci produk/paket agar persetujuan/configuration konsisten dengan pembelian. Retry payload/version sama mempertahankan order/snapshot awal meskipun konfigurasi kemudian berubah; version berbeda pada key sama ditolak.
- Snapshot manual untuk order historis tetap tersedia dan memakai basis configuration_at_simulation. Snapshot checkout bukan pencairan, refund, atau settlement lintas mitra.
- Verifikasi: regresi backend 261 test / 1221 assertion lulus (11 MySQL-only skipped saat run tersebut). Pengujian akhir checkout lintas desa/kupon 6 test / 43 assertion lulus; seluruh concurrency MySQL 12 test / 88 assertion lulus termasuk retry checkout bersamaan tanpa hold/order/sub-order/audit ganda. Frontend lint/build 21 route lulus. Tidak ada migrasi baru atau aktivasi checkout pilot.

Belum selesai: kontrak produksi dan persetujuan hukum mitra; integrasi ledger/refund/payout lintas mitra; stok lintas penyedia; rollout wilayah dan UAT staging. Checkout awal tetap satu produk/satu mitra sesuai scope backlog; simulasi ini tidak mengaktifkan keranjang lintas mitra.


## Lanjutan integrasi pembayaran — Midtrans sandbox

Status: adapter dan integrasi lokal dibuat 1 Oktober 2026. Pengujian provider langsung belum dilakukan karena Server Key sandbox dan URL webhook HTTPS publik belum tersedia.

- PaymentGateway kini dipilih oleh PAYMENT_GATEWAY: sandbox internal atau midtrans_sandbox. Konfigurasi lokal diarahkan ke midtrans_sandbox dengan key kosong sehingga checkout belum siap, tanpa membuka kontrol pilot.
- Snap Redirect dibuat lewat HTTP client Laravel dengan Basic Auth, timeout/connect timeout, URL sandbox tetap, gross_amount dari order net, satu baris item senilai total net, dan UUID public_id sebagai order_id/reference stabil. Tidak menambah dependency.
- Provider reference dibuat sebelum permintaan luar sehingga webhook cepat dapat dikaitkan; respons Snap tidak menurunkan attempt succeeded menjadi pending. Retry uncertain hanya lookup GET, bukan charge kedua; failed Midtrans tidak dibuat ulang memakai order_id yang sama.
- GET /payments/gateway-status tidak membuka kredensial. UI checkout memeriksa kesiapan provider dan menampilkan Midtrans sandbox serta status uncertain.
- Webhook /webhooks/payments/midtrans memverifikasi signature SHA512 dan status API otoritatif, nominal, currency, transaction_id serta order reference. Payload sensitif tidak disimpan. Event durable, ledger/voucher/outbox memakai proses idempoten yang sudah ada.
- Rekonsiliasi memilih provider tersimpan pada attempt sehingga pembayaran internal lama tidak dikirim ke Midtrans setelah driver berubah. Snapshot manual menerima provider sandbox internal atau Midtrans sandbox.
- PHPUnit dan TestCase memaksa driver internal/key kosong untuk isolasi dari env aplikasi; test Midtrans memakai HTTP fake. Tidak ada transaksi provider langsung atau transfer uang nyata.
- Verifikasi regresi penuh: 282 test / 1288 assertion lulus, 12 MySQL-only skipped. Konkurensi MySQL terisolasi: 12 test / 88 assertion lulus. Frontend lint/build 21 route, Pint, dan OpenAPI 23 path valid. Endpoint runtime lokal mengonfirmasi provider midtrans_sandbox dengan ready=false karena key belum tersedia.
- Verifikasi terfokus setelah perubahan akhir: 26 test / 97 assertion lulus, mencakup Midtrans, payment attempt, checkout, serta webhook yang tiba sebelum respons Snap.

Belum selesai: mengisi key sandbox, menyediakan webhook HTTPS publik, UAT end-to-end terhadap Midtrans, recovery redirect Snap yang hilang ketika POST timeout, fee settlement aktual, refund/payout provider, dan konfigurasi production/finance/legal.

## Lanjutan Fase 38–39 — katalog mobile dari API

Status 2 Oktober 2026: katalog mobile terimplementasi dan bundle kedua platform lolos pemeriksaan; fase mobile keseluruhan masih berjalan.

- Mengganti placeholder App.js dengan katalog destinasi publik, pencarian nama, filter wilayah/kategori dari lookup, pagination 12 item, refresh, detail, dan navigasi kembali. Query/filter/halaman dipertahankan ketika kembali dari detail; tombol hardware Android ditangani.
- GET /destinations/{slug} kini memiliki implementasi nyata. Hanya destinasi published yang tampil; draft/deleted/missing mendapat 404. Respons whitelist tanpa partner_id atau audit/media privat, dengan kategori/koordinat nullable dan ETag kondisional yang berubah setelah edit.
- Klien API menggunakan GET tanpa kredensial, URL environment publik, HTTPS wajib pada rilis, timeout 12 detik, cancellation, guard hasil lama, validasi bentuk respons dan pesan retry. Tidak menggunakan URL/token pembayaran dan tidak membuat pesanan.
- Entry point Expo diperbaiki dengan registerRootComponent; konfigurasi app, Metro .mjs, lockfile, ignore runtime dan contoh environment ditambahkan. Versi dependency mengikuti kerangka lama tanpa dependency tambahan.
- Panduan API mobile diperbaiki: pencarian melalui /destinations?q, profil /me, pesanan /account/orders. Login web masih sesi Sanctum/CSRF; penerbitan token native/JWT belum tersedia.
- CI mobile memeriksa lockfile, test Node bawaan dan ekspor Metro/Hermes Android/iOS. Panduan setup emulator/perangkat serta batas uji ada di runbook.
- Verifikasi: API detail/katalog 10 test / 59 assertion lulus. Regresi backend 288 test / 1314 assertion lulus (12 MySQL-only skipped; tidak ada perubahan inventori/konkurensi). Klien mobile 10 test lulus. Ekspor Android 1.49 MB dan iOS 1.48 MB lulus; Pint, diff check, dan OpenAPI 24 path valid.

Belum selesai: upgrade Expo SDK, UAT perangkat/emulator, login native dan penyimpanan token aman, profil/pesanan/voucher/pembayaran native, APK/IPA, serta rollout beta Fase 40. Ekspor bundle bukan build aplikasi atau bukti UAT perangkat. Midtrans langsung tetap menunggu Server Key sandbox dan webhook HTTPS publik.

## Fokus UI/UX — desain marketplace perjalanan

2 Oktober 2026: 20 template halaman web dirapikan dengan inspirasi struktur marketplace perjalanan Traveloka/Tiket dan identitas WisataDaerah sendiri.

- Shell/header/footer, brand, navigasi aktif, breadcrumb, menu/bottom navigation ponsel dan workspace memakai komponen bersama. Kelas layout lama kini mendapat stylesheet yang benar; nested main di akun/portal mitra dihapus.
- Beranda diganti dengan hero foto, pencarian, kategori, inspirasi dan kartu pengalaman. Rating/diskon/cashback tanpa sumber data dihapus; foto ilustrasi diberi label.
- Katalog destinasi memuat API termasuk query kosong, wilayah/kategori, pagination, loading/error/empty dan wishlist. Detail memakai API terbit dengan koordinat asli bila tersedia; slug tidak lagi direka menjadi nama dan koordinat demo.
- Paket memakai katalog API dan meneruskan pilihan produk ke checkout. Detail demo berisi timeline itinerary yang jelas berlabel contoh.
- Login/daftar memakai layout dua kolom, field berlabel dan password visibility. Pendaftaran memakai API yang sudah ada serta konfirmasi password; lupa password mengakses endpoint pemulihan yang sudah ada.
- Checkout memakai step indicator, pilihan nama produk, data pemesan dan ringkasan sticky. Halaman pembayaran, voucher, bantuan, penginapan, kuliner, akun dan seluruh operasional memakai header/panel/spacing konsisten.
- Fallback loading, error dan 404 memakai identitas yang sama. Tidak mengaktifkan pilot atau pembayaran production.
- Verifikasi: lint/build/formatter/diff check lulus; audit browser desktop 1440×960 dan ponsel 390×844 tanpa overflow horizontal pada halaman yang diperiksa; field halaman utama berlabel. Tes API terkait 15 test / 67 assertion lulus. UAT autentikasi penuh seluruh peran dan transaksi tetap terbuka.

## Modul katalog produk UMKM

2 Oktober 2026: katalog `/umkm` dan detail `/umkm/[slug]` ditambahkan dengan nama, deskripsi, lokasi penjual, harga integer IDR per satuan, pencarian dan pagination. Menu utama, kategori beranda, breadcrumb dan footer terhubung.

API publik `GET /api/v1/umkm-products` dan `GET /api/v1/umkm-products/{slug}` hanya menampilkan produk published milik mitra approved yang belum dihapus. Tabel terpisah `umkm_products` tidak masuk checkout tiket. Seeder lokal/testing menyediakan tiga produk sintetis berlabel demo dan tidak mengganti data yang sudah ada. Tidak ada foto penjual palsu; placeholder ditandai.

Verifikasi: 3 tes fitur / 17 assertion lulus untuk kontrak data, pencarian, pagination, validasi dan visibilitas produk/mitra. Pengelolaan produk oleh admin/mitra, unggah foto, stok, pengiriman dan pembayaran UMKM belum termasuk cakupan katalog ini.

## Pemesanan online produk UMKM

2 Oktober 2026: formulir pada detail produk membuat pesanan akun dengan jumlah, nama penerima, nomor kontak dan catatan. Alur awal adalah pengambilan di lokasi penjual, tersedia hanya pada local/testing; status `reserved_sandbox`, pembayaran `unpaid`. Daftar `/akun/umkm` menampilkan snapshot nama/harga/satuan/lokasi, rincian penerima, total dan pembatalan dengan konfirmasi.

Server mengunci akun, pesanan idempoten, produk dan mitra; menghitung total integer dari harga server, menolak harga yang berubah dan stok kurang. Retry dengan payload sama mempertahankan pesanan lama; payload berbeda mendapat 409. Pembatalan mengembalikan stok satu kali, termasuk produk soft-deleted. Data pesanan hanya dapat diakses pemiliknya, dengan respons no-store. UI mempertahankan key/data pada respons jaringan tidak pasti dan memberi retry permintaan yang sama.

Migrasi stok dan pesanan diterapkan lokal. Tiga produk demo diberi stok awal 20 saat migrasi lokal, tanpa mengisi ulang stok pada seeding berikutnya. Produk baru tetap stok nol kecuali dikonfigurasi; data seeder baru menyediakan stok demo.

Verifikasi: 8 tes katalog/pesanan / 42 assertion lulus; 2 tes konkurensi MySQL terisolasi / 10 assertion lulus untuk dua pembeli berebut stok terakhir dan dua retry bersamaan. Uji konkurensi menemukan pembacaan pesanan stale; pencarian key kini memakai locking read dan kedua tes lulus. Frontend lint/build lulus. UAT browser menggunakan akun pelanggan belum dilakukan; tampilan sebelum login diperiksa.

Belum termasuk: pembayaran UMKM Midtrans, pengiriman/ongkir, expiry reservasi, pengelolaan stok dan konfirmasi/penyelesaian oleh mitra, notifikasi serta aktivasi produksi. Pemesanan ini tidak mengaktifkan checkout wisata atau gateway production.

### Login wajib dan pemantauan status UMKM

Pembuatan, daftar dan pembatalan pesanan tetap dilindungi `auth:sanctum` dan ownership akun. Halaman pesanan memperjelas status pesanan serta pembayaran secara terpisah, dengan filter semua/tersimpan simulasi/dibatalkan dan tombol perbarui status. Footer dan halaman akun memberi akses Pesanan UMKM. Status diproses/dikirim/selesai belum diimplementasikan dan tidak direka di UI.

Tes pesanan 5 test / 30 assertion lulus, termasuk penolakan tanpa login, isolasi akun dan filter status. Lint/build frontend diperiksa kembali.

## Akun demo tiga peran dan alur checkout akun

2 Oktober 2026: akun admin.pilot@example.test, owner.pilot@example.test dan visitor.pilot@example.test diterapkan ke database lokal lewat PilotDatabaseSeeder. Pengunjung baru diberi role customer tanpa keanggotaan mitra. Semua akun latihan terverifikasi; password latihan dan cara reseeding ada di RUNBOOK. Checkout tetap ditutup oleh seeder.

Login mengarahkan admin/mitra aktif ke dashboard, pelanggan ke akun. Header menampilkan Akun saya/Keluar saat sesi aktif. Checkout wisata tanpa kupon kini mengikat pesanan ke akun login; pencarian idempotency memakai locking read dan retry dari akun berbeda ditolak.

UAT browser lokal: login/logout admin dan mitra berhasil, dashboard admin menampilkan kontrol pilot yang tidak tampil untuk mitra. Pengunjung diarahkan ke akun dan ditolak saat membuka dashboard. Pengunjung juga membuat satu pesanan UMKM demo, melihat status, lalu membatalkannya; stok dikembalikan. Pesanan dibatalkan disimpan sebagai bukti latihan.

Tes login → checkout Midtrans → notifikasi bertanda tangan → status paid di akun → satu voucher lulus menggunakan Http::fake (bukan provider langsung). Suite backend 301 test / 1429 assertion lulus; 14 test MySQL-only dilewati pada SQLite. Frontend lint/build lulus. Midtrans runtime masih key kosong/ready=false; UAT provider langsung tetap membutuhkan Server Key sandbox dan webhook HTTPS publik. Tidak mengaktifkan checkout pilot atau pembayaran production.

Verifikasi MySQL terisolasi sesudah perubahan checkout akun: seluruh 14 tes konkurensi inventori/pemesanan lulus (98 assertion), termasuk kupon, hold, webhook, snapshot lintas desa dan UMKM.

## Melanjutkan pembayaran dari akun

Rincian pesanan wisata kini menyediakan Perbarui status pembayaran dan tautan Lanjutkan pembayaran Midtrans sandbox. API detail hanya mengembalikan checkout_url tersimpan ketika pemilik login, order pending_payment, attempt terbaru pending/midtrans_sandbox dengan nominal dan mata uang sesuai, serta seluruh hold inventori aktif dan belum kedaluwarsa. URL harus HTTPS pada domain dan path Snap sandbox yang diizinkan. GET tidak membuat attempt/charge baru. Status paid/failed/uncertain atau hold kedaluwarsa tidak menawarkan pembayaran ulang.

Verifikasi terfokus 30 test / 129 assertion lulus sebelum penambahan guard nominal/mata uang; tes resume diperiksa kembali setelah guard. Lint/build frontend lulus. Key Midtrans runtime masih kosong, sehingga uji langsung dan aktivasi checkout tetap belum dijalankan.

## Koreksi format key Midtrans sandbox

Screenshot pengguna menunjukkan dashboard Sandbox dengan key format Mid-server-. Validasi sebelumnya terlalu ketat pada awalan SB-Mid-server-. Gateway kini menerima kedua format Server Key, tetap membatasi environment local/testing/staging dan mematok seluruh endpoint ke domain sandbox. Backend, worker dan scheduler direcreate agar env_file baru terbaca; config cache dibersihkan.

Pemeriksaan autentikasi read-only ke API sandbox dengan referensi acak menghasilkan HTTP 200/status_code 404 (transaksi tidak ada), tanpa membuat transaksi. Format bukan bukti environment; validasi provider tetap diperlukan. Tes Midtrans dan checkout akun: 24 test / 89 assertion lulus. Uji transaksi Snap dan notifikasi langsung masih belum dijalankan; URL webhook HTTPS publik belum diberikan. Nilai key tidak dicatat pada output atau dokumentasi.


## UAT provider Midtrans sandbox melalui ngrok

2 Oktober 2026: tunnel pengguna pada `https://1301-125-165-106-180.ngrok-free.app` mencapai aplikasi. POST webhook kosong ditolak 422, signature palsu ditolak 401, gateway ready=true. Checkout pilot lokal dibuka oleh akun demo admin dengan alasan uji sandbox; tidak mengaktifkan production.

Akun visitor demo membuat pesanan tiket Rp10.000 untuk 4 Oktober 2026, public_id `c632e69e-3329-4917-8753-a04987ba1031`. Snap asli sandbox berhasil dibuat; pembayaran disimulasikan melalui simulator resmi BCA tanpa uang nyata. Dua event Midtrans (pending dan sukses) diproses worker. Database menunjukkan satu payment attempt succeeded, order paid, last_reconciled_at null dan satu voucher. UI akun menampilkan Dibayar dan voucher kunjungan dapat dibuka. Ini membuktikan notifikasi provider langsung, bukan Http::fake atau rekonsiliasi manual.

Temuan: redirect selesai dari Snap sempat menuju `www.vidarabooks.com/auth/login`, sesuai konfigurasi merchant yang belum disesuaikan. Finish Redirect URL perlu diarahkan ke `/akun` aplikasi ini pada domain yang digunakan. Pembayaran UMKM belum terhubung Midtrans; hasil UAT ini hanya tiket wisata. Tunnel harus tetap berjalan untuk menerima notifikasi berikutnya.


## URL kembali setelah pembayaran sandbox

2 Oktober 2026: permintaan pembuatan token Snap kini mengirim `callbacks.finish` dari konfigurasi `services.frontend_url` menuju `/akun`. Ini mengatasi token baru yang sebelumnya mengikuti Finish URL merchant situs lain. Token yang sudah terbit tidak diperbarui. Webhook tetap menjadi sumber status pembayaran; callback tidak menandai pesanan paid. Tes Midtrans dan checkout login lulus 24 test / 89 assertion pada environment testing dengan SQLite memory; Pint lulus. Redirect provider langsung untuk token baru belum diuji ulang.


## Pengelolaan pesanan UMKM oleh mitra

2 Oktober 2026: `/dashboard/umkm` menampilkan pesanan produk dalam cakupan pemilik/manajer mitra aktif; super_admin dapat melihat seluruh pesanan. Pengunjung, staff dan membership nonaktif ditolak. API GET/PATCH `/api/v1/dashboard/umkm-orders` memakai autentikasi, throttle dan respons private/no-store. Produk soft-deleted tetap dapat dilacak untuk pesanan lama.

Urutan simulasi: reserved_sandbox → processing_sandbox → ready_sandbox → completed_sandbox. Pesanan aktif dapat dibatalkan mitra, stok kembali sekali; status terminal tidak dapat dibuka lagi. Update ulang status yang sama idempotent. Transaksi mengunci akun, pesanan dan produk saat pengembalian stok. Pembeli tetap hanya melihat pesanan sendiri dan dapat membatalkan sebelum diproses. Status diproses/siap diambil/selesai kini terlihat dan dapat difilter di akun pembeli. Pembayaran tetap unpaid; pemrosesan ini tidak melakukan charge, pengiriman atau aktivasi produksi.

Tes fitur pengelolaan dan pesanan pembeli: 11 test / 81 assertion lulus pada SQLite memory. Kasus mencakup urutan status, pembaruan ulang, cancel setelah diproses, stok produk soft-deleted, isolasi tenant, staff, membership nonaktif, pengunjung, admin, invalid status dan blokir write production. Pint lulus. Lint/build frontend lulus; uji UI pengunjung ditolak dan pemilik mitra tidak melihat pesanan mitra lain.

Pemeriksaan tambahan pada MySQL terisolasi `wisata_concurrency_test`: pengelolaan mitra dan race pemesanan UMKM lulus 8 test / 61 assertion. UI admin dapat melihat dan memfilter pesanan demo. Pesanan pengguna yang sudah ada tidak diubah selama UAT browser.


## Pengelolaan katalog dan stok UMKM

2 Oktober 2026: halaman `/dashboard/umkm/produk` menyediakan tambah/edit nama, deskripsi, lokasi, harga, satuan, stok tersedia dan status draft/published. GET/POST/PATCH `/api/v1/dashboard/umkm-products` memakai auth, throttle, cakupan partner approved dan respons private/no-store. Pemilik/manajer aktif hanya mengelola produk mitranya; admin dapat mengelola semua partner approved. Staff, pengunjung dan membership nonaktif ditolak. Semua write tetap local/testing; produk baru ditandai demo. Slug dibuat server dan partner produk lama tidak dapat dipindahkan lewat payload edit.

Edit memakai revision hash pada atribut produk/stok, diperiksa setelah row lock di transaksi. Jika stok atau metadata sudah berubah, respons 409 meminta muat ulang, sehingga editor lama tidak menimpa stok reservasi baru. Draft tidak muncul pada katalog publik; snapshot harga pesanan lama tetap utuh. Batas input: harga Rp1–1 miliar, stok 0–1 juta, deskripsi hingga 5000 karakter. Tidak ada penghapusan permanen atau reset stok melalui seeder.

Pint, lint dan build frontend lulus. Tes fitur pengelolaan, katalog dan pesanan: 21 test / 149 assertion pada SQLite memory; tes pengelolaan + race pemesanan MySQL terisolasi: 9 test / 61 assertion. UAT browser admin membuat Souvenir Lokal Pilot — Demo pada Mitra Pilot Demonstrasi, menyimpan draft, kemudian publik dan stok 15, harga Rp35.000; produk tampil pada katalog. Slug `umkm-6bebb58b-86e6-4c13-94dc-dffc0aca286e`. Produk lama tidak diubah. Belum mencakup upload foto, audit perubahan terpisah, aktivasi produksi atau pembayaran UMKM. Pengarahan token Snap ditunda sesuai permintaan pengguna.


## Foto utama produk UMKM

2 Oktober 2026: produk memiliki relasi nullable photo_media_id ke media. Editor produk memakai endpoint unggah media yang sudah ada (JPG/PNG/WebP maksimal 5 MB), kemudian mengaitkan foto saat Simpan produk. Penggantian atau pelepasan foto tidak menghapus file secara permanen. Foto hanya dapat dipilih dari media public pada disk public milik partner produk yang sama, dengan path gambar yang sesuai dan file tersedia.

Katalog dan detail menampilkan foto melalui endpoint same-origin `/api/v1/umkm-products/{slug}/photo`; endpoint hanya melayani produk published pada partner approved. Pratinjau editor memakai `/api/v1/dashboard/umkm-products/{slug}/photo` dengan auth dan cakupan mitra. Tidak bergantung pada URL localhost:8000, storage link atau konfigurasi ngrok untuk gambar. Stream menggunakan no-store/nosniff; stream editor private. Revision produk mencakup photo_media_id, sehingga editor lama tidak menimpa perubahan foto.

Migrasi penambahan kolom berhasil di database aplikasi tanpa mengubah data produk lama. Tes foto/katalog/pengelolaan: 14 test / 106 assertion SQLite memory lulus. Foto/pengelolaan pada MySQL terisolasi: 11 test / 89 assertion lulus. Lint, build dan Pint lulus. UAT browser berhasil mengunggah fixture PNG sintetis `tests/Fixtures/umkm-photo.png` dan menyimpannya pada Souvenir Lokal Pilot — Demo; stok tetap 15 dan harga Rp35.000. Ini gambar pengujian, bukan foto barang nyata. Belum mencakup galeri multi-foto, kompresi gambar atau pembersihan media yang tidak lagi dipakai.


## Kartu produk penginapan dengan foto luar/interior dan tarif

2 Oktober 2026: katalog penginapan kini memakai kartu produk responsif dengan dua foto berdampingan (tampak luar/interior kamar), nama, deskripsi, kapasitas tamu, tarif mulai per kamar/malam dan tombol Pilih kamar. Pemilihan kartu mengisi room_type_id pada formulir dan menggulir ke pemeriksaan ketersediaan; logika reservasi/idempotensi tidak berubah. Foto yang gagal dimuat memakai placeholder. Pagination kamar ditempatkan bersama katalog.

RoomType ditambah exterior_image_url, interior_image_url dan photos_are_illustrations. Katalog hanya mengembalikan URL HTTPS images.unsplash.com pada path photo-. Data demo diberi foto ilustrasi yang terlihat di browser; bukan foto properti nyata. Seeder mempertahankan foto yang sudah terisi, stok dan tarif lama tidak direset. Tarif mulai dihitung dari MIN tarif positif hari ini hingga satu tahun ke depan, dengan stok pada tanggal yang sama >0. Tarif lewat, tanpa inventori atau stok habis tidak dihitung. Tarif aktual tetap melalui quote sesuai tanggal pengguna.

Pint, lint/build frontend dan diff check lulus. Tes kartu + regresi reservasi 23 test / 104 assertion pada SQLite memory; tes kartu MySQL terisolasi 2 test / 11 assertion. UAT browser: dua foto tampil, tarif demo Rp150.000/malam, tombol kartu memilih kamar pada formulir. Foto penginapan nyata dan antarmuka pengelola foto/tarif belum tersedia.

### Pengelolaan penginapan oleh admin — 2 Oktober 2026

- Ditambahkan `/dashboard/penginapan`: edit informasi kamar, kapasitas, status katalog, URL foto luar/interior, serta tarif dan stok tersedia untuk satu tanggal.
- API memerlukan admin platform dengan email terverifikasi. Mutasi dibatasi lingkungan local/testing. Belum ada pemetaan pemilik kamar ke mitra, sehingga mitra belum dapat mengelola penginapan.
- Penguncian tipe kamar mengikuti urutan reservasi. Revision mencakup informasi kamar dan tarif/stok tanggal terpilih; formulir usang ditolak 409. Pesanan dan snapshot harga sebelumnya tidak diubah. Perubahan dicatat di audit log.
- Foto masih memakai URL Unsplash yang divalidasi; unggah foto properti, pembuatan tipe kamar baru, dan pengaturan massal rentang tanggal belum termasuk fase ini.
- Verifikasi: 28 tes fitur pengelolaan/katalog/reservasi lolos (137 assertions) pada SQLite terisolasi; 5 tes pengelolaan lolos pada MySQL terisolasi (33 assertions). Uji browser admin berhasil menyimpan konfigurasi kamar demo tanpa mengubah tarif Rp150.000 atau stok 5. Lint dan build frontend diperiksa.

### Unggah foto luar dan interior penginapan — 2 Oktober 2026

- Admin dapat memilih JPG/PNG/WebP maksimal 5 MB untuk masing-masing foto, lalu menyimpan bersama informasi dan tarif kamar. Multipart menormalkan harga desimal database ke nilai integer rupiah.
- Foto disimpan pada disk privat dan disajikan melalui endpoint kamar. Foto kamar aktif dapat dilihat publik; foto kamar nonaktif hanya dapat dilihat admin terverifikasi. Path penyimpanan tidak ditampilkan pada katalog.
- Foto unggahan didahulukan dari URL ilustrasi. Melepas/mengganti foto tidak menghapus berkas lama secara permanen. Gambar uji browser dilepas kembali setelah unggah berhasil; foto ilustrasi demo tetap tampil.
- Revision mencakup kedua path foto. Konflik formulir ditolak sebelum berkas disimpan; berkas baru dibersihkan jika transaksi gagal. Tarif dan stok tidak berubah karena operasi foto saja.
- SQLite terisolasi: 32 tes foto, pengelolaan, katalog, dan reservasi lolos (175 assertions). Uji browser berhasil unggah, menampilkan gambar lokal pada kartu, dan melepas gambar. Galeri tambahan, kompresi, pembersihan berkas lama, dan pemetaan mitra masih belum tersedia.

### Pembuatan tipe kamar penginapan — 2 Oktober 2026

- Admin terverifikasi dapat menambah tipe kamar melalui `/dashboard/penginapan`, dengan informasi kamar, kapasitas, foto luar/interior, status katalog, serta tarif dan stok awal untuk satu tanggal. Formulir baru berstatus tersembunyi secara awal.
- Endpoint POST `/api/v1/dashboard/lodging-rooms` dibatasi local/testing. Idempotency-Key diikat ke admin; pengulangan payload identik mengembalikan kamar yang sama, sedangkan payload berbeda ditolak 409. Kunci internal tidak tampil pada API katalog.
- Kamar, tarif, stok, dan audit dibuat dalam satu transaksi. Foto baru dibersihkan jika transaksi gagal. Formulir edit memakai kembali validasi dan unggah foto yang sama.
- SQLite terisolasi: 38 tes pembuatan/foto/pengelolaan/katalog/reservasi lolos (223 assertions), termasuk retry dengan file dan rollback kegagalan audit. Lint/build frontend lolos.
- UAT browser membuat `Homestay Pilot — Kamar Keluarga Demo`, kapasitas 4, tarif Rp250.000, stok 3 pada 2 Oktober 2026. Data sintetis ini tetap tersembunyi dari katalog publik; ilustrasi bukan foto properti nyata.
- Tarif/stok masih diatur satu tanggal per simpan. Pengelompokan properti hotel/homestay, lokasi properti, kalender rentang tanggal, dan kepemilikan mitra belum ditambahkan.

### Kalender tarif rentang tanggal penginapan — 2 Oktober 2026

- Dashboard penginapan memiliki tombol Kalender tarif per kamar. Admin dapat melihat rincian tarif/stok dan menerapkan satu tarif pada rentang maksimal 90 tanggal, termasuk tanggal awal dan akhir, dalam batas satu tahun ke depan.
- Stok awal bersifat opsional dan hanya mengisi tanggal yang belum memiliki inventory. Stok yang sudah ada, termasuk nol atau berkurang karena reservasi, tidak ditimpa. Tarif dapat diterapkan tanpa menambahkan inventory; tanggal tersebut belum dapat dipesan sampai stok diatur.
- GET/PATCH `/api/v1/dashboard/lodging-rooms/{room}/calendar` memerlukan admin terverifikasi. Mutasi tetap local/testing. Revision meliputi semua tarif/stok dalam rentang; perubahan sejak pratinjau menghasilkan 409 tanpa perubahan parsial. Lock tipe kamar mengikuti urutan reservasi. Perubahan memiliki audit sebelum/sesudah dan tidak mengubah snapshot pesanan lama.
- Verifikasi: 45 tes fitur penginapan lolos pada SQLite terisolasi (293 assertions); 7 tes kalender lolos pada MySQL terisolasi (70 assertions). Lint/build frontend lolos. Uji browser 2–7 Oktober 2026 pada Homestay Pilot — Kamar Keluarga Demo menerapkan Rp250.000 untuk enam tanggal. Stok awal 5 mengisi lima tanggal kosong; stok tanggal pertama tetap 3. Kamar demo masih tersembunyi dari katalog publik.
- Penyesuaian stok yang sudah ada tetap melalui editor satu tanggal dengan revision. Belum mencakup tarif menurut hari dalam minggu, pengelompokan properti, dan akses mitra.

### Alur katalog → detail → ketersediaan penginapan — 2 Oktober 2026

- Kartu katalog `/penginapan` memakai tautan Lihat detail menuju `/penginapan/{id}`. Formulir pemeriksaan ketersediaan dihapus dari katalog.
- Halaman detail menampilkan foto luar/interior, deskripsi lengkap, kapasitas, tarif mulai dari, lalu formulir tanggal/jumlah kamar/tamu untuk kamar yang dipilih. Pemilihan ulang tipe kamar tidak diperlukan. Daftar reservasi dan pembatalan milik pengguna tetap tersedia pada katalog; detail menyediakan tautan kembali ke daftar tersebut.
- Ditambahkan GET `/api/v1/lodging/rooms/{room}`. Hanya kamar aktif yang dapat dilihat; tarif dan foto menggunakan aturan yang sama dengan katalog. Kamar tersembunyi atau tidak ditemukan menghasilkan 404. Membuka detail tidak mengurangi stok.
- UAT pada alamat pengguna `http://localhost:3000/penginapan`: katalog tanpa formulir ketersediaan, tautan kartu membuka detail kamar 1, pemeriksaan 3–5 Oktober 2026 menghasilkan 2 malam × Rp150.000 = Rp300.000. Tidak dibuat reservasi baru.
- Verifikasi: 30 tes detail/katalog/reservasi/foto lolos pada SQLite terisolasi (165 assertions); lint/build frontend lolos dengan route detail dinamis.


### 2 Oktober 2026 — Detail rumah makan dan lokasi tempat
- Katalog kuliner berupa kartu rumah makan, detail restoran, harga awal, tanggal kunjungan, jam/paket dalam WIB, kuota, dan pemeriksaan harga sebelum reservasi. Reservasi tetap memerlukan login dan bersifat simulasi.
- Detail penginapan dan rumah makan memiliki lokasi, tombol pencarian Google Maps serta petunjuk arah berdasarkan nama + alamat lengkap atau koordinat opsional. Lokasi demo tidak diarahkan ke alamat nyata.
- Admin dapat mengisi alamat penginapan dan mengelola informasi/alamat/foto URL rumah makan melalui dashboard; revisi mencegah penimpaan perubahan lama. Alamat dan foto rumah makan nyata belum diberikan.
- Validasi: 37 pengujian backend / 217 assertions lulus (SQLite), 3 pengujian baru / 26 assertions juga lulus di MySQL terisolasi; lint dan build frontend lulus; alur katalog/detail/jadwal/harga/login diverifikasi di browser. Build memakai 2 pekerja agar sesuai sumber daya lokal.


### 2 Oktober 2026 — Jadwal kuliner dari dashboard admin
- Tambah dan ubah jadwal makan per rumah makan: tanggal/jam WIB, nama paket, harga, kapasitas, dan status aktif. Daftar menampilkan kuota tersedia serta jumlah yang sudah dipesan.
- Revisi mencakup reservasi; kuota yang berubah menolak pembaruan lama. Kapasitas tidak boleh kurang dari peserta dipesan; tanggal/jam/nama paket dengan reservasi aktif dilindungi. Perubahan harga tidak mengubah snapshot reservasi lama. Jadwal ganda ditolak, semua mutasi tercatat pada audit log, akses hanya admin terverifikasi, dan pengelolaan masih lokal/testing.
- Verifikasi: 28 pengujian SQLite / 152 assertions; 5 pengujian jadwal MySQL terisolasi / 39 assertions; lint lulus. Formulir browser berhasil menyimpan jadwal demo nonaktif tanggal 3 Oktober 2026 pukul 12.00 WIB, Rp80.000, kapasitas 8; data tetap tampil setelah muat ulang.
- Build Turbopack menemui panic internal; pemeriksaan production menggunakan webpack sebagai fallback.


### 2 Oktober 2026 — Pemantauan reservasi layanan admin
- Menu Admin → Reservasi layanan menampilkan pesanan penginapan dan kuliner dengan filter jenis layanan, status, tanggal check-in/jadwal makan WIB, serta ID reservasi. Daftar dipaginasi 20 per halaman.
- Informasi meliputi nama pemesan/tempat, tanggal, jumlah kamar/tamu atau peserta/paket, total snapshot, dan status. API hanya untuk super_admin terverifikasi, memakai private no-store, tidak memuat email/telepon/kunci idempotensi, serta tidak mengubah pesanan atau kuota.
- Verifikasi: 4 pengujian / 37 assertions lulus di SQLite; lint dan build webpack lulus. Filter dan keadaan kosong diuji di browser; belum ada reservasi layanan pada data aplikasi.


### 2 Oktober 2026 — Tambah rumah makan dari admin
- Tombol Tambah rumah makan menyediakan formulir nama, deskripsi, alamat, koordinat opsional, URL foto, dan status. Bawaan nonaktif; setelah tersimpan dapat langsung mengatur jadwal.
- Pembuatan memakai kunci idempotensi per admin, fingerprint isi, transaksi dan unique key untuk mencegah duplikasi akibat pengiriman ulang. Percobaan ulang dengan isi berbeda ditolak; kunci tidak dimuat pada respons publik. Akses admin terverifikasi dan mutasi masih dibatasi lokal/testing; pembuatan dicatat pada audit log.
- Verifikasi: 11 pengujian terkait / 90 assertions lulus SQLite; 3 pengujian pembuatan / 25 assertions lulus MySQL terisolasi; lint dan build webpack lulus. Browser berhasil membuat Rumah Makan Pilot Demo — bukan penawaran nyata dalam status nonaktif, tanpa alamat nyata atau jadwal.


### 2 Oktober 2026 — Unggah foto rumah makan
- Admin memilih rumah makan yang sudah tersimpan lalu mengunggah/mengganti foto JPG, PNG, atau WebP maksimal 5 MB, dengan pilihan label ilustrasi. Perubahan informasi tempat harus disimpan sebelum mengunggah foto.
- Foto tersimpan pada disk private local dan dilayani melalui endpoint terkontrol; foto unggahan diutamakan pada kartu dan detail kuliner. Properti nonaktif hanya dapat dipratinjau admin terverifikasi; path file tidak dimuat di respons publik, dan path harus milik tempat yang sesuai.
- Revisi informasi/foto menolak unggahan lama; transaksi gagal membersihkan file baru, foto terdahulu dipertahankan, aktivitas unggah dicatat pada audit log. Mutasi masih untuk lokal/testing.
- Verifikasi: 10 pengujian terkait / 79 assertions lulus SQLite; 4 pengujian foto / 28 assertions lulus MySQL terisolasi; lint dan build webpack lulus. Panel unggah ditinjau di browser. Foto asli belum diberikan; tidak ada foto buatan yang dimasukkan ke tempat demo.


### 2 Oktober 2026 — Reservasi saya untuk pengunjung
- Halaman /akun/reservasi menggabungkan daftar milik akun untuk penginapan dan kuliner, nomor reservasi, rincian status/harga/jadwal, pembatalan simulasi yang sudah tersedia, dan tautan mencari tempat. Akses tanpa login menampilkan ajakan masuk.
- Tautan tersedia di akun/footer serta formulir detail penginapan/kuliner. Jarak panel akun diperbaiki karena margin negatif lama menutupi tautan. Data tetap memakai API pemilik akun; tidak ada perubahan izin backend.
- Verifikasi: lint dan build webpack lulus; 41 pengujian reservasi backend / 180 assertions lulus SQLite. Browser memverifikasi tamu, login pengunjung demo, dan navigasi dari akun ke reservasi setelah perbaikan layout. Akun demo belum memiliki reservasi layanan.


### 2 Oktober 2026 — Pencarian penginapan dan rumah makan
- Kolom pencarian nama/alamat pada kedua katalog, jumlah hasil, reset pencarian, dan keadaan tanpa hasil. Pencarian bekerja di server atas seluruh katalog, mempertahankan filter saat paginasi, dan hanya memuat tempat aktif.
- Input maksimal 100 karakter, parameter SQL terikat, wildcard persen/underscore diperlakukan literal. Pencarian hanya membaca katalog; tidak membuat reservasi.
- Verifikasi: 11 pengujian terkait / 98 assertions lulus SQLite; 3 pengujian pencarian / 38 assertions lulus MySQL terisolasi; lint dan build webpack lulus. Browser memverifikasi hasil kosong dan reset pada penginapan serta pencarian Dapur pada kuliner.


### 2 Oktober 2026 — Urutkan katalog berdasarkan harga
- Penginapan dan rumah makan dapat diurutkan berdasarkan nama, harga terendah, atau harga tertinggi, bersama pencarian nama/alamat. Urutan dan pencarian dipertahankan saat paginasi; reset mengembalikan nama tempat.
- Urutan harga memakai tarif awal dari tanggal/jadwal dengan kuota tersedia. Tempat tanpa tarif diletakkan terakhir; harga sama diurutkan menurut nama/id agar halaman konsisten. Input sort dibatasi pilihan yang tersedia.
- Verifikasi: 5 pengujian pencarian/pengurutan / 64 assertions lulus SQLite; 2 pengujian pengurutan / 26 assertions lulus MySQL terisolasi; lint dan build webpack lulus. Pilihan harga terendah diterapkan dan diperiksa di browser.


### 2 Oktober 2026 — Filter kisaran anggaran
- Penginapan dan kuliner memiliki harga minimum/maksimum opsional, digabung dengan pencarian dan pengurutan serta dipertahankan saat paginasi. Reset mengosongkan batas harga.
- Filter memakai tarif awal pada tanggal/jadwal dengan kuota: per kamar/malam atau per peserta. Tempat tanpa tarif tidak masuk hasil saat batas harga diterapkan. Kisaran terbalik, nilai negatif, dan nilai di luar batas ditolak.
- Verifikasi: 6 pengujian katalog / 102 assertions lulus SQLite; pengujian anggaran / 38 assertions lulus MySQL terisolasi; lint dan build webpack lulus. Browser menunjukkan paket Rp75.000 tidak masuk anggaran Rp50.000 dan kembali tampil pada batas Rp80.000.

### 2026-10-02 — Akses mitra layanan dan konfirmasi reservasi
- Penginapan/rumah makan memiliki `partner_id` opsional. Data lama tetap dikelola admin; admin dapat menetapkan mitra approved lewat form. Kepemilikan yang sudah ditetapkan tidak dapat dipindahkan melalui editor biasa.
- Pemilik/manajer terverifikasi dengan keanggotaan aktif pada mitra approved dapat mengelola tempat miliknya, termasuk foto privat, tarif, stok, jadwal, dan daftar reservasi. Staff, pengunjung, serta anggota nonaktif/tidak approved ditolak. Menu mengikuti kemampuan akun dari `/me`.
- Konfirmasi pengelola memakai `confirmed_at`, pemeriksaan revision, transaksi/lock, audit, dan retry tanpa menggandakan audit. Reservasi dibatalkan ditolak. Status pembayaran, harga snapshot, dan kuota tidak berubah. Pengunjung melihat konfirmasi di `/akun/reservasi`.
- Seluruh mutasi layanan/konfirmasi tetap lokal/testing (simulasi). Midtrans Snap masih ditunda sesuai arahan pengguna. Belum mencakup check-in, penyelesaian layanan, atau pembayaran layanan nyata.
- Verifikasi: 34 tes fitur/276 assertions MySQL, 47 tes termasuk reservasi/224 assertions SQLite; tambahan cakupan foto privat dan jadwal: 7 tes/49 assertions SQLite. ESLint dan build Webpack 29 routes lulus. Migrasi additive diterapkan; browser menguji akses pengunjung ditolak dan form admin menampilkan pilihan mitra, tanpa menetapkan kepemilikan data lama secara otomatis.
- Verifikasi akhir tambahan: 7 tes akses/konfirmasi (49 assertions) lulus pada SQLite dan MySQL. Login owner di browser menampilkan modul layanan, daftar rumah makan kosong karena belum ada kepemilikan yang ditetapkan, dan form hanya menawarkan Mitra Pilot Demonstrasi. Pilihan form diperiksa tanpa menyimpan atau memberikan akses terhadap tempat lama.

### 2026-10-02 — Operasional reservasi layanan (simulasi)
- Penginapan: konfirmasi → check-in pada tanggal menginap (WIB, sebelum tanggal check-out) → penyelesaian layanan. Penyelesaian dapat dicatat sesudah check-in, termasuk checkout lebih awal.
- Kuliner: konfirmasi → penyelesaian setelah waktu kunjungan. Check-in tidak berlaku untuk kuliner.
- Pengelola dapat membatalkan reservasi sebelum check-in/selesai dengan alasan wajib. Layanan reservasi yang ada mengembalikan stok seluruh malam atau kuota makan tepat sekali; setelah check-in/selesai, pembatalan oleh pengunjung maupun pengelola ditolak.
- Timestamps `checked_in_at` (penginapan) dan `completed_at` menyimpan status operasional tanpa mengubah harga snapshot atau menyatakan pembayaran. Transaksi, revision, lock, audit, dan retry melindungi perubahan serta akses antar mitra.
- Dashboard reservasi memiliki filter tahap layanan dan tombol sesuai status/jadwal. Akun pengunjung menampilkan tahap terkini dan menyembunyikan pembatalan untuk layanan yang sudah berjalan/selesai. Semua mutasi tetap local/testing.
- Migrasi additive diterapkan. Verifikasi: 56 tes/307 assertions SQLite dan MySQL; setelah tambahan kasus pembatalan kuliner oleh manager dan akses/tanggal check-out, 6 tes operasional/52 assertions lulus di kedua database. ESLint dan build Webpack 29 routes lulus.
- Penetapan tempat lama ke mitra menunggu pasangan nama tempat → nama mitra dari pengguna; data kepemilikan tidak ditebak. Midtrans Snap tetap ditunda.
- UAT browser: satu reservasi sintetis #1 untuk Kamar Demonstrasi (2–3 Oktober 2026, 1 kamar, Rp150.000 simulasi) dibuat oleh akun demo owner, lalu diproses admin melalui konfirmasi → check-in → selesai. Tombol pembatalan hilang setelah check-in dan tombol penyelesaian hilang setelah selesai. Reservasi demo tetap tersimpan sebagai bukti UAT; stok malamnya tetap terpakai seperti layanan yang sudah dilaksanakan.
- UAT akun pemesan: `/akun/reservasi` menampilkan reservasi #1 sebagai “Layanan selesai (simulasi)” tanpa tombol pembatalan. Bukti tangkapan layar disimpan di folder visualizations/service-fulfillment.

### 2026-10-02 — Pengiriman UMKM (simulasi)
- Penjual dapat menawarkan pengiriman dan menetapkan ongkir tetap per pesanan (termasuk nol/gratis). Produk lama tetap pickup sampai opsi pengiriman diaktifkan. Ongkir bukan hasil perhitungan API kurir.
- Pembeli memilih pickup/pengiriman; pengiriman membutuhkan alamat lengkap dan kode pos 5 digit. Subtotal, ongkir, dan total ditampilkan sebelum menyimpan. Fee berasal dari produk yang terkunci dan diperiksa terhadap expected_shipping_fee; snapshot tidak mengikuti perubahan tarif produk setelah order dibuat.
- Alur pengiriman: pesanan masuk → diproses → dikirim (kurir dan resi wajib) → selesai. Alur pickup tetap masuk → diproses → siap diambil → selesai. Pembatalan setelah dikirim ditolak; sebelumnya stok dikembalikan satu kali.
- Mitra dapat mengoreksi kurir/resi dengan revision terbaru, tanpa mengubah waktu pengiriman pertama. Audit mencatat perubahan; retry identik tidak menggandakan audit atau mengurangi stok lagi.
- Alamat dan resi tersedia hanya pada API pesanan milik pembeli atau admin terverifikasi/pemilik/manajer aktif dari mitra approved. Tidak dimasukkan ke katalog publik. Status terlihat di akun pemesan; pelacakan langsung/API kurir belum dihubungkan.
- Seluruh pemesanan/status tetap simulasi local/testing; pembayaran Midtrans UMKM dan batas waktu pembayaran belum dibuat pada fase ini.
- Migrasi additive diterapkan. Verifikasi: 27 tes/207 assertions pada SQLite dan MySQL; tambahan pembatalan delivery dan concurrency menghasilkan 7 tes pengiriman/67 assertions SQLite dan 9 tes pengiriman+concurrency/77 assertions MySQL. ESLint, build Webpack 29 routes, dan diff whitespace lulus.
- UAT browser: produk sintetis Souvenir Lokal Pilot diaktifkan untuk pengiriman dengan ongkir Rp15.000; order demo 04eff4f3-1257-4c17-aa6e-f4c3189ad82b dibuat (1 produk Rp35.000 + ongkir Rp15.000 = Rp50.000). Alamat, telepon, kode pos, kurir, dan resi DEMO-UAT-UMKM-001 semuanya sintetis; status dikirim tersimpan tanpa pengiriman nyata. Produk demo tetap aktif untuk mencoba fitur; pesanan uji tersimpan sebagai bukti UAT.
- UAT akun pemesan berhasil: status Dikirim, total berongkir, alamat, kurir, dan resi demo tampil di `/akun/umkm`; tombol pembatalan tidak tampil setelah dikirim. Bukti screenshot disimpan di visualizations/umkm-shipping.

## Pendaftaran mitra — 3 Oktober 2026

Pendaftaran mandiri diaktifkan (`PARTNER_SELF_REGISTRATION=true`). Halaman `/daftar-mitra` mensyaratkan login dan email terverifikasi, menyimpan usaha draft dan keanggotaan owner belum aktif secara atomik, serta menampilkan status dan catatan keputusan. Pengajuan ganda per akun ditolak. Admin meninjau melalui `/dashboard/pendaftaran-mitra`; keputusan memerlukan admin terverifikasi dan konfirmasi kata sandi. Persetujuan mengaktifkan owner, penolakan mempertahankan akses belum aktif; keputusan dan pengajuan diaudit. Tidak mengubah usaha atau keanggotaan lama.

Validasi: PartnerApplicationTest 5 tes/46 asersi lulus di SQLite dan MySQL terisolasi; bersama AuthenticationTest dan SecurityBoundaryTest 14 tes/76 asersi lulus. Pint, lint, dan build Webpack lulus; formulir login pengunjung terverifikasi dan jalur tamu diperiksa di browser. Tidak mengirim pengajuan atau menyetujui usaha pada database aplikasi saat pemeriksaan browser.

## Midtrans sandbox seluruh modul — 3 Oktober 2026

Sesuai instruksi terbaru pengguna, penundaan Snap dicabut. UMKM, penginapan, dan kuliner sekarang memiliki checkout Midtrans sandbox melalui pembayaran reservasi bersama; tiket dan paket tetap memakai integrasi Midtrans yang sudah ada. Total berasal dari snapshot server, termasuk ongkir UMKM. Checkout berulang memakai sesi yang sama; kegagalan jaringan tidak membuat charge baru secara otomatis. Status pembayaran terpisah dari status pemenuhan pesanan.

Webhook memeriksa signature, nominal, referensi, dan status otoritatif Midtrans. Pemeriksaan manual pada akun dan rekonsiliasi scheduler setiap menit memperbarui status. Pembatalan pembayaran harus dikonfirmasi Midtrans sebelum stok/kuota dilepas, tepat sekali. Pembayaran terlambat setelah pembatalan dan reversal masuk pengecualian untuk ditinjau admin. Pembayaran yang sudah dimulai harus berstatus paid sebelum pengelola melanjutkan pemenuhan; refund belum dibuat. Migrasi additive reservation_payments diterapkan; token tersimpan terenkripsi dan server key tidak dikirim ke frontend.

Validasi: ReservationPaymentTest 12 tes/92 asersi lulus SQLite; regresi reservasi/UMKM 74 tes/440 asersi dan MidtransSandboxTest 22 tes/69 asersi lulus MySQL terisolasi. Concurrency dijalankan terpisah: 3 tes/15 asersi termasuk checkout paralel dan stok UMKM. Pint, ESLint, dan build Webpack 31 halaman lulus. Perbaikan reorder pada agregasi rekonsiliasi memastikan kompatibilitas ONLY_FULL_GROUP_BY MySQL.

UAT browser memakai akun demo pengguna tanpa mengubah kredensial. UMKM Souvenir Lokal Pilot Rp35.000 berhasil dibayar melalui simulator BCA Midtrans, lalu terverifikasi paid pada akun melalui pemeriksaan status. Penginapan Rp150.000 dan kuliner Rp75.000 berhasil membuka halaman Snap sandbox; kedua sesi dibatalkan melalui Midtrans dan stok/kuota kembali. Semua data pesanan uji sintetis. Bukti di visualizations/2026/10/03/midtrans-semua-modul. Pengiriman webhook melalui ngrok belum diuji ulang pada UAT ini; mode production belum diaktifkan.

## Input destinasi dan komposisi paket wisata — 3 Oktober 2026

Menu `/dashboard/destinasi` dan `/dashboard/paket` tersedia untuk admin terverifikasi serta owner/manager aktif dari mitra approved. Destinasi dapat dibuat/edit dengan wilayah, kategori, ringkasan, deskripsi, alamat, koordinat opsional, serta status draft/terbit. Kepemilikan dan slug ditentukan server; revision mencegah penimpaan perubahan terbaru. Setiap penyimpanan diaudit.

Paket memakai Product/TourPackage/PackageItineraryItem yang sudah ada, dengan migrasi additive untuk alamat destinasi, deskripsi paket, referensi kuliner/UMKM, jumlah per peserta, cakupan harga, dan estimasi biaya tambahan. Minimal satu destinasi; banyak destinasi, kuliner dan UMKM opsional. Aktivitas memiliki hari, jam WIB, durasi menit, dan deskripsi. Jadwal bertabrakan ditolak saat publikasi, aktivitas melewati tengah malam ditolak, dan tempat/produk harus tersedia pada katalog. Pilihan dikunci dan diperiksa lagi saat menyimpan. Harga dasar ditetapkan per orang untuk paket baru; kebijakan harga/keberangkatan paket lama dipertahankan. Biaya opsional ditampilkan terpisah, tidak otomatis ditambahkan ke checkout. Produk/kuliner termasuk merupakan layanan penyelenggara paket; komposisi katalog tidak membuat pesanan UMKM/meja terpisah atau mengurangi stok.

Katalog `/paket` mengarah ke detail `/paket/{slug}` sebelum checkout, menampilkan urutan kunjungan per hari/jam, durasi, produk yang didapat, titik kumpul, transportasi/pemandu, harga dan cakupan biaya. Paket lintas desa yang mempunyai konfigurasi tersendiri tidak dapat ditimpa lewat editor umum. Jadwal keberangkatan/kuota pemesanan tetap memakai mekanisme inventori yang sudah ada; form komposisi ini tidak mengisi kuota otomatis.

Validasi: 21 tes/112 asersi (pengelolaan katalog, destinasi, publikasi paket, katalog produk, keberangkatan) lulus SQLite dan MySQL terisolasi. Pint, ESLint, build Webpack 33 halaman dan diff whitespace lulus. Migrasi diterapkan pada database lokal tanpa mengubah data lama.

UAT browser: pengunjung ditolak, admin demo berhasil masuk menggunakan kredensial pengguna tanpa perubahan password. Destinasi sintetis `destinasi-dfe0d649-0ef1-46fb-b106-59ac23780330` berhasil disimpan dan dipilih pada paket `paket-fe5a21e7-d8b7-4992-bc0b-ee78ecfdb6ad`. Paket demo Rp250.000/orang, satu hari, memuat destinasi 09:00, kuliner 12:00, dan dua souvenir per peserta 14:00 (masing-masing 60 menit), tanpa transaksi atau pemesanan nyata. Halaman detail dan cakupan biaya diverifikasi; bukti visual di visualizations/2026/10/03/paket-itinerary/detail-paket.png. Data berlabel demo dipertahankan sebagai bukti uji.

## Kalender keberangkatan dan pemesanan paket — 3 Oktober 2026

Tombol Jadwal & kuota pada `/dashboard/paket` membuka pengaturan rentang tanggal (maksimal 90 tanggal, horizon tiga bulan WIB), kapasitas per tanggal dan status buka/tutup. Memakai InventoryBucket default yang sama dengan checkout, tanpa tabel kuota duplikat. Lock product/bucket dan revision melindungi edit bersamaan dengan pemesanan. Kapasitas tidak dapat lebih kecil dari held + confirmed; nilai held/confirmed tidak dapat dikirim dari form. Penutupan menghentikan pesanan baru tanpa membatalkan pesanan yang sudah ada. Hak akses memakai admin terverifikasi atau owner/manager aktif mitra approved; penyimpanan diaudit.

Halaman detail paket menampilkan tanggal keberangkatan dan jumlah peserta, memeriksa ketersediaan dan harga dari server, kemudian meneruskan pilihan ke checkout. Backend checkout paket memeriksa status terbit, batas peserta dan tanggal tidak lampau dalam WIB. Kalender publik memakai perbandingan tanggal agar baris tanggal akhir ikut terbaca di SQLite maupun MySQL.

Validasi: regresi kalender/inventori/checkout/autentikasi/lintas desa 41 tes/243 asersi lulus SQLite dan MySQL terisolasi; setelah perbaikan pembacaan tanggal, lima tes kalender/36 asersi kembali lulus MySQL. Pint, ESLint, build Webpack 33 halaman dan diff whitespace lulus. Tidak ada migrasi atau perubahan dependency.

UAT browser: admin demo membuka paket demonstrasi dari fase sebelumnya, menginisialisasi 10 kuota untuk 4 Oktober 2026. Detail paket menampilkan tersedia 10 kuota dan total Rp500.000 untuk dua peserta. Link pemesanan mengisi produk, tanggal 2026-10-04, dan quantity 2 pada checkout; tidak mengirim pesanan/pembayaran baru saat UAT ini. Data kuota demo dipertahankan untuk percobaan pengguna. Bukti visual di visualizations/2026/10/03/paket-calendar/kalender-admin.png dan ketersediaan-paket.png.

## Kartu destinasi beranda — 3 Oktober 2026

Beranda mengambil maksimal empat destinasi terbit dari katalog (`per_page=4`, ditambah pembatasan empat kartu pada frontend). Kartu memakai nama/wilayah/kategori aktual dan seluruh tautan gambar, judul, serta Lihat detail mengarah ke `/destinasi/{slug}`. Empat kartu inspirasi statis yang sebelumnya membuka pencarian dihapus. Tombol Jelajahi destinasi tetap mengarah ke katalog lengkap `/destinasi`. Jika data terbit kurang dari empat, hanya data yang tersedia ditampilkan; gambar tetap berlabel ilustrasi. Loading, kegagalan dengan retry dan kondisi kosong ditangani.

Validasi: ESLint dan build Webpack lulus. Browser memverifikasi dua destinasi yang saat ini terbit, tautan katalog lengkap, dan klik gambar kartu membuka detail Destinasi Pilot Demonstrasi. Tidak menambah data destinasi demo baru untuk mengisi jumlah empat. Bukti visual: visualizations/2026/10/03/home-destinasi/kartu-destinasi.png.

## Seeder 10 destinasi demo — 3 Oktober 2026

Seeder khusus `DestinationDemoSeeder` menambahkan sepuluh destinasi terbit dengan nama, deskripsi, alamat contoh dan kategori alam/budaya. Hanya berjalan pada local/testing. Menggunakan slug stabil dan mempertahankan data, perubahan pengelola serta destinasi yang dihapus saat dijalankan ulang. Memakai mitra pilot yang sudah disetujui jika tersedia; tidak mengubah akun atau kata sandi.

Seeder dijalankan pada aplikasi lokal: pertama menambah 10 data, pengulangan menambah 0. Total katalog kini 12 destinasi; browser memverifikasi empat kartu di beranda dan seluruh 12 pada Jelajah destinasi. Dua tes/23 asersi lulus SQLite dan MySQL terisolasi; Pint dan pemeriksaan whitespace lulus. Bukti visual: visualizations/2026/10/03/destinasi-seeder/katalog.png.


## Galeri, paket demo, lokasi, refund, ekspedisi dan kesiapan produksi — 3 Oktober 2026

Galeri destinasi/paket dikelola admin atau mitra berwenang, maksimal 12 foto JPG/PNG/WebP masing-masing 5 MB, dengan keterangan dan penanda ilustrasi. Foto pertama menjadi sampul katalog; detail menampilkan galeri dan viewer. Penyimpanan privat, foto draft tidak dipublikasikan, revisi mencegah perubahan bersamaan, dan audit tercatat. Katalog destinasi tetap memenuhi batas dua query dengan subquery sampul. Penghapusan menghapus baris galeri dan akses publik; berkas tetap tersimpan privat untuk penanganan operasional, belum ada pemulihan galeri atau pembersihan berkas otomatis.

Seeder khusus TravelPackageDemoSeeder menambah 12 paket sintetis untuk 10 destinasi demo, termasuk tiga paket Air Terjun Embun, serta kuota 10 peserta selama tujuh hari mulai besok. Pengulangan menambah nol dan mempertahankan perubahan/data lama. Detail destinasi mengarahkan Lihat paket ke seluruh paket terbit yang memakai destinasi tersebut, termasuk kunjungan sekunder; tanpa hasil tampil Paket belum ada. Browser memverifikasi tiga pilihan serta paket keluarga dengan kuota 10 dan total Rp150.000/1 peserta pada 4 Oktober, tanpa membuat pesanan.

Lokasi destinasi memakai PlaceLocation yang mendukung pencarian Maps dari nama/alamat atau koordinat. location_is_demo menahan tautan Maps untuk alamat sintetis; alamat/pin dan foto asli masih menunggu pengguna.

Pengajuan refund tersedia untuk tiket/paket dan UMKM/penginapan/kuliner, ditinjau admin dengan konfirmasi kata sandi. Adapter Midtrans memakai refund_key stabil, mencatat pengiriman sebelum POST, dan hanya menyelesaikan refund berdasarkan nominal tepat serta bukti konfirmasi bank dari status provider. Respons pengajuan tidak dianggap berhasil dan pemeriksaan ulang tidak mengirim refund kedua. Stok/kuota dikembalikan sekali setelah terverifikasi; fulfillment, voucher dan pemilihan payout ditahan selama refund aktif. Rekonsiliasi terjadwal tersedia. Konfigurasi lokal diarahkan REFUND_DRIVER=midtrans_sandbox dan MIDTRANS_REFUNDS_ENABLED=true; tidak menjalankan refund aktual. Kelayakan metode/akun merchant dan uji refund sandbox nyata masih diperlukan. Refund reservasi dicatat pada tabel/audit reservasi, belum masuk jurnal Order lama.

Adapter Biteship menyediakan ongkir dari kode pos asal/tujuan, berat, jumlah dan nilai produk di server; quote sepuluh menit melindungi harga. Pelacakan memakai ID tracking provider, memverifikasi ID/resi/kurir, membatasi data yang ditampilkan, dan tidak mengubah pesanan otomatis menjadi selesai. Booking kurir/pickup belum otomatis: pengelola membuat pengiriman di layanan ekspedisi lalu mengisi resi dan ID tracking. Provider tetap nonaktif sampai pengguna memilih ekspedisi dan memasukkan kredensial di server; pengiriman manual tetap tersedia.

Gateway Midtrans produksi memakai key, host dan flag terpisah; pemesanan produksi memerlukan aktivasi eksplisit. Menu admin Kesiapan produksi dan perintah app:production-readiness (--strict) memeriksa konfigurasi tanpa membocorkan rahasia. URL publik/mail Compose dapat diatur; worker/scheduler menggunakan cache Redis yang sama, heartbeat terverifikasi. Script deployment mensyaratkan HTTPS dan secure cookie. Docker backend disesuaikan ke PHP8.5 agar sesuai Laravel13; image baru belum dibangun pada fase ini. Produksi belum diaktifkan/deploy: domain/TLS, key live, SMTP, konten asli, uji webhook dan pemulihan backup masih diperlukan; runtime HTTP pada Compose masih artisan serve dan perlu diganti runtime produksi sebelum peluncuran.

Empat migrasi additive diterapkan di database lokal dan seeder khusus dijalankan dua kali (12 lalu 0). Tidak mereset akun/kata sandi atau menimpa paket lama. Validasi akhir terfokus: 246 tes/1.528 asersi SQLite dan 246 tes/1.528 asersi MySQL terisolasi; 15 tes concurrency/103 asersi MySQL dijalankan terpisah. Tes yang memakai migration rollback dan concurrency tidak dicampur dengan suite RefreshDatabase MySQL. Pint, ESLint, build Webpack 35 halaman, Compose config, syntax deployment dan diff whitespace lulus. Browser memverifikasi katalog/detail/kuota, form galeri admin, laporan kesiapan dan daftar refund kosong tanpa menyetujui transaksi. Bukti visual: /Users/gadstudio/.codex/visualizations/2026/10/03/lanjut-semua/.


## Pemeriksaan kredensial layanan — 4 Oktober 2026

Perintah app:services-status menampilkan kelengkapan konfigurasi Midtrans sesuai mode, webhook HTTPS, kecocokan refund/payment driver, Biteship dan SMTP eksternal. Nilai kunci, password, akun, host dan alamat pengirim tidak ditampilkan; perintah tidak menghubungi provider atau mengirim email. Timeout SMTP dapat diatur melalui MAIL_TIMEOUT (default 15 detik). Kredensial rahasia berada di backend/.env; Compose mengambil override URL publik dan MAIL_HOST/MAIL_PORT dari .env root. Aktivasi produksi/ekspedisi belum dilakukan karena domain, key live, key Biteship dan akun SMTP belum diberikan pengguna.

Validasi: ServiceStatusTest 2 tes/13 asersi lulus SQLite; Pint pada file yang diubah dan diff whitespace lulus. Pemeriksaan aktual menunjukkan Midtrans sandbox/refund terisi, webhook/Biteship/SMTP eksternal belum lengkap. Placeholder yang belum ada ditambahkan tanpa mengubah kredensial lama; config cache dibersihkan dan worker/scheduler dimulai ulang.
