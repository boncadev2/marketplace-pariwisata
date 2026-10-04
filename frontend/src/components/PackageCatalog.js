"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { Map, ArrowRight, Loader2 } from "lucide-react";
import { Shell, Card } from "./Shell";
import { EmptyState, PageHeader } from "./PageHeader";
import { apiRequest } from "../lib/api";

export function PackageCatalog({ destinationSlug = "" }) {
  const [destinationName, setDestinationName] = useState("");
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState(null);
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    const timer = setTimeout(() => {
      setLoading(true);
      setError("");
      const endpoint = destinationSlug
        ? `/destinations/${encodeURIComponent(destinationSlug)}/packages?per_page=12&page=${page}`
        : `/products?type=package&per_page=12&page=${page}`;
      apiRequest(endpoint, {
        signal: controller.signal,
      })
        .then((result) => {
          if (!controller.signal.aborted) {
            setItems(result.data);
            setMeta(result.meta);
            setDestinationName(result.destination?.name || "");
          }
        })
        .catch(() => {
          if (!controller.signal.aborted)
            setError("Paket belum dapat dimuat. Silakan coba lagi.");
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 0);
    return () => {
      controller.abort();
      clearTimeout(timer);
    };
  }, [page, revision, destinationSlug]);
  return (
    <Shell>
      <PageHeader
        eyebrow="Paket wisata"
        title={
          destinationSlug
            ? "Paket untuk destinasi pilihan Anda."
            : "Satu perjalanan. Banyak pengalaman."
        }
        description={
          destinationSlug
            ? `Paket yang mengunjungi ${destinationName || "destinasi pilihan Anda"}. Periksa itinerary, tanggal dan ketersediaan sebelum memesan.`
            : "Temukan paket wisata untuk mengenal alam, budaya, dan cerita desa. Periksa tanggal serta ketersediaan sebelum membuat pesanan."
        }
        image="https://images.unsplash.com/photo-1513415564515-763d91423bdd?auto=format&fit=crop&w=900&q=85"
      />
      <div className="package-toolbar">
        <h2>
          <Map size={20} /> Paket untuk perjalanan Anda
        </h2>
        <Link href={destinationSlug ? "/paket" : "/paket/demo"}>
          {destinationSlug ? "Semua paket wisata" : "Lihat contoh itinerary"}{" "}
          <ArrowRight size={15} />
        </Link>
      </div>
      {loading ? (
        <div className="empty-state" role="status">
          <Loader2 className="mx-auto animate-spin text-blue-600" />
          <p>Memuat paket wisata…</p>
        </div>
      ) : error ? (
        <div className="empty-state">
          <p role="status">{error}</p>
          <button
            className="ui-button"
            onClick={() => setRevision((value) => value + 1)}
          >
            Coba lagi
          </button>
        </div>
      ) : !items.length ? (
        <EmptyState
          title={
            destinationSlug ? "Paket belum ada" : "Paket wisata segera hadir"
          }
          description={
            destinationSlug
              ? `Belum ada paket wisata terbit yang mengunjungi ${destinationName || "destinasi ini"}.`
              : "Belum ada paket terbit pada katalog. Sambil menunggu, jelajahi destinasi untuk rencana perjalanan Anda."
          }
          href={
            destinationSlug
              ? `/destinasi/${encodeURIComponent(destinationSlug)}`
              : "/destinasi"
          }
          label={
            destinationSlug ? "Kembali ke destinasi" : "Jelajahi destinasi"
          }
        />
      ) : (
        <div className="inspiration-grid">
          {items.map((item) => (
            <Card
              key={item.id}
              image={item.photos?.[0]?.url}
              illustration={item.photos?.[0]?.is_illustration ?? true}
              title={item.name}
              actionLabel="Lihat detail paket"
              meta={item.destination_name || "Pengalaman wisata daerah"}
              price={`Harga dasar ${new Intl.NumberFormat("id-ID", { style: "currency", currency: item.currency, maximumFractionDigits: 0 }).format(item.base_price)} · harga akhir saat pemesanan`}
              href={`/paket/${encodeURIComponent(item.slug)}`}
            />
          ))}
        </div>
      )}
      {meta && meta.total > meta.per_page && (
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
            disabled={loading || page * meta.per_page >= meta.total}
            onClick={() => setPage(page + 1)}
          >
            Berikutnya
          </button>
        </div>
      )}
    </Shell>
  );
}
