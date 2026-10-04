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


## Reservasi Simulasi Penginapan dan Kuliner (Fase 34–35)

Semua quote memakai `Cache-Control: no-store`. Endpoint akun membutuhkan sesi Sanctum atau token; akun hanya dapat melihat/membatalkan reservasinya sendiri. Write reservasi hanya tersedia pada `APP_ENV=local/testing`, dan menghasilkan `reserved_sandbox`, bukan order atau transaksi pembayaran. Harga reservasi dikirim sebagai string desimal dua digit dengan mata uang IDR pada quote.

| Metode | Endpoint | Fungsi |
| --- | --- | --- |
| GET | `/lodging/rooms?page=1` | Kamar aktif; `data` berisi paginator 20 item dan `meta.sandbox_reservations_enabled`. |
| GET | `/lodging/quote` | Quote seluruh malam dengan check-out eksklusif. |
| GET/POST | `/account/lodging-bookings` | Daftar reservasi akun atau buat reservasi simulasi. |
| POST | `/account/lodging-bookings/{id}/cancel` | Pembatalan idempoten dan pemulihan stok seluruh malam. |
| GET | `/culinary/slots?page=1` | Slot masa depan pada tempat aktif, harga, waktu ISO 8601, dan kuota tersisa. |
| GET | `/culinary/quote` | Quote harga paket untuk jumlah peserta. |
| GET/POST | `/account/meal-bookings` | Daftar reservasi akun atau buat reservasi simulasi. |
| POST | `/account/meal-bookings/{id}/cancel` | Pembatalan idempoten dan pemulihan kuota. |

Parameter penginapan: `room_type_id`, `check_in`/`check_out` (YYYY-MM-DD), `quantity` (1–10 kamar), `guests` (1–100 dan tidak melebihi kapasitas). Check-in tidak boleh lampau, horizon satu tahun dan masa inap 1–30 malam. Stok dan tarif harus tersedia untuk setiap malam. Parameter kuliner: `meal_slot_id` dan `quantity` (1–100 peserta).

POST reservasi wajib membawa header `Idempotency-Key` (16–100 karakter alfanumerik, underscore, atau hyphen) serta `expected_total_price` sesuai quote, misalnya `"150000.50"`. Replay payload sama mengembalikan reservasi awal (HTTP 200), termasuk yang telah dibatalkan; payload berbeda dengan key sama mendapat HTTP 409. Harga berubah mendapat HTTP 409 tanpa perubahan stok. Reservasi baru mendapat HTTP 201, data tidak valid/kuota tidak cukup HTTP 422, objek tidak aktif/tidak dimiliki HTTP 404, dan write di luar local/testing HTTP 503.

Kuota/stock hanya dikurangi setelah seluruh validasi lulus dalam transaksi. Seeder demo lokal (`LodgingDemoSeeder`, `CulinaryDemoSeeder`) mempertahankan stok dan reservasi terpakai saat dijalankan ulang. Pembayaran, expiry otomatis, tenant mitra, dan konfirmasi layanan nyata belum diintegrasikan.


## Kupon Checkout Sandbox (Fase 36)

`GET /api/v1/promos/quote` membutuhkan sesi/token Sanctum dan email akun terverifikasi. Query wajib: `product_slug`, `visit_date` (tidak lampau), `quantity` (1–100), dan `coupon_code`. Respons `data` memuat `unit_price`, `quantity`, `currency`, `coupon_code`, `subtotal`, `discount`, dan `total`; uang dalam integer IDR dan `Cache-Control: no-store`. Quote tidak mengurangi stok atau kuota.

`POST /checkout` kini menerima `coupon_code` opsional dan `expected_total` wajib bila memakai kupon. Email pemesan harus cocok dengan akun terverifikasi. Harga net harus cocok dengan quote; perubahan ditolak HTTP 409. Kupon tidak aktif, lewat waktu, kuota habis, minimum belanja gagal, atau aturan diskon tidak valid ditolak HTTP 422. Diskon pecahan rupiah dibulatkan ke bawah; total pembayaran harus minimal Rp1.

Kupon hanya tersedia di local/testing (HTTP 503 di luar itu). Kuota dikonsumsi saat pesanan dibuat, bersama hold dan snapshot diskon dalam transaksi yang sama. Key checkout yang sama tidak menghabiskan kuota kedua. Response checkout menambahkan `promotion` (null tanpa kupon). Dalam sandbox, nilai net dipakai untuk item, payment attempt, ledger, dan basis komisi; ini belum menetapkan sponsor atau aliran dana promo produksi. Kuota order expired/cancelled/refunded belum dipulihkan otomatis.


## Simulasi Paket Lintas Desa (Fase 37)

`GET /api/v1/products/{slug}/cross-village-quote?visit_date=YYYY-MM-DD&quantity=1` memerlukan Sanctum, role super_admin, dan email terverifikasi. Hanya tersedia di local/testing. Parameter dan batas peserta divalidasi; harga diambil dari server, bukan nominal client. Mendukung paket published dengan pricing_mode per_person dan mata uang IDR.

Respons data memuat unit_price, quantity, total, currency, commission_rule_id, commission_amount, partner_revenue, simulation_only=true, dan allocations berisi partner_id, region_id, share_percentage, partner_revenue. Semua uang dalam integer rupiah. Porsi dibaca dari CrossVillagePackage, wajib positif dan total 100%, mitra approved dan berasal dari minimal dua desa berbeda; satu primary harus pemilik produk. Pembulatan menggunakan pecahan terbesar dan ID mitra sebagai tie-break. HTTP 401 tanpa login, 403 bukan admin terverifikasi, 404 produk/paket tidak published, 422 input/pemetaan invalid, 503 di luar local/testing.

Quote tidak menulis order, sub-order, inventori, ledger, atau payout dan tidak menerapkan kupon. Konfigurasi porsi dan transaksi lintas mitra produksi belum tersedia. Respons Cache-Control no-store.


Konfigurasi admin simulasi: `GET /products/{slug}/cross-village-configuration` mengembalikan shares, version, owner_partner_id, product_name, simulation_only dan candidates. Query search opsional (maksimal 100 karakter) menyaring nama kandidat; maksimal 30 hasil, hanya mitra approved dengan region village. Produk harus bertipe package dan memiliki TourPackage; konfigurasi draft diperbolehkan, tetapi quote memerlukan published.

`PUT` pada endpoint sama memerlukan header X-Sensitive-Confirmation dari konfirmasi kata sandi. Body: version (hash 64 karakter dari GET), reason (5–500 karakter), shares (2–20 baris). Setiap baris berisi partner_id integer unik, revenue_share_percentage string dua desimal (misalnya 50.00), is_primary_partner boolean. Seluruh aturan alokasi berlaku. Update atomik mencatat audit sebelum/sesudah, tidak membuat transaksi keuangan. Respons memuat shares dan version terbaru. Versi stale mendapat 409; konfirmasi sensitif yang tidak valid mendapat 423. Kedua endpoint hanya local/testing dan admin terverifikasi, no-store.


## Snapshot Sub-order Sandbox (Fase 37)

`GET /api/v1/orders/{public_id}/cross-village-snapshot` khusus super_admin dengan email terverifikasi di local/testing, mengembalikan data snapshot (null bila belum dibuat) serta order_status saat ini. Data snapshot disembunyikan dari serialisasi umum Order.

`POST` pada endpoint sama memerlukan konfirmasi sensitif kata sandi (X-Sensitive-Confirmation). Body version wajib hash 64 karakter dari GET konfigurasi paket, reason wajib 5–500 karakter. Pesanan wajib paid, IDR, satu item paket dengan pemilik sesuai; seluruh payment attempt harus sandbox, minimal satu succeeded dengan jumlah dan currency cocok. Total item harus sama dengan order dan komisi tersimpan tidak boleh negatif/melebihi total. Nominal rupiah pecahan ditolak. Porsi saat simulasi wajib masih sesuai version dan aturan lintas desa.

Respons data menyimpan captured_at/captured_by, basis=configuration_at_simulation, configuration_version, product_id, order_item_id, quantity, currency, total, commission_amount, partner_revenue, shares, allocations dan simulation_only=true. Alokasi subtotal/komisi/pendapatan memakai uang pesanan tersimpan, bukan harga atau aturan komisi terbaru. Pendapatan dan komisi dibagi masing-masing dengan algoritme pecahan terbesar; subtotal per mitra adalah jumlah keduanya. Jumlah seluruh subtotal/komisi/pendapatan tepat sama dengan nominal pesanan.

Dalam satu transaksi, snapshot serta sub_orders berstatus simulation_only dibuat dan diaudit. Order lock menjadikan retry/permintaan bersamaan idempoten per pesanan, tidak menggandakan sub-order/audit. Retry memakai version pertama mengembalikan snapshot awal walaupun konfigurasi/status order kemudian berubah; version lain mendapat 409. Sub-order lama tanpa snapshot juga ditolak 409. Input/pembayaran/pemetaan invalid mendapat 422, konfirmasi diperlukan 423, di luar local/testing 503.

Snapshot adalah riwayat konfigurasi saat admin mensimulasikan order, bukan persetujuan kontrak atau porsi saat checkout awal. Ledger, payout, inventori dan status order tidak berubah. Refund tidak mengubah riwayat simulasi. Integrasi settlement lintas mitra produksi belum tersedia.


## Persetujuan Porsi Mitra Sandbox (Fase 37)

`GET /partner/cross-village-proposals` memerlukan login dan email terverifikasi, local/testing saja. Response data adalah daftar produk paket yang melibatkan mitra pengguna sebagai owner/manager aktif pada partner approved (20 per halaman; query page). memberships memuat ID/nama mitra yang dapat diwakili, meta memuat current_page/last_page. Proposal mitra lain tidak ditampilkan.

`GET /products/{slug}/cross-village-agreement` tersedia bagi owner/manager aktif salah satu mitra proposal, atau admin super_admin terverifikasi sebagai pembaca. Response data berisi product_name, shares, version, revision, agreements, all_accepted, simulation_only. agreements hanya memuat partner_id, decision (pending/accepted/rejected), decided_at; identitas/reason aktor tidak dibuka melalui ringkasan ini.

`POST` pada endpoint agreement sama membutuhkan X-Sensitive-Confirmation serta body partner_id integer, version hash 64 karakter, decision accepted/rejected, reason 5–500 karakter. Pengguna wajib owner/manager aktif mitra itu, partner approved dan berada dalam pemetaan paket. Admin tanpa keanggotaan tidak dapat mewakili mitra; staff/inactive/unrelated mendapat 404. Keputusan memakai porsi revisi terkini, stale version mendapat 409. Porsi invalid mendapat 422; konfirmasi diperlukan 423; di luar local/testing 503.

Keputusan dicatat per package/revision/partner dengan unique constraint dan audit. Pengiriman ulang keputusan yang sama tidak mencatat audit baru; perubahan accepted menjadi rejected menarik persetujuan dan dicatat. Penulisan mengunci product/package/membership. Setiap penyimpanan konfigurasi admin menaikkan cross_village_revision (termasuk porsi identik), sehingga hash version baru dan seluruh mitra perlu memutuskan ulang; keputusan revisi lama tetap disimpan.

Konfigurasi GET/PUT kini juga memuat revision, agreements, all_accepted. Snapshot order yang baru menyimpan bukti status persetujuan saat simulasi dan tidak diubah oleh keputusan berikutnya. Simulasi tetap dapat dilakukan ketika persetujuan belum lengkap, ditandai all_accepted=false. Ini workflow persetujuan sandbox, bukan kontrak hukum atau pembukaan transaksi/settlement produksi.


## Checkout Lintas Desa Sandbox dengan Snapshot Awal (Fase 37)

Quote publik `/products/{slug}/quote` kini menambahkan data.cross_village (null untuk produk biasa), memuat version, revision, all_accepted, available dan simulation_only. Ringkasan ini tidak membuka porsi atau aktor mitra. Frontend checkout membaca ringkasan tersebut, termasuk bila memakai kupon.

Untuk paket yang memiliki pemetaan lintas desa, `POST /checkout` wajib mengirim cross_village_version (hash 64 karakter dari quote) dan expected_total (total pembayaran sesudah kupon jika ada). Seluruh mitra harus accepted pada revisi/hash yang sama. Produk/paket harus published, IDR, harga per_person, tanggal tidak lampau, peserta sesuai minimum/maksimum. Versi berubah, persetujuan belum lengkap, atau harga total berubah ditolak 409 sebelum stok/kuota dikonsumsi. Versi tidak dikirim/input tidak valid ditolak 422. Di luar local/testing checkout lintas desa ditolak 503, termasuk retry yang mengirim versi. Kontrol checkout pilot tetap berlaku.

Checkout mengunci produk dan paket, memvalidasi persetujuan, lalu menyimpan order/item/hold, snapshot, sub_orders simulation_only, audit dan penukaran kupon (bila ada) dalam satu transaksi. Snapshot basis=configuration_at_checkout berisi nominal net, komisi net, porsi dan status persetujuan pada saat order pending_payment dibuat. Perubahan berikutnya tidak mengubah snapshot. Kegagalan snapshot membatalkan order, hold, audit dan kuota kupon.

Key idempotensi yang sama dan payload sama (termasuk cross_village_version) mengembalikan order awal walaupun konfigurasi berubah setelah pembelian. Payload dengan versi berbeda mendapat konflik idempotensi. Checkout response menambahkan cross_village nullable yang hanya memuat simulation_only, revision, basis; detail alokasi tetap hanya melalui endpoint admin snapshot.

Alokasi ini tetap simulasi. Ledger pembayaran, refund dan payout belum membagi kewajiban lintas mitra; jangan gunakan data sandbox sebagai settlement produksi. Snapshot manual admin untuk order lama tetap memakai configuration_at_simulation. Checkout tetap satu produk, bukan keranjang beberapa produk/mitra.


## Midtrans Sandbox

GET `/payments/gateway-status` publik, no-store: data.provider (sandbox/midtrans_sandbox), mode=sandbox, ready boolean. Nilai ready hanya menunjukkan driver/key/env dikenali; bukan pemeriksaan kredensial ke Midtrans. Checkout response kini menambahkan payment_provider. Driver midtrans_sandbox yang belum siap membuat checkout 503 sebelum transaksi dibuat.

POST `/webhooks/payments/midtrans` tanpa sesi pengguna, dengan autentikasi signature provider. Body wajib order_id (maksimal 50), status_code string tiga angka, gross_amount string rupiah utuh (contoh 125000.00), transaction_id, transaction_status dan signature_key 128 karakter. currency opsional harus IDR. Signature SHA512(order_id + status_code + gross_amount + ServerKey) diperiksa constant-time; salah mendapat 401. Server juga GET status langsung ke Midtrans sandbox dan memeriksa reference/transaction_id/amount/currency. Nominal/identitas tidak cocok mendapat 409; status belum tersedia mendapat 503 agar notifikasi dapat diulang.

Payload internal yang disimpan hanya event_key/provider_reference/status/amount/currency; signature, key, nomor rekening/kartu dan body asli tidak disimpan. Event key memuat prefix provider dan hash order/transaction/status normalisasi. Event durable sebelum ProcessPaymentWebhook diantrekan; retry tidak menggandakan event, ledger atau voucher. Response 200 data.accepted=true, ditambah ignored=true untuk status yang belum didukung (refund/authorize, dll). Refund provider tidak diproses lewat endpoint ini.

Provider reference Midtrans adalah UUID public_id order dan dicatat sebelum POST Snap, sehingga notifikasi yang datang sebelum respons Snap dapat dikaitkan. Pembuatan POST tidak diulang jika hasil belum pasti. Attempt uncertain diperiksa GET status; 404 sebelum pelanggan memilih metode di Snap bukan bukti pembayaran gagal. Adapter hanya local/testing/staging, endpoint sandbox dipatok, dan production ditolak. Integrasi menambahkan provider tanpa mengubah webhook sandbox internal.

## Katalog produk UMKM

- `GET /api/v1/umkm-products?q=&page=1&per_page=12`: pencarian nama/lokasi, pagination (maksimum 50 item), hanya produk published dari mitra approved yang tidak terhapus.
- `GET /api/v1/umkm-products/{slug}`: detail publik; draft, terhapus dan mitra belum disetujui menghasilkan 404.
- `data`: `id`, `slug`, `name`, `description`, `location`, `price` (integer rupiah), `currency` (IDR), `unit`, `seller`, `is_demo`.
- Daftar menyertakan `meta.page`, `meta.per_page`, `meta.total`. Tidak memerlukan login dan tidak mengungkap data kontak privat mitra.
- Modul ini adalah katalog. Produk UMKM tidak masuk checkout tiket; stok, pengiriman, pengelolaan produk dan pembayaran online belum tersedia.

## Pemesanan UMKM (simulasi lokal)

- `POST /api/v1/account/umkm-orders` membutuhkan sesi login dan `Idempotency-Key` 16–100 karakter. Body: `product_slug`, `quantity` 1–100, `expected_price` integer rupiah, `customer_name`, `customer_phone`, `notes` opsional maksimal 500 karakter.
- `GET /api/v1/account/umkm-orders?page=1` mengembalikan hanya pesanan akun aktif, pagination 12 item.
- `POST /api/v1/account/umkm-orders/{publicId}/cancel` membatalkan pesanan sendiri dan mengembalikan stok tepat sekali.
- POST hanya local/testing. Total dihitung server, harga berubah/stok kurang/payload retry berbeda mendapat 409. Pesanan baru 201, retry sama 200, pesanan orang lain 404.
- Respons memuat `order_id`, `status` (`reserved_sandbox`/`cancelled`), `payment_status` (`unpaid`), `sandbox`, snapshot `product`, `quantity`, `total`, data penerima dan `created_at`. Metode pemenuhan saat ini `pickup`.
- Katalog UMKM kini memuat `stock` dan `ordering_available`. Pembayaran Midtrans, pengiriman, expiry dan pemenuhan mitra belum diintegrasikan.

Daftar pesanan UMKM mendukung `status=reserved_sandbox` atau `status=cancelled`; kosong berarti semua status. Nilai lain ditolak dengan 422. Login tetap wajib pada seluruh endpoint pesanan dan akses data dibatasi ke akun pembeli.

Detail `GET /api/v1/account/orders/{publicId}` memuat `checkout_url` nullable untuk melanjutkan Snap Midtrans sandbox yang sudah ada. Hanya pemilik akun dapat mengaksesnya; URL tidak dikembalikan setelah batas hold, untuk attempt gagal/uncertain atau order non-pending. Endpoint ini tidak menghubungi provider atau membuat pembayaran baru.
