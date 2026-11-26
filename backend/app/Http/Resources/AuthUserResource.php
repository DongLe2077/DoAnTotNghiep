<?php

namespace App\Http\Resources;

use App\Services\Tenancy\PropertyContext;
use App\Services\Tenancy\PropertyWorkspaces;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $context = app(PropertyContext::class);
        $extra = [];
        if ($context->enabled() && $request->user('web')?->id === $this->id && $context->id() === null) {
            $properties = app(PropertyWorkspaces::class)->visibleTo($this->resource);
            $selected = collect($properties)->firstWhere('id', (int) $request->header('X-Property-ID', $request->session()->get('property_id', 0)));
            if (! $selected && ! $request->hasHeader('X-Property-ID') && count($properties) === 1) {
                $selected = $properties[0];
                $request->session()->put('property_id', $selected['id']);
            }
            $extra = [
                'properties' => $properties,
                'property_id' => $selected['id'] ?? null,
                'needs_property' => ! $selected,
                'can_create_property' => $this->resource->canCreateRentalProperty(),
            ];
            if ($selected) {
                return $context->run($selected['id'], fn () => array_merge($this->toArray($request), $extra));
            }
        }

        return array_merge([
            'id' => $this->id,
            'username' => $this->username,
            'role' => $this->role,
            'tenant_id' => $this->tenant_id,
            'is_active' => $this->is_active,
        ], $extra);
    }
}
