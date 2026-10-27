<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class ProcessMaintenanceRequest extends OwnerRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['in_progress', 'resolved'])],
            'resolution' => ['required_if:status,resolved', 'nullable', 'string', 'max:8000'],
            'version_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'occupancy_id' => ['prohibited'], 'submitted_by' => ['prohibited'],
            'representative_assignment_id' => ['prohibited'], 'title' => ['prohibited'],
            'description' => ['prohibited'], 'submitted_at' => ['prohibited'], 'updated_at' => ['prohibited'],
            'performed_by' => ['prohibited'], 'actor_username' => ['prohibited'], 'actor_role' => ['prohibited'],
            'created_at' => ['prohibited'], 'histories' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['status' => 'Trạng thái xử lý', 'resolution' => 'Nội dung xử lý', 'version_token' => 'Phiên bản yêu cầu'];
    }

    public function messages(): array
    {
        return ['resolution.required_if' => 'Cần ghi rõ kết quả khi hoàn tất sửa chữa.',
            'version_token.regex' => 'Phiên bản yêu cầu không hợp lệ.'] + parent::messages();
    }
}
