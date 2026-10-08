"use client";

import { useEffect, useState, useMemo } from "react";
import Link from "next/link";
import {
  TrendingUp,
  DollarSign,
  ArrowDownLeft,
  ArrowUpRight,
  Wallet,
  Calendar,
  Download,
  Search,
  Filter,
  RefreshCw,
  ArrowLeft,
  CheckCircle2,
  Clock,
  RotateCcw,
  Sparkles,
  PieChart,
  BarChart3,
  Layers,
  ShoppingBag,
  Compass,
  BedDouble,
  Utensils,
} from "lucide-react";
import { Shell } from "../../../components/Shell";
import { PageHeader } from "../../../components/PageHeader";
import { apiRequest } from "../../../lib/api";

const money = (val) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(val || 0);

export default function RevenueReportsPage() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState("");
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [periodPreset, setPeriodPreset] = useState("all"); // 'all' | 'this_month' | 'last_30' | 'this_year' | 'custom'
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [revision, setRevision] = useState(0);

  // Apply presets
  const handlePresetChange = (preset) => {
    setPeriodPreset(preset);
    const now = new Date();
    if (preset === "all") {
      setStartDate("");
      setEndDate("");
    } else if (preset === "this_month") {
      const start = new Date(now.getFullYear(), now.getMonth(), 1);
      setStartDate(start.toISOString().slice(0, 10));
      setEndDate(now.toISOString().slice(0, 10));
    } else if (preset === "last_30") {
      const start = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
      setStartDate(start.toISOString().slice(0, 10));
      setEndDate(now.toISOString().slice(0, 10));
    } else if (preset === "this_year") {
      const start = new Date(now.getFullYear(), 0, 1);
      setStartDate(start.toISOString().slice(0, 10));
      setEndDate(now.toISOString().slice(0, 10));
    }
  };

  useEffect(() => {
    let active = true;
    async function loadReport() {
      setLoading(true);
      setError("");
      try {
        let endpoint = "/revenue-reports";
        const queryParams = new URLSearchParams();
        if (startDate) queryParams.append("start_date", startDate);
        if (endDate) queryParams.append("end_date", endDate);
        if (queryParams.toString()) {
          endpoint += `?${queryParams.toString()}`;
        }

        const res = await apiRequest(endpoint);
        if (active) {
          setData(res.data);
        }
      } catch (err) {
        if (active) {
          setError(err.message || "Gagal memuat data laporan pendapatan.");
        }
      } finally {
        if (active) setLoading(false);
      }
    }

    loadReport();
    return () => {
      active = false;
    };
  }, [startDate, endDate, revision]);

  // Handle Export CSV
  const handleExport = async () => {
    try {
      setExporting(true);
      let url = "/api/v1/revenue-reports/export";
      const params = new URLSearchParams();
      if (startDate) params.append("start_date", startDate);
      if (endDate) params.append("end_date", endDate);
      if (params.toString()) url += `?${params.toString()}`;

      const res = await fetch(url, {
        credentials: "include",
        headers: { Accept: "text/csv" },
      });
      if (!res.ok) throw new Error("Gagal mengunduh laporan CSV.");
      const blob = await res.blob();
      const downloadUrl = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = downloadUrl;
      a.download = `laporan-pendapatan-${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(downloadUrl);
    } catch (err) {
      setError(err.message || "Gagal mengunduh laporan.");
    } finally {
      setExporting(false);
    }
  };

  // Filtered transactions
  const transactions = useMemo(() => {
    if (!data?.recent_transactions) return [];
    return data.recent_transactions.filter((tx) => {
      const matchSearch =
        tx.public_id?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        tx.customer_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        tx.partner_name?.toLowerCase().includes(searchTerm.toLowerCase());
      const matchStatus =
        statusFilter === "all" || tx.status === statusFilter;
      return matchSearch && matchStatus;
    });
  }, [data, searchTerm, statusFilter]);

  // Compute maximum GBV for monthly chart scaling
  const maxTrendGBV = useMemo(() => {
    if (!data?.monthly_trends || data.monthly_trends.length === 0) return 1000000;
    const max = Math.max(...data.monthly_trends.map((t) => t.gross_booking_value || 0));
    return max > 0 ? max : 1000000;
  }, [data]);

  const metrics = data || {};

  return (
    <Shell>
      <div className="account-page-header">
        <div style={{ display: "flex", alignItems: "center", gap: "0.75rem", marginBottom: "0.5rem" }}>
          <Link
            href="/dashboard"
            className="ui-button ui-button-outline"
            style={{ padding: "0.4rem 0.75rem", fontSize: "0.85rem", gap: "0.4rem" }}
          >
            <ArrowLeft size={16} /> Ke Dashboard
          </Link>
          <span style={{ color: "var(--color-text-muted)", fontSize: "0.85rem" }}>/ Operasional & Keuangan</span>
        </div>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", flexWrap: "wrap", gap: "1rem" }}>
          <div>
            <span className="heading-kicker" style={{ display: "flex", alignItems: "center", gap: "0.4rem" }}>
              <TrendingUp size={16} /> Analitik & Keuangan
            </span>
            <h1 style={{ margin: "0.25rem 0", fontSize: "1.75rem", fontWeight: 700 }}>
              Laporan Pendapatan Platform
            </h1>
            <p style={{ color: "var(--color-text-muted)", margin: 0, fontSize: "0.95rem" }}>
              Ringkasan nilai transaksi (GBV), pendapatan komisi platform, hak mitra, dan mutasi keuangan.
            </p>
          </div>
          <div style={{ display: "flex", gap: "0.5rem" }}>
            <button
              onClick={() => setRevision((r) => r + 1)}
              disabled={loading}
              className="ui-button ui-button-outline"
              style={{ display: "inline-flex", alignItems: "center", gap: "0.4rem" }}
              title="Segarkan data"
            >
              <RefreshCw size={16} className={loading ? "animate-spin" : ""} /> Segarkan
            </button>
            <button
              onClick={handleExport}
              disabled={exporting || loading}
              className="ui-button ui-button-primary"
              style={{ display: "inline-flex", alignItems: "center", gap: "0.4rem" }}
            >
              <Download size={16} /> {exporting ? "Mengunduh..." : "Unduh CSV"}
            </button>
          </div>
        </div>
      </div>

      {error && (
        <div
          style={{
            margin: "1rem 0",
            padding: "0.75rem 1rem",
            backgroundColor: "#fef2f2",
            border: "1px solid #fecaca",
            color: "#991b1b",
            borderRadius: "0.5rem",
            fontSize: "0.9rem",
          }}
        >
          {error}
        </div>
      )}

      {/* Date Filter Bar */}
      <div
        className="ui-card"
        style={{
          margin: "1.5rem 0",
          padding: "1rem 1.25rem",
          display: "flex",
          flexWrap: "wrap",
          alignItems: "center",
          justifyContent: "space-between",
          gap: "1rem",
          backgroundColor: "#fff",
          border: "1px solid var(--color-border)",
          borderRadius: "0.75rem",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", flexWrap: "wrap" }}>
          <Calendar size={18} style={{ color: "var(--color-text-muted)" }} />
          <span style={{ fontSize: "0.9rem", fontWeight: 600 }}>Periode:</span>
          {[
            { id: "all", label: "Semua Waktu" },
            { id: "this_month", label: "Bulan Ini" },
            { id: "last_30", label: "30 Hari Terakhir" },
            { id: "this_year", label: "Tahun Ini" },
            { id: "custom", label: "Kustom" },
          ].map((item) => (
            <button
              key={item.id}
              onClick={() => handlePresetChange(item.id)}
              style={{
                padding: "0.35rem 0.75rem",
                borderRadius: "9999px",
                border: "1px solid",
                borderColor: periodPreset === item.id ? "var(--color-primary)" : "var(--color-border)",
                backgroundColor: periodPreset === item.id ? "var(--color-primary-light, #eff6ff)" : "transparent",
                color: periodPreset === item.id ? "var(--color-primary)" : "var(--color-text-main)",
                fontSize: "0.85rem",
                fontWeight: periodPreset === item.id ? 600 : 400,
                cursor: "pointer",
              }}
            >
              {item.label}
            </button>
          ))}
        </div>

        {periodPreset === "custom" && (
          <div style={{ display: "flex", alignItems: "center", gap: "0.5rem" }}>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="ui-input"
              style={{ fontSize: "0.85rem", padding: "0.35rem 0.6rem" }}
            />
            <span style={{ fontSize: "0.85rem", color: "var(--color-text-muted)" }}>s/d</span>
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="ui-input"
              style={{ fontSize: "0.85rem", padding: "0.35rem 0.6rem" }}
            />
          </div>
        )}
      </div>

      {/* 4 Metric KPI Cards */}
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))",
          gap: "1rem",
          marginBottom: "1.5rem",
        }}
      >
        <div
          className="ui-card"
          style={{
            padding: "1.25rem",
            backgroundColor: "#fff",
            border: "1px solid var(--color-border)",
            borderRadius: "0.75rem",
            position: "relative",
            overflow: "hidden",
          }}
        >
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
            <div>
              <span style={{ fontSize: "0.85rem", color: "var(--color-text-muted)", fontWeight: 500 }}>
                Gross Booking Value (GBV)
              </span>
              <h2 style={{ margin: "0.5rem 0 0.25rem 0", fontSize: "1.5rem", fontWeight: 700, color: "var(--color-text-main)" }}>
                {money(metrics.gross_booking_value)}
              </h2>
              <span style={{ fontSize: "0.8rem", color: "#059669", display: "flex", alignItems: "center", gap: "0.25rem" }}>
                <CheckCircle2 size={13} /> Transaksi kotor berhasil
              </span>
            </div>
            <div
              style={{
                width: "42px",
                height: "42px",
                borderRadius: "0.5rem",
                backgroundColor: "#eff6ff",
                color: "#2563eb",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <DollarSign size={22} />
            </div>
          </div>
        </div>

        <div
          className="ui-card"
          style={{
            padding: "1.25rem",
            backgroundColor: "#fff",
            border: "1px solid var(--color-border)",
            borderRadius: "0.75rem",
            position: "relative",
            overflow: "hidden",
          }}
        >
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
            <div>
              <span style={{ fontSize: "0.85rem", color: "var(--color-text-muted)", fontWeight: 500 }}>
                Pendapatan Komisi Platform
              </span>
              <h2 style={{ margin: "0.5rem 0 0.25rem 0", fontSize: "1.5rem", fontWeight: 700, color: "#059669" }}>
                {money(metrics.platform_revenue)}
              </h2>
              <span style={{ fontSize: "0.8rem", color: "var(--color-text-muted)" }}>
                Komisi bersih platform
              </span>
            </div>
            <div
              style={{
                width: "42px",
                height: "42px",
                borderRadius: "0.5rem",
                backgroundColor: "#ecfdf5",
                color: "#059669",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Sparkles size={22} />
            </div>
          </div>
        </div>

        <div
          className="ui-card"
          style={{
            padding: "1.25rem",
            backgroundColor: "#fff",
            border: "1px solid var(--color-border)",
            borderRadius: "0.75rem",
            position: "relative",
            overflow: "hidden",
          }}
        >
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
            <div>
              <span style={{ fontSize: "0.85rem", color: "var(--color-text-muted)", fontWeight: 500 }}>
                Dana Siap Cair (Mitra)
              </span>
              <h2 style={{ margin: "0.5rem 0 0.25rem 0", fontSize: "1.5rem", fontWeight: 700, color: "#d97706" }}>
                {money(metrics.funds_ready_for_payout)}
              </h2>
              <span style={{ fontSize: "0.8rem", color: "var(--color-text-muted)" }}>
                Saldo liabilitas mitra aktif
              </span>
            </div>
            <div
              style={{
                width: "42px",
                height: "42px",
                borderRadius: "0.5rem",
                backgroundColor: "#fffbeb",
                color: "#d97706",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Wallet size={22} />
            </div>
          </div>
        </div>

        <div
          className="ui-card"
          style={{
            padding: "1.25rem",
            backgroundColor: "#fff",
            border: "1px solid var(--color-border)",
            borderRadius: "0.75rem",
            position: "relative",
            overflow: "hidden",
          }}
        >
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start" }}>
            <div>
              <span style={{ fontSize: "0.85rem", color: "var(--color-text-muted)", fontWeight: 500 }}>
                Total Pengembalian Dana
              </span>
              <h2 style={{ margin: "0.5rem 0 0.25rem 0", fontSize: "1.5rem", fontWeight: 700, color: "#dc2626" }}>
                {money(metrics.refunds)}
              </h2>
              <span style={{ fontSize: "0.8rem", color: "var(--color-text-muted)" }}>
                Refund & pembatalan
              </span>
            </div>
            <div
              style={{
                width: "42px",
                height: "42px",
                borderRadius: "0.5rem",
                backgroundColor: "#fef2f2",
                color: "#dc2626",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <RotateCcw size={22} />
            </div>
          </div>
        </div>
      </div>

      {/* Grid: Trend Chart & Category Breakdown */}
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(320px, 1fr))",
          gap: "1.5rem",
          marginBottom: "1.5rem",
        }}
      >
        {/* Trend Monthly Bar Chart */}
        <div
          className="ui-card"
          style={{
            padding: "1.5rem",
            backgroundColor: "#fff",
            border: "1px solid var(--color-border)",
            borderRadius: "0.75rem",
          }}
        >
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1.25rem" }}>
            <div>
              <h3 style={{ margin: 0, fontSize: "1.1rem", fontWeight: 600 }}>Tren Pendapatan Bulanan</h3>
              <p style={{ margin: "0.2rem 0 0", fontSize: "0.85rem", color: "var(--color-text-muted)" }}>
                Perbandingan GBV dan komisi 6 bulan terakhir
              </p>
            </div>
            <div style={{ display: "flex", gap: "1rem", fontSize: "0.8rem" }}>
              <span style={{ display: "flex", alignItems: "center", gap: "0.3rem" }}>
                <span style={{ width: "10px", height: "10px", backgroundColor: "#2563eb", borderRadius: "2px" }} /> GBV
              </span>
              <span style={{ display: "flex", alignItems: "center", gap: "0.3rem" }}>
                <span style={{ width: "10px", height: "10px", backgroundColor: "#059669", borderRadius: "2px" }} /> Komisi
              </span>
            </div>
          </div>

          {/* SVG / HTML5 Bar Chart */}
          <div style={{ height: "200px", display: "flex", alignItems: "flex-end", gap: "1rem", paddingTop: "1rem", borderBottom: "1px solid #e5e7eb" }}>
            {(data?.monthly_trends || []).map((t, idx) => {
              const gbvHeightPercent = Math.max(8, Math.round(((t.gross_booking_value || 0) / maxTrendGBV) * 100));
              const revHeightPercent = Math.max(4, Math.round(((t.platform_revenue || 0) / maxTrendGBV) * 100));

              return (
                <div
                  key={idx}
                  style={{
                    flex: 1,
                    display: "flex",
                    flexDirection: "column",
                    alignItems: "center",
                    height: "100%",
                    justifyContent: "flex-end",
                    position: "relative",
                  }}
                  title={`${t.label}: GBV ${money(t.gross_booking_value)}, Komisi ${money(t.platform_revenue)}`}
                >
                  <div style={{ display: "flex", alignItems: "flex-end", gap: "4px", width: "100%", justifyContent: "center", height: "160px" }}>
                    <div
                      style={{
                        width: "14px",
                        height: `${gbvHeightPercent}%`,
                        backgroundColor: "#2563eb",
                        borderRadius: "3px 3px 0 0",
                        transition: "height 0.3s ease",
                      }}
                    />
                    <div
                      style={{
                        width: "14px",
                        height: `${revHeightPercent}%`,
                        backgroundColor: "#059669",
                        borderRadius: "3px 3px 0 0",
                        transition: "height 0.3s ease",
                      }}
                    />
                  </div>
                  <span style={{ marginTop: "0.5rem", fontSize: "0.75rem", color: "var(--color-text-muted)" }}>
                    {t.label?.split(" ")[0]}
                  </span>
                </div>
              );
            })}
          </div>
        </div>

        {/* Category Breakdown */}
        <div
          className="ui-card"
          style={{
            padding: "1.5rem",
            backgroundColor: "#fff",
            border: "1px solid var(--color-border)",
            borderRadius: "0.75rem",
          }}
        >
          <div style={{ marginBottom: "1.25rem" }}>
            <h3 style={{ margin: 0, fontSize: "1.1rem", fontWeight: 600 }}>Komposisi Lini Bisnis</h3>
            <p style={{ margin: "0.2rem 0 0", fontSize: "0.85rem", color: "var(--color-text-muted)" }}>
              Distribusi pendapatan berdasarkan jenis layanan & produk
            </p>
          </div>

          <div style={{ display: "flex", flexDirection: "column", gap: "1rem" }}>
            {(data?.category_breakdown || []).map((cat, idx) => {
              const icons = {
                package: <Compass size={16} color="#2563eb" />,
                ticket: <TrendingUp size={16} color="#059669" />,
                lodging: <BedDouble size={16} color="#7c3aed" />,
                culinary: <Utensils size={16} color="#ea580c" />,
                umkm: <ShoppingBag size={16} color="#0891b2" />,
              };

              return (
                <div key={idx}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "0.25rem" }}>
                    <span style={{ fontSize: "0.85rem", fontWeight: 600, display: "flex", alignItems: "center", gap: "0.4rem" }}>
                      {icons[cat.type] || <Layers size={16} />} {cat.label}
                    </span>
                    <span style={{ fontSize: "0.85rem", color: "var(--color-text-muted)" }}>
                      {money(cat.total_amount)} ({cat.percentage}%)
                    </span>
                  </div>
                  <div
                    style={{
                      height: "8px",
                      width: "100%",
                      backgroundColor: "#f3f4f6",
                      borderRadius: "9999px",
                      overflow: "hidden",
                    }}
                  >
                    <div
                      style={{
                        height: "100%",
                        width: `${cat.percentage || 0}%`,
                        backgroundColor:
                          cat.type === "package"
                            ? "#2563eb"
                            : cat.type === "lodging"
                            ? "#7c3aed"
                            : cat.type === "culinary"
                            ? "#ea580c"
                            : cat.type === "umkm"
                            ? "#0891b2"
                            : "#059669",
                        borderRadius: "9999px",
                      }}
                    />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* Recent Financial Transactions Table */}
      <div
        className="ui-card"
        style={{
          padding: "1.5rem",
          backgroundColor: "#fff",
          border: "1px solid var(--color-border)",
          borderRadius: "0.75rem",
        }}
      >
        <div
          style={{
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            flexWrap: "wrap",
            gap: "1rem",
            marginBottom: "1rem",
          }}
        >
          <div>
            <h3 style={{ margin: 0, fontSize: "1.1rem", fontWeight: 600 }}>Mutasi & Transaksi Terkini</h3>
            <p style={{ margin: "0.2rem 0 0", fontSize: "0.85rem", color: "var(--color-text-muted)" }}>
              Daftar pembukuan transaksi pesanan dan bagi hasil
            </p>
          </div>

          <div style={{ display: "flex", gap: "0.75rem", flexWrap: "wrap" }}>
            <div style={{ position: "relative" }}>
              <Search
                size={16}
                style={{
                  position: "absolute",
                  left: "0.75rem",
                  top: "50%",
                  transform: "translateY(-50%)",
                  color: "var(--color-text-muted)",
                }}
              />
              <input
                type="text"
                placeholder="Cari ID, pelanggan..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="ui-input"
                style={{ paddingLeft: "2.25rem", fontSize: "0.85rem", width: "200px" }}
              />
            </div>

            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="ui-input"
              style={{ fontSize: "0.85rem", padding: "0.4rem 0.75rem" }}
            >
              <option value="all">Semua Status</option>
              <option value="paid">Lunas</option>
              <option value="refunded">Refund</option>
              <option value="pending_payment">Menunggu Pembayaran</option>
            </select>
          </div>
        </div>

        {/* Data Table */}
        <div style={{ overflowX: "auto" }}>
          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: "0.85rem" }}>
            <thead>
              <tr style={{ borderBottom: "1px solid #e5e7eb", textAlign: "left", color: "var(--color-text-muted)" }}>
                <th style={{ padding: "0.75rem 0.5rem" }}>ID Pesanan</th>
                <th style={{ padding: "0.75rem 0.5rem" }}>Tanggal</th>
                <th style={{ padding: "0.75rem 0.5rem" }}>Pelanggan</th>
                <th style={{ padding: "0.75rem 0.5rem" }}>Mitra</th>
                <th style={{ padding: "0.75rem 0.5rem", textAlign: "right" }}>Nilai Bruto</th>
                <th style={{ padding: "0.75rem 0.5rem", textAlign: "right" }}>Komisi Platform</th>
                <th style={{ padding: "0.75rem 0.5rem", textAlign: "right" }}>Hak Mitra</th>
                <th style={{ padding: "0.75rem 0.5rem", textAlign: "center" }}>Status</th>
              </tr>
            </thead>
            <tbody>
              {transactions.length === 0 ? (
                <tr>
                  <td colSpan={8} style={{ padding: "2rem", textAlign: "center", color: "var(--color-text-muted)" }}>
                    Tidak ada transaksi pada filter yang dipilih.
                  </td>
                </tr>
              ) : (
                transactions.map((tx) => (
                  <tr
                    key={tx.id}
                    style={{
                      borderBottom: "1px solid #f3f4f6",
                      transition: "background-color 0.15s",
                    }}
                  >
                    <td style={{ padding: "0.75rem 0.5rem", fontFamily: "monospace", fontWeight: 600 }}>
                      {tx.public_id}
                    </td>
                    <td style={{ padding: "0.75rem 0.5rem", color: "var(--color-text-muted)" }}>
                      {tx.created_at ? new Date(tx.created_at).toLocaleDateString("id-ID", {
                        day: "numeric",
                        month: "short",
                        year: "numeric",
                      }) : "-"}
                    </td>
                    <td style={{ padding: "0.75rem 0.5rem", fontWeight: 500 }}>
                      {tx.customer_name}
                      <span style={{ display: "block", fontSize: "0.75rem", color: "var(--color-text-muted)" }}>
                        {tx.customer_email}
                      </span>
                    </td>
                    <td style={{ padding: "0.75rem 0.5rem" }}>{tx.partner_name}</td>
                    <td style={{ padding: "0.75rem 0.5rem", textAlign: "right", fontWeight: 600 }}>
                      {money(tx.total)}
                    </td>
                    <td style={{ padding: "0.75rem 0.5rem", textAlign: "right", color: "#059669", fontWeight: 600 }}>
                      {money(tx.commission_amount)}
                    </td>
                    <td style={{ padding: "0.75rem 0.5rem", textAlign: "right", color: "#2563eb", fontWeight: 600 }}>
                      {money(tx.partner_amount)}
                    </td>
                    <td style={{ padding: "0.75rem 0.5rem", textAlign: "center" }}>
                      <span
                        style={{
                          padding: "0.2rem 0.5rem",
                          borderRadius: "9999px",
                          fontSize: "0.75rem",
                          fontWeight: 600,
                          backgroundColor:
                            tx.status === "paid"
                              ? "#ecfdf5"
                              : tx.status === "refunded"
                              ? "#fef2f2"
                              : "#f3f4f6",
                          color:
                            tx.status === "paid"
                              ? "#059669"
                              : tx.status === "refunded"
                              ? "#dc2626"
                              : "#4b5563",
                        }}
                      >
                        {tx.status === "paid"
                          ? "Lunas"
                          : tx.status === "refunded"
                          ? "Refund"
                          : tx.status === "pending_payment"
                          ? "Menunggu"
                          : tx.status}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </Shell>
  );
}
