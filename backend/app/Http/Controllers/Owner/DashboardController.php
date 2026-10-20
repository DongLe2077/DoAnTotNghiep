<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\DashboardRequest;
use App\Services\OwnerDashboard;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardRequest $request, OwnerDashboard $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->summary($request->validated('month'))]);
    }
}
