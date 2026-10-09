/**
 * Offline Voucher & Session Storage for Mobile Expo Client.
 * Enables ticket inspection and QR gate presentation even when network
 * coverage is unavailable at remote nature parks or mountain gates.
 */

export function createMemoryStorage() {
  const store = new Map();
  return {
    async getItem(key) {
      return store.has(key) ? store.get(key) : null;
    },
    async setItem(key, value) {
      store.set(key, String(value));
    },
    async removeItem(key) {
      store.delete(key);
    },
    async clear() {
      store.clear();
    },
  };
}

const STORAGE_KEYS = {
  VOUCHERS: 'wisata_mobile_offline_vouchers_v1',
  SESSION: 'wisata_mobile_auth_session_v1',
};

export async function saveAuthSession(session, storage = createMemoryStorage()) {
  if (!session || typeof session !== 'object') {
    throw new TypeError('Sesi autentikasi tidak valid.');
  }
  await storage.setItem(STORAGE_KEYS.SESSION, JSON.stringify({
    token: session.token || null,
    user: session.user || null,
    saved_at: new Date().toISOString(),
  }));
}

export async function getAuthSession(storage = createMemoryStorage()) {
  try {
    const raw = await storage.getItem(STORAGE_KEYS.SESSION);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object') return null;
    return parsed;
  } catch {
    return null;
  }
}

export async function clearAuthSession(storage = createMemoryStorage()) {
  await storage.removeItem(STORAGE_KEYS.SESSION);
}

export async function saveVouchersOffline(vouchers, storage = createMemoryStorage()) {
  if (!Array.isArray(vouchers)) {
    throw new TypeError('Vouchers harus berupa array.');
  }
  const payload = {
    cached_at: new Date().toISOString(),
    items: vouchers.map(formatVoucherForGate),
  };
  await storage.setItem(STORAGE_KEYS.VOUCHERS, JSON.stringify(payload));
  return payload;
}

export async function getOfflineVouchers(storage = createMemoryStorage()) {
  try {
    const raw = await storage.getItem(STORAGE_KEYS.VOUCHERS);
    if (!raw) return { cached_at: null, items: [] };
    const parsed = JSON.parse(raw);
    if (!parsed || !Array.isArray(parsed.items)) return { cached_at: null, items: [] };
    return parsed;
  } catch {
    return { cached_at: null, items: [] };
  }
}

export async function addOfflineVoucher(voucher, storage = createMemoryStorage()) {
  const current = await getOfflineVouchers(storage);
  const formatted = formatVoucherForGate(voucher);
  const filtered = current.items.filter((item) => item.token !== formatted.token);
  filtered.unshift(formatted);
  await storage.setItem(STORAGE_KEYS.VOUCHERS, JSON.stringify({
    cached_at: new Date().toISOString(),
    items: filtered,
  }));
  return formatted;
}

export async function clearOfflineVouchers(storage = createMemoryStorage()) {
  await storage.removeItem(STORAGE_KEYS.VOUCHERS);
}

export function formatVoucherForGate(voucher) {
  if (!voucher || typeof voucher !== 'object') {
    throw new TypeError('Objek voucher tidak valid.');
  }
  const token = typeof voucher.token === 'string' ? voucher.token.trim() : '';
  if (!token || token.length < 24) {
    throw new Error('Token voucher tidak memenuhi format 48 karakter.');
  }

  const todayStr = new Date().toISOString().slice(0, 10);
  const serviceDate = voucher.service_date ? String(voucher.service_date).slice(0, 10) : todayStr;
  const admissions = Number.isInteger(voucher.admissions) ? voucher.admissions : 1;
  const usedAdmissions = Number.isInteger(voucher.used_admissions) ? voucher.used_admissions : 0;
  const status = voucher.status || (usedAdmissions >= admissions ? 'redeemed' : 'active');

  return {
    token,
    qrData: token,
    orderId: voucher.order_id || voucher.orderId || null,
    productName: voucher.product_name || voucher.productName || 'Tiket Wisata Daerah',
    partnerName: voucher.partner_name || voucher.partnerName || 'Pengelola Destinasi',
    customerName: voucher.customer_name || voucher.customerName || 'Tamu Pengunjung',
    serviceDate,
    admissions,
    usedAdmissions,
    status,
    isToday: serviceDate === todayStr,
    isRedeemed: status === 'redeemed' || usedAdmissions >= admissions,
    cachedAt: voucher.cachedAt || new Date().toISOString(),
    isOfflineCached: true,
  };
}
