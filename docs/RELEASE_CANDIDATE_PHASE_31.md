# Release Candidate Fase 31

Candidate: `RC-2026-09-30-01-local`  
Keputusan: **HOLD**  
Target: staging setelah seluruh blocker ditutup

## Gate yang lulus

- Suite backend lulus tanpa kegagalan (510+ test passed, 3.000+ assertion).
- Concurrency inventori, redeem voucher, race hold/payment, lodging, kupon, dan UMKM 100% lulus pada MySQL terisolasi.
- Frontend lint dan production build lulus tanpa error (52 route Next.js).
- Checkout dan payment attempt idempoten.
- Webhook tidak dapat menurunkan status paid atau membuat efek finansial ganda.
- Refund duplikat tidak menggandakan nilai, panggilan provider, atau jurnal.
- Integrasi payout provider dan skenario timeout/retry T13 selesai diimplementasikan (`PayoutGatewayInterface`, `SandboxPayoutGateway`, `IrisPayoutGateway`).
- UAT lima peran (Tamu, Pengguna berakun, Mitra, Staf lapangan, Admin/Finance) diverifikasi otomatis via `StagingUatFiveRolesTest`.
- Latihan restore backup terverifikasi checksum dan dipulihkan secara aman (T18).

## Status Blocker Teknis

1. **Integrasi payout provider & timeout/retry T13**: SELESAI & LULUS (`PayoutProviderTest`).
2. **UAT lima peran otomatis**: SELESAI & LULUS (`StagingUatFiveRolesTest`).
3. **Skenario lodging T16 & kupon T17**: SELESAI & LULUS pada concurrency test MySQL.
4. **Verifikasi operator staging**: Menunggu tanda tangan fisik/staging dari tim operator pilot.

## Keputusan Promosi

Candidate teknis telah memenuhi seluruh kriteria kelulusan otomatis Lampiran G (18/18 LULUS). Siap untuk deployment pilot staging dan verifikasi manual operator.
