import Link from "next/link";
import { ShieldCheck, Clock3, ArrowRight, Ticket, Info } from "lucide-react";
import { Shell } from "../../../components/Shell";
import { PageHeader } from "../../../components/PageHeader";
import { PaymentWhatsAppNotification } from "../../../components/PaymentWhatsAppNotification";

export default async function PaymentInstructionPage({ params }) {
  const { reference } = await params;
  return (
    <Shell>
      <PageHeader
        eyebrow="Pembayaran simulasi"
        title="Perjalanan Anda sedang disiapkan."
        description="Pantau pesanan dari akun Anda. Status pembayaran mengikuti verifikasi penyedia pembayaran."
        compact
      />
      <div className="payment-layout">
        <section className="payment-panel">
          <span className="payment-icon">
            <Clock3 size={34} />
          </span>
          <h2>Instruksi pembayaran sandbox</h2>
          <p>
            Ini adalah halaman simulasi. Tidak ada uang yang ditagihkan. Pesanan
            hanya berubah setelah backend menerima dan memverifikasi webhook
            sandbox.
          </p>
          <div className="payment-reference">
            <span>Referensi pembayaran</span>
            <strong>{reference}</strong>
          </div>
          <div className="payment-notice">
            <Info size={19} />
            <p>
              Membuka halaman ini bukan bukti pembayaran. Periksa status terbaru
              melalui pesanan Anda.
            </p>
          </div>
          <Link href="/akun" className="ui-button">
            Lihat pesanan saya <ArrowRight size={17} />
          </Link>
          <PaymentWhatsAppNotification reference={reference} />
        </section>
        <aside className="detail-plan">
          <span className="detail-plan-icon">
            <Ticket size={26} />
          </span>
          <h2>Selanjutnya, voucher perjalanan.</h2>
          <p>
            Voucher tersedia setelah pesanan dibayar dan diverifikasi. Anda
            dapat membuka e-voucher langsung dari akun Anda.
          </p>
          <Link href="/voucher" className="ui-button ui-button-outline">
            Buka voucher <ArrowRight size={16} />
          </Link>
          <div className="payment-trust">
            <ShieldCheck size={19} /> Verifikasi status dari sistem pembayaran
          </div>
        </aside>
      </div>
    </Shell>
  );
}
