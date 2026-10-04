"use client";
import Link from "next/link";
import Image from "next/image";
import { useEffect, useState } from "react";
import { Store, MapPin, ArrowRight, Loader2, ShoppingBag } from "lucide-react";
import { Shell } from "./Shell";
import { PageHeader, EmptyState } from "./PageHeader";
import { apiRequest } from "../lib/api";
export const umkmPrice = (price) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(price);
export function UmkmCatalog() {
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    const timer = setTimeout(() => {
      setLoading(true);
      setError("");
      apiRequest(
        `/umkm-products?q=${encodeURIComponent(q)}&page=${page}&per_page=12`,
        { signal: controller.signal }
      )
        .then((data) => {
          if (!controller.signal.aborted) setResult(data);
        })
        .catch(() => {
          if (!controller.signal.aborted)
            setError("Produk belum dapat dimuat. Silakan coba lagi.");
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 250);
    return () => {
      controller.abort();
      clearTimeout(timer);
    };
  }, [q, page, revision]);
  return (
    <Shell>
      <PageHeader
        eyebrow="Produk UMKM"
        title="Karya lokal. Cerita istimewa."
        description="Temukan produk dari usaha lokal. Kenali produknya, lihat lokasi penjual, dan periksa harga per satuan."
      />
      <div className="umkm-search">
        <label htmlFor="umkm-search">Cari produk atau lokasi</label>
        <input
          id="umkm-search"
          value={q}
          onChange={(e) => {
            setQ(e.target.value);
            setPage(1);
          }}
          placeholder="Kopi, kerajinan, atau nama desa"
        />
        <span>
          Dukung usaha lokal <Store size={18} />
        </span>
      </div>
      {loading ? (
        <div className="empty-state" role="status">
          <Loader2 className="mx-auto animate-spin" />
          <p>Memuat produk UMKM…</p>
        </div>
      ) : error ? (
        <div className="empty-state">
          <p role="alert">{error}</p>
          <button
            className="ui-button"
            onClick={() => setRevision(revision + 1)}
          >
            Coba lagi
          </button>
        </div>
      ) : !result?.data?.length ? (
        <EmptyState
          title="Belum ada produk yang cocok"
          description="Coba kata pencarian lain. Produk yang diterbitkan akan tampil di sini."
          href="/umkm"
          label="Lihat katalog"
        />
      ) : (
        <>
          <p className="umkm-count" role="status">
            {result.meta.total} produk ditemukan
          </p>
          <div className="inspiration-grid">
            {result.data.map((item) => (
              <article className="umkm-card" key={item.id}>
                <Link
                  className="umkm-art"
                  href={`/umkm/${item.slug}`}
                  aria-label={`Lihat ${item.name}`}
                >
                  {item.photo_url ? (
                    <Image
                      src={item.photo_url}
                      alt={item.photo_alt || item.name}
                      width={600}
                      height={450}
                      unoptimized
                      className="umkm-catalog-photo"
                    />
                  ) : (
                    <ShoppingBag size={60} strokeWidth={1} />
                  )}
                  <span>{item.is_demo ? "Produk demo" : "Produk lokal"}</span>
                </Link>
                <div className="umkm-card-body">
                  <small>
                    <Store size={14} />
                    {item.seller}
                  </small>
                  <h2>
                    <Link href={`/umkm/${item.slug}`}>{item.name}</Link>
                  </h2>
                  <p className="umkm-description">{item.description}</p>
                  <p className="umkm-location">
                    <MapPin size={15} />
                    {item.location}
                  </p>
                  <strong>
                    {umkmPrice(item.price)} <small>/ {item.unit}</small>
                  </strong>
                  <Link className="umkm-card-link" href={`/umkm/${item.slug}`}>
                    Lihat produk <ArrowRight size={16} />
                  </Link>
                </div>
              </article>
            ))}
          </div>
        </>
      )}
      {result && result.meta.total > 12 && (
        <div className="catalog-pagination">
          <button
            className="ui-button ui-button-outline"
            disabled={loading || page === 1}
            onClick={() => setPage(page - 1)}
          >
            Sebelumnya
          </button>
          <span>Halaman {page}</span>
          <button
            className="ui-button ui-button-outline"
            disabled={loading || page * 12 >= result.meta.total}
            onClick={() => setPage(page + 1)}
          >
            Berikutnya
          </button>
        </div>
      )}
    </Shell>
  );
}
