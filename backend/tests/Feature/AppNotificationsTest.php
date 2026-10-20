<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceItem;
use App\Models\MaintenanceRequest;
use App\Models\Occupancy;
use App\Models\Payment;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use App\Services\AppNotifications;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class AppNotificationsTest extends TestCase
{
    use CreatesBillingSchema;

    private User $owner;

    private User $author;

    private Occupancy $occupancy;

    private RepresentativeAssignment $rep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(12, 0));
        $this->createBillingSchema();
        $this->owner = User::factory()->create();
        $tenant = Tenant::create(['full_name' => 'Người thuê', 'phone_number' => '0900000000']);
        $this->author = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
        $room = Room::create(['room_number' => 'NOTIFY', 'capacity' => 2, 'default_monthly_rent' => '100', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => 'NOTIFY']);
        $this->occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '100']);
        $stay = TenantStay::create(['occupancy_id' => $this->occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);
        $this->rep = RepresentativeAssignment::create(['occupancy_id' => $this->occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);
        $this->actingAs($this->owner, 'web');
    }

    public function test_new_report_notifies_active_owners_once_and_processing_notifies_author(): void
    {
        $otherOwner = User::factory()->create();
        User::factory()->create(['is_active' => false]);
        $this->actingAs($this->author, 'web');
        $payload = ['title' => 'Rò nước', 'description' => 'Báo sự cố'];
        $created = $this->postJson('/api/tenant/occupancies/'.$this->occupancy->id.'/maintenance-requests', $payload)->assertCreated();
        $id = $created->json('data.id');
        $this->postJson('/api/tenant/occupancies/'.$this->occupancy->id.'/maintenance-requests', $payload)->assertOk();
        $this->assertSame(2, AppNotification::count());
        $this->actingAs($this->owner, 'web');
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'maintenance_submitted');
        $token = $this->getJson('/api/owner/maintenance-requests/'.$id)->json('data.version_token');
        $progress = $this->patchJson('/api/owner/maintenance-requests/'.$id, ['status' => 'in_progress', 'resolution' => 'Đang chờ thợ', 'version_token' => $token])->assertOk();
        $this->patchJson('/api/owner/maintenance-requests/'.$id, ['status' => 'in_progress', 'resolution' => 'Đang chờ thợ',
            'version_token' => $progress->json('data.version_token')])->assertOk();
        $this->assertSame(1, AppNotification::where('user_id', $this->author->id)->count());
        $this->patchJson('/api/owner/maintenance-requests/'.$id, ['status' => 'resolved', 'resolution' => 'Đã thay van',
            'version_token' => $progress->json('data.version_token')])->assertOk();
        $this->actingAs($this->author, 'web');
        $this->getJson('/api/notifications')->assertJsonCount(2, 'data')->assertJsonPath('data.0.data.status', 'resolved')
            ->assertJsonPath('data.0.data.body', 'Đã thay van');
        $this->actingAs($otherOwner, 'web');
        $this->getJson('/api/notifications')->assertJsonCount(1, 'data');
    }

    public function test_invoice_reminder_has_one_recipient_per_day_and_new_day_can_repeat(): void
    {
        $invoice = $this->invoice();
        $path = '/api/owner/invoices/'.$invoice->id.'/reminder';
        $first = $this->postJson($path)->assertCreated()->assertJsonPath('replayed', false);
        $this->postJson($path)->assertOk()->assertJsonPath('replayed', true)
            ->assertJsonPath('data.notification_id', $first->json('data.notification_id'));
        $this->travel(1)->days();
        $this->postJson($path)->assertCreated();
        $this->assertSame(2, AppNotification::count());
        $this->actingAs($this->author, 'web');
        $this->getJson('/api/notifications?type=invoice_reminder')->assertJsonCount(2, 'data')->assertJsonMissingPath('data.0.user_id');
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread_count', 2);
    }

    public function test_revocation_replacement_or_payment_hides_invoice_notice_and_unread_count(): void
    {
        $invoice = $this->invoice();
        $id = $this->postJson('/api/owner/invoices/'.$invoice->id.'/reminder')->json('data.notification_id');
        $grant = InvoiceAccessGrant::first();
        $this->actingAs($this->author, 'web');
        $this->getJson('/api/notifications')->assertJsonCount(1, 'data');
        $grant->update(['revoked_at' => now()]);
        $this->getJson('/api/notifications')->assertJsonCount(0, 'data');
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
        $this->patchJson('/api/notifications/'.$id.'/read')->assertNotFound();
        $grant->update(['revoked_at' => null]);
        Payment::create(['invoice_id' => $invoice->id, 'amount' => '100', 'paid_at' => now(), 'payment_method' => 'cash',
            'recorded_by' => $this->owner->id, 'idempotency_key' => (string) Str::uuid()]);
        $this->getJson('/api/notifications')->assertJsonCount(0, 'data');
        $this->actingAs($this->owner, 'web');
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/reminder')->assertConflict();
        $this->assertSame(1, AppNotification::count());
    }

    public function test_reminders_require_an_explicit_active_grant_and_valid_outstanding_invoice(): void
    {
        $invoice = $this->invoice(false);
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/reminder')->assertConflict();
        $this->assertSame(0, AppNotification::count());
        $this->grant($invoice);
        $this->author->update(['is_active' => false]);
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/reminder')->assertConflict();
        $this->author->update(['is_active' => true]);
        $invoice->items()->delete();
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/reminder')->assertConflict();
        $this->assertSame(0, AppNotification::count());
    }

    public function test_notifications_are_private_mark_read_is_idempotent_and_read_all_is_scoped(): void
    {
        $invoice = $this->invoice();
        $id = $this->postJson('/api/owner/invoices/'.$invoice->id.'/reminder')->json('data.notification_id');
        $report = $this->report();
        app(AppNotifications::class)->maintenanceSubmitted($report);
        $ownerNotice = AppNotification::where('user_id', $this->owner->id)->first();
        $this->actingAs($this->author, 'web');
        $this->patchJson('/api/notifications/'.$ownerNotice->id.'/read')->assertNotFound();
        $read = $this->patchJson('/api/notifications/'.$id.'/read')->assertOk()->assertJsonPath('data.is_read', true);
        $this->travel(1)->minutes();
        $this->patchJson('/api/notifications/'.$id.'/read')->assertJsonPath('data.read_at', $read->json('data.read_at'));
        $this->getJson('/api/notifications?unread_only=1')->assertJsonCount(0, 'data');
        $this->postJson('/api/notifications/read-all')->assertJsonPath('data.updated_count', 0);
        $this->assertNull($ownerNotice->fresh()->read_at);
        $this->actingAs($this->owner, 'web');
        $this->postJson('/api/notifications/read-all')->assertJsonPath('data.updated_count', 1);
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_old_owner_turned_tenant_cannot_read_report_notices_for_another_author(): void
    {
        app(AppNotifications::class)->maintenanceSubmitted($this->report());
        $this->owner->update(['role' => 'tenant']);
        $this->getJson('/api/notifications')->assertJsonCount(0, 'data');
    }

    public function test_report_author_keeps_updates_after_checkout_but_other_tenants_do_not_receive_them(): void
    {
        $report = $this->report();
        $this->occupancy->update(['ended_at' => '2026-03-01']);
        $this->occupancy->tenantStays()->update(['moved_out_at' => '2026-03-01']);
        $token = $this->getJson('/api/owner/maintenance-requests/'.$report->id)->json('data.version_token');
        $this->patchJson('/api/owner/maintenance-requests/'.$report->id, ['status' => 'resolved', 'resolution' => 'Đã sửa', 'version_token' => $token])->assertOk();
        $this->actingAs($this->author, 'web');
        $this->getJson('/api/notifications')->assertJsonCount(1, 'data');
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->getJson('/api/notifications')->assertJsonCount(0, 'data');
    }

    public function test_batch_command_dry_run_validation_and_deduplication(): void
    {
        $this->invoice();
        $this->artisan('rental:remind-invoices', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, AppNotification::count());
        $this->artisan('rental:remind-invoices', ['--billing-month' => '2026-01'])->assertSuccessful();
        $this->artisan('rental:remind-invoices')->assertSuccessful();
        $this->assertSame(1, AppNotification::count());
        $this->artisan('rental:remind-invoices', ['--billing-month' => '2026-13'])->assertFailed();
    }

    public function test_inbox_filters_authentication_csrf_and_owner_only_reminder(): void
    {
        $invoice = $this->invoice();
        foreach (['type=unknown', 'per_page=101', 'unread_only=bad', 'page=0'] as $query) {
            $this->getJson('/api/notifications?'.$query)->assertUnprocessable();
        }
        $this->actingAs($this->author, 'web');
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/reminder')->assertForbidden();
        $this->actingAs($this->owner, 'web');
        $this->app->instance('env', 'local');
        $this->postJson('/api/owner/invoices/'.$invoice->id.'/reminder')->assertStatus(419);
        $this->postJson('/api/notifications/read-all')->assertStatus(419);
        auth('web')->logout();
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    }

    private function report(): MaintenanceRequest
    {
        return MaintenanceRequest::create(['occupancy_id' => $this->occupancy->id, 'submitted_by' => $this->author->id,
            'representative_assignment_id' => $this->rep->id, 'title' => 'Báo hỏng', 'description' => 'Mô tả', 'status' => 'submitted']);
    }

    private function invoice(bool $grant = true): Invoice
    {
        $invoice = Invoice::create(['invoice_number' => (string) Str::uuid(), 'occupancy_id' => $this->occupancy->id,
            'billing_month' => '2026-01-01', 'representative_assignment_id' => $this->rep->id, 'issued_by' => $this->owner->id]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $this->occupancy->id, 'item_type' => 'rent',
            'description' => 'Tiền phòng', 'unit' => 'tháng', 'quantity' => '1', 'unit_price' => '100']);
        if ($grant) {
            $this->grant($invoice);
        }

        return $invoice;
    }

    private function grant(Invoice $invoice): void
    {
        InvoiceAccessGrant::create(['invoice_id' => $invoice->id, 'occupancy_id' => $invoice->occupancy_id,
            'user_id' => $this->author->id, 'representative_assignment_id' => $this->rep->id, 'granted_by' => $this->owner->id, 'grant_reason' => 'Kiểm thử']);
    }
}
