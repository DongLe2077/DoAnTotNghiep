<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $roots = ['rooms', 'tenants', 'rental_groups', 'services', 'cash_transactions', 'announcements', 'scheduled_deletions'];

    public function up(): void
    {
        Schema::table('rental_properties', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('property_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('rental_properties')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('tenant_id')->nullable()->unique()->constrained('tenants')->restrictOnDelete();
            $table->string('role', 20)->default('member');
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
        foreach ($this->roots as $name) {
            if (Schema::hasTable($name)) {
                $this->alterRoot($name, fn (Blueprint $table) => $table->foreignId('property_id')->nullable()->constrained('rental_properties')->restrictOnDelete());
            }
        }
        Schema::create('property_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->string('migration_key')->unique();
            $table->foreignId('property_id')->constrained('rental_properties')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('backup_reference', 500);
            $table->json('report');
            $table->timestamp('completed_at');
        });
    }

    private function alterRoot(string $name, Closure $change): void
    {
        // SQLite rebuilds tables when adding constraints; preserve the dependent report view.
        $view = DB::getDriverName() === 'sqlite' && $name === 'rooms'
            ? DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'view' AND name = 'room_occupancy_summary'")
            : null;
        if ($view) {
            DB::statement('DROP VIEW room_occupancy_summary');
        }
        try {
            Schema::table($name, $change);
        } finally {
            if ($view) {
                DB::statement($view->sql);
            }
        }
    }

    public function down(): void
    {
        if (DB::table('property_memberships')->exists() || DB::table('property_migration_runs')->exists()
            || DB::table('rental_properties')->whereNotNull('owner_user_id')->exists()) {
            throw new RuntimeException('Ownership has been assigned. Restore a reviewed backup; do not discard property boundaries.');
        }
        foreach ($this->roots as $name) {
            if (Schema::hasColumn($name, 'property_id') && DB::table($name)->whereNotNull('property_id')->exists()) {
                throw new RuntimeException('Property data exists. Rollback refused before changing any table.');
            }
        }
        Schema::dropIfExists('property_migration_runs');
        Schema::dropIfExists('property_memberships');
        foreach (array_reverse($this->roots) as $name) {
            if (Schema::hasColumn($name, 'property_id')) {
                $this->alterRoot($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('property_id'));
            }
        }
        Schema::table('rental_properties', fn (Blueprint $table) => $table->dropConstrainedForeignId('owner_user_id'));
    }
};
