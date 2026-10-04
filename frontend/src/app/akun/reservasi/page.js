"use client";
import Link from "next/link";
import {useEffect,useState} from "react";
import {BedDouble,Utensils,ArrowLeft} from "lucide-react";
import {Shell} from "../../../components/Shell";
import {PageHeader,EmptyState} from "../../../components/PageHeader";
import {LodgingBookings} from "../../../components/LodgingBookings";
import {CulinaryBookings} from "../../../components/CulinaryBookings";
import {apiRequest} from "../../../lib/api";
export default function Page(){
 const [profile,setProfile]=useState(null),[loading,setLoading]=useState(true),[message,setMessage]=useState(""),[sandbox,setSandbox]=useState(false);
 useEffect(()=>{const c=new AbortController();Promise.allSettled([apiRequest("/me",{signal:c.signal}),apiRequest("/lodging/rooms",{signal:c.signal})]).then(([account,catalog])=>{if(c.signal.aborted)return;if(account.status==="fulfilled")setProfile(account.value.data);else if(account.reason.status!==401)setMessage("Akun belum dapat dimuat. Coba muat ulang halaman.");if(catalog.status==="fulfilled")setSandbox(catalog.value.meta.sandbox_reservations_enabled);setLoading(false);});return()=>c.abort();},[]);
 return <Shell><PageHeader eyebrow="Reservasi saya" title="Rencana menginap dan makan, dalam satu tempat" description="Lihat jadwal, rincian harga, dan status reservasi yang dibuat melalui akun Anda."/><Link href="/akun" className="ui-button ui-button-outline"><ArrowLeft size={16}/>Kembali ke akun</Link><p role="status" aria-live="polite">{message||(loading?"Memuat akun Anda…":"")}</p>{!loading&&!profile&&!message&&<EmptyState title="Masuk untuk melihat reservasi" description="Reservasi penginapan dan kuliner hanya ditampilkan kepada pemilik akun." href="/login" label="Masuk ke akun"/>}{profile&&<><div className="account-reservation-note">Pembayaran penginapan dan kuliner tersedia melalui Midtrans sandbox. Periksa status pembayaran setelah menyelesaikan pembayaran uji.</div><nav className="account-reservation-shortcuts" aria-label="Jenis reservasi"><a href="#reservasi-saya"><BedDouble size={22}/><div><strong>Penginapan</strong><span>Jadwal menginap, kamar, dan tamu</span></div></a><a href="#reservasi-kuliner"><Utensils size={22}/><div><strong>Kuliner</strong><span>Jadwal makan, paket, dan peserta</span></div></a></nav><section className="account-reservation-panel"><LodgingBookings sandboxEnabled={sandbox}/><Link href="/penginapan" className="ui-button ui-button-outline"><BedDouble size={16}/>Cari penginapan</Link></section><section className="account-reservation-panel"><CulinaryBookings sandboxEnabled={sandbox}/><Link href="/kuliner" className="ui-button ui-button-outline"><Utensils size={16}/>Cari rumah makan</Link></section></>}</Shell>;
}
