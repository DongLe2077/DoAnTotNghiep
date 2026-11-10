<?php

namespace Database\Seeders;

use App\Models\RentalGroup;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Announcements;
use App\Services\CashBook;
use App\Services\InvoiceBilling;
use App\Services\InvoicePayments;
use App\Services\MaintenanceExpenses;
use App\Services\MaintenanceManagement;
use App\Services\MaintenancePhotos;
use App\Services\MeterReadingManager;
use App\Services\RentalPropertyProfile;
use App\Services\TenancyLifecycle;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CloudDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound('cloud-demo.install-authorized') || app('cloud-demo.install-authorized') !== true
            || ! config('deployment.cloud_demo_setup') || DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Use the guarded cloud installation command on an empty database.');
        }
        foreach (array_keys(config('rental.models')) as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Cloud demo seeding requires empty business tables.');
            }
        }
        $ownerPassword = bin2hex(random_bytes(12));
        $owner = User::create(['username' => 'admin', 'password_hash' => Hash::make($ownerPassword), 'role' => 'owner', 'is_active' => true]);
        $accounts = ['admin' => ['role' => 'owner', 'password' => $ownerPassword]];
        $users = [];
        foreach (['user' => 'Người thuê dùng thử', 'nhom_demo' => 'Nguyễn Minh An (mẫu)',
            'thanhvien_demo' => 'Trần Bình (mẫu)', 'phong_demo' => 'Lê Hà (mẫu)'] as $username => $name) {
            $password = bin2hex(random_bytes(12));
            $tenant = Tenant::create(['full_name' => $name, 'phone_number' => '0900000000']);
            $users[$username] = User::create(['tenant_id' => $tenant->id, 'username' => $username,
                'password_hash' => Hash::make($password), 'role' => 'tenant', 'is_active' => true]);
            $accounts[$username] = ['role' => 'tenant', 'password' => $password];
        }
        $month = now()->startOfMonth();
        $older = $month->copy()->subMonths(2);
        $previous = $month->copy()->subMonth();
        $services = [];
        foreach ([['DEMO-DIEN', 'Điện', 'kWh', 'metered', '3500.0000'],
            ['DEMO-NUOC', 'Nước', 'm³', 'metered', '15000.0000'],
            ['DEMO-MANG', 'Internet', 'phòng/tháng', 'per_room', '100000.0000']] as [$code, $name, $unit, $method, $price]) {
            $services[] = Service::create(['service_code' => $code, 'name' => $name, 'unit' => $unit,
                'billing_method' => $method, 'default_unit_price' => $price, 'is_active' => true]);
        }
        $lifecycle = app(TenancyLifecycle::class);
        $occupancies = [];
        foreach (['TEST-USER' => [$users['user']], 'TB-201' => [$users['nhom_demo'], $users['thanhvien_demo']],
            'TB-202' => [$users['phong_demo']]] as $number => $residents) {
            $room = Room::create(['room_number' => $number, 'capacity' => count($residents),
                'default_monthly_rent' => '2800000.00', 'operational_status' => 'available']);
            $group = RentalGroup::create(['group_code' => 'NHOM-'.$number]);
            $occupancy = $lifecycle->checkIn(['room_id' => $room->id, 'rental_group_id' => $group->id,
                'tenant_ids' => array_map(fn ($user) => $user->tenant_id, $residents),
                'representative_tenant_id' => $residents[0]->tenant_id, 'started_at' => $older->format('Y-m-d H:i:s')]);
            foreach ($services as $service) {
                $lifecycle->applyService($occupancy, ['service_id' => $service->id, 'effective_from' => $older->format('Y-m-d H:i:s')]);
            }
            foreach ($occupancy->occupancyServices()->get() as $period) {
                if ($period->billing_method !== 'metered') {
                    continue;
                }
                $water = $period->service_id === $services[1]->id;
                $base = $water ? 20 : 100;
                $step = $water ? 5 : 30;
                foreach ([$older, $previous] as $index => $from) {
                    app(MeterReadingManager::class)->create($occupancy, ['occupancy_service_id' => $period->id,
                        'period_start' => $from->format('Y-m-d H:i:s'), 'period_end' => $from->copy()->addMonth()->format('Y-m-d H:i:s'),
                        'previous_reading' => (string) ($base + $step * $index), 'current_reading' => (string) ($base + $step * ($index + 1))], $owner->id);
                }
            }
            $occupancies[$number] = $occupancy;
        }
        foreach (['TB-203' => 'available', 'TB-204' => 'maintenance'] as $number => $status) {
            Room::create(['room_number' => $number, 'capacity' => 2, 'default_monthly_rent' => '2600000.00', 'operational_status' => $status]);
        }
        $billing = app(InvoiceBilling::class);
        $invoices = [];
        foreach ([['TB-201', $older, null], ['TB-201', $previous, '500000.00'], ['TEST-USER', $previous, '1000000.00']] as [$number, $date, $paid]) {
            $preview = $billing->preview($occupancies[$number], $date->format('Y-m'));
            if (! $preview['can_issue']) {
                throw new RuntimeException(implode(' ', $preview['blockers']));
            }
            $invoice = $billing->issue($occupancies[$number], ['billing_month' => $date->format('Y-m'),
                'preview_token' => $preview['preview_token'], 'due_date' => now()->addDays(7)->format('Y-m-d')], $owner->id);
            app(InvoicePayments::class)->record($invoice, ['amount' => $paid ?? $preview['total_amount'],
                'paid_at' => now()->format('Y-m-d H:i:s'), 'payment_method' => 'bank_transfer',
                'idempotency_key' => 'cloud-demo-paid-'.$invoice->id], $owner->id);
            if ($number === 'TB-201' && $paid !== null) {
                // Synthetic overdue example; never used to backdate a real invoice.
                $invoice->update(['due_date' => now()->subDays(3)->format('Y-m-d')]);
            }
            $invoices[] = $invoice->id;
        }
        $profile = app(RentalPropertyProfile::class);
        $profile->save(['version_token' => $profile->data()['version_token'], 'name' => 'Nhà trọ RentBase (dữ liệu mẫu)',
            'address' => 'Địa chỉ minh họa phục vụ khóa luận', 'contact_name' => 'Chủ trọ dùng thử', 'phone' => '0900000000',
            'email' => 'chutro@example.test', 'bank_name' => 'Ngân hàng minh họa', 'bank_account_number' => '0000000000',
            'bank_account_name' => 'TAI KHOAN MAU', 'notes' => 'Dữ liệu giả dùng để thử chức năng.'], $owner->id);
        $management = app(MaintenanceManagement::class);
        $open = $management->submit($occupancies['TEST-USER']->id,
            ['title' => 'Vòi nước bị rò (mẫu)', 'description' => 'Tình huống mẫu để thử gửi ảnh và theo dõi xử lý.'], $users['user'])['request'];
        $resolved = $management->submit($occupancies['TB-201']->id,
            ['title' => 'Thay van nước bồn rửa (mẫu)', 'description' => 'Tình huống sửa chữa có chi phí và lịch sử xử lý.'], $users['nhom_demo'])['request'];
        $imagePath = storage_path('app/private/cloud-demo-faucet.png');
        $image = imagecreatetruecolor(640, 400);
        $background = imagecolorallocate($image, 237, 244, 238);
        $green = imagecolorallocate($image, 35, 92, 75);
        $blue = imagecolorallocate($image, 55, 120, 167);
        imagefill($image, 0, 0, $background);
        imagefilledrectangle($image, 175, 150, 375, 190, $green);
        imagefilledrectangle($image, 335, 150, 375, 225, $green);
        imagefilledellipse($image, 355, 255, 18, 30, $blue);
        imagestring($image, 5, 40, 35, 'DEMO - RENTBASE', $green);
        imagepng($image, $imagePath);
        imagedestroy($image);
        foreach ([[$open, $users['user']], [$resolved, $users['nhom_demo']]] as [$report, $actor]) {
            app(MaintenancePhotos::class)->upload($report->id, [new UploadedFile($imagePath, 'su-co-mau.png', 'image/png', null, true)], $actor);
        }
        foreach (['in_progress' => 'Đã tiếp nhận, kiểm tra van và chuẩn bị vật tư.', 'resolved' => 'Đã thay van và thử nước.'] as $status => $resolution) {
            $resolved = $management->process($resolved->refresh(), ['status' => $status, 'resolution' => $resolution,
                'version_token' => MaintenanceManagement::version($resolved)], $owner);
        }
        $cash = app(CashBook::class)->record(['transaction_type' => 'expense', 'amount' => '150000.00',
            'occurred_at' => now()->format('Y-m-d H:i:s'), 'description' => 'Vật tư thay van TB-201 (mẫu)',
            'reason' => 'Dữ liệu minh họa sửa chữa', 'idempotency_key' => 'cloud-demo-repair-cash'], $owner->id)['transaction'];
        app(MaintenanceExpenses::class)->link($resolved, ['cash_transaction_id' => $cash->id,
            'cash_version_token' => CashBook::version($cash), 'reason' => 'Vật tư thay van mẫu', 'idempotency_key' => 'cloud-demo-repair-link'], $owner->id);
        $announcement = ['title' => 'Chào mừng đến với RentBase', 'body' => 'Đây là dữ liệu minh họa phục vụ khóa luận. Bạn có thể thử xem hóa đơn, tải PDF và gửi yêu cầu sửa chữa.', 'audience' => 'all'];
        $preview = app(Announcements::class)->preview($announcement);
        app(Announcements::class)->publish($announcement + ['preview_token' => $preview['preview_token'], 'idempotency_key' => 'cloud-demo-welcome'], $owner->id);
        file_put_contents(storage_path('app/private/cloud-demo-access.json'), json_encode(['accounts' => $accounts], JSON_THROW_ON_ERROR));
        chmod(storage_path('app/private/cloud-demo-access.json'), 0600);
        file_put_contents(storage_path('app/private/cloud-demo-scenario.json'), json_encode([
            'invoice_months' => [$older->format('Y-m'), $previous->format('Y-m')], 'invoice_ids' => $invoices,
            'open_report_id' => $open->id, 'resolved_report_id' => $resolved->id,
            'ready_occupancy_id' => $occupancies['TB-202']->id, 'ready_billing_month' => $previous->format('Y-m'),
        ], JSON_THROW_ON_ERROR));
    }
}
