"use client";
import { ReservationPayment } from "./ReservationPayment";
import {serviceReservationLabel} from "../lib/service-reservations";
import { useEffect, useState } from "react";
import { apiRequest } from "../lib/api";
const money = (amount) => new Intl.NumberFormat("id-ID", {style:"currency", currency:"IDR", maximumFractionDigits:2}).format(Number(amount));
const dateTime = (value) => new Intl.DateTimeFormat("id-ID", {dateStyle:"medium", timeStyle:"short", timeZone:"Asia/Jakarta"}).format(new Date(value));
export function CulinaryBookings({sandboxEnabled}) {
  const [bookings,setBookings]=useState([]); const [bookingPage,setBookingPage]=useState(1); const [lastBookingPage,setLastBookingPage]=useState(1);
  const [loading,setLoading]=useState(true); const [loggedIn,setLoggedIn]=useState(false); const [busy,setBusy]=useState(false); const [message,setMessage]=useState("");
  useEffect(() => { const controller=new AbortController(); apiRequest("/account/meal-bookings", {signal:controller.signal}).then((result) => { if(controller.signal.aborted)return; setBookings(result.data.data); setLastBookingPage(result.data.last_page); setLoggedIn(true); }).catch((error) => {if(!controller.signal.aborted && error.status!==401)setMessage(error.message);}).finally(()=>{if(!controller.signal.aborted)setLoading(false);}); return () => controller.abort(); }, []);
  async function loadBookings(page = 1) {
    const result = await apiRequest(`/account/meal-bookings?page=${page}`);
    setBookings(result.data.data);
    setBookingPage(result.data.current_page);
    setLastBookingPage(result.data.last_page);
  }


  async function cancel(id) {
    if (busy) return;
    setBusy(true);
    setMessage("");
    try {
      await apiRequest(`/account/meal-bookings/${id}/cancel`, {
        method: "POST",
      });
      setMessage("Reservasi dibatalkan dan kuota dikembalikan.");
      await loadBookings(bookingPage);
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }


  async function changePage(page) { setBusy(true); try { await loadBookings(page); } catch(error){setMessage(error.message);} finally{setBusy(false);} }
  return <section id="reservasi-kuliner" className="lodging-account-section">{(message||loading) && <p role="status" aria-live="polite">{message||"Memuat reservasi kuliner…"}</p>}
      {loggedIn && (
        <section className="mt-8">
          <h2 className="mb-4 text-xl font-bold">Reservasi kuliner saya</h2>
          {bookings.length === 0 ? (
            <p>Belum ada reservasi kuliner.</p>
          ) : (
            <ul className="grid gap-4 sm:grid-cols-2">
              {bookings.map((booking) => (
                <li
                  key={booking.id}
                  className="rounded-xl border border-gray-200 bg-white p-5"
                >
                  <span className="account-reservation-id">Reservasi #{booking.id}</span><h3 className="font-semibold">{booking.package_name}</h3>
                  <p className="mt-2">{dateTime(booking.time_slot)} WIB</p>
                  <p>
                    {booking.quantity} peserta · {money(booking.total_price)}
                  </p>
                  <p className="mt-2">
                    {serviceReservationLabel(booking)}
                  </p>
                  <ReservationPayment kind="culinary" bookingId={booking.id} payment={booking.reservation_payment} payable={booking.status === "reserved_sandbox" && !booking.checked_in_at && !booking.completed_at} disabled={busy} onUpdated={() => loadBookings(bookingPage)} />
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
                disabled={busy || bookingPage === 1}
                onClick={() => changePage(bookingPage - 1)}
                className="min-h-12 underline"
              >
                Sebelumnya
              </button>
              <span>
                {bookingPage} / {lastBookingPage}
              </span>
              <button
                disabled={busy || bookingPage === lastBookingPage}
                onClick={() => changePage(bookingPage + 1)}
                className="min-h-12 underline"
              >
                Berikutnya
              </button>
            </div>
          )}
        </section>
      )}
  </section>;
}
