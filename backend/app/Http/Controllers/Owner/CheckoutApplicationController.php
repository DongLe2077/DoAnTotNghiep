<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\CheckoutRequest;
use App\Http\Resources\CheckoutApplicationResource;
use App\Models\CheckoutApplication;
use App\Services\CheckoutApplications;
use App\Services\TenancyLifecycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CheckoutApplicationController extends Controller
{
    public function __construct(private readonly CheckoutApplications $applications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['status' => ['sometimes', 'in:pending,history']]);
        $query = $this->applications->listing();
        $pending = fn ($query) => $query->where('status', 'pending')
            ->whereHas('occupancy', fn ($occupancy) => $occupancy->whereNull('ended_at'));
        if (isset($filters['status'])) {
            $filters['status'] === 'pending' ? $query->where($pending) : $query->whereNot($pending);
        }

        return CheckoutApplicationResource::collection($query->latest('id')->paginate(15)->withQueryString());
    }

    public function confirm(CheckoutRequest $request, CheckoutApplication $checkoutApplication, TenancyLifecycle $lifecycle): CheckoutApplicationResource
    {
        $lifecycle->checkOut($checkoutApplication->occupancy, $request->validated(), $checkoutApplication);

        return new CheckoutApplicationResource($checkoutApplication->refresh()->load(['occupancy.room', 'requesterStay.tenant']));
    }

    public function reject(Request $request, CheckoutApplication $checkoutApplication): CheckoutApplicationResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'Lý do từ chối']);
        $application = $this->applications->reject($checkoutApplication, $data['reason']);

        return new CheckoutApplicationResource($application->load(['occupancy.room', 'requesterStay.tenant']));
    }
}
