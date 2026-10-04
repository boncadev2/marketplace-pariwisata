"use client";

import Link from "next/link";
import { useState } from "react";
import { useRouter } from "next/navigation";
import {
  Eye,
  EyeOff,
  LockKeyhole,
  Mail,
  Loader2,
  ArrowRight,
} from "lucide-react";
import { Shell } from "../../components/Shell";
import { AuthFrame } from "../../components/AuthFrame";
import { apiRequest } from "../../lib/api";

export default function Page() {
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [visible, setVisible] = useState(false);
  const [forgot, setForgot] = useState(false);
  const router = useRouter();
  async function submit(event) {
    event.preventDefault();
    if (busy) return;
    const form = new FormData(event.currentTarget);
    setBusy(true);
    setMessage("");
    try {
      const result = await apiRequest(forgot ? "/forgot-password" : "/login", {
        method: "POST",
        body: JSON.stringify(
          forgot
            ? { email: form.get("email") }
            : { email: form.get("email"), password: form.get("password") }
        ),
      });
      if (forgot)
        setMessage(
          "Jika akun tersedia, instruksi pemulihan akan dikirim ke email Anda."
        );
      else
        router.push(
          result.redirect_to === "/dashboard" ? "/dashboard" : "/akun"
        );
    } catch (error) {
      setMessage(
        error.status === 422
          ? "Periksa email dan kata sandi Anda."
          : "Belum dapat diproses. Periksa koneksi dan coba lagi."
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <Shell>
      <AuthFrame
        title={forgot ? "Lupa kata sandi?" : "Senang bertemu lagi."}
        description={
          forgot
            ? "Masukkan email akun Anda untuk meminta instruksi pemulihan."
            : "Masuk untuk melanjutkan rencana perjalanan Anda."
        }
      >
        <form onSubmit={submit} className="auth-form" aria-busy={busy}>
          <label htmlFor="login-email">Alamat email</label>
          <div className="auth-input">
            <Mail size={18} />
            <input
              id="login-email"
              name="email"
              type="email"
              autoComplete="username"
              placeholder="nama@email.com"
              required
              disabled={busy}
            />
          </div>
          {!forgot && (
            <>
              <div className="auth-label-row">
                <label htmlFor="login-password">Kata sandi</label>
                <button
                  type="button"
                  onClick={() => {
                    setForgot(true);
                    setMessage("");
                  }}
                  disabled={busy}
                >
                  Lupa kata sandi?
                </button>
              </div>
              <div className="auth-input">
                <LockKeyhole size={18} />
                <input
                  id="login-password"
                  name="password"
                  type={visible ? "text" : "password"}
                  autoComplete="current-password"
                  placeholder="Masukkan kata sandi"
                  required
                  disabled={busy}
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
            </>
          )}
          {message && (
            <p role="status" className="auth-message">
              {message}
            </p>
          )}
          <button
            type="submit"
            className="ui-button auth-submit"
            disabled={busy}
          >
            {busy ? (
              <>
                <Loader2 className="animate-spin" size={18} /> Memproses…
              </>
            ) : (
              <>
                {forgot ? "Kirim instruksi pemulihan" : "Masuk ke akun"}
                <ArrowRight size={17} />
              </>
            )}
          </button>
          {forgot && (
            <button
              type="button"
              className="auth-back"
              onClick={() => {
                setForgot(false);
                setMessage("");
              }}
            >
              Kembali ke halaman masuk
            </button>
          )}
        </form>
        <div className="auth-bottom">
          Belum punya akun? <Link href="/daftar">Daftar sekarang</Link>
        </div>
        <p className="auth-help">
          <LockKeyhole size={13} /> Jangan bagikan kata sandi atau kode akses
          kepada siapa pun.
        </p>
      </AuthFrame>
    </Shell>
  );
}
