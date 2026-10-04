import { TourPackageDetail } from "../../../components/TourPackageDetail";
export default async function Page({ params }) {
  const { slug } = await params;
  return <TourPackageDetail slug={slug} />;
}
