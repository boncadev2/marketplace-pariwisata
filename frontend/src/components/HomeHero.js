"use client";

import Link from "next/link";
import { ArrowRight, MapPin } from "lucide-react";
import { useSettings } from "../lib/settings";

export function HomeHero() {
  const { settings } = useSettings();

  const title = settings?.hero_title || "Liburan dekat.\nCerita hebat.";
  const lines = title.split("\n");

  const kicker = settings?.hero_kicker || "SAATNYA JELAJAHI DAERAH";
  const description =
    settings?.hero_description ||
    "Temukan tempat baru, nikmati pengalaman lokal, dan buat perjalanan Anda lebih berarti.";
  const heroImage =
    settings?.hero_image_url ||
    "https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=1800&q=90";
  const badgeTitle =
    settings?.hero_badge_title || "Keindahan ada di sekitar kita";
  const badgeSubtitle =
    settings?.hero_badge_subtitle || "Indonesia, penuh cerita.";

  return (
    <section
      className="home-hero site-container"
      style={{
        backgroundImage: `url(${heroImage})`,
      }}
    >
      <div className="home-hero-shade" />
      <div className="home-hero-copy">
        <span className="hero-kicker">
          <span
            style={
              settings?.accent_color
                ? { backgroundColor: settings.accent_color }
                : {}
            }
          />{" "}
          {kicker}
        </span>
        <h1>
          {lines.map((line, idx) => {
            const isLast = idx === lines.length - 1;
            if (isLast && line.includes(" ")) {
              const words = line.trim().split(" ");
              const lastWord = words.pop();
              return (
                <span key={idx}>
                  {words.join(" ")}{" "}
                  <span
                    style={
                      settings?.accent_color
                        ? { color: settings.accent_color }
                        : {}
                    }
                  >
                    {lastWord}
                  </span>
                  {idx < lines.length - 1 && <br />}
                </span>
              );
            }
            return (
              <span key={idx}>
                {line}
                {idx < lines.length - 1 && <br />}
              </span>
            );
          })}
        </h1>
        <p className="whitespace-pre-line">{description}</p>
        <div className="hero-cta-wrapper">
          <Link
            href={settings?.hero_cta_url || "/destinasi"}
            className="hero-cta-button"
            style={{
              "--cta-bg": settings?.primary_color || "var(--brand, #0870ce)",
            }}
          >
            <span className="hero-cta-text">
              {settings?.hero_cta_text || "Mulai petualangan"}
            </span>
            <span className="hero-cta-icon">
              <ArrowRight size={17} strokeWidth={2.4} />
            </span>
          </Link>
          <div className="hero-cta-trust">
            <span
              className="trust-dot"
              style={{
                backgroundColor: settings?.accent_color || "#ffb04f",
              }}
            />
            <span>Pesan mudah & terverifikasi</span>
          </div>
        </div>
      </div>
      <div className="hero-note">
        <MapPin size={17} />
        <div>
          <strong>{badgeTitle}</strong>
          <span>{badgeSubtitle}</span>
        </div>
      </div>
      <span className="hero-image-label">Foto ilustrasi wisata</span>
    </section>
  );
}
