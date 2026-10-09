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

const ALLOWED_PATH_REGEX = /^\/(?:destinations(?:\/[a-z0-9%_-]+)?|lookup\/(?:regions|categories)|login|me|logout|account\/orders(?:\/[a-z0-9%_-]+(?:\/vouchers)?)?|guest\/orders\/[a-z0-9%_-]+\/vouchers)(?:\?[^#\\]*)?$/i;

export function createPublicApi(baseUrl, { allowHttp = false, fetchImpl = globalThis.fetch, timeoutMs = 12000, defaultToken = null } = {}) {
  const match = typeof baseUrl === 'string' && baseUrl.match(/^(https?):\/\/([a-z0-9.-]+(?::\d+)?|\[[a-f0-9:]+\](?::\d+)?)\/api\/v1\/?$/i);
  if (!match || (match[1].toLowerCase() === 'http' && !allowHttp)) {
    throw new ApiError('Atur EXPO_PUBLIC_API_URL dengan alamat /api/v1. Build rilis wajib memakai HTTPS.');
  }
  const root = baseUrl.replace(/\/$/, '');

  return async function request(path, { method = 'GET', body = null, headers = {}, signal, token = defaultToken } = {}) {
    if (!ALLOWED_PATH_REGEX.test(path)) {
      throw new ApiError('Jalur katalog tidak valid.');
    }

    const controller = new AbortController();
    const cancel = () => controller.abort();
    let timedOut = false;
    if (signal?.aborted) controller.abort();
    signal?.addEventListener('abort', cancel);
    const timeout = setTimeout(() => { timedOut = true; controller.abort(); }, timeoutMs);

    const reqHeaders = { Accept: 'application/json', ...headers };
    if (token) {
      reqHeaders['Authorization'] = `Bearer ${token}`;
    }
    if (body !== null && typeof body === 'object') {
      reqHeaders['Content-Type'] = 'application/json';
    }

    const fetchOptions = {
      method,
      headers: reqHeaders,
      credentials: token ? 'same-origin' : 'omit',
      signal: controller.signal,
    };
    if (body !== null) {
      fetchOptions.body = typeof body === 'string' ? body : JSON.stringify(body);
    }

    try {
      const response = await fetchImpl(`${root}${path}`, fetchOptions);

      if (response.status === 204) {
        return null;
      }

      let resBody;
      try {
        resBody = await response.json();
      } catch {
        if (controller.signal.aborted) throw new Error('Request aborted');
        throw new ApiError('Respons layanan tidak dapat dibaca.', response.status);
      }

      if (!response.ok) {
        let message = 'Permintaan tidak dapat diproses.';
        if (response.status === 401) {
          message = 'Sesi masuk telah berakhir. Silakan masuk kembali.';
        } else if (response.status === 404) {
          message = path.startsWith('/destinations') ? 'Destinasi tidak tersedia.' : 'Data tidak ditemukan.';
        } else if (response.status === 422) {
          message = resBody?.message || 'Data yang dimasukkan tidak valid.';
        } else if (response.status === 429) {
          message = 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';
        } else if (response.status >= 500) {
          message = 'Layanan sedang mengalami gangguan. Coba lagi nanti.';
        } else if (resBody?.error?.message || resBody?.message) {
          message = resBody.error?.message || resBody.message;
        }
        throw new ApiError(message, response.status);
      }

      const resourcePath = path.split('?')[0];

      // Contract checks for destinations and lookup
      if (resourcePath.startsWith('/destinations') || resourcePath.startsWith('/lookup')) {
        if (!resBody || !Object.prototype.hasOwnProperty.call(resBody, 'data')) {
          throw new ApiError('Respons layanan tidak sesuai kontrak.');
        }
        const isDetail = resourcePath.startsWith('/destinations/');
        const validRecord = (item) => item && Number.isInteger(item.id) && typeof item.name === 'string';
        if (isDetail ? !validRecord(resBody.data) || typeof resBody.data.slug !== 'string'
          : !Array.isArray(resBody.data) || !resBody.data.every(validRecord)) {
          throw new ApiError('Respons layanan tidak sesuai kontrak.');
        }
        if (resourcePath === '/destinations' && (!resBody.meta
            || !Number.isInteger(resBody.meta.page) || resBody.meta.page < 1
            || !Number.isInteger(resBody.meta.per_page) || resBody.meta.per_page < 1
            || !Number.isInteger(resBody.meta.total) || resBody.meta.total < 0
            || !resBody.data.every((item) => typeof item.slug === 'string'))) {
          throw new ApiError('Respons layanan tidak sesuai kontrak.');
        }
      }

      return resBody;
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

export async function loginCustomer(api, { email, password }) {
  if (!email || !password) {
    throw new ApiError('Email dan kata sandi wajib diisi.', 422);
  }
  return api('/login', {
    method: 'POST',
    body: { email: email.trim().toLowerCase(), password },
  });
}

export async function fetchCustomerProfile(api, token) {
  return api('/me', { method: 'GET', token });
}

export async function fetchCustomerOrders(api, token) {
  return api('/account/orders', { method: 'GET', token });
}

export async function fetchCustomerOrderVouchers(api, { orderId, token }) {
  if (!orderId) throw new ApiError('Nomor pesanan wajib diisi.', 422);
  return api(`/account/orders/${encodeURIComponent(orderId)}/vouchers`, { method: 'GET', token });
}

export async function fetchGuestVouchers(api, { orderId, guestToken }) {
  if (!orderId) throw new ApiError('Nomor pesanan wajib diisi.', 422);
  return api(`/guest/orders/${encodeURIComponent(orderId)}/vouchers`, {
    method: 'GET',
    headers: guestToken ? { 'X-Guest-Access-Token': guestToken } : {},
  });
}
