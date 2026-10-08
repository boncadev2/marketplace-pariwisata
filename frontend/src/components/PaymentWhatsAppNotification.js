"use client";

import { useState } from "react";
import { Smartphone, Send, CheckCircle2, Loader2, AlertCircle } from "lucide-react";
import { apiRequest } from "../lib/api";

export function PaymentWhatsAppNotification({ reference }) {
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [result, setResult] = useState(null);

  async function handleSend(e) {
    e.preventDefault();
    if (!phone.trim() || busy) return;
    setBusy(true);
    setError("");
    setResult(null);

    try {
      const res = await apiRequest(`/guest/orders/${reference}/whatsapp`, {
        method: "POST",
        body: JSON.stringify({
          phone: phone.trim(),
          email: email.trim() || undefined,
        }),
      });
      setResult(res.data);
    } catch (err) {
      setError(
        err.message ||
          "Gagal mengirim tiket ke WhatsApp. Pastikan pesanan telah berstatus dibayar."
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 text-slate-800 shadow-sm">
      <div className="flex items-center gap-2.5 mb-2">
        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-600 text-white shadow-sm">
          <Smartphone className="h-4 w-4" />
        </div>
        <div>
          <h3 className="text-sm font-bold text-emerald-950">
            Dapatkan E-Tiket Langsung di WhatsApp
          </h3>
          <p className="text-xs text-emerald-800/80">
            Kirim tautan voucher dan QR Code masuk langsung ke ponsel Anda.
          </p>
        </div>
      </div>

      {error && (
        <div className="my-3 flex items-center gap-2 rounded-xl bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800">
          <AlertCircle className="h-4 w-4 text-rose-600 flex-shrink-0" />
          {error}
        </div>
      )}

      {result ? (
        <div className="mt-3 rounded-xl bg-white p-4 border border-emerald-200 text-center">
          <CheckCircle2 className="mx-auto h-8 w-8 text-emerald-600 mb-2" />
          <p className="text-xs font-bold text-emerald-950">
            Tiket Terkirim ke WhatsApp!
          </p>
          <p className="text-xs text-slate-600 mt-1 mb-3">
            Notifikasi voucher telah dikirim ke nomor <strong>{result.phone}</strong>.
          </p>
          <a
            href={result.direct_url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 shadow-sm transition"
          >
            <Send className="h-3.5 w-3.5" />
            Buka Chat WhatsApp Sekarang
          </a>
        </div>
      ) : (
        <form onSubmit={handleSend} className="mt-3 space-y-2.5">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <input
              type="tel"
              required
              placeholder="No. WhatsApp (08xxxxxx)"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="w-full rounded-xl border border-emerald-300 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
            />
            <input
              type="email"
              placeholder="Email pemesan saat checkout"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full rounded-xl border border-emerald-300 bg-white px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
            />
          </div>

          <button
            type="submit"
            disabled={busy}
            className="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800 disabled:opacity-50 transition shadow-sm"
          >
            {busy ? (
              <>
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                Memproses…
              </>
            ) : (
              <>
                <Send className="h-3.5 w-3.5" />
                Kirim E-Tiket ke WhatsApp
              </>
            )}
          </button>
        </form>
      )}
    </div>
  );
}
