"use client";

import Link from "next/link";
import { useState, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import {
  LockKeyhole,
  Mail,
  Loader2,
  ArrowRight,
  Eye,
  EyeOff,
  CheckCircle2,
  AlertCircle,
} from "lucide-react";
import { Shell } from "../../components/Shell";
import { AuthFrame } from "../../components/AuthFrame";
import { apiRequest } from "../../lib/api";

function ResetPasswordForm() {
  const searchParams = useSearchParams();
  const token = searchParams.get("token") || "";
  const initialEmail = searchParams.get("email") || "";

  const [email, setEmail] = useState(initialEmail);
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [visible, setVisible] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [isSuccess, setIsSuccess] = useState(false);

  async function submit(event) {
    event.preventDefault();
    if (busy) return;

    if (!token) {
      setMessage("Tautan reset tidak valid atau token tidak ditemukan. Silakan minta tautan baru.");
      return;
    }

    if (password.length < 12) {
      setMessage("Kata sandi baru minimal 12 karakter.");
      return;
    }

    if (password !== passwordConfirmation) {
      setMessage("Konfirmasi kata sandi tidak cocok.");
      return;
    }

    setBusy(true);
    setMessage("");

    try {
      await apiRequest("/reset-password", {
        method: "POST",
        body: JSON.stringify({
          token,
          email,
          password,
          password_confirmation: passwordConfirmation,
        }),
      });

      setIsSuccess(true);
      setMessage("Kata sandi Anda berhasil diperbarui. Silakan masuk menggunakan kata sandi baru.");
    } catch (error) {
      setMessage(
        error.message || "Gagal memperbarui kata sandi. Token mungkin telah kedaluwarsa atau tidak valid."
      );
    } finally {
      setBusy(false);
    }
  }

  if (isSuccess) {
    return (
      <div className="auth-success">
        <div style={{ display: "flex", justifyContent: "center", marginBottom: "1rem", color: "#16a34a" }}>
          <CheckCircle2 size={48} />
        </div>
        <p role="status" style={{ textAlign: "center", marginBottom: "1.5rem" }}>
          {message}
        </p>
        <Link href="/login" className="ui-button" style={{ width: "100%", justifyContent: "center" }}>
          Masuk ke akun sekarang <ArrowRight size={17} />
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={submit} className="auth-form" aria-busy={busy}>
      {!token && (
        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: "0.5rem",
            padding: "0.75rem 1rem",
            background: "#fef2f2",
            color: "#b91c1c",
            borderRadius: "8px",
            fontSize: "0.875rem",
            marginBottom: "1rem",
          }}
        >
          <AlertCircle size={18} />
          <span>Tautan tidak memiliki token. Pastikan Anda membuka link lengkap dari email.</span>
        </div>
      )}

      <label htmlFor="reset-email">Alamat email</label>
      <div className="auth-input">
        <Mail size={18} />
        <input
          id="reset-email"
          name="email"
          type="email"
          autoComplete="username"
          placeholder="nama@email.com"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
          disabled={busy || Boolean(initialEmail)}
        />
      </div>

      <label htmlFor="reset-password">Kata sandi baru</label>
      <div className="auth-input">
        <LockKeyhole size={18} />
        <input
          id="reset-password"
          name="password"
          type={visible ? "text" : "password"}
          autoComplete="new-password"
          placeholder="Minimal 12 karakter"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          minLength={12}
          required
          disabled={busy}
        />
        <button
          type="button"
          className="password-toggle"
          aria-label={visible ? "Sembunyikan kata sandi" : "Tampilkan kata sandi"}
          aria-pressed={visible}
          onClick={() => setVisible(!visible)}
        >
          {visible ? <EyeOff size={18} /> : <Eye size={18} />}
        </button>
      </div>

      <label htmlFor="reset-password-confirm">Konfirmasi kata sandi baru</label>
      <div className="auth-input">
        <LockKeyhole size={18} />
        <input
          id="reset-password-confirm"
          name="password_confirmation"
          type={visible ? "text" : "password"}
          autoComplete="new-password"
          placeholder="Ketik ulang kata sandi baru"
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          minLength={12}
          required
          disabled={busy}
        />
      </div>

      {message && (
        <p role="status" className="auth-message">
          {message}
        </p>
      )}

      <button
        type="submit"
        className="ui-button auth-submit"
        disabled={busy || !token}
      >
        {busy ? (
          <>
            <Loader2 className="animate-spin" size={18} /> Menyimpan…
          </>
        ) : (
          <>
            Simpan kata sandi baru
            <ArrowRight size={17} />
          </>
        )}
      </button>

      <div className="auth-bottom" style={{ textAlign: "center", marginTop: "1rem" }}>
        Ingat kata sandi Anda? <Link href="/login">Kembali ke halaman masuk</Link>
      </div>
    </form>
  );
}

export default function ResetPasswordPage() {
  return (
    <Shell>
      <AuthFrame
        title="Atur ulang kata sandi"
        description="Buat kata sandi baru yang aman dan mudah Anda ingat untuk akun Anda."
      >
        <Suspense
          fallback={
            <div style={{ textAlign: "center", padding: "2rem", color: "var(--muted)" }}>
              <Loader2 className="animate-spin" size={24} style={{ margin: "0 auto" }} />
              <p style={{ marginTop: "0.5rem" }}>Memuat formulir pemulihan…</p>
            </div>
          }
        >
          <ResetPasswordForm />
        </Suspense>
      </AuthFrame>
    </Shell>
  );
}
