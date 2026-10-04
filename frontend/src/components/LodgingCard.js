"use client";
import Image from "next/image";
import Link from "next/link";
import { useState } from "react";
import { BedDouble, Users, ArrowRight, Camera } from "lucide-react";

export function LodgingPhoto({ src, label, name }) {
  const [failed, setFailed] = useState(false);
  return (
    <div className="lodging-card-photo">
      {src && !failed ? (
        <Image
          src={src}
          alt={`${label} ${name}`}
          width={700}
          height={520}
          unoptimized
          onError={() => setFailed(true)}
        />
      ) : (
        <div className="lodging-photo-empty">
          <Camera size={28} />
          <span>Foto belum tersedia</span>
        </div>
      )}
      <span className="lodging-photo-label">{label}</span>
    </div>
  );
}
export function LodgingCard({ room, selected, disabled, onSelect, href, actionLabel = "Pilih kamar", selectedLabel = "Dipilih" }) {
  const price =
    room.starting_price === null || room.starting_price === undefined
      ? null
      : new Intl.NumberFormat("id-ID", {
          style: "currency",
          currency: "IDR",
          maximumFractionDigits: 2,
        }).format(Number(room.starting_price));
  return (
    <article
      className={`lodging-product-card${selected ? " lodging-product-selected" : ""}`}
    >
      <div className="lodging-photo-pair">
        <LodgingPhoto
          key={room.exterior_image_url || "exterior"}
          src={room.exterior_image_url}
          label="Tampak luar"
          name={room.name}
        />
        <LodgingPhoto
          key={room.interior_image_url || "interior"}
          src={room.interior_image_url}
          label="Interior kamar"
          name={room.name}
        />
      </div>
      <div className="lodging-card-content">
        <span className="lodging-type">
          <BedDouble size={15} /> Penginapan
          {room.photos_are_illustrations ? " · Foto ilustrasi" : ""}
        </span>
        <h3>{href ? <Link href={href}>{room.name}</Link> : room.name}</h3>
        <p className="lodging-card-description">
          {room.description ||
            "Pilih tanggal menginap untuk memeriksa kamar dan tarif."}
        </p>
        <p className="lodging-capacity">
          <Users size={16} /> Hingga {room.capacity} tamu per kamar
        </p>
        <div className="lodging-card-price">
          <div>
            <span>{price ? "Mulai dari" : "Tarif"}</span>
            <strong>{price || "Belum tersedia"}</strong>
            <small>per kamar / malam</small>
          </div>
          {href ? <Link href={href} className="ui-button" aria-label={`${actionLabel} ${room.name}`}>
            {actionLabel}<ArrowRight size={16} />
          </Link> : <button
            className="ui-button"
            disabled={disabled}
            onClick={onSelect}
            aria-label={`${actionLabel} ${room.name}`}
          >
            {selected ? selectedLabel : actionLabel}
            <ArrowRight size={16} />
          </button>}
        </div>
      </div>
    </article>
  );
}
