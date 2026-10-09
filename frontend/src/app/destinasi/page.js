import { DestinationSearch } from "../../components/DestinationSearch";
import { Shell } from "../../components/Shell";
import { PageHeader } from "../../components/PageHeader";

export default async function Page({ searchParams }) {
  const parameters = await searchParams;
  const initialQuery =
    typeof parameters?.q === "string" ? parameters.q.slice(0, 100) : "";
  return (
    <Shell>
      <PageHeader
        eyebrow="Jelajahi destinasi"
        title={
          <>
            <span>Temukan tempat.</span>{" "}
            <span>
              Ciptakan <span className="heading-accent">cerita.</span>
            </span>
          </>
        }
        description="Dari alam yang menenangkan hingga desa yang penuh budaya. Pilih pengalaman yang paling cocok untuk perjalanan Anda."
        image="https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=900&q=85"
      />
      <DestinationSearch initialQuery={initialQuery} />
    </Shell>
  );
}
