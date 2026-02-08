<?php

namespace Tests\Feature;

use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    public function test_checkout_session_endpoint_returns_checkout_url(): void
    {
        $response = $this->withHeader('X-Tenant-ID', 'tenant_123')
            ->postJson('/api/billing/checkout-session', ['plan' => 'enterprise']);

        $response->assertCreated()
            ->assertJsonPath('provider', 'stripe')
            ->assertJsonStructure(['checkout_url', 'tenant_id', 'plan']);
    }
}
