import Link from "next/link";
import {
  ArrowRight,
  ArrowUpRight,
  BedDouble,
  CalendarDays,
  Store,
  Compass,
  Map,
  MapPin,
  Search,
  Utensils,
  HeartHandshake,
  Ticket,
  Headphones,
} from "lucide-react";
import { Shell } from "../components/Shell";
import { HomeDestinations } from "../components/HomeDestinations";
import { HomeHero } from "../components/HomeHero";

const categories = [
  { href: "/destinasi", label: "Destinasi", icon: Compass },
  { href: "/penginapan", label: "Penginapan", icon: BedDouble },
  { href: "/paket", label: "Paket wisata", icon: Map },
  { href: "/umkm", label: "Produk UMKM", icon: Store },
  { href: "/kuliner", label: "Kuliner lokal", icon: Utensils },
];

export default function HomePage() {
  return (
    <Shell home>
      <HomeHero />
      <section className="home-search" aria-label="Rencanakan perjalanan">
        <nav className="search-categories" aria-label="Pilihan pengalaman">
          {categories.map(({ href, label, icon: Icon }, index) => (
            <Link
              href={href}
              key={href}
              className={index === 0 ? "search-category-primary" : ""}
            >
              <Icon size={19} />
              <span>{label}</span>
            </Link>
          ))}
        </nav>
        <form action="/destinasi" className="home-search-form">
          <div>
            <Search size={23} />
            <label htmlFor="home-destination">
              Mau jelajah ke mana?
              <input
                id="home-destination"
                type="search"
                name="q"
                maxLength={100}
                placeholder="Cari destinasi atau nama tempat"
              />
            </label>
          </div>
          <button type="submit" className="ui-button ui-button-blue">
            Cari wisata <ArrowRight size={18} />
          </button>
        </form>
        <div className="search-suggestions">
          <span>Ide perjalanan:</span>
          {["Air Terjun", "Kampung", "Pantai"].map((term) => (
            <Link key={term} href={`/destinasi?q=${encodeURIComponent(term)}`}>
              {term}
              <ArrowUpRight size={11} />
            </Link>
          ))}
        </div>
      </section>
      <div className="site-container">
        <section
          className="home-benefits"
          aria-label="Rencanakan dengan nyaman"
        >
          {[
            {
              icon: Compass,
              title: "Pengalaman lokal",
              body: "Jelajahi alam dan budaya daerah.",
            },
            {
              icon: CalendarDays,
              title: "Rencana lebih jelas",
              body: "Periksa tanggal, harga, dan ketersediaan.",
            },
            {
              icon: Headphones,
              title: "Bantuan perjalanan",
              body: "Kelola pertanyaan dari satu tempat.",
            },
          ].map(({ icon: Icon, title, body }) => (
            <div key={title}>
              <span>
                <Icon size={23} />
              </span>
              <div>
                <h2>{title}</h2>
                <p>{body}</p>
              </div>
            </div>
          ))}
        </section>
        <section className="home-section">
          <div className="section-title">
            <div>
              <p className="section-kicker">INSPIRASI PERJALANAN</p>
              <h2>Sedikit jeda, banyak cerita.</h2>
              <p>Pilih destinasi dan lihat rincian kunjungan Anda.</p>
            </div>
            <Link href="/destinasi">
              Jelajahi destinasi <ArrowRight size={17} />
            </Link>
          </div>
          <HomeDestinations />
        </section>
        <section className="home-section">
          <div className="section-title">
            <div>
              <p className="section-kicker">SUSUN PERJALANAN ANDA</p>
              <h2>Lengkapi pengalaman liburan.</h2>
            </div>
          </div>
          <div className="experience-grid">
            <Link
              href="/penginapan"
              className="experience-card experience-stay"
            >
              <div>
                <span className="experience-icon">
                  <BedDouble size={24} />
                </span>
                <h3>
                  Tempat nyaman
                  <br />
                  untuk beristirahat.
                </h3>
                <p>Temukan penginapan dan periksa tanggal menginap.</p>
                <span className="experience-link">
                  Cari penginapan <ArrowRight size={16} />
                </span>
              </div>
            </Link>
            <Link href="/paket" className="experience-card experience-tour">
              <div>
                <span className="experience-icon">
                  <Map size={24} />
                </span>
                <h3>
                  Sehari menjelajah,
                  <br />
                  kenangan selamanya.
                </h3>
                <p>Kenali itinerary dan pengalaman wisata desa.</p>
                <span className="experience-link">
                  Lihat paket wisata <ArrowRight size={16} />
                </span>
              </div>
            </Link>
            <Link href="/kuliner" className="experience-card experience-food">
              <div>
                <span className="experience-icon">
                  <Utensils size={24} />
                </span>
                <h3>
                  Rasa lokal,
                  <br />
                  cerita istimewa.
                </h3>
                <p>Pilih paket makan untuk melengkapi perjalanan.</p>
                <span className="experience-link">
                  Jelajahi kuliner <ArrowRight size={16} />
                </span>
              </div>
            </Link>
          </div>
        </section>
        <section className="home-account-banner">
          <span className="account-banner-icon">
            <Ticket size={42} strokeWidth={1.4} />
          </span>
          <div>
            <p className="section-kicker">SATU AKUN, PERJALANAN LEBIH RAPI</p>
            <h2>Simpan rencana. Nikmati perjalanannya.</h2>
            <p>
              Temukan pesanan, wishlist, dan voucher perjalanan di akun Anda.
            </p>
          </div>
          <Link href="/akun" className="ui-button ui-button-white">
            Buka akun saya <ArrowRight size={17} />
          </Link>
        </section>
        <div className="home-local-note">
          <HeartHandshake size={19} />
          <p>
            Setiap perjalanan adalah kesempatan mengenal daerah lebih dekat.
          </p>
        </div>
      </div>
    </Shell>
  );
}
