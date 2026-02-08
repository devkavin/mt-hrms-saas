<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        return response()->json([
            'run_id' => 'pay_' . uniqid(),
            'period' => $request->input('period'),
            'status' => 'processing',
        ], 202);
    }

    public function show(string $runId): JsonResponse
    {
        return response()->json([
            'run_id' => $runId,
            'status' => 'completed',
            'gross_total' => 185000.50,
            'net_total' => 153200.12,
        ]);
    }
}
