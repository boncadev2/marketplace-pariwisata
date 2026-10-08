import Link from "next/link";
import { ShieldCheck, Lock, Eye, FileText, ArrowLeft, Mail } from "lucide-react";
import { Shell } from "../../components/Shell";
import { PageHeader } from "../../components/PageHeader";

export const metadata = {
  title: "Kebijakan Privasi",
  description:
    "Kebijakan Privasi WisataDaerah. Pelajari bagaimana kami mengumpulkan, melindungi, dan mengelola data pribadi Anda sesuai UU PDP.",
};

export default function PrivacyPolicyPage() {
  return (
    <Shell>
      <PageHeader
        eyebrow="Transparansi & Keamanan"
        title="Kebijakan Privasi"
        description="Komitmen kami dalam melindungi privasi dan keamanan data pribadi pengunjung serta mitra platform WisataDaerah."
      />

      <div style={{ maxWidth: "860px", margin: "2rem auto", lineHeight: 1.75, color: "var(--color-text-main)" }}>
        <div
          style={{
            padding: "1rem 1.25rem",
            backgroundColor: "#eff6ff",
            border: "1px solid #bfdbfe",
            borderRadius: "0.75rem",
            marginBottom: "2rem",
            display: "flex",
            alignItems: "center",
            gap: "0.75rem",
          }}
        >
          <ShieldCheck size={28} color="#2563eb" style={{ flexShrink: 0 }} />
          <p style={{ margin: 0, fontSize: "0.9rem", color: "#1e40af" }}>
            Kebijakan privasi ini disusun dengan mengacu pada Undang-Undang Republik Indonesia Nomor 27 Tahun 2022
            tentang Pelindungan Data Pribadi (UU PDP). Terakhir diperbarui: <strong>Oktober 2026</strong>.
          </p>
        </div>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            1. Informasi Umum
          </h2>
          <p>
            WisataDaerah (&quot;Platform&quot;, &quot;kami&quot;) adalah platform pariwisata daerah terintegrasi yang mempertemukan wisatawan dengan
            pengelola destinasi wisata, penginapan (homestay), penyedia paket wisata, pelaku kuliner lokal, dan UMKM daerah.
            Kami menghormati hak privasi setiap pengguna dan berkomitmen memperlakukan informasi pribadi Anda secara bertanggung jawab,
            aman, dan transparan.
          </p>
        </section>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            2. Data Pribadi yang Kami Kumpulkan
          </h2>
          <p>Kami mengumpulkan jenis data berikut saat Anda menggunakan Platform kami:</p>
          <ul style={{ paddingLeft: "1.5rem", margin: "0.5rem 0" }}>
            <li style={{ marginBottom: "0.5rem" }}>
              <strong>Informasi Akun:</strong> Nama lengkap, alamat email, nomor telepon/WhatsApp, dan kata sandi terenkripsi.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              <strong>Informasi Transaksi & Pemesanan:</strong> Rincian paket wisata, tanggal kunjungan, jumlah peserta,
              nama tamu penginapan, dan nomor referensi pesanan.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              <strong>Informasi Pengiriman UMKM:</strong> Alamat lengkap penerima, kota/kabupaten, kode pos, dan instruksi kurir.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              <strong>Data Pembayaran:</strong> Metode pembayaran yang dipilih, status transaksi, dan bukti konfirmasi dari
              payment gateway berizin Bank Indonesia. <em>Kami tidak pernah menyimpan nomor kartu debit/kredit penuh Anda.</em>
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              <strong>Data Mitra:</strong> Nama usaha, nomor rekening bank terverifikasi untuk pencairan dana (payout), identitas penanggung jawab, dan perizinan terkait.
            </li>
          </ul>
        </section>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            3. Tujuan Pemrosesan Data
          </h2>
          <p>Data pribadi Anda digunakan untuk:</p>
          <ul style={{ paddingLeft: "1.5rem", margin: "0.5rem 0" }}>
            <li style={{ marginBottom: "0.5rem" }}>Memproses pemesanan tiket wisata, kamar homestay, meja kuliner, dan produk UMKM.</li>
            <li style={{ marginBottom: "0.5rem" }}>Menerbitkan tiket elektronik (e-voucher) dengan QR code untuk verifikasi masuk lokasi wisata.</li>
            <li style={{ marginBottom: "0.5rem" }}>Memfasilitasi komunikasi pengiriman kurir antara penjual UMKM dan pembeli.</li>
            <li style={{ marginBottom: "0.5rem" }}>Penyelesaian pengembalian dana (refund) dan pencairan saldo hak mitra usaha.</li>
            <li style={{ marginBottom: "0.5rem" }}>Layanan pelanggan melalui tiket bantuan resmi platform.</li>
            <li style={{ marginBottom: "0.5rem" }}>Mencegah kecurangan, duplikasi transaksi, dan aktivitas melanggar hukum.</li>
          </ul>
        </section>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            4. Keamanan & Enkripsi Data
          </h2>
          <p>
            Kami menerapkan standar pengamanan teknis dan organisasional yang ketat, meliputi transmisi data bersertifikat SSL/TLS (HTTPS),
            enkripsi kata sandi menggunakan algoritma hash modern (Bcrypt/Argon2), pembatasan akses data internal berbasis peran (RBAC),
            serta otentikasi konfirmasi sensitif sebelum mengeksekusi tindakan finansial penting.
          </p>
        </section>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            5. Hak Anda sebagai Pemilik Data Pribadi
          </h2>
          <p>Sesuai dengan ketentuan UU PDP, Anda memiliki hak-hak berikut:</p>
          <ul style={{ paddingLeft: "1.5rem", margin: "0.5rem 0" }}>
            <li style={{ marginBottom: "0.5rem" }}>
              <strong>Hak Mengakses & Memperbarui:</strong> Anda dapat memeriksa dan memperbarui data profil akun Anda kapan saja di menu profil.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              <strong>Hak Penghapusan Data (Right to Erasure):</strong> Anda berhak mengajukan permohonan penghapusan data pribadi Anda dari sistem kami
              melalui menu pengaturan akun atau menghubungi tim dukungan privasi kami.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              <strong>Hak Menarik Persetujuan:</strong> Anda dapat menarik izin penerimaan notifikasi pemasaran kapan saja.
            </li>
          </ul>
        </section>

        <section style={{ marginBottom: "2.5rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            6. Kontak & Petugas Pelindungan Data (DPO)
          </h2>
          <p>
            Apabila Anda memiliki pertanyaan, keberatan, atau ingin menggunakan hak pelindungan data pribadi Anda,
            silakan hubungi Petugas Pelindungan Data kami melalui:
          </p>
          <div
            style={{
              padding: "1rem",
              backgroundColor: "var(--color-surface, #f9fafb)",
              border: "1px solid var(--color-border)",
              borderRadius: "0.5rem",
              marginTop: "0.5rem",
            }}
          >
            <p style={{ margin: "0 0 0.35rem 0" }}>
              <strong>Petugas Pelindungan Data (DPO) WisataDaerah</strong>
            </p>
            <p style={{ margin: "0 0 0.35rem 0", display: "flex", alignItems: "center", gap: "0.4rem" }}>
              <Mail size={16} /> Email: <code>privasi@wisatadaerah.id</code>
            </p>
            <p style={{ margin: 0 }}>
              Pusat Dukungan: Melalui halaman <Link href="/bantuan" style={{ color: "var(--color-primary)", textDecoration: "underline" }}>Pusat Bantuan</Link>
            </p>
          </div>
        </section>

        <div style={{ textAlign: "center", paddingTop: "1rem", borderTop: "1px solid var(--color-border)" }}>
          <Link href="/" className="ui-button ui-button-outline" style={{ display: "inline-flex", alignItems: "center", gap: "0.4rem" }}>
            <ArrowLeft size={16} /> Kembali ke Beranda
          </Link>
        </div>
      </div>
    </Shell>
  );
}
