<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StayIndexRequest;
use App\Http\Resources\AuthUserResource;
use App\Http\Resources\MyStayResource;
use App\Services\TenantPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfileController extends Controller
{
    public function __construct(private readonly TenantPortal $portal) {}

    public function show(Request $request): JsonResponse
    {
        $actor = $request->user('web');
        $tenant = $this->portal->profile($actor);

        return response()->json(['data' => [
            ...$tenant->only(['id', 'full_name', 'phone_number', 'identity_number']),
            'account' => new AuthUserResource($actor),
        ]]);
    }

    public function currentStay(Request $request): JsonResponse
    {
        $stay = $this->portal->currentStay($request->user('web'));

        return response()->json(['data' => $stay === null ? null : new MyStayResource($stay)]);
    }

    public function stays(StayIndexRequest $request): AnonymousResourceCollection
    {
        return MyStayResource::collection($this->portal->stays($request->user('web'))
            ->orderByDesc('moved_in_at')->orderByDesc('id')
            ->paginate($request->validated('per_page', 15))->withQueryString());
    }
}
