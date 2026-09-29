"use client";
import { useEffect, useState } from "react";
import { Card } from "./Shell";
import { apiRequest } from "../lib/api";
import { Search, Heart, Info, Loader2 } from "lucide-react";

const fallback = [
  { name: "Air Terjun Demo", slug: "air-terjun-demo", image: "https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=600&q=80" },
  { name: "Kampung Budaya Demo", slug: "kampung-budaya-demo", image: "https://images.unsplash.com/photo-1513415564515-763d91423bdd?auto=format&fit=crop&w=600&q=80" },
  { name: "Gunung Indah Demo", slug: "gunung-indah-demo", image: "https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=600&q=80" },
  { name: "Pantai Pasir Putih Demo", slug: "pantai-pasir-putih-demo", image: "https://images.unsplash.com/photo-1570222094114-d054a817e56b?auto=format&fit=crop&w=600&q=80" },
];

export function DestinationSearch() {
  const [query, setQuery] = useState("");
  const [items, setItems] = useState(fallback);
  const [status, setStatus] = useState("Menampilkan rekomendasi destinasi");
  const [saveStatus, setSaveStatus] = useState("");
  const [loading, setLoading] = useState(false);

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
    if (!query) {
      setItems(fallback);
      setStatus("Menampilkan rekomendasi destinasi");
      setLoading(false);
      return;
    }

    setLoading(true);
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
        setItems([]);
        setStatus("Server katalog belum tersedia. (Gunakan data demo dengan mengosongkan pencarian)");
      } finally {
        setLoading(false);
      }
    }, 300);
    return () => {
      controller.abort();
      clearTimeout(timeout);
    };
  }, [query]);

  return (
    <div className="space-y-8 mb-24 md:mb-12">
      {/* Search Bar */}
      <div className="bg-white rounded-2xl shadow-md p-4 border border-gray-100 max-w-4xl mx-auto -mt-16 relative z-20">
        <div className="flex gap-4">
          <div className="flex-1 border border-gray-300 rounded-xl px-4 py-3 flex items-center focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-all bg-gray-50 focus-within:bg-white">
            <Search size={20} className="text-gray-400 mr-3" />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Cari nama destinasi (Cth: Air Terjun...)"
              type="search"
              className="w-full bg-transparent outline-none text-gray-700 font-medium"
            />
            {loading && <Loader2 size={18} className="text-emerald-500 animate-spin" />}
          </div>
        </div>
      </div>

      {/* Status Messages */}
      <div className="flex flex-col items-center justify-center gap-2">
        {status && (
          <p className="text-sm font-medium text-gray-500 flex items-center gap-2" aria-live="polite">
            <Info size={16} className="text-blue-500" />
            {status}
          </p>
        )}
        {saveStatus && (
          <p className="text-sm font-bold text-emerald-600 bg-emerald-50 px-4 py-2 rounded-lg border border-emerald-100" role="status">
            {saveStatus}
          </p>
        )}
      </div>

      {/* Grid of Results */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        {items.map((item, index) => (
          <Card
            key={item.id ?? item.slug}
            title={item.name}
            meta="Katalog destinasi"
            image={item.image || fallback[index % fallback.length].image}
            href={`/destinasi/${item.slug}`}
          >
            {item.id && (
              <button 
                type="button" 
                onClick={(e) => { e.preventDefault(); saveDestination(item); }}
                className="bg-emerald-50 text-emerald-600 p-2 rounded-full hover:bg-emerald-600 hover:text-white transition-colors"
                title="Simpan ke wishlist"
              >
                <Heart size={18} />
              </button>
            )}
          </Card>
        ))}
      </div>
    </div>
  );
}
