import Link from "next/link";
import { Card, Shell } from "../components/Shell";

export default function Home() {
  return (
    <Shell>
      <section className="hero">
        <p className="eyebrow">Marketplace Pariwisata Daerah</p>
        <h1>Rencanakan perjalanan lokal dengan lebih mudah</h1>
        <p>
          Cari destinasi, pengalaman desa, dan informasi kunjungan dari
          Kabupaten Demo.
        </p>
        <Link className="button" href="/destinasi">
          Cari destinasi
        </Link>
      </section>
      <section className="section">
        <p className="eyebrow">Pilihan demo</p>
        <h2>Mulai dari inspirasi perjalanan</h2>
        <div className="card-grid">
          <Card
            title="Air Terjun Demo"
            meta="Desa Demo"
            price="Gratis"
            href="/destinasi/demo"
          />
          <Card
            title="Kampung Budaya Demo"
            meta="Kabupaten Demo"
            price="Informasi demonstrasi"
          />
          <Card
            title="Sehari di Desa Demo"
            meta="Paket wisata"
            price="Pemesanan belum aktif"
            href="/paket/demo"
          />
        </div>
      </section>
    </Shell>
  );
}
