"use client";

import { useEffect, useState } from "react";
import { Shell } from "./Shell";
import { PageHeader } from "./PageHeader";
import { apiRequest } from "../lib/api";

export function ProductionReadiness() {
  const [report, setReport] = useState(null),
    [error, setError] = useState("");
  useEffect(() => {
    const controller = new AbortController();
    apiRequest("/dashboard/production-readiness", { signal: controller.signal })
      .then((result) => setReport(result.data))
      .catch((error) => {
        if (!controller.signal.aborted) setError(error.message);
      });
    return () => controller.abort();
  }, []);
  return (
    <Shell>
      <PageHeader
        eyebrow="Persiapan peluncuran"
        title="Kesiapan produksi"
        description="Pantau konfigurasi pembayaran, operasional, dan kelengkapan katalog sebelum membuka layanan."
      />
      {error ? (
        <p role="alert">{error}</p>
      ) : !report ? (
        <p role="status">Memeriksa kesiapan…</p>
      ) : (
        <>
          <p className="travel-message">
            {report.ready
              ? "Konfigurasi dasar lengkap. Lanjutkan verifikasi operasional sebelum peluncuran."
              : "Masih ada kebutuhan yang harus dilengkapi."}
          </p>
          <div className="travel-list">
            {report.checks.map((check) => (
              <article className="travel-list-card" key={check.label}>
                <div>
                  <span className="travel-status">
                    {check.ready ? "Siap" : "Belum siap"}
                  </span>
                  <h2>{check.label}</h2>
                  {!check.ready && <p>{check.instruction}</p>}
                </div>
              </article>
            ))}
          </div>
          <p>{report.note}</p>
        </>
      )}
    </Shell>
  );
}
