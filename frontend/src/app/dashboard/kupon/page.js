"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Tag,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  AlertCircle,
  Loader2,
  ArrowLeft,
  Search,
  Filter,
  Percent,
  Calendar,
  ToggleLeft,
  ToggleRight,
  Sparkles,
} from "lucide-react";
import { Shell } from "../../../components/Shell";
import { PageHeader } from "../../../components/PageHeader";
import { apiRequest } from "../../../lib/api";

const money = (val) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(val || 0);

export default function AdminCouponsPage() {
  const [coupons, setCoupons] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("all");
  const [searchTerm, setSearchTerm] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [revision, setRevision] = useState(0);

  // Modal create/edit state
  const [modalMode, setModalMode] = useState(null); // null | 'create' | 'edit'
  const [editingCoupon, setEditingCoupon] = useState(null);
  const [deleteConfirmCoupon, setDeleteConfirmCoupon] = useState(null);

  const [form, setForm] = useState({
    code: "",
    name: "",
    description: "",
    discount_type: "percentage",
    discount_value: "",
    minimum_spend: "0",
    maximum_discount: "",
    global_quota: "",
    user_quota: "1",
    starts_at: "",
    expires_at: "",
    is_active: true,
  });

  useEffect(() => {
    let active = true;
    const params = new URLSearchParams();
    if (statusFilter !== "all") params.set("status", statusFilter);
    if (searchTerm.trim()) params.set("search", searchTerm.trim());

    apiRequest(`/dashboard/coupons?${params.toString()}`)
      .then((res) => {
        if (!active) return;
        setCoupons(res.data || []);
        setLoading(false);
      })
      .catch((err) => {
        if (!active) return;
        setError(err.message || "Gagal memuat daftar kupon promo.");
        setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [statusFilter, searchTerm, revision]);

  function openCreateModal() {
    setEditingCoupon(null);
    setForm({
      code: "",
      name: "",
      description: "",
      discount_type: "percentage",
      discount_value: "10",
      minimum_spend: "50000",
      maximum_discount: "25000",
      global_quota: "100",
      user_quota: "1",
      starts_at: "",
      expires_at: "",
      is_active: true,
    });
    setModalMode("create");
    setError("");
  }

  function openEditModal(coupon) {
    setEditingCoupon(coupon);
    setForm({
      code: coupon.code,
      name: coupon.name,
      description: coupon.description || "",
      discount_type: coupon.discount_type,
      discount_value: String(coupon.discount_value),
      minimum_spend: String(coupon.minimum_spend || 0),
      maximum_discount: coupon.maximum_discount ? String(coupon.maximum_discount) : "",
      global_quota: coupon.global_quota ? String(coupon.global_quota) : "",
      user_quota: coupon.user_quota ? String(coupon.user_quota) : "1",
      starts_at: coupon.starts_at ? coupon.starts_at.slice(0, 10) : "",
      expires_at: coupon.expires_at ? coupon.expires_at.slice(0, 10) : "",
      is_active: Boolean(coupon.is_active),
    });
    setModalMode("edit");
    setError("");
  }

  async function handleFormSubmit(e) {
    e.preventDefault();
    if (busy) return;

    setBusy(true);
    setMessage("");
    setError("");

    const payload = {
      code: form.code.trim().toUpperCase(),
      name: form.name.trim(),
      description: form.description?.trim() || null,
      discount_type: form.discount_type,
      discount_value: Number(form.discount_value),
      minimum_spend: form.minimum_spend ? Number(form.minimum_spend) : 0,
      maximum_discount: form.maximum_discount ? Number(form.maximum_discount) : null,
      global_quota: form.global_quota ? Number(form.global_quota) : null,
      user_quota: form.user_quota ? Number(form.user_quota) : null,
      starts_at: form.starts_at ? `${form.starts_at} 00:00:00` : null,
      expires_at: form.expires_at ? `${form.expires_at} 23:59:59` : null,
      is_active: form.is_active,
    };

    try {
      if (modalMode === "create") {
        await apiRequest("/dashboard/coupons", {
          method: "POST",
          body: JSON.stringify(payload),
        });
        setMessage(`Kupon promo "${payload.code}" berhasil dibuat.`);
      } else {
        await apiRequest(`/dashboard/coupons/${editingCoupon.id}`, {
          method: "PUT",
          body: JSON.stringify(payload),
        });
        setMessage(`Kupon promo "${payload.code}" berhasil diperbarui.`);
      }

      setModalMode(null);
      setRevision((r) => r + 1);
    } catch (err) {
      setError(err.message || "Gagal menyimpan kupon promo.");
    } finally {
      setBusy(false);
    }
  }

  async function handleToggle(coupon) {
    setMessage("");
    try {
      await apiRequest(`/dashboard/coupons/${coupon.id}/toggle`, { method: "PATCH" });
      setRevision((r) => r + 1);
      setMessage(`Status kupon "${coupon.code}" berhasil diubah.`);
    } catch (err) {
      setError(err.message || "Gagal mengubah status kupon.");
    }
  }

  async function handleDelete(coupon) {
    setMessage("");
    try {
      await apiRequest(`/dashboard/coupons/${coupon.id}`, { method: "DELETE" });
      setDeleteConfirmCoupon(null);
      setRevision((r) => r + 1);
      setMessage(`Kupon "${coupon.code}" berhasil dihapus.`);
    } catch (err) {
      setError(err.message || "Gagal menghapus kupon.");
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
              eyebrow="Pemasaran & Promosi"
              title="Kupon & Kode Promo"
              description="Kelola diskon belanja dan kuota voucher untuk meningkatkan transaksi pemesanan wisata."
              compact
            />
          </div>

          <button
            type="button"
            onClick={openCreateModal}
            className="ui-button"
            style={{ padding: "0.6rem 1.25rem", fontSize: "0.875rem", gap: "0.4rem" }}
          >
            <Plus size={16} /> Buat Kupon Baru
          </button>
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
              display: "flex",
              alignItems: "center",
              gap: "0.5rem",
            }}
          >
            <AlertCircle size={18} />
            <span>{error}</span>
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
              placeholder="Cari kode kupon atau nama promo..."
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
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
          </div>
        </div>

        {/* List of Coupons */}
        {loading ? (
          <div style={{ textAlign: "center", padding: "4rem 2rem", color: "var(--muted, #64748b)" }}>
            <Loader2 size={32} className="animate-spin" style={{ margin: "0 auto 1rem" }} />
            <p>Memuat kupon promo…</p>
          </div>
        ) : coupons.length === 0 ? (
          <div
            style={{
              padding: "3.5rem 2rem",
              textAlign: "center",
              background: "#ffffff",
              borderRadius: "12px",
              border: "1px dashed #cbd5e1",
            }}
          >
            <Tag size={36} color="#94a3b8" style={{ margin: "0 auto 0.75rem" }} />
            <h3 style={{ fontSize: "1.05rem", fontWeight: 600, margin: "0 0 0.25rem" }}>
              Belum ada kupon promo
            </h3>
            <p style={{ color: "#64748b", fontSize: "0.875rem", marginBottom: "1.25rem" }}>
              Buat kode kupon diskon pertama untuk menarik wisatawan bertransaksi.
            </p>
            <button type="button" onClick={openCreateModal} className="ui-button">
              <Plus size={16} /> Buat Kupon Sekarang
            </button>
          </div>
        ) : (
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(350px, 1fr))", gap: "1.25rem" }}>
            {coupons.map((c) => (
              <div
                key={c.id}
                style={{
                  background: "#ffffff",
                  borderRadius: "12px",
                  border: "1px solid var(--border, #e2e8f0)",
                  padding: "1.25rem",
                  boxShadow: "0 1px 3px rgba(0,0,0,0.04)",
                  display: "flex",
                  flexDirection: "column",
                  justifyContent: "space-between",
                }}
              >
                <div>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "0.75rem" }}>
                    <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
                      <span
                        style={{
                          background: "#eff6ff",
                          color: "#1d4ed8",
                          border: "1px dashed #3b82f6",
                          padding: "0.25rem 0.65rem",
                          borderRadius: "6px",
                          fontWeight: 700,
                          fontSize: "1rem",
                          letterSpacing: "0.05em",
                        }}
                      >
                        {c.code}
                      </span>
                    </div>

                    <button
                      type="button"
                      onClick={() => handleToggle(c)}
                      style={{
                        background: "transparent",
                        border: "none",
                        cursor: "pointer",
                        display: "flex",
                        alignItems: "center",
                        gap: "0.25rem",
                        color: c.is_active ? "#16a34a" : "#94a3b8",
                      }}
                      title={c.is_active ? "Nonaktifkan kupon" : "Aktifkan kupon"}
                    >
                      {c.is_active ? <ToggleRight size={28} /> : <ToggleLeft size={28} />}
                      <span style={{ fontSize: "0.75rem", fontWeight: 600 }}>
                        {c.is_active ? "Aktif" : "Nonaktif"}
                      </span>
                    </button>
                  </div>

                  <h4 style={{ fontSize: "1.05rem", fontWeight: 700, margin: "0 0 0.25rem", color: "#0f172a" }}>
                    {c.name}
                  </h4>
                  {c.description && (
                    <p style={{ margin: "0 0 0.75rem", fontSize: "0.85rem", color: "#64748b" }}>
                      {c.description}
                    </p>
                  )}

                  <div style={{ background: "#f8fafc", padding: "0.75rem", borderRadius: "8px", marginBottom: "0.75rem" }}>
                    <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.85rem", marginBottom: "0.25rem" }}>
                      <span style={{ color: "#64748b" }}>Besar Diskon:</span>
                      <strong style={{ color: "#16a34a" }}>
                        {c.discount_type === "percentage"
                          ? `${Number(c.discount_value)}%${c.maximum_discount ? ` (Maks. ${money(c.maximum_discount)})` : ""}`
                          : money(c.discount_value)}
                      </strong>
                    </div>

                    <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.85rem", marginBottom: "0.25rem" }}>
                      <span style={{ color: "#64748b" }}>Min. Belanja:</span>
                      <strong>{c.minimum_spend > 0 ? money(c.minimum_spend) : "Tanpa minimum"}</strong>
                    </div>

                    <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.85rem" }}>
                      <span style={{ color: "#64748b" }}>Kuota Terpakai:</span>
                      <span>
                        <strong>{c.used_quota}</strong>
                        {c.global_quota ? ` / ${c.global_quota}` : " (tanpa batas)"}
                      </span>
                    </div>
                  </div>
                </div>

                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "center",
                    paddingTop: "0.75rem",
                    borderTop: "1px solid #f1f5f9",
                  }}
                >
                  <span style={{ fontSize: "0.75rem", color: "#94a3b8" }}>
                    {c.expires_at
                      ? `Berlaku s/d ${new Date(c.expires_at).toLocaleDateString("id-ID")}`
                      : "Berlaku seterusnya"}
                  </span>

                  <div style={{ display: "flex", gap: "0.5rem" }}>
                    <button
                      type="button"
                      onClick={() => openEditModal(c)}
                      className="ui-button ui-button-outline"
                      style={{ padding: "0.35rem 0.7rem", fontSize: "0.8rem", gap: "0.3rem" }}
                    >
                      <Edit2 size={13} /> Edit
                    </button>
                    <button
                      type="button"
                      onClick={() => setDeleteConfirmCoupon(c)}
                      style={{
                        background: "transparent",
                        border: "none",
                        color: "#dc2626",
                        cursor: "pointer",
                        padding: "0.35rem 0.5rem",
                        borderRadius: "6px",
                      }}
                      title="Hapus kupon"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Modal Form Tambah / Edit Kupon */}
        {modalMode && (
          <div
            role="dialog"
            aria-modal="true"
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
            <div style={{ background: "#ffffff", padding: "1.75rem", borderRadius: "14px", maxWidth: "520px", width: "100%", maxHeight: "90vh", overflowY: "auto" }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1rem" }}>
                <h3 style={{ fontSize: "1.2rem", fontWeight: 700, margin: 0, color: "#0f172a" }}>
                  {modalMode === "create" ? "Buat Kupon Promo Baru" : "Edit Kupon Promo"}
                </h3>
                <button
                  type="button"
                  onClick={() => setModalMode(null)}
                  style={{ background: "transparent", border: "none", color: "#64748b", cursor: "pointer", fontSize: "1.3rem" }}
                >
                  &times;
                </button>
              </div>

              <form onSubmit={handleFormSubmit}>
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem", marginBottom: "1rem" }}>
                  <div>
                    <label htmlFor="coupon-code" style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                      Kode Kupon
                    </label>
                    <input
                      id="coupon-code"
                      type="text"
                      required
                      placeholder="CONTOH: PROMO2026"
                      value={form.code}
                      onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })}
                      style={{ width: "100%", padding: "0.55rem", borderRadius: "8px", border: "1px solid #cbd5e1", fontSize: "0.875rem", textTransform: "uppercase", fontWeight: 700 }}
                    />
                  </div>

                  <div>
                    <label htmlFor="coupon-type" style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                      Tipe Diskon
                    </label>
                    <select
                      id="coupon-type"
                      value={form.discount_type}
                      onChange={(e) => setForm({ ...form, discount_type: e.target.value })}
                      style={{ width: "100%", padding: "0.55rem", borderRadius: "8px", border: "1px solid #cbd5e1", fontSize: "0.875rem" }}
                    >
                      <option value="percentage">Persentase (%)</option>
                      <option value="fixed">Nominal Tetap (Rp)</option>
                    </select>
                  </div>
                </div>

                <div style={{ marginBottom: "1rem" }}>
                  <label htmlFor="coupon-name" style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                    Nama Promo
                  </label>
                  <input
                    id="coupon-name"
                    type="text"
                    required
                    placeholder="Contoh: Diskon Wisata Liburan Sekolah"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    style={{ width: "100%", padding: "0.55rem", borderRadius: "8px", border: "1px solid #cbd5e1", fontSize: "0.875rem" }}
                  />
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem", marginBottom: "1rem" }}>
                  <div>
                    <label htmlFor="discount-val" style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                      Nilai Diskon ({form.discount_type === "percentage" ? "%" : "Rp"})
                    </label>
                    <input
                      id="discount-val"
                      type="number"
                      min="1"
                      required
                      placeholder={form.discount_type === "percentage" ? "10" : "50000"}
                      value={form.discount_value}
                      onChange={(e) => setForm({ ...form, discount_value: e.target.value })}
                      style={{ width: "100%", padding: "0.55rem", borderRadius: "8px", border: "1px solid #cbd5e1", fontSize: "0.875rem" }}
                    />
                  </div>

                  <div>
                    <label htmlFor="max-disc" style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                      Maksimal Diskon (Rp, opsional)
                    </label>
                    <input
                      id="max-disc"
                      type="number"
                      min="0"
                      placeholder="Contoh: 50000"
                      value={form.maximum_discount}
                      onChange={(e) => setForm({ ...form, maximum_discount: e.target.value })}
                      style={{ width: "100%", padding: "0.55rem", borderRadius: "8px", border: "1px solid #cbd5e1", fontSize: "0.875rem" }}
                    />
                  </div>
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem", marginBottom: "1rem" }}>
                  <div>
                    <label htmlFor="min-spend" style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                      Minimal Belanja (Rp)
                    </label>
                    <input
                      id="min-spend"
                      type="number"
                      min="0"
                      placeholder="Contoh: 100000"
                      value={form.minimum_spend}
                      onChange={(e) => setForm({ ...form, minimum_spend: e.target.value })}
                      style={{ width: "100%", padding: "0.55rem", borderRadius: "8px", border: "1px solid #cbd5e1", fontSize: "0.875rem" }}
                    />
                  </div>

                  <div>
                    <label htmlFor="global-quota" style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                      Kuota Pemakaian (opsional)
                    </label>
                    <input
                      id="global-quota"
                      type="number"
                      min="1"
                      placeholder="Contoh: 100"
                      value={form.global_quota}
                      onChange={(e) => setForm({ ...form, global_quota: e.target.value })}
                      style={{ width: "100%", padding: "0.55rem", borderRadius: "8px", border: "1px solid #cbd5e1", fontSize: "0.875rem" }}
                    />
                  </div>
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "1rem", marginBottom: "1.25rem" }}>
                  <div>
                    <label htmlFor="starts-at" style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                      Tanggal Mulai
                    </label>
                    <input
                      id="starts-at"
                      type="date"
                      value={form.starts_at}
                      onChange={(e) => setForm({ ...form, starts_at: e.target.value })}
                      style={{ width: "100%", padding: "0.55rem", borderRadius: "8px", border: "1px solid #cbd5e1", fontSize: "0.875rem" }}
                    />
                  </div>

                  <div>
                    <label htmlFor="expires-at" style={{ display: "block", fontSize: "0.85rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                      Tanggal Berakhir
                    </label>
                    <input
                      id="expires-at"
                      type="date"
                      value={form.expires_at}
                      onChange={(e) => setForm({ ...form, expires_at: e.target.value })}
                      style={{ width: "100%", padding: "0.55rem", borderRadius: "8px", border: "1px solid #cbd5e1", fontSize: "0.875rem" }}
                    />
                  </div>
                </div>

                <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                  <button
                    type="button"
                    onClick={() => setModalMode(null)}
                    disabled={busy}
                    className="ui-button ui-button-outline"
                  >
                    Batal
                  </button>
                  <button type="submit" disabled={busy} className="ui-button">
                    {busy ? <Loader2 size={16} className="animate-spin" /> : "Simpan Kupon"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Modal Konfirmasi Hapus */}
        {deleteConfirmCoupon && (
          <div
            role="dialog"
            aria-modal="true"
            style={{
              position: "fixed",
              inset: 0,
              backgroundColor: "rgba(15, 23, 42, 0.65)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              padding: "1rem",
              zIndex: 9999,
            }}
          >
            <div style={{ background: "#ffffff", padding: "1.75rem", borderRadius: "14px", maxWidth: "420px", width: "100%" }}>
              <h3 style={{ fontSize: "1.15rem", fontWeight: 700, margin: "0 0 0.5rem", color: "#dc2626" }}>
                Hapus Kupon Promo?
              </h3>
              <p style={{ fontSize: "0.875rem", color: "#64748b", marginBottom: "1.5rem" }}>
                Apakah Anda yakin ingin menghapus kupon <strong>{deleteConfirmCoupon.code}</strong>? Jika kupon pernah digunakan, kupon akan dinonaktifkan agar riwayat transaksi tetap terjaga.
              </p>
              <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                <button
                  type="button"
                  onClick={() => setDeleteConfirmCoupon(null)}
                  className="ui-button ui-button-outline"
                >
                  Batal
                </button>
                <button
                  type="button"
                  onClick={() => handleDelete(deleteConfirmCoupon)}
                  className="ui-button"
                  style={{ background: "#dc2626", borderColor: "#dc2626", color: "#ffffff" }}
                >
                  Ya, Hapus Kupon
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </Shell>
  );
}
