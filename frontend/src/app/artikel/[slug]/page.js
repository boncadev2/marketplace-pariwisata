/* eslint-disable @next/next/no-img-element */
import Link from "next/link";
import { notFound } from "next/navigation";
import {
  Calendar,
  Clock,
  User,
  ArrowLeft,
  Share2,
  Tag,
  ChevronRight,
  Compass,
  ArrowRight,
} from "lucide-react";
import { Shell } from "../../../components/Shell";

function formatDate(dateStr) {
  if (!dateStr) return "";
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString("id-ID", {
      day: "numeric",
      month: "long",
      year: "numeric",
    });
  } catch {
    return dateStr;
  }
}

export async function generateMetadata({ params }) {
  const { slug } = await params;
  try {
    const backend = process.env.BACKEND_INTERNAL_URL || "http://backend:8000";
    const response = await fetch(
      `${backend}/api/v1/articles/${encodeURIComponent(slug)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(5000),
      }
    );
    if (!response.ok) return { title: "Artikel Wisata" };
    const { data: item } = await response.json();
    const title = item.meta_title || `${item.title} — Panduan Wisata`;
    const desc =
      item.meta_description ||
      item.excerpt ||
      "Baca panduan wisata, tips liburan, dan ulasan budaya daerah terlengkap di WisataDaerah.";
    const image = item.image_url || null;

    return {
      title,
      description: desc,
      openGraph: {
        title,
        description: desc,
        images: image ? [{ url: image }] : [],
        type: "article",
      },
      twitter: {
        card: "summary_large_image",
        title,
        description: desc,
        images: image ? [image] : [],
      },
    };
  } catch {
    return { title: "Artikel Wisata" };
  }
}

function renderArticleContent(bodyText) {
  if (!bodyText) return null;
  const blocks = bodyText.split("\n\n");

  return blocks.map((block, idx) => {
    const trimmed = block.trim();
    if (!trimmed) return null;

    // Heading 2
    if (trimmed.startsWith("## ")) {
      return (
        <h2
          key={idx}
          className="mt-8 mb-4 text-xl sm:text-2xl font-bold text-slate-900 border-b border-slate-100 pb-2"
        >
          {trimmed.replace("## ", "")}
        </h2>
      );
    }

    // Heading 3
    if (trimmed.startsWith("### ")) {
      return (
        <h3
          key={idx}
          className="mt-6 mb-3 text-lg font-bold text-slate-800"
        >
          {trimmed.replace("### ", "")}
        </h3>
      );
    }

    // Numbered List
    if (/^\d+\.\s/.test(trimmed)) {
      const items = trimmed.split("\n").filter(Boolean);
      return (
        <ol key={idx} className="my-4 list-decimal list-inside space-y-2 text-slate-700 leading-relaxed text-sm sm:text-base">
          {items.map((item, iIdx) => {
            const content = item.replace(/^\d+\.\s/, "");
            return (
              <li key={iIdx}>
                <span dangerouslySetInnerHTML={{ __html: formatBold(content) }} />
              </li>
            );
          })}
        </ol>
      );
    }

    // Bullet List
    if (trimmed.startsWith("* ") || trimmed.startsWith("- ")) {
      const items = trimmed.split("\n").filter(Boolean);
      return (
        <ul key={idx} className="my-4 list-disc list-inside space-y-2 text-slate-700 leading-relaxed text-sm sm:text-base">
          {items.map((item, iIdx) => {
            const content = item.replace(/^[\*\-]\s/, "");
            return (
              <li key={iIdx}>
                <span dangerouslySetInnerHTML={{ __html: formatBold(content) }} />
              </li>
            );
          })}
        </ul>
      );
    }

    // Blockquote
    if (trimmed.startsWith("> ")) {
      return (
        <blockquote
          key={idx}
          className="my-6 border-l-4 border-emerald-500 bg-emerald-50/50 p-4 rounded-r-xl italic text-slate-700 text-sm sm:text-base"
        >
          {trimmed.replace(/^>\s/, "")}
        </blockquote>
      );
    }

    // Regular Paragraph
    return (
      <p
        key={idx}
        className="my-4 text-slate-700 leading-relaxed text-sm sm:text-base"
        dangerouslySetInnerHTML={{ __html: formatBold(trimmed) }}
      />
    );
  });
}

function formatBold(str) {
  // Simple bold markdown converter: **text** -> <strong>text</strong>
  return str.replace(/\*\*(.*?)\*\*/g, '<strong class="font-semibold text-slate-900">$1</strong>');
}

export default async function ArticleDetailPage({ params }) {
  const { slug } = await params;
  let article = null;
  let related = [];

  try {
    const backend = process.env.BACKEND_INTERNAL_URL || "http://backend:8000";
    const res = await fetch(
      `${backend}/api/v1/articles/${encodeURIComponent(slug)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(8000),
      }
    );

    if (res.status === 404) {
      notFound();
    }

    if (res.ok) {
      const json = await res.json();
      article = json.data;
      related = json.related || [];
    }
  } catch (error) {
    if (error?.digest === "NEXT_NOT_FOUND") throw error;
  }

  if (!article) {
    notFound();
  }

  return (
    <Shell>
      <article className="mx-auto max-w-4xl px-4 py-8">
        {/* Breadcrumb Navigation */}
        <nav className="mb-6 flex items-center gap-1.5 text-xs text-slate-500">
          <Link href="/" className="hover:text-emerald-700 transition">
            Beranda
          </Link>
          <ChevronRight className="h-3 w-3 text-slate-400" />
          <Link href="/artikel" className="hover:text-emerald-700 transition">
            Artikel Wisata
          </Link>
          <ChevronRight className="h-3 w-3 text-slate-400" />
          <span className="line-clamp-1 max-w-[200px] sm:max-w-xs font-medium text-slate-800">
            {article.title}
          </span>
        </nav>

        {/* Back Button */}
        <Link
          href="/artikel"
          className="mb-6 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-800 transition"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          Kembali ke Daftar Artikel
        </Link>

        {/* Article Header */}
        <header className="mb-8">
          <div className="flex flex-wrap items-center gap-2 mb-4">
            {article.category && (
              <span className="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">
                <Tag className="h-3 w-3" />
                {article.category}
              </span>
            )}
            <span className="text-xs text-slate-400">•</span>
            <span className="inline-flex items-center gap-1 text-xs text-slate-500">
              <Calendar className="h-3.5 w-3.5 text-slate-400" />
              {formatDate(article.published_at)}
            </span>
            <span className="text-xs text-slate-400">•</span>
            <span className="inline-flex items-center gap-1 text-xs text-slate-500">
              <Clock className="h-3.5 w-3.5 text-slate-400" />
              {article.read_time || 3} menit baca
            </span>
          </div>

          <h1 className="text-2xl sm:text-4xl font-extrabold tracking-tight text-slate-900 leading-tight">
            {article.title}
          </h1>

          {article.excerpt && (
            <p className="mt-4 text-base sm:text-lg text-slate-600 leading-relaxed font-normal border-l-2 border-emerald-400 pl-4 py-0.5">
              {article.excerpt}
            </p>
          )}

          {/* Author info */}
          <div className="mt-6 flex items-center justify-between border-y border-slate-100 py-3 text-xs">
            <div className="flex items-center gap-2.5">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 font-bold">
                <User className="h-4 w-4" />
              </div>
              <div>
                <p className="font-semibold text-slate-800">
                  {article.author_name || "Redaksi Wisata Daerah"}
                </p>
                <p className="text-[11px] text-slate-400">Kontributor Konten Wisata</p>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <span className="inline-flex items-center gap-1 text-xs text-slate-500">
                <Share2 className="h-3.5 w-3.5" />
                Bagikan
              </span>
            </div>
          </div>
        </header>

        {/* Featured Image */}
        {article.image_url && (
          <div className="mb-8 overflow-hidden rounded-2xl shadow-sm border border-slate-100 bg-slate-50">
            <img
              src={article.image_url}
              alt={article.title}
              className="w-full max-h-[500px] object-cover"
            />
          </div>
        )}

        {/* Article Body */}
        <div className="prose prose-slate max-w-none">
          {renderArticleContent(article.body)}
        </div>

        {/* CTA Card */}
        <div className="mt-12 rounded-2xl bg-gradient-to-br from-emerald-800 to-teal-900 p-6 sm:p-8 text-white shadow-md">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <span className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold backdrop-blur-sm">
                <Compass className="h-3.5 w-3.5" />
                Mulai Petualangan Anda
              </span>
              <h3 className="mt-3 text-xl font-bold">
                Ingin Mengunjungi Destinasi Ini?
              </h3>
              <p className="mt-1 text-xs sm:text-sm text-emerald-100 max-w-xl">
                Dapatkan tiket masuk resmi, paket tur rombongan, serta produk oleh-oleh khas UMKM langsung dari para pengelola desa.
              </p>
            </div>
            <div className="flex flex-wrap gap-2.5">
              <Link
                href="/destinasi"
                className="rounded-xl bg-white px-4 py-2.5 text-xs font-bold text-emerald-900 hover:bg-emerald-50 transition shadow-sm"
              >
                Lihat Destinasi
              </Link>
              <Link
                href="/paket"
                className="rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-xs font-bold text-white hover:bg-white/20 transition backdrop-blur-sm"
              >
                Paket Tur Wisata
              </Link>
            </div>
          </div>
        </div>

        {/* Related Articles */}
        {related.length > 0 && (
          <section className="mt-12 border-t border-slate-200 pt-8">
            <h2 className="text-xl font-bold text-slate-900 mb-6">
              Artikel Rekomendasi Lainnya
            </h2>
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
              {related.map((item) => (
                <Link
                  key={item.id}
                  href={`/artikel/${item.slug}`}
                  className="group block overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md hover:border-slate-300"
                >
                  <div className="relative h-36 w-full overflow-hidden bg-slate-100">
                    <img
                      src={
                        item.image_url ||
                        "https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=600&q=80"
                      }
                      alt={item.title}
                      className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    />
                  </div>
                  <div className="p-4">
                    <span className="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">
                      {item.category || "Wisata"}
                    </span>
                    <h3 className="mt-1 line-clamp-2 text-xs font-bold text-slate-900 group-hover:text-emerald-700 transition">
                      {item.title}
                    </h3>
                    <span className="mt-3 inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700">
                      Baca Artikel
                      <ArrowRight className="h-3 w-3 transition group-hover:translate-x-0.5" />
                    </span>
                  </div>
                </Link>
              ))}
            </div>
          </section>
        )}
      </article>
    </Shell>
  );
}
