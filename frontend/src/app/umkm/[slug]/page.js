import Image from "next/image";
import { UmkmOrderForm } from "../../../components/UmkmOrderForm";
import { notFound } from "next/navigation";
import { ShoppingBag, MapPin, Store, ArrowUpRight } from "lucide-react";
import { Shell } from "../../../components/Shell";
import { PageHeader, EmptyState } from "../../../components/PageHeader";
import { getBackendUrl } from "../../../lib/api";

export async function generateMetadata({ params }) {
  const { slug } = await params;
  try {
    const backend = getBackendUrl();
    const res = await fetch(
      `${backend}/api/v1/umkm-products/${encodeURIComponent(slug)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(5000),
      }
    );
    if (!res.ok) return { title: "Produk UMKM" };
    const { data: item } = await res.json();
    const title = `${item.name} — Produk UMKM Lokal`;
    const desc = item.description
      ? item.description.slice(0, 160)
      : `Beli produk UMKM khas daerah ${item.name} dari ${item.seller}. Dijamin asli dan berkualitas di WisataDaerah.`;
    const image = item.photo_url || null;

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
    return { title: "Produk UMKM" };
  }
}

export default async function Page({ params }) {
  const { slug } = await params;
  let response;
  try {
    const backend = getBackendUrl();
    response = await fetch(
      `${backend}/api/v1/umkm-products/${encodeURIComponent(slug)}`,
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
          title="Produk belum dapat dimuat"
          description="Kembali ke katalog dan coba lagi."
          href="/umkm"
          label="Kembali ke produk UMKM"
        />
      </Shell>
    );
  const { data: item } = await response.json();
  const price = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(item.price);

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Product",
    name: item.name,
    description: item.description || "",
    image: item.photo_url || undefined,
    offers: {
      "@type": "Offer",
      price: item.price || 0,
      priceCurrency: "IDR",
      availability: "https://schema.org/InStock",
      seller: {
        "@type": "Organization",
        name: item.seller || "",
      },
    },
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <Shell>
        <PageHeader
          eyebrow="Produk UMKM"
          title={item.name}
          description={item.seller}
        />
        <div className="umkm-detail">
          <section className="umkm-detail-content">
            <div className="umkm-art umkm-art-detail">
              {item.photo_url ? (
                <Image
                  src={item.photo_url}
                  alt={item.photo_alt || item.name}
                  width={1200}
                  height={900}
                  unoptimized
                  className="umkm-catalog-photo"
                />
              ) : (
                <>
                  <ShoppingBag size={100} strokeWidth={1} />
                  <span>Produk UMKM · foto belum tersedia</span>
                </>
              )}
            </div>
            <h2>Tentang produk</h2>
            <p className="umkm-full-description">{item.description}</p>
            <h2>Lokasi penjual</h2>
            <p className="umkm-location">
              <MapPin size={18} />
              {item.location}
            </p>
            {!item.is_demo && (
              <a
                className="ui-button ui-button-outline"
                href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(item.location)}`}
                target="_blank"
                rel="noopener noreferrer"
              >
                Cari lokasi di peta <ArrowUpRight size={16} />
              </a>
            )}
          </section>
          <aside className="umkm-buy">
            <span className="umkm-eyebrow">Harga beli</span>
            <strong>{price}</strong>
            <p>Per {item.unit}</p>
            <div className="umkm-seller">
              <Store size={20} />
              {item.seller}
            </div>
            <UmkmOrderForm product={item} />
          </aside>
        </div>
      </Shell>
    </>
  );
}
