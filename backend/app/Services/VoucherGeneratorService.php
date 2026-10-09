<?php

namespace App\Services;

class VoucherGeneratorService
{
    public function generateVoucher($orderId)
    {
        // Generate voucher code
        return 'VOUCHER-'.$orderId.'-'.strtoupper(uniqid());
    }
}
