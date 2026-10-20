<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RestoreBillingRepresentativeRequest;
use App\Http\Resources\RepresentativeResource;
use App\Models\Occupancy;
use App\Services\BillingRepresentative;
use Illuminate\Http\JsonResponse;

class BillingRepresentativeController extends Controller
{
    public function __invoke(RestoreBillingRepresentativeRequest $request, Occupancy $occupancy, BillingRepresentative $service): JsonResponse
    {
        $representative = $service->restore($occupancy, $request->integer('tenant_stay_id'));

        return response()->json([
            'message' => 'Đã bổ sung đại diện theo lịch sử cư trú để lập hóa đơn cho đợt thuê đã đóng.',
            'data' => new RepresentativeResource($representative->load('tenantStay.tenant')),
        ], 201);
    }
}
