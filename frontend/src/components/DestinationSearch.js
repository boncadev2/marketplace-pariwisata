"use client";

import { useEffect, useRef, useState } from "react";
import {
  Search,
  Heart,
  SlidersHorizontal,
  Loader2,
  ChevronLeft,
  ChevronRight,
  Compass,
} from "lucide-react";
import { Card } from "./Shell";
import { EmptyState } from "./PageHeader";
import { apiRequest } from "../lib/api";

const images = [
  "photo-1555400038-63f5ba517a47",
  "photo-1513415564515-763d91423bdd",
  "photo-1588668214407-6ea9a6d8c272",
  "photo-1570222094114-d054a817e56b",
];
export function DestinationSearch({ initialQuery = "" }) {
  const [query, setQuery] = useState(initialQuery);
  const [region, setRegion] = useState("");
  const [category, setCategory] = useState("");
  const [regions, setRegions] = useState([]);
  const [categories, setCategories] = useState([]);
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const [error, setError] = useState("");
  const [saveStatus, setSaveStatus] = useState("");
  const [loading, setLoading] = useState(true);
  const [lookupError, setLookupError] = useState(false);
  const requestSequence = useRef(0);
  useEffect(() => {
    const controller = new AbortController();
    Promise.all([
      apiRequest("/lookup/regions", { signal: controller.signal }),
      apiRequest("/lookup/categories", { signal: controller.signal }),
    ])
      .then(([areas, types]) => {
        setRegions(areas.data);
        setCategories(types.data);
        setLookupError(false);
      })
      .catch(() => {
        if (!controller.signal.aborted) setLookupError(true);
      });
    return () => controller.abort();
  }, [revision]);
  useEffect(() => {
    const controller = new AbortController();
    const sequence = ++requestSequence.current;
    const timeout = setTimeout(async () => {
      setLoading(true);
      setError("");
      setItems([]);
      setMeta(null);
      const params = new URLSearchParams({
        page: String(page),
        per_page: "12",
      });
      if (query.trim()) params.set("q", query.trim());
      if (region) params.set("region_id", region);
      if (category) params.set("category_id", category);
      const deadline = setTimeout(() => controller.abort(), 12000);
      try {
        const payload = await apiRequest(`/destinations?${params}`, {
          signal: controller.signal,
        });
        if (sequence !== requestSequence.current || controller.signal.aborted)
          return;
        setItems(payload.data);
        setMeta(payload.meta);
      } catch (failure) {
        if (sequence === requestSequence.current)
          setError(
            failure.name === "AbortError"
              ? "Pencarian terlalu lama. Periksa koneksi dan coba lagi."
              : "Destinasi belum dapat dimuat. Silakan coba lagi."
          );
      } finally {
        clearTimeout(deadline);
        if (sequence === requestSequence.current) setLoading(false);
      }
    }, 250);
    return () => {
      controller.abort();
      clearTimeout(timeout);
    };
  }, [query, region, category, page, revision]);
  async function saveDestination(item) {
    try {
      await apiRequest("/account/wishlist", {
        method: "POST",
        body: JSON.stringify({ destination_slug: item.slug }),
      });
      setSaveStatus(`${item.name} tersimpan di wishlist akun Anda.`);
    } catch (failure) {
      setSaveStatus(
        failure.status === 401
          ? "Masuk ke akun untuk menyimpan destinasi."
          : "Destinasi belum dapat disimpan. Coba lagi."
      );
    }
  }
  return (
    <div className="catalog-layout">
      <aside className="catalog-filters">
        <h2>
          <SlidersHorizontal size={17} /> Sesuaikan perjalanan
        </h2>
        <p>Temukan tempat yang Anda cari.</p>
        <label htmlFor="destination-region">Wilayah</label>
        <select
          id="destination-region"
          value={region}
          onChange={(event) => {
            setRegion(event.target.value);
            setPage(1);
          }}
        >
          <option value="">Semua wilayah</option>
          {regions.map((item) => (
            <option value={item.id} key={item.id}>
              {item.name}
            </option>
          ))}
        </select>
        <label htmlFor="destination-category">Jenis wisata</label>
        <select
          id="destination-category"
          value={category}
          onChange={(event) => {
            setCategory(event.target.value);
            setPage(1);
          }}
        >
          <option value="">Semua kategori</option>
          {categories.map((item) => (
            <option value={item.id} key={item.id}>
              {item.name}
            </option>
          ))}
        </select>
        {lookupError && (
          <p role="status">
            Filter belum dapat dimuat.{" "}
            <button
              className="text-blue-700 underline"
              onClick={() => setRevision((value) => value + 1)}
            >
              Coba lagi
            </button>
          </p>
        )}
        <button
          type="button"
          className="filter-reset"
          onClick={() => {
            setQuery("");
            setRegion("");
            setCategory("");
            setPage(1);
          }}
        >
          Reset pencarian
        </button>
        <div className="filter-note">
          <Compass size={24} />
          <strong>Kenali daerah lebih dekat.</strong>
          <p>Informasi destinasi membantu Anda merencanakan kunjungan.</p>
        </div>
      </aside>
      <section className="catalog-results" aria-busy={loading}>
        <div className="catalog-search">
          <Search size={21} />
          <label className="sr-only" htmlFor="destination-search">
            Cari nama destinasi
          </label>
          <input
            id="destination-search"
            type="search"
            maxLength={100}
            value={query}
            onChange={(event) => {
              setQuery(event.target.value);
              setPage(1);
            }}
            placeholder="Cari nama destinasi…"
          />
          {loading && (
            <Loader2 className="animate-spin text-blue-600" size={18} />
          )}
        </div>
        <div className="catalog-results-heading">
          <h2>
            {query.trim()
              ? `Hasil untuk “${query.trim()}”`
              : "Destinasi untuk dijelajahi"}
          </h2>
          <span aria-live="polite">
            {meta ? `${meta.total} destinasi` : loading ? "Memuat…" : ""}
          </span>
        </div>
        {saveStatus && (
          <p role="status" className="catalog-message">
            {saveStatus}
          </p>
        )}
        {error ? (
          <div className="empty-state">
            <h2>Koneksi belum tersedia</h2>
            <p role="status">{error}</p>
            <button
              className="ui-button"
              onClick={() => setRevision((value) => value + 1)}
            >
              Coba lagi
            </button>
          </div>
        ) : loading ? (
          <div className="catalog-grid">
            {[0, 1, 2].map((key) => (
              <div className="catalog-skeleton" key={key}>
                <div />
                <span />
                <span />
              </div>
            ))}
          </div>
        ) : !items.length ? (
          <EmptyState
            title="Belum ada destinasi yang cocok"
            description="Coba nama tempat lain atau ubah wilayah dan kategori wisata."
          />
        ) : (
          <div className="catalog-grid">
            {items.map((item, index) => (
              <Card
                key={item.id}
                illustration={item.photos?.[0]?.is_illustration ?? true}
                title={item.name}
                meta={item.region?.name || "Destinasi daerah"}
                image={
                  item.photos?.[0]?.url ||
                  `https://images.unsplash.com/${images[index % images.length]}?auto=format&fit=crop&w=700&q=85`
                }
                href={`/destinasi/${item.slug}`}
              >
                <button
                  type="button"
                  onClick={() => saveDestination(item)}
                  className="wishlist-button"
                  aria-label={`Simpan ${item.name} ke wishlist`}
                >
                  <Heart size={17} />
                </button>
              </Card>
            ))}
          </div>
        )}
        {meta && meta.total > meta.per_page && (
          <nav className="catalog-pagination" aria-label="Halaman hasil">
            <button
              type="button"
              className="ui-button ui-button-outline"
              disabled={loading || page <= 1}
              onClick={() => setPage(page - 1)}
            >
              <ChevronLeft size={16} /> Sebelumnya
            </button>
            <span>
              Halaman {page} / {Math.ceil(meta.total / meta.per_page)}
            </span>
            <button
              type="button"
              className="ui-button ui-button-outline"
              disabled={loading || page * meta.per_page >= meta.total}
              onClick={() => setPage(page + 1)}
            >
              Berikutnya <ChevronRight size={16} />
            </button>
          </nav>
        )}
      </section>
    </div>
  );
}
