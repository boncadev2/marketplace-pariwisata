"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import {
  Clock,
  MapPin,
  CalendarDays,
  Check,
  Compass,
  Store,
  Utensils,
} from "lucide-react";
import { TravelGallery } from "./TravelGallery";
import { Shell } from "./Shell";
import { PageHeader } from "./PageHeader";
import { PackageAvailability } from "./PackageAvailability";
import { apiRequest } from "../lib/api";
const money = (value) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value || 0);
export function TourPackageDetail({ slug }) {
  const [item, setItem] = useState(null);
  const [error, setError] = useState("");
  useEffect(() => {
    const controller = new AbortController();
    apiRequest(`/tour-packages/${encodeURIComponent(slug)}`, {
      signal: controller.signal,
    })
      .then((response) => setItem(response.data))
      .catch((error) => {
        if (!controller.signal.aborted)
          setError(
            error.status === 404
              ? "Paket belum diterbitkan atau tidak tersedia."
              : "Detail paket belum dapat dimuat."
          );
      });
    return () => controller.abort();
  }, [slug]);
  return (
    <Shell>
      {!item ? (
        <div className="empty-state" role="status">
          {error || "Memuat rincian perjalanan…"}
          <p>
            <Link href="/paket">Kembali ke paket wisata</Link>
          </p>
        </div>
      ) : (
        <>
          <PageHeader
            eyebrow="Paket wisata"
            title={item.name}
            description={item.description}
          />
          <TravelGallery photos={item.photos} name={item.name} />
          <div className="travel-detail-layout">
            <div>
              <section className="travel-detail-section">
                <h2>Perjalanan Anda</h2>
                <div className="travel-facts">
                  <span>
                    <CalendarDays size={18} />
                    {item.duration_days} hari
                  </span>
                  <span>
                    <MapPin size={18} />
                    {item.meeting_point}
                  </span>
                </div>
                <p>
                  {item.minimum_participants}–{item.maximum_participants}{" "}
                  peserta
                </p>
                {item.transportation && (
                  <p>Transportasi: {item.transportation}</p>
                )}
                {item.guide_information && (
                  <p>Pemandu: {item.guide_information}</p>
                )}
              </section>
              <section className="travel-detail-section">
                <h2>Destinasi & rencana perjalanan</h2>
                <p>
                  Jadwal dalam WIB. Lihat kunjungan dan produk yang termasuk
                  sebelum memesan.
                </p>
                {Array.from(
                  new Set(item.items.map((row) => row.day_number))
                ).map((day) => (
                  <div className="travel-day" key={day}>
                    <h3>Hari {day}</h3>
                    {item.items
                      .filter((row) => row.day_number === day)
                      .map((row, index) => {
                        const Icon =
                          row.kind === "umkm"
                            ? Store
                            : row.kind === "kuliner"
                              ? Utensils
                              : Compass;
                        return (
                          <article className="travel-timeline-item" key={index}>
                            <div className="travel-timeline-icon">
                              <Icon size={20} />
                            </div>
                            <div>
                              <span className="travel-status">{row.kind}</span>
                              <h3>{row.title}</h3>
                              <p>
                                <Clock size={15} /> {row.starts_at} WIB ·{" "}
                                {row.duration_minutes} menit
                              </p>
                              {row.description && <p>{row.description}</p>}
                              {row.kind === "umkm" && (
                                <p>
                                  {row.quantity} unit per peserta{" "}
                                  {row.included
                                    ? "didapat dalam paket"
                                    : "tersedia sebagai pilihan tambahan"}
                                </p>
                              )}
                              <span
                                className={
                                  row.included
                                    ? "travel-included"
                                    : "travel-extra"
                                }
                              >
                                {row.included
                                  ? "Termasuk harga paket"
                                  : `Tidak termasuk · estimasi ${money(row.additional_cost)} / orang`}
                              </span>
                            </div>
                          </article>
                        );
                      })}
                  </div>
                ))}
              </section>
              <section className="travel-detail-section">
                <h2>Cakupan biaya</h2>
                <div className="travel-field-grid">
                  <div>
                    <h3>Termasuk</h3>
                    {item.inclusions.length ? (
                      item.inclusions.map((value, index) => (
                        <p key={index}>
                          <Check size={16} /> {value}
                        </p>
                      ))
                    ) : (
                      <p>Aktivitas berlabel termasuk pada itinerary.</p>
                    )}
                  </div>
                  <div>
                    <h3>Tidak termasuk</h3>
                    {item.exclusions.length ? (
                      item.exclusions.map((value, index) => (
                        <p key={index}>{value}</p>
                      ))
                    ) : (
                      <p>Biaya opsional yang tercantum pada itinerary.</p>
                    )}
                  </div>
                </div>
              </section>
            </div>
            <aside className="travel-booking-card">
              <span>Harga dasar paket</span>
              <strong>{money(item.base_price)}</strong>
              <p>
                {item.pricing_mode === "per_person" ? "per orang" : "per paket"}{" "}
                · {item.duration_days} hari
              </p>
              <PackageAvailability key={item.slug} item={item} />
              <p>
                Harga akhir diperiksa saat pemesanan. Biaya opsional dibayar
                terpisah. Produk dan kuliner yang termasuk disediakan
                penyelenggara.
              </p>
            </aside>
          </div>
        </>
      )}
    </Shell>
  );
}
