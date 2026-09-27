"use client";
import { useEffect, useState } from "react";
import { Card } from "./Shell";
const fallback = [
  { name: "Air Terjun Demo", slug: "air-terjun-demo" },
  { name: "Kampung Budaya Demo", slug: "kampung-budaya-demo" },
];
export function DestinationSearch() {
  const [query, setQuery] = useState("");
  const [items, setItems] = useState(fallback);
  const [status, setStatus] = useState("Data demonstrasi ditampilkan.");
  useEffect(() => {
    const controller = new AbortController();
    const timeout = setTimeout(async () => {
      try {
        const response = await fetch(
          `${process.env.NEXT_PUBLIC_API_URL}/destinations?q=${encodeURIComponent(query)}`,
          { signal: controller.signal }
        );
        if (!response.ok) throw new Error();
        const payload = await response.json();
        setItems(payload.data);
        setStatus(
          payload.data.length
            ? `${payload.data.length} destinasi ditemukan.`
            : "Tidak ada destinasi yang cocok."
        );
      } catch {
        setItems(query ? [] : fallback);
        setStatus("Server katalog belum tersedia.");
      }
    }, 300);
    return () => {
      controller.abort();
      clearTimeout(timeout);
    };
  }, [query]);
  return (
    <>
      <label className="search-field">
        Cari wisata
        <input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder="Nama destinasi"
          type="search"
        />
      </label>
      <p aria-live="polite">{status}</p>
      <section className="card-grid">
        {items.map((item) => (
          <Card
            key={item.id ?? item.slug}
            title={item.name}
            meta="Katalog destinasi"
            price="Lihat informasi"
            href={`/destinasi/${item.slug}`}
          />
        ))}
      </section>
    </>
  );
}
