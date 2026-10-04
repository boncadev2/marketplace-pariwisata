import React, { useEffect, useMemo, useState } from 'react';
import { StatusBar } from 'expo-status-bar';
import {
  ActivityIndicator, BackHandler, FlatList, Modal, Platform, Pressable,
  SafeAreaView, ScrollView, StatusBar as NativeStatusBar, StyleSheet, Text, TextInput, View,
} from 'react-native';
import { createPublicApi, destinationPath } from './src/api.mjs';
import { usePublicResource } from './src/usePublicResource';

function Button({ children, onPress, disabled = false, secondary = false, selected = false }) {
  return (
    <Pressable onPress={onPress} disabled={disabled} accessibilityRole="button"
      accessibilityState={{ disabled, selected }}
      style={({ pressed }) => [styles.button, secondary && styles.secondary, disabled && styles.disabled, pressed && styles.pressed]}>
      <Text style={[styles.buttonText, secondary && styles.secondaryText]}>{children}</Text>
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

export default function App() {
  const [slug, setSlug] = useState(null);
  const [filters, setFilters] = useState({ query: '', regionId: null, categoryId: null, page: 1 });
  const configuration = useMemo(() => {
    try { return { api: createPublicApi(process.env.EXPO_PUBLIC_API_URL, { allowHttp: __DEV__ }) }; }
    catch (error) { return { error }; }
  }, []);
  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="dark" />
      <View style={styles.header}><Text style={styles.brand}>Wisata Daerah</Text><Text style={styles.badge}>Katalog mobile</Text></View>
      {configuration.error ? <View style={styles.content}><Text style={styles.error}>{configuration.error.message}</Text></View>
        : slug ? <DestinationDetail api={configuration.api} slug={slug} onBack={() => setSlug(null)} />
          : <DestinationCatalog api={configuration.api} onSelect={setSlug} filters={filters} setFilters={setFilters} />}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f5f7f5', paddingTop: Platform.OS === 'android' ? NativeStatusBar.currentHeight : 0 },
  header: { padding: 20, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#dce5df', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8 },
  brand: { fontSize: 20, fontWeight: '800', color: '#065f46' },
  badge: { fontSize: 12, color: '#065f46', backgroundColor: '#d1fae5', padding: 8, borderRadius: 16 },
  content: { padding: 20, paddingBottom: 40, gap: 12 },
  eyebrow: { color: '#047857', fontWeight: '700', fontSize: 12, marginBottom: 8 },
  title: { fontSize: 28, fontWeight: '800', color: '#16352b', marginBottom: 10 },
  body: { color: '#40554b', fontSize: 16, lineHeight: 25, marginBottom: 8 },
  summary: { color: '#16352b', fontSize: 18, lineHeight: 28, marginBottom: 16 },
  location: { color: '#52675c', fontSize: 14, marginBottom: 12 },
  search: { marginTop: 14, marginBottom: 10, gap: 8 },
  input: { minHeight: 48, backgroundColor: '#fff', borderWidth: 1, borderColor: '#83998c', borderRadius: 12, paddingHorizontal: 14, color: '#16352b', fontSize: 16 },
  button: { minHeight: 48, backgroundColor: '#047857', borderRadius: 12, paddingVertical: 12, paddingHorizontal: 16, alignItems: 'center', justifyContent: 'center', marginBottom: 4 },
  buttonText: { color: '#fff', fontSize: 15, fontWeight: '700' },
  secondary: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#83998c' },
  secondaryText: { color: '#065f46' },
  disabled: { opacity: 0.4 },
  pressed: { opacity: 0.75 },
  filter: { marginBottom: 8 },
  notice: { padding: 24, alignItems: 'center', gap: 12, color: '#40554b', fontSize: 16, lineHeight: 25 },
  error: { fontSize: 16, lineHeight: 25, color: '#9f1239', marginBottom: 12 },
  count: { color: '#52675c', fontSize: 14, marginVertical: 12 },
  card: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#dce5df', borderRadius: 18, padding: 20, marginBottom: 12 },
  cardTitle: { color: '#16352b', fontSize: 21, fontWeight: '700', marginBottom: 8 },
  link: { color: '#047857', fontWeight: '700', fontSize: 15, marginTop: 12 },
  pagination: { marginTop: 8, alignItems: 'stretch' },
});
