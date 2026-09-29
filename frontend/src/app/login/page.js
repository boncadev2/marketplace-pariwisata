"use client";

import Link from "next/link";
import { useState } from "react";
import { Shell } from "../../components/Shell";
import { apiRequest } from "../../lib/api";
import { useRouter } from "next/navigation";
import { LogIn, Mail, Lock } from "lucide-react";

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
      <div className="max-w-md mx-auto w-full bg-white rounded-2xl shadow-xl overflow-hidden mt-8 mb-16 border border-gray-100">
        <div className="bg-emerald-600 p-8 text-center text-white">
          <div className="bg-white/20 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
            <LogIn size={32} />
          </div>
          <h1 className="text-2xl font-bold">Selamat Datang Kembali</h1>
          <p className="text-emerald-100 mt-2 text-sm">Masuk untuk melanjutkan ke akun Anda</p>
        </div>
        
        <div className="p-8">
          <form onSubmit={submit} className="space-y-5">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Mail size={18} className="text-gray-400" />
                </div>
                <input
                  required
                  name="email"
                  type="email"
                  autoComplete="username"
                  disabled={busy}
                  className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all text-gray-700 bg-gray-50 focus:bg-white"
                  placeholder="nama@email.com"
                />
              </div>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Kata Sandi</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Lock size={18} className="text-gray-400" />
                </div>
                <input
                  required
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  disabled={busy}
                  className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all text-gray-700 bg-gray-50 focus:bg-white"
                  placeholder="••••••••"
                />
              </div>
              <div className="flex justify-end mt-2">
                <Link href="#" className="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Lupa kata sandi?</Link>
              </div>
            </div>
            
            {message && (
              <div className="bg-red-50 text-red-600 p-3 rounded-lg text-sm border border-red-100 flex items-center">
                {message}
              </div>
            )}
            
            <button 
              disabled={busy}
              className={`w-full py-3 px-4 rounded-xl text-white font-bold text-lg shadow-md transition-all flex justify-center items-center gap-2 ${busy ? 'bg-emerald-400 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 hover:shadow-lg'}`}
            >
              {busy ? "Memproses…" : "Masuk"}
            </button>
          </form>
          
          <div className="mt-8 pt-6 border-t border-gray-100 text-center">
            <p className="text-gray-600 text-sm">
              Belum punya akun? <Link href="/daftar" className="text-emerald-600 font-bold hover:underline">Daftar sekarang</Link>
            </p>
          </div>
        </div>
      </div>
    </Shell>
  );
}
