<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::middleware('tenant')->group(function () {
    Route::get('/health', fn () => response()->json(['service' => 'user-management', 'status' => 'ok']));
    Route::post('/users/invite', [UserController::class, 'invite']);
    Route::patch('/users/{userId}/roles', [UserController::class, 'assignRole']);
});
