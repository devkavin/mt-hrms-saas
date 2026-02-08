<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function workforceKpis(): JsonResponse
    {
        return response()->json([
            'headcount' => 152,
            'attrition_rate' => 0.08,
            'time_to_hire_days' => 19,
        ]);
    }

    public function export(): JsonResponse
    {
        return response()->json([
            'export_id' => 'rpt_' . uniqid(),
            'status' => 'queued',
            'format' => 'csv',
        ], 202);
    }
}
