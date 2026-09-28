"use client";
import { useEffect, useState } from "react";
import { Card } from "./Shell";
import { apiRequest } from "../lib/api";
const fallback = [
  { name: "Air Terjun Demo", slug: "air-terjun-demo" },
  { name: "Kampung Budaya Demo", slug: "kampung-budaya-demo" },
];
export function DestinationSearch() {
  const [query, setQuery] = useState("");
  const [items, setItems] = useState(fallback);
  const [status, setStatus] = useState("Data demonstrasi ditampilkan.");
  const [saveStatus, setSaveStatus] = useState("");
  async function saveDestination(item) {
    setSaveStatus("");
    try {
      await apiRequest("/account/wishlist", { method: "POST", body: JSON.stringify({ destination_slug: item.slug }) });
      setSaveStatus(`${item.name} tersimpan di wishlist akun Anda.`);
    } catch (error) {
      setSaveStatus(error.status === 401 ? "Masuk ke akun untuk menyimpan destinasi." : "Destinasi belum dapat disimpan. Coba lagi.");
    }
  }
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
      <p role="status">{saveStatus}</p>
      <section className="card-grid">
        {items.map((item) => (
          <Card
            key={item.id ?? item.slug}
            title={item.name}
            meta="Katalog destinasi"
            price="Lihat informasi"
            href={`/destinasi/${item.slug}`}
          >{item.id && <p><button type="button" onClick={() => saveDestination(item)}>Simpan ke wishlist</button></p>}</Card>
        ))}
      </section>
    </>
  );
}
