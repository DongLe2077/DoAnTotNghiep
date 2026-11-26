<?php

namespace App\Console\Commands;

use App\Services\Tenancy\PropertyQueries;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditPropertyOwnership extends Command
{
    protected $signature = 'rental:audit-property-ownership';

    protected $description = 'Read-only ownership readiness and cross-property reference audit';

    public function handle(): int
    {
        if (! Schema::hasTable('property_memberships')) {
            $this->error('Ownership schema missing.');

            return self::FAILURE;
        }
        $problems = [];
        $properties = DB::table('rental_properties')->get();
        foreach ($properties as $property) {
            if (! $property->owner_user_id || ! DB::table('property_memberships')->where('property_id', $property->id)->where('user_id', $property->owner_user_id)->where('status', 'approved')->whereNull('revoked_at')->exists()) {
                $problems[] = 'Property '.$property->id.' has no approved owner.';
            }
        }
        foreach (PropertyQueries::ROOTS as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'property_id')) {
                $problems[] = $table.': missing schema';

                continue;
            }
            $count = DB::table($table)->whereNull('property_id')->orWhereNotIn('property_id', $properties->pluck('id'))->count();
            if ($count) {
                $problems[] = $table.': '.$count.' unassigned or orphan records';
            }
        }
        foreach (PropertyQueries::PARENTS as $table => $parents) {
            if (! Schema::hasTable($table)) {
                $problems[] = $table.': missing schema';

                continue;
            }
            $matched = 0;
            foreach ($properties as $property) {
                $matched += app(PropertyQueries::class)->scoped($property->id, $table)->count();
            }
            if ($matched !== DB::table($table)->count()) {
                $problems[] = $table.': orphan or cross-property relationships';
            }
        }
        $invalid = DB::table('property_memberships as m')->join('tenants as t', 't.id', '=', 'm.tenant_id')->whereColumn('m.property_id', '!=', 't.property_id')->count();
        if ($invalid) {
            $problems[] = 'Membership tenant mapping crosses properties.';
        }
        foreach (['rooms' => 'room_number', 'services' => 'service_code', 'rental_groups' => 'group_code', 'tenants' => 'identity_number'] as $table => $column) {
            $indexes = Schema::getIndexes($table);
            if (! collect($indexes)->contains(fn ($index) => $index['unique'] && $index['columns'] === ['property_id', $column])) {
                $problems[] = $table.': composite unique key missing';
            }
        }
        foreach ($problems as $problem) {
            $this->error($problem);
        }
        if ($problems) {
            return self::FAILURE;
        }
        $this->info('Ownership graph passed. Also verify payment channels, backup restore and HTTP acceptance before activation.');

        return self::SUCCESS;
    }
}
