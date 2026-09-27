"use client";

import Link from "next/link";
import { useState } from "react";
import { Shell } from "../../components/Shell";

export default function Page() {
  const [message, setMessage] = useState("");
  async function submit(event) {
    event.preventDefault();
    setMessage("Autentikasi backend sedang disiapkan.");
  }
  return (
    <Shell>
      <section className="page-intro">
        <p className="eyebrow">Akun</p>
        <h1>Masuk</h1>
        <form className="search-panel" onSubmit={submit}>
          <label>
            Email
            <input required type="email" />
          </label>
          <label>
            Kata sandi
            <input required minLength="12" type="password" />
          </label>
          <button>Masuk</button>
        </form>
        <p aria-live="polite">{message}</p>
        <p>
          Belum punya akun? <Link href="/daftar">Daftar</Link>
        </p>
      </section>
    </Shell>
  );
}
