import { getBackendUrl } from "../lib/api";

export default async function sitemap() {
  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || "https://wisatadaerah.id";
  const backend = getBackendUrl();

  const staticRoutes = [
    {
      url: `${baseUrl}`,
      lastModified: new Date(),
      changeFrequency: "daily",
      priority: 1.0,
    },
    {
      url: `${baseUrl}/destinasi`,
      lastModified: new Date(),
      changeFrequency: "daily",
      priority: 0.9,
    },
    {
      url: `${baseUrl}/paket`,
      lastModified: new Date(),
      changeFrequency: "daily",
      priority: 0.9,
    },
    {
      url: `${baseUrl}/penginapan`,
      lastModified: new Date(),
      changeFrequency: "daily",
      priority: 0.9,
    },
    {
      url: `${baseUrl}/umkm`,
      lastModified: new Date(),
      changeFrequency: "daily",
      priority: 0.8,
    },
    {
      url: `${baseUrl}/kuliner`,
      lastModified: new Date(),
      changeFrequency: "daily",
      priority: 0.8,
    },
    {
      url: `${baseUrl}/artikel`,
      lastModified: new Date(),
      changeFrequency: "daily",
      priority: 0.85,
    },
    {
      url: `${baseUrl}/daftar-mitra`,
      lastModified: new Date(),
      changeFrequency: "monthly",
      priority: 0.6,
    },
    {
      url: `${baseUrl}/bantuan`,
      lastModified: new Date(),
      changeFrequency: "monthly",
      priority: 0.5,
    },
    {
      url: `${baseUrl}/tentang-kami`,
      lastModified: new Date(),
      changeFrequency: "monthly",
      priority: 0.6,
    },
    {
      url: `${baseUrl}/syarat-ketentuan`,
      lastModified: new Date(),
      changeFrequency: "monthly",
      priority: 0.4,
    },
    {
      url: `${baseUrl}/kebijakan-privasi`,
      lastModified: new Date(),
      changeFrequency: "monthly",
      priority: 0.4,
    },
  ];

  // Helper fetch with timeout
  async function safeFetch(path) {
    try {
      const res = await fetch(`${backend}/api/v1${path}`, {
        headers: { Accept: "application/json" },
        cache: "no-store",
        signal: AbortSignal.timeout(6000),
      });
      if (!res.ok) return [];
      const json = await res.json();
      return Array.isArray(json.data) ? json.data : [];
    } catch {
      return [];
    }
  }

  const [destinations, packages, lodgings, umkmProducts, culinaryPlaces, articles] =
    await Promise.all([
      safeFetch("/destinations"),
      safeFetch("/products?type=package&per_page=100"),
      safeFetch("/lodging/rooms"),
      safeFetch("/umkm-products"),
      safeFetch("/culinary/places"),
      safeFetch("/articles?per_page=100"),
    ]);

  const destinationRoutes = destinations
    .filter((d) => d.slug)
    .map((item) => ({
      url: `${baseUrl}/destinasi/${encodeURIComponent(item.slug)}`,
      lastModified: item.updated_at ? new Date(item.updated_at) : new Date(),
      changeFrequency: "weekly",
      priority: 0.8,
    }));

  const packageRoutes = packages
    .filter((p) => p.slug)
    .map((item) => ({
      url: `${baseUrl}/paket/${encodeURIComponent(item.slug)}`,
      lastModified: item.updated_at ? new Date(item.updated_at) : new Date(),
      changeFrequency: "weekly",
      priority: 0.8,
    }));

  const lodgingRoutes = lodgings
    .filter((l) => l.id)
    .map((item) => ({
      url: `${baseUrl}/penginapan/${item.id}`,
      lastModified: item.updated_at ? new Date(item.updated_at) : new Date(),
      changeFrequency: "weekly",
      priority: 0.7,
    }));

  const umkmRoutes = umkmProducts
    .filter((u) => u.slug)
    .map((item) => ({
      url: `${baseUrl}/umkm/${encodeURIComponent(item.slug)}`,
      lastModified: item.updated_at ? new Date(item.updated_at) : new Date(),
      changeFrequency: "weekly",
      priority: 0.7,
    }));

  const culinaryRoutes = culinaryPlaces
    .filter((c) => c.id)
    .map((item) => ({
      url: `${baseUrl}/kuliner/${item.id}`,
      lastModified: item.updated_at ? new Date(item.updated_at) : new Date(),
      changeFrequency: "weekly",
      priority: 0.7,
    }));

  const articleRoutes = articles
    .filter((a) => a.slug)
    .map((item) => ({
      url: `${baseUrl}/artikel/${encodeURIComponent(item.slug)}`,
      lastModified: item.updated_at ? new Date(item.updated_at) : new Date(),
      changeFrequency: "weekly",
      priority: 0.8,
    }));

  return [
    ...staticRoutes,
    ...destinationRoutes,
    ...packageRoutes,
    ...lodgingRoutes,
    ...umkmRoutes,
    ...culinaryRoutes,
    ...articleRoutes,
  ];
}
