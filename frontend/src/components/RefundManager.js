"use client";

import { useEffect, useState } from "react";
import { Shell } from "./Shell";
import { PageHeader } from "./PageHeader";
import { refundLabels } from "./RefundRequestControl";
import { apiRequest } from "../lib/api";

export function RefundManager() {
  const [kind, setKind] = useState("reservation"),
    [result, setResult] = useState(null),
    [page, setPage] = useState(1),
    [revision, setRevision] = useState(0),
    [message, setMessage] = useState(""),
    [selected, setSelected] = useState(null),
    [action, setAction] = useState("approve"),
    [password, setPassword] = useState(""),
    [notes, setNotes] = useState(""),
    [busy, setBusy] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    apiRequest(`/dashboard/refunds?kind=${kind}&page=${page}`, {
      signal: controller.signal,
    })
      .then(setResult)
      .catch((error) => {
        if (!controller.signal.aborted) setMessage(error.message);
      });
    return () => controller.abort();
  }, [kind, page, revision]);
  async function decide(event) {
    event.preventDefault();
    setBusy(true);
    setMessage("");
    try {
      const confirmation = await apiRequest("/security/confirm-password", {
        method: "POST",
        body: JSON.stringify({ password }),
      });
      setPassword("");
      const endpoint =
        kind === "reservation"
          ? `/dashboard/reservation-refunds/${selected.id}/${action}`
          : `/refunds/${selected.id}/${action}`;
      await apiRequest(endpoint, {
        method: "POST",
        headers: {
          "X-Sensitive-Confirmation": confirmation.confirmation_token,
        },
        body: JSON.stringify({ notes }),
      });
      setSelected(null);
      setRevision((value) => value + 1);
      setMessage(
        "Keputusan disimpan. Periksa status provider untuk hasil refund."
      );
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }
  async function refresh(item) {
    setBusy(true);
    setMessage("");
    try {
      await apiRequest(`/dashboard/refunds/${kind}/${item.id}/refresh`, {
        method: "POST",
        body: "{}",
      });
      setRevision((value) => value + 1);
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Shell>
      <PageHeader
        eyebrow="Operasional pembayaran"
        title="Pengajuan refund"
        description="Tinjau alasan dan nilai pengembalian. Dana dan kuota mengikuti hasil verifikasi resmi provider."
      />
      <label className="travel-field">
        Jenis pesanan
        <select
          value={kind}
          disabled={busy}
          onChange={(event) => {
            setKind(event.target.value);
            setResult(null);
            setPage(1);
            setSelected(null);
            setMessage("");
          }}
        >
          <option value="reservation">UMKM / penginapan / kuliner</option>
          <option value="order">Tiket / paket wisata</option>
        </select>
      </label>
      {message && (
        <p role="status" className="travel-message">
          {message}
        </p>
      )}
      {selected && (
        <form className="travel-editor" onSubmit={decide}>
          <fieldset disabled={busy}>
            <h2>
              {action === "approve" ? "Setujui" : "Tolak"} refund{" "}
              {selected.reference}
            </h2>
            <p>
              Nominal: Rp{selected.refundable_amount.toLocaleString("id-ID")}.
              Persetujuan mengajukan refund ke provider yang dikonfigurasi.
            </p>
            <label className="travel-field">
              Catatan keputusan
              <textarea
                required
                minLength={5}
                maxLength={255}
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
              />
            </label>
            <label className="travel-field">
              Konfirmasi kata sandi admin
              <input
                type="password"
                autoComplete="current-password"
                required
                value={password}
                onChange={(event) => setPassword(event.target.value)}
              />
            </label>
            <button className="ui-button">
              {busy ? "Memproses…" : "Simpan keputusan"}
            </button>
            <button
              type="button"
              className="text-link"
              onClick={() => {
                setSelected(null);
                setPassword("");
              }}
            >
              Batal
            </button>
          </fieldset>
        </form>
      )}
      <div className="travel-list">
        {result?.data.map((item) => (
          <article className="travel-list-card" key={item.id}>
            <div>
              <span className="travel-status">
                {refundLabels[item.status] || item.status}
              </span>
              <h2>{item.reference}</h2>
              <p>
                {item.service} · Rp
                {item.refundable_amount.toLocaleString("id-ID")}
              </p>
              <p>{item.reason}</p>
              {item.failure_reason && <p>{item.failure_reason}</p>}
            </div>
            {item.status === "requested" && (
              <>
                <button
                  className="ui-button"
                  disabled={busy}
                  onClick={() => {
                    setSelected(item);
                    setAction("approve");
                    setNotes("");
                  }}
                >
                  Tinjau persetujuan
                </button>
                <button
                  className="ui-button ui-button-outline"
                  disabled={busy}
                  onClick={() => {
                    setSelected(item);
                    setAction("reject");
                    setNotes("");
                  }}
                >
                  Tolak
                </button>
              </>
            )}
            {item.status === "processing" && (
              <button
                className="ui-button ui-button-outline"
                disabled={busy}
                onClick={() => refresh(item)}
              >
                Periksa provider
              </button>
            )}
          </article>
        ))}
      </div>
      {result && !result.data.length && (
        <div className="empty-state">Belum ada pengajuan refund.</div>
      )}
      {result && (
        <div className="travel-toolbar">
          <button
            className="ui-button ui-button-outline"
            disabled={busy || page <= 1}
            onClick={() => setPage(page - 1)}
          >
            Sebelumnya
          </button>
          <span>
            Halaman {page} / {result.meta.last_page}
          </span>
          <button
            className="ui-button ui-button-outline"
            disabled={busy || page >= result.meta.last_page}
            onClick={() => setPage(page + 1)}
          >
            Berikutnya
          </button>
        </div>
      )}
    </Shell>
  );
}
