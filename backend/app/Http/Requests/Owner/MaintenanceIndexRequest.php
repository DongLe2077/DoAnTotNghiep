<?php

namespace App\Http\Requests\Owner;

use App\Http\Requests\Concerns\MaintenanceFilterRules;

class MaintenanceIndexRequest extends OwnerRequest
{
    use MaintenanceFilterRules;

    public function rules(): array
    {
        return $this->maintenanceFilters() + ['submitted_by' => ['sometimes', 'integer', 'min:1']];
    }
}
