# Laporan Pengujian Fase 31

Tanggal pelaksanaan: 30 September 2026  
Lingkungan: lokal/container, bukan production  
Ruang lingkup: integrasi backend, concurrency MySQL, build frontend, dan smoke test browser

## Kesimpulan

Implementasi otomatis utama stabil, tetapi UAT Fase 31 belum dapat dinyatakan selesai sepenuhnya. Setelah latihan restore Fase 32, dari 18 skenario pada acceptance matrix Lampiran G, 15 lulus dan 3 belum dijalankan. Release tetap **HOLD** sampai payout provider dan UAT staging/operator selesai.

## Perbaikan yang dilakukan saat audit

1. Checkout kini menolak idempotency key yang dipakai ulang dengan payload berbeda menggunakan HTTP 409 `IDEMPOTENCY_CONFLICT`.
2. Checkout membuat satu payment attempt idempoten dan mengembalikan status serta URL instruksi pembayaran.
3. Timeout provider ditangani dengan lookup merchant reference sebelum retry sehingga tidak membuat charge kedua.
4. Event pending setelah paid tidak dapat menurunkan status transaksi.
5. Permintaan dan approval refund berulang tidak dapat menggandakan panggilan provider atau jurnal ledger.
6. Halaman instruksi pembayaran sandbox dibuat dalam JavaScript dan tidak memiliki aksi yang menandai order sebagai paid.

## Hasil aktual

| Pemeriksaan | Hasil |
| --- | --- |
| Seluruh test backend SQLite/in-memory | 138 lulus, 647 assertion, 3 test MySQL-only dilewati |
| Test concurrency pada MySQL terisolasi | 3 lulus, 19 assertion |
| ESLint frontend | Lulus |
| Production build frontend | Lulus; 19 route termasuk `/pembayaran/[reference]` |
| Smoke test browser halaman pembayaran | Lulus; heading/reference tampil, tanpa form mutasi, tanpa overflow horizontal, tanpa error console |
| Acceptance matrix Lampiran G | 15 lulus, 3 belum dijalankan setelah bukti restore Fase 32 |

Angka finansial konsisten pada fixture otomatis untuk checkout, paid webhook, voucher, ledger, dan refund. Konsistensi settlement terhadap provider nyata belum dibuktikan karena koneksi payment/payout production tidak termasuk dalam lingkungan lokal.

## Pengujian yang belum dijalankan

- T13: payout timeout dan retry terhadap provider nyata; adapter provider belum tersedia.
- T16: booking penginapan multi-malam atomik; alur transaksi belum tersedia.
- T17: perebutan kupon terakhir; layanan redemption atomik belum tersedia.
- UAT staging untuk tamu, pengguna berakun, partner, staf lapangan, dan admin/finance.
- Simulasi SOP operator tanpa bantuan developer.

## Perintah reproduksi

```bash
cd backend
php artisan test
vendor/bin/pint app/Exceptions/IdempotencyConflictException.php app/Http/Controllers/Api/CheckoutController.php app/Payments/PaymentGateway.php app/Payments/SandboxPaymentGateway.php app/Services/CheckoutService.php app/Services/PaymentAttemptService.php tests/Feature/CheckoutTest.php tests/Feature/PaymentAttemptTest.php tests/Feature/PaymentWebhookTest.php tests/Feature/RefundSafetyTest.php

cd ../frontend
npm run lint
npm run build
```

Test concurrency wajib dijalankan terhadap database MySQL terisolasi agar locking database yang sesungguhnya ikut teruji.
