import Link from "next/link";
import {
  Clock3,
  MapPin,
  ArrowRight,
  Coffee,
  Mountain,
  Utensils,
} from "lucide-react";
import { Shell } from "../../../components/Shell";
import { PageHeader } from "../../../components/PageHeader";

export default function Page() {
  return (
    <Shell>
      <PageHeader
        eyebrow="Pratinjau · data demonstrasi"
        title="Sehari mengenal cerita desa."
        description="Contoh susunan perjalanan: suasana alam, budaya setempat, dan cita rasa lokal dalam satu hari yang berkesan."
        image="https://images.unsplash.com/photo-1513415564515-763d91423bdd?auto=format&fit=crop&w=900&q=85"
      />
      <div className="package-detail-grid">
        <section className="itinerary-panel">
          <div className="itinerary-heading">
            <div>
              <p className="section-kicker">RENCANA PERJALANAN</p>
              <h2>Hari yang penuh pengalaman.</h2>
            </div>
            <span>
              <Clock3 size={16} /> 1 hari · contoh
            </span>
          </div>
          <ol className="itinerary">
            {[
              {
                time: "08.00",
                title: "Bertemu dan mengenal desa",
                body: "Titik kumpul di balai desa. Pengantar singkat sebelum perjalanan.",
                icon: Coffee,
              },
              {
                time: "09.30",
                title: "Jelajah alam sekitar",
                body: "Berjalan santai dan menikmati pemandangan bersama pemandu.",
                icon: Mountain,
              },
              {
                time: "12.00",
                title: "Mencicipi cita rasa lokal",
                body: "Beristirahat dan menikmati hidangan daerah.",
                icon: Utensils,
              },
              {
                time: "14.00",
                title: "Cerita budaya dan perjalanan pulang",
                body: "Mengenal kegiatan lokal sebelum menutup perjalanan.",
                icon: MapPin,
              },
            ].map(({ time, title, body, icon: Icon }) => (
              <li key={time}>
                <span className="itinerary-icon">
                  <Icon size={19} />
                </span>
                <div>
                  <span>{time}</span>
                  <h3>{title}</h3>
                  <p>{body}</p>
                </div>
              </li>
            ))}
          </ol>
        </section>
        <aside className="detail-plan">
          <h2>Mulai dari rencana.</h2>
          <p>
            Itinerary ini merupakan contoh desain, bukan paket yang dapat
            dipesan. Lihat katalog untuk paket terbit yang tersedia.
          </p>
          <Link href="/paket" className="ui-button">
            Lihat katalog paket <ArrowRight size={16} />
          </Link>
          <div className="detail-demo-note">
            Tanggal, harga, titik kumpul, dan kebijakan mengikuti informasi
            paket terbit.
          </div>
        </aside>
      </div>
    </Shell>
  );
}
