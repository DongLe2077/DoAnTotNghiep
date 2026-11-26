<?php

use App\Services\Tenancy\PropertyQueries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = ['rooms' => 'room_number', 'services' => 'service_code', 'rental_groups' => 'group_code', 'tenants' => 'identity_number'];

    public function up(): void
    {
        $roots = array_filter(
            PropertyQueries::ROOTS,
            fn (string $table): bool => Schema::hasTable($table),
        );
        foreach ($roots as $table) {
            if (DB::table($table)->whereNull('property_id')->exists()) {
                throw new RuntimeException('Assign existing data to a property before finalizing ownership: '.$table);
            }
        }

        $views = DB::getDriverName() === 'sqlite' ? DB::select("SELECT name, sql FROM sqlite_master WHERE type='view'") : [];
        foreach ($views as $view) {
            DB::statement('DROP VIEW "'.str_replace('"', '""', $view->name).'"');
        }
        try {
            foreach ($this->columns as $table => $column) {
                if (! Schema::hasIndex($table, $table.'_property_code_unique')) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique(['property_id', $column], $table.'_property_code_unique'));
                }
                foreach (Schema::getIndexes($table) as $index) {
                    if ($index['unique'] && $index['columns'] === [$column]) {
                        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($index['name']));
                    }
                }
            }
            foreach ($roots as $table) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('property_id')->nullable(false)->change());
            }
        } finally {
            foreach ($views as $view) {
                DB::statement($view->sql);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Catalog codes may now repeat between properties. Restore a reviewed backup instead of reverting this boundary.');
    }
};
