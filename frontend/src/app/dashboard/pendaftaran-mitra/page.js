"use client";

import { useEffect, useState } from "react";
import { Shell } from "../../../components/Shell";
import { PageHeader } from "../../../components/PageHeader";
import { apiRequest } from "../../../lib/api";

const labels = { draft: "Menunggu persetujuan", approved: "Disetujui", rejected: "Ditolak" };
export default function PartnerApplicationsPage() {
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [filter, setFilter] = useState("draft");
  const [selected, setSelected] = useState(null);
  const [decision, setDecision] = useState("approved");
  const [reason, setReason] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  async function load(status = filter, page = 1) {
    setBusy(true); setMessage("");
    try {
      const result = await apiRequest(`/dashboard/partner-applications?status=${status}&page=${page}`);
      setRows(result.data); setMeta(result.meta);
    } catch (error) { setMessage(error.status === 403 ? "Halaman ini hanya untuk admin dengan email terverifikasi." : error.message); }
    finally { setBusy(false); }
  }
  useEffect(() => {
    let active = true;
    apiRequest("/dashboard/partner-applications?status=draft&page=1").then(result => { if (active) { setRows(result.data); setMeta(result.meta); } }).catch(error => { if (active) setMessage(error.status === 403 ? "Halaman ini hanya untuk admin dengan email terverifikasi." : error.message); });
    return () => { active = false; };
  }, []);
  async function submit(event) {
    event.preventDefault(); if (busy) return;
    setBusy(true); setMessage("");
    try {
      const confirmation = await apiRequest("/security/confirm-password", { method: "POST", body: JSON.stringify({ password }) });
      await apiRequest(`/dashboard/partner-applications/${selected.id}/decision`, { method: "POST", headers: { "X-Sensitive-Confirmation": confirmation.confirmation_token }, body: JSON.stringify({ decision, reason: reason || null }) });
      setSelected(null);
      await load(filter, meta?.current_page || 1);
      setMessage("Keputusan tersimpan. Akses pemilik aktif hanya untuk usaha yang disetujui.");
    } catch (error) { setMessage(error.message); }
    finally { setPassword(""); setBusy(false); }
  }
  return <Shell><div className="operations-form space-y-6"><PageHeader eyebrow="Pendaftaran mitra" title="Tinjau usaha yang bergabung." description="Periksa identitas usaha dan kontak sebelum mengaktifkan akses pemilik." compact />
    <div className="flex flex-wrap gap-3"><select aria-label="Status pengajuan" className="rounded-xl border p-3" disabled={busy} value={filter} onChange={event => { setFilter(event.target.value); setSelected(null); load(event.target.value); }}>{Object.entries(labels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select><button className="ui-button" disabled={busy} onClick={() => load()}>Muat ulang</button></div>
    {meta && !rows.length && <p>Belum ada pengajuan dengan status ini.</p>}
    {rows.map(row => <section key={row.id} className="rounded-2xl border border-slate-200 bg-white p-6 space-y-3"><p className="font-semibold text-blue-700">{labels[row.status]}</p><h2 className="text-xl font-bold">{row.name}</h2><p>Wilayah ID {row.region_id} · {row.contact_email} · {row.contact_phone}</p>{row.review_reason && <p>Catatan: {row.review_reason}</p>}{row.status === "draft" && <button className="ui-button" disabled={busy} onClick={() => { setSelected(row); setPassword(""); setReason(""); setDecision("approved"); }}>Tinjau pengajuan</button>}</section>)}
    {selected && <form onSubmit={submit} className="rounded-2xl border border-blue-200 bg-blue-50 p-6 space-y-4"><h2 className="text-xl font-bold">Keputusan untuk {selected.name}</h2><fieldset disabled={busy} className="space-y-4"><label className="block">Keputusan<select className="mt-2 w-full rounded-xl border bg-white p-3" value={decision} onChange={event => setDecision(event.target.value)}><option value="approved">Setujui dan aktifkan pemilik</option><option value="rejected">Tolak pengajuan</option></select></label><label className="block">Catatan untuk pendaftar<textarea className="mt-2 w-full rounded-xl border bg-white p-3" value={reason} onChange={event => setReason(event.target.value)} required={decision === "rejected"} minLength={5} maxLength={500} /></label><label className="block">Kata sandi admin<input className="mt-2 w-full rounded-xl border bg-white p-3" type="password" autoComplete="current-password" value={password} onChange={event => setPassword(event.target.value)} required /></label><button className="ui-button">Simpan keputusan</button><button type="button" className="ml-4" onClick={() => { setSelected(null); setPassword(""); }}>Batal</button></fieldset></form>}
    {meta && <div className="flex gap-4 items-center"><button disabled={busy || meta.current_page <= 1} onClick={() => load(filter, meta.current_page - 1)}>Sebelumnya</button><span>{meta.current_page} / {meta.last_page}</span><button disabled={busy || meta.current_page >= meta.last_page} onClick={() => load(filter, meta.current_page + 1)}>Berikutnya</button></div>}
    {message && <p role="status" className="rounded-xl bg-slate-100 p-4">{message}</p>}
  </div></Shell>;
}
