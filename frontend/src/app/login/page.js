"use client";

import Link from "next/link";
import { useState } from "react";
import { Shell } from "../../components/Shell";
import { apiRequest } from "../../lib/api";
import { useRouter } from "next/navigation";

export default function Page() {
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const router = useRouter();
  async function submit(event) {
    event.preventDefault();
    if (busy) return;
    const form = new FormData(event.currentTarget);
    setBusy(true);
    setMessage("");
    try {
      await apiRequest("/login", {
        method: "POST",
        body: JSON.stringify({
          email: form.get("email"),
          password: form.get("password"),
        }),
      });
      router.push("/akun");
    } catch (error) {
      setMessage(
        error.status === 422
          ? "Email atau kata sandi tidak valid."
          : "Tidak dapat masuk. Periksa koneksi dan coba kembali."
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <Shell>
      <section className="page-intro">
        <p className="eyebrow">Akun</p>
        <h1>Masuk</h1>
        <form className="search-panel" onSubmit={submit}>
          <label>
            Email
            <input
              required
              name="email"
              type="email"
              autoComplete="username"
              disabled={busy}
            />
          </label>
          <label>
            Kata sandi
            <input
              required
              name="password"
              type="password"
              autoComplete="current-password"
              disabled={busy}
            />
          </label>
          <button disabled={busy}>{busy ? "Memproses…" : "Masuk"}</button>
        </form>
        <p aria-live="polite">{message}</p>
        <p>
          Belum punya akun? <Link href="/daftar">Daftar</Link>
        </p>
      </section>
    </Shell>
  );
}
