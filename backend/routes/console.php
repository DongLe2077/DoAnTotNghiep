<?php

use App\Services\InvoicePayos;
use App\Services\PayosGateway;
use App\Services\ScheduledDeletions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('rental:remind-invoices --overdue')->dailyAt('08:00')->timezone(config('app.timezone'))->withoutOverlapping();

Artisan::command('rental:reconcile-payos', function () {
    app(\App\Services\Tenancy\PropertyTasks::class)->each(function () {
        if (app(PayosGateway::class)->configured()) app(InvoicePayos::class)->reconcilePending();
    });
})->purpose('Kiểm tra lại các link payOS chưa có xác nhận');

Schedule::command('rental:reconcile-payos')->everyMinute()->withoutOverlapping(5)
    ->when(fn () => app(\App\Services\Tenancy\PropertyContext::class)->enabled() || app(PayosGateway::class)->configured());

Artisan::command('rental:purge-scheduled-deletions', function () {
    $failures = array_sum(app(\App\Services\Tenancy\PropertyTasks::class)->each(fn () => app(ScheduledDeletions::class)->run()));
    if ($failures > 0) {
        $this->error("Có {$failures} lịch xóa cần kiểm tra.");
    }

    return $failures > 0 ? 1 : 0;
})->purpose('Xóa dữ liệu đã hết thời hạn chờ 3 ngày');

Artisan::command('rental:archive-month', function () {
    app(\App\Services\Tenancy\PropertyTasks::class)->each(function () {
        $archive = app(\App\Services\MonthlyReportArchive::class);
        $archive->capture(now()->startOfMonth()->subMonth()->format('Y-m'));
    });
})->purpose('Lưu tổng hợp của tháng vừa kết thúc');

Schedule::command('rental:archive-month')->monthlyOn(1, '00:15')->timezone(config('app.timezone'))->withoutOverlapping();

Schedule::command('rental:purge-scheduled-deletions')->everyMinute()->withoutOverlapping();

if ($backupScript = config('deployment.backup_script')) {
    Schedule::exec('/usr/bin/php '.escapeshellarg($backupScript))->dailyAt('02:15')
        ->timezone(config('app.timezone'))->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/rental-backup.log'));
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
