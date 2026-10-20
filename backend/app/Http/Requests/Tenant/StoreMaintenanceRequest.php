<?php

namespace App\Http\Requests\Tenant;

class StoreMaintenanceRequest extends TenantRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:8000'],
            'occupancy_id' => ['prohibited'], 'submitted_by' => ['prohibited'],
            'representative_assignment_id' => ['prohibited'], 'status' => ['prohibited'],
            'resolution' => ['prohibited'], 'submitted_at' => ['prohibited'], 'updated_at' => ['prohibited'],
        ];
    }
}
