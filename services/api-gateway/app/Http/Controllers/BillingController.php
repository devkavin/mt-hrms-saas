<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\StripeBillingService;

class BillingController extends Controller
{
    public function __construct(private readonly StripeBillingService $stripeBillingService)
    {
    }

    public function createCheckoutSession(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');
        $plan = $request->input('plan', 'growth');

        $session = $this->stripeBillingService->createCheckoutSession($tenantId, $plan);

        return response()->json($session, 201);
    }

    public function createPortalSession(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');

        $session = $this->stripeBillingService->createPortalSession($tenantId);

        return response()->json($session);
    }
}
