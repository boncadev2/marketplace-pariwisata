"use client";

import Link from "next/link";
import { useState } from "react";
import {
  UserRound,
  Mail,
  LockKeyhole,
  Loader2,
  ArrowRight,
  Eye,
  EyeOff,
} from "lucide-react";
import { Shell } from "../../components/Shell";
import { AuthFrame } from "../../components/AuthFrame";
import { apiRequest } from "../../lib/api";

export default function Page() {
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [complete, setComplete] = useState(false);
  const [visible, setVisible] = useState(false);
  async function submit(event) {
    event.preventDefault();
    if (busy) return;
    const data = new FormData(event.currentTarget);
    if (data.get("password") !== data.get("password_confirmation")) {
      setMessage("Konfirmasi kata sandi belum cocok.");
      return;
    }
    setBusy(true);
    setMessage("");
    try {
      await apiRequest("/register", {
        method: "POST",
        body: JSON.stringify(Object.fromEntries(data)),
      });
      setComplete(true);
      setMessage(
        "Akun berhasil dibuat. Periksa email verifikasi, lalu masuk ke akun Anda."
      );
    } catch (error) {
      setMessage(
        error.status === 422
          ? "Periksa data Anda. Email mungkin sudah digunakan atau kata sandi belum memenuhi ketentuan."
          : "Pendaftaran belum dapat diproses. Coba lagi."
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <Shell>
      <AuthFrame
        title="Cerita Anda dimulai di sini."
        description="Buat akun untuk menyimpan favorit dan mengelola perjalanan."
      >
        {complete ? (
          <div className="auth-success">
            <p role="status">{message}</p>
            <Link href="/login" className="ui-button">
              Masuk ke akun <ArrowRight size={17} />
            </Link>
          </div>
        ) : (
          <form onSubmit={submit} className="auth-form" aria-busy={busy}>
            <label htmlFor="register-name">Nama lengkap</label>
            <div className="auth-input">
              <UserRound size={18} />
              <input
                id="register-name"
                name="name"
                autoComplete="name"
                required
                maxLength={255}
                disabled={busy}
                placeholder="Nama lengkap Anda"
              />
            </div>
            <label htmlFor="register-email">Alamat email</label>
            <div className="auth-input">
              <Mail size={18} />
              <input
                id="register-email"
                name="email"
                type="email"
                autoComplete="email"
                required
                disabled={busy}
                placeholder="nama@email.com"
              />
            </div>
            <label htmlFor="register-password">Kata sandi</label>
            <div className="auth-input">
              <LockKeyhole size={18} />
              <input
                id="register-password"
                name="password"
                type={visible ? "text" : "password"}
                autoComplete="new-password"
                minLength={12}
                required
                disabled={busy}
                placeholder="Minimal 12 karakter"
              />
              <button
                type="button"
                className="password-toggle"
                aria-label={
                  visible ? "Sembunyikan kata sandi" : "Tampilkan kata sandi"
                }
                aria-pressed={visible}
                onClick={() => setVisible(!visible)}
              >
                {visible ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
            <label htmlFor="register-confirm">Konfirmasi kata sandi</label>
            <div className="auth-input">
              <LockKeyhole size={18} />
              <input
                id="register-confirm"
                name="password_confirmation"
                type={visible ? "text" : "password"}
                autoComplete="new-password"
                minLength={12}
                required
                disabled={busy}
                placeholder="Ulangi kata sandi"
              />
            </div>
            {message && (
              <p role="status" className="auth-message">
                {message}
              </p>
            )}
            <button disabled={busy} className="ui-button auth-submit">
              {busy ? (
                <>
                  <Loader2 className="animate-spin" size={18} /> Memproses…
                </>
              ) : (
                <>
                  Buat akun <ArrowRight size={17} />
                </>
              )}
            </button>
          </form>
        )}
        <div className="auth-bottom">
          Ingin membuka usaha? <Link href="/daftar-mitra">Daftar sebagai mitra</Link>
        </div>
        <div className="auth-bottom">
          Sudah punya akun? <Link href="/login">Masuk di sini</Link>
        </div>
      </AuthFrame>
    </Shell>
  );
}
