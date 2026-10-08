import "./globals.css";

const siteUrl = process.env.NEXT_PUBLIC_APP_URL || "https://wisatadaerah.id";

export const viewport = {
  themeColor: "#059669",
  width: "device-width",
  initialScale: 1,
  maximumScale: 5,
};

export const metadata = {
  metadataBase: new URL(siteUrl),
  icons: {
    icon: "/icon.svg",
    shortcut: "/icon.svg",
    apple: "/icon.svg",
  },
  manifest: "/manifest.webmanifest",
  title: {
    default: "WisataDaerah — Destinasi, Penginapan & Pengalaman Lokal",
    template: "%s | WisataDaerah",
  },
  description:
    "Platform pariwisata daerah terintegrasi. Temukan tiket destinasi wisata, homestay & penginapan, paket tour seru, kuliner khas, dan produk UMKM lokal.",
  keywords: [
    "wisata daerah",
    "pariwisata indonesia",
    "desa wisata",
    "paket wisata",
    "homestay",
    "penginapan daerah",
    "kuliner lokal",
    "produk UMKM",
    "tiket wisata",
  ],
  authors: [{ name: "WisataDaerah Team" }],
  creator: "WisataDaerah",
  openGraph: {
    type: "website",
    locale: "id_ID",
    url: siteUrl,
    siteName: "WisataDaerah",
    title: "WisataDaerah — Destinasi, Penginapan & Pengalaman Lokal",
    description:
      "Jelajahi keindahan Indonesia lewat platform pariwisata daerah. Pesan tiket wisata, penginapan, paket tur, kuliner, dan oleh-oleh UMKM dengan mudah dan aman.",
  },
  twitter: {
    card: "summary_large_image",
    title: "WisataDaerah — Destinasi, Penginapan & Pengalaman Lokal",
    description:
      "Temukan tiket destinasi, penginapan, paket tour seru, kuliner khas, dan produk UMKM lokal.",
  },
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      "max-video-preview": -1,
      "max-image-preview": "large",
      "max-snippet": -1,
    },
  },
};

export default function RootLayout({ children }) {
  return (
    <html lang="id">
      <body>{children}</body>
    </html>
  );
}
