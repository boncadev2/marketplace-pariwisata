import Link from "next/link";
import {
  ArrowLeft,
  ArrowUpRight,
  MapPin,
  Compass,
  ChevronRight,
} from "lucide-react";
import { TravelGallery } from "./TravelGallery";
import { PlaceLocation } from "./PlaceLocation";
import { WishlistButton } from "./WishlistButton";
import { ReviewSection } from "./ReviewSection";
import { Shell } from "./Shell";

export function DestinationDetail({ destination, demo = false }) {
  return (
    <Shell>
      <div className="destination-detail">
        <div className="detail-topline">
          <div>
            <span className="heading-kicker text-blue-700">
              {demo
                ? "Pratinjau · data demonstrasi"
                : destination.category?.name || "Destinasi wisata"}
            </span>
            <h1>{destination.name}</h1>
            <p>
              <MapPin size={15} />
              {destination.region?.name || "Wilayah wisata"}
            </p>
          </div>
          <div style={{ display: "flex", gap: "0.5rem", alignItems: "center", flexWrap: "wrap" }}>
            {!demo && destination.slug && (
              <WishlistButton
                destinationSlug={destination.slug}
                destinationName={destination.name}
              />
            )}
            <Link href="/destinasi" className="ui-button ui-button-outline">
              <ArrowLeft size={16} /> Kembali ke destinasi
            </Link>
          </div>
        </div>
        <TravelGallery photos={destination.photos} name={destination.name} />
        <div className="detail-content-grid">
          <section className="detail-description">
            <nav className="detail-anchor-nav" aria-label="Informasi destinasi">
              <a href="#tentang-destinasi">Tentang destinasi</a>
              <a href="#lokasi-destinasi">Lokasi & akses</a>
              <a href="#ulasan-destinasi">Ulasan pengunjung</a>
            </nav>
            <div id="tentang-destinasi">
              <p className="section-kicker">KENALI TUJUAN ANDA</p>
              <h2>Pengalaman yang menanti.</h2>
              {destination.summary && (
                <p className="detail-summary">{destination.summary}</p>
              )}
              <p className="detail-description-text">
                {destination.description ||
                  "Deskripsi lengkap belum tersedia. Periksa kembali informasi sebelum merencanakan kunjungan."}
              </p>
            </div>
            <div id="lokasi-destinasi">
              <PlaceLocation
                place={{
                  ...destination,
                  location: destination.address,
                  location_is_demo: demo || destination.location_is_demo,
                }}
              />
            </div>
            <div id="ulasan-destinasi">
              <ReviewSection destinationSlug={destination.slug} />
            </div>
          </section>
          <aside className="detail-plan">
            <span className="detail-plan-icon">
              <Compass size={26} />
            </span>
            <h2>Susun perjalanan Anda.</h2>
            <p>
              Lengkapi kunjungan dengan penginapan, paket wisata, dan kuliner
              daerah.
            </p>
            <Link
              href={
                destination.slug
                  ? `/paket?destinasi=${encodeURIComponent(destination.slug)}`
                  : "/paket"
              }
              className="ui-button"
            >
              Lihat paket <ChevronRight size={16} />
            </Link>
            <Link href="/penginapan">
              Cari penginapan <ArrowUpRight size={14} />
            </Link>
            <Link href="/kuliner">
              Temukan kuliner <ArrowUpRight size={14} />
            </Link>
            {demo && (
              <p className="detail-demo-note">
                Halaman ini adalah pratinjau desain dengan data contoh, belum
                menjadi penawaran yang dapat dipesan.
              </p>
            )}
          </aside>
        </div>
      </div>
    </Shell>
  );
}
