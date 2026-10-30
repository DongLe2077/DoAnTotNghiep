<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceAccessGrant;
use App\Models\InvoiceItem;
use App\Models\Occupancy;
use App\Models\Payment;
use App\Models\RentalGroup;
use App\Models\RentalProperty;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use App\Services\InvoicePayments;
use App\Services\PaymentCorrections;
use App\Services\PdfDocuments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class PdfDocumentTest extends TestCase
{
    use CreatesCashBookSchema;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 28)->setTime(12, 0));
        $this->createCashBookSchema();
        $this->owner = User::factory()->create(['username' => 'private_pdf_actor']);
        $this->actingAs($this->owner, 'web');
    }

    public function test_owner_can_download_real_pdf_with_private_headers_embedded_unicode_font_and_safe_filename(): void
    {
        [$invoice] = $this->invoice();
        $this->profile();
        $response = $this->get('/api/owner/invoices/'.$invoice->id.'/pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename="hoa-don-'.$invoice->id.'.pdf"');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $bytes = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('%%EOF', $bytes);
        $this->assertStringContainsString('/FontFile2', $bytes);
        $this->assertStringNotContainsString('/JavaScript', $bytes);
        $this->assertStringNotContainsString('/Launch', $bytes);
        $this->assertGreaterThan(10000, strlen($bytes));
    }

    public function test_tenant_pdf_checks_current_grant_and_receipt_invoice_binding(): void
    {
        [$invoice, $tenant] = $this->invoice();
        [$other] = $this->invoice();
        $payment = $this->payment($invoice, '800000.25');
        $foreign = $this->payment($other, '100');
        $this->actingAs($tenant, 'web');
        $this->get('/api/tenant/invoices/'.$invoice->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/api/tenant/invoices/'.$invoice->id.'/payments/'.$payment->id.'/pdf')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="phieu-thu-'.$payment->id.'.pdf"');
        $this->getJson('/api/tenant/invoices/'.$invoice->id.'/payments/'.$foreign->id.'/pdf')->assertNotFound();
        $this->getJson('/api/tenant/invoices/'.$other->id.'/pdf')->assertNotFound();
        $this->getJson('/api/owner/payments/'.$payment->id.'/pdf')->assertForbidden();
        InvoiceAccessGrant::query()->where('invoice_id', $invoice->id)->update(['revoked_at' => now()]);
        $this->getJson('/api/tenant/invoices/'.$invoice->id.'/pdf')->assertNotFound();
        $this->getJson('/api/tenant/invoices/'.$invoice->id.'/payments/'.$payment->id.'/pdf')->assertNotFound();
    }

    public function test_guests_inactive_accounts_and_wrong_roles_cannot_download_private_pdfs(): void
    {
        [$invoice, $tenant] = $this->invoice();
        $payment = $this->payment($invoice, '100');
        $this->getJson('/api/tenant/invoices/'.$invoice->id.'/pdf')->assertForbidden();
        $this->owner->update(['is_active' => false]);
        $this->getJson('/api/owner/invoices/'.$invoice->id.'/pdf')->assertUnauthorized();
        auth('web')->logout();
        foreach (['/api/owner/invoices/'.$invoice->id.'/pdf', '/api/owner/payments/'.$payment->id.'/pdf',
            '/api/tenant/invoices/'.$invoice->id.'/pdf'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $this->actingAs($tenant, 'web');
        $tenant->update(['is_active' => false]);
        $this->getJson('/api/tenant/invoices/'.$invoice->id.'/pdf')->assertUnauthorized();
    }

    public function test_snapshot_and_html_keep_exact_money_corrections_and_no_actor_or_request_secrets(): void
    {
        [$invoice, $tenant] = $this->invoice();
        $payment = $this->payment($invoice, '1000000');
        $receipt = app(InvoicePayments::class)->query()->findOrFail($payment->id);
        $result = app(PaymentCorrections::class)->correct($receipt, ['action' => 'replace', 'reason' => 'Đối chiếu: đã nhập dư tiền thực nhận',
            'version_token' => PaymentCorrections::version($receipt), 'idempotency_key' => 'secret-correction-pdf-key',
            'replacement' => ['amount' => '800000.25', 'paid_at' => '2026-10-28 10:00:00', 'payment_method' => 'bank_transfer']], $this->owner);
        $documents = app(PdfDocuments::class);
        $snapshot = $documents->snapshot($invoice->id, null, $tenant->id);
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->assertSame('800000.25', $snapshot['invoice']['paid_amount']);
        $this->assertSame('1999999.75', $snapshot['invoice']['outstanding_amount']);
        $this->assertTrue($snapshot['payments'][0]['voided']);
        $this->assertFalse($snapshot['payments'][1]['voided']);
        $this->assertSame($payment->id, $snapshot['payments'][1]['replaces_id']);
        $this->assertStringNotContainsString('private_pdf_actor', $json);
        $this->assertStringNotContainsString('secret-correction-pdf-key', $json);
        $this->assertStringNotContainsString('recorded_by', $json);
        $this->assertStringNotContainsString('version_token', $json);
        $html = $documents->html($snapshot);
        $this->assertStringContainsString('800.000,25 đ', $html);
        $this->assertStringContainsString('1.999.999,75 đ', $html);
        $this->assertStringContainsString('Đã hủy ghi nhận', $html);
        $this->assertStringContainsString('PT-'.$result['correction']->replacement_payment_id, $html);
        $voided = $documents->snapshot($invoice->id, $payment->id, $tenant->id);
        $this->assertStringContainsString('ĐÃ HỦY GHI NHẬN', $documents->html($voided));
    }

    public function test_cancelled_invoice_is_labelled_and_original_lines_remain_visible(): void
    {
        [$invoice] = $this->invoice();
        $invoice->cancelled_at = now();
        $invoice->save();
        $documents = app(PdfDocuments::class);
        $snapshot = $documents->snapshot($invoice->id);
        $this->assertSame('cancelled', $snapshot['invoice']['status']);
        $this->assertSame('0.00', $snapshot['invoice']['total_amount']);
        $this->assertSame('2800000.00', $snapshot['invoice']['original_amount']);
        $html = $documents->html($snapshot);
        $this->assertStringContainsString('HÓA ĐƠN ĐÃ HỦY', $html);
        $this->assertStringContainsString('Tiền phòng tháng 9', $html);
        $this->assertStringContainsString('Không dùng để yêu cầu thanh toán.', $html);
    }

    public function test_payment_instructions_only_appear_for_an_invoice_with_collectible_debt(): void
    {
        [$invoice] = $this->invoice();
        $this->profile();
        $documents = app(PdfDocuments::class);
        $this->assertStringContainsString('Thông tin nhận chuyển khoản', $documents->html($documents->snapshot($invoice->id)));
        $this->payment($invoice, '2800000');
        $paid = $documents->snapshot($invoice->id);
        $this->assertSame('paid', $paid['invoice']['status']);
        $this->assertStringNotContainsString('Thông tin nhận chuyển khoản', $documents->html($paid));
        $invoice->cancelled_at = now();
        $invoice->save();
        $this->assertStringNotContainsString('Thông tin nhận chuyển khoản', $documents->html($documents->snapshot($invoice->id)));
    }

    public function test_unconfigured_property_has_truthful_fallback_and_configured_html_escapes_user_content(): void
    {
        [$invoice] = $this->invoice();
        $documents = app(PdfDocuments::class);
        $this->assertStringContainsString('Thông tin nhà trọ chưa được cấu hình', $documents->html($documents->snapshot($invoice->id)));
        $this->profile(['name' => '<script>alert(1)</script>', 'address' => '<img src="https://example.invalid/private">', 'notes' => '<?php echo "unsafe"; ?>']);
        $html = $documents->html($documents->snapshot($invoice->id));
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<?php', $html);
        $this->assertStringContainsString('&lt;?php', $html);
    }

    public function test_render_uses_a_read_snapshot_without_late_database_queries_or_changing_original_content(): void
    {
        [$invoice] = $this->invoice();
        $property = $this->profile();
        $documents = app(PdfDocuments::class);
        $snapshot = $documents->snapshot($invoice->id);
        $property->update(['name' => 'Tên mới sau lần đọc']);
        $invoice->items()->first()->update(['description' => 'Nội dung đã đổi sau lần đọc']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $bytes = $documents->bytes($snapshot);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame([], $queries);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $html = $documents->html($snapshot);
        $this->assertStringContainsString('Nhà trọ An Tâm', $html);
        $this->assertStringNotContainsString('Tên mới sau lần đọc', $html);
        $this->assertStringNotContainsString('Nội dung đã đổi sau lần đọc', $html);
    }

    public function test_long_vietnamese_invoice_can_render_multiple_pages_with_repeated_table_header(): void
    {
        [$invoice] = $this->invoice();
        $this->profile();
        for ($index = 1; $index <= 65; $index++) {
            InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $invoice->occupancy_id,
                'item_type' => 'service', 'description' => 'Dịch vụ kiểm tra tiếng Việt dòng '.$index.' - '.str_repeat('Nội dung đối chiếu ', 6),
                'unit' => 'lần', 'quantity' => '1.125', 'unit_price' => '1000.1234']);
        }
        $documents = app(PdfDocuments::class);
        $snapshot = $documents->snapshot($invoice->id);
        $bytes = $documents->bytes($snapshot);
        preg_match_all('/\/Type\s*\/Page\b/', $bytes, $pages);
        $this->assertGreaterThanOrEqual(3, count($pages[0]));
        $this->assertLessThan(18, count($pages[0]));
        $this->assertCount(66, $snapshot['invoice']['items']);
        $this->assertStringContainsString('table-header-group', $documents->html($snapshot));
    }

    public function test_decimal_formatting_never_uses_binary_floats_and_preserves_unit_price_precision(): void
    {
        $this->assertSame('9.999.999.999.999.999,99', PdfDocuments::decimal('9999999999999999.99'));
        $this->assertSame('-120.000,25', PdfDocuments::decimal('-120000.25'));
        $this->assertSame('3.500,1234', PdfDocuments::decimal('3500.1234', 4));
        $this->assertSame('1,001', PdfDocuments::decimal('1.001', 3));
        $this->assertSame('0', PdfDocuments::decimal('0.00'));
    }

    private function profile(array $changes = []): RentalProperty
    {
        return RentalProperty::create(array_replace(['name' => 'Nhà trọ An Tâm', 'address' => '28 Đường Bình Yên, Thành phố Hồ Chí Minh',
            'contact_name' => 'Nguyễn Văn An', 'phone' => '0900000000', 'email' => 'nha-tro@example.test',
            'bank_name' => 'Ngân hàng dùng thử', 'bank_account_number' => '0000123456', 'bank_account_name' => 'NGUYEN VAN AN',
            'updated_by' => $this->owner->id], $changes));
    }

    private function payment(Invoice $invoice, string $amount): Payment
    {
        return app(InvoicePayments::class)->record($invoice, ['amount' => $amount, 'paid_at' => '2026-10-28 09:00:00',
            'payment_method' => 'cash', 'idempotency_key' => 'private-pdf-payment-'.Str::uuid()], $this->owner->id)['payment'];
    }

    private function invoice(): array
    {
        $tag = (string) Str::uuid();
        $room = Room::create(['room_number' => 'PDF-'.(Room::count() + 1), 'capacity' => 2, 'default_monthly_rent' => '2800000', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => 'PDF-'.$tag]);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '2800000']);
        $tenant = Tenant::create(['full_name' => 'Người thuê kiểm thử PDF', 'phone_number' => '0900000000', 'identity_number' => 'private-identity-'.(Tenant::count() + 1)]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);
        $assignment = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);
        $invoice = Invoice::create(['invoice_number' => 'HD-PDF-'.$tag, 'occupancy_id' => $occupancy->id, 'billing_month' => '2026-09-01',
            'representative_assignment_id' => $assignment->id, 'issued_by' => $this->owner->id, 'due_date' => '2026-10-30']);
        $invoice->issued_at = '2026-10-01 00:00:00';
        $invoice->save();
        InvoiceItem::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id, 'item_type' => 'rent',
            'description' => 'Tiền phòng tháng 9', 'unit' => 'tháng', 'quantity' => '1', 'unit_price' => '2800000']);
        InvoiceAccessGrant::create(['invoice_id' => $invoice->id, 'occupancy_id' => $occupancy->id, 'user_id' => $user->id,
            'representative_assignment_id' => $assignment->id, 'granted_by' => $this->owner->id, 'grant_reason' => 'Kiểm tra PDF']);

        return [$invoice, $user];
    }
}
