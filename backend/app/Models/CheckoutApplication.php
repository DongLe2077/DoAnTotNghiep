<?php

namespace App\Models;

use App\Models\Concerns\BelongsToProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutApplication extends Model
{
    use BelongsToProperty;

    protected $table = 'checkout_requests';

    protected $fillable = ['occupancy_id', 'requester_stay_id', 'requested_by', 'status', 'rejection_reason'];

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(Occupancy::class);
    }

    public function requesterStay(): BelongsTo
    {
        return $this->belongsTo(TenantStay::class);
    }

    public function lockPending(): self
    {
        $locked = self::query()->where('occupancy_id', $this->occupancy_id)->lockForUpdate()->findOrFail($this->id);
        abort_unless($locked->status === 'pending', 409, 'Yêu cầu trả phòng đã được xử lý.');

        return $locked;
    }
}
