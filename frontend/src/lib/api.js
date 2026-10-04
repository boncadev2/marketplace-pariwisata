export async function apiRequest(path, options = {}) {
  const headers = { Accept: "application/json", ...options.headers };
  if (options.method && options.method !== "GET") {
    const csrf = await fetch("/sanctum/csrf-cookie", {
      credentials: "include",
      signal: options.signal,
    });
    if (!csrf.ok) throw new Error("Tidak dapat menyiapkan sesi.");
    const cookie = document.cookie
      .split("; ")
      .find((entry) => entry.startsWith("XSRF-TOKEN="));
    if (cookie) headers["X-XSRF-TOKEN"] = decodeURIComponent(cookie.slice(11));
    if (!(options.body instanceof FormData)) headers["Content-Type"] = "application/json";
  }
  const response = await fetch(`/api/v1${path}`, {
    ...options,
    headers,
    credentials: "include",
  });
  const result = response.status === 204 ? null : await response.json();
  if (!response.ok) {
    const error = new Error(
      result?.error?.message || result?.message || "Permintaan tidak dapat diproses."
    );
    error.status = response.status;
    throw error;
  }
  return result;
}
