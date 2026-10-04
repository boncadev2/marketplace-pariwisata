"use client";

import { useEffect, useState } from "react";
import { apiRequest } from "../lib/api";

export const refundLabels = {
  requested: "Menunggu keputusan admin",
  approved: "Disetujui",
  processing: "Menunggu konfirmasi Midtrans / bank",
  succeeded: "Refund dikonfirmasi",
  failed: "Perlu pemeriksaan admin",
  rejected: "Ditolak",
};
export function RefundRequestControl({ reference, kind = "reservation" }) {
  const endpoint =
    kind === "order"
      ? `/account/orders/${encodeURIComponent(reference)}/refund`
      : `/account/reservation-payments/${encodeURIComponent(reference)}/refund`;
  const [refund, setRefund] = useState(null),
    [form, setForm] = useState(false),
    [reason, setReason] = useState(""),
    [busy, setBusy] = useState(false),
    [message, setMessage] = useState("");
  useEffect(() => {
    const controller = new AbortController();
    apiRequest(endpoint, { signal: controller.signal })
      .then((result) => setRefund(result.data))
      .catch((error) => {
        if (!controller.signal.aborted) setMessage(error.message);
      });
    return () => controller.abort();
  }, [endpoint]);
  async function submit(event) {
    event.preventDefault();
    setBusy(true);
    setMessage("");
    try {
      const result = await apiRequest(endpoint, {
        method: "POST",
        body: JSON.stringify({ reason }),
      });
      setRefund(result.data);
      setForm(false);
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="refund-request-control">
      {refund ? (
        <>
          <p>
            <strong>
              Refund: {refundLabels[refund.status] || refund.status}
            </strong>
          </p>
          <p>
            {refund.decision_notes ||
              refund.failure_reason ||
              "Pengajuan telah diterima. Status diperbarui setelah pemeriksaan admin dan provider."}
          </p>
        </>
      ) : form ? (
        <form onSubmit={submit}>
          <label>
            Alasan pengajuan refund
            <textarea
              required
              minLength={5}
              maxLength={255}
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            />
          </label>
          <p>
            Pengajuan ditinjau admin dan mengikuti kebijakan pesanan.
            Pengembalian dana belum terjadi saat pengajuan.
          </p>
          <button className="ui-button ui-button-outline" disabled={busy}>
            {busy ? "Mengajukan…" : "Kirim pengajuan"}
          </button>
          <button
            type="button"
            className="text-link"
            disabled={busy}
            onClick={() => setForm(false)}
          >
            Batal
          </button>
        </form>
      ) : (
        <button
          type="button"
          className="ui-button ui-button-outline"
          onClick={() => setForm(true)}
        >
          Ajukan refund
        </button>
      )}
      {message && <p role="status">{message}</p>}
    </section>
  );
}
