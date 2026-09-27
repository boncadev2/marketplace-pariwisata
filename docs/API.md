# Kontrak API

## Konvensi Awal

- Base path API: `/api/v1`.
- Format respons: JSON.
- Waktu teknis menggunakan ISO 8601 UTC.
- Nilai uang IDR kelak dikirim sebagai integer rupiah dan `currency` eksplisit.
- Spesifikasi mesin-baca tersedia pada `openapi.yaml`. Endpoint domain diaktifkan bertahap sesuai fase terkait.

## Health Check

Laravel menyediakan health check dasar pada `GET /up`. Endpoint ini tidak membawa data bisnis dan digunakan oleh runtime lokal/deployment untuk memeriksa proses aplikasi.

## Kutipan Harga Produk

`GET /api/v1/products/{slug}/quote`

Menghitung harga produk publik untuk tanggal kunjungan dan jumlah pengunjung. Endpoint hanya menerima produk dengan status `published`.

Parameter query wajib:

- `visit_date`: tanggal kunjungan dalam format `YYYY-MM-DD`.
- `quantity`: jumlah pembelian, bilangan bulat 1–100.

Aturan harga aktif yang mencakup tanggal kunjungan dipilih berdasarkan `priority` tertinggi. Jika tidak ada aturan yang berlaku, sistem menggunakan `base_price` produk. Respons mengembalikan harga satuan dan `total` sebagai integer rupiah, bersama kode mata uang.

## Kalender Inventori Produk

`GET /api/v1/products/{slug}/inventory?from=YYYY-MM-DD&to=YYYY-MM-DD`

Menampilkan ketersediaan publik per tanggal dan sesi untuk produk berstatus `published`. Nilai `available` telah mengurangi kuota yang sedang ditahan dan sudah dikonfirmasi; tanggal yang ditutup selalu mengembalikan `available: 0`.
