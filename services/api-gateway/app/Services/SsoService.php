<?php

namespace App\Services;

class SsoService
{
    public function listProviders(): array
    {
        return [
            'providers' => ['okta', 'azure-ad', 'google-workspace', 'saml-custom'],
            'default_protocol' => 'saml2',
        ];
    }

    public function configure(string $tenantId, array $payload): array
    {
        return [
            'tenant_id' => $tenantId,
            'provider' => $payload['provider'] ?? 'saml-custom',
            'status' => 'configured',
            'sso_enabled' => true,
        ];
    }

    public function loginUrl(string $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'login_url' => 'https://sso.mock.pulsehrms.com/init?tenant=' . $tenantId,
        ];
    }
}
