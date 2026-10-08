"use client";

import { useEffect, useState } from "react";
import { Star, MessageSquare, Loader2 } from "lucide-react";
import { apiRequest } from "../lib/api";

export function ReviewSection({ productSlug, destinationSlug }) {
  const [reviews, setReviews] = useState([]);
  const [summary, setSummary] = useState({ average_rating: 0, total_reviews: 0 });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    async function loadReviews() {
      try {
        let query = "";
        if (productSlug) query = `?product_slug=${encodeURIComponent(productSlug)}`;
        else if (destinationSlug) query = `?destination_slug=${encodeURIComponent(destinationSlug)}`;

        const res = await apiRequest(`/reviews${query}`);
        if (active) {
          setReviews(res.data || []);
          setSummary(res.summary || { average_rating: 0, total_reviews: 0 });
        }
      } catch {
        // fail gracefully without blocking the rest of the page
      } finally {
        if (active) setLoading(false);
      }
    }

    loadReviews();
    return () => {
      active = false;
    };
  }, [productSlug, destinationSlug]);

  return (
    <section className="detail-description" style={{ marginTop: "2rem" }}>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: "1rem", marginBottom: "1.5rem" }}>
        <div>
          <p className="section-kicker">ULASAN PENGUNJUNG</p>
          <h2 style={{ margin: 0 }}>Pengalaman wisatawan</h2>
        </div>
        {summary.total_reviews > 0 && (
          <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", background: "var(--card-bg, #f8fafc)", padding: "0.5rem 1rem", borderRadius: "100px", border: "1px solid var(--border, #e2e8f0)" }}>
            <Star size={18} fill="#f59e0b" color="#f59e0b" />
            <strong style={{ fontSize: "1.1rem" }}>{summary.average_rating.toFixed(1)}</strong>
            <span style={{ color: "var(--muted, #64748b)", fontSize: "0.875rem" }}>
              ({summary.total_reviews} ulasan)
            </span>
          </div>
        )}
      </div>

      {loading ? (
        <div style={{ padding: "2rem", textAlign: "center", color: "var(--muted, #64748b)" }}>
          <Loader2 size={24} className="animate-spin" style={{ margin: "0 auto 0.5rem" }} />
          <p>Memuat ulasan…</p>
        </div>
      ) : reviews.length === 0 ? (
        <div
          style={{
            padding: "2.5rem 1.5rem",
            textAlign: "center",
            background: "var(--surface-subtle, #f8fafc)",
            borderRadius: "12px",
            border: "1px dashed var(--border, #cbd5e1)",
          }}
        >
          <MessageSquare size={32} style={{ color: "var(--muted, #94a3b8)", margin: "0 auto 0.75rem" }} />
          <h3 style={{ fontSize: "1rem", fontWeight: 600, marginBottom: "0.25rem" }}>Belum ada ulasan</h3>
          <p style={{ color: "var(--muted, #64748b)", fontSize: "0.875rem", maxWidth: "420px", margin: "0 auto" }}>
            Jadilah yang pertama memberikan ulasan setelah menyelesaikan perjalanan atau pesanan Anda.
          </p>
        </div>
      ) : (
        <div style={{ display: "flex", flexDirection: "column", gap: "1rem" }}>
          {reviews.map((rev) => (
            <article
              key={rev.id}
              style={{
                padding: "1.25rem",
                borderRadius: "10px",
                border: "1px solid var(--border, #e2e8f0)",
                background: "var(--surface, #ffffff)",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "0.5rem" }}>
                <div>
                  <strong style={{ fontSize: "0.95rem", display: "block" }}>
                    {rev.user?.name || "Wisatawan Terverifikasi"}
                  </strong>
                  <span style={{ fontSize: "0.75rem", color: "var(--muted, #64748b)" }}>
                    Pembeli Terverifikasi
                  </span>
                </div>
                <div style={{ display: "flex", gap: "2px" }}>
                  {[1, 2, 3, 4, 5].map((star) => (
                    <Star
                      key={star}
                      size={15}
                      fill={star <= rev.rating ? "#f59e0b" : "transparent"}
                      color={star <= rev.rating ? "#f59e0b" : "#cbd5e1"}
                    />
                  ))}
                </div>
              </div>
              {rev.comment && (
                <p style={{ margin: "0.5rem 0 0", fontSize: "0.9rem", color: "var(--text, #334155)", lineHeight: 1.5 }}>
                  {rev.comment}
                </p>
              )}
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
