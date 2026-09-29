"use client";

import Link from "next/link";
import { useState } from "react";
import { Shell } from "../../components/Shell";
import { UserPlus, User, Mail, Lock } from "lucide-react";

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
      <div className="max-w-md mx-auto w-full bg-white rounded-2xl shadow-xl overflow-hidden mt-8 mb-16 border border-gray-100">
        <div className="bg-emerald-600 p-8 text-center text-white">
          <div className="bg-white/20 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
            <UserPlus size={32} />
          </div>
          <h1 className="text-2xl font-bold">Buat Akun Baru</h1>
          <p className="text-emerald-100 mt-2 text-sm">Bergabung dan mulai petualangan Anda</p>
        </div>
        
        <div className="p-8">
          <form onSubmit={submit} className="space-y-5">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <User size={18} className="text-gray-400" />
                </div>
                <input
                  required
                  name="name"
                  type="text"
                  className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all text-gray-700 bg-gray-50 focus:bg-white"
                  placeholder="Nama lengkap Anda"
                />
              </div>
            </div>

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
                  minLength="12"
                  className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all text-gray-700 bg-gray-50 focus:bg-white"
                  placeholder="Minimal 12 karakter"
                />
              </div>
            </div>
            
            {message && (
              <div className="bg-emerald-50 text-emerald-700 p-3 rounded-lg text-sm border border-emerald-100 flex items-center">
                {message}
              </div>
            )}
            
            <button 
              className="w-full py-3 px-4 rounded-xl text-white font-bold text-lg shadow-md transition-all flex justify-center items-center gap-2 bg-emerald-600 hover:bg-emerald-700 hover:shadow-lg mt-2"
            >
              Daftar Sekarang
            </button>
          </form>
          
          <div className="mt-8 pt-6 border-t border-gray-100 text-center">
            <p className="text-gray-600 text-sm">
              Sudah punya akun? <Link href="/login" className="text-emerald-600 font-bold hover:underline">Masuk di sini</Link>
            </p>
          </div>
        </div>
      </div>
    </Shell>
  );
}
