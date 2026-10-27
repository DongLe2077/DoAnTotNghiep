<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceHistoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50']];
    }
}
