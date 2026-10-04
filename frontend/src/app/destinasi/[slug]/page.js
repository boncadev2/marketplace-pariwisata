import { notFound } from "next/navigation";
import { DestinationDetail } from "../../../components/DestinationDetail";
import { EmptyState } from "../../../components/PageHeader";
import { Shell } from "../../../components/Shell";

export default async function Page({ params }) {
  const { slug } = await params;
  let response;
  try {
    const backend = process.env.BACKEND_INTERNAL_URL || "http://backend:8000";
    response = await fetch(
      `${backend}/api/v1/destinations/${encodeURIComponent(slug)}`,
      {
        cache: "no-store",
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(8000),
      }
    );
  } catch {
    return (
      <Shell>
        <EmptyState
          headingLevel="h1"
          title="Destinasi belum dapat dimuat"
          description="Layanan katalog belum dapat dihubungi. Kembali ke pencarian dan coba lagi."
          href="/destinasi"
          label="Kembali ke pencarian"
        />
      </Shell>
    );
  }
  if (response.status === 404) notFound();
  if (!response.ok)
    return (
      <Shell>
        <EmptyState
          headingLevel="h1"
          title="Destinasi belum dapat dimuat"
          description="Coba lagi nanti atau temukan destinasi lain."
          href="/destinasi"
        />
      </Shell>
    );
  const { data } = await response.json();
  return <DestinationDetail destination={data} />;
}
