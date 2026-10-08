"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Star,
  CheckCircle,
  XCircle,
  Trash2,
  Search,
  Filter,
  Loader2,
  ArrowLeft,
  AlertTriangle,
  MessageSquare,
} from "lucide-react";
import { Shell } from "../../../components/Shell";
import { PageHeader } from "../../../components/PageHeader";
import { apiRequest } from "../../../lib/api";

export default function AdminReviewsPage() {
  const [reviews, setReviews] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("all");
  const [searchTerm, setSearchTerm] = useState("");
  const [busyId, setBusyId] = useState(null);
  const [message, setMessage] = useState("");
  const [deleteConfirmId, setDeleteConfirmId] = useState(null);
  const [rejectModalReview, setRejectModalReview] = useState(null);
  const [rejectReason, setRejectReason] = useState("");
  const [revision, setRevision] = useState(0);

  useEffect(() => {
    let active = true;
    const params = new URLSearchParams();
    if (statusFilter !== "all") params.set("status", statusFilter);
    if (searchTerm.trim()) params.set("search", searchTerm.trim());

    apiRequest(`/dashboard/reviews?${params.toString()}`)
      .then((res) => {
        if (!active) return;
        setReviews(res.data || []);
        setLoading(false);
      })
      .catch(() => {
        if (!active) return;
        setMessage("Gagal memuat daftar ulasan. Pastikan Anda masuk sebagai admin.");
        setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [statusFilter, searchTerm, revision]);

  async function handleModerate(reviewId, status, reason = null) {
    setBusyId(reviewId);
    setMessage("");
    try {
      await apiRequest(`/dashboard/reviews/${reviewId}`, {
        method: "PATCH",
        body: JSON.stringify({
          status,
          moderation_reason: reason,
        }),
      });
      setMessage(
        status === "approved"
          ? "Ulasan berhasil disetujui dan ditayangkan."
          : "Ulasan berhasil ditolak/disembunyikan."
      );
      setRejectModalReview(null);
      setRejectReason("");
      setRevision((r) => r + 1);
    } catch (err) {
      setMessage(err.message || "Gagal memproses moderasi ulasan.");
    } finally {
      setBusyId(null);
    }
  }

  async function handleDelete(reviewId) {
    setBusyId(reviewId);
    setMessage("");
    try {
      await apiRequest(`/dashboard/reviews/${reviewId}`, {
        method: "DELETE",
      });
      setMessage("Ulasan berhasil dihapus secara permanen.");
      setDeleteConfirmId(null);
      setRevision((r) => r + 1);
    } catch (err) {
      setMessage(err.message || "Gagal menghapus ulasan.");
    } finally {
      setBusyId(null);
    }
  }

  return (
    <Shell>
      <div style={{ maxWidth: "1200px", margin: "0 auto", padding: "1.5rem" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1.5rem", flexWrap: "wrap", gap: "1rem" }}>
          <div>
            <Link
              href="/dashboard"
              style={{
                display: "inline-flex",
                alignItems: "center",
                gap: "0.4rem",
                fontSize: "0.875rem",
                color: "var(--muted, #64748b)",
                marginBottom: "0.5rem",
                textDecoration: "none",
              }}
            >
              <ArrowLeft size={16} /> Kembali ke Dashboard
            </Link>
            <PageHeader
              eyebrow="Operasional & Pengawasan"
              title="Moderasi Ulasan Pengunjung"
              description="Periksa rating dan komentar wisatawan untuk memastikan ulasan berkualitas dan bebas spam."
              compact
            />
          </div>
        </div>

        {message && (
          <div
            role="status"
            style={{
              padding: "0.875rem 1.25rem",
              background: "#eff6ff",
              border: "1px solid #bfdbfe",
              borderRadius: "10px",
              color: "#1e40af",
              marginBottom: "1.5rem",
              fontSize: "0.875rem",
            }}
          >
            {message}
          </div>
        )}

        {/* Filter & Search Bar */}
        <div
          style={{
            display: "flex",
            gap: "1rem",
            marginBottom: "1.5rem",
            flexWrap: "wrap",
            alignItems: "center",
            background: "var(--surface, #ffffff)",
            padding: "1rem",
            borderRadius: "12px",
            border: "1px solid var(--border, #e2e8f0)",
          }}
        >
          <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", flex: 1, minWidth: "260px" }}>
            <Search size={18} color="#94a3b8" />
            <input
              type="text"
              placeholder="Cari kata kunci, nama wisatawan, atau nama wisata..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              style={{
                width: "100%",
                padding: "0.5rem 0.75rem",
                border: "1px solid #cbd5e1",
                borderRadius: "8px",
                fontSize: "0.875rem",
              }}
            />
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
            <Filter size={16} color="#64748b" />
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              style={{
                padding: "0.5rem 0.75rem",
                border: "1px solid #cbd5e1",
                borderRadius: "8px",
                fontSize: "0.875rem",
                background: "#ffffff",
              }}
            >
              <option value="all">Semua Status</option>
              <option value="approved">Disetujui (Tayang)</option>
              <option value="pending">Menunggu Moderasi</option>
              <option value="rejected">Ditolak / Disembunyikan</option>
            </select>
          </div>
        </div>

        {/* List of Reviews */}
        {loading ? (
          <div style={{ textAlign: "center", padding: "4rem 2rem", color: "var(--muted, #64748b)" }}>
            <Loader2 size={32} className="animate-spin" style={{ margin: "0 auto 1rem" }} />
            <p>Memuat daftar ulasan…</p>
          </div>
        ) : reviews.length === 0 ? (
          <div
            style={{
              padding: "3.5rem 2rem",
              textAlign: "center",
              background: "#ffffff",
              borderRadius: "12px",
              border: "1px dashed #cbd5e1",
            }}
          >
            <MessageSquare size={36} color="#94a3b8" style={{ margin: "0 auto 0.75rem" }} />
            <h3 style={{ fontSize: "1.1rem", fontWeight: 600, color: "#1e293b", margin: "0 0 0.5rem" }}>
              Tidak ada ulasan ditemukan
            </h3>
            <p style={{ color: "#64748b", fontSize: "0.875rem" }}>
              Belum ada ulasan yang sesuai dengan kriteria filter saat ini.
            </p>
          </div>
        ) : (
          <div style={{ display: "flex", flexDirection: "column", gap: "1rem" }}>
            {reviews.map((rev) => (
              <div
                key={rev.id}
                style={{
                  background: "#ffffff",
                  borderRadius: "12px",
                  border: "1px solid var(--border, #e2e8f0)",
                  padding: "1.25rem",
                  boxShadow: "0 1px 3px rgba(0,0,0,0.04)",
                }}
              >
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", flexWrap: "wrap", gap: "1rem", marginBottom: "0.75rem" }}>
                  <div>
                    <div style={{ display: "flex", alignItems: "center", gap: "0.6rem", flexWrap: "wrap" }}>
                      <strong style={{ fontSize: "1rem", color: "#0f172a" }}>
                        {rev.user?.name || "Pengguna"}
                      </strong>
                      <span style={{ fontSize: "0.8rem", color: "#64748b" }}>
                        ({rev.user?.email || "Email tersembunyi"})
                      </span>
                      <span
                        style={{
                          fontSize: "0.75rem",
                          fontWeight: 600,
                          padding: "0.2rem 0.6rem",
                          borderRadius: "999px",
                          background:
                            rev.status === "approved"
                              ? "#dcfce7"
                              : rev.status === "rejected"
                                ? "#fee2e2"
                                : "#fef3c7",
                          color:
                            rev.status === "approved"
                              ? "#166534"
                              : rev.status === "rejected"
                                ? "#991b1b"
                                : "#92400e",
                        }}
                      >
                        {rev.status === "approved"
                          ? "✓ Tayang"
                          : rev.status === "rejected"
                            ? "✕ Ditolak"
                            : "⏳ Menunggu"}
                      </span>
                    </div>

                    <div style={{ marginTop: "0.25rem", fontSize: "0.85rem", color: "#475569" }}>
                      Produk / Wisata: <strong>{rev.product?.name || "Paket Wisata"}</strong>
                      {rev.order?.order_id && (
                        <span style={{ marginLeft: "0.75rem", color: "#64748b" }}>
                          (Order: {rev.order.order_id})
                        </span>
                      )}
                    </div>
                  </div>

                  <div style={{ display: "flex", alignItems: "center", gap: "2px" }}>
                    {[1, 2, 3, 4, 5].map((star) => (
                      <Star
                        key={star}
                        size={16}
                        fill={star <= rev.rating ? "#f59e0b" : "transparent"}
                        color={star <= rev.rating ? "#f59e0b" : "#cbd5e1"}
                      />
                    ))}
                    <span style={{ marginLeft: "0.4rem", fontWeight: 700, fontSize: "0.9rem", color: "#b45309" }}>
                      {rev.rating}/5
                    </span>
                  </div>
                </div>

                {rev.comment && (
                  <p
                    style={{
                      background: "#f8fafc",
                      padding: "0.875rem",
                      borderRadius: "8px",
                      fontSize: "0.9rem",
                      color: "#334155",
                      lineHeight: 1.5,
                      margin: "0.5rem 0",
                    }}
                  >
                    &ldquo;{rev.comment}&rdquo;
                  </p>
                )}

                {rev.moderation_reason && (
                  <p style={{ fontSize: "0.8rem", color: "#dc2626", margin: "0.4rem 0" }}>
                    Alasan penolakan: {rev.moderation_reason}
                  </p>
                )}

                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    marginTop: "0.75rem",
                    paddingTop: "0.75rem",
                    borderTop: "1px solid #f1f5f9",
                    flexWrap: "wrap",
                    gap: "0.5rem",
                  }}
                >
                  <span style={{ fontSize: "0.75rem", color: "#94a3b8" }}>
                    Dibuat: {new Date(rev.created_at).toLocaleDateString("id-ID", { dateStyle: "medium" })}
                  </span>

                  <div style={{ display: "flex", gap: "0.5rem", alignItems: "center" }}>
                    {rev.status !== "approved" && (
                      <button
                        type="button"
                        onClick={() => handleModerate(rev.id, "approved")}
                        disabled={busyId === rev.id}
                        className="ui-button"
                        style={{ padding: "0.4rem 0.8rem", fontSize: "0.8rem", gap: "0.3rem" }}
                      >
                        <CheckCircle size={14} /> Setujui
                      </button>
                    )}

                    {rev.status !== "rejected" && (
                      <button
                        type="button"
                        onClick={() => setRejectModalReview(rev)}
                        disabled={busyId === rev.id}
                        className="ui-button ui-button-outline"
                        style={{ padding: "0.4rem 0.8rem", fontSize: "0.8rem", gap: "0.3rem", color: "#dc2626", borderColor: "#fca5a5" }}
                      >
                        <XCircle size={14} /> Tolak
                      </button>
                    )}

                    <button
                      type="button"
                      onClick={() => setDeleteConfirmId(rev.id)}
                      disabled={busyId === rev.id}
                      style={{
                        background: "transparent",
                        border: "none",
                        color: "#94a3b8",
                        cursor: "pointer",
                        padding: "0.4rem",
                        borderRadius: "6px",
                      }}
                      title="Hapus ulasan permanen"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Modal Konfirmasi Hapus */}
        {deleteConfirmId && (
          <div
            role="dialog"
            aria-modal="true"
            style={{
              position: "fixed",
              inset: 0,
              backgroundColor: "rgba(15, 23, 42, 0.6)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              padding: "1rem",
              zIndex: 9999,
            }}
          >
            <div style={{ background: "#ffffff", padding: "1.75rem", borderRadius: "14px", maxWidth: "420px", width: "100%" }}>
              <div style={{ display: "flex", gap: "0.75rem", alignItems: "center", marginBottom: "1rem", color: "#dc2626" }}>
                <AlertTriangle size={24} />
                <h3 style={{ margin: 0, fontSize: "1.1rem", fontWeight: 700 }}>Hapus Ulasan?</h3>
              </div>
              <p style={{ fontSize: "0.875rem", color: "#64748b", marginBottom: "1.5rem" }}>
                Tindakan ini akan menghapus ulasan secara permanen dari basis data dan tidak dapat dibatalkan.
              </p>
              <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                <button
                  type="button"
                  onClick={() => setDeleteConfirmId(null)}
                  disabled={busyId === deleteConfirmId}
                  className="ui-button ui-button-outline"
                >
                  Batal
                </button>
                <button
                  type="button"
                  onClick={() => handleDelete(deleteConfirmId)}
                  disabled={busyId === deleteConfirmId}
                  className="ui-button"
                  style={{ background: "#dc2626", borderColor: "#dc2626", color: "#ffffff" }}
                >
                  {busyId === deleteConfirmId ? "Menghapus…" : "Ya, Hapus"}
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Modal Alasan Penolakan */}
        {rejectModalReview && (
          <div
            role="dialog"
            aria-modal="true"
            style={{
              position: "fixed",
              inset: 0,
              backgroundColor: "rgba(15, 23, 42, 0.6)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              padding: "1rem",
              zIndex: 9999,
            }}
          >
            <div style={{ background: "#ffffff", padding: "1.75rem", borderRadius: "14px", maxWidth: "460px", width: "100%" }}>
              <h3 style={{ margin: "0 0 0.5rem", fontSize: "1.15rem", fontWeight: 700, color: "#0f172a" }}>
                Tolak & Sembunyikan Ulasan
              </h3>
              <p style={{ fontSize: "0.875rem", color: "#64748b", marginBottom: "1rem" }}>
                Ulasan ini tidak akan ditampilkan kepada publik. Anda dapat mencantumkan alasan moderasi.
              </p>

              <label htmlFor="moderation-reason" style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.4rem" }}>
                Alasan Penolakan (opsional)
              </label>
              <textarea
                id="moderation-reason"
                rows={3}
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                placeholder="Contoh: Mengandung spam, kata kasar, atau tidak relevan dengan objek wisata."
                style={{
                  width: "100%",
                  border: "1px solid #cbd5e1",
                  borderRadius: "8px",
                  padding: "0.75rem",
                  fontSize: "0.875rem",
                  marginBottom: "1.25rem",
                  fontFamily: "inherit",
                }}
              />

              <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                <button
                  type="button"
                  onClick={() => setRejectModalReview(null)}
                  disabled={busyId === rejectModalReview.id}
                  className="ui-button ui-button-outline"
                >
                  Batal
                </button>
                <button
                  type="button"
                  onClick={() => handleModerate(rejectModalReview.id, "rejected", rejectReason)}
                  disabled={busyId === rejectModalReview.id}
                  className="ui-button"
                  style={{ background: "#dc2626", borderColor: "#dc2626", color: "#ffffff" }}
                >
                  {busyId === rejectModalReview.id ? "Memproses…" : "Tolak Ulasan"}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </Shell>
  );
}
