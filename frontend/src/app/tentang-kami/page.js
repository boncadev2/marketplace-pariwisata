import Link from "next/link";
import { Compass, HeartHandshake, MapPin, Users, Store, BedDouble, ArrowRight, ShieldCheck, Sparkles } from "lucide-react";
import { Shell } from "../../components/Shell";
import { PageHeader } from "../../components/PageHeader";

export const metadata = {
  title: "Tentang Kami",
  description:
    "Mengenal WisataDaerah — Gerakan pariwisata daerah terintegrasi yang memberdayakan desa wisata, pelaku UMKM, homestay lokal, dan ekonomi rakyat.",
};

export default function AboutUsPage() {
  return (
    <Shell>
      <PageHeader
        eyebrow="Cerita & Misi Kami"
        title="Tentang WisataDaerah"
        description="Membawa potensi pariwisata lokal dan desa wisata ke panggung nasional melalui teknologi yang inklusif dan berkeadilan."
      />

      <div style={{ maxWidth: "900px", margin: "2rem auto", lineHeight: 1.8, color: "var(--color-text-main)" }}>
        {/* Hero Highlight */}
        <section
          style={{
            padding: "2rem",
            backgroundColor: "#eff6ff",
            borderRadius: "1rem",
            border: "1px solid #bfdbfe",
            marginBottom: "3rem",
            textAlign: "center",
          }}
        >
          <span style={{ display: "inline-flex", alignItems: "center", gap: "0.5rem", color: "#2563eb", fontWeight: 600, fontSize: "0.95rem" }}>
            <Sparkles size={20} /> Menggerakkan Ekonomi Wisata Komunitas
          </span>
          <h2 style={{ fontSize: "1.75rem", fontWeight: 700, margin: "0.75rem 0", color: "#1e3a8a" }}>
            Setiap Perjalanan Membawa Dampak Nyata bagi Warga Lokal
          </h2>
          <p style={{ maxWidth: "700px", margin: "0 auto", color: "#3b82f6", fontSize: "1.05rem" }}>
            WisataDaerah lahir dari keyakinan bahwa keindahan alam dan keunikan budaya Indonesia tersimpan di pelosok daerah.
            Dengan platform ini, kami menghubungkan wisatawan dengan masyarakat lokal secara langsung, transparan, dan bermartabat.
          </p>
        </section>

        {/* 3 Core Values */}
        <section style={{ marginBottom: "3rem" }}>
          <h2 style={{ fontSize: "1.5rem", fontWeight: 700, textAlign: "center", marginBottom: "1.5rem" }}>
            Tiga Pilar Utama Kami
          </h2>
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(260px, 1fr))",
              gap: "1.5rem",
            }}
          >
            <div
              style={{
                padding: "1.5rem",
                borderRadius: "0.75rem",
                backgroundColor: "#fff",
                border: "1px solid var(--color-border)",
              }}
            >
              <div
                style={{
                  width: "44px",
                  height: "44px",
                  borderRadius: "0.5rem",
                  backgroundColor: "#eff6ff",
                  color: "#2563eb",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  marginBottom: "1rem",
                }}
              >
                <Compass size={22} />
              </div>
              <h3 style={{ fontSize: "1.15rem", fontWeight: 600, margin: "0 0 0.5rem 0" }}>
                Keaslian & Kearifan Lokal
              </h3>
              <p style={{ margin: 0, fontSize: "0.9rem", color: "var(--color-text-muted)" }}>
                Kami memprioritaskan pengalaman wisata asli yang dipandu warga lokal, homestay keluarga di desa, serta cita rasa kuliner warisan leluhur.
              </p>
            </div>

            <div
              style={{
                padding: "1.5rem",
                borderRadius: "0.75rem",
                backgroundColor: "#fff",
                border: "1px solid var(--color-border)",
              }}
            >
              <div
                style={{
                  width: "44px",
                  height: "44px",
                  borderRadius: "0.5rem",
                  backgroundColor: "#ecfdf5",
                  color: "#059669",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  marginBottom: "1rem",
                }}
              >
                <HeartHandshake size={22} />
              </div>
              <h3 style={{ fontSize: "1.15rem", fontWeight: 600, margin: "0 0 0.5rem 0" }}>
                Bagi Hasil Adil & Transparan
              </h3>
              <p style={{ margin: 0, fontSize: "0.9rem", color: "var(--color-text-muted)" }}>
                Mayoritas pendapatan transaksi mengalir langsung kepada mitra penyedia jasa dan pengrajin daerah melalui sistem pembukuan otomatis dan pencairan cepat.
              </p>
            </div>

            <div
              style={{
                padding: "1.5rem",
                borderRadius: "0.75rem",
                backgroundColor: "#fff",
                border: "1px solid var(--color-border)",
              }}
            >
              <div
                style={{
                  width: "44px",
                  height: "44px",
                  borderRadius: "0.5rem",
                  backgroundColor: "#fef3c7",
                  color: "#d97706",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  marginBottom: "1rem",
                }}
              >
                <Users size={22} />
              </div>
              <h3 style={{ fontSize: "1.15rem", fontWeight: 600, margin: "0 0 0.5rem 0" }}>
                Kolaborasi Lintas Desa (BUMDes)
              </h3>
              <p style={{ margin: 0, fontSize: "0.9rem", color: "var(--color-text-muted)" }}>
                Fitur lintas desa kami memungkinkan beberapa desa wisata saling berjejaring, menyusun paket wisata gabungan, dan berbagi wisatawan secara sinergis.
              </p>
            </div>
          </div>
        </section>

        {/* Ekosistem Layanan */}
        <section style={{ marginBottom: "3rem" }}>
          <h2 style={{ fontSize: "1.5rem", fontWeight: 700, marginBottom: "1rem" }}>
            Ekosistem Layanan WisataDaerah
          </h2>
          <p>
            Platform kami menyatukan seluruh kebutuhan perjalanan wisatawan dalam satu atap yang terpercaya:
          </p>
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
              gap: "1rem",
              marginTop: "1rem",
            }}
          >
            <div style={{ padding: "1rem", backgroundColor: "#f9fafb", borderRadius: "0.5rem", border: "1px solid var(--color-border)" }}>
              <strong>🎟️ Tiket Destinasi</strong>
              <p style={{ margin: "0.25rem 0 0", fontSize: "0.85rem", color: "var(--color-text-muted)" }}>
                E-tiket QR code instan untuk masuk lokasi wisata tanpa antre tiket fisik.
              </p>
            </div>
            <div style={{ padding: "1rem", backgroundColor: "#f9fafb", borderRadius: "0.5rem", border: "1px solid var(--color-border)" }}>
              <strong>🏡 Homestay Desa</strong>
              <p style={{ margin: "0.25rem 0 0", fontSize: "0.85rem", color: "var(--color-text-muted)" }}>
                Penginapan ramah dengan suasana hangat warga desa dan tarif terjangkau.
              </p>
            </div>
            <div style={{ padding: "1rem", backgroundColor: "#f9fafb", borderRadius: "0.5rem", border: "1px solid var(--color-border)" }}>
              <strong>🍲 Wisata Kuliner</strong>
              <p style={{ margin: "0.25rem 0 0", fontSize: "0.85rem", color: "var(--color-text-muted)" }}>
                Reservasi meja dan paket makan tradisional khas racikan juru masak lokal.
              </p>
            </div>
            <div style={{ padding: "1rem", backgroundColor: "#f9fafb", borderRadius: "0.5rem", border: "1px solid var(--color-border)" }}>
              <strong>🛍️ Produk UMKM</strong>
              <p style={{ margin: "0.25rem 0 0", fontSize: "0.85rem", color: "var(--color-text-muted)" }}>
                Oleh-oleh kerajinan tangan dan camilan khas dikirim langsung ke rumah Anda.
              </p>
            </div>
          </div>
        </section>

        {/* Call to action for partners & visitors */}
        <section
          style={{
            padding: "2rem",
            borderRadius: "1rem",
            background: "linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%)",
            color: "#fff",
            textAlign: "center",
          }}
        >
          <h2 style={{ fontSize: "1.5rem", fontWeight: 700, margin: "0 0 0.5rem 0" }}>
            Mari Tumbuh Bersama Kami
          </h2>
          <p style={{ maxWidth: "600px", margin: "0 auto 1.5rem auto", opacity: 0.9 }}>
            Apakah Anda pengelola objek wisata, pemilik penginapan, pegiat kuliner, atau perajin UMKM?
            Daftarkan usaha Anda dan jangkau ribuan wisatawan dari berbagai kota.
          </p>
          <div style={{ display: "flex", justifyContent: "center", gap: "1rem", flexWrap: "wrap" }}>
            <Link
              href="/daftar-mitra"
              className="ui-button"
              style={{
                backgroundColor: "#fff",
                color: "#1e3a8a",
                fontWeight: 600,
                display: "inline-flex",
                alignItems: "center",
                gap: "0.5rem",
              }}
            >
              Daftar Jadi Mitra <ArrowRight size={16} />
            </Link>
            <Link
              href="/destinasi"
              className="ui-button"
              style={{
                backgroundColor: "rgba(255, 255, 255, 0.2)",
                color: "#fff",
                border: "1px solid rgba(255, 255, 255, 0.4)",
              }}
            >
              Jelajahi Wisata
            </Link>
          </div>
        </section>
      </div>
    </Shell>
  );
}
