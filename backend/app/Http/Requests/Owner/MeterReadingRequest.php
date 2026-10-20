<?php

namespace App\Http\Requests\Owner;

class MeterReadingRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'occupancy_service_id' => ['required', 'integer', 'min:1'],
            'period_start' => ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:now', 'after_or_equal:1000-01-01 00:00:00'],
            'period_end' => ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:now', 'after:period_start'],
            'previous_reading' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/D'],
            'current_reading' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,3})?$/D'],
            'consumption' => ['prohibited'],
            'recorded_by' => ['prohibited'],
            'recorded_at' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'occupancy_service_id' => 'Khoảng áp dụng dịch vụ',
            'period_start' => 'Thời điểm đầu kỳ',
            'period_end' => 'Thời điểm cuối kỳ',
            'previous_reading' => 'Chỉ số đầu kỳ',
            'current_reading' => 'Chỉ số cuối kỳ',
            'consumption' => 'Lượng tiêu thụ',
            'recorded_by' => 'Người ghi chỉ số',
            'recorded_at' => 'Thời điểm ghi chỉ số',
        ];
    }

    public function messages(): array
    {
        return parent::messages() + ['after' => ':attribute phải sau thời điểm đầu kỳ.'];
    }
}
