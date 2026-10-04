import { Shell } from "../../components/Shell";
import { CheckoutForm } from "../../components/CheckoutForm";
import { PageHeader } from "../../components/PageHeader";
import { Check, CreditCard, UserRound } from "lucide-react";

export default async function Page({ searchParams }) {
  const params = await searchParams;
  const product =
    typeof params?.product === "string" ? params.product.slice(0, 200) : "";
  return (
    <Shell>
      <PageHeader
        eyebrow="Pemesanan perjalanan"
        title="Selangkah lebih dekat ke liburan."
        description="Lengkapi pilihan dan data pemesan. Periksa rincian harga sebelum membuat pesanan."
        compact
      />
      <ol className="checkout-steps" aria-label="Tahap pemesanan">
        <li aria-current="step">
          <span>1</span>
          <UserRound size={15} /> Detail pemesanan
        </li>
        <li>
          <span>2</span>
          <CreditCard size={15} /> Pembayaran
        </li>
        <li>
          <span>3</span>
          <Check size={15} /> Voucher perjalanan
        </li>
      </ol>
      <CheckoutForm
        initialProduct={product}
        initialDate={
          typeof params?.date === "string" &&
          /^\d{4}-\d{2}-\d{2}$/.test(params.date)
            ? params.date
            : ""
        }
        initialQuantity={
          Number(params?.quantity) >= 1 && Number(params?.quantity) <= 100
            ? Number(params.quantity)
            : 1
        }
      />
    </Shell>
  );
}
