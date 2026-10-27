<?php

use App\Models\MeterReading;
use App\Models\RentalGroup;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceBilling;
use App\Services\MaintenanceManagement;
use App\Services\TenancyLifecycle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$frontend = dirname(__DIR__, 3);
$backend = dirname($frontend).DIRECTORY_SEPARATOR.'backend';
$root = $frontend.DIRECTORY_SEPARATOR.'.cache'.DIRECTORY_SEPARATOR.'e2e';
$database = $root.DIRECTORY_SEPARATOR.'owner-demo.sqlite';
$runId = getenv('E2E_RUN_ID');
$storage = $root.DIRECTORY_SEPARATOR.'runs'.DIRECTORY_SEPARATOR.$runId.DIRECTORY_SEPARATOR.'storage';
if (getenv('E2E_DEMO') !== '1' || getenv('APP_ENV') !== 'e2e' || getenv('DB_CONNECTION') !== 'sqlite'
    || getenv('DB_DATABASE') !== $database || ! is_file($database) || ! preg_match('/^[a-f0-9]{16}$/D', $runId ?: '')
    || getenv('LARAVEL_STORAGE_PATH') !== $storage) {
    throw new RuntimeException('Only the isolated manual demo can receive sample accounts.');
}
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->useStoragePath($storage);
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== $database) {
    throw new RuntimeException('Unexpected demo connection.');
}
DB::statement('PRAGMA busy_timeout = 5000');
DB::statement('VACUUM INTO ?', [$root.'/demo-before-test-accounts-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite']);
if (! Schema::hasColumn('invoices', 'due_date')) {
    DB::transaction(fn () => (require $backend.'/database/migrations/2026_10_23_000001_add_invoice_controls.php')->up());
}
if (! Schema::hasTable('announcements')) {
    DB::transaction(fn () => (require $backend.'/database/migrations/2026_10_24_000001_create_announcements.php')->up());
}
if (! Schema::hasTable('maintenance_request_histories')) {
    DB::transaction(fn () => (require $backend.'/database/migrations/2026_10_25_000001_create_maintenance_request_histories.php')->up());
}

// Adopt previous demo uploads into stable manual-demo storage, matching each stored hash.
foreach (DB::table('maintenance_photos')->get() as $photo) {
    $relative = str_replace('\\', '/', $photo->path);
    if (str_contains($relative, '..') || ! preg_match('#^[A-Za-z0-9_/-]+\.[A-Za-z0-9]+$#D', $relative)) {
        throw new RuntimeException('Unexpected private photo path.');
    }
    if (Storage::disk('maintenance')->exists($relative)) {
        continue;
    }
    foreach (glob($root.'/runs/*/storage/app/private/maintenance/'.$relative) ?: [] as $candidate) {
        if (is_file($candidate) && hash_equals($photo->sha256, hash_file('sha256', $candidate))) {
            Storage::disk('maintenance')->put($relative, file_get_contents($candidate));
            break;
        }
    }
}

$newRoom = null;
DB::transaction(function () use (&$newRoom) {
    $admin = User::firstOrCreate(['username' => 'admin'], ['password_hash' => Hash::make('123'), 'role' => 'owner', 'is_active' => true]);
    if ($admin->role !== 'owner') {
        throw new RuntimeException('The demo admin name belongs to another role.');
    }
    $user = User::where('username', 'user')->first();
    if ($user !== null && $user->role !== 'tenant') {
        throw new RuntimeException('The demo user name belongs to another role.');
    }
    if (getenv('E2E_DEMO_RESET_PASSWORDS') === '1') {
        $admin->update(['password_hash' => Hash::make('123'), 'is_active' => true]);
        $user?->update(['password_hash' => Hash::make('123'), 'is_active' => true]);
    }
    if ($user !== null) {
        return;
    }
    $tenant = Tenant::create(['full_name' => 'Người thuê dùng thử', 'phone_number' => '0900000123']);
    $user = User::create(['username' => 'user', 'tenant_id' => $tenant->id, 'password_hash' => Hash::make('123'), 'role' => 'tenant', 'is_active' => true]);
    $number = 'TEST-USER';
    if (Room::where('room_number', $number)->exists()) {
        $number .= '-'.bin2hex(random_bytes(2));
    }
    $room = Room::create(['room_number' => $number, 'capacity' => 2, 'default_monthly_rent' => Room::orderBy('id')->value('default_monthly_rent') ?? '2800000.00', 'operational_status' => 'available']);
    $group = RentalGroup::create(['group_code' => 'USER-'.bin2hex(random_bytes(4))]);
    $from = now()->startOfMonth()->subMonth()->format('Y-m-d H:i:s');
    $until = now()->startOfMonth()->format('Y-m-d H:i:s');
    $lifecycle = app(TenancyLifecycle::class);
    $stay = $lifecycle->checkIn(['room_id' => $room->id, 'rental_group_id' => $group->id, 'tenant_ids' => [$tenant->id], 'representative_tenant_id' => $tenant->id, 'started_at' => $from]);
    $meterSources = [];
    foreach ([['DEMO-USER-DIEN', 'Điện dùng thử', 'kWh', 'metered', '3500', '100', '130'],
        ['DEMO-USER-NUOC', 'Nước dùng thử', 'm³', 'metered', '15000', '20', '25'],
        ['DEMO-USER-WIFI', 'Internet dùng thử', 'phòng/tháng', 'per_room', '100000', null, null]] as [$code, $name, $unit, $method, $price, $previous, $current]) {
        $service = Service::firstOrCreate(['service_code' => $code], ['name' => $name, 'unit' => $unit, 'billing_method' => $method, 'default_unit_price' => $price, 'is_active' => true]);
        $lifecycle->applyService($stay, ['service_id' => $service->id, 'effective_from' => $from]);
        if ($method === 'metered') {
            $meterSources[] = ['occupancy_service_id' => $stay->occupancyServices()->where('service_id', $service->id)->value('id'),
                'period_start' => $from, 'period_end' => $until, 'previous_reading' => $previous, 'current_reading' => $current, 'recorded_by' => $admin->id];
        }
    }
    foreach ($meterSources as $reading) {
        MeterReading::create($reading);
    }
    $month = substr($from, 0, 7);
    $billing = app(InvoiceBilling::class);
    $preview = $billing->preview($stay, $month);
    if (! $preview['can_issue']) {
        throw new RuntimeException('The new sample invoice is not ready.');
    }
    $billing->issue($stay, ['billing_month' => $month, 'preview_token' => $preview['preview_token'], 'due_date' => now()->addDays(7)->format('Y-m-d')], $admin->id);
    app(MaintenanceManagement::class)->submit($stay->id, ['title' => 'Vòi nước cần kiểm tra (mẫu)', 'description' => 'Nước rỉ ở van bồn rửa. Đây là yêu cầu mẫu để thử cập nhật sửa chữa.'], $user);
    $newRoom = $room->room_number;
});
echo json_encode(['demo_accounts_ready' => true, 'created_sample_room' => $newRoom, 'existing_demo_data_retained' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
