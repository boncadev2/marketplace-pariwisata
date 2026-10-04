"use client";

import { useState } from "react";
import { apiRequest } from "../lib/api";

export function ShipmentTracking({ orderId }) {
  const [result, setResult] = useState(null),
    [message, setMessage] = useState(""),
    [busy, setBusy] = useState(false);
  async function refresh() {
    setBusy(true);
    setMessage("");
    try {
      setResult(
        await apiRequest(
          `/account/umkm-orders/${encodeURIComponent(orderId)}/tracking`
        )
      );
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="shipment-tracking">
      <button
        type="button"
        className="ui-button ui-button-outline"
        disabled={busy}
        onClick={refresh}
      >
        {busy ? "Memeriksa ekspedisi…" : "Lacak pengiriman"}
      </button>
      {message && <p role="status">{message}</p>}
      {result && (
        <>
          <p>
            Status ekspedisi: <strong>{result.data.status}</strong>
          </p>
          <ol>
            {result.data.history.map((event, index) => (
              <li key={index}>
                <strong>{event.status}</strong>
                <p>{event.note}</p>
                <time>{event.updated_at}</time>
              </li>
            ))}
          </ol>
          <p>
            Diperiksa: {new Date(result.checked_at).toLocaleString("id-ID")}
          </p>
        </>
      )}
    </section>
  );
}
