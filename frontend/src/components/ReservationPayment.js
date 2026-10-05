"use client";

import { RefundRequestControl } from "./RefundRequestControl";
import { useEffect, useState } from "react";
import { CreditCard, Loader2, RefreshCw } from "lucide-react";
import { apiRequest } from "../lib/api";

export const reservationPaymentLabels = {
  unpaid: "Belum dibayar",
  created: "Menyiapkan pembayaran",
  pending: "Menunggu pembayaran",
  uncertain: "Status pembayaran perlu diperiksa",
  paid: "Dibayar · Midtrans",
  failed: "Pembayaran dibatalkan / kedaluwarsa",
  refunded: "Dana dikembalikan",
  payment_exception: "Pembayaran diterima · perlu bantuan admin",
};

export function ReservationPayment({
  kind,
  bookingId,
  payment = null,
  payable = true,
  onUpdated,
  disabled = false,
}) {
  const [mode, setMode] = useState("sandbox");
  useEffect(() => {
    const controller = new AbortController();
    apiRequest("/payments/gateway-status", { signal: controller.signal })
      .then((result) => setMode(result.data.mode))
      .catch(() => {});
    return () => controller.abort();
  }, []);
  const [current, setCurrent] = useState(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [confirm, setConfirm] = useState(false);
  const state = current || payment;
  const displayMode =
    state?.provider === "midtrans_production"
      ? "production"
      : state?.provider === "midtrans_sandbox"
        ? "sandbox"
        : mode;
  const terminal = ["paid", "failed", "payment_exception", "refunded"].includes(
    state?.status
  );
  async function action(name) {
    if (busy || disabled) return;
    setBusy(true);
    setMessage("");
    try {
      const result = await apiRequest(
        `/account/reservation-payments/${kind}/${encodeURIComponent(bookingId)}/${name}`,
        { method: "POST", body: "{}", signal: AbortSignal.timeout(45000) }
      );
      setCurrent(result.data);
      setConfirm(false);
      if (
        (name === "checkout" || name === "change-method") &&
        result.data.status === "pending" &&
        result.data.checkout_url
      ) {
        const url = new URL(result.data.checkout_url);
        if (
          url.protocol !== "https:" ||
          !["app.sandbox.midtrans.com", "app.midtrans.com"].includes(
            url.hostname
          )
        )
          throw new Error("Alamat pembayaran tidak valid.");
        window.location.assign(url.href);
        return;
      }
      setMessage(
        name === "cancel" && result.data.status === "failed"
          ? "Pembayaran dibatalkan. Stok atau kuota dikembalikan."
          : `Status: ${reservationPaymentLabels[result.data.status] || result.data.status}.`
      );
      if (onUpdated) await onUpdated();
    } catch (error) {
      setMessage(
        error.status
          ? error.message
          : "Hasil permintaan belum pasti. Periksa pesanan atau muat ulang halaman sebelum mencoba lagi; pembayaran yang sama akan digunakan kembali."
      );
    } finally {
      setBusy(false);
    }
  }
  if (!payable && !state) return null;
  return (
    <div className="reservation-payment">
      <p className="reservation-payment-status">
        <CreditCard size={18} />{" "}
        {reservationPaymentLabels[state?.status || "unpaid"]}
      </p>
      <p className="reservation-payment-note">
        {displayMode === "production"
          ? "Pembayaran melalui Midtrans."
          : "Pembayaran uji melalui Midtrans sandbox. Jangan transfer uang nyata."}
      </p>
      {!state && payable && (
        <button
          className="ui-button"
          disabled={busy || disabled}
          onClick={() => action("checkout")}
        >
          {busy ? (
            <>
              <Loader2 size={17} className="animate-spin" /> Menghubungkan…
            </>
          ) : (
            <>
              <CreditCard size={17} /> Bayar dengan Midtrans
              {displayMode === "sandbox" ? " sandbox" : ""}
            </>
          )}
        </button>
      )}
      {state && !terminal && (
        <div className="reservation-payment-actions">
          {state.checkout_url && state.status === "pending" && (
            <a className="ui-button" href={state.checkout_url}>
              Lanjutkan pembayaran
            </a>
          )}
          {state.status === "pending" && (
            <button
              className="ui-button ui-button-outline"
              disabled={busy || disabled}
              onClick={() => action("change-method")}
            >
              {busy ? "Menyiapkan…" : "Ganti cara bayar"}
            </button>
          )}
          <button
            className="ui-button ui-button-outline"
            disabled={busy || disabled}
            onClick={() => action("refresh")}
          >
            <RefreshCw size={16} />{" "}
            {busy ? "Memeriksa…" : "Periksa status pembayaran"}
          </button>
          {!confirm ? (
            <button
              className="text-link"
              disabled={busy || disabled}
              onClick={() => setConfirm(true)}
            >
              Batalkan pembayaran
            </button>
          ) : (
            <div>
              <p>
                Batalkan sesi Midtrans dan lepaskan stok atau kuota pesanan?
              </p>
              <button
                className="ui-button ui-button-outline"
                disabled={busy || disabled}
                onClick={() => action("cancel")}
              >
                Ya, batalkan pembayaran
              </button>
              <button
                className="text-link"
                disabled={busy || disabled}
                onClick={() => setConfirm(false)}
              >
                Kembali
              </button>
            </div>
          )}
        </div>
      )}
      {state?.status === "uncertain" && (
        <p>
          Permintaan belum dapat dipastikan. Sistem memeriksa Midtrans tanpa
          membuat tagihan baru.
        </p>
      )}
      {state?.status === "payment_exception" && (
        <p>
          Hubungi admin dengan referensi {state.reference}. Pesanan tidak
          diaktifkan kembali secara otomatis.
        </p>
      )}
      {state?.status === "paid" && (
        <p>
          Pembayaran terverifikasi oleh server. Pembatalan pesanan yang sudah
          dibayar memerlukan bantuan admin untuk refund.
        </p>
      )}
      {["paid", "refunded"].includes(state?.status) && (
        <RefundRequestControl reference={state.reference} />
      )}
      {message && (
        <p role="status" aria-live="polite">
          {message}
        </p>
      )}
    </div>
  );
}
