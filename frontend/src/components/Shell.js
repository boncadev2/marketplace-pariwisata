import Link from "next/link";
import {
  ArrowUpRight,
  Compass,
  MapPin,
  ChevronRight,
  HeartHandshake,
} from "lucide-react";
import { Brand, RouteNavigation, SiteNavigation } from "./SiteNavigation";

export function Shell({ children, home = false }) {
  return (
    <div className="travel-app">
      <a href="#main-content" className="skip-link">
        Lewati ke konten utama
      </a>
      <SiteNavigation />
      <main
        id="main-content"
        tabIndex={-1}
        className={home ? "home-main" : "site-container page-main"}
      >
        <RouteNavigation />
        {children}
      </main>
      <footer className="site-footer">
        <div className="site-container footer-grid">
          <div className="footer-brand">
            <Brand />
            <p>
              Perjalanan yang berkesan dimulai dari tempat yang dekat. Temukan
              alam, budaya, dan cerita baru bersama daerah.
            </p>
            <span>
              <HeartHandshake size={18} /> Dukung pengalaman wisata lokal
            </span>
          </div>
          <div>
            <h3>Jelajahi</h3>
            {[
              ["/destinasi", "Destinasi wisata"],
              ["/penginapan", "Penginapan"],
              ["/paket", "Paket wisata"],
              ["/kuliner", "Kuliner lokal"],
              ["/umkm", "Produk UMKM"],
            ].map(([href, label]) => (
              <Link key={href} href={href}>
                {label}
              </Link>
            ))}
          </div>
          <div>
            <h3>Perjalanan Anda</h3>
            {[
              ["/akun", "Akun & pesanan"],
              ["/daftar-mitra", "Daftar mitra"],
              ["/akun/umkm", "Pesanan UMKM"],
              ["/akun/reservasi", "Reservasi penginapan & kuliner"],
              ["/voucher", "Voucher perjalanan"],
              ["/bantuan", "Pusat bantuan"],
            ].map(([href, label]) => (
              <Link key={href} href={href}>
                {label}
              </Link>
            ))}
          </div>
          <div>
            <h3>Bersama mitra</h3>
            <Link href="/dashboard">
              Portal pengelola <ArrowUpRight size={14} />
            </Link>
            <Link href="/petugas">Validasi kunjungan</Link>
            <Link href="/login">Masuk ke portal</Link>
          </div>
        </div>
        <div className="site-container footer-bottom">
          <span>© {new Date().getFullYear()} WisataDaerah</span>
          <span>
            Dibuat untuk perjalanan yang lebih berarti. <Compass size={14} />
          </span>
        </div>
      </footer>
    </div>
  );
}

export function Card({
  title,
  meta,
  price,
  href,
  children,
  image = "https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=600&q=80",
  illustration = true,
  actionLabel = "Lihat detail",
}) {
  return (
    <article className="travel-card">
      <Link
        href={href || "/destinasi"}
        className="travel-card-image"
        aria-label={`Lihat ${title}`}
      >
        {/* Partner images do not yet have a hostname allowlist. */}
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={image}
          alt={illustration ? `Ilustrasi wisata untuk ${title}` : title}
          loading="lazy"
          decoding="async"
          width={600}
          height={400}
        />
        {illustration && <span className="image-caption">Ilustrasi</span>}
      </Link>
      <div className="travel-card-body">
        {meta && (
          <p className="travel-card-meta">
            <MapPin size={13} />
            {meta}
          </p>
        )}
        <h2>
          <Link href={href || "/destinasi"}>{title}</Link>
        </h2>
        {price && <p className="travel-card-price">{price}</p>}
        <div className="travel-card-bottom">
          <Link href={href || "/destinasi"}>
            {actionLabel} <ChevronRight size={15} />
          </Link>
          {children}
        </div>
      </div>
    </article>
  );
}
