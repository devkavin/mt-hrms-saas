<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        return response()->json([
            'workflow_id' => 'onb_' . uniqid(),
            'employee_id' => $request->input('employee_id'),
            'status' => 'pending_documents',
        ], 201);
    }

    public function completeStep(string $workflow, string $step): JsonResponse
    {
        return response()->json([
            'workflow_id' => $workflow,
            'step' => $step,
            'status' => 'completed',
        ]);
    }
}
