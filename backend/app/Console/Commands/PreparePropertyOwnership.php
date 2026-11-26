<?php

namespace App\Console\Commands;

use App\Services\Tenancy\PropertyQueries;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PreparePropertyOwnership extends Command
{
    protected $signature = 'rental:prepare-property-ownership {--owner= : Existing legacy owner user ID} {--apply : Assign existing records; otherwise only inspect} {--backup-reference= : Location/identifier of a verified backup}';

    protected $description = 'Review and explicitly assign legacy single-property data without altering financial values';

    public function handle(): int
    {
        try {
            if (! Schema::hasTable('property_memberships')) {
                throw new RuntimeException('Run the ownership schema migration on an isolated copy first.');
            }
            $ownerId = filter_var($this->option('owner'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! $ownerId || ! DB::table('users')->where('id', $ownerId)->where('role', 'owner')->where('is_active', true)->exists()) {
                throw new RuntimeException('Specify an existing active legacy owner with --owner. No owner is guessed automatically.');
            }
            $properties = DB::table('rental_properties')->get();
            if ($properties->count() !== 1 || (int) $properties[0]->id !== 1) {
                throw new RuntimeException('Requires exactly the configured legacy property id=1. Multiple/unconfigured properties need manual mapping.');
            }
            if ($properties[0]->owner_user_id !== null && (int) $properties[0]->owner_user_id !== $ownerId) {
                throw new RuntimeException('Property already belongs to another owner.');
            }
            $roots = array_values(array_filter(PropertyQueries::ROOTS, fn ($table) => Schema::hasTable($table)));
            foreach ($roots as $table) {
                if (! Schema::hasColumn($table, 'property_id') || DB::table($table)->whereNotNull('property_id')->where('property_id', '!=', 1)->exists()) {
                    throw new RuntimeException('Unreviewed or mixed ownership in '.$table);
                }
            }
            $tenants = DB::table('users')->where('role', 'tenant')->whereNotNull('tenant_id')->get();
            foreach ($tenants as $user) {
                if (! DB::table('tenants')->where('id', $user->tenant_id)->exists()) {
                    throw new RuntimeException('Orphan tenant account: '.$user->id.'. Fix mapping before assigning any records.');
                }
                if ($tenants->where('tenant_id', $user->tenant_id)->count() > 1) {
                    throw new RuntimeException('Multiple accounts refer to the same tenant; manual review required.');
                }
            }
            $report = [];
            foreach ($roots as $table) {
                $report[$table] = ['total' => DB::table($table)->count(), 'unassigned' => DB::table($table)->whereNull('property_id')->count()];
            }
            $this->table(['Table', 'Total', 'Unassigned'], collect($report)->map(fn ($v, $k) => [$k, $v['total'], $v['unassigned']])->all());
            $this->line('Legacy property: 1. Selected owner: '.$ownerId.'. Linked tenant accounts: '.$tenants->count());
            if (! $this->option('apply')) {
                $this->info('DRY RUN: no data changed. This command does not enable multi-property routes.');

                return self::SUCCESS;
            }
            if (! trim((string) $this->option('backup-reference'))) {
                throw new RuntimeException('--backup-reference is required for apply. Verify the backup before proceeding.');
            }
            if (! app()->environment('testing') && ! app()->isDownForMaintenance()) {
                throw new RuntimeException('Apply requires maintenance mode, stopped writers and a verified backup.');
            }
            DB::transaction(function () use ($ownerId, $roots, $tenants, $report) {
                $property = DB::table('rental_properties')->where('id', 1)->lockForUpdate()->first();
                if ($property->owner_user_id !== null && (int) $property->owner_user_id !== $ownerId) {
                    throw new RuntimeException('Ownership changed; review again.');
                }
                if (DB::table('property_migration_runs')->where('migration_key', 'legacy-property-1')->exists()) {
                    foreach ($roots as $table) {
                        if (DB::table($table)->whereNull('property_id')->exists()) {
                            throw new RuntimeException('Unassigned records appeared after migration. Stop legacy writers and review; no automatic reassignment.');
                        }
                    }
                    $this->info('Already applied. Nothing changed.');

                    return;
                }
                if (DB::table('property_memberships')->exists()) {
                    throw new RuntimeException('Memberships already exist without a completed migration. Review manually.');
                }
                DB::table('rental_properties')->where('id', 1)->update(['owner_user_id' => $ownerId]);
                foreach ($roots as $table) {
                    DB::table($table)->whereNull('property_id')->update(['property_id' => 1]);
                    if (DB::table($table)->count() !== $report[$table]['total']) {
                        throw new RuntimeException('Count changed during migration: '.$table);
                    }
                }
                DB::table('property_memberships')->insert(['property_id' => 1, 'user_id' => $ownerId, 'role' => 'owner', 'status' => 'approved',
                    'approved_by' => $ownerId, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                foreach ($tenants as $user) {
                    DB::table('property_memberships')->insert(['property_id' => 1, 'user_id' => $user->id, 'tenant_id' => $user->tenant_id,
                        'role' => 'member', 'status' => $user->is_active ? 'approved' : 'revoked', 'approved_by' => $ownerId,
                        'approved_at' => $user->is_active ? now() : null, 'revoked_at' => $user->is_active ? null : now(),
                        'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('property_migration_runs')->insert(['migration_key' => 'legacy-property-1', 'property_id' => 1,
                    'owner_user_id' => $ownerId, 'backup_reference' => mb_substr($this->option('backup-reference'), 0, 500),
                    'report' => json_encode($report, JSON_THROW_ON_ERROR), 'completed_at' => now()]);
            });
            $this->info('Assignment complete. Verify all modules before enabling multi-property access.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
