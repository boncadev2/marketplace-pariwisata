import { TourPackageDetail } from "../../../components/TourPackageDetail";
import { getBackendUrl } from "../../../lib/api";

export async function generateMetadata({ params }) {
  const { slug } = await params;
  try {
    const backend = getBackendUrl();
    const res = await fetch(
      `${backend}/api/v1/tour-packages/${encodeURIComponent(slug)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(5000),
      }
    );
    if (!res.ok) return { title: "Paket Wisata" };
    const { data: item } = await res.json();
    const title = `${item.name} — Paket Wisata`;
    const desc = item.description
      ? item.description.slice(0, 160)
      : `Pesan paket wisata ${item.name}. Dapatkan harga terbaik dan jadwal fleksibel di WisataDaerah.`;
    const image = item.photos?.[0]?.url || item.cover_image_url || null;

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
    return { title: "Paket Wisata" };
  }
}

export default async function Page({ params }) {
  const { slug } = await params;

  let pkgData = null;
  try {
    const backend = getBackendUrl();
    const res = await fetch(
      `${backend}/api/v1/tour-packages/${encodeURIComponent(slug)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(5000),
      }
    );
    if (res.ok) {
      const json = await res.json();
      pkgData = json.data;
    }
  } catch {}

  const jsonLd = pkgData
    ? {
        "@context": "https://schema.org",
        "@type": "Product",
        name: pkgData.name,
        description: pkgData.description || "",
        image: pkgData.photos?.[0]?.url || pkgData.cover_image_url || undefined,
        offers: {
          "@type": "Offer",
          price: pkgData.base_price || 0,
          priceCurrency: "IDR",
          availability: "https://schema.org/InStock",
        },
      }
    : null;

  return (
    <>
      {jsonLd && (
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
        />
      )}
      <TourPackageDetail slug={slug} />
    </>
  );
}
