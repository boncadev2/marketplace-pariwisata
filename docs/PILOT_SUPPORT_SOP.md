# SOP Dukungan dan Insiden Pilot

SOP ini digunakan admin, mitra, petugas, finance, dan engineering selama simulasi serta pilot terbatas. Prioritas pertama adalah mencegah transaksi baru menambah dampak, menjaga bukti, dan memberi informasi yang konsisten kepada pelanggan.

## Severity dan respons awal

| Severity | Contoh | Respons awal | Pemilik utama |
| --- | --- | --- | --- |
| Critical | Oversell, charge/refund/payout ganda, voucher ganda, kebocoran data, ledger berbeda dari provider | Tutup checkout segera, hentikan tindakan manual, page engineering dan finance | Incident commander |
| High | Payment paid tidak menerbitkan voucher, refund tertunda, check-in massal gagal | Tahan alur terdampak, buka incident, rekonsiliasi setiap transaksi | Operasional dan engineering |
| Medium | Notifikasi gagal, satu konten salah, keluhan tanpa dampak dana | Buat tiket, koreksi terkontrol, pantau backlog | Dukungan atau content admin |
| Low | Pertanyaan penggunaan atau kosmetik | Jawab melalui kanal dukungan dan catat pola | Dukungan |

## Menutup checkout

1. Masuk sebagai super admin individual dan buka Dashboard Operasional.
2. Pada Kontrol Insiden Pilot, isi alasan yang menyebut insiden atau keputusan operasi.
3. Isi ulang kata sandi administrator dan pilih **Tutup checkout sekarang**.
4. Pastikan status berubah menjadi Checkout ditutup.
5. Verifikasi `GET /api/v1/pilot/checkout-status` mengembalikan `enabled: false`.
6. Lakukan satu request sintetis ke checkout dan pastikan HTTP 503 `CHECKOUT_CLOSED`; jangan memakai data pelanggan nyata untuk verifikasi.
7. Catat waktu, pelaku, alasan, dan nomor incident. Audit log aplikasi menyimpan perubahan kontrol.

Checkout hanya boleh dibuka kembali oleh super admin setelah incident commander dan finance menyetujui, penyebab diketahui, transaksi terdampak direkonsiliasi, serta alasan pembukaan dicatat.

## Pembayaran bermasalah

1. Cari order melalui dashboard menggunakan ID publik, bukan email penuh.
2. Bandingkan order, payment attempt, webhook, voucher, ledger, dan laporan provider.
3. Jangan menurunkan status paid berdasarkan event pending/unknown.
4. Pada timeout, lookup merchant reference sebelum membuat operasi baru.
5. Bila nominal/reference/currency berbeda, jangan terbitkan voucher dan eskalasi sebagai discrepancy.
6. Jangan meminta pelanggan membayar ulang sampai status provider dipastikan.

## Check in bermasalah

1. Validasi order paid, tanggal layanan, tenant petugas, status voucher, dan audit scan.
2. Jangan membuat voucher baru atau menandai hadir langsung di database.
3. Bila jaringan terputus, catat ID order dan waktu secara terbatas tanpa menyalin token QR; lakukan redeem resmi setelah layanan pulih.
4. Scan kedua harus ditolak. Jika dua check-in tercatat, perlakukan sebagai Critical.

## Pembatalan dan refund

1. Pastikan pemohon memiliki order dan kebijakan snapshot tersedia.
2. Nilai refund tidak boleh melebihi payment terkonfirmasi dikurangi refund sebelumnya.
3. Tindakan finance membutuhkan maker-checker serta konfirmasi kata sandi.
4. Pada timeout provider, cari reference sebelum retry.
5. Rekonsiliasi refund provider dengan jurnal ledger sebelum menutup tiket.

## Komunikasi pelanggan

- Jangan menjanjikan waktu selesai yang belum disetujui.
- Jangan mengirim token, rekening lengkap, payload webhook, atau data pelanggan lain melalui chat.
- Gunakan ID order publik dan status yang telah diverifikasi.
- Catat setiap keputusan, penyesuaian, dan persetujuan pada tiket/audit trail.

## Penutupan insiden

Incident dapat ditutup setelah dampak dibatasi, transaksi diperiksa satu per satu, saldo provider dan ledger cocok, pelanggan terdampak mendapat jawaban, serta tindakan pencegahan masuk backlog dengan pemilik dan tenggat. Insiden Critical memerlukan review tertulis sebelum checkout dibuka kembali.
