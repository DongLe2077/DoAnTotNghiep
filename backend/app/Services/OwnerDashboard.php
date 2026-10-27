<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OwnerDashboard
{
    public function __construct(private readonly InvoiceReports $invoices, private readonly CashBook $cash) {}

    public function summary(string $month): array
    {
        return DB::transaction(function () use ($month) {
            $rooms = DB::table('room_occupancy_summary as rooms')
                ->leftJoin('occupancies as active', fn ($join) => $join->on('active.room_id', '=', 'rooms.room_id')->whereNull('active.ended_at'))
                ->selectRaw('COUNT(*) AS total_count,
                    COALESCE(SUM(CASE WHEN rooms.occupancy_status = ? THEN 1 ELSE 0 END), 0) AS occupied_count,
                    COALESCE(SUM(CASE WHEN rooms.occupancy_status = ? THEN 1 ELSE 0 END), 0) AS vacant_count,
                    COALESCE(SUM(CASE WHEN rooms.occupancy_status = ? THEN 1 ELSE 0 END), 0) AS maintenance_count,
                    COALESCE(SUM(CASE WHEN rooms.operational_status = ? AND active.id IS NULL THEN 1 ELSE 0 END), 0) AS available_for_check_in_count,
                    COUNT(active.id) AS active_occupancy_count,
                    COALESCE(SUM(rooms.resident_count), 0) AS resident_count,
                    COALESCE(SUM(rooms.capacity), 0) AS total_capacity', ['occupied', 'vacant', 'maintenance', 'available'])->first();
            $maintenance = DB::table('maintenance_requests')->selectRaw("COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END), 0) AS submitted_count,
                COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) AS in_progress_count,
                COALESCE(SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END), 0) AS resolved_count")->first();

            return [
                'month' => $month,
                'as_of' => now()->format('Y-m-d H:i:s'),
                'rooms' => array_map(intval(...), (array) $rooms),
                'tenants' => [
                    'total_count' => DB::table('tenants')->count(),
                    'residing_count' => DB::table('tenant_stays')->join('occupancies', 'occupancies.id', '=', 'tenant_stays.occupancy_id')
                        ->whereNull('tenant_stays.moved_out_at')->whereNull('occupancies.ended_at')->distinct()->count('tenant_stays.tenant_id'),
                ],
                'maintenance' => [...array_map(intval(...), (array) $maintenance),
                    'open_count' => (int) $maintenance->submitted_count + (int) $maintenance->in_progress_count],
                'invoices' => $this->invoices->summary(['billing_month' => $month]),
                'receivables' => $this->invoices->summary(),
                'cash_flow' => $this->cash->summary(['month' => $month]),
                'monthly_series' => $this->monthlySeries($month),
            ];
        });
    }

    private function monthlySeries(string $month): array
    {
        $end = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'));
        $start = $end->subMonths(5)->max(CarbonImmutable::create(1000, 1, 1, 0, 0, 0, config('app.timezone')));
        $invoices = $this->invoices->monthlySeries($start->format('Y-m-d'), $end->endOfMonth()->format('Y-m-d'));
        $cash = collect($this->cash->summary(['from' => $start->format('Y-m-d H:i:s'),
            'until' => $end->endOfMonth()->format('Y-m-d H:i:s'), 'group_by' => 'month'])['periods'])->keyBy('period');
        $series = [];
        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addMonth()) {
            $key = $date->format('Y-m');
            $period = $cash->get($key);
            $series[] = ['month' => $key, ...($invoices[$key] ?? [
                'invoice_count' => 0, 'inconsistent_count' => 0, 'invoice_total' => '0.00',
                'invoice_paid' => '0.00', 'invoice_outstanding' => '0.00',
            ]), 'cash_income' => $period['total_income'] ?? '0.00',
                'cash_expense' => $period['expense_total'] ?? '0.00',
                'cash_net' => $period['net_cash_flow'] ?? '0.00'];
        }

        return $series;
    }
}
