import { DestinationSearch } from "../../components/DestinationSearch";
import { Shell } from "../../components/Shell";

export default function Page() {
  return (
    <Shell>
      <section className="page-intro">
        <p className="eyebrow">Destinasi</p>
        <h1>Temukan wisata pilihanmu</h1>
        <p>Cari berdasarkan nama destinasi.</p>
      </section>
      <DestinationSearch />
    </Shell>
  );
}
