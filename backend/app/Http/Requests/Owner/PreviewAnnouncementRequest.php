<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class PreviewAnnouncementRequest extends OwnerRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['title', 'body'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim(str_replace(["\r\n", "\r"], "\n", $this->input($key)))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:8000'],
            'audience' => ['required', Rule::in(['all', 'rooms'])],
            'room_ids' => ['required_if:audience,rooms', 'prohibited_if:audience,all', 'array', 'min:1', 'max:100'],
            'room_ids.*' => ['integer', 'min:1', 'distinct', 'exists:rooms,id'],
            'recipient_user_ids' => ['prohibited'], 'created_by' => ['prohibited'], 'created_at' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'Tiêu đề', 'body' => 'Nội dung', 'audience' => 'Phạm vi gửi', 'room_ids' => 'Phòng nhận thông báo', 'room_ids.*' => 'Phòng'] + parent::attributes();
    }
}
