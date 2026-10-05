"use client";

import Link from "next/link";
import { useEffect, useState, useTransition, useRef } from "react";
import {
  Check,
  Clock,
  Eye,
  Globe,
  ImageIcon,
  Info,
  Loader2,
  MapPin,
  Palette,
  RefreshCcw,
  Save,
  ShieldAlert,
  SlidersHorizontal,
  Sparkles,
  Upload,
  X,
  ArrowRight,
} from "lucide-react";
import { Shell } from "../../../components/Shell";
import { apiRequest } from "../../../lib/api";
import {
  applyThemeColors,
  defaultSettings,
  notifySettingsUpdated,
  themePresets,
  uploadSettingAsset,
} from "../../../lib/settings";

const heroPresets = [
  {
    label: "Pantai & Laut",
    url: "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1800&q=90",
  },
  {
    label: "Danau & Pegunungan (Default)",
    url: "https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=1800&q=90",
  },
  {
    label: "Desa & Persawahan",
    url: "https://images.unsplash.com/photo-1518548419970-58e3b4079ab2?auto=format&fit=crop&w=1800&q=90",
  },
  {
    label: "Candi & Budaya",
    url: "https://images.unsplash.com/photo-1596402184320-417e7178b2cd?auto=format&fit=crop&w=1800&q=90",
  },
  {
    label: "Air Terjun Tropis",
    url: "https://images.unsplash.com/photo-1432405972618-c60b0225b8f9?auto=format&fit=crop&w=1800&q=90",
  },
];

const ctaUrlOptions = [
  { label: "Katalog Destinasi (/destinasi)", value: "/destinasi" },
  { label: "Paket Wisata (/paket)", value: "/paket" },
  { label: "Penginapan (/penginapan)", value: "/penginapan" },
  { label: "Produk UMKM (/umkm)", value: "/umkm" },
  { label: "Kuliner Daerah (/kuliner)", value: "/kuliner" },
  { label: "Form Pendaftaran Mitra (/daftar-mitra)", value: "/daftar-mitra" },
];

export default function SettingsPage() {
  const [form, setForm] = useState(defaultSettings);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [uploading, setUploading] = useState({ hero: false, logo: false, favicon: false });
  const [status, setStatus] = useState({ type: "", text: "" });
  const [activeTab, setActiveTab] = useState("general");
  const [showConfirmReset, setShowConfirmReset] = useState(false);
  const [, startTransition] = useTransition();

  const heroFileInputRef = useRef(null);
  const logoFileInputRef = useRef(null);
  const faviconFileInputRef = useRef(null);

  useEffect(() => {
    let active = true;
    apiRequest("/dashboard/settings")
      .then((res) => {
        if (!active) return;
        if (res?.data) {
          setForm((prev) => ({ ...prev, ...res.data }));
          applyThemeColors(res.data.primary_color, res.data.accent_color);
        }
      })
      .catch((err) => {
        if (!active) return;
        setStatus({
          type: "error",
          text:
            err.status === 403
              ? "Halaman ini hanya dapat diakses oleh Administrator Platform (super_admin) dengan email terverifikasi."
              : err.status === 401
                ? "Silakan masuk dengan akun Administrator terlebih dahulu."
                : err.message || "Gagal memuat pengaturan.",
        });
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, []);

  function handleFieldChange(e) {
    const { name, value } = e.target;
    setForm((prev) => {
      const next = { ...prev, [name]: value };
      if (name === "primary_color" || name === "accent_color") {
        applyThemeColors(
          name === "primary_color" ? value : prev.primary_color,
          name === "accent_color" ? value : prev.accent_color
        );
      }
      return next;
    });
  }

  function handleThemeSelect(themeKey) {
    const preset = themePresets[themeKey];
    if (!preset) return;
    setForm((prev) => {
      const next = {
        ...prev,
        theme: themeKey,
        ...(themeKey !== "custom"
          ? {
              primary_color: preset.primary_color,
              accent_color: preset.accent_color,
            }
          : {}),
      };
      if (themeKey !== "custom") {
        applyThemeColors(preset.primary_color, preset.accent_color);
      }
      return next;
    });
  }

  async function handleFileUpload(e, type) {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploading((prev) => ({ ...prev, [type]: true }));
    setStatus({ type: "", text: "" });

    try {
      const res = await uploadSettingAsset(file, type);
      if (res?.url) {
        const fieldName =
          type === "hero"
            ? "hero_image_url"
            : type === "logo"
              ? "app_logo_url"
              : "app_favicon_url";

        setForm((prev) => ({ ...prev, [fieldName]: res.url }));
        setStatus({
          type: "success",
          text: `File ${type === "hero" ? "gambar hero" : type === "logo" ? "logo aplikasi" : "favicon"} berhasil diunggah.`,
        });
      }
    } catch (err) {
      setStatus({
        type: "error",
        text: err.message || `Gagal mengunggah file ${type}.`,
      });
    } finally {
      setUploading((prev) => ({ ...prev, [type]: false }));
      if (e.target) e.target.value = "";
    }
  }

  async function handleSave(e) {
    e.preventDefault();
    if (saving) return;
    setSaving(true);
    setStatus({ type: "", text: "" });

    try {
      const res = await apiRequest("/dashboard/settings", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(form),
      });

      if (res?.data) {
        setForm((prev) => ({ ...prev, ...res.data }));
        notifySettingsUpdated(res.data);
      }
      setStatus({
        type: "success",
        text: res?.message || "Pengaturan aplikasi berhasil disimpan.",
      });
    } catch (err) {
      setStatus({
        type: "error",
        text: err.message || "Terjadi kesalahan saat menyimpan pengaturan.",
      });
    } finally {
      setSaving(false);
    }
  }

  async function handleReset() {
    if (saving) return;
    setSaving(true);
    setStatus({ type: "", text: "" });
    setShowConfirmReset(false);

    try {
      const res = await apiRequest("/dashboard/settings/reset", {
        method: "POST",
      });
      if (res?.data) {
        startTransition(() => {
          setForm({ ...defaultSettings, ...res.data });
          notifySettingsUpdated(res.data);
        });
      }
      setStatus({
        type: "success",
        text: "Pengaturan berhasil dikembalikan ke default.",
      });
    } catch (err) {
      setStatus({
        type: "error",
        text: err.message || "Gagal mengembalikan pengaturan ke default.",
      });
    } finally {
      setSaving(false);
    }
  }

  const tabs = [
    { id: "general", label: "Identitas & Branding", icon: SlidersHorizontal },
    { id: "theme", label: "Tema & Warna", icon: Palette },
    { id: "hero", label: "Hero Beranda & CTA", icon: ImageIcon },
    { id: "contact", label: "Alamat & Kontak", icon: MapPin },
    { id: "social", label: "Media Sosial", icon: Globe },
  ];

  return (
    <Shell>
      <div className="space-y-8 pb-16">
        <header className="overflow-hidden rounded-3xl bg-slate-950 px-6 py-8 text-white shadow-xl md:px-10">
          <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <div>
              <p className="text-sm font-bold uppercase tracking-[0.2em] text-emerald-400">
                PENGATURAN SISTEM
              </p>
              <h1 className="mt-2 text-3xl font-extrabold md:text-4xl">
                Pengaturan web & aplikasi
              </h1>
              <p className="mt-3 max-w-2xl text-slate-300">
                Atur nama aplikasi, logo, favicon, tema warna, banner hero beranda, tombol aksi, alamat
                kontak, jam operasional, dan akun media sosial dari satu tempat.
              </p>
            </div>
            <div className="flex flex-wrap gap-3">
              <Link
                href="/"
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-2 rounded-xl border border-white/20 px-4 py-2.5 text-sm font-bold hover:bg-white/10"
              >
                <Eye size={17} /> Pratinjau Beranda
              </Link>
              <button
                type="button"
                onClick={() => setShowConfirmReset(true)}
                disabled={saving || loading}
                className="inline-flex items-center gap-2 rounded-xl border border-rose-400/30 bg-rose-500/10 px-4 py-2.5 text-sm font-bold text-rose-300 hover:bg-rose-500/20 disabled:opacity-50"
              >
                <RefreshCcw size={16} /> Reset Default
              </button>
            </div>
          </div>
        </header>

        {status.text && (
          <div
            role="status"
            aria-live="polite"
            className={`flex items-start gap-3 rounded-2xl border p-4 ${
              status.type === "success"
                ? "border-emerald-200 bg-emerald-50 text-emerald-900"
                : "border-rose-200 bg-rose-50 text-rose-900"
            }`}
          >
            {status.type === "success" ? (
              <Check className="mt-0.5 text-emerald-600 shrink-0" size={20} />
            ) : (
              <Info className="mt-0.5 text-rose-600 shrink-0" size={20} />
            )}
            <div>
              <p className="font-semibold">{status.text}</p>
              {status.type === "error" && (
                <p className="mt-1 text-xs opacity-80">
                  Pastikan Anda masuk dengan email admin yang sudah
                  diverifikasi.
                </p>
              )}
            </div>
          </div>
        )}

        {showConfirmReset && (
          <div className="rounded-2xl border border-rose-300 bg-rose-50 p-6 text-rose-950 shadow-sm">
            <h3 className="text-lg font-bold">
              Konfirmasi Reset ke Pengaturan Awal
            </h3>
            <p className="mt-2 text-sm text-rose-800">
              Semua pengaturan (nama aplikasi, tema, gambar hero, alamat, dan
              media sosial) akan dikembalikan ke nilai default WisataDaerah.
              Tindakan ini tidak dapat dibatalkan.
            </p>
            <div className="mt-4 flex gap-3">
              <button
                type="button"
                onClick={handleReset}
                disabled={saving}
                className="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700"
              >
                {saving ? "Memproses…" : "Ya, Kembalikan ke Default"}
              </button>
              <button
                type="button"
                onClick={() => setShowConfirmReset(false)}
                className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"
              >
                Batal
              </button>
            </div>
          </div>
        )}

        {loading ? (
          <div className="flex items-center justify-center rounded-3xl border border-slate-200 bg-white py-20 text-slate-500">
            <Loader2 className="mr-3 animate-spin text-emerald-600" size={28} />
            <span className="font-medium">Memuat pengaturan aplikasi…</span>
          </div>
        ) : (
          <form onSubmit={handleSave} className="space-y-8">
            {/* Hidden File Inputs for Upload */}
            <input
              ref={heroFileInputRef}
              type="file"
              accept="image/png,image/jpeg,image/webp"
              onChange={(e) => handleFileUpload(e, "hero")}
              className="hidden"
            />
            <input
              ref={logoFileInputRef}
              type="file"
              accept="image/png,image/jpeg,image/webp,image/svg+xml"
              onChange={(e) => handleFileUpload(e, "logo")}
              className="hidden"
            />
            <input
              ref={faviconFileInputRef}
              type="file"
              accept="image/x-icon,image/png,image/svg+xml"
              onChange={(e) => handleFileUpload(e, "favicon")}
              className="hidden"
            />

            {/* Navigation Tabs */}
            <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
              {tabs.map((tab) => {
                const Icon = tab.icon;
                const active = activeTab === tab.id;
                return (
                  <button
                    key={tab.id}
                    type="button"
                    onClick={() => setActiveTab(tab.id)}
                    className={`inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition-all ${
                      active
                        ? "bg-slate-900 text-white shadow-sm"
                        : "text-slate-600 hover:bg-slate-100"
                    }`}
                  >
                    <Icon size={16} />
                    {tab.label}
                  </button>
                );
              })}
            </div>

            {/* TAB 1: GENERAL / IDENTITAS */}
            {activeTab === "general" && (
              <section className="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div>
                  <h2 className="text-xl font-bold text-slate-900">
                    Identitas & Branding Aplikasi
                  </h2>
                  <p className="mt-1 text-sm text-slate-500">
                    Nama, slogan, logo gambar, dan favicon ini akan tampil di seluruh aplikasi, judul tab browser, navbar, dan footer.
                  </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                  <div>
                    <label
                      htmlFor="app_name"
                      className="block text-sm font-bold text-slate-700"
                    >
                      Nama Aplikasi
                    </label>
                    <input
                      id="app_name"
                      name="app_name"
                      type="text"
                      required
                      maxLength={100}
                      value={form.app_name}
                      onChange={handleFieldChange}
                      className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                      placeholder="Contoh: WisataDaerah"
                    />
                    <p className="mt-1.5 text-xs text-slate-500">
                      Tampil sebagai judul web, judul tab browser, dan copyright di footer.
                    </p>
                  </div>

                  <div>
                    <label
                      htmlFor="app_tagline"
                      className="block text-sm font-bold text-slate-700"
                    >
                      Tagline / Slogan
                    </label>
                    <input
                      id="app_tagline"
                      name="app_tagline"
                      type="text"
                      maxLength={255}
                      value={form.app_tagline || ""}
                      onChange={handleFieldChange}
                      className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                      placeholder="Contoh: Jelajahi Keindahan & Pengalaman Lokal"
                    />
                    <p className="mt-1.5 text-xs text-slate-500">
                      Teks deskriptif singkat tentang marketplace pariwisata ini.
                    </p>
                  </div>
                </div>

                {/* Logo & Favicon Uploads */}
                <div className="grid gap-6 border-t border-slate-200 pt-6 md:grid-cols-2">
                  <div>
                    <label
                      htmlFor="app_logo_url"
                      className="block text-sm font-bold text-slate-700"
                    >
                      Logo Gambar Aplikasi (Opsional)
                    </label>
                    <div className="mt-2 flex gap-2">
                      <input
                        id="app_logo_url"
                        name="app_logo_url"
                        type="text"
                        maxLength={2048}
                        value={form.app_logo_url || ""}
                        onChange={handleFieldChange}
                        className="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="https://... atau klik Upload"
                      />
                      <button
                        type="button"
                        onClick={() => logoFileInputRef.current?.click()}
                        disabled={uploading.logo}
                        className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-slate-50 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                      >
                        {uploading.logo ? (
                          <Loader2 size={14} className="animate-spin" />
                        ) : (
                          <Upload size={14} />
                        )}
                        Upload
                      </button>
                      {form.app_logo_url && (
                        <button
                          type="button"
                          onClick={() => setForm((prev) => ({ ...prev, app_logo_url: "" }))}
                          className="rounded-xl border border-rose-200 bg-rose-50 p-2 text-rose-600 hover:bg-rose-100"
                          title="Hapus Logo"
                        >
                          <X size={16} />
                        </button>
                      )}
                    </div>
                    <p className="mt-1.5 text-xs text-slate-500">
                      Format: PNG transparan atau SVG. Jika dikosongkan, logo teks otomatis digunakan.
                    </p>
                  </div>

                  <div>
                    <label
                      htmlFor="app_favicon_url"
                      className="block text-sm font-bold text-slate-700"
                    >
                      Favicon (Ikon Tab Browser)
                    </label>
                    <div className="mt-2 flex gap-2">
                      <input
                        id="app_favicon_url"
                        name="app_favicon_url"
                        type="text"
                        maxLength={2048}
                        value={form.app_favicon_url || ""}
                        onChange={handleFieldChange}
                        className="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="https://... atau klik Upload"
                      />
                      <button
                        type="button"
                        onClick={() => faviconFileInputRef.current?.click()}
                        disabled={uploading.favicon}
                        className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-slate-50 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                      >
                        {uploading.favicon ? (
                          <Loader2 size={14} className="animate-spin" />
                        ) : (
                          <Upload size={14} />
                        )}
                        Upload
                      </button>
                      {form.app_favicon_url && (
                        <button
                          type="button"
                          onClick={() => setForm((prev) => ({ ...prev, app_favicon_url: "" }))}
                          className="rounded-xl border border-rose-200 bg-rose-50 p-2 text-rose-600 hover:bg-rose-100"
                          title="Hapus Favicon"
                        >
                          <X size={16} />
                        </button>
                      )}
                    </div>
                    <p className="mt-1.5 text-xs text-slate-500">
                      Ikon kecil di tab browser (ICO, PNG 32x32, atau SVG).
                    </p>
                  </div>
                </div>

                {/* Preview Logo Brand */}
                <div className="rounded-2xl border border-slate-100 bg-slate-50 p-5">
                  <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                    Pratinjau Tampilan Brand di Navigasi
                  </p>
                  <div className="mt-3 flex items-center gap-3">
                    {form.app_logo_url ? (
                      /* eslint-disable-next-line @next/next/no-img-element */
                      <img
                        src={form.app_logo_url}
                        alt="Logo Preview"
                        className="h-10 max-w-[200px] object-contain rounded-md border border-slate-200 bg-white p-1"
                      />
                    ) : (
                      <>
                        <span
                          className="flex h-10 w-10 items-center justify-center rounded-xl text-white shadow-sm"
                          style={{ backgroundColor: form.primary_color }}
                        >
                          <Sparkles size={20} />
                        </span>
                        <span className="text-xl font-black text-slate-900">
                          {form.app_name || "WisataDaerah"}
                          <span style={{ color: form.accent_color }}>.</span>
                        </span>
                      </>
                    )}
                  </div>
                </div>
              </section>
            )}

            {/* TAB 2: THEME & COLOR */}
            {activeTab === "theme" && (
              <section className="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div>
                  <h2 className="text-xl font-bold text-slate-900">
                    Tema & Skema Warna
                  </h2>
                  <p className="mt-1 text-sm text-slate-500">
                    Pilih skema tema warna bawaan atau sesuaikan sendiri warna
                    primer dan warna aksen tombol.
                  </p>
                </div>

                {/* Preset Themes */}
                <div>
                  <label className="block text-sm font-bold text-slate-700">
                    Pilihan Preset Tema
                  </label>
                  <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {Object.entries(themePresets).map(([key, preset]) => {
                      const isSelected = form.theme === key;
                      return (
                        <button
                          key={key}
                          type="button"
                          onClick={() => handleThemeSelect(key)}
                          className={`flex items-center justify-between rounded-2xl border p-4 text-left transition-all ${
                            isSelected
                              ? "border-slate-900 bg-slate-900 text-white shadow-md ring-2 ring-slate-900/20"
                              : "border-slate-200 bg-white text-slate-900 hover:border-slate-300 hover:bg-slate-50"
                          }`}
                        >
                          <div>
                            <p className="font-bold">{preset.name}</p>
                            <div className="mt-2 flex items-center gap-1.5">
                              <span
                                className="h-4 w-4 rounded-full border border-black/10"
                                style={{
                                  backgroundColor: preset.primary_color,
                                }}
                              />
                              <span
                                className="h-4 w-4 rounded-full border border-black/10"
                                style={{ backgroundColor: preset.accent_color }}
                              />
                              <span className="text-xs opacity-75">
                                {preset.primary_color}
                              </span>
                            </div>
                          </div>
                          {isSelected && <Check size={18} />}
                        </button>
                      );
                    })}
                  </div>
                </div>

                {/* Custom Color Pickers */}
                <div className="grid gap-6 border-t border-slate-200 pt-6 md:grid-cols-2">
                  <div>
                    <label
                      htmlFor="primary_color"
                      className="block text-sm font-bold text-slate-700"
                    >
                      Warna Primer (Brand)
                    </label>
                    <div className="mt-2 flex items-center gap-3">
                      <input
                        id="primary_color_picker"
                        type="color"
                        value={form.primary_color || "#0870ce"}
                        onChange={(e) => {
                          setForm((prev) => ({
                            ...prev,
                            theme: "custom",
                            primary_color: e.target.value,
                          }));
                          applyThemeColors(e.target.value, form.accent_color);
                        }}
                        className="h-11 w-14 cursor-pointer rounded-xl border border-slate-300 p-1"
                      />
                      <input
                        id="primary_color"
                        name="primary_color"
                        type="text"
                        maxLength={7}
                        value={form.primary_color || ""}
                        onChange={handleFieldChange}
                        className="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 font-mono text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="#0870ce"
                      />
                    </div>
                    <p className="mt-1.5 text-xs text-slate-500">
                      Warna utama tombol, link, header, dan elemen navigasi.
                    </p>
                  </div>

                  <div>
                    <label
                      htmlFor="accent_color"
                      className="block text-sm font-bold text-slate-700"
                    >
                      Warna Aksen (Highlight)
                    </label>
                    <div className="mt-2 flex items-center gap-3">
                      <input
                        id="accent_color_picker"
                        type="color"
                        value={form.accent_color || "#ef7f1a"}
                        onChange={(e) => {
                          setForm((prev) => ({
                            ...prev,
                            theme: "custom",
                            accent_color: e.target.value,
                          }));
                          applyThemeColors(form.primary_color, e.target.value);
                        }}
                        className="h-11 w-14 cursor-pointer rounded-xl border border-slate-300 p-1"
                      />
                      <input
                        id="accent_color"
                        name="accent_color"
                        type="text"
                        maxLength={7}
                        value={form.accent_color || ""}
                        onChange={handleFieldChange}
                        className="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 font-mono text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="#ef7f1a"
                      />
                    </div>
                    <p className="mt-1.5 text-xs text-slate-500">
                      Warna aksen highlight judul, badge, dan penawaran khusus.
                    </p>
                  </div>
                </div>

                {/* Live Elements Preview */}
                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                  <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
                    Pratinjau Elemen Tombol & Badge
                  </p>
                  <div className="mt-3 flex flex-wrap items-center gap-4">
                    <button
                      type="button"
                      className="rounded-xl px-5 py-2.5 text-sm font-bold text-white shadow-sm"
                      style={{ backgroundColor: form.primary_color }}
                    >
                      Tombol Utama
                    </button>
                    <button
                      type="button"
                      className="rounded-xl px-5 py-2.5 text-sm font-bold text-white shadow-sm"
                      style={{ backgroundColor: form.accent_color }}
                    >
                      Tombol Aksen
                    </button>
                    <span
                      className="rounded-full px-3.5 py-1 text-xs font-bold"
                      style={{
                        backgroundColor: `${form.primary_color}18`,
                        color: form.primary_color,
                      }}
                    >
                      Badge Primer
                    </span>
                    <span
                      className="rounded-full px-3.5 py-1 text-xs font-bold"
                      style={{
                        backgroundColor: `${form.accent_color}18`,
                        color: form.accent_color,
                      }}
                    >
                      Badge Aksen
                    </span>
                  </div>
                </div>
              </section>
            )}

            {/* TAB 3: HERO SECTION & CTA */}
            {activeTab === "hero" && (
              <section className="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div>
                  <h2 className="text-xl font-bold text-slate-900">
                    Pengaturan Banner Hero Beranda & Tombol Aksi (CTA)
                  </h2>
                  <p className="mt-1 text-sm text-slate-500">
                    Ubah judul, deskripsi, foto latar, dan tombol ajakan bertindak (Call To Action) di beranda utama.
                  </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                  <div className="space-y-4">
                    <div>
                      <label
                        htmlFor="hero_kicker"
                        className="block text-sm font-bold text-slate-700"
                      >
                        Teks Kicker (Label Pembuka Atas)
                      </label>
                      <input
                        id="hero_kicker"
                        name="hero_kicker"
                        type="text"
                        maxLength={100}
                        value={form.hero_kicker || ""}
                        onChange={handleFieldChange}
                        className="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="Contoh: SAATNYA JELAJAHI DAERAH"
                      />
                    </div>

                    <div>
                      <label
                        htmlFor="hero_title"
                        className="block text-sm font-bold text-slate-700"
                      >
                        Judul Hero (Title)
                      </label>
                      <textarea
                        id="hero_title"
                        name="hero_title"
                        rows={3}
                        required
                        maxLength={500}
                        value={form.hero_title}
                        onChange={handleFieldChange}
                        className="mt-1.5 w-full rounded-xl border border-slate-300 p-4 text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="Contoh: Liburan dekat.&#10;Cerita hebat."
                      />
                      <p className="mt-1 text-xs text-slate-500">
                        Tip: Gunakan baris baru (Enter) untuk membagi baris judul secara estetis.
                      </p>
                    </div>

                    <div>
                      <label
                        htmlFor="hero_description"
                        className="block text-sm font-bold text-slate-700"
                      >
                        Deskripsi Hero
                      </label>
                      <textarea
                        id="hero_description"
                        name="hero_description"
                        rows={3}
                        maxLength={1000}
                        value={form.hero_description || ""}
                        onChange={handleFieldChange}
                        className="mt-1.5 w-full rounded-xl border border-slate-300 p-4 text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="Deskripsi singkat yang menggugah wisatawan..."
                      />
                    </div>

                    {/* CTA Settings */}
                    <div className="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                      <div>
                        <label
                          htmlFor="hero_cta_text"
                          className="block text-xs font-bold text-slate-700"
                        >
                          Teks Tombol Aksi (CTA)
                        </label>
                        <input
                          id="hero_cta_text"
                          name="hero_cta_text"
                          type="text"
                          maxLength={100}
                          value={form.hero_cta_text || ""}
                          onChange={handleFieldChange}
                          className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                          placeholder="Mulai petualangan"
                        />
                      </div>

                      <div>
                        <label
                          htmlFor="hero_cta_url"
                          className="block text-xs font-bold text-slate-700"
                        >
                          Tujuan Link Tombol
                        </label>
                        <select
                          id="hero_cta_url"
                          name="hero_cta_url"
                          value={form.hero_cta_url || "/destinasi"}
                          onChange={handleFieldChange}
                          className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900"
                        >
                          {ctaUrlOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                              {opt.label}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </div>

                  <div className="space-y-4">
                    <div>
                      <label
                        htmlFor="hero_image_url"
                        className="block text-sm font-bold text-slate-700"
                      >
                        Foto Latar Belakang Hero
                      </label>
                      <div className="mt-1.5 flex gap-2">
                        <input
                          id="hero_image_url"
                          name="hero_image_url"
                          type="text"
                          maxLength={2048}
                          value={form.hero_image_url || ""}
                          onChange={handleFieldChange}
                          className="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none"
                          placeholder="URL gambar atau klik Upload Foto"
                        />
                        <button
                          type="button"
                          onClick={() => heroFileInputRef.current?.click()}
                          disabled={uploading.hero}
                          className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                          {uploading.hero ? (
                            <Loader2 size={14} className="animate-spin" />
                          ) : (
                            <Upload size={14} />
                          )}
                          Unggah Foto
                        </button>
                      </div>
                      <p className="mt-1.5 text-xs text-slate-500">
                        Anda dapat mengunggah file foto dari laptop/komputer langsung, atau pilih preset di bawah.
                      </p>
                    </div>

                    <div>
                      <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Pilihan Gambar Cepat:
                      </p>
                      <div className="mt-2 flex flex-wrap gap-2">
                        {heroPresets.map((preset) => (
                          <button
                            key={preset.label}
                            type="button"
                            onClick={() =>
                              setForm((prev) => ({
                                ...prev,
                                hero_image_url: preset.url,
                              }))
                            }
                            className={`rounded-xl border px-3 py-1.5 text-xs font-semibold ${
                              form.hero_image_url === preset.url
                                ? "border-emerald-600 bg-emerald-50 text-emerald-900"
                                : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50"
                            }`}
                          >
                            {preset.label}
                          </button>
                        ))}
                      </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                      <div>
                        <label
                          htmlFor="hero_badge_title"
                          className="block text-xs font-bold text-slate-700"
                        >
                          Catatan Pojok (Judul)
                        </label>
                        <input
                          id="hero_badge_title"
                          name="hero_badge_title"
                          type="text"
                          maxLength={150}
                          value={form.hero_badge_title || ""}
                          onChange={handleFieldChange}
                          className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900"
                        />
                      </div>
                      <div>
                        <label
                          htmlFor="hero_badge_subtitle"
                          className="block text-xs font-bold text-slate-700"
                        >
                          Catatan Pojok (Subjudul)
                        </label>
                        <input
                          id="hero_badge_subtitle"
                          name="hero_badge_subtitle"
                          type="text"
                          maxLength={150}
                          value={form.hero_badge_subtitle || ""}
                          onChange={handleFieldChange}
                          className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                {/* Live Mini Hero Preview */}
                <div className="space-y-2 border-t border-slate-200 pt-6">
                  <p className="text-xs font-bold uppercase tracking-wider text-slate-500">
                    Pratinjau Tampilan Hero Beranda
                  </p>
                  <div
                    className="relative overflow-hidden rounded-3xl p-6 text-white md:p-8"
                    style={{
                      minHeight: 260,
                      backgroundColor: "#123e5a",
                      backgroundImage: `url(${form.hero_image_url || defaultSettings.hero_image_url})`,
                      backgroundPosition: "center",
                      backgroundSize: "cover",
                    }}
                  >
                    <div
                      className="absolute inset-0"
                      style={{
                        background:
                          "linear-gradient(90deg, rgba(7, 46, 77, 0.92) 0%, rgba(12, 64, 99, 0.75) 45%, rgba(7, 46, 77, 0.2) 100%)",
                      }}
                    />
                    <div className="relative max-w-xl space-y-3">
                      <span className="inline-flex items-center gap-2 text-xs font-bold tracking-widest uppercase text-emerald-300">
                        <span
                          className="h-2 w-2 rounded-full"
                          style={{ backgroundColor: form.accent_color }}
                        />
                        {form.hero_kicker || "SAATNYA JELAJAHI DAERAH"}
                      </span>
                      <h3 className="whitespace-pre-line text-2xl font-black md:text-3xl">
                        {form.hero_title || "Liburan dekat.\nCerita hebat."}
                      </h3>
                      <p className="text-xs leading-relaxed text-slate-200 md:text-sm">
                        {form.hero_description ||
                          "Temukan tempat baru, nikmati pengalaman lokal, dan buat perjalanan Anda lebih berarti."}
                      </p>
                      <div className="pt-2">
                        <span
                          className="hero-cta-button"
                          style={{
                            "--cta-bg": form.primary_color || "var(--brand, #0870ce)",
                            padding: "8px 10px 8px 18px",
                            fontSize: "13px",
                            cursor: "default",
                          }}
                        >
                          <span className="hero-cta-text">
                            {form.hero_cta_text || "Mulai petualangan"}
                          </span>
                          <span
                            className="hero-cta-icon"
                            style={{ width: 26, height: 26 }}
                          >
                            <ArrowRight size={14} strokeWidth={2.4} />
                          </span>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </section>
            )}

            {/* TAB 4: CONTACT & ADDRESS */}
            {activeTab === "contact" && (
              <section className="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div>
                  <h2 className="text-xl font-bold text-slate-900">
                    Alamat, Jam Operasional & Layanan Pengaduan
                  </h2>
                  <p className="mt-1 text-sm text-slate-500">
                    Informasi kontak resmi, jam layanan pelanggan, dan kontak darurat yang ditampilkan di footer situs.
                  </p>
                </div>

                <div className="space-y-5">
                  <div>
                    <label
                      htmlFor="address"
                      className="block text-sm font-bold text-slate-700"
                    >
                      Alamat Kantor / Pengelola
                    </label>
                    <textarea
                      id="address"
                      name="address"
                      rows={3}
                      maxLength={1000}
                      value={form.address || ""}
                      onChange={handleFieldChange}
                      className="mt-2 w-full rounded-xl border border-slate-300 p-4 text-slate-900 focus:border-emerald-500 focus:outline-none"
                      placeholder="Contoh: Jl. Malioboro No. 56, Yogyakarta 55271"
                    />
                    <p className="mt-1 text-xs text-slate-500">
                      Alamat lengkap yang akan tampil di bagian bawah situs.
                    </p>
                  </div>

                  <div className="grid gap-6 md:grid-cols-2">
                    <div>
                      <label
                        htmlFor="contact_email"
                        className="block text-sm font-bold text-slate-700"
                      >
                        Email Resmi
                      </label>
                      <input
                        id="contact_email"
                        name="contact_email"
                        type="email"
                        maxLength={255}
                        value={form.contact_email || ""}
                        onChange={handleFieldChange}
                        className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="kontak@domainanda.id"
                      />
                    </div>

                    <div>
                      <label
                        htmlFor="contact_phone"
                        className="block text-sm font-bold text-slate-700"
                      >
                        Nomor Telepon / WhatsApp
                      </label>
                      <input
                        id="contact_phone"
                        name="contact_phone"
                        type="text"
                        maxLength={50}
                        value={form.contact_phone || ""}
                        onChange={handleFieldChange}
                        className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="+62 812-3456-7890"
                      />
                    </div>

                    <div>
                      <label
                        htmlFor="contact_hours"
                        className="block text-sm font-bold text-slate-700"
                      >
                        Jam Operasional Pelayanan
                      </label>
                      <input
                        id="contact_hours"
                        name="contact_hours"
                        type="text"
                        maxLength={255}
                        value={form.contact_hours || ""}
                        onChange={handleFieldChange}
                        className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="Senin – Minggu: 08:00 – 21:00 WIB"
                      />
                    </div>

                    <div>
                      <label
                        htmlFor="emergency_phone"
                        className="block text-sm font-bold text-slate-700"
                      >
                        Nomor Layanan Darurat (Emergency Call)
                      </label>
                      <input
                        id="emergency_phone"
                        name="emergency_phone"
                        type="text"
                        maxLength={50}
                        value={form.emergency_phone || ""}
                        onChange={handleFieldChange}
                        className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none"
                        placeholder="112 / +62 811-9988-7766"
                      />
                    </div>
                  </div>
                </div>
              </section>
            )}

            {/* TAB 5: SOCIAL MEDIA */}
            {activeTab === "social" && (
              <section className="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div>
                  <h2 className="text-xl font-bold text-slate-900">
                    Akun & Tautan Media Sosial
                  </h2>
                  <p className="mt-1 text-sm text-slate-500">
                    Tautan ke media sosial resmi aplikasi untuk meningkatkan
                    keterlibatan pengunjung.
                  </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                  <div>
                    <label
                      htmlFor="social_instagram"
                      className="block text-sm font-bold text-slate-700"
                    >
                      Instagram URL
                    </label>
                    <input
                      id="social_instagram"
                      name="social_instagram"
                      type="url"
                      maxLength={255}
                      value={form.social_instagram || ""}
                      onChange={handleFieldChange}
                      className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none"
                      placeholder="https://instagram.com/nama_akun"
                    />
                  </div>

                  <div>
                    <label
                      htmlFor="social_facebook"
                      className="block text-sm font-bold text-slate-700"
                    >
                      Facebook URL
                    </label>
                    <input
                      id="social_facebook"
                      name="social_facebook"
                      type="url"
                      maxLength={255}
                      value={form.social_facebook || ""}
                      onChange={handleFieldChange}
                      className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none"
                      placeholder="https://facebook.com/nama_halaman"
                    />
                  </div>

                  <div>
                    <label
                      htmlFor="social_tiktok"
                      className="block text-sm font-bold text-slate-700"
                    >
                      TikTok URL
                    </label>
                    <input
                      id="social_tiktok"
                      name="social_tiktok"
                      type="url"
                      maxLength={255}
                      value={form.social_tiktok || ""}
                      onChange={handleFieldChange}
                      className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none"
                      placeholder="https://tiktok.com/@nama_akun"
                    />
                  </div>

                  <div>
                    <label
                      htmlFor="social_youtube"
                      className="block text-sm font-bold text-slate-700"
                    >
                      YouTube Channel URL
                    </label>
                    <input
                      id="social_youtube"
                      name="social_youtube"
                      type="url"
                      maxLength={255}
                      value={form.social_youtube || ""}
                      onChange={handleFieldChange}
                      className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none"
                      placeholder="https://youtube.com/@nama_channel"
                    />
                  </div>

                  <div>
                    <label
                      htmlFor="social_twitter"
                      className="block text-sm font-bold text-slate-700"
                    >
                      Twitter / X URL
                    </label>
                    <input
                      id="social_twitter"
                      name="social_twitter"
                      type="url"
                      maxLength={255}
                      value={form.social_twitter || ""}
                      onChange={handleFieldChange}
                      className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none"
                      placeholder="https://x.com/nama_akun"
                    />
                  </div>

                  <div>
                    <label
                      htmlFor="social_whatsapp"
                      className="block text-sm font-bold text-slate-700"
                    >
                      WhatsApp Link / Nomor
                    </label>
                    <input
                      id="social_whatsapp"
                      name="social_whatsapp"
                      type="text"
                      maxLength={255}
                      value={form.social_whatsapp || ""}
                      onChange={handleFieldChange}
                      className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-emerald-500 focus:outline-none"
                      placeholder="https://wa.me/6281234567890"
                    />
                  </div>
                </div>
              </section>
            )}

            {/* Sticky Save Bar */}
            <div className="sticky bottom-6 flex items-center justify-between rounded-2xl border border-slate-800 bg-slate-950 p-4 text-white shadow-2xl">
              <div className="flex items-center gap-3">
                <span className="flex h-3 w-3 rounded-full bg-emerald-400" />
                <span className="text-xs text-slate-300 sm:text-sm">
                  Perubahan akan langsung diterapkan ke seluruh pengguna & tab browser.
                </span>
              </div>
              <button
                type="submit"
                disabled={saving}
                className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-6 py-3 font-bold text-slate-950 shadow-md transition-all hover:bg-emerald-400 disabled:opacity-50"
              >
                {saving ? (
                  <>
                    <Loader2 size={18} className="animate-spin" /> Menyimpan…
                  </>
                ) : (
                  <>
                    <Save size={18} /> Simpan Pengaturan
                  </>
                )}
              </button>
            </div>
          </form>
        )}
      </div>
    </Shell>
  );
}
