# Audit Keamanan dan Privasi Fase 29

Tanggal audit: 30 September 2026  
Ruang lingkup: implementasi lokal frontend Next.js, API Laravel, proxy Caddy, database, worker, scheduler, CI, dan konfigurasi contoh. Dokumen ini adalah bukti teknis, bukan persetujuan legal atau izin produksi.

## Ringkasan

Tidak ada temuan kritis yang masih terbuka setelah perbaikan fase ini. Kontrol IDOR, CSRF, XSS/output escaping, upload, rate limit, cookie/session, CORS, secret repository, dependency, retensi, penghapusan data, rekening, dan payout telah diperiksa terhadap implementasi aktual.

Aplikasi belum boleh dinyatakan siap menerima data pelanggan nyata hanya berdasarkan audit ini. Persetujuan kebijakan privasi/retensi oleh penanggung jawab legal, inventaris akses operator produksi, rotasi secret produksi, restore ke database terpisah, dan pemindaian malware lampiran masih harus diselesaikan sebelum pilot transaksi nyata.

## Temuan dan Perbaikan

| ID | Risiko awal | Temuan | Perbaikan | Status |
| --- | --- | --- | --- | --- |
| S29-01 | Tinggi | Middleware header keamanan ada tetapi tidak terdaftar; HSTS juga dipasang pada HTTP lokal. | Middleware didaftarkan global, HSTS hanya pada HTTPS, CSP API, `nosniff`, anti-frame, referrer dan permissions policy ditambahkan. Header frontend/proxy juga diperketat. | Ditutup |
| S29-02 | Tinggi | Setiap pengguna terautentikasi dapat mengunggah media publik tanpa scope mitra. | Upload wajib `partner_id`, hanya admin atau owner/manager aktif pada mitra tersebut, validasi image/MIME/ekstensi/ukuran, nama file acak storage, respons tidak membuka path, dan audit log tanpa data sensitif. | Ditutup |
| S29-03 | Tinggi | Retensi menghapus seluruh delivery notifikasi dan tidak terjadwal; tidak ada proses permintaan penghapusan data. | Job harian kini menghapus sesi/token kedaluwarsa, meredaksi PII notifikasi tetapi mempertahankan bukti delivery, menghapus lampiran tiket tertutup setelah masa retensi, serta menyediakan permintaan dan pemrosesan penghapusan akun dengan anonimisasi. Catatan transaksi/keuangan/audit tetap dipertahankan. | Ditutup |
| S29-04 | Sedang | CORS belum memiliki konfigurasi eksplisit. | Origin diambil dari allowlist environment, credentials aktif, metode/header dibatasi, wildcard dihapus. | Ditutup |
| S29-05 | Sedang | Tidak ada redaksi generik untuk context log. | Processor log meredaksi password, token, cookie, signature, rekening, email, bearer token, dan rangkaian nomor panjang. Probe runtime membuktikan nomor dan password tersamarkan. | Ditutup |
| S29-06 | Sedang | Endpoint sensitif belum memiliki limiter khusus dan admin hanya diperiksa dari role. | Limiter khusus login, checkout, upload, webhook, privasi, dan konfirmasi sensitif ditambahkan. Admin wajib role `super_admin` serta email terverifikasi; perubahan payout, rekening, refund, sengketa, rekonsiliasi, dan penghapusan data memerlukan konfirmasi password terkini. | Ditutup |
| S29-07 | Sedang | Perubahan rekening dan payout belum selalu meninggalkan audit trail. | Pembuatan/verifikasi rekening dan pembuatan/persetujuan batch payout dicatat tanpa nomor rekening atau data pribadi lengkap. | Ditutup |
| S29-08 | Sedang | Lampiran dukungan belum dipindai malware. | File tetap private, tidak dieksekusi, dibatasi PDF/JPG/PNG 5 MB, MIME dan ekstensi diperiksa, akses owner-only dan `nosniff`. Pemindaian malware masih wajib sebelum data pelanggan nyata diterima. | Terbuka — non-kritis, blokir pilot data nyata |
| S29-09 | Operasional | Belum ada bukti legal sign-off, roster operator produksi, rotasi secret produksi, atau restore ke instance terpisah. | Checklist dan batas aktivasi ditambahkan ke runbook/keputusan. Uji lokal dump → AES-256-CBC/PBKDF2 → decrypt → checksum berhasil; ini belum menggantikan uji restore produksi. | Terbuka — membutuhkan pemilik manusia |

## Bukti Kontrol

- IDOR: suite akun/order/tiket/voucher/dashboard/mitra menguji akses lintas pemilik/tenant dengan hasil 404/403.
- CSRF: POST stateful tanpa cookie/token melalui `http://localhost:8080` menghasilkan HTTP 419 `CSRF token mismatch`; frontend mengambil cookie CSRF sebelum mutasi.
- XSS: template notifikasi meng-escape input pelanggan; tidak ada render HTML mentah berbasis data pengguna. Penggunaan `dangerouslySetInnerHTML` frontend hanya untuk CSS statis yang dikontrol source code.
- File private: lampiran dukungan disimpan pada disk lokal private dan hanya diunduh melalui endpoint terautentikasi/owner-scoped.
- Rekening: nilai terenkripsi at-rest melalui cast model, disembunyikan dari serialisasi, dan hanya empat digit terakhir ditampilkan.
- Cookie/session: HTTP-only dan SameSite Lax tetap aktif; enkripsi session default aktif. Produksi wajib `SESSION_SECURE_COOKIE=true` dan HTTPS.
- Secret: hanya `.env.example` yang terlacak; scan pola key/private key tidak menemukan credential produksi. Nilai Compose adalah default lokal dan dilarang digunakan di staging/produksi.
- Dependency: `composer audit --locked --format=json` dan `npm audit --omit=dev --json` melaporkan nol advisory. CI sekarang menjalankan audit dependency, test backend, lint, dan build frontend.
- Backup: dump lokal 132.560 byte berhasil dienkripsi AES-256-CBC/PBKDF2, didekripsi, dan cocok checksum; seluruh artefak uji sementara dihapus.
- Log: probe runtime menghasilkan `phase29-redaction-probe ************3456 {"password":"[REDACTED]"}`.

## Hasil Verifikasi

- Formatter Laravel untuk seluruh file Fase 29: lulus.
- Test terkait keamanan/payout: 17 test, 81 assertion lulus.
- Seluruh backend: 128 test, 570 assertion lulus; 3 test konkurensi dilewati karena memerlukan MySQL terisolasi.
- Frontend: ESLint lulus; production build lulus untuk 19 halaman.
- Migrasi `data_deletion_requests` dan `personal_data_redacted_at` berhasil diterapkan pada database lokal.
- Health check proxy HTTP 200 dan header keamanan terlihat setelah restart.

## Checklist Sebelum Pilot Data Nyata

- [ ] Legal/DPO menyetujui kebijakan privasi, dasar pemrosesan, periode retensi, pengecualian catatan transaksi, dan teks permintaan penghapusan.
- [ ] Security/ops mengaktifkan HTTPS, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, origin produksi tunggal, dan secret manager terpisah per environment.
- [ ] Semua secret produksi dibuat baru, dicatat pemilik serta tanggal rotasinya, dan tidak memakai default Compose.
- [ ] Daftar operator/admin produksi disetujui; akun bersama dilarang; offboarding dan review akses berkala diuji. MFA/SSO eksternal direkomendasikan sebelum payout nyata.
- [ ] Scanner malware dipasang untuk lampiran, atau upload lampiran dinonaktifkan selama pilot.
- [ ] Backup terenkripsi dipulihkan ke database terpisah dan hasil aplikasi diverifikasi, bukan hanya checksum file.
- [ ] Provider pembayaran/refund/payout produksi dan webhook secret telah disetujui; adapter sandbox tetap fail-closed sampai itu selesai.

