<?php

use App\Services\InvoicePayos;
use App\Services\PayosGateway;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('rental:remind-invoices --overdue')->dailyAt('08:00')->timezone(config('app.timezone'))->withoutOverlapping();

Artisan::command('rental:reconcile-payos', function () {
    if (app(PayosGateway::class)->configured()) {
        app(InvoicePayos::class)->reconcilePending();
    }
})->purpose('Kiểm tra lại các link payOS chưa có xác nhận');

Schedule::command('rental:reconcile-payos')->everyMinute()->withoutOverlapping(5)
    ->when(fn () => app(PayosGateway::class)->configured());

if ($backupScript = config('deployment.backup_script')) {
    Schedule::exec('/usr/bin/php '.escapeshellarg($backupScript))->dailyAt('02:15')
        ->timezone(config('app.timezone'))->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/rental-backup.log'));
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
