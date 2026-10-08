/* eslint-disable @next/next/no-img-element */
"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import {
  BookOpen,
  Search,
  Clock,
  Calendar,
  User,
  ArrowRight,
  Sparkles,
  Tag,
} from "lucide-react";
import { PageHeader } from "../../components/PageHeader";
import { Shell } from "../../components/Shell";

function formatDate(dateStr) {
  if (!dateStr) return "";
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString("id-ID", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  } catch {
    return dateStr;
  }
}

export default function ArticlesPage() {
  const [articles, setArticles] = useState([]);
  const [categories, setCategories] = useState([]);
  const [selectedCategory, setSelectedCategory] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let url = "/api/v1/articles?per_page=20";
    if (selectedCategory) {
      url += `&category=${encodeURIComponent(selectedCategory)}`;
    }
    if (searchQuery.trim()) {
      url += `&search=${encodeURIComponent(searchQuery.trim())}`;
    }

    fetch(url)
      .then((res) => res.json())
      .then((data) => {
        setArticles(data.data || []);
        if (data.categories && data.categories.length > 0) {
          setCategories(data.categories);
        }
      })
      .catch(() => {
        setArticles([]);
      })
      .finally(() => {
        setLoading(false);
      });
  }, [selectedCategory, searchQuery]);

  const featured = articles.length > 0 && !selectedCategory && !searchQuery ? articles[0] : null;
  const list = featured ? articles.slice(1) : articles;

  return (
    <Shell>
      <PageHeader
        eyebrow="Inspirasi & Cerita Wisata"
        title="Jelajah Cerita, Tips & Ragam Budaya Daerah"
        subtitle="Temukan rekomendasi destinasi tersembunyi, kiat liburan keluarga, kelezatan kuliner lokal, hingga panduan tradisi nusantara."
      />

      <div className="mx-auto max-w-6xl px-4 py-8">
        {/* Search & Filter Bar */}
        <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          {/* Category Tabs */}
          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              onClick={() => setSelectedCategory("")}
              className={`rounded-full px-4 py-2 text-xs font-semibold transition ${
                selectedCategory === ""
                  ? "bg-emerald-600 text-white shadow-sm"
                  : "bg-slate-100 text-slate-700 hover:bg-slate-200"
              }`}
            >
              Semua Artikel
            </button>
            {categories.map((cat) => (
              <button
                key={cat}
                type="button"
                onClick={() => setSelectedCategory(cat)}
                className={`rounded-full px-4 py-2 text-xs font-semibold transition ${
                  selectedCategory === cat
                    ? "bg-emerald-600 text-white shadow-sm"
                    : "bg-slate-100 text-slate-700 hover:bg-slate-200"
                }`}
              >
                {cat}
              </button>
            ))}
          </div>

          {/* Search Input */}
          <div className="relative min-w-[260px]">
            <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Cari artikel, tips, atau tema..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full rounded-full border border-slate-200 bg-white py-2 pl-9 pr-4 text-xs shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>
        </div>

        {/* Loading State */}
        {loading && (
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {[1, 2, 3, 4, 5, 6].map((i) => (
              <div
                key={i}
                className="animate-pulse rounded-2xl border border-slate-100 bg-white p-4 shadow-sm"
              >
                <div className="h-44 w-full rounded-xl bg-slate-200" />
                <div className="mt-4 h-4 w-1/3 rounded bg-slate-200" />
                <div className="mt-2 h-6 w-3/4 rounded bg-slate-200" />
                <div className="mt-2 h-4 w-full rounded bg-slate-100" />
              </div>
            ))}
          </div>
        )}

        {/* Empty State */}
        {!loading && articles.length === 0 && (
          <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-12 text-center">
            <BookOpen className="mx-auto h-12 w-12 text-slate-300" />
            <h3 className="mt-4 text-base font-bold text-slate-800">
              Belum Ada Artikel Ditemukan
            </h3>
            <p className="mt-1 text-xs text-slate-500">
              Coba sesuaikan kata kunci pencarian atau pilih kategori artikel lainnya.
            </p>
            {(selectedCategory || searchQuery) && (
              <button
                type="button"
                onClick={() => {
                  setSelectedCategory("");
                  setSearchQuery("");
                }}
                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
              >
                Reset Filter
              </button>
            )}
          </div>
        )}

        {/* Content Layout */}
        {!loading && articles.length > 0 && (
          <div className="space-y-8">
            {/* Featured Hero Article */}
            {featured && (
              <Link
                href={`/artikel/${featured.slug}`}
                className="group relative block overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm transition hover:shadow-md"
              >
                <div className="grid grid-cols-1 md:grid-cols-12">
                  <div className="relative h-64 md:h-auto md:col-span-7 overflow-hidden bg-slate-100">
                    <img
                      src={
                        featured.image_url ||
                        "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80"
                      }
                      alt={featured.title}
                      className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    />
                    <div className="absolute top-4 left-4">
                      <span className="inline-flex items-center gap-1 rounded-full bg-emerald-600/90 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm shadow-sm">
                        <Sparkles className="h-3 w-3" />
                        Artikel Pilihan
                      </span>
                    </div>
                  </div>
                  <div className="flex flex-col justify-between p-6 sm:p-8 md:col-span-5">
                    <div>
                      <div className="flex items-center gap-2 text-xs font-medium text-slate-500">
                        {featured.category && (
                          <span className="rounded-md bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">
                            {featured.category}
                          </span>
                        )}
                        <span>•</span>
                        <span className="inline-flex items-center gap-1">
                          <Calendar className="h-3 w-3" />
                          {formatDate(featured.published_at)}
                        </span>
                      </div>
                      <h2 className="mt-3 text-xl font-bold text-slate-900 group-hover:text-emerald-700 sm:text-2xl transition">
                        {featured.title}
                      </h2>
                      <p className="mt-3 line-clamp-3 text-xs leading-relaxed text-slate-600">
                        {featured.excerpt}
                      </p>
                    </div>

                    <div className="mt-6 flex items-center justify-between border-t border-slate-100 pt-4 text-xs">
                      <span className="font-medium text-slate-600">
                        Oleh: {featured.author_name || "Redaksi Wisata"}
                      </span>
                      <span className="inline-flex items-center gap-1 font-semibold text-emerald-700">
                        Baca Selengkapnya
                        <ArrowRight className="h-3.5 w-3.5 transition group-hover:translate-x-1" />
                      </span>
                    </div>
                  </div>
                </div>
              </Link>
            )}

            {/* Articles Grid */}
            {list.length > 0 && (
              <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {list.map((item) => (
                  <Link
                    key={item.id}
                    href={`/artikel/${item.slug}`}
                    className="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md hover:border-slate-300"
                  >
                    <div className="relative h-48 w-full overflow-hidden bg-slate-100">
                      <img
                        src={
                          item.image_url ||
                          "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80"
                        }
                        alt={item.title}
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                      />
                      {item.category && (
                        <span className="absolute top-3 left-3 inline-flex items-center gap-1 rounded-full bg-slate-900/70 px-2.5 py-0.5 text-[11px] font-semibold text-white backdrop-blur-sm">
                          <Tag className="h-2.5 w-2.5" />
                          {item.category}
                        </span>
                      )}
                    </div>
                    <div className="flex flex-1 flex-col justify-between p-5">
                      <div>
                        <div className="flex items-center gap-2 text-[11px] text-slate-500">
                          <span className="inline-flex items-center gap-1">
                            <Calendar className="h-3 w-3" />
                            {formatDate(item.published_at)}
                          </span>
                          <span>•</span>
                          <span className="inline-flex items-center gap-1">
                            <Clock className="h-3 w-3" />
                            {item.read_time || 3} mnt baca
                          </span>
                        </div>
                        <h3 className="mt-2.5 line-clamp-2 text-base font-bold text-slate-900 group-hover:text-emerald-700 transition">
                          {item.title}
                        </h3>
                        <p className="mt-2 line-clamp-3 text-xs leading-relaxed text-slate-600">
                          {item.excerpt}
                        </p>
                      </div>

                      <div className="mt-5 flex items-center justify-between border-t border-slate-100 pt-3 text-[11px]">
                        <span className="inline-flex items-center gap-1 text-slate-500 font-medium">
                          <User className="h-3 w-3 text-slate-400" />
                          {item.author_name || "Redaksi"}
                        </span>
                        <span className="font-semibold text-emerald-700 inline-flex items-center gap-1">
                          Baca
                          <ArrowRight className="h-3 w-3 transition group-hover:translate-x-0.5" />
                        </span>
                      </div>
                    </div>
                  </Link>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    </Shell>
  );
}
