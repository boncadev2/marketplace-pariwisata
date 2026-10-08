export default function manifest() {
  return {
    name: "Wisata Daerah — Marketplace Pariwisata",
    short_name: "WisataDaerah",
    description:
      "Temukan dan pesan tiket destinasi wisata, penginapan homestay, paket tour lokal, kuliner khas, dan produk UMKM daerah.",
    start_url: "/",
    id: "/",
    display: "standalone",
    background_color: "#ffffff",
    theme_color: "#059669",
    orientation: "portrait-primary",
    categories: ["travel", "tourism", "lifestyle"],
    icons: [
      {
        src: "/icon.svg",
        sizes: "any",
        type: "image/svg+xml",
        purpose: "any",
      },
      {
        src: "/icon.svg",
        sizes: "any",
        type: "image/svg+xml",
        purpose: "maskable",
      },
    ],
    shortcuts: [
      {
        name: "E-Voucher & Tiket",
        short_name: "Tiket",
        description: "Buka e-tiket dan voucher QR kunjungan Anda",
        url: "/voucher",
        icons: [{ src: "/icon.svg", sizes: "any" }],
      },
      {
        name: "Jelajahi Destinasi",
        short_name: "Destinasi",
        description: "Temukan tempat wisata alam dan budaya menarik",
        url: "/destinasi",
        icons: [{ src: "/icon.svg", sizes: "any" }],
      },
      {
        name: "Paket Wisata Desa",
        short_name: "Paket Tur",
        description: "Rencana perjalanan terpadu dengan pemandu lokal",
        url: "/paket",
        icons: [{ src: "/icon.svg", sizes: "any" }],
      },
      {
        name: "Akun & Pesanan",
        short_name: "Akun",
        description: "Kelola pesanan dan tiket saya",
        url: "/akun",
        icons: [{ src: "/icon.svg", sizes: "any" }],
      },
    ],
  };
}
