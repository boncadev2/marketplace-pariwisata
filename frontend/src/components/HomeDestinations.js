"use client";

import { useEffect, useState } from "react";
import { Loader2 } from "lucide-react";
import { Card } from "./Shell";
import { apiRequest } from "../lib/api";

const images = [
  "photo-1555400038-63f5ba517a47",
  "photo-1513415564515-763d91423bdd",
  "photo-1588668214407-6ea9a6d8c272",
  "photo-1570222094114-d054a817e56b",
];

export function HomeDestinations() {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [revision, setRevision] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    apiRequest("/destinations?per_page=4&page=1", {
      signal: controller.signal,
    })
      .then((result) => {
        if (!controller.signal.aborted) setItems(result.data.slice(0, 4));
      })
      .catch(() => {
        if (!controller.signal.aborted) setError(true);
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [revision]);

  if (loading) {
    return (
      <div className="empty-state" role="status">
        <Loader2 className="mx-auto animate-spin text-blue-600" />
        <p>Memuat destinasi pilihan…</p>
      </div>
    );
  }
  if (error) {
    return (
      <div className="empty-state">
        <p role="status">Destinasi belum dapat dimuat.</p>
        <button
          className="ui-button ui-button-outline"
          onClick={() => {
            setError(false);
            setLoading(true);
            setRevision((value) => value + 1);
          }}
        >
          Coba lagi
        </button>
      </div>
    );
  }
  if (!items.length) {
    return (
      <div className="empty-state">
        <p>Destinasi pilihan akan tampil setelah diterbitkan oleh pengelola.</p>
      </div>
    );
  }
  return (
    <div className="inspiration-grid">
      {items.map((item, index) => (
        <Card
          key={item.id}
          title={item.name}
          meta={[item.region?.name, item.category?.name]
            .filter(Boolean)
            .join(" · ")}
          image={
            item.photos?.[0]?.url ||
            `https://images.unsplash.com/${images[index]}?auto=format&fit=crop&w=700&q=85`
          }
          illustration={item.photos?.[0]?.is_illustration ?? true}
          href={`/destinasi/${encodeURIComponent(item.slug)}`}
          actionLabel="Lihat detail"
        />
      ))}
    </div>
  );
}
