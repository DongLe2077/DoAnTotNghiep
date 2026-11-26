<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckoutApplicationResource;
use App\Models\CheckoutApplication;
use App\Models\Occupancy;
use App\Services\CheckoutApplications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CheckoutApplicationController extends Controller
{
    public function __construct(private readonly CheckoutApplications $applications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return CheckoutApplicationResource::collection($this->applications->listing()
            ->where('requested_by', $request->user('web')->id)->latest('id')->paginate(15));
    }

    public function store(Request $request, Occupancy $occupancy): JsonResponse
    {
        $application = $this->applications->submit($occupancy, $request->user('web'));

        return (new CheckoutApplicationResource($application->load(['occupancy.room', 'requesterStay.tenant'])))
            ->response()->setStatusCode(201);
    }

    public function destroy(Request $request, CheckoutApplication $checkoutApplication): CheckoutApplicationResource
    {
        $application = $this->applications->withdraw($checkoutApplication, $request->user('web'));

        return new CheckoutApplicationResource($application->load(['occupancy.room', 'requesterStay.tenant']));
    }
}
