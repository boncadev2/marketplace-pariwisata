# Kontrak API

## Konvensi Awal

- Base path API: `/api/v1`.
- Format respons: JSON.
- Waktu teknis menggunakan ISO 8601 UTC.
- Nilai uang IDR kelak dikirim sebagai integer rupiah dan `currency` eksplisit.
- Endpoint domain belum diaktifkan pada Fase 02. Kontrak sumber daya dan OpenAPI dikembangkan pada Fase 03.

## Health Check

Laravel menyediakan health check dasar pada `GET /up`. Endpoint ini tidak membawa data bisnis dan digunakan oleh runtime lokal/deployment untuk memeriksa proses aplikasi.
