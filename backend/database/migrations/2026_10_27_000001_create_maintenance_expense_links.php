<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_expense_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_request_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('revision')->default(1);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->index(['maintenance_request_id', 'is_active'], 'maintenance_expense_report_active');
        });
        Schema::create('maintenance_expense_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_expense_link_id');
            $table->foreign('maintenance_expense_link_id', 'maintenance_expense_history_link_fk')->references('id')->on('maintenance_expense_links')->restrictOnDelete();
            $table->foreignId('maintenance_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_transaction_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('revision');
            $table->string('action', 20);
            $table->json('before_data')->nullable();
            $table->json('after_data');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->string('actor_username', 80);
            $table->string('actor_role', 20);
            $table->string('reason', 1000);
            $key = $table->string('idempotency_key', 64);
            if (DB::getDriverName() === 'mysql') {
                $key->charset('ascii')->collation('ascii_bin');
            }
            $table->unique('idempotency_key');
            $table->string('request_fingerprint', 64);
            $table->dateTime('created_at');
            $table->unique(['maintenance_expense_link_id', 'revision'], 'maintenance_expense_history_revision');
            $table->index(['maintenance_request_id', 'id'], 'maintenance_expense_history_report');
        });
    }

    public function down(): void
    {
        if (DB::table('maintenance_expense_histories')->exists() || DB::table('maintenance_expense_links')->exists()) {
            throw new RuntimeException('Cannot discard recorded maintenance expense links or history.');
        }
        Schema::dropIfExists('maintenance_expense_histories');
        Schema::dropIfExists('maintenance_expense_links');
    }
};
