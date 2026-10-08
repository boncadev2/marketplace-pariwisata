import Link from "next/link";
import { notFound } from "next/navigation";
import { ArrowLeft, BedDouble, Users, Info } from "lucide-react";
import { Shell } from "../../../components/Shell";
import { EmptyState } from "../../../components/PageHeader";
import { LodgingPhoto } from "../../../components/LodgingCard";
import { PlaceLocation } from "../../../components/PlaceLocation";
import { LodgingBookingForm } from "../../../components/LodgingBookingForm";

export async function generateMetadata({ params }) {
  const { id } = await params;
  if (!/^[1-9][0-9]*$/.test(id)) return { title: "Penginapan" };
  try {
    const backend = process.env.BACKEND_INTERNAL_URL || "http://backend:8000";
    const res = await fetch(
      `${backend}/api/v1/lodging/rooms/${encodeURIComponent(id)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(5000),
      }
    );
    if (!res.ok) return { title: "Penginapan" };
    const { data: room } = await res.json();
    const title = `${room.name} — Penginapan & Homestay`;
    const desc = room.description
      ? room.description.slice(0, 160)
      : `Sewa kamar ${room.name} dengan kapasitas hingga ${room.capacity} tamu. Fasilitas lengkap dan lokasi strategis di WisataDaerah.`;
    const image = room.exterior_image_url || room.interior_image_url || null;

    return {
      title,
      description: desc,
      openGraph: {
        title,
        description: desc,
        images: image ? [{ url: image }] : [],
        type: "article",
      },
      twitter: {
        card: "summary_large_image",
        title,
        description: desc,
        images: image ? [image] : [],
      },
    };
  } catch {
    return { title: "Penginapan" };
  }
}

export default async function Page({ params }) {
  const { id } = await params;
  if (!/^[1-9][0-9]*$/.test(id)) notFound();
  let response;
  try {
    response = await fetch(
      `${process.env.BACKEND_INTERNAL_URL || "http://backend:8000"}/api/v1/lodging/rooms/${encodeURIComponent(id)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(8000),
      }
    );
  } catch {}
  if (response?.status === 404) notFound();
  if (!response?.ok)
    return (
      <Shell>
        <EmptyState
          headingLevel="h1"
          title="Penginapan belum dapat dimuat"
          description="Kembali ke daftar penginapan dan coba lagi."
          href="/penginapan"
          label="Kembali ke penginapan"
        />
      </Shell>
    );
  const { data: room, meta } = await response.json();
  const price =
    room.starting_price === null
      ? null
      : new Intl.NumberFormat("id-ID", {
          style: "currency",
          currency: "IDR",
          maximumFractionDigits: 2,
        }).format(Number(room.starting_price));

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "LodgingBusiness",
    name: room.name,
    description: room.description || "",
    image: room.exterior_image_url || undefined,
    priceRange: price || "IDR",
    address: {
      "@type": "PostalAddress",
      addressLocality: room.address || "",
      addressCountry: "ID",
    },
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <Shell>
        <div className="lodging-detail-heading">
          <div>
            <span className="heading-kicker">
              <BedDouble size={16} /> Detail penginapan
            </span>
            <h1>{room.name}</h1>
            <p>
              <Users size={17} /> Hingga {room.capacity} tamu per kamar
            </p>
          </div>
          <Link href="/penginapan" className="ui-button ui-button-outline">
            <ArrowLeft size={16} /> Semua penginapan
          </Link>
        </div>
        <section className="lodging-detail-gallery" aria-label="Foto penginapan">
          <LodgingPhoto
            src={room.exterior_image_url}
            label="Tampak luar"
            name={room.name}
          />
          <LodgingPhoto
            src={room.interior_image_url}
            label="Interior kamar"
            name={room.name}
          />
        </section>
        {room.photos_are_illustrations && (
          <p className="lodging-detail-photo-note">
            <Info size={15} /> Foto ilustrasi, bukan foto properti nyata.
          </p>
        )}
        <div className="lodging-detail-grid">
          <section className="lodging-detail-information">
            <nav className="detail-anchor-nav" aria-label="Informasi penginapan">
              <a href="#tentang-penginapan">Tentang penginapan</a>
              <a href="#lokasi-tempat">Lokasi & akses</a>
              <a href="#ketersediaan">Periksa ketersediaan</a>
            </nav>
            <div id="tentang-penginapan">
              <span className="heading-kicker">Kenali tempat menginap Anda</span>
              <h2>Tentang penginapan</h2>
              <p className="lodging-detail-description">
                {room.description || "Deskripsi lengkap belum tersedia."}
              </p>
            </div>
            <div className="lodging-detail-rate">
              <span>{price ? "Tarif mulai dari" : "Tarif"}</span>
              <strong>{price || "Belum tersedia"}</strong>
              <span>per kamar / malam</span>
              <p>
                Tarif terendah pada tanggal dengan stok tersedia. Pilih tanggal
                untuk mendapatkan harga menginap yang sesuai.
              </p>
            </div>
            <PlaceLocation place={room} />
          </section>
          <aside>
            <LodgingBookingForm
              key={room.id}
              room={room}
              sandboxEnabled={meta.sandbox_reservations_enabled}
            />
          </aside>
        </div>
      </Shell>
    </>
  );
}
