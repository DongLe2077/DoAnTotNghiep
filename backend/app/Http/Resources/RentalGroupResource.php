<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_code' => $this->group_code,
            'history_count' => (int) $this->occupancies_count,
            'can_delete' => (int) $this->occupancies_count === 0,
        ];
    }
}
