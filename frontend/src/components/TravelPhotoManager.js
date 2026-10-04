"use client";

import { useEffect, useRef, useState } from "react";
import { apiRequest } from "../lib/api";

export function TravelPhotoManager({ kind, item, onClose, onChanged }) {
  const section = useRef(null);
  useEffect(() => {
    section.current?.scrollIntoView({ behavior: "smooth", block: "start" });
  }, []);
  const [gallery, setGallery] = useState(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [file, setFile] = useState(null);
  const [caption, setCaption] = useState("");
  const [illustration, setIllustration] = useState(false);
  const endpoint = `/dashboard/travel/${kind}/${item.id}/photos`;
  useEffect(() => {
    const controller = new AbortController();
    apiRequest(endpoint, { signal: controller.signal })
      .then(setGallery)
      .catch((error) => {
        if (!controller.signal.aborted) setMessage(error.message);
      });
    return () => controller.abort();
  }, [endpoint]);
  async function upload(event) {
    event.preventDefault();
    setBusy(true);
    setMessage("");
    const payload = new FormData();
    payload.append("photo", file);
    payload.append("caption", caption);
    payload.append("is_illustration", illustration ? "1" : "0");
    payload.append("revision", gallery.revision);
    try {
      setGallery(await apiRequest(endpoint, { method: "POST", body: payload }));
      setCaption("");
      setMessage("Foto berhasil ditambahkan.");
      onChanged();
    } catch (error) {
      setMessage(error.message);
      if (error.status === 409) setGallery(await apiRequest(endpoint));
    } finally {
      setBusy(false);
    }
  }
  async function remove(photo) {
    setBusy(true);
    setMessage("");
    try {
      setGallery(
        await apiRequest(`${endpoint}/${photo.id}`, {
          method: "DELETE",
          body: JSON.stringify({ revision: gallery.revision }),
        })
      );
      setMessage("Foto dihapus dari galeri.");
      onChanged();
    } catch (error) {
      setMessage(error.message);
      if (error.status === 409) setGallery(await apiRequest(endpoint));
    } finally {
      setBusy(false);
    }
  }
  return (
    <section
      ref={section}
      className="travel-editor"
      style={{ scrollMarginTop: 100 }}
    >
      <div className="travel-toolbar">
        <h2>Galeri {item.name}</h2>
        <button
          type="button"
          className="ui-button ui-button-outline"
          disabled={busy}
          onClick={onClose}
        >
          Tutup galeri
        </button>
      </div>
      <p>
        JPG, PNG atau WebP, maksimal 5 MB per foto dan 12 foto. Foto pertama
        menjadi sampul. Foto draft tampil untuk pengunjung setelah katalog
        diterbitkan.
      </p>
      {message && <p role="status">{message}</p>}
      {gallery && (
        <>
          <div className="travel-gallery-management">
            {gallery.data.map((photo, index) => (
              <article key={photo.id}>
                <strong>
                  {index === 0 ? "Sampul · " : ""}
                  {photo.caption}
                </strong>
                <p>{photo.is_illustration ? "Ilustrasi" : "Foto pengelola"}</p>
                <button
                  type="button"
                  className="ui-button ui-button-outline"
                  disabled={busy}
                  onClick={() => remove(photo)}
                >
                  Hapus dari galeri
                </button>
              </article>
            ))}
          </div>
          <form onSubmit={upload}>
            <fieldset
              disabled={busy || gallery.data.length >= 12}
              className="travel-field-grid"
            >
              <label className="travel-field">
                Foto
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  required
                  onChange={(event) => setFile(event.target.files[0] || null)}
                />
              </label>
              <label className="travel-field">
                Keterangan foto
                <input
                  required
                  maxLength={160}
                  value={caption}
                  onChange={(event) => setCaption(event.target.value)}
                  placeholder="Contoh: Area luar / pemandangan"
                />
              </label>
              <label>
                <input
                  type="checkbox"
                  checked={illustration}
                  onChange={(event) => setIllustration(event.target.checked)}
                />
                Foto ilustrasi
              </label>
              <button className="ui-button" disabled={!file} type="submit">
                {busy ? "Mengunggah…" : "Unggah foto"}
              </button>
            </fieldset>
          </form>
        </>
      )}
    </section>
  );
}
