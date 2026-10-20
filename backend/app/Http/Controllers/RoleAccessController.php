<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleAccessController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Bạn có quyền truy cập khu vực này.',
            'data' => ['role' => $request->user('web')->role],
        ]);
    }
}
