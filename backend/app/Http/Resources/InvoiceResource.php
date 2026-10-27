<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\InvoiceControls;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user('web')?->hasRole(User::ROLE_OWNER) ?? false;

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'occupancy_id' => $this->occupancy_id,
            'billing_month' => $this->billing_month->format('Y-m'),
            'issued_at' => $this->issued_at->format('Y-m-d H:i:s'),
            'representative_assignment_id' => $this->representative_assignment_id,
            'room' => $this->whenLoaded('occupancy', fn () => $this->occupancy->room->only(['id', 'room_number'])),
            'group' => $this->whenLoaded('occupancy', fn () => $this->occupancy->rentalGroup->only(['id', 'group_code'])),
            'item_count' => (int) $this->item_count,
            'total_amount' => $this->money($this->total_amount),
            'paid_amount' => $this->money($this->paid_amount),
            'outstanding_amount' => $this->money($this->outstanding_amount),
            'payment_status' => $this->payment_status,
            'original_amount' => $this->money($this->original_amount),
            'adjustment_amount' => $this->money($this->adjustment_amount),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'due_status' => InvoiceControls::dueStatus($this->resource),
            'overdue_days' => InvoiceControls::dueStatus($this->resource) === 'overdue' ? (int) $this->due_date->startOfDay()->diffInDays(now()->startOfDay()) : 0,
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'revision' => $this->revision,
            'version_token' => $this->when($isOwner, fn () => InvoiceControls::version($this->resource)),
            'changes' => $this->whenLoaded('changes', fn () => $this->changes->map(fn ($change) => [
                'id' => $change->id, 'revision' => $change->revision, 'action' => $change->action,
                'amount' => $change->amount, 'reason' => $change->reason,
                'before' => $change->before_data, 'after' => $change->after_data,
                'performed_by' => $isOwner ? $change->performedBy?->username : null,
                'created_at' => $change->created_at->format('Y-m-d H:i:s'),
            ])),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'item_type' => $item->item_type,
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'source_unit_price' => $item->occupancyService?->unit_price
                    ?? ($item->item_type === 'rent' ? $item->unit_price : null),
                'line_total' => $item->line_total,
                'occupancy_service_id' => $item->occupancy_service_id,
                'service' => $item->occupancyService?->service?->only(['id', 'service_code', 'name']),
                'meter_reading_id' => $item->meter_reading_id,
                'meter_reading' => $item->meterReading === null ? null : [
                    'id' => $item->meterReading->id,
                    'period_start' => $item->meterReading->period_start->format('Y-m-d H:i:s'),
                    'period_end' => $item->meterReading->period_end->format('Y-m-d H:i:s'),
                    'previous_reading' => $item->meterReading->previous_reading,
                    'current_reading' => $item->meterReading->current_reading,
                    'consumption' => $item->meterReading->consumption,
                ],
                'charge_period_start' => $item->charge_period_start?->format('Y-m-d H:i:s'),
                'charge_period_end' => $item->charge_period_end?->format('Y-m-d H:i:s'),
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at->format('Y-m-d H:i:s'),
                'payment_method' => $payment->payment_method,
            ])),
            'access_grants' => $this->when($isOwner && $this->relationLoaded('accessGrants'), fn () => $this->accessGrants->map(fn ($grant) => [
                'id' => $grant->id,
                'user_id' => $grant->user_id,
                'representative_assignment_id' => $grant->representative_assignment_id,
                'granted_by' => $grant->granted_by,
                'granted_at' => $grant->granted_at->format('Y-m-d H:i:s'),
                'revoked_at' => $grant->revoked_at?->format('Y-m-d H:i:s'),
                'grant_reason' => $grant->grant_reason,
                'is_active' => $grant->revoked_at === null,
            ])),
        ];
    }

    private function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HALF_UP);
    }
}
