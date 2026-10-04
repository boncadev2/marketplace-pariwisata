import { DestinationDetail } from "../../../components/DestinationDetail";
export default function Page() {
  return (
    <DestinationDetail
      demo
      destination={{
        name: "Sejenak di alam daerah",
        summary:
          "Nikmati suasana hijau, udara segar, dan perjalanan yang memberi ruang untuk beristirahat.",
        description:
          "Ini adalah contoh tampilan detail destinasi. Pada katalog terbit, deskripsi dan koordinat berasal dari informasi pengelola. Jelajahi katalog untuk melihat destinasi yang tersedia.",
        category: { name: "Wisata alam" },
        region: { name: "Wilayah demonstrasi" },
        latitude: null,
        longitude: null,
      }}
    />
  );
}
