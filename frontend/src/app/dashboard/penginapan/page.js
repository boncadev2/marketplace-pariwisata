"use client";
import { useEffect, useState } from "react";
import { apiRequest } from "../../../lib/api";
import { PageHeader } from "../../../components/PageHeader";
import { Shell } from "../../../components/Shell";
import { LodgingRateCalendar } from "../../../components/LodgingRateCalendar";
import { LodgingCard } from "../../../components/LodgingCard";

export default function Page() {
  const [date, setDate] = useState(() => new Date().toLocaleDateString("en-CA"));
  const [page, setPage] = useState(1);
  const [catalog, setCatalog] = useState(null);
  const [calendarRoom, setCalendarRoom] = useState(null);
  const [form, setForm] = useState(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [photos, setPhotos] = useState({});
  const [refresh, setRefresh] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    apiRequest(`/dashboard/lodging-rooms?date=${date}&page=${page}`, { signal: controller.signal })
      .then((result) => { if (controller.signal.aborted) return; setCatalog(result); setForm(null); setCalendarRoom(null); setPhotos({}); setMessage(""); })
      .catch((error) => { if (!controller.signal.aborted) { setCatalog(null); setForm(null); setMessage(error.status === 403 ? "Halaman ini khusus administrator. Masuk dengan akun admin." : error.message); } });
    return () => controller.abort();
  }, [date, page, refresh]);
  function field(key, value) { setForm((current) => ({ ...current, [key]: value })); }
  async function save(event) {
    event.preventDefault(); setBusy(true); setMessage("");
    try {
      const body = new FormData();
      if (form.id) body.set("_method", "PATCH");
      for (const key of ["partner_id", "name", "description", "location", "latitude", "longitude", "exterior_image_url", "interior_image_url", "date", "revision"]) body.set(key, form[key] ?? "");
      for (const key of ["capacity", "price", "stock"]) body.set(key, String(Number(form[key])));
      for (const key of ["is_active", "location_is_demo", "photos_are_illustrations", "remove_exterior_photo", "remove_interior_photo"]) body.set(key, form[key] ? "1" : "0");
      for (const kind of ["exterior", "interior"]) if (photos[kind]) body.set(`${kind}_photo`, photos[kind]);
      const creating = !form.id;
      const result = await apiRequest(creating ? "/dashboard/lodging-rooms" : `/dashboard/lodging-rooms/${form.id}`, { method: "POST", body, headers: creating ? { "Idempotency-Key": form.creation_key } : {} });
      setForm(result.data); setPhotos({});
      if (creating) {
        try { setCatalog(await apiRequest(`/dashboard/lodging-rooms?date=${date}&page=${page}`)); }
        catch { setMessage("Kamar berhasil dibuat, tetapi daftar belum dapat diperbarui. Klik Muat ulang."); return; }
      } else {
        setCatalog((current) => ({ ...current, data: current.data.map((room) => room.id === result.data.id ? result.data : room) }));
      }
      setMessage(creating ? "Tipe kamar berhasil dibuat. Tarif dan stok awal berlaku untuk tanggal yang dipilih." : "Perubahan tersimpan. Tarif berlaku untuk tanggal yang dipilih.");
    } catch (error) { setMessage(error.message); }
    finally { setBusy(false); }
  }
  return <Shell>
    <PageHeader compact eyebrow="Pengelolaan penginapan" title="Kamar siap untuk perjalanan berikutnya." description="Atur tampilan penginapan, tarif per malam, dan jumlah kamar yang masih tersedia." action={{ href: "/penginapan", label: "Lihat katalog" }} />
    <section className="lodging-manager-toolbar lodging-manager-panel">
      <label>Tanggal tarif & ketersediaan<input aria-label="Tanggal tarif dan ketersediaan" type="date" value={date} disabled={busy} onChange={(event) => { setForm(null); setCalendarRoom(null); setCatalog(null); setDate(event.target.value); setPage(1); }} /></label>
      {catalog?.meta.editing_available && <button className="ui-button" disabled={busy || !date} onClick={() => {
        setCalendarRoom(null); setForm({ name: "", description: "", location: "", latitude: "", longitude: "", location_is_demo: false, capacity: 2, price: "", stock: "", is_active: false, photos_are_illustrations: true, exterior_image_url: "", interior_image_url: "", date, creation_key: crypto.randomUUID() }); setPhotos({}); setMessage("");
      }}>Tambah tipe kamar</button>}
      <button className="ui-button ui-button-outline" disabled={busy} onClick={() => setRefresh((current) => current + 1)}>Muat ulang</button>
      <p>Stok adalah kamar yang masih tersedia setelah pemesanan. Perubahan tarif tidak mengubah pesanan sebelumnya.</p>
    </section>
    {message && <p role="status" className="lodging-manager-panel">{message}</p>}
    {catalog && !catalog.meta.editing_available && <p className="lodging-manager-panel">Pengeditan tersedia pada lingkungan simulasi lokal.</p>}
    <div className="lodging-manager-layout">
      <section aria-label="Daftar kamar" className="lodging-product-grid">
        {catalog?.data.map((room) => <div key={room.id}><LodgingCard room={{ ...room, starting_price: room.price, exterior_image_url: room.exterior_photo_url || room.exterior_image_url, interior_image_url: room.interior_photo_url || room.interior_image_url }} actionLabel="Edit kamar" selectedLabel="Sedang diedit" selected={form?.id === room.id} disabled={busy || !catalog.meta.editing_available} onSelect={() => { setCalendarRoom(null); setForm(room); setPhotos({}); setMessage(""); }} /><p className="lodging-manager-summary">{room.is_active ? "Aktif" : "Disembunyikan"} · {room.stock === null ? "Ketersediaan belum diatur" : `${room.stock} kamar tersedia`} · {date}</p><button className="ui-button ui-button-outline" disabled={busy || !catalog.meta.editing_available} onClick={() => { setCalendarRoom(room); setForm(null); setPhotos({}); setMessage(""); }} aria-expanded={calendarRoom?.id === room.id} aria-controls="lodging-rate-calendar" aria-label={`Kalender tarif ${room.name}`}>Kalender tarif</button></div>)}
        {catalog?.data.length === 0 && <p>Belum ada tipe kamar.</p>}
      </section>
      {form && <form className="lodging-manager-panel lodging-manager-form" onSubmit={save}>
        <h2>{form.id ? "Edit kamar" : "Tambah tipe kamar"}</h2><p>Tarif & stok untuk <strong>{form.date}</strong>. Informasi dan foto berlaku untuk semua tanggal.</p>
        <fieldset disabled={busy}>
          <label>Mitra pengelola<select value={form.partner_id ?? ""} disabled={form.partner_id != null && Boolean(form.id)} onChange={event => field("partner_id", event.target.value || null)}><option value="">{form.id ? "Dikelola admin" : "Pilih mitra (admin boleh kosong)"}</option>{catalog?.meta.partners?.map(partner => <option key={partner.id} value={partner.id}>{partner.name}</option>)}</select></label><label>Nama kamar<input required minLength={2} maxLength={255} value={form.name} onChange={(event) => field("name", event.target.value)} /></label>
          <label>Deskripsi<textarea required minLength={10} maxLength={5000} rows={4} value={form.description || ""} onChange={(event) => field("description", event.target.value)} /></label>
          <label>Alamat lengkap penginapan<textarea maxLength={500} rows={3} value={form.location || ""} onChange={(event) => field("location", event.target.value)} /></label>
          <p>Nama penginapan dan alamat lengkap digunakan untuk membuka Google Maps. Koordinat berikut opsional jika Anda memiliki titik lokasi yang tepat.</p>
          <label>Latitude (opsional)<input type="number" step="any" min={-90} max={90} value={form.latitude ?? ""} onChange={(event) => field("latitude", event.target.value)} /></label>
          <label>Longitude (opsional)<input type="number" step="any" min={-180} max={180} value={form.longitude ?? ""} onChange={(event) => field("longitude", event.target.value)} /></label>
          <label><input type="checkbox" checked={!!form.location_is_demo} onChange={(event) => field("location_is_demo", event.target.checked)} /> Lokasi merupakan data demonstrasi</label>
          <label>Kapasitas tamu per kamar<input type="number" required min={1} max={100} value={form.capacity} onChange={(event) => field("capacity", event.target.value)} /></label>
          <label>URL foto tampak luar<input type="url" value={form.exterior_image_url || ""} onChange={(event) => field("exterior_image_url", event.target.value)} /></label>
          <label>URL foto interior<input type="url" value={form.interior_image_url || ""} onChange={(event) => field("interior_image_url", event.target.value)} /></label>
          <p>URL Unsplash menjadi cadangan jika foto unggahan belum tersedia. Foto unggahan tampil lebih dahulu.</p>
          {[{kind: "exterior", label: "tampak luar"}, {kind: "interior", label: "interior"}].map(({kind, label}) => <div className="lodging-upload-field" key={`${form.id}-${kind}-${form.revision}`}>
            <label>Unggah foto {label}<input type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => {
              const file = event.target.files?.[0];
              if (file && (file.size > 5 * 1024 * 1024 || !["image/jpeg", "image/png", "image/webp"].includes(file.type))) { setMessage("Gunakan foto JPG, PNG, atau WebP maksimal 5 MB."); event.target.value = ""; return; }
              setPhotos((current) => ({...current, [kind]: file}));
              field(`remove_${kind}_photo`, false);
            }} /></label>
            {photos[kind] && <p>{photos[kind].name} — akan diunggah saat disimpan.</p>}
            {form[`${kind}_photo_url`] && <label><input type="checkbox" checked={!!form[`remove_${kind}_photo`]} onChange={(event) => { field(`remove_${kind}_photo`, event.target.checked); setPhotos((current) => ({...current, [kind]: null})); }} /> Lepaskan foto unggahan {label}</label>}
          </div>)}
          <p>JPG, PNG, atau WebP, maksimal 5 MB per foto. Foto dipublikasikan ketika kamar aktif. Melepas foto tidak menghapus berkas secara permanen.</p>
          <label><input type="checkbox" checked={form.photos_are_illustrations} onChange={(event) => field("photos_are_illustrations", event.target.checked)} /> Foto merupakan ilustrasi</label>
          <label><input type="checkbox" checked={form.is_active} onChange={(event) => field("is_active", event.target.checked)} /> Tampilkan di katalog</label>
          <label>Tarif per kamar / malam (Rp)<input required type="number" min={1} max={1000000000} value={form.price ?? ""} onChange={(event) => field("price", event.target.value)} /></label>
          <label>Kamar yang masih tersedia<input required type="number" min={0} max={1000000} value={form.stock ?? ""} onChange={(event) => field("stock", event.target.value)} /></label>
          <button className="ui-button" type="submit">{busy ? "Menyimpan…" : form.id ? "Simpan perubahan" : "Buat tipe kamar"}</button>
          <button className="ui-button ui-button-outline" type="button" onClick={() => { setForm(null); setCalendarRoom(null); setPhotos({}); setMessage(""); }}>Tutup formulir</button>
        </fieldset>
      </form>}
    </div>
    {calendarRoom && <LodgingRateCalendar key={`${calendarRoom.id}-${date}`} room={calendarRoom} date={date} busy={busy} setBusy={setBusy} onClose={() => setCalendarRoom(null)} onSaved={async () => {
      try { setCatalog(await apiRequest(`/dashboard/lodging-rooms?date=${date}&page=${page}`)); }
      catch { setMessage("Tarif tersimpan, tetapi daftar kamar belum dapat diperbarui. Klik Muat ulang."); }
    }} />}
    {catalog && <div className="lodging-manager-toolbar"><button className="ui-button ui-button-outline" disabled={busy || page <= 1} onClick={() => { setCatalog(null); setForm(null); setCalendarRoom(null); setPage(page - 1); }}>Sebelumnya</button><span>Halaman {page} / {catalog.meta.last_page}</span><button className="ui-button ui-button-outline" disabled={busy || page >= catalog.meta.last_page} onClick={() => { setCatalog(null); setForm(null); setCalendarRoom(null); setPage(page + 1); }}>Berikutnya</button></div>}
  </Shell>;
}
