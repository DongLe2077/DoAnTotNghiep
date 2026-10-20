<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesTenancySchema
{
    use CreatesCatalogSchema;

    private function createTenancySchema(): void
    {
        $this->createCatalogSchema();
        Schema::table('tenant_stays', fn (Blueprint $table) => $table->unique(['id', 'occupancy_id']));
        Schema::table('occupancy_services', fn (Blueprint $table) => $table->unique(['id', 'occupancy_id']));
        Schema::create('representative_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('occupancy_id');
            $table->unsignedBigInteger('tenant_stay_id');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedBigInteger('active_occupancy_id')->nullable()->storedAs('CASE WHEN ended_at IS NULL THEN occupancy_id ELSE NULL END')->unique();
            $table->dateTime('created_at');
            $table->unique(['id', 'occupancy_id']);
            $table->foreign(['tenant_stay_id', 'occupancy_id'])->references(['id', 'occupancy_id'])->on('tenant_stays')->restrictOnDelete();
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('occupancy_id')->constrained('occupancies')->restrictOnDelete();
            $table->date('billing_month');
            $table->unsignedBigInteger('representative_assignment_id');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('issued_at');
            $table->unique(['occupancy_id', 'billing_month']);
            $table->unique(['id', 'occupancy_id']);
            $table->foreign(['representative_assignment_id', 'occupancy_id'])->references(['id', 'occupancy_id'])->on('representative_assignments')->restrictOnDelete();
        });
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occupancy_service_id')->constrained('occupancy_services')->restrictOnDelete();
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->decimal('previous_reading', 15, 3);
            $table->decimal('current_reading', 15, 3);
            $table->decimal('consumption', 15, 3)->storedAs('current_reading - previous_reading');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('recorded_at');
            $table->unique(['occupancy_service_id', 'period_start', 'period_end']);
        });
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('occupancy_id')->constrained('occupancies')->restrictOnDelete();
            $table->string('item_type', 20);
            $table->string('description');
            $table->string('unit', 30);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('line_total', 18, 2)->storedAs('ROUND(quantity * unit_price, 2)');
            $table->foreignId('occupancy_service_id')->nullable()->constrained('occupancy_services')->restrictOnDelete();
            $table->foreignId('meter_reading_id')->nullable()->constrained('meter_readings')->restrictOnDelete();
            $table->dateTime('charge_period_start')->nullable();
            $table->dateTime('charge_period_end')->nullable();
            $table->unsignedBigInteger('rent_invoice_id')->nullable()->storedAs("CASE WHEN item_type = 'rent' THEN invoice_id ELSE NULL END");
        });
        Schema::create('invoice_access_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('occupancy_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('representative_assignment_id');
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('granted_at');
            $table->dateTime('revoked_at')->nullable();
            $table->string('grant_reason');
            $table->unsignedBigInteger('active_invoice_id')->nullable()->storedAs('CASE WHEN revoked_at IS NULL THEN invoice_id ELSE NULL END')->unique();
            $table->foreign(['invoice_id', 'occupancy_id'])->references(['id', 'occupancy_id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['representative_assignment_id', 'occupancy_id'])->references(['id', 'occupancy_id'])->on('representative_assignments')->restrictOnDelete();
        });
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occupancy_id')->constrained('occupancies')->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('representative_assignment_id');
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('submitted');
            $table->text('resolution')->nullable();
            $table->dateTime('submitted_at');
            $table->dateTime('updated_at');
            $table->foreign(['representative_assignment_id', 'occupancy_id'])->references(['id', 'occupancy_id'])->on('representative_assignments')->restrictOnDelete();
        });
        DB::statement("CREATE VIEW active_invoice_access AS SELECT g.user_id, g.invoice_id, g.occupancy_id, g.representative_assignment_id, g.granted_at FROM invoice_access_grants g JOIN users u ON u.id = g.user_id WHERE g.revoked_at IS NULL AND u.role = 'tenant' AND u.is_active = 1");
        (require database_path('migrations/2026_10_20_000002_create_maintenance_photos.php'))->up();
        (require database_path('migrations/2026_10_20_000003_create_app_notifications.php'))->up();
    }
}
