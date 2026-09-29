import { DestinationSearch } from "../../components/DestinationSearch";
import { Shell } from "../../components/Shell";
import { MapPin } from "lucide-react";

export default function Page() {
  return (
    <Shell>
      <div className="bg-emerald-600 rounded-3xl p-8 md:p-12 text-white mb-10 shadow-lg relative overflow-hidden">
        {/* Abstract Background Element */}
        <div className="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 rounded-full bg-white opacity-10 blur-3xl"></div>
        
        <div className="relative z-10 max-w-2xl">
          <div className="flex items-center gap-2 text-emerald-200 font-bold mb-3 uppercase tracking-wider text-sm">
            <MapPin size={18} />
            <span>Destinasi Pilihan</span>
          </div>
          <h1 className="text-3xl md:text-5xl font-extrabold mb-4 leading-tight">
            Temukan Wisata <br />Impian Anda
          </h1>
          <p className="text-emerald-50 text-lg max-w-xl">
            Jelajahi berbagai keindahan desa wisata, pemandangan alam, dan tempat-tempat unik di seluruh daerah.
          </p>
        </div>
      </div>
      
      <DestinationSearch />
    </Shell>
  );
}
