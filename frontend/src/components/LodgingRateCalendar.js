"use client";

import { useEffect, useRef, useState } from "react";
import { CalendarDays, CheckCircle2 } from "lucide-react";
import { apiRequest } from "../lib/api";

const money = (value) => value === null ? "Belum diatur" : new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 2 }).format(Number(value));

export function LodgingRateCalendar({ room, date, busy, setBusy, onSaved, onClose }) {
  const panel = useRef(null);
  useEffect(() => { panel.current?.scrollIntoView({ behavior: "smooth", block: "start" }); }, []);
  const [start, setStart] = useState(date);
  const [end, setEnd] = useState(date);
  const [calendar, setCalendar] = useState(null);
  const [price, setPrice] = useState("");
  const [initializeStock, setInitializeStock] = useState(false);
  const [stock, setStock] = useState("");
  const [message, setMessage] = useState("");
  const [success, setSuccess] = useState(false);
  async function load(event) {
    event.preventDefault(); setBusy(true); setMessage(""); setSuccess(false); setCalendar(null);
    try {
      const result = await apiRequest(`/dashboard/lodging-rooms/${room.id}/calendar?start_date=${start}&end_date=${end}`);
      setCalendar(result.data);
    } catch (error) { setMessage(error.message); }
    finally { setBusy(false); }
  }
  async function apply(event) {
    event.preventDefault(); setBusy(true); setMessage(""); setSuccess(false);
    try {
      const result = await apiRequest(`/dashboard/lodging-rooms/${room.id}/calendar`, { method: "PATCH", body: JSON.stringify({ start_date: calendar.start_date, end_date: calendar.end_date, revision: calendar.revision, price: Number(price), initial_stock: initializeStock ? Number(stock) : null }) });
      setCalendar(result.data); setSuccess(true);
      setMessage(`Tarif diterapkan untuk ${result.data.days.length} tanggal. Stok awal diisi pada ${result.data.initialized_dates} tanggal; stok yang sudah ada tetap dipertahankan.`);
      await onSaved();
    } catch (error) { setMessage(error.message); }
    finally { setBusy(false); }
  }
  return <section id="lodging-rate-calendar" ref={panel} className="lodging-manager-panel lodging-rate-calendar" aria-label="Kalender tarif kamar">
    <div className="lodging-calendar-heading"><div><span className="lodging-type"><CalendarDays size={16} /> Tarif & ketersediaan</span><h2>{room.name}</h2><p>Terapkan satu tarif untuk maksimal 90 tanggal, termasuk tanggal awal dan akhir. Tarif baru tidak mengubah harga pesanan yang sudah dibuat.</p></div><button className="ui-button ui-button-outline" disabled={busy} onClick={onClose}>Tutup kalender</button></div>
    <form className="lodging-calendar-controls" onSubmit={load}>
      <label>Tanggal awal<input required type="date" value={start} disabled={busy} onChange={(event) => { setStart(event.target.value); setCalendar(null); setMessage(""); }} /></label>
      <label>Tanggal akhir<input required type="date" min={start} value={end} disabled={busy} onChange={(event) => { setEnd(event.target.value); setCalendar(null); setMessage(""); }} /></label>
      <button className="ui-button ui-button-outline" disabled={busy} type="submit">{busy ? "Memproses…" : "Tampilkan kalender"}</button>
    </form>
    {message && <p role="status" className={success ? "lodging-calendar-success" : "lodging-calendar-message"}>{success && <CheckCircle2 size={18} />}{message}</p>}
    {calendar && <>
      <div className="lodging-calendar-table" tabIndex={0} aria-label="Rincian tarif per tanggal"><table><caption>{calendar.days.length} tanggal · {calendar.start_date} sampai {calendar.end_date}</caption><thead><tr><th>Tanggal</th><th>Tarif / malam</th><th>Kamar tersedia</th><th>Status</th></tr></thead><tbody>{calendar.days.map((day) => <tr key={day.date}><th scope="row">{new Date(`${day.date}T12:00:00`).toLocaleDateString("id-ID", { weekday: "short", day: "numeric", month: "short", year: "numeric" })}</th><td>{money(day.price)}</td><td>{day.stock === null ? "Belum diatur" : day.stock}</td><td><span className={`lodging-calendar-status ${day.stock > 0 && Number(day.price) > 0 ? "is-available" : ""}`}>{day.stock === 0 ? "Habis" : day.stock === null || Number(day.price) <= 0 ? "Belum lengkap" : "Tersedia"}</span></td></tr>)}</tbody></table></div>
      <form onSubmit={apply} className="lodging-calendar-apply"><fieldset disabled={busy}>
        <label>Tarif untuk semua tanggal (Rp)<input required type="number" min={1} max={1000000000} value={price} onChange={(event) => setPrice(event.target.value)} /></label>
        <label className="lodging-calendar-checkbox"><input type="checkbox" checked={initializeStock} onChange={(event) => setInitializeStock(event.target.checked)} /> Isi stok awal untuk tanggal yang belum diatur</label>
        {initializeStock && <label>Stok awal per tanggal<input required type="number" min={0} max={1000000} value={stock} onChange={(event) => setStock(event.target.value)} /></label>}
        <p>Stok yang sudah diatur, termasuk stok habis dan stok setelah pemesanan, tidak ditimpa. Tanpa stok awal, tanggal yang belum memiliki stok tetap belum dapat dipesan.</p>
        <button className="ui-button" type="submit">{busy ? "Menyimpan…" : `Terapkan untuk ${calendar.days.length} tanggal`}</button>
      </fieldset></form>
    </>}
  </section>;
}
