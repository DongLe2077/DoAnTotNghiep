<?php

// Only a disposable SQLite fixture is created; application migrations are not run.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$frontendPath = dirname(__DIR__, 3);
$backendPath = dirname($frontendPath).DIRECTORY_SEPARATOR.'backend';
$expectedRoot = $frontendPath.DIRECTORY_SEPARATOR.'.cache'.DIRECTORY_SEPARATOR.'e2e';
$demoMode = getenv('E2E_DEMO') === '1';
$expectedDatabase = $expectedRoot.DIRECTORY_SEPARATOR.($demoMode ? 'owner-demo.sqlite' : 'auth.sqlite');
$runId = getenv('E2E_RUN_ID');
$expectedStorage = $expectedRoot.DIRECTORY_SEPARATOR.'runs'.DIRECTORY_SEPARATOR.$runId.DIRECTORY_SEPARATOR.'storage';

if (getenv('APP_ENV') !== 'e2e' || getenv('DB_CONNECTION') !== 'sqlite'
    || getenv('E2E_ISOLATED_PATH') !== $expectedRoot || getenv('DB_DATABASE') !== $expectedDatabase
    || ! preg_match('/^[a-f0-9]{16}$/', $runId ?: '') || getenv('LARAVEL_STORAGE_PATH') !== $expectedStorage) {
    throw new RuntimeException('Refusing to prepare a database outside the isolated frontend E2E directory.');
}

require $backendPath.'/vendor/autoload.php';

if (file_put_contents($expectedDatabase, '') === false) {
    throw new RuntimeException('Unable to prepare the isolated E2E SQLite database.');
}

$app = require $backendPath.'/bootstrap/app.php';
$app->useStoragePath(getenv('LARAVEL_STORAGE_PATH'));
$app->make(Kernel::class)->bootstrap();

if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $expectedDatabase) {
    throw new RuntimeException('Laravel did not select the isolated E2E SQLite database.');
}

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('tenant_id')->nullable();
    $table->string('username')->unique();
    $table->string('password_hash');
    $table->string('role');
    $table->boolean('is_active')->default(true);
    $table->rememberToken();
    $table->timestamps();
});

foreach ([
    ['username' => 'e2e_owner', 'role' => 'owner'],
    ['username' => 'e2e_tenant', 'role' => 'tenant', 'tenant_id' => 1],
    ['username' => 'e2e_password_owner', 'role' => 'owner'],
    ['username' => 'e2e_disabled', 'role' => 'owner', 'is_active' => false],
    ['username' => 'e2e_unknown_role', 'role' => 'unexpected'],
    ['username' => 'e2e_throttled', 'role' => 'owner'],
    ['username' => 'e2e_notify_owner', 'role' => 'owner'],
    ['username' => 'e2e_notify_tenant', 'role' => 'tenant', 'tenant_id' => 2],
    ['username' => 'e2e_notify_empty', 'role' => 'tenant', 'tenant_id' => 3],
    ['username' => 'e2e_unlinked_tenant', 'role' => 'tenant'],
] as $account) {
    DB::table('users')->insert(array_merge([
        'tenant_id' => null,
        'password_hash' => Hash::make('E2e-Password-2077!'),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ], $account));
}

// Minimal relational fixture for the real notification visibility and subject APIs.
// These SQLite-only declarations never load or execute application migrations.
// All legacy authentication accounts intentionally keep an empty inbox.
DB::unprepared(<<<'SQL'
CREATE TABLE tenants (
    id INTEGER PRIMARY KEY AUTOINCREMENT, full_name VARCHAR(150) NOT NULL,
    phone_number VARCHAR(20) NOT NULL, identity_number VARCHAR(30) UNIQUE,
    created_at DATETIME, updated_at DATETIME
);
CREATE TABLE rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT, room_number VARCHAR(30) NOT NULL UNIQUE,
    capacity INTEGER NOT NULL, default_monthly_rent DECIMAL(18,2) NOT NULL,
    operational_status VARCHAR(20) NOT NULL DEFAULT 'available', created_at DATETIME, updated_at DATETIME
);
CREATE TABLE rental_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT, group_code VARCHAR(40) NOT NULL UNIQUE, created_at DATETIME NOT NULL
);
CREATE TABLE occupancies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rental_group_id INTEGER NOT NULL REFERENCES rental_groups(id),
    room_id INTEGER NOT NULL REFERENCES rooms(id), previous_occupancy_id INTEGER UNIQUE,
    started_at DATETIME NOT NULL, ended_at DATETIME, monthly_rent DECIMAL(18,2) NOT NULL,
    created_at DATETIME, updated_at DATETIME
);
CREATE TABLE tenant_stays (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    occupancy_id INTEGER NOT NULL REFERENCES occupancies(id), tenant_id INTEGER NOT NULL REFERENCES tenants(id),
    moved_in_at DATETIME NOT NULL, moved_out_at DATETIME, created_at DATETIME, updated_at DATETIME,
    UNIQUE(id, occupancy_id)
);
CREATE TABLE representative_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    occupancy_id INTEGER NOT NULL REFERENCES occupancies(id), tenant_stay_id INTEGER NOT NULL REFERENCES tenant_stays(id),
    started_at DATETIME NOT NULL, ended_at DATETIME, created_at DATETIME, UNIQUE(id, occupancy_id)
);
CREATE TABLE services (
    id INTEGER PRIMARY KEY AUTOINCREMENT, service_code VARCHAR(40) UNIQUE, name VARCHAR(100),
    unit VARCHAR(30), billing_method VARCHAR(20), default_unit_price DECIMAL(18,4), is_active BOOLEAN,
    created_at DATETIME, updated_at DATETIME
);
CREATE TABLE occupancy_services (
    id INTEGER PRIMARY KEY AUTOINCREMENT, occupancy_id INTEGER NOT NULL REFERENCES occupancies(id),
    service_id INTEGER NOT NULL REFERENCES services(id), effective_from DATETIME NOT NULL,
    effective_until DATETIME, unit_price DECIMAL(18,4), billing_method VARCHAR(20), unit VARCHAR(30), created_at DATETIME
);
CREATE TABLE meter_readings (
    id INTEGER PRIMARY KEY AUTOINCREMENT, occupancy_service_id INTEGER NOT NULL REFERENCES occupancy_services(id),
    period_start DATETIME, period_end DATETIME, previous_reading DECIMAL(15,3), current_reading DECIMAL(15,3),
    consumption DECIMAL(15,3) GENERATED ALWAYS AS (current_reading - previous_reading) STORED,
    recorded_by INTEGER REFERENCES users(id), recorded_at DATETIME
);
CREATE TABLE invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_number VARCHAR(255) NOT NULL UNIQUE,
    occupancy_id INTEGER NOT NULL REFERENCES occupancies(id), billing_month DATE NOT NULL,
    representative_assignment_id INTEGER NOT NULL REFERENCES representative_assignments(id),
    issued_by INTEGER NOT NULL REFERENCES users(id), issued_at DATETIME NOT NULL,
    UNIQUE(occupancy_id, billing_month), UNIQUE(id, occupancy_id)
);
CREATE TABLE invoice_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL REFERENCES invoices(id),
    occupancy_id INTEGER NOT NULL REFERENCES occupancies(id), item_type VARCHAR(20) NOT NULL,
    description VARCHAR(255) NOT NULL, unit VARCHAR(30) NOT NULL,
    quantity DECIMAL(15,3) NOT NULL, unit_price DECIMAL(18,4) NOT NULL,
    line_total DECIMAL(18,2) GENERATED ALWAYS AS (ROUND(quantity * unit_price, 2)) STORED,
    occupancy_service_id INTEGER REFERENCES occupancy_services(id), meter_reading_id INTEGER REFERENCES meter_readings(id),
    charge_period_start DATETIME, charge_period_end DATETIME
);
CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL REFERENCES invoices(id),
    amount DECIMAL(18,2) NOT NULL, paid_at DATETIME NOT NULL, payment_method VARCHAR(20) NOT NULL,
    idempotency_key VARCHAR(64) NOT NULL UNIQUE, recorded_by INTEGER NOT NULL REFERENCES users(id), recorded_at DATETIME NOT NULL
);
CREATE TABLE invoice_access_grants (
    id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL REFERENCES invoices(id),
    occupancy_id INTEGER NOT NULL REFERENCES occupancies(id), user_id INTEGER NOT NULL REFERENCES users(id),
    representative_assignment_id INTEGER NOT NULL REFERENCES representative_assignments(id),
    granted_by INTEGER NOT NULL REFERENCES users(id), granted_at DATETIME NOT NULL, revoked_at DATETIME,
    grant_reason VARCHAR(255) NOT NULL
);
CREATE TABLE maintenance_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT, occupancy_id INTEGER NOT NULL REFERENCES occupancies(id),
    submitted_by INTEGER NOT NULL REFERENCES users(id),
    representative_assignment_id INTEGER NOT NULL REFERENCES representative_assignments(id),
    title VARCHAR(255) NOT NULL, description TEXT NOT NULL, status VARCHAR(30) NOT NULL,
    resolution TEXT, submitted_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, revision INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE maintenance_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT, maintenance_request_id INTEGER NOT NULL REFERENCES maintenance_requests(id),
    uploaded_by INTEGER NOT NULL REFERENCES users(id), path VARCHAR(200) NOT NULL UNIQUE, sha256 CHAR(64) NOT NULL,
    mime_type VARCHAR(50) NOT NULL, byte_size INTEGER NOT NULL, created_at DATETIME NOT NULL,
    UNIQUE(maintenance_request_id, sha256)
);
CREATE TABLE app_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id),
    type VARCHAR(40) NOT NULL, invoice_id INTEGER REFERENCES invoices(id),
    maintenance_request_id INTEGER REFERENCES maintenance_requests(id), dedupe_key CHAR(64) NOT NULL UNIQUE,
    data TEXT NOT NULL, read_at DATETIME, created_at DATETIME NOT NULL
);
CREATE INDEX notifications_inbox_index ON app_notifications(user_id, read_at, id);
CREATE TABLE cash_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_type VARCHAR(20) NOT NULL,
    amount DECIMAL(18,2) NOT NULL, occurred_at DATETIME NOT NULL, description VARCHAR(255) NOT NULL,
    recorded_by INTEGER NOT NULL REFERENCES users(id), recorded_at DATETIME NOT NULL,
    revision INTEGER NOT NULL DEFAULT 0, deleted_at DATETIME
);
CREATE TABLE cash_transaction_histories (
    id INTEGER PRIMARY KEY AUTOINCREMENT, cash_transaction_id INTEGER NOT NULL REFERENCES cash_transactions(id),
    revision INTEGER NOT NULL, action VARCHAR(20) NOT NULL, before_data TEXT, after_data TEXT NOT NULL,
    performed_by INTEGER NOT NULL REFERENCES users(id), reason TEXT,
    reverted_history_id INTEGER REFERENCES cash_transaction_histories(id), created_at DATETIME NOT NULL,
    UNIQUE(cash_transaction_id, revision)
);
CREATE TABLE cash_creation_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT, idempotency_key VARCHAR(64) NOT NULL UNIQUE,
    cash_transaction_id INTEGER NOT NULL UNIQUE REFERENCES cash_transactions(id),
    recorded_by INTEGER NOT NULL REFERENCES users(id), request_data TEXT NOT NULL,
    request_fingerprint CHAR(64) NOT NULL, created_at DATETIME NOT NULL
);
CREATE VIEW active_invoice_access AS
SELECT g.user_id, g.invoice_id, g.occupancy_id, g.representative_assignment_id, g.granted_at
FROM invoice_access_grants g JOIN users u ON u.id = g.user_id
WHERE g.revoked_at IS NULL AND u.role = 'tenant' AND u.is_active = 1;
CREATE VIEW room_occupancy_summary AS
SELECT r.id AS room_id, r.room_number, r.capacity, r.operational_status,
       COALESCE(s.resident_count, 0) AS resident_count,
       CASE WHEN r.operational_status = 'maintenance' THEN 'maintenance'
            WHEN COALESCE(s.resident_count, 0) > 0 THEN 'occupied' ELSE 'vacant' END AS occupancy_status
FROM rooms r LEFT JOIN (
    SELECT o.room_id, COUNT(*) AS resident_count
    FROM occupancies o JOIN tenant_stays ts ON ts.occupancy_id = o.id
    WHERE o.ended_at IS NULL AND ts.moved_out_at IS NULL GROUP BY o.room_id
) s ON s.room_id = r.id;
CREATE VIEW invoice_balances AS
SELECT i.id AS invoice_id, i.invoice_number, i.occupancy_id, i.billing_month, i.issued_at,
       COALESCE(li.line_count, 0) AS item_count,
       COALESCE(li.total_amount, 0.00) AS total_amount,
       COALESCE(p.paid_amount, 0.00) AS paid_amount,
       COALESCE(li.total_amount, 0.00) - COALESCE(p.paid_amount, 0.00) AS outstanding_amount,
       CASE WHEN COALESCE(li.line_count, 0) = 0 THEN 'inconsistent'
            WHEN COALESCE(p.paid_amount, 0.00) > COALESCE(li.total_amount, 0.00) THEN 'inconsistent'
            WHEN COALESCE(p.paid_amount, 0.00) = COALESCE(li.total_amount, 0.00) THEN 'paid'
            WHEN COALESCE(p.paid_amount, 0.00) = 0 THEN 'unpaid'
            ELSE 'partially_paid' END AS payment_status
FROM invoices i
LEFT JOIN (SELECT invoice_id, COUNT(*) AS line_count, ROUND(SUM(line_total), 2) AS total_amount FROM invoice_items GROUP BY invoice_id) li ON li.invoice_id = i.id
LEFT JOIN (SELECT invoice_id, ROUND(SUM(amount), 2) AS paid_amount FROM payments GROUP BY invoice_id) p ON p.invoice_id = i.id;
SQL);

$fixtureTime = now()->format('Y-m-d H:i:s');
$ownerId = DB::table('users')->where('username', 'e2e_notify_owner')->value('id');
$tenantUserId = DB::table('users')->where('username', 'e2e_notify_tenant')->value('id');
$otherTenantUserId = DB::table('users')->where('username', 'e2e_tenant')->value('id');
foreach ([1 => 'Người thuê khác', 2 => 'Nguyễn Minh An', 3 => 'Người thuê chưa có thông báo'] as $id => $name) {
    DB::table('tenants')->insert(['id' => $id, 'full_name' => $name, 'phone_number' => '090000000'.$id,
        'identity_number' => '01234567890'.$id, 'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
}
DB::table('rooms')->insert(['id' => 1, 'room_number' => 'A101', 'capacity' => 2,
    'default_monthly_rent' => '2800000.00', 'operational_status' => 'available', 'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
DB::table('rental_groups')->insert(['id' => 1, 'group_code' => 'E2E-A101', 'created_at' => $fixtureTime]);
DB::table('occupancies')->insert(['id' => 1, 'rental_group_id' => 1, 'room_id' => 1,
    'started_at' => '2026-01-01 00:00:00', 'monthly_rent' => '2800000.00', 'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
DB::table('tenant_stays')->insert(['id' => 1, 'occupancy_id' => 1, 'tenant_id' => 2,
    'moved_in_at' => '2026-01-01 00:00:00', 'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
DB::table('representative_assignments')->insert(['id' => 1, 'occupancy_id' => 1, 'tenant_stay_id' => 1,
    'started_at' => '2026-01-01 00:00:00', 'created_at' => $fixtureTime]);
DB::table('invoices')->insert(['id' => 1, 'invoice_number' => 'HD-E2E-2026-10-A101', 'occupancy_id' => 1,
    'billing_month' => '2026-10-01', 'representative_assignment_id' => 1, 'issued_by' => $ownerId, 'issued_at' => $fixtureTime]);
DB::table('invoice_items')->insert(['invoice_id' => 1, 'occupancy_id' => 1, 'item_type' => 'rent',
    'description' => 'Tiền phòng tháng 10', 'unit' => 'tháng', 'quantity' => '1.000', 'unit_price' => '2800000.0000']);
DB::table('invoice_access_grants')->insert(['invoice_id' => 1, 'occupancy_id' => 1, 'user_id' => $tenantUserId,
    'representative_assignment_id' => 1, 'granted_by' => $ownerId, 'granted_at' => $fixtureTime,
    'grant_reason' => 'Quyền xem hóa đơn của tài khoản kiểm thử riêng']);
DB::table('maintenance_requests')->insert([
    ['id' => 1, 'occupancy_id' => 1, 'submitted_by' => $tenantUserId, 'representative_assignment_id' => 1,
        'title' => 'Vòi nước phòng A101 bị rò', 'description' => 'Nước rỉ ở van dưới bồn rửa.',
        'status' => 'in_progress', 'resolution' => 'Thợ sẽ đến kiểm tra chiều nay.',
        'submitted_at' => $fixtureTime, 'updated_at' => $fixtureTime, 'revision' => 1],
    ['id' => 2, 'occupancy_id' => 1, 'submitted_by' => $otherTenantUserId, 'representative_assignment_id' => 1,
        'title' => 'Yêu cầu riêng của người thuê khác', 'description' => 'Không được hiển thị cho người thuê kiểm thử thông báo.',
        'status' => 'submitted', 'resolution' => null, 'submitted_at' => $fixtureTime, 'updated_at' => $fixtureTime, 'revision' => 0],
]);

$addNotice = static function (int $userId, string $type, string $key, array $data, ?int $reportId = null,
    ?int $invoiceId = null, bool $read = false) use ($fixtureTime): void {
    DB::table('app_notifications')->insert(['user_id' => $userId, 'type' => $type,
        'invoice_id' => $invoiceId, 'maintenance_request_id' => $reportId,
        'dedupe_key' => hash('sha256', $key), 'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'read_at' => $read ? $fixtureTime : null, 'created_at' => $fixtureTime]);
};
for ($index = 1; $index <= 18; $index++) {
    $addNotice($ownerId, 'maintenance_submitted', 'owner-notice-'.$index,
        ['title' => 'Báo hỏng cần xem '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'body' => 'Người thuê phòng A101 đã gửi yêu cầu kiểm tra vòi nước.'], reportId: 1, read: $index === 1);
}
for ($index = 1; $index <= 16; $index++) {
    $addNotice($tenantUserId, 'maintenance_updated', 'tenant-notice-'.$index,
        ['title' => 'Cập nhật sửa chữa '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'body' => 'Chủ trọ đã tiếp nhận và sắp xếp kiểm tra.', 'status' => 'in_progress'], reportId: 1, read: $index === 1);
}
for ($index = 1; $index <= 2; $index++) {
    $addNotice($tenantUserId, 'invoice_reminder', 'tenant-invoice-'.$index,
        ['title' => 'Nhắc thanh toán hóa đơn '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'body' => 'Hóa đơn HD-E2E-2026-10-A101 còn khoản cần thanh toán.'], invoiceId: 1);
}
// Even a stored notice must remain hidden when current permissions reject its subject.
$addNotice($tenantUserId, 'maintenance_updated', 'tenant-invisible-notice',
    ['title' => 'Thông báo không được phép xem', 'body' => 'Riêng tư của người khác.'], reportId: 2);

DB::table('maintenance_requests')->insert(['id' => 3, 'occupancy_id' => 1, 'submitted_by' => $tenantUserId,
    'representative_assignment_id' => 1, 'title' => 'Đèn hành lang đã thay', 'description' => 'Đèn đã được thay bằng bóng mới.',
    'status' => 'resolved', 'resolution' => 'Đã kiểm tra và hoàn tất thay đèn.', 'submitted_at' => $fixtureTime,
    'updated_at' => $fixtureTime, 'revision' => 1]);
$photoBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQ9sAAAAASUVORK5CYII=');
Storage::disk('maintenance')->put('2/fixture.png', $photoBytes);
DB::table('maintenance_photos')->insert(['id' => 1, 'maintenance_request_id' => 2,
    'uploaded_by' => $otherTenantUserId, 'path' => '2/fixture.png', 'sha256' => hash('sha256', $photoBytes),
    'mime_type' => 'image/png', 'byte_size' => strlen($photoBytes), 'created_at' => $fixtureTime]);

if ($demoMode) {
    // These credentials and records belong exclusively to the disposable manual demo.
    $demoOwner = DB::table('users')->insertGetId(['username' => 'chutro_demo', 'password_hash' => Hash::make('Demo@2077'),
        'role' => 'owner', 'is_active' => true, 'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
    $demoTenant = DB::table('tenants')->insertGetId(['full_name' => 'Minh Hoàng (dữ liệu mẫu)', 'phone_number' => '0900000099',
        'identity_number' => null, 'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
    $demoUser = DB::table('users')->insertGetId(['username' => 'nguoithue_demo', 'tenant_id' => $demoTenant,
        'password_hash' => Hash::make('Demo@2077'), 'role' => 'tenant', 'is_active' => true,
        'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
    $demoRoom = DB::table('rooms')->insertGetId(['room_number' => 'B202', 'capacity' => 3,
        'default_monthly_rent' => '2800000.00', 'operational_status' => 'available', 'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
    foreach (['B203', 'B204'] as $number) DB::table('rooms')->insert(['room_number' => $number, 'capacity' => 3,
        'default_monthly_rent' => '2800000.00', 'operational_status' => 'available', 'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
    $demoGroup = DB::table('rental_groups')->insertGetId(['group_code' => 'NHOM-HOANG', 'created_at' => $fixtureTime]);
    $from = now()->startOfYear()->format('Y-m-d H:i:s');
    $demoOccupancy = DB::table('occupancies')->insertGetId(['room_id' => $demoRoom, 'rental_group_id' => $demoGroup,
        'started_at' => $from, 'monthly_rent' => '2800000.00', 'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
    $demoStay = DB::table('tenant_stays')->insertGetId(['occupancy_id' => $demoOccupancy, 'tenant_id' => $demoTenant,
        'moved_in_at' => $from, 'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
    $demoRep = DB::table('representative_assignments')->insertGetId(['occupancy_id' => $demoOccupancy, 'tenant_stay_id' => $demoStay,
        'started_at' => $from, 'created_at' => $fixtureTime]);
    $periodStart = now()->startOfMonth()->subMonth()->format('Y-m-d H:i:s');
    $periodEnd = now()->startOfMonth()->format('Y-m-d H:i:s');
    foreach ([['DEMO-DIEN', 'Điện', 'kWh', 'metered', '3500.0000', '100.000', '130.000'],
        ['DEMO-NUOC', 'Nước', 'm³', 'metered', '15000.0000', '20.000', '25.000'],
        ['DEMO-WIFI', 'Internet', 'phòng/tháng', 'per_room', '100000.0000', null, null]] as [$code, $name, $unit, $method, $price, $previous, $current]) {
        $service = DB::table('services')->insertGetId(['service_code' => $code, 'name' => $name, 'unit' => $unit,
            'billing_method' => $method, 'default_unit_price' => $price, 'is_active' => true,
            'created_at' => $fixtureTime, 'updated_at' => $fixtureTime]);
        $period = DB::table('occupancy_services')->insertGetId(['occupancy_id' => $demoOccupancy, 'service_id' => $service,
            'effective_from' => $from, 'unit_price' => $price, 'billing_method' => $method, 'unit' => $unit, 'created_at' => $fixtureTime]);
        if ($previous !== null) DB::table('meter_readings')->insert(['occupancy_service_id' => $period,
            'period_start' => $periodStart, 'period_end' => $periodEnd, 'previous_reading' => $previous,
            'current_reading' => $current, 'recorded_by' => $demoOwner, 'recorded_at' => $fixtureTime]);
    }
    $demoReport = DB::table('maintenance_requests')->insertGetId(['occupancy_id' => $demoOccupancy,
        'submitted_by' => $demoUser, 'representative_assignment_id' => $demoRep, 'title' => 'Vòi nước bếp bị rò',
        'description' => 'Nước rỉ ở van dưới bồn rửa. Nhờ chủ trọ sắp xếp kiểm tra.', 'status' => 'submitted',
        'resolution' => null, 'submitted_at' => $fixtureTime, 'updated_at' => $fixtureTime, 'revision' => 0]);
    $addNotice($demoOwner, 'maintenance_submitted', 'demo-owner-report', ['title' => 'Có yêu cầu báo hỏng mới', 'body' => 'Vòi nước bếp phòng B202 bị rò.'], reportId: $demoReport);
    app(\App\Services\CashBook::class)->record(['idempotency_key' => 'demo-seed-expense-001', 'transaction_type' => 'expense',
        'amount' => '150000.00', 'occurred_at' => $fixtureTime, 'description' => 'Mua vật tư sửa chữa (mẫu)', 'reason' => 'Khoản chi minh họa để thử chỉnh sửa và hoàn tác'], $demoOwner);
    app(\App\Services\CashBook::class)->record(['idempotency_key' => 'demo-seed-income-001', 'transaction_type' => 'other_income',
        'amount' => '50000.00', 'occurred_at' => $fixtureTime, 'description' => 'Thu khác (mẫu)', 'reason' => 'Dữ liệu cho bản chạy thử'], $demoOwner);
}

(require $backendPath.'/database/migrations/2026_10_23_000001_add_invoice_controls.php')->up();
(require $backendPath.'/database/migrations/2026_10_24_000001_create_announcements.php')->up();
(require $backendPath.'/database/migrations/2026_10_25_000001_create_maintenance_request_histories.php')->up();
if (! $demoMode) {
    // An explicit synthetic past deadline exercises overdue UI without backdating live invoices.
    DB::table('invoices')->where('id', 1)->update(['due_date' => now()->subDays(2)->format('Y-m-d')]);
}
fwrite(STDOUT, "Isolated E2E auth fixtures ready (SQLite; real CSRF enabled).\n");
