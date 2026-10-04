"use client";
import Link from "next/link";
import { ReservationPayment } from "./ReservationPayment";
import { useEffect, useRef, useState } from "react";
import { ShoppingBag, Loader2 } from "lucide-react";
import { apiRequest } from "../lib/api";
export function UmkmOrderForm({ product }) {
  const [profile, setProfile] = useState(null);
  const [checking, setChecking] = useState(true);
  const [authError, setAuthError] = useState("");
  const [quantity, setQuantity] = useState(1);
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [fulfillment, setFulfillment] = useState("pickup"),
    [address, setAddress] = useState(""),
    [postalCode, setPostalCode] = useState("");
  const [quotes, setQuotes] = useState([]),
    [quoteId, setQuoteId] = useState(""),
    [quoteBusy, setQuoteBusy] = useState(false);
  const selectedQuote = quotes.find(
    (quote) =>
      quote.public_id === quoteId &&
      quote.quantity === Number(quantity) &&
      quote.postal_code === postalCode
  );
  const shippingFee = product.shipping_provider_available
    ? selectedQuote?.fee
    : product.shipping_fee;
  async function checkRates() {
    setQuoteBusy(true);
    setMessage("");
    setQuotes([]);
    setQuoteId("");
    try {
      const result = await apiRequest("/account/umkm-shipping/quotes", {
        method: "POST",
        body: JSON.stringify({
          product_slug: product.slug,
          quantity: Number(quantity),
          postal_code: postalCode,
        }),
      });
      setQuotes(
        result.data.map((quote) => ({
          ...quote,
          quantity: Number(quantity),
          postal_code: postalCode,
        }))
      );
      if (!result.data.length)
        setMessage("Belum ada layanan ekspedisi untuk tujuan ini.");
    } catch (error) {
      setMessage(error.message);
    } finally {
      setQuoteBusy(false);
    }
  }
  const [notes, setNotes] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [order, setOrder] = useState(null);
  const [uncertain, setUncertain] = useState(false);
  const attempt = useRef(null);
  useEffect(() => {
    const controller = new AbortController();
    apiRequest("/me", { signal: controller.signal })
      .then((result) => {
        if (!controller.signal.aborted) {
          setProfile(result.data);
          setName(result.data.name);
        }
      })
      .catch((error) => {
        if (!controller.signal.aborted && error.status !== 401)
          setAuthError("Sesi belum dapat diperiksa. Muat ulang halaman.");
      })
      .finally(() => {
        if (!controller.signal.aborted) setChecking(false);
      });
    return () => controller.abort();
  }, []);
  const money = (value) =>
    new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: "IDR",
      maximumFractionDigits: 0,
    }).format(value);
  async function submit(event) {
    event.preventDefault();
    if (busy || !profile) return;
    setBusy(true);
    setMessage("");
    if (!attempt.current)
      attempt.current = {
        key: crypto.randomUUID(),
        payload: {
          product_slug: product.slug,
          quantity: Number(quantity),
          expected_price: product.price,
          customer_name: name,
          customer_phone: phone,
          notes,
          fulfillment,
          ...(fulfillment === "delivery"
            ? {
                shipping_address: address,
                postal_code: postalCode,
                expected_shipping_fee: shippingFee,
                ...(selectedQuote
                  ? { shipping_quote_id: selectedQuote.public_id }
                  : {}),
              }
            : {}),
        },
      };
    try {
      const result = await apiRequest("/account/umkm-orders", {
        method: "POST",
        signal: AbortSignal.timeout(15000),
        headers: { "Idempotency-Key": attempt.current.key },
        body: JSON.stringify(attempt.current.payload),
      });
      setOrder(result.data);
      setUncertain(false);
    } catch (error) {
      setMessage(error.message);
      if (error.status && error.status < 500) {
        attempt.current = null;
        setUncertain(false);
      } else {
        setUncertain(true);
      }
    } finally {
      setBusy(false);
    }
  }
  if (checking) return <p role="status">Memeriksa akun…</p>;
  if (authError) return <p role="alert">{authError}</p>;
  if (order)
    return (
      <div className="umkm-order-success" role="status">
        <h2>Pesanan tersimpan</h2>
        <p>Kode pesanan: {order.order_id}</p>
        <strong>{money(order.total)}</strong>
        <p>
          Lanjutkan pembayaran Midtrans untuk pesanan{" "}
          {order.shipping?.method === "delivery"
            ? "dengan pengiriman"
            : "ambil di lokasi"}
          .
        </p>
        <ReservationPayment
          kind="umkm"
          bookingId={order.order_id}
          payment={order.reservation_payment}
          payable={order.status === "reserved_sandbox"}
        />
        <Link className="ui-button" href="/akun/umkm">
          Lihat pesanan UMKM
        </Link>
      </div>
    );
  if (!product.ordering_available)
    return <p>Pemesanan online UMKM belum aktif di lingkungan ini.</p>;
  return (
    <form className="umkm-order-form" onSubmit={submit}>
      <h2>
        <ShoppingBag size={20} />
        Pesan online
      </h2>
      <p className="umkm-order-notice">
        Simulasi · pengambilan atau pengiriman · belum dibayar
      </p>
      <p>
        Stok tersedia: {product.stock} {product.unit}
      </p>
      <fieldset disabled={!profile || busy || uncertain || product.stock < 1}>
        <label htmlFor="umkm-quantity">Jumlah</label>
        <input
          id="umkm-quantity"
          type="number"
          min="1"
          max={Math.min(100, product.stock)}
          required
          value={quantity}
          onChange={(e) => setQuantity(e.target.value)}
        />
        <label htmlFor="umkm-fulfillment">Cara menerima produk</label>
        <select
          id="umkm-fulfillment"
          value={fulfillment}
          onChange={(e) => setFulfillment(e.target.value)}
        >
          <option value="pickup">Ambil di lokasi penjual</option>
          {product.delivery_available && (
            <option value="delivery">
              Kirim ke alamat —{" "}
              {product.shipping_provider_available
                ? "pilih ekspedisi"
                : `${money(product.shipping_fee)} / pesanan`}
            </option>
          )}
        </select>
        {fulfillment === "delivery" && (
          <>
            <label htmlFor="umkm-address">Alamat pengiriman lengkap</label>
            <textarea
              id="umkm-address"
              required
              minLength={10}
              maxLength={1000}
              autoComplete="street-address"
              placeholder="Jalan, nomor rumah, kelurahan, kecamatan, kota/kabupaten, dan provinsi"
              value={address}
              onChange={(e) => setAddress(e.target.value)}
            />
            <label htmlFor="umkm-postal">Kode pos</label>
            <input
              id="umkm-postal"
              required
              inputMode="numeric"
              pattern="[0-9]{5}"
              maxLength={5}
              autoComplete="postal-code"
              value={postalCode}
              onChange={(e) => setPostalCode(e.target.value)}
            />
            {product.shipping_provider_available ? (
              <>
                <button
                  type="button"
                  className="ui-button ui-button-outline"
                  disabled={quoteBusy || !/^[0-9]{5}$/.test(postalCode)}
                  onClick={checkRates}
                >
                  {quoteBusy ? "Memeriksa ongkir…" : "Periksa ongkir ekspedisi"}
                </button>
                <label>
                  Layanan ekspedisi
                  <select
                    required
                    value={selectedQuote?.public_id || ""}
                    onChange={(event) => setQuoteId(event.target.value)}
                  >
                    <option value="">Pilih layanan</option>
                    {quotes
                      .filter(
                        (quote) =>
                          quote.quantity === Number(quantity) &&
                          quote.postal_code === postalCode
                      )
                      .map((quote) => (
                        <option key={quote.public_id} value={quote.public_id}>
                          {quote.courier.toUpperCase()} {quote.service} ·{" "}
                          {money(quote.fee)} · {quote.duration}
                        </option>
                      ))}
                  </select>
                </label>
              </>
            ) : (
              <p>
                Ongkir tetap dari penjual. Pastikan alamat tujuan dapat
                dilayani.
              </p>
            )}
          </>
        )}
        <label htmlFor="umkm-name">Nama penerima</label>
        <input
          id="umkm-name"
          minLength={2}
          maxLength={120}
          autoComplete="name"
          required
          value={name}
          onChange={(e) => setName(e.target.value)}
        />
        <label htmlFor="umkm-phone">Nomor kontak</label>
        <input
          id="umkm-phone"
          type="tel"
          autoComplete="tel"
          maxLength={30}
          required
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
        />
        <label htmlFor="umkm-notes">Catatan (opsional)</label>
        <textarea
          id="umkm-notes"
          maxLength={500}
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
        />
      </fieldset>
      <div className="umkm-order-total">
        <span>Total produk</span>
        <strong>{money(product.price * (Number(quantity) || 0))}</strong>
      </div>
      {fulfillment === "delivery" && (
        <>
          <div className="umkm-order-total">
            <span>Ongkir per pesanan</span>
            <strong>
              {shippingFee === undefined
                ? "Periksa ongkir"
                : money(shippingFee)}
            </strong>
          </div>
          <div className="umkm-order-total">
            <span>Total termasuk ongkir</span>
            <strong>
              {shippingFee === undefined
                ? "Pilih ekspedisi"
                : money(product.price * (Number(quantity) || 0) + shippingFee)}
            </strong>
          </div>
        </>
      )}
      {fulfillment === "pickup" && (
        <p>Lokasi pengambilan: {product.location}</p>
      )}
      {message && <p role="alert">{message}</p>}
      {uncertain && (
        <p>
          Respons belum diterima. Gunakan tombol coba lagi untuk memeriksa
          permintaan yang sama.
        </p>
      )}
      {!profile ? (
        <>
          <p>Masuk untuk mengisi data penerima dan menyimpan pesanan.</p>
          <Link href="/login" className="ui-button">
            Masuk untuk memesan
          </Link>
        </>
      ) : (
        <button
          className="ui-button"
          disabled={
            busy ||
            product.stock < 1 ||
            (fulfillment === "delivery" &&
              product.shipping_provider_available &&
              !selectedQuote)
          }
        >
          {busy ? (
            <>
              <Loader2 size={16} className="animate-spin" />
              Menyimpan…
            </>
          ) : uncertain ? (
            "Coba lagi dengan data yang sama"
          ) : product.stock < 1 ? (
            "Stok habis"
          ) : (
            "Buat pesanan"
          )}
        </button>
      )}
    </form>
  );
}
