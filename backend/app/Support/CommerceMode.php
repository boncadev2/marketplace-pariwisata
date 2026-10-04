<?php

namespace App\Support;

use App\Payments\MidtransProductionGateway;

class CommerceMode
{
    public static function enabled(): bool
    {
        return app()->environment(['local', 'testing']) || (config('services.commerce.production_enabled')
            && config('services.payment_gateway.driver') === 'midtrans_production' && app(MidtransProductionGateway::class)->isConfigured());
    }
}
