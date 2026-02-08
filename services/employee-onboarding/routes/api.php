<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OnboardingController;

Route::middleware('tenant')->group(function () {
    Route::get('/health', fn () => response()->json(['service' => 'employee-onboarding', 'status' => 'ok']));
    Route::post('/onboarding/workflows', [OnboardingController::class, 'create']);
    Route::post('/onboarding/workflows/{workflow}/steps/{step}/complete', [OnboardingController::class, 'completeStep']);
});
