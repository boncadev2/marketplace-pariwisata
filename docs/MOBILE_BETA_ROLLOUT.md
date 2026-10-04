# Rencana Rollout Beta Mobile dan Pemeliharaan (Fase 40)

Status 2 Oktober 2026: dokumen ini adalah rencana, belum bukti pelaksanaan. Katalog mobile dan bundle Metro/Hermes tersedia; uji perangkat, upgrade SDK, APK/IPA, Crashlytics, dan distribusi beta belum dilakukan.

## 1. Persiapan Rilis Beta
- **Platform:** Android (Play Store) dan iOS (App Store, jika perlu via TestFlight).
- **Target:** 100 pengguna internal dan mitra percontohan awal.
- **Monitoring:** Menggunakan Crashlytics (via Firebase) untuk mencatat crash aplikasi dan Exception Analytics.

## 2. Proses Submission (Play Store / App Store)
- **App Bundle/APK:** Build aplikasi via EAS (Expo Application Services). `eas build -p android --profile production`
- **Review App:** Pastikan tidak ada konten placeholder atau dummy data yang bocor ke user produksi. Gunakan API Production (Staging API untuk internal test).
- **Privacy Policy:** Tautkan kebijakan privasi yang berlaku untuk produk marketplace pariwisata.

## 3. Kompatibilitas Versi API
- **Versi API:** Gunakan URL versi, misal `/api/v1/`.
- **Force Update:** Implementasikan pengecekan minimal versi aplikasi saat aplikasi mobile start. Jika versi terlalu tua, paksa pengguna mengupdate dari app store.
- **Backward Compatibility:** Backend tidak boleh memecah endpoint `v1` yang sedang dipakai oleh aplikasi yang ada di user sebelum update terdistribusi penuh.

## 4. Feedback & Dukungan
- User dapat melaporkan bug langsung dari dalam aplikasi mobile atau melalui kontak support.
- Tiket bug dikategorikan dan dievaluasi setiap minggu.
