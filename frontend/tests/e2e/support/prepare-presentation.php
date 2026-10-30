<?php

use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\MeterReading;
use App\Models\Occupancy;
use App\Models\RentalGroup;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashBook;
use App\Services\InvoiceBilling;
use App\Services\InvoicePayments;
use App\Services\MaintenanceExpenses;
use App\Services\MaintenanceManagement;
use App\Services\MaintenancePhotos;
use App\Services\RentalPropertyProfile;
use App\Services\TenancyLifecycle;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

$frontend = dirname(__DIR__, 3);
$backend = dirname($frontend).DIRECTORY_SEPARATOR.'backend';
$root = $frontend.DIRECTORY_SEPARATOR.'.cache'.DIRECTORY_SEPARATOR.'presentation';
$database = $root.DIRECTORY_SEPARATOR.'owner-demo.sqlite';
$runId = getenv('E2E_RUN_ID');
$storage = $root.DIRECTORY_SEPARATOR.'runs'.DIRECTORY_SEPARATOR.$runId.DIRECTORY_SEPARATOR.'storage';
if (getenv('E2E_PRESENTATION') !== '1' || getenv('E2E_DEMO') !== '1' || getenv('APP_ENV') !== 'e2e'
    || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== $database
    || getenv('E2E_ISOLATED_PATH') !== $root || ! is_file($database)
    || ! preg_match('/^[a-f0-9]{16}$/D', $runId ?: '') || getenv('LARAVEL_STORAGE_PATH') !== $storage) {
    throw new RuntimeException('Presentation samples require their exact isolated SQLite/storage paths.');
}
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->useStoragePath($storage);
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== $database) {
    throw new RuntimeException('Unexpected presentation connection.');
}
if (! Schema::hasTable('presentation_seed')) {
    // Fixture metadata only, never an application migration or MySQL table.
    Schema::create('presentation_seed', function (Blueprint $table) {
        $table->unsignedInteger('id')->primary();
        $table->text('manifest');
    });
}
$manifest = DB::table('presentation_seed')->where('id', 1)->value('manifest');
if ($manifest === null) {
    $manifest = DB::transaction(function () use ($root) {
        $owner = User::where('username', 'admin')->firstOrFail();
        $single = User::where('username', 'user')->firstOrFail();
        $lifecycle = app(TenancyLifecycle::class);
        $billing = app(InvoiceBilling::class);
        $month = now()->startOfMonth();
        $older = $month->copy()->subMonths(2);
        $previous = $month->copy()->subMonth();
        $actors = [];
        foreach (['nhom_demo' => 'Nguyễn Minh An (mẫu)', 'thanhvien_demo' => 'Trần Bình (mẫu)', 'phong_demo' => 'Lê Hà (mẫu)'] as $username => $name) {
            $tenant = Tenant::create(['full_name' => $name, 'phone_number' => '0900000000']);
            $actors[$username] = User::create(['tenant_id' => $tenant->id, 'username' => $username,
                'password_hash' => Hash::make('123'), 'role' => 'tenant', 'is_active' => true]);
        }
        $services = [];
        foreach ([['TB-DIEN', 'Điện minh họa', 'kWh', 'metered', '3500.0000'],
            ['TB-NUOC', 'Nước minh họa', 'm³', 'metered', '15000.0000'],
            ['TB-MANG', 'Internet minh họa', 'phòng/tháng', 'per_room', '100000.0000']] as [$code, $name, $unit, $method, $price]) {
            $services[] = Service::create(['service_code' => $code, 'name' => $name, 'unit' => $unit,
                'billing_method' => $method, 'default_unit_price' => $price, 'is_active' => true]);
        }
        $occupancies = [];
        foreach (['TB-201' => [$actors['nhom_demo'], $actors['thanhvien_demo']], 'TB-202' => [$actors['phong_demo']]] as $number => $residents) {
            $room = Room::create(['room_number' => $number, 'capacity' => count($residents), 'default_monthly_rent' => '2800000.00', 'operational_status' => 'available']);
            $group = RentalGroup::create(['group_code' => 'NHOM-'.$number]);
            $occupancy = $lifecycle->checkIn(['room_id' => $room->id, 'rental_group_id' => $group->id,
                'tenant_ids' => array_map(fn ($user) => $user->tenant_id, $residents),
                'representative_tenant_id' => $residents[0]->tenant_id, 'started_at' => $older->format('Y-m-d H:i:s')]);
            foreach ($services as $service) {
                $lifecycle->applyService($occupancy, ['service_id' => $service->id, 'effective_from' => $older->format('Y-m-d H:i:s')]);
            }
            $occupancy->refresh();
            foreach ($occupancy->occupancyServices()->get() as $period) {
                if ($period->billing_method !== 'metered') {
                    continue;
                }
                $water = $period->service_id === $services[1]->id;
                $base = $water ? 20 : 100;
                $step = $water ? 5 : 30;
                foreach ([$older, $previous] as $index => $from) {
                    MeterReading::create(['occupancy_service_id' => $period->id, 'period_start' => $from->format('Y-m-d H:i:s'),
                        'period_end' => $from->copy()->addMonth()->format('Y-m-d H:i:s'), 'previous_reading' => (string) ($base + $step * $index),
                        'current_reading' => (string) ($base + $step * ($index + 1)), 'recorded_by' => $owner->id]);
                }
            }
            $occupancies[$number] = $occupancy;
        }
        foreach (['TB-203' => 'available', 'TB-204' => 'maintenance'] as $number => $status) {
            Room::create(['room_number' => $number, 'capacity' => 2, 'default_monthly_rent' => '2600000.00', 'operational_status' => $status]);
        }
        $invoices = [];
        foreach ([$older, $previous] as $index => $date) {
            $period = $date->format('Y-m');
            $preview = $billing->preview($occupancies['TB-201'], $period);
            if (! $preview['can_issue']) {
                throw new RuntimeException(implode(' ', $preview['blockers']));
            }
            $invoice = $billing->issue($occupancies['TB-201'], ['billing_month' => $period,
                'preview_token' => $preview['preview_token'], 'due_date' => now()->addDays(7)->format('Y-m-d')], $owner->id);
            $amount = $index === 0 ? (string) BigDecimal::of($preview['total_amount'])->toScale(2) : '500000.00';
            app(InvoicePayments::class)->record($invoice, ['amount' => $amount, 'paid_at' => now()->format('Y-m-d H:i:s'),
                'payment_method' => 'bank_transfer', 'idempotency_key' => 'presentation-paid-'.$invoice->id], $owner->id);
            if ($index === 1) {
                // Explicit past-deadline synthetic fixture, never backdate a real invoice.
                $invoice->update(['due_date' => now()->subDays(3)->format('Y-m-d')]);
            }
            $invoices[] = $invoice->id;
        }
        $singleOccupancy = Occupancy::whereHas('tenantStays', fn ($query) => $query->where('tenant_id', $single->tenant_id)->whereNull('moved_out_at'))->firstOrFail();
        $singleInvoice = Invoice::where('occupancy_id', $singleOccupancy->id)->firstOrFail();
        app(InvoicePayments::class)->record($singleInvoice, ['amount' => '1000000.00', 'paid_at' => now()->format('Y-m-d H:i:s'),
            'payment_method' => 'cash', 'idempotency_key' => 'presentation-single-payment'], $owner->id);
        $management = app(MaintenanceManagement::class);
        $report = $management->submit($occupancies['TB-201']->id, ['title' => 'Thay van nước bồn rửa (mẫu)',
            'description' => 'Van bị rò khi khóa. Đây là tình huống minh họa có ảnh và lịch sử xử lý.'], $actors['nhom_demo'])['request'];
        $path = $root.DIRECTORY_SEPARATOR.'sample-faucet.png';
        $image = imagecreatetruecolor(640, 400);
        $background = imagecolorallocate($image, 237, 244, 238);
        $green = imagecolorallocate($image, 35, 92, 75);
        $blue = imagecolorallocate($image, 55, 120, 167);
        imagefill($image, 0, 0, $background);
        imagefilledrectangle($image, 175, 150, 375, 190, $green);
        imagefilledrectangle($image, 175, 150, 215, 275, $green);
        imagefilledrectangle($image, 335, 150, 375, 225, $green);
        imagefilledrectangle($image, 260, 115, 285, 155, $green);
        imagefilledrectangle($image, 230, 100, 315, 120, $green);
        imagefilledellipse($image, 355, 255, 18, 30, $blue);
        imagefilledellipse($image, 355, 310, 14, 24, $blue);
        imagestring($image, 5, 40, 35, 'ANH MINH HOA - DU LIEU TRINH BAY', $green);
        imagestring($image, 4, 40, 350, 'Su co van nuoc - khong phai anh nguoi dung', $green);
        imagepng($image, $path);
        imagedestroy($image);
        app(MaintenancePhotos::class)->upload($report->id, [new UploadedFile($path, 'su-co-mau.png', 'image/png', null, true)], $actors['nhom_demo']);
        $report = $management->process($report->refresh(), ['status' => 'in_progress', 'resolution' => 'Đã tiếp nhận, kiểm tra van và chuẩn bị vật tư.',
            'version_token' => MaintenanceManagement::version($report->refresh())], $owner);
        $report = $management->process($report, ['status' => 'resolved', 'resolution' => 'Đã thay van, thử nước và bàn giao. Chi phí vật tư 150.000 đồng (mẫu).',
            'version_token' => MaintenanceManagement::version($report)], $owner);
        $cash = app(CashBook::class)->record(['transaction_type' => 'expense', 'amount' => '150000.00',
            'occurred_at' => now()->format('Y-m-d H:i:s'), 'description' => 'Vật tư thay van TB-201 (mẫu)',
            'reason' => 'Minh họa liên kết một khoản chi với sửa chữa', 'idempotency_key' => 'presentation-repair-cash'], $owner->id)['transaction'];
        app(MaintenanceExpenses::class)->link($report, ['cash_transaction_id' => $cash->id,
            'cash_version_token' => CashBook::version($cash), 'reason' => 'Vật tư cho yêu cầu thay van mẫu',
            'idempotency_key' => 'presentation-repair-link'], $owner->id);
        $open = MaintenanceRequest::where('submitted_by', $single->id)->firstOrFail();
        app(MaintenancePhotos::class)->upload($open->id, [new UploadedFile($path, 'su-co-mau.png', 'image/png', null, true)], $single);
        $profile = app(RentalPropertyProfile::class);
        $profile->save(['version_token' => $profile->data()['version_token'], 'name' => 'Nhà trọ Bình An (dữ liệu mẫu)',
            'address' => 'Địa chỉ minh họa phục vụ trình sản phẩm', 'contact_name' => 'Chủ trọ dùng thử', 'phone' => '0900000000',
            'email' => 'chutro@example.test', 'bank_name' => 'Ngân hàng minh họa', 'bank_account_number' => '0000000000',
            'bank_account_name' => 'TAI KHOAN MAU', 'notes' => 'Chứng từ dữ liệu giả dùng để thử chức năng.'], $owner->id);
        $data = ['version' => 1, 'seeded_at' => now()->format('Y-m-d H:i:s'), 'month' => $month->format('Y-m'),
            'invoice_months' => [$older->format('Y-m'), $previous->format('Y-m')], 'group_invoice_ids' => $invoices,
            'single_invoice_id' => $singleInvoice->id, 'resolved_report_id' => $report->id, 'open_report_id' => $open->id,
            'ready_occupancy_id' => $occupancies['TB-202']->id, 'ready_billing_month' => $previous->format('Y-m'),
            'single_room' => $singleOccupancy->room->room_number, 'group_room' => 'TB-201', 'ready_room' => 'TB-202',
            'group_total' => (string) BigDecimal::of($preview['total_amount'])->toScale(2), 'repair_cash_id' => $cash->id];
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        DB::table('presentation_seed')->insert(['id' => 1, 'manifest' => $json]);

        return $json;
    });
}
file_put_contents($root.DIRECTORY_SEPARATOR.'scenario.json', $manifest);
echo json_encode(['presentation_ready' => true, 'preserves_existing_edits' => true,
    'scenario' => json_decode($manifest, true, flags: JSON_THROW_ON_ERROR)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
