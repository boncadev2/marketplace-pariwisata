"use client";

import Link from "next/link";
import { useEffect, useState, useMemo } from "react";
import {
  ArrowLeft,
  Check,
  Edit2,
  FolderTree,
  Loader2,
  MapPin,
  Plus,
  RefreshCw,
  Search,
  Tag,
  Trash2,
  X,
  AlertCircle,
} from "lucide-react";
import { Shell } from "../../../components/Shell";
import { PageHeader } from "../../../components/PageHeader";
import { apiRequest } from "../../../lib/api";

const regionTypeLabels = {
  regency: { label: "Kabupaten", color: "bg-blue-50 text-blue-700 border-blue-200" },
  city: { label: "Kota", color: "bg-indigo-50 text-indigo-700 border-indigo-200" },
  district: { label: "Kecamatan", color: "bg-purple-50 text-purple-700 border-purple-200" },
  village: { label: "Desa / Kelurahan", color: "bg-emerald-50 text-emerald-700 border-emerald-200" },
};

export default function MasterDataPage() {
  const [activeTab, setActiveTab] = useState("categories");
  const [categories, setCategories] = useState([]);
  const [regions, setRegions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  // Category search & modal
  const [catSearch, setCatSearch] = useState("");
  const [catModal, setCatModal] = useState(null); // null | { mode: 'create'|'edit', item?: any }
  const [catForm, setCatForm] = useState({ name: "", slug: "", is_active: true });

  // Region search, filter & modal
  const [regSearch, setRegSearch] = useState("");
  const [regTypeFilter, setRegTypeFilter] = useState("all");
  const [regModal, setRegModal] = useState(null); // null | { mode: 'create'|'edit', item?: any }
  const [regForm, setRegForm] = useState({
    name: "",
    code: "",
    type: "regency",
    parent_id: "",
    is_active: true,
  });

  // Delete confirmation modal
  const [deleteTarget, setDeleteTarget] = useState(null); // null | { type: 'category'|'region', item: any }
  const [revision, setRevision] = useState(0);

  useEffect(() => {
    let active = true;
    Promise.all([
      apiRequest("/dashboard/master-data/categories"),
      apiRequest("/dashboard/master-data/regions"),
    ])
      .then(([catRes, regRes]) => {
        if (!active) return;
        setCategories(catRes.data || []);
        setRegions(regRes.data || []);
        setLoading(false);
      })
      .catch((err) => {
        if (!active) return;
        setError(
          err.status === 403
            ? "Halaman ini hanya dapat diakses oleh Administrator."
            : err.message || "Gagal memuat master data."
        );
        setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [revision]);

  // --- Category Handlers ---
  function openCategoryModal(item = null) {
    if (item) {
      setCatModal({ mode: "edit", item });
      setCatForm({
        name: item.name || "",
        slug: item.slug || "",
        is_active: Boolean(item.is_active),
      });
    } else {
      setCatModal({ mode: "create" });
      setCatForm({ name: "", slug: "", is_active: true });
    }
    setError("");
    setMessage("");
  }

  async function handleCategorySubmit(e) {
    e.preventDefault();
    if (busy) return;
    setBusy(true);
    setError("");
    setMessage("");
    try {
      const isEdit = catModal?.mode === "edit";
      const url = isEdit
        ? `/dashboard/master-data/categories/${catModal.item.id}`
        : "/dashboard/master-data/categories";
      const method = isEdit ? "PUT" : "POST";

      const res = await apiRequest(url, {
        method,
        body: JSON.stringify(catForm),
      });

      setMessage(res.message || "Kategori berhasil disimpan.");
      setCatModal(null);
      setRevision((r) => r + 1);
    } catch (err) {
      setError(err.message || "Gagal menyimpan kategori.");
    } finally {
      setBusy(false);
    }
  }

  // --- Region Handlers ---
  function openRegionModal(item = null) {
    if (item) {
      setRegModal({ mode: "edit", item });
      setRegForm({
        name: item.name || "",
        code: item.code || "",
        type: item.type || "regency",
        parent_id: item.parent_id ? String(item.parent_id) : "",
        is_active: Boolean(item.is_active),
      });
    } else {
      setRegModal({ mode: "create" });
      setRegForm({
        name: "",
        code: "",
        type: "regency",
        parent_id: "",
        is_active: true,
      });
    }
    setError("");
    setMessage("");
  }

  async function handleRegionSubmit(e) {
    e.preventDefault();
    if (busy) return;
    setBusy(true);
    setError("");
    setMessage("");
    try {
      const isEdit = regModal?.mode === "edit";
      const url = isEdit
        ? `/dashboard/master-data/regions/${regModal.item.id}`
        : "/dashboard/master-data/regions";
      const method = isEdit ? "PUT" : "POST";

      const payload = {
        ...regForm,
        parent_id: regForm.parent_id ? Number(regForm.parent_id) : null,
      };

      const res = await apiRequest(url, {
        method,
        body: JSON.stringify(payload),
      });

      setMessage(res.message || "Wilayah berhasil disimpan.");
      setRegModal(null);
      setRevision((r) => r + 1);
    } catch (err) {
      setError(err.message || "Gagal menyimpan wilayah.");
    } finally {
      setBusy(false);
    }
  }

  // --- Delete Handler ---
  async function confirmDelete() {
    if (!deleteTarget || busy) return;
    setBusy(true);
    setError("");
    setMessage("");
    try {
      const isCategory = deleteTarget.type === "category";
      const url = isCategory
        ? `/dashboard/master-data/categories/${deleteTarget.item.id}`
        : `/dashboard/master-data/regions/${deleteTarget.item.id}`;

      const res = await apiRequest(url, { method: "DELETE" });
      setMessage(res.message || "Data berhasil dihapus.");
      setDeleteTarget(null);
      setRevision((r) => r + 1);
    } catch (err) {
      setError(err.message || "Gagal menghapus data.");
    } finally {
      setBusy(false);
    }
  }

  // Filtered lists
  const filteredCategories = useMemo(() => {
    return categories.filter((c) =>
      c.name.toLowerCase().includes(catSearch.toLowerCase()) ||
      c.slug.toLowerCase().includes(catSearch.toLowerCase())
    );
  }, [categories, catSearch]);

  const filteredRegions = useMemo(() => {
    return regions.filter((r) => {
      const matchesSearch =
        r.name.toLowerCase().includes(regSearch.toLowerCase()) ||
        r.code.toLowerCase().includes(regSearch.toLowerCase());
      const matchesType = regTypeFilter === "all" || r.type === regTypeFilter;
      return matchesSearch && matchesType;
    });
  }, [regions, regSearch, regTypeFilter]);

  // Potential parent regions (cannot be self in edit mode)
  const availableParents = useMemo(() => {
    return regions.filter((r) => {
      if (regModal?.mode === "edit" && r.id === regModal.item.id) return false;
      return r.type === "regency" || r.type === "city" || r.type === "district";
    });
  }, [regions, regModal]);

  return (
    <Shell>
      <div className="operations-form space-y-6 max-w-6xl mx-auto px-4 py-8">
        <PageHeader
          eyebrow="Master Data"
          title="Kategori & Wilayah"
          description="Kelola master data kategori destinasi dan wilayah administratif untuk katalog, pencarian, dan pendaftaran mitra."
          compact
        />

        {/* Toolbar navigation */}
        <div className="flex flex-wrap items-center justify-between gap-4">
          <Link href="/dashboard" className="ui-button ui-button-outline">
            <ArrowLeft size={16} /> Kembali ke dashboard
          </Link>
          <button
            type="button"
            className="ui-button ui-button-outline"
            onClick={() => {
              setLoading(true);
              setRevision((r) => r + 1);
            }}
            disabled={loading}
          >
            <RefreshCw size={15} className={loading ? "animate-spin" : ""} /> Segarkan data
          </button>
        </div>

        {/* Toast / Alert Messages */}
        {message && (
          <div
            role="status"
            className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 text-sm flex items-center justify-between"
          >
            <div className="flex items-center gap-2">
              <Check size={18} className="text-emerald-600 flex-shrink-0" />
              <span>{message}</span>
            </div>
            <button
              type="button"
              onClick={() => setMessage("")}
              className="text-emerald-600 hover:text-emerald-800 p-1"
            >
              <X size={16} />
            </button>
          </div>
        )}

        {error && (
          <div
            role="alert"
            className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800 text-sm flex items-center justify-between"
          >
            <div className="flex items-center gap-2">
              <AlertCircle size={18} className="text-rose-600 flex-shrink-0" />
              <span>{error}</span>
            </div>
            <button
              type="button"
              onClick={() => setError("")}
              className="text-rose-600 hover:text-rose-800 p-1"
            >
              <X size={16} />
            </button>
          </div>
        )}

        {/* Main Tab Navigation */}
        <div className="border-b border-slate-200 flex gap-4">
          <button
            type="button"
            className={`pb-3 font-semibold text-sm sm:text-base flex items-center gap-2 border-b-2 transition-colors ${
              activeTab === "categories"
                ? "border-blue-600 text-blue-600"
                : "border-transparent text-slate-500 hover:text-slate-800"
            }`}
            onClick={() => setActiveTab("categories")}
          >
            <Tag size={18} />
            <span>Kategori Destinasi</span>
            <span className="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-bold">
              {categories.length}
            </span>
          </button>
          <button
            type="button"
            className={`pb-3 font-semibold text-sm sm:text-base flex items-center gap-2 border-b-2 transition-colors ${
              activeTab === "regions"
                ? "border-blue-600 text-blue-600"
                : "border-transparent text-slate-500 hover:text-slate-800"
            }`}
            onClick={() => setActiveTab("regions")}
          >
            <MapPin size={18} />
            <span>Wilayah Administratif</span>
            <span className="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-bold">
              {regions.length}
            </span>
          </button>
        </div>

        {/* ===================== TAB 1: KATEGORI ===================== */}
        {activeTab === "categories" && (
          <div className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-3 justify-between items-stretch sm:items-center">
              <div className="relative flex-1 max-w-md">
                <Search
                  size={18}
                  className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                />
                <input
                  type="search"
                  value={catSearch}
                  onChange={(e) => setCatSearch(e.target.value)}
                  placeholder="Cari nama atau slug kategori…"
                  className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <button
                type="button"
                className="ui-button flex items-center justify-center gap-2"
                onClick={() => openCategoryModal()}
              >
                <Plus size={16} /> Tambah Kategori
              </button>
            </div>

            {loading ? (
              <div className="text-center py-12 bg-white rounded-2xl border border-slate-200">
                <Loader2 size={28} className="animate-spin text-blue-600 mx-auto mb-2" />
                <p className="text-sm text-slate-500">Memuat data kategori…</p>
              </div>
            ) : filteredCategories.length === 0 ? (
              <div className="text-center py-12 bg-white rounded-2xl border border-slate-200">
                <Tag size={36} className="text-slate-300 mx-auto mb-2" />
                <h3 className="font-bold text-slate-700">Belum ada kategori</h3>
                <p className="text-sm text-slate-500 mt-1">
                  {catSearch ? "Tidak ada kategori yang cocok dengan pencarian." : "Tambahkan kategori baru untuk mulai mengelompokkan destinasi."}
                </p>
              </div>
            ) : (
              <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                      <tr>
                        <th className="py-3.5 px-4">Nama Kategori</th>
                        <th className="py-3.5 px-4">Slug URL</th>
                        <th className="py-3.5 px-4 text-center">Destinasi</th>
                        <th className="py-3.5 px-4 text-center">Status</th>
                        <th className="py-3.5 px-4 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {filteredCategories.map((cat) => (
                        <tr key={cat.id} className="hover:bg-slate-50 transition-colors">
                          <td className="py-3.5 px-4 font-semibold text-slate-800">
                            {cat.name}
                          </td>
                          <td className="py-3.5 px-4 font-mono text-xs text-slate-500">
                            {cat.slug}
                          </td>
                          <td className="py-3.5 px-4 text-center">
                            <span className="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                              {cat.destinations_count ?? 0} destinasi
                            </span>
                          </td>
                          <td className="py-3.5 px-4 text-center">
                            {cat.is_active ? (
                              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                Aktif
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500">
                                Nonaktif
                              </span>
                            )}
                          </td>
                          <td className="py-3.5 px-4 text-right">
                            <div className="inline-flex items-center gap-2">
                              <button
                                type="button"
                                className="p-1.5 text-slate-500 hover:text-blue-600 rounded-lg hover:bg-slate-100 transition-colors"
                                title="Edit Kategori"
                                onClick={() => openCategoryModal(cat)}
                              >
                                <Edit2 size={16} />
                              </button>
                              <button
                                type="button"
                                className="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-slate-100 transition-colors"
                                title="Hapus Kategori"
                                onClick={() =>
                                  setDeleteTarget({ type: "category", item: cat })
                                }
                              >
                                <Trash2 size={16} />
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </div>
        )}

        {/* ===================== TAB 2: WILAYAH ===================== */}
        {activeTab === "regions" && (
          <div className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-3 justify-between items-stretch sm:items-center">
              <div className="flex flex-wrap flex-1 gap-2">
                <div className="relative flex-1 min-w-[200px]">
                  <Search
                    size={18}
                    className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                  />
                  <input
                    type="search"
                    value={regSearch}
                    onChange={(e) => setRegSearch(e.target.value)}
                    placeholder="Cari nama atau kode wilayah…"
                    className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <select
                  value={regTypeFilter}
                  onChange={(e) => setRegTypeFilter(e.target.value)}
                  className="rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="all">Semua Tipe Wilayah</option>
                  <option value="regency">Kabupaten</option>
                  <option value="city">Kota</option>
                  <option value="district">Kecamatan</option>
                  <option value="village">Desa / Kelurahan</option>
                </select>
              </div>
              <button
                type="button"
                className="ui-button flex items-center justify-center gap-2"
                onClick={() => openRegionModal()}
              >
                <Plus size={16} /> Tambah Wilayah
              </button>
            </div>

            {loading ? (
              <div className="text-center py-12 bg-white rounded-2xl border border-slate-200">
                <Loader2 size={28} className="animate-spin text-blue-600 mx-auto mb-2" />
                <p className="text-sm text-slate-500">Memuat data wilayah…</p>
              </div>
            ) : filteredRegions.length === 0 ? (
              <div className="text-center py-12 bg-white rounded-2xl border border-slate-200">
                <MapPin size={36} className="text-slate-300 mx-auto mb-2" />
                <h3 className="font-bold text-slate-700">Belum ada wilayah</h3>
                <p className="text-sm text-slate-500 mt-1">
                  {regSearch || regTypeFilter !== "all"
                    ? "Tidak ada wilayah yang cocok dengan kriteria pencarian."
                    : "Tambahkan wilayah administratif baru untuk lokasi destinasi."}
                </p>
              </div>
            ) : (
              <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                      <tr>
                        <th className="py-3.5 px-4">Kode</th>
                        <th className="py-3.5 px-4">Nama Wilayah</th>
                        <th className="py-3.5 px-4">Tipe</th>
                        <th className="py-3.5 px-4">Wilayah Induk</th>
                        <th className="py-3.5 px-4 text-center">Destinasi</th>
                        <th className="py-3.5 px-4 text-center">Status</th>
                        <th className="py-3.5 px-4 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {filteredRegions.map((reg) => {
                        const typeInfo = regionTypeLabels[reg.type] || {
                          label: reg.type,
                          color: "bg-slate-50 text-slate-600 border-slate-200",
                        };
                        return (
                          <tr key={reg.id} className="hover:bg-slate-50 transition-colors">
                            <td className="py-3.5 px-4 font-mono font-bold text-xs text-blue-600">
                              {reg.code}
                            </td>
                            <td className="py-3.5 px-4 font-semibold text-slate-800">
                              {reg.name}
                            </td>
                            <td className="py-3.5 px-4">
                              <span
                                className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold border ${typeInfo.color}`}
                              >
                                {typeInfo.label}
                              </span>
                            </td>
                            <td className="py-3.5 px-4 text-slate-500 text-xs">
                              {reg.parent ? (
                                <div className="flex items-center gap-1.5">
                                  <FolderTree size={14} className="text-slate-400" />
                                  <span>{reg.parent.name}</span>
                                </div>
                              ) : (
                                <span className="text-slate-400">—</span>
                              )}
                            </td>
                            <td className="py-3.5 px-4 text-center">
                              <span className="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                {reg.destinations_count ?? 0}
                              </span>
                            </td>
                            <td className="py-3.5 px-4 text-center">
                              {reg.is_active ? (
                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                  Aktif
                                </span>
                              ) : (
                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500">
                                  Nonaktif
                                </span>
                              )}
                            </td>
                            <td className="py-3.5 px-4 text-right">
                              <div className="inline-flex items-center gap-2">
                                <button
                                  type="button"
                                  className="p-1.5 text-slate-500 hover:text-blue-600 rounded-lg hover:bg-slate-100 transition-colors"
                                  title="Edit Wilayah"
                                  onClick={() => openRegionModal(reg)}
                                >
                                  <Edit2 size={16} />
                                </button>
                                <button
                                  type="button"
                                  className="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-slate-100 transition-colors"
                                  title="Hapus Wilayah"
                                  onClick={() =>
                                    setDeleteTarget({ type: "region", item: reg })
                                  }
                                >
                                  <Trash2 size={16} />
                                </button>
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </div>
            )}
          </div>
        )}

        {/* ===================== MODAL: KATEGORI ===================== */}
        {catModal && (
          <div
            className="modal-backdrop"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cat-modal-title"
            onClick={(e) => {
              if (e.target === e.currentTarget && !busy) setCatModal(null);
            }}
          >
            <div className="modal-dialog-card max-w-md">
              <div className="modal-header-row">
                <div className="modal-icon-badge" style={{ background: "#eff6ff", color: "#2563eb", borderColor: "#dbeafe" }}>
                  <Tag size={22} />
                </div>
                <button
                  type="button"
                  className="modal-close-btn"
                  onClick={() => !busy && setCatModal(null)}
                  disabled={busy}
                  aria-label="Tutup"
                >
                  <X size={18} />
                </button>
              </div>

              <div>
                <h2 id="cat-modal-title" className="modal-title">
                  {catModal.mode === "edit" ? "Edit Kategori" : "Tambah Kategori Baru"}
                </h2>
                <p className="modal-description" style={{ marginTop: 4 }}>
                  Kategori digunakan untuk mengelompokkan destinasi pada filter pencarian.
                </p>
              </div>

              <form onSubmit={handleCategorySubmit} className="space-y-4">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1.5">
                    Nama Kategori <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    value={catForm.name}
                    onChange={(e) => {
                      const name = e.target.value;
                      setCatForm((prev) => ({
                        ...prev,
                        name,
                        // Auto-generate slug if create mode or slug wasn't manually customized
                        slug:
                          catModal.mode === "create"
                            ? name
                                .toLowerCase()
                                .replace(/[^a-z0-9]+/g, "-")
                                .replace(/^-+|-+$/g, "")
                            : prev.slug,
                      }));
                    }}
                    placeholder="Contoh: Wisata Bahari, Agrowisata"
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    disabled={busy}
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1.5">
                    Slug URL
                  </label>
                  <input
                    type="text"
                    value={catForm.slug}
                    onChange={(e) =>
                      setCatForm((prev) => ({
                        ...prev,
                        slug: e.target.value
                          .toLowerCase()
                          .replace(/[^a-z0-9-]+/g, "-"),
                      }))
                    }
                    placeholder="Contoh: wisata-bahari"
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 font-mono text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    disabled={busy}
                  />
                  <p className="text-[11px] text-slate-400 mt-1">
                    Dikosongkan jika ingin dibuat otomatis dari nama kategori.
                  </p>
                </div>

                <div className="pt-2">
                  <label className="flex items-center gap-2.5 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      checked={catForm.is_active}
                      onChange={(e) =>
                        setCatForm((prev) => ({ ...prev, is_active: e.target.checked }))
                      }
                      disabled={busy}
                      className="w-4 h-4 rounded text-blue-600 focus:ring-blue-500"
                    />
                    <span className="text-sm font-semibold text-slate-700">
                      Aktifkan kategori ini
                    </span>
                  </label>
                </div>

                <div className="modal-actions pt-4 border-t border-slate-100">
                  <button
                    type="button"
                    className="ui-button ui-button-outline"
                    onClick={() => setCatModal(null)}
                    disabled={busy}
                  >
                    Batal
                  </button>
                  <button type="submit" className="ui-button" disabled={busy}>
                    {busy ? (
                      <>
                        <Loader2 size={16} className="animate-spin" /> Menyimpan…
                      </>
                    ) : (
                      "Simpan Kategori"
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* ===================== MODAL: WILAYAH ===================== */}
        {regModal && (
          <div
            className="modal-backdrop"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reg-modal-title"
            onClick={(e) => {
              if (e.target === e.currentTarget && !busy) setRegModal(null);
            }}
          >
            <div className="modal-dialog-card max-w-md">
              <div className="modal-header-row">
                <div className="modal-icon-badge" style={{ background: "#eff6ff", color: "#2563eb", borderColor: "#dbeafe" }}>
                  <MapPin size={22} />
                </div>
                <button
                  type="button"
                  className="modal-close-btn"
                  onClick={() => !busy && setRegModal(null)}
                  disabled={busy}
                  aria-label="Tutup"
                >
                  <X size={18} />
                </button>
              </div>

              <div>
                <h2 id="reg-modal-title" className="modal-title">
                  {regModal.mode === "edit" ? "Edit Wilayah" : "Tambah Wilayah Baru"}
                </h2>
                <p className="modal-description" style={{ marginTop: 4 }}>
                  Wilayah digunakan untuk penempatan lokasi destinasi dan mitra daerah.
                </p>
              </div>

              <form onSubmit={handleRegionSubmit} className="space-y-4">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1.5">
                    Nama Wilayah <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    value={regForm.name}
                    onChange={(e) => {
                      const name = e.target.value;
                      setRegForm((prev) => ({
                        ...prev,
                        name,
                        // Auto-generate code candidate if create mode and empty
                        code:
                          regModal.mode === "create" && !prev.code
                            ? name
                                .toUpperCase()
                                .replace(/[^A-Z0-9]+/g, "-")
                                .slice(0, 16)
                            : prev.code,
                      }));
                    }}
                    placeholder="Contoh: Kabupaten Sleman, Desa Sambirejo"
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    disabled={busy}
                  />
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1.5">
                    Kode Wilayah (Unik) <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    value={regForm.code}
                    onChange={(e) =>
                      setRegForm((prev) => ({
                        ...prev,
                        code: e.target.value.toUpperCase().replace(/\s+/g, "-"),
                      }))
                    }
                    placeholder="Contoh: KAB-SLM, DESA-SMB"
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 font-mono text-sm uppercase focus:outline-none focus:ring-2 focus:ring-blue-500"
                    disabled={busy}
                  />
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1.5">
                      Tipe Wilayah <span className="text-red-500">*</span>
                    </label>
                    <select
                      value={regForm.type}
                      onChange={(e) =>
                        setRegForm((prev) => ({ ...prev, type: e.target.value }))
                      }
                      className="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                      disabled={busy}
                    >
                      <option value="regency">Kabupaten</option>
                      <option value="city">Kota</option>
                      <option value="district">Kecamatan</option>
                      <option value="village">Desa / Kelurahan</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1.5">
                      Wilayah Induk (Parent)
                    </label>
                    <select
                      value={regForm.parent_id}
                      onChange={(e) =>
                        setRegForm((prev) => ({ ...prev, parent_id: e.target.value }))
                      }
                      className="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                      disabled={busy}
                    >
                      <option value="">(Tidak Ada / Tingkat Atas)</option>
                      {availableParents.map((p) => (
                        <option key={p.id} value={p.id}>
                          {p.name} ({regionTypeLabels[p.type]?.label || p.type})
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="pt-2">
                  <label className="flex items-center gap-2.5 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      checked={regForm.is_active}
                      onChange={(e) =>
                        setRegForm((prev) => ({ ...prev, is_active: e.target.checked }))
                      }
                      disabled={busy}
                      className="w-4 h-4 rounded text-blue-600 focus:ring-blue-500"
                    />
                    <span className="text-sm font-semibold text-slate-700">
                      Aktifkan wilayah ini
                    </span>
                  </label>
                </div>

                <div className="modal-actions pt-4 border-t border-slate-100">
                  <button
                    type="button"
                    className="ui-button ui-button-outline"
                    onClick={() => setRegModal(null)}
                    disabled={busy}
                  >
                    Batal
                  </button>
                  <button type="submit" className="ui-button" disabled={busy}>
                    {busy ? (
                      <>
                        <Loader2 size={16} className="animate-spin" /> Menyimpan…
                      </>
                    ) : (
                      "Simpan Wilayah"
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* ===================== MODAL: DELETE CONFIRMATION ===================== */}
        {deleteTarget && (
          <div
            className="modal-backdrop"
            role="dialog"
            aria-modal="true"
            aria-labelledby="del-modal-title"
            onClick={(e) => {
              if (e.target === e.currentTarget && !busy) setDeleteTarget(null);
            }}
          >
            <div className="modal-dialog-card max-w-md">
              <div className="modal-header-row">
                <div className="modal-icon-badge">
                  <Trash2 size={22} />
                </div>
                <button
                  type="button"
                  className="modal-close-btn"
                  onClick={() => !busy && setDeleteTarget(null)}
                  disabled={busy}
                  aria-label="Tutup"
                >
                  <X size={18} />
                </button>
              </div>

              <div>
                <h2 id="del-modal-title" className="modal-title">
                  Konfirmasi Hapus {deleteTarget.type === "category" ? "Kategori" : "Wilayah"}
                </h2>
                <p className="modal-description" style={{ marginTop: 8 }}>
                  Apakah Anda yakin ingin menghapus{" "}
                  <strong>“{deleteTarget.item.name}”</strong>? Tindakan ini tidak dapat
                  dibatalkan.
                </p>
              </div>

              {((deleteTarget.type === "category" && deleteTarget.item.destinations_count > 0) ||
                (deleteTarget.type === "region" &&
                  (deleteTarget.item.destinations_count > 0 ||
                    deleteTarget.item.children_count > 0))) && (
                <div className="rounded-xl bg-amber-50 border border-amber-200 p-3 text-amber-800 text-xs">
                  <strong>Peringatan:</strong> Data ini sedang digunakan oleh destinasi atau
                  memiliki wilayah bawahan. Penghapusan kemungkinan akan ditolak oleh sistem demi
                  menjaga integritas data.
                </div>
              )}

              <div className="modal-actions pt-2">
                <button
                  type="button"
                  className="ui-button ui-button-outline"
                  onClick={() => setDeleteTarget(null)}
                  disabled={busy}
                >
                  Batal
                </button>
                <button
                  type="button"
                  className="ui-button modal-btn-danger"
                  onClick={confirmDelete}
                  disabled={busy}
                >
                  {busy ? (
                    <>
                      <Loader2 size={16} className="animate-spin" /> Menghapus…
                    </>
                  ) : (
                    "Ya, Hapus"
                  )}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </Shell>
  );
}
