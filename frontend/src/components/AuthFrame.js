import Link from "next/link";
import { ArrowUpRight, HeartHandshake, MapPin, Sparkles } from "lucide-react";

export function AuthFrame({ children, title, description }) {
  return (
    <section className="auth-layout">
      <aside className="auth-story">
        <div className="auth-story-shade" />
        <div className="auth-story-content">
          <span className="auth-kicker">
            <Sparkles size={16} /> CERITA BARU MENANTI
          </span>
          <h2>
            Perjalanan terbaik
            <br />
            dimulai dari sini.
          </h2>
          <p>
            Simpan tempat favorit, kelola pesanan, dan nikmati pengalaman daerah
            dengan lebih nyaman.
          </p>
          <Link href="/destinasi">
            Temukan inspirasi <ArrowUpRight size={18} />
          </Link>
          <div className="auth-story-note">
            <HeartHandshake size={19} />
            <span>Kenali daerah. Dukung pengalaman lokal.</span>
          </div>
        </div>
        <span className="auth-photo-label">
          <MapPin size={13} /> Foto ilustrasi wisata
        </span>
      </aside>
      <div className="auth-form-panel">
        <span className="section-kicker">SELAMAT DATANG DI WISATADAERAH</span>
        <h1>{title}</h1>
        <p className="auth-description">{description}</p>
        {children}
      </div>
    </section>
  );
}
