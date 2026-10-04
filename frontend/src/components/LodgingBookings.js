"use client";
import { ReservationPayment } from "./ReservationPayment";
import {serviceReservationLabel} from "../lib/service-reservations";
import { useEffect, useState } from "react";
import { apiRequest } from "../lib/api";

const money = (amount) => new Intl.NumberFormat("id-ID", {style: "currency", currency: "IDR", maximumFractionDigits: 2}).format(Number(amount));
export function LodgingBookings({sandboxEnabled}) {
  const [bookings, setBookings] = useState([]);
  const [bookingPage, setBookingPage] = useState(1);
  const [lastBookingPage, setLastBookingPage] = useState(1);
  const [loggedIn, setLoggedIn] = useState(false);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  async function loadBookings(page = 1) {
    const result = await apiRequest(`/account/lodging-bookings?page=${page}`);
    setBookings(result.data.data);
    setBookingPage(result.data.current_page);
    setLastBookingPage(result.data.last_page);
    setLoggedIn(true);
  }


  useEffect(() => {
    const controller = new AbortController();
    apiRequest("/account/lodging-bookings", {signal: controller.signal}).then((result) => {
      if (controller.signal.aborted) return;
      setBookings(result.data.data); setLastBookingPage(result.data.last_page); setLoggedIn(true);
    }).catch((error) => { if (!controller.signal.aborted && error.status !== 401) setMessage(error.message); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, []);
  async function cancel(id) {
    if (busy) return;
    setBusy(true);
    setMessage("");
    try {
      await apiRequest(`/account/lodging-bookings/${id}/cancel`, {
        method: "POST",
      });
      setMessage("Reservasi dibatalkan dan stok seluruh malam dikembalikan.");
      await loadBookings(bookingPage);
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }


  async function changePage(page) {
    setBusy(true);
    try { await loadBookings(page); }
    catch (error) { setMessage(error.message); }
    finally { setBusy(false); }
  }
  return <section id="reservasi-saya" className="lodging-account-section">
    {(message || loading) && <p role="status" aria-live="polite">{message || "Memuat reservasi…"}</p>}
      {loggedIn && (
        <section className="mt-8">
          <h2 className="mb-4 text-xl font-bold">Reservasi penginapan saya</h2>
          {bookings.length === 0 ? (
            <p>Belum ada reservasi penginapan.</p>
          ) : (
            <ul className="grid gap-4 sm:grid-cols-2">
              {bookings.map((booking) => (
                <li
                  key={booking.id}
                  className="rounded-xl border border-gray-200 bg-white p-5"
                >
                  <span className="account-reservation-id">Reservasi #{booking.id}</span>
                  <h3 className="font-semibold">
                    {booking.room_type?.name ||
                      `Kamar #${booking.room_type_id}`}
                  </h3>
                  <p className="mt-2">
                    {booking.check_in.slice(0, 10)} –{" "}
                    {booking.check_out.slice(0, 10)}
                  </p>
                  <p>
                    {booking.quantity} kamar · {booking.guests} tamu
                  </p>
                  <p className="my-2 font-bold">{money(booking.total_price)}</p>
                  <p>
                    {serviceReservationLabel(booking)}
                  </p>
                  <ReservationPayment kind="lodging" bookingId={booking.id} payment={booking.reservation_payment} payable={booking.status === "reserved_sandbox" && !booking.checked_in_at && !booking.completed_at} disabled={busy} onUpdated={() => loadBookings(bookingPage)} />
                  {booking.status === "reserved_sandbox" && !booking.reservation_payment && !booking.checked_in_at && !booking.completed_at && sandboxEnabled && (
                    <button
                      disabled={busy}
                      onClick={() => cancel(booking.id)}
                      className="mt-3 min-h-12 rounded-lg border border-gray-300 px-4 font-semibold disabled:opacity-50"
                    >
                      Batalkan reservasi simulasi
                    </button>
                  )}
                </li>
              ))}
            </ul>
          )}
          {lastBookingPage > 1 && (
            <div className="mt-4 flex items-center gap-4">
              <button
                className="min-h-12 underline"
                disabled={busy || bookingPage === 1}
                onClick={() => changePage(bookingPage - 1)}
              >
                Sebelumnya
              </button>
              <span>
                {bookingPage} / {lastBookingPage}
              </span>
              <button
                className="min-h-12 underline"
                disabled={busy || bookingPage === lastBookingPage}
                onClick={() => changePage(bookingPage + 1)}
              >
                Berikutnya
              </button>
            </div>
          )}
        </section>
      )}
  </section>;
}
