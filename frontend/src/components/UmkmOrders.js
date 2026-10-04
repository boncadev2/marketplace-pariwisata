"use client";
import { ShipmentTracking } from "./ShipmentTracking";
import {
  ReservationPayment,
  reservationPaymentLabels,
} from "./ReservationPayment";
import Link from "next/link";
import { useEffect, useState } from "react";
import { Shell } from "./Shell";
import { PageHeader, EmptyState } from "./PageHeader";
import { apiRequest } from "../lib/api";
const statuses = {
  reserved_sandbox: "Pesanan masuk",
  processing_sandbox: "Sedang diproses",
  ready_sandbox: "Siap diambil",
  shipped_sandbox: "Dikirim",
  completed_sandbox: "Selesai",
  cancelled: "Dibatalkan",
};
const nextStatuses = {
  reserved_sandbox: "processing_sandbox",
  processing_sandbox: "ready_sandbox",
  ready_sandbox: "completed_sandbox",
  shipped_sandbox: "completed_sandbox",
};
export function UmkmOrders({ managed = false }) {
  const endpoint = managed ? "/dashboard/umkm-orders" : "/account/umkm-orders";
  const [result, setResult] = useState(null);
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState("");
  const [unauthorized, setUnauthorized] = useState(false);
  const [busy, setBusy] = useState(null);
  const [shipment, setShipment] = useState(null),
    [carrier, setCarrier] = useState(""),
    [trackingNumber, setTrackingNumber] = useState("");
  const [providerTrackingId, setProviderTrackingId] = useState("");
  const [confirm, setConfirm] = useState(null);
  useEffect(() => {
    const controller = new AbortController();
    const timer = setTimeout(() => {
      setLoading(true);
      setMessage("");
      setUnauthorized(false);
      apiRequest(`${endpoint}?page=${page}&status=${status}`, {
        signal: controller.signal,
      })
        .then((data) => {
          if (!controller.signal.aborted) setResult(data);
        })
        .catch((error) => {
          if (!controller.signal.aborted) {
            setUnauthorized(error.status === 401);
            setResult(null);
            setMessage(error.message);
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
  }, [page, revision, status, endpoint]);
  async function updateOrder(
    id,
    targetStatus = "cancelled",
    shippingData = {}
  ) {
    if (busy !== null) return;
    setBusy(id);
    setMessage("");
    try {
      const { data } = await apiRequest(
        `${endpoint}/${id}${managed ? "" : "/cancel"}`,
        {
          method: managed ? "PATCH" : "POST",
          ...(managed
            ? {
                body: JSON.stringify({
                  status: targetStatus,
                  revision: result.data.find((item) => item.order_id === id)
                    ?.revision,
                  ...shippingData,
                }),
              }
            : {}),
        }
      );
      setResult((value) => ({
        ...value,
        data: value.data.map((item) => (item.order_id === id ? data : item)),
      }));
      setConfirm(null);
      setShipment(null);
      setRevision((value) => value + 1);
    } catch (error) {
      setMessage(error.message);
    } finally {
      setBusy(null);
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
        eyebrow={managed ? "Portal mitra" : "Akun Anda"}
        title={managed ? "Kelola pesanan UMKM" : "Pesanan produk UMKM"}
        description={
          managed
            ? "Siapkan produk, kabari pembeli melalui status, dan catat pengambilan atau pengiriman produk."
            : "Pantau status produk yang Anda pesan. Pesanan hanya dapat dilihat oleh akun pembelinya."
        }
      />
      <div className="package-toolbar">
        <Link href={managed ? "/dashboard" : "/akun"}>
          {managed ? "Kembali ke dashboard" : "Kembali ke akun"}
        </Link>
        {managed && (
          <Link href="/dashboard/umkm/produk">Kelola produk UMKM</Link>
        )}
        <Link href="/umkm">Jelajahi produk UMKM</Link>
      </div>
      {!unauthorized && (
        <div className="umkm-status-toolbar">
          <label htmlFor="umkm-order-status">Status pesanan</label>
          <select
            id="umkm-order-status"
            value={status}
            disabled={loading || busy !== null}
            onChange={(event) => {
              setStatus(event.target.value);
              setPage(1);
            }}
          >
            <option value="">Semua pesanan</option>
            {Object.entries(statuses).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
          <button
            className="ui-button ui-button-outline"
            disabled={loading || busy !== null}
            onClick={() => setRevision(revision + 1)}
          >
            Perbarui status
          </button>
        </div>
      )}
      {loading ? (
        <p className="empty-state" role="status">
          Memuat pesanan…
        </p>
      ) : unauthorized ? (
        <EmptyState
          title={
            managed ? "Masuk ke portal mitra" : "Masuk untuk melihat pesanan"
          }
          description="Gunakan akun yang memiliki akses ke pesanan ini."
          href="/login"
          label="Masuk"
        />
      ) : !result ? (
        <div className="empty-state">
          <p role="alert">{message}</p>
          <button
            className="ui-button"
            onClick={() => setRevision(revision + 1)}
          >
            Coba lagi
          </button>
        </div>
      ) : (
        <>
          {message && <p role="alert">{message}</p>}
          {!result.data.length ? (
            <EmptyState
              title={
                managed
                  ? "Belum ada pesanan untuk dikelola"
                  : "Belum ada pesanan UMKM"
              }
              description={
                managed
                  ? "Pesanan pembeli untuk mitra Anda akan muncul di sini."
                  : "Temukan produk lokal dan buat pesanan pertama Anda."
              }
              href="/umkm"
              label="Lihat produk"
            />
          ) : (
            <div className="umkm-orders">
              {result.data.map((order) => (
                <article className="umkm-order-item" key={order.order_id}>
                  <div>
                    <span className="umkm-order-notice">
                      Status pesanan:{" "}
                      {statuses[order.status] || "Status belum tersedia"}{" "}
                    </span>
                    <p>
                      Status pembayaran:{" "}
                      {reservationPaymentLabels[order.payment_status] ||
                        "Status belum tersedia"}
                    </p>
                    <h2>{order.product.name}</h2>
                    <p className="umkm-order-code">{order.order_id}</p>
                    <p>
                      {order.quantity} × {money(order.product.price)} /{" "}
                      {order.product.unit}
                    </p>
                    <strong>{money(order.total)}</strong>
                    <p>Penjual: {order.product.seller}</p>
                    {order.shipping?.method === "delivery" ? (
                      <div className="umkm-shipping-summary">
                        <p>
                          <strong>Pengiriman ke alamat</strong>
                        </p>
                        <p>{order.shipping.address}</p>
                        <p>Kode pos: {order.shipping.postal_code}</p>
                        <p>
                          Subtotal produk: {money(order.subtotal)} · Ongkir:{" "}
                          {money(order.shipping.fee)}
                        </p>
                        {order.shipping.tracking_number ? (
                          <>
                            <p>
                              Kurir: <strong>{order.shipping.carrier}</strong>
                            </p>
                            <p>
                              Nomor resi:{" "}
                              <strong className="umkm-tracking-number">
                                {order.shipping.tracking_number}
                              </strong>
                            </p>
                            <p>Status pesanan diperbarui penjual.</p>
                            {order.shipping.tracking_available && (
                              <ShipmentTracking orderId={order.order_id} />
                            )}
                          </>
                        ) : (
                          <p>
                            Kurir dan resi akan tampil setelah penjual mencatat
                            pengiriman.
                          </p>
                        )}
                      </div>
                    ) : (
                      <p>Ambil di lokasi: {order.product.location}</p>
                    )}
                    <p>
                      Penerima: {order.customer_name} · {order.customer_phone}
                    </p>
                    {order.notes && <p>Catatan: {order.notes}</p>}
                    <p>
                      Pembayaran diverifikasi melalui Midtrans. Pengiriman
                      dicatat oleh penjual.
                    </p>
                  </div>
                  {!managed && (
                    <ReservationPayment
                      kind="umkm"
                      bookingId={order.order_id}
                      payment={order.reservation_payment}
                      payable={order.status === "reserved_sandbox"}
                      disabled={busy !== null}
                      onUpdated={() => setRevision((value) => value + 1)}
                    />
                  )}
                  {managed &&
                    (!order.reservation_payment ||
                      order.payment_status === "paid") &&
                    nextStatuses[order.status] &&
                    !(
                      order.shipping?.method === "delivery" &&
                      order.status === "processing_sandbox"
                    ) && (
                      <button
                        className="ui-button"
                        disabled={busy !== null || loading}
                        onClick={() =>
                          updateOrder(
                            order.order_id,
                            nextStatuses[order.status]
                          )
                        }
                      >
                        {busy === order.order_id
                          ? "Menyimpan…"
                          : `Tandai ${statuses[nextStatuses[order.status]].toLowerCase()}`}
                      </button>
                    )}
                  {managed &&
                    order.shipping?.method === "delivery" &&
                    ["processing_sandbox", "shipped_sandbox"].includes(
                      order.status
                    ) &&
                    (shipment === order.order_id ? (
                      <form
                        className="umkm-shipment-form"
                        onSubmit={(e) => {
                          e.preventDefault();
                          updateOrder(order.order_id, "shipped_sandbox", {
                            carrier,
                            tracking_number: trackingNumber,
                            provider_tracking_id: providerTrackingId || null,
                          });
                        }}
                      >
                        <label>
                          Kurir
                          <input
                            required
                            minLength={2}
                            maxLength={80}
                            value={carrier}
                            disabled={busy !== null}
                            onChange={(e) => setCarrier(e.target.value)}
                            placeholder="Nama jasa pengiriman"
                          />
                        </label>
                        <label>
                          Nomor resi
                          <input
                            required
                            minLength={3}
                            maxLength={100}
                            pattern="[A-Za-z0-9][A-Za-z0-9 ._-]*"
                            value={trackingNumber}
                            disabled={busy !== null}
                            onChange={(e) => setTrackingNumber(e.target.value)}
                            autoComplete="off"
                          />
                        </label>
                        <p>
                          Catat resi dari pengiriman yang telah disiapkan
                          penjual.
                        </p>
                        <label>
                          ID pelacakan Biteship (opsional)
                          <input
                            value={providerTrackingId}
                            maxLength={100}
                            pattern="[A-Za-z0-9_-]+"
                            onChange={(event) =>
                              setProviderTrackingId(event.target.value)
                            }
                          />
                          <small>
                            Salin tracking ID dari pengiriman yang dibuat
                            melalui Biteship. Isi kode kurir seperti jne atau
                            sicepat.
                          </small>
                        </label>
                        <button
                          className="ui-button"
                          disabled={busy !== null || loading}
                        >
                          {order.status === "shipped_sandbox"
                            ? "Simpan koreksi resi"
                            : "Simpan resi dan tandai dikirim"}
                        </button>
                        <button
                          className="ui-button ui-button-outline"
                          type="button"
                          disabled={busy !== null}
                          onClick={() => setShipment(null)}
                        >
                          Tutup
                        </button>
                      </form>
                    ) : (
                      <button
                        className="ui-button ui-button-outline"
                        disabled={busy !== null || loading}
                        onClick={() => {
                          setShipment(order.order_id);
                          setCarrier(order.shipping.carrier ?? "");
                          setTrackingNumber(
                            order.shipping.tracking_number ?? ""
                          );
                          setProviderTrackingId(
                            order.shipping.provider_tracking_id ?? ""
                          );
                        }}
                      >
                        {order.status === "shipped_sandbox"
                          ? "Koreksi kurir / resi"
                          : "Catat pengiriman"}
                      </button>
                    ))}
                  {!order.reservation_payment &&
                    (order.status === "reserved_sandbox" ||
                      (managed &&
                        ["processing_sandbox", "ready_sandbox"].includes(
                          order.status
                        ))) &&
                    (confirm === order.order_id ? (
                      <div>
                        <p>Batalkan pesanan dan kembalikan stok?</p>
                        <button
                          className="ui-button"
                          disabled={busy !== null || loading}
                          onClick={() => updateOrder(order.order_id)}
                        >
                          {busy === order.order_id
                            ? "Membatalkan…"
                            : "Ya, batalkan"}
                        </button>
                        <button
                          className="ui-button ui-button-outline"
                          disabled={busy !== null || loading}
                          onClick={() => setConfirm(null)}
                        >
                          Kembali
                        </button>
                      </div>
                    ) : (
                      <button
                        className="ui-button ui-button-outline"
                        disabled={busy !== null || loading}
                        onClick={() => setConfirm(order.order_id)}
                      >
                        Batalkan pesanan
                      </button>
                    ))}
                </article>
              ))}
            </div>
          )}
          {result.meta.last_page > 1 && (
            <div className="catalog-pagination">
              <button
                className="ui-button ui-button-outline"
                disabled={page === 1 || loading || busy !== null}
                onClick={() => setPage(page - 1)}
              >
                Sebelumnya
              </button>
              <span>Halaman {page}</span>
              <button
                className="ui-button ui-button-outline"
                disabled={
                  page === result.meta.last_page || loading || busy !== null
                }
                onClick={() => setPage(page + 1)}
              >
                Berikutnya
              </button>
            </div>
          )}
        </>
      )}
    </Shell>
  );
}
