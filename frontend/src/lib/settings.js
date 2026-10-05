"use client";

import { useEffect, useState, useCallback } from "react";
import { apiRequest } from "./api";

export const defaultSettings = {
  app_name: "WisataDaerah",
  app_tagline: "Jelajahi Keindahan & Pengalaman Lokal",
  app_logo_url: "",
  app_favicon_url: "",
  theme: "ocean",
  primary_color: "#0870ce",
  accent_color: "#ef7f1a",
  hero_title: "Liburan dekat.\nCerita hebat.",
  hero_description:
    "Temukan tempat baru, nikmati pengalaman lokal, dan buat perjalanan Anda lebih berarti.",
  hero_image_url:
    "https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=1800&q=90",
  hero_kicker: "SAATNYA JELAJAHI DAERAH",
  hero_badge_title: "Keindahan ada di sekitar kita",
  hero_badge_subtitle: "Indonesia, penuh cerita.",
  hero_cta_text: "Mulai petualangan",
  hero_cta_url: "/destinasi",
  address:
    "Jl. Malioboro No. 56, Sosromenduran, Gedong Tengen, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55271",
  contact_email: "kontak@wisatadaerah.id",
  contact_phone: "+62 812-3456-7890",
  contact_hours: "Senin – Minggu: 08:00 – 21:00 WIB",
  emergency_phone: "+62 811-9988-7766",
  social_instagram: "https://instagram.com/wisatadaerah",
  social_facebook: "https://facebook.com/wisatadaerah",
  social_tiktok: "https://tiktok.com/@wisatadaerah",
  social_youtube: "https://youtube.com/@wisatadaerah",
  social_twitter: "https://x.com/wisatadaerah",
  social_whatsapp: "https://wa.me/6281234567890",
};

export const themePresets = {
  ocean: {
    name: "Biru Bahari (Default)",
    primary_color: "#0870ce",
    accent_color: "#ef7f1a",
  },
  emerald: {
    name: "Hijau Alam",
    primary_color: "#059669",
    accent_color: "#d97706",
  },
  sunset: {
    name: "Jingga Senja",
    primary_color: "#e11d48",
    accent_color: "#f59e0b",
  },
  forest: {
    name: "Hutan Pinus",
    primary_color: "#15803d",
    accent_color: "#ca8a04",
  },
  purple: {
    name: "Pesona Ungu",
    primary_color: "#7c3aed",
    accent_color: "#f43f5e",
  },
  teal: {
    name: "Pesisir Pantai",
    primary_color: "#0f766e",
    accent_color: "#f97316",
  },
  custom: {
    name: "Kustom Mandiri",
    primary_color: "#0870ce",
    accent_color: "#ef7f1a",
  },
};

function darkenHex(hex, percent = 15) {
  if (!hex || typeof hex !== "string" || !hex.startsWith("#")) return "#075ba9";
  const cleanHex = hex.replace("#", "");
  const num = parseInt(
    cleanHex.length === 3
      ? cleanHex
          .split("")
          .map((c) => c + c)
          .join("")
      : cleanHex,
    16
  );
  if (isNaN(num)) return "#075ba9";
  const amt = Math.round(2.55 * percent);
  const R = Math.max(0, (num >> 16) - amt);
  const G = Math.max(0, ((num >> 8) & 0x00ff) - amt);
  const B = Math.max(0, (num & 0x0000ff) - amt);
  return `#${(0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1)}`;
}

export function applyThemeColors(primary, accent) {
  if (typeof document === "undefined") return;
  const root = document.documentElement;
  if (primary) {
    root.style.setProperty("--brand", primary);
    root.style.setProperty("--brand-dark", darkenHex(primary, 15));
  }
  if (accent) {
    root.style.setProperty("--accent", accent);
  }
}

export function applyBrowserMeta(settings) {
  if (typeof document === "undefined") return;
  if (settings?.app_name) {
    document.title = `${settings.app_name} — ${settings.app_tagline || "Destinasi, Penginapan & Pengalaman Lokal"}`;
  }
  if (settings?.app_favicon_url) {
    let link = document.querySelector("link[rel*='icon']");
    if (!link) {
      link = document.createElement("link");
      link.rel = "shortcut icon";
      document.head.appendChild(link);
    }
    link.href = settings.app_favicon_url;
  }
}

let cachedSettings = undefined;
let pendingFetch = null;
const listeners = new Set();

function initCachedSettings() {
  if (typeof window !== "undefined" && cachedSettings === undefined) {
    try {
      const stored = sessionStorage.getItem("wisata_settings");
      if (stored) {
        cachedSettings = { ...defaultSettings, ...JSON.parse(stored) };
        applyThemeColors(
          cachedSettings.primary_color,
          cachedSettings.accent_color
        );
        applyBrowserMeta(cachedSettings);
      }
    } catch {
      // Ignore storage errors
    }
  }
}

export function notifySettingsUpdated(newSettings) {
  cachedSettings = { ...defaultSettings, ...newSettings };
  if (typeof window !== "undefined") {
    try {
      sessionStorage.setItem("wisata_settings", JSON.stringify(cachedSettings));
    } catch {
      // Ignore storage errors
    }
  }
  applyThemeColors(cachedSettings.primary_color, cachedSettings.accent_color);
  applyBrowserMeta(cachedSettings);
  listeners.forEach((listener) => listener(cachedSettings));
}

export function fetchSettingsOnce() {
  initCachedSettings();
  if (cachedSettings !== undefined) {
    return Promise.resolve(cachedSettings);
  }
  if (pendingFetch) return pendingFetch;

  pendingFetch = apiRequest("/lookup/settings")
    .then((result) => {
      const data = result?.data || {};
      notifySettingsUpdated(data);
      return cachedSettings;
    })
    .catch(() => {
      cachedSettings = { ...defaultSettings };
      return cachedSettings;
    })
    .finally(() => {
      pendingFetch = null;
    });

  return pendingFetch;
}

export async function uploadSettingAsset(file, type) {
  const formData = new FormData();
  formData.append("file", file);
  formData.append("type", type);

  return apiRequest("/dashboard/settings/upload", {
    method: "POST",
    body: formData,
  });
}

export function useSettings() {
  initCachedSettings();
  const [settings, setSettings] = useState(cachedSettings || defaultSettings);
  const [loading, setLoading] = useState(!cachedSettings);

  useEffect(() => {
    let mounted = true;
    const handleChange = (updated) => {
      if (mounted) {
        setSettings(updated);
        setLoading(false);
      }
    };
    listeners.add(handleChange);

    if (cachedSettings === undefined) {
      fetchSettingsOnce().then((res) => {
        if (mounted) {
          setSettings(res);
          setLoading(false);
        }
      });
    } else {
      applyThemeColors(settings.primary_color, settings.accent_color);
      applyBrowserMeta(settings);
    }

    return () => {
      mounted = false;
      listeners.delete(handleChange);
    };
  }, [settings]);

  const reloadSettings = useCallback(() => {
    return apiRequest("/lookup/settings").then((result) => {
      const data = result?.data || {};
      notifySettingsUpdated(data);
      return data;
    });
  }, []);

  return {
    settings,
    loading,
    reloadSettings,
    themePresets,
    applyThemeColors,
    uploadSettingAsset,
  };
}
