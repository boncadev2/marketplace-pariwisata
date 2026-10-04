# Keputusan dan Hal yang Menunggu

| ID | Keputusan | Status | Pemilik yang Dibutuhkan | Dampak |
| --- | --- | --- | --- | --- |
| D-001 | Nama produk dan kabupaten pilot | Belum diputuskan | Pemilik produk/pemerintah daerah | Merek, konten, dan konfigurasi wilayah |
| D-002 | Badan pengelola platform dan penanggung jawab dukungan | Belum diputuskan | Sponsor bisnis | Kepatuhan dan SOP layanan |
| D-003 | Mitra pertama serta pemilik konten | Belum diputuskan | Pemilik produk | Data pilot dan moderasi |
| D-004 | Provider pembayaran dan model penyaluran dana | Belum diputuskan | Keuangan/legal | Aktivasi transaksi produksi dan settlement |
| D-005 | Komisi per produk, fee gateway, pajak, pembulatan, dan penanggung diskon | Belum diputuskan | Keuangan | Quote, ledger, dan laporan |
| D-006 | Kebijakan pembatalan/refund per produk | Belum diputuskan | Bisnis/operasional/legal | Pembatalan dan nominal refund |
| D-007 | Jam dukungan, SLA konfirmasi, periode settlement, minimum peserta, dan cuaca buruk | Belum diputuskan | Operasional | Status keberangkatan dan dukungan |
| D-008 | Sumber master wilayah yang disetujui | Belum diputuskan | Pemilik data/admin wilayah | Import Fase 07 |
| D-009 | Kebijakan dokumen verifikasi mitra dan retensi media privat | Belum diputuskan | Legal/operasional | Onboarding mitra |
| D-010 | Provider peta dan anggaran kuota | Belum diputuskan | Pemilik produk/teknis | Aktivasi peta berbayar |
| D-011 | Kebijakan privasi, dasar pemrosesan, retensi, dan pengecualian penghapusan catatan transaksi | Belum disetujui | Legal/DPO dan pemilik bisnis | Pilot data nyata dan pemrosesan permintaan penghapusan |
| D-012 | Roster operator produksi, MFA/SSO, siklus review akses, secret manager, dan periode rotasi | Belum disetujui | Security/operasional | Akses admin, payout nyata, dan respons insiden |

## Keputusan Teknis Sementara

- T-001: Nama kerja aplikasi adalah Wisata Daerah sampai D-001 selesai.
- T-002: Pembayaran produksi tetap nonaktif; fase transaksi menggunakan adapter palsu atau sandbox hingga D-004–D-007 selesai.
- T-003: Data contoh hanya demonstrasi dan tidak menggunakan identitas pribadi nyata.
- T-004: Frontend web menggunakan Next.js dengan JavaScript.
- T-005: Framework aplikasi mobile ditetapkan menggunakan React Native dengan Expo.
