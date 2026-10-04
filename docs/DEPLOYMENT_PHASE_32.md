# Deployment, Backup, dan Observabilitas — Fase 32

Tanggal verifikasi lokal: 30 September 2026  
Baseline hosting: satu host Linux dengan Docker Compose, Caddy sebagai reverse proxy, MySQL, Redis, worker, dan scheduler terpisah.

## Status

Artefak deployment dan recovery sudah dapat diulang secara lokal. Deployment staging/production eksternal belum dijalankan karena repository ini tidak memiliki domain, host, secret manager, credential provider, atau akun monitoring milik pengguna. Hal tersebut tidak diklaim lulus.

## Pipeline

`.github/workflows/deploy.yml` melakukan:

1. Composer install/audit, seluruh test Laravel, dan Pint check.
2. NPM clean install/audit, ESLint, dan production build.
3. Deployment hanya setelah test lulus, memakai GitHub Environment `staging` atau `production`.
4. SSH memakai host key yang dipin melalui secret, lalu checkout commit SHA tepat dari workflow.
5. Backup sebelum migrasi, build image, migrasi `--force`, cache konfigurasi/route/view, restart worker/scheduler, lalu smoke check.

Secret per environment yang wajib diisi: `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`, `DEPLOY_TARGET`, `DEPLOY_PATH`, dan `BACKUP_ROOT`. Variable `SMOKE_URL` harus memakai URL HTTPS environment terkait. Production environment harus memakai required reviewers.

## Bukti restore aktual

Perintah yang dijalankan:

```bash
restore_dir=$(mktemp -d /private/tmp/wisata-phase32-restore-XXXXXX)
./infra/scripts/backup-local.sh "$restore_dir"
shasum -a 256 -c "$restore_dir/SHA256SUMS"
./infra/scripts/restore-drill-local.sh "$restore_dir/database.sql"
```

Hasil:

- checksum dump database dan arsip private media: valid;
- restore ke database khusus `wisata_restore_drill`: berhasil;
- jumlah tabel sumber dan hasil restore: 61;
- jumlah migrasi sumber dan hasil restore: 64;
- waktu restore lokal terukur: 3 detik;
- database latihan dan dump sementara dibersihkan setelah verifikasi.

Angka 3 detik adalah RTO latihan lokal berukuran kecil, bukan SLA production. RPO lokal pada latihan adalah waktu dump dibuat. RPO/RTO production harus diukur lagi dengan snapshot provider dan volume data staging yang realistis.

## Readiness dan observabilitas

- `/up` memeriksa proses Laravel.
- `/api/v1/health/dependencies` memeriksa database, cache round-trip, queue, storage/free disk, failed jobs, dan heartbeat scheduler.
- `X-Request-ID` UUID diteruskan bila valid atau dibuat baru, ditambahkan ke log context, dan dikembalikan pada respons.
- Scheduler memperbarui heartbeat setiap menit; production menolak readiness bila heartbeat melewati threshold.
- Log sensitif tetap melalui redaction processor. Production harus mengirim log JSON/stderr ke aggregator eksternal.
- `.dockerignore` mengecualikan `.env`, dependency host, build cache, log, dan Git metadata; build frontend production terverifikasi dengan context 3,32 kB dan 19 route.

Alarm minimum yang harus dibuat pada platform hosting:

| Sinyal | Threshold awal | Tindakan |
| --- | --- | --- |
| Readiness | 2 kegagalan berturut-turut | Page operator, hentikan traffic instance |
| HTTP 5xx | > 2% selama 5 menit | Page engineering |
| Failed jobs | > 0 | Ticket/page sesuai job |
| Queue oldest age | > 5 menit | Periksa worker dan Redis |
| Disk | < 10% atau di bawah threshold byte | Tambah kapasitas/hapus artefak aman |
| Payment discrepancy | setiap alert Critical | Page finance dan engineering |
| Backup | tidak ada backup sukses 24 jam | Page operator |

## Rollback

Rollback aplikasi dilakukan dengan menjalankan pipeline pada commit/tag sebelumnya yang sudah lulus. Migrasi production wajib mengikuti pola expand/contract; deploy tidak otomatis menjalankan `migrate:rollback` karena dapat merusak data yang sudah ditulis versi baru. Bila migrasi inkompatibel terlanjur berjalan, hentikan traffic dan gunakan prosedur recovery yang disetujui, bukan rollback skema spontan.

## Gate sebelum production

- DNS dan sertifikat HTTPS aktif; Caddy/provider hanya mengekspos 80/443.
- `APP_ENV=production`, `APP_DEBUG=false`, `HEALTH_FAILED_JOBS_THRESHOLD=0`, secure/encrypted session cookie, dan origin CORS resmi.
- MySQL/Redis tidak terbuka ke internet dan credential lokal contoh sudah diganti.
- Payment, refund, email, object storage, error tracking, serta payout memakai konfigurasi per-environment.
- Backup terenkripsi/off-site memiliki retensi, akses terbatas, alarm, dan restore drill berkala.
- Dashboard/alert eksternal diuji dengan synthetic failure.
