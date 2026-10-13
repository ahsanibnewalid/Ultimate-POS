<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BillingController
{
    public function status(Request $request)
    {
        $tenant = $request->user()->tenant;
        return response()->json([
            'plan' => $tenant->plan,
            'billing_status' => $tenant->billing_status ?? 'inactive',
            'subscription_ends_at' => $tenant->subscription_ends_at,
            'available_plans' => collect(config('billing.plans'))->map(fn ($plan, $key) => [
                'key' => $key,
                'name' => $plan['name'],
                'sslcommerz_monthly_bdt' => $plan['sslcommerz_monthly_bdt'],
                'stripe_monthly_minor' => $plan['stripe_monthly_minor'],
                'stripe_currency' => config('billing.currency.stripe'),
            ])->values(),
            'transactions' => DB::table('billing_transactions')
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('id')->limit(20)
                ->get(['transaction_id', 'provider', 'plan', 'amount', 'currency', 'status', 'paid_at', 'created_at']),
        ]);
    }

    public function checkout(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403, 'Only the workspace administrator can manage billing.');

        $data = $request->validate([
            'provider' => ['required', 'in:stripe,sslcommerz'],
            'plan' => ['required', 'string', 'in:' . implode(',', array_keys(config('billing.plans')))],
            'phone' => ['required_if:provider,sslcommerz', 'nullable', 'string', 'regex:/^\\+?[0-9 ()-]{7,25}$/'],
            'address' => ['required_if:provider,sslcommerz', 'nullable', 'string', 'max:200'],
            'city' => ['required_if:provider,sslcommerz', 'nullable', 'string', 'max:60'],
        ]);
        if ($data['provider'] === 'stripe' && !config('billing.stripe.secret')) {
            return response()->json(['message' => 'Stripe is not configured. Add the server-side Stripe secret key first.'], 503);
        }
        if ($data['provider'] === 'sslcommerz' && (!config('billing.sslcommerz.store_id') || !config('billing.sslcommerz.store_password'))) {
            return response()->json(['message' => 'SSLCommerz is not configured. Add merchant credentials first.'], 503);
        }
        $tenant = $request->user()->tenant;
        $plan = config('billing.plans.' . $data['plan']);
        $provider = $data['provider'];
        $transactionId = (string) Str::uuid();
        $currency = $provider === 'stripe'
            ? config('billing.currency.stripe')
            : config('billing.currency.sslcommerz');
        $amount = $provider === 'stripe'
            ? number_format(((int) $plan['stripe_monthly_minor']) / 100, 2, '.', '')
            : number_format((float) $plan['sslcommerz_monthly_bdt'], 2, '.', '');

        $transactionRow = [
            'tenant_id' => $tenant->id,
            'transaction_id' => $transactionId,
            'provider' => $provider,
            'plan' => $data['plan'],
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('billing_transactions')->insert($transactionRow);

        try {
            return $provider === 'stripe'
                ? $this->createStripeCheckout($transactionId, $tenant, $plan, $currency)
                : $this->createSslCommerzCheckout($transactionId, $tenant, $plan, $amount, (string) ($data['phone'] ?? ''), (string) ($data['address'] ?? ''), (string) ($data['city'] ?? ''));
        } catch (Throwable $exception) {
            report($exception);
            DB::table('billing_transactions')->where('transaction_id', $transactionId)->where('status', 'pending')
                ->update(['status' => 'failed', 'updated_at' => now()]);
            return response()->json(['message' => 'The payment provider could not create a checkout session. Please try again later.'], 502);
        }
    }

    private function createStripeCheckout(string $transactionId, $tenant, array $plan, string $currency)
    {
        $secret = (string) config('billing.stripe.secret');

        $frontend = config('billing.frontend_url');
        $response = Http::asForm()->withToken($secret)->acceptJson()->connectTimeout(5)->timeout(20)->post(
            config('billing.stripe.base_url') . '/checkout/sessions',
            [
                'mode' => 'subscription',
                'success_url' => $frontend . '/?billing=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontend . '/?billing=cancelled',
                'client_reference_id' => $transactionId,
                'customer_email' => $tenant->users()->where('role', 'admin')->value('email'),
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => $currency,
                'line_items[0][price_data][unit_amount]' => (int) $plan['stripe_monthly_minor'],
                'line_items[0][price_data][recurring][interval]' => 'month',
                'line_items[0][price_data][product_data][name]' => 'Ultimate POS ' . $plan['name'] . ' plan',
                'metadata[transaction_id]' => $transactionId,
                'metadata[tenant_id]' => (string) $tenant->id,
                'metadata[plan]' => $this->planKey($plan),
                'subscription_data[metadata][transaction_id]' => $transactionId,
                'subscription_data[metadata][tenant_id]' => (string) $tenant->id,
                'subscription_data[metadata][plan]' => $this->planKey($plan),
            ]
        );

        if (!$response->successful() || !is_string($response->json('url')) || !is_string($response->json('id'))) {
            throw new \RuntimeException('Stripe Checkout session creation failed: HTTP ' . $response->status());
        }

        DB::table('billing_transactions')->where('transaction_id', $transactionId)->update([
            'provider_reference' => $response->json('id'),
            'provider_metadata' => json_encode(['checkout_url_created' => true], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return response()->json(['provider' => 'stripe', 'transaction_id' => $transactionId, 'checkout_url' => $response->json('url')]);
    }

    private function planKey(array $plan): string
    {
        foreach (config('billing.plans') as $key => $configured) {
            if ($configured['name'] === $plan['name']) {
                return (string) $key;
            }
        }
        throw new \LogicException('Unknown billing plan.');
    }

    private function createSslCommerzCheckout(string $transactionId, $tenant, array $plan, string $amount, string $phone, string $address, string $city)
    {
        $storeId = (string) config('billing.sslcommerz.store_id');
        $password = (string) config('billing.sslcommerz.store_password');

        $baseUrl = config('billing.sslcommerz.sandbox')
            ? config('billing.sslcommerz.sandbox_url')
            : config('billing.sslcommerz.live_url');
        $apiUrl = rtrim($baseUrl, '/') . '/gwprocess/v4/api.php';
        $appUrl = rtrim((string) config('app.url'), '/');

        $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(25)->post($apiUrl, [
            'store_id' => $storeId,
            'store_passwd' => $password,
            'total_amount' => $amount,
            'currency' => 'BDT',
            'tran_id' => $transactionId,
            'success_url' => $appUrl . '/api/billing/sslcommerz/return',
            'fail_url' => $appUrl . '/api/billing/sslcommerz/return',
            'cancel_url' => $appUrl . '/api/billing/sslcommerz/return',
            'ipn_url' => $appUrl . '/api/billing/sslcommerz/ipn',
            'cus_name' => $tenant->name,
            'cus_email' => $tenant->users()->where('role', 'admin')->value('email') ?: 'billing@example.invalid',
            'cus_add1' => $address,
            'cus_city' => $city,
            'cus_country' => 'Bangladesh',
            'cus_phone' => preg_replace('/\\D+/', '', $phone),
            'shipping_method' => 'NO',
            'product_name' => 'Ultimate POS ' . $plan['name'] . ' plan',
            'product_category' => 'software',
            'product_profile' => 'general',
            'value_a' => (string) $tenant->id,
            'value_b' => $this->planKey($plan),
            'value_c' => $transactionId,
        ]);

        $checkoutUrl = $response->json('GatewayPageURL');
        $sessionKey = $response->json('sessionkey');
        if (!$response->successful() || !is_string($checkoutUrl) || !str_starts_with($checkoutUrl, 'https://') || !is_string($sessionKey)) {
            throw new \RuntimeException('SSLCommerz session creation failed: HTTP ' . $response->status());
        }

        DB::table('billing_transactions')->where('transaction_id', $transactionId)->update([
            'provider_reference' => $sessionKey,
            'provider_metadata' => json_encode(['gateway' => 'hosted'], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return response()->json(['provider' => 'sslcommerz', 'transaction_id' => $transactionId, 'checkout_url' => $checkoutUrl]);
    }

    public function stripeWebhook(Request $request)
    {
        $secret = (string) config('billing.stripe.webhook_secret');
        abort_if($secret === '', 503, 'Stripe webhook is not configured.');
        $rawBody = $request->getContent();
        $header = (string) $request->header('Stripe-Signature', '');
        if (!$this->validStripeSignature($rawBody, $header, $secret)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event) || !isset($event['type'], $event['data']['object'])) {
            return response()->json(['message' => 'Malformed webhook event.'], 400);
        }

        $object = $event['data']['object'];
        if (in_array($event['type'], ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $this->activateStripeCheckout($object);
        } elseif ($event['type'] === 'invoice.paid') {
            $subscriptionId = $object['subscription'] ?? null;
            $periodEnd = $object['lines']['data'][0]['period']['end'] ?? null;
            if (is_string($subscriptionId) && $subscriptionId !== '') {
                $tenant = DB::table('tenants')->where('stripe_subscription_id', $subscriptionId)->first();
                if ($tenant) {
                    DB::table('tenants')->where('id', $tenant->id)->update([
                        'billing_status' => 'active',
                        'subscription_ends_at' => is_numeric($periodEnd) ? now()->setTimestamp((int) $periodEnd) : now()->addMonth(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } elseif ($event['type'] === 'invoice.payment_failed') {
            $subscriptionId = $object['subscription'] ?? null;
            if (is_string($subscriptionId) && $subscriptionId !== '') {
                DB::table('tenants')->where('stripe_subscription_id', $subscriptionId)->update(['billing_status' => 'past_due', 'updated_at' => now()]);
            }
        } elseif ($event['type'] === 'customer.subscription.deleted') {
            $subscriptionId = $object['id'] ?? null;
            if (is_string($subscriptionId) && $subscriptionId !== '') {
                DB::table('tenants')->where('stripe_subscription_id', $subscriptionId)->update([
                    'plan' => 'free', 'billing_status' => 'canceled', 'subscription_ends_at' => null,
                    'stripe_subscription_id' => null, 'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['received' => true]);
    }

    private function activateStripeCheckout(array $session): void
    {
        if (($session['mode'] ?? null) !== 'subscription' || !in_array($session['payment_status'] ?? null, ['paid', 'no_payment_required'], true)) {
            return;
        }
        $transactionId = $session['metadata']['transaction_id'] ?? $session['client_reference_id'] ?? null;
        $tenantId = filter_var($session['metadata']['tenant_id'] ?? null, FILTER_VALIDATE_INT);
        $planKey = $session['metadata']['plan'] ?? null;
        if (!is_string($transactionId) || !$tenantId || !is_string($planKey) || !isset(config('billing.plans')[$planKey])) {
            return;
        }

        DB::transaction(function () use ($session, $transactionId, $tenantId, $planKey) {
            $transaction = DB::table('billing_transactions')->where('transaction_id', $transactionId)->lockForUpdate()->first();
            if (!$transaction || $transaction->provider !== 'stripe' || (int) $transaction->tenant_id !== (int) $tenantId
                || $transaction->plan !== $planKey || $transaction->provider_reference !== ($session['id'] ?? null)) {
                return;
            }
            $actualAmount = (int) ($session['amount_total'] ?? -1);
            $expectedAmount = (int) round(((float) $transaction->amount) * 100);
            if ($actualAmount !== $expectedAmount || strtolower((string) ($session['currency'] ?? '')) !== strtolower($transaction->currency)) {
                report(new \RuntimeException('Stripe webhook amount/currency mismatch for billing transaction ' . $transactionId));
                return;
            }
            if ($transaction->status === 'paid') {
                return;
            }

            $subscriptionId = $session['subscription'] ?? null;
            DB::table('billing_transactions')->where('id', $transaction->id)->update([
                'status' => 'paid',
                'provider_subscription_id' => is_string($subscriptionId) ? $subscriptionId : null,
                'paid_at' => now(),
                'provider_metadata' => json_encode(['checkout_session_id' => $session['id']], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            DB::table('tenants')->where('id', $tenantId)->update([
                'plan' => $planKey,
                'billing_status' => 'active',
                'stripe_subscription_id' => is_string($subscriptionId) ? $subscriptionId : null,
                'updated_at' => now(),
            ]);
        }, 3);
    }

    private function validStripeSignature(string $payload, string $header, string $secret): bool
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }
        if (!$timestamp || abs(time() - $timestamp) > (int) config('billing.stripe.signature_tolerance_seconds', 300) || !$signatures) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }

    public function sslCommerzReturn(Request $request)
    {
        // Browser redirects are never proof of payment. Only the validated IPN may activate a plan.
        return redirect()->away(rtrim((string) config('billing.frontend_url'), '/') . '/?billing=processing');
    }

    public function sslCommerzIpn(Request $request)
    {
        $data = $request->validate([
            'tran_id' => ['required', 'string', 'max:36'],
            'val_id' => ['required', 'string', 'max:80'],
        ]);
        $transaction = DB::table('billing_transactions')
            ->where('transaction_id', $data['tran_id'])
            ->where('provider', 'sslcommerz')
            ->first();
        if (!$transaction) {
            return response()->json(['message' => 'Unknown transaction.'], 404);
        }
        if ($transaction->status === 'paid') {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $storeId = (string) config('billing.sslcommerz.store_id');
        $password = (string) config('billing.sslcommerz.store_password');
        abort_if($storeId === '' || $password === '', 503, 'SSLCommerz is not configured.');
        $baseUrl = config('billing.sslcommerz.sandbox')
            ? config('billing.sslcommerz.sandbox_url')
            : config('billing.sslcommerz.live_url');

        try {
            $validation = Http::acceptJson()->connectTimeout(5)->timeout(20)->get(
                rtrim($baseUrl, '/') . '/validator/api/validationserverAPI.php',
                ['val_id' => $data['val_id'], 'store_id' => $storeId, 'store_passwd' => $password, 'format' => 'json']
            );
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Payment validation service temporarily unavailable.'], 503);
        }

        $verified = $validation->successful() ? $validation->json() : null;
        if (!is_array($verified)
            || !in_array($verified['status'] ?? null, ['VALID', 'VALIDATED'], true)
            || !hash_equals((string) $transaction->transaction_id, (string) ($verified['tran_id'] ?? ''))
            || (string) ($verified['store_id'] ?? '') !== $storeId
            || strtoupper((string) ($verified['currency'] ?? '')) !== 'BDT'
            || (int) ($verified['risk_level'] ?? 1) !== 0
            || (int) round(((float) ($verified['amount'] ?? 0)) * 100) !== (int) round(((float) $transaction->amount) * 100)) {
            DB::table('billing_transactions')->where('id', $transaction->id)->where('status', 'pending')
                ->update(['status' => 'review', 'updated_at' => now()]);
            return response()->json(['message' => 'Payment did not pass validation.'], 422);
        }

        DB::transaction(function () use ($transaction) {
            $locked = DB::table('billing_transactions')->where('id', $transaction->id)->lockForUpdate()->first();
            if (!$locked || $locked->status === 'paid') {
                return;
            }
            $tenant = DB::table('tenants')->where('id', $locked->tenant_id)->lockForUpdate()->first();
            $baseDate = $tenant && $tenant->subscription_ends_at && \Illuminate\Support\Carbon::parse($tenant->subscription_ends_at)->isFuture()
                ? \Illuminate\Support\Carbon::parse($tenant->subscription_ends_at)
                : now();
            $endsAt = $baseDate->copy()->addMonth();
            DB::table('billing_transactions')->where('id', $locked->id)->update([
                'status' => 'paid',
                'paid_at' => now(),
                'provider_metadata' => json_encode(['validated_by_provider' => true], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            DB::table('tenants')->where('id', $locked->tenant_id)->update([
                'plan' => $locked->plan,
                'billing_status' => 'active',
                'subscription_ends_at' => $endsAt,
                'updated_at' => now(),
            ]);
        }, 3);

        return response()->json(['received' => true]);
    }

    public function transactions(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403, 'Only the workspace administrator can view billing.');
        return response()->json(['data' => DB::table('billing_transactions')
            ->where('tenant_id', $request->user()->tenant_id)->orderByDesc('id')->limit(100)->get()]);
    }
}
