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
            'balances.paid_amount', 'balances.outstanding_amount', 'balances.payment_status')
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
        if ($detail) {
            $query->with([
                'items' => fn ($items) => $items->with('occupancyService.service', 'meterReading')->orderBy('id'),
                'payments' => fn ($payments) => $payments->orderBy('paid_at')->orderBy('id'),
            ]);
            if ($tenantUserId === null) {
                $query->with(['accessGrants' => fn ($grants) => $grants->orderBy('granted_at')->orderBy('id')]);
            }
        }

        return $query;
    }
}
