const API_BASE_URL = (
  process.env.NEXT_PUBLIC_API_URL || "/api/v1"
).replace(/\/+$/, "");

export function getBackendUrl() {
  if (process.env.BACKEND_INTERNAL_URL) {
    return process.env.BACKEND_INTERNAL_URL.replace(/\/+$/, "");
  }
  if (process.env.NEXT_PUBLIC_API_URL) {
    return process.env.NEXT_PUBLIC_API_URL.replace(/\/api\/v1\/?$/, "").replace(/\/+$/, "");
  }
  return "http://backend:8000";
}

export async function apiRequest(path, options = {}) {
  const headers = { Accept: "application/json", ...options.headers };

  if (options.method && options.method !== "GET") {
    const csrf = await fetch(`${API_BASE_URL.replace(/\/api\/v1$/, "")}/sanctum/csrf-cookie`, {
      credentials: "include",
      signal: options.signal,
    });

    if (!csrf.ok) throw new Error("Tidak dapat menyiapkan sesi.");

    const cookie = document.cookie
      .split("; ")
      .find((entry) => entry.startsWith("XSRF-TOKEN="));

    if (cookie) headers["X-XSRF-TOKEN"] = decodeURIComponent(cookie.slice(11));

    if (!(options.body instanceof FormData)) {
      headers["Content-Type"] = "application/json";
    }
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
    credentials: "include",
  });

  const result = response.status === 204 ? null : await response.json();

  if (!response.ok) {
    const error = new Error(
      result?.error?.message ||
        result?.message ||
        "Permintaan tidak dapat diproses."
    );
    error.status = response.status;
    throw error;
  }

  return result;
}