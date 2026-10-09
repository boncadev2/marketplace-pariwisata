# Panduan Lengkap Deployment Produksi (Production Deployment Guide)
## Marketplace Pariwisata & Layanan Wisata Daerah

Dokumen ini adalah panduan resmi step-by-step untuk menyiapkan, mengonfigurasi, dan mengoperasikan platform **Wisata Daerah** di lingkungan produksi menggunakan **Ubuntu 24.04 LTS**.

---

## 1. Arsitektur & Spesifikasi Server

### 1.1 Topologi Layanan

```
                         Internet (HTTPS / Port 443)
                                      │
                                      ▼
                        ┌───────────────────────────┐
                        │  Caddy / Nginx TLS Proxy  │
                        │ (Let's Encrypt Auto SSL)  │
                        └─────────────┬─────────────┘
                                      │
                   ┌──────────────────┴──────────────────┐
                   ▼                                     ▼
        ┌───────────────────────┐             ┌───────────────────────┐
        │ Next.js 16 (Node 22)  │             │   Laravel 12 (PHP 8.4)│
        │ Frontend (Port 3000)  │             │   API / Core (Port 8000)
        └───────────────────────┘             └───────────┬───────────┘
                                                          │
                                     ┌────────────────────┴────────────────────┐
                                     ▼                                         ▼
                          ┌─────────────────────┐                   ┌─────────────────────┐
                          │   MySQL 8.0 Innodb  │                   │  Redis 7 (In-Memory)│
                          │ Transaksi & Katalog │                   │ Cache, Queue, Lock  │
                          └─────────────────────┘                   └─────────────────────┘
                                                                               ▲
                                                                               │
                                                       ┌───────────────────────┴───────────────────────┐
                                                       │  Laravel Horizon / Background Workers         │
                                                       │  - Queue Worker (Email, Webhook, Outbox)      │
                                                       │  - Scheduler Daemon (Heartbeat, Expiry, Sync) │
                                                       └───────────────────────────────────────────────┘
```

### 1.2 Rekomendasi Spesifikasi Hardware

| Komponen | Spesifikasi Minimum (Pilot / Staging) | Rekomendasi Produksi (High Traffic) |
|---|---|---|
| **OS** | Ubuntu 24.04 LTS (x86_64 atau ARM64) | Ubuntu 24.04 LTS (x86_64 atau ARM64) |
| **vCPU** | 2 Core | 4 - 8 Core |
| **RAM** | 4 GB | 8 - 16 GB |
| **Penyimpanan** | 40 GB NVMe SSD | 120+ GB NVMe SSD |
| **Jaringan** | 1 Gbps port, bandwidth 1 TB/bulan | 1 Gbps port unmetered, DDoS protection |
| **Backup Storage** | AWS S3 / Cloudflare R2 (Off-site) | AWS S3 / Cloudflare R2 (Versioning & Object Lock) |

---

## 2. Persiapan & Hardening Server Ubuntu 24.04 LTS

### 2.1 Pembaruan Sistem Operasi & Paket Dasar

Login sebagai `root` melalui SSH, lalu jalankan pembaruan sistem:

```bash
apt update && apt upgrade -y
apt install -y curl wget git ufw htop fail2ban unzip zip jq certbot ca-certificates
```

### 2.2 Membuat Akun Operator Non-Root

```bash
# Buat user baru (misal: 'deployer')
adduser deployer
usermod -aG sudo deployer

# Salin SSH keys dari root ke user deployer
mkdir -p /home/deployer/.ssh
cp /root/.ssh/authorized_keys /home/deployer/.ssh/
chown -R deployer:deployer /home/deployer/.ssh
chmod 700 /home/deployer/.ssh
chmod 600 /home/deployer/.ssh/authorized_keys
```

### 2.3 Hardening SSH & Firewall (UFW)

Edit konfigurasi SSH di `/etc/ssh/sshd_config.d/hardening.conf`:

```ini
PermitRootLogin no
PasswordAuthentication no
X11Forwarding no
MaxAuthTries 4
```

Restart SSH daemon:

```bash
systemctl restart ssh
```

Konfigurasi firewall UFW:

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

### 2.4 Memasang Docker Engine & Docker Compose Plugin

```bash
# Tambahkan repositori resmi Docker
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  tee /etc/apt/sources.list.d/docker.list > /dev/null

apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Berikan izin ke user deployer
usermod -aG docker deployer
```

Keluar dari root dan login kembali sebagai `deployer`.

---

## 3. Deployment Menggunakan Docker Stack (Rekomendasi)

### 3.1 Struktur Direktori di Server

```bash
sudo mkdir -p /opt/wisata
sudo chown deployer:deployer /opt/wisata
cd /opt/wisata
git clone <URL_REPOSITORI> .
```

### 3.2 Menyiapkan File `.env` Produksi Backend

Buat file `/opt/wisata/backend/.env` dengan izin baca ketat (`chmod 600 /opt/wisata/backend/.env`):

```ini
APP_NAME="Wisata Daerah"
APP_ENV=production
APP_KEY=base64:... # Generate via: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://api.wisatadaerah.id

FRONTEND_URL=https://wisatadaerah.id
SANCTUM_STATEFUL_DOMAINS=wisatadaerah.id,api.wisatadaerah.id
CORS_ALLOWED_ORIGINS=https://wisatadaerah.id

LOG_CHANNEL=daily
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=wisata_prod
DB_USERNAME=wisata_user
DB_PASSWORD=<PASSWORD_MYSQL_KUAT_MIN_24_KARAKTER>

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.wisatadaerah.id
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PASSWORD=<PASSWORD_REDIS_KUAT>
REDIS_PORT=6379

# Transaksi & Notifikasi Outbox
TRANSACTION_NOTICES_ENABLED=true
MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com # atau AWS SES / Resend
MAIL_PORT=587
MAIL_USERNAME=<POSTMARK_TOKEN>
MAIL_PASSWORD=<POSTMARK_TOKEN>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@wisatadaerah.id
MAIL_FROM_NAME="Wisata Daerah"

# Gateway Pembayaran Midtrans (Live / Production)
MIDTRANS_SERVER_KEY=Mid-server-...
MIDTRANS_CLIENT_KEY=Mid-client-...
MIDTRANS_IS_PRODUCTION=true
MIDTRANS_PAYMENT_URL=https://app.midtrans.com/snap/v1/transactions

# Payout Provider Midtrans Iris (Live / Production)
MIDTRANS_IRIS_BASE_URL=https://app.midtrans.com/iris/api/v1
MIDTRANS_IRIS_API_KEY=IRIS-...
MIDTRANS_IRIS_CREATOR_KEY=IRIS-CREATOR-...
MIDTRANS_IRIS_APPROVER_KEY=IRIS-APPROVER-...
PAYOUT_GATEWAY_DRIVER=iris

# Object Storage S3 / Cloudflare R2
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<R2_OR_S3_KEY>
AWS_SECRET_ACCESS_KEY=<R2_OR_S3_SECRET>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=wisata-media-production
AWS_URL=https://media.wisatadaerah.id
AWS_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=false

# Audit & Health
HEALTH_FAILED_JOBS_THRESHOLD=0
```

### 3.3 Menyiapkan File `.env.local` Frontend

Buat file `/opt/wisata/frontend/.env.local`:

```ini
NEXT_PUBLIC_API_URL=https://api.wisatadaerah.id/api/v1
NEXT_PUBLIC_SITE_URL=https://wisatadaerah.id
```

### 3.4 File Docker Compose Produksi (`compose.prod.yaml`)

Simpan sebagai `/opt/wisata/compose.prod.yaml`:

```yaml
services:
  mysql:
    image: mysql:8.0
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: "${MYSQL_ROOT_PASSWORD}"
      MYSQL_DATABASE: "${DB_DATABASE}"
      MYSQL_USER: "${DB_USERNAME}"
      MYSQL_PASSWORD: "${DB_PASSWORD}"
    volumes:
      - mysql_prod_data:/var/lib/mysql
    networks:
      - internal
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    command: ["redis-server", "--requirepass", "${REDIS_PASSWORD}"]
    volumes:
      - redis_prod_data:/data
    networks:
      - internal
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  backend:
    build:
      context: .
      dockerfile: infra/docker/backend.Dockerfile
    restart: unless-stopped
    env_file: backend/.env
    volumes:
      - private_media:/var/www/storage/app/private
    networks:
      - internal
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy

  worker:
    build:
      context: .
      dockerfile: infra/docker/backend.Dockerfile
    restart: unless-stopped
    command: ["php", "artisan", "queue:work", "--sleep=3", "--tries=3", "--max-time=3600"]
    env_file: backend/.env
    networks:
      - internal
    depends_on:
      backend:
        condition: service_started

  scheduler:
    build:
      context: .
      dockerfile: infra/docker/backend.Dockerfile
    restart: unless-stopped
    command: ["sh", "-c", "while true; do php artisan schedule:run --verbose --no-interaction & sleep 60; done"]
    env_file: backend/.env
    networks:
      - internal
    depends_on:
      backend:
        condition: service_started

  frontend:
    build:
      context: frontend
      dockerfile: ../infra/docker/frontend.Dockerfile
    restart: unless-stopped
    env_file: frontend/.env.local
    networks:
      - internal

  proxy:
    image: caddy:2-alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./infra/caddy/Caddyfile.prod:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
    networks:
      - internal
    depends_on:
      - backend
      - frontend

volumes:
  mysql_prod_data:
  redis_prod_data:
  private_media:
  caddy_data:
  caddy_config:

networks:
  internal:
    driver: bridge
```

### 3.5 Caddyfile Produksi dengan Auto-HTTPS

Simpan sebagai `/opt/wisata/infra/caddy/Caddyfile.prod`:

```caddyfile
wisatadaerah.id {
    header {
        -Server
        -X-Powered-By
        Permissions-Policy "camera=(self), microphone=(), geolocation=()"
        Referrer-Policy "strict-origin-when-cross-origin"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "DENY"
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    }

    # Static assets Next.js caching
    @static {
        path /_next/static/* /images/* /favicon.ico /icons/*
    }
    header @static Cache-Control "public, max-age=31536000, immutable"

    reverse_proxy frontend:3000
}

api.wisatadaerah.id {
    header {
        -Server
        -X-Powered-By
        Referrer-Policy "no-referrer"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "DENY"
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    }

    reverse_proxy backend:8000 {
        header_up X-Real-IP {remote_host}
        header_up X-Forwarded-For {remote_host}
        header_up X-Forwarded-Proto {scheme}
    }
}
```

---

## 4. Langkah Deployment Perdana (Initial Launch)

Jalankan perintah berikut di direktori `/opt/wisata`:

```bash
# 1. Jalankan container database & redis
docker compose -f compose.prod.yaml up -d mysql redis

# 2. Jalankan migrasi skema database
docker compose -f compose.prod.yaml run --rm backend php artisan migrate --force

# 3. Optimasi cache framework Laravel
docker compose -f compose.prod.yaml run --rm backend php artisan config:cache
docker compose -f compose.prod.yaml run --rm backend php artisan route:cache
docker compose -f compose.prod.yaml run --rm backend php artisan view:cache

# 4. Bangun dan jalankan seluruh container
docker compose -f compose.prod.yaml up -d --build

# 5. Verifikasi status kesehatan (Smoke Check)
curl -I https://wisatadaerah.id/
curl https://api.wisatadaerah.id/up
curl https://api.wisatadaerah.id/api/v1/health/dependencies
```

---

## 5. Konfigurasi Alternatif Standalone (Nginx + PHP-FPM 8.4 + Systemd)

Bagi organisasi yang mengharuskan deployment tanpa Docker container:

### 5.1 Instalasi PHP 8.4 & Nginx di Ubuntu 24.04

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y nginx php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-gd php8.4-intl
```

### 5.2 Konfigurasi Nginx Server Block

Simpan di `/etc/nginx/sites-available/wisata`:

```nginx
server {
    listen 80;
    server_name wisatadaerah.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name wisatadaerah.id;

    ssl_certificate /etc/letsencrypt/live/wisatadaerah.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/wisatadaerah.id/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers (Allowing Camera on /petugas)
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(self), microphone=(), geolocation=()" always;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }
}

server {
    listen 443 ssl http2;
    server_name api.wisatadaerah.id;
    root /opt/wisata/backend/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/wisatadaerah.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/wisatadaerah.id/privkey.pem;

    client_max_body_size 8M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan dan reload Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/wisata /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 5.3 Systemd Services untuk Worker & Scheduler

**1. Queue Worker (`/etc/systemd/system/wisata-worker.service`)**:

```ini
[Unit]
Description=Wisata Daerah Queue Worker
After=network.target

[Service]
User=deployer
Group=deployer
Restart=always
ExecStart=/usr/bin/php8.4 /opt/wisata/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
RestartSec=5s

[Install]
WantedBy=multi-user.target
```

**2. Scheduler Timer (`/etc/systemd/system/wisata-scheduler.timer`)**:

```ini
[Unit]
Description=Wisata Daerah Scheduler Timer

[Timer]
OnCalendar=*:*
AccuracySec=1s
Persistent=true

[Install]
WantedBy=timers.target
```

**3. Scheduler Service (`/etc/systemd/system/wisata-scheduler.service`)**:

```ini
[Unit]
Description=Wisata Daerah Scheduler Run
After=network.target

[Service]
User=deployer
Group=deployer
Type=oneshot
ExecStart=/usr/bin/php8.4 /opt/wisata/backend/artisan schedule:run
```

Aktifkan kedua service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now wisata-worker.service
sudo systemctl enable --now wisata-scheduler.timer
```

---

## 6. Automasi Backup & Disaster Recovery Runbook

### 6.1 Script Backup Otomatis Harian (`/opt/wisata/scripts/backup-production.sh`)

Buat file executable:

```bash
#!/usr/bin/env bash
set -euo pipefail

BACKUP_ROOT="/var/backups/wisata"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TARGET_DIR="${BACKUP_ROOT}/${TIMESTAMP}"
mkdir -p "${TARGET_DIR}"
chmod 700 "${TARGET_DIR}"

echo "[$(date)] Memulai proses backup produksi..."

# 1. Dump Database MySQL
docker compose -f /opt/wisata/compose.prod.yaml exec -T mysql \
  mysqldump --single-transaction --routines --triggers -uroot -p"${MYSQL_ROOT_PASSWORD}" wisata_prod \
  | gzip > "${TARGET_DIR}/database_${TIMESTAMP}.sql.gz"

# 2. Backup Private Storage (Lampiran Tiket & Manifest)
tar -C /opt/wisata/backend/storage/app -czf "${TARGET_DIR}/private_storage_${TIMESTAMP}.tar.gz" private

# 3. Checksum
sha256sum "${TARGET_DIR}"/*.gz > "${TARGET_DIR}/SHA256SUMS"

# 4. Upload ke Bucket Off-Site (Cloudflare R2 / AWS S3)
aws s3 sync "${TARGET_DIR}" "s3://wisata-backups-offsite/${TIMESTAMP}" \
  --endpoint-url "https://${AWS_R2_ACCOUNT_ID}.r2.cloudflarestorage.com"

# 5. Rotasi Backup Lokal (Hapus data lokal lebih dari 7 hari)
find "${BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d -mtime +7 -exec rm -rf {} +

echo "[$(date)] Backup selesai dan tersinkronkan ke off-site storage."
```

Pasang pada Crontab (`crontab -e` milik user `deployer`):

```cron
0 2 * * * /opt/wisata/scripts/backup-production.sh >> /var/log/wisata-backup.log 2>&1
```

### 6.2 Prosedur Latihan Pemulihan Bencana (Disaster Recovery Drill)

Jalankan simulasi pemulihan setiap kuartal ke database uji coba:

```bash
# 1. Unduh arsip backup terbaru
LATEST_BACKUP="/var/backups/wisata/20261009_020000"

# 2. Verifikasi keutuhan berkas
sha256sum -c "${LATEST_BACKUP}/SHA256SUMS"

# 3. Pulihkan ke database uji coba terisolasi (wisata_restore_drill)
./infra/scripts/restore-drill-local.sh "${LATEST_BACKUP}/database_20261009_020000.sql"
```

---

## 7. Prosedur Zero-Downtime Rollback & Deployment

### 7.1 Aturan Migrasi Skema Database (Expand & Contract)

Semua migrasi database produksi **WAJIB** menerapkan prinsip kompatibilitas ke belakang (backward-compatibility):
1. **Fase Expand (Rilis N)**: Tambahkan kolom atau tabel baru sebagai `nullable` atau memiliki nilai `default`.
2. **Fase Dual-Write (Rilis N+1)**: Aplikasi membaca dan menulis ke skema baru.
3. **Fase Contract (Rilis N+2)**: Hapus kolom lama yang sudah tidak lagi dibaca oleh versi mana pun.
> **PENTING**: Jangan pernah menjalankan `php artisan migrate:rollback` di database produksi yang aktif karena berisiko menghapus data transaksi terbaru yang ditulis oleh pengguna!

### 7.2 Prosedur Fast Rollback Aplikasi

Jika rilis baru mengalami error kritikal, lakukan rollback instan ke commit atau tag stabil sebelumnya dalam kurang dari 60 detik:

```bash
cd /opt/wisata

# 1. Checkout commit SHA atau Git tag stabil sebelumnya
PREVIOUS_STABLE_COMMIT="v1.2.0" # atau commit hash
git fetch origin
git checkout --detach "${PREVIOUS_STABLE_COMMIT}"

# 2. Bangun ulang container backend & frontend
docker compose -f compose.prod.yaml build backend frontend

# 3. Reload cache aplikasi
docker compose -f compose.prod.yaml exec -T backend php artisan optimize:clear
docker compose -f compose.prod.yaml exec -T backend php artisan config:cache
docker compose -f compose.prod.yaml exec -T backend php artisan route:cache
docker compose -f compose.prod.yaml exec -T backend php artisan view:cache

# 4. Restart container frontend dan worker
docker compose -f compose.prod.yaml restart frontend worker scheduler

# 5. Validasi kesehatan
curl -f https://api.wisatadaerah.id/up
curl -f https://api.wisatadaerah.id/api/v1/health/dependencies
```

### 7.3 Verifikasi Akhir Kesiapan Produksi (Go-Live Gate)

Sebelum membuka platform untuk transaksi umum:
- [ ] Buka `/dashboard/kesiapan-produksi` menggunakan akun Super Admin.
- [ ] Pastikan seluruh indikator berwarna Hijau: Database OK, Redis Cache OK, Queue OK, Storage OK, Scheduler Heartbeat OK.
- [ ] Pastikan domain menggunakan sertifikat SSL resmi (A+ rating pada SSL Labs).
- [ ] Pastikan kredensial Midtrans Live & Iris Payout telah tervalidasi.
- [ ] Pastikan tombol pilot checkout dibuka secara resmi melalui `/dashboard`.
