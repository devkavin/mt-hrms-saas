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

$users = new JsonStore('user_management_users');
$invites = new JsonStore('user_management_invites');
$directorySyncJobs = new JsonStore('user_management_directory_sync_jobs');

$nowIso = static fn (): string => gmdate('c');
$allowedRoles = ['employee', 'manager', 'hr-admin', 'finance-admin', 'admin', 'super-admin'];
$adminRoles = ['admin', 'super-admin', 'hr-admin'];

$ensureSessionUser = static function (string $tenantId, array $claims) use ($users, $nowIso): void {
    $userId = (string) ($claims['sub'] ?? '');
    if ($userId === '') {
        return;
    }

    $existing = $users->find($userId);
    if ($existing !== null) {
        return;
    }

    $timestamp = $nowIso();
    $users->set($userId, [
        'user_id' => $userId,
        'tenant_id' => $tenantId,
        'email' => strtolower((string) ($claims['email'] ?? '')),
        'name' => (string) ($claims['name'] ?? 'SSO User'),
        'role' => strtolower((string) ($claims['role'] ?? 'manager')),
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
};

$router->get('/', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'user-management',
    'status' => 'ok',
    'version' => '2.0.0',
]));

$router->get('/api/health', static fn (): JsonResponse => JsonResponse::make([
    'service' => 'user-management',
    'status' => 'ok',
    'timestamp' => gmdate('c'),
]));

$router->get('/api/users', static function (Request $request) use ($tokens, $users, $ensureSessionUser): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    $ensureSessionUser($tenantId, $claims);

    $records = $users->list(static fn (array $record): bool => ($record['tenant_id'] ?? '') === $tenantId);

    return JsonResponse::make([
        'tenant_id' => $tenantId,
        'users' => array_values($records),
        'count' => count($records),
    ]);
});

$router->post('/api/users/invite', static function (Request $request) use (
    $adminRoles,
    $allowedRoles,
    $tokens,
    $users,
    $invites,
    $nowIso,
    $ensureSessionUser
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    TenantGuard::requireRole($claims, $adminRoles);
    $ensureSessionUser($tenantId, $claims);

    $email = strtolower(trim((string) $request->input('email', '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new HttpException(422, 'A valid invite email is required.');
    }

    $role = strtolower(trim((string) $request->input('role', 'employee')));
    if (!in_array($role, $allowedRoles, true)) {
        throw new HttpException(422, 'Invalid role for invite.');
    }

    $timestamp = $nowIso();
    $userId = 'usr_' . substr(hash('sha256', $tenantId . '|' . $email), 0, 16);
    $existing = $users->find($userId);
    $users->set($userId, [
        'user_id' => $userId,
        'tenant_id' => $tenantId,
        'email' => $email,
        'name' => trim((string) $request->input('name', 'Pending User')),
        'role' => $role,
        'status' => 'invited',
        'invited_by' => (string) ($claims['sub'] ?? ''),
        'created_at' => (string) ($existing['created_at'] ?? $timestamp),
        'updated_at' => $timestamp,
    ]);

    $inviteId = IdGenerator::next('inv');
    $invite = [
        'invite_id' => $inviteId,
        'tenant_id' => $tenantId,
        'user_id' => $userId,
        'email' => $email,
        'role' => $role,
        'status' => 'sent',
        'expires_at' => gmdate('c', strtotime('+7 days')),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ];
    $invites->set($inviteId, $invite);

    return JsonResponse::make($invite, 201);
});

$router->patch('/api/users/{userId}/roles', static function (Request $request, array $params) use (
    $adminRoles,
    $allowedRoles,
    $tokens,
    $users,
    $nowIso,
    $ensureSessionUser
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    TenantGuard::requireRole($claims, $adminRoles);
    $ensureSessionUser($tenantId, $claims);

    $userId = (string) ($params['userId'] ?? '');
    if ($userId === '') {
        throw new HttpException(422, 'Target user id is required.');
    }

    $target = $users->find($userId);
    if ($target === null || ($target['tenant_id'] ?? '') !== $tenantId) {
        throw new HttpException(404, 'User not found in this tenant.');
    }

    $role = strtolower(trim((string) $request->input('role', '')));
    if (!in_array($role, $allowedRoles, true)) {
        throw new HttpException(422, 'Invalid role.');
    }

    $updated = array_merge($target, [
        'role' => $role,
        'status' => (string) ($target['status'] ?? 'active'),
        'updated_by' => (string) ($claims['sub'] ?? ''),
        'updated_at' => $nowIso(),
    ]);
    $users->set($userId, $updated);

    return JsonResponse::make([
        'user_id' => $userId,
        'tenant_id' => $tenantId,
        'role' => $role,
        'status' => 'updated',
    ]);
});

$router->post('/api/sso/directory-sync', static function (Request $request) use (
    $adminRoles,
    $tokens,
    $directorySyncJobs,
    $users,
    $nowIso,
    $ensureSessionUser
): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    $claims = TenantGuard::requireUser($request, $tokens, $tenantId);
    TenantGuard::requireRole($claims, $adminRoles);
    $ensureSessionUser($tenantId, $claims);

    $provider = strtolower(trim((string) $request->input('provider', 'okta')));
    $timestamp = $nowIso();
    $jobId = IdGenerator::next('sync');

    $sampleUsers = [
        ['email' => 'finance.lead@' . $tenantId . '.com', 'role' => 'finance-admin'],
        ['email' => 'hr.manager@' . $tenantId . '.com', 'role' => 'hr-admin'],
        ['email' => 'team.lead@' . $tenantId . '.com', 'role' => 'manager'],
    ];

    foreach ($sampleUsers as $sample) {
        $email = strtolower($sample['email']);
        $userId = 'usr_' . substr(hash('sha256', $tenantId . '|' . $email), 0, 16);
        $existing = $users->find($userId);
        $users->set($userId, [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'email' => $email,
            'name' => ucwords(str_replace(['.', '@'], [' ', ' '], explode('@', $email)[0] ?? $email)),
            'role' => $sample['role'],
            'status' => 'active',
            'created_at' => (string) ($existing['created_at'] ?? $timestamp),
            'updated_at' => $timestamp,
        ]);
    }

    $job = [
        'job_id' => $jobId,
        'tenant_id' => $tenantId,
        'provider' => $provider,
        'status' => 'queued',
        'synced_users' => count($sampleUsers),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ];
    $directorySyncJobs->set($jobId, $job);

    return JsonResponse::make($job, 202);
});

$router->get('/api/sso/policies', static function (Request $request) use ($tokens): JsonResponse {
    $tenantId = TenantGuard::requireTenant($request);
    TenantGuard::requireUser($request, $tokens, $tenantId);

    return JsonResponse::make([
        'tenant_id' => $tenantId,
        'jit_provisioning' => true,
        'mfa_required' => true,
        'session_timeout_minutes' => 30,
        'passwordless_sso_only' => true,
    ]);
});

$router->dispatch($request);
