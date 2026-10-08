"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import {
  HelpCircle,
  MessageSquare,
  Ticket,
  CreditCard,
  RotateCcw,
  Store,
  ChevronDown,
  ChevronUp,
  Mail,
  Phone,
  Clock,
  Send,
  FileText,
  Search,
  CheckCircle2,
  ExternalLink,
} from "lucide-react";
import { PageHeader } from "../../components/PageHeader";
import { Shell } from "../../components/Shell";
import { apiRequest } from "../../lib/api";

const categories = {
  booking: "Pemesanan",
  payment: "Pembayaran",
  voucher: "Voucher",
  visit: "Kunjungan",
  other: "Lainnya",
};

const faqs = [
  {
    category: "Pemesanan & Tiket",
    icon: Ticket,
    items: [
      {
        q: "Bagaimana cara memesan tiket destinasi atau paket wisata?",
        a: "Pilih destinasi atau paket wisata yang Anda inginkan dari katalog, tentukan tanggal kunjungan dan jumlah tiket/peserta, lalu klik 'Pesan Sekarang'. Lengkapi data kontak Anda dan lanjutkan ke pembayaran.",
      },
      {
        q: "Di mana saya bisa melihat e-voucher / tiket yang sudah dibayar?",
        a: "E-voucher Anda dapat diakses di menu 'Akun' > 'Pesanan Saya' atau langsung di halaman '/voucher' menggunakan nomor referensi pesanan yang dikirimkan ke email Anda.",
      },
      {
        q: "Apakah tiket elektronik harus dicetak di kertas?",
        a: "Tidak perlu. Cukup buka e-voucher di ponsel cerdas Anda dan tunjukkan kode QR kepada petugas di pintu masuk destinasi atau resepsionis homestay untuk dipindai.",
      },
    ],
  },
  {
    category: "Pembayaran",
    icon: CreditCard,
    items: [
      {
        q: "Metode pembayaran apa saja yang didukung?",
        a: "Kami menerima pembayaran melalui QRIS (dapat di-scan dengan GoPay, OVO, DANA, ShopeePay, BCA Mobile, dan aplikasi bank lainnya), Virtual Account bank nasional (BCA, Mandiri, BNI, BRI, Permata), serta transfer bank.",
      },
      {
        q: "Berapa lama batas waktu pembayaran tiket/kamar?",
        a: "Batas waktu pembayaran biasanya 15 hingga 60 menit tergantung jenis layanan untuk menjamin kuota kamar dan tiket tidak terkunci jika batal.",
      },
      {
        q: "Bagaimana jika pembayaran saya sudah sukses tetapi status belum berubah?",
        a: "Sistem secara otomatis mendeteksi notifikasi payment gateway dalam beberapa detik. Jika dalam 5 menit status belum berubah, klik tombol 'Segarkan Status' di halaman pembayaran atau hubungi bantuan kami dengan bukti bayar.",
      },
    ],
  },
  {
    category: "Pembatalan & Refund",
    icon: RotateCcw,
    items: [
      {
        q: "Apakah saya bisa membatalkan pesanan dan meminta refund?",
        a: "Ya. Setiap produk wisata memiliki kebijakan pembatalan (Fleksibel, Moderat, atau Ketat). Anda dapat mengajukan refund langsung di halaman rincian pesanan akun Anda sebelum batas waktu pembatalan berakhir.",
      },
      {
        q: "Berapa lama waktu pengembalian dana sampai ke rekening saya?",
        a: "Setelah pengajuan refund disetujui oleh admin/mitra, dana akan dikembalikan melalui payment gateway dalam 3 hingga 14 hari kerja bank sesuai ketentuan bank penerbit.",
      },
    ],
  },
  {
    category: "Mitra & Pelaku Usaha UMKM",
    icon: Store,
    items: [
      {
        q: "Bagaimana cara mendaftarkan objek wisata, homestay, atau produk UMKM saya?",
        a: "Kunjungi halaman '/daftar-mitra', lengkapi formulir pendaftaran identitas penanggung jawab dan profil usaha Anda. Tim verifikator kami akan meninjau pendaftaran Anda dalam 1-2 hari kerja.",
      },
      {
        q: "Kapan hasil penjualan mitra dapat dicairkan (payout)?",
        a: "Dana hasil transaksi yang telah selesai divalidasi dan melewati periode retensi bebas sengketa akan otomatis berstatus 'Siap Cair' dan dapat ditransfer ke rekening bank mitra terdaftar.",
      },
    ],
  },
];

export default function SupportPage() {
  const [activeTab, setActiveTab] = useState("faq"); // 'faq' | 'tickets'
  const [orders, setOrders] = useState([]);
  const [tickets, setTickets] = useState([]);
  const [selected, setSelected] = useState(null);
  const [orderId, setOrderId] = useState("");
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [authenticated, setAuthenticated] = useState(false);
  const [openFaqIndex, setOpenFaqIndex] = useState({});
  const [faqSearch, setFaqSearch] = useState("");

  const toggleFaq = (catIdx, itemIdx) => {
    const key = `${catIdx}-${itemIdx}`;
    setOpenFaqIndex((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  useEffect(() => {
    let active = true;
    Promise.all([
      apiRequest("/me"),
      apiRequest("/account/orders"),
      apiRequest("/account/support-tickets"),
    ])
      .then(([, orderResult, ticketResult]) => {
        if (!active) return;
        setAuthenticated(true);
        setOrders(orderResult.data);
        setTickets(ticketResult.data);
        const requested = new URLSearchParams(window.location.search).get(
          "order_id"
        );
        setOrderId(
          orderResult.data.some((order) => order.order_id === requested)
            ? requested
            : orderResult.data[0]?.order_id || ""
        );
      })
      .catch((error) => {
        if (active && error.status !== 401)
          setMessage("Bantuan belum dapat dimuat. Coba lagi.");
      });
    return () => {
      active = false;
    };
  }, []);

  async function refreshTickets() {
    const result = await apiRequest("/account/support-tickets");
    setTickets(result.data);
  }

  async function openTicket(id) {
    setMessage("");
    try {
      const result = await apiRequest(`/account/support-tickets/${id}`);
      setSelected(result.data);
    } catch {
      setMessage("Tiket tidak dapat dibuka. Coba lagi.");
    }
  }

  async function createTicket(event) {
    event.preventDefault();
    if (busy || !orderId) return;
    setBusy(true);
    setMessage("");
    const form = event.currentTarget;
    const data = new FormData(form);
    if (!data.get("attachment")?.size) data.delete("attachment");
    try {
      const result = await apiRequest(
        `/account/orders/${encodeURIComponent(orderId)}/support-tickets`,
        { method: "POST", body: data }
      );
      form.reset();
      setSelected(result.data);
      await refreshTickets();
      setMessage("Tiket bantuan berhasil dibuat.");
    } catch (error) {
      setMessage(
        error.status === 422
          ? "Periksa kategori, pesan (minimal 10 karakter), dan lampiran PDF/JPG/PNG maksimal 5 MB."
          : "Tiket belum dapat dibuat. Coba lagi."
      );
    } finally {
      setBusy(false);
    }
  }

  async function addMessage(event) {
    event.preventDefault();
    if (busy || !selected) return;
    setBusy(true);
    setMessage("");
    const form = event.currentTarget;
    const data = new FormData(form);
    if (!data.get("attachment")?.size) data.delete("attachment");
    try {
      await apiRequest(`/account/support-tickets/${selected.id}/messages`, {
        method: "POST",
        body: data,
      });
      form.reset();
      await openTicket(selected.id);
      setMessage("Pesan tambahan berhasil dikirim.");
    } catch (error) {
      setMessage(
        error.status === 409
          ? "Tiket sudah ditutup."
          : error.status === 422
            ? "Periksa isi pesan dan lampiran Anda."
            : "Pesan belum dapat dikirim. Coba lagi."
      );
    } finally {
      setBusy(false);
    }
  }

  // Filter FAQs based on search
  const filteredFaqs = faqs
    .map((cat) => ({
      ...cat,
      items: cat.items.filter(
        (it) =>
          it.q.toLowerCase().includes(faqSearch.toLowerCase()) ||
          it.a.toLowerCase().includes(faqSearch.toLowerCase())
      ),
    }))
    .filter((cat) => cat.items.length > 0);

  return (
    <Shell>
      <PageHeader
        eyebrow="Layanan Pelanggan & FAQ"
        title="Pusat Bantuan WisataDaerah"
        description="Temukan jawaban cepat atas pertanyaan Anda atau ajukan bantuan langsung terkait pesanan Anda."
        compact
      />

      <div className="support-quicklinks" style={{ marginBottom: "1.5rem" }}>
        <Link href="/akun">Kelola pesanan</Link>
        <Link href="/voucher">Buka voucher</Link>
        <Link href="/destinasi">Jelajahi destinasi</Link>
        <Link href="/syarat-ketentuan">Syarat & ketentuan</Link>
      </div>

      {/* Tabs */}
      <div
        style={{
          display: "flex",
          gap: "0.75rem",
          borderBottom: "1px solid var(--color-border)",
          marginBottom: "2rem",
        }}
      >
        <button
          type="button"
          onClick={() => setActiveTab("faq")}
          style={{
            padding: "0.6rem 1.25rem",
            fontSize: "0.95rem",
            fontWeight: 600,
            border: "none",
            background: "none",
            cursor: "pointer",
            borderBottom: activeTab === "faq" ? "2px solid var(--color-primary)" : "2px solid transparent",
            color: activeTab === "faq" ? "var(--color-primary)" : "var(--color-text-muted)",
            display: "inline-flex",
            alignItems: "center",
            gap: "0.4rem",
          }}
        >
          <HelpCircle size={18} /> Tanya Jawab & FAQ
        </button>
        <button
          type="button"
          onClick={() => setActiveTab("tickets")}
          style={{
            padding: "0.6rem 1.25rem",
            fontSize: "0.95rem",
            fontWeight: 600,
            border: "none",
            background: "none",
            cursor: "pointer",
            borderBottom: activeTab === "tickets" ? "2px solid var(--color-primary)" : "2px solid transparent",
            color: activeTab === "tickets" ? "var(--color-primary)" : "var(--color-text-muted)",
            display: "inline-flex",
            alignItems: "center",
            gap: "0.4rem",
          }}
        >
          <MessageSquare size={18} /> Tiket Bantuan Pesanan {tickets.length > 0 && `(${tickets.length})`}
        </button>
      </div>

      {/* Tab 1: FAQ & Contact Info */}
      {activeTab === "faq" && (
        <div style={{ maxWidth: "860px", margin: "0 auto" }}>
          {/* Contact Cards Grid */}
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))",
              gap: "1rem",
              marginBottom: "2rem",
            }}
          >
            <div
              style={{
                padding: "1.25rem",
                borderRadius: "0.75rem",
                backgroundColor: "#eff6ff",
                border: "1px solid #bfdbfe",
              }}
            >
              <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", color: "#2563eb", marginBottom: "0.5rem" }}>
                <Mail size={18} />
                <strong style={{ fontSize: "0.95rem" }}>Email Layanan</strong>
              </div>
              <p style={{ margin: "0 0 0.25rem 0", fontSize: "0.85rem", color: "var(--color-text-main)" }}>
                Kirim pertanyaan atau kendala umum ke tim kami:
              </p>
              <a href="mailto:bantuan@wisatadaerah.id" style={{ color: "#2563eb", fontWeight: 600, fontSize: "0.9rem" }}>
                bantuan@wisatadaerah.id
              </a>
            </div>

            <div
              style={{
                padding: "1.25rem",
                borderRadius: "0.75rem",
                backgroundColor: "#ecfdf5",
                border: "1px solid #bbf7d0",
              }}
            >
              <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", color: "#059669", marginBottom: "0.5rem" }}>
                <Clock size={18} />
                <strong style={{ fontSize: "0.95rem" }}>Jam Operasional CS</strong>
              </div>
              <p style={{ margin: "0 0 0.25rem 0", fontSize: "0.85rem", color: "var(--color-text-main)" }}>
                Senin — Minggu:
              </p>
              <span style={{ color: "#059669", fontWeight: 600, fontSize: "0.9rem" }}>
                08:00 — 20:00 WIB
              </span>
            </div>

            <div
              style={{
                padding: "1.25rem",
                borderRadius: "0.75rem",
                backgroundColor: "#fef3c7",
                border: "1px solid #fde68a",
              }}
            >
              <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", color: "#d97706", marginBottom: "0.5rem" }}>
                <Phone size={18} />
                <strong style={{ fontSize: "0.95rem" }}>Bantuan Mendesak</strong>
              </div>
              <p style={{ margin: "0 0 0.25rem 0", fontSize: "0.85rem", color: "var(--color-text-main)" }}>
                Kendala tiket saat di pintu masuk:
              </p>
              <span style={{ color: "#d97706", fontWeight: 600, fontSize: "0.9rem" }}>
                Gunakan menu Tiket Pesanan
              </span>
            </div>
          </div>

          {/* Search FAQ */}
          <div style={{ position: "relative", marginBottom: "1.5rem" }}>
            <Search
              size={18}
              style={{
                position: "absolute",
                left: "1rem",
                top: "50%",
                transform: "translateY(-50%)",
                color: "var(--color-text-muted)",
              }}
            />
            <input
              type="text"
              placeholder="Ketik kata kunci pertanyaan (misal: refund, pembayaran, voucher, qris)..."
              value={faqSearch}
              onChange={(e) => setFaqSearch(e.target.value)}
              className="ui-input"
              style={{ paddingLeft: "2.75rem", paddingRight: "1rem", fontSize: "0.95rem" }}
            />
          </div>

          {/* FAQ Accordion */}
          {filteredFaqs.length === 0 ? (
            <p style={{ textAlign: "center", padding: "2rem", color: "var(--color-text-muted)" }}>
              Tidak ada jawaban yang cocok dengan kata kunci &quot;{faqSearch}&quot;.
            </p>
          ) : (
            filteredFaqs.map((cat, catIdx) => {
              const Icon = cat.icon;
              return (
                <div key={cat.category} style={{ marginBottom: "2rem" }}>
                  <h3
                    style={{
                      display: "flex",
                      alignItems: "center",
                      gap: "0.5rem",
                      fontSize: "1.15rem",
                      fontWeight: 700,
                      marginBottom: "0.75rem",
                      color: "var(--color-text-main)",
                    }}
                  >
                    <Icon size={20} color="var(--color-primary)" /> {cat.category}
                  </h3>
                  <div style={{ display: "flex", flexDirection: "column", gap: "0.5rem" }}>
                    {cat.items.map((item, itemIdx) => {
                      const isOpen = openFaqIndex[`${catIdx}-${itemIdx}`];
                      return (
                        <div
                          key={item.q}
                          style={{
                            border: "1px solid var(--color-border)",
                            borderRadius: "0.5rem",
                            backgroundColor: "#fff",
                            overflow: "hidden",
                          }}
                        >
                          <button
                            type="button"
                            onClick={() => toggleFaq(catIdx, itemIdx)}
                            style={{
                              width: "100%",
                              padding: "0.85rem 1.25rem",
                              display: "flex",
                              justifyContent: "space-between",
                              alignItems: "center",
                              background: "none",
                              border: "none",
                              textAlign: "left",
                              cursor: "pointer",
                              fontSize: "0.95rem",
                              fontWeight: 600,
                              color: "var(--color-text-main)",
                            }}
                          >
                            <span>{item.q}</span>
                            {isOpen ? <ChevronUp size={18} /> : <ChevronDown size={18} />}
                          </button>
                          {isOpen && (
                            <div
                              style={{
                                padding: "0.5rem 1.25rem 1rem",
                                fontSize: "0.9rem",
                                color: "var(--color-text-muted)",
                                lineHeight: 1.6,
                                borderTop: "1px solid #f3f4f6",
                              }}
                            >
                              {item.a}
                            </div>
                          )}
                        </div>
                      );
                    })}
                  </div>
                </div>
              );
            })
          )}
        </div>
      )}

      {/* Tab 2: Tickets (Orders) */}
      {activeTab === "tickets" && (
        <>
          {!authenticated && (
            <div className="access-state">
              <h2>Masuk untuk melihat dan membuat tiket pesanan</h2>
              <p>
                Tiket bantuan terhubung dengan transaksi pesanan Anda. Masuk ke akun Anda untuk berkonsultasi langsung dengan petugas bantuan kami.
              </p>
              <Link href="/login" className="ui-button ui-button-primary">
                Masuk ke akun
              </Link>
            </div>
          )}

          {authenticated && (
            <>
              <section className="section">
                <h2>Tiket saya</h2>
                {tickets.length ? (
                  <div className="card-grid">
                    {tickets.map((ticket) => (
                      <article className="card" key={ticket.id}>
                        <h3>
                          #{ticket.id} ·{" "}
                          {categories[ticket.category] || ticket.category}
                        </h3>
                        <p>Pesanan: {ticket.order_id}</p>
                        <p>
                          Status:{" "}
                          <span
                            style={{
                              padding: "0.15rem 0.4rem",
                              borderRadius: "4px",
                              fontWeight: 600,
                              fontSize: "0.8rem",
                              backgroundColor: ticket.status === "open" ? "#ecfdf5" : "#f3f4f6",
                              color: ticket.status === "open" ? "#059669" : "#6b7280",
                            }}
                          >
                            {ticket.status === "open" ? "Terbuka" : "Ditutup"}
                          </span>
                        </p>
                        <button type="button" onClick={() => openTicket(ticket.id)} className="ui-button ui-button-outline" style={{ marginTop: "0.5rem" }}>
                          Lihat percakapan
                        </button>
                      </article>
                    ))}
                  </div>
                ) : (
                  <p>Belum ada tiket bantuan yang diajukan.</p>
                )}
              </section>

              {selected && (
                <section className="section" style={{ backgroundColor: "#f9fafb", padding: "1.5rem", borderRadius: "0.75rem", margin: "1.5rem 0" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1rem" }}>
                    <div>
                      <h2 style={{ margin: 0 }}>Tiket #{selected.id}</h2>
                      <p style={{ margin: "0.2rem 0 0", color: "var(--color-text-muted)", fontSize: "0.85rem" }}>
                        Kategori: {categories[selected.category] || selected.category} · Pesanan: {selected.order_id}
                      </p>
                    </div>
                    <span
                      style={{
                        padding: "0.25rem 0.6rem",
                        borderRadius: "9999px",
                        fontSize: "0.8rem",
                        fontWeight: 600,
                        backgroundColor: selected.status === "open" ? "#ecfdf5" : "#f3f4f6",
                        color: selected.status === "open" ? "#059669" : "#4b5563",
                      }}
                    >
                      {selected.status === "open" ? "Menunggu Tanggapan" : "Ditutup"}
                    </span>
                  </div>

                  <div style={{ display: "flex", flexDirection: "column", gap: "0.75rem", marginBottom: "1.25rem" }}>
                    {selected.messages.map((entry) => (
                      <article
                        className="card"
                        key={entry.id}
                        style={{
                          backgroundColor: entry.author === "customer" ? "#eff6ff" : "#fff",
                          border: entry.author === "customer" ? "1px solid #bfdbfe" : "1px solid var(--color-border)",
                        }}
                      >
                        <p style={{ fontSize: "0.8rem", color: "var(--color-text-muted)", margin: "0 0 0.4rem 0" }}>
                          <strong>{entry.author === "customer" ? "Anda" : "Petugas Bantuan WisataDaerah"}</strong> ·{" "}
                          {new Date(entry.created_at).toLocaleString("id-ID")}
                        </p>
                        <p style={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere", margin: 0 }}>
                          {entry.body}
                        </p>
                        {entry.attachments?.map((attachment) => (
                          <p key={attachment.id} style={{ margin: "0.5rem 0 0 0" }}>
                            <a
                              href={`/api/v1/account/support-tickets/${selected.id}/attachments/${attachment.id}`}
                              style={{ color: "var(--color-primary)", textDecoration: "underline", fontSize: "0.85rem" }}
                            >
                              Unduh lampiran ({attachment.mime_type})
                            </a>
                          </p>
                        ))}
                      </article>
                    ))}
                  </div>

                  {selected.status === "open" && (
                    <form className="search-panel" onSubmit={addMessage} style={{ backgroundColor: "#fff", padding: "1.25rem", borderRadius: "0.5rem" }}>
                      <label>
                        Tulis Pesan Lanjutan
                        <textarea
                          name="message"
                          minLength={10}
                          maxLength={5000}
                          required
                          disabled={busy}
                          placeholder="Tuliskan detail pertanyaan atau keluhan Anda..."
                          style={{ minHeight: "80px" }}
                        />
                      </label>
                      <label>
                        Lampiran Gambar / Dokumen (Opsional: PDF/JPG/PNG, maks. 5 MB)
                        <input
                          type="file"
                          name="attachment"
                          accept=".pdf,.jpg,.jpeg,.png"
                          disabled={busy}
                        />
                      </label>
                      <button disabled={busy} className="ui-button ui-button-primary">
                        {busy ? "Mengirim…" : "Kirim pesan"}
                      </button>
                    </form>
                  )}
                </section>
              )}

              <section className="section" style={{ marginTop: "2rem" }}>
                <h2>Buat Tiket Bantuan Baru</h2>
                {orders.length ? (
                  <form className="search-panel" onSubmit={createTicket} style={{ backgroundColor: "#fff", padding: "1.5rem", borderRadius: "0.75rem", border: "1px solid var(--color-border)" }}>
                    <label>
                      Pilih Pesanan yang Berkendala
                      <select
                        value={orderId}
                        onChange={(event) => setOrderId(event.target.value)}
                        required
                      >
                        {orders.map((order) => (
                          <option key={order.order_id} value={order.order_id}>
                            {order.order_id} · {order.items[0]?.name || "Pesanan wisata"}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label>
                      Kategori Masalah
                      <select name="category" required>
                        {Object.entries(categories).map(([value, label]) => (
                          <option key={value} value={value}>
                            {label}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label>
                      Jelaskan Kendala Anda (Minimal 10 Karakter)
                      <textarea
                        name="message"
                        minLength={10}
                        maxLength={5000}
                        required
                        disabled={busy}
                        placeholder="Contoh: Saya sudah melakukan pembayaran via QRIS namun status pesanan belum berubah..."
                        style={{ minHeight: "100px" }}
                      />
                    </label>
                    <label>
                      Bukti Pembayaran / Lampiran (Opsional: PDF/JPG/PNG, maks. 5 MB)
                      <input
                        type="file"
                        name="attachment"
                        accept=".pdf,.jpg,.jpeg,.png"
                        disabled={busy}
                      />
                    </label>
                    <button disabled={busy} className="ui-button ui-button-primary">
                      {busy ? "Mengirim…" : "Kirim Tiket Bantuan"}
                    </button>
                  </form>
                ) : (
                  <p>
                    Anda belum memiliki pesanan aktif. Bantuan tiket dikhususkan untuk kendala pesanan. Untuk pertanyaan umum, lihat tab{" "}
                    <button
                      type="button"
                      onClick={() => setActiveTab("faq")}
                      style={{ color: "var(--color-primary)", textDecoration: "underline", background: "none", border: "none", cursor: "pointer", padding: 0 }}
                    >
                      Tanya Jawab & FAQ
                    </button>.
                  </p>
                )}
              </section>
            </>
          )}
        </>
      )}

      {message && (
        <p
          role="status"
          aria-live="polite"
          style={{
            margin: "1rem 0",
            padding: "0.75rem 1rem",
            backgroundColor: "#eff6ff",
            color: "#1e40af",
            borderRadius: "0.5rem",
            fontWeight: 500,
          }}
        >
          {message}
        </p>
      )}
    </Shell>
  );
}
