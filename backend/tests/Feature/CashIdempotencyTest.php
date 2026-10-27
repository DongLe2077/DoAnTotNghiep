<?php

namespace Tests\Feature;

use App\Models\CashCreationRequest;
use App\Models\CashTransaction;
use App\Models\CashTransactionHistory;
use App\Models\User;
use App\Services\CashBook;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class CashIdempotencyTest extends TestCase
{
    use CreatesCashBookSchema;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(12, 0));
        $this->createCashBookSchema();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner, 'web');
    }

    public function test_same_request_returns_one_entry_one_audit_and_one_cash_flow_row(): void
    {
        $payload = $this->payload(['reason' => 'Tiền mua vật tư']);
        $first = $this->postJson('/api/owner/cash-transactions', $payload)->assertCreated()->assertJsonPath('replayed', false)->json('data');
        $this->travel(5)->minutes();
        $this->postJson('/api/owner/cash-transactions', $payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data', $first);
        $this->assertSame(1, CashTransaction::count());
        $this->assertSame(1, CashTransactionHistory::count());
        $this->assertSame(1, CashCreationRequest::count());
        $this->getJson('/api/owner/cash-flow/summary')->assertJsonPath('data.expense_total', '10.10')->assertJsonPath('data.entry_count', 1);
        $request = CashCreationRequest::firstOrFail();
        $this->assertSame($first['id'], $request->cashTransaction->id);
        $this->assertSame($this->owner->id, $request->recordedBy->id);
        $this->assertSame('Tiền mua vật tư', $request->request_data['reason']);
    }

    public function test_replay_compares_normalized_original_amount_and_nullable_reason(): void
    {
        $this->postJson('/api/owner/cash-transactions', $this->payload(['amount' => '00010.10']))->assertCreated();
        $this->postJson('/api/owner/cash-transactions', $this->payload(['amount' => 10.1, 'reason' => null]))->assertOk()->assertJsonPath('replayed', true);
        $this->postJson('/api/owner/cash-transactions', $this->payload(['reason' => '   ']))->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame('10.10', CashCreationRequest::firstOrFail()->request_data['amount']);
        $this->assertNull(CashCreationRequest::firstOrFail()->request_data['reason']);
        $this->assertSame(1, CashTransactionHistory::count());
    }

    #[DataProvider('changedPayloads')]
    public function test_reused_key_with_any_changed_original_field_returns_conflict(array $changes): void
    {
        $first = $this->postJson('/api/owner/cash-transactions', $this->payload())->assertCreated()->json('data');
        $this->postJson('/api/owner/cash-transactions', $this->payload($changes))->assertConflict()->assertJsonMissingPath('data');
        $this->assertSame('10.10', CashTransaction::findOrFail($first['id'])->amount);
        $this->assertSame(1, CashTransactionHistory::count());
        $this->assertSame(1, CashCreationRequest::count());
    }

    public static function changedPayloads(): array
    {
        return [
            'type' => [['transaction_type' => 'other_income']],
            'amount' => [['amount' => '10.11']],
            'time' => [['occurred_at' => '2026-03-01 10:00:01']],
            'description' => [['description' => 'Chi mua khóa khác']],
            'audit reason' => [['reason' => 'Lý do mới']],
        ];
    }

    public function test_different_owner_cannot_reuse_key_or_obtain_original_entry(): void
    {
        $this->postJson('/api/owner/cash-transactions', $this->payload())->assertCreated();
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/owner/cash-transactions', $this->payload())->assertConflict()->assertJsonMissingPath('data');
        $this->assertSame(1, CashTransaction::count());
        $this->assertSame($this->owner->id, CashCreationRequest::firstOrFail()->recorded_by);
    }

    public function test_new_keys_allow_intentionally_identical_transactions_and_case_is_significant(): void
    {
        foreach (['request-Abc123', 'request-abc123', 'another-key-123'] as $key) {
            $this->postJson('/api/owner/cash-transactions', $this->payload(['idempotency_key' => $key]))->assertCreated();
        }
        $this->postJson('/api/owner/cash-transactions', $this->payload(['idempotency_key' => 'request-Abc123']))->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame(3, CashTransaction::count());
        $this->assertSame(3, CashCreationRequest::count());
    }

    public function test_replay_after_edit_returns_current_entry_and_keeps_original_request_immutable(): void
    {
        $first = $this->postJson('/api/owner/cash-transactions', $this->payload(['reason' => 'Lý do ban đầu']))->assertCreated()->json('data');
        $requestBefore = CashCreationRequest::firstOrFail()->getAttributes();
        $edited = $this->patchJson('/api/owner/cash-transactions/'.$first['id'], ['version_token' => $first['version_token'],
            'transaction_type' => 'other_income', 'amount' => '25', 'description' => 'Sửa nội dung', 'reason' => 'Sửa nhầm'])->assertOk()->json('data');
        $this->postJson('/api/owner/cash-transactions', $this->payload(['reason' => 'Lý do ban đầu']))->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data', $edited);
        $this->postJson('/api/owner/cash-transactions', $this->payload(['transaction_type' => 'other_income', 'amount' => '25',
            'description' => 'Sửa nội dung', 'reason' => 'Sửa nhầm']))->assertConflict();
        $this->assertSame($requestBefore, CashCreationRequest::firstOrFail()->getAttributes());
        $this->assertSame(2, CashTransactionHistory::count());
    }

    public function test_replay_of_deleted_entry_does_not_restore_it_or_add_an_audit(): void
    {
        $first = $this->postJson('/api/owner/cash-transactions', $this->payload())->assertCreated()->json('data');
        $this->deleteJson('/api/owner/cash-transactions/'.$first['id'], ['version_token' => $first['version_token']])->assertNoContent();
        $this->postJson('/api/owner/cash-transactions', $this->payload())->assertOk()->assertJsonPath('replayed', true)
            ->assertJsonPath('data.id', $first['id'])->assertJsonPath('data.is_deleted', true)->assertJsonPath('data.revision', 2);
        $this->assertSame(0, CashTransaction::count());
        $this->assertSame(1, CashTransaction::withTrashed()->count());
        $this->assertSame(2, CashTransactionHistory::count());
        $this->getJson('/api/owner/cash-flow/summary')->assertJsonPath('data.entry_count', 0);
    }

    public function test_replay_after_undo_creation_and_restore_follows_current_state_without_recreating(): void
    {
        $entry = $this->postJson('/api/owner/cash-transactions', $this->payload())->assertCreated()->json('data');
        foreach ([true, false] as $deleted) {
            $history = CashTransactionHistory::latest('revision')->firstOrFail();
            $entry = $this->postJson('/api/owner/cash-transactions/'.$entry['id'].'/revert', ['version_token' => $entry['version_token'],
                'history_id' => $history->id, 'reason' => 'Hoàn tác kiểm thử'])->assertOk()->json('data');
            $this->postJson('/api/owner/cash-transactions', $this->payload())->assertOk()->assertJsonPath('replayed', true)
                ->assertJsonPath('data', $entry)->assertJsonPath('data.is_deleted', $deleted);
        }
        $this->assertSame(1, CashCreationRequest::count());
        $this->assertSame(1, CashTransaction::count());
        $this->assertSame(3, CashTransactionHistory::count());
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_keys_are_rejected_before_financial_changes(mixed $key): void
    {
        $this->postJson('/api/owner/cash-transactions', $this->payload(['idempotency_key' => $key]))->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertSame(0, CashTransaction::count());
        $this->assertSame(0, CashCreationRequest::count());
    }

    public static function invalidKeys(): array
    {
        return [[''], [null], ['short'], [str_repeat('a', 65)], ['mã-yêu-cầu'], ['some key 123'], ['.invalid-key'], [['array-key']]];
    }

    public function test_post_requires_key_and_patch_cannot_change_it(): void
    {
        $payload = $this->payload();
        unset($payload['idempotency_key']);
        $this->postJson('/api/owner/cash-transactions', $payload)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $entry = $this->postJson('/api/owner/cash-transactions', $this->payload())->assertCreated()->json('data');
        $this->patchJson('/api/owner/cash-transactions/'.$entry['id'], ['version_token' => $entry['version_token'],
            'amount' => '20', 'idempotency_key' => 'new-key-123'])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertSame(1, CashTransactionHistory::count());
    }

    public function test_key_and_payload_are_not_exposed_in_entry_listing_history_or_ledger(): void
    {
        $entry = $this->postJson('/api/owner/cash-transactions', $this->payload())->assertCreated()->json('data');
        foreach (['/api/owner/cash-transactions', '/api/owner/cash-transactions/'.$entry['id'],
            '/api/owner/cash-transactions/'.$entry['id'].'/history', '/api/owner/cash-flow'] as $path) {
            $content = $this->getJson($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('idempotency_key', $content);
            $this->assertStringNotContainsString('request_fingerprint', $content);
            $this->assertStringNotContainsString('request_data', $content);
        }
    }

    public function test_audit_failure_rolls_back_request_and_entry_and_retry_can_succeed(): void
    {
        CashTransactionHistory::creating(fn () => throw new \RuntimeException('Simulated audit failure'));
        try {
            $this->postJson('/api/owner/cash-transactions', $this->payload())->assertServerError();
            $this->assertSame(0, CashTransaction::withTrashed()->count());
            $this->assertSame(0, CashTransactionHistory::count());
            $this->assertSame(0, CashCreationRequest::count());
        } finally {
            CashTransactionHistory::flushEventListeners();
        }
        $this->postJson('/api/owner/cash-transactions', $this->payload())->assertCreated();
    }

    public function test_request_insert_failure_rolls_back_entry_and_creation_audit(): void
    {
        CashCreationRequest::creating(fn () => throw new \RuntimeException('Simulated request persistence failure'));
        try {
            $this->postJson('/api/owner/cash-transactions', $this->payload())->assertServerError();
            $this->assertSame(0, CashTransaction::withTrashed()->count());
            $this->assertSame(0, CashTransactionHistory::count());
            $this->assertSame(0, CashCreationRequest::count());
        } finally {
            CashCreationRequest::flushEventListeners();
        }
        $this->postJson('/api/owner/cash-transactions', $this->payload())->assertCreated();
    }

    public function test_outer_transaction_rollback_removes_all_creation_artifacts(): void
    {
        DB::beginTransaction();
        $result = app(CashBook::class)->record($this->payload(), $this->owner->id);
        $this->assertFalse($result['replayed']);
        DB::rollBack();
        $this->assertSame(0, CashTransaction::withTrashed()->count());
        $this->assertSame(0, CashTransactionHistory::count());
        $this->assertSame(0, CashCreationRequest::count());
    }

    public function test_legacy_create_signature_and_existing_rows_do_not_need_request_keys(): void
    {
        $payload = $this->payload();
        unset($payload['idempotency_key']);
        $entry = app(CashBook::class)->create($payload, $this->owner->id);
        $this->assertInstanceOf(CashTransaction::class, $entry);
        $this->assertSame(0, CashCreationRequest::count());
        $this->getJson('/api/owner/cash-transactions/'.$entry->id)->assertOk()->assertJsonPath('data.amount', '10.10');
        $this->postJson('/api/owner/cash-transactions', $this->payload())->assertCreated();
        $this->assertSame(2, CashTransaction::count());
    }

    public function test_guest_tenant_and_inactive_owner_cannot_replay_a_valid_key(): void
    {
        $this->postJson('/api/owner/cash-transactions', $this->payload())->assertCreated();
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->postJson('/api/owner/cash-transactions', $this->payload())->assertForbidden();
        $this->owner->update(['is_active' => false]);
        $this->actingAs($this->owner, 'web');
        $this->postJson('/api/owner/cash-transactions', $this->payload())->assertUnauthorized();
        auth('web')->logout();
        $this->postJson('/api/owner/cash-transactions', $this->payload())->assertUnauthorized();
        $this->assertSame(1, CashTransactionHistory::count());
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['idempotency_key' => 'cash-request-123', 'transaction_type' => 'expense', 'amount' => '10.10',
            'occurred_at' => '2026-03-01 10:00:00', 'description' => 'Chi mua khóa'], $changes);
    }
}
