import { Shell } from "../components/Shell";
import { Loader2 } from "lucide-react";
export default function Loading() {
  return (
    <Shell>
      <div className="empty-state" role="status">
        <Loader2
          className="mx-auto mb-5 animate-spin text-blue-600"
          size={30}
        />
        <h2>Menyiapkan halaman untuk Anda…</h2>
        <p>Sebentar lagi rencana perjalanan Anda siap ditampilkan.</p>
      </div>
    </Shell>
  );
}
