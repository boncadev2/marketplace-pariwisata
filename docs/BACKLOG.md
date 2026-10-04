# Backlog Produk

## Wajib untuk Pilot

| Prioritas | Kelompok kerja | Fase |
| --- | --- | --- |
| P0 | Fondasi produk, lingkungan, desain, data, autentikasi, dan izin | 01–06 |
| P0 | Wilayah, kategori, mitra, media, destinasi, pencarian, peta, dan CMS | 07–13 |
| P0 | Tiket, inventori, paket, keberangkatan, checkout, pembayaran, dan webhook | 14–20 |
| P0 | Voucher, notifikasi, dukungan, refund, keuangan, settlement, operasional, rekonsiliasi | 21–28 |
| P0 | Keamanan, performa, UAT, deployment, backup, observabilitas, dan pilot | 29–33 |

### Blocker Go Live Fase 33

| Prioritas | Pekerjaan terbuka | Status |
| --- | --- | --- |
| P0 | Keputusan kabupaten, badan pengelola, mitra, konten berizin, dan penanggung jawab | Menunggu pemilik bisnis |
| P0 | Provider payment/refund/payout production dan pengujian T13 | Menunggu finance/legal dan implementasi provider |
| P0 | UAT staging lima peran serta simulasi operator tanpa developer | Belum dijalankan |
| P0 | Legal privacy/retention dan kontrol lampiran pelanggan | Belum disetujui |
| P0 | Rekonsiliasi settlement nyata dan transaksi kecil terotorisasi | Belum boleh dijalankan |

## Sesudah Pilot

| Prioritas | Kelompok kerja | Fase |
| --- | --- | --- |
| P1 | Penginapan dan inventori kamar | 34 |
| P1 | Kuliner dan reservasi paket makan | 35 |
| P1 | Ulasan, promo, dan retensi | 36 |
| P1 | Perluasan wilayah dan produk lanjutan | 37 |

## Opsional Berdasarkan Validasi API dan Bisnis

| Prioritas | Kelompok kerja | Fase |
| --- | --- | --- |
| P2 | Persiapan API dan desain aplikasi mobile | 38 |
| P2 | Android dan iOS | 39 |
| P2 | Beta mobile dan pemeliharaan berkelanjutan | 40 |

## Aturan Prioritisasi

- Satu fase dikerjakan dalam satu cabang kerja setelah prasyaratnya selesai.
- Fase tidak dianggap selesai hanya karena UI tampil; migrasi, pengujian risiko utama, dokumentasi, dan acceptance test harus ada.
- Checkout awal hanya mendukung satu produk dari satu mitra. Keranjang lintas mitra ditunda.
- Fitur transaksi produksi diblokir dengan konfigurasi/feature flag hingga keputusan bisnisnya disetujui.
