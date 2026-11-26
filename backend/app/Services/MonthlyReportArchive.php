<?php

namespace App\Services;

use App\Services\Tenancy\PropertyDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MonthlyReportArchive
{
    public function __construct(
        private readonly MonthlyOccupancy $occupancy,
        private readonly InvoiceReports $invoices,
        private readonly CashBook $cash,
    ) {}

    public function capture(string $month): void
    {
        if (! $this->occupancy->enabled()) {
            return;
        }

        DB::transaction(function () use ($month) {
            $this->occupancy->archive($month, [
                'occupancy' => $this->occupancy->month($month),
                'invoices' => $this->invoices->summary(['billing_month' => $month]),
                'cash' => $this->cash->summary(['month' => $month]),
            ]);
        }, 3);
    }

    public function beforeDeletion(array $records): void
    {
        $earliest = now()->toDateTimeString();
        foreach (['occupancies' => 'started_at', 'tenant_stays' => 'moved_in_at', 'invoices' => 'billing_month',
            'cash_transactions' => 'occurred_at', 'payments' => 'paid_at'] as $table => $date) {
            if (! empty($records[$table])) {
                $first = PropertyDatabase::table($table)->whereIn('id', $records[$table])->min($date);
                if ($first !== null && $first < $earliest) {
                    $earliest = $first;
                }
            }
        }
        $end = CarbonImmutable::now()->startOfMonth();
        for ($month = CarbonImmutable::parse($earliest)->startOfMonth(); $month <= $end; $month = $month->addMonth()) {
            $this->capture($month->format('Y-m'));
        }
    }
}
