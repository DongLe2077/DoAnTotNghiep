<?php

namespace App\Http\Requests\Owner;

class BulkMeterIndexRequest extends OwnerRequest
{
    public function rules(): array
    {
        return ['billing_month' => ['required', 'date_format:Y-m', 'after_or_equal:1000-01'],
            'room_id' => ['sometimes', 'integer', 'min:1'], 'occupancy_service_id' => ['sometimes', 'integer', 'min:1'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50']];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['billing_month' => 'Tháng ghi chỉ số', 'occupancy_service_id' => 'Khoảng áp dụng dịch vụ'];
    }
}
