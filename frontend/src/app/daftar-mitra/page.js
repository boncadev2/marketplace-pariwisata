"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Shell } from "../../components/Shell";
import { PageHeader } from "../../components/PageHeader";
import { apiRequest } from "../../lib/api";

const labels = { draft: "Menunggu persetujuan admin", approved: "Disetujui", rejected: "Ditolak" };
const field = "mt-2 w-full rounded-xl border border-slate-300 bg-white p-3";

export default function PartnerRegistrationPage() {
  const [account, setAccount] = useState(null);
  const [regions, setRegions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [guest, setGuest] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  useEffect(() => {
    async function load() {
      try {
        const result = await apiRequest("/partner-applications");
        setAccount(result);
        const locations = await apiRequest("/lookup/regions");
        setRegions(locations.data);
      } catch (error) {
        if (error.status === 401) setGuest(true);
        else setMessage("Pendaftaran belum dapat dimuat. Muat ulang halaman untuk mencoba lagi.");
      } finally { setLoading(false); }
    }
    load();
  }, []);
  async function submit(event) {
    event.preventDefault();
    if (busy) return;
    setBusy(true); setMessage("");
    const values = new FormData(event.currentTarget);
    try {
      const result = await apiRequest("/partner-applications", {
        method: "POST", body: JSON.stringify({ name: values.get("name"), region_id: Number(values.get("region_id")), contact_phone: values.get("contact_phone") }),
      });
      setAccount({ ...account, data: [result.data] });
      setMessage("Pengajuan berhasil dikirim. Admin akan meninjau usaha Anda.");
    } catch (error) {
      if (error.status === 409) {
        setAccount(await apiRequest("/partner-applications"));
      }
      setMessage(error.status === 422 ? "Periksa nama usaha, wilayah, dan nomor telepon (7–30 karakter, angka, spasi, +, tanda kurung atau tanda hubung)." : error.message);
    } finally { setBusy(false); }
  }
  async function verify() {
    if (busy) return;
    setBusy(true); setMessage("");
    try {
      await apiRequest("/email/verification-notification", { method: "POST", body: "{}" });
      setMessage("Tautan verifikasi dikirim. Periksa email, lalu muat ulang halaman setelah verifikasi.");
    } catch (error) { setMessage(error.message); }
    finally { setBusy(false); }
  }
  return <Shell><div className="operations-form space-y-6">
    <PageHeader eyebrow="Kemitraan" title="Usaha lokal, peluang lebih luas." description="Daftarkan usaha Anda untuk menawarkan produk UMKM, penginapan, dan kuliner melalui WisataDaerah." compact />
    <div className="grid gap-3 sm:grid-cols-3">{["1 · Buat akun & verifikasi email", "2 · Ajukan profil usaha", "3 · Persetujuan admin"].map(step => <p key={step} className="rounded-2xl bg-blue-50 p-4 font-semibold text-blue-900">{step}</p>)}</div>
    {loading && <p role="status">Memuat pendaftaran…</p>}
    {guest && <section className="rounded-2xl border border-slate-200 bg-white p-6 space-y-4"><h2 className="text-xl font-bold">Mulai dengan akun Anda</h2><p>Gunakan akun yang sama untuk berbelanja dan mengelola usaha. Setelah masuk dan verifikasi email, isi pengajuan di halaman ini.</p><div className="flex flex-wrap gap-3"><Link className="ui-button" href="/daftar">Buat akun</Link><Link className="ui-button" href="/login">Masuk ke akun</Link></div></section>}
    {account && !account.email_verified && <section className="rounded-2xl bg-amber-50 p-6 space-y-3"><h2 className="font-bold">Verifikasi email terlebih dahulu</h2><p>Periksa tautan verifikasi di email Anda sebelum mengajukan usaha.</p><button className="ui-button" disabled={busy} onClick={verify}>Kirim ulang tautan verifikasi</button></section>}
    {account?.data.map(row => <section key={row.id} className="rounded-2xl border border-slate-200 bg-white p-6 space-y-3"><p className="text-sm font-bold text-blue-700">{labels[row.status] || row.status}</p><h2 className="text-2xl font-bold">{row.name}</h2><p>{row.contact_email} · {row.contact_phone}</p>{row.status === "draft" && <p>Pengajuan tersimpan. Akses pengelolaan usaha akan aktif setelah persetujuan admin.</p>}{row.review_reason && <p>Catatan admin: {row.review_reason}</p>}{row.status === "approved" && <Link className="ui-button" href="/dashboard">Buka dashboard mitra</Link>}{row.status === "rejected" && <p>Hubungi admin untuk menindaklanjuti catatan pengajuan.</p>}</section>)}
    {account && !account.enabled && <p className="rounded-xl bg-amber-50 p-4">Pendaftaran mitra sedang ditutup.</p>}
    {account?.enabled && account.email_verified && !account.data.length && <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-6 space-y-5" aria-busy={busy}><h2 className="text-xl font-bold">Profil usaha Anda</h2><fieldset disabled={busy} className="space-y-5"><label className="block font-semibold">Nama usaha<input className={field} name="name" required minLength={2} maxLength={255} placeholder="Nama UMKM, penginapan, atau rumah makan" /></label><label className="block font-semibold">Wilayah usaha<select className={field} name="region_id" required defaultValue=""><option value="">Pilih wilayah</option>{regions.map(row => <option key={row.id} value={row.id}>{row.name} · {row.type}</option>)}</select></label><label className="block font-semibold">Nomor telepon usaha<input className={field} type="tel" name="contact_phone" required minLength={7} maxLength={30} placeholder="081234567890" /></label><p className="text-sm text-slate-600">Email kontak menggunakan email akun Anda. Pengajuan akan ditinjau admin sebelum akses pengelolaan diaktifkan.</p><button className="ui-button" disabled={!regions.length}>{busy ? "Mengirim…" : "Kirim pengajuan mitra"}</button></fieldset></form>}
    {message && <p role="status" className="rounded-xl bg-slate-100 p-4">{message}</p>}
  </div></Shell>;
}
