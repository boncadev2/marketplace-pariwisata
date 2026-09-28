"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Shell } from "../../components/Shell";
import { apiRequest } from "../../lib/api";

const categories = {
  booking: "Pemesanan",
  payment: "Pembayaran",
  voucher: "Voucher",
  visit: "Kunjungan",
  other: "Lainnya",
};

export default function SupportPage() {
  const [orders, setOrders] = useState([]);
  const [tickets, setTickets] = useState([]);
  const [selected, setSelected] = useState(null);
  const [orderId, setOrderId] = useState("");
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [authenticated, setAuthenticated] = useState(false);

  useEffect(() => {
    let active = true;
    Promise.all([apiRequest("/me"), apiRequest("/account/orders"), apiRequest("/account/support-tickets")])
      .then(([, orderResult, ticketResult]) => {
        if (!active) return;
        setAuthenticated(true);
        setOrders(orderResult.data);
        setTickets(ticketResult.data);
        const requested = new URLSearchParams(window.location.search).get("order_id");
        setOrderId(orderResult.data.some((order) => order.order_id === requested) ? requested : (orderResult.data[0]?.order_id || ""));
      })
      .catch((error) => {
        if (active && error.status !== 401) setMessage("Bantuan belum dapat dimuat. Coba lagi.");
      });
    return () => { active = false; };
  }, []);

  async function refreshTickets() {
    const result = await apiRequest("/account/support-tickets");
    setTickets(result.data);
  }

  async function openTicket(id) {
    setMessage("");
    try {
      const result = await apiRequest(`/account/support-tickets/${id}`);
      setSelected(result.data);
    } catch {
      setMessage("Tiket tidak dapat dibuka. Coba lagi.");
    }
  }

  async function createTicket(event) {
    event.preventDefault();
    if (busy || !orderId) return;
    setBusy(true);
    setMessage("");
    const form = event.currentTarget;
    const data = new FormData(form);
    if (!data.get("attachment")?.size) data.delete("attachment");
    try {
      const result = await apiRequest(`/account/orders/${encodeURIComponent(orderId)}/support-tickets`, { method: "POST", body: data });
      form.reset();
      setSelected(result.data);
      await refreshTickets();
      setMessage("Tiket bantuan berhasil dibuat.");
    } catch (error) {
      setMessage(error.status === 422 ? "Periksa kategori, pesan (minimal 10 karakter), dan lampiran PDF/JPG/PNG maksimal 5 MB." : "Tiket belum dapat dibuat. Coba lagi.");
    } finally {
      setBusy(false);
    }
  }

  async function addMessage(event) {
    event.preventDefault();
    if (busy || !selected) return;
    setBusy(true);
    setMessage("");
    const form = event.currentTarget;
    const data = new FormData(form);
    if (!data.get("attachment")?.size) data.delete("attachment");
    try {
      await apiRequest(`/account/support-tickets/${selected.id}/messages`, { method: "POST", body: data });
      form.reset();
      await openTicket(selected.id);
      setMessage("Pesan tambahan berhasil dikirim.");
    } catch (error) {
      setMessage(error.status === 409 ? "Tiket sudah ditutup." : error.status === 422 ? "Periksa isi pesan dan lampiran Anda." : "Pesan belum dapat dikirim. Coba lagi.");
    } finally {
      setBusy(false);
    }
  }

  return <Shell>
    <section className="page-intro">
      <p className="eyebrow">Akun pelanggan</p>
      <h1>Bantuan pesanan</h1>
      <p>Tiket hanya dapat dibuat untuk pesanan yang sudah ditautkan ke akun Anda. Jangan tulis kata sandi atau nomor kartu dalam pesan.</p>
      {!authenticated && <p><Link href="/login">Masuk</Link> untuk mengakses tiket bantuan.</p>}
    </section>
    {authenticated && <>
      <section className="section">
        <h2>Tiket saya</h2>
        {tickets.length ? <div className="card-grid">{tickets.map((ticket) => <article className="card" key={ticket.id}>
          <h3>#{ticket.id} · {categories[ticket.category] || ticket.category}</h3>
          <p>Pesanan: {ticket.order_id}</p>
          <p>Status: {ticket.status === "open" ? "Terbuka" : "Ditutup"}</p>
          <button type="button" onClick={() => openTicket(ticket.id)}>Lihat percakapan</button>
        </article>)}</div> : <p>Belum ada tiket bantuan.</p>}
      </section>
      {selected && <section className="section">
        <h2>Tiket #{selected.id}</h2>
        <p>{categories[selected.category] || selected.category} · {selected.order_id}</p>
        {selected.messages.map((entry) => <article className="card" key={entry.id}>
          <p>{entry.author === "customer" ? "Anda" : "Petugas bantuan"} · {new Date(entry.created_at).toLocaleString("id-ID")}</p>
          <p style={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>{entry.body}</p>
          {entry.attachments.map((attachment) => <p key={attachment.id}><a href={`/api/v1/account/support-tickets/${selected.id}/attachments/${attachment.id}`}>Unduh lampiran ({attachment.mime_type})</a></p>)}
        </article>)}
        {selected.status === "open" && <form className="search-panel" onSubmit={addMessage}>
          <label>Pesan lanjutan<textarea name="message" minLength={10} maxLength={5000} required disabled={busy} /></label>
          <label>Lampiran opsional (PDF/JPG/PNG, maks. 5 MB)<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" disabled={busy} /></label>
          <button disabled={busy}>{busy ? "Mengirim…" : "Kirim pesan"}</button>
        </form>}
      </section>}
      <section className="section">
        <h2>Buat tiket baru</h2>
        {orders.length ? <form className="search-panel" onSubmit={createTicket}>
          <label>Pesanan<select value={orderId} onChange={(event) => setOrderId(event.target.value)} required>{orders.map((order) => <option key={order.order_id} value={order.order_id}>{order.order_id} · {order.items[0]?.name || "Pesanan wisata"}</option>)}</select></label>
          <label>Kategori<select name="category" required>{Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
          <label>Pesan<textarea name="message" minLength={10} maxLength={5000} required disabled={busy} /></label>
          <label>Lampiran opsional (PDF/JPG/PNG, maks. 5 MB)<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" disabled={busy} /></label>
          <button disabled={busy}>{busy ? "Mengirim…" : "Buat tiket"}</button>
        </form> : <p>Tautkan pesanan tamu terlebih dahulu di <Link href="/akun">akun</Link>.</p>}
      </section>
    </>}
    <p role="status" aria-live="polite">{message}</p>
  </Shell>;
}
