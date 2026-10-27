<?php

namespace Tests\Feature;

use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class BusinessAcceptanceTest extends TestCase
{
    use CreatesCashBookSchema;

    public function test_complete_rental_journey_keeps_finance_photos_and_access_correct_after_transfer_checkout_and_final_payment(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 5)->setTime(12, 0));
        $this->createCashBookSchema();
        Storage::fake('maintenance');
        $owner = User::factory()->create();
        $this->actingAs($owner, 'web');
        $tenant = $this->postJson('/api/owner/tenants', ['full_name' => 'Người thuê nghiệm thu', 'phone_number' => '0900123456'])->assertCreated()->json('data');
        $account = $this->putJson('/api/owner/tenants/'.$tenant['id'].'/account', ['username' => 'acceptance_tenant', 'password' => 'Acceptance@2077', 'password_confirmation' => 'Acceptance@2077', 'is_active' => true])->assertOk()->json('data');
        $user = User::findOrFail($account['id']);
        $room = $this->postJson('/api/owner/rooms', ['room_number' => 'ACCEPT-01', 'capacity' => 2, 'default_monthly_rent' => '3000000'])->assertCreated()->json('data');
        $destination = $this->postJson('/api/owner/rooms', ['room_number' => 'ACCEPT-02', 'capacity' => 2])->assertCreated()->json('data');
        $group = $this->postJson('/api/owner/rental-groups', ['group_code' => 'ACCEPT-GROUP'])->assertCreated()->json('data');
        $stay = $this->postJson('/api/owner/occupancies', ['room_id' => $room['id'], 'rental_group_id' => $group['id'],
            'tenant_ids' => [$tenant['id']], 'representative_tenant_id' => $tenant['id'], 'started_at' => '2026-01-01 00:00:00'])->assertCreated()->json('data');
        foreach ([['ACCEPT-E', 'Điện', 'metered', 'kWh', '3500'], ['ACCEPT-I', 'Internet', 'per_room', 'phòng', '100000']] as [$code, $name, $method, $unit, $price]) {
            $service = $this->postJson('/api/owner/services', ['service_code' => $code, 'name' => $name, 'billing_method' => $method, 'unit' => $unit,
                'default_unit_price' => $price, 'is_active' => true])->assertCreated()->json('data');
            $this->postJson('/api/owner/occupancies/'.$stay['id'].'/services', ['service_id' => $service['id'], 'effective_from' => '2026-01-01 00:00:00'])->assertCreated();
        }
        $stay = $this->getJson('/api/owner/occupancies/'.$stay['id'])->assertOk()->json('data');
        $period = collect($stay['services'])->firstWhere('billing_method', 'metered');
        $meterData = ['occupancy_service_id' => $period['id'], 'period_start' => '2026-01-01 00:00:00', 'period_end' => '2026-02-01 00:00:00', 'previous_reading' => '100', 'current_reading' => '130'];
        $reading = $this->postJson('/api/owner/occupancies/'.$stay['id'].'/meter-readings', $meterData)->assertCreated()->json('data');
        $preview = $this->postJson('/api/owner/occupancies/'.$stay['id'].'/invoices/preview', ['billing_month' => '2026-01'])->assertOk()->assertJsonPath('data.can_issue', true)->json('data');
        $expected = (string) BigDecimal::of($stay['monthly_rent'])->plus('205000')->toScale(2);
        $this->assertSame($expected, $preview['total_amount']);
        $invoice = $this->postJson('/api/owner/occupancies/'.$stay['id'].'/invoices', ['billing_month' => '2026-01', 'preview_token' => $preview['preview_token'], 'due_date' => '2026-03-05'])->assertCreated()->json('data');
        $id = $invoice['id'];
        $this->putJson('/api/owner/occupancies/'.$stay['id'].'/meter-readings/'.$reading['id'], array_replace($meterData, ['current_reading' => '131']))->assertConflict();
        $adjustment = ['action' => 'adjustment', 'amount' => '-5000', 'reason' => 'Đối chiếu và giảm phí', 'version_token' => $invoice['version_token'], 'idempotency_key' => (string) Str::uuid()];
        $this->postJson('/api/owner/invoices/'.$id.'/changes', $adjustment)->assertOk();
        $this->postJson('/api/owner/invoices/'.$id.'/changes', $adjustment)->assertOk()->assertJsonPath('replayed', true);
        $receipt = ['amount' => '1000000', 'paid_at' => now()->format('Y-m-d H:i:s'), 'payment_method' => 'bank_transfer', 'idempotency_key' => (string) Str::uuid()];
        $this->postJson('/api/owner/invoices/'.$id.'/payments', $receipt)->assertCreated();
        $this->postJson('/api/owner/invoices/'.$id.'/payments', $receipt)->assertOk()->assertJsonPath('replayed', true);

        $this->withSession(['password_hash_web' => $user->getAuthPassword()])->actingAs($user, 'web');
        $this->getJson('/api/tenant/invoices/'.$id)->assertOk()->assertJsonPath('data.paid_amount', '1000000.00')->assertJsonCount(1, 'data.changes');
        $this->postJson('/api/owner/invoices/'.$id.'/changes', [])->assertForbidden();
        $report = $this->postJson('/api/tenant/occupancies/'.$stay['id'].'/maintenance-requests', ['title' => 'Vòi nước bị rò', 'description' => 'Cần kiểm tra van nước'])->assertCreated()->json('data');
        $image = UploadedFile::fake()->createWithContent('su-co.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQ9sAAAAASUVORK5CYII='));
        $this->post('/api/tenant/maintenance-requests/'.$report['id'].'/photos', ['photos' => [$image]], ['Accept' => 'application/json'])->assertOk();
        $this->withSession(['password_hash_web' => $owner->getAuthPassword()])->actingAs($owner, 'web');
        $report = $this->getJson('/api/owner/maintenance-requests/'.$report['id'])->assertOk()->json('data');
        $this->putJson('/api/owner/maintenance-requests/'.$report['id'], ['status' => 'resolved', 'resolution' => 'Đã thay van', 'version_token' => $report['version_token']])->assertOk();
        $expense = ['transaction_type' => 'expense', 'amount' => '150000', 'occurred_at' => now()->format('Y-m-d H:i:s'), 'description' => 'Thay van nước', 'idempotency_key' => (string) Str::uuid()];
        $this->postJson('/api/owner/cash-transactions', $expense)->assertCreated();
        $this->postJson('/api/owner/cash-transactions', $expense)->assertOk()->assertJsonPath('replayed', true);
        $this->travelTo(now()->addHour());
        $new = $this->postJson('/api/owner/occupancies/'.$stay['id'].'/transfer', ['room_id' => $destination['id'], 'transferred_at' => now()->format('Y-m-d H:i:s')])->assertCreated()->json('data');
        $this->travelTo(now()->addHour());
        $this->postJson('/api/owner/occupancies/'.$new['id'].'/checkout', ['ended_at' => now()->format('Y-m-d H:i:s')])->assertOk();
        $this->travelTo(now()->addDay());
        $remaining = (string) BigDecimal::of($expected)->minus('1005000')->toScale(2);
        $this->getJson('/api/owner/receivables/summary?due_status=overdue')->assertOk()->assertJsonPath('data.overdue_amount', $remaining);
        $this->getJson('/api/owner/dashboard?month=2026-03')->assertOk()->assertJsonPath('data.cash_flow.invoice_payment_total', '1000000.00')->assertJsonPath('data.cash_flow.expense_total', '150000.00');
        $this->postJson('/api/owner/invoices/'.$id.'/payments', ['amount' => $remaining, 'paid_at' => now()->format('Y-m-d H:i:s'), 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertCreated()->assertJsonPath('invoice.payment_status', 'paid');
        $this->withSession(['password_hash_web' => $user->getAuthPassword()])->actingAs($user, 'web');
        $this->getJson('/api/tenant/current-stay')->assertOk()->assertJsonPath('data', null);
        $this->getJson('/api/tenant/stays')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/tenant/invoices/'.$id)->assertOk()->assertJsonPath('data.due_status', 'paid')->assertJsonCount(2, 'data.payments');
        $this->getJson('/api/tenant/maintenance-requests/'.$report['id'])->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonCount(1, 'data.photos');
        $this->getJson('/api/notifications?type=maintenance_updated')->assertOk()->assertJsonCount(1, 'data');
    }
}
