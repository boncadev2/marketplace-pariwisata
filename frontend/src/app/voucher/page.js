"use client";

import { useEffect, useRef, useState } from "react";
import QRCode from "qrcode";
import { Shell } from "../../components/Shell";
import { apiRequest } from "../../lib/api";

function VoucherCode({ voucher }) {
  const canvas = useRef(null);
  const [error, setError] = useState("");
  useEffect(() => {
    if (voucher.status !== "active") return;
    let active = true;
    QRCode.toCanvas(canvas.current, voucher.token, {
      width: 260,
      margin: 4,
      errorCorrectionLevel: "M",
    }).catch(() => {
      if (active)
        setError("QR tidak dapat ditampilkan. Gunakan kode voucher di bawah.");
    });
    return () => {
      active = false;
    };
  }, [voucher.token, voucher.status]);
  return (
    <article className="card">
      <div className="card-body">
        <h2>Voucher kunjungan</h2>
        <p>
          Tanggal: {voucher.service_date} · {voucher.admissions} peserta
        </p>
        <p>
          {voucher.status === "redeemed"
            ? "Sudah digunakan"
            : "Tunjukkan QR ini kepada petugas saat kunjungan."}
        </p>
        {voucher.status === "active" && (
          <>
            <canvas ref={canvas} aria-label="QR voucher kunjungan" />
            <p>{error}</p>
            <p style={{ overflowWrap: "anywhere" }}>{voucher.token}</p>
          </>
        )}
      </div>
    </article>
  );
}

export default function VoucherPage() {
  const [result, setResult] = useState(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  async function open(event) {
    event.preventDefault();
    if (busy) return;
    const form = new FormData(event.currentTarget);
    setResult(null);
    setMessage("");
    setBusy(true);
    try {
      const response = await apiRequest(
        `/guest/orders/${encodeURIComponent(form.get("order_id").trim())}/vouchers`,
        { headers: { "X-Guest-Access-Token": form.get("access_token").trim() } }
      );
      setResult(response.data);
      if (!response.data.vouchers.length)
        setMessage("Voucher belum tersedia untuk status pesanan ini.");
    } catch {
      setMessage(
        "Pesanan tidak dapat dibuka. Periksa nomor pesanan, kode akses, dan koneksi."
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <Shell>
      <section className="page-intro">
        <h1>Voucher perjalanan</h1>
        <p>
          Masukkan nomor pesanan dan kode akses yang diterima saat checkout.
        </p>
        <form className="search-panel" onSubmit={open}>
          <label>
            Nomor pesanan
            <input name="order_id" required disabled={busy} />
          </label>
          <label>
            Kode akses
            <input
              name="access_token"
              type="password"
              autoComplete="off"
              minLength={48}
              maxLength={48}
              required
              disabled={busy}
            />
          </label>
          <button disabled={busy}>{busy ? "Memuat…" : "Buka voucher"}</button>
        </form>
        <p role="status">{message}</p>
        {result?.vouchers.map((voucher) => (
          <VoucherCode key={voucher.token} voucher={voucher} />
        ))}
      </section>
    </Shell>
  );
}
