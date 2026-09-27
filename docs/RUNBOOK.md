# Runbook Pengembangan

## Prasyarat

- Docker Desktop aktif.
- Node.js 20.9 atau lebih baru untuk menjalankan frontend di host; proyek ini telah diverifikasi menggunakan Node.js 24.
- Untuk menjalankan Laravel di host diperlukan PHP 8.4.1 atau lebih baru. Konfigurasi Docker menggunakan PHP 8.4 agar tidak bergantung pada PHP host.

## Menjalankan Lingkungan Lokal

1. Salin konfigurasi aman: `cp .env.example .env` dan `cp frontend/.env.example frontend/.env.local`.
2. Pastikan `backend/.env` hanya berisi konfigurasi lokal dan tidak pernah dikomit.
3. Jalankan `docker compose up --build`.
4. Buka aplikasi melalui `http://localhost:8080`, frontend langsung melalui `http://localhost:3000`, backend melalui `http://localhost:8000/up`, dan Mailpit melalui `http://localhost:8025`.
5. Setelah backend hidup, jalankan migrasi dengan `docker compose exec backend php artisan migrate`.

## Pemeriksaan Dasar

- Frontend: `cd frontend && npm run lint && npm run build`
- Backend: `docker compose exec backend php artisan test`
- Status layanan: `docker compose ps`
- Health check: `curl -f http://localhost:8000/up`

## Konfigurasi Lingkungan

Gunakan file `.env` lokal untuk nilai yang berubah antar lingkungan. Jangan masukkan key provider, password produksi, atau data pribadi ke Git. Nilai pada `.env.example` hanyalah placeholder pengembangan.

## Backup dan Restore

Backup database, restore, dan prosedur insiden akan dirinci pada Fase 32. Sebelum fase tersebut, volume `mysql_data` hanya untuk pengembangan dan tidak dianggap sebagai backup.
