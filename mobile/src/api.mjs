export class ApiError extends Error {
  constructor(message, status = 0) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

export function destinationPath({ query = '', regionId = null, categoryId = null, page = 1 } = {}) {
  const params = [['page', page], ['per_page', 12]];
  if (query.trim()) params.push(['q', query.trim()]);
  if (regionId !== null) params.push(['region_id', regionId]);
  if (categoryId !== null) params.push(['category_id', categoryId]);
  return `/destinations?${params.map(([key, value]) => `${key}=${encodeURIComponent(value)}`).join('&')}`;
}

export function createPublicApi(baseUrl, { allowHttp = false, fetchImpl = globalThis.fetch, timeoutMs = 12000 } = {}) {
  const match = typeof baseUrl === 'string' && baseUrl.match(/^(https?):\/\/([a-z0-9.-]+(?::\d+)?|\[[a-f0-9:]+\](?::\d+)?)\/api\/v1\/?$/i);
  if (!match || (match[1].toLowerCase() === 'http' && !allowHttp)) {
    throw new ApiError('Atur EXPO_PUBLIC_API_URL dengan alamat /api/v1. Build rilis wajib memakai HTTPS.');
  }
  const root = baseUrl.replace(/\/$/, '');
  return async function request(path, { signal } = {}) {
    if (!/^\/(?:destinations(?:\/[a-z0-9%_-]+)?|lookup\/(?:regions|categories))(?:\?[^#\\]*)?$/i.test(path)) {
      throw new ApiError('Jalur katalog tidak valid.');
    }
    const controller = new AbortController();
    const cancel = () => controller.abort();
    let timedOut = false;
    if (signal?.aborted) controller.abort();
    signal?.addEventListener('abort', cancel);
    const timeout = setTimeout(() => { timedOut = true; controller.abort(); }, timeoutMs);
    try {
      const response = await fetchImpl(`${root}${path}`, {
        method: 'GET', headers: { Accept: 'application/json' },
        credentials: 'omit', signal: controller.signal,
      });
      let body;
      try { body = await response.json(); }
      catch {
        if (controller.signal.aborted) throw new Error('Request aborted');
        throw new ApiError('Respons layanan tidak dapat dibaca.', response.status);
      }
      if (!response.ok) {
        const message = response.status === 404 ? 'Destinasi tidak tersedia.'
          : response.status === 429 ? 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.'
            : response.status >= 500 ? 'Layanan sedang mengalami gangguan. Coba lagi nanti.'
              : body?.error?.message || body?.message || 'Permintaan tidak dapat diproses.';
        throw new ApiError(message, response.status);
      }
      if (!body || !Object.prototype.hasOwnProperty.call(body, 'data')) throw new ApiError('Respons layanan tidak sesuai kontrak.');
      const resourcePath = path.split('?')[0];
      const isDetail = resourcePath.startsWith('/destinations/');
      const validRecord = (item) => item && Number.isInteger(item.id) && typeof item.name === 'string';
      if (isDetail ? !validRecord(body.data) || typeof body.data.slug !== 'string'
        : !Array.isArray(body.data) || !body.data.every(validRecord)) {
        throw new ApiError('Respons layanan tidak sesuai kontrak.');
      }
      if (resourcePath === '/destinations' && (!body.meta
          || !Number.isInteger(body.meta.page) || body.meta.page < 1
          || !Number.isInteger(body.meta.per_page) || body.meta.per_page < 1
          || !Number.isInteger(body.meta.total) || body.meta.total < 0
          || !body.data.every((item) => typeof item.slug === 'string'))) {
        throw new ApiError('Respons layanan tidak sesuai kontrak.');
      }
      return body;
    } catch (error) {
      if (signal?.aborted) {
        const cancelled = new Error('Permintaan dibatalkan.');
        cancelled.name = 'AbortError';
        throw cancelled;
      }
      if (timedOut) throw new ApiError('Koneksi terlalu lama. Periksa jaringan lalu coba lagi.');
      if (error instanceof ApiError) throw error;
      throw new ApiError('Tidak dapat terhubung. Periksa jaringan dan alamat API.');
    } finally {
      clearTimeout(timeout);
      signal?.removeEventListener('abort', cancel);
    }
  };
}
