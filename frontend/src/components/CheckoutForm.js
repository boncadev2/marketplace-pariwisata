"use client";

import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import { AlertCircle, CheckCircle2, Loader2, WifiOff } from "lucide-react";
import { apiRequest } from "../lib/api";

const initialForm = {
  product_slug: "",
  visit_date: "",
  quantity: "1",
  customer_name: "",
  customer_email: "",
  customer_phone: "",
  coupon_code: "",
};

function subscribeToConnectivity(callback) {
  window.addEventListener("online", callback);
  window.addEventListener("offline", callback);

  return () => {
    window.removeEventListener("online", callback);
    window.removeEventListener("offline", callback);
  };
}

function getConnectivitySnapshot() {
  return navigator.onLine;
}

function getServerConnectivitySnapshot() {
  return true;
}

function formatMoney(value, currency = "IDR") {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(value);
}

export function CheckoutForm({
  initialProduct = "",
  initialDate = "",
  initialQuantity = 1,
}) {
  const [form, setForm] = useState({
    ...initialForm,
    product_slug: initialProduct,
    visit_date: initialDate,
    quantity: String(initialQuantity),
  });
  const [products, setProducts] = useState([]);
  const [quote, setQuote] = useState(null);
  const [result, setResult] = useState(null);
  const [pendingCheckout, setPendingCheckout] = useState(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState("");
  const [checkoutStatus, setCheckoutStatus] = useState({
    enabled: false,
    loading: true,
    reason: "Memeriksa status checkout…",
  });
  const [promos, setPromos] = useState([]);
  const [participants, setParticipants] = useState([]);
  const [showParticipantsForm, setShowParticipantsForm] = useState(false);
  const online = useSyncExternalStore(
    subscribeToConnectivity,
    getConnectivitySnapshot,
    getServerConnectivitySnapshot
  );
  const idempotencyKey = useRef(null);

  useEffect(() => {
    const controller = new AbortController();
    fetch("/api/v1/products?per_page=50", {
      headers: { Accept: "application/json" },
      signal: controller.signal,
    })
      .then((response) => {
        if (!response.ok) throw new Error();
        return response.json();
      })
      .then((payload) => setProducts(payload.data ?? []))
      .catch((error) => {
        if (error.name !== "AbortError")
          setMessage(
            "Daftar produk belum dapat dimuat. Periksa koneksi lalu muat ulang halaman."
          );
      });

    return () => {
      controller.abort();
    };
  }, []);

  useEffect(() => {
    Promise.all([
      apiRequest("/pilot/checkout-status"),
      apiRequest("/payments/gateway-status"),
    ])
      .then(([pilot, gateway]) =>
        setCheckoutStatus({
          ...pilot.data,
          enabled: pilot.data.enabled && gateway.data.ready,
          loading: false,
          payment_provider: gateway.data.provider,
          reason: !gateway.data.ready
            ? "Layanan pembayaran belum siap. Hubungi pengelola aplikasi."
            : pilot.data.reason,
        })
      )
      .catch(() =>
        setCheckoutStatus({
          enabled: false,
          loading: false,
          reason: "Status checkout tidak dapat diverifikasi.",
        })
      );

    apiRequest("/lookup/promos")
      .then((res) => {
        if (Array.isArray(res?.data)) setPromos(res.data);
      })
      .catch(() => {});

    apiRequest("/me")
      .then((res) => {
        if (res?.data) {
          setForm((current) => ({
            ...current,
            customer_name: current.customer_name || res.data.name || "",
            customer_email: current.customer_email || res.data.email || "",
          }));
        }
      })
      .catch(() => {});
  }, []);

  function updateField(event) {
    const { name, value } = event.target;
    setForm((current) => ({ ...current, [name]: value }));
    setQuote(null);
    setResult(null);
    setMessage("");
  }

  async function requestWithTimeout(path, options = {}) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
      return await apiRequest(path, { ...options, signal: controller.signal });
    } finally {
      clearTimeout(timeout);
    }
  }

  async function checkQuote() {
    if (!form.product_slug || !form.visit_date || !form.quantity) {
      setMessage(
        "Pilih produk, tanggal kunjungan, dan jumlah terlebih dahulu."
      );
      return;
    }

    setBusy("quote");
    setMessage("Memeriksa harga terbaru dari server…");
    try {
      const productQuote = await requestWithTimeout(
        `/products/${encodeURIComponent(form.product_slug)}/quote?visit_date=${encodeURIComponent(form.visit_date)}&quantity=${encodeURIComponent(form.quantity)}`
      );
      if (form.coupon_code.trim()) {
        const payload = await requestWithTimeout(
          `/promos/quote?${new URLSearchParams({ product_slug: form.product_slug, visit_date: form.visit_date, quantity: form.quantity, coupon_code: form.coupon_code.trim() })}`
        );
        setQuote({
          ...payload.data,
          cross_village: productQuote.data.cross_village,
        });
      } else {
        setQuote(productQuote.data);
      }
      setMessage(
        "Harga terbaru berhasil diperiksa. Harga dan stok akan divalidasi kembali saat pemesanan."
      );
    } catch (error) {
      setMessage(
        error.name === "AbortError"
          ? "Koneksi terlalu lambat. Silakan coba periksa harga lagi."
          : error.message
      );
    } finally {
      setBusy("");
    }
  }

  async function submitCheckout(event) {
    event.preventDefault();
    if (!online) {
      setMessage(
        "Perangkat sedang offline. Data form tetap tersimpan; kirim kembali setelah terhubung."
      );
      return;
    }
    if (!checkoutStatus.enabled) {
      setMessage(
        checkoutStatus.reason || "Checkout sedang ditutup oleh operator pilot."
      );
      return;
    }

    if (form.coupon_code.trim() && !pendingCheckout && !quote?.coupon_code) {
      setMessage("Periksa harga dengan kupon sebelum membuat pesanan.");
      return;
    }

    if (
      !pendingCheckout &&
      quote?.cross_village &&
      (!quote.cross_village.available || !quote.cross_village.all_accepted)
    ) {
      setMessage(
        "Paket lintas desa belum tersedia untuk checkout: persetujuan mitra harus lengkap dan pemesanan hanya tersedia di sandbox lokal."
      );
      return;
    }

    setBusy("checkout");
    setResult(null);
    setMessage(
      "Memvalidasi harga dan stok di server. Jangan tutup halaman ini…"
    );
    idempotencyKey.current ||= crypto.randomUUID();
    const validParticipants = participants.filter((p) => p.name?.trim());
    const body =
      pendingCheckout ??
      JSON.stringify({
        ...form,
        quantity: Number(form.quantity),
        participants: validParticipants.length > 0 ? validParticipants : undefined,
        expected_total:
          form.coupon_code.trim() || quote?.cross_village
            ? quote?.total
            : undefined,
        cross_village_version: quote?.cross_village?.version,
      });
    setPendingCheckout(body);

    try {
      const payload = await requestWithTimeout("/checkout", {
        method: "POST",
        headers: { "Idempotency-Key": idempotencyKey.current },
        body,
      });
      setResult(payload.data);
      setMessage(
        "Pesanan berhasil dibuat. Membuka pembayaran…"
      );
      idempotencyKey.current = null;
      setPendingCheckout(null);
      if (payload.data?.checkout_url) {
        window.location.href = payload.data.checkout_url;
      }
    } catch (error) {
      if (error.status && error.status < 500) {
        idempotencyKey.current = null;
        setPendingCheckout(null);
        if (error.status === 409) setQuote(null);
      }
      setMessage(
        error.name === "AbortError"
          ? "Respons belum diterima. Tekan kirim lagi; sistem memakai kunci yang sama agar pesanan tidak ganda."
          : error.message
      );
    } finally {
      setBusy("");
    }
  }

  return (
    <div className="checkout-layout grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
      <form
        onSubmit={submitCheckout}
        className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm md:p-8"
        aria-busy={Boolean(busy)}
      >
        <div className="form-section-heading">
          <span>01</span>
          <div>
            <h2>Pilihan & data pemesan</h2>
            <p>Isi data sesuai rencana perjalanan Anda.</p>
          </div>
        </div>
        {!online && (
          <p
            className="mb-5 flex items-center gap-2 rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-900"
            role="alert"
          >
            <WifiOff size={18} /> Anda sedang offline. Form tidak akan dikirim
            sampai koneksi kembali.
          </p>
        )}
        {!checkoutStatus.loading && !checkoutStatus.enabled && (
          <p
            className="mb-5 flex items-center gap-2 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-900"
            role="alert"
          >
            <AlertCircle size={18} /> Checkout sedang ditutup.{" "}
            {checkoutStatus.reason}
          </p>
        )}

        {checkoutStatus.payment_provider === "midtrans_sandbox" && (
          <p className="mb-5 rounded-xl bg-blue-50 p-3 text-sm text-blue-900">
            Pembayaran melalui Midtrans sandbox. Gunakan metode pembayaran
            simulasi; jangan transfer uang nyata.
          </p>
        )}
        <fieldset
          disabled={Boolean(busy) || Boolean(pendingCheckout)}
          className="grid gap-5 sm:grid-cols-2 disabled:opacity-60"
        >
          <label
            className="sm:col-span-2 font-semibold text-gray-800"
            htmlFor="product_slug"
          >
            Produk
            <select
              id="product_slug"
              name="product_slug"
              value={form.product_slug}
              onChange={updateField}
              required
              className="mt-2 min-h-12 w-full rounded-xl border border-gray-300 px-4 font-normal"
            >
              <option value="">Pilih produk dari katalog</option>
              {initialProduct &&
                !products.some(
                  (product) => product.slug === initialProduct
                ) && (
                  <option value={initialProduct}>
                    Produk pilihan Anda — periksa ketersediaan
                  </option>
                )}
              {products.map((product) => (
                <option key={product.id} value={product.slug}>
                  {product.name}
                </option>
              ))}
            </select>
          </label>

          <label className="font-semibold text-gray-800" htmlFor="visit_date">
            Tanggal kunjungan
            <input
              id="visit_date"
              name="visit_date"
              type="date"
              value={form.visit_date}
              onChange={updateField}
              required
              className="mt-2 min-h-12 w-full rounded-xl border border-gray-300 px-4 font-normal"
            />
          </label>

          <label className="font-semibold text-gray-800" htmlFor="quantity">
            Jumlah pengunjung
            <input
              id="quantity"
              name="quantity"
              type="number"
              min="1"
              max="100"
              inputMode="numeric"
              value={form.quantity}
              onChange={updateField}
              required
              className="mt-2 min-h-12 w-full rounded-xl border border-gray-300 px-4 font-normal"
            />
          </label>

          <label
            className="font-semibold text-gray-800"
            htmlFor="customer_name"
          >
            Nama pemesan
            <input
              id="customer_name"
              name="customer_name"
              autoComplete="name"
              value={form.customer_name}
              onChange={updateField}
              required
              maxLength={120}
              className="mt-2 min-h-12 w-full rounded-xl border border-gray-300 px-4 font-normal"
            />
          </label>

          <label
            className="font-semibold text-gray-800"
            htmlFor="customer_email"
          >
            Email pemesan
            <input
              id="customer_email"
              name="customer_email"
              type="email"
              autoComplete="email"
              inputMode="email"
              value={form.customer_email}
              onChange={updateField}
              required
              maxLength={255}
              className="mt-2 min-h-12 w-full rounded-xl border border-gray-300 px-4 font-normal"
            />
          </label>

          <label
            className="sm:col-span-2 font-semibold text-gray-800"
            htmlFor="customer_phone"
          >
            Nomor WhatsApp (Opsional, untuk pengiriman E-Tiket)
            <input
              id="customer_phone"
              name="customer_phone"
              type="tel"
              autoComplete="tel"
              inputMode="tel"
              placeholder="Contoh: 081234567890"
              value={form.customer_phone}
              onChange={updateField}
              maxLength={30}
              className="mt-2 min-h-12 w-full rounded-xl border border-gray-300 px-4 font-normal"
            />
            <span className="mt-1 block text-xs font-normal text-gray-500">
              Voucher dan QR Code masuk akan dikirimkan langsung ke WhatsApp Anda begitu pembayaran terverifikasi.
            </span>
          </label>
          <label
            className="sm:col-span-2 font-semibold text-gray-800"
            htmlFor="coupon_code"
          >
            Kode kupon (opsional, sandbox)
            <input
              id="coupon_code"
              name="coupon_code"
              value={form.coupon_code}
              onChange={updateField}
              maxLength={100}
              autoComplete="off"
              placeholder="Contoh kupon demo: DEMO10"
              className="mt-2 min-h-12 w-full rounded-xl border border-gray-300 px-4 font-normal"
            />
            {promos.length > 0 && (
              <div className="mt-2.5 flex flex-wrap items-center gap-2">
                <span className="text-xs font-semibold text-gray-500">
                  Kupon tersedia:
                </span>
                {promos.map((p) => (
                  <button
                    key={p.code}
                    type="button"
                    onClick={() => {
                      setForm((prev) => ({ ...prev, coupon_code: p.code }));
                      setQuote(null);
                    }}
                    className="inline-flex items-center gap-1 rounded-lg border border-dashed border-blue-400 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-900 hover:bg-blue-100 transition"
                  >
                    🏷️ {p.code} ({p.discount_type === "percentage" ? `Diskon ${p.discount_value}%` : `Diskon ${formatMoney(p.discount_value)}`})
                  </button>
                ))}
              </div>
            )}
            <span className="mt-2 block text-sm font-normal text-gray-600">
              Kupon memerlukan login dan email akun terverifikasi. Email pemesan
              harus sama dengan akun. Promo belum tersedia untuk transaksi
              produksi.
            </span>
          </label>

          {/* Manifest Peserta Rombongan (Opsional saat checkout) */}
          <div className="sm:col-span-2 p-4 bg-slate-50 border border-slate-200 rounded-xl">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <div>
                <span className="font-semibold text-gray-800 text-sm block">
                  Manifest Peserta Rombongan ({form.quantity} Orang)
                </span>
                <span className="text-xs text-gray-500">
                  Opsional: Anda dapat melengkapi nama anggota rombongan sekarang, atau mengisinya nanti di dasbor akun.
                </span>
              </div>
              <button
                type="button"
                onClick={() => {
                  const nextState = !showParticipantsForm;
                  setShowParticipantsForm(nextState);
                  if (nextState && participants.length === 0) {
                    const rows = [];
                    const qty = Number(form.quantity) || 1;
                    for (let i = 0; i < qty; i++) {
                      rows.push({
                        name: i === 0 ? form.customer_name : "",
                        id_number: "",
                        phone: "",
                        notes: "",
                      });
                    }
                    setParticipants(rows);
                  }
                }}
                className="self-start sm:self-auto text-xs font-semibold text-blue-700 bg-white border border-blue-200 hover:bg-blue-50 px-3 py-1.5 rounded-lg transition"
              >
                {showParticipantsForm ? "Tutup Form Manifest" : "Isi Manifest Sekarang"}
              </button>
            </div>

            {showParticipantsForm && (
              <div className="mt-4 space-y-3">
                {participants.map((p, idx) => (
                  <div key={idx} className="p-3 bg-white border border-gray-200 rounded-lg text-xs space-y-2">
                    <span className="font-bold text-gray-700 block">
                      Peserta #{idx + 1} {idx === 0 ? "(Pemesan Utama)" : ""}
                    </span>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                      <input
                        type="text"
                        placeholder="Nama Lengkap (sesuai KTP/Paspor)"
                        value={p.name}
                        onChange={(e) => {
                          const val = e.target.value;
                          setParticipants((prev) =>
                            prev.map((item, i) =>
                              i === idx ? { ...item, name: val } : item
                            )
                          );
                        }}
                        className="p-2 border border-gray-300 rounded-md w-full"
                      />
                      <input
                        type="text"
                        placeholder="NIK / No. Identitas (Opsional)"
                        value={p.id_number}
                        onChange={(e) => {
                          const val = e.target.value;
                          setParticipants((prev) =>
                            prev.map((item, i) =>
                              i === idx ? { ...item, id_number: val } : item
                            )
                          );
                        }}
                        className="p-2 border border-gray-300 rounded-md w-full"
                      />
                    </div>
                  </div>
                ))}
                <button
                  type="button"
                  onClick={() =>
                    setParticipants((prev) => [
                      ...prev,
                      { name: "", id_number: "", phone: "", notes: "" },
                    ])
                  }
                  className="w-full py-1.5 border border-dashed border-blue-300 bg-blue-50/50 hover:bg-blue-50 text-blue-700 rounded-lg font-semibold text-xs transition"
                >
                  + Tambah Baris Peserta
                </button>
              </div>
            )}
          </div>
        </fieldset>

        <div className="mt-6 flex flex-col gap-3 sm:flex-row">
          <button
            type="button"
            onClick={checkQuote}
            disabled={Boolean(busy) || Boolean(pendingCheckout)}
            className="min-h-12 rounded-xl border border-blue-700 px-5 font-bold text-emerald-800 disabled:opacity-60"
          >
            {busy === "quote" ? (
              <span className="flex items-center justify-center gap-2">
                <Loader2 className="animate-spin" size={18} /> Memeriksa…
              </span>
            ) : (
              "Periksa harga"
            )}
          </button>
          <button
            type="submit"
            disabled={Boolean(busy) || !online || !checkoutStatus.enabled}
            className="min-h-12 flex-1 rounded-xl bg-blue-700 px-5 font-bold text-white disabled:opacity-60"
          >
            {busy === "checkout" ? (
              <span className="flex items-center justify-center gap-2">
                <Loader2 className="animate-spin" size={18} /> Memproses pembayaran…
              </span>
            ) : checkoutStatus.loading ? (
              "Memeriksa status…"
            ) : (
              "Lanjut ke Pembayaran"
            )}
          </button>
        </div>

        {message && (
          <p
            className="mt-5 flex items-start gap-2 text-sm text-gray-700"
            role="status"
            aria-live="polite"
          >
            <AlertCircle size={18} className="mt-0.5 shrink-0 text-blue-700" />{" "}
            {message}
          </p>
        )}
      </form>

      <aside
        className="checkout-summary rounded-2xl border border-gray-200 bg-white p-5 shadow-sm md:p-6"
        aria-label="Ringkasan checkout"
      >
        <h2 className="text-xl font-bold text-gray-900">
          Ringkasan perjalanan
        </h2>
        {quote ? (
          <dl className="mt-4 space-y-3 text-sm">
            <div className="flex justify-between gap-3">
              <dt>Harga satuan</dt>
              <dd className="font-semibold">
                {formatMoney(quote.unit_price, quote.currency)}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt>Jumlah</dt>
              <dd className="font-semibold">{quote.quantity}</dd>
            </div>
            {quote.coupon_code && (
              <>
                <div className="flex justify-between gap-3">
                  <dt>Subtotal</dt>
                  <dd>{formatMoney(quote.subtotal, quote.currency)}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt>Diskon {quote.coupon_code}</dt>
                  <dd>−{formatMoney(quote.discount, quote.currency)}</dd>
                </div>
              </>
            )}
            {quote.cross_village && (
              <div className="rounded-xl bg-amber-50 p-3 text-amber-900">
                <dt className="font-bold">Paket lintas desa · simulasi</dt>
                <dd>
                  Revisi {quote.cross_village.revision}.{" "}
                  {quote.cross_village.all_accepted
                    ? "Persetujuan mitra lengkap."
                    : "Menunggu persetujuan mitra."}{" "}
                  {!quote.cross_village.available &&
                    "Pemesanan sandbox tidak tersedia di lingkungan ini."}{" "}
                  Porsi akan disimpan saat checkout.
                </dd>
              </div>
            )}
            <div className="flex justify-between gap-3 border-t pt-3 text-base">
              <dt>Total estimasi</dt>
              <dd className="font-bold text-emerald-800">
                {formatMoney(quote.total, quote.currency)}
              </dd>
            </div>
          </dl>
        ) : (
          <p className="mt-3 text-sm text-gray-600">
            Pilih produk, tanggal, dan jumlah pengunjung, lalu periksa harga
            untuk melihat rincian biaya. Harga serta ketersediaan diperiksa
            kembali saat pemesanan.
          </p>
        )}

        {result && (
          <div className="mt-6 border-t pt-5" role="status">
            <p className="flex items-center gap-2 font-bold text-emerald-800">
              <CheckCircle2 size={20} /> Pesanan dibuat
            </p>
            <dl className="mt-3 space-y-3 text-sm">
              <div>
                <dt className="text-gray-600">ID pesanan</dt>
                <dd className="break-all font-mono font-semibold">
                  {result.order_id}
                </dd>
              </div>
              <div>
                <dt className="text-gray-600">Kode akses tamu</dt>
                <dd className="break-all font-mono font-semibold">
                  {result.guest_access_token}
                </dd>
              </div>
              <div>
                <dt className="text-gray-600">Total pembayaran</dt>
                <dd className="font-semibold">
                  {formatMoney(result.total, result.currency)}
                </dd>
              </div>
              {result.cross_village && (
                <div>
                  <dt className="text-gray-600">Porsi lintas desa</dt>
                  <dd>
                    Snapshot revisi {result.cross_village.revision} tersimpan
                    saat checkout sandbox.
                  </dd>
                </div>
              )}
              {result.promotion && (
                <div>
                  <dt className="text-gray-600">Kupon</dt>
                  <dd>
                    {result.promotion.coupon_code} · diskon{" "}
                    {formatMoney(result.promotion.discount, result.currency)}
                  </dd>
                </div>
              )}
              <div>
                <dt className="text-gray-600">Penyedia pembayaran</dt>
                <dd>
                  {result.payment_provider === "midtrans_sandbox"
                    ? "Midtrans sandbox"
                    : result.payment_provider === "midtrans_production"
                      ? "Midtrans"
                      : "Simulasi internal"}
                </dd>
              </div>
              <div>
                <dt className="text-gray-600">Status pembayaran</dt>
                <dd className="font-semibold">{result.payment_status}</dd>
              </div>
            </dl>
            {result.payment_status === "uncertain" && (
              <p className="mt-3 text-sm text-amber-800">
                Hasil permintaan pembayaran belum pasti. Sistem akan memeriksa
                penyedia; jangan membuat pesanan baru untuk mencoba lagi.
              </p>
            )}
            {result.checkout_url && (
              <a
                href={result.checkout_url}
                className="mt-5 flex min-h-12 items-center justify-center rounded-xl bg-blue-700 px-5 font-bold text-white hover:bg-blue-800"
              >
                Buka instruksi pembayaran
              </a>
            )}
          </div>
        )}
      </aside>
    </div>
  );
}
