<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait MaintenanceFilterRules
{
    protected function maintenanceFilters(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['submitted', 'in_progress', 'resolved'])],
            'occupancy_id' => ['sometimes', 'integer', 'min:1'],
            'room_id' => ['sometimes', 'integer', 'min:1'],
            'rental_group_id' => ['sometimes', 'integer', 'min:1'],
            'q' => ['sometimes', 'string', 'max:150'],
            'from' => ['sometimes', 'date_format:Y-m-d H:i:s', 'after_or_equal:1000-01-01 00:00:00'],
            'until' => ['sometimes', 'date_format:Y-m-d H:i:s', 'after_or_equal:1000-01-01 00:00:00', Rule::when($this->filled('from'), 'after_or_equal:from')],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
