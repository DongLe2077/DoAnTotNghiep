<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\MaintenanceFilterRules;

class MaintenanceIndexRequest extends TenantRequest
{
    use MaintenanceFilterRules;

    public function rules(): array
    {
        return $this->maintenanceFilters();
    }
}
