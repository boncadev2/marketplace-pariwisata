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
const dateTime = (value) =>
  new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: "Asia/Jakarta",
  }).format(new Date(value));
const localDate = (value) =>
  new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Jakarta",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date(value));
export function CulinaryBookingForm({ place, sandboxEnabled, today }) {
  const [booking, setBooking] = useState(null);
  const [date, setDate] = useState(
    place.next_available_time ? localDate(place.next_available_time) : today
  );
  const [slots, setSlots] = useState([]),
    [page, setPage] = useState(1),
    [lastPage, setLastPage] = useState(1),
    [refresh, setRefresh] = useState(0);
  const [form, setForm] = useState({ meal_slot_id: "", quantity: 1 });
  const [quote, setQuote] = useState(null),
    [retry, setRetry] = useState(null),
    [loggedIn, setLoggedIn] = useState(false),
    [busy, setBusy] = useState(false),
    [loading, setLoading] = useState(true),
    [message, setMessage] = useState("");
  useEffect(() => {
    const c = new AbortController();
    apiRequest("/me", { signal: c.signal })
      .then(() => {
        if (!c.signal.aborted) setLoggedIn(true);
      })
      .catch(() => {});
    return () => c.abort();
  }, []);
  useEffect(() => {
    const c = new AbortController();
    apiRequest(
      `/culinary/places/${place.id}/slots?date=${encodeURIComponent(date)}&page=${page}`,
      { signal: c.signal }
    )
      .then((r) => {
        if (c.signal.aborted) return;
        setSlots(r.data.data);
        setLastPage(r.data.last_page);
      })
      .catch((e) => {
        if (!c.signal.aborted) {
          setSlots([]);
          setMessage(e.message);
        }
      })
      .finally(() => {
        if (!c.signal.aborted) setLoading(false);
      });
    return () => c.abort();
  }, [place.id, date, page, refresh]);
  function update(name, value) {
    setForm((v) => ({ ...v, [name]: value }));
    setQuote(null);
    setMessage("");
  }
  async function check(event) {
    event.preventDefault();
    if (busy || retry) return;
    setBusy(true);
    setQuote(null);
    setMessage("");
    try {
      const meal = { ...form, culinary_place_id: place.id };
      const r = await apiRequest(
        `/culinary/quote?${new URLSearchParams(meal)}`
      );
      setQuote({ ...r.data, meal });
    } catch (e) {
      setMessage(e.message);
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
        ...quote.meal,
        expected_total_price: quote.total_price,
      }),
    };
    setRetry(attempt);
    try {
      const result = await apiRequest("/account/meal-bookings", {
        method: "POST",
        headers: { "Idempotency-Key": attempt.key },
        body: attempt.body,
      });
      setBooking(result.data);
      setRetry(null);
      setQuote(null);
      setLoading(true);
      setRefresh((v) => v + 1);
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
          (retryable && quote
            ? " Gunakan Coba lagi untuk memeriksa hasil permintaan yang sama."
            : "")
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <section id="ketersediaan" className="lodging-booking-panel">
      <span className="heading-kicker">Rencanakan kunjungan</span>
      <h2>Periksa ketersediaan</h2>
      <p>Pilih tanggal, jam, dan paket makan di {place.name}.</p>
      <form onSubmit={check} className="culinary-schedule-form">
        <label>
          Tanggal kunjungan
          <input
            type="date"
            required
            min={today}
            value={date}
            disabled={busy || Boolean(retry)}
            onChange={(e) => {
              setDate(e.target.value);
              setPage(1);
              setSlots([]);
              setLoading(true);
              update("meal_slot_id", "");
            }}
          />
        </label>
        <label>
          Jam dan paket makan
          <select
            required
            value={form.meal_slot_id}
            disabled={busy || loading || Boolean(retry)}
            onChange={(e) => update("meal_slot_id", e.target.value)}
          >
            <option value="">
              {loading ? "Memuat jadwal…" : "Pilih jam dan paket"}
            </option>
            {slots.map((s) => (
              <option key={s.id} value={s.id} disabled={s.available === 0}>
                {dateTime(s.time_slot)} WIB · {s.package_name} ·{" "}
                {money(s.price)} · {s.available} tersedia
              </option>
            ))}
          </select>
        </label>
        {!loading && slots.length === 0 && (
          <p>Belum ada jadwal makan pada tanggal ini. Pilih tanggal lain.</p>
        )}
        {lastPage > 1 && (
          <div className="catalog-pagination">
            <button
              type="button"
              disabled={busy || Boolean(retry) || page === 1}
              onClick={() => {
                setPage(page - 1);
                setLoading(true);
                update("meal_slot_id", "");
              }}
            >
              Jam sebelumnya
            </button>
            <span>
              {page}/{lastPage}
            </span>
            <button
              type="button"
              disabled={busy || Boolean(retry) || page === lastPage}
              onClick={() => {
                setPage(page + 1);
                setLoading(true);
                update("meal_slot_id", "");
              }}
            >
              Jam berikutnya
            </button>
          </div>
        )}
        <label>
          Jumlah peserta
          <input
            type="number"
            min="1"
            max="100"
            required
            value={form.quantity}
            disabled={busy || Boolean(retry)}
            onChange={(e) => update("quantity", e.target.value)}
          />
        </label>
        <button
          className="ui-button"
          disabled={busy || loading || !form.meal_slot_id || Boolean(retry)}
        >
          {busy ? "Memeriksa…" : "Periksa ketersediaan"}
        </button>
      </form>
      <p role="status" aria-live="polite">
        {message}
      </p>
      {quote && (
        <div className="mt-5 rounded-xl bg-emerald-50 p-5">
          <h3 className="font-bold">{quote.package_name}</h3>
          <p>{dateTime(quote.time_slot)} WIB</p>
          <p>
            {quote.quantity} peserta × {money(quote.unit_price)}
          </p>
          <p className="my-3 text-xl font-bold">
            Total {money(quote.total_price)}
          </p>
          <p className="mb-3 text-sm">
            Harga dan kuota diperiksa kembali saat reservasi disimpan.
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

      {booking && (
        <ReservationPayment
          kind="culinary"
          bookingId={booking.id}
          payment={booking.reservation_payment}
          payable={booking.status === "reserved_sandbox"}
        />
      )}
      <p className="lodging-rate-note">
        Pembayaran melalui Midtrans. Jadwal ditampilkan dalam WIB.
      </p>
      <Link href="/akun/reservasi#reservasi-kuliner" className="text-link">
        Lihat status reservasi saya
      </Link>
    </section>
  );
}
