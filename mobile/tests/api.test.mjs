import test from 'node:test';
import assert from 'node:assert/strict';
import { ApiError, createPublicApi, destinationPath } from '../src/api.mjs';

const base = 'https://wisata.example.test/api/v1';
const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });

test('search safely encodes user input and preserves filter/page choices', () => {
  assert.equal(destinationPath({ query: '  Bukit & Pantai  ', regionId: 7, categoryId: 3, page: 2 }),
    '/destinations?page=2&per_page=12&q=Bukit%20%26%20Pantai&region_id=7&category_id=3');
  assert.equal(destinationPath(), '/destinations?page=1&per_page=12');
});

test('release configuration requires HTTPS and rejects credential-bearing or invalid bases', () => {
  for (const address of [undefined, '', 'http://localhost:8080/api/v1', 'https://secret@wisata.test/api/v1', 'https://wisata.test/api/v2', `${base}?secret=key`]) {
    assert.throws(() => createPublicApi(address), ApiError);
  }
  assert.doesNotThrow(() => createPublicApi('http://10.0.2.2:8080/api/v1', { allowHttp: true }));
});

test('catalog requests use only the configured host and send no credentials', async () => {
  let received;
  const api = createPublicApi(`${base}/`, { fetchImpl: async (url, options) => {
    received = { url, options };
    return response({ data: [], meta: { page: 1, per_page: 12, total: 0 } });
  } });
  const result = await api(destinationPath());
  assert.deepEqual(result.data, []);
  assert.equal(received.url, `${base}/destinations?page=1&per_page=12`);
  assert.equal(received.options.method, 'GET');
  assert.equal(received.options.credentials, 'omit');
  assert.deepEqual(received.options.headers, { Accept: 'application/json' });
  for (const path of ['//evil.test', '/../checkout', '/checkout', '/destinations/../../checkout']) {
    await assert.rejects(api(path), /Jalur katalog tidak valid/);
  }
});

for (const [status, expected] of [[404, 'Destinasi tidak tersedia.'], [429, 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.'], [503, 'Layanan sedang mengalami gangguan. Coba lagi nanti.']]) {
  test(`HTTP ${status} has an actionable error`, async () => {
    const api = createPublicApi(base, { fetchImpl: async () => response({ message: 'Internal provider stack' }, status) });
    await assert.rejects(api('/destinations/bukit'), (error) => error.status === status && error.message === expected);
  });
}

test('malformed and non-JSON successful responses are errors rather than empty catalogs', async () => {
  const malformed = createPublicApi(base, { fetchImpl: async () => response({ message: 'Unexpected result' }) });
  await assert.rejects(malformed('/destinations'), /tidak sesuai kontrak/);
  const nonJson = createPublicApi(base, { fetchImpl: async () => ({ ok: true, status: 200, json: async () => { throw new SyntaxError(); } }) });
  await assert.rejects(nonJson('/destinations'), /tidak dapat dibaca/);
  for (const body of [{ data: null }, { data: [] }, { data: [], meta: { page: 1, per_page: 0, total: 0 } }]) {
    const api = createPublicApi(base, { fetchImpl: async () => response(body) });
    await assert.rejects(api('/destinations'), /tidak sesuai kontrak/);
  }
});

test('network failure can be retried without creating an order', async () => {
  let attempts = 0;
  const api = createPublicApi(base, { fetchImpl: async () => {
    if (++attempts === 1) throw new TypeError('Network failure');
    return response({ data: [], meta: { page: 1, per_page: 12, total: 0 } });
  } });
  await assert.rejects(api('/destinations'), /Tidak dapat terhubung/);
  assert.deepEqual((await api('/destinations')).data, []);
  assert.equal(attempts, 2);
});

const pendingFetch = (_url, { signal }) => new Promise((_resolve, reject) => {
  const cancel = () => { const error = new Error('Aborted'); error.name = 'AbortError'; reject(error); };
  if (signal.aborted) cancel();
  else signal.addEventListener('abort', cancel, { once: true });
});

test('request timeout gives a retryable error', async () => {
  const api = createPublicApi(base, { fetchImpl: pendingFetch, timeoutMs: 5 });
  await assert.rejects(api('/destinations'), /Koneksi terlalu lama/);
});

test('leaving a screen or changing search cancels its in-flight request', async () => {
  const api = createPublicApi(base, { fetchImpl: pendingFetch });
  const controller = new AbortController();
  const request = api('/destinations', { signal: controller.signal });
  controller.abort();
  await assert.rejects(request, (error) => error.name === 'AbortError');
});
