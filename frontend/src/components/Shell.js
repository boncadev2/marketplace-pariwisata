import Link from "next/link";
import styles from "./Shell.module.css";

const links = [
  ["Destinasi", "/destinasi"],
  ["Paket", "/paket"],
  ["Penginapan", "/penginapan"],
  ["Kuliner", "/kuliner"],
];

export function Shell({ children }) {
  return (
    <div className={styles.shell}>
      <a className="skip-link" href="#konten">
        Lewati ke konten
      </a>
      <header className="site-header">
        <Link className="brand" href="/">
          Wisata Daerah
        </Link>
        <nav aria-label="Navigasi utama">
          {links.map(([label, href]) => (
            <Link key={href} href={href}>
              {label}
            </Link>
          ))}
        </nav>
        <Link className="account-link" href="/login">
          Akun
        </Link>
      </header>
      <main id="konten">{children}</main>
      <footer className="site-footer">
        Data demonstrasi untuk tahap pengembangan.
      </footer>
    </div>
  );
}

export function Card({ title, meta, price, href = "#", children }) {
  return (
    <article className="card">
      <div className="image-placeholder" aria-hidden="true" />
      <div className="card-body">
        <p className="eyebrow">{meta}</p>
        <h2>{title}</h2>
        <p>{price}</p>
        <Link href={href}>Lihat detail</Link>
        {children}
      </div>
    </article>
  );
}
