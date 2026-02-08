<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;

Route::middleware('tenant')->group(function () {
    Route::get('/health', fn () => response()->json(['service' => 'reporting', 'status' => 'ok']));
    Route::get('/reports/workforce-kpis', [ReportController::class, 'workforceKpis']);
    Route::post('/reports/export', [ReportController::class, 'export']);
});
