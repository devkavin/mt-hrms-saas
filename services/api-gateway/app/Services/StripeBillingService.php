<?php

namespace App\Services;

class StripeBillingService
{
    public function createCheckoutSession(?string $tenantId, string $plan): array
    {
        return [
            'tenant_id' => $tenantId,
            'plan' => $plan,
            'provider' => 'stripe',
            'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_mocked',
        ];
    }

    public function createPortalSession(?string $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'provider' => 'stripe',
            'portal_url' => 'https://billing.stripe.com/p/login/mock',
        ];
    }
}
