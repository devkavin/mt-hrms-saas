<?php

namespace App\Http\Controllers;

use App\Services\SsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SsoController extends Controller
{
    public function __construct(private readonly SsoService $ssoService)
    {
    }

    public function providers(): JsonResponse
    {
        return response()->json($this->ssoService->listProviders());
    }

    public function configure(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID', 'tenant_demo');

        return response()->json($this->ssoService->configure($tenantId, $request->all()), 201);
    }

    public function loginUrl(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID', 'tenant_demo');

        return response()->json($this->ssoService->loginUrl($tenantId));
    }
}
