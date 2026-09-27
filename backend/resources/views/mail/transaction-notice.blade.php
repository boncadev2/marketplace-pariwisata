<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title }}</title></head>
<body style="margin:0;background:#f3f6f1;color:#16221b;font-family:Arial,sans-serif;padding:24px">
<main style="max-width:600px;margin:auto;background:white;border:1px solid #dce4dc;border-radius:12px;padding:28px">
    <p style="color:#19583e;font-weight:bold">Wisata Daerah</p>
    <h1 style="font-size:26px">{{ $title }}</h1>
    <p>Halo {{ $snapshot['name'] }},</p>
    <p style="line-height:1.6">{{ $description }}</p>
    <p>Nomor pesanan: <strong>{{ $snapshot['order_id'] }}</strong></p>
    <p style="line-height:1.6;white-space:pre-line">{{ $snapshot['detail'] }}</p>
    <hr style="border:0;border-top:1px solid #dce4dc">
    <p style="font-size:13px;color:#52635a">Pesan transaksi ini tidak meminta kata sandi, OTP, atau pembayaran di luar aplikasi.</p>
</main>
</body>
</html>
