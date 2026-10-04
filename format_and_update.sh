#!/bin/bash
cd backend
vendor/bin/pint --dirty --format agent
cd ..

TEMP_FILE=$(mktemp)
cat << 'MD' > "$TEMP_FILE"
## Fase 33, 34, dan 35: Pilot Seeder, Penginapan, dan Kuliner

Status: selesai pada 29 September 2026.

Keluaran yang dibuat:
- **Fase 33 (Pilot)**: `PilotDatabaseSeeder` yang menyuntikkan data fiktif realistis untuk peluncuran pilot, termasuk user Admin, Staff, Destinasi, dan Tiket reguler/kendaraan.
- **Fase 34 (Penginapan/Accommodations)**: Model dan migrasi untuk `RoomType`, `RoomInventory`, `RoomRate`, dan `LodgingBooking` untuk menangani stok dan pemesanan kamar menginap.
- **Fase 35 (Kuliner)**: Model dan migrasi untuk `CulinaryPlace` dan `MealSlot` untuk manajemen paket makanan dan slot waktu.

Verifikasi yang dijalankan:
- `vendor/bin/pint --dirty --format agent` untuk merapikan kode.

MD
cat docs/PROGRESS.md >> "$TEMP_FILE"
mv "$TEMP_FILE" docs/PROGRESS.md
