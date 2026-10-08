"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import { Download, X } from "lucide-react";

export function PwaInstallBanner() {
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [isVisible, setIsVisible] = useState(false);
  const [isIOS, setIsIOS] = useState(false);
  const [showIOSGuide, setShowIOSGuide] = useState(false);

  useEffect(() => {
    if (typeof window === "undefined") return;

    // Check if already in standalone mode (already installed)
    const isStandalone =
      window.matchMedia("(display-mode: standalone)").matches ||
      window.navigator.standalone === true;
    if (isStandalone) return;

    // Check if dismissed recently (7 days)
    const dismissedAt = localStorage.getItem("wisatadaerah_pwa_dismissed");
    if (dismissedAt) {
      const daysSince =
        (Date.now() - parseInt(dismissedAt, 10)) / (1000 * 60 * 60 * 24);
      if (daysSince < 7) return;
    }

    // Android/Chrome beforeinstallprompt event
    const handleBeforeInstall = (e) => {
      e.preventDefault();
      setDeferredPrompt(e);
      setIsVisible(true);
    };

    window.addEventListener("beforeinstallprompt", handleBeforeInstall);

    // Detect iOS Safari
    const userAgent = window.navigator.userAgent.toLowerCase();
    const isIosDevice = /iphone|ipad|ipod/.test(userAgent);
    const isSafari =
      /safari/.test(userAgent) &&
      !/chrome|crios|crmo|firefox|fxios/.test(userAgent);

    if (isIosDevice && isSafari && !isStandalone) {
      // Show prompt on iOS after 3 seconds of browsing
      const timer = setTimeout(() => {
        setIsIOS(true);
        setIsVisible(true);
      }, 3000);
      return () => {
        clearTimeout(timer);
        window.removeEventListener("beforeinstallprompt", handleBeforeInstall);
      };
    }

    return () => {
      window.removeEventListener("beforeinstallprompt", handleBeforeInstall);
    };
  }, []);

  const handleInstallClick = async () => {
    if (isIOS) {
      setShowIOSGuide(true);
      return;
    }

    if (!deferredPrompt) return;

    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    if (outcome === "accepted") {
      setIsVisible(false);
    }
    setDeferredPrompt(null);
  };

  const handleDismiss = () => {
    setIsVisible(false);
    setShowIOSGuide(false);
    localStorage.setItem("wisatadaerah_pwa_dismissed", Date.now().toString());
  };

  if (!isVisible) return null;

  return (
    <aside
      className="pwa-install-banner no-print"
      aria-label="Pemasangan Aplikasi"
      style={{
        position: "fixed",
        bottom: "4.5rem", // Above mobile bottom navigation bar
        left: "1rem",
        right: "1rem",
        maxWidth: "480px",
        margin: "0 auto",
        zIndex: 999,
        backgroundColor: "#ffffff",
        border: "1px solid #e2e8f0",
        borderRadius: "1rem",
        padding: "0.85rem 1rem",
        boxShadow: "0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1)",
        display: "flex",
        flexDirection: "column",
        gap: "0.6rem",
        animation: "fadeInUp 0.3s ease-out",
      }}
    >
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: "0.75rem" }}>
        <div style={{ display: "flex", alignItems: "center", gap: "0.75rem" }}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src="/icon.svg"
            alt="Wisata Daerah Icon"
            width={40}
            height={40}
            style={{ borderRadius: "0.6rem", flexShrink: 0 }}
          />
          <div>
            <h4 style={{ margin: 0, fontSize: "0.95rem", fontWeight: 700, color: "#0f172a" }}>
              Pasang Aplikasi Wisata
            </h4>
            <p style={{ margin: 0, fontSize: "0.8rem", color: "#64748b" }}>
              Akses e-tiket offline & check-in lebih cepat
            </p>
          </div>
        </div>
        <button
          type="button"
          onClick={handleDismiss}
          aria-label="Tutup saran pemasangan"
          style={{
            background: "transparent",
            border: "none",
            color: "#94a3b8",
            cursor: "pointer",
            padding: "0.25rem",
            display: "inline-flex",
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <X size={18} />
        </button>
      </div>

      {showIOSGuide ? (
        <div
          style={{
            backgroundColor: "#f8fafc",
            padding: "0.65rem 0.85rem",
            borderRadius: "0.5rem",
            fontSize: "0.8rem",
            color: "#334155",
            lineHeight: 1.5,
          }}
        >
          📲 <strong>Untuk pengguna iPhone/iPad:</strong> Ketuk tombol <strong>Bagikan (Share)</strong> di bilah bawah peramban Safari, lalu pilih <strong>&quot;Tambahkan ke Layar Utama&quot;</strong>.
        </div>
      ) : (
        <div style={{ display: "flex", gap: "0.5rem", justifyContent: "flex-end" }}>
          <button
            type="button"
            onClick={handleDismiss}
            style={{
              padding: "0.4rem 0.75rem",
              fontSize: "0.8rem",
              fontWeight: 500,
              color: "#64748b",
              background: "transparent",
              border: "none",
              borderRadius: "0.375rem",
              cursor: "pointer",
            }}
          >
            Nanti Saja
          </button>
          <button
            type="button"
            onClick={handleInstallClick}
            style={{
              padding: "0.4rem 0.95rem",
              fontSize: "0.8rem",
              fontWeight: 600,
              color: "#ffffff",
              backgroundColor: "#059669",
              border: "none",
              borderRadius: "0.5rem",
              cursor: "pointer",
              display: "inline-flex",
              alignItems: "center",
              gap: "0.35rem",
              boxShadow: "0 1px 2px rgba(0,0,0,0.05)",
            }}
          >
            <Download size={14} /> Pasang Sekarang
          </button>
        </div>
      )}
    </aside>
  );
}
