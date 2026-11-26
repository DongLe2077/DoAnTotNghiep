<?php

namespace App\Http\Controllers;

use App\Models\RentalProperty;
use App\Services\Tenancy\PropertyContext;
use App\Services\TenantPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyMemberHomeController extends Controller
{
    public function __invoke(Request $request, PropertyContext $context, TenantPortal $portal): JsonResponse
    {
        abort_unless($context->enabled(), 404);
        $property = RentalProperty::query()->findOrFail($context->requireId());
        $member = $request->user('web');
        $linked = $member->tenant_id !== null;

        return response()->json(['data' => [
            ...$property->only(['id', 'name', 'address', 'contact_name', 'phone', 'email']),
            'has_photo' => (bool) $property->photo_path,
            'rental_status' => ! $linked ? 'unlinked' : ($portal->currentStay($member) ? 'current' : 'no_current_stay'),
        ]]);
    }
}
