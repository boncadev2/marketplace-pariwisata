# Evaluasi Kesiapan Pilot Fase 33

Tanggal: 30 September 2026  
Jenis evaluasi: kesiapan teknis lokal dan sandbox  
Hasil: **NO GO untuk pilot transaksi nyata**

## Yang sudah tersedia

- Seeder sintetis yang idempoten untuk satu kabupaten demo, satu mitra, satu destinasi, satu produk, 14 hari inventori, dan akun latihan berbasis password environment.
- Seeder dibatasi pada local/testing dan menutup checkout setelah data dibuat.
- Kontrol buka/tutup checkout berlaku di backend, memerlukan super admin serta konfirmasi kata sandi, dan menghasilkan audit log.
- Halaman checkout membaca status pilot dan fail-closed jika status tidak dapat diverifikasi.
- Dashboard menampilkan rasio order ke pembayaran, masalah pembayaran, transaction exception, keluhan/sengketa terbuka, dan selisih keuangan.
- SOP dukungan dan checklist go-live tersedia.

## Yang belum dapat divalidasi

- Konten nyata berizin, harga, tanggal, kebijakan, dan kontak penanggung jawab karena D-001 sampai D-007 masih belum diputuskan.
- Pelatihan manusia dan simulasi staging lima peran karena akun/operator serta staging eksternal belum tersedia.
- Gateway, refund, dan payout production karena provider serta credential belum disetujui.
- Conversion pengunjung ke checkout karena analytics consent dan event tracking belum tersedia. Dashboard hanya menampilkan rasio order yang selesai dibayar.
- Settlement provider nyata dan dana pilot karena tidak ada transaksi nyata yang diotorisasi.

## Backlog hasil evaluasi

| Prioritas | Pekerjaan | Pemilik yang dibutuhkan | Gate |
| --- | --- | --- | --- |
| P0 | Putuskan kabupaten, merek, badan pengelola, mitra, dan pemilik konten | Product owner/pemerintah daerah | Go live |
| P0 | Setujui harga, komisi, pajak, refund, settlement, dan SOP cuaca/jadwal | Finance/legal/operasional | Go live |
| P0 | Implementasikan adapter payout provider dan luluskan T13 | Finance/engineering | Release |
| P0 | Siapkan staging, akun individual, MFA/SSO, secret, dan monitoring eksternal | Security/engineering | UAT |
| P0 | Jalankan serta tandatangani UAT tamu, akun, admin, mitra, dan staf | Product/operator/finance | Go live |
| P0 | Tambahkan scanner malware atau nonaktifkan lampiran pilot | Security/operasional | Data nyata |
| P1 | Tambahkan analytics berbasis consent untuk funnel katalog ke checkout | Product/legal/engineering | Evaluasi conversion |
| P1 | Jalankan pilot tanpa transaksi nyata terlebih dahulu untuk validasi konten dan SOP | Operator/mitra | Soft launch |

Ekspansi wilayah atau volume harus ditahan selama ada masalah transaksi Critical, discrepancy keuangan, atau keluhan tanpa pemilik penyelesaian.
