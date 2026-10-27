<?php

namespace App\Http\Requests\Owner;

use Illuminate\Validation\Rule;

class AnnouncementIndexRequest extends OwnerRequest
{
    public function rules(): array
    {
        return ['q' => ['sometimes', 'string', 'max:160'], 'audience' => ['sometimes', Rule::in(['all', 'rooms'])],
            'read_status' => ['sometimes', Rule::in(['read', 'unread'])],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100']];
    }
}
