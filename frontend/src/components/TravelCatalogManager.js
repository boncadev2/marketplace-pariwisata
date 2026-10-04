"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Compass, Map, Plus, Trash2, Save } from "lucide-react";
import { Shell } from "./Shell";
import { PageHeader } from "./PageHeader";
import { TravelPhotoManager } from "./TravelPhotoManager";
import { PackageCalendar } from "./PackageCalendar";
import { apiRequest } from "../lib/api";

const money = (value) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value || 0);
const activity = () => ({
  kind: "destination_id",
  reference: "",
  day_number: 1,
  starts_at: "09:00",
  duration_minutes: 60,
  quantity: 1,
  included: true,
  additional_cost: 0,
  description: "",
});
const blankDestination = {
  name: "",
  partner_id: "",
  region_id: "",
  category_id: "",
  summary: "",
  description: "",
  address: "",
  location_is_demo: false,
  latitude: "",
  longitude: "",
  publication_status: "draft",
};
const blankPackage = {
  pricing_mode: "per_person",
  name: "",
  partner_id: "",
  description: "",
  base_price: 0,
  duration_days: 1,
  meeting_point: "",
  transportation: "",
  guide_information: "",
  minimum_participants: 1,
  maximum_participants: 10,
  status: "draft",
  inclusions: "",
  exclusions: "",
};

export function TravelCatalogManager({ kind }) {
  const destinations = kind === "destinations";
  const [catalog, setCatalog] = useState(null);
  const [options, setOptions] = useState(null);
  const [regions, setRegions] = useState([]);
  const [categories, setCategories] = useState([]);
  const [photoItem, setPhotoItem] = useState(null);
  const [calendarProduct, setCalendarProduct] = useState(null);
  const [form, setForm] = useState(null);
  const [rows, setRows] = useState([activity()]);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    Promise.all([
      apiRequest(`/dashboard/travel/${kind}?page=${page}`, {
        signal: controller.signal,
      }),
      apiRequest("/dashboard/travel/options", { signal: controller.signal }),
      apiRequest("/lookup/regions", { signal: controller.signal }),
      apiRequest("/lookup/categories", { signal: controller.signal }),
    ])
      .then(([list, choices, areas, types]) => {
        setCatalog(list);
        setOptions(choices.data);
        setRegions(areas.data);
        setCategories(types.data);
      })
      .catch((error) => {
        if (!controller.signal.aborted)
          setMessage(
            error.status === 401
              ? "Masuk sebagai admin atau pengelola mitra untuk mengisi katalog."
              : error.message
          );
      });
    return () => controller.abort();
  }, [kind, page, revision]);
  function edit(item) {
    setMessage("");
    if (destinations)
      setForm({
        ...blankDestination,
        ...item,
        latitude: item.latitude ?? "",
        longitude: item.longitude ?? "",
        category_id: item.category_id ?? "",
      });
    else {
      const pack = item.tour_package || {};
      setForm({
        ...blankPackage,
        ...pack,
        id: item.id,
        name: item.name,
        partner_id: item.partner_id,
        base_price: item.base_price,
        status: item.status,
        revision: item.revision,
        inclusions: (pack.inclusions || []).join("\n"),
        exclusions: (pack.exclusions || []).join("\n"),
      });
      setRows(
        (pack.itinerary_items || []).map((row) => {
          const key = row.destination_id
            ? "destination_id"
            : row.culinary_place_id
              ? "culinary_place_id"
              : "umkm_product_id";
          return {
            ...activity(),
            ...row,
            kind: key,
            reference: row[key] || "",
            starts_at: row.starts_at.slice(0, 5),
            included: Boolean(row.included),
          };
        })
      );
    }
  }
  function create() {
    setForm({
      ...(destinations ? blankDestination : blankPackage),
      partner_id: options?.partners[0]?.id || "",
    });
    setRows([activity()]);
    setMessage("");
  }
  const field = (key, label, type = "text", extra = {}) => (
    <label className="travel-field" key={key}>
      {label}
      <input
        type={type}
        value={form[key] ?? ""}
        onChange={(event) => setForm({ ...form, [key]: event.target.value })}
        required
        {...extra}
      />
    </label>
  );
  const textarea = (key, label, required = false) => (
    <label className="travel-field" key={key}>
      {label}
      <textarea
        rows={3}
        value={form[key] || ""}
        required={required}
        onChange={(event) => setForm({ ...form, [key]: event.target.value })}
      />
    </label>
  );
  const updateRow = (index, values) =>
    setRows(
      rows.map((row, position) =>
        position === index ? { ...row, ...values } : row
      )
    );
  async function submit(event) {
    event.preventDefault();
    setBusy(true);
    setMessage("");
    try {
      const payload = destinations
        ? {
            ...form,
            category_id: form.category_id || null,
            latitude: form.latitude === "" ? null : Number(form.latitude),
            longitude: form.longitude === "" ? null : Number(form.longitude),
          }
        : {
            ...form,
            inclusions: form.inclusions
              .split("\n")
              .map((line) => line.trim())
              .filter(Boolean),
            exclusions: form.exclusions
              .split("\n")
              .map((line) => line.trim())
              .filter(Boolean),
            items: rows.map((row) => ({
              [row.kind]: Number(row.reference),
              day_number: Number(row.day_number),
              starts_at: row.starts_at,
              duration_minutes: Number(row.duration_minutes),
              quantity: Number(row.quantity),
              included: row.included,
              additional_cost: row.included ? 0 : Number(row.additional_cost),
              description: row.description || null,
            })),
          };
      await apiRequest(
        `/dashboard/travel/${kind}${form.id ? `/${form.id}` : ""}`,
        { method: form.id ? "PATCH" : "POST", body: JSON.stringify(payload) }
      );
      setMessage("Data berhasil disimpan.");
      setForm(null);
      setRevision((value) => value + 1);
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(false);
    }
  }
  return (
    <Shell>
      <PageHeader
        eyebrow="Pengelolaan katalog"
        title={destinations ? "Destinasi wisata" : "Rancang paket wisata"}
        description={
          destinations
            ? "Lengkapi cerita, alamat, dan lokasi destinasi. Destinasi terbit dapat dipilih pada paket wisata."
            : "Gabungkan satu atau beberapa destinasi, kuliner, dan produk lokal dalam perjalanan yang jelas."
        }
      />
      <div className="travel-toolbar">
        <Link href={destinations ? "/dashboard/paket" : "/dashboard/destinasi"}>
          {destinations ? "Susun paket wisata →" : "Kelola destinasi →"}
        </Link>
        <button
          className="ui-button"
          onClick={create}
          disabled={!options || busy}
        >
          <Plus size={18} />
          {destinations ? "Tambah destinasi" : "Tambah paket"}
        </button>
      </div>
      {message && (
        <p className="travel-message" role="status">
          {message} {!catalog && <Link href="/login">Masuk</Link>}
        </p>
      )}
      {photoItem && (
        <TravelPhotoManager
          key={`${kind}-${photoItem.id}`}
          kind={kind}
          item={photoItem}
          onClose={() => setPhotoItem(null)}
          onChanged={() => setRevision((value) => value + 1)}
        />
      )}
      {calendarProduct && (
        <PackageCalendar
          key={calendarProduct.id}
          product={calendarProduct}
          onClose={() => setCalendarProduct(null)}
        />
      )}
      {form && (
        <form className="travel-editor" onSubmit={submit}>
          <fieldset disabled={busy}>
            <h2>
              {form.id ? "Edit" : "Tambah"}{" "}
              {destinations ? "destinasi" : "paket wisata"}
            </h2>
            <div className="travel-field-grid">
              {field("name", "Nama", "text", { maxLength: 255 })}
              <label className="travel-field">
                Mitra pengelola
                <select
                  required
                  disabled={Boolean(form.id)}
                  value={form.partner_id}
                  onChange={(event) =>
                    setForm({ ...form, partner_id: event.target.value })
                  }
                >
                  <option value="">Pilih mitra</option>
                  {options.partners.map((partner) => (
                    <option key={partner.id} value={partner.id}>
                      {partner.name}
                    </option>
                  ))}
                </select>
              </label>
              {destinations ? (
                <>
                  <label className="travel-field">
                    Wilayah
                    <select
                      required
                      value={form.region_id}
                      onChange={(event) =>
                        setForm({ ...form, region_id: event.target.value })
                      }
                    >
                      <option value="">Pilih wilayah</option>
                      {regions.map((region) => (
                        <option key={region.id} value={region.id}>
                          {region.name}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="travel-field">
                    Kategori (opsional)
                    <select
                      value={form.category_id}
                      onChange={(event) =>
                        setForm({ ...form, category_id: event.target.value })
                      }
                    >
                      <option value="">Tanpa kategori</option>
                      {categories.map((category) => (
                        <option key={category.id} value={category.id}>
                          {category.name}
                        </option>
                      ))}
                    </select>
                  </label>
                  {field("address", "Alamat lengkap")}
                  <label>
                    <input
                      type="checkbox"
                      checked={Boolean(form.location_is_demo)}
                      onChange={(event) =>
                        setForm({
                          ...form,
                          location_is_demo: event.target.checked,
                        })
                      }
                    />
                    Alamat demonstrasi (Google Maps disembunyikan)
                  </label>
                  {field("latitude", "Latitude (opsional)", "number", {
                    required: false,
                    step: "any",
                    min: -90,
                    max: 90,
                  })}
                  {field("longitude", "Longitude (opsional)", "number", {
                    required: false,
                    step: "any",
                    min: -180,
                    max: 180,
                  })}
                </>
              ) : (
                <>
                  {field(
                    "base_price",
                    form.pricing_mode === "per_person"
                      ? "Harga paket per orang (Rp)"
                      : "Harga per paket (Rp)",
                    "number",
                    { min: 1, max: 1000000000 }
                  )}
                  {field("duration_days", "Lama perjalanan (hari)", "number", {
                    min: 1,
                    max: 30,
                  })}
                  {field("meeting_point", "Titik kumpul")}
                  {field("minimum_participants", "Minimal peserta", "number", {
                    min: 1,
                    max: 1000,
                  })}
                  {field("maximum_participants", "Maksimal peserta", "number", {
                    min: form.minimum_participants,
                    max: 1000,
                  })}
                </>
              )}
              <label className="travel-field">
                Status
                <select
                  value={destinations ? form.publication_status : form.status}
                  onChange={(event) =>
                    setForm({
                      ...form,
                      [destinations ? "publication_status" : "status"]:
                        event.target.value,
                    })
                  }
                >
                  <option value="draft">Draft</option>
                  <option value="published">Terbit</option>
                </select>
              </label>
            </div>
            {destinations && textarea("summary", "Ringkasan", true)}
            {textarea("description", "Deskripsi", true)}
            {!destinations && (
              <>
                <div className="travel-field-grid">
                  {textarea("transportation", "Transportasi (opsional)")}
                  {textarea("guide_information", "Pemandu (opsional)")}
                  {textarea(
                    "inclusions",
                    "Termasuk harga — satu rincian per baris"
                  )}
                  {textarea(
                    "exclusions",
                    "Tidak termasuk — satu rincian per baris"
                  )}
                </div>
                <div className="travel-section-heading">
                  <div>
                    <h2>Rencana perjalanan</h2>
                    <p>
                      Minimal satu destinasi. Tambahkan kuliner atau UMKM bila
                      diperlukan. Jadwal memakai WIB.
                    </p>
                  </div>
                  <button
                    type="button"
                    className="ui-button ui-button-outline"
                    onClick={() => setRows([...rows, activity()])}
                  >
                    <Plus size={16} />
                    Tambah aktivitas
                  </button>
                </div>
                {rows.map((row, index) => {
                  const choices =
                    options[
                      row.kind === "destination_id"
                        ? "destinations"
                        : row.kind === "culinary_place_id"
                          ? "culinary"
                          : "umkm"
                    ];
                  return (
                    <section className="travel-activity" key={index}>
                      <div className="travel-section-heading">
                        <h3>Aktivitas {index + 1}</h3>
                        <button
                          type="button"
                          aria-label={`Hapus aktivitas ${index + 1}`}
                          className="ui-button ui-button-outline"
                          disabled={rows.length === 1}
                          onClick={() =>
                            setRows(
                              rows.filter((_, position) => position !== index)
                            )
                          }
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                      <div className="travel-field-grid">
                        <label className="travel-field">
                          Jenis
                          <select
                            value={row.kind}
                            onChange={(event) =>
                              updateRow(index, {
                                kind: event.target.value,
                                reference: "",
                              })
                            }
                          >
                            <option value="destination_id">Destinasi</option>
                            <option value="culinary_place_id">Kuliner</option>
                            <option value="umkm_product_id">Produk UMKM</option>
                          </select>
                        </label>
                        <label className="travel-field">
                          Tempat / produk
                          <select
                            required
                            value={row.reference}
                            onChange={(event) =>
                              updateRow(index, {
                                reference: event.target.value,
                              })
                            }
                          >
                            <option value="">Pilih dari katalog</option>
                            {choices.map((choice) => (
                              <option key={choice.id} value={choice.id}>
                                {choice.name}
                                {choice.unit ? ` / ${choice.unit}` : ""}
                              </option>
                            ))}
                          </select>
                        </label>
                        {[
                          [
                            "day_number",
                            "Hari ke",
                            "number",
                            1,
                            form.duration_days,
                          ],
                          ["starts_at", "Jam mulai (WIB)", "time"],
                          [
                            "duration_minutes",
                            "Durasi (menit)",
                            "number",
                            1,
                            1440,
                          ],
                          ["quantity", "Jumlah per peserta", "number", 1, 1000],
                        ].map(([key, label, type, min, max]) => (
                          <label className="travel-field" key={key}>
                            {label}
                            <input
                              required
                              type={type}
                              min={min}
                              max={max}
                              value={row[key]}
                              onChange={(event) =>
                                updateRow(index, { [key]: event.target.value })
                              }
                            />
                          </label>
                        ))}
                        <label className="travel-field">
                          Cakupan harga
                          <select
                            value={row.included ? "included" : "extra"}
                            onChange={(event) =>
                              updateRow(index, {
                                included: event.target.value === "included",
                                additional_cost: 0,
                              })
                            }
                          >
                            <option value="included">
                              Termasuk harga paket
                            </option>
                            <option value="extra">
                              Tidak termasuk / opsional
                            </option>
                          </select>
                        </label>
                        {!row.included && (
                          <label className="travel-field">
                            Estimasi biaya tambahan per peserta (Rp)
                            <input
                              required
                              type="number"
                              min="0"
                              max="1000000000"
                              value={row.additional_cost}
                              onChange={(event) =>
                                updateRow(index, {
                                  additional_cost: event.target.value,
                                })
                              }
                            />
                          </label>
                        )}
                      </div>
                      <label className="travel-field">
                        Rincian kunjungan / produk yang didapat
                        <textarea
                          rows={2}
                          value={row.description || ""}
                          onChange={(event) =>
                            updateRow(index, {
                              description: event.target.value,
                            })
                          }
                        />
                      </label>
                    </section>
                  );
                })}
                <div className="travel-price-note">
                  <strong>
                    {money(form.base_price)} /{" "}
                    {form.pricing_mode === "per_person" ? "orang" : "paket"} ·{" "}
                    {form.duration_days} hari
                  </strong>
                  <p>
                    Harga paket ditetapkan pengelola. Biaya opsional ditampilkan
                    terpisah dan tidak otomatis ditambahkan ke checkout. Kuliner
                    dan UMKM yang termasuk disediakan oleh penyelenggara paket.
                  </p>
                </div>
              </>
            )}
            <div className="travel-toolbar">
              <button className="ui-button" type="submit">
                <Save size={18} />
                {busy ? "Menyimpan…" : "Simpan"}
              </button>
              <button
                className="ui-button ui-button-outline"
                type="button"
                onClick={() => setForm(null)}
              >
                Tutup formulir
              </button>
            </div>
          </fieldset>
        </form>
      )}
      <div className="travel-list">
        {catalog?.data.map((item) => (
          <article className="travel-list-card" key={item.id}>
            <div className="travel-list-icon">
              {destinations ? <Compass /> : <Map />}
            </div>
            <div>
              <span className="travel-status">
                {(destinations ? item.publication_status : item.status) ===
                "published"
                  ? "Terbit"
                  : "Draft"}
              </span>
              <h2>{item.name}</h2>
              <p>
                {destinations
                  ? item.address || item.summary
                  : `${item.tour_package?.duration_days || "—"} hari · ${money(item.base_price)} / ${item.tour_package?.pricing_mode === "per_person" ? "orang" : "paket"}`}
              </p>
            </div>
            <button
              className="ui-button ui-button-outline"
              disabled={busy}
              onClick={() => edit(item)}
            >
              Edit
            </button>
            <button
              type="button"
              className="ui-button ui-button-outline"
              onClick={() => {
                setPhotoItem(item);
                setForm(null);
              }}
            >
              Foto & galeri
            </button>
            {!destinations && (
              <button
                type="button"
                className="ui-button ui-button-outline"
                disabled={busy}
                onClick={() => {
                  setCalendarProduct(item);
                  setForm(null);
                }}
              >
                Jadwal & kuota
              </button>
            )}
            {(destinations ? item.publication_status : item.status) ===
              "published" && (
              <Link
                href={`/${destinations ? "destinasi" : "paket"}/${item.slug}`}
              >
                Lihat detail →
              </Link>
            )}
          </article>
        ))}
      </div>
      {catalog && !catalog.data.length && (
        <div className="empty-state">
          <h2>Belum ada {destinations ? "destinasi" : "paket"}</h2>
          <p>Mulai dengan tombol tambah untuk melengkapi katalog.</p>
        </div>
      )}
      {catalog && (
        <div className="travel-toolbar">
          <button
            className="ui-button ui-button-outline"
            disabled={page <= 1 || busy}
            onClick={() => setPage(page - 1)}
          >
            Sebelumnya
          </button>
          <span>
            Halaman {page} / {catalog.meta.last_page}
          </span>
          <button
            className="ui-button ui-button-outline"
            disabled={page >= catalog.meta.last_page || busy}
            onClick={() => setPage(page + 1)}
          >
            Berikutnya
          </button>
        </div>
      )}
    </Shell>
  );
}
