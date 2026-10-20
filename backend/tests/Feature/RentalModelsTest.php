<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RentalModelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These fixtures exist only in the in-memory SQLite test connection.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
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
    }

    public function test_authentication_uses_username_and_password_hash_without_exposing_the_hash(): void
    {
        $user = User::factory()->create(['username' => 'owner_demo', 'password_hash' => 'test-secret']);
        $provider = new EloquentUserProvider(app('hash'), User::class);
        $credentials = ['username' => 'owner_demo', 'password' => 'test-secret'];
        $retrieved = $provider->retrieveByCredentials($credentials);

        $this->assertTrue($retrieved->is($user));
        $this->assertTrue($provider->validateCredentials($retrieved, $credentials));
        $this->assertFalse($provider->validateCredentials($retrieved, ['password' => 'incorrect']));
        $this->assertTrue($retrieved->is_active);
        $this->assertArrayNotHasKey('password_hash', $retrieved->toArray());
        $this->assertArrayNotHasKey('remember_token', $retrieved->toArray());
    }

    public function test_invoices_use_issued_at_and_eager_load_the_issuer(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->unsignedBigInteger('occupancy_id');
            $table->date('billing_month');
            $table->unsignedBigInteger('representative_assignment_id');
            $table->unsignedBigInteger('issued_by');
            $table->dateTime('issued_at');
        });

        $issuer = User::factory()->create();
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'occupancy_id' => 1,
            'billing_month' => '2026-10-01',
            'representative_assignment_id' => 1,
            'issued_by' => $issuer->id,
        ]);
        $issuedAt = $invoice->issued_at->copy();
        $this->travel(10)->seconds();
        $invoice->update(['invoice_number' => 'INV-TEST-002']);
        $loaded = Invoice::with('issuedBy')->findOrFail($invoice->id);

        $this->assertTrue($loaded->issuedBy->is($issuer));
        $this->assertTrue($loaded->issued_at->equalTo($issuedAt));
        $this->assertSame('2026-10-01', $loaded->billing_month->toDateString());
        $this->assertArrayNotHasKey('created_at', $loaded->getAttributes());
        $this->assertArrayNotHasKey('updated_at', $loaded->getAttributes());
    }

    public function test_maintenance_updates_preserve_submitted_at_and_advance_updated_at(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('occupancy_id');
            $table->unsignedBigInteger('submitted_by');
            $table->unsignedBigInteger('representative_assignment_id');
            $table->string('title');
            $table->text('description');
            $table->string('status');
            $table->text('resolution')->nullable();
            $table->dateTime('submitted_at');
            $table->dateTime('updated_at');
        });

        $request = MaintenanceRequest::create([
            'occupancy_id' => 1,
            'submitted_by' => 1,
            'representative_assignment_id' => 1,
            'title' => 'Leaking tap',
            'description' => 'The bathroom tap is leaking.',
            'status' => 'pending',
        ]);
        $submittedAt = $request->submitted_at->copy();
        $this->travel(10)->seconds();
        $request->update(['status' => 'resolved', 'resolution' => 'Tap replaced.']);
        $request->refresh();

        $this->assertTrue($request->submitted_at->equalTo($submittedAt));
        $this->assertTrue($request->updated_at->greaterThan($submittedAt));
    }
}
