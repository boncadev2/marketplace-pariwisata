"use client";
import { RefundRequestControl } from "../../components/RefundRequestControl";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { EmptyState } from "../../components/PageHeader";
import { Shell } from "../../components/Shell";
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
        await reload();
        const saved = await apiRequest("/account/wishlist");
        setWishlist(saved.data);
      } catch (error) {
        if (error.status !== 401)
          setMessage("Pesanan tidak dapat dimuat. Coba lagi.");
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

  async function claim(event) {
    event.preventDefault();
    if (busy) return;
    const form = event.currentTarget;
    const values = new FormData(form);
    setBusy(true);
    setMessage("");
    try {
      await apiRequest("/account/orders/claim", {
        method: "POST",
        headers: { "X-Guest-Access-Token": values.get("access_token") },
        body: JSON.stringify({ order_id: values.get("order_id") }),
      });
      form.reset();
      await reload(status);
      setMessage("Pesanan berhasil ditautkan ke akun Anda.");
    } catch (error) {
      setMessage(
        error.status === 403
          ? "Verifikasi email akun Anda terlebih dahulu."
          : error.status === 404
            ? "Pesanan atau kode akses tidak cocok dengan akun ini."
            : "Klaim pesanan gagal. Coba lagi."
      );
    } finally {
      setBusy(false);
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
    {
      id: "claim",
      label: "Klaim Pesanan",
      icon: "M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z",
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
        <div className="account-heading pb-24 pt-8">
          <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 className="text-3xl font-bold text-white mb-2">
              Rencana tersimpan. Perjalanan tertata.
            </h1>
            {loading ? (
              <p className="text-blue-100">Memuat akun…</p>
            ) : profile ? (
              <p className="text-blue-100">
                Halo, {profile.name}. Selamat datang di dasbor Anda.
              </p>
            ) : (
              <p className="text-blue-100">
                <Link
                  href="/login"
                  className="underline font-medium hover:text-white"
                >
                  Masuk
                </Link>{" "}
                untuk melihat perjalanan Anda.
              </p>
            )}
          </div>
        </div>

        <div className="package-toolbar">
          <Link href="/akun/reservasi">Reservasi penginapan & kuliner →</Link>
          <Link href="/akun/umkm">Pesanan produk UMKM →</Link>
          <Link href="/daftar-mitra">Pendaftaran & status mitra →</Link>
        </div>
        {!loading && !profile && (
          <EmptyState
            title="Satu akun untuk semua perjalanan"
            description="Masuk untuk melihat pesanan, menyimpan tempat favorit, dan membuka voucher Anda."
            href="/login"
            label="Masuk ke akun"
          />
        )}
        {profile && (
          <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
            {!profile.email_verified_at && (
              <div className="bg-amber-50 border-l-4 border-amber-400 p-4 mb-6 rounded-md shadow-sm flex flex-col sm:flex-row justify-between items-center">
                <p className="text-amber-800 text-sm mb-3 sm:mb-0">
                  Email akun belum terverifikasi. Verifikasi dulu sebelum
                  mengklaim pesanan tamu.
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
                                <div className="flex gap-2">
                                  <button
                                    type="button"
                                    onClick={() => showOrder(order.order_id)}
                                    className="px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-50 rounded-md hover:bg-emerald-100 transition-colors"
                                  >
                                    Lihat Rincian
                                  </button>
                                  {order.status === "paid" && (
                                    <Link
                                      href={`/voucher?order_id=${encodeURIComponent(order.order_id)}&account=1`}
                                      className="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors"
                                    >
                                      Voucher
                                    </Link>
                                  )}
                                </div>
                                <Link
                                  href={`/bantuan?order_id=${encodeURIComponent(order.order_id)}`}
                                  className="text-xs text-gray-500 hover:text-emerald-600 mt-1"
                                >
                                  Minta bantuan
                                </Link>
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
                    </div>
                  </div>
                )}

                {activeTab === "claim" && (
                  <div className="bg-white rounded-xl shadow-sm p-6">
                    <h2 className="text-xl font-bold text-gray-900 mb-2">
                      Klaim Pesanan Tamu
                    </h2>
                    <p className="text-gray-500 text-sm mb-6">
                      Gunakan ID dan kode akses yang Anda terima saat checkout.
                      Email akun harus sudah terverifikasi dan sama dengan email
                      pesanan.
                    </p>

                    <form
                      onSubmit={claim}
                      className="max-w-md space-y-4 bg-gray-50 p-5 rounded-xl border border-gray-100"
                    >
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          ID Pesanan
                        </label>
                        <input
                          name="order_id"
                          required
                          maxLength={128}
                          autoComplete="off"
                          className="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                          placeholder="Contoh: ORD-12345"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Kode Akses
                        </label>
                        <input
                          name="access_token"
                          required
                          minLength={48}
                          maxLength={48}
                          autoComplete="off"
                          type="password"
                          className="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                          placeholder="Masukkan 48 karakter kode"
                        />
                      </div>
                      <button
                        disabled={busy}
                        className="w-full px-4 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-70 mt-2"
                      >
                        {busy ? "Memproses…" : "Tautkan Pesanan"}
                      </button>
                    </form>

                    <div className="mt-8 flex gap-4 pt-6 border-t border-gray-100">
                      <Link
                        href="/petugas"
                        className="text-sm font-medium text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                      >
                        Portal Petugas &rarr;
                      </Link>
                      <span className="text-gray-300">|</span>
                      <Link
                        href="/bantuan"
                        className="text-sm font-medium text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                      >
                        Riwayat Tiket Bantuan &rarr;
                      </Link>
                    </div>
                  </div>
                )}
              </section>
            </div>
          </div>
        )}
      </div>
    </Shell>
  );
}
