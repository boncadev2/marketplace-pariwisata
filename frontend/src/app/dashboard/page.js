"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  CalendarDays,
  ClipboardList,
  Download,
  Eye,
  FileClock,
  Gauge,
  Loader2,
  RefreshCw,
  Search,
  TicketCheck,
  WalletCards,
} from "lucide-react";
import { Shell } from "../../components/Shell";
import { PilotCheckoutControl } from "../../components/PilotCheckoutControl";
import { apiRequest } from "../../lib/api";

const orderStatuses = {
  pending_payment: "Menunggu pembayaran",
  expired: "Kedaluwarsa",
  paid: "Dibayar",
  payment_exception: "Pengecualian pembayaran",
  cancelled: "Dibatalkan",
  refunded: "Direfund",
};

const severityStyles = {
  critical: "bg-red-100 text-red-800 border-red-200",
  high: "bg-orange-100 text-orange-800 border-orange-200",
  medium: "bg-amber-100 text-amber-800 border-amber-200",
};

const defaultFilters = {
  from: "",
  to: "",
  status: "",
  q: "",
  partner_id: "",
  timezone: "Asia/Jakarta",
};

function currency(value, code = "IDR") {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: code,
    maximumFractionDigits: 0,
  }).format(value || 0);
}

function number(value) {
  return new Intl.NumberFormat("id-ID").format(value || 0);
}

function dateTime(value) {
  return value
    ? new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
      }).format(new Date(value))
    : "—";
}

function queryString(filters) {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null && value !== undefined)
      params.set(key, value);
  });
  return params.toString();
}

function MetricCard({ icon: Icon, label, value, helper, tone = "emerald" }) {
  const tones = {
    emerald: "bg-emerald-50 text-emerald-700",
    blue: "bg-blue-50 text-blue-700",
    amber: "bg-amber-50 text-amber-700",
    violet: "bg-violet-50 text-violet-700",
  };

  return (
    <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div
        className={`mb-4 flex h-11 w-11 items-center justify-center rounded-xl ${tones[tone]}`}
      >
        <Icon size={22} />
      </div>
      <p className="text-sm font-medium text-slate-500">{label}</p>
      <p className="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">
        {value}
      </p>
      {helper && <p className="mt-2 text-xs text-slate-500">{helper}</p>}
    </article>
  );
}

export default function DashboardPage() {
  const [summary, setSummary] = useState(null);
  const [transactions, setTransactions] = useState([]);
  const [transactionMeta, setTransactionMeta] = useState(null);
  const [exceptions, setExceptions] = useState([]);
  const [selected, setSelected] = useState(null);
  const [filters, setFilters] = useState(defaultFilters);
  const [loading, setLoading] = useState(true);
  const [detailLoading, setDetailLoading] = useState(false);
  const [message, setMessage] = useState("");
  const [access, setAccess] = useState("checking");

  const loadDashboard = useCallback(async (nextFilters) => {
    setLoading(true);
    setMessage("");
    setSelected(null);
    const query = queryString(nextFilters);

    try {
      const [summaryResult, transactionResult, exceptionResult] =
        await Promise.all([
          apiRequest(`/dashboard/summary?${query}`),
          apiRequest(`/dashboard/transactions?${query}`),
          apiRequest(`/dashboard/exceptions?${query}`),
        ]);
      setSummary(summaryResult);
      setTransactions(transactionResult.data);
      setTransactionMeta(transactionResult.meta);
      setExceptions(exceptionResult.data);
      setAccess("allowed");
    } catch (error) {
      setAccess(
        error.status === 401
          ? "login"
          : error.status === 403
            ? "forbidden"
            : "error"
      );
      setMessage(
        error.status >= 500
          ? "Dashboard belum dapat dimuat. Coba kembali."
          : error.message
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const initialLoad = window.setTimeout(
      () => loadDashboard(defaultFilters),
      0
    );
    return () => window.clearTimeout(initialLoad);
  }, [loadDashboard]);

  const exportUrl = useMemo(
    () => `/api/v1/dashboard/export?${queryString(filters)}`,
    [filters]
  );

  function updateFilter(event) {
    const { name, value } = event.target;
    setFilters((current) => ({ ...current, [name]: value }));
  }

  function submitFilters(event) {
    event.preventDefault();
    loadDashboard(filters);
  }

  async function openTransaction(transaction) {
    setDetailLoading(true);
    setMessage("");
    try {
      const query = queryString({
        partner_id: filters.partner_id,
        timezone: filters.timezone,
      });
      const result = await apiRequest(
        `/dashboard/transactions/${transaction.id}?${query}`
      );
      setSelected(result.data);
    } catch (error) {
      setMessage(
        error.status === 404
          ? "Transaksi tidak ditemukan dalam cakupan akses Anda."
          : "Detail transaksi belum dapat dimuat."
      );
    } finally {
      setDetailLoading(false);
    }
  }

  if (!loading && access === "login") {
    return (
      <Shell>
        <div className="access-state mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
          <h1 className="text-2xl font-bold text-slate-900">
            Masuk untuk membuka dashboard
          </h1>
          <p className="mt-3 text-slate-600">
            Dashboard tersedia untuk administrator dan anggota mitra aktif.
          </p>
          <Link
            href="/login"
            className="mt-6 inline-flex rounded-xl bg-blue-600 px-5 py-3 font-bold text-white hover:bg-blue-700"
          >
            Masuk
          </Link>
        </div>
      </Shell>
    );
  }

  if (!loading && access === "forbidden") {
    return (
      <Shell>
        <div className="access-state mx-auto max-w-xl rounded-2xl border border-amber-200 bg-amber-50 p-10 text-center">
          <h1 className="text-2xl font-bold text-amber-900">
            Akses dashboard belum tersedia
          </h1>
          <p className="mt-3 text-amber-800">
            Akun Anda bukan administrator atau anggota mitra aktif.
          </p>
          <Link
            href="/akun"
            className="mt-6 inline-flex rounded-xl bg-amber-700 px-5 py-3 font-bold text-white hover:bg-amber-800"
          >
            Kembali ke akun
          </Link>
        </div>
      </Shell>
    );
  }

  return (
    <Shell>
      <div className="space-y-8 pb-12">
        <header className="operations-heading overflow-hidden rounded-3xl bg-slate-950 px-6 py-8 text-white shadow-xl md:px-10">
          <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <div>
              <p className="text-sm font-bold uppercase tracking-[0.2em] text-emerald-400">
                PORTAL OPERASIONAL
              </p>
              <h1 className="mt-2 text-3xl font-extrabold md:text-4xl">
                Dashboard operasional
              </h1>
              <p className="mt-3 max-w-2xl text-slate-300">
                Pantau penjualan, layanan, kuota, refund, payout, dan
                pengecualian dari sumber data yang sama.
              </p>
            </div>
            <div className="flex flex-wrap gap-3">
              <Link href="/dashboard/umkm" className="ui-button">
                Pesanan UMKM
              </Link>
              <Link
                href="/reconciliation"
                className="inline-flex items-center gap-2 rounded-xl border border-white/20 px-4 py-2.5 text-sm font-bold hover:bg-white/10"
              >
                <FileClock size={17} /> Rekonsiliasi
              </Link>
              <button
                type="button"
                onClick={() => loadDashboard(filters)}
                disabled={loading}
                className="inline-flex items-center gap-2 rounded-xl border border-white/20 px-4 py-2.5 text-sm font-bold hover:bg-white/10 disabled:opacity-50"
              >
                <RefreshCw
                  size={17}
                  className={loading ? "animate-spin" : ""}
                />{" "}
                Segarkan
              </button>
              {summary?.scope?.can_export && (
                <a
                  href={exportUrl}
                  className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-emerald-400"
                >
                  <Download size={17} /> Export CSV
                </a>
              )}
            </div>
          </div>
        </header>

        {summary && (
          <Link
            href="/dashboard/persetujuan-mitra"
            className="inline-flex rounded-xl border border-blue-700 px-4 py-3 font-bold text-emerald-700"
          >
            Persetujuan porsi mitra
          </Link>
        )}
        {summary?.scope?.role === "super_admin" && (
          <>
            <PilotCheckoutControl />
            <Link
              href="/dashboard/lintas-desa"
              className="inline-flex rounded-xl border border-blue-700 px-4 py-3 font-bold text-emerald-700"
            >
              Kelola simulasi paket lintas desa
            </Link>
          </>
        )}

        <form
          onSubmit={submitFilters}
          className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-6"
        >
          <label className="text-sm font-semibold text-slate-700">
            Dari
            <input
              type="date"
              name="from"
              value={filters.from}
              onChange={updateFilter}
              className="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"
            />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Sampai
            <input
              type="date"
              name="to"
              value={filters.to}
              onChange={updateFilter}
              className="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"
            />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Status
            <select
              name="status"
              value={filters.status}
              onChange={updateFilter}
              className="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"
            >
              <option value="">Semua</option>
              {Object.entries(orderStatuses).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          {summary?.scope?.available_partners?.length > 1 && (
            <label className="text-sm font-semibold text-slate-700">
              Mitra
              <select
                name="partner_id"
                value={filters.partner_id}
                onChange={updateFilter}
                className="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 font-normal"
              >
                {summary.scope.role === "super_admin" && (
                  <option value="">Semua mitra</option>
                )}
                {summary.scope.available_partners.map((partner) => (
                  <option key={partner.id} value={partner.id}>
                    {partner.name}
                  </option>
                ))}
              </select>
            </label>
          )}
          <label className="text-sm font-semibold text-slate-700 md:col-span-2">
            Cari ID transaksi
            <div className="relative mt-1.5">
              <Search
                size={17}
                className="absolute left-3 top-3 text-slate-400"
              />
              <input
                name="q"
                value={filters.q}
                onChange={updateFilter}
                maxLength={100}
                placeholder="ORDER-..."
                className="w-full rounded-xl border border-slate-300 py-2.5 pl-10 pr-3 font-normal"
              />
            </div>
          </label>
          <button
            disabled={loading}
            className="rounded-xl bg-slate-900 px-4 py-2.5 font-bold text-white hover:bg-slate-800 disabled:opacity-50 md:col-start-6"
          >
            Terapkan
          </button>
        </form>

        {message && (
          <p
            role="status"
            className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
          >
            {message}
          </p>
        )}

        {loading && !summary ? (
          <div className="flex min-h-64 items-center justify-center text-slate-500">
            <Loader2 className="mr-3 animate-spin" /> Memuat dashboard…
          </div>
        ) : (
          summary && (
            <>
              <section aria-labelledby="ringkasan-title">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                  <div>
                    <h2
                      id="ringkasan-title"
                      className="text-xl font-extrabold text-slate-900"
                    >
                      Ringkasan
                    </h2>
                    <p className="text-sm text-slate-500">
                      {summary.period.from}–{summary.period.to} ·{" "}
                      {summary.period.timezone}
                    </p>
                  </div>
                  <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                    {summary.scope.role.replaceAll("_", " ")}
                  </span>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                  <MetricCard
                    icon={WalletCards}
                    label="Penjualan bersih"
                    value={currency(summary.sales.net)}
                    helper={`Bruto ${currency(summary.sales.gross)} · Refund ${currency(summary.sales.refunds)}`}
                  />
                  <MetricCard
                    icon={ClipboardList}
                    label="Pesanan"
                    value={number(summary.orders.total)}
                    helper={`${number(summary.orders.paid)} dibayar · ${number(summary.orders.pending_payment)} menunggu`}
                    tone="blue"
                  />
                  <MetricCard
                    icon={TicketCheck}
                    label="Kunjungan"
                    value={number(summary.visits)}
                    helper={`${number(summary.orders.upcoming_30_days)} pesanan 30 hari ke depan`}
                    tone="violet"
                  />
                  <MetricCard
                    icon={Gauge}
                    label="Kuota tersedia"
                    value={number(summary.quota.available)}
                    helper={`${number(summary.quota.confirmed)} terkonfirmasi · ${number(summary.quota.held)} ditahan`}
                    tone="amber"
                  />
                  <MetricCard
                    icon={RefreshCw}
                    label="Refund tertunda"
                    value={number(summary.pending_refunds.count)}
                    helper={currency(summary.pending_refunds.amount)}
                    tone="amber"
                  />
                  <MetricCard
                    icon={CalendarDays}
                    label="Payout tertunda"
                    value={number(summary.pending_payouts.count)}
                    helper={currency(summary.pending_payouts.amount)}
                    tone="blue"
                  />
                  <MetricCard
                    icon={WalletCards}
                    label="Pendapatan platform"
                    value={currency(summary.sales.platform_revenue)}
                    helper="Berdasarkan ledger"
                    tone="violet"
                  />
                  <MetricCard
                    icon={WalletCards}
                    label="Kewajiban mitra"
                    value={currency(summary.sales.partner_liability)}
                    helper="Berdasarkan ledger"
                  />
                  <MetricCard
                    icon={Gauge}
                    label="Order ke pembayaran"
                    value={`${summary.pilot_monitoring.order_to_payment_percent}%`}
                    helper="Bukan conversion pengunjung; dihitung dari order"
                    tone="blue"
                  />
                  <MetricCard
                    icon={AlertTriangle}
                    label="Masalah pembayaran"
                    value={number(
                      summary.pilot_monitoring.failed_payments +
                        summary.pilot_monitoring.transaction_exceptions
                    )}
                    helper={`${number(summary.pilot_monitoring.failed_payments)} gagal/tidak pasti · ${number(summary.pilot_monitoring.transaction_exceptions)} exception`}
                    tone="amber"
                  />
                  <MetricCard
                    icon={ClipboardList}
                    label="Keluhan terbuka"
                    value={number(
                      summary.pilot_monitoring.open_support_tickets +
                        summary.pilot_monitoring.open_disputes
                    )}
                    helper={`${number(summary.pilot_monitoring.open_support_tickets)} tiket · ${number(summary.pilot_monitoring.open_disputes)} sengketa`}
                    tone="violet"
                  />
                  <MetricCard
                    icon={FileClock}
                    label="Selisih keuangan"
                    value={number(
                      summary.pilot_monitoring.financial_discrepancies
                    )}
                    helper="Harus nol sebelum ekspansi"
                    tone="amber"
                  />
                </div>
              </section>

              <section
                className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                aria-labelledby="transaksi-title"
              >
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                  <div>
                    <h2
                      id="transaksi-title"
                      className="text-lg font-extrabold text-slate-900"
                    >
                      Transaksi
                    </h2>
                    <p className="text-sm text-slate-500">
                      {number(transactionMeta?.total)} hasil sesuai filter
                    </p>
                  </div>
                </div>
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[780px] text-left text-sm">
                    <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                      <tr>
                        <th className="px-5 py-3">Order</th>
                        <th className="px-5 py-3">Mitra</th>
                        <th className="px-5 py-3">Status</th>
                        <th className="px-5 py-3">Pembayaran</th>
                        <th className="px-5 py-3 text-right">Total</th>
                        <th className="px-5 py-3">Waktu</th>
                        <th className="px-5 py-3">
                          <span className="sr-only">Detail</span>
                        </th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {transactions.map((transaction) => (
                        <tr key={transaction.id} className="hover:bg-slate-50">
                          <td className="px-5 py-4 font-bold text-slate-900">
                            {transaction.order_id}
                          </td>
                          <td className="px-5 py-4 text-slate-600">
                            {transaction.partner?.name || "—"}
                          </td>
                          <td className="px-5 py-4">
                            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                              {orderStatuses[transaction.status] ||
                                transaction.status}
                            </span>
                          </td>
                          <td className="px-5 py-4 text-slate-600">
                            {transaction.payment_status || "—"}
                          </td>
                          <td className="px-5 py-4 text-right font-bold text-slate-900">
                            {currency(transaction.total, transaction.currency)}
                          </td>
                          <td className="px-5 py-4 text-slate-500">
                            {dateTime(transaction.created_at)}
                          </td>
                          <td className="px-5 py-4 text-right">
                            <button
                              type="button"
                              onClick={() => openTransaction(transaction)}
                              className="inline-flex items-center gap-1.5 font-bold text-emerald-700 hover:text-emerald-900"
                            >
                              <Eye size={16} /> Detail
                            </button>
                          </td>
                        </tr>
                      ))}
                      {!transactions.length && (
                        <tr>
                          <td
                            colSpan={7}
                            className="px-5 py-12 text-center text-slate-500"
                          >
                            Tidak ada transaksi sesuai filter.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </section>

              {selected && (
                <section
                  className="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-6"
                  aria-labelledby="detail-title"
                >
                  <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                      <p className="text-xs font-bold uppercase tracking-wide text-emerald-700">
                        Drilldown sumber
                      </p>
                      <h2
                        id="detail-title"
                        className="mt-1 text-xl font-extrabold text-slate-900"
                      >
                        {selected.order_id}
                      </h2>
                      <p className="mt-1 text-sm text-slate-600">
                        Pelanggan {selected.customer.name} ·{" "}
                        {selected.customer.email}
                      </p>
                    </div>
                    <button
                      type="button"
                      onClick={() => setSelected(null)}
                      className="rounded-lg bg-white px-3 py-2 text-sm font-bold text-slate-600 shadow-sm"
                    >
                      Tutup
                    </button>
                  </div>
                  <div className="mt-5 grid gap-4 md:grid-cols-3">
                    <div className="rounded-xl bg-white p-4">
                      <p className="text-xs font-bold uppercase text-slate-500">
                        Item
                      </p>
                      {selected.items.map((item) => (
                        <p
                          key={`${item.name}-${item.quantity}`}
                          className="mt-2 font-semibold text-slate-800"
                        >
                          {item.quantity}× {item.name}{" "}
                          <span className="block text-sm font-normal text-slate-500">
                            {currency(item.total)}
                          </span>
                        </p>
                      ))}
                    </div>
                    <div className="rounded-xl bg-white p-4">
                      <p className="text-xs font-bold uppercase text-slate-500">
                        Pembayaran
                      </p>
                      {selected.payment_attempts.length ? (
                        selected.payment_attempts.map((attempt) => (
                          <p
                            key={attempt.id}
                            className="mt-2 text-sm text-slate-700"
                          >
                            {attempt.provider} ·{" "}
                            <strong>{attempt.status}</strong> ·{" "}
                            {currency(attempt.amount, attempt.currency)}
                          </p>
                        ))
                      ) : (
                        <p className="mt-2 text-sm text-slate-500">
                          Belum ada payment attempt.
                        </p>
                      )}
                    </div>
                    <div className="rounded-xl bg-white p-4">
                      <p className="text-xs font-bold uppercase text-slate-500">
                        Refund dan payout
                      </p>
                      <p className="mt-2 text-sm text-slate-700">
                        Refund:{" "}
                        <strong>
                          {selected.refund?.status || "tidak ada"}
                        </strong>
                      </p>
                      <p className="mt-2 text-sm text-slate-700">
                        Payout:{" "}
                        <strong>
                          {selected.payouts[0]?.status ||
                            selected.payout_status}
                        </strong>
                      </p>
                    </div>
                  </div>
                </section>
              )}
              {detailLoading && (
                <p className="flex items-center text-sm text-slate-500">
                  <Loader2 size={16} className="mr-2 animate-spin" /> Memuat
                  detail transaksi…
                </p>
              )}

              <section aria-labelledby="exception-title">
                <div className="mb-4 flex items-center justify-between">
                  <div>
                    <h2
                      id="exception-title"
                      className="text-xl font-extrabold text-slate-900"
                    >
                      Exception work queue
                    </h2>
                    <p className="text-sm text-slate-500">
                      Prioritas operasional yang perlu ditindaklanjuti.
                    </p>
                  </div>
                  <span className="rounded-full bg-red-100 px-3 py-1 text-sm font-bold text-red-700">
                    {exceptions.length}
                  </span>
                </div>
                <div className="grid gap-3">
                  {exceptions.map((item) => (
                    <article
                      key={item.id}
                      className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:flex-row md:items-center"
                    >
                      <div
                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border ${severityStyles[item.severity] || severityStyles.medium}`}
                      >
                        <AlertTriangle size={19} />
                      </div>
                      <div className="flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="font-extrabold text-slate-900">
                            {item.title}
                          </h3>
                          <span
                            className={`rounded-full border px-2 py-0.5 text-[11px] font-bold uppercase ${severityStyles[item.severity] || severityStyles.medium}`}
                          >
                            {item.severity}
                          </span>
                        </div>
                        <p className="mt-1 text-sm text-slate-600">
                          {item.description}
                        </p>
                      </div>
                      <time className="text-xs font-medium text-slate-500">
                        {dateTime(item.occurred_at)}
                      </time>
                    </article>
                  ))}
                  {!exceptions.length && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center text-emerald-800">
                      Tidak ada pengecualian aktif dalam cakupan ini.
                    </div>
                  )}
                </div>
              </section>
            </>
          )
        )}
      </div>
    </Shell>
  );
}
