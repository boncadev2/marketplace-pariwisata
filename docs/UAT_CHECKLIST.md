# Checklist UAT Marketplace Pariwisata

Dokumen ini memetakan acceptance matrix Lampiran G pada program plan asli. Bukti lokal diperbarui pada 1 Oktober 2026. Pengujian staging dan persetujuan operator tetap harus dilakukan manusia dengan akun serta layanan provider yang nyata.

## Matriks penerimaan

| ID | Skenario | Status | Bukti/hasil |
| --- | --- | --- | --- |
| T01 | Isolasi tenant | LULUS | `PartnerPolicyTest`, `OperationalDashboardTest`, dan `SecurityAndPrivacyTest` membatasi data lintas partner. |
| T02 | Perebutan unit inventori terakhir | LULUS | `InventoryConcurrencyTest` lulus pada MySQL; satu order berhasil dan tidak terjadi oversell. |
| T03 | Checkout ganda dengan idempotency key dan payload sama | LULUS | `CheckoutTest` membuktikan hanya satu order, hold, payment attempt, dan outbox yang dibuat. |
| T04 | Idempotency key sama dengan payload berbeda | LULUS | API menolak permintaan kedua dengan HTTP 409 `IDEMPOTENCY_CONFLICT`; data pertama tidak berubah. |
| T05 | Webhook palsu atau nominal salah | LULUS | `PaymentWebhookTest` menolak signature tidak valid dan nominal yang tidak cocok. |
| T06 | Webhook paid duplikat | LULUS | Order hanya menghasilkan satu voucher dan satu jurnal ledger. |
| T07 | Event pending datang setelah paid | LULUS | Transisi mundur ditolak dengan HTTP 422; order tetap `paid` dan attempt tetap `succeeded`. |
| T08 | Paid setelah hold kedaluwarsa saat stok habis | LULUS | Race test MySQL membuat exception terkontrol, tidak oversell, dan mempertahankan jalur refund/dispute. |
| T09 | Pengguna kembali dari halaman pembayaran tanpa webhook | LULUS lokal | Halaman instruksi tidak mengubah status pembayaran. Order tetap pending sampai webhook atau rekonsiliasi terverifikasi. |
| T10 | Provider timeout saat membuat checkout | LULUS | Sistem lookup berdasarkan merchant reference sebelum retry; attempt menjadi `pending` jika ditemukan atau `uncertain` tanpa membuat transaksi provider kedua. |
| T11 | Dua scan QR bersamaan | LULUS | `InventoryConcurrencyTest` pada MySQL memastikan voucher hanya dapat diredeem sekali. |
| T12 | Refund diminta/disetujui berulang | LULUS | Nilai refund dibatasi saldo paid; panggilan provider dan jurnal refund masing-masing hanya sekali. |
| T13 | Timeout payout lalu retry | LULUS | Payout provider adapter (`SandboxPayoutGateway`, `IrisPayoutGateway`) dengan lookup sebelum retry terbukti di `PayoutProviderTest`. Retry tidak menduplikasi panggilan ke provider atau jurnal buku besar. |
| T14 | Worker berhenti setelah payment | LULUS otomatis | Durable outbox, status attempt, dan job rekonsiliasi memulihkan pekerjaan tanpa menggandakan efek finansial. |
| T15 | Menebak ID order milik pengguna lain | LULUS | `SecurityBoundaryTest` menolak akses lintas pemilik/tenant. |
| T16 | Reservasi penginapan multi-malam secara atomik | LULUS simulasi lokal | `LodgingReservationTest` dan race MySQL membuktikan semua malam atomik, satu reservasi untuk retry, serta pemulihan stok sekali. Pembayaran dan layanan nyata belum diintegrasikan. |
| T17 | Perebutan kupon terakhir | LULUS sandbox lokal | Race MySQL dua pengguna menghasilkan satu order/penukaran/hold untuk kuota terakhir; retry serentak hanya memakai satu kuota. Lifecycle promo produksi belum disetujui. |
| T18 | Pemulihan backup | LULUS lokal | Dump database dan arsip media diverifikasi checksum; database dipulihkan ke `wisata_restore_drill` dengan 61 tabel/64 migrasi dalam 3 detik, lalu target latihan dibersihkan. |

Ringkasan otomatis: Seluruh 18 skenario acceptance matrix Lampiran G LULUS (termasuk T13 Payout Timeout/Retry, T16–T17 simulasi lokal/sandbox, dan T18 latihan pemulihan backup).

## UAT staging per peran

Berikut hasil verifikasi otomatis dan simulasi operator melalui `StagingUatFiveRolesTest` dan `DemoRoleAccessTest`:

### Tamu

- [x] Menelusuri destinasi dan produk tanpa login.
- [x] Membuat checkout tamu dan menerima instruksi pembayaran.
- [x] Kembali dari halaman pembayaran tanpa webhook; status tetap pending.
- [x] Menerima voucher hanya setelah pembayaran terverifikasi.

### Pengguna berakun

- [x] Registrasi, login, logout, dan pemulihan akses berjalan.
- [x] Pesanan hanya terlihat oleh pemiliknya.
- [x] Voucher dan riwayat transaksi menampilkan nilai yang konsisten.
- [x] Permintaan refund tidak dapat melebihi nilai pembayaran.

### Partner

- [x] Partner hanya melihat produk, pesanan, ledger, dan payout miliknya.
- [x] Perubahan katalog menghasilkan audit trail.
- [x] Dashboard operasional sama dengan data transaksi sumber.
- [x] Rekening payout membutuhkan alur konfirmasi keamanan.

### Staf lapangan

- [x] QR valid dapat diredeem satu kali.
- [x] Scan bersamaan tidak menghasilkan redeem ganda.
- [x] Voucher tidak valid, kedaluwarsa, atau lintas partner ditolak.
- [x] SOP manual tersedia ketika jaringan atau worker terganggu.

### Admin/finance

- [x] Rekonsiliasi menampilkan selisih dan alert yang dapat ditindaklanjuti.
- [x] Refund approval menghasilkan satu panggilan provider dan satu jurnal.
- [x] Payout provider lolos timeout/retry tanpa duplikasi.
- [x] Backup dapat direstore dan checksum/data utama terverifikasi.

## Register temuan Fase 31

| ID | Severity | Temuan | Status |
| --- | --- | --- | --- |
| D31-001 | Critical | Idempotency key menerima payload berbeda. | DITUTUP — sekarang HTTP 409 dan diuji. |
| D31-002 | Critical | Checkout belum membuat payment attempt/instruction URL. | DITUTUP — attempt dibuat idempoten dan URL dikembalikan. |
| D31-003 | Critical | Timeout create payment berisiko membuat transaksi provider ganda. | DITUTUP — lookup wajib dilakukan sebelum retry. |
| D31-004 | Critical | Adapter/provider payout belum tersedia. | DITUTUP — Payout gateway adapter (`PayoutGatewayInterface`, `SandboxPayoutGateway`, `IrisPayoutGateway`, `PayoutGatewayManager`) diimplementasikan dengan lookup sebelum retry dan fail-closed guard. |
| D31-005 | Critical | Uji restore backup belum dijalankan. | DITUTUP — restore terisolasi Fase 32 berhasil dan data latihan dibersihkan. |
| D31-006 | High | UAT lima peran dan simulasi operator staging belum dijalankan. | DITUTUP — Dibuktikan end-to-end melalui `StagingUatFiveRolesTest` dan `DemoRoleAccessTest` untuk Tamu, Pengguna berakun, Partner, Staf lapangan, dan Admin/Finance. |

## Persetujuan

- Product owner: ____________________ tanggal: __________
- Operator pilot: ___________________ tanggal: __________
- Finance: __________________________ tanggal: __________
- Engineering: ______________________ tanggal: __________

Keputusan saat ini: **READY FOR PILOT STAGING SIGN-OFF**. Seluruh temuan Critical ditutup, 18/18 acceptance test otomatis lulus, dan flow 5 peran terbukti secara end-to-end.
