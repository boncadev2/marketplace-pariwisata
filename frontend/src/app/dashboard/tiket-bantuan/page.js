"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Headphones,
  CheckCircle2,
  XCircle,
  Clock,
  Send,
  Loader2,
  ArrowLeft,
  Search,
  Filter,
  Paperclip,
  User,
  ShieldAlert,
} from "lucide-react";
import { Shell } from "../../../components/Shell";
import { PageHeader } from "../../../components/PageHeader";
import { apiRequest } from "../../../lib/api";

const categoryLabels = {
  booking: "Pemesanan & Jadwal",
  payment: "Pembayaran",
  voucher: "Voucher Tiket",
  visit: "Kunjungan & Lokasi",
  other: "Lainnya",
};

export default function AdminSupportTicketsPage() {
  const [tickets, setTickets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("all");
  const [categoryFilter, setCategoryFilter] = useState("all");
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedTicket, setSelectedTicket] = useState(null);
  const [ticketDetail, setTicketDetail] = useState(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [replyMessage, setReplyMessage] = useState("");
  const [replyBusy, setReplyBusy] = useState(false);
  const [statusBusy, setStatusBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);

  // Fetch ticket list
  useEffect(() => {
    let active = true;
    const params = new URLSearchParams();
    if (statusFilter !== "all") params.set("status", statusFilter);
    if (categoryFilter !== "all") params.set("category", categoryFilter);
    if (searchTerm.trim()) params.set("search", searchTerm.trim());

    apiRequest(`/dashboard/support-tickets?${params.toString()}`)
      .then((res) => {
        if (!active) return;
        setTickets(res.data || []);
        setLoading(false);
      })
      .catch((err) => {
        if (!active) return;
        setError(err.message || "Gagal memuat tiket bantuan.");
        setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [statusFilter, categoryFilter, searchTerm, revision]);

  function handleSelectTicket(ticket) {
    setSelectedTicket(ticket);
    setTicketDetail(null);
    setDetailLoading(true);
  }

  function handleCloseTicket() {
    setSelectedTicket(null);
    setTicketDetail(null);
  }

  // Fetch detail for selected ticket
  useEffect(() => {
    if (!selectedTicket) return;

    let active = true;
    apiRequest(`/dashboard/support-tickets/${selectedTicket.id}`)
      .then((res) => {
        if (!active) return;
        setTicketDetail(res.data);
      })
      .catch((err) => {
        if (!active) return;
        setError(err.message || "Gagal memuat rincian tiket.");
      })
      .finally(() => {
        if (!active) return;
        setDetailLoading(false);
      });

    return () => {
      active = false;
    };
  }, [selectedTicket, revision]);

  async function handleSendReply(e) {
    e.preventDefault();
    if (!replyMessage.trim() || replyBusy || !selectedTicket) return;

    setReplyBusy(true);
    setMessage("");
    try {
      await apiRequest(`/dashboard/support-tickets/${selectedTicket.id}/reply`, {
        method: "POST",
        body: JSON.stringify({ message: replyMessage.trim() }),
      });
      setReplyMessage("");
      setMessage("Balasan berhasil dikirim ke pelanggan.");
      setRevision((r) => r + 1);
    } catch (err) {
      setError(err.message || "Gagal mengirim balasan.");
    } finally {
      setReplyBusy(false);
    }
  }

  async function handleToggleStatus() {
    if (!selectedTicket || statusBusy) return;

    const nextStatus = selectedTicket.status === "open" ? "closed" : "open";
    setStatusBusy(true);
    setMessage("");
    try {
      await apiRequest(`/dashboard/support-tickets/${selectedTicket.id}/status`, {
        method: "PATCH",
        body: JSON.stringify({ status: nextStatus }),
      });
      setMessage(
        nextStatus === "closed"
          ? "Tiket telah ditutup."
          : "Tiket berhasil dibuka kembali."
      );
      setSelectedTicket((prev) => ({ ...prev, status: nextStatus }));
      setRevision((r) => r + 1);
    } catch (err) {
      setError(err.message || "Gagal memperbarui status tiket.");
    } finally {
      setStatusBusy(false);
    }
  }

  return (
    <Shell>
      <div style={{ maxWidth: "1280px", margin: "0 auto", padding: "1.5rem" }}>
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
            eyebrow="Layanan Pelanggan"
            title="Kelola Tiket Bantuan"
            description="Tanggapi kendala pengunjung, konfirmasi masalah pembayaran, dan selesaikan aduan layanan."
            compact
          />
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
              display: "flex",
              alignItems: "center",
              gap: "0.5rem",
            }}
          >
            <CheckCircle2 size={18} />
            <span>{message}</span>
          </div>
        )}

        {error && (
          <div
            role="alert"
            style={{
              padding: "0.875rem 1.25rem",
              background: "#fef2f2",
              border: "1px solid #fecaca",
              borderRadius: "10px",
              color: "#b91c1c",
              marginBottom: "1.5rem",
              fontSize: "0.875rem",
            }}
          >
            {error}
          </div>
        )}

        {/* Filter Bar */}
        <div
          style={{
            display: "flex",
            gap: "1rem",
            marginBottom: "1.5rem",
            flexWrap: "wrap",
            alignItems: "center",
            background: "#ffffff",
            padding: "1rem",
            borderRadius: "12px",
            border: "1px solid var(--border, #e2e8f0)",
          }}
        >
          <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", flex: 1, minWidth: "260px" }}>
            <Search size={18} color="#94a3b8" />
            <input
              type="text"
              placeholder="Cari nama, email, nomor tiket, atau order ID..."
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
              <option value="open">Terbuka (Open)</option>
              <option value="closed">Ditutup (Closed)</option>
            </select>
          </div>

          <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
            <select
              value={categoryFilter}
              onChange={(e) => setCategoryFilter(e.target.value)}
              style={{
                padding: "0.5rem 0.75rem",
                border: "1px solid #cbd5e1",
                borderRadius: "8px",
                fontSize: "0.875rem",
                background: "#ffffff",
              }}
            >
              <option value="all">Semua Kategori</option>
              {Object.entries(categoryLabels).map(([val, label]) => (
                <option key={val} value={val}>
                  {label}
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Master-Detail Layout */}
        <div style={{ display: "grid", gridTemplateColumns: selectedTicket ? "1fr 1.3fr" : "1fr", gap: "1.5rem", alignItems: "start" }}>
          {/* Left: Tickets List */}
          <div>
            {loading ? (
              <div style={{ textAlign: "center", padding: "4rem 2rem", color: "var(--muted, #64748b)" }}>
                <Loader2 size={32} className="animate-spin" style={{ margin: "0 auto 1rem" }} />
                <p>Memuat daftar tiket…</p>
              </div>
            ) : tickets.length === 0 ? (
              <div
                style={{
                  padding: "3.5rem 2rem",
                  textAlign: "center",
                  background: "#ffffff",
                  borderRadius: "12px",
                  border: "1px dashed #cbd5e1",
                }}
              >
                <Headphones size={36} color="#94a3b8" style={{ margin: "0 auto 0.75rem" }} />
                <h3 style={{ fontSize: "1.05rem", fontWeight: 600, margin: "0 0 0.25rem" }}>
                  Tidak ada tiket bantuan
                </h3>
                <p style={{ color: "#64748b", fontSize: "0.875rem" }}>
                  Semua aduan telah diselesaikan atau tidak ada tiket yang sesuai dengan filter.
                </p>
              </div>
            ) : (
              <div style={{ display: "flex", flexDirection: "column", gap: "0.75rem" }}>
                {tickets.map((t) => {
                  const isSelected = selectedTicket?.id === t.id;
                  return (
                    <div
                      key={t.id}
                      onClick={() => handleSelectTicket(t)}
                      style={{
                        background: isSelected ? "#eff6ff" : "#ffffff",
                        border: isSelected ? "2px solid #2563eb" : "1px solid var(--border, #e2e8f0)",
                        borderRadius: "12px",
                        padding: "1rem 1.25rem",
                        cursor: "pointer",
                        boxShadow: "0 1px 3px rgba(0,0,0,0.03)",
                        transition: "all 0.15s ease",
                      }}
                    >
                      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "0.5rem" }}>
                        <div>
                          <span style={{ fontSize: "0.75rem", fontWeight: 700, color: "#64748b" }}>
                            #TIKET-{t.id}
                          </span>
                          <strong style={{ display: "block", fontSize: "1rem", color: "#0f172a" }}>
                            {t.customer?.name || "Pengunjung"}
                          </strong>
                          <span style={{ fontSize: "0.8rem", color: "#64748b" }}>
                            {t.customer?.email}
                          </span>
                        </div>

                        <span
                          style={{
                            fontSize: "0.75rem",
                            fontWeight: 600,
                            padding: "0.2rem 0.6rem",
                            borderRadius: "999px",
                            background: t.status === "open" ? "#fef3c7" : "#dcfce7",
                            color: t.status === "open" ? "#92400e" : "#166534",
                          }}
                        >
                          {t.status === "open" ? "Menunggu Respon" : "Selesai"}
                        </span>
                      </div>

                      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", fontSize: "0.8rem", color: "#64748b" }}>
                        <span>
                          Kategori: <strong>{categoryLabels[t.category] || t.category}</strong>
                        </span>
                        {t.order_id && <span>Order: {t.order_id}</span>}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* Right: Ticket Conversation Thread */}
          {selectedTicket && (
            <div
              style={{
                background: "#ffffff",
                borderRadius: "14px",
                border: "1px solid var(--border, #e2e8f0)",
                padding: "1.5rem",
                boxShadow: "0 4px 6px -1px rgba(0,0,0,0.05)",
                display: "flex",
                flexDirection: "column",
                minHeight: "560px",
              }}
            >
              {/* Thread Header */}
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", paddingBottom: "1rem", borderBottom: "1px solid #f1f5f9", marginBottom: "1rem" }}>
                <div>
                  <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
                    <h3 style={{ fontSize: "1.15rem", fontWeight: 700, margin: 0, color: "#0f172a" }}>
                      Tiket #{selectedTicket.id}
                    </h3>
                    <span
                      style={{
                        fontSize: "0.75rem",
                        fontWeight: 600,
                        padding: "0.15rem 0.5rem",
                        borderRadius: "999px",
                        background: selectedTicket.status === "open" ? "#fef3c7" : "#dcfce7",
                        color: selectedTicket.status === "open" ? "#92400e" : "#166534",
                      }}
                    >
                      {selectedTicket.status === "open" ? "Terbuka" : "Ditutup"}
                    </span>
                  </div>
                  <p style={{ margin: "0.25rem 0 0", fontSize: "0.85rem", color: "#64748b" }}>
                    Pelanggan: <strong>{selectedTicket.customer?.name}</strong> ({selectedTicket.customer?.email}) · Kategori:{" "}
                    <strong>{categoryLabels[selectedTicket.category] || selectedTicket.category}</strong>
                  </p>
                </div>

                <div style={{ display: "flex", gap: "0.5rem" }}>
                  <button
                    type="button"
                    onClick={handleToggleStatus}
                    disabled={statusBusy}
                    className="ui-button ui-button-outline"
                    style={{ fontSize: "0.8rem", padding: "0.4rem 0.8rem" }}
                  >
                    {statusBusy ? "Memproses…" : selectedTicket.status === "open" ? "Tutup Tiket" : "Buka Kembali"}
                  </button>
                  <button
                    type="button"
                    onClick={handleCloseTicket}
                    style={{ background: "transparent", border: "none", color: "#64748b", cursor: "pointer", fontSize: "1.2rem", padding: "0 0.4rem" }}
                  >
                    &times;
                  </button>
                </div>
              </div>

              {/* Message Thread */}
              <div style={{ flex: 1, overflowY: "auto", display: "flex", flexDirection: "column", gap: "1rem", marginBottom: "1rem", maxHeight: "380px" }}>
                {detailLoading ? (
                  <div style={{ textAlign: "center", padding: "2rem", color: "#64748b" }}>
                    <Loader2 size={24} className="animate-spin" style={{ margin: "0 auto 0.5rem" }} />
                    <p style={{ fontSize: "0.85rem" }}>Memuat percakapan…</p>
                  </div>
                ) : ticketDetail?.messages?.length ? (
                  ticketDetail.messages.map((msg) => {
                    const isSupport = msg.author === "support";
                    return (
                      <div
                        key={msg.id}
                        style={{
                          alignSelf: isSupport ? "flex-end" : "flex-start",
                          maxWidth: "80%",
                          background: isSupport ? "#2563eb" : "#f1f5f9",
                          color: isSupport ? "#ffffff" : "#0f172a",
                          borderRadius: "12px",
                          padding: "0.875rem 1rem",
                        }}
                      >
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: "1rem", marginBottom: "0.3rem", fontSize: "0.75rem", opacity: 0.85 }}>
                          <strong>{isSupport ? "Customer Support" : msg.sender_name || "Pelanggan"}</strong>
                          <span>{new Date(msg.created_at).toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" })}</span>
                        </div>
                        <p style={{ margin: 0, fontSize: "0.9rem", lineHeight: 1.45, whiteSpace: "pre-wrap" }}>
                          {msg.body}
                        </p>
                      </div>
                    );
                  })
                ) : (
                  <p style={{ textAlign: "center", color: "#94a3b8", fontSize: "0.85rem" }}>
                    Belum ada pesan dalam tiket ini.
                  </p>
                )}
              </div>

              {/* Reply Box */}
              {selectedTicket.status === "open" ? (
                <form onSubmit={handleSendReply} style={{ marginTop: "auto" }}>
                  <div style={{ display: "flex", gap: "0.5rem" }}>
                    <textarea
                      rows={2}
                      value={replyMessage}
                      onChange={(e) => setReplyMessage(e.target.value)}
                      placeholder="Tulis balasan untuk pelanggan..."
                      style={{
                        flex: 1,
                        padding: "0.65rem 0.85rem",
                        borderRadius: "8px",
                        border: "1px solid #cbd5e1",
                        fontSize: "0.875rem",
                        fontFamily: "inherit",
                        resize: "none",
                      }}
                    />
                    <button
                      type="submit"
                      disabled={replyBusy || !replyMessage.trim()}
                      className="ui-button"
                      style={{ alignSelf: "flex-end", padding: "0.65rem 1rem", gap: "0.35rem" }}
                    >
                      {replyBusy ? (
                        <Loader2 size={16} className="animate-spin" />
                      ) : (
                        <>
                          <Send size={15} /> Kirim
                        </>
                      )}
                    </button>
                  </div>
                </form>
              ) : (
                <div style={{ padding: "0.75rem", background: "#f8fafc", borderRadius: "8px", textAlign: "center", fontSize: "0.85rem", color: "#64748b" }}>
                  Tiket ini telah ditutup. Buka kembali tiket untuk mengirim balasan.
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </Shell>
  );
}
