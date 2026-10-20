<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class InvoiceReports
{
    public function __construct(private readonly InvoiceQueries $invoices) {}

    public function outstanding(array $filters = []): Builder
    {
        return $this->invoices->query($filters)
            ->whereIn('balances.payment_status', ['unpaid', 'partially_paid'])->where('balances.outstanding_amount', '>', 0);
    }

    public function summary(array $filters = []): array
    {
        // Aggregate the balances view, never a join of individual lines and receipts.
        // Bound status strings also work when the MySQL view uses a different collation.
        $row = $this->invoices->query($filters)->toBase()->select([])
            ->join('occupancies as reporting_occupancies', 'reporting_occupancies.id', '=', 'invoices.occupancy_id')
            ->selectRaw('COUNT(*) AS invoice_count,
                COALESCE(SUM(CASE WHEN balances.payment_status = ? THEN 1 ELSE 0 END), 0) AS unpaid_count,
                COALESCE(SUM(CASE WHEN balances.payment_status = ? THEN 1 ELSE 0 END), 0) AS partially_paid_count,
                COALESCE(SUM(CASE WHEN balances.payment_status = ? THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN balances.payment_status = ? THEN 1 ELSE 0 END), 0) AS inconsistent_count,
                COUNT(DISTINCT CASE WHEN balances.payment_status IN (?, ?)
                    AND balances.outstanding_amount > 0 THEN reporting_occupancies.room_id END) AS outstanding_room_count,
                COUNT(DISTINCT CASE WHEN balances.payment_status IN (?, ?)
                    AND balances.outstanding_amount > 0 THEN reporting_occupancies.rental_group_id END) AS outstanding_group_count,
                COALESCE(SUM(CASE WHEN balances.payment_status != ? THEN balances.total_amount ELSE 0 END), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN balances.payment_status != ? THEN balances.paid_amount ELSE 0 END), 0) AS paid_amount,
                COALESCE(SUM(CASE WHEN balances.payment_status != ? THEN balances.outstanding_amount ELSE 0 END), 0) AS outstanding_amount', [
                'unpaid', 'partially_paid', 'paid', 'inconsistent', 'unpaid', 'partially_paid', 'unpaid', 'partially_paid',
                'inconsistent', 'inconsistent', 'inconsistent',
            ])
            ->first();

        $counts = [];
        foreach (['invoice_count', 'unpaid_count', 'partially_paid_count', 'paid_count', 'inconsistent_count', 'outstanding_room_count', 'outstanding_group_count'] as $key) {
            $counts[$key] = (int) $row->$key;
        }

        return [
            'filters' => array_intersect_key($filters, array_flip(['billing_month', 'room_id', 'occupancy_id', 'rental_group_id'])),
            ...$counts,
            'open_invoice_count' => $counts['unpaid_count'] + $counts['partially_paid_count'],
            'valid_invoice_count' => $counts['invoice_count'] - $counts['inconsistent_count'],
            'total_amount' => self::money($row->total_amount),
            'paid_amount' => self::money($row->paid_amount),
            'outstanding_amount' => self::money($row->outstanding_amount),
        ];
    }

    public function groups(array $filters): QueryBuilder
    {
        $byRoom = ($filters['group_by'] ?? 'room') === 'room';
        $table = $byRoom ? 'rooms' : 'rental_groups';
        $foreignKey = $byRoom ? 'room_id' : 'rental_group_id';
        $label = $byRoom ? 'room_number' : 'group_code';

        return $this->outstanding($filters)->toBase()->select([])
            ->join('occupancies as reporting_occupancies', 'reporting_occupancies.id', '=', 'invoices.occupancy_id')
            ->join($table.' as reporting_groups', 'reporting_groups.id', '=', 'reporting_occupancies.'.$foreignKey)
            ->selectRaw('reporting_groups.id AS group_id, reporting_groups.'.$label.' AS group_label,
                COUNT(*) AS invoice_count, SUM(balances.total_amount) AS total_amount,
                SUM(balances.paid_amount) AS paid_amount, SUM(balances.outstanding_amount) AS outstanding_amount')
            ->groupBy('reporting_groups.id', 'reporting_groups.'.$label);
    }

    public static function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HALF_UP);
    }
}
