import React, { useEffect, useMemo, useState, useCallback } from 'react';
import { StatusBar } from 'expo-status-bar';
import {
  ActivityIndicator, BackHandler, FlatList, Modal, Platform, Pressable,
  SafeAreaView, ScrollView, StatusBar as NativeStatusBar, StyleSheet, Text, TextInput, View,
} from 'react-native';
import {
  createPublicApi,
  destinationPath,
  loginCustomer,
  fetchCustomerOrders,
  fetchCustomerOrderVouchers,
  fetchGuestVouchers,
} from './src/api.mjs';
import {
  getAuthSession,
  saveAuthSession,
  clearAuthSession,
  getOfflineVouchers,
  saveVouchersOffline,
  addOfflineVoucher,
  clearOfflineVouchers,
  createMemoryStorage,
} from './src/voucherStorage.mjs';
import { usePublicResource } from './src/usePublicResource';

// Fallback in-memory storage if AsyncStorage is not present in pure native testing
const appStorage = createMemoryStorage();

function Button({ children, onPress, disabled = false, secondary = false, selected = false, danger = false }) {
  return (
    <Pressable onPress={onPress} disabled={disabled} accessibilityRole="button"
      accessibilityState={{ disabled, selected }}
      style={({ pressed }) => [
        styles.button,
        secondary && styles.secondary,
        danger && styles.dangerButton,
        disabled && styles.disabled,
        pressed && styles.pressed,
      ]}>
      <Text style={[styles.buttonText, secondary && styles.secondaryText, danger && styles.dangerText]}>{children}</Text>
    </Pressable>
  );
}

function RequestState({ resource, children }) {
  if (resource.loading) return <View style={styles.notice} accessibilityLiveRegion="polite"><ActivityIndicator color="#047857" /><Text style={styles.body}>Memuat data…</Text></View>;
  if (resource.error) return (
    <View style={styles.notice} accessibilityLiveRegion="polite">
      <Text style={styles.error}>{resource.error.message}</Text>
      <Button onPress={resource.reload}>Coba lagi</Button>
    </View>
  );
  return children;
}

function FilterPicker({ label, values, selected, onSelect, resource }) {
  const [open, setOpen] = useState(false);
  return (
    <View style={styles.filter}>
      <Button secondary selected={selected !== null} onPress={() => setOpen(true)}>
        {label}: {values?.find((item) => item.id === selected)?.name || 'Semua'}
      </Button>
      <Modal visible={open} animationType="slide" onRequestClose={() => setOpen(false)}>
        <SafeAreaView style={styles.container}>
          <View style={styles.content}>
            <Text style={styles.title}>Pilih {label.toLowerCase()}</Text>
            <Button secondary onPress={() => setOpen(false)}>Tutup</Button>
          </View>
          <RequestState resource={resource}>
            <FlatList data={[{ id: null, name: 'Semua' }, ...(values || [])]}
              keyExtractor={(item) => String(item.id)} contentContainerStyle={styles.content}
              renderItem={({ item }) => <Button secondary selected={item.id === selected}
                onPress={() => { onSelect(item.id); setOpen(false); }}>{item.name}{item.id === selected ? ' ✓' : ''}</Button>} />
          </RequestState>
        </SafeAreaView>
      </Modal>
    </View>
  );
}

function DestinationDetail({ api, slug, onBack }) {
  const detail = usePublicResource(api, `/destinations/${encodeURIComponent(slug)}`);
  useEffect(() => {
    const subscription = BackHandler.addEventListener('hardwareBackPress', () => { onBack(); return true; });
    return () => subscription.remove();
  }, [onBack]);
  const destination = detail.result?.data;
  return (
    <ScrollView contentContainerStyle={styles.content}>
      <Button secondary onPress={onBack}>← Kembali ke destinasi</Button>
      <RequestState resource={detail}>
        {destination && <View style={styles.card}>
          <Text style={styles.eyebrow}>{destination.category?.name || 'Destinasi wisata'}</Text>
          <Text style={styles.title} accessibilityRole="header">{destination.name}</Text>
          <Text style={styles.location}>{destination.region?.name || 'Wilayah belum tersedia'}</Text>
          {destination.summary ? <Text style={styles.summary}>{destination.summary}</Text> : null}
          <Text style={styles.body}>{destination.description || 'Deskripsi lengkap belum tersedia.'}</Text>
          {destination.latitude != null && destination.longitude != null && <Text style={styles.body}>
            Koordinat: {destination.latitude}, {destination.longitude}
          </Text>}
        </View>}
      </RequestState>
    </ScrollView>
  );
}

function DestinationCatalog({ api, onSelect, filters, setFilters }) {
  const [input, setInput] = useState(filters.query);
  const catalog = usePublicResource(api, destinationPath(filters));
  const regions = usePublicResource(api, '/lookup/regions');
  const categories = usePublicResource(api, '/lookup/categories');
  const updateFilter = (change) => setFilters((current) => ({ ...current, ...change, page: 1 }));
  const meta = catalog.result?.meta;
  const pages = meta ? Math.max(1, Math.ceil(meta.total / meta.per_page)) : 1;
  return (
    <FlatList data={catalog.result?.data || []} keyExtractor={(item) => String(item.id)}
      contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled"
      refreshing={catalog.loading} onRefresh={catalog.reload}
      ListHeaderComponent={<View>
        <Text style={styles.eyebrow}>Jelajahi wisata daerah</Text>
        <Text style={styles.title} accessibilityRole="header">Temukan tujuan berikutnya</Text>
        <Text style={styles.body}>Kenali destinasi dan rencanakan kunjungan Anda.</Text>
        <View style={styles.search}>
          <TextInput value={input} onChangeText={setInput} placeholder="Cari nama destinasi"
            accessibilityLabel="Cari nama destinasi" maxLength={100} returnKeyType="search"
            onSubmitEditing={() => updateFilter({ query: input.trim() })} style={styles.input} />
          <Button onPress={() => updateFilter({ query: input.trim() })}>Cari</Button>
        </View>
        <FilterPicker label="Wilayah" values={regions.result?.data} selected={filters.regionId}
          resource={regions} onSelect={(regionId) => updateFilter({ regionId })} />
        <FilterPicker label="Kategori" values={categories.result?.data} selected={filters.categoryId}
          resource={categories} onSelect={(categoryId) => updateFilter({ categoryId })} />
        {(filters.query || filters.regionId !== null || filters.categoryId !== null) &&
          <Button secondary onPress={() => { setInput(''); updateFilter({ query: '', regionId: null, categoryId: null }); }}>Hapus filter</Button>}
        {meta && <Text style={styles.count} accessibilityLiveRegion="polite">{meta.total} destinasi{filters.query ? ` untuk “${filters.query}”` : ''}</Text>}
      </View>}
      ListEmptyComponent={<RequestState resource={catalog}><Text style={styles.notice}>Belum ada destinasi yang cocok. Coba ubah pencarian atau filter.</Text></RequestState>}
      renderItem={({ item }) => <Pressable onPress={() => onSelect(item.slug)} accessibilityRole="button"
        accessibilityLabel={`Lihat ${item.name}`} style={({ pressed }) => [styles.card, pressed && styles.pressed]}>
        <Text style={styles.eyebrow}>{item.category?.name || 'Destinasi wisata'}</Text>
        <Text style={styles.cardTitle}>{item.name}</Text>
        <Text style={styles.location}>{item.region?.name}</Text>
        {item.summary ? <Text numberOfLines={3} style={styles.body}>{item.summary}</Text> : null}
        <Text style={styles.link}>Lihat destinasi →</Text>
      </Pressable>}
      ListFooterComponent={meta && meta.total > 0 ? <View style={styles.pagination}>
        <Button secondary disabled={meta.page <= 1} onPress={() => setFilters((current) => ({ ...current, page: meta.page - 1 }))}>Sebelumnya</Button>
        <Text style={styles.count}>Halaman {meta.page} / {pages}</Text>
        <Button secondary disabled={meta.page >= pages} onPress={() => setFilters((current) => ({ ...current, page: meta.page + 1 }))}>Berikutnya</Button>
      </View> : null} />
  );
}

function GateVoucherModal({ voucher, onClose }) {
  if (!voucher) return null;
  return (
    <Modal visible={true} animationType="slide" onRequestClose={onClose}>
      <SafeAreaView style={styles.container}>
        <ScrollView contentContainerStyle={styles.content}>
          <Button secondary onPress={onClose}>← Tutup Tiket</Button>
          <View style={styles.gateCard}>
            <View style={styles.offlineGateBadge}>
              <Text style={styles.offlineGateBadgeText}>✓ TERSIMPAN SECARA OFFLINE</Text>
            </View>
            <Text style={styles.eyebrow}>Pintu Masuk & Gerbang Wisata</Text>
            <Text style={styles.gateTitle}>{voucher.productName}</Text>
            <Text style={styles.location}>{voucher.partnerName}</Text>

            {/* QR Gate Display Simulation */}
            <View style={styles.qrFrame}>
              <View style={styles.qrBox}>
                <Text style={styles.qrSimulationHeader}>KODE CHECK-IN PETUGAS</Text>
                <Text style={styles.qrTokenText} selectable>{voucher.token}</Text>
                <Text style={styles.qrSubtext}>Perlihatkan layar ini kepada petugas gate wisata</Text>
              </View>
            </View>

            <View style={styles.gateDetails}>
              <View style={styles.gateDetailRow}>
                <Text style={styles.gateLabel}>Nama Pengunjung:</Text>
                <Text style={styles.gateValue}>{voucher.customerName}</Text>
              </View>
              <View style={styles.gateDetailRow}>
                <Text style={styles.gateLabel}>Tanggal Kunjungan:</Text>
                <Text style={styles.gateValue}>{voucher.serviceDate}</Text>
              </View>
              <View style={styles.gateDetailRow}>
                <Text style={styles.gateLabel}>Jumlah Tiket:</Text>
                <Text style={styles.gateValue}>{voucher.admissions} Peserta</Text>
              </View>
              <View style={styles.gateDetailRow}>
                <Text style={styles.gateLabel}>Status:</Text>
                <Text style={[styles.gateValue, voucher.isRedeemed ? styles.statusRedeemed : styles.statusActive]}>
                  {voucher.isRedeemed ? 'Sudah Digunakan' : 'Siap Check-in'}
                </Text>
              </View>
            </View>
          </View>
        </ScrollView>
      </SafeAreaView>
    </Modal>
  );
}

function MyVouchersScreen({ api, session, offlineVouchers, onRefreshOffline, onOpenVoucher }) {
  const [syncing, setSyncing] = useState(false);
  const [guestOrderId, setGuestOrderId] = useState('');
  const [guestToken, setGuestToken] = useState('');
  const [lookupMessage, setLookupMessage] = useState('');
  const [lookupError, setLookupError] = useState('');

  const syncOnline = useCallback(async () => {
    if (!session?.token) return;
    setSyncing(true);
    setLookupError('');
    try {
      const ordersRes = await fetchCustomerOrders(api, session.token);
      const orders = ordersRes?.data || [];
      const paidOrders = orders.filter((o) => o.status === 'paid');
      const allVouchers = [];
      for (const order of paidOrders) {
        try {
          const vRes = await fetchCustomerOrderVouchers(api, { orderId: order.public_id, token: session.token });
          const items = vRes?.data?.vouchers || [];
          for (const v of items) {
            allVouchers.push({
              ...v,
              order_id: order.public_id,
              product_name: order.items?.[0]?.name || 'Tiket Wisata Daerah',
              partner_name: order.manager?.name || 'Pengelola Destinasi',
              customer_name: session.user?.name || 'Pengunjung',
            });
          }
        } catch {
          // ignore individual order failure
        }
      }
      if (allVouchers.length > 0) {
        await saveVouchersOffline(allVouchers, appStorage);
        await onRefreshOffline();
      }
    } catch (err) {
      setLookupError('Tidak dapat terhubung untuk menyinkronkan tiket saat ini.');
    } finally {
      setSyncing(false);
    }
  }, [api, session, onRefreshOffline]);

  const handleGuestLookup = async () => {
    if (!guestOrderId.trim() || !guestToken.trim()) {
      setLookupError('ID Pesanan dan Token 48 karakter wajib diisi.');
      return;
    }
    setLookupError('');
    setLookupMessage('Mencari tiket...');
    try {
      const res = await fetchGuestVouchers(api, {
        orderId: guestOrderId.trim(),
        guestToken: guestToken.trim(),
      });
      const vouchers = res?.data?.vouchers || [];
      if (vouchers.length === 0) {
        setLookupError('Tidak ada voucher aktif untuk pesanan ini.');
        setLookupMessage('');
        return;
      }
      for (const v of vouchers) {
        await addOfflineVoucher({
          ...v,
          order_id: guestOrderId.trim(),
          product_name: 'Tiket Tamu Terverifikasi',
          customer_name: 'Tamu Wisatawan',
        }, appStorage);
      }
      await onRefreshOffline();
      setLookupMessage(`Berhasil menyimpan ${vouchers.length} voucher offline!`);
      setGuestOrderId('');
      setGuestToken('');
    } catch (err) {
      setLookupError(err.message || 'Gagal mengambil voucher. Periksa nomor pesanan dan token.');
      setLookupMessage('');
    }
  };

  const vouchers = offlineVouchers?.items || [];

  return (
    <ScrollView contentContainerStyle={styles.content}>
      <Text style={styles.eyebrow}>Dompet Tiket & Gerbang Wisata</Text>
      <Text style={styles.title} accessibilityRole="header">Tiket Saya</Text>
      <Text style={styles.body}>
        Tiket yang telah disimpan dapat dibuka di gerbang lokasi wisata bahkan tanpa jaringan internet.
      </Text>

      {session ? (
        <Button secondary disabled={syncing} onPress={syncOnline}>
          {syncing ? 'Menyinkronkan tiket…' : '🔄 Sinkronkan Tiket Terbaru'}
        </Button>
      ) : null}

      {vouchers.length > 0 ? (
        <View style={{ marginTop: 12 }}>
          <Text style={styles.count}>{vouchers.length} tiket tersimpan secara offline</Text>
          {vouchers.map((item, idx) => (
            <Pressable key={item.token || idx} onPress={() => onOpenVoucher(item)} style={styles.card}>
              <View style={styles.cardTopRow}>
                <Text style={styles.eyebrow}>{item.serviceDate}{item.isToday ? ' (HARI INI)' : ''}</Text>
                <Text style={[styles.statusBadge, item.isRedeemed ? styles.statusRedeemed : styles.statusActive]}>
                  {item.isRedeemed ? 'Sudah Digunakan' : 'Siap Check-in'}
                </Text>
              </View>
              <Text style={styles.cardTitle}>{item.productName}</Text>
              <Text style={styles.location}>{item.customerName} • {item.admissions} Orang</Text>
              <Text style={styles.tokenPreview}>Token: {item.token.slice(0, 14)}…</Text>
              <Text style={styles.link}>Buka Tampilan Check-in Gerbang →</Text>
            </Pressable>
          ))}
        </View>
      ) : (
        <View style={styles.notice}>
          <Text style={styles.body}>Belum ada tiket yang tersimpan di perangkat ini.</Text>
        </View>
      )}

      {/* Manual Guest Lookup Box */}
      <View style={[styles.card, { marginTop: 16 }]}>
        <Text style={styles.cardTitle}>Simpan Tiket Pesanan Tamu</Text>
        <Text style={styles.body}>Masukkan kode dari email atau WhatsApp untuk menyimpan tiket ke perangkat:</Text>
        <TextInput
          placeholder="ID Pesanan (contoh: ORD-202610...)"
          value={guestOrderId}
          onChangeText={setGuestOrderId}
          style={styles.input}
        />
        <TextInput
          placeholder="Token Akses Tamu (48 Karakter)"
          value={guestToken}
          onChangeText={setGuestToken}
          style={styles.input}
        />
        {lookupError ? <Text style={styles.error}>{lookupError}</Text> : null}
        {lookupMessage ? <Text style={styles.success}>{lookupMessage}</Text> : null}
        <Button onPress={handleGuestLookup}>Ambil & Simpan Tiket</Button>
      </View>
    </ScrollView>
  );
}

function AccountScreen({ api, session, onLoginSuccess, onLogout }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleLogin = async () => {
    if (!email.trim() || !password) {
      setError('Email dan kata sandi wajib diisi.');
      return;
    }
    setLoading(true);
    setError('');
    try {
      const res = await loginCustomer(api, { email, password });
      await saveAuthSession({ token: res.token, user: res.data }, appStorage);
      onLoginSuccess({ token: res.token, user: res.data });
      setEmail('');
      setPassword('');
    } catch (err) {
      setError(err.message || 'Gagal masuk akun. Periksa email dan kata sandi Anda.');
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = async () => {
    await clearAuthSession(appStorage);
    onLogout();
  };

  if (session?.user) {
    return (
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.eyebrow}>Profil Pengguna</Text>
        <Text style={styles.title} accessibilityRole="header">Akun Anda</Text>
        <View style={styles.card}>
          <Text style={styles.cardTitle}>{session.user.name}</Text>
          <Text style={styles.body}>{session.user.email}</Text>
          <Text style={styles.location}>
            Peran: {session.user.platform_role === 'super_admin' ? 'Administrator' : 'Pengguna Terdaftar'}
          </Text>
          <View style={{ marginTop: 16 }}>
            <Button danger onPress={handleLogout}>Keluar dari Akun</Button>
          </View>
        </View>
      </ScrollView>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.content}>
      <Text style={styles.eyebrow}>Portal Wisatawan</Text>
      <Text style={styles.title} accessibilityRole="header">Masuk ke Akun</Text>
      <Text style={styles.body}>Masuk untuk menyinkronkan seluruh riwayat tiket dan pesanan wisata Anda.</Text>
      <View style={styles.card}>
        <TextInput
          placeholder="Email Anda"
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
          style={styles.input}
        />
        <TextInput
          placeholder="Kata Sandi"
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          style={styles.input}
        />
        {error ? <Text style={styles.error}>{error}</Text> : null}
        <Button disabled={loading} onPress={handleLogin}>
          {loading ? 'Memeriksa kredensial…' : 'Masuk Sekarang'}
        </Button>
      </View>
    </ScrollView>
  );
}

export default function App() {
  const [tab, setTab] = useState('katalog'); // 'katalog' | 'tiket' | 'akun'
  const [slug, setSlug] = useState(null);
  const [filters, setFilters] = useState({ query: '', regionId: null, categoryId: null, page: 1 });
  const [session, setSession] = useState(null);
  const [offlineVouchers, setOfflineVouchers] = useState({ cached_at: null, items: [] });
  const [activeGateVoucher, setActiveGateVoucher] = useState(null);

  const configuration = useMemo(() => {
    try {
      return { api: createPublicApi(process.env.EXPO_PUBLIC_API_URL, { allowHttp: __DEV__ }) };
    } catch (error) {
      return { error };
    }
  }, []);

  const refreshOffline = useCallback(async () => {
    const data = await getOfflineVouchers(appStorage);
    setOfflineVouchers(data);
  }, []);

  useEffect(() => {
    getAuthSession(appStorage).then(setSession);
    refreshOffline();
  }, [refreshOffline]);

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="dark" />
      <View style={styles.header}>
        <Text style={styles.brand}>Wisata Daerah</Text>
        <View style={styles.tabBar}>
          <Pressable onPress={() => { setTab('katalog'); setSlug(null); }} style={[styles.tabItem, tab === 'katalog' && styles.tabItemActive]}>
            <Text style={[styles.tabText, tab === 'katalog' && styles.tabTextActive]}>Katalog</Text>
          </Pressable>
          <Pressable onPress={() => setTab('tiket')} style={[styles.tabItem, tab === 'tiket' && styles.tabItemActive]}>
            <Text style={[styles.tabText, tab === 'tiket' && styles.tabTextActive]}>Tiket Saya</Text>
          </Pressable>
          <Pressable onPress={() => setTab('akun')} style={[styles.tabItem, tab === 'akun' && styles.tabItemActive]}>
            <Text style={[styles.tabText, tab === 'akun' && styles.tabTextActive]}>{session ? 'Akun' : 'Masuk'}</Text>
          </Pressable>
        </View>
      </View>

      {configuration.error ? (
        <View style={styles.content}><Text style={styles.error}>{configuration.error.message}</Text></View>
      ) : tab === 'katalog' ? (
        slug ? <DestinationDetail api={configuration.api} slug={slug} onBack={() => setSlug(null)} />
          : <DestinationCatalog api={configuration.api} onSelect={setSlug} filters={filters} setFilters={setFilters} />
      ) : tab === 'tiket' ? (
        <MyVouchersScreen
          api={configuration.api}
          session={session}
          offlineVouchers={offlineVouchers}
          onRefreshOffline={refreshOffline}
          onOpenVoucher={setActiveGateVoucher}
        />
      ) : (
        <AccountScreen
          api={configuration.api}
          session={session}
          onLoginSuccess={setSession}
          onLogout={() => setSession(null)}
        />
      )}

      {activeGateVoucher && (
        <GateVoucherModal voucher={activeGateVoucher} onClose={() => setActiveGateVoucher(null)} />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f7f9fa', paddingTop: Platform.OS === 'android' ? NativeStatusBar.currentHeight : 0 },
  header: { padding: 16, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#e5e9ed', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8 },
  brand: { fontSize: 20, fontWeight: '800', color: '#0194f3' },
  tabBar: { flexDirection: 'row', gap: 6 },
  tabItem: { paddingVertical: 6, paddingHorizontal: 12, borderRadius: 16, backgroundColor: '#f0f8ff' },
  tabItemActive: { backgroundColor: '#0194f3' },
  tabText: { fontSize: 13, fontWeight: '600', color: '#0194f3' },
  tabTextActive: { color: '#ffffff' },
  content: { padding: 20, paddingBottom: 40, gap: 12 },
  eyebrow: { color: '#0194f3', fontWeight: '700', fontSize: 12, marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.8 },
  title: { fontSize: 24, fontWeight: '700', color: '#03121a', marginBottom: 8, lineHeight: 32 },
  body: { color: '#687176', fontSize: 14, lineHeight: 22, marginBottom: 8 },
  summary: { color: '#434d54', fontSize: 16, lineHeight: 24, marginBottom: 12 },
  location: { color: '#687176', fontSize: 13, marginBottom: 10 },
  search: { marginTop: 12, marginBottom: 10, gap: 8 },
  input: { minHeight: 48, backgroundColor: '#fff', borderWidth: 1, borderColor: '#d1d8dd', borderRadius: 10, paddingHorizontal: 14, color: '#03121a', fontSize: 14, marginBottom: 8 },
  button: { minHeight: 44, backgroundColor: '#0194f3', borderRadius: 10, paddingVertical: 10, paddingHorizontal: 16, alignItems: 'center', justifyContent: 'center', marginBottom: 4 },
  buttonText: { color: '#fff', fontSize: 14, fontWeight: '700' },
  secondary: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#d1d8dd' },
  secondaryText: { color: '#0194f3' },
  dangerButton: { backgroundColor: '#fee2e2', borderWidth: 1, borderColor: '#fca5a5' },
  dangerText: { color: '#dc2626' },
  disabled: { opacity: 0.4 },
  pressed: { opacity: 0.75 },
  filter: { marginBottom: 8 },
  notice: { padding: 24, alignItems: 'center', gap: 12, color: '#687176', fontSize: 14, lineHeight: 22 },
  error: { fontSize: 13, lineHeight: 18, color: '#dc2626', marginBottom: 8 },
  success: { fontSize: 13, lineHeight: 18, color: '#059669', marginBottom: 8 },
  count: { color: '#687176', fontSize: 13, marginVertical: 10 },
  card: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#e5e9ed', borderRadius: 16, padding: 18, marginBottom: 12 },
  cardTopRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  cardTitle: { color: '#03121a', fontSize: 18, fontWeight: '700', marginBottom: 6, lineHeight: 24 },
  link: { color: '#0194f3', fontWeight: '700', fontSize: 14, marginTop: 10 },
  tokenPreview: { fontFamily: Platform.OS === 'ios' ? 'Courier' : 'monospace', fontSize: 12, color: '#687176', marginTop: 4 },
  statusBadge: { fontSize: 12, fontWeight: '700', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8 },
  statusActive: { backgroundColor: '#e6f4ea', color: '#137333' },
  statusRedeemed: { backgroundColor: '#f1f5f9', color: '#64748b' },
  pagination: { marginTop: 8, alignItems: 'stretch' },

  // Gate Modal Styles
  gateCard: { backgroundColor: '#fff', borderRadius: 20, padding: 20, borderWidth: 1, borderColor: '#cbd5e1' },
  offlineGateBadge: { backgroundColor: '#d1fae5', padding: 8, borderRadius: 8, marginBottom: 12, alignItems: 'center' },
  offlineGateBadgeText: { color: '#065f46', fontSize: 12, fontWeight: '800', letterSpacing: 0.5 },
  gateTitle: { fontSize: 24, fontWeight: '800', color: '#0f172a', marginBottom: 4 },
  qrFrame: { marginVertical: 20, alignItems: 'center', justifyContent: 'center' },
  qrBox: { width: '100%', backgroundColor: '#0f172a', borderRadius: 16, padding: 20, alignItems: 'center', borderWidth: 2, borderColor: '#10b981' },
  qrSimulationHeader: { color: '#10b981', fontSize: 11, fontWeight: '800', letterSpacing: 1.5, marginBottom: 12 },
  qrTokenText: { color: '#fff', fontFamily: Platform.OS === 'ios' ? 'Courier' : 'monospace', fontSize: 14, textAlign: 'center', lineHeight: 22, backgroundColor: '#1e293b', padding: 12, borderRadius: 8, width: '100%' },
  qrSubtext: { color: '#94a3b8', fontSize: 11, marginTop: 10, textAlign: 'center' },
  gateDetails: { borderTopWidth: 1, borderTopColor: '#e2e8f0', paddingTop: 16, gap: 8 },
  gateDetailRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 },
  gateLabel: { fontSize: 14, color: '#64748b' },
  gateValue: { fontSize: 14, fontWeight: '700', color: '#1e293b' },
});
