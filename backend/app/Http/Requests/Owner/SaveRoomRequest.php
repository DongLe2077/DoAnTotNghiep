<?php

namespace App\Http\Requests\Owner;

use App\Models\Room;
use Illuminate\Validation\Rule;

class SaveRoomRequest extends OwnerRequest
{
    public function rules(): array
    {
        $uniqueNumber = Rule::unique('rooms', 'room_number');
        if ($this->route('room') instanceof Room) {
            $uniqueNumber->ignore($this->route('room'));
        }

        $rules = [
            'room_number' => ['bail', 'required', 'string', 'max:30', $uniqueNumber],
            'capacity' => ['bail', 'required', 'integer', 'between:1,65535'],
            'default_monthly_rent' => ['bail', 'sometimes', 'required', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D'],
            'operational_status' => ['sometimes', 'required', Rule::in(['available', 'maintenance'])],
        ];

        if (! $this->isMethod('POST')) {
            array_unshift($rules['room_number'], 'sometimes');
            array_unshift($rules['capacity'], 'sometimes');
        }

        return $rules;
    }
}
