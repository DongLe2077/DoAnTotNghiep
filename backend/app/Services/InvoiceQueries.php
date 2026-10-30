<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class InvoiceQueries
{
    public function query(array $filters = [], ?int $tenantUserId = null, bool $detail = false): Builder
    {
        $query = Invoice::query()->select('invoices.*', 'balances.item_count', 'balances.total_amount',
            'balances.paid_amount', 'balances.outstanding_amount', 'balances.payment_status', 'balances.original_amount', 'balances.adjustment_amount')
            ->join('invoice_balances as balances', 'balances.invoice_id', '=', 'invoices.id')
            ->with('occupancy.room', 'occupancy.rentalGroup');

        if ($tenantUserId !== null) {
            $query->whereExists(fn ($access) => $access->selectRaw('1')->from('active_invoice_access as access')
                ->whereColumn('access.invoice_id', 'invoices.id')->where('access.user_id', $tenantUserId));
        }
        if (isset($filters['billing_month'])) {
            $month = CarbonImmutable::createFromFormat('!Y-m', $filters['billing_month']);
            $query->where('invoices.billing_month', '>=', $month->format('Y-m-d'))
                ->where('invoices.billing_month', '<=', $month->endOfMonth()->format('Y-m-d'));
        }
        if (isset($filters['occupancy_id'])) {
            $query->where('invoices.occupancy_id', $filters['occupancy_id']);
        }
        foreach (['room_id', 'rental_group_id'] as $field) {
            if (isset($filters[$field])) {
                $query->whereHas('occupancy', fn ($occupancy) => $occupancy->where($field, $filters[$field]));
            }
        }
        if (isset($filters['payment_status'])) {
            $query->where('balances.payment_status', $filters['payment_status']);
        }
        if (isset($filters['due_status'])) {
            $query->whereIn('balances.payment_status', ['unpaid', 'partially_paid'])->where('balances.outstanding_amount', '>', 0);
            match ($filters['due_status']) {
                'not_set' => $query->whereNull('invoices.due_date'),
                'overdue' => $query->where('invoices.due_date', '<', now()->format('Y-m-d')),
                default => $query->where('invoices.due_date', '>=', now()->format('Y-m-d')),
            };
        }
        if ($detail) {
            $query->with([
                'items' => fn ($items) => $items->with('occupancyService.service', 'meterReading')->orderBy('id'),
                'payments' => fn ($payments) => $payments->with('correction', 'replacementFor', 'invoice')->orderBy('paid_at')->orderBy('id'),
                'changes' => fn ($changes) => $changes->with('performedBy')->orderBy('revision'),
            ]);
            if ($tenantUserId === null) {
                $query->with(['accessGrants' => fn ($grants) => $grants->orderBy('granted_at')->orderBy('id')]);
            }
        }

        return $query;
    }
}
