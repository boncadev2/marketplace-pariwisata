<?php

return [
    'checkout_enabled_default' => (bool) env('PILOT_CHECKOUT_ENABLED', env('APP_ENV') !== 'production'),
    'demo_password' => env('PILOT_DEMO_PASSWORD'),
];
