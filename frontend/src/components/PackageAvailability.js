"use client";
import Link from "next/link";
import { useState } from "react";
import { apiRequest } from "../lib/api";
export function PackageAvailability({ item }) {
  const [date, setDate] = useState("");
  const [quantity, setQuantity] = useState(item.minimum_participants);
  const [result, setResult] = useState(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  async function check(event) {
    event.preventDefault();
    setBusy(true);
    setMessage("");
    setResult(null);
    try {
      const calendar = await apiRequest(
        `/products/${encodeURIComponent(item.slug)}/inventory?${new URLSearchParams({ from: date, to: date })}`
      );
      const day = calendar.data.find((row) => row.session_key === "default");
      if (!day || day.is_closed || day.available < Number(quantity)) {
        setMessage(
          "Kuota untuk tanggal dan jumlah peserta ini belum tersedia. Pilih tanggal lain."
        );
        return;
      }
      const quote = await apiRequest(
        `/products/${encodeURIComponent(item.slug)}/quote?${new URLSearchParams({ visit_date: date, quantity: String(quantity) })}`
      );
      setResult({
        available: day.available,
        total: quote.data.total,
        currency: quote.data.currency,
        date,
        quantity,
      });
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <div>
      <form onSubmit={check}>
        <fieldset disabled={busy} style={{ border: 0, padding: 0 }}>
          <label className="travel-field">
            Tanggal keberangkatan
            <input
              type="date"
              min={new Intl.DateTimeFormat("en-CA", {
                timeZone: "Asia/Jakarta",
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
              }).format(new Date())}
              required
              value={date}
              onChange={(event) => {
                setDate(event.target.value);
                setResult(null);
                setMessage("");
              }}
            />
          </label>
          <label className="travel-field">
            Jumlah peserta
            <input
              type="number"
              required
              min={item.minimum_participants}
              max={Math.min(100, item.maximum_participants)}
              value={quantity}
              onChange={(event) => {
                setQuantity(event.target.value);
                setResult(null);
                setMessage("");
              }}
            />
          </label>
          <button type="submit" className="ui-button">
            {busy ? "Memeriksa…" : "Periksa ketersediaan"}
          </button>
        </fieldset>
      </form>
      {message && <p role="status">{message}</p>}
      {result && (
        <div className="travel-price-note" role="status">
          <p>Tersedia {result.available} kuota peserta</p>
          <strong style={{ fontSize: 22 }}>
            Total{" "}
            {new Intl.NumberFormat("id-ID", {
              style: "currency",
              currency: result.currency,
              maximumFractionDigits: 0,
            }).format(result.total)}
          </strong>
          <p>
            Untuk {result.quantity} peserta. Stok dan harga diperiksa kembali
            saat pemesanan.
          </p>
          <Link
            className="ui-button"
            href={`/checkout?${new URLSearchParams({ product: item.slug, date: result.date, quantity: String(result.quantity) })}`}
          >
            Lanjutkan pemesanan
          </Link>
        </div>
      )}
    </div>
  );
}
