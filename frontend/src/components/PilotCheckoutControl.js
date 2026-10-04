"use client";

import { useEffect, useState } from "react";
import { LockKeyhole, Loader2, ShieldAlert } from "lucide-react";
import { apiRequest } from "../lib/api";

export function PilotCheckoutControl() {
  const [status, setStatus] = useState(null);
  const [reason, setReason] = useState("");
  const [password, setPassword] = useState("");
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    apiRequest("/pilot/checkout-status")
      .then((payload) => setStatus(payload.data))
      .catch(() => setMessage("Status checkout belum dapat dimuat."));
  }, []);

  async function toggleCheckout() {
    if (reason.trim().length < 5 || !password) {
      setMessage("Isi alasan operasional dan kata sandi administrator.");
      return;
    }

    setBusy(true);
    setMessage("Memverifikasi dan memperbarui kontrol checkout…");
    try {
      const confirmation = await apiRequest("/security/confirm-password", {
        method: "POST",
        body: JSON.stringify({ password }),
      });
      const payload = await apiRequest("/pilot/checkout-status", {
        method: "PATCH",
        headers: {
          "X-Sensitive-Confirmation": confirmation.confirmation_token,
        },
        body: JSON.stringify({
          enabled: !status.enabled,
          reason: reason.trim(),
        }),
      });
      setStatus(payload.data);
      setReason("");
      setPassword("");
      setMessage(
        payload.data.enabled
          ? "Checkout pilot berhasil dibuka."
          : "Checkout pilot berhasil ditutup."
      );
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <section
      className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
      aria-labelledby="pilot-checkout-title"
    >
      <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
        <div className="max-w-2xl">
          <p className="flex items-center gap-2 text-xs font-extrabold uppercase tracking-[0.16em] text-slate-500">
            <ShieldAlert size={17} /> Kontrol insiden pilot
          </p>
          <h2
            id="pilot-checkout-title"
            className="mt-2 text-xl font-extrabold text-slate-900"
          >
            Buka atau tutup checkout
          </h2>
          <p className="mt-2 text-sm text-slate-600">
            Penutupan berlaku di backend dan menghentikan pembuatan order, hold
            inventori, serta payment attempt baru.
          </p>
          {status && (
            <p
              className={`mt-3 inline-flex rounded-full px-3 py-1 text-xs font-extrabold ${status.enabled ? "bg-emerald-100 text-emerald-800" : "bg-red-100 text-red-800"}`}
            >
              {status.enabled ? "Checkout dibuka" : "Checkout ditutup"}
            </p>
          )}
          {status?.reason && (
            <p className="mt-3 text-sm text-slate-600">
              Alasan terakhir: {status.reason}
            </p>
          )}
        </div>
        <div className="grid w-full gap-3 lg:max-w-md">
          <label className="text-sm font-bold text-slate-700">
            Alasan perubahan
            <textarea
              value={reason}
              onChange={(event) => setReason(event.target.value)}
              maxLength={500}
              rows={2}
              className="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2 font-normal"
            />
          </label>
          <label className="text-sm font-bold text-slate-700">
            Kata sandi administrator
            <input
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete="current-password"
              className="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 px-3 font-normal"
            />
          </label>
          <button
            type="button"
            onClick={toggleCheckout}
            disabled={busy || !status}
            className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 font-extrabold text-white disabled:opacity-50 ${status?.enabled ? "bg-red-700 hover:bg-red-800" : "bg-blue-700 hover:bg-blue-800"}`}
          >
            {busy ? (
              <Loader2 size={18} className="animate-spin" />
            ) : (
              <LockKeyhole size={18} />
            )}
            {status?.enabled
              ? "Tutup checkout sekarang"
              : "Buka checkout pilot"}
          </button>
        </div>
      </div>
      {message && (
        <p role="status" className="mt-4 text-sm font-semibold text-slate-700">
          {message}
        </p>
      )}
    </section>
  );
}
