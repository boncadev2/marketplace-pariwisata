# Panduan Integrasi API Mobile (Fase 38–39)

Status 2 Oktober 2026: aplikasi Expo JavaScript menyediakan katalog destinasi, pencarian nama, filter wilayah/kategori, pagination, refresh, detail, serta navigasi kembali. Data berasal dari API publik Laravel. Fase mobile keseluruhan belum selesai; login native, pesanan, voucher, pembayaran, dan distribusi beta belum diimplementasikan.

## Endpoint yang tersedia

Base URL harus berakhiran `/api/v1`.

| Kebutuhan | Endpoint | Kontrak |
| --- | --- | --- |
| Katalog/pencarian destinasi | `GET /destinations?q=...&region_id=...&category_id=...&page=1&per_page=12` | `data` array; `meta.page`, `meta.per_page`, `meta.total` integer |
| Detail destinasi | `GET /destinations/{slug}` | `data` object; hanya published; draft/deleted/missing mendapat 404 |
| Filter wilayah | `GET /lookup/regions` | `data` array wilayah aktif; opsional parent_id/type |
| Filter kategori | `GET /lookup/categories` | `data` array kategori aktif |
| Katalog produk | `GET /products?type=ticket&page=1&per_page=12` | API tersedia; belum menjadi layar mobile |
| Profil sesi | `GET /me` | API tersedia, membutuhkan auth:sanctum; belum diintegrasikan native |
| Pesanan akun | `GET /account/orders` | API tersedia, membutuhkan auth:sanctum; belum diintegrasikan native |

`/search`, `/user/profile`, dan `/orders` dari draft panduan lama bukan endpoint aplikasi ini. Pencarian mobile saat ini menggunakan `/destinations?q=...`; pencarian lintas produk belum tersedia.

Detail mencakup id numerik, name, slug, publication_status, summary, description, region, category, latitude dan longitude. Kategori/koordinat/deskripsi boleh null. Respons memakai daftar field publik eksplisit; partner_id, metadata audit, dan media privat tidak dikirim. Tidak ada galeri pada kontrak detail saat ini.

## Konfigurasi dan jaringan

Isi `mobile/.env` dengan `EXPO_PUBLIC_API_URL`, misalnya `http://10.0.2.2:8080/api/v1` untuk Android emulator lokal. Perangkat fisik menggunakan IP LAN komputer yang menjalankan Docker; iOS simulator dapat memakai localhost. Panduan perintah ada di `docs/RUNBOOK.md`.

Build rilis wajib memakai HTTPS. Klien memberi pesan konfigurasi ketika URL kosong/tidak valid dan menolak HTTP di luar development. Variabel EXPO_PUBLIC dibundel sebagai nilai publik: jangan memasukkan Server Key Midtrans, token akun, password, atau kredensial lain. Lihat [dokumentasi environment Expo](https://docs.expo.dev/guides/environment-variables/).

Katalog hanya mengirim GET dengan Accept application/json tanpa kredensial sesi. Timeout 12 detik, pembatalan request ketika layar/filter berubah, dan guard respons lama mencegah hasil lama menggantikan pencarian baru. Pagination 12 item, filter baru kembali ke halaman 1; kembali dari detail mempertahankan query/filter/halaman. Refresh memuat ulang data. Kegagalan lookup dapat dicoba ulang dari pemilih filter.

Respons Laravel umumnya `{message, errors?}`; beberapa endpoint domain memakai `{error: {message}}`. Tidak ada jaminan envelope error tunggal `{error, message, details}` seperti draft lama. Klien menangani 404, 429, gangguan server, JSON/kontrak tidak valid, dan jaringan gagal. Kegagalan jaringan tidak ditampilkan sebagai katalog kosong.

## Autentikasi native belum tersedia

Login web yang ada memakai sesi/cookie Sanctum dan CSRF; endpoint login tersebut tidak menerbitkan JWT atau personal access token. Jangan menganggap Bearer token sudah tersedia hanya karena route menggunakan auth:sanctum. Fase selanjutnya perlu implementasi penerbitan/revokasi token native, penyimpanan aman pada perangkat, expiry, serta pengujian isolasi akun sebelum profil/pesanan native ditambahkan.

## Verifikasi

- Pengujian API detail dan katalog menutup akses draft/deleted, whitelist field, null, perubahan ETag setelah edit, serta filter/pagination.
- Pengujian Node bawaan pada mobile menguji encoding query, konfigurasi HTTPS, request GET tanpa kredensial, error, timeout, cancellation, retry dan kontrak respons.
- Ekspor Metro/Hermes Android dan iOS memeriksa bundling. Ini bukan APK/IPA atau UAT pada perangkat.
- Expo SDK 50 dan React Native 0.73.6 mengikuti kerangka yang sudah ada. Upgrade SDK serta pengujian perangkat merupakan pekerjaan terbuka sebelum beta/store. Tidak ada dependency baru untuk implementasi katalog ini.
