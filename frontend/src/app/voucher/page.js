"use client";

import { useEffect, useRef, useState, Suspense } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import QRCode from "qrcode";
import {
  Ticket,
  ArrowLeft,
  Loader2,
  CheckCircle2,
  AlertCircle,
  Calendar,
  Users,
  Compass,
  ArrowRight,
} from "lucide-react";
import { PageHeader } from "../../components/PageHeader";
import { Shell } from "../../components/Shell";
import { apiRequest } from "../../lib/api";

function VoucherCode({ voucher, order }) {
  const canvas = useRef(null);
  const [error, setError] = useState("");

  useEffect(() => {
    if (voucher.status !== "active") return;
    let active = true;
    QRCode.toCanvas(canvas.current, voucher.token, {
      width: 240,
      margin: 3,
      errorCorrectionLevel: "M",
    }).catch(() => {
      if (active)
        setError("QR tidak dapat ditampilkan. Gunakan kode voucher di bawah.");
    });
    return () => {
      active = false;
    };
  }, [voucher.token, voucher.status]);

  const manifest = order?.policy_snapshot?.manifest_peserta || [];
  const itemName = order?.items?.[0]?.name || "Tiket / Paket Kunjungan";

  return (
    <article
      className="card printable-ticket"
      style={{
        maxWidth: "480px",
        margin: "1.5rem auto",
        textAlign: "center",
        border: "1px solid var(--color-border)",
        borderRadius: "0.75rem",
        padding: "1.75rem",
        backgroundColor: "#fff",
        boxShadow: "0 2px 8px rgba(0,0,0,0.06)",
      }}
    >
      <div className="card-body">
        {/* Printable Official Header */}
        <div style={{ borderBottom: "1px dashed #e5e7eb", paddingBottom: "1rem", marginBottom: "1rem" }}>
          <p style={{ fontSize: "0.8rem", textTransform: "uppercase", letterSpacing: "0.05em", color: "#6b7280", margin: "0 0 0.25rem 0" }}>
            E-Tiket & Voucher Masuk Resmi
          </p>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 800, margin: "0.25rem 0", color: "#111827" }}>
            {itemName}
          </h2>
          {order?.order_id && (
            <p style={{ fontSize: "0.85rem", color: "#4b5563", margin: "0.25rem 0" }}>
              ID Pesanan: <strong style={{ fontFamily: "monospace" }}>{order.order_id}</strong>
            </p>
          )}
          {order?.customer_name && (
            <p style={{ fontSize: "0.85rem", color: "#6b7280", margin: "0.15rem 0" }}>
              Atas Nama: <strong>{order.customer_name}</strong>
            </p>
          )}
        </div>

        <div
          style={{
            display: "inline-flex",
            alignItems: "center",
            gap: "0.4rem",
            padding: "0.25rem 0.75rem",
            borderRadius: "9999px",
            fontSize: "0.8rem",
            fontWeight: 600,
            backgroundColor: voucher.status === "redeemed" ? "#f3f4f6" : "#ecfdf5",
            color: voucher.status === "redeemed" ? "#6b7280" : "#059669",
            marginBottom: "0.75rem",
          }}
        >
          {voucher.status === "redeemed" ? "Sudah Digunakan" : "✓ Voucher Aktif (Siap Digunakan)"}
        </div>

        <p
          style={{
            color: "var(--color-text-muted)",
            fontSize: "0.95rem",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            gap: "0.5rem",
            margin: "0.4rem 0 1rem 0",
            fontWeight: 500,
          }}
        >
          <Calendar size={16} /> {voucher.service_date} · <Users size={16} /> {voucher.admissions} Peserta
        </p>

        {voucher.status === "active" ? (
          <div>
            <div
              style={{
                display: "inline-block",
                padding: "0.5rem",
                backgroundColor: "#fff",
                borderRadius: "0.5rem",
                border: "1px solid #e5e7eb",
                boxShadow: "0 2px 6px rgba(0,0,0,0.05)",
              }}
            >
              <canvas ref={canvas} aria-label="QR voucher kunjungan" style={{ display: "block" }} />
            </div>
            {error && <p style={{ color: "#dc2626", fontSize: "0.85rem", marginTop: "0.5rem" }}>{error}</p>}
            <p style={{ marginTop: "0.75rem", fontSize: "0.85rem", color: "var(--color-text-muted)" }}>
              Tunjukkan kode QR ini kepada petugas di lokasi wisata untuk dipindai.
            </p>
            <div style={{ marginTop: "0.5rem" }}>
              <span
                style={{
                  fontFamily: "monospace",
                  fontSize: "0.95rem",
                  fontWeight: 700,
                  letterSpacing: "0.05em",
                  color: "#1d4ed8",
                  overflowWrap: "anywhere",
                  backgroundColor: "#eff6ff",
                  padding: "0.4rem 0.8rem",
                  borderRadius: "0.375rem",
                  display: "inline-block",
                  border: "1px solid #bfdbfe",
                }}
              >
                {voucher.token}
              </span>
            </div>

            {/* Manifest Peserta Rombongan if present */}
            {manifest.length > 0 && (
              <div
                style={{
                  marginTop: "1.25rem",
                  textAlign: "left",
                  padding: "0.85rem",
                  backgroundColor: "#f9fafb",
                  borderRadius: "0.5rem",
                  border: "1px solid #e5e7eb",
                }}
              >
                <h4 style={{ fontSize: "0.85rem", fontWeight: 700, margin: "0 0 0.5rem 0", color: "#374151" }}>
                  📋 Data Peserta Rombongan ({manifest.length} orang)
                </h4>
                <div style={{ maxHeight: "180px", overflowY: "auto", fontSize: "0.8rem" }}>
                  <table style={{ width: "100%", borderCollapse: "collapse" }}>
                    <thead>
                      <tr style={{ borderBottom: "1px solid #e5e7eb", textAlign: "left", color: "#6b7280" }}>
                        <th style={{ padding: "0.3rem 0.4rem" }}>No</th>
                        <th style={{ padding: "0.3rem 0.4rem" }}>Nama</th>
                        <th style={{ padding: "0.3rem 0.4rem" }}>Identitas</th>
                      </tr>
                    </thead>
                    <tbody>
                      {manifest.map((p, idx) => (
                        <tr key={idx} style={{ borderBottom: "1px solid #f3f4f6" }}>
                          <td style={{ padding: "0.3rem 0.4rem", color: "#9ca3af" }}>{idx + 1}</td>
                          <td style={{ padding: "0.3rem 0.4rem", fontWeight: 600 }}>{p.name || "-"}</td>
                          <td style={{ padding: "0.3rem 0.4rem", color: "#6b7280" }}>{p.nik || p.phone || "-"}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Action Buttons (Hidden when printing) */}
            <div className="no-print" style={{ marginTop: "1.25rem", display: "flex", gap: "0.75rem", justifyContent: "center", flexWrap: "wrap" }}>
              <button
                type="button"
                onClick={() => window.print()}
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "0.4rem",
                  fontSize: "0.85rem",
                  fontWeight: 600,
                  padding: "0.55rem 1rem",
                  backgroundColor: "#1e293b",
                  color: "#fff",
                  border: "none",
                  borderRadius: "0.5rem",
                  cursor: "pointer",
                }}
              >
                🖨️ Cetak / Simpan PDF
              </button>
              <a
                href={`https://api.whatsapp.com/send?text=${encodeURIComponent(
                  `Halo, berikut e-tiket dan voucher masuk wisata saya di WisataDaerah:\nNama: ${order?.customer_name || ""}\nLayanan: ${itemName}\nTanggal: ${voucher.service_date} (${voucher.admissions} peserta)\nKode Tiket: ${voucher.token}`
                )}`}
                target="_blank"
                rel="noopener noreferrer"
                style={{
                  display: "inline-flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "0.4rem",
                  fontSize: "0.85rem",
                  fontWeight: 600,
                  padding: "0.55rem 1rem",
                  backgroundColor: "#16a34a",
                  color: "#fff",
                  textDecoration: "none",
                  borderRadius: "0.5rem",
                }}
              >
                📱 Bagikan WA
              </a>
            </div>
          </div>
        ) : (
          <div
            style={{
              padding: "1.25rem",
              backgroundColor: "#f9fafb",
              borderRadius: "0.5rem",
              marginTop: "0.5rem",
            }}
          >
            <p style={{ color: "#6b7280", margin: 0, fontSize: "0.85rem" }}>
              Voucher ini telah divalidasi oleh petugas lapangan saat kunjungan dan tidak dapat digunakan kembali.
            </p>
          </div>
        )}
      </div>
    </article>
  );
}

function VoucherContent() {
  const searchParams = useSearchParams();
  const requestedOrderId = searchParams.get("order_id");

  const [authenticated, setAuthenticated] = useState(false);
  const [loadingAuth, setLoadingAuth] = useState(true);
  const [orders, setOrders] = useState([]);
  const [selectedOrderId, setSelectedOrderId] = useState("");
  const [result, setResult] = useState(null);
  const [loadingVouchers, setLoadingVouchers] = useState(false);
  const [message, setMessage] = useState("");

  const loadVouchersForOrder = async (orderId) => {
    if (!orderId) return;
    setSelectedOrderId(orderId);
    setLoadingVouchers(true);
    setMessage("");
    setResult(null);

    try {
      const response = await apiRequest(`/account/orders/${encodeURIComponent(orderId)}/vouchers`);
      setResult(response.data);
      if (!response.data?.vouchers?.length) {
        setMessage("Voucher belum tersedia untuk status pesanan ini.");
      }
    } catch {
      setMessage("Voucher pesanan tidak dapat dibuka. Silakan coba lagi.");
    } finally {
      setLoadingVouchers(false);
    }
  };

  useEffect(() => {
    let active = true;

    async function init() {
      setLoadingAuth(true);
      try {
        await apiRequest("/me");
        if (!active) return;
        setAuthenticated(true);

        const ordersRes = await apiRequest("/account/orders");
        if (!active) return;

        const allOrders = ordersRes.data || [];
        const paidOrders = allOrders.filter(
          (o) => o.status === "paid" || o.status === "completed"
        );
        setOrders(paidOrders);

        // Select requested order or first paid order
        const targetId =
          requestedOrderId && allOrders.some((o) => o.order_id === requestedOrderId)
            ? requestedOrderId
            : paidOrders[0]?.order_id || "";

        if (targetId) {
          loadVouchersForOrder(targetId);
        }
      } catch {
        if (active) setAuthenticated(false);
      } finally {
        if (active) setLoadingAuth(false);
      }
    }

    init();
    return () => {
      active = false;
    };
  }, [requestedOrderId]);

  const shouldPrint = searchParams.get("print") === "1";
  useEffect(() => {
    if (shouldPrint && result?.vouchers?.length && !loadingVouchers) {
      const timer = setTimeout(() => {
        window.print();
      }, 700);
      return () => clearTimeout(timer);
    }
  }, [shouldPrint, result, loadingVouchers]);

  if (loadingAuth) {
    return (
      <div style={{ textAlign: "center", padding: "4rem 1rem", color: "var(--color-text-muted)" }}>
        <Loader2 size={32} className="animate-spin" style={{ margin: "0 auto 1rem" }} />
        <p>Memeriksa status akun…</p>
      </div>
    );
  }

  if (!authenticated) {
    return (
      <div
        className="access-state"
        style={{
          maxWidth: "520px",
          margin: "2rem auto",
          textAlign: "center",
          padding: "2.5rem 1.5rem",
          backgroundColor: "#fff",
          borderRadius: "1rem",
          border: "1px solid var(--color-border)",
          boxShadow: "0 1px 3px rgba(0,0,0,0.05)",
        }}
      >
        <div
          style={{
            width: "56px",
            height: "56px",
            borderRadius: "50%",
            backgroundColor: "#eff6ff",
            color: "#2563eb",
            display: "inline-flex",
            alignItems: "center",
            justifyContent: "center",
            marginBottom: "1rem",
          }}
        >
          <Ticket size={28} />
        </div>
        <h2 style={{ fontSize: "1.35rem", fontWeight: 700, margin: "0 0 0.5rem 0" }}>
          Masuk untuk Membuka Voucher
        </h2>
        <p
          style={{
            color: "var(--color-text-muted)",
            fontSize: "0.95rem",
            maxWidth: "400px",
            margin: "0 auto 1.5rem auto",
            lineHeight: 1.6,
          }}
        >
          Pemesanan tiket hanya dapat dilakukan oleh pengguna terdaftar. Masuk ke akun Anda untuk membuka dan menggunakan e-voucher perjalanan.
        </p>
        <Link
          href="/login?redirect=/voucher"
          className="ui-button ui-button-primary"
          style={{ padding: "0.6rem 1.5rem", display: "inline-flex", alignItems: "center", gap: "0.4rem" }}
        >
          Masuk ke Akun <ArrowRight size={16} />
        </Link>
      </div>
    );
  }

  const selectedOrder = orders.find((o) => o.order_id === selectedOrderId);

  return (
    <section className="page-intro voucher-panel" style={{ maxWidth: "600px", margin: "0 auto" }}>
      <style jsx global>{`
        @media print {
          header, nav, footer, .no-print, select, .action-buttons, .navbar, .page-header, .site-header, .site-footer {
            display: none !important;
          }
          body, main {
            background: #ffffff !important;
            padding: 0 !important;
            margin: 0 !important;
          }
          .printable-ticket {
            max-width: 100% !important;
            margin: 0 auto !important;
            border: 2px solid #000000 !important;
            box-shadow: none !important;
            padding: 1.5rem !important;
            page-break-inside: avoid;
          }
        }
      `}</style>
      {orders.length === 0 ? (
        <div
          style={{
            textAlign: "center",
            padding: "2.5rem 1.5rem",
            backgroundColor: "#fff",
            borderRadius: "0.75rem",
            border: "1px solid var(--color-border)",
          }}
        >
          <Ticket size={40} style={{ color: "var(--color-text-muted)", margin: "0 auto 1rem" }} />
          <h2 style={{ fontSize: "1.25rem", fontWeight: 700, margin: "0 0 0.5rem 0" }}>
            Belum Ada Voucher Aktif
          </h2>
          <p style={{ color: "var(--color-text-muted)", fontSize: "0.9rem", marginBottom: "1.5rem" }}>
            Anda belum memiliki pesanan tiket atau paket wisata yang lunas.
          </p>
          <Link href="/destinasi" className="ui-button ui-button-primary">
            Jelajahi Destinasi Wisata
          </Link>
        </div>
      ) : (
        <div>
          {/* Order picker if user has multiple paid orders */}
          <div
            className="no-print"
            style={{
              marginBottom: "1.5rem",
              padding: "1rem 1.25rem",
              backgroundColor: "#fff",
              borderRadius: "0.75rem",
              border: "1px solid var(--color-border)",
            }}
          >
            <label style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, marginBottom: "0.4rem" }}>
              Pilih Pesanan Kunjungan:
            </label>
            <div style={{ display: "flex", gap: "0.75rem", flexWrap: "wrap", alignItems: "center" }}>
              <select
                value={selectedOrderId}
                onChange={(e) => loadVouchersForOrder(e.target.value)}
                className="ui-input"
                style={{ flex: 1, minWidth: "220px", fontSize: "0.9rem" }}
              >
                {orders.map((order) => (
                  <option key={order.order_id} value={order.order_id}>
                    {order.order_id} · {order.items?.[0]?.name || "Pesanan Wisata"}
                  </option>
                ))}
              </select>
              <Link
                href="/akun"
                className="ui-button ui-button-outline"
                style={{ fontSize: "0.85rem", padding: "0.45rem 0.75rem" }}
              >
                Semua Pesanan
              </Link>
            </div>
          </div>

          {loadingVouchers && (
            <div style={{ textAlign: "center", padding: "2rem", color: "var(--color-text-muted)" }}>
              <Loader2 size={24} className="animate-spin" style={{ margin: "0 auto 0.5rem" }} />
              <p style={{ fontSize: "0.9rem" }}>Memuat e-voucher…</p>
            </div>
          )}

          {message && (
            <p
              role="status"
              style={{
                textAlign: "center",
                padding: "0.75rem",
                backgroundColor: "#eff6ff",
                color: "#1e40af",
                borderRadius: "0.5rem",
                fontSize: "0.9rem",
              }}
            >
              {message}
            </p>
          )}

          {result?.vouchers?.map((voucher) => (
            <VoucherCode key={voucher.token} voucher={voucher} order={selectedOrder} />
          ))}
        </div>
      )}
    </section>
  );
}

export default function VoucherPage() {
  return (
    <Shell>
      <PageHeader
        eyebrow="Voucher perjalanan"
        title="E-Voucher & Tiket Kunjungan"
        description="Buka dan tunjukkan kode QR voucher dari pesanan akun Anda kepada petugas di lokasi wisata."
        compact
      />
      <Suspense
        fallback={
          <div style={{ textAlign: "center", padding: "3rem" }}>
            <Loader2 size={30} className="animate-spin" style={{ margin: "0 auto" }} />
          </div>
        }
      >
        <VoucherContent />
      </Suspense>
    </Shell>
  );
}
