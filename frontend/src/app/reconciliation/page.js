"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  FileClock,
  Loader2,
  RefreshCw,
} from "lucide-react";
import { Shell } from "../../components/Shell";
import { apiRequest } from "../../lib/api";

const alertLabels = {
  paid_without_voucher: "Pembayaran tanpa voucher",
  stale_refund: "Refund terlalu lama",
  uncertain_payout: "Payout tidak pasti",
  unreleased_hold: "Hold belum dilepas",
  payment_discrepancy: "Selisih pembayaran",
};

function dateTime(value) {
  return value
    ? new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
      }).format(new Date(value))
    : "—";
}

function count(summary, key) {
  return Number(summary?.[key] || 0).toLocaleString("id-ID");
}

export default function ReconciliationPage() {
  const [reports, setReports] = useState([]);
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [access, setAccess] = useState("checking");
  const [message, setMessage] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setMessage("");
    try {
      const [reportResult, alertResult] = await Promise.all([
        apiRequest("/reconciliation/reports?per_page=30"),
        apiRequest("/reconciliation/alerts?status=open&per_page=100"),
      ]);
      setReports(reportResult.data || []);
      setAlerts(alertResult.data || []);
      setAccess("allowed");
    } catch (error) {
      setAccess(
        error.status === 401
          ? "login"
          : error.status === 403
            ? "forbidden"
            : "error"
      );
      setMessage(error.message || "Laporan rekonsiliasi belum dapat dimuat.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const initialLoad = window.setTimeout(load, 0);
    return () => window.clearTimeout(initialLoad);
  }, [load]);

  if (!loading && access !== "allowed") {
    return (
      <Shell>
        <div className="access-state mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
          <AlertTriangle className="mx-auto text-amber-600" size={36} />
          <h1 className="mt-4 text-2xl font-extrabold text-slate-900">
            {access === "login"
              ? "Masuk sebagai administrator"
              : "Akses rekonsiliasi tidak tersedia"}
          </h1>
          <p className="mt-3 text-slate-600">{message}</p>
          <Link
            href={access === "login" ? "/login" : "/dashboard"}
            className="mt-6 inline-flex rounded-xl bg-slate-900 px-5 py-3 font-bold text-white"
          >
            {access === "login" ? "Masuk" : "Kembali"}
          </Link>
        </div>
      </Shell>
    );
  }

  const latestDaily = reports.find(
    (report) => report.source === "daily_report"
  );

  return (
    <Shell>
      <div className="space-y-8 pb-12">
        <header className="operations-heading rounded-3xl bg-slate-950 px-6 py-8 text-white shadow-xl md:px-10">
          <div className="flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <div>
              <p className="text-sm font-bold uppercase tracking-[0.2em] text-cyan-400">
                VERIFIKASI PEMBAYARAN
              </p>
              <h1 className="mt-2 text-3xl font-extrabold md:text-4xl">
                Rekonsiliasi transaksi
              </h1>
              <p className="mt-3 max-w-2xl text-slate-300">
                Pantau hasil verifikasi provider, pemulihan pembayaran, dan
                alarm operasional harian.
              </p>
            </div>
            <div className="flex gap-3">
              <Link
                href="/dashboard"
                className="inline-flex items-center gap-2 rounded-xl border border-white/20 px-4 py-2.5 text-sm font-bold hover:bg-white/10"
              >
                <ArrowLeft size={17} /> Dashboard
              </Link>
              <button
                type="button"
                onClick={load}
                disabled={loading}
                className="inline-flex items-center gap-2 rounded-xl bg-cyan-400 px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-cyan-300 disabled:opacity-50"
              >
                <RefreshCw
                  size={17}
                  className={loading ? "animate-spin" : ""}
                />{" "}
                Segarkan
              </button>
            </div>
          </div>
        </header>

        {message && (
          <p
            role="status"
            className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"
          >
            {message}
          </p>
        )}

        {loading && !reports.length ? (
          <div className="flex min-h-64 items-center justify-center text-slate-500">
            <Loader2 className="mr-3 animate-spin" /> Memuat rekonsiliasi…
          </div>
        ) : (
          <>
            <section
              className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
              aria-label="Ringkasan rekonsiliasi"
            >
              <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <CheckCircle2 className="text-emerald-600" />
                <p className="mt-4 text-sm font-semibold text-slate-500">
                  Cocok
                </p>
                <p className="text-3xl font-extrabold text-slate-900">
                  {count(latestDaily?.summary?.entries, "matched")}
                </p>
              </article>
              <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <AlertTriangle className="text-red-600" />
                <p className="mt-4 text-sm font-semibold text-slate-500">
                  Selisih
                </p>
                <p className="text-3xl font-extrabold text-slate-900">
                  {count(latestDaily?.summary?.entries, "mismatched")}
                </p>
              </article>
              <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <FileClock className="text-amber-600" />
                <p className="mt-4 text-sm font-semibold text-slate-500">
                  Tidak ditemukan
                </p>
                <p className="text-3xl font-extrabold text-slate-900">
                  {count(latestDaily?.summary?.entries, "not_found")}
                </p>
              </article>
              <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <AlertTriangle className="text-orange-600" />
                <p className="mt-4 text-sm font-semibold text-slate-500">
                  Alarm terbuka
                </p>
                <p className="text-3xl font-extrabold text-slate-900">
                  {alerts.length.toLocaleString("id-ID")}
                </p>
              </article>
            </section>

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
              <div className="border-b border-slate-200 px-5 py-4">
                <h2 className="text-lg font-extrabold text-slate-900">
                  Laporan rekonsiliasi
                </h2>
                <p className="text-sm text-slate-500">
                  Hasil manual, otomatis, dan ringkasan harian.
                </p>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full min-w-[720px] text-left text-sm">
                  <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                      <th className="px-5 py-3">Tanggal</th>
                      <th className="px-5 py-3">Sumber</th>
                      <th className="px-5 py-3">Referensi</th>
                      <th className="px-5 py-3 text-right">Entri</th>
                      <th className="px-5 py-3 text-right">Selisih</th>
                      <th className="px-5 py-3">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {reports.map((report) => (
                      <tr key={report.id}>
                        <td className="px-5 py-4 text-slate-600">
                          {report.date}
                        </td>
                        <td className="px-5 py-4 font-semibold text-slate-800">
                          {report.source.replaceAll("_", " ")}
                        </td>
                        <td className="px-5 py-4 text-slate-600">
                          {report.reference}
                        </td>
                        <td className="px-5 py-4 text-right font-bold">
                          {report.entries_count}
                        </td>
                        <td className="px-5 py-4 text-right font-bold text-red-700">
                          {report.discrepancy_count}
                        </td>
                        <td className="px-5 py-4">
                          <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">
                            {report.status}
                          </span>
                        </td>
                      </tr>
                    ))}
                    {!reports.length && (
                      <tr>
                        <td
                          colSpan={6}
                          className="px-5 py-10 text-center text-slate-500"
                        >
                          Belum ada laporan rekonsiliasi.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </section>

            <section aria-labelledby="alerts-title">
              <div className="mb-4">
                <h2
                  id="alerts-title"
                  className="text-xl font-extrabold text-slate-900"
                >
                  Alarm operasional
                </h2>
                <p className="text-sm text-slate-500">
                  Tindakan koreksi dilakukan administrator melalui alur sensitif
                  dan selalu diaudit.
                </p>
              </div>
              <div className="grid gap-3">
                {alerts.map((alert) => (
                  <article
                    key={alert.id}
                    className="rounded-2xl border border-amber-200 bg-amber-50 p-5"
                  >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p className="font-extrabold text-amber-950">
                          {alertLabels[alert.type] || alert.type}
                        </p>
                        <p className="mt-1 text-sm text-amber-800">
                          {alert.order?.public_id
                            ? `Order ${alert.order.public_id}`
                            : alert.partner?.name || "Operasional platform"}
                        </p>
                      </div>
                      <span className="rounded-full bg-white px-3 py-1 text-xs font-bold uppercase text-amber-800">
                        {alert.severity}
                      </span>
                    </div>
                    <p className="mt-3 text-xs text-amber-700">
                      Terdeteksi {dateTime(alert.detected_at)}
                    </p>
                  </article>
                ))}
                {!alerts.length && (
                  <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center text-emerald-800">
                    <CheckCircle2 className="mx-auto mb-3" />
                    Tidak ada alarm rekonsiliasi terbuka.
                  </div>
                )}
              </div>
            </section>
          </>
        )}
      </div>
    </Shell>
  );
}
