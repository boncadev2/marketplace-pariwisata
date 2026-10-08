import Link from "next/link";
import { FileText, CheckCircle2, AlertCircle, ArrowLeft, Scale } from "lucide-react";
import { Shell } from "../../components/Shell";
import { PageHeader } from "../../components/PageHeader";

export const metadata = {
  title: "Syarat & Ketentuan",
  description:
    "Syarat dan Ketentuan penggunaan marketplace pariwisata daerah WisataDaerah. Panduan hak dan kewajiban pengguna dan mitra usaha.",
};

export default function TermsConditionsPage() {
  return (
    <Shell>
      <PageHeader
        eyebrow="Pedoman & Regulasi"
        title="Syarat & Ketentuan Layanan"
        description="Harap membaca syarat dan ketentuan ini dengan saksama sebelum menggunakan layanan di platform WisataDaerah."
      />

      <div style={{ maxWidth: "860px", margin: "2rem auto", lineHeight: 1.75, color: "var(--color-text-main)" }}>
        <div
          style={{
            padding: "1rem 1.25rem",
            backgroundColor: "#f0fdf4",
            border: "1px solid #bbf7d0",
            borderRadius: "0.75rem",
            marginBottom: "2rem",
            display: "flex",
            alignItems: "center",
            gap: "0.75rem",
          }}
        >
          <Scale size={28} color="#16a34a" style={{ flexShrink: 0 }} />
          <p style={{ margin: 0, fontSize: "0.9rem", color: "#166534" }}>
            Dengan mengakses, mendaftar, atau melakukan transaksi di platform WisataDaerah, Anda menyetujui untuk terikat
            pada seluruh Syarat & Ketentuan ini. Terakhir diperbarui: <strong>Oktober 2026</strong>.
          </p>
        </div>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            1. Definisi & Ketentuan Umum
          </h2>
          <p>
            <strong>WisataDaerah</strong> adalah platform marketplace yang memfasilitasi transaksi antara Pengguna (wisatawan)
            dan Mitra Pengelola (BUMDes, operator tur, pemilik homestay, pengelola rumah makan, dan produsen UMKM lokal).
            Kecuali dinyatakan lain secara eksplisit, penyedia layanan lapangan adalah masing-masing Mitra yang terdaftar.
          </p>
        </section>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            2. Akun Pengguna & Keamanan
          </h2>
          <ul style={{ paddingLeft: "1.5rem", margin: "0.5rem 0" }}>
            <li style={{ marginBottom: "0.5rem" }}>
              Pengguna wajib memberikan data yang valid, akurat, dan dapat dihubungi (nama lengkap, nomor telepon, dan email).
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              Pengguna bertanggung jawab penuh atas kerahasiaan kata sandi akun dan seluruh aktivitas yang dilakukan di bawah akun tersebut.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              WisataDaerah berhak membekukan atau menutup akun yang terindikasi melakukan penyalahgunaan kupon promo, penipuan,
              atau pelanggaran keamanan sistem.
            </li>
          </ul>
        </section>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            3. Pemesanan, Harga & Pembayaran
          </h2>
          <ul style={{ paddingLeft: "1.5rem", margin: "0.5rem 0" }}>
            <li style={{ marginBottom: "0.5rem" }}>
              Semua harga yang ditampilkan di platform dalam mata uang Rupiah (IDR) dan mencakup rincian biaya yang tertera pada saat checkout.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              Setiap pemesanan memiliki batas waktu pembayaran (expiry time). Apabila pembayaran tidak diselesaikan sebelum batas waktu habis,
              pesanan akan kedaluwarsa secara otomatis dan kuota/stok akan dikembalikan ke sistem.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              Pembayaran sah hanya yang dilakukan melalui kanal resmi payment gateway platform (Virtual Account, QRIS, E-Wallet, atau Kartu).
            </li>
          </ul>
        </section>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            4. Validasi Tiket Elektronik & Voucher
          </h2>
          <p>
            Setelah pembayaran terverifikasi, sistem akan menerbitkan bukti pemesanan atau e-voucher dengan kode QR unik.
            Saat tiba di lokasi destinasi wisata atau penginapan, Pengguna wajib menunjukkan kode QR tersebut kepada petugas
            lapangan untuk divalidasi. Setiap voucher hanya dapat divalidasi satu kali sesuai kuota yang dipesan.
          </p>
        </section>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            5. Kebijakan Pembatalan & Pengembalian Dana (Refund)
          </h2>
          <p>
            Pengguna dapat mengajukan pembatalan dan permohonan pengembalian dana melalui menu riwayat pesanan dengan ketentuan:
          </p>
          <ul style={{ paddingLeft: "1.5rem", margin: "0.5rem 0" }}>
            <li style={{ marginBottom: "0.5rem" }}>
              Pengajuan refund harus diajukan sebelum batas waktu toleransi pembatalan yang ditentukan pada masing-masing kebijakan produk/layanan.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              Tiket atau voucher yang sudah divalidasi atau digunakan di lokasi tidak dapat dibatalkan atau di-refund.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              Pengembalian dana yang disetujui akan diproses kembali ke metode pembayaran awal atau rekening bank yang diverifikasi
              dalam kurun waktu 3 hingga 14 hari kerja bank.
            </li>
          </ul>
        </section>

        <section style={{ marginBottom: "2rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            6. Ketentuan Belanja Produk UMKM & Pengiriman
          </h2>
          <ul style={{ paddingLeft: "1.5rem", margin: "0.5rem 0" }}>
            <li style={{ marginBottom: "0.5rem" }}>
              Penjual mitra UMKM berkewajiban mengirimkan produk pesanan sesuai dengan deskripsi, foto, dan batas waktu pengiriman.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              Nomor resi pengiriman kurir akan dicatat di platform sehingga pembeli dapat melacak status pengiriman secara berkala.
            </li>
            <li style={{ marginBottom: "0.5rem" }}>
              Klaim kerusakan atau barang tidak sesuai wajib dilaporkan maksimal 2x24 jam sejak paket berstatus terkirim disertai foto/video unboxing.
            </li>
          </ul>
        </section>

        <section style={{ marginBottom: "2.5rem" }}>
          <h2 style={{ fontSize: "1.35rem", fontWeight: 700, marginBottom: "0.75rem", color: "var(--color-text-main)" }}>
            7. Hukum yang Berlaku & Penyelesaian Sengketa
          </h2>
          <p>
            Syarat & Ketentuan ini diatur dan ditafsirkan sesuai dengan hukum Republik Indonesia. Segala perselisihan yang timbul
            antara Pengguna, Mitra, dan WisataDaerah akan diupayakan diselesaikan terlebih dahulu melalui musyawarah untuk mufakat
            melalui mediasi Pusat Bantuan Layanan Pelanggan kami.
          </p>
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
