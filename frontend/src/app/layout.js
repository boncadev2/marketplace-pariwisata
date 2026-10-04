import "./globals.css";

export const metadata = {
  title: "WisataDaerah — Destinasi, Penginapan & Pengalaman Lokal",
  description:
    "Temukan destinasi, penginapan, paket wisata, dan kuliner daerah. Rencanakan pengalaman lokal bersama WisataDaerah.",
};

export default function RootLayout({ children }) {
  return (
    <html lang="id">
      <body>{children}</body>
    </html>
  );
}
