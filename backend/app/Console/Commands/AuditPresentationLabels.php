<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditPresentationLabels extends Command
{
    protected $signature = 'rental:audit-presentation-labels {--normalize-local-property-names : Remove known trailing labels from property names on a local database}';

    protected $description = 'List records with presentation labels for review without deleting records';

    private const MARKERS = '/(?:dữ liệu mẫu|bản (?:xem |dùng )?thử|dùng thử|minh họa|nghiệm thu|\(mẫu\)|(?<!\p{L})(?:demo|test)(?!\p{L}))/iu';

    private const MARKERS_PLAIN = '/(?:du lieu mau|dung thu|ban xem thu|minh hoa|nghiem thu|nghiemthu_payos|nt-payos|\(mau\))/i';

    private const COLUMNS = [
        'rental_properties' => ['name', 'address', 'contact_name', 'notes'],
        'rooms' => ['room_number'],
        'tenants' => ['full_name'],
        'users' => ['username', 'full_name'],
        'rental_groups' => ['group_code'],
        'services' => ['name', 'service_code'],
        'room_listings' => ['title', 'description', 'fee_notes', 'amenities'],
        'maintenance_requests' => ['title', 'description', 'resolution'],
        'announcements' => ['title', 'body'],
        'app_notifications' => ['data'],
        'invoices' => ['invoice_number'],
        'invoice_items' => ['description'],
        'cash_transactions' => ['description', 'reason'],
        'cash_transaction_histories' => ['reason'],
        'invoice_changes' => ['reason'],
        'payments' => ['note', 'notes', 'description'],
        'payment_corrections' => ['reason'],
        'room_requests' => ['note', 'owner_note'],
        'checkout_requests' => ['note', 'rejection_reason'],
    ];

    public function handle(): int
    {
        if ($this->option('normalize-local-property-names')) {
            if (! app()->environment(['local', 'testing', 'e2e'])) {
                $this->error('Chỉ áp dụng đổi tên tự động trên bản local.');

                return self::FAILURE;
            }

            $changed = DB::transaction(function () {
                $count = 0;
                foreach (DB::table('rental_properties')->orderBy('id')->lockForUpdate()->get(['id', 'name']) as $property) {
                    $name = trim(preg_replace('/\h*\((?:dữ liệu mẫu|mẫu|minh họa|dùng thử|demo|test)\)\h*$/iu', '', $property->name));
                    if ($name === '' || $name === $property->name) {
                        continue;
                    }
                    DB::table('rental_properties')->where('id', $property->id)->update([
                        'name' => $name,
                        'revision' => DB::raw('revision + 1'),
                        'updated_at' => now(),
                    ]);
                    $count++;
                }

                return $count;
            });
            $this->info("Đã chuẩn hóa tên khu local: {$changed}.");
        }

        $count = 0;
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $availableColumns = Schema::getColumnListing($table);
            $columns = array_values(array_intersect($columns, $availableColumns));
            if ($columns === []) {
                continue;
            }
            $identity = in_array('property_id', $availableColumns, true) ? ['id', 'property_id'] : ['id'];
            DB::table($table)->select([...$identity, ...$columns])->chunkById(200, function ($rows) use ($columns, $table, &$count) {
                foreach ($rows as $row) {
                    foreach ($columns as $column) {
                        $value = (string) $row->$column;
                        if (preg_match(self::MARKERS, $value, $match) || preg_match(self::MARKERS_PLAIN, Str::ascii($value), $match)) {
                            $property = isset($row->property_id) ? ' · khu #'.$row->property_id : '';
                            $this->line($table.' #'.$row->id.$property.' · '.$column.' · '.$match[0]);
                            $count++;
                        }
                    }
                }
            });
        }
        $this->info("Có {$count} trường cần xem lại.");

        return self::SUCCESS;
    }
}
