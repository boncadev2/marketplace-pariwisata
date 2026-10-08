export default function robots() {
  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || "https://wisatadaerah.id";

  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        disallow: [
          "/dashboard",
          "/dashboard/*",
          "/akun",
          "/akun/*",
          "/pembayaran",
          "/pembayaran/*",
          "/checkout",
          "/checkout/*",
          "/reconciliation",
          "/reconciliation/*",
          "/petugas",
          "/petugas/*",
          "/reset-password",
          "/reset-password/*",
          "/api/*",
        ],
      },
    ],
    sitemap: `${baseUrl}/sitemap.xml`,
  };
}
