"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Home, Compass, Map, Ticket, User } from "lucide-react";

export function MobileBottomNav() {
  const pathname = usePathname();

  // Don't show bottom nav inside dashboard admin or checkout payment fullscreens
  if (pathname.startsWith("/dashboard") || pathname.startsWith("/petugas")) {
    return null;
  }

  const navItems = [
    {
      href: "/",
      label: "Beranda",
      icon: Home,
      isActive: pathname === "/",
    },
    {
      href: "/destinasi",
      label: "Destinasi",
      icon: Compass,
      isActive: pathname.startsWith("/destinasi"),
    },
    {
      href: "/paket",
      label: "Paket Tur",
      icon: Map,
      isActive: pathname.startsWith("/paket"),
    },
    {
      href: "/voucher",
      label: "E-Tiket",
      icon: Ticket,
      isActive: pathname.startsWith("/voucher"),
    },
    {
      href: "/akun",
      label: "Akun",
      icon: User,
      isActive: pathname.startsWith("/akun") || pathname === "/login",
    },
  ];

  return (
    <nav
      className="mobile-bottom-nav no-print"
      aria-label="Navigasi Bawah Ponsel"
      style={{
        position: "fixed",
        bottom: 0,
        left: 0,
        right: 0,
        zIndex: 990,
        backgroundColor: "rgba(255, 255, 255, 0.95)",
        backdropFilter: "blur(12px)",
        WebkitBackdropFilter: "blur(12px)",
        borderTop: "1px solid #e2e8f0",
        padding: "0.4rem 0.5rem calc(0.4rem + env(safe-area-inset-bottom, 0px)) 0.5rem",
        display: "flex",
        alignItems: "center",
        justifyContent: "space-around",
        boxShadow: "0 -2px 10px rgba(0, 0, 0, 0.05)",
      }}
    >
      <style jsx global>{`
        /* Hide bottom nav on desktop and print */
        @media (min-width: 769px) {
          .mobile-bottom-nav {
            display: none !important;
          }
        }
        @media print {
          .mobile-bottom-nav {
            display: none !important;
          }
        }
        /* Add bottom padding to body on mobile so content isn't covered by bottom nav */
        @media (max-width: 768px) {
          body {
            padding-bottom: 4rem !important;
          }
        }
      `}</style>
      {navItems.map(({ href, label, icon: Icon, isActive }) => (
        <Link
          key={href}
          href={href}
          style={{
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            justifyContent: "center",
            gap: "0.2rem",
            textDecoration: "none",
            color: isActive ? "#059669" : "#64748b",
            fontSize: "0.7rem",
            fontWeight: isActive ? 700 : 500,
            padding: "0.25rem 0.5rem",
            borderRadius: "0.5rem",
            minWidth: "56px",
            transition: "all 0.15s ease-in-out",
          }}
        >
          <div
            style={{
              padding: "0.2rem",
              borderRadius: "0.5rem",
              backgroundColor: isActive ? "#ecfdf5" : "transparent",
              transition: "background-color 0.15s ease",
            }}
          >
            <Icon size={20} strokeWidth={isActive ? 2.3 : 1.8} />
          </div>
          <span>{label}</span>
        </Link>
      ))}
    </nav>
  );
}
