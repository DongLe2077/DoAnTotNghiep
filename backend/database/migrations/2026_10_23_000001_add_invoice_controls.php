<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('due_date')->nullable()->index();
            $table->unsignedInteger('revision')->default(0);
            $table->dateTime('cancelled_at')->nullable();
        });
        Schema::create('invoice_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('action', 20);
            $table->decimal('amount', 18, 2)->default(0);
            $table->text('reason');
            $table->json('before_data');
            $table->json('after_data');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $key = $table->string('idempotency_key', 64);
            if (DB::getDriverName() === 'mysql') {
                $key->charset('ascii')->collation('ascii_bin');
            }
            $table->unique('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->dateTime('created_at');
            $table->unique(['invoice_id', 'revision']);
        });
        $this->balances(true);
    }

    public function down(): void
    {
        if (DB::table('invoice_changes')->exists() || DB::table('invoices')->whereNotNull('due_date')->orWhereNotNull('cancelled_at')->exists()) {
            throw new RuntimeException('Cannot discard invoice history or deadlines. Restore a reviewed backup instead.');
        }
        $this->balances(false);
        Schema::dropIfExists('invoice_changes');
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['due_date', 'revision', 'cancelled_at']));
    }

    private function balances(bool $controls): void
    {
        // Preserve the original sources and receipts. Only the effective amount changes.
        $sum = DB::getDriverName() === 'sqlite' ? 'ROUND(SUM(%s), 2)' : 'SUM(%s)';
        $original = 'COALESCE(li.total_amount, 0.00)';
        $total = $controls ? '(CASE WHEN i.cancelled_at IS NOT NULL THEN 0.00 ELSE '.$original.' + COALESCE(a.amount, 0.00) END)' : $original;
        $cancelled = $controls ? "WHEN i.cancelled_at IS NOT NULL THEN 'cancelled'" : '';
        $extra = $controls ? ", $original AS original_amount, COALESCE(a.amount, 0.00) AS adjustment_amount" : '';
        $adjustments = $controls ? 'LEFT JOIN (SELECT invoice_id, '.sprintf($sum, 'amount')." AS amount FROM invoice_changes WHERE action = 'adjustment' GROUP BY invoice_id) a ON a.invoice_id = i.id" : '';
        $lines = sprintf($sum, 'line_total');
        $payments = sprintf($sum, 'amount');
        $sql = "SELECT i.id AS invoice_id, i.invoice_number, i.occupancy_id, i.billing_month, i.issued_at,
            COALESCE(li.line_count, 0) AS item_count, $total AS total_amount,
            COALESCE(p.paid_amount, 0.00) AS paid_amount,
            $total - COALESCE(p.paid_amount, 0.00) AS outstanding_amount $extra,
            CASE $cancelled WHEN COALESCE(li.line_count, 0) = 0 OR $total < 0 THEN 'inconsistent'
                WHEN COALESCE(p.paid_amount, 0.00) > $total THEN 'inconsistent'
                WHEN COALESCE(p.paid_amount, 0.00) = $total THEN 'paid'
                WHEN COALESCE(p.paid_amount, 0.00) = 0 THEN 'unpaid'
                ELSE 'partially_paid' END AS payment_status
            FROM invoices i
            LEFT JOIN (SELECT invoice_id, COUNT(*) AS line_count, $lines AS total_amount FROM invoice_items GROUP BY invoice_id) li ON li.invoice_id = i.id
            LEFT JOIN (SELECT invoice_id, $payments AS paid_amount FROM payments GROUP BY invoice_id) p ON p.invoice_id = i.id
            $adjustments";
        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE OR REPLACE VIEW invoice_balances AS '.$sql);
        } else {
            DB::statement('DROP VIEW IF EXISTS invoice_balances');
            DB::statement('CREATE VIEW invoice_balances AS '.$sql);
        }
    }
};
