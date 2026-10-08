"use client";

import Link from "next/link";
import {
  ArrowUpRight,
  Compass,
  MapPin,
  ChevronRight,
  HeartHandshake,
  Mail,
  Phone,
  MessageCircle,
  Clock,
  ShieldAlert,
} from "lucide-react";
import { Brand, RouteNavigation, SiteNavigation } from "./SiteNavigation";
import { useSettings } from "../lib/settings";
import { OfflineNotice } from "./OfflineNotice";
import { PwaInstallBanner } from "./PwaInstallBanner";
import { MobileBottomNav } from "./MobileBottomNav";

function SocialIcon({ type }) {
  if (type === "instagram") {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
      </svg>
    );
  }
  if (type === "facebook") {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
      </svg>
    );
  }
  if (type === "tiktok") {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path>
      </svg>
    );
  }
  if (type === "youtube") {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path>
        <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon>
      </svg>
    );
  }
  if (type === "twitter") {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M4 4l11.733 16h4.267l-11.733 -16z"></path>
        <path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"></path>
      </svg>
    );
  }
  return <MessageCircle size={18} />;
}

export function Shell({ children, home = false }) {
  const { settings } = useSettings();

  return (
    <div className="travel-app">
      <OfflineNotice />
      <a href="#main-content" className="skip-link">
        Lewati ke konten utama
      </a>
      <SiteNavigation />
      <main
        id="main-content"
        tabIndex={-1}
        className={home ? "home-main" : "site-container page-main"}
      >
        <RouteNavigation />
        {children}
      </main>
      <footer className="site-footer">
        <div className="site-container footer-grid">
          <div className="footer-brand">
            <Brand />
            <p>
              {settings.app_tagline ||
                "Perjalanan yang berkesan dimulai dari tempat yang dekat. Temukan alam, budaya, dan cerita baru bersama daerah."}
            </p>
            {settings.address && (
              <div className="footer-address">
                <MapPin size={15} className="shrink-0 text-slate-400" />
                <span>{settings.address}</span>
              </div>
            )}
            {(settings.contact_email || settings.contact_phone || settings.contact_hours || settings.emergency_phone) && (
              <div className="footer-contacts">
                {settings.contact_email && (
                  <span className="footer-contact-item">
                    <Mail size={13} /> {settings.contact_email}
                  </span>
                )}
                {settings.contact_phone && (
                  <span className="footer-contact-item">
                    <Phone size={13} /> {settings.contact_phone}
                  </span>
                )}
                {settings.contact_hours && (
                  <span className="footer-contact-item">
                    <Clock size={13} /> {settings.contact_hours}
                  </span>
                )}
                {settings.emergency_phone && (
                  <span className="footer-contact-item" style={{ color: "#e11d48", fontWeight: 600 }}>
                    <ShieldAlert size={13} /> Darurat: {settings.emergency_phone}
                  </span>
                )}
              </div>
            )}
            <div className="footer-socials">
              {settings.social_instagram && (
                <a
                  href={settings.social_instagram}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="social-btn"
                  title="Instagram"
                >
                  <SocialIcon type="instagram" />
                </a>
              )}
              {settings.social_facebook && (
                <a
                  href={settings.social_facebook}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="social-btn"
                  title="Facebook"
                >
                  <SocialIcon type="facebook" />
                </a>
              )}
              {settings.social_tiktok && (
                <a
                  href={settings.social_tiktok}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="social-btn"
                  title="TikTok"
                >
                  <SocialIcon type="tiktok" />
                </a>
              )}
              {settings.social_youtube && (
                <a
                  href={settings.social_youtube}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="social-btn"
                  title="YouTube"
                >
                  <SocialIcon type="youtube" />
                </a>
              )}
              {settings.social_twitter && (
                <a
                  href={settings.social_twitter}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="social-btn"
                  title="Twitter / X"
                >
                  <SocialIcon type="twitter" />
                </a>
              )}
              {settings.social_whatsapp && (
                <a
                  href={settings.social_whatsapp}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="social-btn"
                  title="WhatsApp"
                >
                  <SocialIcon type="whatsapp" />
                </a>
              )}
            </div>
            <span className="mt-4">
              <HeartHandshake size={18} /> Dukung pengalaman wisata lokal
            </span>
          </div>
          <div>
            <h3>Jelajahi</h3>
            {[
              ["/destinasi", "Destinasi wisata"],
              ["/penginapan", "Penginapan"],
              ["/paket", "Paket wisata"],
              ["/kuliner", "Kuliner lokal"],
              ["/umkm", "Produk UMKM"],
              ["/artikel", "Artikel & Cerita Wisata"],
            ].map(([href, label]) => (
              <Link key={href} href={href}>
                {label}
              </Link>
            ))}
          </div>
          <div>
            <h3>Perjalanan Anda</h3>
            {[
              ["/akun", "Akun & pesanan"],
              ["/voucher", "Voucher perjalanan"],
              ["/bantuan", "Pusat bantuan & FAQ"],
              ["/syarat-ketentuan", "Syarat & ketentuan"],
              ["/kebijakan-privasi", "Kebijakan privasi"],
            ].map(([href, label]) => (
              <Link key={href} href={href}>
                {label}
              </Link>
            ))}
          </div>
          <div>
            <h3>Bersama mitra</h3>
            <Link href="/tentang-kami">Tentang kami</Link>
            <Link href="/daftar-mitra">Pendaftaran mitra</Link>
            <Link href="/dashboard">
              Portal pengelola <ArrowUpRight size={14} />
            </Link>
            <Link href="/dashboard/pengaturan">
              Pengaturan web <ArrowUpRight size={14} />
            </Link>
            <Link href="/petugas">Validasi kunjungan</Link>
            <Link href="/login">Masuk ke portal</Link>
          </div>
        </div>
        <div className="site-container footer-bottom">
          <span>© {new Date().getFullYear()} {settings.app_name || "WisataDaerah"}. Seluruh hak cipta dilindungi.</span>
          <span style={{ display: "flex", gap: "0.75rem", alignItems: "center", fontSize: "0.85rem" }}>
            <Link href="/kebijakan-privasi">Kebijakan Privasi</Link>
            <span>·</span>
            <Link href="/syarat-ketentuan">Syarat Layanan</Link>
            <span>·</span>
            <Link href="/tentang-kami">Tentang Kami</Link>
            <span>·</span>
            <Compass size={14} />
          </span>
        </div>
      </footer>
      <PwaInstallBanner />
      <MobileBottomNav />
    </div>
  );
}

export function Card({
  title,
  meta,
  price,
  href,
  children,
  image = "https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=600&q=80",
  illustration = true,
  actionLabel = "Lihat detail",
}) {
  return (
    <article className="travel-card">
      <Link
        href={href || "/destinasi"}
        className="travel-card-image"
        aria-label={`Lihat ${title}`}
      >
        {/* Partner images do not yet have a hostname allowlist. */}
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={image}
          alt={illustration ? `Ilustrasi wisata untuk ${title}` : title}
          loading="lazy"
          decoding="async"
          width={600}
          height={400}
        />
        {illustration && <span className="image-caption">Ilustrasi</span>}
      </Link>
      <div className="travel-card-body">
        {meta && (
          <p className="travel-card-meta">
            <MapPin size={13} />
            {meta}
          </p>
        )}
        <h2>
          <Link href={href || "/destinasi"}>{title}</Link>
        </h2>
        {price && <p className="travel-card-price">{price}</p>}
        <div className="travel-card-bottom">
          <Link href={href || "/destinasi"}>
            {actionLabel} <ChevronRight size={15} />
          </Link>
          {children}
        </div>
      </div>
    </article>
  );
}
