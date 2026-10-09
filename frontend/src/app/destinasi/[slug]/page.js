import { notFound } from "next/navigation";
import { DestinationDetail } from "../../../components/DestinationDetail";
import { EmptyState } from "../../../components/PageHeader";
import { Shell } from "../../../components/Shell";
import { getBackendUrl } from "../../../lib/api";

export async function generateMetadata({ params }) {
  const { slug } = await params;
  try {
    const backend = getBackendUrl();
    const response = await fetch(
      `${backend}/api/v1/destinations/${encodeURIComponent(slug)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(5000),
      }
    );
    if (!response.ok) return { title: "Destinasi Wisata" };
    const { data: item } = await response.json();
    const title = `${item.name} — Destinasi Wisata`;
    const desc = item.description
      ? item.description.slice(0, 160)
      : `Jelajahi keindahan ${item.name}. Informasi tiket, lokasi, dan paket wisata terlengkap di WisataDaerah.`;
    const image = item.cover_image_url || null;

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
    return { title: "Destinasi Wisata" };
  }
}

export default async function Page({ params }) {
  const { slug } = await params;
  let response;
  try {
    const backend = getBackendUrl();
    response = await fetch(
      `${backend}/api/v1/destinations/${encodeURIComponent(slug)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(8000),
      }
    );
  } catch {
    return (
      <Shell>
        <EmptyState
          headingLevel="h1"
          title="Destinasi belum dapat dimuat"
          description="Layanan katalog belum dapat dihubungi. Kembali ke pencarian dan coba lagi."
          href="/destinasi"
          label="Kembali ke pencarian"
        />
      </Shell>
    );
  }
  if (response.status === 404) notFound();
  if (!response.ok)
    return (
      <Shell>
        <EmptyState
          headingLevel="h1"
          title="Destinasi belum dapat dimuat"
          description="Coba lagi nanti atau temukan destinasi lain."
          href="/destinasi"
        />
      </Shell>
    );
  const { data } = await response.json();

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "TouristAttraction",
    name: data.name,
    description: data.description || "",
    image: data.cover_image_url || undefined,
    address: {
      "@type": "PostalAddress",
      addressLocality: data.city || data.location || "",
      addressRegion: data.province || "",
      addressCountry: "ID",
    },
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <DestinationDetail destination={data} />
    </>
  );
}
