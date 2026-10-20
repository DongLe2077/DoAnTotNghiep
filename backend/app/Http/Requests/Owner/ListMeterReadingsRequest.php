<?php

namespace App\Http\Requests\Owner;

class ListMeterReadingsRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'occupancy_service_id' => ['sometimes', 'integer', 'min:1'],
            'billing_month' => ['sometimes', 'date_format:Y-m'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    public function messages(): array
    {
        return ['billing_month.date_format' => 'Tháng tính tiền cần có định dạng năm-tháng.'] + parent::messages();
    }
}
