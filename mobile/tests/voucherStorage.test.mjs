import test from 'node:test';
import assert from 'node:assert/strict';
import {
  createMemoryStorage,
  saveAuthSession,
  getAuthSession,
  clearAuthSession,
  saveVouchersOffline,
  getOfflineVouchers,
  addOfflineVoucher,
  clearOfflineVouchers,
  formatVoucherForGate,
} from '../src/voucherStorage.mjs';

test('auth session persistence saves, reads, and clears valid user tokens', async () => {
  const storage = createMemoryStorage();
  assert.equal(await getAuthSession(storage), null);

  const session = {
    token: '1|test-sanctum-token-abcdef1234567890',
    user: { id: 42, name: 'Budi Wisatawan', email: 'budi@example.test' },
  };

  await saveAuthSession(session, storage);
  const loaded = await getAuthSession(storage);
  assert.equal(loaded.token, session.token);
  assert.equal(loaded.user.name, 'Budi Wisatawan');
  assert.ok(loaded.saved_at);

  await clearAuthSession(storage);
  assert.equal(await getAuthSession(storage), null);
});

test('auth session gracefully handles corrupt or empty storage values', async () => {
  const storage = createMemoryStorage();
  await storage.setItem('wisata_mobile_auth_session_v1', '{invalid-json');
  assert.equal(await getAuthSession(storage), null);

  await storage.setItem('wisata_mobile_auth_session_v1', 'null');
  assert.equal(await getAuthSession(storage), null);
});

test('formatVoucherForGate formats voucher payload with 48-char gate token', () => {
  const token = '2vJ1166q2nZ0FR0dxvk8TKl30ER6ROwM8lzskahPpM8afvsw';
  const voucher = {
    token,
    product_name: 'Tiket Masuk Bukit Teletubbies',
    partner_name: 'Pokdarwis Teletubbies',
    customer_name: 'Ahmad Pengunjung',
    service_date: '2026-10-15',
    admissions: 3,
    used_admissions: 0,
    status: 'active',
  };

  const formatted = formatVoucherForGate(voucher);
  assert.equal(formatted.token, token);
  assert.equal(formatted.qrData, token);
  assert.equal(formatted.productName, 'Tiket Masuk Bukit Teletubbies');
  assert.equal(formatted.admissions, 3);
  assert.equal(formatted.isRedeemed, false);
  assert.equal(formatted.isOfflineCached, true);
});

test('formatVoucherForGate rejects invalid tokens or empty objects', () => {
  assert.throws(() => formatVoucherForGate(null), /tidak valid/);
  assert.throws(() => formatVoucherForGate({ token: 'short' }), /tidak memenuhi format/);
  assert.throws(() => formatVoucherForGate({ token: '' }), /tidak memenuhi format/);
});

test('offline voucher storage caches vouchers and handles offline retrieval', async () => {
  const storage = createMemoryStorage();
  const initial = await getOfflineVouchers(storage);
  assert.deepEqual(initial.items, []);
  assert.equal(initial.cached_at, null);

  const token1 = '111111111111111111111111111111111111111111111111';
  const token2 = '222222222222222222222222222222222222222222222222';

  const vouchers = [
    { token: token1, product_name: 'Tiket Pantai Pasir Putih', admissions: 2 },
    { token: token2, product_name: 'Tiket Air Terjun Madakaripura', admissions: 1 },
  ];

  await saveVouchersOffline(vouchers, storage);
  const cached = await getOfflineVouchers(storage);
  assert.equal(cached.items.length, 2);
  assert.equal(cached.items[0].token, token1);
  assert.equal(cached.items[1].token, token2);
  assert.ok(cached.cached_at);

  // Add another voucher individually
  const token3 = '333333333333333333333333333333333333333333333333';
  await addOfflineVoucher({ token: token3, product_name: 'Tiket Candi Penataran', admissions: 4 }, storage);

  const updated = await getOfflineVouchers(storage);
  assert.equal(updated.items.length, 3);
  assert.equal(updated.items[0].token, token3);

  // Clear offline vouchers
  await clearOfflineVouchers(storage);
  const cleared = await getOfflineVouchers(storage);
  assert.deepEqual(cleared.items, []);
});

test('offline voucher storage deduplicates existing vouchers on addition', async () => {
  const storage = createMemoryStorage();
  const token = '444444444444444444444444444444444444444444444444';

  await addOfflineVoucher({ token, product_name: 'Tiket Kebun Teh', status: 'active' }, storage);
  await addOfflineVoucher({ token, product_name: 'Tiket Kebun Teh', status: 'redeemed' }, storage);

  const res = await getOfflineVouchers(storage);
  assert.equal(res.items.length, 1);
  assert.equal(res.items[0].status, 'redeemed');
  assert.equal(res.items[0].isRedeemed, true);
});
