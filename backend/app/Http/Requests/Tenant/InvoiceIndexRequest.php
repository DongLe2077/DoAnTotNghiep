<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\InvoiceFilterRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceIndexRequest extends FormRequest
{
    use InvoiceFilterRules;

    public function authorize(): bool
    {
        $user = $this->user('web');

        return $user instanceof User && $user->is_active && $user->hasRole(User::ROLE_TENANT);
    }
}
