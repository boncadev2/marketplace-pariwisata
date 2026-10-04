import { PackageCatalog } from "../../components/PackageCatalog";
export default async function Page({ searchParams }) {
  const params = await searchParams;
  const destinationSlug = typeof params.destinasi === "string" ? params.destinasi : "";
  return <PackageCatalog key={destinationSlug} destinationSlug={destinationSlug} />;
}
