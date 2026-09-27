import { Card, Shell } from "./Shell";
const config = {
  destinasi: [
    "Temukan wisata pilihanmu",
    "Cari destinasi berdasarkan wilayah, kategori, atau fasilitas.",
  ],
  paket: [
    "Paket wisata desa",
    "Itinerary dan kepastian keberangkatan tampil sebelum pemesanan.",
  ],
  penginapan: ["Penginapan", "Kategori ini masih informasi pada rilis pilot."],
  kuliner: ["Kuliner", "Kategori ini masih informasi pada rilis pilot."],
};
export function ListingPage({ type }) {
  const [title, description] = config[type];
  return (
    <Shell>
      <section className="page-intro">
        <p className="eyebrow">Wireframe Fase 03</p>
        <h1>{title}</h1>
        <p>{description}</p>
      </section>
      <form className="search-panel">
        <label>
          Cari wisata
          <input placeholder="Nama destinasi atau kegiatan" type="search" />
        </label>
        <label>
          Wilayah
          <select defaultValue="">
            <option value="">Semua wilayah</option>
            <option>Kabupaten Demo</option>
          </select>
        </label>
        <button type="button">Terapkan filter</button>
      </form>
      <section aria-label="Hasil demonstrasi" className="card-grid">
        {["Air Terjun Demo", "Kampung Budaya Demo", "Taman Pantai Demo"].map(
          (name) => (
            <Card
              key={name}
              title={name}
              meta="Kabupaten Demo"
              price="Informasi demonstrasi"
              href={`/${type}/demo`}
            />
          )
        )}
      </section>
    </Shell>
  );
}
export function DetailPage({ packageDetail = false }) {
  const title = packageDetail ? "Sehari di Desa Demo" : "Air Terjun Demo";
  return (
    <Shell>
      <section className="detail-layout">
        <div
          className="hero-placeholder"
          aria-label="Placeholder galeri"
          role="img"
        />
        <div>
          <p className="eyebrow">Kabupaten Demo · Data demonstrasi</p>
          <h1>{title}</h1>
          <p>
            {packageDetail
              ? "Paket perjalanan dengan itinerary yang ditinjau sebelum transaksi aktif."
              : "Destinasi gratis dengan informasi kunjungan, fasilitas, dan akses."}
          </p>
          <dl className="facts">
            <div>
              <dt>Lokasi</dt>
              <dd>Desa Demo</dd>
            </div>
            <div>
              <dt>{packageDetail ? "Titik kumpul" : "Jam buka"}</dt>
              <dd>{packageDetail ? "Balai Desa Demo" : "08.00–17.00"}</dd>
            </div>
            <div>
              <dt>Biaya</dt>
              <dd>{packageDetail ? "Belum aktif" : "Gratis"}</dd>
            </div>
          </dl>
          <button disabled>
            {packageDetail ? "Pemesanan belum aktif" : "Dapatkan petunjuk"}
          </button>
        </div>
      </section>
    </Shell>
  );
}
