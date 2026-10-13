<?php

namespace App\Http\Controllers;

use App\Support\PlatformPaymentSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformPaymentSettingsController
{
    public function show(Request $request, PlatformPaymentSettings $settings)
    {
        $this->authorizePlatformAdmin($request, $settings);
        $plans = $settings->plans();
        return response()->json([
            'stripe' => [
                'configured' => (bool) $settings->get('stripe_secret', config('billing.stripe.secret')),
                'webhook_configured' => (bool) $settings->get('stripe_webhook_secret', config('billing.stripe.webhook_secret')),
                'live_mode' => filter_var($settings->get('stripe_live_mode', config('billing.stripe.live_mode')), FILTER_VALIDATE_BOOLEAN),
                'currency' => strtolower((string) $settings->get('stripe_currency', config('billing.currency.stripe', 'usd'))),
            ],
            'sslcommerz' => [
                'configured' => (bool) $settings->get('sslcommerz_store_id', config('billing.sslcommerz.store_id'))
                    && (bool) $settings->get('sslcommerz_store_password', config('billing.sslcommerz.store_password')),
                'sandbox' => filter_var($settings->get('sslcommerz_sandbox', config('billing.sslcommerz.sandbox')), FILTER_VALIDATE_BOOLEAN),
            ],
            'billing_frontend_url' => (string) $settings->get('billing_frontend_url', config('billing.frontend_url')),
            'plans' => collect($plans)->map(fn ($plan, $key) => [
                'key' => $key, 'name' => $plan['name'],
                'stripe_monthly_minor' => $plan['stripe_monthly_minor'],
                'sslcommerz_monthly_bdt' => $plan['sslcommerz_monthly_bdt'],
            ])->values(),
        ]);
    }

    public function update(Request $request, PlatformPaymentSettings $settings)
    {
        $this->authorizePlatformAdmin($request, $settings);
        $data = $request->validate([
            'stripe_secret' => ['nullable', 'string', 'max:500'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:500'],
            'sslcommerz_store_id' => ['nullable', 'string', 'max:100'],
            'sslcommerz_store_password' => ['nullable', 'string', 'max:500'],
            'stripe_live_mode' => ['required', 'boolean'],
            'stripe_currency' => ['required', 'string', Rule::in(['usd', 'eur', 'gbp', 'sgd', 'myr'])],
            'sslcommerz_sandbox' => ['required', 'boolean'],
            'billing_frontend_url' => ['required', 'url', 'max:255'],
            'clear_stripe_secret' => ['sometimes', 'boolean'],
            'clear_stripe_webhook_secret' => ['sometimes', 'boolean'],
            'clear_sslcommerz_credentials' => ['sometimes', 'boolean'],
            'plans' => ['required', 'array'],
            'plans.*.key' => ['required', 'string', Rule::in(array_keys(config('billing.plans', [])))],
            'plans.*.stripe_monthly_minor' => ['required', 'integer', 'min:1', 'max:100000000'],
            'plans.*.sslcommerz_monthly_bdt' => ['required', 'numeric', 'min:10', 'max:500000'],
        ]);
        if (config('app.env') === 'production' && !str_starts_with($data['billing_frontend_url'], 'https://')) {
            return response()->json(['message' => 'The billing frontend URL must use HTTPS in production.'], 422);
        }
        $effectiveStripeSecret = trim((string) ($data['stripe_secret'] ?? ''));
        if ($effectiveStripeSecret === '') $effectiveStripeSecret = (string) $settings->get('stripe_secret', config('billing.stripe.secret', ''));
        if ($effectiveStripeSecret !== '' && (str_starts_with($effectiveStripeSecret, 'sk_live_') !== (bool) $data['stripe_live_mode'])) {
            return response()->json(['message' => 'Stripe key prefix must match test/live mode.'], 422);
        }
        foreach (['stripe_secret','stripe_webhook_secret','sslcommerz_store_id','sslcommerz_store_password'] as $key) {
            if (isset($data[$key]) && trim($data[$key]) !== '') $settings->put($key, trim($data[$key]), (int) $request->user()->id);
        }
        if (($data['clear_stripe_secret'] ?? false) === true) $settings->clear('stripe_secret', (int) $request->user()->id);
        if (($data['clear_stripe_webhook_secret'] ?? false) === true) $settings->clear('stripe_webhook_secret', (int) $request->user()->id);
        if (($data['clear_sslcommerz_credentials'] ?? false) === true) {
            $settings->clear('sslcommerz_store_id', (int) $request->user()->id);
            $settings->clear('sslcommerz_store_password', (int) $request->user()->id);
        }
        foreach ([
            'stripe_live_mode' => $data['stripe_live_mode'],
            'stripe_currency' => $data['stripe_currency'],
            'sslcommerz_sandbox' => $data['sslcommerz_sandbox'],
            'billing_frontend_url' => rtrim($data['billing_frontend_url'], '/'),
        ] as $key => $value) $settings->put($key, $value, (int) $request->user()->id);
        foreach ($data['plans'] as $plan) {
            $settings->put('plan_'.$plan['key'].'_stripe_monthly_minor', $plan['stripe_monthly_minor'], (int) $request->user()->id);
            $settings->put('plan_'.$plan['key'].'_sslcommerz_monthly_bdt', $plan['sslcommerz_monthly_bdt'], (int) $request->user()->id);
        }
        foreach (['stripe_secret','stripe_webhook_secret','sslcommerz_store_id','sslcommerz_store_password','stripe_live_mode','stripe_currency','sslcommerz_sandbox','billing_frontend_url','plan_prices'] as $key) {
            DB::table('platform_setting_audits')->insert([
                'actor_id' => $request->user()->id, 'setting_key' => $key,
                'ip_address' => substr((string) $request->ip(), 0, 45), 'created_at' => now(),
            ]);
        }
        return $this->show($request, $settings);
    }

    private function authorizePlatformAdmin(Request $request, PlatformPaymentSettings $settings): void
    {
        abort_unless($settings->isPlatformAdmin($request->user()), 403, 'Platform administrator access is required.');
    }
}
