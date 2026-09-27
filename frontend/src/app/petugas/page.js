"use client";

import { useState } from "react";
import { Shell } from "../../components/Shell";

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
      const api = (
        process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1"
      ).replace(/\/$/, "");
      const csrf = await fetch(
        `${api.replace(/\/api\/v1$/, "")}/sanctum/csrf-cookie`,
        { credentials: "include" }
      );
      if (!csrf.ok) throw new Error("Tidak dapat menyiapkan sesi petugas.");
      const cookie = document.cookie
        .split("; ")
        .find((entry) => entry.startsWith("XSRF-TOKEN="));
      const response = await fetch(`${api}/staff/vouchers/redeem`, {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          ...(cookie
            ? { "X-XSRF-TOKEN": decodeURIComponent(cookie.slice(11)) }
            : {}),
        },
        body: JSON.stringify({ token: token.trim() }),
      });
      const result = await response.json();
      if (!response.ok) {
        const errors = {
          401: "Masuk dengan akun petugas terlebih dahulu.",
          404: "Voucher tidak ditemukan atau bukan milik mitra Anda.",
          409: "Voucher sudah digunakan, tanggal tidak sesuai, atau pesanan tidak aktif.",
          419: "Sesi kedaluwarsa. Silakan masuk kembali.",
        };
        throw new Error(
          errors[response.status] ||
            "Validasi gagal. Periksa kode dan coba kembali."
        );
      }
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
      <section className="page-intro">
        <p className="eyebrow">Petugas mitra</p>
        <h1>Validasi kunjungan</h1>
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
