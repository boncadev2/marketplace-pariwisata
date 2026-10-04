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
| T13 | Timeout payout lalu retry | BELUM DIJALANKAN | Integrasi provider payout belum tersedia. Endpoint sengaja fail-closed dengan HTTP 503. |
| T14 | Worker berhenti setelah payment | LULUS otomatis | Durable outbox, status attempt, dan job rekonsiliasi memulihkan pekerjaan tanpa menggandakan efek finansial. |
| T15 | Menebak ID order milik pengguna lain | LULUS | `SecurityBoundaryTest` menolak akses lintas pemilik/tenant. |
| T16 | Reservasi penginapan multi-malam secara atomik | LULUS simulasi lokal | `LodgingReservationTest` dan race MySQL membuktikan semua malam atomik, satu reservasi untuk retry, serta pemulihan stok sekali. Pembayaran dan layanan nyata belum diintegrasikan. |
| T17 | Perebutan kupon terakhir | LULUS sandbox lokal | Race MySQL dua pengguna menghasilkan satu order/penukaran/hold untuk kuota terakhir; retry serentak hanya memakai satu kuota. Lifecycle promo produksi belum disetujui. |
| T18 | Pemulihan backup | LULUS lokal | Dump database dan arsip media diverifikasi checksum; database dipulihkan ke `wisata_restore_drill` dengan 61 tabel/64 migrasi dalam 3 detik, lalu target latihan dibersihkan. |

Ringkasan otomatis: 17 skenario lulus (termasuk T16–T17 simulasi lokal/sandbox) dan 1 belum dijalankan (T13 provider payout). Skenario yang belum dijalankan tidak dianggap lulus.

## UAT staging per peran

Bagian berikut harus dijalankan di staging dengan data pilot dan dicentang oleh operator.

### Tamu

- [ ] Menelusuri destinasi dan produk tanpa login.
- [ ] Membuat checkout tamu dan menerima instruksi pembayaran.
- [ ] Kembali dari halaman pembayaran tanpa webhook; status tetap pending.
- [ ] Menerima voucher hanya setelah pembayaran terverifikasi.

### Pengguna berakun

- [ ] Registrasi, login, logout, dan pemulihan akses berjalan.
- [ ] Pesanan hanya terlihat oleh pemiliknya.
- [ ] Voucher dan riwayat transaksi menampilkan nilai yang konsisten.
- [ ] Permintaan refund tidak dapat melebihi nilai pembayaran.

### Partner

- [ ] Partner hanya melihat produk, pesanan, ledger, dan payout miliknya.
- [ ] Perubahan katalog menghasilkan audit trail.
- [ ] Dashboard operasional sama dengan data transaksi sumber.
- [ ] Rekening payout membutuhkan alur konfirmasi keamanan.

### Staf lapangan

- [ ] QR valid dapat diredeem satu kali.
- [ ] Scan bersamaan tidak menghasilkan redeem ganda.
- [ ] Voucher tidak valid, kedaluwarsa, atau lintas partner ditolak.
- [ ] SOP manual tersedia ketika jaringan atau worker terganggu.

### Admin/finance

- [ ] Rekonsiliasi menampilkan selisih dan alert yang dapat ditindaklanjuti.
- [ ] Refund approval menghasilkan satu panggilan provider dan satu jurnal.
- [ ] Payout provider lolos timeout/retry tanpa duplikasi.
- [ ] Backup dapat direstore dan checksum/data utama terverifikasi.

## Register temuan Fase 31

| ID | Severity | Temuan | Status |
| --- | --- | --- | --- |
| D31-001 | Critical | Idempotency key menerima payload berbeda. | DITUTUP — sekarang HTTP 409 dan diuji. |
| D31-002 | Critical | Checkout belum membuat payment attempt/instruction URL. | DITUTUP — attempt dibuat idempoten dan URL dikembalikan. |
| D31-003 | Critical | Timeout create payment berisiko membuat transaksi provider ganda. | DITUTUP — lookup wajib dilakukan sebelum retry. |
| D31-004 | Critical | Adapter/provider payout belum tersedia. | TERBUKA — memblokir release. |
| D31-005 | Critical | Uji restore backup belum dijalankan. | DITUTUP — restore terisolasi Fase 32 berhasil dan data latihan dibersihkan. |
| D31-006 | High | UAT lima peran dan simulasi operator staging belum dijalankan. | TERBUKA — memblokir release. |

## Persetujuan

- Product owner: ____________________ tanggal: __________
- Operator pilot: ___________________ tanggal: __________
- Finance: __________________________ tanggal: __________
- Engineering: ______________________ tanggal: __________

Keputusan saat ini: **HOLD**. Release candidate belum boleh dipromosikan sampai seluruh temuan Critical ditutup dan UAT staging ditandatangani.
