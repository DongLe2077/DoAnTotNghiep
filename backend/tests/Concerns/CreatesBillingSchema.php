<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesBillingSchema
{
    use CreatesTenancySchema;

    private function createBillingSchema(): void
    {
        $this->createTenancySchema();
        Schema::table('meter_readings', fn (Blueprint $table) => $table->unique(['id', 'occupancy_service_id']));
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unique('meter_reading_id');
            $table->unique('rent_invoice_id');
            $table->unique(['occupancy_service_id', 'charge_period_start', 'charge_period_end']);
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->dateTime('paid_at');
            $table->string('payment_method', 20);
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('recorded_at');
        });
        // SQLite stores DECIMAL as floating point; rounding sums mirrors MySQL's exact cents.
        DB::statement(<<<'SQL'
CREATE VIEW invoice_balances AS
SELECT i.id AS invoice_id, i.invoice_number, i.occupancy_id, i.billing_month, i.issued_at,
       COALESCE(li.line_count, 0) AS item_count,
       COALESCE(li.total_amount, 0.00) AS total_amount,
       COALESCE(p.paid_amount, 0.00) AS paid_amount,
       COALESCE(li.total_amount, 0.00) - COALESCE(p.paid_amount, 0.00) AS outstanding_amount,
       CASE WHEN COALESCE(li.line_count, 0) = 0 THEN 'inconsistent'
            WHEN COALESCE(p.paid_amount, 0.00) > COALESCE(li.total_amount, 0.00) THEN 'inconsistent'
            WHEN COALESCE(p.paid_amount, 0.00) = COALESCE(li.total_amount, 0.00) THEN 'paid'
            WHEN COALESCE(p.paid_amount, 0.00) = 0 THEN 'unpaid'
            ELSE 'partially_paid' END AS payment_status
FROM invoices i
LEFT JOIN (SELECT invoice_id, COUNT(*) AS line_count, ROUND(SUM(line_total), 2) AS total_amount FROM invoice_items GROUP BY invoice_id) li ON li.invoice_id = i.id
LEFT JOIN (SELECT invoice_id, ROUND(SUM(amount), 2) AS paid_amount FROM payments GROUP BY invoice_id) p ON p.invoice_id = i.id
SQL);
        (require database_path('migrations/2026_10_23_000001_add_invoice_controls.php'))->up();
        (require database_path('migrations/2026_10_27_000002_create_payment_corrections.php'))->up();
        (require database_path('migrations/2026_10_28_000002_create_meter_reading_requests.php'))->up();
    }
}
