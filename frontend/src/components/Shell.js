import Link from "next/link";
import { MapPin, Search, WalletCards, User, ChevronRight } from "lucide-react";

export function Shell({ children }) {
  return (
    <div className="min-h-screen bg-gray-50 pb-20 md:pb-0 font-sans flex flex-col">
      {/* Desktop Navbar (Solid for inner pages) */}
      <div className="bg-emerald-600 shadow-md hidden md:block">
        <div className="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center text-white">
          <Link href="/" className="text-3xl font-extrabold tracking-tight flex items-center gap-2">
            <MapPin size={28} className="text-emerald-300" />
            WisataDaerah
          </Link>
          <div className="flex items-center gap-8 text-sm font-semibold">
            <Link href="/destinasi" className="hover:text-emerald-200 transition-colors">Promo</Link>
            <Link href="/bantuan" className="hover:text-emerald-200 transition-colors">Bantuan</Link>
            <Link href="/akun" className="hover:text-emerald-200 transition-colors">Pesanan Saya</Link>
            <Link href="/login" className="flex items-center gap-2 bg-white text-emerald-700 hover:bg-gray-100 px-5 py-2.5 rounded-full cursor-pointer transition-colors shadow-sm">
              <User size={16} />
              <span>Login / Daftar</span>
            </Link>
          </div>
        </div>
      </div>

      {/* Mobile Top Navbar (Solid) */}
      <div className="bg-emerald-600 text-white p-4 flex justify-between items-center md:hidden shadow-md">
        <Link href="/" className="text-2xl font-extrabold tracking-tight flex items-center gap-2">
          <MapPin size={24} className="text-emerald-300" />
          WisataDaerah
        </Link>
      </div>

      {/* Main Content */}
      <main className="flex-grow max-w-6xl mx-auto px-4 py-8 w-full">
        {children}
      </main>

      {/* Mobile Bottom Nav Bar */}
      <div className="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 flex justify-around py-3 px-2 z-50 rounded-t-2xl shadow-[0_-10px_20px_-15px_rgba(0,0,0,0.1)]">
        <Link href="/" className="flex flex-col items-center text-gray-400 hover:text-emerald-600 transition-colors">
          <Search size={24} strokeWidth={2} />
          <span className="text-[10px] font-bold mt-1">Explore</span>
        </Link>
        <Link href="/akun" className="flex flex-col items-center text-gray-400 hover:text-emerald-500 transition-colors cursor-pointer">
          <WalletCards size={24} strokeWidth={1.5} />
          <span className="text-[10px] font-medium mt-1">Pesanan</span>
        </Link>
        <Link href="/akun" className="flex flex-col items-center text-gray-400 hover:text-emerald-500 transition-colors cursor-pointer">
          <User size={24} strokeWidth={1.5} />
          <span className="text-[10px] font-medium mt-1">Akun</span>
        </Link>
      </div>

      {/* Desktop Footer */}
      <footer className="hidden md:block bg-gray-900 text-gray-300 py-16 border-t-[6px] border-emerald-600 mt-auto">
        <div className="max-w-6xl mx-auto px-4 grid grid-cols-4 gap-8">
          <div className="col-span-1">
            <h3 className="text-white font-extrabold text-2xl mb-4 flex items-center gap-2">
              <MapPin className="text-emerald-500" size={28} />
              WisataDaerah
            </h3>
            <p className="text-sm text-gray-400 leading-relaxed">Platform terbaik untuk menemukan keindahan pariwisata daerah di seluruh Indonesia dengan nuansa alam yang asri.</p>
          </div>
          <div>
            <h4 className="text-white font-bold mb-6 text-lg">Produk</h4>
            <ul className="text-sm space-y-3 text-gray-400">
              <li><Link href="/destinasi" className="hover:text-emerald-400 transition-colors flex items-center gap-2"><ChevronRight size={14}/> Destinasi Wisata</Link></li>
              <li><Link href="/penginapan" className="hover:text-emerald-400 transition-colors flex items-center gap-2"><ChevronRight size={14}/> Penginapan & Hotel</Link></li>
              <li><Link href="/paket" className="hover:text-emerald-400 transition-colors flex items-center gap-2"><ChevronRight size={14}/> Paket Tour Desa</Link></li>
              <li><Link href="/kuliner" className="hover:text-emerald-400 transition-colors flex items-center gap-2"><ChevronRight size={14}/> Kuliner Lokal</Link></li>
            </ul>
          </div>
          <div>
            <h4 className="text-white font-bold mb-6 text-lg">Dukungan</h4>
            <ul className="text-sm space-y-3 text-gray-400">
              <li><Link href="/bantuan" className="hover:text-emerald-400 transition-colors flex items-center gap-2"><ChevronRight size={14}/> Pusat Bantuan</Link></li>
              <li><Link href="#" className="hover:text-emerald-400 transition-colors flex items-center gap-2"><ChevronRight size={14}/> Kebijakan Privasi</Link></li>
              <li><Link href="#" className="hover:text-emerald-400 transition-colors flex items-center gap-2"><ChevronRight size={14}/> Syarat & Ketentuan</Link></li>
              <li><Link href="/bantuan" className="hover:text-emerald-400 transition-colors flex items-center gap-2"><ChevronRight size={14}/> Hubungi Kami</Link></li>
            </ul>
          </div>
          <div>
            <h4 className="text-white font-bold mb-6 text-lg">Mitra</h4>
            <ul className="text-sm space-y-3 text-gray-400">
              <li><Link href="/daftar" className="hover:text-emerald-400 transition-colors flex items-center gap-2"><ChevronRight size={14}/> Daftar Menjadi Mitra</Link></li>
              <li><Link href="/login" className="hover:text-emerald-400 transition-colors flex items-center gap-2"><ChevronRight size={14}/> Portal Pengelola</Link></li>
            </ul>
          </div>
        </div>
      </footer>
    </div>
  );
}

export function Card({ title, meta, price, href = "#", children, image = "https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=600&q=80" }) {
  return (
    <article className="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl border border-gray-100 group transition-all duration-300 flex flex-col h-full">
      <div className="h-48 bg-gray-200 overflow-hidden relative">
        <img src={image} alt={title} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
      </div>
      <div className="p-5 flex flex-col flex-grow">
        {meta && <p className="text-xs text-emerald-600 font-bold mb-2 uppercase tracking-wide">{meta}</p>}
        <h2 className="font-bold text-gray-800 text-lg mb-2 group-hover:text-emerald-600 transition-colors">{title}</h2>
        {price && <p className="text-gray-600 font-medium mb-4">{price}</p>}
        <div className="mt-auto pt-4 border-t border-gray-100 flex justify-between items-center">
          <Link href={href} className="text-sm text-emerald-600 font-bold hover:underline flex items-center gap-1">
            Lihat detail <ChevronRight size={16} />
          </Link>
          {children}
        </div>
      </div>
    </article>
  );
}
