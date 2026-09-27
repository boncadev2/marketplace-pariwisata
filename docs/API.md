# Kontrak API

## Konvensi Awal

- Base path API: `/api/v1`.
- Format respons: JSON.
- Waktu teknis menggunakan ISO 8601 UTC.
- Nilai uang IDR kelak dikirim sebagai integer rupiah dan `currency` eksplisit.
- Spesifikasi mesin-baca tersedia pada `openapi.yaml`. Endpoint domain belum diaktifkan sampai fase domain terkait selesai.

## Health Check

Laravel menyediakan health check dasar pada `GET /up`. Endpoint ini tidak membawa data bisnis dan digunakan oleh runtime lokal/deployment untuk memeriksa proses aplikasi.

`GET /api/v1/health` dan `GET /api/v1/destinations` merupakan kontrak yang disepakati untuk implementasi berikutnya; keduanya belum diaktifkan di backend pada Fase 03.
