<?php

namespace App\Services\Tenancy;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Maps business tables to their owning property for model scopes and SQL reports. */
final class PropertyQueries
{
    public const ROOTS = ['rooms', 'tenants', 'rental_groups', 'services', 'cash_transactions', 'announcements', 'scheduled_deletions'];

    public const PARENTS = [
        'representative_assignments' => ['tenant_stay_id' => 'tenant_stays', 'occupancy_id' => 'occupancies'],
        'meter_readings' => ['occupancy_service_id' => 'occupancy_services'],
        'meter_reading_requests' => ['occupancy_id' => 'occupancies'],
        'invoice_changes' => ['invoice_id' => 'invoices'],
        'invoice_access_grants' => ['invoice_id' => 'invoices'],
        'payment_corrections' => ['invoice_id' => 'invoices'],
        'cash_creation_requests' => ['cash_transaction_id' => 'cash_transactions'],
        'maintenance_expense_links' => ['maintenance_request_id' => 'maintenance_requests', 'cash_transaction_id' => 'cash_transactions'],
        'maintenance_expense_histories' => ['maintenance_request_id' => 'maintenance_requests', 'cash_transaction_id' => 'cash_transactions'],
        'occupancies' => ['room_id' => 'rooms', 'rental_group_id' => 'rental_groups'],
        'tenant_stays' => ['occupancy_id' => 'occupancies', 'tenant_id' => 'tenants'],
        'checkout_requests' => ['occupancy_id' => 'occupancies', 'requester_stay_id' => 'tenant_stays'],
        'occupancy_services' => ['occupancy_id' => 'occupancies', 'service_id' => 'services'],
        'invoices' => ['occupancy_id' => 'occupancies'],
        'invoice_items' => ['invoice_id' => 'invoices'],
        'payments' => ['invoice_id' => 'invoices'],
        'invoice_payos_checkouts' => ['invoice_id' => 'invoices'],
        'invoice_payos_transfers' => ['checkout_id' => 'invoice_payos_checkouts'],
        'maintenance_requests' => ['occupancy_id' => 'occupancies'],
        'maintenance_photos' => ['maintenance_request_id' => 'maintenance_requests'],
        'maintenance_request_histories' => ['maintenance_request_id' => 'maintenance_requests'],
        'cash_transaction_histories' => ['cash_transaction_id' => 'cash_transactions'],
    ];

    public function owner(User $actor, int $propertyId, string $table): Builder
    {
        app(PropertyAccess::class)->owner($actor, $propertyId);

        return $this->scoped($propertyId, $table);
    }

    public function scoped(int $propertyId, string $table): Builder
    {
        if ($table === 'rental_properties') {
            return DB::table($table)->where('id', $propertyId);
        }
        if ($table === 'room_occupancy_summary') {
            return DB::table($table)->whereIn('room_id', $this->scoped($propertyId, 'rooms')->select('id'));
        }
        if (in_array($table, ['invoice_balances', 'active_invoice_access'], true)) {
            return DB::table($table)->whereIn('invoice_id', $this->scoped($propertyId, 'invoices')->select('id'));
        }
        if ($table === 'app_notifications') {
            return DB::table($table)->where(function ($query) use ($propertyId) {
                foreach (['invoice_id' => 'invoices', 'maintenance_request_id' => 'maintenance_requests', 'announcement_id' => 'announcements'] as $key => $parent) {
                    $query->orWhereIn($key, $this->scoped($propertyId, $parent)->select($parent.'.id'));
                }
            });
        }
        if (in_array($table, self::ROOTS, true)) {
            return DB::table($table)->where($table.'.property_id', $propertyId);
        }
        if (! isset(self::PARENTS[$table])) {
            throw new InvalidArgumentException('Table has no reviewed property path: '.$table);
        }
        $query = DB::table($table);
        foreach (self::PARENTS[$table] as $column => $parent) {
            $query->whereIn($table.'.'.$column, $this->scoped($propertyId, $parent)->select($parent.'.id'));
        }

        return $query;
    }
}
