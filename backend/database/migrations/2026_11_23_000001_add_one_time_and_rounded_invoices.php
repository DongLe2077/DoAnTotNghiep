<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $monthlyIndex = $this->monthlyIndex();
        if (DB::getDriverName() === 'sqlite' && str_starts_with($monthlyIndex, 'sqlite_autoindex_')) {
            $this->upgradeLegacySqlite();
            return;
        }

        $this->upgradeInvoices($monthlyIndex);
    }

    private function upgradeInvoices(?string $monthlyIndex): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_kind', 16)->default('monthly');
            $table->index('occupancy_id', 'ix_invoices_occupancy');
            $table->decimal('rounding_amount', 5, 2)->default(0);
            $table->char('issue_key_hash', 64)->nullable()->unique();
            $table->char('issue_fingerprint', 64)->nullable();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('monthly_billing_month')->nullable()->virtualAs("CASE WHEN invoice_kind = 'monthly' THEN billing_month END");
            $table->unique(['occupancy_id', 'monthly_billing_month'], 'uq_invoices_monthly');
        });
        if ($monthlyIndex !== null) {
            Schema::table('invoices', fn (Blueprint $table) => $table->dropUnique($monthlyIndex));
        }
        $this->itemChecks(true);
        $this->balances(true);
    }

    public function down(): void
    {
        if (DB::table('invoices')->where('invoice_kind', 'one_time')->orWhere('rounding_amount', '<>', 0)->exists()) {
            throw new RuntimeException('Cannot discard issued one-time charges or rounding amounts. Restore a reviewed backup instead.');
        }
        $this->balances(false);
        $this->itemChecks(false);
        Schema::table('invoices', fn (Blueprint $table) => $table->unique(['occupancy_id', 'billing_month'], 'uq_invoices_month'));
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('uq_invoices_monthly');
            $table->dropIndex('ix_invoices_occupancy');
            $table->dropUnique(['issue_key_hash']);
        });
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(
            ['monthly_billing_month', 'invoice_kind', 'rounding_amount', 'issue_key_hash', 'issue_fingerprint']
        ));
    }

    private function upgradeLegacySqlite(): void
    {
        // SQLite implements the old inline UNIQUE as an index that DROP INDEX cannot remove.
        $definition = DB::scalar("SELECT sql FROM sqlite_schema WHERE type = 'table' AND name = 'invoices'");
        $replacement = preg_replace(
            '/,\s*UNIQUE\s*\(\s*"?occupancy_id"?\s*,\s*"?billing_month"?\s*\)/i',
            '',
            $definition,
            -1,
            $removed,
        );
        if ($removed !== 1) {
            throw new RuntimeException('Unrecognized legacy SQLite invoice constraint.');
        }
        $replacement = preg_replace('/^CREATE TABLE\s+"?invoices"?\s*\(/i', 'CREATE TABLE invoices_rebuild (', $replacement, 1, $renamed);
        if ($renamed !== 1 || DB::transactionLevel() !== 0 || Schema::hasTable('invoices_rebuild')) {
            throw new RuntimeException('Cannot safely rebuild the legacy SQLite invoice table.');
        }

        $foreignKeys = (bool) DB::scalar('PRAGMA foreign_keys');
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($replacement): void {
                $objects = DB::table('sqlite_schema')->where('tbl_name', 'invoices')
                    ->whereIn('type', ['index', 'trigger'])->whereNotNull('sql')->orderBy('type')->pluck('sql');
                $sequence = DB::table('sqlite_sequence')->where('name', 'invoices')->value('seq');
                $columns = collect(DB::select('PRAGMA table_xinfo(invoices)'))->where('hidden', 0)
                    ->map(fn ($column) => '"'.str_replace('"', '""', $column->name).'"')->implode(', ');

                DB::statement('DROP VIEW IF EXISTS invoice_balances');
                DB::statement($replacement);
                DB::statement("INSERT INTO invoices_rebuild ($columns) SELECT $columns FROM invoices");
                DB::statement('DROP TABLE invoices');
                DB::statement('ALTER TABLE invoices_rebuild RENAME TO invoices');
                if ($sequence !== null) {
                    DB::table('sqlite_sequence')->updateOrInsert(['name' => 'invoices'], ['seq' => $sequence]);
                }
                foreach ($objects as $sql) {
                    DB::statement($sql);
                }

                $this->upgradeInvoices(null);
                if (DB::select('PRAGMA foreign_key_check') !== []) {
                    throw new RuntimeException('Invoice upgrade failed SQLite foreign key validation.');
                }
            });
        } finally {
            if ($foreignKeys) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }

    private function monthlyIndex(): string
    {
        foreach (Schema::getIndexes('invoices') as $index) {
            if ($index['unique'] && $index['columns'] === ['occupancy_id', 'billing_month']) {
                return $index['name'];
            }
        }

        throw new RuntimeException('Missing unique invoice month index.');
    }

    private function itemChecks(bool $oneTime): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $checks = DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::connection()->getDatabaseName())
            ->where('table_name', 'invoice_items')->where('constraint_type', 'CHECK')
            ->whereIn('constraint_name', ['ck_invoice_items_shape', 'ck_invoice_items_type'])->pluck('constraint_name');
        $version = DB::selectOne('SELECT VERSION() AS version')->version;
        $drop = str_contains(strtolower($version), 'mariadb') ? 'DROP CONSTRAINT' : 'DROP CHECK';
        $clauses = $checks->map(fn ($name) => "$drop $name")->all();
        $types = $oneTime ? "'rent', 'service', 'one_time'" : "'rent', 'service'";
        $unlinked = $oneTime ? "item_type IN ('rent', 'one_time')" : "item_type = 'rent'";
        $clauses[] = "ADD CONSTRAINT ck_invoice_items_type CHECK (item_type IN ($types))";
        $clauses[] = "ADD CONSTRAINT ck_invoice_items_shape CHECK (
            ($unlinked AND quantity = 1 AND occupancy_service_id IS NULL AND meter_reading_id IS NULL
                AND charge_period_start IS NULL AND charge_period_end IS NULL)
            OR (item_type = 'service' AND occupancy_service_id IS NOT NULL AND charge_period_start IS NOT NULL
                AND charge_period_end IS NOT NULL AND charge_period_end > charge_period_start))";
        DB::statement('ALTER TABLE invoice_items '.implode(', ', $clauses));
    }

    private function balances(bool $rounding): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';
        $sum = fn (string $column) => $sqlite ? "ROUND(SUM($column), 2)" : "SUM($column)";
        $original = 'COALESCE(li.total_amount, 0.00)';
        $activeTotal = $original.($rounding ? ' + i.rounding_amount' : '').' + COALESCE(a.amount, 0.00)';
        if ($sqlite) {
            $activeTotal = "ROUND($activeTotal, 2)";
        }
        $total = "(CASE WHEN i.cancelled_at IS NOT NULL THEN 0.00 ELSE $activeTotal END)";
        $lines = $sum('line_total');
        $amount = $sum('amount');
        $sql = "SELECT i.id AS invoice_id, i.invoice_number, i.occupancy_id, i.billing_month, i.issued_at,
            COALESCE(li.line_count, 0) AS item_count, $total AS total_amount,
            COALESCE(p.paid_amount, 0.00) AS paid_amount,
            $total - COALESCE(p.paid_amount, 0.00) AS outstanding_amount,
            $original AS original_amount, COALESCE(a.amount, 0.00) AS adjustment_amount,
            CASE WHEN i.cancelled_at IS NOT NULL THEN 'cancelled'
                WHEN COALESCE(li.line_count, 0) = 0 OR $total < 0 THEN 'inconsistent'
                WHEN COALESCE(p.paid_amount, 0.00) > $total THEN 'inconsistent'
                WHEN COALESCE(p.paid_amount, 0.00) = $total THEN 'paid'
                WHEN COALESCE(p.paid_amount, 0.00) = 0 THEN 'unpaid'
                ELSE 'partially_paid' END AS payment_status
            FROM invoices i
            LEFT JOIN (SELECT invoice_id, COUNT(*) AS line_count, $lines AS total_amount FROM invoice_items GROUP BY invoice_id) li ON li.invoice_id = i.id
            LEFT JOIN (SELECT invoice_id, $amount AS paid_amount FROM payments
                WHERE NOT EXISTS (SELECT 1 FROM payment_corrections pc WHERE pc.payment_id = payments.id)
                GROUP BY invoice_id) p ON p.invoice_id = i.id
            LEFT JOIN (SELECT invoice_id, $amount AS amount FROM invoice_changes WHERE action = 'adjustment' GROUP BY invoice_id) a ON a.invoice_id = i.id";
        if ($sqlite) {
            DB::statement('DROP VIEW IF EXISTS invoice_balances');
            DB::statement('CREATE VIEW invoice_balances AS '.$sql);
        } else {
            DB::statement('CREATE OR REPLACE VIEW invoice_balances AS '.$sql);
        }
    }
};
