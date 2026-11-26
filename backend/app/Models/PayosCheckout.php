<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayosCheckout extends Model
{
    use \App\Models\Concerns\BelongsToProperty;

    protected $table = 'invoice_payos_checkouts';

    protected $fillable = [
        'invoice_id', 'order_code', 'amount', 'invoice_version', 'status', 'link_id', 'checkout_url', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'order_code' => 'integer', 'expires_at' => 'immutable_datetime'];
    }
}
