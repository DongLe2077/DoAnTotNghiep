<?php

namespace App\Console\Commands;

use App\Services\AppNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class RemindInvoices extends Command
{
    protected $signature = 'rental:remind-invoices {--billing-month= : Chỉ nhắc tháng YYYY-MM} {--dry-run : Chỉ đếm hóa đơn ứng viên, không tạo thông báo}';

    protected $description = 'Create private in-app reminders for outstanding invoices, at most once per recipient/invoice/day';

    public function handle(AppNotifications $notifications): int
    {
        $filters = $this->option('billing-month') === null ? [] : ['billing_month' => $this->option('billing-month')];
        $validator = Validator::make($filters, ['billing_month' => ['sometimes', 'date_format:Y-m', 'regex:/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D']]);
        if ($validator->fails()) {
            $this->error('Tháng cần có định dạng YYYY-MM.');

            return self::FAILURE;
        }
        $this->line(json_encode($notifications->remindOutstanding($filters, (bool) $this->option('dry-run')), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
