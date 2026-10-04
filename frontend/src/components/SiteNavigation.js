"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import {
  Store,
  Compass,
  BedDouble,
  Map,
  Utensils,
  UserRound,
  Headphones,
  Menu,
  X,
  ArrowUpRight,
  Ticket,
  ChevronRight,
  LayoutDashboard,
} from "lucide-react";

import { apiRequest } from "../lib/api";

const products = [
  { href: "/destinasi", label: "Destinasi", icon: Compass },
  { href: "/penginapan", label: "Penginapan", icon: BedDouble },
  { href: "/paket", label: "Paket wisata", icon: Map },
  { href: "/umkm", label: "Produk UMKM", icon: Store },
  { href: "/kuliner", label: "Kuliner", icon: Utensils },
];
const workspace = [
  {
    label: "Ringkasan",
    items: [{ href: "/dashboard", label: "Dashboard", icon: LayoutDashboard }],
  },
  {
    label: "Produk & tempat",
    items: [
      {
        href: "/dashboard/destinasi",
        services: true,
        label: "Destinasi",
        icon: Compass,
      },
      {
        href: "/dashboard/paket",
        services: true,
        label: "Paket wisata",
        icon: Map,
      },
      {
        href: "/dashboard/penginapan",
        services: true,
        label: "Penginapan",
        icon: BedDouble,
        admin: true,
      },
      {
        href: "/dashboard/kuliner",
        services: true,
        label: "Rumah makan",
        icon: Utensils,
        admin: true,
      },
      { href: "/dashboard/umkm/produk", label: "Produk UMKM", icon: Store },
      { href: "/dashboard/lintas-desa", label: "Paket lintas desa", icon: Map },
    ],
  },
  {
    label: "Pesanan & operasional",
    items: [
      {
        href: "/dashboard/reservasi",
        services: true,
        label: "Reservasi layanan",
        icon: BedDouble,
        admin: true,
      },
      { href: "/dashboard/umkm", label: "Pesanan UMKM", icon: Ticket },
      {
        href: "/dashboard/refund",
        admin: true,
        label: "Pengajuan refund",
        icon: Ticket,
      },
      {
        href: "/dashboard/kesiapan-produksi",
        admin: true,
        label: "Kesiapan produksi",
        icon: LayoutDashboard,
      },
      { href: "/reconciliation", label: "Rekonsiliasi", icon: LayoutDashboard },
      { href: "/petugas", label: "Validasi voucher", icon: Ticket },
      {
        href: "/dashboard/pendaftaran-mitra",
        label: "Pendaftaran mitra",
        icon: UserRound,
        admin: true,
      },
      {
        href: "/dashboard/persetujuan-mitra",
        label: "Persetujuan mitra",
        icon: UserRound,
        admin: true,
      },
    ],
  },
];
export function Brand() {
  return (
    <Link href="/" className="site-brand" aria-label="WisataDaerah — Beranda">
      <span className="brand-symbol">
        <Compass size={24} strokeWidth={2.4} />
      </span>
      <span>
        Wisata<span className="brand-accent">Daerah</span>
        <span className="brand-dot">.</span>
      </span>
    </Link>
  );
}
export function SiteNavigation() {
  const pathname = usePathname();
  const router = useRouter();
  const [profile, setProfile] = useState(null);
  const [loggingOut, setLoggingOut] = useState(false);
  const [sessionError, setSessionError] = useState("");
  useEffect(() => {
    const controller = new AbortController();
    apiRequest("/me", { signal: controller.signal })
      .then((result) => {
        if (!controller.signal.aborted) setProfile(result.data);
      })
      .catch(() => {
        if (!controller.signal.aborted) setProfile(null);
      });
    return () => controller.abort();
  }, [pathname]);
  async function logout() {
    setLoggingOut(true);
    setSessionError("");
    try {
      await apiRequest("/logout", { method: "POST" });
      setProfile(null);
      router.push("/login");
      router.refresh();
    } catch {
      setSessionError("Belum dapat keluar. Coba lagi.");
    } finally {
      setLoggingOut(false);
    }
  }
  const [menuPath, setMenuPath] = useState(null);
  const open = menuPath === pathname;
  const mobileHref = [
    "/destinasi",
    "/penginapan",
    "/paket",
    "/kuliner",
    "/umkm",
  ].some((path) => pathname.startsWith(path))
    ? "/destinasi"
    : ["/akun", "/checkout", "/voucher", "/pembayaran"].some((path) =>
          pathname.startsWith(path)
        )
      ? "/akun"
      : pathname;
  return (
    <>
      <div className="utility-bar">
        <div className="site-container">
          <span>Lebih dekat dengan cerita daerah.</span>
          <div>
            <span>ID · IDR</span>
            <Link href="/bantuan">
              <Headphones size={14} /> Pusat bantuan
            </Link>
            <Link href="/dashboard">
              {profile?.platform_role === "super_admin"
                ? "Dashboard admin"
                : "Portal mitra"}{" "}
              <ArrowUpRight size={13} />
            </Link>
          </div>
        </div>
      </div>
      <header className="site-header">
        <div className="site-container nav-row">
          <Brand />
          <nav className="desktop-products" aria-label="Navigasi produk">
            {products.map(({ href, label }) => (
              <Link
                key={href}
                href={href}
                aria-current={pathname.startsWith(href) ? "page" : undefined}
              >
                {label}
              </Link>
            ))}
          </nav>
          <div className="nav-actions">
            <Link href="/akun" className="nav-orders">
              <Ticket size={18} /> Pesanan saya
            </Link>
            <Link
              href={profile ? "/akun" : "/login"}
              className="ui-button ui-button-outline nav-login"
            >
              <UserRound size={16} /> {profile ? "Akun saya" : "Masuk"}
            </Link>
            {profile ? (
              <button
                className="ui-button nav-register"
                disabled={loggingOut}
                onClick={logout}
              >
                {loggingOut ? "Keluar…" : "Keluar"}
              </button>
            ) : (
              <Link href="/daftar" className="ui-button nav-register">
                Daftar
              </Link>
            )}
            <button
              type="button"
              className="nav-toggle"
              aria-label={open ? "Tutup menu" : "Buka menu"}
              aria-expanded={open}
              aria-controls="mobile-menu"
              onClick={() => setMenuPath(open ? null : pathname)}
            >
              {open ? <X /> : <Menu />}
            </button>
          </div>
        </div>
        {open && (
          <nav
            id="mobile-menu"
            className="mobile-menu"
            aria-label="Menu seluler"
          >
            {[
              ...products,
              { href: "/bantuan", label: "Pusat bantuan" },
              {
                href: "/dashboard",
                label:
                  profile?.platform_role === "super_admin"
                    ? "Dashboard admin"
                    : "Portal mitra",
              },
              {
                href: profile ? "/akun" : "/login",
                label: profile ? "Akun saya" : "Masuk / daftar",
              },
            ].map(({ href, label }) => (
              <Link key={href} href={href} onClick={() => setMenuPath(null)}>
                {label}
                <ChevronRight size={16} />
              </Link>
            ))}
          </nav>
        )}
        {sessionError && (
          <p role="alert" className="site-container">
            {sessionError}
          </p>
        )}
        {open && profile && (
          <button className="ui-button" disabled={loggingOut} onClick={logout}>
            {loggingOut ? "Keluar…" : "Keluar"}
          </button>
        )}
      </header>
      <nav className="mobile-bottom" aria-label="Navigasi seluler">
        {[
          { href: "/", label: "Beranda", icon: Compass },
          { href: "/destinasi", label: "Jelajahi", icon: Map },
          { href: "/akun", label: "Pesanan", icon: Ticket },
          { href: "/bantuan", label: "Bantuan", icon: Headphones },
        ].map(({ href, label, icon: Icon }) => (
          <Link
            key={href}
            href={href}
            aria-current={mobileHref === href ? "page" : undefined}
          >
            <Icon size={21} />
            <span>{label}</span>
          </Link>
        ))}
      </nav>
    </>
  );
}
export function RouteNavigation() {
  const pathname = usePathname();
  const router = useRouter();
  const [profile, setProfile] = useState(null);
  const [loggingOut, setLoggingOut] = useState(false);
  const [sessionError, setSessionError] = useState("");
  useEffect(() => {
    const controller = new AbortController();
    apiRequest("/me", { signal: controller.signal })
      .then((result) => {
        if (!controller.signal.aborted) setProfile(result.data);
      })
      .catch(() => {
        if (!controller.signal.aborted) setProfile(null);
      });
    return () => controller.abort();
  }, [pathname]);
  async function logout() {
    setLoggingOut(true);
    setSessionError("");
    try {
      await apiRequest("/logout", { method: "POST" });
      setProfile(null);
      router.push("/login");
      router.refresh();
    } catch {
      setSessionError("Belum dapat keluar. Coba lagi.");
    } finally {
      setLoggingOut(false);
    }
  }
  if (pathname === "/") return null;
  const labels = {
    destinasi: "Destinasi",
    penginapan: "Penginapan",
    paket: "Paket wisata",
    umkm: "Produk UMKM",
    kuliner: "Kuliner",
    akun: "Akun & pesanan",
    checkout: "Pemesanan",
    login: "Masuk",
    daftar: "Daftar",
    "daftar-mitra": "Daftar mitra",
    "pendaftaran-mitra": "Pendaftaran mitra",
    bantuan: "Pusat bantuan",
    voucher: "Voucher perjalanan",
    petugas: "Validasi kunjungan",
    dashboard:
      profile?.platform_role === "super_admin"
        ? "Dashboard admin"
        : "Portal mitra",
    reconciliation: "Rekonsiliasi",
    pembayaran: "Pembayaran",
    "lintas-desa": "Paket lintas desa",
    "persetujuan-mitra": "Persetujuan mitra",
    reservasi: pathname.startsWith("/akun")
      ? "Reservasi saya"
      : "Reservasi layanan",
    produk: "Kelola produk",
    demo: "Pratinjau",
  };
  const segments = pathname.startsWith("/pembayaran/")
    ? ["akun", "pembayaran"]
    : pathname.split("/").filter(Boolean);
  const isWorkspace =
    pathname.startsWith("/dashboard") ||
    pathname === "/reconciliation" ||
    pathname === "/petugas";
  return (
    <>
      <nav className="breadcrumbs" aria-label="Breadcrumb">
        <Link href="/">Beranda</Link>
        {segments.map((segment, index) => (
          <span key={index}>
            <ChevronRight size={12} />
            {index === segments.length - 1 ? (
              <span aria-current="page">{labels[segment] || "Detail"}</span>
            ) : (
              <Link href={`/${segments.slice(0, index + 1).join("/")}`}>
                {labels[segment] || "Detail"}
              </Link>
            )}
          </span>
        ))}
      </nav>
      {isWorkspace && (
        <nav className="admin-module-menu" aria-label="Menu pengelolaan">
          <div className="admin-menu-heading">
            <LayoutDashboard size={20} />
            <div>
              <strong>
                {profile?.platform_role === "super_admin"
                  ? "Menu admin"
                  : "Menu operasional"}
              </strong>
              <p>Kelola tempat, produk, dan aktivitas pemesanan.</p>
            </div>
          </div>
          <div className="admin-menu-groups">
            {workspace.map((group) => {
              const items = group.items.filter((item) =>
                item.services
                  ? Boolean(profile?.can_manage_services)
                  : !item.admin || profile?.platform_role === "super_admin"
              );
              if (!items.length) return null;
              return (
                <section key={group.label}>
                  <h2>{group.label}</h2>
                  <div>
                    {items.map(({ href, label, icon: Icon }) => (
                      <Link
                        href={href}
                        key={href}
                        aria-current={pathname === href ? "page" : undefined}
                      >
                        <Icon size={18} />
                        <span>{label}</span>
                        <ChevronRight size={14} />
                      </Link>
                    ))}
                  </div>
                </section>
              );
            })}
          </div>
        </nav>
      )}
    </>
  );
}
