<?php

namespace Tests\Feature;

use Tests\TestCase;

class BillingSecurityTest extends TestCase
{
    public function test_sslcommerz_browser_return_does_not_confirm_payment(): void
    {
        $response = $this->get('/api/billing/sslcommerz/return?status=VALID&tran_id=forged');

        $response->assertRedirect();
        $this->assertStringContainsString('billing=processing', $response->headers->get('Location'));
    }

    public function test_stripe_webhook_rejects_missing_signature(): void
    {
        config(['billing.stripe.webhook_secret' => 'whsec_test_secret']);

        $this->call('POST', '/api/billing/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{"type":"checkout.session.completed","data":{"object":{}}}')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid webhook signature.');
    }

    public function test_stripe_webhook_fails_closed_when_not_configured(): void
    {
        config(['billing.stripe.webhook_secret' => null]);

        $this->call('POST', '/api/billing/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}')->assertStatus(503);
    }
}
