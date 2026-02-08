<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SsoDirectoryController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        return response()->json([
            'provider' => $request->input('provider', 'okta'),
            'synced_users' => 128,
            'status' => 'queued',
        ], 202);
    }

    public function policies(): JsonResponse
    {
        return response()->json([
            'jit_provisioning' => true,
            'mfa_required' => true,
            'session_timeout_minutes' => 30,
        ]);
    }
}
