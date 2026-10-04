"use client";
import Link from "next/link";
import Image from "next/image";
import { useEffect, useState } from "react";
import { PackagePlus, Pencil, Store } from "lucide-react";
import { Shell } from "./Shell";
import { PageHeader, EmptyState } from "./PageHeader";
import { apiRequest } from "../lib/api";

const blank = {
  name: "",
  description: "",
  location: "",
  price: "",
  delivery_available: false,
  shipping_fee: 0,
  origin_postal_code: "",
  weight_grams: "",
  unit: "pcs",
  stock: "0",
  status: "draft",
  partner_id: "",
};
export function UmkmProductManager() {
  const [result, setResult] = useState(null);
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [form, setForm] = useState(null);
  useEffect(() => {
    const controller = new AbortController();
    const timer = setTimeout(() => {
      setLoading(true);
      apiRequest(`/dashboard/umkm-products?page=${page}`, {
        signal: controller.signal,
      })
        .then((data) => {
          if (!controller.signal.aborted) {
            setResult(data);
            setError("");
          }
        })
        .catch((err) => {
          if (!controller.signal.aborted) {
            setError(err.message);
            setResult(null);
            setForm(null);
          }
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 0);
    return () => {
      controller.abort();
      clearTimeout(timer);
    };
  }, [page, revision]);
  function field(event) {
    setForm({
      ...form,
      [event.target.name]:
        event.target.type === "checkbox"
          ? event.target.checked
          : event.target.value,
      ...(event.target.name === "partner_id"
        ? { photo_media_id: null, photo_url: null }
        : {}),
    });
  }
  async function save(event) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    setError("");
    setMessage("");
    try {
      const { slug, revision: productRevision, ...values } = form;
      await apiRequest(`/dashboard/umkm-products${slug ? `/${slug}` : ""}`, {
        method: slug ? "PATCH" : "POST",
        body: JSON.stringify({
          ...values,
          price: Number(values.price),
          origin_postal_code: values.origin_postal_code || null,
          weight_grams: values.weight_grams
            ? Number(values.weight_grams)
            : null,
          shipping_fee: Number(values.shipping_fee ?? 0),
          stock: Number(values.stock),
          partner_id: Number(values.partner_id),
          ...(slug ? { revision: productRevision } : {}),
        }),
      });
      setForm(null);
      setPage(1);
      setMessage("Produk berhasil disimpan.");
      setRevision((value) => value + 1);
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }
  async function uploadPhoto(event) {
    const file = event.target.files?.[0];
    if (!file || !form) return;
    if (!form.partner_id) {
      setError("Pilih mitra penjual sebelum unggah foto.");
      return;
    }
    if (
      !["image/jpeg", "image/png", "image/webp"].includes(file.type) ||
      file.size > 5 * 1024 * 1024
    ) {
      setError("Foto harus JPG, PNG, atau WebP, maksimal 5 MB.");
      event.target.value = "";
      return;
    }
    setBusy(true);
    setError("");
    try {
      const body = new FormData();
      body.append("file", file);
      body.append("partner_id", form.partner_id);
      body.append("alt_text", form.name || "Foto produk UMKM");
      const { data } = await apiRequest("/media", { method: "POST", body });
      setForm((value) => ({
        ...value,
        photo_media_id: data.id,
        photo_url: null,
      }));
      setMessage("Foto terunggah. Simpan produk untuk menerapkan foto.");
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
      event.target.value = "";
    }
  }
  const money = (value) =>
    new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: "IDR",
      maximumFractionDigits: 0,
    }).format(value);
  return (
    <Shell>
      <PageHeader
        eyebrow="Portal mitra"
        title="Produk lokal, dikelola lebih mudah."
        description="Atur informasi produk, harga, dan stok tersedia. Simpan sebagai draft atau tampilkan di katalog."
      />
      <div className="package-toolbar">
        <Link href="/dashboard/umkm">Pesanan UMKM</Link>
        <Link href="/umkm">Lihat katalog</Link>
      </div>
      <div className="umkm-status-toolbar">
        <button
          className="ui-button"
          disabled={loading || busy || !result?.meta.editing_available}
          onClick={() => {
            setError("");
            setMessage("");
            setForm({
              ...blank,
              partner_id: String(result.meta.partners[0]?.id || ""),
            });
          }}
        >
          <PackagePlus size={18} /> Tambah produk
        </button>
        <button
          className="ui-button ui-button-outline"
          disabled={loading || busy}
          onClick={() => {
            setForm(null);
            setRevision((value) => value + 1);
          }}
        >
          Muat ulang produk
        </button>
      </div>
      {message && (
        <p className="umkm-order-notice" role="status">
          {message}
        </p>
      )}
      {error && (
        <p className="empty-state" role="alert">
          {error}
        </p>
      )}
      {form && (
        <form onSubmit={save} className="umkm-product-editor">
          <div>
            <span className="heading-kicker">
              <Store size={16} /> Katalog UMKM
            </span>
            <h2>{form.slug ? "Edit produk" : "Produk baru"}</h2>
            <p>
              Stok adalah jumlah yang masih tersedia, di luar barang yang sudah
              dipesan. Produk baru pada fase ini ditandai sebagai demo.
            </p>
          </div>
          <div className="umkm-photo-editor">
            {form.photo_url && (
              <Image
                src={form.photo_url}
                alt={form.photo_alt || form.name}
                width={600}
                height={450}
                unoptimized
                className="umkm-product-photo"
              />
            )}
            <label>
              Foto produk (JPG, PNG, WebP · maksimal 5 MB)
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                disabled={busy}
                onChange={uploadPhoto}
              />
            </label>
            {form.photo_media_id && (
              <p>
                Foto dipilih.{" "}
                {form.photo_url
                  ? "Foto saat ini ditampilkan di atas."
                  : "Klik Simpan produk untuk menampilkan foto baru."}
              </p>
            )}
            {form.photo_media_id && (
              <button
                type="button"
                className="ui-button ui-button-outline"
                disabled={busy}
                onClick={() =>
                  setForm({ ...form, photo_media_id: null, photo_url: null })
                }
              >
                Lepaskan foto dari produk
              </button>
            )}
          </div>
          <fieldset disabled={busy} className="umkm-editor-fields">
            <label>
              Mitra penjual
              <select
                name="partner_id"
                value={form.partner_id}
                onChange={field}
                required
                disabled={!!form.slug}
              >
                {result.meta.partners.map((partner) => (
                  <option key={partner.id} value={partner.id}>
                    {partner.name}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Nama produk
              <input
                name="name"
                value={form.name}
                onChange={field}
                minLength={2}
                maxLength={255}
                required
              />
            </label>
            <label className="umkm-editor-wide">
              Deskripsi
              <textarea
                name="description"
                value={form.description}
                onChange={field}
                minLength={10}
                maxLength={5000}
                rows={4}
                required
              />
            </label>
            <label className="umkm-editor-wide">
              Lokasi pengambilan
              <input
                name="location"
                value={form.location}
                onChange={field}
                minLength={2}
                maxLength={255}
                required
              />
            </label>
            <label>
              Harga (Rp)
              <input
                type="number"
                name="price"
                value={form.price}
                onChange={field}
                min={1}
                max={1000000000}
                step={1}
                required
              />
            </label>
            <label className="umkm-editor-wide umkm-delivery-toggle">
              <input
                type="checkbox"
                name="delivery_available"
                checked={!!form.delivery_available}
                onChange={field}
              />
              Tawarkan pengiriman produk
            </label>
            {form.delivery_available && (
              <>
                <label>
                  Kode pos asal pengiriman
                  <input
                    name="origin_postal_code"
                    value={form.origin_postal_code || ""}
                    onChange={field}
                    pattern="[0-9]{5}"
                    maxLength={5}
                  />
                </label>
                <label>
                  Berat per produk termasuk kemasan (gram)
                  <input
                    type="number"
                    name="weight_grams"
                    value={form.weight_grams || ""}
                    onChange={field}
                    min={1}
                    max={1000000}
                  />
                  <small>Diperlukan untuk tarif ekspedisi.</small>
                </label>
              </>
            )}
            {form.delivery_available && (
              <label>
                Ongkir tetap per pesanan (Rp)
                <input
                  type="number"
                  name="shipping_fee"
                  value={form.shipping_fee ?? 0}
                  onChange={field}
                  required
                  min={0}
                  max={1000000}
                  step={1}
                />
                <small>
                  Tarif yang disepakati penjual, berlaku per pesanan. Pastikan
                  wilayah tujuan pembeli dapat dilayani.
                </small>
              </label>
            )}
            <label>
              Satuan
              <input
                name="unit"
                value={form.unit}
                onChange={field}
                maxLength={50}
                required
              />
            </label>
            <label>
              Stok tersedia
              <input
                type="number"
                name="stock"
                value={form.stock}
                onChange={field}
                min={0}
                max={1000000}
                step={1}
                required
              />
            </label>
            <label>
              Status katalog
              <select name="status" value={form.status} onChange={field}>
                <option value="draft">Draft — belum ditampilkan</option>
                <option value="published">Publik — tampil di katalog</option>
              </select>
            </label>
          </fieldset>
          <div className="umkm-status-toolbar">
            <button className="ui-button" disabled={busy}>
              {busy ? "Menyimpan…" : "Simpan produk"}
            </button>
            <button
              type="button"
              className="ui-button ui-button-outline"
              disabled={busy}
              onClick={() => setForm(null)}
            >
              Tutup editor
            </button>
          </div>
        </form>
      )}
      {loading ? (
        <p role="status" className="empty-state">
          Memuat produk…
        </p>
      ) : (
        result && (
          <>
            {!result.meta.editing_available && (
              <p className="umkm-order-notice">
                Pengeditan produk masih tersedia pada simulasi lokal.
              </p>
            )}
            {!result.data.length ? (
              <EmptyState
                title="Katalog mitra masih kosong"
                description="Tambahkan produk pertama, lalu siapkan informasi untuk pembeli."
              />
            ) : (
              <div className="umkm-orders">
                {result.data.map((item) => (
                  <article key={item.slug} className="umkm-order-item">
                    <div>
                      <span className="umkm-order-notice">
                        {item.status === "published" ? "Publik" : "Draft"}
                        {item.is_demo ? " · Demo" : ""}
                      </span>
                      <h2>{item.name}</h2>
                      {item.photo_url && (
                        <Image
                          src={item.photo_url}
                          alt={item.photo_alt || item.name}
                          width={600}
                          height={450}
                          unoptimized
                          className="umkm-product-photo"
                        />
                      )}
                      <p>{item.description}</p>
                      <p>{item.location}</p>
                      <strong>
                        {money(item.price)} / {item.unit}
                      </strong>
                      <p>Stok tersedia: {item.stock}</p>
                    </div>
                    <button
                      className="ui-button ui-button-outline"
                      disabled={busy || !result.meta.editing_available}
                      onClick={() => {
                        setForm({ ...item });
                        setError("");
                        setMessage("");
                      }}
                    >
                      <Pencil size={16} /> Edit {item.name}
                    </button>
                  </article>
                ))}
              </div>
            )}
            {result.meta.last_page > 1 && (
              <div className="catalog-pagination">
                <button
                  className="ui-button ui-button-outline"
                  disabled={page === 1 || busy}
                  onClick={() => {
                    setForm(null);
                    setPage(page - 1);
                  }}
                >
                  Sebelumnya
                </button>
                <span>Halaman {page}</span>
                <button
                  className="ui-button ui-button-outline"
                  disabled={page === result.meta.last_page || busy}
                  onClick={() => {
                    setForm(null);
                    setPage(page + 1);
                  }}
                >
                  Berikutnya
                </button>
              </div>
            )}
          </>
        )
      )}
    </Shell>
  );
}
