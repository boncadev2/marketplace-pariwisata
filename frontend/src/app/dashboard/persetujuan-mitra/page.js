"use client";

import Link from "next/link";
import { useState } from "react";
import { PageHeader } from "../../../components/PageHeader";
import { Shell } from "../../../components/Shell";
import { apiRequest } from "../../../lib/api";

const field = "mt-1 w-full rounded-xl border border-slate-300 p-3";
const button =
  "rounded-xl bg-blue-700 px-4 py-3 font-bold text-white disabled:opacity-50";
const labels = {
  pending: "Menunggu keputusan",
  accepted: "Disetujui",
  rejected: "Ditolak",
};

export default function PartnerAgreementPage() {
  const [proposals, setProposals] = useState([]);
  const [memberships, setMemberships] = useState([]);
  const [meta, setMeta] = useState(null);
  const [selected, setSelected] = useState(null);
  const [partnerId, setPartnerId] = useState("");
  const [decision, setDecision] = useState("accepted");
  const [reason, setReason] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");

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
  async function load(page = 1) {
    await run(async () => {
      const payload = await apiRequest(
        `/partner/cross-village-proposals?page=${page}`
      );
      setProposals(payload.data);
      setMemberships(payload.memberships);
      setMeta(payload.meta);
      setSelected(null);
      setPassword("");
      if (!payload.data.length)
        setMessage("Belum ada proposal untuk mitra yang Anda kelola.");
    });
  }
  async function select(proposal) {
    await run(async () => {
      const payload = await apiRequest(
        `/products/${encodeURIComponent(proposal.slug)}/cross-village-agreement`
      );
      setSelected({ ...payload.data, slug: proposal.slug });
      setPartnerId("");
      setReason("");
      setPassword("");
    });
  }
  async function submit() {
    await run(async () => {
      try {
        const confirmation = await apiRequest("/security/confirm-password", {
          method: "POST",
          body: JSON.stringify({ password }),
        });
        const payload = await apiRequest(
          `/products/${encodeURIComponent(selected.slug)}/cross-village-agreement`,
          {
            method: "POST",
            headers: {
              "X-Sensitive-Confirmation": confirmation.confirmation_token,
            },
            body: JSON.stringify({
              partner_id: Number(partnerId),
              version: selected.version,
              decision,
              reason,
            }),
          }
        );
        setSelected({ ...selected, ...payload.data });
        setReason("");
        setMessage("Keputusan mitra tersimpan dan diaudit.");
      } finally {
        setPassword("");
      }
    });
  }
  const eligible = selected
    ? memberships.filter((partner) =>
        selected.shares.some((share) => share.partner_id === partner.id)
      )
    : [];

  return (
    <Shell>
      <div className="operations-form space-y-6">
        <PageHeader
          eyebrow="Persetujuan mitra"
          title="Kolaborasi dimulai dari persetujuan."
          description="Tinjau proposal paket, porsi pendapatan, dan keputusan pada revisi yang aktif."
          compact
        />
        <p className="rounded-xl bg-amber-50 p-4 text-amber-900">
          Alur persetujuan simulasi lokal untuk owner dan manager aktif.
          Keputusan ini belum menjadi kontrak produksi atau instruksi pencairan
          dana. Revisi konfigurasi meminta keputusan ulang dari seluruh mitra.
        </p>
        <button className={button} disabled={busy} onClick={() => load()}>
          Muat / muat ulang proposal saya
        </button>
        <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-6">
          {proposals.map((proposal) => (
            <button
              key={proposal.id}
              disabled={busy}
              onClick={() => select(proposal)}
              className="block w-full rounded-xl border border-slate-200 p-4 text-left font-bold text-emerald-800"
            >
              {proposal.name} · Lihat porsi dan keputusan
            </button>
          ))}
          {meta && (
            <div className="flex items-center gap-3">
              <button
                disabled={busy || meta.current_page === 1}
                onClick={() => load(meta.current_page - 1)}
                className={button}
              >
                Sebelumnya
              </button>
              <span>
                {meta.current_page} / {meta.last_page}
              </span>
              <button
                disabled={busy || meta.current_page >= meta.last_page}
                onClick={() => load(meta.current_page + 1)}
                className={button}
              >
                Berikutnya
              </button>
            </div>
          )}
        </section>
        {selected && (
          <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6">
            <h2 className="text-xl font-bold">
              {selected.product_name} · Revisi {selected.revision}
            </h2>
            <p className="font-semibold">
              {selected.all_accepted
                ? "Semua mitra menyetujui revisi ini."
                : "Persetujuan seluruh mitra belum terpenuhi."}
            </p>
            {selected.shares.map((share) => (
              <p key={share.partner_id}>
                Mitra ID {share.partner_id}:{" "}
                <strong>{share.revenue_share_percentage}%</strong>
                {share.is_primary_partner ? " · Utama" : ""} ·{" "}
                {labels[
                  selected.agreements.find(
                    (row) => row.partner_id === share.partner_id
                  )?.decision
                ] || labels.pending}
              </p>
            ))}
            <fieldset disabled={busy} className="space-y-3">
              <label className="block">
                Mitra yang Anda wakili
                <select
                  className={field}
                  value={partnerId}
                  onChange={(event) => setPartnerId(event.target.value)}
                >
                  <option value="">Pilih mitra</option>
                  {eligible.map((partner) => (
                    <option key={partner.id} value={partner.id}>
                      {partner.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="block">
                Keputusan
                <select
                  className={field}
                  value={decision}
                  onChange={(event) => setDecision(event.target.value)}
                >
                  <option value="accepted">
                    Setujui porsi pada revisi ini
                  </option>
                  <option value="rejected">
                    Tolak / tarik persetujuan pada revisi ini
                  </option>
                </select>
              </label>
              <label className="block">
                Alasan
                <textarea
                  className={field}
                  value={reason}
                  maxLength={500}
                  onChange={(event) => setReason(event.target.value)}
                />
              </label>
              <label className="block">
                Kata sandi akun Anda
                <input
                  className={field}
                  type="password"
                  autoComplete="current-password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                />
              </label>
              <button
                className={button}
                disabled={!partnerId || !password || reason.trim().length < 5}
                onClick={submit}
              >
                Simpan keputusan mitra
              </button>
            </fieldset>
            <button
              className={button}
              disabled={busy}
              onClick={() => select(selected)}
            >
              Muat ulang revisi dan keputusan
            </button>
          </section>
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
