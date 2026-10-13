<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformPaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_tenant_admin_cannot_read_global_payment_configuration(): void
    {
        $account = $this->postJson('/api/register', [
            'business_name' => 'Regular Store', 'name' => 'Regular Owner',
            'email' => 'regular@example.test', 'password' => 'Correct-Horse-Battery-4!',
            'password_confirmation' => 'Correct-Horse-Battery-4!',
        ])->assertCreated()->json();
        $this->getJson('/api/platform/payment-settings', ['Authorization' => 'Bearer '.$account['token']])->assertForbidden();
    }

    public function test_platform_admin_can_save_encrypted_credentials_without_readback(): void
    {
        config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
        config(['platform.admin_emails' => ['operator@example.test']]);
        $account = $this->postJson('/api/register', [
            'business_name' => 'Platform Store', 'name' => 'Platform Operator',
            'email' => 'operator@example.test', 'password' => 'Correct-Horse-Battery-5!',
            'password_confirmation' => 'Correct-Horse-Battery-5!',
        ])->assertCreated()->json();
        $payload = [
            'stripe_secret' => 'sk_test_example_secret', 'stripe_webhook_secret' => 'whsec_example_secret',
            'sslcommerz_store_id' => 'test-store', 'sslcommerz_store_password' => 'example-store-password',
            'stripe_live_mode' => false, 'stripe_currency' => 'usd', 'sslcommerz_sandbox' => true,
            'billing_frontend_url' => 'https://pos.example.test',
            'plans' => [
                ['key' => 'starter', 'stripe_monthly_minor' => 900, 'sslcommerz_monthly_bdt' => 990],
                ['key' => 'business', 'stripe_monthly_minor' => 2900, 'sslcommerz_monthly_bdt' => 2490],
            ],
        ];
        $response = $this->putJson('/api/platform/payment-settings', $payload, ['Authorization' => 'Bearer '.$account['token']])
            ->assertOk()->assertJsonPath('stripe.configured', true)->assertJsonPath('stripe.webhook_configured', true)
            ->assertJsonPath('sslcommerz.configured', true);
        $this->assertStringNotContainsString('sk_test_example_secret', $response->getContent());
        $stored = DB::table('platform_settings')->where('key', 'stripe_secret')->value('value');
        $this->assertNotSame('sk_test_example_secret', $stored);
        $this->assertSame('sk_test_example_secret', Crypt::decryptString($stored));
    }
}
