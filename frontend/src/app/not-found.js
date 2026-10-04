import { Shell } from "../components/Shell";
import { EmptyState } from "../components/PageHeader";
import { MapPinOff } from "lucide-react";
export default function NotFound() {
  return (
    <Shell>
      <EmptyState
        headingLevel="h1"
        icon={MapPinOff}
        title="Tujuan ini belum ditemukan."
        description="Halaman mungkin sudah berubah atau destinasi belum tersedia. Mari temukan pengalaman lain untuk perjalanan Anda."
        href="/destinasi"
        label="Jelajahi destinasi"
      />
    </Shell>
  );
}
