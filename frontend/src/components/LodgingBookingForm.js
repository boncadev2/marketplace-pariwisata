"use client";
import Link from "next/link";
import { ReservationPayment } from "./ReservationPayment";
import { useEffect, useState } from "react";
import { apiRequest } from "../lib/api";

const money = (amount) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 2,
  }).format(Number(amount));
const inputClass =
  "w-full rounded-xl border border-gray-300 bg-white p-3 min-h-12";
export function LodgingBookingForm({ room, sandboxEnabled }) {
  const [booking, setBooking] = useState(null);
  const [form, setForm] = useState({
    room_type_id: String(room.id),
    check_in: "",
    check_out: "",
    quantity: 1,
    guests: 1,
  });
  const [quote, setQuote] = useState(null);
  const [loggedIn, setLoggedIn] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [retry, setRetry] = useState(null);
  useEffect(() => {
    const controller = new AbortController();
    apiRequest("/me", { signal: controller.signal })
      .then(() => {
        if (!controller.signal.aborted) setLoggedIn(true);
      })
      .catch(() => {});
    return () => controller.abort();
  }, []);
  function change(event) {
    setForm((current) => ({
      ...current,
      [event.target.name]: event.target.value,
    }));
    setQuote(null);
    setMessage("");
  }

  async function checkStay(event) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    setMessage("");
    setQuote(null);
    try {
      const result = await apiRequest(
        `/lodging/quote?${new URLSearchParams(form)}`
      );
      setQuote({ ...result.data, stay: { ...form } });
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }

  async function reserve() {
    if (busy || !quote) return;
    setBusy(true);
    setMessage("");
    const attempt = retry ?? {
      key: crypto.randomUUID(),
      body: JSON.stringify({
        ...quote.stay,
        expected_total_price: quote.total_price,
      }),
    };
    setRetry(attempt);
    try {
      const result = await apiRequest("/account/lodging-bookings", {
        method: "POST",
        headers: { "Idempotency-Key": attempt.key },
        body: attempt.body,
      });
      setBooking(result.data);
      setRetry(null);
      setQuote(null);
      setMessage(
        result.data.status === "cancelled"
          ? "Reservasi ini sudah dibatalkan."
          : `Reservasi #${result.data.id} tersimpan. Lanjutkan pembayaran Midtrans di bawah.`
      );
    } catch (error) {
      const retryable = !error.status || error.status >= 500;
      if (!retryable) setRetry(null);
      if (error.status === 409) setQuote(null);
      if (error.status === 401) setLoggedIn(false);
      setMessage(
        error.message +
          (retryable
            ? " Gunakan tombol Coba lagi untuk memeriksa hasil permintaan yang sama."
            : "")
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <section id="ketersediaan" className="booking-panel lodging-detail-booking">
      <span className="heading-kicker">Rencanakan menginap</span>
      <h2>Periksa ketersediaan</h2>
      <p className="lodging-booking-room">
        {room.name} · Hingga {room.capacity} tamu per kamar
      </p>
      <p className="lodging-rate-note">
        Periksa rincian reservasi sebelum melanjutkan pembayaran Midtrans.
      </p>
      {message && (
        <p role="status" aria-live="polite" className="lodging-booking-message">
          {message}
        </p>
      )}
      {booking && (
        <ReservationPayment
          kind="lodging"
          bookingId={booking.id}
          payment={booking.reservation_payment}
          payable={booking.status === "reserved_sandbox"}
        />
      )}
      <form onSubmit={checkStay}>
        <fieldset
          disabled={busy || !!retry}
          className="grid gap-4 sm:grid-cols-2 disabled:opacity-60"
        >
          <label>
            Check-in
            <input
              name="check_in"
              type="date"
              value={form.check_in}
              onChange={change}
              required
              className={inputClass}
            />
          </label>
          <label>
            Check-out
            <input
              name="check_out"
              type="date"
              value={form.check_out}
              onChange={change}
              required
              className={inputClass}
            />
          </label>
          <label>
            Jumlah kamar
            <input
              name="quantity"
              type="number"
              min="1"
              max="10"
              value={form.quantity}
              onChange={change}
              required
              className={inputClass}
            />
          </label>
          <label>
            Jumlah tamu
            <input
              name="guests"
              type="number"
              min="1"
              max="100"
              value={form.guests}
              onChange={change}
              required
              className={inputClass}
            />
          </label>
          <p className="sm:col-span-2 text-sm text-gray-600">
            Maksimal 30 malam. Tanggal check-out tidak dihitung sebagai malam
            menginap.
          </p>
          <button
            className="min-h-12 rounded-xl bg-blue-700 px-5 py-3 font-semibold text-white sm:col-span-2"
            type="submit"
          >
            Periksa ketersediaan
          </button>
        </fieldset>
      </form>
      {quote && (
        <div className="mt-6 rounded-xl bg-emerald-50 p-5">
          <h3 className="font-bold">
            {quote.nights} malam · {quote.quantity} kamar · {quote.guests} tamu
          </h3>
          <ul className="my-3 text-sm">
            {quote.nightly_prices.map((night) => (
              <li key={night.date}>
                {night.date}: {money(night.price_per_room)} per kamar
              </li>
            ))}
          </ul>
          <p className="text-xl font-bold">Total {money(quote.total_price)}</p>
          <p className="my-3 text-sm">
            Harga dan stok diperiksa kembali saat reservasi disimpan.
          </p>
          {loggedIn && sandboxEnabled ? (
            <button
              disabled={busy}
              onClick={reserve}
              className="min-h-12 rounded-xl bg-blue-700 px-5 py-3 font-semibold text-white disabled:opacity-50"
            >
              {busy
                ? "Memproses…"
                : retry
                  ? "Coba lagi reservasi yang sama"
                  : "Buat reservasi"}
            </button>
          ) : (
            <p>
              {!loggedIn ? (
                <Link href="/login" className="font-semibold underline">
                  Masuk untuk membuat reservasi
                </Link>
              ) : (
                "Reservasi belum dibuka pada lingkungan ini."
              )}
            </p>
          )}
        </div>
      )}

      <Link
        href="/akun/reservasi#reservasi-saya"
        className="lodging-booking-orders"
      >
        Lihat reservasi penginapan saya →
      </Link>
    </section>
  );
}
