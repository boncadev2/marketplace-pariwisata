"use client";

import { useState } from "react";
import { Camera, X } from "lucide-react";

export function TravelGallery({ photos = [], name }) {
  const [selected, setSelected] = useState(null);
  if (!photos.length)
    return (
      <div className="travel-gallery-empty">
        <Camera size={28} />
        <p>Foto {name} belum ditambahkan pengelola.</p>
      </div>
    );
  return (
    <section className="travel-gallery" aria-label={`Galeri ${name}`}>
      <div className="travel-gallery-grid">
        {photos.map((photo) => (
          <button
            type="button"
            key={photo.id}
            onClick={() => setSelected(photo)}
            aria-label={`Lihat foto: ${photo.caption}`}
          >
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={photo.url} alt={photo.caption} loading="lazy" />
            <span>
              {photo.caption}
              {photo.is_illustration ? " · Ilustrasi" : ""}
            </span>
          </button>
        ))}
      </div>
      {selected && (
        <div
          className="travel-photo-overlay"
          role="dialog"
          aria-modal="true"
          aria-label={selected.caption}
          onKeyDown={(event) => {
            if (event.key === "Escape") setSelected(null);
            if (event.key === "Tab") event.preventDefault();
          }}
        >
          <button
            className="ui-button"
            type="button"
            autoFocus
            onClick={() => setSelected(null)}
          >
            <X size={18} />
            Tutup foto
          </button>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={selected.url} alt={selected.caption} />
          <p>
            {selected.caption}
            {selected.is_illustration ? " · Ilustrasi" : ""}
          </p>
        </div>
      )}
    </section>
  );
}
