<?php

declare(strict_types=1);

namespace MtHrms\Shared;

class TenantGuard
{
    public static function requireTenant(Request $request): string
    {
        $tenantId = trim((string) $request->header('x-tenant-id', ''));
        if ($tenantId === '') {
            throw new HttpException(422, 'X-Tenant-ID header is required.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', strtolower($tenantId)) !== 1) {
            throw new HttpException(422, 'X-Tenant-ID format is invalid.');
        }

        return strtolower($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    public static function requireUser(
        Request $request,
        AuthTokenService $tokens,
        ?string $expectedTenantId = null
    ): array {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            throw new HttpException(401, 'Authorization bearer token is required.');
        }

        $claims = $tokens->verify($token);
        $tokenTenant = strtolower((string) ($claims['tenant_id'] ?? ''));
        if ($tokenTenant === '') {
            throw new HttpException(401, 'Bearer token has no tenant context.');
        }

        if ($expectedTenantId !== null && $tokenTenant !== strtolower($expectedTenantId)) {
            throw new HttpException(403, 'Token tenant does not match X-Tenant-ID.');
        }

        return $claims;
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<int, string> $allowedRoles
     */
    public static function requireRole(array $claims, array $allowedRoles): void
    {
        $role = strtolower((string) ($claims['role'] ?? ''));
        if (!in_array($role, array_map('strtolower', $allowedRoles), true)) {
            throw new HttpException(403, 'Insufficient permissions for this action.');
        }
    }
}
