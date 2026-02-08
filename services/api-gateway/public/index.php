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
$tenantAccounts = new JsonStore('gateway_tenant_accounts');
$ssoConfigs = new JsonStore('gateway_sso_configs');
$ssoStates = new JsonStore('gateway_sso_states');
$subscriptions = new JsonStore('gateway_subscriptions');

$nowIso = static fn (): string => gmdate('c');
$allowedPlans = ['starter', 'growth', 'enterprise'];
$supportedProviders = ['okta', 'azure-ad', 'google-workspace', 'saml-custom'];

$requireTenant = static function (string $tenantId) use ($tenants): array {
    $tenant = $tenants->find($tenantId);
    if ($tenant === null) {
        throw new HttpException(404, 'Tenant not found. Please register the tenant first.');
    }

    if (($tenant['status'] ?? 'active') !== 'active') {
        throw new HttpException(403, 'Tenant is not active.');
    }

    return $tenant;
};

$requireTenantAccount = static function (string $tenantId) use ($tenantAccounts): array {
    $record = $tenantAccounts->find($tenantId);
    if ($record === null) {
        throw new HttpException(404, 'Tenant account not found. Please register first.');
    }

    return $record;
};

$router->get('/', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'api-gateway',
    'status' => 'ok',
    'version' => '2.1.0',
    'description' => 'Enterprise multi-tenant gateway with tenant registration, SSO login and billing orchestration.',
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

$router->post('/api/tenants/register', static function (Request $request) use ($allowedPlans, $tenants, $tenantAccounts, $nowIso): JsonResponse {
    $name = trim((string) $request->input('name', ''));
    $slug = strtolower(trim((string) $request->input('slug', '')));
    $adminEmail = strtolower(trim((string) $request->input('admin_email', '')));
    $password = (string) $request->input('password', '');

    if ($name === '' || $slug === '' || $adminEmail === '' || $password === '') {
        throw new HttpException(422, 'name, slug, admin_email and password are required.');
    }

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        throw new HttpException(422, 'admin_email must be a valid email.');
    }

    if (strlen($password) < 8) {
        throw new HttpException(422, 'password must be at least 8 characters.');
    }

    if (preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/', $slug) !== 1) {
        throw new HttpException(422, 'Tenant slug format is invalid.');
    }

    if ($tenants->find($slug) !== null || $tenantAccounts->find($slug) !== null) {
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

    $account = [
        'tenant_id' => $slug,
        'admin_email' => $adminEmail,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ];

    $tenants->set($slug, $tenant);
    $tenantAccounts->set($slug, $account);

    return JsonResponse::make([
        'tenant' => $tenant,
        'registered' => true,
    ], 201);
});

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

$router->post('/api/auth/password/verify', static function (Request $request) use ($requireTenant, $requireTenantAccount): JsonResponse {
    $tenantId = strtolower(trim((string) $request->input('tenant_id', $request->header('X-Tenant-ID', ''))));
    $password = (string) $request->input('password', '');
    if ($tenantId === '' || $password === '') {
        throw new HttpException(422, 'tenant_id and password are required.');
    }

    $tenant = $requireTenant($tenantId);
    $account = $requireTenantAccount($tenantId);

    if (!password_verify($password, (string) ($account['password_hash'] ?? ''))) {
        throw new HttpException(401, 'Invalid tenant credentials.');
    }

    return JsonResponse::make([
        'tenant_id' => $tenantId,
        'tenant_name' => $tenant['name'] ?? $tenantId,
        'authenticated' => true,
    ]);
});

$router->get('/api/tenants/current', static function (Request $request) use ($requireTenant): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);

    return JsonResponse::make($requireTenant($tenantId));
});

$configureSso = static function (Request $request) use (
    $requireTenant,
    $nowIso,
    $ssoConfigs,
    $supportedProviders
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $requireTenant($tenantId);

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
    $requireTenant,
    $nowIso,
    $ssoConfigs,
    $ssoStates,
    $supportedProviders
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $requireTenant($tenantId);

    $email = strtolower(trim((string) $request->input('email', '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new HttpException(422, 'A valid email is required to start SSO.');
    }

    $configuration = $ssoConfigs->find($tenantId);
    if ($configuration === null) {
        throw new HttpException(409, 'SSO is not configured for this tenant.');
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
    $requireTenant,
    $ssoStates,
    $tokens
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $tenant = $requireTenant($tenantId);

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

$router->get('/api/views/overview', static function (Request $request) use ($tokens, $tenants, $subscriptions): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    return JsonResponse::make([
        'service' => 'api-gateway',
        'tenant' => $tenants->find($tenantId),
        'subscription' => $subscriptions->find($tenantId) ?? ['status' => 'none', 'plan' => 'starter'],
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
