<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\BillingController;

Route::get('/health', HealthController::class);
Route::post('/billing/checkout-session', [BillingController::class, 'createCheckoutSession']);
Route::post('/billing/portal-session', [BillingController::class, 'createPortalSession']);
