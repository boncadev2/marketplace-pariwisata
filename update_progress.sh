awk '
/^## Fase 18 Checkout dan Pesanan Tamu/ {
    print "## Fase 27 Operational Disputes dan Reviews\n"
    print "Status: selesai pada 28 September 2026.\n"
    print "Keluaran yang dibuat:\n"
    print "- Tabel `operational_disputes` dan model terkait untuk manajemen sengketa operasional antara pelanggan dan layanan."
    print "- Endpoint publik `GET` dan `POST` untuk pelaporan perselisihan pesanan serta update resolusi dan status."
    print "- Tabel `reviews` dan model terkait untuk melacak penilaian dan ulasan pelanggan terhadap produk wisata."
    print "- Factory, migrasi, dan pengujian fitur.\n"
    print "Verifikasi tertunda:\n"
    print "- Pint dan Testing tidak bisa dieksekusi dikarenakan batasan PHP host dan Docker Desktop yang tidak aktif, namun file telah terstruktur.\n"
}
{ print }
' docs/PROGRESS.md > docs/PROGRESS.md.tmp && mv docs/PROGRESS.md.tmp docs/PROGRESS.md
