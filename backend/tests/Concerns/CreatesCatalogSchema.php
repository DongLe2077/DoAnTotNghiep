<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesCatalogSchema
{
    private function createCatalogSchema(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('username')->unique();
            $table->string('password_hash');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number', 30)->collation('NOCASE')->unique();
            $table->unsignedSmallInteger('capacity');
            $table->decimal('default_monthly_rent', 18, 2);
            $table->string('operational_status', 20)->default('available');
            $table->timestamps();
        });
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('service_code', 40)->collation('NOCASE')->unique();
            $table->string('name', 100);
            $table->string('unit', 30);
            $table->string('billing_method', 20);
            $table->decimal('default_unit_price', 18, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('rental_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_code', 40)->unique();
            $table->dateTime('created_at');
        });
        Schema::create('occupancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_group_id')->constrained('rental_groups')->restrictOnDelete();
            $table->unsignedBigInteger('previous_occupancy_id')->nullable()->unique();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->decimal('monthly_rent', 18, 2);
            $table->unsignedBigInteger('active_room_id')->nullable()->storedAs('CASE WHEN ended_at IS NULL THEN room_id ELSE NULL END')->unique();
            $table->timestamps();
            $table->unsignedBigInteger('active_rental_group_id')->nullable()->storedAs('CASE WHEN ended_at IS NULL THEN rental_group_id ELSE NULL END')->unique();
        });
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->string('phone_number', 20);
            $table->string('identity_number', 30)->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('tenant_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occupancy_id')->constrained('occupancies')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->dateTime('moved_in_at');
            $table->dateTime('moved_out_at')->nullable();
            $table->unsignedBigInteger('active_tenant_id')->nullable()->storedAs('CASE WHEN moved_out_at IS NULL THEN tenant_id ELSE NULL END')->unique();
            $table->timestamps();
        });
        Schema::create('occupancy_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occupancy_id')->constrained('occupancies')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->dateTime('effective_from');
            $table->dateTime('effective_until')->nullable();
            $table->decimal('unit_price', 18, 4);
            $table->string('billing_method', 20);
            $table->string('unit', 30);
            $table->unsignedBigInteger('active_service_id')->nullable()->storedAs('CASE WHEN effective_until IS NULL THEN service_id ELSE NULL END');
            $table->dateTime('created_at');
            $table->unique(['occupancy_id', 'active_service_id']);
        });
        DB::statement(<<<'SQL'
CREATE VIEW room_occupancy_summary AS
SELECT r.id AS room_id, r.room_number, r.capacity, r.operational_status,
       COALESCE(s.resident_count, 0) AS resident_count,
       CASE WHEN r.operational_status = 'maintenance' THEN 'maintenance'
            WHEN COALESCE(s.resident_count, 0) > 0 THEN 'occupied' ELSE 'vacant' END AS occupancy_status
FROM rooms r LEFT JOIN (
    SELECT o.room_id, COUNT(*) AS resident_count
    FROM occupancies o JOIN tenant_stays ts ON ts.occupancy_id = o.id
    WHERE o.ended_at IS NULL AND ts.moved_out_at IS NULL
    GROUP BY o.room_id
) s ON s.room_id = r.id
SQL);
    }
}
