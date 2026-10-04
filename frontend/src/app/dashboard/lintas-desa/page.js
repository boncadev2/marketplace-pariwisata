"use client";

import Link from "next/link";
import { useState } from "react";
import { PageHeader } from "../../../components/PageHeader";
import { Shell } from "../../../components/Shell";
import { apiRequest } from "../../../lib/api";

const inputStyle = "mt-1 w-full rounded-xl border border-slate-300 p-3";
const buttonStyle =
  "rounded-xl bg-blue-700 px-4 py-3 font-bold text-white disabled:opacity-50";
const money = (value) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);

export default function CrossVillagePage() {
  const [slug, setSlug] = useState("");
  const [loadedSlug, setLoadedSlug] = useState("");
  const [configuration, setConfiguration] = useState(null);
  const [shares, setShares] = useState([]);
  const [search, setSearch] = useState("");
  const [reason, setReason] = useState("");
  const [password, setPassword] = useState("");
  const [date, setDate] = useState("");
  const [quantity, setQuantity] = useState(1);
  const [quote, setQuote] = useState(null);
  const [orderId, setOrderId] = useState("");
  const [orderSnapshot, setOrderSnapshot] = useState(null);
  const [snapshotReason, setSnapshotReason] = useState("");
  const [snapshotPassword, setSnapshotPassword] = useState("");
  const [snapshotOrderStatus, setSnapshotOrderStatus] = useState("");
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");

  const path = (value) =>
    `/products/${encodeURIComponent(value)}/cross-village-configuration`;
  async function run(action) {
    setBusy(true);
    setMessage("");
    try {
      await action();
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }
  async function load() {
    await run(async () => {
      const payload = await apiRequest(path(slug.trim()));
      setConfiguration(payload.data);
      setLoadedSlug(slug.trim());
      setOrderSnapshot(null);
      setSnapshotPassword("");
      setShares(payload.data.shares);
      setDirty(false);
      setQuote(null);
      setPassword("");
      setMessage(
        "Konfigurasi dimuat. Hasil yang belum disimpan diganti dengan data server."
      );
    });
  }
  function edit(next) {
    setShares(next);
    setDirty(true);
    setQuote(null);
  }
  async function save() {
    await run(async () => {
      try {
        const confirmation = await apiRequest("/security/confirm-password", {
          method: "POST",
          body: JSON.stringify({ password }),
        });
        const payload = await apiRequest(path(loadedSlug), {
          method: "PUT",
          headers: {
            "X-Sensitive-Confirmation": confirmation.confirmation_token,
          },
          body: JSON.stringify({
            version: configuration.version,
            reason,
            shares,
          }),
        });
        setConfiguration({ ...configuration, ...payload.data });
        setShares(payload.data.shares);
        setDirty(false);
        setReason("");
        setMessage("Porsi simulasi tersimpan dan tercatat di audit.");
      } finally {
        setPassword("");
      }
    });
  }
  const snapshotPath = `/orders/${encodeURIComponent(orderId.trim())}/cross-village-snapshot`;
  async function captureSnapshot() {
    await run(async () => {
      setOrderSnapshot(null);
      setSnapshotOrderStatus("");
      try {
        const confirmation = await apiRequest("/security/confirm-password", {
          method: "POST",
          body: JSON.stringify({ password: snapshotPassword }),
        });
        const payload = await apiRequest(snapshotPath, {
          method: "POST",
          headers: {
            "X-Sensitive-Confirmation": confirmation.confirmation_token,
          },
          body: JSON.stringify({
            version: configuration.version,
            reason: snapshotReason,
          }),
        });
        setOrderSnapshot(payload.data);
        setSnapshotReason("");
        setMessage(
          "Snapshot simulasi tersimpan. Pembagian ini belum digunakan untuk payout."
        );
      } finally {
        setSnapshotPassword("");
      }
    });
  }
  const total = shares.reduce(
    (sum, share) =>
      sum + Math.round(Number(share.revenue_share_percentage) * 100),
    0
  );

  return (
    <Shell>
      <div className="operations-form space-y-6">
        <PageHeader
          eyebrow="Konfigurasi paket"
          title="Satu perjalanan, kolaborasi lintas desa."
          description="Kelola mitra dan porsi pendapatan paket pada setiap revisi konfigurasi."
          compact
        />
        <p className="rounded-xl bg-amber-50 p-4 text-amber-900">
          Konfigurasi simulasi lokal. Memerlukan admin terverifikasi. Pembagian
          ini belum digunakan untuk transaksi atau pencairan dana.
        </p>
        <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-6">
          <label className="block font-bold">
            Slug produk paket
            <input
              value={slug}
              onChange={(event) => setSlug(event.target.value)}
              className={inputStyle}
              placeholder="contoh-paket-wisata"
              disabled={busy}
            />
          </label>
          <button
            onClick={load}
            disabled={busy || !slug.trim()}
            className={buttonStyle}
          >
            Muat / muat ulang konfigurasi
          </button>
          {dirty && (
            <p className="text-sm text-amber-800">
              Ada perubahan belum disimpan. Muat ulang akan menggantinya.
            </p>
          )}
        </section>
        {configuration && (
          <>
            <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6">
              <h2 className="text-xl font-bold">
                {configuration.product_name}
              </h2>
              <p className="text-sm">
                Mitra utama harus pemilik produk (ID{" "}
                {configuration.owner_partner_id}). Pilih minimal dua mitra dari
                dua desa berbeda.
              </p>
              <div className="rounded-xl bg-slate-50 p-4">
                <p className="font-bold">
                  Revisi {configuration.revision} ·{" "}
                  {configuration.all_accepted
                    ? "Semua mitra menyetujui"
                    : "Persetujuan mitra belum lengkap"}
                </p>
                {configuration.agreements?.map((row) => (
                  <p key={row.partner_id}>
                    Mitra ID {row.partner_id}:{" "}
                    {
                      {
                        pending: "Menunggu",
                        accepted: "Disetujui",
                        rejected: "Ditolak",
                      }[row.decision]
                    }
                  </p>
                ))}
                <p className="mt-2 text-sm">
                  Muat ulang konfigurasi untuk keputusan terbaru. Menyimpan
                  porsi membuat revisi baru dan meminta persetujuan ulang.
                </p>
              </div>
              <fieldset disabled={busy} className="space-y-4">
                <label className="block">
                  Cari mitra disetujui
                  <input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    className={inputStyle}
                  />
                </label>
                <button
                  type="button"
                  className={buttonStyle}
                  onClick={() =>
                    run(async () => {
                      const payload = await apiRequest(
                        `${path(loadedSlug)}?search=${encodeURIComponent(search)}`
                      );
                      setConfiguration({
                        ...configuration,
                        candidates: payload.data.candidates,
                      });
                    })
                  }
                >
                  Cari mitra
                </button>
                <p className="text-sm text-slate-500">
                  Menampilkan maksimal 30 kandidat. Gunakan pencarian untuk
                  mitra lainnya.
                </p>
                {shares.map((share, index) => (
                  <div
                    key={index}
                    className="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-3"
                  >
                    <label>
                      Mitra
                      <select
                        className={inputStyle}
                        value={share.partner_id}
                        onChange={(event) =>
                          edit(
                            shares.map((row, i) =>
                              i === index
                                ? {
                                    ...row,
                                    partner_id: Number(event.target.value),
                                  }
                                : row
                            )
                          )
                        }
                      >
                        <option value="">Pilih mitra</option>
                        {share.partner_id &&
                          !configuration.candidates.some(
                            (partner) => partner.id === share.partner_id
                          ) && (
                            <option value={share.partner_id}>
                              Mitra ID {share.partner_id}
                            </option>
                          )}
                        {configuration.candidates.map((partner) => (
                          <option key={partner.id} value={partner.id}>
                            {partner.name} · {partner.village_name}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label>
                      Porsi (%)
                      <input
                        className={inputStyle}
                        inputMode="decimal"
                        value={share.revenue_share_percentage}
                        onChange={(event) =>
                          edit(
                            shares.map((row, i) =>
                              i === index
                                ? {
                                    ...row,
                                    revenue_share_percentage:
                                      event.target.value,
                                  }
                                : row
                            )
                          )
                        }
                        placeholder="50.00"
                      />
                    </label>
                    <div className="space-y-3 pt-3">
                      <label className="flex gap-2">
                        <input
                          type="radio"
                          name="primary"
                          checked={share.is_primary_partner}
                          onChange={() =>
                            edit(
                              shares.map((row, i) => ({
                                ...row,
                                is_primary_partner: i === index,
                              }))
                            )
                          }
                        />
                        Mitra utama
                      </label>
                      <button
                        type="button"
                        className="text-red-700 underline"
                        onClick={() =>
                          edit(shares.filter((_, i) => i !== index))
                        }
                      >
                        Hapus baris
                      </button>
                    </div>
                  </div>
                ))}
                <button
                  type="button"
                  className="font-bold text-emerald-700"
                  disabled={shares.length >= 20}
                  onClick={() =>
                    edit([
                      ...shares,
                      {
                        partner_id: "",
                        revenue_share_percentage: "0.00",
                        is_primary_partner: false,
                      },
                    ])
                  }
                >
                  + Tambah mitra
                </button>
                <p className="font-bold">
                  Total porsi:{" "}
                  {Number.isFinite(total) ? (total / 100).toFixed(2) : "—"}%
                </p>
                <p className="text-sm">
                  Gunakan dua desimal dengan titik, misalnya 50.00.
                </p>
                <label className="block">
                  Alasan perubahan
                  <textarea
                    className={inputStyle}
                    value={reason}
                    maxLength={500}
                    onChange={(event) => setReason(event.target.value)}
                  />
                </label>
                <label className="block">
                  Kata sandi admin
                  <input
                    type="password"
                    autoComplete="current-password"
                    className={inputStyle}
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                  />
                </label>
                <button
                  onClick={save}
                  className={buttonStyle}
                  disabled={
                    !dirty ||
                    total !== 10000 ||
                    shares.length < 2 ||
                    !password ||
                    reason.trim().length < 5
                  }
                >
                  Simpan porsi simulasi
                </button>
              </fieldset>
            </section>
            <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6">
              <h2 className="text-xl font-bold">Simulasi pendapatan</h2>
              <div className="grid gap-3 sm:grid-cols-2">
                <label>
                  Tanggal kunjungan
                  <input
                    type="date"
                    className={inputStyle}
                    value={date}
                    onChange={(event) => {
                      setDate(event.target.value);
                      setQuote(null);
                    }}
                    disabled={busy}
                  />
                </label>
                <label>
                  Peserta
                  <input
                    type="number"
                    min={1}
                    max={100}
                    className={inputStyle}
                    value={quantity}
                    onChange={(event) => {
                      setQuantity(event.target.value);
                      setQuote(null);
                    }}
                    disabled={busy}
                  />
                </label>
              </div>
              <button
                className={buttonStyle}
                disabled={busy || dirty || !date || !quantity}
                onClick={() =>
                  run(async () => {
                    setQuote(null);
                    const payload = await apiRequest(
                      `/products/${encodeURIComponent(loadedSlug)}/cross-village-quote?${new URLSearchParams({ visit_date: date, quantity })}`
                    );
                    setQuote(payload.data);
                  })
                }
              >
                Hitung dari porsi tersimpan
              </button>
              {quote && (
                <div className="space-y-2">
                  <p>
                    Total: {money(quote.total)} · Komisi:{" "}
                    {money(quote.commission_amount)} · Pendapatan mitra:{" "}
                    {money(quote.partner_revenue)}
                  </p>
                  {quote.allocations.map((row) => (
                    <p key={row.partner_id}>
                      Mitra ID {row.partner_id} ({row.share_percentage}%):{" "}
                      <strong>{money(row.partner_revenue)}</strong>
                    </p>
                  ))}
                </div>
              )}
            </section>
            <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6">
              <h2 className="text-xl font-bold">Snapshot pesanan sandbox</h2>
              <p className="text-sm text-slate-600">
                Gunakan ID publik pesanan paket yang sudah dibayar melalui
                sandbox. Snapshot memakai nominal pesanan dan porsi tersimpan
                saat ini. Snapshot pertama bersifat tetap; konfigurasi baru
                tidak menggantinya.
              </p>
              <fieldset disabled={busy} className="space-y-3">
                <label className="block">
                  ID publik pesanan
                  <input
                    className={inputStyle}
                    value={orderId}
                    onChange={(event) => {
                      setOrderId(event.target.value);
                      setOrderSnapshot(null);
                      setSnapshotOrderStatus("");
                    }}
                  />
                </label>
                <button
                  className={buttonStyle}
                  disabled={!orderId.trim()}
                  onClick={() =>
                    run(async () => {
                      setOrderSnapshot(null);
                      setSnapshotOrderStatus("");
                      const payload = await apiRequest(snapshotPath);
                      setOrderSnapshot(payload.data);
                      setSnapshotOrderStatus(payload.order_status);
                      if (!payload.data)
                        setMessage(
                          "Pesanan ini belum memiliki snapshot lintas desa."
                        );
                    })
                  }
                >
                  Lihat snapshot tersimpan
                </button>
                <label className="block">
                  Alasan membuat snapshot
                  <textarea
                    className={inputStyle}
                    maxLength={500}
                    value={snapshotReason}
                    onChange={(event) => setSnapshotReason(event.target.value)}
                  />
                </label>
                <label className="block">
                  Kata sandi admin
                  <input
                    className={inputStyle}
                    type="password"
                    autoComplete="current-password"
                    value={snapshotPassword}
                    onChange={(event) =>
                      setSnapshotPassword(event.target.value)
                    }
                  />
                </label>
                <button
                  className={buttonStyle}
                  disabled={
                    dirty ||
                    !orderId.trim() ||
                    !snapshotPassword ||
                    snapshotReason.trim().length < 5
                  }
                  onClick={captureSnapshot}
                >
                  Simpan snapshot simulasi pesanan
                </button>
              </fieldset>
              {orderSnapshot && (
                <div className="space-y-2 rounded-xl bg-amber-50 p-4">
                  <p className="font-bold">
                    Snapshot simulasi · {orderSnapshot.captured_at}
                  </p>
                  {snapshotOrderStatus && (
                    <p>Status pesanan saat ini: {snapshotOrderStatus}</p>
                  )}
                  <p>
                    Total pesanan: {money(orderSnapshot.total)} · Komisi:{" "}
                    {money(orderSnapshot.commission_amount)}
                  </p>
                  {orderSnapshot.allocations.map((row) => (
                    <p key={row.partner_id}>
                      Mitra ID {row.partner_id} ({row.share_percentage}%):
                      pendapatan <strong>{money(row.partner_revenue)}</strong>,
                      komisi {money(row.commission_amount)}
                    </p>
                  ))}
                  <p>
                    Persetujuan saat snapshot:{" "}
                    {orderSnapshot.all_accepted ? "Lengkap" : "Belum lengkap"}
                  </p>
                  <p className="text-sm">
                    Riwayat simulasi ini tidak berubah otomatis ketika pesanan
                    direfund.
                  </p>
                </div>
              )}
            </section>
          </>
        )}
        {busy && <p role="status">Memproses…</p>}
        {message && (
          <p role="status" className="rounded-xl bg-slate-100 p-4">
            {message}
          </p>
        )}
      </div>
    </Shell>
  );
}
