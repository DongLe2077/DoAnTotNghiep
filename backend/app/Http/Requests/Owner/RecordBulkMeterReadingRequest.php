<?php

namespace App\Http\Requests\Owner;

class RecordBulkMeterReadingRequest extends MeterReadingRequest
{
    public function rules(): array
    {
        return parent::rules() + ['billing_month' => ['required', 'date_format:Y-m', 'after_or_equal:1000-01'],
            'occupancy_id' => ['required', 'integer', 'min:1'],
            'context_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D']];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['billing_month' => 'Tháng ghi chỉ số', 'occupancy_id' => 'Đợt thuê',
            'context_token' => 'Phiên bản dữ liệu chỉ số', 'idempotency_key' => 'Mã yêu cầu ghi chỉ số'];
    }
}
