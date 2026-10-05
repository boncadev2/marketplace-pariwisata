"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import {
  Tag,
  CheckCircle2,
  AlertCircle,
  CreditCard,
  Loader2,
  Sparkles,
  UserRound,
  Mail,
  X,
  ArrowRight,
  ExternalLink,
} from "lucide-react";
import { apiRequest } from "../lib/api";

function formatMoney(value, currency = "IDR") {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency,
    maximumFractionDigits: 0,
  }).format(value || 0);
}

function generateIdempotencyKey() {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }
  return (
    "idemp-" +
    Date.now() +
    "-" +
    Math.random().toString(36).substring(2, 10) +
    Math.random().toString(36).substring(2, 10)
  );
}

export function PackageAvailability({ item }) {
  const router = useRouter();
  const [date, setDate] = useState("");
  const [quantity, setQuantity] = useState(item.minimum_participants);
  const [result, setResult] = useState(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);

  // Promos & Coupons
  const [availablePromos, setAvailablePromos] = useState([]);
  const [couponCode, setCouponCode] = useState("");
  const [appliedCoupon, setAppliedCoupon] = useState(null);
  const [couponMessage, setCouponMessage] = useState("");
  const [couponBusy, setCouponBusy] = useState(false);

  // User & Customer Info
  const [user, setUser] = useState(null);
  const [customerName, setCustomerName] = useState("");
  const [customerEmail, setCustomerEmail] = useState("");

  // Payment State
  const [paymentBusy, setPaymentBusy] = useState(false);
  const [paymentResult, setPaymentResult] = useState(null);

  // Load promos and user profile on mount
  useEffect(() => {
    const controller = new AbortController();

    // Load available sandbox promos
    apiRequest("/lookup/promos", { signal: controller.signal })
      .then((res) => {
        if (Array.isArray(res.data) && res.data.length > 0) {
          setAvailablePromos(res.data);
        }
      })
      .catch(() => {});

    // Load current user profile if logged in
    apiRequest("/me", { signal: controller.signal })
      .then((res) => {
        if (res?.data) {
          setUser(res.data);
          setCustomerName(res.data.name || "");
          setCustomerEmail(res.data.email || "");
        }
      })
      .catch(() => {});

    return () => controller.abort();
  }, []);

  async function check(event) {
    event.preventDefault();
    setBusy(true);
    setMessage("");
    setResult(null);
    setPaymentResult(null);
    try {
      const calendar = await apiRequest(
        `/products/${encodeURIComponent(item.slug)}/inventory?${new URLSearchParams({ from: date, to: date })}`
      );
      const day = calendar.data?.find((row) => row.session_key === "default");
      if (!day || day.is_closed || day.available < Number(quantity)) {
        setMessage(
          "Kuota untuk tanggal dan jumlah peserta ini belum tersedia. Silakan pilih tanggal lain."
        );
        return;
      }
      const quote = await apiRequest(
        `/products/${encodeURIComponent(item.slug)}/quote?${new URLSearchParams({ visit_date: date, quantity: String(quantity) })}`
      );
      setResult({
        available: day.available,
        total: quote.data.total,
        currency: quote.data.currency,
        date,
        quantity,
      });

      // If user had applied a coupon before re-checking, recalculate
      if (appliedCoupon) {
        recalculateCoupon(appliedCoupon.code, quote.data.total, date, quantity);
      }
    } catch (error) {
      setMessage(error.message || "Gagal memeriksa ketersediaan.");
    } finally {
      setBusy(false);
    }
  }

  async function recalculateCoupon(code, currentSubtotal, visitDate, visitQty) {
    const targetCode = (code || couponCode).trim().toUpperCase();
    if (!targetCode) return;

    setCouponBusy(true);
    setCouponMessage("");

    // If user is logged in with verified email, use backend quote
    if (user && user.email_verified_at) {
      try {
        const quoteRes = await apiRequest(
          `/promos/quote?${new URLSearchParams({
            product_slug: item.slug,
            visit_date: visitDate || date,
            quantity: String(visitQty || quantity),
            coupon_code: targetCode,
          })}`
        );
        setAppliedCoupon({
          code: targetCode,
          discount: quoteRes.data.discount,
          total: quoteRes.data.total,
          subtotal: quoteRes.data.subtotal,
        });
        setCouponCode(targetCode);
        setCouponMessage(`Kupon ${targetCode} berhasil digunakan!`);
      } catch (err) {
        setAppliedCoupon(null);
        setCouponMessage(err.message || "Kupon tidak dapat digunakan.");
      } finally {
        setCouponBusy(false);
      }
      return;
    }

    // Client-side preview from available promos
    const promo = availablePromos.find((p) => p.code.toUpperCase() === targetCode);
    if (!promo) {
      setCouponMessage("Kode kupon tidak ditemukan.");
      setCouponBusy(false);
      return;
    }

    const sub = currentSubtotal || result?.total || 0;
    if (promo.minimum_spend && sub < Number(promo.minimum_spend)) {
      setCouponMessage(
        `Nilai pesanan belum memenuhi minimum belanja ${formatMoney(promo.minimum_spend)}.`
      );
      setCouponBusy(false);
      return;
    }

    let discount = 0;
    if (promo.discount_type === "percentage") {
      discount = Math.round(sub * (Number(promo.discount_value) / 100));
      if (promo.maximum_discount) {
        discount = Math.min(discount, Number(promo.maximum_discount));
      }
    } else {
      discount = Number(promo.discount_value);
    }

    const total = Math.max(1, sub - discount);
    setAppliedCoupon({
      code: targetCode,
      discount,
      total,
      subtotal: sub,
    });
    setCouponCode(targetCode);
    if (!user) {
      setCouponMessage("Kupon diterapkan. Masuk ke akun Anda sebelum pembayaran.");
    } else if (!user.email_verified_at) {
      setCouponMessage("Verifikasi email akun Anda sebelum memakai kupon.");
    } else {
      setCouponMessage(`Kupon ${targetCode} berhasil digunakan!`);
    }
    setCouponBusy(false);
  }

  function removeCoupon() {
    setAppliedCoupon(null);
    setCouponCode("");
    setCouponMessage("");
  }

  async function handleProceedPayment() {
    if (!result) return;
    if (!customerName.trim()) {
      setMessage("Harap isi nama lengkap pemesan.");
      return;
    }
    if (!customerEmail.trim()) {
      setMessage("Harap isi email pemesan.");
      return;
    }

    if (appliedCoupon && (!user || !user.email_verified_at)) {
      setMessage(
        "Kupon memerlukan akun dengan email terverifikasi. Silakan masuk ke akun Anda terlebih dahulu."
      );
      return;
    }

    setPaymentBusy(true);
    setMessage("");

    try {
      const payload = {
        product_slug: item.slug,
        visit_date: result.date,
        quantity: Number(result.quantity),
        customer_name: customerName.trim(),
        customer_email: customerEmail.trim(),
      };

      if (appliedCoupon) {
        payload.coupon_code = appliedCoupon.code;
        payload.expected_total = appliedCoupon.total;
      }

      const res = await apiRequest("/checkout", {
        method: "POST",
        headers: {
          "Idempotency-Key": generateIdempotencyKey(),
        },
        body: JSON.stringify(payload),
      });

      const orderData = res.data;
      setPaymentResult(orderData);

      // If a checkout URL exists (e.g. Midtrans Snap or payment instructions), redirect immediately!
      if (orderData.checkout_url) {
        if (orderData.checkout_url.startsWith("/")) {
          router.push(orderData.checkout_url);
        } else {
          window.location.assign(orderData.checkout_url);
        }
      } else if (orderData.order_id) {
        router.push(`/pembayaran/${orderData.order_id}`);
      }
    } catch (err) {
      setMessage(err.message || "Gagal memproses pembayaran. Silakan coba lagi.");
    } finally {
      setPaymentBusy(false);
    }
  }

  const subtotal = result?.total || 0;
  const discount = appliedCoupon?.discount || 0;
  const finalTotal = appliedCoupon ? appliedCoupon.total : subtotal;

  return (
    <div>
      <form onSubmit={check}>
        <fieldset disabled={busy || paymentBusy} style={{ border: 0, padding: 0 }}>
          <label className="travel-field">
            Tanggal keberangkatan
            <input
              type="date"
              min={new Intl.DateTimeFormat("en-CA", {
                timeZone: "Asia/Jakarta",
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
              }).format(new Date())}
              required
              value={date}
              onChange={(event) => {
                setDate(event.target.value);
                setResult(null);
                setMessage("");
                setPaymentResult(null);
              }}
            />
          </label>
          <label className="travel-field">
            Jumlah peserta
            <input
              type="number"
              required
              min={item.minimum_participants}
              max={Math.min(100, item.maximum_participants)}
              value={quantity}
              onChange={(event) => {
                setQuantity(event.target.value);
                setResult(null);
                setMessage("");
                setPaymentResult(null);
              }}
            />
          </label>
          {!result && (
            <button type="submit" className="ui-button w-full" disabled={busy}>
              {busy ? (
                <span className="flex items-center justify-center gap-2">
                  <Loader2 className="animate-spin" size={16} /> Memeriksa ketersediaan…
                </span>
              ) : (
                "Periksa ketersediaan"
              )}
            </button>
          )}
        </fieldset>
      </form>

      {message && (
        <div
          className="mt-3 flex items-start gap-2 rounded-xl bg-red-50 p-3 text-xs text-red-700"
          role="alert"
        >
          <AlertCircle size={16} className="mt-0.5 shrink-0 text-red-600" />
          <span>{message}</span>
        </div>
      )}

      {/* Tampilan Setelah Ketersediaan Diperiksa */}
      {result && (
        <div className="mt-4 space-y-4">
          {/* Status Kuota & Ringkasan Awal */}
          <div className="rounded-xl border border-blue-200 bg-blue-50/80 p-4 text-blue-900">
            <div className="flex items-center justify-between text-xs font-semibold">
              <span className="flex items-center gap-1.5 text-emerald-800">
                <CheckCircle2 size={16} /> Kuota tersedia ({result.available} sisa)
              </span>
              <button
                type="button"
                onClick={() => {
                  setResult(null);
                  removeCoupon();
                }}
                className="text-blue-700 hover:underline"
              >
                Ubah tanggal
              </button>
            </div>
            <p className="mt-1 text-xs text-blue-800">
              {result.quantity} peserta · {result.date}
            </p>
          </div>

          {/* Kupon & Promo sebelum pembayaran */}
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex items-center gap-2 font-bold text-slate-800 text-sm">
              <Tag size={16} className="text-blue-700" />
              <span>Kupon & Promo Diskon</span>
            </div>

            {/* List kupon yang tersedia */}
            {availablePromos.length > 0 && !appliedCoupon && (
              <div className="mt-3 space-y-2">
                <span className="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                  Promo Tersedia
                </span>
                {availablePromos.map((promo) => (
                  <div
                    key={promo.code}
                    className="flex items-center justify-between rounded-lg border border-dashed border-blue-300 bg-blue-50/50 p-2.5 transition hover:bg-blue-50"
                  >
                    <div>
                      <span className="font-mono text-xs font-bold text-blue-900">
                        {promo.code}
                      </span>
                      <p className="text-[11px] text-slate-600">
                        {promo.name}
                        {promo.discount_type === "percentage" &&
                          ` · Diskon ${promo.discount_value}%`}
                        {promo.maximum_discount &&
                          ` (maks ${formatMoney(promo.maximum_discount)})`}
                      </p>
                    </div>
                    <button
                      type="button"
                      onClick={() => recalculateCoupon(promo.code, subtotal, date, quantity)}
                      disabled={couponBusy}
                      className="rounded-md bg-blue-700 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-800 transition"
                    >
                      Gunakan
                    </button>
                  </div>
                ))}
              </div>
            )}

            {/* Input manual kode kupon */}
            {!appliedCoupon ? (
              <div className="mt-3 flex gap-2">
                <input
                  type="text"
                  placeholder="Punya kode kupon? Contoh: DEMO10"
                  value={couponCode}
                  onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
                  className="w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-mono uppercase focus:border-blue-700 focus:outline-none"
                />
                <button
                  type="button"
                  onClick={() => recalculateCoupon(couponCode, subtotal, date, quantity)}
                  disabled={couponBusy || !couponCode.trim()}
                  className="shrink-0 rounded-lg border border-blue-700 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50 disabled:opacity-50 transition"
                >
                  {couponBusy ? <Loader2 className="animate-spin" size={14} /> : "Terapkan"}
                </button>
              </div>
            ) : (
              <div className="mt-3 flex items-center justify-between rounded-lg bg-emerald-50 border border-emerald-300 p-2.5">
                <div className="flex items-center gap-2">
                  <Sparkles size={16} className="text-emerald-700" />
                  <div>
                    <span className="font-mono text-xs font-bold text-emerald-900">
                      {appliedCoupon.code}
                    </span>
                    <span className="ml-2 text-xs text-emerald-800 font-semibold">
                      Hemat {formatMoney(discount)}
                    </span>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={removeCoupon}
                  title="Hapus kupon"
                  className="rounded p-1 text-slate-400 hover:bg-emerald-100 hover:text-red-600"
                >
                  <X size={15} />
                </button>
              </div>
            )}

            {couponMessage && (
              <p
                className={`mt-2 text-xs ${
                  appliedCoupon ? "text-emerald-800 font-medium" : "text-amber-800"
                }`}
              >
                {couponMessage}
              </p>
            )}

            {!user && appliedCoupon && (
              <div className="mt-2 text-xs text-slate-600">
                Promo kupon memerlukan akun terverifikasi.{" "}
                <Link href="/login" className="font-semibold text-blue-700 underline">
                  Masuk ke akun
                </Link>
              </div>
            )}
          </div>

          {/* Form Data Pemesan */}
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 className="text-xs font-bold text-slate-700 uppercase tracking-wide mb-2.5">
              Data Pemesan
            </h3>
            {user ? (
              <div className="rounded-lg bg-slate-50 p-2.5 text-xs text-slate-700 flex items-center justify-between">
                <div>
                  <p className="font-semibold text-slate-900">{customerName}</p>
                  <p className="text-slate-500">{customerEmail}</p>
                </div>
                <span className="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                  {user.email_verified_at ? "Terverifikasi" : "Belum verifikasi"}
                </span>
              </div>
            ) : (
              <div className="space-y-2">
                <div>
                  <label className="text-xs font-semibold text-slate-700 block mb-1">
                    Nama lengkap
                  </label>
                  <div className="relative">
                    <UserRound
                      size={15}
                      className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                    />
                    <input
                      type="text"
                      required
                      placeholder="Nama pemesan"
                      value={customerName}
                      onChange={(e) => setCustomerName(e.target.value)}
                      className="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-xs focus:border-blue-700 focus:outline-none"
                    />
                  </div>
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-700 block mb-1">
                    Alamat email
                  </label>
                  <div className="relative">
                    <Mail
                      size={15}
                      className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                    />
                    <input
                      type="email"
                      required
                      placeholder="email@contoh.com"
                      value={customerEmail}
                      onChange={(e) => setCustomerEmail(e.target.value)}
                      className="w-full rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-xs focus:border-blue-700 focus:outline-none"
                    />
                  </div>
                </div>
                <p className="text-[11px] text-slate-500">
                  Sudah punya akun?{" "}
                  <Link href="/login" className="text-blue-700 font-semibold underline">
                    Masuk di sini
                  </Link>
                </p>
              </div>
            )}
          </div>

          {/* Rincian Harga Akhir */}
          <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="space-y-2 text-xs text-slate-700">
              <div className="flex justify-between">
                <span>Subtotal ({result.quantity} peserta)</span>
                <span className="font-semibold">{formatMoney(subtotal)}</span>
              </div>
              {appliedCoupon && (
                <div className="flex justify-between text-emerald-800 font-medium">
                  <span>Diskon Kupon ({appliedCoupon.code})</span>
                  <span>− {formatMoney(discount)}</span>
                </div>
              )}
              <div className="border-t border-slate-200 pt-2 flex justify-between items-baseline">
                <span className="text-sm font-bold text-slate-900">Total Pembayaran</span>
                <span className="text-lg font-black text-blue-700">
                  {formatMoney(finalTotal)}
                </span>
              </div>
            </div>

            {/* Tombol Lanjut ke Pembayaran */}
            <div className="mt-4">
              <button
                type="button"
                onClick={handleProceedPayment}
                disabled={paymentBusy}
                className="w-full flex items-center justify-center gap-2 rounded-xl bg-blue-700 py-3.5 px-4 font-bold text-white shadow-md hover:bg-blue-800 disabled:opacity-60 transition"
              >
                {paymentBusy ? (
                  <>
                    <Loader2 className="animate-spin" size={18} />
                    <span>Menyiapkan pembayaran…</span>
                  </>
                ) : (
                  <>
                    <CreditCard size={18} />
                    <span>Lanjut ke Pembayaran</span>
                    <ArrowRight size={16} />
                  </>
                )}
              </button>
            </div>

            {paymentResult?.checkout_url && (
              <div className="mt-3 rounded-lg bg-emerald-50 p-3 text-xs text-emerald-900 text-center">
                <p className="font-semibold">Pesanan berhasil dibuat!</p>
                <p className="mt-0.5 text-slate-600">
                  Membuka halaman pembayaran... Jika tidak otomatis terbuka:
                </p>
                <a
                  href={paymentResult.checkout_url}
                  className="mt-2 inline-flex items-center gap-1.5 font-bold text-blue-700 underline"
                >
                  Buka Halaman Pembayaran <ExternalLink size={14} />
                </a>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
