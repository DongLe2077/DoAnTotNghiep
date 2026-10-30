<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentCorrections;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user('web')?->hasRole(User::ROLE_OWNER) ?? false;

        return [
            'id' => $this->id, 'receipt_number' => 'PT-'.$this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice->invoice_number),
            'billing_month' => $this->whenLoaded('invoice', fn () => $this->invoice->billing_month->format('Y-m')),
            'room' => $this->whenLoaded('invoice', fn () => $this->invoice->occupancy->room->only(['id', 'room_number'])),
            'group' => $this->whenLoaded('invoice', fn () => $this->invoice->occupancy->rentalGroup->only(['id', 'group_code'])),
            'amount' => $this->amount, 'paid_at' => $this->paid_at->format('Y-m-d H:i:s'),
            'payment_method' => $this->payment_method, 'idempotency_key' => $this->when($isOwner, $this->idempotency_key),
            'recorded_by' => $this->when($isOwner, $this->recorded_by), 'recorded_at' => $this->recorded_at->format('Y-m-d H:i:s'),
            ...self::metadata($this->resource, $isOwner),
        ];
    }

    public static function metadata(Payment $payment, bool $isOwner): array
    {
        $correction = $payment->correction;
        $values = ['status' => $correction === null ? 'active' : 'voided',
            'replaces_payment_id' => $payment->replacementFor?->payment_id,
            'correction' => $correction === null ? null : [
                'id' => $correction->id, 'action' => $correction->action, 'reason' => $correction->reason,
                'created_at' => $correction->created_at->format('Y-m-d H:i:s'),
                'replacement_payment_id' => $correction->replacement_payment_id,
                ...($isOwner ? ['performed_by' => ['id' => $correction->performed_by, 'username' => $correction->actor_username],
                    'before' => $correction->before_data, 'after' => $correction->after_data] : []),
            ]];
        if ($isOwner) {
            $values['version_token'] = PaymentCorrections::version($payment);
            $values['can_correct'] = $correction === null && $payment->invoice->cancelled_at === null;
        }

        return $values;
    }
}
