"use client";

import { useEffect, useState } from "react";
import { PlaceCatalogSearch } from "../../components/PlaceCatalogSearch";
import { PageHeader } from "../../components/PageHeader";
import { LodgingCard } from "../../components/LodgingCard";
import { LodgingBookings } from "../../components/LodgingBookings";
import { Shell } from "../../components/Shell";
import { apiRequest } from "../../lib/api";

export default function Page() {
  const [rooms, setRooms] = useState([]);
  const [roomPage, setRoomPage] = useState(1);
  const [lastRoomPage, setLastRoomPage] = useState(1);
  const [sandboxEnabled, setSandboxEnabled] = useState(false);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [search,setSearch]=useState(""), [appliedSearch,setAppliedSearch]=useState(""), [total,setTotal]=useState(0), [sort,setSort]=useState("name"), [appliedSort,setAppliedSort]=useState("name"), [minPrice,setMinPrice]=useState(""), [maxPrice,setMaxPrice]=useState(""), [budget,setBudget]=useState({min:"",max:""});
  useEffect(() => {
    const controller = new AbortController();
    apiRequest("/lodging/rooms", {signal: controller.signal}).then((catalog) => {
      if (controller.signal.aborted) return;
      setRooms(catalog.data.data); setLastRoomPage(catalog.data.last_page);
      setTotal(catalog.data.total);
      setSandboxEnabled(catalog.meta.sandbox_reservations_enabled);
    }).catch((error) => { if (!controller.signal.aborted) setMessage(error.message); })
      .finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, []);
  async function changePage(page, query = appliedSearch, order = appliedSort, range = budget) {
    setBusy(true); setMessage("");
    try {
      const result = await apiRequest(`/lodging/rooms?${new URLSearchParams({page:String(page),q:query,sort:order,...(range.min!==""?{min_price:range.min}:{}),...(range.max!==""?{max_price:range.max}:{})})}`);
      setRooms(result.data.data); setTotal(result.data.total); setAppliedSearch(query); setAppliedSort(order); setBudget(range); setRoomPage(result.data.current_page); setLastRoomPage(result.data.last_page);
    } catch (error) { setMessage(error.message); }
    finally { setBusy(false); }
  }
  function submitSearch(e){e.preventDefault();changePage(1,search.trim(),sort,{min:minPrice,max:maxPrice});}
  function resetSearch(){setSearch("");setSort("name");setMinPrice("");setMaxPrice("");changePage(1,"","name",{min:"",max:""});}
  return <Shell>
    <PageHeader eyebrow="Penginapan" title="Beristirahat nyaman. Bangun dengan cerita baru." description="Temukan penginapan pilihan Anda. Lihat detail, foto, dan tarif sebelum memeriksa ketersediaan tanggal menginap." image="https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=900&q=85" />
    <PlaceCatalogSearch label="penginapan" value={search} onChange={setSearch} sort={sort} onSortChange={setSort} minPrice={minPrice} maxPrice={maxPrice} onMinPriceChange={setMinPrice} onMaxPriceChange={setMaxPrice} onSearch={submitSearch} onReset={resetSearch} busy={busy||loading} />
    {!loading && <p className="place-catalog-result">{total} penginapan ditemukan{appliedSearch ? ` untuk “${appliedSearch}”` : ""}</p>}
    <p className="place-budget-note">Tarif per kamar / malam. Filter memakai harga mulai dari pada tanggal/jadwal dengan kuota tersedia; harga pilihan Anda diperiksa di halaman detail.</p>
    <p role="status" aria-live="polite">{message || (loading ? "Memuat penginapan…" : "")}</p>
    {!loading && rooms.length === 0 && <div className="lodging-manager-panel">{appliedSearch || budget.min!=="" || budget.max!=="" ? "Tidak ada tempat yang cocok. Coba nama atau alamat lain, sesuaikan kisaran harga, atau reset pencarian." : "Belum ada tempat aktif yang tersedia."}</div>}
    {rooms.length > 0 && <section className="lodging-catalog" aria-labelledby="lodging-catalog-title">
      <div className="lodging-catalog-heading"><div><span className="heading-kicker">Temukan tempat beristirahat</span><h2 id="lodging-catalog-title">Pilihan penginapan</h2></div><span>{rooms.length} tipe kamar di halaman ini</span></div>
      <p className="lodging-rate-note">Lihat detail penginapan terlebih dahulu. Tarif mulai dari harga terendah pada malam yang memiliki stok; harga tanggal pilihan Anda diperiksa di halaman detail.</p>
      <div className="lodging-product-grid">{rooms.map((room) => <LodgingCard key={room.id} room={room} href={`/penginapan/${room.id}`} actionLabel="Lihat detail" />)}</div>
      {lastRoomPage > 1 && <div className="catalog-pagination"><button className="ui-button ui-button-outline" disabled={busy || roomPage === 1} onClick={() => changePage(roomPage - 1)}>Sebelumnya</button><span>Halaman {roomPage} / {lastRoomPage}</span><button className="ui-button ui-button-outline" disabled={busy || roomPage === lastRoomPage} onClick={() => changePage(roomPage + 1)}>Berikutnya</button></div>}
    </section>}
    {!loading && <LodgingBookings sandboxEnabled={sandboxEnabled} />}
  </Shell>;
}
