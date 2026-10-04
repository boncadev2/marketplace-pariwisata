"use client";
import { Shell } from "../components/Shell";
import { RefreshCw } from "lucide-react";
export default function ErrorPage({ retry }) {
  return (
    <Shell>
      <div className="empty-state">
        <span className="empty-state-icon">
          <RefreshCw size={28} />
        </span>
        <h1 className="text-2xl font-bold">Ada kendala dalam perjalanan.</h1>
        <p className="mt-3" role="status">
          Halaman belum dapat dimuat. Coba lagi untuk melanjutkan.
        </p>
        <button type="button" className="ui-button" onClick={() => retry()}>
          Muat ulang halaman
        </button>
      </div>
    </Shell>
  );
}
