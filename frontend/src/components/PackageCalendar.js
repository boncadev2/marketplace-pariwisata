"use client";
import { useState } from "react";
import { apiRequest } from "../lib/api";
const tomorrow = () =>
  new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Jakarta",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date(Date.now() + 86400000));
export function PackageCalendar({ product, onClose }) {
  const [start, setStart] = useState(tomorrow);
  const [end, setEnd] = useState(tomorrow);
  const [capacity, setCapacity] = useState(10);
  const [closed, setClosed] = useState(false);
  const [snapshot, setSnapshot] = useState(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  async function load() {
    setBusy(true);
    setMessage("");
    setSnapshot(null);
    try {
      const result = await apiRequest(
        `/dashboard/packages/${product.id}/calendar?${new URLSearchParams({ start_date: start, end_date: end })}`
      );
      setSnapshot(result.data);
      const first = result.data.days[0];
      if (
        first.capacity !== null &&
        result.data.days.every(
          (day) =>
            day.capacity === first.capacity && day.is_closed === first.is_closed
        )
      ) {
        setCapacity(first.capacity);
        setClosed(first.is_closed);
      } else {
        setCapacity(10);
        setClosed(false);
        setMessage(
          "Pilih kapasitas dan status yang akan diterapkan pada seluruh tanggal dalam rentang ini."
        );
      }
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }
  async function save(event) {
    event.preventDefault();
    setBusy(true);
    setMessage("");
    try {
      const result = await apiRequest(
        `/dashboard/packages/${product.id}/calendar`,
        {
          method: "PATCH",
          body: JSON.stringify({
            start_date: start,
            end_date: end,
            capacity: Number(capacity),
            is_closed: closed,
            revision: snapshot.revision,
          }),
        }
      );
      setSnapshot(result.data);
      setMessage("Jadwal dan kuota keberangkatan berhasil disimpan.");
    } catch (error) {
      setMessage(error.message);
      if (error.status === 409) setSnapshot(null);
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="travel-editor" aria-label="Kalender keberangkatan">
      <h2>Jadwal & kuota · {product.name}</h2>
      <p>
        Setiap tanggal adalah satu keberangkatan. Kuota dihitung per peserta.
        Menutup tanggal menghentikan pesanan baru; pesanan yang sudah ada tetap
        dipertahankan.
      </p>
      <form onSubmit={save}>
        <fieldset disabled={busy}>
          <div className="travel-field-grid">
            <label className="travel-field">
              Dari tanggal
              <input
                type="date"
                required
                value={start}
                onChange={(event) => {
                  setStart(event.target.value);
                  setSnapshot(null);
                }}
              />
            </label>
            <label className="travel-field">
              Sampai tanggal
              <input
                type="date"
                required
                min={start}
                value={end}
                onChange={(event) => {
                  setEnd(event.target.value);
                  setSnapshot(null);
                }}
              />
            </label>
          </div>
          <button
            type="button"
            className="ui-button ui-button-outline"
            onClick={load}
          >
            {busy ? "Memuat…" : "Tampilkan kalender"}
          </button>
          {snapshot && (
            <>
              <div className="travel-field-grid" style={{ marginTop: 20 }}>
                <label className="travel-field">
                  Kapasitas per tanggal
                  <input
                    required
                    type="number"
                    min="0"
                    max="1000000"
                    value={capacity}
                    onChange={(event) => setCapacity(event.target.value)}
                  />
                </label>
                <label className="travel-field">
                  Pemesanan
                  <select
                    value={closed ? "closed" : "open"}
                    onChange={(event) =>
                      setClosed(event.target.value === "closed")
                    }
                  >
                    <option value="open">Buka pemesanan</option>
                    <option value="closed">Tutup pemesanan</option>
                  </select>
                </label>
              </div>
              <div className="package-calendar-table">
                <table>
                  <thead>
                    <tr>
                      <th>Tanggal</th>
                      <th>Kapasitas</th>
                      <th>Ditahan</th>
                      <th>Dibayar</th>
                      <th>Tersedia</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {snapshot.days.map((day) => (
                      <tr key={day.date}>
                        <td>{day.date}</td>
                        <td>{day.capacity ?? "Belum diatur"}</td>
                        <td>{day.held}</td>
                        <td>{day.confirmed}</td>
                        <td>{day.available}</td>
                        <td>
                          {day.capacity === null
                            ? "Belum tersedia"
                            : day.is_closed
                              ? "Ditutup"
                              : "Dibuka"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <button type="submit" className="ui-button">
                {busy ? "Menyimpan…" : "Simpan jadwal & kuota"}
              </button>
            </>
          )}
        </fieldset>
      </form>
      {message && (
        <p className="travel-message" role="status">
          {message}
        </p>
      )}
      <button
        type="button"
        className="ui-button ui-button-outline"
        disabled={busy}
        onClick={onClose}
      >
        Tutup kalender
      </button>
    </section>
  );
}
