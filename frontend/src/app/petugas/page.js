"use client";

import { useState } from "react";
import { PageHeader } from "../../components/PageHeader";
import { Shell } from "../../components/Shell";
import { apiRequest } from "../../lib/api";

export default function StaffPage() {
  const [token, setToken] = useState("");
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);

  async function scan(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    if (!("BarcodeDetector" in window)) {
      setMessage(
        "Browser ini belum mendukung pemindaian QR. Masukkan kode voucher."
      );
      return;
    }
    let bitmap;
    try {
      bitmap = await createImageBitmap(file);
      const detector = new window.BarcodeDetector({ formats: ["qr_code"] });
      const results = await detector.detect(bitmap);
      const value = results.find((result) =>
        /^[A-Za-z0-9]{48}$/.test(result.rawValue)
      )?.rawValue;
      if (!value) throw new Error("Kode voucher tidak ditemukan pada foto.");
      setToken(value);
      setMessage(
        "Kode terbaca. Periksa voucher lalu tekan Validasi kunjungan."
      );
    } catch (error) {
      setMessage(
        error.message || "Foto tidak dapat dibaca. Masukkan kode voucher."
      );
    } finally {
      bitmap?.close();
      event.target.value = "";
    }
  }

  async function redeem(event) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    setMessage("");
    try {
      const result = await apiRequest("/staff/vouchers/redeem", {
        method: "POST",
        body: JSON.stringify({ token: token.trim() }),
      });
      setMessage(
        `Kunjungan tervalidasi untuk ${result.data.used_admissions} peserta.`
      );
      setToken("");
    } catch (error) {
      setMessage(
        error.message || "Tidak dapat terhubung. Kunjungan belum divalidasi."
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Shell>
      <PageHeader
        eyebrow="Portal petugas"
        title="Sambut pengunjung dengan lebih mudah."
        description="Validasi voucher dan pastikan kunjungan sesuai pesanan."
        compact
      />
      <section className="page-intro voucher-panel">
        <h2 className="text-xl font-bold mb-3">Validasi voucher</h2>
        <p>
          Gunakan akun petugas mitra. Validasi memerlukan koneksi internet dan
          tanggal kunjungan yang sesuai.
        </p>
        <form className="search-panel" onSubmit={redeem}>
          <label>
            Kode voucher
            <input
              required
              minLength={48}
              maxLength={48}
              autoComplete="off"
              value={token}
              onChange={(event) => setToken(event.target.value)}
              disabled={busy}
            />
          </label>
          <label>
            Pindai QR dari foto
            <input
              type="file"
              accept="image/*"
              capture="environment"
              onChange={scan}
              disabled={busy}
            />
          </label>
          <button disabled={busy}>
            {busy ? "Memvalidasi…" : "Validasi kunjungan"}
          </button>
        </form>
        <p role="status" aria-live="polite">
          {message}
        </p>
      </section>
    </Shell>
  );
}
