# Laporan Performa dan Pengalaman Mobile — Fase 30

Tanggal pengukuran: 30 September 2026 (lingkungan Docker lokal, Asia/Jakarta).

## Ringkasan hasil

Fase 30 selesai untuk baseline lokal. Katalog publik kini memakai query terbatas, cache yang dapat diinvalidasi, ETag, pagination, dan respons yang hanya berisi field publik. Harga, kalender stok, dan checkout selalu divalidasi server dan dikirim dengan `Cache-Control: no-store`. Checkout web sudah fungsional, mempertahankan input ketika koneksi bermasalah, serta memakai idempotency key yang sama saat percobaan ulang.

Tes konkurensi MySQL menemukan dan memperbaiki race antara pembayaran terlambat dan kedaluwarsa hold. Konfirmasi atau penggantian hold sekarang dilakukan atomik di bawah lock bucket yang sama. Tiga skenario multiproses lulus dan unit terakhir hanya dapat dipesan satu proses.

## Baseline sebelum optimasi

- Request hangat `GET /api/v1/destinations` menghasilkan HTTP 500 karena objek `LengthAwarePaginator` diserialisasi ke Redis lalu dipulihkan sebagai incomplete object. Karena itu baseline pencarian lama tidak memiliki throughput sukses yang dapat dibandingkan (0% sukses pada percobaan awal).
- Cache katalog satu jam tidak mempunyai invalidasi saat data dipublikasikan atau diubah.
- Halaman pencarian mengirim request setiap perubahan karakter, memakai origin backend langsung, dan dapat menampilkan error palsu akibat race abort.
- Halaman checkout hanya wireframe dan tidak melakukan quote/checkout server.
- Gambar kartu native dimuat eager dan antarmuka belum memiliki skip link/fokus global yang terlihat.

## Optimasi yang diterapkan

- Katalog destinasi: maksimal dua query database pada cache miss (count dan page), select field eksplisit, pagination maksimum 50, urutan stabil, payload array aman untuk Redis.
- Katalog destinasi, produk, region, dan kategori: cache versi 300 detik; versi otomatis berubah ketika konten terkait disimpan, dihapus, atau dipulihkan.
- Respons publik: ETag, conditional request `304`, browser cache 60 detik dan stale-while-revalidate 60 detik.
- Respons quote, stok, dan checkout: `no-store`; checkout menghitung ulang harga dan stok di dalam transaksi server.
- Pencarian: debounce 300 ms, minimal dua karakter, timeout 12 detik, status koneksi lambat, pembatalan request aman, serta hasil lama tidak dihapus saat jaringan gagal.
- Checkout: timeout 20 detik, status online/offline, form dipertahankan, retry idempoten, label dan pesan live-region, kontrol minimum 48 px.
- Media: `next/image` tetap responsif pada hero/kartu statis; gambar partner native memakai dimensi eksplisit, `loading="lazy"`, dan `decoding="async"`. Tidak ada peta berat yang dimuat pada initial render.
- Aksesibilitas: skip link, landmark navigasi/main, focus-visible, reduced motion, label input, status/error terbaca pembaca layar, kontras navigasi mobile diperkuat.

## Target lokal dan hasil terukur

Target ini adalah baseline referensi lokal, bukan SLA produksi. Target wajib dikalibrasi ulang di staging dengan jumlah data dan topologi hosting representatif.

| Metrik | Target lokal | Hasil |
| --- | ---: | ---: |
| Search API hangat, 100 request / concurrency 20 | p95 <= 200 ms; error 0% | p95 141,7 ms; 100/100 sukses |
| Throughput search hangat | >= 100 req/detik | 158,3 req/detik |
| Search cold request | dicatat sebagai baseline | 166,5 ms |
| Conditional ETag | HTTP 304 | 304 dalam 12,5 ms, body 0 byte |
| Query katalog pada cache miss | <= 2 query | <= 2, ditegakkan test |
| Checkout/stok oversell | 0 oversell | 3/3 tes multiproses MySQL lulus |
| Halaman mobile 390x844 | tanpa overflow horizontal / input tanpa label / target <44 px | 0 / 0 / 0 |
| Console checkout mobile | tanpa warning/error | 0 warning, 0 error |

Pengukuran lima request hangat pada server development: `/` median 96,7 ms, `/destinasi` 76,8 ms, dan `/checkout` 44,4 ms. Angka ini tidak mewakili server produksi. HTML yang diterima masing-masing 81.292, 44.297, dan 38.822 byte.

Build produksi menghasilkan 19 route. Estimasi JavaScript awal berdasarkan build manifest: checkout 138,5 KiB gzip dan destinasi 139,7 KiB gzip; CSS bersama 9,4 KiB gzip. Angka mencakup runtime bersama dan chunk route, bukan gambar yang dioptimalkan terpisah.

## Bukti verifikasi

- Suite backend SQLite terisolasi: 133 test lulus, 607 assertion; 3 tes khusus MySQL dilewati sesuai guard.
- Suite MySQL terisolasi `wisata_concurrency_test`: 3 test multiproses lulus, 19 assertion.
- Tes terfokus pembayaran dan Fase 30: 12 test lulus, 73 assertion.
- Frontend: ESLint lulus dan build produksi Next.js lulus untuk 19 route.
- Audit browser viewport 390x844: tidak ada overflow, kontrol terlalu kecil, input tanpa label, hydration mismatch, atau pesan console.

## Batas dan langkah staging

- Jalankan pengujian ulang pada staging dengan dataset dan latensi jaringan representatif; gunakan target lokal di atas sebagai titik awal, bukan janji SLA.
- Load test checkout staging harus memakai produk/data uji khusus dan provider pembayaran sandbox agar tidak menciptakan transaksi nyata.
- Pantau p50/p95/p99, error rate, slow query, hit ratio Redis, CPU, memory, dan lock wait MySQL. Hentikan test jika error atau saturasi dapat mengganggu pengguna.
- Uji koneksi lambat/offline dan perangkat fisik sebelum pilot. Audit browser lokal membuktikan struktur dan breakpoint, bukan seluruh variasi browser/perangkat.

