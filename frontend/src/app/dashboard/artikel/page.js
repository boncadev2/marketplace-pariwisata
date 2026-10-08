/* eslint-disable @next/next/no-img-element */
"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  BookOpen,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  AlertCircle,
  Loader2,
  ArrowLeft,
  Search,
  ExternalLink,
  Eye,
  FileText,
  Calendar,
  X,
} from "lucide-react";
import { Shell } from "../../../components/Shell";
import { PageHeader } from "../../../components/PageHeader";
import { apiRequest } from "../../../lib/api";

function formatDate(dateStr) {
  if (!dateStr) return "-";
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString("id-ID", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  } catch {
    return dateStr;
  }
}

export default function AdminArticlesDashboardPage() {
  const [articles, setArticles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState("all");
  const [searchTerm, setSearchTerm] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [revision, setRevision] = useState(0);

  // Modal create/edit state
  const [modalMode, setModalMode] = useState(null); // null | 'create' | 'edit'
  const [editingArticle, setEditingArticle] = useState(null);
  const [deleteConfirmArticle, setDeleteConfirmArticle] = useState(null);

  const [form, setForm] = useState({
    title: "",
    slug: "",
    image_url: "",
    category: "Panduan Wisata",
    author_name: "",
    excerpt: "",
    body: "",
    status: "published",
    meta_title: "",
    meta_description: "",
  });

  useEffect(() => {
    let active = true;

    let url = "/api/v1/dashboard/articles?per_page=50";
    if (statusFilter !== "all") {
      url += `&status=${encodeURIComponent(statusFilter)}`;
    }
    if (searchTerm.trim()) {
      url += `&search=${encodeURIComponent(searchTerm.trim())}`;
    }

    apiRequest(url)
      .then((res) => {
        if (!active) return;
        setArticles(res.data?.data || res.data || []);
      })
      .catch((err) => {
        if (!active) return;
        setError(err.message || "Gagal memuat daftar artikel.");
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [revision, statusFilter, searchTerm]);

  function openCreateModal() {
    setForm({
      title: "",
      slug: "",
      image_url: "",
      category: "Panduan Wisata",
      author_name: "",
      excerpt: "",
      body: "",
      status: "published",
      meta_title: "",
      meta_description: "",
    });
    setEditingArticle(null);
    setModalMode("create");
    setMessage("");
    setError("");
  }

  function openEditModal(article) {
    setForm({
      title: article.title || "",
      slug: article.slug || "",
      image_url: article.image_url || "",
      category: article.category || "Panduan Wisata",
      author_name: article.author_name || "",
      excerpt: article.excerpt || "",
      body: article.body || "",
      status: article.status || "published",
      meta_title: article.meta_title || "",
      meta_description: article.meta_description || "",
    });
    setEditingArticle(article);
    setModalMode("edit");
    setMessage("");
    setError("");
  }

  async function handleSave(e) {
    e.preventDefault();
    if (!form.title.trim() || !form.body.trim()) {
      setError("Judul dan isi artikel wajib diisi.");
      return;
    }

    setBusy(true);
    setMessage("");
    setError("");

    try {
      if (modalMode === "create") {
        await apiRequest("/api/v1/dashboard/articles", {
          method: "POST",
          body: JSON.stringify(form),
        });
        setMessage("Artikel baru berhasil diterbitkan!");
      } else {
        await apiRequest(`/api/v1/dashboard/articles/${editingArticle.id}`, {
          method: "PUT",
          body: JSON.stringify(form),
        });
        setMessage("Artikel berhasil diperbarui.");
      }
      setModalMode(null);
      setRevision((r) => r + 1);
    } catch (err) {
      setError(err.message || "Gagal menyimpan artikel.");
    } finally {
      setBusy(false);
    }
  }

  async function handleDelete() {
    if (!deleteConfirmArticle) return;
    setBusy(true);
    try {
      await apiRequest(`/api/v1/dashboard/articles/${deleteConfirmArticle.id}`, {
        method: "DELETE",
      });
      setMessage("Artikel berhasil dihapus.");
      setDeleteConfirmArticle(null);
      setRevision((r) => r + 1);
    } catch (err) {
      setError(err.message || "Gagal menghapus artikel.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <Shell>
      <div className="mx-auto max-w-7xl px-4 py-8">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
          <div>
            <Link
              href="/dashboard"
              className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-800 transition"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
              Kembali ke Ringkasan Dasbor
            </Link>
            <h1 className="mt-2 text-2xl font-extrabold text-slate-900 sm:text-3xl">
              Kelola Blog & Artikel Wisata
            </h1>
            <p className="mt-1 text-xs text-slate-500">
              Publikasikan panduan liburan, rekomendasi kuliner, dan liputan budaya daerah untuk meningkatkan traffic SEO dan pemesanan.
            </p>
          </div>

          <div className="flex items-center gap-2">
            <Link
              href="/artikel"
              target="_blank"
              className="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-sm"
            >
              <Eye className="h-3.5 w-3.5" />
              Lihat Portal Pembaca
              <ExternalLink className="h-3 w-3 text-slate-400" />
            </Link>
            <button
              type="button"
              onClick={openCreateModal}
              className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-700 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-800 transition"
            >
              <Plus className="h-4 w-4" />
              Tulis Artikel Baru
            </button>
          </div>
        </div>

        {/* Notifications */}
        {message && (
          <div className="mb-6 flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-xs font-medium text-emerald-800">
            <CheckCircle2 className="h-4 w-4 text-emerald-600 flex-shrink-0" />
            {message}
          </div>
        )}
        {error && (
          <div className="mb-6 flex items-center gap-2 rounded-xl bg-rose-50 border border-rose-200 p-4 text-xs font-medium text-rose-800">
            <AlertCircle className="h-4 w-4 text-rose-600 flex-shrink-0" />
            {error}
          </div>
        )}

        {/* Filters */}
        <div className="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-semibold text-slate-500">Status:</span>
            {["all", "published", "draft"].map((st) => (
              <button
                key={st}
                type="button"
                onClick={() => setStatusFilter(st)}
                className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                  statusFilter === st
                    ? "bg-slate-900 text-white"
                    : "bg-slate-100 text-slate-600 hover:bg-slate-200"
                }`}
              >
                {st === "all" ? "Semua" : st === "published" ? "Terbit" : "Draf"}
              </button>
            ))}
          </div>

          <div className="relative min-w-[240px]">
            <Search className="absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Cari judul artikel..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full rounded-xl border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>
        </div>

        {/* Articles Table */}
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          {loading ? (
            <div className="flex h-64 items-center justify-center">
              <Loader2 className="h-6 w-6 animate-spin text-emerald-600" />
            </div>
          ) : articles.length === 0 ? (
            <div className="p-12 text-center">
              <BookOpen className="mx-auto h-12 w-12 text-slate-300" />
              <h3 className="mt-3 text-sm font-bold text-slate-800">
                Belum Ada Artikel
              </h3>
              <p className="mt-1 text-xs text-slate-500">
                Mulai dengan menulis panduan wisata pertama Anda.
              </p>
              <button
                type="button"
                onClick={openCreateModal}
                className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800"
              >
                <Plus className="h-3.5 w-3.5" />
                Buat Artikel Sekarang
              </button>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="border-b border-slate-100 bg-slate-50/70 font-semibold text-slate-600">
                  <tr>
                    <th className="py-3 px-4">Artikel</th>
                    <th className="py-3 px-4">Kategori & Penulis</th>
                    <th className="py-3 px-4">Status</th>
                    <th className="py-3 px-4">Tanggal Rilis</th>
                    <th className="py-3 px-4 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {articles.map((item) => (
                    <tr key={item.id} className="hover:bg-slate-50/60 transition">
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-3">
                          {item.image_url ? (
                            <img
                              src={item.image_url}
                              alt=""
                              className="h-10 w-14 rounded-lg object-cover bg-slate-100 flex-shrink-0"
                            />
                          ) : (
                            <div className="flex h-10 w-14 items-center justify-center rounded-lg bg-slate-100 text-slate-400 flex-shrink-0">
                              <FileText className="h-5 w-5" />
                            </div>
                          )}
                          <div>
                            <p className="font-bold text-slate-900 line-clamp-1">
                              {item.title}
                            </p>
                            <p className="text-[11px] text-slate-400 font-mono line-clamp-1">
                              /{item.slug}
                            </p>
                          </div>
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <span className="inline-block rounded bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                          {item.category || "Umum"}
                        </span>
                        <p className="mt-0.5 text-[11px] text-slate-400">
                          {item.author_name || "Redaksi"}
                        </p>
                      </td>
                      <td className="py-3 px-4">
                        {item.status === "published" ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-600" />
                            Terbit
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">
                            <span className="h-1.5 w-1.5 rounded-full bg-slate-400" />
                            Draf
                          </span>
                        )}
                      </td>
                      <td className="py-3 px-4 text-slate-500">
                        {formatDate(item.published_at || item.created_at)}
                      </td>
                      <td className="py-3 px-4 text-right">
                        <div className="inline-flex items-center gap-1.5">
                          {item.status === "published" && (
                            <Link
                              href={`/artikel/${item.slug}`}
                              target="_blank"
                              className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                              title="Buka Halaman"
                            >
                              <ExternalLink className="h-4 w-4" />
                            </Link>
                          )}
                          <button
                            type="button"
                            onClick={() => openEditModal(item)}
                            className="rounded-lg p-1.5 text-slate-500 hover:bg-blue-50 hover:text-blue-700 transition"
                            title="Edit Artikel"
                          >
                            <Edit2 className="h-4 w-4" />
                          </button>
                          <button
                            type="button"
                            onClick={() => setDeleteConfirmArticle(item)}
                            className="rounded-lg p-1.5 text-slate-500 hover:bg-rose-50 hover:text-rose-700 transition"
                            title="Hapus Artikel"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Create / Edit Modal Dialog */}
        {modalMode && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm overflow-y-auto">
            <div className="relative my-8 w-full max-w-3xl rounded-3xl bg-white p-6 sm:p-8 shadow-2xl">
              <button
                type="button"
                onClick={() => setModalMode(null)}
                className="absolute right-6 top-6 rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              >
                <X className="h-5 w-5" />
              </button>

              <h2 className="text-xl font-bold text-slate-900">
                {modalMode === "create" ? "Tulis Artikel Baru" : "Edit Artikel"}
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                Isi form berikut untuk mempublikasikan artikel di platform.
              </p>

              <form onSubmit={handleSave} className="mt-6 space-y-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div className="sm:col-span-2">
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Judul Artikel *
                    </label>
                    <input
                      type="text"
                      required
                      placeholder="Contoh: Panduan Lengkap Menikmati Sunset di Pantai Granit"
                      value={form.title}
                      onChange={(e) => setForm({ ...form, title: e.target.value })}
                      className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Slug URL (Opsional)
                    </label>
                    <input
                      type="text"
                      placeholder="Dibuat otomatis dari judul jika kosong"
                      value={form.slug}
                      onChange={(e) => setForm({ ...form, slug: e.target.value })}
                      className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Kategori Artikel
                    </label>
                    <select
                      value={form.category}
                      onChange={(e) => setForm({ ...form, category: e.target.value })}
                      className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    >
                      <option value="Panduan Wisata">Panduan Wisata</option>
                      <option value="Tips Liburan">Tips Liburan</option>
                      <option value="Kuliner Lokal">Kuliner Lokal</option>
                      <option value="Tradisi & Budaya">Tradisi & Budaya</option>
                      <option value="Event Daerah">Event Daerah</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      URL Gambar Sampul (Unsplash / CDN)
                    </label>
                    <input
                      type="url"
                      placeholder="https://images.unsplash.com/photo-..."
                      value={form.image_url}
                      onChange={(e) => setForm({ ...form, image_url: e.target.value })}
                      className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Nama Penulis / Kontributor
                    </label>
                    <input
                      type="text"
                      placeholder="Contoh: Tim Redaksi Wisata"
                      value={form.author_name}
                      onChange={(e) => setForm({ ...form, author_name: e.target.value })}
                      className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                  </div>

                  <div className="sm:col-span-2">
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Ringkasan / Excerpt
                    </label>
                    <textarea
                      rows={2}
                      placeholder="Ringkasan singkat yang muncul pada kartu artikel..."
                      value={form.excerpt}
                      onChange={(e) => setForm({ ...form, excerpt: e.target.value })}
                      className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                  </div>

                  <div className="sm:col-span-2">
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Isi Artikel (Markdown format) *
                    </label>
                    <textarea
                      rows={8}
                      required
                      placeholder="Tulis artikel dengan format markdown:&#10;## Subjudul Utama&#10;Paragraf artikel...&#10;* Poin rekomendasi 1&#10;* Poin rekomendasi 2"
                      value={form.body}
                      onChange={(e) => setForm({ ...form, body: e.target.value })}
                      className="w-full font-mono rounded-xl border border-slate-300 p-2.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 leading-relaxed"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      Status Publikasi
                    </label>
                    <select
                      value={form.status}
                      onChange={(e) => setForm({ ...form, status: e.target.value })}
                      className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    >
                      <option value="published">Terbitkan Langsung (Publik)</option>
                      <option value="draft">Simpan Sebagai Draf (Tersembunyi)</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 mb-1">
                      SEO Meta Title (Opsional)
                    </label>
                    <input
                      type="text"
                      placeholder="Judul untuk Google Search"
                      value={form.meta_title}
                      onChange={(e) => setForm({ ...form, meta_title: e.target.value })}
                      className="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                  </div>
                </div>

                <div className="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                  <button
                    type="button"
                    onClick={() => setModalMode(null)}
                    className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={busy}
                    className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-700 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-800 disabled:opacity-50 transition shadow-sm"
                  >
                    {busy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                    {modalMode === "create" ? "Terbitkan Artikel" : "Simpan Perubahan"}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Delete Confirmation Modal */}
        {deleteConfirmArticle && (
          <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
            <div className="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
              <h3 className="text-base font-bold text-slate-900">
                Konfirmasi Hapus Artikel
              </h3>
              <p className="mt-2 text-xs text-slate-600 leading-relaxed">
                Apakah Anda yakin ingin menghapus artikel <strong>&quot;{deleteConfirmArticle.title}&quot;</strong>? Artikel ini tidak akan dapat diakses lagi oleh pengunjung.
              </p>
              <div className="mt-6 flex items-center justify-end gap-2.5">
                <button
                  type="button"
                  onClick={() => setDeleteConfirmArticle(null)}
                  className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                >
                  Batal
                </button>
                <button
                  type="button"
                  disabled={busy}
                  onClick={handleDelete}
                  className="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 disabled:opacity-50"
                >
                  {busy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                  Ya, Hapus
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </Shell>
  );
}
