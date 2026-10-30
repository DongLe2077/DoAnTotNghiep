<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalProperty extends Model
{
    protected $fillable = ['name', 'address', 'contact_name', 'phone', 'email', 'bank_name', 'bank_account_number', 'bank_account_name', 'notes', 'updated_by'];

    protected function casts(): array
    {
        return ['id' => 'integer', 'revision' => 'integer', 'updated_by' => 'integer'];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
