"use client";

import Link from "next/link";
import { useState } from "react";
import { Shell } from "../../components/Shell";

export default function Page() {
  const [message, setMessage] = useState("");
  function submit(event) {
    event.preventDefault();
    setMessage(
      "Pendaftaran akan diproses melalui API setelah konfigurasi email selesai."
    );
  }
  return (
    <Shell>
      <section className="page-intro">
        <p className="eyebrow">Akun</p>
        <h1>Buat akun</h1>
        <form className="search-panel" onSubmit={submit}>
          <label>
            Nama
            <input required />
          </label>
          <label>
            Email
            <input required type="email" />
          </label>
          <label>
            Kata sandi
            <input required minLength="12" type="password" />
          </label>
          <button>Daftar</button>
        </form>
        <p aria-live="polite">{message}</p>
        <p>
          Sudah punya akun? <Link href="/login">Masuk</Link>
        </p>
      </section>
    </Shell>
  );
}
