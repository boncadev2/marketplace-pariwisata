import Link from "next/link";
import { Shell } from "../../../components/Shell";

export default async function Page({ params }) {
  const { slug } = await params;
  const destination = {
    name: slug.replaceAll("-", " "),
    latitude: -6.914744,
    longitude: 107.60981,
  };
  const navigation = `https://www.google.com/maps/dir/?api=1&destination=${destination.latitude},${destination.longitude}`;
  return (
    <Shell>
      <section className="detail-layout">
        <div
          className="hero-placeholder"
          role="img"
          aria-label="Placeholder galeri destinasi"
        />
        <div>
          <p className="eyebrow">Data demonstrasi</p>
          <h1>{destination.name}</h1>
          <p>
            Detail destinasi akan memakai data katalog terbit dari API. Peta
            bersifat opsional dan pencarian tetap dapat digunakan tanpa akses
            lokasi perangkat.
          </p>
          <dl className="facts">
            <div>
              <dt>Koordinat</dt>
              <dd>
                {destination.latitude}, {destination.longitude}
              </dd>
            </div>
            <div>
              <dt>Status</dt>
              <dd>Informasi demonstrasi</dd>
            </div>
          </dl>
          <a
            className="button"
            href={navigation}
            rel="noreferrer"
            target="_blank"
          >
            Buka navigasi
          </a>
          <p>
            <Link href="/destinasi">Kembali ke pencarian</Link>
          </p>
        </div>
      </section>
    </Shell>
  );
}
