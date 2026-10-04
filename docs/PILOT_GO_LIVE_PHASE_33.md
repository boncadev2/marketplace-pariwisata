# Checklist Go Live Pilot Fase 33

Tanggal evaluasi: 30 September 2026  
Keputusan saat ini: **NO GO untuk transaksi nyata**  
Ruang aman saat ini: simulasi lokal atau staging dengan payment sandbox dan data sintetis

## Ringkasan keputusan

Fondasi teknis untuk latihan pilot tersedia, termasuk data demo yang aman, dashboard pemantauan, rekonsiliasi, SOP insiden, dan kontrol server-side untuk menutup checkout. Pilot nyata belum boleh diluncurkan karena kabupaten, mitra, konten berizin, pemilik dukungan, kebijakan bisnis, gateway produksi, serta persetujuan legal belum ditetapkan pada `DECISIONS.md`.

Tidak ada transaksi nyata atau transaksi verifikasi bernilai kecil yang dilakukan dalam fase ini.

## Gate konten dan mitra

- [ ] Kabupaten pilot dan nama produk disetujui tertulis.
- [ ] Badan pengelola dan penanggung jawab dukungan ditetapkan.
- [ ] Mitra pilot telah diverifikasi dan menyetujui penayangan konten.
- [ ] Setiap foto, deskripsi, titik lokasi, kontak, dan jam operasional memiliki izin serta pemilik data.
- [ ] Harga, pajak, fee, komisi, pembulatan, kuota, dan tanggal tersedia dikonfirmasi mitra serta finance.
- [ ] Kebijakan pembatalan, refund, cuaca buruk, minimum peserta, dan perubahan jadwal disetujui.
- [ ] Konten nyata telah melalui pemeriksaan empat mata dan tidak berasal dari seeder demo.

## Gate operasi dan pelatihan

- [ ] Admin dapat memoderasi katalog dan melihat audit log.
- [ ] Pemilik mitra hanya dapat melihat data tenant sendiri.
- [ ] Petugas dapat redeem satu voucher dan menolak scan kedua.
- [ ] Tim dukungan menyelesaikan simulasi pembelian, check-in, pembatalan, dan refund tanpa bantuan developer.
- [ ] Operator dapat menutup checkout melalui dashboard dan membuktikan request baru mendapat `CHECKOUT_CLOSED`.
- [ ] Jadwal piket, kanal eskalasi, jam layanan, dan pemilik keputusan tersedia.
- [ ] UAT lima peran pada `UAT_CHECKLIST.md` ditandatangani.

## Gate pembayaran dan keuangan

- [ ] Provider pembayaran dan model aliran dana disetujui legal/finance.
- [ ] Credential production tersimpan di secret manager dan terpisah dari sandbox.
- [ ] Webhook production memakai signature, allowlist/rate limit, dan endpoint HTTPS.
- [ ] Refund serta payout provider lulus timeout, retry, lookup, dan idempotency test.
- [ ] Transaksi verifikasi bernilai kecil memiliki otorisasi tertulis dari pemilik dana.
- [ ] Payment, ledger, refund, fee, dan settlement direkonsiliasi tanpa selisih terbuka.
- [ ] Tombol checkout tetap tertutup sampai seluruh gate sebelumnya lulus.
- [ ] `PILOT_CHECKOUT_ENABLED` tetap `false` pada deploy awal dan pembukaan pertama dilakukan melalui kontrol admin yang diaudit.

## Gate keamanan dan deployment

- [ ] Staging dan production memiliki domain, HTTPS, backup, restore drill, alarm, dan akses individual.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, cookie aman, origin CORS resmi, dan MFA/SSO operator aktif.
- [ ] Upload lampiran dinonaktifkan atau dipindai malware.
- [ ] Kebijakan privasi, retensi, penghapusan data, dan pengecualian transaksi disetujui legal/DPO.
- [ ] Tidak ada defect Critical terbuka pada release candidate.

## Aturan keputusan

Keputusan berubah menjadi GO hanya bila semua gate wajib dicentang, T13 payout provider lulus, UAT staging ditandatangani, dan tidak ada selisih transaksi terbuka. Masalah pembayaran, oversell, voucher ganda, refund ganda, atau selisih ledger otomatis mengubah keputusan menjadi HOLD dan checkout harus ditutup.

Persetujuan:

- Product owner: ____________________ tanggal: __________
- Operator pilot: ___________________ tanggal: __________
- Finance: __________________________ tanggal: __________
- Legal/DPO: ________________________ tanggal: __________
- Engineering/Security: _____________ tanggal: __________
