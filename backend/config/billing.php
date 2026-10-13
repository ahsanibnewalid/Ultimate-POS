<?php

return [
    'frontend_url' => rtrim((string) env('BILLING_FRONTEND_URL', strtok((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), ',')), '/'),
    'currency' => [
        'stripe' => strtolower((string) env('STRIPE_CURRENCY', 'usd')),
        'sslcommerz' => 'BDT',
    ],
    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'live_mode' => (bool) env('STRIPE_LIVE_MODE', false),
        'base_url' => 'https://api.stripe.com/v1',
        'signature_tolerance_seconds' => 300,
    ],
    'sslcommerz' => [
        'store_id' => env('SSLCOMMERZ_STORE_ID'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
        'sandbox' => (bool) env('SSLCOMMERZ_SANDBOX', true),
        'sandbox_url' => 'https://sandbox.sslcommerz.com',
        'live_url' => 'https://securepay.sslcommerz.com',
    ],
    // Prices are server-side only. Never accept amount/currency from the browser.
    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'stripe_monthly_minor' => 900,
            'sslcommerz_monthly_bdt' => 990.00,
        ],
        'business' => [
            'name' => 'Business',
            'stripe_monthly_minor' => 2900,
            'sslcommerz_monthly_bdt' => 2490.00,
        ],
    ],
];
