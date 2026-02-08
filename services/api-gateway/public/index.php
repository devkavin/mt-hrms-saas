<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/bootstrap.php';

use MtHrms\Shared\AuthTokenService;
use MtHrms\Shared\HttpException;
use MtHrms\Shared\IdGenerator;
use MtHrms\Shared\JsonResponse;
use MtHrms\Shared\JsonStore;
use MtHrms\Shared\Request;
use MtHrms\Shared\Router;
use MtHrms\Shared\TenantGuard;

$request = Request::fromGlobals();
$router = new Router();
$tokens = new AuthTokenService();

$tenants = new JsonStore('gateway_tenants');
$ssoConfigs = new JsonStore('gateway_sso_configs');
$ssoStates = new JsonStore('gateway_sso_states');
$subscriptions = new JsonStore('gateway_subscriptions');

$nowIso = static fn (): string => gmdate('c');
$allowedPlans = ['starter', 'growth', 'enterprise'];
$supportedProviders = ['okta', 'azure-ad', 'google-workspace', 'saml-custom'];

$ensureTenant = static function (string $tenantId) use ($tenants, $nowIso): array {
    $tenant = $tenants->find($tenantId);
    if ($tenant !== null) {
        return $tenant;
    }

    $timestamp = $nowIso();
    $tenant = [
        'tenant_id' => $tenantId,
        'name' => strtoupper($tenantId) . ' HR',
        'slug' => $tenantId,
        'domain' => $tenantId . '.example.com',
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ];
    $tenants->set($tenantId, $tenant);

    return $tenant;
};

$router->get('/', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'api-gateway',
    'status' => 'ok',
    'version' => '2.0.0',
    'description' => 'Enterprise multi-tenant gateway with SSO login and billing orchestration.',
]));

$router->get('/api/health', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'api-gateway',
    'status' => 'ok',
    'timestamp' => gmdate('c'),
]));

$providerPayload = static function () use ($supportedProviders): array {
    return [
        'providers' => $supportedProviders,
        'default_protocol' => 'oidc',
        'sso_sign_in_required' => true,
    ];
};

$router->get('/api/auth/sso/providers', static fn (): JsonResponse => JsonResponse::make($providerPayload()));
$router->get('/api/sso/providers', static fn (): JsonResponse => JsonResponse::make($providerPayload()));

$router->post('/api/tenants', static function (Request $request) use ($allowedPlans, $tenants, $nowIso): JsonResponse {
    $name = trim((string) $request->input('name', ''));
    $slug = strtolower(trim((string) $request->input('slug', '')));
    if ($name === '' || $slug === '') {
        throw new HttpException(422, 'Both "name" and "slug" are required.');
    }

    if (preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/', $slug) !== 1) {
        throw new HttpException(422, 'Tenant slug format is invalid.');
    }

    if ($tenants->find($slug) !== null) {
        throw new HttpException(409, 'A tenant with this slug already exists.');
    }

    $plan = strtolower((string) $request->input('plan', 'starter'));
    if (!in_array($plan, $allowedPlans, true)) {
        throw new HttpException(422, 'Plan must be one of: ' . implode(', ', $allowedPlans) . '.');
    }

    $timestamp = $nowIso();
    $tenant = [
        'tenant_id' => $slug,
        'name' => $name,
        'slug' => $slug,
        'domain' => strtolower((string) $request->input('domain', $slug . '.example.com')),
        'status' => 'active',
        'plan' => $plan,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ];

    $tenants->set($slug, $tenant);

    return JsonResponse::make($tenant, 201);
});

$router->get('/api/tenants/current', static function (Request $request) use ($tenants): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $tenant = $tenants->find($tenantId);
    if ($tenant === null) {
        throw new HttpException(404, 'Tenant not found.');
    }

    return JsonResponse::make($tenant);
});

$configureSso = static function (Request $request) use (
    $ensureTenant,
    $nowIso,
    $ssoConfigs,
    $supportedProviders
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $ensureTenant($tenantId);

    $provider = strtolower((string) $request->input('provider', 'saml-custom'));
    if (!in_array($provider, $supportedProviders, true)) {
        throw new HttpException(422, 'Unsupported provider.');
    }

    $timestamp = $nowIso();
    $configuration = [
        'tenant_id' => $tenantId,
        'provider' => $provider,
        'sso_enabled' => true,
        'jit_provisioning' => (bool) $request->input('jit_provisioning', true),
        'mfa_required' => (bool) $request->input('mfa_required', true),
        'updated_at' => $timestamp,
    ];

    $existing = $ssoConfigs->find($tenantId);
    if ($existing === null) {
        $configuration['created_at'] = $timestamp;
    } else {
        $configuration['created_at'] = (string) ($existing['created_at'] ?? $timestamp);
    }

    $ssoConfigs->set($tenantId, $configuration);

    return JsonResponse::make($configuration, 201);
};

$router->post('/api/auth/sso/configure', $configureSso);
$router->post('/api/sso/configure', $configureSso);

$router->post('/api/auth/sso/start', static function (Request $request) use (
    $ensureTenant,
    $nowIso,
    $ssoConfigs,
    $ssoStates,
    $supportedProviders
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $ensureTenant($tenantId);

    $email = strtolower(trim((string) $request->input('email', '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new HttpException(422, 'A valid email is required to start SSO.');
    }

    $configuration = $ssoConfigs->find($tenantId);
    if ($configuration === null) {
        $configuration = [
            'tenant_id' => $tenantId,
            'provider' => 'saml-custom',
            'sso_enabled' => true,
            'jit_provisioning' => true,
            'mfa_required' => true,
            'created_at' => $nowIso(),
            'updated_at' => $nowIso(),
        ];
        $ssoConfigs->set($tenantId, $configuration);
    }

    if (($configuration['sso_enabled'] ?? false) !== true) {
        throw new HttpException(409, 'SSO is disabled for this tenant.');
    }

    $provider = strtolower((string) $request->input('provider', (string) $configuration['provider']));
    if (!in_array($provider, $supportedProviders, true)) {
        throw new HttpException(422, 'Unsupported provider.');
    }

    $state = IdGenerator::next('sso_state');
    $timestamp = $nowIso();
    $ssoStates->set($state, [
        'state' => $state,
        'tenant_id' => $tenantId,
        'email' => $email,
        'provider' => $provider,
        'expires_at' => time() + 600,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    return JsonResponse::make([
        'tenant_id' => $tenantId,
        'provider' => $provider,
        'state' => $state,
        'authorization_url' => 'https://sso.mock.' . $provider . '.local/authorize?state=' . urlencode($state),
        'status' => 'awaiting_callback',
    ], 201);
});

$router->post('/api/auth/sso/callback', static function (Request $request) use (
    $ensureTenant,
    $ssoStates,
    $tokens
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $tenant = $ensureTenant($tenantId);

    $state = trim((string) $request->input('state', ''));
    if ($state === '') {
        throw new HttpException(422, 'SSO callback state is required.');
    }

    $stateRecord = $ssoStates->find($state);
    if ($stateRecord === null) {
        throw new HttpException(401, 'Unknown or expired SSO state.');
    }

    if (($stateRecord['tenant_id'] ?? '') !== $tenantId) {
        throw new HttpException(403, 'SSO state does not belong to this tenant.');
    }

    if ((int) ($stateRecord['expires_at'] ?? 0) < time()) {
        $ssoStates->delete($state);
        throw new HttpException(401, 'SSO state expired. Restart login.');
    }

    $email = strtolower((string) ($stateRecord['email'] ?? ''));
    $isAdmin = str_starts_with($email, 'admin@') || str_contains($email, '.admin@');
    $role = $isAdmin ? 'admin' : 'manager';
    $userId = 'usr_' . substr(hash('sha256', $tenantId . '|' . $email), 0, 16);

    $token = $tokens->issue([
        'sub' => $userId,
        'email' => $email,
        'tenant_id' => $tenantId,
        'role' => $role,
        'name' => ucwords(str_replace(['.', '@'], [' ', ' '], explode('@', $email)[0] ?? $email)),
    ]);

    $ssoStates->delete($state);

    return JsonResponse::make([
        'tenant' => [
            'tenant_id' => $tenant['tenant_id'],
            'name' => $tenant['name'],
            'domain' => $tenant['domain'],
        ],
        'user' => [
            'id' => $userId,
            'email' => $email,
            'role' => $role,
        ],
        'token' => $token,
        'expires_in' => 28800,
    ]);
});

$router->get('/api/sso/login-url', static function (Request $request) use (
    $nowIso,
    $ssoStates,
    $supportedProviders
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $provider = strtolower((string) $request->input('provider', 'saml-custom'));
    if (!in_array($provider, $supportedProviders, true)) {
        throw new HttpException(422, 'Unsupported provider.');
    }

    $state = IdGenerator::next('sso_state');
    $timestamp = $nowIso();
    $ssoStates->set($state, [
        'state' => $state,
        'tenant_id' => $tenantId,
        'email' => strtolower((string) $request->input('email', 'user@' . $tenantId . '.com')),
        'provider' => $provider,
        'expires_at' => time() + 600,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    return JsonResponse::make([
        'tenant_id' => $tenantId,
        'login_url' => 'https://sso.mock.' . $provider . '.local/authorize?state=' . urlencode($state),
    ]);
});

$router->get('/api/auth/session', static function (Request $request) use ($tokens): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);

    return JsonResponse::make([
        'authenticated' => true,
        'tenant_id' => $tenantId,
        'user' => [
            'id' => (string) ($claims['sub'] ?? ''),
            'email' => (string) ($claims['email'] ?? ''),
            'role' => (string) ($claims['role'] ?? ''),
            'name' => (string) ($claims['name'] ?? ''),
        ],
        'expires_at' => (int) ($claims['exp'] ?? 0),
    ]);
});

$router->post('/api/billing/checkout-session', static function (Request $request) use (
    $allowedPlans,
    $nowIso,
    $subscriptions,
    $tokens
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    TenantGuard::requireRole($claims, ['admin', 'finance-admin', 'super-admin']);

    $plan = strtolower(trim((string) $request->input('plan', 'growth')));
    if (!in_array($plan, $allowedPlans, true)) {
        throw new HttpException(422, 'Plan must be one of: ' . implode(', ', $allowedPlans) . '.');
    }

    $timestamp = $nowIso();
    $subscription = $subscriptions->find($tenantId) ?? [];
    $subscription = array_merge(
        $subscription,
        [
            'tenant_id' => $tenantId,
            'provider' => 'stripe',
            'plan' => $plan,
            'status' => 'checkout_pending',
            'provider_subscription_id' => (string) ($subscription['provider_subscription_id'] ?? ''),
            'current_period_end' => gmdate('c', strtotime('+30 days')),
            'updated_at' => $timestamp,
            'created_at' => (string) ($subscription['created_at'] ?? $timestamp),
        ]
    );
    $subscriptions->set($tenantId, $subscription);

    return JsonResponse::make([
        'tenant_id' => $tenantId,
        'plan' => $plan,
        'provider' => 'stripe',
        'checkout_url' => 'https://checkout.stripe.com/pay/' . rawurlencode(IdGenerator::next('cs')),
        'status' => 'checkout_pending',
    ], 201);
});

$router->post('/api/billing/portal-session', static function (Request $request) use ($subscriptions, $tokens): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    TenantGuard::requireRole($claims, ['admin', 'finance-admin', 'super-admin']);

    $subscription = $subscriptions->find($tenantId);
    if ($subscription === null) {
        throw new HttpException(404, 'No subscription exists for this tenant yet.');
    }

    return JsonResponse::make([
        'tenant_id' => $tenantId,
        'provider' => 'stripe',
        'portal_url' => 'https://billing.stripe.com/p/session/' . rawurlencode(IdGenerator::next('bps')),
        'subscription' => $subscription,
    ]);
});

$router->get('/api/billing/subscription', static function (Request $request) use ($subscriptions, $tokens): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    $subscription = $subscriptions->find($tenantId);
    if ($subscription === null) {
        return JsonResponse::make([
            'tenant_id' => $tenantId,
            'status' => 'none',
            'plan' => 'starter',
            'provider' => 'stripe',
        ]);
    }

    return JsonResponse::make($subscription);
});

$router->dispatch($request);
