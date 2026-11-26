<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeletionPlan
{
    public const TARGETS = [
        'rooms' => ['rooms', 'room_number'],
        'tenants' => ['tenants', 'full_name'],
        'groups' => ['rental_groups', 'group_code'],
        'services' => ['services', 'name'],
        'stays' => ['occupancies', null],
        'invoices' => ['invoices', 'invoice_number'],
        'maintenance' => ['maintenance_requests', 'title'],
        'cash' => ['cash_transactions', 'description'],
        'announcements' => ['announcements', 'title'],
    ];

    public const LABELS = [
        'rooms' => 'Phòng', 'tenants' => 'Hồ sơ người thuê', 'rental_groups' => 'Nhóm thuê',
        'services' => 'Dịch vụ', 'occupancies' => 'Đợt thuê', 'invoices' => 'Hóa đơn',
        'occupancy_services' => 'Kỳ dịch vụ', 'meter_readings' => 'Kỳ chỉ số',
        'maintenance_requests' => 'Báo hỏng', 'payments' => 'Phiếu thu',
        'announcements' => 'Thông báo chung', 'cash_transactions' => 'Khoản thu chi',
        'tenant_stays' => 'Lượt cư trú', 'users' => 'Tài khoản', 'maintenance_photos' => 'Ảnh báo hỏng',
        'checkout_requests' => 'Yêu cầu trả phòng',
    ];

    /**
     * Collect affected records in dependency order. Edges follow the billing and tenancy
     * schema, including links represented by composite foreign keys in MariaDB.
     */
    public function collect(string $type, int $id): array
    {
        [$root, $labelColumn] = self::TARGETS[$type];
        $record = \App\Services\Tenancy\PropertyDatabase::table($root)->where('id', $id)->lockForUpdate()->first();
        abort_if($record === null, 404, 'Mục này không còn tồn tại.');
        $ids = [$root => [$id]];

        $this->related($ids, 'occupancies', 'room_id', 'rooms');
        $this->related($ids, 'occupancies', 'rental_group_id', 'rental_groups');
        if (!app(\App\Services\Tenancy\PropertyContext::class)->enabled()) $this->related($ids, 'users', 'tenant_id', 'tenants');
        if (! empty($ids['users'])) {
            abort_if(\App\Services\Tenancy\PropertyDatabase::table('users')->whereIn('id', $ids['users'])->where('role', '!=', 'tenant')->exists(),
                409, 'Hồ sơ này liên kết với tài khoản chủ trọ.');
        }
        $this->related($ids, 'tenant_stays', 'tenant_id', 'tenants');
        // An open stay without occupants blocks the room from being rented again.
        if ($type === 'tenants' && ! empty($ids['tenant_stays'])) {
            $emptyStays = \App\Services\Tenancy\PropertyDatabase::table('occupancies')->whereNull('ended_at')
                ->whereIn('id', \App\Services\Tenancy\PropertyDatabase::table('tenant_stays')->select('occupancy_id')
                    ->whereIn('id', $ids['tenant_stays'])->whereNull('moved_out_at'))
                ->whereNotIn('id', \App\Services\Tenancy\PropertyDatabase::table('tenant_stays')->select('occupancy_id')
                    ->where('tenant_id', '!=', $id)->whereNull('moved_out_at'))
                ->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $this->include($ids, 'occupancies', $emptyStays);
        }
        $this->related($ids, 'tenant_stays', 'occupancy_id', 'occupancies');
        $this->related($ids, 'checkout_requests', 'requester_stay_id', 'tenant_stays');
        $this->related($ids, 'checkout_requests', 'occupancy_id', 'occupancies');
        $this->related($ids, 'checkout_requests', 'requested_by', 'users');
        $this->related($ids, 'representative_assignments', 'tenant_stay_id', 'tenant_stays');
        $this->related($ids, 'occupancy_services', 'occupancy_id', 'occupancies');
        $this->related($ids, 'occupancy_services', 'service_id', 'services');
        $this->related($ids, 'meter_readings', 'occupancy_service_id', 'occupancy_services');

        $this->related($ids, 'invoices', 'occupancy_id', 'occupancies');
        $this->related($ids, 'invoices', 'representative_assignment_id', 'representative_assignments');
        $this->related($ids, 'invoice_items', 'occupancy_service_id', 'occupancy_services');
        $this->related($ids, 'invoice_items', 'meter_reading_id', 'meter_readings');
        if (! empty($ids['invoice_items'])) {
            $this->include($ids, 'invoices', \App\Services\Tenancy\PropertyDatabase::table('invoice_items')->whereIn('id', $ids['invoice_items'])
                ->distinct()->pluck('invoice_id')->all());
        }
        $this->related($ids, 'invoice_items', 'invoice_id', 'invoices');
        $this->related($ids, 'payments', 'invoice_id', 'invoices');
        $this->related($ids, 'payment_corrections', 'invoice_id', 'invoices');
        $this->related($ids, 'invoice_changes', 'invoice_id', 'invoices');
        $this->related($ids, 'invoice_access_grants', 'invoice_id', 'invoices');
        $this->related($ids, 'invoice_access_grants', 'user_id', 'users');
        $this->related($ids, 'invoice_access_grants', 'representative_assignment_id', 'representative_assignments');
        $this->related($ids, 'invoice_payos_checkouts', 'invoice_id', 'invoices');
        $this->related($ids, 'invoice_payos_transfers', 'checkout_id', 'invoice_payos_checkouts');
        $this->related($ids, 'invoice_payos_transfers', 'payment_id', 'payments');

        $this->related($ids, 'maintenance_requests', 'occupancy_id', 'occupancies');
        $this->related($ids, 'maintenance_requests', 'representative_assignment_id', 'representative_assignments');
        $this->related($ids, 'maintenance_requests', 'submitted_by', 'users');
        $this->related($ids, 'maintenance_photos', 'maintenance_request_id', 'maintenance_requests');
        $this->related($ids, 'maintenance_photos', 'uploaded_by', 'users');
        $this->related($ids, 'maintenance_request_histories', 'maintenance_request_id', 'maintenance_requests');
        $this->related($ids, 'maintenance_expense_links', 'maintenance_request_id', 'maintenance_requests');
        $this->related($ids, 'maintenance_expense_links', 'cash_transaction_id', 'cash_transactions');
        $this->related($ids, 'maintenance_expense_histories', 'maintenance_expense_link_id', 'maintenance_expense_links');
        $this->related($ids, 'maintenance_expense_histories', 'maintenance_request_id', 'maintenance_requests');
        $this->related($ids, 'maintenance_expense_histories', 'cash_transaction_id', 'cash_transactions');
        $this->related($ids, 'cash_transaction_histories', 'cash_transaction_id', 'cash_transactions');
        $this->related($ids, 'cash_creation_requests', 'cash_transaction_id', 'cash_transactions');
        $this->related($ids, 'app_notifications', 'maintenance_request_id', 'maintenance_requests');
        $this->related($ids, 'app_notifications', 'invoice_id', 'invoices');
        $this->related($ids, 'app_notifications', 'user_id', 'users');
        $this->related($ids, 'app_notifications', 'announcement_id', 'announcements');
        $this->related($ids, 'meter_reading_requests', 'occupancy_id', 'occupancies');
        $this->related($ids, 'meter_reading_requests', 'occupancy_service_id', 'occupancy_services');
        $this->related($ids, 'meter_reading_requests', 'meter_reading_id', 'meter_readings');

        ksort($ids);

        return ['label' => $labelColumn ? $record->$labelColumn : '#'.$id, 'records' => $ids];
    }

    public function purge(array $ids): array
    {
        if (app(\App\Services\Tenancy\PropertyContext::class)->enabled() && !empty($ids['tenants'])) {
            DB::table('property_memberships')->where('property_id', app(\App\Services\Tenancy\PropertyContext::class)->requireId())
                ->whereIn('tenant_id', $ids['tenants'])->update(['tenant_id' => null]);
            DB::table('users')->whereIn('tenant_id', $ids['tenants'])->update(['tenant_id' => null]);
        }
        $paths = empty($ids['maintenance_photos']) ? [] : \App\Services\Tenancy\PropertyDatabase::table('maintenance_photos')
            ->whereIn('id', $ids['maintenance_photos'])->pluck('path')->all();
        if (! empty($ids['occupancies'])) {
            \App\Services\Tenancy\PropertyDatabase::table('occupancies')->whereIn('previous_occupancy_id', $ids['occupancies'])->update(['previous_occupancy_id' => null]);
        }
        if (! empty($ids['cash_transaction_histories'])) {
            \App\Services\Tenancy\PropertyDatabase::table('cash_transaction_histories')->whereIn('id', $ids['cash_transaction_histories'])->update(['reverted_history_id' => null]);
        }
        foreach ([
            'app_notifications', 'invoice_payos_transfers', 'invoice_payos_checkouts',
            'payment_corrections', 'payments', 'invoice_changes', 'invoice_access_grants', 'invoice_items', 'invoices',
            'maintenance_expense_histories', 'maintenance_expense_links', 'maintenance_request_histories', 'maintenance_photos',
            'maintenance_requests', 'cash_transaction_histories', 'cash_creation_requests', 'cash_transactions',
            'meter_reading_requests', 'meter_readings', 'occupancy_services', 'representative_assignments',
            'checkout_requests', 'tenant_stays', 'occupancies', 'users', 'tenants', 'rental_groups', 'rooms', 'services', 'announcements',
        ] as $table) {
            if (! empty($ids[$table])) {
                \App\Services\Tenancy\PropertyDatabase::table($table)->whereIn('id', $ids[$table])->delete();
            }
        }

        return $paths;
    }

    private function related(array &$ids, string $table, string $column, string $parent): void
    {
        if (empty($ids[$parent])) {
            return;
        }
        if (($table === 'checkout_requests' || str_starts_with($table, 'invoice_payos_')) && ! Schema::hasTable($table)) {
            return;
        }
        $this->include($ids, $table, \App\Services\Tenancy\PropertyDatabase::table($table)->whereIn($column, $ids[$parent])
            ->orderBy('id')->lockForUpdate()->pluck('id')->all());
    }

    private function include(array &$ids, string $table, array $found): void
    {
        if ($found === []) {
            return;
        }
        $ids[$table] = array_values(array_unique([...($ids[$table] ?? []), ...array_map('intval', $found)]));
        sort($ids[$table], SORT_NUMERIC);
    }
}
