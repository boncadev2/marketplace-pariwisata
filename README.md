# Marketplace Pariwisata Daerah

Marketplace pariwisata daerah dengan frontend Next.js JavaScript dan backend Laravel API. Implementasi dilakukan bertahap sesuai `docs/PROGRESS.md`.

Status saat ini: reservasi simulasi penginapan Fase 34, kuliner Fase 35, promo checkout sandbox Fase 36, dan alur lintas desa sandbox Fase 37 terimplementasi serta diuji lokal. Adapter Midtrans Snap sandbox tersedia; aktivasi dan UAT langsung memerlukan Server Key sandbox serta webhook HTTPS publik. Integrasi pembayaran dan operasional kedua fase masih terbuka. Go live pilot tetap NO GO sampai blocker bisnis dan UAT ditutup. Katalog mobile Expo Fase 38–39 kini mengambil destinasi, pencarian, filter, dan detail dari API; login native serta beta perangkat masih terbuka. Frontend memakai JavaScript, bukan TypeScript.

## Dokumen

- `docs/PRD.md` — kebutuhan produk rilis pilot
- `docs/BACKLOG.md` — prioritas pekerjaan
- `docs/GLOSSARY.md` — istilah domain
- `docs/DECISIONS.md` — keputusan dan hal yang masih menunggu pemilik bisnis
- `docs/PROJECT_CONTEXT.md` — konteks dan batasan teknis
- `docs/PROGRESS.md` — catatan fase dan bukti verifikasi
- `docs/RUNBOOK.md` — menjalankan dan memeriksa lingkungan lokal
- `docs/API.md` — konvensi kontrak API awal

## Menjalankan Lokal

Prasyarat utama adalah Docker Desktop aktif. Lalu jalankan:

```sh
cp .env.example .env
cp frontend/.env.example frontend/.env.local
docker compose up --build
```

Aplikasi tersedia di `http://localhost:8080`; frontend langsung di port 3000 dan health check Laravel di `http://localhost:8000/up`. Detail layanan dan pemeriksaan tersedia di `docs/RUNBOOK.md`.

## Aturan penting

Harga, kuota, izin akses, dan status pembayaran nantinya harus menjadi sumber kebenaran di backend. Pembayaran produksi tidak boleh diaktifkan sampai keputusan provider, skema settlement, komisi, pajak, dan refund telah disetujui.
