<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\SsoController;

Route::get('/health', HealthController::class);
Route::post('/billing/checkout-session', [BillingController::class, 'createCheckoutSession']);
Route::post('/billing/portal-session', [BillingController::class, 'createPortalSession']);

Route::get('/sso/providers', [SsoController::class, 'providers']);
Route::post('/sso/configure', [SsoController::class, 'configure']);
Route::get('/sso/login-url', [SsoController::class, 'loginUrl']);
