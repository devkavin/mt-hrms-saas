<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PayrollController;

Route::middleware('tenant')->group(function () {
    Route::get('/health', fn () => response()->json(['service' => 'payroll', 'status' => 'ok']));
    Route::post('/payroll/runs', [PayrollController::class, 'run']);
    Route::get('/payroll/runs/{runId}', [PayrollController::class, 'show']);
});
