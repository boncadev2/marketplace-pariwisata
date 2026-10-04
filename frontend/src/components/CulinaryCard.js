"use client";
import Image from "next/image";
import Link from "next/link";
import { useState } from "react";
import { Utensils, MapPin, ArrowRight } from "lucide-react";

export function CulinaryPhoto({place}) {
  const [failed, setFailed] = useState(false);
  return <div className="culinary-photo">{place.image_url && !failed ? <Image src={place.image_url} alt={`Rumah makan ${place.name}${place.photos_are_illustrations ? " — foto ilustrasi" : ""}`} width={1100} height={650} unoptimized onError={() => setFailed(true)} /> : <div className="culinary-photo-empty"><Utensils size={46} /><span>Foto rumah makan belum tersedia</span></div>}{place.photos_are_illustrations && <span className="lodging-photo-label">Foto ilustrasi</span>}</div>;
}
export function CulinaryCard({place}) {
  const price = place.starting_price === null ? null : new Intl.NumberFormat("id-ID", {style: "currency", currency: "IDR", maximumFractionDigits: 2}).format(Number(place.starting_price));
  return <article className="lodging-product-card culinary-product-card"><CulinaryPhoto place={place} /><div className="lodging-card-content"><span className="lodging-type"><Utensils size={16} /> Rumah makan</span><h3><Link href={`/kuliner/${place.id}`}>{place.name}</Link></h3><p className="lodging-card-description">{place.description || "Lihat detail rumah makan dan jadwal makan yang tersedia."}</p><p className="culinary-location"><MapPin size={16} /> {place.location_is_demo ? "Lokasi demonstrasi" : place.location || "Alamat belum ditambahkan"}</p><div className="lodging-card-price"><div><span>{price ? "Paket mulai dari" : "Tarif paket"}</span><strong>{price || "Belum tersedia"}</strong><small>per peserta</small></div><Link href={`/kuliner/${place.id}`} className="ui-button" aria-label={`Lihat detail ${place.name}`}>Lihat detail <ArrowRight size={16} /></Link></div></div></article>;
}
