<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\CashTransactionHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCashBookSchema;
use Tests\TestCase;

class CashTransactionHistoryTest extends TestCase
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

    public function test_history_records_exact_before_after_original_creator_and_actual_editor(): void
    {
        $created = $this->createEntry();
        $id = $created['id'];
        $editor = User::factory()->create();
        $this->actingAs($editor, 'web');
        $this->patchJson('/api/owner/cash-transactions/'.$id, ['version_token' => $created['version_token'], 'amount' => '12.34', 'reason' => 'Sửa số tiền nhập nhầm'])
            ->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.recorded_by', $this->owner->id);
        $history = $this->getJson('/api/owner/cash-transactions/'.$id.'/history')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'updated')->assertJsonPath('data.0.before.amount', '10.10')
            ->assertJsonPath('data.0.after.amount', '12.34')->assertJsonPath('data.0.performed_by', $editor->id)
            ->assertJsonPath('data.1.performed_by', $this->owner->id)->assertJsonPath('current.amount', '12.34');
        $this->assertSame($created['recorded_at'], $history->json('data.0.after.recorded_at'));
        $this->patchJson('/api/owner/cash-transactions/'.$id.'/history/1', [])->assertNotFound();
    }

    public function test_revert_update_restores_ledger_and_has_a_new_token_even_at_same_timestamp(): void
    {
        $created = $this->createEntry();
        $id = $created['id'];
        $updated = $this->patchJson('/api/owner/cash-transactions/'.$id, ['version_token' => $created['version_token'], 'amount' => '50'])->assertOk()->json('data');
        $history = $this->getJson('/api/owner/cash-transactions/'.$id.'/history')->json('data.0');
        $this->postJson('/api/owner/cash-transactions/'.$id.'/revert', ['history_id' => $history['id'], 'version_token' => $updated['version_token'], 'reason' => 'Hoàn tác sửa nhầm'])
            ->assertOk()->assertJsonPath('data.amount', '10.10')->assertJsonPath('data.revision', 3);
        $this->patchJson('/api/owner/cash-transactions/'.$id, ['version_token' => $created['version_token'], 'amount' => '99'])->assertConflict();
        $this->getJson('/api/owner/cash-flow/summary')->assertJsonPath('data.expense_total', '10.10');
        $this->getJson('/api/owner/cash-transactions/'.$id.'/history')->assertJsonCount(3, 'data')->assertJsonPath('data.0.action', 'reverted')
            ->assertJsonPath('data.0.reverted_history_id', $history['id']);
    }

    public function test_soft_delete_can_be_reverted_and_never_counts_in_cash_flow_while_deleted(): void
    {
        $entry = $this->createEntry();
        $id = $entry['id'];
        $this->deleteJson('/api/owner/cash-transactions/'.$id, ['version_token' => $entry['version_token'], 'reason' => 'Nhập thừa'])->assertNoContent();
        $this->getJson('/api/owner/cash-transactions/'.$id)->assertNotFound();
        $this->assertSame(0, CashTransaction::count());
        $this->assertSame(1, DB::table('cash_transactions')->count());
        $this->getJson('/api/owner/cash-flow/summary')->assertJsonPath('data.entry_count', 0);
        $this->getJson('/api/owner/cash-transactions?state=deleted')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_deleted', true);
        $history = $this->getJson('/api/owner/cash-transactions/'.$id.'/history')->assertOk()->json();
        $this->postJson('/api/owner/cash-transactions/'.$id.'/revert', ['history_id' => $history['data'][0]['id'],
            'version_token' => $history['current']['version_token'], 'reason' => 'Khôi phục khoản bị xóa nhầm'])->assertOk()->assertJsonPath('data.is_deleted', false);
        $this->getJson('/api/owner/cash-flow/summary')->assertJsonPath('data.expense_total', '10.10');
        $this->assertSame(3, CashTransactionHistory::count());
    }

    public function test_undo_creation_and_undo_that_revert_are_both_recorded(): void
    {
        $entry = $this->createEntry();
        for ($i = 0; $i < 2; $i++) {
            $history = $this->getJson('/api/owner/cash-transactions/'.$entry['id'].'/history')->json();
            $this->postJson('/api/owner/cash-transactions/'.$entry['id'].'/revert', ['history_id' => $history['data'][0]['id'],
                'version_token' => $history['current']['version_token'], 'reason' => 'Thử hoàn tác'])->assertOk()->assertJsonPath('data.is_deleted', $i === 0);
        }
        $this->assertSame(1, CashTransaction::count());
        $this->assertSame(3, CashTransactionHistory::count());
    }

    public function test_stale_foreign_and_non_latest_history_cannot_overwrite_later_edits(): void
    {
        $entry = $this->createEntry();
        $first = CashTransactionHistory::first();
        $new = $this->patchJson('/api/owner/cash-transactions/'.$entry['id'], ['version_token' => $entry['version_token'], 'amount' => '20'])->json('data');
        $this->postJson('/api/owner/cash-transactions/'.$entry['id'].'/revert', ['history_id' => $first->id,
            'version_token' => $new['version_token'], 'reason' => 'Cũ'])->assertConflict();
        $latest = CashTransactionHistory::orderByDesc('id')->first();
        $this->postJson('/api/owner/cash-transactions/'.$entry['id'].'/revert', ['history_id' => $latest->id,
            'version_token' => $entry['version_token'], 'reason' => 'Phiên cũ'])->assertConflict();
        $foreign = $this->createEntry();
        $this->postJson('/api/owner/cash-transactions/'.$foreign['id'].'/revert', ['history_id' => $latest->id,
            'version_token' => $foreign['version_token'], 'reason' => 'Sai khoản'])->assertConflict();
        $this->assertSame('20.00', CashTransaction::find($entry['id'])->amount);
    }

    public function test_legacy_record_without_prior_history_can_be_edited_and_reverted(): void
    {
        $entry = CashTransaction::create(['transaction_type' => 'expense', 'amount' => '25', 'occurred_at' => '2026-03-01', 'description' => 'Dữ liệu cũ', 'recorded_by' => $this->owner->id])->refresh();
        $token = $this->getJson('/api/owner/cash-transactions/'.$entry->id)->json('data.version_token');
        $new = $this->patchJson('/api/owner/cash-transactions/'.$entry->id, ['amount' => '35', 'version_token' => $token])->json('data');
        $this->postJson('/api/owner/cash-transactions/'.$entry->id.'/revert', ['history_id' => CashTransactionHistory::first()->id,
            'version_token' => $new['version_token'], 'reason' => 'Hoàn tác'])->assertOk()->assertJsonPath('data.amount', '25.00');
    }

    public function test_audit_insert_failure_rolls_back_financial_changes(): void
    {
        $entry = $this->createEntry();
        CashTransactionHistory::creating(fn () => throw new \RuntimeException('Simulated audit failure'));
        try {
            $this->patchJson('/api/owner/cash-transactions/'.$entry['id'], ['version_token' => $entry['version_token'], 'amount' => '99'])->assertServerError();
            $this->assertSame('10.10', CashTransaction::find($entry['id'])->amount);
            $this->assertSame(1, CashTransactionHistory::count());
        } finally {
            CashTransactionHistory::flushEventListeners();
        }
    }

    public function test_only_owner_can_view_deleted_history_or_revert_and_csrf_is_required(): void
    {
        $entry = $this->createEntry();
        $payload = ['history_id' => CashTransactionHistory::first()->id, 'version_token' => $entry['version_token'], 'reason' => 'Hoàn tác'];
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->getJson('/api/owner/cash-transactions/'.$entry['id'].'/history')->assertForbidden();
        $this->postJson('/api/owner/cash-transactions/'.$entry['id'].'/revert', $payload)->assertForbidden();
        $this->actingAs($this->owner, 'web');
        $this->postJson('/api/owner/cash-transactions/'.$entry['id'].'/revert', [])->assertUnprocessable();
        $this->app->instance('env', 'local');
        $this->postJson('/api/owner/cash-transactions/'.$entry['id'].'/revert', $payload)->assertStatus(419);
    }

    private function createEntry(): array
    {
        return $this->postJson('/api/owner/cash-transactions', ['idempotency_key' => (string) Str::uuid(), 'transaction_type' => 'expense', 'amount' => '10.10',
            'occurred_at' => '2026-03-01 10:00:00', 'description' => 'Chi sửa chữa'])->assertCreated()->json('data');
    }
}
