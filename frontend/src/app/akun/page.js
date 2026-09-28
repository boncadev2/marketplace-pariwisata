"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
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
      } catch (error) {
        setMessage(error.status === 401 ? "Masuk untuk melihat pesanan Anda." : "Pesanan tidak dapat dimuat. Coba lagi.");
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [reload]);

  async function changeFilter(event) {
    const next = event.target.value;
    setStatus(next);
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
      setMessage(error.status === 403 ? "Verifikasi email akun Anda terlebih dahulu." : error.status === 404 ? "Pesanan atau kode akses tidak cocok dengan akun ini." : "Klaim pesanan gagal. Coba lagi.");
    } finally {
      setBusy(false);
    }
  }

  async function resendVerification() {
    setMessage("");
    try {
      await apiRequest("/email/verification-notification", { method: "POST", body: "{}" });
      setMessage("Tautan verifikasi dikirim. Periksa kotak masuk email Anda.");
    } catch {
      setMessage("Tautan verifikasi belum dapat dikirim. Coba lagi nanti.");
    }
  }

  return (
    <Shell>
      <section className="page-intro">
        <p className="eyebrow">Akun pelanggan</p>
        <h1>Akun dan perjalanan</h1>
        {loading ? <p>Memuat akun…</p> : profile ? <p>Halo, {profile.name}. Pesanan hanya tampil setelah ditautkan ke akun ini.</p> : <p><Link href="/login">Masuk</Link> untuk melihat perjalanan Anda.</p>}
      </section>
      {profile && <>
        {!profile.email_verified_at && <section className="section"><p>Email akun belum terverifikasi. Verifikasi dulu sebelum mengklaim pesanan tamu.</p><button type="button" onClick={resendVerification}>Kirim ulang tautan verifikasi</button></section>}
        <section className="section">
          <h2>Pesanan saya</h2>
          <label htmlFor="order-status">Status</label>{" "}
          <select id="order-status" value={status} onChange={changeFilter}>
            <option value="">Semua status</option>
            {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
          {orders.length ? <div className="card-grid">{orders.map((order) => <article className="card" key={order.order_id}>
            <h3>{order.items[0]?.name || "Pesanan wisata"}</h3>
            <p>{statuses[order.status] || order.status}</p>
            <p>Kunjungan: {order.visit_date || "Belum ditentukan"}</p>
            <p>{new Intl.NumberFormat("id-ID", { style: "currency", currency: order.currency }).format(order.total)}</p>
            <small>ID: {order.order_id}</small>
            {order.status === "paid" && <p><Link href={`/voucher?order_id=${encodeURIComponent(order.order_id)}&account=1`}>Buka voucher</Link></p>}
            {order.status === "payment_exception" && <p>Pembayaran perlu diperiksa petugas. Jangan membayar ulang.</p>}
          </article>)}</div> : <p>Belum ada pesanan yang ditautkan dengan filter ini.</p>}
        </section>
        <section className="section">
          <h2>Klaim pesanan tamu</h2>
          <p>Gunakan ID dan kode akses yang Anda terima saat checkout. Email akun harus sudah terverifikasi dan sama dengan email pesanan.</p>
          <form className="search-panel" onSubmit={claim}>
            <label>ID pesanan<input name="order_id" required maxLength={128} autoComplete="off" /></label>
            <label>Kode akses<input name="access_token" required minLength={48} maxLength={48} autoComplete="off" type="password" /></label>
            <button disabled={busy}>{busy ? "Memproses…" : "Tautkan pesanan"}</button>
          </form>
          <p><Link href="/petugas">Portal petugas</Link></p>
        </section>
      </>}
      <p role="status" aria-live="polite">{message}</p>
    </Shell>
  );
}
