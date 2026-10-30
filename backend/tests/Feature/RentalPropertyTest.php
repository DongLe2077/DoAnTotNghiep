<?php

namespace Tests\Feature;

use App\Models\RentalProperty;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCatalogSchema;
use Tests\TestCase;

class RentalPropertyTest extends TestCase
{
    use CreatesCatalogSchema;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCatalogSchema();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');
    }

    public function test_empty_read_is_not_a_write_and_owner_can_configure_a_single_property(): void
    {
        $empty = $this->getJson('/api/owner/property')->assertOk()->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.name', null)->assertJsonPath('data.revision', 0);
        $this->assertSame(0, RentalProperty::count());
        $created = $this->putJson('/api/owner/property', $this->payload($empty->json('data.version_token')))
            ->assertOk()->assertJsonPath('data.configured', true)->assertJsonPath('data.name', 'Nhà trọ An Tâm')
            ->assertJsonPath('data.bank_account_number', '000123456789')->assertJsonPath('data.revision', 1);
        $this->assertSame(1, RentalProperty::count());
        $this->assertSame($this->owner->id, RentalProperty::first()->updated_by);
        $this->putJson('/api/owner/property', $this->payload($created->json('data.version_token')))->assertOk()->assertJsonPath('data.revision', 1);
    }

    public function test_stale_editor_cannot_overwrite_a_changed_property(): void
    {
        $token = $this->getJson('/api/owner/property')->json('data.version_token');
        $first = $this->putJson('/api/owner/property', $this->payload($token))->assertOk();
        $this->putJson('/api/owner/property', $this->payload($token, ['name' => 'Tên ghi đè']))->assertConflict();
        $this->assertSame('Nhà trọ An Tâm', RentalProperty::first()->name);
        $this->putJson('/api/owner/property', $this->payload($first->json('data.version_token'), ['name' => 'Nhà trọ Bình An']))
            ->assertOk()->assertJsonPath('data.name', 'Nhà trọ Bình An')->assertJsonPath('data.revision', 2);
        $this->assertSame(1, RentalProperty::count());
    }

    public function test_optional_contact_and_bank_can_be_cleared_without_changing_login_accounts(): void
    {
        $token = $this->getJson('/api/owner/property')->json('data.version_token');
        $first = $this->putJson('/api/owner/property', $this->payload($token))->assertOk();
        $before = $this->owner->fresh()->getAttributes();
        $this->putJson('/api/owner/property', $this->payload($first->json('data.version_token'), ['bank_name' => '',
            'bank_account_number' => ' ', 'bank_account_name' => null, 'email' => null, 'phone' => '', 'notes' => null]))
            ->assertOk()->assertJsonPath('data.bank_name', null)->assertJsonPath('data.bank_account_number', null)
            ->assertJsonPath('data.email', null)->assertJsonPath('data.phone', null);
        $this->assertSame($before, $this->owner->fresh()->getAttributes());
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_and_forged_values_are_rejected(array $change, string $field): void
    {
        $token = $this->getJson('/api/owner/property')->json('data.version_token');
        $this->putJson('/api/owner/property', $this->payload($token, $change))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, RentalProperty::count());
    }

    public static function invalidPayloads(): array
    {
        return [
            [['name' => '   '], 'name'], [['address' => ''], 'address'], [['email' => 'not-an-email'], 'email'],
            [['bank_account_number' => null], 'bank_account_number'], [['phone' => 'abc<script>'], 'phone'],
            [['updated_by' => 99], 'updated_by'], [['revision' => 99], 'revision'], [['notes' => str_repeat('x', 2001)], 'notes'],
        ];
    }

    public function test_tenant_guest_and_disabled_owner_cannot_read_or_change_configuration(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->getJson('/api/owner/property')->assertForbidden();
        $this->putJson('/api/owner/property', [])->assertForbidden();
        $this->getJson('/api/tenant/property')->assertNotFound();
        $this->owner->update(['is_active' => false]);
        $this->actingAs($this->owner, 'web');
        $this->getJson('/api/owner/property')->assertUnauthorized();
        auth('web')->logout();
        $this->getJson('/api/owner/property')->assertUnauthorized();
    }

    public function test_property_change_requires_csrf_with_browser_protection(): void
    {
        $token = $this->getJson('/api/owner/property')->json('data.version_token');
        $this->app->instance('env', 'local');
        $csrf = str_repeat('c', 40);
        $this->withSession(['_token' => $csrf]);
        $this->putJson('/api/owner/property', $this->payload($token))->assertStatus(419);
        $this->putJson('/api/owner/property', $this->payload($token), ['X-CSRF-TOKEN' => $csrf])->assertOk();
    }

    private function payload(string $token, array $changes = []): array
    {
        return array_replace(['name' => 'Nhà trọ An Tâm', 'address' => '12 Đường Bình An, Thành phố Hồ Chí Minh',
            'contact_name' => 'Nguyễn Văn An', 'phone' => '0900000000', 'email' => 'contact@example.test',
            'bank_name' => 'Ngân hàng mẫu', 'bank_account_number' => '000123456789', 'bank_account_name' => 'NGUYEN VAN AN',
            'notes' => 'Liên hệ vào giờ hành chính.', 'version_token' => $token], $changes);
    }
}
