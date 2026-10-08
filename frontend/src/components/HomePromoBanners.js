"use client";

import { useState } from "react";
import Link from "next/link";
import { Tag, Copy, Check, ArrowRight, Sparkles } from "lucide-react";

const PROMOS = [
  {
    id: "explore",
    code: "DEMO10",
    title: "Diskon 10% Eksplorasi",
    subtitle: "Kupon promo siap pakai untuk tiket & tur wisata",
    badge: "Kupon Aktif",
    minSpend: "Min. belanja Rp10.000",
    gradient: "linear-gradient(135deg, #065f46 0%, #0d9488 50%, #0284c7 100%)",
    accent: "#34d399",
    targetUrl: "/destinasi",
  },
  {
    id: "desawisata",
    code: "DESAWISATA",
    title: "Potongan Rp25.000",
    subtitle: "Khusus pemesanan paket tur & homestay desa",
    badge: "Dukung Desa Wisata",
    minSpend: "Min. transaksi Rp100.000",
    gradient: "linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #0284c7 100%)",
    accent: "#60a5fa",
    targetUrl: "/paket",
  },
  {
    id: "rombongan",
    code: "ROMBONGAN",
    title: "Ekstra Diskon 20%",
    subtitle: "Hemat lebih banyak untuk grup & rombongan tur",
    badge: "Liburan Rombongan",
    minSpend: "Min. 4 peserta wisata",
    gradient: "linear-gradient(135deg, #701a75 0%, #a21caf 50%, #d946ef 100%)",
    accent: "#f472b6",
    targetUrl: "/paket",
  },
];

export function HomePromoBanners() {
  const [copiedCode, setCopiedCode] = useState(null);

  const handleCopy = (code) => {
    if (typeof navigator !== "undefined" && navigator.clipboard) {
      navigator.clipboard.writeText(code);
      setCopiedCode(code);
      setTimeout(() => {
        setCopiedCode(null);
      }, 2500);
    }
  };

  return (
    <section className="home-promos-section" aria-label="Promo & Kupon Diskon Wisata">
      <div className="section-title">
        <div>
          <p className="section-kicker" style={{ display: "flex", alignItems: "center", gap: "0.35rem" }}>
            <Sparkles size={14} className="text-amber-500" /> PROMO & DISKON SPESIAL
          </p>
          <h2 style={{ fontSize: "1.75rem", fontWeight: 800, margin: "0.25rem 0", color: "#0f172a" }}>
            Jelajah Lebih Hemat dengan Kupon
          </h2>
          <p style={{ color: "var(--color-text-muted)", margin: 0, fontSize: "0.95rem" }}>
            Salin kode kupon promo dan gunakan saat checkout untuk mendapatkan potongan harga.
          </p>
        </div>
      </div>

      <div
        className="promo-cards-scroll"
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))",
          gap: "1.25rem",
          marginTop: "1.5rem",
        }}
      >
        {PROMOS.map((promo) => {
          const isCopied = copiedCode === promo.code;

          return (
            <article
              key={promo.id}
              style={{
                background: promo.gradient,
                color: "#ffffff",
                borderRadius: "1rem",
                padding: "1.35rem 1.25rem",
                position: "relative",
                overflow: "hidden",
                boxShadow: "0 10px 20px -5px rgba(0, 0, 0, 0.12)",
                display: "flex",
                flexDirection: "column",
                justifyContent: "space-between",
                minHeight: "190px",
                border: "1px solid rgba(255, 255, 255, 0.15)",
                transition: "transform 0.2s ease, box-shadow 0.2s ease",
              }}
            >
              {/* Decorative Circle */}
              <div
                style={{
                  position: "absolute",
                  top: "-2rem",
                  right: "-2rem",
                  width: "120px",
                  height: "120px",
                  borderRadius: "50%",
                  background: "radial-gradient(circle, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 70%)",
                  pointerEvents: "none",
                }}
              />

              <div>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "0.5rem" }}>
                  <span
                    style={{
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "0.3rem",
                      fontSize: "0.75rem",
                      fontWeight: 700,
                      textTransform: "uppercase",
                      letterSpacing: "0.05em",
                      backgroundColor: "rgba(255, 255, 255, 0.2)",
                      padding: "0.2rem 0.55rem",
                      borderRadius: "9999px",
                      backdropFilter: "blur(4px)",
                    }}
                  >
                    <Tag size={12} /> {promo.badge}
                  </span>
                  <span style={{ fontSize: "0.75rem", opacity: 0.85 }}>{promo.minSpend}</span>
                </div>

                <h3 style={{ fontSize: "1.25rem", fontWeight: 800, margin: "0.4rem 0 0.2rem 0", lineHeight: 1.2 }}>
                  {promo.title}
                </h3>
                <p style={{ fontSize: "0.85rem", opacity: 0.9, margin: 0, lineHeight: 1.4 }}>
                  {promo.subtitle}
                </p>
              </div>

              <div
                style={{
                  marginTop: "1.25rem",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                  gap: "0.75rem",
                  backgroundColor: "rgba(0, 0, 0, 0.2)",
                  padding: "0.5rem 0.75rem",
                  borderRadius: "0.6rem",
                  backdropFilter: "blur(6px)",
                }}
              >
                <div>
                  <span style={{ display: "block", fontSize: "0.7rem", opacity: 0.8, textTransform: "uppercase" }}>
                    Kode Promo
                  </span>
                  <strong style={{ fontFamily: "monospace", fontSize: "1rem", letterSpacing: "0.05em" }}>
                    {promo.code}
                  </strong>
                </div>

                <div style={{ display: "flex", gap: "0.4rem" }}>
                  <button
                    type="button"
                    onClick={() => handleCopy(promo.code)}
                    style={{
                      display: "inline-flex",
                      alignItems: "center",
                      gap: "0.3rem",
                      fontSize: "0.8rem",
                      fontWeight: 600,
                      padding: "0.4rem 0.65rem",
                      backgroundColor: isCopied ? "#10b981" : "#ffffff",
                      color: isCopied ? "#ffffff" : "#0f172a",
                      border: "none",
                      borderRadius: "0.375rem",
                      cursor: "pointer",
                      transition: "all 0.2s ease",
                    }}
                    title="Salin kode kupon"
                  >
                    {isCopied ? (
                      <>
                        <Check size={14} /> Tersalin!
                      </>
                    ) : (
                      <>
                        <Copy size={14} /> Salin
                      </>
                    )}
                  </button>

                  <Link
                    href={promo.targetUrl}
                    style={{
                      display: "inline-flex",
                      alignItems: "center",
                      justifyContent: "center",
                      padding: "0.4rem 0.55rem",
                      backgroundColor: "rgba(255, 255, 255, 0.2)",
                      color: "#ffffff",
                      textDecoration: "none",
                      borderRadius: "0.375rem",
                      fontSize: "0.8rem",
                      fontWeight: 600,
                      transition: "background-color 0.2s ease",
                    }}
                    title="Gunakan promo sekarang"
                  >
                    <ArrowRight size={15} />
                  </Link>
                </div>
              </div>
            </article>
          );
        })}
      </div>
    </section>
  );
}
