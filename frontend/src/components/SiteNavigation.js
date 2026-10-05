"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
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
  ChevronDown,
  LogOut,
  LayoutDashboard,
  SlidersHorizontal,
  Loader2,
  MapPin,
} from "lucide-react";

import { apiRequest } from "../lib/api";
import { useSettings } from "../lib/settings";

const products = [
  { href: "/destinasi", label: "Destinasi", icon: Compass },
  { href: "/penginapan", label: "Penginapan", icon: BedDouble },
  { href: "/paket", label: "Paket wisata", icon: Map },
  { href: "/umkm", label: "Produk UMKM", icon: Store },
  { href: "/kuliner", label: "Kuliner", icon: Utensils },
];

const workspaceProducts = [
  {
    href: "/dashboard/destinasi",
    services: true,
    label: "Destinasi",
    desc: "Kelola destinasi & tiket",
    icon: Compass,
  },
  {
    href: "/dashboard/paket",
    services: true,
    label: "Paket wisata",
    desc: "Paket tur & agenda wisata",
    icon: Map,
  },
  {
    href: "/dashboard/penginapan",
    services: true,
    label: "Penginapan",
    desc: "Homestay, villa & tipe kamar",
    icon: BedDouble,
    admin: true,
  },
  {
    href: "/dashboard/kuliner",
    services: true,
    label: "Rumah makan",
    desc: "Restoran & menu kuliner",
    icon: Utensils,
    admin: true,
  },
  {
    href: "/dashboard/umkm/produk",
    label: "Produk UMKM",
    desc: "Katalog oleh-oleh & kerajinan",
    icon: Store,
  },
  {
    href: "/dashboard/lintas-desa",
    label: "Paket lintas desa",
    desc: "Kolaborasi wisata antar-desa",
    icon: Map,
  },
];

const workspaceOperations = [
  {
    href: "/dashboard/reservasi",
    services: true,
    label: "Reservasi layanan",
    desc: "Pemesanan tiket & penginapan",
    icon: BedDouble,
    admin: true,
  },
  {
    href: "/dashboard/umkm",
    label: "Pesanan UMKM",
    desc: "Order belanja & status kirim",
    icon: Ticket,
  },
  {
    href: "/dashboard/refund",
    admin: true,
    label: "Pengajuan refund",
    desc: "Persetujuan pengembalian dana",
    icon: Ticket,
  },
  {
    href: "/dashboard/kesiapan-produksi",
    admin: true,
    label: "Kesiapan produksi",
    desc: "Monitoring kesiapan dapur & stok",
    icon: LayoutDashboard,
  },
  {
    href: "/reconciliation",
    label: "Rekonsiliasi",
    desc: "Pencairan dana & keuangan",
    icon: LayoutDashboard,
  },
  {
    href: "/petugas",
    label: "Validasi voucher",
    desc: "Scan & verifikasi tiket masuk",
    icon: Ticket,
  },
  {
    href: "/dashboard/pendaftaran-mitra",
    label: "Pendaftaran mitra",
    desc: "Registrasi pelaku usaha baru",
    icon: UserRound,
    admin: true,
  },
  {
    href: "/dashboard/persetujuan-mitra",
    label: "Persetujuan mitra",
    desc: "Verifikasi dokumen kemitraan",
    icon: UserRound,
    admin: true,
  },
  {
    href: "/dashboard/kategori-wilayah",
    label: "Kategori & wilayah",
    desc: "Master data kategori & desa",
    icon: MapPin,
    admin: true,
  },
  {
    href: "/dashboard/pengaturan",
    label: "Pengaturan web",
    desc: "Branding, hero & kontak portal",
    icon: SlidersHorizontal,
    admin: true,
  },
];


function filterNavItems(items, profile) {
  return items.filter((item) => {
    if (profile?.platform_role === "super_admin") return true;
    if (item.services) return Boolean(profile?.can_manage_services);
    return !item.admin;
  });
}

export function Brand() {
  const { settings } = useSettings();
  const name = settings?.app_name || "WisataDaerah";

  if (settings?.app_logo_url) {
    return (
      <Link href="/" className="site-brand" aria-label={`${name} — Beranda`}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={settings.app_logo_url}
          alt={name}
          className="brand-logo-img"
          style={{ maxHeight: 36, maxWidth: 160, objectFit: "contain" }}
        />
      </Link>
    );
  }

  let firstPart = name;
  let secondPart = "";
  if (name.toLowerCase() === "wisatadaerah") {
    firstPart = "Wisata";
    secondPart = "Daerah";
  } else if (name.includes(" ")) {
    const parts = name.split(" ");
    firstPart = parts[0];
    secondPart = parts.slice(1).join(" ");
  }

  return (
    <Link href="/" className="site-brand" aria-label={`${name} — Beranda`}>
      <span className="brand-symbol">
        <Compass size={24} strokeWidth={2.4} />
      </span>
      <span>
        {firstPart}
        {secondPart ? <span className="brand-accent"> {secondPart}</span> : ""}
        <span className="brand-dot">.</span>
      </span>
    </Link>
  );
}
let cachedProfile = undefined;
let pendingFetch = null;
let profileFetchSeq = 0;
const profileListeners = new Set();

function initCachedProfile() {
  if (typeof window !== "undefined" && cachedProfile === undefined) {
    try {
      const stored = sessionStorage.getItem("wisata_profile");
      if (stored && stored !== "guest") {
        cachedProfile = JSON.parse(stored);
      } else {
        cachedProfile = null;
      }
    } catch {
      cachedProfile = null;
    }
  }
}

export function syncProfile(data) {
  if (!data) {
    profileFetchSeq++;
  }
  cachedProfile = data ?? null;
  if (typeof window !== "undefined") {
    try {
      if (data) {
        sessionStorage.setItem("wisata_profile", JSON.stringify(data));
      } else {
        sessionStorage.setItem("wisata_profile", "guest");
      }
    } catch {
      // Ignore storage errors
    }
  }
  profileListeners.forEach((fn) => fn(cachedProfile));
}

export function fetchProfile(force = false) {
  initCachedProfile();
  if (pendingFetch) return pendingFetch;

  const currentSeq = ++profileFetchSeq;

  pendingFetch = apiRequest("/me")
    .then((result) => {
      if (currentSeq === profileFetchSeq) {
        syncProfile(result.data);
      }
      return result.data;
    })
    .catch((err) => {
      if (
        currentSeq === profileFetchSeq &&
        (err?.status === 401 || err?.status === 403)
      ) {
        syncProfile(null);
      }
      return null;
    })
    .finally(() => {
      pendingFetch = null;
    });

  if (!force && cachedProfile !== undefined) {
    return Promise.resolve(cachedProfile);
  }
  return pendingFetch;
}

export function useProfile() {
  initCachedProfile();
  const [profile, setProfile] = useState(cachedProfile ?? null);
  const [loading, setLoading] = useState(cachedProfile === undefined);

  useEffect(() => {
    function onProfileChange(next) {
      setProfile(next);
      setLoading(false);
    }
    profileListeners.add(onProfileChange);

    fetchProfile();

    return () => {
      profileListeners.delete(onProfileChange);
    };
  }, []);

  return { profile, loading };
}

export function SiteNavigation() {
  const pathname = usePathname();
  const router = useRouter();
  const { profile, loading: profileLoading } = useProfile();
  const [loggingOut, setLoggingOut] = useState(false);
  const [sessionError, setSessionError] = useState("");
  const [showLogoutModal, setShowLogoutModal] = useState(false);
  const [clearLocalData, setClearLocalData] = useState(true);

  useEffect(() => {
    if (!showLogoutModal) return;
    function handleKeyDown(event) {
      if (event.key === "Escape" && !loggingOut) {
        setShowLogoutModal(false);
      }
    }
    document.addEventListener("keydown", handleKeyDown);
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", handleKeyDown);
      document.body.style.overflow = "";
    };
  }, [showLogoutModal, loggingOut]);

  async function confirmLogout() {
    setLoggingOut(true);
    setSessionError("");
    try {
      await apiRequest("/logout", { method: "POST" });
    } catch {
      // Silently ignore 401 or network issues; local session cleanup proceeds
    } finally {
      if (clearLocalData) {
        try {
          sessionStorage.clear();
          localStorage.clear();
        } catch {
          // Ignore storage errors
        }
      }
      syncProfile(null);
      setLoggingOut(false);
      setShowLogoutModal(false);
      setDropdownPath(null);
      setMenuPath(null);
      if (
        pathname.startsWith("/akun") ||
        pathname.startsWith("/dashboard") ||
        pathname.startsWith("/reconciliation") ||
        pathname.startsWith("/checkout")
      ) {
        router.push("/login");
      } else {
        router.refresh();
      }
    }
  }
  const isWorkspace =
    pathname.startsWith("/dashboard") ||
    pathname === "/reconciliation" ||
    pathname === "/petugas";

  const isAdmin = Boolean(
    profile?.platform_role === "super_admin" ||
      profile?.role === "admin" ||
      profile?.can_manage_services
  );

  const isDashboardActive = pathname === "/dashboard";
  const isProductActive = workspaceProducts.some((item) =>
    pathname.startsWith(item.href)
  );
  const isOperationActive =
    !isProductActive &&
    workspaceOperations.some((item) =>
      item.href === "/dashboard/umkm"
        ? pathname === "/dashboard/umkm" || pathname.startsWith("/dashboard/umkm/")
        : pathname.startsWith(item.href)
    );

  const [adminMenuState, setAdminMenuState] = useState({ path: null, key: null });
  const activeAdminMenu =
    adminMenuState.path === pathname ? adminMenuState.key : null;
  const adminNavRef = useRef(null);
  const adminHoverTimeoutRef = useRef(null);

  const handleAdminMouseEnter = (menuKey) => {
    if (adminHoverTimeoutRef.current) clearTimeout(adminHoverTimeoutRef.current);
    setAdminMenuState({ path: pathname, key: menuKey });
  };

  const handleAdminMouseLeave = () => {
    if (adminHoverTimeoutRef.current) clearTimeout(adminHoverTimeoutRef.current);
    adminHoverTimeoutRef.current = setTimeout(() => {
      setAdminMenuState({ path: null, key: null });
    }, 180);
  };

  const handleAdminToggle = (menuKey) => {
    if (adminHoverTimeoutRef.current) clearTimeout(adminHoverTimeoutRef.current);
    setAdminMenuState((prev) =>
      prev.path === pathname && prev.key === menuKey
        ? { path: null, key: null }
        : { path: pathname, key: menuKey }
    );
  };

  const closeAdminMenu = () => {
    if (adminHoverTimeoutRef.current) clearTimeout(adminHoverTimeoutRef.current);
    setAdminMenuState({ path: null, key: null });
  };

  const [dropdownPath, setDropdownPath] = useState(null);
  const dropdownOpen = dropdownPath === pathname;
  const dropdownRef = useRef(null);
  const hoverTimeoutRef = useRef(null);

  const handleMouseEnter = () => {
    if (hoverTimeoutRef.current) clearTimeout(hoverTimeoutRef.current);
    setDropdownPath(pathname);
  };

  const handleMouseLeave = () => {
    hoverTimeoutRef.current = setTimeout(() => {
      setDropdownPath(null);
    }, 150);
  };

  const toggleDropdown = () => {
    if (hoverTimeoutRef.current) clearTimeout(hoverTimeoutRef.current);
    setDropdownPath(dropdownOpen ? null : pathname);
  };

  const closeDropdown = () => {
    if (hoverTimeoutRef.current) clearTimeout(hoverTimeoutRef.current);
    setDropdownPath(null);
  };

  useEffect(() => {
    function handleClickOutside(event) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setDropdownPath(null);
      }
      if (adminNavRef.current && !adminNavRef.current.contains(event.target)) {
        setAdminMenuState({ path: null, key: null });
      }
    }
    function handleKeyDown(event) {
      if (event.key === "Escape") {
        setDropdownPath(null);
        setAdminMenuState({ path: null, key: null });
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
      document.removeEventListener("keydown", handleKeyDown);
      if (hoverTimeoutRef.current) clearTimeout(hoverTimeoutRef.current);
      if (adminHoverTimeoutRef.current) clearTimeout(adminHoverTimeoutRef.current);
    };
  }, []);

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
            <Link
              href={
                profile?.platform_role === "super_admin" ||
                profile?.can_manage_services
                  ? "/dashboard"
                  : "/daftar-mitra"
              }
            >
              {profile?.platform_role === "super_admin"
                ? "Dashboard admin"
                : profile?.can_manage_services
                  ? "Portal mitra"
                  : "Daftar mitra"}{" "}
              <ArrowUpRight size={13} />
            </Link>
          </div>
        </div>
      </div>
      <header className="site-header">
        <div className="site-container nav-row">
          <Brand />
          {isWorkspace ? (
            <nav
              className="desktop-workspace-nav"
              aria-label="Navigasi admin"
              ref={adminNavRef}
            >
              {/* 1. Dashboard */}
              <Link
                href="/dashboard"
                className={`nav-admin-btn ${isDashboardActive ? "is-active" : ""}`}
                onClick={closeAdminMenu}
              >
                <LayoutDashboard size={15} />
                <span>Dashboard</span>
              </Link>

              {/* 2. Produk & tempat */}
              <div
                className="nav-admin-dropdown-wrap"
                onMouseEnter={() => handleAdminMouseEnter("products")}
                onMouseLeave={handleAdminMouseLeave}
              >
                <button
                  type="button"
                  className={`nav-admin-btn ${isProductActive ? "is-active" : ""}`}
                  onClick={() => handleAdminToggle("products")}
                  aria-expanded={activeAdminMenu === "products"}
                  aria-haspopup="true"
                >
                  <Store size={15} />
                  <span>Produk & tempat</span>
                  <ChevronDown
                    size={13}
                    className={`nav-chevron ${activeAdminMenu === "products" ? "is-open" : ""}`}
                  />
                </button>
                {activeAdminMenu === "products" && (
                  <div
                    className="nav-admin-dropdown-menu nav-admin-dropdown-products"
                    role="menu"
                  >
                    <div className="nav-admin-dropdown-header">
                      <strong>Produk & Tempat</strong>
                      <span>Kelola destinasi, penginapan, kuliner & UMKM</span>
                    </div>
                    <div className="nav-admin-dropdown-list">
                      {filterNavItems(workspaceProducts, profile).map(
                        ({ href, label, desc, icon: Icon }) => (
                          <Link
                            key={href}
                            href={href}
                            className={`nav-admin-dropdown-item ${pathname.startsWith(href) ? "is-active" : ""}`}
                            role="menuitem"
                            onClick={closeAdminMenu}
                          >
                            <div className="nav-admin-item-icon">
                              <Icon size={16} />
                            </div>
                            <div className="nav-admin-item-text">
                              <span className="nav-admin-item-title">{label}</span>
                              <span className="nav-admin-item-desc">{desc}</span>
                            </div>
                          </Link>
                        )
                      )}
                    </div>
                  </div>
                )}
              </div>

              {/* 3. Pesanan & operasional */}
              <div
                className="nav-admin-dropdown-wrap"
                onMouseEnter={() => handleAdminMouseEnter("operations")}
                onMouseLeave={handleAdminMouseLeave}
              >
                <button
                  type="button"
                  className={`nav-admin-btn ${isOperationActive ? "is-active" : ""}`}
                  onClick={() => handleAdminToggle("operations")}
                  aria-expanded={activeAdminMenu === "operations"}
                  aria-haspopup="true"
                >
                  <Ticket size={15} />
                  <span>Pesanan & operasional</span>
                  <ChevronDown
                    size={13}
                    className={`nav-chevron ${activeAdminMenu === "operations" ? "is-open" : ""}`}
                  />
                </button>
                {activeAdminMenu === "operations" && (
                  <div
                    className="nav-admin-dropdown-menu nav-admin-dropdown-operations"
                    role="menu"
                  >
                    <div className="nav-admin-dropdown-header">
                      <strong>Pesanan & Operasional</strong>
                      <span>
                        Pantau reservasi, pengiriman, rekonsiliasi & master data
                      </span>
                    </div>
                    <div className="nav-admin-dropdown-grid">
                      {filterNavItems(workspaceOperations, profile).map(
                        ({ href, label, desc, icon: Icon }) => {
                          const isActive =
                            href === "/dashboard/umkm"
                              ? pathname === "/dashboard/umkm"
                              : pathname.startsWith(href);
                          return (
                            <Link
                              key={href}
                              href={href}
                              className={`nav-admin-dropdown-item ${isActive ? "is-active" : ""}`}
                              role="menuitem"
                              onClick={closeAdminMenu}
                            >
                              <div className="nav-admin-item-icon">
                                <Icon size={16} />
                              </div>
                              <div className="nav-admin-item-text">
                                <span className="nav-admin-item-title">{label}</span>
                                <span className="nav-admin-item-desc">{desc}</span>
                              </div>
                            </Link>
                          );
                        }
                      )}
                    </div>
                  </div>
                )}
              </div>

              {/* Quick link: Lihat situs (hanya tampil jika bukan admin) */}
              {!isAdmin && (
                <Link
                  href="/"
                  className="nav-admin-public-link"
                  title="Lihat tampilan situs publik"
                >
                  <span>Lihat situs</span>
                  <ArrowUpRight size={13} />
                </Link>
              )}
            </nav>
          ) : (
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
          )}
          <div className="nav-actions">
            {!isAdmin && (
              <Link href="/akun" className="nav-orders">
                <Ticket size={18} /> Pesanan saya
              </Link>
            )}
            {profileLoading ? (
              <div className="nav-profile-placeholder" aria-hidden="true" />
            ) : profile ? (
              <div
                className="nav-profile-wrapper"
                ref={dropdownRef}
                onMouseEnter={handleMouseEnter}
                onMouseLeave={handleMouseLeave}
              >
                <button
                  type="button"
                  className="ui-button ui-button-outline nav-login nav-profile-btn"
                  onClick={toggleDropdown}
                  aria-expanded={dropdownOpen}
                  aria-haspopup="true"
                >
                  <UserRound size={16} />
                  <span>Profil</span>
                  <ChevronDown
                    size={14}
                    className={`nav-profile-chevron ${dropdownOpen ? "is-open" : ""}`}
                  />
                </button>
                {dropdownOpen && (
                  <div className="nav-profile-menu" role="menu">
                    {profile.name && (
                      <div className="nav-profile-header">
                        <p className="nav-profile-name">{profile.name}</p>
                        {profile.email && (
                          <p className="nav-profile-email">{profile.email}</p>
                        )}
                      </div>
                    )}
                    <Link
                      href="/akun"
                      className="nav-profile-item"
                      role="menuitem"
                      onClick={closeDropdown}
                    >
                      <UserRound size={16} />
                      <span>Akun saya</span>
                    </Link>
                    {(profile?.platform_role === "super_admin" ||
                      profile?.can_manage_services) && (
                      <Link
                        href="/dashboard"
                        className="nav-profile-item"
                        role="menuitem"
                        onClick={closeDropdown}
                      >
                        <LayoutDashboard size={16} />
                        <span>
                          {profile?.platform_role === "super_admin"
                            ? "Dashboard admin"
                            : "Portal mitra"}
                        </span>
                      </Link>
                    )}
                    <button
                      type="button"
                      className="nav-profile-item is-logout"
                      role="menuitem"
                      disabled={loggingOut}
                      onClick={() => {
                        closeDropdown();
                        setShowLogoutModal(true);
                      }}
                    >
                      <LogOut size={16} />
                      <span>Keluar</span>
                    </button>
                  </div>
                )}
              </div>
            ) : (
              <>
                <Link
                  href="/login"
                  className="ui-button ui-button-outline nav-login"
                >
                  <UserRound size={16} /> Masuk
                </Link>
                <Link href="/daftar" className="ui-button nav-register">
                  Daftar
                </Link>
              </>
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
            {isWorkspace ? (
              <>
                <div className="mobile-admin-section-title">Ringkasan</div>
                <Link
                  href="/dashboard"
                  onClick={() => setMenuPath(null)}
                  className={pathname === "/dashboard" ? "is-active" : ""}
                >
                  <LayoutDashboard size={18} />
                  <span>Dashboard</span>
                  <ChevronRight size={16} />
                </Link>

                <div className="mobile-admin-section-title">Produk & Tempat</div>
                {filterNavItems(workspaceProducts, profile).map(
                  ({ href, label, icon: Icon }) => (
                    <Link
                      key={href}
                      href={href}
                      onClick={() => setMenuPath(null)}
                      className={pathname.startsWith(href) ? "is-active" : ""}
                    >
                      <Icon size={18} />
                      <span>{label}</span>
                      <ChevronRight size={16} />
                    </Link>
                  )
                )}

                <div className="mobile-admin-section-title">Pesanan & Operasional</div>
                {filterNavItems(workspaceOperations, profile).map(
                  ({ href, label, icon: Icon }) => {
                    const isActive =
                      href === "/dashboard/umkm"
                        ? pathname === "/dashboard/umkm"
                        : pathname.startsWith(href);
                    return (
                      <Link
                        key={href}
                        href={href}
                        onClick={() => setMenuPath(null)}
                        className={isActive ? "is-active" : ""}
                      >
                        <Icon size={18} />
                        <span>{label}</span>
                        <ChevronRight size={16} />
                      </Link>
                    );
                  }
                )}

                <div className="mobile-admin-section-title">Lainnya</div>
                {!isAdmin && (
                  <Link href="/" onClick={() => setMenuPath(null)}>
                    <Compass size={18} />
                    <span>Lihat situs publik</span>
                    <ArrowUpRight size={16} />
                  </Link>
                )}
                <Link href="/akun" onClick={() => setMenuPath(null)}>
                  <UserRound size={18} />
                  <span>Akun saya</span>
                  <ChevronRight size={16} />
                </Link>
              </>
            ) : (
              [
                ...products,
                { href: "/bantuan", label: "Pusat bantuan" },
                {
                  href:
                    profile?.platform_role === "super_admin" ||
                    profile?.can_manage_services
                      ? "/dashboard"
                      : "/daftar-mitra",
                  label:
                    profile?.platform_role === "super_admin"
                      ? "Dashboard admin"
                      : profile?.can_manage_services
                        ? "Portal mitra"
                        : "Daftar jadi mitra",
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
              ))
            )}
          </nav>
        )}
        {sessionError && (
          <p role="alert" className="site-container">
            {sessionError}
          </p>
        )}
        {open && profile && (
          <button
            className="ui-button is-mobile-logout"
            disabled={loggingOut}
            onClick={() => {
              setMenuPath(null);
              setShowLogoutModal(true);
            }}
          >
            <LogOut size={16} /> Keluar
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
      {showLogoutModal && (
        <div
          className="modal-backdrop"
          role="dialog"
          aria-modal="true"
          aria-labelledby="logout-modal-title"
          onClick={(e) => {
            if (e.target === e.currentTarget && !loggingOut) {
              setShowLogoutModal(false);
            }
          }}
        >
          <div className="modal-dialog-card">
            <div className="modal-header-row">
              <div className="modal-icon-badge" aria-hidden="true">
                <LogOut size={22} />
              </div>
              <button
                type="button"
                className="modal-close-btn"
                onClick={() => !loggingOut && setShowLogoutModal(false)}
                aria-label="Tutup dialog konfirmasi"
                disabled={loggingOut}
              >
                <X size={18} />
              </button>
            </div>
            <div>
              <h2 id="logout-modal-title" className="modal-title">
                Konfirmasi Keluar
              </h2>
              <p className="modal-description" style={{ marginTop: 8 }}>
                Apakah Anda yakin ingin keluar dari akun Anda?
              </p>
            </div>

            <div className="modal-option-box">
              <label className="modal-checkbox-label">
                <input
                  type="checkbox"
                  checked={clearLocalData}
                  onChange={(e) => setClearLocalData(e.target.checked)}
                  disabled={loggingOut}
                />
                <div className="modal-checkbox-content">
                  <strong>Hapus data sesi & cache pada perangkat ini</strong>
                  <p>
                    Membersihkan data sesi, preferensi tersimpan, dan cache
                    peramban untuk menjaga privasi dan keamanan akun Anda.
                  </p>
                </div>
              </label>
            </div>

            {sessionError && (
              <p role="alert" className="text-sm text-red-600 font-medium">
                {sessionError}
              </p>
            )}

            <div className="modal-actions">
              <button
                type="button"
                className="ui-button ui-button-outline"
                onClick={() => setShowLogoutModal(false)}
                disabled={loggingOut}
              >
                Batal
              </button>
              <button
                type="button"
                className="ui-button modal-btn-danger"
                onClick={confirmLogout}
                disabled={loggingOut}
              >
                {loggingOut ? (
                  <>
                    <Loader2 size={16} className="animate-spin" />
                    <span>Mengeluarkan…</span>
                  </>
                ) : (
                  <>
                    <LogOut size={16} />
                    <span>
                      {clearLocalData ? "Keluar & Hapus Data" : "Ya, Keluar"}
                    </span>
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
export function RouteNavigation() {
  const pathname = usePathname();
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
    dashboard: "Dashboard",
    "kategori-wilayah": "Kategori & wilayah",
    pengaturan: "Pengaturan web",
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

  return (
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
  );
}
