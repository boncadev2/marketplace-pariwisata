"use client";
import { RefundRequestControl } from "../../components/RefundRequestControl";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import {
  Star,
  X,
  Loader2,
  ShieldAlert,
  AlertTriangle,
  Trash2,
  CheckCircle2,
  Clock,
  ShieldCheck,
  Users,
  Smartphone,
  Printer,
  Send,
} from "lucide-react";
import { EmptyState } from "../../components/PageHeader";
import { Shell } from "../../components/Shell";
import { syncProfile } from "../../components/SiteNavigation";
import { apiRequest } from "../../lib/api";

const statuses = {
  pending_payment: "Menunggu pembayaran",
  expired: "Waktu pembayaran habis",
  paid: "Dibayar",
  payment_exception: "Perlu bantuan pembayaran",
  cancelled: "Dibatalkan",
  refunded: "Dikembalikan",
};

export default function Page() {
  const [profile, setProfile] = useState(null);
  const [orders, setOrders] = useState([]);
  const [status, setStatus] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [wishlist, setWishlist] = useState([]);
  const [activeTab, setActiveTab] = useState("orders");

  const [reviewingOrder, setReviewingOrder] = useState(null);
  const [reviewRating, setReviewRating] = useState(5);
  const [reviewHoverRating, setReviewHoverRating] = useState(0);
  const [reviewComment, setReviewComment] = useState("");
  const [reviewBusy, setReviewBusy] = useState(false);
  const [reviewError, setReviewError] = useState("");

  const [disputeOrder, setDisputeOrder] = useState(null);
  const [disputeReason, setDisputeReason] = useState("Layanan Tidak Sesuai Deskripsi");
  const [disputeDescription, setDisputeDescription] = useState("");
  const [disputeBusy, setDisputeBusy] = useState(false);
  const [disputeError, setDisputeError] = useState("");
  const [viewingDispute, setViewingDispute] = useState(null);

  const [deletionRequest, setDeletionRequest] = useState(null);
  const [deletionModalOpen, setDeletionModalOpen] = useState(false);
  const [deletionReason, setDeletionReason] = useState("");
  const [deletionBusy, setDeletionBusy] = useState(false);
  const [deletionError, setDeletionError] = useState("");

  const [manifestOrder, setManifestOrder] = useState(null);
  const [manifestParticipants, setManifestParticipants] = useState([]);
  const [manifestBusy, setManifestBusy] = useState(false);
  const [manifestError, setManifestError] = useState("");

  const [whatsAppModalOrder, setWhatsAppModalOrder] = useState(null);
  const [waPhone, setWaPhone] = useState("");
  const [waBusy, setWaBusy] = useState(false);
  const [waError, setWaError] = useState("");
  const [waResult, setWaResult] = useState(null);

  function openWhatsAppModal(order) {
    setWhatsAppModalOrder(order);
    setWaPhone(profile?.phone || "");
    setWaBusy(false);
    setWaError("");
    setWaResult(null);
  }

  async function sendWhatsAppVoucher(event) {
    event.preventDefault();
    if (!waPhone.trim() || waBusy || !whatsAppModalOrder) return;
    setWaBusy(true);
    setWaError("");
    setWaResult(null);
    try {
      const res = await apiRequest(`/account/orders/${whatsAppModalOrder.order_id}/whatsapp`, {
        method: "POST",
        body: JSON.stringify({ phone: waPhone.trim() }),
      });
      setWaResult(res.data);
    } catch (err) {
      setWaError(err.message || "Gagal mengirim tiket ke WhatsApp.");
    } finally {
      setWaBusy(false);
    }
  }

  function openManifestModal(order) {
    setManifestOrder(order);
    if (order.participants && order.participants.length > 0) {
      setManifestParticipants(order.participants.map((p) => ({
        name: p.name || "",
        id_number: p.id_number || "",
        phone: p.phone || "",
        emergency_contact: p.emergency_contact || "",
        notes: p.notes || "",
      })));
    } else {
      const defaultQty = order.items?.[0]?.quantity || 1;
      const initialRows = [];
      for (let i = 0; i < Math.max(1, defaultQty); i++) {
        initialRows.push({
          name: i === 0 ? (order.customer_name || profile?.name || "") : "",
          id_number: "",
          phone: i === 0 ? (profile?.phone || "") : "",
          emergency_contact: "",
          notes: "",
        });
      }
      setManifestParticipants(initialRows);
    }
    setManifestError("");
  }

  function addParticipantRow() {
    setManifestParticipants((prev) => [
      ...prev,
      { name: "", id_number: "", phone: "", emergency_contact: "", notes: "" },
    ]);
  }

  function removeParticipantRow(idx) {
    if (manifestParticipants.length <= 1) return;
    setManifestParticipants((prev) => prev.filter((_, i) => i !== idx));
  }

  function updateParticipantField(idx, field, value) {
    setManifestParticipants((prev) =>
      prev.map((row, i) => (i === idx ? { ...row, [field]: value } : row))
    );
  }

  async function saveManifest(event) {
    event.preventDefault();
    if (manifestBusy || !manifestOrder) return;
    const hasEmptyName = manifestParticipants.some((p) => !p.name.trim());
    if (hasEmptyName) {
      setManifestError("Nama lengkap wajib diisi untuk setiap peserta.");
      return;
    }
    setManifestBusy(true);
    setManifestError("");
    try {
      await apiRequest(`/account/orders/${manifestOrder.order_id}/participants`, {
        method: "POST",
        body: JSON.stringify({
          participants: manifestParticipants.map((p) => ({
            name: p.name.trim(),
            id_number: p.id_number?.trim() || null,
            phone: p.phone?.trim() || null,
            emergency_contact: p.emergency_contact?.trim() || null,
            notes: p.notes?.trim() || null,
          })),
        }),
      });
      setMessage("Manifest peserta rombongan berhasil disimpan.");
      setManifestOrder(null);
      await reload(status);
      if (selectedOrder?.order_id === manifestOrder.order_id) {
        await showOrder(manifestOrder.order_id);
      }
    } catch (err) {
      setManifestError(err.message || "Gagal menyimpan manifest peserta.");
    } finally {
      setManifestBusy(false);
    }
  }

  function openReviewModal(order) {
    setReviewingOrder(order);
    setReviewRating(5);
    setReviewHoverRating(0);
    setReviewComment("");
    setReviewError("");
  }

  async function submitReview(event) {
    event.preventDefault();
    if (reviewBusy || !reviewingOrder) return;
    setReviewBusy(true);
    setReviewError("");
    try {
      await apiRequest("/reviews", {
        method: "POST",
        body: JSON.stringify({
          order_id: reviewingOrder.order_id,
          product_id: reviewingOrder.items?.[0]?.product_id,
          rating: reviewRating,
          comment: reviewComment,
        }),
      });
      setReviewingOrder(null);
      setMessage("Terima kasih! Ulasan Anda berhasil dikirim.");
      await reload(status);
    } catch (err) {
      setReviewError(err.message || "Gagal mengirim ulasan. Silakan coba lagi.");
    } finally {
      setReviewBusy(false);
    }
  }

  async function submitDispute(event) {
    event.preventDefault();
    if (disputeBusy || !disputeOrder) return;
    setDisputeBusy(true);
    setDisputeError("");
    try {
      await apiRequest("/disputes", {
        method: "POST",
        body: JSON.stringify({
          order_id: disputeOrder.id || disputeOrder.order_id,
          reason: disputeReason,
          description: disputeDescription.trim() || null,
        }),
      });
      setDisputeOrder(null);
      setMessage("Laporan komplain sengketa berhasil diajukan. Tim mediator platform akan meninjau kendala Anda.");
      await reload(status);
    } catch (err) {
      setDisputeError(err.message || "Gagal mengajukan sengketa. Silakan coba lagi.");
    } finally {
      setDisputeBusy(false);
    }
  }

  async function submitDeletionRequest(event) {
    event.preventDefault();
    if (deletionBusy) return;
    setDeletionBusy(true);
    setDeletionError("");
    try {
      const res = await apiRequest("/account/data-deletion-request", {
        method: "POST",
        body: JSON.stringify({
          reason: deletionReason.trim() || null,
        }),
      });
      setDeletionRequest(res.data);
      setDeletionModalOpen(false);
      setMessage("Permintaan penghapusan akun Anda telah dicatat sesuai hak subjek data UU PDP.");
    } catch (err) {
      setDeletionError(err.message || "Gagal mengajukan penghapusan akun.");
    } finally {
      setDeletionBusy(false);
    }
  }

  const reload = useCallback(async (filter = "") => {
    const query = filter ? `?status=${encodeURIComponent(filter)}` : "";
    const result = await apiRequest(`/account/orders${query}`);
    setOrders(result.data);
  }, []);

  useEffect(() => {
    async function load() {
      try {
        const account = await apiRequest("/me");
        setProfile(account.data);
        syncProfile(account.data);
        await reload();
        const saved = await apiRequest("/account/wishlist");
        setWishlist(saved.data);
        try {
          const delRes = await apiRequest("/account/data-deletion-request");
          setDeletionRequest(delRes.data);
        } catch {
          // ignore if deletion endpoint not applicable
        }
      } catch (error) {
        if (error.status === 401) {
          syncProfile(null);
        } else {
          setMessage("Pesanan tidak dapat dimuat. Coba lagi.");
        }
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [reload]);

  async function changeFilter(event) {
    const next = event.target.value;
    setStatus(next);
    setSelectedOrder(null);
    setMessage("");
    try {
      await reload(next);
    } catch {
      setMessage("Daftar pesanan tidak dapat dimuat. Coba lagi.");
    }
  }


  async function resendVerification() {
    setMessage("");
    try {
      await apiRequest("/email/verification-notification", {
        method: "POST",
        body: "{}",
      });
      setMessage("Tautan verifikasi dikirim. Periksa kotak masuk email Anda.");
    } catch {
      setMessage("Tautan verifikasi belum dapat dikirim. Coba lagi nanti.");
    }
  }

  async function showOrder(orderId) {
    if (busy) return;
    setBusy(true);
    setMessage("");
    try {
      const result = await apiRequest(
        `/account/orders/${encodeURIComponent(orderId)}`
      );
      setSelectedOrder(result.data);
      setActiveTab("details");
    } catch {
      setMessage("Rincian pesanan tidak dapat dibuka. Coba lagi.");
    } finally {
      setBusy(false);
    }
  }

  async function updateProfile(event) {
    event.preventDefault();
    if (busy) return;
    const form = new FormData(event.currentTarget);
    setBusy(true);
    setMessage("");
    try {
      const result = await apiRequest("/account/profile", {
        method: "PATCH",
        body: JSON.stringify({ name: form.get("name") }),
      });
      setProfile(result.data);
      syncProfile(result.data);
      setMessage("Nama profil berhasil diperbarui.");
    } catch {
      setMessage(
        "Nama profil belum dapat diperbarui. Gunakan minimal 2 karakter."
      );
    } finally {
      setBusy(false);
    }
  }

  async function removeWishlist(itemId) {
    setMessage("");
    try {
      await apiRequest(`/account/wishlist/${itemId}`, { method: "DELETE" });
      setWishlist((items) => items.filter((item) => item.id !== itemId));
      setMessage("Destinasi dihapus dari wishlist.");
    } catch {
      setMessage("Wishlist belum dapat diperbarui. Coba lagi.");
    }
  }

  const tabs = [
    {
      id: "orders",
      label: "Pesanan Saya",
      icon: "M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z",
    },
    {
      id: "wishlist",
      label: "Wishlist",
      icon: "M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z",
    },
    {
      id: "profile",
      label: "Pengaturan Profil",
      icon: "M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z",
    },
  ];

  if (selectedOrder && !tabs.find((t) => t.id === "details")) {
    tabs.splice(1, 0, {
      id: "details",
      label: "Rincian Pesanan",
      icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01",
    });
  }

  return (
    <Shell>
      <div className="account-page pb-12">
        {profile && (
          <>
            <div className="account-heading pb-24 pt-8">
              <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 className="text-3xl font-bold text-white mb-2">
                  Rencana tersimpan. Perjalanan tertata.
                </h1>
                <p className="text-blue-100">
                  Halo, {profile.name}. Selamat datang di dasbor Anda.
                </p>
              </div>
            </div>

            <div className="package-toolbar">
              <Link href="/akun/reservasi">Reservasi penginapan & kuliner →</Link>
              <Link href="/akun/umkm">Pesanan produk UMKM →</Link>
              <Link href="/daftar-mitra">Pendaftaran & status mitra →</Link>
            </div>
          </>
        )}
        {!loading && !profile && (
          <div className="pt-8">
            <EmptyState
              title="Satu akun untuk semua perjalanan"
              description="Masuk untuk melihat pesanan, menyimpan tempat favorit, dan membuka voucher Anda."
              href="/login"
              label="Masuk ke akun"
            />
          </div>
        )}
        {profile && (
          <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
            {!profile.email_verified_at && (
              <div className="bg-amber-50 border-l-4 border-amber-400 p-4 mb-6 rounded-md shadow-sm flex flex-col sm:flex-row justify-between items-center">
                <p className="text-amber-800 text-sm mb-3 sm:mb-0">
                  Email akun belum terverifikasi. Silakan periksa kotak masuk email Anda dan klik tautan verifikasi.
                </p>
                <button
                  type="button"
                  onClick={resendVerification}
                  className="px-4 py-2 bg-amber-100 text-amber-800 hover:bg-amber-200 rounded-md text-sm font-medium transition-colors"
                >
                  Kirim ulang tautan
                </button>
              </div>
            )}

            <div className="flex flex-col md:flex-row gap-6">
              <aside className="md:w-64 flex-shrink-0">
                <nav className="bg-white rounded-xl shadow-sm overflow-hidden flex md:flex-col p-2 gap-1 overflow-x-auto">
                  {tabs.map((tab) => (
                    <button
                      key={tab.id}
                      aria-pressed={activeTab === tab.id}
                      onClick={() => setActiveTab(tab.id)}
                      className={`flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium whitespace-nowrap transition-colors ${activeTab === tab.id ? "bg-emerald-50 text-emerald-700" : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"}`}
                    >
                      <svg
                        className="w-5 h-5 opacity-75"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d={tab.icon}
                        />
                      </svg>
                      {tab.label}
                    </button>
                  ))}
                </nav>

                {message && (
                  <div
                    className="mt-6 bg-slate-800 text-white p-4 rounded-xl text-sm"
                    role="status"
                    aria-live="polite"
                  >
                    {message}
                  </div>
                )}
              </aside>

              <section className="account-content flex-1 min-w-0">
                {activeTab === "orders" && (
                  <div className="bg-white rounded-xl shadow-sm p-6">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                      <h2 className="text-xl font-bold text-gray-900">
                        Pesanan Saya
                      </h2>
                      <div className="flex items-center gap-2">
                        <label
                          htmlFor="order-status"
                          className="text-sm font-medium text-gray-700"
                        >
                          Status:
                        </label>
                        <select
                          id="order-status"
                          value={status}
                          onChange={changeFilter}
                          className="border border-gray-300 rounded-md shadow-sm py-1.5 pl-3 pr-8 text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        >
                          <option value="">Semua status</option>
                          {Object.entries(statuses).map(([value, label]) => (
                            <option key={value} value={value}>
                              {label}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>

                    {orders.length ? (
                      <div className="space-y-4">
                        {orders.map((order) => (
                          <article
                            key={order.order_id}
                            className="border border-gray-200 rounded-xl p-5 hover:shadow-md transition-shadow"
                          >
                            <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                              <div>
                                <h3 className="font-bold text-lg text-gray-900 mb-1">
                                  {order.items[0]?.name || "Pesanan wisata"}
                                </h3>
                                <div className="flex flex-wrap gap-2 mb-2">
                                  <span
                                    className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${order.status === "paid" ? "bg-emerald-100 text-emerald-800" : order.status === "cancelled" ? "bg-red-100 text-red-800" : "bg-amber-100 text-amber-800"}`}
                                  >
                                    {statuses[order.status] || order.status}
                                  </span>
                                  <span className="px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-medium">
                                    Kunjungan:{" "}
                                    {order.visit_date || "Belum ditentukan"}
                                  </span>
                                </div>
                                <p className="text-sm text-gray-500 mb-1">
                                  ID: {order.order_id}
                                </p>
                                {order.disputes && order.disputes.length > 0 && (
                                  <div className="mt-2 flex flex-wrap gap-1.5">
                                    {order.disputes.map((disp) => (
                                      <button
                                        key={disp.id}
                                        type="button"
                                        onClick={() => setViewingDispute(disp)}
                                        className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200 cursor-pointer hover:bg-amber-100 transition-colors"
                                      >
                                        <ShieldAlert size={13} />
                                        <span>
                                          Sengketa #{disp.id}: {disp.status === "open" ? "Menunggu Peninjauan" : disp.status === "under_review" ? "Sedang Ditinjau" : disp.status === "resolved" ? "Terselesaikan" : "Ditutup"}
                                        </span>
                                      </button>
                                    ))}
                                  </div>
                                )}
                                {["paid", "refunded"].includes(order.status) && <RefundRequestControl kind="order" reference={order.order_id} />}
                                {order.status === "payment_exception" && (
                                  <p className="text-sm text-amber-600 bg-amber-50 p-2 rounded mt-2">
                                    Pembayaran perlu diperiksa petugas. Jangan
                                    membayar ulang.
                                  </p>
                                )}
                              </div>
                              <div className="flex flex-col items-start md:items-end gap-2 shrink-0">
                                <span className="font-bold text-lg text-emerald-600">
                                  {new Intl.NumberFormat("id-ID", {
                                    style: "currency",
                                    currency: order.currency,
                                  }).format(order.total)}
                                </span>
                                <div className="flex flex-wrap gap-2 items-center">
                                  <button
                                    type="button"
                                    onClick={() => showOrder(order.order_id)}
                                    className="px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-50 rounded-md hover:bg-emerald-100 transition-colors"
                                  >
                                    Lihat Rincian
                                  </button>
                                  {order.status === "paid" && (
                                    <>
                                      <Link
                                        href={`/voucher?order_id=${encodeURIComponent(order.order_id)}&account=1`}
                                        className="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors"
                                      >
                                        Voucher
                                      </Link>
                                      <Link
                                        href={`/voucher?order_id=${encodeURIComponent(order.order_id)}&account=1&print=1`}
                                        className="px-3 py-1.5 text-sm font-medium text-neutral-700 bg-neutral-100 border border-neutral-300 rounded-md hover:bg-neutral-200 transition-colors inline-flex items-center gap-1"
                                        title="Cetak atau simpan e-tiket PDF"
                                      >
                                        <Printer size={15} /> Cetak
                                      </Link>
                                      <button
                                        type="button"
                                        onClick={() => openWhatsAppModal(order)}
                                        className="px-3 py-1.5 text-sm font-medium text-emerald-800 bg-emerald-50 border border-emerald-300 rounded-md hover:bg-emerald-100 transition-colors inline-flex items-center gap-1"
                                        title="Kirim tiket dan tautan voucher ke nomor WhatsApp"
                                      >
                                        <Smartphone size={15} /> Kirim WA
                                      </button>
                                      {order.has_review ? (
                                        <span className="px-2.5 py-1 text-xs font-semibold text-emerald-800 bg-emerald-100 rounded-md inline-flex items-center gap-1">
                                          ✓ Sudah Diulas
                                        </span>
                                      ) : (
                                        <button
                                          type="button"
                                          onClick={() => openReviewModal(order)}
                                          className="px-3 py-1.5 text-sm font-medium text-amber-800 bg-amber-50 border border-amber-200 rounded-md hover:bg-amber-100 transition-colors inline-flex items-center gap-1"
                                        >
                                          ⭐ Beri Ulasan
                                        </button>
                                      )}
                                    </>
                                  )}
                                </div>
                                <div className="flex flex-wrap items-center gap-3 mt-1">
                                  <Link
                                    href={`/bantuan?order_id=${encodeURIComponent(order.order_id)}`}
                                    className="text-xs text-gray-500 hover:text-emerald-600"
                                  >
                                    Minta bantuan
                                  </Link>
                                  <button
                                    type="button"
                                    onClick={() => openManifestModal(order)}
                                    className="text-xs text-blue-600 hover:text-blue-700 font-medium inline-flex items-center gap-1"
                                  >
                                    <Users size={12} />
                                    {order.participants && order.participants.length > 0
                                      ? `Manifest (${order.participants.length})`
                                      : "Manifest Peserta"}
                                  </button>
                                  {["paid", "cancelled", "payment_exception"].includes(order.status) && (
                                    <button
                                      type="button"
                                      onClick={() => {
                                        setDisputeOrder(order);
                                        setDisputeReason("Layanan Tidak Sesuai Deskripsi");
                                        setDisputeDescription("");
                                        setDisputeError("");
                                      }}
                                      className="text-xs text-rose-600 hover:text-rose-700 font-medium inline-flex items-center gap-0.5"
                                    >
                                      <AlertTriangle size={12} /> Ajukan Sengketa
                                    </button>
                                  )}
                                </div>
                              </div>
                            </div>
                          </article>
                        ))}
                      </div>
                    ) : (
                      <div className="text-center py-12 bg-gray-50 rounded-xl border border-gray-200 border-dashed">
                        <p className="text-gray-500">
                          Belum ada pesanan yang ditautkan dengan filter ini.
                        </p>
                      </div>
                    )}
                  </div>
                )}

                {activeTab === "details" && selectedOrder && (
                  <div className="bg-white rounded-xl shadow-sm p-6">
                    <div className="flex items-center justify-between mb-6">
                      <h2 className="text-xl font-bold text-gray-900">
                        Rincian Pesanan
                      </h2>
                      <button
                        onClick={() => setActiveTab("orders")}
                        className="text-sm text-emerald-600 hover:text-emerald-700 font-medium"
                      >
                        &larr; Kembali ke Daftar
                      </button>
                    </div>

                    <div className="mb-6 flex flex-wrap items-center gap-3">
                      <button
                        className="ui-button ui-button-outline"
                        disabled={busy}
                        onClick={() => showOrder(selectedOrder.order_id)}
                      >
                        Perbarui status pembayaran
                      </button>
                      {selectedOrder.checkout_url && (
                        <a
                          className="ui-button"
                          href={selectedOrder.checkout_url}
                        >
                          Lanjutkan pembayaran Midtrans sandbox
                        </a>
                      )}
                      {selectedOrder.status === "pending_payment" &&
                        !selectedOrder.checkout_url && (
                          <p className="text-sm text-slate-600">
                            Tautan pembayaran belum tersedia atau masa reservasi
                            telah berakhir. Perbarui status; jangan membuat
                            pembayaran ulang untuk pesanan yang sama.
                          </p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div className="space-y-4">
                        <div>
                          <p className="text-sm text-gray-500">ID Pesanan</p>
                          <p className="font-medium text-gray-900">
                            {selectedOrder.order_id}
                          </p>
                        </div>
                        <div>
                          <p className="text-sm text-gray-500">
                            Status Pesanan
                          </p>
                          <p className="font-medium text-gray-900">
                            {statuses[selectedOrder.status] ||
                              selectedOrder.status}
                          </p>
                        </div>
                        <div>
                          <p className="text-sm text-gray-500">
                            Status Upaya Pembayaran Terakhir
                          </p>
                          <p className="font-medium text-gray-900">
                            {selectedOrder.payment_status ||
                              "Belum ada upaya tercatat"}
                          </p>
                        </div>
                        <div>
                          <p className="text-sm text-gray-500">
                            Jadwal Kunjungan
                          </p>
                          <p className="font-medium text-gray-900">
                            {selectedOrder.visit_date || "Belum ditentukan"}
                          </p>
                        </div>
                      </div>

                      <div className="bg-slate-50 p-4 rounded-lg space-y-3">
                        <h3 className="font-semibold text-gray-900 mb-2">
                          Informasi Pengelola
                        </h3>
                        <div>
                          <p className="text-sm text-gray-500">Nama</p>
                          <p className="font-medium text-gray-900">
                            {selectedOrder.manager.name}
                          </p>
                        </div>
                        {selectedOrder.manager.email && (
                          <div>
                            <p className="text-sm text-gray-500">Email</p>
                            <a
                              href={`mailto:${selectedOrder.manager.email}`}
                              className="font-medium text-emerald-600 hover:underline"
                            >
                              {selectedOrder.manager.email}
                            </a>
                          </div>
                        )}
                        {selectedOrder.manager.phone && (
                          <div>
                            <p className="text-sm text-gray-500">Telepon</p>
                            <a
                              href={`tel:${selectedOrder.manager.phone}`}
                              className="font-medium text-emerald-600 hover:underline"
                            >
                              {selectedOrder.manager.phone}
                            </a>
                          </div>
                        )}
                        {!selectedOrder.manager.email &&
                          !selectedOrder.manager.phone && (
                            <p className="text-sm text-amber-600 bg-amber-50 p-2 rounded">
                              Kontak pengelola belum tersedia. Gunakan tiket
                              bantuan.
                            </p>
                          )}
                      </div>
                    </div>

                    {/* Manifest Peserta Rombongan Card */}
                    <div className="mt-6 border border-slate-200 rounded-xl p-5 bg-slate-50/50">
                      <div className="flex items-center justify-between mb-3 flex-wrap gap-2">
                        <div>
                          <h3 className="font-bold text-gray-900 text-base flex items-center gap-2">
                            <Users size={18} className="text-blue-600" />
                            Manifest Peserta Rombongan
                          </h3>
                          <p className="text-xs text-gray-500">
                            Data identitas dan manifest peserta rombongan untuk klaim asuransi & verifikasi tiket masuk.
                          </p>
                        </div>
                        <button
                          type="button"
                          onClick={() => openManifestModal(selectedOrder)}
                          className="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition-colors"
                        >
                          {selectedOrder.participants && selectedOrder.participants.length > 0 ? "Edit Manifest" : "Lengkapi Manifest"}
                        </button>
                      </div>
                      {selectedOrder.participants && selectedOrder.participants.length > 0 ? (
                        <div className="overflow-x-auto">
                          <table className="w-full text-xs text-left bg-white rounded-lg border border-gray-200">
                            <thead className="bg-gray-100 text-gray-700">
                              <tr>
                                <th className="p-2.5 font-semibold">No</th>
                                <th className="p-2.5 font-semibold">Nama Lengkap</th>
                                <th className="p-2.5 font-semibold">No. KTP / Paspor</th>
                                <th className="p-2.5 font-semibold">No. Kontak</th>
                                <th className="p-2.5 font-semibold">Catatan Khusus</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                              {selectedOrder.participants.map((p, i) => (
                                <tr key={i}>
                                  <td className="p-2.5 font-bold text-gray-500">{i + 1}</td>
                                  <td className="p-2.5 font-semibold text-gray-900">{p.name}</td>
                                  <td className="p-2.5 font-mono text-gray-600">{p.id_number || "-"}</td>
                                  <td className="p-2.5 text-gray-600">{p.phone || "-"}</td>
                                  <td className="p-2.5 text-gray-500">{p.notes || "-"}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      ) : (
                        <div className="p-4 bg-white rounded-lg border border-dashed border-gray-300 text-center text-xs text-gray-500">
                          Manifest peserta rombongan belum diisi. Lengkapi nama peserta untuk mempermudah pemeriksaan di pos masuk.
                        </div>
                      )}
                    </div>

                    {!selectedOrder.receipt_available && (
                      <div className="mt-6 bg-blue-50 text-blue-800 p-4 rounded-lg text-sm">
                        Invoice atau bukti transaksi resmi belum tersedia untuk
                        pesanan ini.
                      </div>
                    )}
                  </div>
                )}

                {activeTab === "wishlist" && (
                  <div className="bg-white rounded-xl shadow-sm p-6">
                    <h2 className="text-xl font-bold text-gray-900 mb-6">
                      Wishlist Destinasi
                    </h2>

                    {wishlist.length ? (
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {wishlist.map((item) => (
                          <article
                            key={item.id}
                            className="border border-gray-200 rounded-xl p-4 flex flex-col justify-between"
                          >
                            <div>
                              <h3 className="font-bold text-gray-900 mb-2">
                                {item.available
                                  ? item.destination.name
                                  : "Destinasi tidak tersedia"}
                              </h3>
                            </div>
                            <div className="flex gap-2 mt-4 pt-4 border-t border-gray-100">
                              {item.available && (
                                <Link
                                  href={`/destinasi/${item.destination.slug}`}
                                  className="flex-1 text-center px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                                >
                                  Lihat
                                </Link>
                              )}
                              <button
                                type="button"
                                onClick={() => removeWishlist(item.id)}
                                className="flex-1 px-3 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors"
                              >
                                Hapus
                              </button>
                            </div>
                          </article>
                        ))}
                      </div>
                    ) : (
                      <div className="text-center py-12 bg-gray-50 rounded-xl border border-gray-200 border-dashed">
                        <p className="text-gray-500 mb-4">
                          Belum ada destinasi tersimpan.
                        </p>
                        <Link
                          href="/destinasi"
                          className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
                        >
                          Cari Destinasi
                        </Link>
                      </div>
                    )}
                  </div>
                )}

                {activeTab === "profile" && (
                  <div className="bg-white rounded-xl shadow-sm p-6">
                    <h2 className="text-xl font-bold text-gray-900 mb-6">
                      Pengaturan Profil
                    </h2>

                    <div className="max-w-md space-y-6">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Email Akun
                        </label>
                        <input
                          type="text"
                          value={profile.email}
                          disabled
                          className="w-full border border-gray-300 bg-gray-50 rounded-lg px-4 py-2.5 text-gray-500 cursor-not-allowed"
                        />
                        <p className="mt-1 text-xs text-gray-500">
                          Perubahan email belum tersedia di sini.
                        </p>
                      </div>

                      <form
                        onSubmit={updateProfile}
                        className="space-y-4 pt-4 border-t border-gray-100"
                      >
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            Nama Lengkap
                          </label>
                          <input
                            key={profile.name}
                            name="name"
                            defaultValue={profile.name}
                            minLength={2}
                            maxLength={255}
                            required
                            disabled={busy}
                            className="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-50 disabled:text-gray-500"
                          />
                        </div>
                        <button
                          disabled={busy}
                          className="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-70 flex items-center justify-center"
                        >
                          {busy ? "Menyimpan…" : "Simpan Nama"}
                        </button>
                      </form>

                      <div className="mt-8 flex flex-wrap gap-4 pt-6 border-t border-gray-100">
                        <Link
                          href="/bantuan"
                          className="text-sm font-medium text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                        >
                          Riwayat Tiket Bantuan &rarr;
                        </Link>
                        {(profile?.platform_role === "super_admin" ||
                          profile?.can_manage_services) && (
                          <>
                            <span className="text-gray-300">|</span>
                            <Link
                              href="/petugas"
                              className="text-sm font-medium text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                            >
                              Portal Petugas &rarr;
                            </Link>
                          </>
                        )}
                      </div>

                      {/* Hak Privasi & Hapus Data (UU PDP) */}
                      <div className="mt-8 pt-6 border-t border-gray-100">
                        <h3 className="text-sm font-bold text-gray-900 mb-1 flex items-center gap-1.5">
                          <ShieldCheck size={16} className="text-emerald-600" />
                          Hak Privasi & Hapus Data (UU PDP No. 27/2022)
                        </h3>
                        <p className="text-xs text-gray-500 mb-3 leading-relaxed">
                          Anda berhak meminta penghapusan akun serta data identitas pribadi Anda dari sistem kami kapan saja. Catatan transaksi keuangan tetap diarsipkan secara anonim untuk kepatuhan perpajakan.
                        </p>

                        {deletionRequest ? (
                          <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900">
                            <div className="font-semibold flex items-center gap-1.5 mb-0.5">
                              <Clock size={13} /> Permintaan Penghapusan Akun Aktif
                            </div>
                            <p className="text-amber-800">
                              Status: <strong>{deletionRequest.status === "requested" ? "Menunggu Antrean Pemrosesan" : deletionRequest.status}</strong>
                            </p>
                            {deletionRequest.requested_at && (
                              <p className="text-slate-500 mt-1">
                                Diajukan pada: {new Date(deletionRequest.requested_at).toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" })}
                              </p>
                            )}
                          </div>
                        ) : (
                          <button
                            type="button"
                            onClick={() => {
                              setDeletionModalOpen(true);
                              setDeletionReason("");
                              setDeletionError("");
                            }}
                            className="px-3 py-1.5 border border-red-200 text-red-600 hover:bg-red-50 text-xs font-semibold rounded-lg transition-colors inline-flex items-center gap-1.5"
                          >
                            <Trash2 size={13} /> Ajukan Penghapusan Akun
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                )}
              </section>
            </div>
          </div>
        )}
        {reviewingOrder && (
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="review-modal-title"
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
                maxWidth: "500px",
                width: "100%",
                padding: "1.75rem",
                boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.2)",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1rem" }}>
                <h3 id="review-modal-title" style={{ fontSize: "1.25rem", fontWeight: 700, margin: 0, color: "#0f172a" }}>
                  Beri Ulasan Wisata
                </h3>
                <button
                  type="button"
                  onClick={() => setReviewingOrder(null)}
                  style={{ background: "transparent", border: "none", cursor: "pointer", color: "#64748b", padding: "4px" }}
                  aria-label="Tutup modal"
                >
                  <X size={20} />
                </button>
              </div>

              <p style={{ fontSize: "0.875rem", color: "#64748b", marginBottom: "1.25rem" }}>
                Bagikan pengalaman Anda untuk <strong>{reviewingOrder.items?.[0]?.name || "kegiatan wisata ini"}</strong>.
              </p>

              <form onSubmit={submitReview}>
                <div style={{ marginBottom: "1.25rem" }}>
                  <label style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.5rem" }}>
                    Rating Kepuasan
                  </label>
                  <div style={{ display: "flex", gap: "0.5rem", alignItems: "center" }}>
                    {[1, 2, 3, 4, 5].map((star) => {
                      const active = (reviewHoverRating || reviewRating) >= star;
                      return (
                        <button
                          key={star}
                          type="button"
                          onMouseEnter={() => setReviewHoverRating(star)}
                          onMouseLeave={() => setReviewHoverRating(0)}
                          onClick={() => setReviewRating(star)}
                          style={{
                            background: "transparent",
                            border: "none",
                            cursor: "pointer",
                            padding: "4px",
                            transition: "transform 0.1s",
                          }}
                          aria-label={`Beri rating ${star} bintang`}
                        >
                          <Star
                            size={28}
                            fill={active ? "#f59e0b" : "transparent"}
                            color={active ? "#f59e0b" : "#cbd5e1"}
                          />
                        </button>
                      );
                    })}
                    <span style={{ fontSize: "0.875rem", fontWeight: 600, color: "#b45309", marginLeft: "0.5rem" }}>
                      {reviewRating === 5
                        ? "Luar Biasa (5/5)"
                        : reviewRating === 4
                          ? "Sangat Bagus (4/5)"
                          : reviewRating === 3
                            ? "Cukup (3/5)"
                            : reviewRating === 2
                              ? "Kurang Puas (2/5)"
                              : "Mengecewakan (1/5)"}
                    </span>
                  </div>
                </div>

                <div style={{ marginBottom: "1.25rem" }}>
                  <label htmlFor="review-comment" style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.5rem" }}>
                    Catatan / Komentar (opsional)
                  </label>
                  <textarea
                    id="review-comment"
                    rows={4}
                    value={reviewComment}
                    onChange={(e) => setReviewComment(e.target.value)}
                    maxLength={1000}
                    placeholder="Ceritakan keseruan, pemandu, fasilitas, atau tips untuk pengunjung lain..."
                    style={{
                      width: "100%",
                      borderRadius: "8px",
                      border: "1px solid #cbd5e1",
                      padding: "0.75rem",
                      fontSize: "0.875rem",
                      fontFamily: "inherit",
                    }}
                  />
                  <div style={{ textAlign: "right", fontSize: "0.75rem", color: "#94a3b8", marginTop: "4px" }}>
                    {reviewComment.length}/1000 karakter
                  </div>
                </div>

                {reviewError && (
                  <div style={{ padding: "0.75rem", background: "#fef2f2", color: "#b91c1c", borderRadius: "8px", fontSize: "0.875rem", marginBottom: "1rem" }}>
                    {reviewError}
                  </div>
                )}

                <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                  <button
                    type="button"
                    onClick={() => setReviewingOrder(null)}
                    disabled={reviewBusy}
                    className="ui-button ui-button-outline"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={reviewBusy}
                    className="ui-button"
                  >
                    {reviewBusy ? (
                      <>
                        <Loader2 size={16} className="animate-spin" /> Mengirim…
                      </>
                    ) : (
                      "Kirim Ulasan"
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Form Modal Ajukan Sengketa */}
        {disputeOrder && (
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
                maxWidth: "520px",
                width: "100%",
                padding: "1.75rem",
                boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.2)",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1rem" }}>
                <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
                  <AlertTriangle size={20} className="text-rose-600" />
                  <h3 id="dispute-modal-title" style={{ fontSize: "1.2rem", fontWeight: 700, margin: 0, color: "#0f172a" }}>
                    Ajukan Komplain / Sengketa
                  </h3>
                </div>
                <button
                  type="button"
                  onClick={() => setDisputeOrder(null)}
                  style={{ background: "transparent", border: "none", cursor: "pointer", color: "#64748b", padding: "4px" }}
                  aria-label="Tutup modal"
                >
                  <X size={20} />
                </button>
              </div>

              <p style={{ fontSize: "0.875rem", color: "#64748b", marginBottom: "1rem" }}>
                Pesanan <strong>{disputeOrder.order_id}</strong> ({disputeOrder.items?.[0]?.name || "Wisata"}). Aduan akan dimediasi oleh tim operasional platform.
              </p>

              {disputeError && (
                <div style={{ padding: "0.75rem", background: "#fef2f2", color: "#b91c1c", borderRadius: "8px", fontSize: "0.875rem", marginBottom: "1rem" }}>
                  {disputeError}
                </div>
              )}

              <form onSubmit={submitDispute}>
                <div style={{ marginBottom: "1rem" }}>
                  <label style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                    Kategori Kendala / Alasan Sengketa:
                  </label>
                  <select
                    value={disputeReason}
                    onChange={(e) => setDisputeReason(e.target.value)}
                    style={{
                      width: "100%",
                      padding: "0.6rem 0.75rem",
                      border: "1px solid #cbd5e1",
                      borderRadius: "8px",
                      fontSize: "0.875rem",
                      background: "#ffffff",
                    }}
                  >
                    <option value="Layanan Tidak Sesuai Deskripsi">Layanan Tidak Sesuai Deskripsi</option>
                    <option value="Pemandu / Petugas Tidak Hadir di Lokasi">Pemandu / Petugas Tidak Hadir di Lokasi</option>
                    <option value="Pembatalan Sepihak oleh Pengelola">Pembatalan Sepihak oleh Pengelola</option>
                    <option value="Keterlambatan / Perubahan Jadwal Ekstrem">Keterlambatan / Perubahan Jadwal Ekstrem</option>
                    <option value="Fasilitas / Kebersihan Tidak Layak">Fasilitas / Kebersihan Tidak Layak</option>
                    <option value="Lainnya">Lainnya</option>
                  </select>
                </div>

                <div style={{ marginBottom: "1.25rem" }}>
                  <label style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                    Kronologi & Rincian Masalah:
                  </label>
                  <textarea
                    rows={4}
                    required
                    value={disputeDescription}
                    onChange={(e) => setDisputeDescription(e.target.value)}
                    placeholder="Ceritakan kejadian secara jelas (waktu, lokasi, apa yang tidak terpenuhi)..."
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

                <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                  <button
                    type="button"
                    onClick={() => setDisputeOrder(null)}
                    disabled={disputeBusy}
                    className="ui-button ui-button-outline"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={disputeBusy}
                    className="ui-button"
                    style={{ background: "#e11d48", borderColor: "#e11d48" }}
                  >
                    {disputeBusy ? (
                      <>
                        <Loader2 size={16} className="animate-spin" /> Mengirim…
                      </>
                    ) : (
                      "Kirim Laporan Sengketa"
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Modal Detail & Status Sengketa yang Sudah Diajukan */}
        {viewingDispute && (
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="view-dispute-modal-title"
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
                maxWidth: "500px",
                width: "100%",
                padding: "1.75rem",
                boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.2)",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1rem" }}>
                <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
                  <ShieldAlert size={20} className="text-amber-600" />
                  <h3 id="view-dispute-modal-title" style={{ fontSize: "1.2rem", fontWeight: 700, margin: 0, color: "#0f172a" }}>
                    Status Sengketa #{viewingDispute.id}
                  </h3>
                </div>
                <button
                  type="button"
                  onClick={() => setViewingDispute(null)}
                  style={{ background: "transparent", border: "none", cursor: "pointer", color: "#64748b", padding: "4px" }}
                  aria-label="Tutup modal"
                >
                  <X size={20} />
                </button>
              </div>

              <div style={{ display: "flex", flexDirection: "column", gap: "0.875rem", fontSize: "0.875rem" }}>
                <div>
                  <span style={{ fontSize: "0.75rem", color: "#64748b", display: "block" }}>Status Terkini:</span>
                  <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold mt-0.5 ${viewingDispute.status === "open" ? "bg-amber-100 text-amber-800" : viewingDispute.status === "under_review" ? "bg-blue-100 text-blue-800" : viewingDispute.status === "resolved" ? "bg-emerald-100 text-emerald-800" : "bg-slate-100 text-slate-700"}`}>
                    {viewingDispute.status === "open" ? "Menunggu Peninjauan Petugas" : viewingDispute.status === "under_review" ? "Sedang Diinvestigasi Tim Mediasi" : viewingDispute.status === "resolved" ? "Terselesaikan / Solusi Diberikan" : "Sengketa Ditutup"}
                  </span>
                </div>

                <div>
                  <span style={{ fontSize: "0.75rem", color: "#64748b", display: "block" }}>Alasan Komplain:</span>
                  <strong style={{ color: "#0f172a" }}>{viewingDispute.reason}</strong>
                </div>

                <div>
                  <span style={{ fontSize: "0.75rem", color: "#64748b", display: "block" }}>Kronologi yang Anda Laporkan:</span>
                  <p style={{ color: "#334155", background: "#f8fafc", padding: "0.6rem 0.75rem", borderRadius: "6px", border: "1px solid #e2e8f0", margin: "0.2rem 0 0" }}>
                    {viewingDispute.description || "-"}
                  </p>
                </div>

                {viewingDispute.resolution && (
                  <div style={{ background: "#ecfdf5", border: "1px solid #a7f3d0", padding: "0.75rem", borderRadius: "8px" }}>
                    <span style={{ fontSize: "0.75rem", color: "#065f46", fontWeight: 700, display: "block" }}>
                      Putusan Resolusi Mediator Platform:
                    </span>
                    <p style={{ color: "#047857", margin: "0.25rem 0 0", fontSize: "0.875rem", lineHeight: 1.5 }}>
                      {viewingDispute.resolution}
                    </p>
                  </div>
                )}
              </div>

              <div style={{ display: "flex", justifyContent: "flex-end", marginTop: "1.25rem" }}>
                <button
                  type="button"
                  onClick={() => setViewingDispute(null)}
                  className="ui-button ui-button-outline"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Modal Konfirmasi Hapus Akun (UU PDP) */}
        {deletionModalOpen && (
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="deletion-modal-title"
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
                maxWidth: "500px",
                width: "100%",
                padding: "1.75rem",
                boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.2)",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1rem" }}>
                <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
                  <Trash2 size={20} className="text-red-600" />
                  <h3 id="deletion-modal-title" style={{ fontSize: "1.2rem", fontWeight: 700, margin: 0, color: "#0f172a" }}>
                    Konfirmasi Penghapusan Akun
                  </h3>
                </div>
                <button
                  type="button"
                  onClick={() => setDeletionModalOpen(false)}
                  style={{ background: "transparent", border: "none", cursor: "pointer", color: "#64748b", padding: "4px" }}
                  aria-label="Tutup modal"
                >
                  <X size={20} />
                </button>
              </div>

              <div style={{ background: "#fef2f2", border: "1px solid #fecaca", borderRadius: "8px", padding: "0.75rem 1rem", marginBottom: "1rem", fontSize: "0.8rem", color: "#991b1b", lineHeight: 1.5 }}>
                Sesuai UU Perlindungan Data Pribadi (UU PDP No. 27/2022), data pribadi, profil, dan token login Anda akan dianonimkan atau dihapus. Riwayat transaksi finansial tetap diarsipkan secara anonim untuk pembukuan akuntansi.
              </div>

              {deletionError && (
                <div style={{ padding: "0.75rem", background: "#fef2f2", color: "#b91c1c", borderRadius: "8px", fontSize: "0.875rem", marginBottom: "1rem" }}>
                  {deletionError}
                </div>
              )}

              <form onSubmit={submitDeletionRequest}>
                <div style={{ marginBottom: "1.25rem" }}>
                  <label style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                    Alasan Penutupan Akun (Opsional):
                  </label>
                  <textarea
                    rows={3}
                    value={deletionReason}
                    onChange={(e) => setDeletionReason(e.target.value)}
                    placeholder="Beri tahu kami alasan Anda menutup akun..."
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

                <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                  <button
                    type="button"
                    onClick={() => setDeletionModalOpen(false)}
                    disabled={deletionBusy}
                    className="ui-button ui-button-outline"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={deletionBusy}
                    className="ui-button"
                    style={{ background: "#dc2626", borderColor: "#dc2626" }}
                  >
                    {deletionBusy ? (
                      <>
                        <Loader2 size={16} className="animate-spin" /> Mengirim…
                      </>
                    ) : (
                      "Ya, Ajukan Hapus Akun"
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Modal Kelola Manifest Peserta Rombongan */}
        {manifestOrder && (
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="manifest-modal-title"
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
                maxWidth: "650px",
                width: "100%",
                maxHeight: "90vh",
                overflowY: "auto",
                padding: "1.75rem",
                boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.2)",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1rem" }}>
                <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
                  <Users size={22} className="text-blue-600" />
                  <h3 id="manifest-modal-title" style={{ fontSize: "1.2rem", fontWeight: 700, margin: 0, color: "#0f172a" }}>
                    Manifest Peserta Rombongan
                  </h3>
                </div>
                <button
                  type="button"
                  onClick={() => setManifestOrder(null)}
                  style={{ background: "transparent", border: "none", cursor: "pointer", color: "#64748b", padding: "4px" }}
                  aria-label="Tutup modal"
                >
                  <X size={20} />
                </button>
              </div>

              <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "1rem", flexWrap: "wrap", gap: "0.5rem" }}>
                <p style={{ fontSize: "0.85rem", color: "#64748b", margin: 0 }}>
                  Pesanan <strong>{manifestOrder.order_id}</strong> ({manifestOrder.items?.[0]?.name || "Tiket Wisata"}).
                </p>
                <span className="text-xs font-semibold px-2.5 py-1 bg-blue-50 text-blue-700 rounded-md border border-blue-200">
                  Total Tiket: {manifestOrder.items?.[0]?.quantity || 1} Pax
                </span>
              </div>

              <div style={{ background: "#f8fafc", border: "1px solid #e2e8f0", borderRadius: "8px", padding: "0.75rem 1rem", marginBottom: "1.25rem", fontSize: "0.8rem", color: "#475569", lineHeight: 1.5 }}>
                Daftar nama peserta digunakan untuk administrasi tiket masuk kawasan wisata dan klaim asuransi keselamatan pengunjung. Masukkan nama sesuai kartu identitas.
              </div>

              {manifestError && (
                <div style={{ padding: "0.75rem", background: "#fef2f2", color: "#b91c1c", borderRadius: "8px", fontSize: "0.875rem", marginBottom: "1rem" }}>
                  {manifestError}
                </div>
              )}

              <form onSubmit={saveManifest}>
                <div style={{ display: "flex", flexDirection: "column", gap: "1rem", marginBottom: "1.25rem" }}>
                  {manifestParticipants.map((row, idx) => (
                    <div
                      key={idx}
                      style={{
                        padding: "1rem",
                        backgroundColor: "#f8fafc",
                        border: "1px solid #e2e8f0",
                        borderRadius: "10px",
                        position: "relative",
                      }}
                    >
                      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "0.5rem" }}>
                        <span style={{ fontSize: "0.85rem", fontWeight: 700, color: "#1e293b" }}>
                          Peserta #{idx + 1} {idx === 0 ? "(Pemesan Utama)" : ""}
                        </span>
                        {manifestParticipants.length > 1 && (
                          <button
                            type="button"
                            onClick={() => removeParticipantRow(idx)}
                            style={{
                              background: "transparent",
                              border: "none",
                              color: "#ef4444",
                              fontSize: "0.75rem",
                              fontWeight: 600,
                              cursor: "pointer",
                              padding: "2px 6px",
                            }}
                          >
                            Hapus Baris
                          </button>
                        )}
                      </div>

                      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "0.75rem", marginBottom: "0.5rem" }}>
                        <div>
                          <label style={{ display: "block", fontSize: "0.75rem", fontWeight: 600, color: "#475569", marginBottom: "0.25rem" }}>
                            Nama Lengkap (Wajib) *
                          </label>
                          <input
                            type="text"
                            required
                            value={row.name}
                            onChange={(e) => updateParticipantField(idx, "name", e.target.value)}
                            placeholder="Sesuai KTP / Paspor"
                            style={{
                              width: "100%",
                              padding: "0.45rem 0.65rem",
                              border: "1px solid #cbd5e1",
                              borderRadius: "6px",
                              fontSize: "0.85rem",
                            }}
                          />
                        </div>

                        <div>
                          <label style={{ display: "block", fontSize: "0.75rem", fontWeight: 600, color: "#475569", marginBottom: "0.25rem" }}>
                            NIK / No. Paspor (Asuransi)
                          </label>
                          <input
                            type="text"
                            value={row.id_number}
                            onChange={(e) => updateParticipantField(idx, "id_number", e.target.value)}
                            placeholder="16 digit NIK / Paspor"
                            style={{
                              width: "100%",
                              padding: "0.45rem 0.65rem",
                              border: "1px solid #cbd5e1",
                              borderRadius: "6px",
                              fontSize: "0.85rem",
                            }}
                          />
                        </div>
                      </div>

                      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "0.75rem" }}>
                        <div>
                          <label style={{ display: "block", fontSize: "0.75rem", fontWeight: 600, color: "#475569", marginBottom: "0.25rem" }}>
                            No. Telepon / WhatsApp
                          </label>
                          <input
                            type="text"
                            value={row.phone}
                            onChange={(e) => updateParticipantField(idx, "phone", e.target.value)}
                            placeholder="Contoh: 08123456789"
                            style={{
                              width: "100%",
                              padding: "0.45rem 0.65rem",
                              border: "1px solid #cbd5e1",
                              borderRadius: "6px",
                              fontSize: "0.85rem",
                            }}
                          />
                        </div>

                        <div>
                          <label style={{ display: "block", fontSize: "0.75rem", fontWeight: 600, color: "#475569", marginBottom: "0.25rem" }}>
                            Catatan Medis / Alergi / Darurat
                          </label>
                          <input
                            type="text"
                            value={row.notes}
                            onChange={(e) => updateParticipantField(idx, "notes", e.target.value)}
                            placeholder="Contoh: Asma, vegetarian, kontak darurat..."
                            style={{
                              width: "100%",
                              padding: "0.45rem 0.65rem",
                              border: "1px solid #cbd5e1",
                              borderRadius: "6px",
                              fontSize: "0.85rem",
                            }}
                          />
                        </div>
                      </div>
                    </div>
                  ))}
                </div>

                <div style={{ marginBottom: "1.25rem" }}>
                  <button
                    type="button"
                    onClick={addParticipantRow}
                    style={{
                      width: "100%",
                      padding: "0.55rem",
                      border: "1px dashed #93c5fd",
                      background: "#eff6ff",
                      color: "#2563eb",
                      borderRadius: "8px",
                      fontSize: "0.85rem",
                      fontWeight: 600,
                      cursor: "pointer",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                      gap: "0.4rem",
                    }}
                  >
                    + Tambah Baris Peserta
                  </button>
                </div>

                <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                  <button
                    type="button"
                    onClick={() => setManifestOrder(null)}
                    disabled={manifestBusy}
                    className="ui-button ui-button-outline"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={manifestBusy}
                    className="ui-button ui-button-primary"
                  >
                    {manifestBusy ? (
                      <>
                        <Loader2 size={16} className="animate-spin" /> Menyimpan…
                      </>
                    ) : (
                      "Simpan Manifest Peserta"
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Modal Kirim Tiket & Voucher ke WhatsApp */}
        {whatsAppModalOrder && (
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="whatsapp-modal-title"
            style={{
              position: "fixed",
              inset: 0,
              backgroundColor: "rgba(15, 23, 42, 0.65)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              padding: "1rem",
              zIndex: 9999,
              backdropFilter: "blur(4px)",
            }}
          >
            <div
              style={{
                backgroundColor: "#fff",
                borderRadius: "1.25rem",
                width: "100%",
                maxWidth: "480px",
                padding: "1.5rem",
                boxShadow: "0 25px 50px -12px rgba(0, 0, 0, 0.25)",
              }}
            >
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "1rem" }}>
                <div>
                  <h3 id="whatsapp-modal-title" style={{ fontSize: "1.1rem", fontWeight: 700, margin: 0, color: "#0f172a", display: "flex", alignItems: "center", gap: "0.5rem" }}>
                    <Smartphone size={20} className="text-emerald-600" /> Kirim Tiket ke WhatsApp
                  </h3>
                  <p style={{ fontSize: "0.8rem", color: "#64748b", margin: "0.25rem 0 0" }}>
                    Pesanan #{whatsAppModalOrder.order_id?.slice(0, 8)} • {whatsAppModalOrder.items?.[0]?.name || "Tiket Wisata"}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setWhatsAppModalOrder(null)}
                  style={{ background: "none", border: "none", cursor: "pointer", color: "#94a3b8" }}
                >
                  <X size={20} />
                </button>
              </div>

              {waError && (
                <div style={{ padding: "0.75rem", borderRadius: "0.5rem", backgroundColor: "#fef2f2", color: "#991b1b", fontSize: "0.8rem", marginBottom: "1rem", border: "1px solid #fecaca" }}>
                  {waError}
                </div>
              )}

              {waResult ? (
                <div style={{ textAlign: "center", padding: "1rem 0" }}>
                  <div style={{ width: "48px", height: "48px", borderRadius: "50%", backgroundColor: "#ecfdf5", color: "#059669", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 0.75rem" }}>
                    <CheckCircle2 size={28} />
                  </div>
                  <h4 style={{ fontSize: "1rem", fontWeight: 700, margin: 0, color: "#065f46" }}>
                    Notifikasi Berhasil Dikirim!
                  </h4>
                  <p style={{ fontSize: "0.8rem", color: "#475569", margin: "0.5rem 0 1.25rem" }}>
                    Tautan e-tiket dan kode QR voucher telah dikirimkan ke nomor <strong>{waResult.phone}</strong>.
                  </p>
                  <div style={{ display: "flex", flexDirection: "column", gap: "0.5rem" }}>
                    <a
                      href={waResult.direct_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="ui-button ui-button-primary"
                      style={{ backgroundColor: "#16a34a", borderColor: "#16a34a", textDecoration: "none", display: "inline-flex", justifyContent: "center", alignItems: "center", gap: "0.5rem" }}
                    >
                      <Send size={16} /> Buka WhatsApp Sekarang
                    </a>
                    <button
                      type="button"
                      onClick={() => setWhatsAppModalOrder(null)}
                      className="ui-button ui-button-outline"
                    >
                      Selesai
                    </button>
                  </div>
                </div>
              ) : (
                <form onSubmit={sendWhatsAppVoucher}>
                  <div style={{ marginBottom: "1.25rem" }}>
                    <label style={{ display: "block", fontSize: "0.8rem", fontWeight: 600, color: "#334155", marginBottom: "0.35rem" }}>
                      Nomor WhatsApp Tujuan (Aktif)
                    </label>
                    <input
                      type="tel"
                      required
                      placeholder="Contoh: 081234567890"
                      value={waPhone}
                      onChange={(e) => setWaPhone(e.target.value)}
                      style={{
                        width: "100%",
                        padding: "0.6rem 0.75rem",
                        borderRadius: "0.5rem",
                        border: "1px solid #cbd5e1",
                        fontSize: "0.85rem",
                        outline: "none",
                      }}
                    />
                    <span style={{ display: "block", fontSize: "0.75rem", color: "#64748b", marginTop: "0.35rem" }}>
                      Format nomor didukung: 08xx atau 628xx. Tautan e-tiket dan QR voucher akan dikirim ke nomor ini.
                    </span>
                  </div>

                  <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                    <button
                      type="button"
                      onClick={() => setWhatsAppModalOrder(null)}
                      disabled={waBusy}
                      className="ui-button ui-button-outline"
                    >
                      Batal
                    </button>
                    <button
                      type="submit"
                      disabled={waBusy}
                      className="ui-button ui-button-primary"
                      style={{ backgroundColor: "#16a34a", borderColor: "#16a34a" }}
                    >
                      {waBusy ? (
                        <>
                          <Loader2 size={16} className="animate-spin" /> Mengirim…
                        </>
                      ) : (
                        <>
                          <Send size={15} /> Kirim ke WhatsApp
                        </>
                      )}
                    </button>
                  </div>
                </form>
              )}
            </div>
          </div>
        )}
      </div>
    </Shell>
  );
}
