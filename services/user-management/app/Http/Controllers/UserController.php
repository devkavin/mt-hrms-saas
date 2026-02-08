<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function invite(Request $request): JsonResponse
    {
        return response()->json([
            'invite_id' => 'inv_' . uniqid(),
            'email' => $request->input('email'),
            'status' => 'sent',
        ], 201);
    }

    public function assignRole(Request $request, string $userId): JsonResponse
    {
        return response()->json([
            'user_id' => $userId,
            'role' => $request->input('role'),
            'status' => 'updated',
        ]);
    }
}
