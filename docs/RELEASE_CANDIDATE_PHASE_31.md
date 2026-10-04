# Release Candidate Fase 31

Candidate: `RC-2026-09-30-01-local`  
Keputusan: **HOLD**  
Target: staging setelah seluruh blocker ditutup

## Gate yang lulus

- Suite backend lulus tanpa kegagalan.
- Concurrency inventori, redeem voucher, dan race hold/payment lulus pada MySQL.
- Frontend lint dan production build lulus.
- Checkout dan payment attempt idempoten.
- Webhook tidak dapat menurunkan status paid atau membuat efek finansial ganda.
- Refund duplikat tidak menggandakan nilai, panggilan provider, atau jurnal.
- Isolasi pengguna dan tenant memiliki cakupan test otomatis.

## Blocker release

1. Integrasi payout provider dan skenario timeout/retry T13 belum tersedia.
2. UAT staging lima peran dan simulasi operator tanpa developer belum ditandatangani.
3. Skenario lodging multi-malam T16 dan kupon terakhir T17 belum dapat dijalankan karena workflow belum lengkap.
4. Candidate lokal belum dibekukan menjadi commit/tag immutable.

## Syarat keputusan GO

- Seluruh defect Critical pada `UAT_CHECKLIST.md` berstatus ditutup dan diretest.
- T13 memiliki bukti eksekusi aktual; T18 sudah dibuktikan melalui restore terisolasi Fase 32.
- T16 dan T17 diimplementasikan serta lulus concurrency test, atau secara tertulis dikeluarkan dari scope release oleh product owner.
- UAT staging ditandatangani product owner, operator pilot, finance, dan engineering.
- Hasil ledger internal direkonsiliasi terhadap provider tanpa selisih yang belum dijelaskan.
- Commit release dibekukan, CI lulus, lalu tag release dibuat.

Dokumen ini tidak menyatakan aplikasi siap production. Candidate hanya layak diteruskan ke pengujian staging setelah blocker teknis yang relevan selesai.
