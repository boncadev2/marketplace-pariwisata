"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  ShieldAlert,
  AlertTriangle,
  CheckCircle2,
  Clock,
  XCircle,
  ArrowLeft,
  Search,
  Lock,
  User,
  FileText,
  Loader2,
  ExternalLink,
  X,
} from "lucide-react";
import { Shell } from "../../../components/Shell";
import { PageHeader } from "../../../components/PageHeader";
import { apiRequest } from "../../../lib/api";

const statusConfig = {
  open: {
    label: "Terbuka (Baru)",
    badgeClass: "bg-amber-100 text-amber-800 border-amber-200",
    icon: AlertTriangle,
  },
  under_review: {
    label: "Sedang Ditinjau",
    badgeClass: "bg-blue-100 text-blue-800 border-blue-200",
    icon: Clock,
  },
  resolved: {
    label: "Terselesaikan",
    badgeClass: "bg-emerald-100 text-emerald-800 border-emerald-200",
    icon: CheckCircle2,
  },
  closed: {
    label: "Ditutup",
    badgeClass: "bg-slate-100 text-slate-700 border-slate-200",
    icon: XCircle,
  },
};

export default function AdminDisputesPage() {
  const [disputes, setDisputes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("all");
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedDispute, setSelectedDispute] = useState(null);
  const [newStatus, setNewStatus] = useState("under_review");
  const [resolutionText, setResolutionText] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [modalError, setModalError] = useState("");
  const [revision, setRevision] = useState(0);

  useEffect(() => {
    let active = true;
    const params = new URLSearchParams();
    if (statusFilter !== "all") params.set("status", statusFilter);

    apiRequest(`/disputes?${params.toString()}`)
      .then((res) => {
        if (!active) return;
        setDisputes(res.data || []);
        setLoading(false);
      })
      .catch((err) => {
        if (!active) return;
        setMessage(err.message || "Gagal memuat daftar sengketa.");
        setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [statusFilter, revision]);

  function openDisputeModal(dispute) {
    setSelectedDispute(dispute);
    setNewStatus(dispute.status || "under_review");
    setResolutionText(dispute.resolution || "");
    setPassword("");
    setModalError("");
  }

  function closeDisputeModal() {
    setSelectedDispute(null);
    setPassword("");
    setModalError("");
  }

  async function handleSaveResolution(e) {
    e.preventDefault();
    if (busy || !selectedDispute) return;
    if (!password) {
      setModalError("Kata sandi admin diperlukan untuk mengonfirmasi keputusan.");
      return;
    }

    setBusy(true);
    setModalError("");
    setMessage("");

    try {
      // 1. Password confirmation for sensitive action
      const confirmRes = await apiRequest("/security/confirm-password", {
        method: "POST",
        body: JSON.stringify({ password }),
      });

      // 2. Submit resolution update
      await apiRequest(`/disputes/${selectedDispute.id}`, {
        method: "PATCH",
        headers: {
          "X-Sensitive-Confirmation": confirmRes.confirmation_token,
        },
        body: JSON.stringify({
          status: newStatus,
          resolution: resolutionText.trim() || null,
        }),
      });

      setMessage(`Keputusan sengketa #${selectedDispute.id} berhasil diperbarui.`);
      closeDisputeModal();
      setRevision((r) => r + 1);
    } catch (err) {
      setModalError(err.message || "Gagal memperbarui status sengketa. Periksa kata sandi Anda.");
    } finally {
      setBusy(false);
    }
  }

  // Filter client-side for search
  const filteredDisputes = disputes.filter((item) => {
    if (!searchTerm.trim()) return true;
    const term = searchTerm.toLowerCase();
    const orderCode = item.order?.public_id?.toLowerCase() || "";
    const reporterName = item.reporter?.name?.toLowerCase() || "";
    const reporterEmail = item.reporter?.email?.toLowerCase() || "";
    const reason = item.reason?.toLowerCase() || "";
    const desc = item.description?.toLowerCase() || "";
    return (
      orderCode.includes(term) ||
      reporterName.includes(term) ||
      reporterEmail.includes(term) ||
      reason.includes(term) ||
      desc.includes(term)
    );
  });

  // Calculate statistics
  const stats = {
    total: disputes.length,
    open: disputes.filter((d) => d.status === "open").length,
    under_review: disputes.filter((d) => d.status === "under_review").length,
    resolved: disputes.filter((d) => d.status === "resolved").length,
    closed: disputes.filter((d) => d.status === "closed").length,
  };

  return (
    <Shell>
      <div style={{ maxWidth: "1200px", margin: "0 auto", padding: "1.5rem" }}>
        {/* Navigation Breadcrumb */}
        <div style={{ marginBottom: "1.5rem" }}>
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
            eyebrow="Operasional & Mediasi"
            title="Resolusi Sengketa & Komplain"
            description="Investigasi kendala operasional wisatawan, periksa bukti aduan, dan tetapkan keputusan resolusi mediasi transaksi."
            compact
          />
        </div>

        {/* Global Feedback Banner */}
        {message && (
          <div
            role="status"
            style={{
              padding: "0.875rem 1.25rem",
              background: "#ecfdf5",
              border: "1px solid #a7f3d0",
              borderRadius: "10px",
              color: "#065f46",
              marginBottom: "1.5rem",
              fontSize: "0.875rem",
              display: "flex",
              alignItems: "center",
              gap: "0.5rem",
            }}
          >
            <CheckCircle2 size={18} color="#059669" />
            <span>{message}</span>
          </div>
        )}

        {/* Stat Cards */}
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))",
            gap: "1rem",
            marginBottom: "1.5rem",
          }}
        >
          <div className="border border-slate-200 bg-white p-4 rounded-xl shadow-xs">
            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Sengketa</p>
            <p className="text-2xl font-bold text-slate-900 mt-1">{stats.total}</p>
          </div>
          <div className="border border-amber-200 bg-amber-50/50 p-4 rounded-xl shadow-xs">
            <p className="text-xs font-semibold text-amber-700 uppercase tracking-wide">Perlu Penanganan</p>
            <p className="text-2xl font-bold text-amber-800 mt-1">{stats.open}</p>
          </div>
          <div className="border border-blue-200 bg-blue-50/50 p-4 rounded-xl shadow-xs">
            <p className="text-xs font-semibold text-blue-700 uppercase tracking-wide">Sedang Ditinjau</p>
            <p className="text-2xl font-bold text-blue-800 mt-1">{stats.under_review}</p>
          </div>
          <div className="border border-emerald-200 bg-emerald-50/50 p-4 rounded-xl shadow-xs">
            <p className="text-xs font-semibold text-emerald-700 uppercase tracking-wide">Terselesaikan</p>
            <p className="text-2xl font-bold text-emerald-800 mt-1">{stats.resolved}</p>
          </div>
          <div className="border border-slate-200 bg-slate-50/50 p-4 rounded-xl shadow-xs">
            <p className="text-xs font-semibold text-slate-600 uppercase tracking-wide">Ditutup</p>
            <p className="text-2xl font-bold text-slate-700 mt-1">{stats.closed}</p>
          </div>
        </div>

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
              placeholder="Cari ID pesanan, nama pelapor, atau alasan komplain..."
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
            <label htmlFor="filter-status" style={{ fontSize: "0.875rem", fontWeight: 500, color: "#475569" }}>
              Status:
            </label>
            <select
              id="filter-status"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              style={{
                padding: "0.5rem 0.75rem",
                border: "1px solid #cbd5e1",
                borderRadius: "8px",
                fontSize: "0.875rem",
                background: "#ffffff",
                cursor: "pointer",
              }}
            >
              <option value="all">Semua Status</option>
              <option value="open">Terbuka (Open)</option>
              <option value="under_review">Sedang Ditinjau</option>
              <option value="resolved">Terselesaikan</option>
              <option value="closed">Ditutup</option>
            </select>
          </div>
        </div>

        {/* Dispute List Table */}
        <div
          style={{
            background: "#ffffff",
            borderRadius: "12px",
            border: "1px solid #e2e8f0",
            overflow: "hidden",
            boxShadow: "0 1px 3px rgba(0,0,0,0.05)",
          }}
        >
          {loading ? (
            <div style={{ padding: "3rem", textAlign: "center", color: "#64748b" }}>
              <Loader2 size={32} className="animate-spin" style={{ margin: "0 auto 1rem" }} />
              <p>Memuat daftar sengketa operasional...</p>
            </div>
          ) : filteredDisputes.length === 0 ? (
            <div style={{ padding: "3rem", textAlign: "center", color: "#64748b" }}>
              <ShieldAlert size={40} color="#94a3b8" style={{ margin: "0 auto 1rem" }} />
              <p style={{ fontWeight: 600, color: "#1e293b", marginBottom: "0.25rem" }}>
                Tidak ada sengketa yang sesuai
              </p>
              <p style={{ fontSize: "0.875rem" }}>
                Semua pesanan berjalan tertib atau tidak ada komplain yang cocok dengan filter saat ini.
              </p>
            </div>
          ) : (
            <div style={{ overflowX: "auto" }}>
              <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left", fontSize: "0.875rem" }}>
                <thead>
                  <tr style={{ background: "#f8fafc", borderBottom: "1px solid #e2e8f0", color: "#475569" }}>
                    <th style={{ padding: "0.75rem 1rem", fontWeight: 600 }}>ID</th>
                    <th style={{ padding: "0.75rem 1rem", fontWeight: 600 }}>Tanggal</th>
                    <th style={{ padding: "0.75rem 1rem", fontWeight: 600 }}>Pesanan</th>
                    <th style={{ padding: "0.75rem 1rem", fontWeight: 600 }}>Pelapor</th>
                    <th style={{ padding: "0.75rem 1rem", fontWeight: 600 }}>Alasan Sengketa</th>
                    <th style={{ padding: "0.75rem 1rem", fontWeight: 600 }}>Status</th>
                    <th style={{ padding: "0.75rem 1rem", fontWeight: 600, textAlign: "right" }}>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredDisputes.map((item) => {
                    const cfg = statusConfig[item.status] || statusConfig.open;
                    const StatusIcon = cfg.icon;
                    return (
                      <tr
                        key={item.id}
                        style={{
                          borderBottom: "1px solid #f1f5f9",
                          transition: "background 0.15s",
                        }}
                        className="hover:bg-slate-50/70"
                      >
                        <td style={{ padding: "0.875rem 1rem", fontWeight: 600, color: "#0f172a" }}>
                          #{item.id}
                        </td>
                        <td style={{ padding: "0.875rem 1rem", color: "#64748b", whiteSpace: "nowrap" }}>
                          {item.created_at
                            ? new Date(item.created_at).toLocaleDateString("id-ID", {
                                day: "numeric",
                                month: "short",
                                year: "numeric",
                              })
                            : "-"}
                        </td>
                        <td style={{ padding: "0.875rem 1rem" }}>
                          <span className="font-mono text-xs font-semibold text-slate-800 bg-slate-100 px-2 py-1 rounded">
                            {item.order?.public_id || `Order #${item.order_id}`}
                          </span>
                        </td>
                        <td style={{ padding: "0.875rem 1rem" }}>
                          <div style={{ fontWeight: 500, color: "#1e293b" }}>
                            {item.reporter?.name || "Wisatawan"}
                          </div>
                          <div style={{ fontSize: "0.75rem", color: "#64748b" }}>
                            {item.reporter?.email || "-"}
                          </div>
                        </td>
                        <td style={{ padding: "0.875rem 1rem", maxWidth: "280px" }}>
                          <div style={{ fontWeight: 600, color: "#0f172a", marginBottom: "0.2rem" }}>
                            {item.reason}
                          </div>
                          {item.description && (
                            <p
                              style={{
                                fontSize: "0.75rem",
                                color: "#64748b",
                                margin: 0,
                                whiteSpace: "nowrap",
                                overflow: "hidden",
                                textOverflow: "ellipsis",
                              }}
                            >
                              {item.description}
                            </p>
                          )}
                        </td>
                        <td style={{ padding: "0.875rem 1rem" }}>
                          <span
                            className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border ${cfg.badgeClass}`}
                          >
                            <StatusIcon size={13} />
                            {cfg.label}
                          </span>
                        </td>
                        <td style={{ padding: "0.875rem 1rem", textAlign: "right" }}>
                          <button
                            type="button"
                            onClick={() => openDisputeModal(item)}
                            className="px-3 py-1.5 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors"
                          >
                            Tinjau Mediasi
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Investigation & Resolution Modal */}
      {selectedDispute && (
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="dispute-modal-title"
          style={{
            position: "fixed",
            inset: 0,
            backgroundColor: "rgba(15, 23, 42, 0.65)",
            backdropFilter: "blur(4px)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            padding: "1rem",
            zIndex: 9999,
          }}
        >
          <div
            style={{
              background: "#ffffff",
              borderRadius: "16px",
              maxWidth: "600px",
              width: "100%",
              maxHeight: "90vh",
              overflowY: "auto",
              padding: "1.75rem",
              boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.2)",
            }}
          >
            {/* Modal Header */}
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1.25rem" }}>
              <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
                <ShieldAlert size={22} className="text-blue-600" />
                <h3 id="dispute-modal-title" style={{ fontSize: "1.25rem", fontWeight: 700, margin: 0, color: "#0f172a" }}>
                  Investigasi Sengketa #{selectedDispute.id}
                </h3>
              </div>
              <button
                type="button"
                onClick={closeDisputeModal}
                style={{ background: "transparent", border: "none", cursor: "pointer", color: "#64748b", padding: "4px" }}
                aria-label="Tutup modal"
              >
                <X size={20} />
              </button>
            </div>

            {/* Error Message inside Modal */}
            {modalError && (
              <div
                style={{
                  padding: "0.75rem 1rem",
                  background: "#fef2f2",
                  border: "1px solid #fecaca",
                  borderRadius: "8px",
                  color: "#991b1b",
                  marginBottom: "1rem",
                  fontSize: "0.875rem",
                }}
              >
                {modalError}
              </div>
            )}

            {/* Tourist & Order Details Card */}
            <div
              style={{
                background: "#f8fafc",
                borderRadius: "10px",
                border: "1px solid #e2e8f0",
                padding: "1rem",
                marginBottom: "1.25rem",
                fontSize: "0.875rem",
              }}
            >
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "0.75rem", marginBottom: "0.75rem" }}>
                <div>
                  <span style={{ fontSize: "0.75rem", color: "#64748b", display: "block" }}>Pelapor:</span>
                  <span style={{ fontWeight: 600, color: "#0f172a" }}>
                    {selectedDispute.reporter?.name || "Wisatawan"}
                  </span>
                  <span style={{ fontSize: "0.75rem", color: "#64748b", display: "block" }}>
                    {selectedDispute.reporter?.email}
                  </span>
                </div>
                <div>
                  <span style={{ fontSize: "0.75rem", color: "#64748b", display: "block" }}>No. Pesanan:</span>
                  <span className="font-mono text-xs font-semibold text-slate-800 bg-white px-2 py-0.5 rounded border border-slate-200">
                    {selectedDispute.order?.public_id || `Order #${selectedDispute.order_id}`}
                  </span>
                  {selectedDispute.order?.total && (
                    <span style={{ fontSize: "0.75rem", color: "#059669", display: "block", fontWeight: 600, marginTop: "2px" }}>
                      Rp {Number(selectedDispute.order.total).toLocaleString("id-ID")}
                    </span>
                  )}
                </div>
              </div>

              <div style={{ borderTop: "1px solid #e2e8f0", paddingTop: "0.75rem" }}>
                <span style={{ fontSize: "0.75rem", color: "#64748b", display: "block" }}>Alasan Sengketa:</span>
                <p style={{ fontWeight: 600, color: "#0f172a", margin: "0.2rem 0 0.5rem" }}>
                  {selectedDispute.reason}
                </p>
                <span style={{ fontSize: "0.75rem", color: "#64748b", display: "block" }}>Rincian Kronologi Wisatawan:</span>
                <p style={{ color: "#334155", background: "#ffffff", padding: "0.6rem 0.75rem", borderRadius: "6px", border: "1px solid #e2e8f0", margin: "0.2rem 0 0", whiteSpace: "pre-wrap", lineHeight: 1.5 }}>
                  {selectedDispute.description || "Tidak ada keterangan kronologi tambahan."}
                </p>
              </div>
            </div>

            {/* Quick Links for Action */}
            <div style={{ display: "flex", gap: "0.75rem", marginBottom: "1.25rem", flexWrap: "wrap" }}>
              <Link
                href="/dashboard/refund"
                target="_blank"
                className="text-xs text-blue-700 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg border border-blue-200 inline-flex items-center gap-1 font-medium"
              >
                Buka Pengajuan Refund <ExternalLink size={12} />
              </Link>
              <Link
                href="/dashboard/tiket-bantuan"
                target="_blank"
                className="text-xs text-slate-700 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200 inline-flex items-center gap-1 font-medium"
              >
                Buka Tiket Bantuan CS <ExternalLink size={12} />
              </Link>
            </div>

            {/* Resolution Form */}
            <form onSubmit={handleSaveResolution} style={{ display: "flex", flexDirection: "column", gap: "1rem" }}>
              <div>
                <label style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                  Pembaruan Status Sengketa:
                </label>
                <select
                  value={newStatus}
                  onChange={(e) => setNewStatus(e.target.value)}
                  style={{
                    width: "100%",
                    padding: "0.6rem 0.75rem",
                    border: "1px solid #cbd5e1",
                    borderRadius: "8px",
                    fontSize: "0.875rem",
                    background: "#ffffff",
                  }}
                >
                  <option value="under_review">Sedang Ditinjau (Investigasi Berlangsung)</option>
                  <option value="resolved">Terselesaikan (Solusi/Kompensasi Disepakati)</option>
                  <option value="closed">Ditutup (Mediasi Berakhir)</option>
                  <option value="open">Terbuka (Perlu Penanganan Ulang)</option>
                </select>
              </div>

              <div>
                <label style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                  Catatan Resolusi / Putusan Mediasi:
                </label>
                <textarea
                  rows={3}
                  value={resolutionText}
                  onChange={(e) => setResolutionText(e.target.value)}
                  placeholder="Contoh: Telah diklarifikasi dengan mitra homestay. Wisatawan menyetujui kompensasi voucher potongan 50% atau telah diajukan pengembalian dana melalui refund..."
                  style={{
                    width: "100%",
                    padding: "0.6rem 0.75rem",
                    border: "1px solid #cbd5e1",
                    borderRadius: "8px",
                    fontSize: "0.875rem",
                    lineHeight: 1.5,
                  }}
                />
              </div>

              {/* Sensitive Confirmation: Admin Password */}
              <div style={{ background: "#f8fafc", padding: "0.875rem", borderRadius: "8px", border: "1px solid #e2e8f0" }}>
                <label style={{ display: "flex", alignItems: "center", gap: "0.4rem", fontSize: "0.875rem", fontWeight: 600, color: "#0f172a", marginBottom: "0.35rem" }}>
                  <Lock size={15} color="#64748b" /> Konfirmasi Kata Sandi Administrator:
                </label>
                <p style={{ fontSize: "0.75rem", color: "#64748b", margin: "0 0 0.5rem" }}>
                  Tindakan resolusi sengketa memiliki konsekuensi hukum & operasional. Masukkan kata sandi admin Anda.
                </p>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Kata sandi administrator..."
                  required
                  style={{
                    width: "100%",
                    padding: "0.5rem 0.75rem",
                    border: "1px solid #cbd5e1",
                    borderRadius: "6px",
                    fontSize: "0.875rem",
                  }}
                />
              </div>

              {/* Action Buttons */}
              <div style={{ display: "flex", justifyContent: "flex-end", gap: "0.75rem", marginTop: "0.5rem" }}>
                <button
                  type="button"
                  onClick={closeDisputeModal}
                  disabled={busy}
                  style={{
                    padding: "0.6rem 1rem",
                    border: "1px solid #cbd5e1",
                    background: "#ffffff",
                    color: "#475569",
                    borderRadius: "8px",
                    fontSize: "0.875rem",
                    fontWeight: 500,
                    cursor: "pointer",
                  }}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={busy}
                  style={{
                    padding: "0.6rem 1.25rem",
                    background: "#2563eb",
                    color: "#ffffff",
                    border: "none",
                    borderRadius: "8px",
                    fontSize: "0.875rem",
                    fontWeight: 600,
                    cursor: "pointer",
                    display: "flex",
                    alignItems: "center",
                    gap: "0.5rem",
                  }}
                >
                  {busy ? <Loader2 size={16} className="animate-spin" /> : null}
                  {busy ? "Menyimpan Keputusan..." : "Simpan Putusan Mediasi"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </Shell>
  );
}
