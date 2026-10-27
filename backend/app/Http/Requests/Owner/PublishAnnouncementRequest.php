<?php

namespace App\Http\Requests\Owner;

class PublishAnnouncementRequest extends PreviewAnnouncementRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'preview_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/D'],
        ];
    }
}
