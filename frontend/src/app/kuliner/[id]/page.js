import Link from "next/link";
import { notFound } from "next/navigation";
import { Utensils, ArrowLeft } from "lucide-react";
import { Shell } from "../../../components/Shell";
import { EmptyState } from "../../../components/PageHeader";
import { CulinaryPhoto } from "../../../components/CulinaryCard";
import { PlaceLocation } from "../../../components/PlaceLocation";
import { CulinaryBookingForm } from "../../../components/CulinaryBookingForm";
import { getBackendUrl } from "../../../lib/api";

export async function generateMetadata({ params }) {
  const { id } = await params;
  if (!/^[1-9][0-9]*$/.test(id)) return { title: "Wisata Kuliner" };
  try {
    const backend = getBackendUrl();
    const res = await fetch(
      `${backend}/api/v1/culinary/places/${encodeURIComponent(id)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(5000),
      }
    );
    if (!res.ok) return { title: "Wisata Kuliner" };
    const { data: place } = await res.json();
    const title = `${place.name} — Wisata Kuliner`;
    const desc = place.description
      ? place.description.slice(0, 160)
      : `Nikmati cita rasa kuliner khas di ${place.name}. Reservasi meja dan paket santap online di WisataDaerah.`;
    const image = place.photo_url || null;

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
    return { title: "Wisata Kuliner" };
  }
}

export default async function Page({ params }) {
  const { id } = await params;
  if (!/^[1-9][0-9]*$/.test(id)) notFound();
  let response;
  try {
    const backend = getBackendUrl();
    response = await fetch(
      `${backend}/api/v1/culinary/places/${id}`,
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
          title="Rumah makan belum dapat dimuat"
          description="Coba kembali dari daftar kuliner."
          href="/kuliner"
          label="Kembali ke kuliner"
        />
      </Shell>
    );
  const { data: place, meta } = await response.json();
  const price =
    place.starting_price === null
      ? null
      : new Intl.NumberFormat("id-ID", {
          style: "currency",
          currency: "IDR",
          maximumFractionDigits: 2,
        }).format(Number(place.starting_price));

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Restaurant",
    name: place.name,
    description: place.description || "",
    image: place.photo_url || undefined,
    priceRange: price || "IDR",
    address: {
      "@type": "PostalAddress",
      addressLocality: place.address || "",
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
              <Utensils size={16} /> Detail rumah makan
            </span>
            <h1>{place.name}</h1>
            <p>Kenali tempatnya, pilih waktu makan Anda.</p>
          </div>
          <Link href="/kuliner" className="ui-button ui-button-outline">
            <ArrowLeft size={16} /> Semua kuliner
          </Link>
        </div>
        <div className="culinary-detail-photo">
          <CulinaryPhoto place={place} />
        </div>
        <div className="lodging-detail-grid">
          <section className="lodging-detail-information">
            <nav className="detail-anchor-nav" aria-label="Informasi rumah makan">
              <a href="#tentang-kuliner">Tentang rumah makan</a>
              <a href="#lokasi-tempat">Lokasi & akses</a>
              <a href="#ketersediaan">Tanggal & jam</a>
            </nav>
            <div id="tentang-kuliner">
              <span className="heading-kicker">Nikmati cita rasa lokal</span>
              <h2>Tentang rumah makan</h2>
              <p className="lodging-detail-description">
                {place.description || "Deskripsi belum tersedia."}
              </p>
            </div>
            <div className="lodging-detail-rate">
              <span>Harga paket mulai dari</span>
              <strong>{price || "Belum tersedia"}</strong>
              <span>per peserta</span>
              <p>
                Pilih tanggal dan jam untuk melihat paket, harga, dan kuota yang
                tersedia.
              </p>
            </div>
            <PlaceLocation place={place} />
          </section>
          <aside>
            <CulinaryBookingForm
              today={
                new Intl.DateTimeFormat("en-CA", {
                  timeZone: "Asia/Jakarta",
                  year: "numeric",
                  month: "2-digit",
                  day: "2-digit",
                }).format(new Date())
              }
              place={place}
              sandboxEnabled={meta.sandbox_reservations_enabled}
            />
          </aside>
        </div>
      </Shell>
    </>
  );
}
