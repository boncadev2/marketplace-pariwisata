"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { BookOpen, Calendar, Clock, ArrowRight } from "lucide-react";
import { apiRequest } from "../lib/api";

const FALLBACK_ARTICLES = [
  {
    id: 1,
    title: "Panduan Lengkap Jelajah Pantai Berpasir Putih & Bebatuan Granit Eksotis",
    slug: "panduan-lengkap-jelajah-pantai-pasir-putih-granit",
    category: "Panduan Wisata",
    image_url: "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80",
    published_at: "2026-10-02T09:00:00Z",
    excerpt: "Tips terbaik mengunjungi pantai tersembunyi dengan pemandangan batuan granit raksasa dan air laut biru jernih.",
  },
  {
    id: 2,
    title: "5 Kuliner Otentik Khas Daerah yang Wajib Dicoba Saat Berlibur",
    slug: "5-kuliner-otentik-khas-daerah-wajib-dicoba",
    category: "Kuliner Lokal",
    image_url: "https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=800&q=80",
    published_at: "2026-10-04T09:00:00Z",
    excerpt: "Eksplorasi cita rasa rempah khas pedesaan yang diracik turun-temurun oleh masyarakat lokal.",
  },
  {
    id: 3,
    title: "Tips Liburan Keluarga Hemat & Nyaman ke Desa Wisata",
    slug: "tips-liburan-keluarga-hemat-dan-nyaman-ke-desa-wisata",
    category: "Tips Liburan",
    image_url: "https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=800&q=80",
    published_at: "2026-10-06T09:00:00Z",
    excerpt: "Cara merencanakan itinerary tur rombongan keluarga dengan hemat, nyaman, dan edukatif bagi anak-anak.",
  },
];

export function HomeArticles() {
  const [articles, setArticles] = useState(FALLBACK_ARTICLES);

  useEffect(() => {
    let active = true;
    async function fetchArticles() {
      try {
        const res = await apiRequest("/articles?per_page=3");
        if (active && res?.data?.length) {
          setArticles(res.data);
        }
      } catch {
        // Fallback already in place
      }
    }
    fetchArticles();
    return () => {
      active = false;
    };
  }, []);

  const formatDate = (dateStr) => {
    if (!dateStr) return "";
    try {
      return new Date(dateStr).toLocaleDateString("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
      });
    } catch {
      return dateStr;
    }
  };

  return (
    <section className="home-articles-section" aria-label="Artikel dan Panduan Wisata">
      <div className="section-title">
        <div>
          <p className="section-kicker">CERITA & PANDUAN WISATA</p>
          <h2 style={{ fontSize: "1.75rem", fontWeight: 800, margin: "0.25rem 0", color: "#0f172a" }}>
            Inspirasi & Tips Perjalanan Daerah
          </h2>
          <p style={{ color: "var(--color-text-muted)", margin: 0, fontSize: "0.95rem" }}>
            Baca ulasan, rekomendasi kuliner, dan panduan lengkap sebelum memulai liburan Anda.
          </p>
        </div>
        <Link
          href="/artikel"
          style={{
            display: "inline-flex",
            alignItems: "center",
            gap: "0.4rem",
            fontSize: "0.95rem",
            fontWeight: 600,
            color: "#059669",
            textDecoration: "none",
          }}
        >
          Lihat semua artikel <ArrowRight size={17} />
        </Link>
      </div>

      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))",
          gap: "1.5rem",
          marginTop: "1.5rem",
        }}
      >
        {articles.slice(0, 3).map((article) => (
          <article
            key={article.slug || article.id}
            style={{
              backgroundColor: "#ffffff",
              borderRadius: "1rem",
              overflow: "hidden",
              border: "1px solid var(--color-border, #e2e8f0)",
              boxShadow: "0 4px 6px -1px rgba(0, 0, 0, 0.05)",
              display: "flex",
              flexDirection: "column",
              transition: "transform 0.2s ease, box-shadow 0.2s ease",
            }}
          >
            <Link
              href={`/artikel/${article.slug}`}
              style={{
                position: "relative",
                display: "block",
                height: "190px",
                overflow: "hidden",
                backgroundColor: "#f1f5f9",
              }}
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={article.image_url || "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80"}
                alt={article.title}
                loading="lazy"
                decoding="async"
                style={{
                  width: "100%",
                  height: "100%",
                  objectFit: "cover",
                  transition: "transform 0.3s ease",
                }}
              />
              <span
                style={{
                  position: "absolute",
                  top: "0.75rem",
                  left: "0.75rem",
                  backgroundColor: "rgba(15, 23, 42, 0.75)",
                  color: "#ffffff",
                  fontSize: "0.75rem",
                  fontWeight: 600,
                  padding: "0.25rem 0.65rem",
                  borderRadius: "9999px",
                  backdropFilter: "blur(4px)",
                }}
              >
                {article.category || "Panduan Wisata"}
              </span>
            </Link>

            <div style={{ padding: "1.25rem", display: "flex", flexDirection: "column", flex: 1, justifyContent: "space-between" }}>
              <div>
                <div
                  style={{
                    display: "flex",
                    alignItems: "center",
                    gap: "0.75rem",
                    fontSize: "0.8rem",
                    color: "var(--color-text-muted, #64748b)",
                    marginBottom: "0.6rem",
                  }}
                >
                  <span style={{ display: "inline-flex", alignItems: "center", gap: "0.25rem" }}>
                    <Calendar size={13} /> {formatDate(article.published_at)}
                  </span>
                  <span>·</span>
                  <span style={{ display: "inline-flex", alignItems: "center", gap: "0.25rem" }}>
                    <Clock size={13} /> 3 mnt baca
                  </span>
                </div>

                <h3 style={{ fontSize: "1.1rem", fontWeight: 700, margin: "0 0 0.5rem 0", lineHeight: 1.4 }}>
                  <Link
                    href={`/artikel/${article.slug}`}
                    style={{ color: "#0f172a", textDecoration: "none" }}
                  >
                    {article.title}
                  </Link>
                </h3>

                <p
                  style={{
                    fontSize: "0.85rem",
                    color: "var(--color-text-muted, #64748b)",
                    margin: 0,
                    lineHeight: 1.5,
                    display: "-webkit-box",
                    WebkitLineClamp: 2,
                    WebkitBoxOrient: "vertical",
                    overflow: "hidden",
                  }}
                >
                  {article.excerpt || article.summary || article.body?.substring(0, 100) + "..."}
                </p>
              </div>

              <div style={{ marginTop: "1rem", paddingTop: "0.75rem", borderTop: "1px solid #f1f5f9" }}>
                <Link
                  href={`/artikel/${article.slug}`}
                  style={{
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "0.35rem",
                    fontSize: "0.85rem",
                    fontWeight: 600,
                    color: "#059669",
                    textDecoration: "none",
                  }}
                >
                  Baca artikel <ArrowRight size={14} />
                </Link>
              </div>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
