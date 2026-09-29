import Link from "next/link";
import { Search, MapPin, Bed, Utensils, Bus, WalletCards, User, Bell, ChevronRight } from "lucide-react";

export default function Home() {
  return (
    <div className="min-h-screen bg-gray-50 pb-20 md:pb-0 font-sans">
      
      {/* =========================================
          DESKTOP HERO SECTION (WITH IMAGE)
      ========================================= */}
      <div className="hidden md:block relative w-full h-[500px]">
        {/* Background Image & Overlay */}
        <div className="absolute inset-0 z-0">
          <img 
            src="https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=1920&q=80" 
            alt="Hero Background" 
            className="w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-b from-gray-900/70 via-gray-900/40 to-transparent"></div>
        </div>

        {/* Top Navbar (Transparent over Hero) */}
        <div className="relative z-10 max-w-6xl mx-auto px-4 py-4 flex justify-between items-center text-white">
          <div className="text-3xl font-extrabold tracking-tight flex items-center gap-2">
            <MapPin size={28} className="text-emerald-400" />
            WisataDaerah
          </div>
          <div className="flex items-center gap-8 text-sm font-semibold">
            <Link href="/destinasi" className="hover:text-emerald-300 transition-colors drop-shadow-md">Promo</Link>
            <Link href="/bantuan" className="hover:text-emerald-300 transition-colors drop-shadow-md">Bantuan</Link>
            <Link href="/akun" className="hover:text-emerald-300 transition-colors drop-shadow-md">Pesanan Saya</Link>
            <Link href="/login" className="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 rounded-full cursor-pointer transition-colors shadow-lg">
              <User size={16} />
              <span>Login / Daftar</span>
            </Link>
          </div>
        </div>

        {/* Desktop Hero Content & Search Box */}
        <div className="relative z-10 max-w-6xl mx-auto px-4 pt-16 flex flex-col items-center text-center">
          <h1 className="text-5xl font-bold text-white mb-4 drop-shadow-lg leading-tight">Mulai Petualangan<br/>Tak Terlupakan Anda</h1>
          <p className="text-xl text-gray-100 mb-10 drop-shadow-md font-medium max-w-2xl">Jelajahi keindahan desa wisata, penginapan nyaman, dan kuliner autentik di seluruh pelosok daerah.</p>
          
          {/* Main Search Card */}
          <div className="bg-white rounded-2xl shadow-2xl p-6 max-w-4xl w-full transform translate-y-8 text-left">
            <div className="flex gap-6 mb-4 border-b border-gray-200 pb-2">
              <button className="text-emerald-600 font-bold border-b-2 border-emerald-600 pb-2 flex items-center gap-2"><MapPin size={18}/> Destinasi</button>
              <button className="text-gray-500 hover:text-gray-800 font-medium pb-2 flex items-center gap-2"><Bed size={18}/> Penginapan</button>
              <button className="text-gray-500 hover:text-gray-800 font-medium pb-2 flex items-center gap-2"><Bus size={18}/> Paket Tour</button>
            </div>
            
            <div className="flex gap-4">
              <div className="flex-1 border border-gray-300 rounded-xl px-4 py-3 flex items-center focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-all">
                <Search size={20} className="text-gray-400 mr-3" />
                <input 
                  type="text" 
                  placeholder="Mau liburan kemana? (Cth: Bali, Jogja...)" 
                  className="w-full bg-transparent outline-none text-gray-700 font-medium"
                />
              </div>
              <button className="bg-orange-500 hover:bg-orange-600 transition-colors text-white font-bold py-3 px-10 rounded-xl flex items-center gap-2 shadow-lg">
                <Search size={20} />
                Cari
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* =========================================
          MOBILE HERO SECTION (WITH IMAGE)
      ========================================= */}
      <div className="md:hidden relative w-full h-[280px] rounded-b-[2.5rem] shadow-md overflow-hidden z-10">
        <div className="absolute inset-0 z-0">
          <img 
            src="https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=800&q=80" 
            alt="Hero Background" 
            className="w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-b from-gray-900/70 via-emerald-900/50 to-emerald-800/90"></div>
        </div>

        <div className="relative z-10 p-5 pt-10 text-white">
          <div className="flex justify-between items-center mb-6">
            <div>
              <h1 className="text-2xl font-extrabold tracking-tight drop-shadow-md">WisataDaerah</h1>
              <p className="text-sm text-emerald-100 mt-1 font-medium">Mau liburan kemana hari ini?</p>
            </div>
            <div className="bg-white/20 p-2 rounded-full backdrop-blur-md shadow-sm">
              <Bell size={24} />
            </div>
          </div>

          <div className="bg-white rounded-xl p-3 flex items-center shadow-lg text-gray-700 transform translate-y-6">
            <Search size={20} className="text-gray-400 mr-3" />
            <input 
              type="text" 
              placeholder="Cari destinasi, penginapan..." 
              className="w-full outline-none text-sm font-medium bg-transparent"
            />
          </div>
        </div>
      </div>

      {/* =========================================
          MAIN MENUS (RESPONSIVE)
      ========================================= */}
      <div className="max-w-6xl mx-auto px-5 md:px-4 mt-12 md:mt-24 md:relative z-20">
        <div className="grid grid-cols-4 md:flex md:justify-center md:gap-8 gap-4 bg-white p-5 md:p-8 rounded-2xl md:rounded-2xl shadow-sm md:shadow-lg border border-gray-100">
          <Link href="/destinasi" className="flex flex-col items-center gap-3 group md:w-32">
            <div className="bg-emerald-50 p-3 md:p-5 rounded-2xl text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300 group-hover:-translate-y-1 group-hover:shadow-md">
              <MapPin size={28} strokeWidth={1.5} className="md:w-10 md:h-10" />
            </div>
            <span className="text-[11px] md:text-sm font-bold text-gray-700">Destinasi</span>
          </Link>
          <Link href="/penginapan" className="flex flex-col items-center gap-3 group md:w-32">
            <div className="bg-teal-50 p-3 md:p-5 rounded-2xl text-teal-600 group-hover:bg-teal-600 group-hover:text-white transition-all duration-300 group-hover:-translate-y-1 group-hover:shadow-md">
              <Bed size={28} strokeWidth={1.5} className="md:w-10 md:h-10" />
            </div>
            <span className="text-[11px] md:text-sm font-bold text-gray-700">Penginapan</span>
          </Link>
          <Link href="/paket" className="flex flex-col items-center gap-3 group md:w-32">
            <div className="bg-amber-50 p-3 md:p-5 rounded-2xl text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition-all duration-300 group-hover:-translate-y-1 group-hover:shadow-md">
              <Bus size={28} strokeWidth={1.5} className="md:w-10 md:h-10" />
            </div>
            <span className="text-[11px] md:text-sm font-bold text-gray-700">Paket Wisata</span>
          </Link>
          <Link href="/kuliner" className="flex flex-col items-center gap-3 group md:w-32">
            <div className="bg-rose-50 p-3 md:p-5 rounded-2xl text-rose-500 group-hover:bg-rose-500 group-hover:text-white transition-all duration-300 group-hover:-translate-y-1 group-hover:shadow-md">
              <Utensils size={28} strokeWidth={1.5} className="md:w-10 md:h-10" />
            </div>
            <span className="text-[11px] md:text-sm font-bold text-gray-700">Kuliner</span>
          </Link>
        </div>
      </div>

      {/* =========================================
          PROMOTIONS SECTION
      ========================================= */}
      <div className="max-w-6xl mx-auto mt-8 md:mt-16 px-5 md:px-4">
        <div className="flex justify-between items-end mb-4 md:mb-6">
          <h2 className="text-lg md:text-2xl font-extrabold text-gray-800">Promo Menarik Untukmu</h2>
          <Link href="/penginapan" className="text-xs md:text-sm text-emerald-600 font-bold cursor-pointer hover:underline flex items-center">Lihat Semua <ChevronRight size={16}/></Link>
        </div>
        <div className="flex gap-4 md:gap-6 overflow-x-auto pb-4 hide-scrollbar snap-x">
          <Link href="/penginapan" className="snap-start min-w-[280px] md:min-w-[420px] bg-[url('https://images.unsplash.com/photo-1543637005-4d639a4e160c?auto=format&fit=crop&w=800&q=80')] bg-cover bg-center rounded-2xl text-white shadow-md flex flex-col justify-between h-[150px] md:h-[220px] relative overflow-hidden group cursor-pointer">
            <div className="absolute inset-0 bg-gradient-to-r from-emerald-900/90 to-transparent group-hover:from-emerald-900/80 transition-all"></div>
            <div className="relative z-10 p-5 md:p-8 flex flex-col justify-between h-full">
              <div>
                <span className="bg-orange-500 text-[10px] md:text-xs font-bold px-2 md:px-3 py-1 rounded shadow-sm mb-2 inline-block">HOT PROMO</span>
                <h3 className="font-bold text-lg md:text-2xl leading-tight">Diskon Penginapan<br/>Hingga 50%</h3>
              </div>
              <p className="text-xs md:text-sm font-medium text-emerald-100 flex items-center gap-1 group-hover:translate-x-1 transition-transform">Klaim Sekarang <ChevronRight size={14}/></p>
            </div>
          </Link>
          <Link href="/paket" className="snap-start min-w-[280px] md:min-w-[420px] bg-[url('https://images.unsplash.com/photo-1506462945848-ac8ea6f609cc?auto=format&fit=crop&w=800&q=80')] bg-cover bg-center rounded-2xl text-white shadow-md flex flex-col justify-between h-[150px] md:h-[220px] relative overflow-hidden group cursor-pointer">
            <div className="absolute inset-0 bg-gradient-to-r from-amber-900/90 to-transparent group-hover:from-amber-900/80 transition-all"></div>
            <div className="relative z-10 p-5 md:p-8 flex flex-col justify-between h-full">
              <div>
                <span className="bg-emerald-500 text-[10px] md:text-xs font-bold px-2 md:px-3 py-1 rounded shadow-sm mb-2 inline-block">CASHBACK</span>
                <h3 className="font-bold text-lg md:text-2xl leading-tight">Cashback Paket Tour<br/>Rp 200.000</h3>
              </div>
              <p className="text-xs md:text-sm font-medium text-amber-100 flex items-center gap-1 group-hover:translate-x-1 transition-transform">Lihat Detail <ChevronRight size={14}/></p>
            </div>
          </Link>
        </div>
      </div>

      {/* =========================================
          POPULAR DESTINATIONS
      ========================================= */}
      <div className="max-w-6xl mx-auto mt-6 md:mt-16 px-5 md:px-4 mb-10 md:mb-24">
        <h2 className="text-lg md:text-2xl font-extrabold text-gray-800 mb-4 md:mb-8">Rekomendasi Destinasi</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
          <Link href="/destinasi/demo" className="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl border border-gray-100 group transition-all duration-300">
            <div className="h-36 md:h-56 bg-gray-200 overflow-hidden relative">
                <img src="https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=600&q=80" alt="Bali" className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
                <div className="absolute top-2 right-2 bg-white/90 backdrop-blur text-gray-800 text-[10px] md:text-xs font-bold px-2 py-1 rounded-full flex items-center gap-1">
                  ⭐ 4.8
                </div>
            </div>
            <div className="p-4 md:p-5">
              <h3 className="font-bold text-gray-800 text-sm md:text-lg mb-1 group-hover:text-emerald-600 transition-colors">Pantai Kuta</h3>
              <p className="text-[11px] md:text-sm text-gray-500 flex items-center gap-1"><MapPin size={12} className="text-emerald-500"/> Bali, Indonesia</p>
            </div>
          </Link>
          <Link href="/destinasi/demo" className="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl border border-gray-100 group cursor-pointer transition-all duration-300">
            <div className="h-36 md:h-56 bg-gray-200 overflow-hidden relative">
               <img src="https://images.unsplash.com/photo-1513415564515-763d91423bdd?auto=format&fit=crop&w=600&q=80" alt="Jogja" className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
               <div className="absolute top-2 right-2 bg-white/90 backdrop-blur text-gray-800 text-[10px] md:text-xs font-bold px-2 py-1 rounded-full flex items-center gap-1">
                  ⭐ 4.9
                </div>
            </div>
            <div className="p-4 md:p-5">
              <h3 className="font-bold text-gray-800 text-sm md:text-lg mb-1 group-hover:text-emerald-600 transition-colors">Candi Borobudur</h3>
              <p className="text-[11px] md:text-sm text-gray-500 flex items-center gap-1"><MapPin size={12} className="text-emerald-500"/> Jawa Tengah</p>
            </div>
          </Link>
          <Link href="/destinasi/demo" className="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl border border-gray-100 group cursor-pointer transition-all duration-300 hidden md:block">
            <div className="h-56 bg-gray-200 overflow-hidden relative">
               <img src="https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=600&q=80" alt="Bromo" className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
               <div className="absolute top-2 right-2 bg-white/90 backdrop-blur text-gray-800 text-[10px] md:text-xs font-bold px-2 py-1 rounded-full flex items-center gap-1">
                  ⭐ 4.7
                </div>
            </div>
            <div className="p-5">
              <h3 className="font-bold text-gray-800 text-lg mb-1 group-hover:text-emerald-600 transition-colors">Gunung Bromo</h3>
              <p className="text-sm text-gray-500 flex items-center gap-1"><MapPin size={14} className="text-emerald-500"/> Jawa Timur</p>
            </div>
          </Link>
          <Link href="/destinasi/demo" className="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl border border-gray-100 group cursor-pointer transition-all duration-300 hidden md:block">
            <div className="h-56 bg-gray-200 overflow-hidden relative">
               <img src="https://images.unsplash.com/photo-1570222094114-d054a817e56b?auto=format&fit=crop&w=600&q=80" alt="Raja Ampat" className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
               <div className="absolute top-2 right-2 bg-white/90 backdrop-blur text-gray-800 text-[10px] md:text-xs font-bold px-2 py-1 rounded-full flex items-center gap-1">
                  ⭐ 5.0
                </div>
            </div>
            <div className="p-5">
              <h3 className="font-bold text-gray-800 text-lg mb-1 group-hover:text-emerald-600 transition-colors">Raja Ampat</h3>
              <p className="text-sm text-gray-500 flex items-center gap-1"><MapPin size={14} className="text-emerald-500"/> Papua Barat</p>
            </div>
          </Link>
        </div>
      </div>
      
      {/* =========================================
          MOBILE BOTTOM NAV BAR
      ========================================= */}
      <div className="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 flex justify-around py-3 px-2 z-50 rounded-t-2xl shadow-[0_-10px_20px_-15px_rgba(0,0,0,0.1)]">
        <Link href="/" className="flex flex-col items-center text-emerald-600">
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

      {/* =========================================
          DESKTOP FOOTER
      ========================================= */}
      <footer className="hidden md:block bg-gray-900 text-gray-300 py-16 border-t-[6px] border-emerald-600">
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
      
      <style dangerouslySetInnerHTML={{__html: `
        .hide-scrollbar::-webkit-scrollbar {
          display: none;
        }
        .hide-scrollbar {
          -ms-overflow-style: none;
          scrollbar-width: none;
        }
      `}} />
    </div>
  );
}
