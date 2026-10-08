"use client";

import { useSyncExternalStore, useState, useEffect, useRef } from "react";
import { WifiOff, Wifi } from "lucide-react";

function subscribeOnline(callback) {
  window.addEventListener("online", callback);
  window.addEventListener("offline", callback);
  return () => {
    window.removeEventListener("online", callback);
    window.removeEventListener("offline", callback);
  };
}

function getOnlineSnapshot() {
  return navigator.onLine;
}

function getOnlineServerSnapshot() {
  return true;
}

export function OfflineNotice() {
  const isOnline = useSyncExternalStore(
    subscribeOnline,
    getOnlineSnapshot,
    getOnlineServerSnapshot
  );

  const [wasOffline, setWasOffline] = useState(false);
  const prevOnlineRef = useRef(isOnline);

  useEffect(() => {
    // Detect reconnection
    if (!prevOnlineRef.current && isOnline) {
      setWasOffline(true);
      const timer = setTimeout(() => {
        setWasOffline(false);
      }, 3500);
      return () => clearTimeout(timer);
    }
    prevOnlineRef.current = isOnline;
  }, [isOnline]);

  useEffect(() => {
    if ("serviceWorker" in navigator && process.env.NODE_ENV === "production") {
      navigator.serviceWorker.register("/sw.js").catch(() => {});
    }
  }, []);

  if (isOnline && !wasOffline) return null;

  if (!isOnline) {
    return (
      <aside
        className="no-print"
        role="status"
        aria-live="polite"
        style={{
          position: "sticky",
          top: 0,
          zIndex: 9999,
          backgroundColor: "#b45309",
          color: "#ffffff",
          padding: "0.55rem 1rem",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          gap: "0.5rem",
          fontSize: "0.85rem",
          fontWeight: 600,
          boxShadow: "0 2px 8px rgba(0,0,0,0.15)",
          textAlign: "center",
        }}
      >
        <WifiOff size={16} className="shrink-0" />
        <span>
          Mode Offline: Koneksi internet terputus. E-voucher dan tiket yang sudah dibuka tetap dapat diakses tanpa kuota.
        </span>
      </aside>
    );
  }

  return (
    <aside
      className="no-print"
      role="status"
      aria-live="polite"
      style={{
        position: "sticky",
        top: 0,
        zIndex: 9999,
        backgroundColor: "#059669",
        color: "#ffffff",
        padding: "0.5rem 1rem",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        gap: "0.5rem",
        fontSize: "0.85rem",
        fontWeight: 600,
        boxShadow: "0 2px 8px rgba(0,0,0,0.15)",
        textAlign: "center",
        transition: "opacity 0.3s ease",
      }}
    >
      <Wifi size={16} className="shrink-0" />
      <span>✓ Koneksi kembali terhubung. Memperbarui data...</span>
    </aside>
  );
}
