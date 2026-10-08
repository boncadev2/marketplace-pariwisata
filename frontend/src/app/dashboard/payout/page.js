"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Wallet,
  Building2,
  Clock,
  CheckCircle2,
  AlertCircle,
  Loader2,
  ArrowLeft,
  Plus,
  ShieldCheck,
  Lock,
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

export default function PayoutManagementPage() {
  const [activeTab, setActiveTab] = useState("eligible"); // 'eligible' | 'batches' | 'accounts'
  const [profile, setProfile] = useState(null);
  const [eligibleFunds, setEligibleFunds] = useState([]);
  const [batches, setBatches] = useState([]);
  const [bankAccounts, setBankAccounts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [revision, setRevision] = useState(0);

  // Security password modal state
  const [securityModal, setSecurityModal] = useState(null); // { title, description, onConfirm: async (password) => void }
  const [securityPassword, setSecurityPassword] = useState("");
  const [securityBusy, setSecurityBusy] = useState(false);
  const [securityError, setSecurityError] = useState("");

  // Create Batch Modal
  const [createBatchItem, setCreateBatchItem] = useState(null);
  const [batchProvider, setBatchProvider] = useState("bank_transfer");
  const [batchNotes, setBatchNotes] = useState("");

  // Add Bank Account Modal
  const [showAddAccountModal, setShowAddAccountModal] = useState(false);
  const [accountForm, setAccountForm] = useState({
    bank_name: "BCA",
    account_number: "",
    account_name: "",
  });

  const isAdmin = profile?.platform_role === "super_admin" || profile?.role === "admin";

  // Initial load
  useEffect(() => {
    let active = true;
    apiRequest("/me")
      .then((res) => {
        if (!active) return;
        setProfile(res.data);
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, []);

  // Data loading based on tab
  useEffect(() => {
    let active = true;

    async function loadData() {
      setError("");
      try {
        if (activeTab === "eligible") {
          const res = await apiRequest("/payouts/eligible");
          if (active) setEligibleFunds(res.data || []);
        } else if (activeTab === "batches") {
          const res = await apiRequest("/payouts/batches");
          if (active) setBatches(res.data || []);
        } else if (activeTab === "accounts") {
          const res = await apiRequest("/partner-bank-accounts");
          if (active) setBankAccounts(res.data || []);
        }
      } catch (err) {
        if (active) {
          setError(
            err.status === 403
              ? "Anda tidak memiliki izin untuk melihat modul ini."
              : err.message || "Gagal memuat data pencairan dana."
          );
        }
      } finally {
        if (active) setLoading(false);
      }
    }

    loadData();
    return () => {
      active = false;
    };
  }, [activeTab, revision]);

  async function requestSensitiveAction(title, description, actionCallback) {
    setSecurityPassword("");
    setSecurityError("");
    setSecurityModal({
      title,
      description,
      onConfirm: async (pwd) => {
        setSecurityBusy(true);
        setSecurityError("");
        try {
          const confirmRes = await apiRequest("/security/confirm-password", {
            method: "POST",
            body: JSON.stringify({ password: pwd }),
          });
          await actionCallback(confirmRes.confirmation_token);
          setSecurityModal(null);
          setRevision((r) => r + 1);
        } catch (err) {
          setSecurityError(err.message || "Konfirmasi kata sandi gagal.");
        } finally {
          setSecurityBusy(false);
        }
      },
    });
  }

  // Handle batch creation
  async function submitCreateBatch(e) {
    e.preventDefault();
    if (!createBatchItem) return;

    requestSensitiveAction(
      "Konfirmasi Pembuatan Batch Pencairan",
      `Anda akan membuat batch pencairan sebesar ${money(createBatchItem.total_amount)} untuk mitra ${createBatchItem.partner_name}.`,
      async (token) => {
        await apiRequest("/payouts/batches", {
          method: "POST",
          headers: { "X-Sensitive-Confirmation": token },
          body: JSON.stringify({
            provider: batchProvider,
            notes: batchNotes || `Pencairan dana ${createBatchItem.partner_name}`,
            items: [
              {
                partner_id: createBatchItem.partner_id,
                order_ids: createBatchItem.orders,
              },
            ],
          }),
        });
        setMessage("Batch pencairan dana berhasil dibuat dan menunggu persetujuan (approval).");
        setCreateBatchItem(null);
        setBatchNotes("");
      }
    );
  }

  // Handle batch approval
  function handleApproveBatch(batch) {
    requestSensitiveAction(
      "Setujui Batch Pencairan Dana",
      `Konfirmasi persetujuan batch ${batch.batch_number} sebesar ${money(batch.total_amount)}.`,
      async (token) => {
        await apiRequest(`/payouts/batches/${batch.id}/approve`, {
          method: "POST",
          headers: { "X-Sensitive-Confirmation": token },
        });
        setMessage(`Batch ${batch.batch_number} berhasil disetujui.`);
      }
    );
  }

  // Handle bank account registration
  async function submitAddAccount(e) {
    e.preventDefault();
    requestSensitiveAction(
      "Daftarkan Rekening Bank",
      `Menambahkan rekening ${accountForm.bank_name} - ${accountForm.account_number} atas nama ${accountForm.account_name}.`,
      async (token) => {
        await apiRequest("/partner-bank-accounts", {
          method: "POST",
          headers: { "X-Sensitive-Confirmation": token },
          body: JSON.stringify(accountForm),
        });
        setMessage("Rekening bank berhasil didaftarkan dan menunggu verifikasi admin.");
        setShowAddAccountModal(false);
        setAccountForm({ bank_name: "BCA", account_number: "", account_name: "" });
      }
    );
  }

  // Handle bank account verification (admin)
  function handleVerifyAccount(account) {
    requestSensitiveAction(
      "Verifikasi Rekening Bank Mitra",
      `Konfirmasi validitas rekening ${account.bank_name} (${account.account_name}) untuk pembayaran payout.`,
      async (token) => {
        await apiRequest(`/partner-bank-accounts/${account.id}/verify`, {
          method: "POST",
          headers: { "X-Sensitive-Confirmation": token },
        });
        setMessage("Rekening bank mitra berhasil diverifikasi.");
      }
    );
  }

  return (
    <Shell>
      <div style={{ maxWidth: "1200px", margin: "0 auto", padding: "1.5rem" }}>
        <div style={{ marginBottom: "1.5rem" }}>
          <Link
            href="/dashboard"
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: "0.4rem",
              fontSize: "0.875rem",
              color: "var(--muted, #64748b)",
              marginBottom: "0.5rem",
              textDecoration: "none",
            }}
          >
            <ArrowLeft size={16} /> Kembali ke Dashboard
          </Link>
          <PageHeader
            eyebrow="Operasional & Keuangan"
            title="Pencairan Dana & Rekening Mitra"
            description="Kelola penyelesaian dana transaksi mitra, batch payout, dan verifikasi rekening penerima."
            compact
          />
        </div>

        {message && (
          <div
            role="status"
            style={{
              padding: "0.875rem 1.25rem",
              background: "#eff6ff",
              border: "1px solid #bfdbfe",
              borderRadius: "10px",
              color: "#1e40af",
              marginBottom: "1.5rem",
              fontSize: "0.875rem",
              display: "flex",
              alignItems: "center",
              gap: "0.5rem",
            }}
          >
            <CheckCircle2 size={18} />
            <span>{message}</span>
          </div>
        )}

        {error && (
          <div
            role="alert"
            style={{
              padding: "0.875rem 1.25rem",
              background: "#fef2f2",
              border: "1px solid #fecaca",
              borderRadius: "10px",
              color: "#b91c1c",
              marginBottom: "1.5rem",
              fontSize: "0.875rem",
              display: "flex",
              alignItems: "center",
              gap: "0.5rem",
            }}
          >
            <AlertCircle size={18} />
            <span>{error}</span>
          </div>
        )}

        {/* Tab Navigation */}
        <div
          style={{
            display: "flex",
            gap: "0.5rem",
            borderBottom: "1px solid var(--border, #e2e8f0)",
            marginBottom: "1.5rem",
            flexWrap: "wrap",
          }}
        >
          {isAdmin && (
            <>
              <button
                type="button"
                onClick={() => {
                  setActiveTab("eligible");
                  setLoading(true);
                }}
                style={{
                  padding: "0.75rem 1.25rem",
                  fontSize: "0.9rem",
                  fontWeight: 600,
                  borderBottom: activeTab === "eligible" ? "2px solid #2563eb" : "2px solid transparent",
                  color: activeTab === "eligible" ? "#2563eb" : "#64748b",
                  background: "transparent",
                  border: "none",
                  cursor: "pointer",
                }}
              >
                Dana Siap Cair
              </button>
              <button
                type="button"
                onClick={() => {
                  setActiveTab("batches");
                  setLoading(true);
                }}
                style={{
                  padding: "0.75rem 1.25rem",
                  fontSize: "0.9rem",
                  fontWeight: 600,
                  borderBottom: activeTab === "batches" ? "2px solid #2563eb" : "2px solid transparent",
                  color: activeTab === "batches" ? "#2563eb" : "#64748b",
                  background: "transparent",
                  border: "none",
                  cursor: "pointer",
                }}
              >
                Riwayat Batch Payout
              </button>
            </>
          )}
          <button
            type="button"
            onClick={() => {
              setActiveTab("accounts");
              setLoading(true);
            }}
            style={{
              padding: "0.75rem 1.25rem",
              fontSize: "0.9rem",
              fontWeight: 600,
              borderBottom: activeTab === "accounts" ? "2px solid #2563eb" : "2px solid transparent",
              color: activeTab === "accounts" ? "#2563eb" : "#64748b",
              background: "transparent",
              border: "none",
              cursor: "pointer",
            }}
          >
            Rekening Bank Mitra
          </button>
        </div>

        {/* Tab Content */}
        {loading ? (
          <div style={{ textAlign: "center", padding: "4rem 2rem", color: "var(--muted, #64748b)" }}>
            <Loader2 size={32} className="animate-spin" style={{ margin: "0 auto 1rem" }} />
            <p>Memuat data pencairan…</p>
          </div>
        ) : (
          <>
            {/* 1. Eligible Funds Tab */}
            {activeTab === "eligible" && (
              <div>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1rem" }}>
                  <h3 style={{ fontSize: "1.1rem", fontWeight: 700, margin: 0, color: "#0f172a" }}>
                    Mitra dengan Dana Siap Cair
                  </h3>
                  <span style={{ fontSize: "0.85rem", color: "#64748b" }}>
                    Transaksi selesai melewati batas masa sanggah (dispute period)
                  </span>
                </div>

                {eligibleFunds.length === 0 ? (
                  <div
                    style={{
                      padding: "3.5rem 2rem",
                      textAlign: "center",
                      background: "#ffffff",
                      borderRadius: "12px",
                      border: "1px dashed #cbd5e1",
                    }}
                  >
                    <Wallet size={36} color="#94a3b8" style={{ margin: "0 auto 0.75rem" }} />
                    <h3 style={{ fontSize: "1.05rem", fontWeight: 600, margin: "0 0 0.25rem" }}>
                      Tidak ada dana siap dicairkan
                    </h3>
                    <p style={{ color: "#64748b", fontSize: "0.875rem" }}>
                      Semua transaksi mitra telah diproses ke batch payout atau masih dalam masa tunggu operasional.
                    </p>
                  </div>
                ) : (
                  <div style={{ display: "flex", flexDirection: "column", gap: "1rem" }}>
                    {eligibleFunds.map((item) => (
                      <div
                        key={item.partner_id}
                        style={{
                          background: "#ffffff",
                          borderRadius: "12px",
                          border: "1px solid var(--border, #e2e8f0)",
                          padding: "1.25rem",
                          display: "flex",
                          justifyContent: "space-between",
                          alignItems: "center",
                          flexWrap: "wrap",
                          gap: "1rem",
                          boxShadow: "0 1px 3px rgba(0,0,0,0.04)",
                        }}
                      >
                        <div>
                          <div style={{ display: "flex", alignItems: "center", gap: "0.5rem", marginBottom: "0.25rem" }}>
                            <Building2 size={18} color="#2563eb" />
                            <strong style={{ fontSize: "1.05rem", color: "#0f172a" }}>
                              {item.partner_name}
                            </strong>
                          </div>
                          <span style={{ fontSize: "0.85rem", color: "#64748b" }}>
                            {item.total_orders} pesanan selesai siap dicairkan
                          </span>
                        </div>

                        <div style={{ display: "flex", alignItems: "center", gap: "1.5rem" }}>
                          <div style={{ textAlign: "right" }}>
                            <span style={{ fontSize: "0.75rem", color: "#64748b", display: "block" }}>
                              Total Bersih Mitra
                            </span>
                            <strong style={{ fontSize: "1.25rem", color: "#16a34a" }}>
                              {money(item.total_amount)}
                            </strong>
                          </div>

                          <button
                            type="button"
                            onClick={() => setCreateBatchItem(item)}
                            className="ui-button"
                            style={{ padding: "0.5rem 1rem", fontSize: "0.875rem" }}
                          >
                            Proses Payout &rarr;
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* 2. Payout Batches Tab */}
            {activeTab === "batches" && (
              <div>
                <h3 style={{ fontSize: "1.1rem", fontWeight: 700, margin: "0 0 1rem", color: "#0f172a" }}>
                  Daftar Batch Payout
                </h3>

                {batches.length === 0 ? (
                  <div
                    style={{
                      padding: "3.5rem 2rem",
                      textAlign: "center",
                      background: "#ffffff",
                      borderRadius: "12px",
                      border: "1px dashed #cbd5e1",
                    }}
                  >
                    <Clock size={36} color="#94a3b8" style={{ margin: "0 auto 0.75rem" }} />
                    <h3 style={{ fontSize: "1.05rem", fontWeight: 600, margin: "0 0 0.25rem" }}>
                      Belum ada riwayat batch
                    </h3>
                    <p style={{ color: "#64748b", fontSize: "0.875rem" }}>
                      Batch yang dibuat oleh staf keuangan akan muncul di sini untuk verifikasi maker-checker.
                    </p>
                  </div>
                ) : (
                  <div style={{ display: "flex", flexDirection: "column", gap: "1rem" }}>
                    {batches.map((batch) => (
                      <div
                        key={batch.id}
                        style={{
                          background: "#ffffff",
                          borderRadius: "12px",
                          border: "1px solid var(--border, #e2e8f0)",
                          padding: "1.25rem",
                          boxShadow: "0 1px 3px rgba(0,0,0,0.04)",
                        }}
                      >
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", flexWrap: "wrap", gap: "1rem" }}>
                          <div>
                            <div style={{ display: "flex", alignItems: "center", gap: "0.6rem", marginBottom: "0.35rem" }}>
                              <strong style={{ fontSize: "1.05rem", color: "#0f172a" }}>
                                {batch.batch_number}
                              </strong>
                              <span
                                style={{
                                  fontSize: "0.75rem",
                                  fontWeight: 600,
                                  padding: "0.2rem 0.6rem",
                                  borderRadius: "999px",
                                  background:
                                    batch.status === "completed" || batch.status === "paid"
                                      ? "#dcfce7"
                                      : batch.status === "approved"
                                        ? "#eff6ff"
                                        : "#fef3c7",
                                  color:
                                    batch.status === "completed" || batch.status === "paid"
                                      ? "#166534"
                                      : batch.status === "approved"
                                        ? "#1e40af"
                                        : "#92400e",
                                }}
                              >
                                {batch.status === "requested"
                                  ? "Menunggu Approval"
                                  : batch.status === "approved"
                                    ? "Disetujui"
                                    : batch.status}
                              </span>
                            </div>

                            <div style={{ fontSize: "0.85rem", color: "#64748b" }}>
                              Provider: <strong>{batch.provider}</strong> · Dibuat oleh:{" "}
                              <strong>{batch.maker?.name || "Staf"}</strong>
                              {batch.checker && (
                                <> · Disetujui oleh: <strong>{batch.checker.name}</strong></>
                              )}
                            </div>
                            {batch.notes && (
                              <p style={{ margin: "0.4rem 0 0", fontSize: "0.85rem", color: "#475569" }}>
                                Catatan: {batch.notes}
                              </p>
                            )}
                          </div>

                          <div style={{ display: "flex", alignItems: "center", gap: "1.5rem" }}>
                            <div style={{ textAlign: "right" }}>
                              <span style={{ fontSize: "0.75rem", color: "#64748b", display: "block" }}>
                                Total Batch
                              </span>
                              <strong style={{ fontSize: "1.25rem", color: "#0f172a" }}>
                                {money(batch.total_amount)}
                              </strong>
                            </div>

                            {batch.status === "requested" && (
                              <button
                                type="button"
                                onClick={() => handleApproveBatch(batch)}
                                className="ui-button"
                                style={{ padding: "0.45rem 0.9rem", fontSize: "0.85rem" }}
                              >
                                Setujui Batch
                              </button>
                            )}
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* 3. Partner Bank Accounts Tab */}
            {activeTab === "accounts" && (
              <div>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1rem", flexWrap: "wrap", gap: "0.75rem" }}>
                  <div>
                    <h3 style={{ fontSize: "1.1rem", fontWeight: 700, margin: 0, color: "#0f172a" }}>
                      Rekening Bank Mitra
                    </h3>
                    <span style={{ fontSize: "0.85rem", color: "#64748b" }}>
                      Rekening terverifikasi yang digunakan untuk menerima transfer pencairan dana
                    </span>
                  </div>

                  <button
                    type="button"
                    onClick={() => setShowAddAccountModal(true)}
                    className="ui-button"
                    style={{ padding: "0.5rem 1rem", fontSize: "0.875rem", gap: "0.4rem" }}
                  >
                    <Plus size={16} /> Tambah Rekening
                  </button>
                </div>

                {bankAccounts.length === 0 ? (
                  <div
                    style={{
                      padding: "3.5rem 2rem",
                      textAlign: "center",
                      background: "#ffffff",
                      borderRadius: "12px",
                      border: "1px dashed #cbd5e1",
                    }}
                  >
                    <Building2 size={36} color="#94a3b8" style={{ margin: "0 auto 0.75rem" }} />
                    <h3 style={{ fontSize: "1.05rem", fontWeight: 600, margin: "0 0 0.25rem" }}>
                      Belum ada rekening terdaftar
                    </h3>
                    <p style={{ color: "#64748b", fontSize: "0.875rem", maxWidth: "420px", margin: "0 auto" }}>
                      Mitra wajib mendaftarkan rekening bank agar dana penjualan dapat dicairkan.
                    </p>
                  </div>
                ) : (
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(320px, 1fr))", gap: "1rem" }}>
                    {bankAccounts.map((acc) => (
                      <div
                        key={acc.id}
                        style={{
                          background: "#ffffff",
                          borderRadius: "12px",
                          border: "1px solid var(--border, #e2e8f0)",
                          padding: "1.25rem",
                          boxShadow: "0 1px 3px rgba(0,0,0,0.04)",
                        }}
                      >
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "0.75rem" }}>
                          <div>
                            <span style={{ fontSize: "0.75rem", fontWeight: 700, color: "#2563eb", textTransform: "uppercase" }}>
                              {acc.bank_name}
                            </span>
                            <strong style={{ fontSize: "1.1rem", display: "block", color: "#0f172a", marginTop: "2px" }}>
                              {acc.account_number_masked}
                            </strong>
                          </div>

                          <span
                            style={{
                              fontSize: "0.75rem",
                              fontWeight: 600,
                              padding: "0.2rem 0.6rem",
                              borderRadius: "999px",
                              background: acc.is_verified ? "#dcfce7" : "#fef3c7",
                              color: acc.is_verified ? "#166534" : "#92400e",
                              display: "inline-flex",
                              alignItems: "center",
                              gap: "0.25rem",
                            }}
                          >
                            {acc.is_verified ? (
                              <>
                                <ShieldCheck size={13} /> Terverifikasi
                              </>
                            ) : (
                              "Menunggu Verifikasi"
                            )}
                          </span>
                        </div>

                        <p style={{ margin: "0 0 0.75rem", fontSize: "0.9rem", color: "#334155" }}>
                          Atas Nama: <strong>{acc.account_name}</strong>
                        </p>

                        {acc.partner && (
                          <p style={{ margin: "0 0 0.75rem", fontSize: "0.8rem", color: "#64748b" }}>
                            Mitra: {acc.partner.name}
                          </p>
                        )}

                        {isAdmin && !acc.is_verified && (
                          <div style={{ paddingTop: "0.75rem", borderTop: "1px solid #f1f5f9" }}>
                            <button
                              type="button"
                              onClick={() => handleVerifyAccount(acc)}
                              className="ui-button"
                              style={{ width: "100%", justifyContent: "center", padding: "0.45rem", fontSize: "0.85rem" }}
                            >
                              Verifikasi Rekening Ini
                            </button>
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </>
        )}

        {/* Modal Buat Batch Payout */}
        {createBatchItem && (
          <div
            role="dialog"
            aria-modal="true"
            style={{
              position: "fixed",
              inset: 0,
              backgroundColor: "rgba(15, 23, 42, 0.65)",
              backdropFilter: "blur(4px)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              padding: "1rem",
              zIndex: 9999,
            }}
          >
            <div style={{ background: "#ffffff", padding: "1.75rem", borderRadius: "14px", maxWidth: "480px", width: "100%" }}>
              <h3 style={{ fontSize: "1.2rem", fontWeight: 700, margin: "0 0 0.5rem", color: "#0f172a" }}>
                Buat Batch Payout Mitra
              </h3>
              <p style={{ fontSize: "0.875rem", color: "#64748b", marginBottom: "1.25rem" }}>
                Mitra: <strong>{createBatchItem.partner_name}</strong> · Nominal:{" "}
                <strong style={{ color: "#16a34a" }}>{money(createBatchItem.total_amount)}</strong>
              </p>

              <form onSubmit={submitCreateBatch}>
                <div style={{ marginBottom: "1rem" }}>
                  <label htmlFor="payout-provider" style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.4rem" }}>
                    Metode Pembayaran
                  </label>
                  <select
                    id="payout-provider"
                    value={batchProvider}
                    onChange={(e) => setBatchProvider(e.target.value)}
                    style={{
                      width: "100%",
                      padding: "0.6rem",
                      borderRadius: "8px",
                      border: "1px solid #cbd5e1",
                      fontSize: "0.875rem",
                    }}
                  >
                    <option value="bank_transfer">Transfer Bank Manual</option>
                    <option value="manual">Tunai / Manual Operasional</option>
                    <option value="api">Automatisasi API Provider</option>
                  </select>
                </div>

                <div style={{ marginBottom: "1.5rem" }}>
                  <label htmlFor="payout-notes" style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.4rem" }}>
                    Catatan Batch (opsional)
                  </label>
                  <input
                    id="payout-notes"
                    type="text"
                    value={batchNotes}
                    onChange={(e) => setBatchNotes(e.target.value)}
                    placeholder="Contoh: Pencairan periode minggu ke-1"
                    style={{
                      width: "100%",
                      padding: "0.6rem",
                      borderRadius: "8px",
                      border: "1px solid #cbd5e1",
                      fontSize: "0.875rem",
                    }}
                  />
                </div>

                <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                  <button
                    type="button"
                    onClick={() => setCreateBatchItem(null)}
                    className="ui-button ui-button-outline"
                  >
                    Batal
                  </button>
                  <button type="submit" className="ui-button">
                    Lanjut Konfirmasi Kata Sandi &rarr;
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Modal Tambah Rekening Bank */}
        {showAddAccountModal && (
          <div
            role="dialog"
            aria-modal="true"
            style={{
              position: "fixed",
              inset: 0,
              backgroundColor: "rgba(15, 23, 42, 0.65)",
              backdropFilter: "blur(4px)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              padding: "1rem",
              zIndex: 9999,
            }}
          >
            <div style={{ background: "#ffffff", padding: "1.75rem", borderRadius: "14px", maxWidth: "460px", width: "100%" }}>
              <h3 style={{ fontSize: "1.2rem", fontWeight: 700, margin: "0 0 0.5rem", color: "#0f172a" }}>
                Tambah Rekening Bank Mitra
              </h3>
              <p style={{ fontSize: "0.875rem", color: "#64748b", marginBottom: "1.25rem" }}>
                Pastikan nama pemilik rekening sama dengan nama pada identitas/badan usaha mitra.
              </p>

              <form onSubmit={submitAddAccount}>
                <div style={{ marginBottom: "1rem" }}>
                  <label htmlFor="bank-name-select" style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.4rem" }}>
                    Nama Bank
                  </label>
                  <select
                    id="bank-name-select"
                    value={accountForm.bank_name}
                    onChange={(e) => setAccountForm({ ...accountForm, bank_name: e.target.value })}
                    style={{
                      width: "100%",
                      padding: "0.6rem",
                      borderRadius: "8px",
                      border: "1px solid #cbd5e1",
                      fontSize: "0.875rem",
                    }}
                  >
                    <option value="BCA">BCA (Bank Central Asia)</option>
                    <option value="Mandiri">Bank Mandiri</option>
                    <option value="BRI">BRI (Bank Rakyat Indonesia)</option>
                    <option value="BNI">BNI (Bank Negara Indonesia)</option>
                    <option value="BSI">BSI (Bank Syariah Indonesia)</option>
                    <option value="CIMB">CIMB Niaga</option>
                    <option value="Permata">Bank Permata</option>
                  </select>
                </div>

                <div style={{ marginBottom: "1rem" }}>
                  <label htmlFor="account-number-input" style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.4rem" }}>
                    Nomor Rekening
                  </label>
                  <input
                    id="account-number-input"
                    type="text"
                    required
                    value={accountForm.account_number}
                    onChange={(e) => setAccountForm({ ...accountForm, account_number: e.target.value })}
                    placeholder="Contoh: 1234567890"
                    style={{
                      width: "100%",
                      padding: "0.6rem",
                      borderRadius: "8px",
                      border: "1px solid #cbd5e1",
                      fontSize: "0.875rem",
                    }}
                  />
                </div>

                <div style={{ marginBottom: "1.5rem" }}>
                  <label htmlFor="account-name-input" style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.4rem" }}>
                    Nama Pemilik Rekening
                  </label>
                  <input
                    id="account-name-input"
                    type="text"
                    required
                    value={accountForm.account_name}
                    onChange={(e) => setAccountForm({ ...accountForm, account_name: e.target.value })}
                    placeholder="Nama sesuai buku tabungan"
                    style={{
                      width: "100%",
                      padding: "0.6rem",
                      borderRadius: "8px",
                      border: "1px solid #cbd5e1",
                      fontSize: "0.875rem",
                    }}
                  />
                </div>

                <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                  <button
                    type="button"
                    onClick={() => setShowAddAccountModal(false)}
                    className="ui-button ui-button-outline"
                  >
                    Batal
                  </button>
                  <button type="submit" className="ui-button">
                    Lanjut Konfirmasi Kata Sandi &rarr;
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* Modal Konfirmasi Kata Sandi Sensitif */}
        {securityModal && (
          <div
            role="dialog"
            aria-modal="true"
            style={{
              position: "fixed",
              inset: 0,
              backgroundColor: "rgba(15, 23, 42, 0.7)",
              backdropFilter: "blur(4px)",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              padding: "1rem",
              zIndex: 10000,
            }}
          >
            <div style={{ background: "#ffffff", padding: "1.75rem", borderRadius: "14px", maxWidth: "420px", width: "100%" }}>
              <div style={{ display: "flex", gap: "0.6rem", alignItems: "center", marginBottom: "0.75rem", color: "#0f172a" }}>
                <Lock size={20} color="#2563eb" />
                <h3 style={{ margin: 0, fontSize: "1.15rem", fontWeight: 700 }}>{securityModal.title}</h3>
              </div>
              <p style={{ fontSize: "0.875rem", color: "#64748b", marginBottom: "1.25rem" }}>
                {securityModal.description}
              </p>

              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  securityModal.onConfirm(securityPassword);
                }}
              >
                <div style={{ marginBottom: "1.25rem" }}>
                  <label htmlFor="confirm-pass-input" style={{ display: "block", fontSize: "0.875rem", fontWeight: 600, color: "#334155", marginBottom: "0.4rem" }}>
                    Masukkan Kata Sandi Akun Anda
                  </label>
                  <input
                    id="confirm-pass-input"
                    type="password"
                    required
                    autoFocus
                    value={securityPassword}
                    onChange={(e) => setSecurityPassword(e.target.value)}
                    placeholder="Kata sandi akun"
                    style={{
                      width: "100%",
                      padding: "0.65rem",
                      borderRadius: "8px",
                      border: "1px solid #cbd5e1",
                      fontSize: "0.9rem",
                    }}
                  />
                </div>

                {securityError && (
                  <div style={{ padding: "0.6rem 0.8rem", background: "#fef2f2", color: "#b91c1c", borderRadius: "8px", fontSize: "0.8rem", marginBottom: "1rem" }}>
                    {securityError}
                  </div>
                )}

                <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                  <button
                    type="button"
                    onClick={() => setSecurityModal(null)}
                    disabled={securityBusy}
                    className="ui-button ui-button-outline"
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    disabled={securityBusy || !securityPassword}
                    className="ui-button"
                  >
                    {securityBusy ? (
                      <>
                        <Loader2 size={16} className="animate-spin" /> Verifikasi…
                      </>
                    ) : (
                      "Konfirmasi & Jalankan"
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </Shell>
  );
}
