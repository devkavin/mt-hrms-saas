<?php

namespace Tests\Feature;

use Tests\TestCase;

class SsoControllerTest extends TestCase
{
    public function test_sso_providers_are_available(): void
    {
        $response = $this->getJson('/api/sso/providers');

        $response->assertOk()->assertJsonStructure(['providers', 'default_protocol']);
    }

    public function test_tenant_can_configure_sso(): void
    {
        $response = $this->withHeader('X-Tenant-ID', 'tenant_enterprise')
            ->postJson('/api/sso/configure', ['provider' => 'okta']);

        $response->assertCreated()->assertJsonPath('sso_enabled', true);
    }
}
